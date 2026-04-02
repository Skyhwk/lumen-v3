<?php

namespace App\Services;

use App\Models\{QuotationNonKontrak, RequestQr, DFUS};
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\{Jadwal, SamplingPlan, PersiapanSampelDetail, PersiapanSampelHeader};
use Mpdf;
use App\Services\Crypto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QtTransactionNonKontrakService
{
    public static $todayDate;
    public function run(){
        try {
            self::$todayDate = Carbon::now()->format('Y-m-d H:i:s');
            $minQtDate = Carbon::now()->subDays(10);
            // $minQtDate = Carbon::parse('2026-03-01 00:00:00');

            $data = $this->trackQr($minQtDate);
            $this->insertData(collect($data));

            return $data;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    private function trackQr($minDate)
    {
        // ===================== PRELOAD DATA =====================

        // QR
        $qrMapped = RequestQr::where('tipe', 'non_kontrak')
            ->where('is_active', 0)
            ->where('created_at', '>=', $minDate)
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy(function ($item) {
                $qrKey = $item->created_by . '_' . $item->nama_pelanggan;
                if(isset($item->id_quotation)){
                    $qrKey .= '_' . $item->id_quotation;
                }
                return $qrKey;
            });

        // DFUS (ambil sekali saja)
        $dfusMapped = DFUS::where('created_at', '>=', $minDate)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('id_pelanggan');

        // Sampling
        $samplingMapped = SamplingPlan::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('quotation_id');

        // Jadwal
        $jadwalMapped = Jadwal::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('id_sampling');

        // Order
        $orderMapped = OrderHeader::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('no_document');

        // Order Detail
        $orderDetailMapped = OrderDetail::select('no_order', 'cfr', 'no_sampel', 'approved_at','status', 'created_at', 'tanggal_sampling')
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('no_order');

        // QT
        $qtNonKontrak = QuotationNonKontrak::with('sales')
            ->where('created_at', '>=', $minDate)
            ->where('is_active', 1)
            // ->where('flag_status', 'ordered')
            ->get();

        $dataToInsert = [];

        // ===================== LOOP UTAMA =====================
        foreach ($qtNonKontrak as $item) {
            $processedData = [];

            $qtKey1 = $item->sales->nama_lengkap . '_' . $item->nama_perusahaan . '_' . $item->id;
            $qtKey2 = $item->sales->nama_lengkap . '_' . $item->nama_perusahaan;

            // ========================= QR QT =========================
            $matchedQr = null;

            if (isset($qrMapped[$qtKey1])) {
                $matchedQr = $qrMapped[$qtKey1]
                    ->first();
            }else if(isset($qrMapped[$qtKey2])){
                $matchedQr = $qrMapped[$qtKey2]
                    ->first(function ($qr) use ($item) {
                        return $qr->created_at < $item->created_at;
                    });
            }

            $durationQr = $matchedQr
                ? $this->getDuration($matchedQr->created_at, $item->created_at)
                : 'No Data';

            $processedData['rekap_transactions'][] = (object)[
                'name' => 'qr-qt',
                'duration' => $durationQr,
                'detail' => null
            ];

            // ========================= DFUS =========================
            $dfus = null;

            if (isset($dfusMapped[$item->pelanggan_ID])) {
                $dfus = $dfusMapped[$item->pelanggan_ID]
                    ->first(function ($d) use ($item) {
                        return $d->created_at <= $item->created_at;
                    });
            }

            $durationDfus = $dfus
                ? $this->getDuration($dfus->created_at, $item->created_at)
                : 'No Data';

            $processedData['rekap_transactions'][] = (object)[
                'name' => 'follow up-keluar qt',
                'duration' => $durationDfus,
                'detail' => null
            ];

            // ========================== SP ==========================
            $sampling = null;
            $jadwal = null;

            if (isset($samplingMapped[$item->id])) {
                $sampling = $samplingMapped[$item->id]
                    ->sortBy('tanggal_jadwal')
                    ->first();

                if (isset($jadwalMapped[$sampling->id])) {
                    $jadwal = $jadwalMapped[$sampling->id]
                        ->sortBy('tanggal')
                        ->first();
                }
            }

            $durationSampling = $jadwal
                ? $this->getDuration($item->created_at, $jadwal->created_at)
                : 'No Data';

            $processedData['rekap_transactions'][] = (object)[
                'name' => 'keluar qt-sp',
                'tgl' => $jadwal ? $jadwal->tanggal : null,
                'duration' => $durationSampling,
                'detail' => null
            ];

            // ========================= ORDER =========================
            $order = null;

            if (isset($orderMapped[$item->no_document])) {
                $order = $orderMapped[$item->no_document]
                    ->first();
            }

            $durationOrder = $order && $jadwal
                ? $this->getDuration($jadwal->created_at, $order->created_at)
                : 'No Data';

            $processedData['rekap_transactions'][] = (object)[
                'name' => 'sp-qs',
                'tgl' => $order ? $order->tanggal_order : null,
                'duration' => $durationOrder,
                'detail' => null
            ];

            // ========================= SAMPLING =========================
            $jadwalCollect = $sampling && isset($jadwalMapped[$sampling->id])
                ? $jadwalMapped[$sampling->id]->sortBy('created_at')
                : collect();

            $result = [];

            if ($jadwalCollect->isNotEmpty()) {
                $result = $jadwalCollect
                    ->flatMap(function ($item) {

                        $date = Carbon::parse($item->tanggal . ' ' . $item->jam)->format('Y-m-d H:i:s');

                        $kategoriList = is_array(json_decode($item->kategori, true)) ? $item->kategori : [];

                        return collect($kategoriList)->map(function ($kategori) use ($date) {
                            $parts = explode(' - ', $kategori);
                            $kode = \preg_filter('/[^0-9]/', '', $parts[1]) ?? null;

                            return $kode ? $kode . '_' . $date : null;
                        });
                    })
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
            }

            $detailSampling = array_map(function ($ds) use ($order) {
                [$sample, $date] = explode('_', $ds);
                $durationSample = $order ? $this->getDuration($order->tanggal_order, $date) : null;
                return (object) [
                    'no sample' => $sample,
                    'tanggal' => explode(' ',$date)[0],
                    'duration' => $durationSample
                ];
            }, $result);
            // if($item->no_document == 'ISL/QT/26-III/005186') dd($result, $jadwalCollect, $sampling);

            $durationOrderSampling = $order && $jadwal
                ? $this->getDuration($order->tanggal_order, $jadwal->tanggal)
                : 'No Data';

            $processedData['rekap_transactions'][] = (object)[
                'name' => 'qs-sampling',
                'tgl' => null,
                'duration' => $durationOrderSampling,
                'detail' => $detailSampling
            ];

            // ========================= LHP RELEASE =========================
            $orderDetail = [];

            if ($order && isset($orderDetailMapped[$order->no_order])) {
                $orderDetail = $orderDetailMapped[$order->no_order]
                    ->sortBy('no_sampel')
                    ->groupBy('cfr')
                    ->map(function ($od) {
                        return $od->first();
                    })
                    ->values()
                    ->toArray();
            }

            $approvedAt = null;

            $detailLhp = array_map(function ($ds) {
                $parts = explode('/', $ds['cfr'] ?? '');
                $noCfr = $parts[1] ?? null;

                $approvedAt = $ds['approved_at'] ?? null;

                $durationLhp = $approvedAt
                    ? $this->getDuration($ds['tanggal_sampling'], $approvedAt)
                    : 'No Data';

                return (object) [
                    'no lhp' => $noCfr,
                    'tanggal' => $ds['status'] == 3 && $approvedAt
                        ? Carbon::parse($approvedAt)->format('Y-m-d')
                        : null,
                    'duration' => $durationLhp
                ];
            }, $orderDetail);

            $maxLhpRelease = collect($detailLhp)
                ->pluck('tanggal')
                ->filter()
                ->max() ?? null;

            $processedData['rekap_transactions'][] = (object)[
                'name' => 'sampling-lhp',
                'duration' => count($orderDetail) > 0 && $approvedAt ? $this->getDuration($order->tanggal_order, $maxLhpRelease) : 'No Data',
                'detail' => $detailLhp
            ];

            $processedData['uuid'] = (new Crypto())->encrypt($item->no_quotation . '|' . $item->sales->nama_lengkap);
            $processedData['id_pelanggan'] = $item->pelanggan_ID;
            $processedData['nama_pelanggan'] = $item->nama_perusahaan;
            $processedData['no_qt'] = $item->no_document;
            $processedData['rekap_transactions'] = json_encode($processedData['rekap_transactions']);
            $processedData['sales_id'] = $item->sales_id;
            $processedData['created_at'] = self::$todayDate;

            array_push($dataToInsert, $processedData);
        }

        return $dataToInsert;
    }

    private function insertData($data) {
        DB::beginTransaction();
        try {
            $validIds = $data->pluck('uuid')->unique()->values()->toArray();
            $data->chunk(200)->each(function ($chunk) {
                DB::table('qt_transaction_non_kontrak')->upsert(
                    $chunk->toArray(), ['uuid', 'created_at'], [
                        'id_pelanggan',
                        'nama_pelanggan',
                        'no_qt',
                        'rekap_transactions',
                        'sales_id'
                    ]
                );
            });
            if (!empty($validIds)) {
                DB::table('qt_transaction_non_kontrak')
                    ->whereNotIn('uuid', $validIds)
                    ->delete();
            }

            DB::table('qt_transaction_non_kontrak')->where('created_at', '!=', self::$todayDate)->update([
                'updated_at' => self::$todayDate
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function getDuration($startDate, $endDate) {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $minutes = $start->diffInMinutes($end);
        $hours = $start->diffInHours($end);
        $days = $start->diffInDays($end);

        if ($minutes < 60) {
            $duration = $minutes . ' menit';
        } elseif ($hours < 24) {
            $duration = $hours . ' jam';
        } else {
            $duration = $days . ' hari';
        }

        return $duration;
    }
}
