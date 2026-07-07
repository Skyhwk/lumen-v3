<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\DailyQsd;
use App\Models\TrackingOrder;
use App\Models\Parameter;
use App\Models\WsFinalApprovalDetail;

use Schema;

use Carbon\Carbon;
use DB;

class CheckOrderActive extends Command
{
    protected $signature = 'checkorder';
    protected $description = 'Check order active satu tahun terakhir';
    protected $lhpRelations = [
        'lhps_air' => 'LhpsAirHeader',
        'lhps_emisi' => 'LhpsEmisiHeader',
        'lhps_emisi_c' => 'LhpsEmisiCHeader',
        'lhps_emisi_isokinetik' => 'LhpsEmisiIsokinetikHeader',
        'lhps_getaran' => 'LhpsGetaranHeader',
        'lhps_kebisingan' => 'LhpsKebisinganHeader',
        'lhps_kebisingan_personal' => 'LhpsKebisinganPersonalHeader',
        'lhps_ling' => 'LhpsLingHeader',
        'lhps_medanlm' => 'LhpsMedanlmHeader',
        'lhps_pencahayaan' => 'LhpsPencahayaanHeader',
        'lhps_sinaruv' => 'LhpsSinaruvHeader',
        'lhps_ergonomi' => 'DraftErgonomiFile',
        'lhps_iklim' => 'LhpsIklimHeader',
        'lhps_swab_udara' => 'LhpsSwabTesHeader',
        'lhps_microbiologi' => 'LhpsMicrobiologiHeader',
        'lhps_padatan' => 'LhpsPadatanHeader',
        'lhp_psikologi' => 'LhpUdaraPsikologiHeader',
        'lhps_hygiene_sanitasi' => 'LhpsHygieneSanitasiHeader'
    ];

