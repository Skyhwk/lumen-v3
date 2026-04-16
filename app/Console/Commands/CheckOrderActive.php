<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderHeader;
use Carbon\Carbon;
use DB;

class CheckOrderActive extends Command
{
    protected $signature = 'checkorder';

    protected $description = 'Check order active satu tahun terakhir';

    public function handle()
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
                'nama_perusahaan','alamat_sampling','is_revisi'
            )
            ->with(array_merge(
                ["orderDetail.TrackingSatu:id,no_sample,ftc_sd,ftc_verifier,ftc_laboratory"],
                collect($lhpRelations)->map(fn($r) => "orderDetail.$r")->toArray()
            ))
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
                                : ($isDirect ? $d->tanggal_terima : $d->tanggal_sampling);

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
                            ];
                        })->values();

                        return [
                            'periode' => $periode,
                            'status_selesai' => $details->every(fn($i) => $i['lhp_rilis']),
                            'detail' => $details->toArray(),
                        ];
                    })->values();

                $statusSelesai = $dataOrderDetail->every(fn($i) => $i['status_selesai']);

                return [
                    'id'              => $order->id,
                    'id_pelanggan'    => $order->id_pelanggan,
                    'jenis_order'     => str_contains($order->no_document, 'ISL/QTC/') ? 'KONTRAK' : 'NORMAL',
                    'no_penawaran'    => $order->no_document,
                    'no_order'        => $order->no_order,
                    'tgl_order'       => Carbon::parse($order->tgl_order)->format('Y-m-d'),
                    'nama_perusahaan' => $order->nama_perusahaan,
                    'alamat_sampling' => $order->alamat_sampling,
                    'is_revisi'       => $order->is_revisi,
                    'sales_id'        => $order->sales_id,
                    'dataOrderDetail' => $dataOrderDetail->toArray(),
                    'status_selesai'  => $statusSelesai,
                ];
            })

            ->filter(fn($o) => !$o['status_selesai'])
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