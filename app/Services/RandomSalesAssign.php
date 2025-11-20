<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{
    DFUS,
    MasterPelanggan,
    MasterKaryawan,
    HistoryPerubahanSales,
    QuotationKontrakH,
    QuotationNonKontrak,
};

class RandomSalesAssign
{
    private static $salesIdInactive;
    private static $salesIdNew;
    private static $salesPoolIndex = 0;

    public static function run($type = 'check')
    {
        try {
            Log::channel('reassign_customer')->info("\n\n\n== RANDOM SALES ASSIGNER STARTED ==\n\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);

            self::$salesIdInactive = MasterKaryawan::select('id', 'nama_lengkap')
                ->where('is_active', false)
                ->whereIn('id_jabatan', [24]) // sales staff
                ->get()
                ->shuffle()
                ->toArray();
            
            Log::channel('reassign_customer')->info("Finish Collect Inactive Sales ID with total data " . count(self::$salesIdInactive) . "\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);

            self::$salesIdNew = MasterKaryawan::select('id', 'nama_lengkap')
                ->where('is_active', true)
                ->whereIn('id_jabatan', [24]) // sales staff
                ->whereDate('tgl_mulai_kerja', '>=', Carbon::now()->subYear()) // hanya 1 tahun terakhir
                ->get()
                ->shuffle()
                ->toArray();

            Log::channel('reassign_customer')->info("Finish Collect New Sales ID | total data " . count(self::$salesIdNew) . "\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);

            // dump('Sales Inactive', self::$salesIdInactive, 'Sales New', self::$salesIdNew);

            $excludedSalesIds = MasterKaryawan::whereIn('id_jabatan', [15, 21]) // manajer, spv
                ->pluck('id')
                ->toArray();
            $excludedSalesIds[] = 41; // Novva Novita Ayu Putri Rukmana

            $customerWithDeactiveSales = MasterPelanggan::with([
                'latestOrder:id,no_order,tanggal_order,id_pelanggan',
                'currentSales:id,nama_lengkap',
                'historySales',
            ])
                ->select('id', 'id_pelanggan', 'nama_pelanggan', 'sales_id', 'sales_penanggung_jawab', 'is_active')
                ->where('is_active', true)
                ->whereNotIn('sales_id', $excludedSalesIds)
                ->whereIn('sales_id', array_column(self::$salesIdInactive, 'id'))
                ->get();
            // dump(count($customerWithDeactiveSales));

            Log::channel('reassign_customer')->info("Finish Collect data Pelanggan with Deactive Sales | total data " . count(self::$salesIdNew) . "\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);

            $allID = $customerWithDeactiveSales->pluck('id')->toArray();

            // dump(count($allID));

            $AssignToSalesExecutive = [];
            $AssignToSalesNew = [];
            $AssignToSalesNewByCheckingAll = [];
            $NotReAssign = [];

            foreach ($customerWithDeactiveSales as $customer) {
                if ($customer->latestOrder) {
                    $customer->reAssignReason = 'Sales Penanggung Jawab lama sudah tidak aktif, dan ada data Order';
                    $AssignToSalesExecutive[] = $customer;
                } else {
                    $customer->reAssignReason = 'Sales Penanggung Jawab lama sudah tidak aktif, dan tidak ada data Order';
                    $AssignToSalesNew[] = $customer;
                }
            }

            Log::channel('reassign_customer')->info("Finish Progress sorting data Pelanggan from old Sales \n | Assign To Sales Executive " . count($AssignToSalesExecutive) . "\n | Assign To Sales New " . count($AssignToSalesNew) . "\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);

            // dump(count($AssignToSalesExecutive), count($AssignToSalesNew));

            // dump('customerWithDeactiveSales', count($customerWithDeactiveSales));

            $customerSpecial = MasterPelanggan::with([
                'latestOrder:id,no_order,tanggal_order,id_pelanggan',
                'currentSales:id,nama_lengkap',
                'historySales',
            ])
                ->select('id', 'id_pelanggan', 'nama_pelanggan', 'sales_id', 'sales_penanggung_jawab', 'is_active')
                ->where('is_active', true)
                ->whereNotIn('id', $allID)
                ->whereIn('sales_id', $excludedSalesIds)
                ->get();

            // dump('customerSpecial', count($customerSpecial));
            $chunkIndex = 1;

            $customerMustBeReassigned = MasterPelanggan::with([
                'latestOrder:id,no_order,tanggal_order,id_pelanggan',
                'currentSales:id,nama_lengkap',
                'historySales',
                'latestNonKontrakQuotation:id,pelanggan_ID,no_document,created_at,updated_at',
                'latestKontrakQuotation:id,pelanggan_ID,no_document,created_at,updated_at,periode_kontrak_akhir',
                'latestDFUS:id,id_pelanggan,sales_penanggung_jawab,tanggal',
            ])
                ->select(
                    'id',
                    'id_pelanggan',
                    'nama_pelanggan',
                    'sales_id',
                    'sales_penanggung_jawab',
                    'is_active'
                )
                ->where('is_active', true)
                ->whereNotIn('id', $allID)
                ->whereNotIn('sales_id', $excludedSalesIds)
                ->chunk(2000, function ($customers) use (&$NotReAssign, &$AssignToSalesNewByCheckingAll, &$chunkIndex) {
                    Log::channel('reassign_customer')->info("== Processing Chunk #{$chunkIndex} ==", [
                        'timestamp' => Carbon::now()->toDateTimeString(),
                        'customer_count' => $customers->count(),
                    ]);
                    foreach ($customers as $customer) {

                        // 1. Cek history rotasi 3 bulan
                        if (
                            $customer->historySales
                            && Carbon::parse($customer->historySales->tanggal_rotasi)->lt(Carbon::now()->subMonths(3))
                        ) {
                            $NotReAssign[] = $customer;
                            continue;
                        }

                        // 2. Penawaran terakhir > 6 bulan
                        $shouldReassignByQuotation = function () use ($customer) {
                            $checkQuotation = self::latestQuot(
                                $customer->latestKontrakQuotation,
                                $customer->latestNonKontrakQuotation,
                                $customer->id
                            );

                            return $checkQuotation
                                && Carbon::parse($checkQuotation)->lt(Carbon::now()->subMonths(6));
                        };

                        $shouldReassignByDFUS = function () use ($customer) {
                            $dfus = $customer->latestDFUSMatch;
                            return $dfus && Carbon::parse($dfus->tanggal)->lt(Carbon::now()->subWeek());
                        };

                        // 3. Jika punya order
                        if ($customer->latestOrder) {

                            // Order > 8 bulan?
                            if (Carbon::parse($customer->latestOrder->tanggal_order)->lt(Carbon::now()->subMonths(8))) {

                                if ($shouldReassignByQuotation()) {
                                    $customer->reAssignReason = 'Order lebih dari 8 bulan dan penawaran terakhir lebih dari 6 bulan';
                                    $AssignToSalesNewByCheckingAll[] = $customer;
                                } elseif ($shouldReassignByDFUS()) {
                                    $customer->reAssignReason = 'Order lebih dari 8 bulan dan follow up sales terakhir lebih dari 1 minggu';
                                    $AssignToSalesNewByCheckingAll[] = $customer;
                                } else {
                                    $customer->reAssignReason = 'Order lebih dari 8 bulan';
                                    $AssignToSalesNewByCheckingAll[] = $customer;
                                }
                            } else {
                                $NotReAssign[] = $customer;
                            }

                            continue;
                        }

                        // 4. Tidak punya order
                        if ($shouldReassignByQuotation()) {
                            $customer->reAssignReason = 'Penawaran terakhir lebih dari 6 bulan';
                            $AssignToSalesNewByCheckingAll[] = $customer;
                        } elseif ($shouldReassignByDFUS()) {
                            $customer->reAssignReason = 'Follow up sales terakhir lebih dari 1 minggu';
                            $AssignToSalesNewByCheckingAll[] = $customer;
                        } else {
                            $NotReAssign[] = $customer;
                        }
                    }
                    $chunkIndex++;
                });
            $selectedData = collect($AssignToSalesNewByCheckingAll);
            $firstMatch = $selectedData->first(function ($customer) {
                return $customer->latestOrder
                    && ($customer->latestNonKontrakQuotation
                        || $customer->latestKontrakQuotation);
            });
            // dump('customerMustBeReassigned', count($AssignToSalesNewByCheckingAll));
            // dump('customerNotReassigned', count($NotReAssign));
            // dump('Data pertama yang punya ketiganya:', $firstMatch ? $firstMatch->toArray() : 'Ga ada');
            Log::channel('reassign_customer')->info("Finish Progress sorting All data Pelanggan \n | Assign To New Sales " . count($AssignToSalesNewByCheckingAll) . "\n | Not Re-Assign To Sales New " . count($NotReAssign) . "\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);
            Log::channel('reassign_customer')->info("\n\n== GET DATA TO REASSIGN FINISHED ==\n\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);


            // dd('----------------------------------------------');

            if($type == 'check'){
                return response()->json([
                    'status' => 'success',
                    'message' => 'Success get data to reassign.',
                    'total new sales' => count(self::$salesIdNew),
                    'data from old sales to new sales' => count($AssignToSalesNew),
                    'data from old sales to sales executive' => count($AssignToSalesExecutive),
                    'data reassign to new sales' => count($AssignToSalesNewByCheckingAll),
                    'data not reassign' => count($NotReAssign),
                ]);
            } else if($type == 'reassign'){
                // Process re assign
            }

        } catch (\Throwable $th) {
            dd($th);
            Log::channel('reassign_customer')->error('error', [$th->getMessage(), $th->getLine(), $th->getFile()]);
            throw $th;
        }
    }

    private static function latestQuot($kontrak, $nonKontrak, $id)
    {
        $latestKontrak = null;
        $latestNonKontrak = null;

        if ($kontrak && $kontrak->periode_kontrak_akhir != null) {
            // formatnya contoh: "05-2024"
            $periodeAkhir = Carbon::createFromFormat('m-Y', $kontrak->periode_kontrak_akhir)->endOfMonth();

            // kalau periode akhir sudah lewat bulan sekarang
            if ($periodeAkhir->lt(Carbon::now()->startOfMonth())) {
                $latestKontrak = $kontrak->updated_at ?? $kontrak->created_at;
            }
        }

        if ($nonKontrak) {
            $latestNonKontrak = $nonKontrak->updated_at ?? $nonKontrak->created_at;
        }

        // kalau dua-duanya null, ya null aja
        if (!$latestNonKontrak && !$latestKontrak) {
            return null;
        }

        // kalau salah satu null, ambil yang gak null
        if (!$latestNonKontrak) return $latestKontrak;
        if (!$latestKontrak) return $latestNonKontrak;

        // ambil yang paling baru (tertinggi)
        return Carbon::parse($latestNonKontrak)->gt(Carbon::parse($latestKontrak))
            ? $latestNonKontrak
            : $latestKontrak;
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

        $newSales = null;
        $poolSize = count(self::$salesPool);
        $initialIndex = self::$salesPoolIndex;

        do {
            $potentialSales = self::$salesPool[self::$salesPoolIndex];

            self::$salesPoolIndex = (self::$salesPoolIndex + 1) % $poolSize;

            if (!in_array($potentialSales['id'], $excludedSalesIds)) {
                $newSales = (object) $potentialSales;
                break;
            }
        } while (self::$salesPoolIndex !== $initialIndex);

        if ($newSales) {
            DB::beginTransaction();
            try {
                $historySales = new HistoryPerubahanSales();
                $historySales->id_pelanggan = $customer->id_pelanggan;
                $historySales->id_sales_lama = $currentSales ? $currentSales->id : null;
                $historySales->id_sales_baru = $newSales->id;
                $historySales->tanggal_rotasi = Carbon::now();
                $historySales->save();

                MasterPelanggan::where('id_pelanggan', $customer->id_pelanggan)->update([
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