    public function handle()
    {
        $commandStartedAt = microtime(true);
        $startDate = Carbon::now()->subMonths(6)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $this->info("===== Start Command: CheckOrderActive =====");
        $this->info("Waktu mulai  : " . Carbon::now()->toDateTimeString());
        $this->info("Start Date : " . $startDate);
        $this->info("End Date   : " . $endDate);
        $this->info("Start to get data headers [".Carbon::now()->toDateTimeString()."]");

        $allParameter = Parameter::select('id', 'nama_lab', 'nama_regulasi')->where('is_active', 1)->get()->keyBy('id')->toArray();

        $dataHeaders = OrderHeader::select(
            'id','no_order','id_pelanggan','sales_id',
            'no_document','tanggal_order as tgl_order',
            'nama_perusahaan','alamat_sampling','is_revisi','konsultan', 'tanggal_penawaran'
        )
        ->whereDate('created_at', ">=", $startDate)
        ->whereDate('created_at', "<=", $endDate)
        ->where('is_active', 1)
        ->whereHas('orderDetail')
        ->get();

        if ($dataHeaders->isEmpty()) {
            $this->info("No data headers found.");
            $this->logCommandDuration($commandStartedAt);
            return;
        }

        $noOrderArray = $dataHeaders->pluck('no_order')->toArray();

        $this->info("Finish to get data headers [".Carbon::now()->toDateTimeString()."]");
        $this->info("Total Data Headers : " . $dataHeaders->count());
        $this->info("Start to get data order detail [".Carbon::now()->toDateTimeString()."]");

        $dataOrderDetail = OrderDetail::select(
            'id',
            'id_order_header',
            'no_order',
            'no_sampel',
            'cfr',
            'status',
            'kategori_1',
            'kategori_2',
            'kategori_3',
            'parameter',
            'regulasi',
            'kontrak',
            'keterangan_1',
            'tanggal_terima',
            'tanggal_sampling',
            'periode'
        )
        ->with('TrackingSatu:id,no_sample,ftc_sd,ftc_verifier,ftc_laboratory')
        ->whereIn('id_order_header', $dataHeaders->pluck('id')->toArray())
        ->where('is_active', 1)
        ->get()
        ->groupBy('no_order')
        ->map(fn ($items) => $items->map(fn ($item) => $item->toArray())->all())
        ->toArray();
        
        $this->info("Finish to get data order detail [".Carbon::now()->toDateTimeString()."]");
        $this->info("Total Data Order Detail : " . count($dataOrderDetail));

        $this->info("Start to get data pembayaran [".Carbon::now()->toDateTimeString()."]");
        
        $dataPembayaran = DailyQsd::whereIn('no_order', $noOrderArray)
        ->select(
            'status_customer',
            'no_order',
            'no_invoice',
            'nilai_invoice',
            'nilai_pembayaran',
            'nilai_pengurangan',
            'revenue_invoice',
            'tanggal_pembayaran',
            'no_po',
            'is_lunas',
            'is_point_calculated',
            'is_invoicing',
            'no_quotation',
            'total_cfr',
            'pelanggan_ID',
            'nama_perusahaan',
            'konsultan',
            'periode',
            'kontrak',
            'status_sampling',
            'sales_id',
            'sales_nama',
            'total_discount',
            'total_ppn',
            'total_pph',
            'biaya_akhir',
            'grand_total',
            'total_revenue',
            'tanggal_sampling_min',
            'tanggal_kelompok'
        )
        ->get();

        $this->info("Finish to get data pembayaran [".Carbon::now()->toDateTimeString()."]");
        $this->info("Total Data Pembayaran : " . count($dataPembayaran));


        $this->info("Start to get data lhp [".Carbon::now()->toDateTimeString()."]");

        $dataLhp = $this->fetchLhpDataByOrder($noOrderArray);

        $this->info("Total Data Lhp : " . count($dataLhp));
        $this->info("Start to build data [".Carbon::now()->toDateTimeString()."]");

        $orders = $dataHeaders->map(function ($order) use ($dataOrderDetail, $dataLhp, $allParameter) {
            $detailPerOrder = $dataOrderDetail[$order->no_order] ?? [];
            $lhpPerOrder = $dataLhp[$order->no_order] ?? [];
            $orderDate = Carbon::parse($order->tgl_order)->format('Y-m-d');

            $dataOrderDetailMapped = collect($detailPerOrder)
                ->groupBy('periode')
                ->map(function ($detailsByPeriode, $periode) use ($orderDate, $lhpPerOrder, $allParameter) {
                    $details = $detailsByPeriode
                        ->groupBy('cfr')
                        ->map(fn ($group) => $this->buildCfrDetail($group, $orderDate, $lhpPerOrder, $allParameter))
                        ->values();

                    $totalLhp = $details->count();
                    $selesaiLhp = $details->filter(fn ($item) => $item['lhp_rilis'])->count();
                    $releasedDates = $details->pluck('tgl_lhp_rilis')->filter();

                    return [
                        'periode' => $periode,
                        'status_selesai' => $details->every(fn ($item) => $item['lhp_rilis']),
                        'jumlah_lhp' => $totalLhp,
                        'jumlah_lhp_selesai' => $selesaiLhp,
                        'persentase_lhp_selesai' => $totalLhp > 0 ? ($selesaiLhp / $totalLhp) * 100 : 0,
                        'proses' => $selesaiLhp . '/' . $totalLhp,
                        'tgl_lhp_rilis_terakhir' => $releasedDates->isNotEmpty() ? $releasedDates->max() : null,
                        'detail' => $details->toArray(),
                    ];
                })
                ->values();

            $statusSelesai = $dataOrderDetailMapped->every(fn ($item) => $item['status_selesai']);
            $namaPt = ($order->konsultan != null || $order->konsultan != '')
                ? $order->konsultan . ' (' . $order->nama_perusahaan . ')'
                : $order->nama_perusahaan;

            return [
                'id'              => $order->id,
                'id_pelanggan'    => $order->id_pelanggan,
                'jenis_order'     => str_contains($order->no_document, 'ISL/QTC/') ? 'KONTRAK' : 'NORMAL',
                'no_penawaran'    => $order->no_document,
                'tgl_penawaran'   => $order->tanggal_penawaran,
                'no_order'        => $order->no_order,
                'tgl_order'       => $orderDate,
                'nama_perusahaan' => $namaPt,
                'alamat_sampling' => $order->alamat_sampling,
                'is_revisi'       => $order->is_revisi,
                'sales_id'        => $order->sales_id,
                'dataOrderDetail' => $dataOrderDetailMapped->toArray(),
                'status_selesai'  => $statusSelesai,
            ];
        })->values()->toArray();

        $this->info("Finish to build data [".Carbon::now()->toDateTimeString()."]");
        $this->info("Total mapped orders : " . count($orders));

        $this->persistOrderBerjalan($orders);

        $this->info("Finish Insert to Order Berjalan [".Carbon::now()->toDateTimeString()."]");

        $this->info("Start to sync tracking order [".Carbon::now()->toDateTimeString()."]");
        $trackingRecords = $this->buildTrackingOrderRecords($dataPembayaran, $orders);
        $this->persistTrackingOrder($trackingRecords, $noOrderArray);
        $this->info("Finish sync tracking order [".Carbon::now()->toDateTimeString()."]");
        $this->info("Total Tracking Order : " . count($trackingRecords));
        $this->info("Waktu selesai : " . Carbon::now()->toDateTimeString());
        $this->logCommandDuration($commandStartedAt);
        $this->info("===== Finish Command: CheckOrderActive =====");
    }

