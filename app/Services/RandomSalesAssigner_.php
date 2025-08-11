<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

use App\Models\{
    MasterPelanggan,
    MasterKaryawan,
    OrderHeader,
    QuotationKontrakH,
    QuotationNonKontrak,
    HistoryPerubahanSales,
    Dfus,
};

Carbon::setLocale('id');

class RandomSalesAssigner
{
    public function run()
    {
        try {
            $customers = MasterPelanggan::where('is_active', true)->orderBy('id')->get();

            foreach ($customers as $customer) {
                $oldSales = MasterKaryawan::select('id', 'nama_lengkap')->find($customer->sales_id);

                $history = HistoryPerubahanSales::where('id_pelanggan', $customer->id_pelanggan)
                    ->where('id_sales_baru', $customer->sales_id)
                    ->orderByDesc('tanggal_rotasi')
                    ->first();

                if ($history) {
                    $dfus = Dfus::where('id_pelanggan', $customer->id_pelanggan)
                        ->where('sales_penanggung_jawab', $oldSales->nama_lengkap)
                        ->latest()
                        ->first();

                    if ($dfus) {
                        $latestOrder = OrderHeader::where([
                            'id_pelanggan' => $customer->id_pelanggan,
                            'is_active' => true
                        ])->orderByDesc('tanggal_order')->first();

                        if ($latestOrder) {
                            $lastOrderDate = Carbon::parse($latestOrder->tanggal_order);
                            if ($lastOrderDate->lte(Carbon::now()->subMonths(6))) {
                                $dataNonKontrak = QuotationNonKontrak::where('pelanggan_ID', $customer->id_pelanggan)
                                    ->where('is_active', true)
                                    ->select('pelanggan_ID', 'tanggal_penawaran', 'no_document', 'sales_id')
                                    ->orderByDesc('tanggal_penawaran')->limit(1)->get();

                                $dataKontrak = QuotationKontrakH::where('pelanggan_ID', $customer->id_pelanggan)
                                    ->where('is_active', true)
                                    ->select('pelanggan_ID', 'tanggal_penawaran', 'no_document', 'sales_id')
                                    ->orderByDesc('tanggal_penawaran')->limit(1)->get();

                                $latestQuotation = $dataNonKontrak->merge($dataKontrak)->sortByDesc('tanggal_penawaran')->first();

                                if ($latestQuotation) {
                                    $lastQuotationDate = Carbon::parse($latestQuotation->tanggal_penawaran);
                                    if ($lastQuotationDate->lte(Carbon::now()->subMonths(2))) {
                                        $this->assignNewSales($customer, $oldSales);
                                        continue;
                                    }
                                }
                            };
                        }
                    } else {
                        if (Carbon::parse($history->tanggal_rotasi)->lte(Carbon::now()->subDays(7))) {
                            $this->assignNewSales($customer, $oldSales);
                            continue;
                        }
                    }
                } else {
                    $latestOrder = OrderHeader::where([
                        'id_pelanggan' => $customer->id_pelanggan,
                        'is_active' => true
                    ])->orderByDesc('tanggal_order')->first();

                    if ($latestOrder) {
                        $lastOrderDate = Carbon::parse($latestOrder->tanggal_order);
                        if ($lastOrderDate->lte(Carbon::now()->subYear())) {
                            $dataNonKontrak = QuotationNonKontrak::where('pelanggan_ID', $customer->id_pelanggan)
                                ->where('is_active', true)
                                ->select('pelanggan_ID', 'tanggal_penawaran', 'no_document', 'sales_id')
                                ->orderByDesc('tanggal_penawaran')->limit(1)->get();

                            $dataKontrak = QuotationKontrakH::where('pelanggan_ID', $customer->id_pelanggan)
                                ->where('is_active', true)
                                ->select('pelanggan_ID', 'tanggal_penawaran', 'no_document', 'sales_id')
                                ->orderByDesc('tanggal_penawaran')->limit(1)->get();

                            $latestQuotation = $dataNonKontrak->merge($dataKontrak)->sortByDesc('tanggal_penawaran')->first();

                            if ($latestQuotation) {
                                $lastQuotationDate = Carbon::parse($latestQuotation->tanggal_penawaran);
                                if ($lastQuotationDate->lte(Carbon::now()->subMonths(8))) {
                                    $this->assignNewSales($customer, $oldSales);
                                    continue;
                                }
                            }
                        };
                    }
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    private function assignNewSales($customer, $oldSales)
    {
        $excludedSalesIds = collect([$customer->sales_id])
            ->merge(
                HistoryPerubahanSales::where('id_pelanggan', $customer->id_pelanggan)->get()
                    ->flatMap(fn($history) => [$history->id_sales_lama, $history->id_sales_baru])
            )
            ->unique()
            ->values()
            ->all();

        $newSales = MasterKaryawan::where('is_active', true)
            ->whereIn('id_jabatan', [21, 24]) // spv sama staff
            ->whereNotIn('id', $excludedSalesIds)
            ->inRandomOrder()
            ->first();

        $this->saveToLog("Perubahan Data : ", ["$customer->nama_pelanggan telah berhasil diubah sales penanggung jawab dari $oldSales->nama_lengkap menjadi $newSales->nama_lengkap"]);

        if ($newSales) {
            $timestamp = Carbon::now();
            // update master pelanggan
            $customer->sales_id = $newSales->id;
            $customer->sales_penanggung_jawab = $newSales->nama_lengkap;
            $customer->save();

            // tambah record di history_perubahan_sales
            $historySales = new HistoryPerubahanSales();
            $historySales->id_pelanggan = $customer->id_pelanggan;
            $historySales->id_sales_lama = $oldSales->id;
            $historySales->id_sales_baru = $newSales->id;
            $historySales->tanggal_rotasi = $timestamp;
            $historySales->save();
        }
    }

    private function saveToLog($title, $data)
    {
        Log::channel('reassign_customer')->info($title, $data);
    }
}
