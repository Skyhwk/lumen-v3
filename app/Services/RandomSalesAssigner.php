<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

use App\Models\{
    MasterPelanggan,
    MasterKaryawan,
    HistoryPerubahanSales,
    QuotationKontrakH,
    QuotationNonKontrak,
};

Carbon::setLocale('id');

class RandomSalesAssigner
{
    public static function run()
    {
        try {
            Log::channel('reassign_customer')->info("\n\n\n== RANDOM SALES ASSIGNER STARTED ==\n\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);

            MasterPelanggan::with([
                'latestOrder:id,no_order,tanggal_order,id_pelanggan',
                'currentSales:id,nama_lengkap',
                'historySales',
            ])
                ->select('id_pelanggan', 'nama_pelanggan', 'sales_id', 'sales_penanggung_jawab', 'is_active')
                ->where('is_active', true)
                ->whereNotIn('sales_id', [14, 22, 41])
                ->chunk(5000, function ($customers) {
                    foreach ($customers as $customer) {
                        if (!$customer->historySales) {
                            self::checkCustomer($customer, 12, 8, 'Sales belum pernah dishuffle; ');
                        } else {
                            $historySales = $customer->historySales;

                            if (!$historySales->latest_dfus) {
                                if (Carbon::parse($historySales->tanggal_rotasi)->lte(Carbon::now()->subDays(7))) {
                                    self::assignNewSales($customer, 'Sales sudah dishuffle, namun belum pernah melakukan Follow Up dalam tempo 7 hari setelah rotasi; ');
                                }
                            } else {
                                if (Carbon::parse($historySales->tanggal_rotasi)->lte(Carbon::now()->subMonths(6))) {
                                    self::checkCustomer($customer, 6, 2, 'Sales sudah dishuffle dan sudah followup; ');
                                }
                            }
                        }
                    }
                });

            Log::channel('reassign_customer')->info("\n\n== RANDOM SALES ASSIGNER FINISHED ==\n\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Random sales assignment completed successfully.',
            ]);
        } catch (\Throwable $th) {
            Log::channel('reassign_customer')->error('error', [$th->getMessage(), $th->getLine(), $th->getFile()]);
            throw $th;
        }
    }

    private static function checkCustomer($customer, $orderThreshold, $quotationThreshold, $info)
    {
        $orderThreshold = Carbon::now()->subMonths($orderThreshold);
        $quotationThreshold = Carbon::now()->subMonths($quotationThreshold);

        [$shouldReassign, $description] = self::checkOrderAndQuotation($customer, $orderThreshold, $quotationThreshold);

        if ($shouldReassign) self::assignNewSales($customer, $info . $description);
    }

    private static function checkOrderAndQuotation($customer, $orderThreshold, $quotationThreshold)
    {
        $info = null;
        $latestOrder = $customer->latestOrder;

        $latestQuotation = collect([
            QuotationKontrakH::select('id', 'no_document', 'pelanggan_ID', 'tanggal_penawaran')->where('pelanggan_ID', $customer->id_pelanggan)->orderByDesc('tanggal_penawaran')->first(),
            QuotationNonKontrak::select('id', 'no_document', 'pelanggan_ID', 'tanggal_penawaran')->where('pelanggan_ID', $customer->id_pelanggan)->orderByDesc('tanggal_penawaran')->first()
        ])->sortByDesc('tanggal_penawaran')->first();

        if (!$latestOrder) {
            $info = 'Customer belum pernah order';

            if ($latestQuotation && Carbon::parse($latestQuotation->tanggal_penawaran)->lte($quotationThreshold)) {
                $info .= ' dan penawaran terakhir lebih dari ' . $quotationThreshold->diffInMonths(Carbon::now()) . ' bulan lalu (' . $latestQuotation->no_document . ', ' . $latestQuotation->tanggal_penawaran . ')';
                return [true, $info];
            }
        } else {
            if (Carbon::parse($latestOrder->tanggal_order)->lte($orderThreshold)) {
                $info = 'Order terakhir lebih dari ' . $orderThreshold->diffInMonths(Carbon::now()) . ' bulan lalu (' . $latestOrder->no_order . ', ' . $latestOrder->tanggal_order . ')';

                if ($latestQuotation && Carbon::parse($latestQuotation->tanggal_penawaran)->lte($quotationThreshold)) {
                    $info .= ' dan penawaran terakhir lebih dari ' . $quotationThreshold->diffInMonths(Carbon::now()) . ' bulan lalu (' . $latestQuotation->no_document . ', ' . $latestQuotation->tanggal_penawaran . ')';
                    return [true, $info];
                }
            }
        }

        return [false, $info];
    }

    private static function assignNewSales($customer, $info)
    {
        $currentSales = $customer->currentSales;

        $excludedSalesIds = collect([$currentSales ? $currentSales->id : null])
            ->merge(
                HistoryPerubahanSales::where('id_pelanggan', $customer->id_pelanggan)->get()
                    ->flatMap(fn($history) => [$history->id_sales_lama, $history->id_sales_baru])
            )
            ->unique()
            ->values()
            ->all();

        $newSales = MasterKaryawan::select('id', 'nama_lengkap', 'id_jabatan', 'is_active')
            ->where('is_active', true)
            ->whereIn('id_jabatan', [24]) // spv sama staff
            ->whereNotIn('id', $excludedSalesIds)
            ->inRandomOrder()
            ->first();

        if ($newSales) {
            DB::beginTransaction();
            try {
                //code...
                $historySales = new HistoryPerubahanSales();
                $historySales->id_pelanggan = $customer->id_pelanggan;
                $historySales->id_sales_lama = $currentSales ? $currentSales->id : null;
                $historySales->id_sales_baru = $newSales->id;
                $historySales->tanggal_rotasi = Carbon::now();
                $historySales->save();
    
                // UPDATE DATABASE
                $updateCustomer = MasterPelanggan::where('id_pelanggan', $customer->id_pelanggan)->update([
                    'sales_id' => $newSales->id,
                    'sales_penanggung_jawab' => $newSales->nama_lengkap,
                ]);
    
                Log::channel('reassign_customer')->info("\n\n== Informasi Perubahan Sales ==\n\n", [
                    'timestamp' => Carbon::now()->toDateTimeString(),
                    'id_pelanggan' => $customer->id_pelanggan,
                    'nama_pelanggan' => $customer->nama_pelanggan,
                    'sales_lama' => $currentSales ? [
                        'id' => $currentSales->id,
                        'nama' => $currentSales->nama_lengkap,
                    ] : null,
                    'sales_baru' => [
                        'id' => $newSales->id,
                        'nama' => $newSales->nama_lengkap,
                    ],
                    'keterangan' => $info,
                ]);

                DB::commit();
            } catch (\Exception $th) {
                DB::rollBack();
                Log::channel('reassign_customer')->error('error', [$th->getMessage(), $th->getLine(), $th->getFile()]);
            }
        }
    }
}