    private function fetchLhpDataByOrder(array $noOrderArray): array
    {
        if (empty($noOrderArray)) {
            return [];
        }

        $records = collect($this->lhpRelations)->flatMap(function ($relationModel, $relationKey) use ($noOrderArray) {
            $modelClass = 'App\Models\\' . $relationModel;
            $modelInstance = new $modelClass();
            $tableColumns = Schema::getColumnListing($modelInstance->getTable());

            if (in_array('no_cfr', $tableColumns)) {
                $keyColumn = 'no_cfr';
            } elseif (in_array('no_lhp', $tableColumns)) {
                $keyColumn = 'no_lhp';
            } else {
                $keyColumn = null;
            }

            $selectColumns = ['no_order', 'created_at', 'approved_at'];
            if ($keyColumn !== null) {
                array_unshift($selectColumns, $keyColumn);
            }
            if (in_array('no_sampel', $tableColumns)) {
                $selectColumns[] = 'no_sampel';
            }

            $selectColumns = array_values(array_filter($selectColumns, fn ($col) => in_array($col, $tableColumns)));

            $query = $modelClass::whereIn('no_order', $noOrderArray)->select($selectColumns);
            if (in_array('is_active', $tableColumns)) {
                $query->where('is_active', 1);
            }

            return $query->get()->map(function ($row) use ($relationKey, $keyColumn) {
                return array_merge($row->toArray(), [
                    '_relation' => $relationKey,
                    '_match_column' => $keyColumn,
                ]);
            })->all();
        })->all();

        return collect($records)->groupBy('no_order')->toArray();
    }

    private function getLhpMatchColumn(string $relation): string
    {
        if (in_array($relation, ['lhps_air', 'lhps_padatan'])) {
            return 'no_sampel';
        }

        if ($relation === 'lhp_psikologi') {
            return 'no_cfr';
        }

        return 'no_lhp';
    }

    private function getDetailMatchValue(string $relation, array $detail): ?string
    {
        if (in_array($relation, ['lhps_air', 'lhps_padatan'])) {
            return $detail['no_sampel'] ?? null;
        }

        return $detail['cfr'] ?? null;
    }

