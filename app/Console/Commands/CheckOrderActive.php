<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderHeader;
use App\Models\OrderDetail;

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

        $this->info("Start to get data lhp [".Carbon::now()->toDateTimeString()."]");

        $dataLhp = $this->fetchLhpDataByOrder($noOrderArray);

        $this->info("Total Data Lhp : " . count($dataLhp));
        $this->info("Start to build data [".Carbon::now()->toDateTimeString()."]");

        $orders = $dataHeaders->map(function ($order) use ($dataOrderDetail, $dataLhp) {
            $detailPerOrder = $dataOrderDetail[$order->no_order] ?? [];
            $lhpPerOrder = $dataLhp[$order->no_order] ?? [];
            $orderDate = Carbon::parse($order->tgl_order)->format('Y-m-d');

            $dataOrderDetailMapped = collect($detailPerOrder)
                ->groupBy('periode')
                ->map(function ($detailsByPeriode, $periode) use ($orderDate, $lhpPerOrder) {
                    $details = $detailsByPeriode
                        ->groupBy('cfr')
                        ->map(fn ($group) => $this->buildCfrDetail($group, $orderDate, $lhpPerOrder))
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
        $this->info("Waktu selesai : " . Carbon::now()->toDateTimeString());
        $this->logCommandDuration($commandStartedAt);
        $this->info("===== Finish Command: CheckOrderActive =====");
    }

    public function handleBackup()
    {
        $startDate = '2025-11-01';
        $endDate = Carbon::now();

        printf("[CheckOrderActive] [%s] Start Running... ", Carbon::now());

        $lhpRelations = [
            'lhps_air','lhps_emisi','lhps_emisi_c','lhps_emisi_isokinetik',
            'lhps_getaran','lhps_kebisingan','lhps_kebisingan_personal',
            'lhps_ling','lhps_medanlm','lhps_pencahayaan','lhps_sinaruv',
            'lhps_ergonomi','lhps_iklim','lhps_swab_udara','lhps_microbiologi',
            'lhps_padatan','lhp_psikologi','lhps_hygiene_sanitasi'
        ];

        $orders = OrderHeader::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'id','no_order','id_pelanggan','sales_id',
                'no_document','created_at as tgl_order',
                'nama_perusahaan','alamat_sampling','is_revisi','konsultan'
            )
            ->with(array_merge(
                ["orderDetail.TrackingSatu:id,no_sample,ftc_sd,ftc_verifier,ftc_laboratory"],
                collect($lhpRelations)->map(fn($r) => "orderDetail.$r")->toArray()
            ))
            ->whereDate('tanggal_order', ">=", '2025-05-01')
            ->where('is_active', 1)
            ->whereHas('orderDetail')
            ->get()

            ->map(function ($order) use ($lhpRelations) {

                $dataOrderDetail = $order->orderDetail
                    ->groupBy('periode')
                    ->map(function ($detailsByPeriode, $periode) use ($order, $lhpRelations) {

                        $details = $detailsByPeriode->groupBy('cfr')->map(function ($group) use ($order, $lhpRelations) {

                            $d = $group->first();
                            $track = optional($d->TrackingSatu);

                            $steps = $this->initializeSteps(
                                Carbon::parse($order->tgl_order)->format('Y-m-d')
                            );

                            // ===== SAMPLING =====
                            $isDirect = $d->kategori_3 == '118-Psikologi';
                            $isSD = $d->kategori_1 == 'SD';

                            $samplingDate = $isSD
                                ? $d->tanggal_terima
                                // : ($isDirect ? $d->tanggal_terima : $d->tanggal_sampling);
                                : ($isDirect ? $d->tanggal_terima : $d->tanggal_terima); // optional sementara

                            $steps['sampling'] = [
                                'label' => $isSD ? 'Sampel Diterima' : ($isDirect ? 'Direct' : 'Sampling'),
                                'date'  => $samplingDate
                            ];

                            // ===== NON ANALISA =====
                            $kategoriValidation = [
                                '13-Getaran','14-Getaran (Bangunan)','15-Getaran (Kejut Bangunan)',
                                '16-Getaran (Kenyamanan & Kesehatan)','17-Getaran (Lengan & Tangan)',
                                '18-Getaran (Lingkungan)','19-Getaran (Mesin)','20-Getaran (Seluruh Tubuh)',
                                '21-Iklim Kerja','23-Kebisingan','24-Kebisingan (24 Jam)',
                                '25-Kebisingan (Indoor)','28-Pencahayaan'
                            ];

                            $tglAnalisa = $isDirect
                                ? $samplingDate
                                : ($track->ftc_laboratory ?? null);

                            $forceSameDate =
                                in_array($d->kategori_3, $kategoriValidation) ||
                                str_contains($d->parameter, 'Ergonomi') ||
                                str_contains($d->parameter, 'Gelombang Elektro') ||
                                str_contains($d->parameter, 'Sinar Uv') ||
                                str_contains($d->parameter, 'Getaran') ||
                                str_contains($d->kategori_3, 'Psikologi') ||
                                str_contains(strtolower($d->keterangan_1 ?? ''), 'higiene');

                            $steps['analisa']['date'] = $forceSameDate
                                ? $samplingDate
                                : ($tglAnalisa ? Carbon::parse($tglAnalisa)->format('Y-m-d') : null);

                            // ===== LHP =====
                            $lhps = collect($lhpRelations)
                                ->map(fn($rel) => $d->$rel)
                                ->first(fn($item) => !empty($item));

                            if ($lhps) {
                                $steps['drafting']['date'] = optional($lhps->created_at)
                                    ? Carbon::parse($lhps->created_at)->format('Y-m-d')
                                    : null;
                                
                                if($steps['drafting']['date'] != null){
                                    $steps['analisa']['date'] = optional($lhps->created_at)
                                    ? Carbon::parse($lhps->created_at)->format('Y-m-d')
                                    : null;
                                }

                                $steps['lhp_release']['date'] = optional($lhps->approved_at)
                                    ? Carbon::parse($lhps->approved_at)->format('Y-m-d')
                                    : null;
                            }

                            $steps['activeStep'] = $this->detectActiveStep($steps);

                            return [
                                'no_order'      => $d->no_order,
                                'jumlah_sampel' => $group->count(),
                                'cfr'           => $d->cfr,
                                'kategori_1'    => $d->kategori_1,
                                'kategori_2'    => $d->kategori_2,
                                'kategori_3'    => $d->kategori_3,
                                'parameter'     => json_decode($d->parameter, true),
                                'regulasi'      => json_decode($d->regulasi, true),
                                'lhp_rilis'     => ($d->status === 3) || ($steps['activeStep'] === 5) ? true : false,
                                'steps'         => $steps,
                                'points'        => $group->pluck('keterangan_1')->toArray(),
                                'categories'    => $group->pluck('kategori_3')->toArray(),
                                'sampelNumbers' => $group->pluck('no_sampel')->toArray(),
                            ];
                        })->values();

                        return [
                            'periode' => $periode,
                            'status_selesai' => $details->every(fn($i) => $i['lhp_rilis']),
                            'detail' => $details->toArray(),
                        ];
                    })->values();

                $statusSelesai = $dataOrderDetail->every(fn($i) => $i['status_selesai']);
                $namaPt = ($order->konsultan != null || $order->konsultan != '')
                    ? $order->konsultan . ' (' . $order->nama_perusahaan . ')' 
                    : $order->nama_perusahaan;
                    
                return [
                    'id'              => $order->id,
                    'id_pelanggan'    => $order->id_pelanggan,
                    'jenis_order'     => str_contains($order->no_document, 'ISL/QTC/') ? 'KONTRAK' : 'NORMAL',
                    'no_penawaran'    => $order->no_document,
                    'no_order'        => $order->no_order,
                    'tgl_order'       => Carbon::parse($order->tgl_order)->format('Y-m-d'),
                    'nama_perusahaan' => $namaPt,
                    'alamat_sampling' => $order->alamat_sampling,
                    'is_revisi'       => $order->is_revisi,
                    'sales_id'        => $order->sales_id,
                    'dataOrderDetail' => $dataOrderDetail->toArray(),
                    'status_selesai'  => $statusSelesai,
                ];
            })

            // ->filter(fn($o) => !$o['status_selesai'])
            ->values()
            ->toArray();

        printf("\n[CheckOrderActive] [%s] Total data :" . count($orders), Carbon::now());

        // DB::beginTransaction();

        try {
            $ids = collect($orders)->pluck('id')->toArray();

            // ❌ delete data yang tidak ada di hasil terbaru
            DB::table('order_berjalan')
                ->whereNotIn('id', $ids)
                ->delete();

            // 🔥 upsert in chunks of 300
            $mappedOrders = collect($orders)->map(function ($item) {
                return [
                    'id'                => $item['id'],
                    'id_pelanggan'      => $item['id_pelanggan'],
                    'jenis_order'       => $item['jenis_order'],
                    'no_penawaran'      => $item['no_penawaran'],
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
                'no_order',
                'tgl_order',
                'nama_perusahaan',
                'alamat_sampling',
                'is_revisi',
                'sales_id',
                'dataOrderDetail',
                'status_selesai',
                'updated_at'
            ];

            printf("\n[CheckOrderActive] [%s] Start to Insert to Order Berjalan dengan data mapped : " . $mappedOrders->count(), Carbon::now());

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
        printf("\n[CheckOrderActive] [%s] Finish Insert to Order Berjalan... ", Carbon::now());
        printf("\n[CheckOrderActive] [%s] Finish Running... ", Carbon::now());
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

    private function buildCfrDetail($group, string $orderDate, array $lhpRecords): array
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

        return [
            'no_order'      => $d['no_order'],
            'jumlah_sampel' => $group->count(),
            'cfr'           => $d['cfr'],
            'kategori_1'    => $d['kategori_1'],
            'kategori_2'    => $d['kategori_2'],
            'kategori_3'    => $d['kategori_3'],
            'parameter'     => json_decode($d['parameter'] ?? '', true),
            'regulasi'      => json_decode($d['regulasi'] ?? '', true),
            'lhp_rilis'     => (($d['status'] ?? null) === 3) || ($steps['activeStep'] === 5),
            'tgl_lhp_rilis' => $tglLhpRilis,
            'steps'         => $steps,
            'points'        => $group->pluck('keterangan_1')->toArray(),
            'categories'    => $group->pluck('kategori_3')->toArray(),
            'sampelNumbers' => $group->pluck('no_sampel')->toArray(),
        ];
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