    private function findLhpForDetail(array $detail, array $lhpRecords): ?array
    {
        foreach (array_keys($this->lhpRelations) as $relation) {
            $matchValue = $this->getDetailMatchValue($relation, $detail);
            if (empty($matchValue)) {
                continue;
            }

            $matchColumn = $this->getLhpMatchColumn($relation);

            foreach ($lhpRecords as $record) {
                if (($record['_relation'] ?? null) !== $relation) {
                    continue;
                }

                if (!empty($record[$matchColumn]) && $record[$matchColumn] == $matchValue) {
                    return $record;
                }
            }
        }

        return null;
    }

    private function buildHasilUji(array $sampelNumbers, $kategori_2, array $expectedParams = [], $steps){
       $details = WsFinalApprovalDetail::whereIn('no_sampel', $sampelNumbers)
           ->get(['no_sampel', 'parameter_lab', 'parameter_regulasi', 'hasil'])
           ->toArray();

       $categoryName = '';
       if (!empty($kategori_2)) {
           $parts = explode('-', $kategori_2);
           $categoryName = isset($parts[1]) ? trim($parts[1]) : trim($kategori_2);
       }

       $hasilJikaDetailKosong = null;

       $orderDate = $steps['order']['date'] ?? '';
       $samplingDate = $steps['sampling']['date'] ?? '';
       $analisaDate = $steps['analisa']['date'] ?? '';

       if ($orderDate !== '' && (!isset($samplingDate) || $samplingDate == '' || $samplingDate == null)){
           $hasilJikaDetailKosong = 'Menunggu Sampling';
       } else if ($samplingDate !== '' && (!isset($analisaDate) || $analisaDate == '' || $analisaDate == null)){
           $hasilJikaDetailKosong = 'Menunggu Analisa';
       }

       $foundMap = [];
       foreach ($details as &$detail) {
           if (empty($detail['parameter_regulasi']) || $detail['parameter_regulasi'] == null) {
               $query = Parameter::where('is_active', 1)
                   ->where(function ($q) use ($detail) {
                       $q->where('nama_lab', $detail['parameter_lab']);
                   }
                );

               if (!empty($categoryName)) {
                   $query->whereRaw("TRIM(nama_kategori) = ?", [$categoryName]);
               }

               $param = $query->first();
               if ($param) {
                   $detail['parameter_regulasi'] = $param->nama_regulasi;
               } else {
                   $detail['parameter_regulasi'] = $detail['parameter_lab'];
               }
           }

           foreach ($expectedParams as $expectedParam) {
               $cleanExpected = strtolower(trim($expectedParam));
               $cleanRegulasi = strtolower(trim($detail['parameter_regulasi']));
               if ($cleanExpected === $cleanRegulasi || 
                   (!empty($cleanRegulasi) && (str_contains($cleanExpected, $cleanRegulasi) || str_contains($cleanRegulasi, $cleanExpected)))) {
                   $detail['parameter_regulasi'] = $expectedParam;
                   break;
               }
           }

           $key = $detail['no_sampel'] . '|' . ($detail['parameter_regulasi'] ?? '');
           $foundMap[$key] = true;

           unset($detail['parameter_lab']);
       }

        foreach ($sampelNumbers as $sampelNumber) {
           foreach ($expectedParams as $expectedParam) {
               $key = $sampelNumber . '|' . $expectedParam;
               if (!isset($foundMap[$key])) {
                   $details[] = [
                       'no_sampel' => $sampelNumber,
                       'parameter_regulasi' => $expectedParam,
                       'hasil' => $hasilJikaDetailKosong
                   ];
                   $foundMap[$key] = true;
               }
           }
        }

       return $details;
    }

    private function buildCfrDetail($group, string $orderDate, array $lhpRecords, $allParameter): array
    {
        $group = collect($group);
        $d = $group->first();
        $track = $d['tracking_satu'] ?? null;

        $steps = $this->initializeSteps($orderDate);

        $isDirect = ($d['kategori_3'] ?? null) == '118-Psikologi';
        $isSD = ($d['kategori_1'] ?? null) == 'SD';

        $samplingDate = $isSD
            ? ($d['tanggal_terima'] ?? null)
            : ($isDirect ? ($d['tanggal_terima'] ?? null) : ($d['tanggal_terima'] ?? null));

        $steps['sampling'] = [
            'label' => $isSD ? 'Sampel Diterima' : ($isDirect ? 'Direct' : 'Sampling'),
            'date'  => $samplingDate,
        ];

        $kategoriValidation = [
            '13-Getaran','14-Getaran (Bangunan)','15-Getaran (Kejut Bangunan)',
            '16-Getaran (Kenyamanan & Kesehatan)','17-Getaran (Lengan & Tangan)',
            '18-Getaran (Lingkungan)','19-Getaran (Mesin)','20-Getaran (Seluruh Tubuh)',
            '21-Iklim Kerja','23-Kebisingan','24-Kebisingan (24 Jam)',
            '25-Kebisingan (Indoor)','28-Pencahayaan',
        ];

        $parameter = $d['parameter'] ?? '';
        $kategori3 = $d['kategori_3'] ?? '';
        $keterangan1 = strtolower($d['keterangan_1'] ?? '');

        $tglAnalisa = $isDirect
            ? $samplingDate
            : ($track['ftc_laboratory'] ?? null);

        $forceSameDate =
            in_array($kategori3, $kategoriValidation) ||
            str_contains($parameter, 'Ergonomi') ||
            str_contains($parameter, 'Gelombang Elektro') ||
            str_contains($parameter, 'Sinar Uv') ||
            str_contains($parameter, 'Getaran') ||
            str_contains($kategori3, 'Psikologi') ||
            str_contains($keterangan1, 'higiene');

        $steps['analisa']['date'] = $forceSameDate
            ? $samplingDate
            : ($tglAnalisa ? Carbon::parse($tglAnalisa)->format('Y-m-d') : null);

        $lhps = $this->findLhpForDetail($d, $lhpRecords);
        $tglLhpRilis = null;

        if ($lhps) {
            $steps['drafting']['date'] = !empty($lhps['created_at'])
                ? Carbon::parse($lhps['created_at'])->format('Y-m-d')
                : null;

            if ($steps['drafting']['date'] != null) {
                $steps['analisa']['date'] = !empty($lhps['created_at'])
                    ? Carbon::parse($lhps['created_at'])->format('Y-m-d')
                    : null;
            }

            $tglLhpRilis = !empty($lhps['approved_at'])
                ? Carbon::parse($lhps['approved_at'])->format('Y-m-d')
                : null;

            $steps['lhp_release']['date'] = $tglLhpRilis;
        }

        $steps['activeStep'] = $this->detectActiveStep($steps);

       $lhpRilis = (($d['status'] ?? null) === 3) || ($steps['activeStep'] === 5);
       $sampelNumbers = $group->pluck('no_sampel')->toArray();
       $parameterHasil = json_decode($d['parameter'] ?? '', true);
       $parameterRegulasi = $this->buildParameterRegulasi($d['parameter'] ?? '', $allParameter);
       $kategori_2 = $d['kategori_2'] ?? '';

        $result = [
            'no_order'      => $d['no_order'],
            'jumlah_sampel' => $group->count(),
            'cfr'           => $d['cfr'],
            'kategori_1'    => $d['kategori_1'],
            'kategori_2'    => $kategori_2,
            'kategori_3'    => $d['kategori_3'],
            'parameter'          => $parameterHasil,
            'regulasi'           => json_decode($d['regulasi'] ?? '', true),
            'lhp_rilis'     => $lhpRilis,
            'tgl_lhp_rilis' => $tglLhpRilis,
            'steps'         => $steps,
            'points'        => $group->pluck('keterangan_1')->toArray(),
            'categories'    => $group->pluck('kategori_3')->toArray(),
            'sampelNumbers' => $sampelNumbers,          
        ];

        if (!$lhpRilis) {
            $result['parameter_regulasi'] = $parameterRegulasi;
            $result['hasil_uji'] = $this->buildHasilUji($sampelNumbers, $kategori_2, $parameterRegulasi, $steps);
        }

        return $result;
    }

    private function buildParameterRegulasi(?string $parameterJson, array $allParameter): array
    {
        $decoded = json_decode($parameterJson ?? '', true);
        if (!is_array($decoded) || empty($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(function ($item) use ($allParameter) {
                $id = is_string($item) && str_contains($item, ';')
                    ? explode(';', $item, 2)[0]
                    : $item;

                return $allParameter[$id]['nama_regulasi'] ?? null;
            })
            ->filter()
            ->values()
            ->toArray();
    }

    private function buildTrackingOrderRecords($dataPembayaran, array $orders): array
    {
        $ordersByNoOrder = collect($orders)->keyBy('no_order');

        return collect($dataPembayaran)->map(function ($pembayaran) use ($ordersByNoOrder) {
            $order = $ordersByNoOrder->get($pembayaran->no_order);
            $lhpSummary = $order
                ? $this->resolveLhpSummaryForPeriode($order, $pembayaran->periode)
                : $this->emptyLhpSummary();

            $totalLhp = (int) ($pembayaran->total_cfr ?? $lhpSummary['total_lhp']);
            $selesaiLhp = (int) $lhpSummary['jumlah_lhp_selesai'];
            $isLunas = (int) ($pembayaran->is_lunas ?? 0) === 1;
            $lhpTerbitSemua = $totalLhp > 0 && $selesaiLhp >= $totalLhp;

            return [
                'status_customer'             => $pembayaran->status_customer,
                'no_order'                    => $pembayaran->no_order,
                'tanggal_order'               => $order['tgl_order'] ?? null,
                'no_invoice'                  => $pembayaran->no_invoice,
                'nilai_invoice'               => $pembayaran->nilai_invoice,
                'nilai_pembayaran'            => $pembayaran->nilai_pembayaran,
                'nilai_pengurangan'           => $pembayaran->nilai_pengurangan,
                'revenue_invoice'             => $pembayaran->revenue_invoice,
                'tanggal_pembayaran'          => $pembayaran->tanggal_pembayaran,
                'no_po'                       => $pembayaran->no_po,
                'is_lunas'                    => (int) ($pembayaran->is_lunas ?? 0),
                'is_invoicing'                => (int) ($pembayaran->is_invoicing ?? 0),
                'no_quotation'                => $pembayaran->no_quotation,
                'tanggal_penawaran'           => $order['tgl_penawaran'] ?? null,
                'total_lhp'                   => $totalLhp,
                'jumlah_lhp_selesai'          => $selesaiLhp,
                'progress'                    => $totalLhp > 0 ? ($selesaiLhp . '/' . $totalLhp) : '0/0',
                'tanggal_awal_sampling'       => $pembayaran->tanggal_sampling_min,
                'tanggal_terakhir_lhp_rilis'  => $lhpSummary['tanggal_terakhir_lhp_rilis'],
                'pelanggan_ID'                => $pembayaran->pelanggan_ID,
                'nama_perusahaan'             => $pembayaran->nama_perusahaan,
                'konsultan'                   => $pembayaran->konsultan,
                'periode'                     => $pembayaran->periode,
                'kontrak'                     => $pembayaran->kontrak,
                'status_sampling'             => $pembayaran->status_sampling,
                'sales_id'                    => $pembayaran->sales_id,
                'sales_nama'                  => $pembayaran->sales_nama,
                'total_discount'              => $pembayaran->total_discount,
                'total_ppn'                   => $pembayaran->total_ppn,
                'total_pph'                   => $pembayaran->total_pph,
                'biaya_akhir'                 => $pembayaran->biaya_akhir,
                'grand_total'                 => $pembayaran->grand_total,
                'total_revenue'               => $pembayaran->total_revenue,
                'tanggal_kelompok'            => $pembayaran->tanggal_kelompok,
                'is_selesai'                  => ($isLunas && $lhpTerbitSemua) ? 1 : 0,
                'updated_at'                  => Carbon::now(),
                'created_at'                  => Carbon::now(),
            ];
        })->values()->toArray();
    }

    private function resolveLhpSummaryForPeriode(array $order, ?string $periode): array
    {
        $details = collect($order['dataOrderDetail'] ?? []);

        if ($periode !== null && $periode !== '') {
            $matched = $details->firstWhere('periode', $periode);
            if ($matched) {
                return [
                    'total_lhp'                  => (int) ($matched['jumlah_lhp'] ?? 0),
                    'jumlah_lhp_selesai'         => (int) ($matched['jumlah_lhp_selesai'] ?? 0),
                    'tanggal_terakhir_lhp_rilis' => $matched['tgl_lhp_rilis_terakhir'] ?? null,
                ];
            }
        }

        $totalLhp = (int) $details->sum('jumlah_lhp');
        $selesaiLhp = (int) $details->sum('jumlah_lhp_selesai');
        $releasedDates = $details->pluck('tgl_lhp_rilis_terakhir')->filter();

        return [
            'total_lhp'                  => $totalLhp,
            'jumlah_lhp_selesai'         => $selesaiLhp,
            'tanggal_terakhir_lhp_rilis' => $releasedDates->isNotEmpty() ? $releasedDates->max() : null,
        ];
    }

    private function emptyLhpSummary(): array
    {
        return [
            'total_lhp'                  => 0,
            'jumlah_lhp_selesai'         => 0,
            'tanggal_terakhir_lhp_rilis' => null,
        ];
    }

    private function trackingOrderKey(string $noOrder, ?string $periode): string
    {
        return trim($noOrder) . '|' . trim((string) ($periode ?? '__NULL__'));
    }

    private function persistTrackingOrder(array $records, array $noOrderArray): void
    {
        if (empty($noOrderArray)) {
            return;
        }

        try {
            $validKeys = collect($records)
                ->map(fn ($row) => $this->trackingOrderKey($row['no_order'], $row['periode'] ?? null))
                ->flip();

            $existingRows = TrackingOrder::whereIn('no_order', $noOrderArray)->get();
            $existingByKey = $existingRows->keyBy(
                fn ($row) => $this->trackingOrderKey($row->no_order, $row->periode)
            );

            $deleteIds = $existingRows
                ->filter(fn ($row) => !$validKeys->has($this->trackingOrderKey($row->no_order, $row->periode)))
                ->pluck('id')
                ->all();

            if (!empty($deleteIds)) {
                TrackingOrder::whereIn('id', $deleteIds)->delete();
            }

            if (empty($records)) {
                return;
            }

            $now = Carbon::now();
            $rowsToUpsert = collect($records)->map(function ($row) use ($existingByKey, $now) {
                $key = $this->trackingOrderKey($row['no_order'], $row['periode'] ?? null);
                $existing = $existingByKey->get($key);

                return array_merge($row, [
                    'id'         => $existing->id ?? null,
                    'created_at' => $existing->created_at ?? $now,
                    'updated_at' => $now,
                ]);
            });

            $insertRows = $rowsToUpsert->filter(fn ($row) => empty($row['id']))->map(function ($row) {
                unset($row['id']);
                return $row;
            });

            $updateRows = $rowsToUpsert->filter(fn ($row) => !empty($row['id']))->values();

            $updateFields = [
                'status_customer',
                'tanggal_order',
                'no_invoice',
                'nilai_invoice',
                'nilai_pembayaran',
                'nilai_pengurangan',
                'revenue_invoice',
                'tanggal_pembayaran',
                'no_po',
                'is_lunas',
                'is_invoicing',
                'no_quotation',
                'tanggal_penawaran',
                'total_lhp',
                'jumlah_lhp_selesai',
                'progress',
                'tanggal_awal_sampling',
                'tanggal_terakhir_lhp_rilis',
                'pelanggan_ID',
                'nama_perusahaan',
                'konsultan',
                'periode',
                'kontrak',
                'status_sampling',
                'sales_id',
                'sales_nama',
                'total_discount',
                'total_ppn',
                'total_pph',
                'biaya_akhir',
                'grand_total',
                'total_revenue',
                'tanggal_kelompok',
                'is_selesai',
                'updated_at',
            ];

            $insertRows->chunk(300)->each(function ($chunk) {
                DB::table('tracking_order')->insert($chunk->toArray());
            });

            $updateRows->chunk(300)->each(function ($chunk) use ($updateFields) {
                DB::table('tracking_order')->upsert(
                    $chunk->toArray(),
                    ['id'],
                    $updateFields
                );
            });
        } catch (\Exception $e) {
            dd($e);
        }
    }

    private function persistOrderBerjalan(array $orders): void
    {
        try {
            $ids = collect($orders)->pluck('id')->toArray();

            DB::table('order_berjalan')
                ->whereNotIn('id', $ids)
                ->delete();

            $mappedOrders = collect($orders)->map(function ($item) {
                return [
                    'id'                => $item['id'],
                    'id_pelanggan'      => $item['id_pelanggan'],
                    'jenis_order'       => $item['jenis_order'],
                    'no_penawaran'      => $item['no_penawaran'],
                    'tgl_penawaran'     => $item['tgl_penawaran'],
                    'no_order'          => $item['no_order'],
                    'tgl_order'         => $item['tgl_order'],
                    'nama_perusahaan'   => $item['nama_perusahaan'],
                    'alamat_sampling'   => $item['alamat_sampling'],
                    'is_revisi'         => $item['is_revisi'],
                    'sales_id'          => $item['sales_id'],
                    'dataOrderDetail'   => json_encode($item['dataOrderDetail'], JSON_PRETTY_PRINT),
                    'status_selesai'    => $item['status_selesai'],
                    'updated_at'        => Carbon::now(),
                    'created_at'        => Carbon::now(),
                ];
            });

            $uniqueKeys = ['id'];
            $updateFields = [
                'id_pelanggan',
                'jenis_order',
                'no_penawaran',
                'tgl_penawaran',
                'no_order',
                'tgl_order',
                'nama_perusahaan',
                'alamat_sampling',
                'is_revisi',
                'sales_id',
                'dataOrderDetail',
                'status_selesai',
                'updated_at',
            ];

            $mappedOrders->chunk(300)->each(function ($chunk) use ($uniqueKeys, $updateFields) {
                DB::table('order_berjalan')->upsert(
                    $chunk->toArray(),
                    $uniqueKeys,
                    $updateFields
                );
            });
        } catch (\Exception $e) {
            dd($e);
        }
    }

    private function logCommandDuration(float $startedAt): void
    {
        $duration = microtime(true) - $startedAt;
        $this->info("Durasi eksekusi : " . $this->formatCommandDuration($duration));
    }

    private function formatCommandDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return number_format($seconds, 2) . ' detik';
        }

        $minutes = (int) floor($seconds / 60);
        $remainingSeconds = (int) round($seconds % 60);

        if ($minutes < 60) {
            return $minutes . ' menit ' . $remainingSeconds . ' detik';
        }

        $hours = (int) floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return $hours . ' jam ' . $remainingMinutes . ' menit ' . $remainingSeconds . ' detik';
    }

    private function initializeSteps($orderDate)
    {
        return [
            'order' => ['label' => 'Order', 'date' => $orderDate],
            'sampling' => ['label' => 'Sampling', 'date' => null],
            'analisa' => ['label' => 'Analisa', 'date' => null],
            'drafting' => ['label' => 'Drafting', 'date' => null],
            'lhp_release' => ['label' => 'LHP Release', 'date' => null],
        ];
    }

    private function detectActiveStep($steps)
    {
        $search = collect(['order', 'sampling', 'analisa', 'drafting', 'lhp_release'])
            ->search(fn($step) => empty($steps[$step]['date']));
        return $search === false ? 5 : $search;
    }
}