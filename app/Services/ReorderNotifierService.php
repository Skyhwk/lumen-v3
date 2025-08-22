<?php

namespace App\Services;

use Illuminate\Support\Facades\View;

use App\Models\OrderDetail;
use App\Models\PerubahanSampel;
use App\Services\PerubahanSampelService;
use App\Services\SendEmail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReorderNotifierService
{
    private $no_order;
    public function run($orderHeader, $log, $bcc, $userid)
    {
        $dataLama = collect($log['data_lama']);
        $dataBaru = collect($log['data_baru']);

        // Identifikasi penambahan dan pengurangan sampel
        $add = $this->identifyAdditions($dataLama, $dataBaru);
        $sub = $this->identifyRemovals($dataLama, $dataBaru);
        $changes = $this->identifyChanges($dataLama, $dataBaru);

        // Mengumpulkan no_sampel yang ada di add dan sub
        $addNoSampel = collect($add)->pluck('no_sampel')->toArray();
        $subNoSampel = collect($sub)->pluck('no_sampel')->toArray();

        // Mengambil semua no_sampel unik
        $allNoSampel = $dataLama->pluck('no_sampel')
            ->merge($dataBaru->pluck('no_sampel'))
            ->unique()
            ->values()
            ->toArray();

        $orderDetails = OrderDetail::where('no_order', $orderHeader->no_order)
            ->whereIn('no_sampel', $allNoSampel)
            ->get()
            ->map(function ($detail) use ($addNoSampel, $subNoSampel) {
                $detailArray = $detail->toArray();
                $detailArray['keterangan'] = null;

                if (in_array($detail->no_sampel, $addNoSampel)) {
                    $detailArray['keterangan'] = 'add';
                } elseif (in_array($detail->no_sampel, $subNoSampel)) {
                    $detailArray['keterangan'] = 'sub';
                }

                return $detailArray;
            });

        $orderHeader->order_detail = collect($orderDetails);

        $result = (object) [
            'order_header' => collect($orderHeader),
            'perubahan' => [
                'add' => $add,
                'sub' => $sub,
                'changes' => $changes['perubahan']
            ]
        ];

        $reorderNotifierService = new PerubahanSampelService();
        $reorderNotifierService->run($this->no_order, $userid);

        $this->notify($result, $bcc);
    }

    private function notify($orders, $bcc)
    {
        array_push($bcc, "afryan@intilab.com");
        array_push($bcc, "dedi@intilab.com");
        SendEmail::where('to', "technicalcontrolsampel@intilab.com")
            // SendEmail::where('to', "afryan@intilab.com")
            ->where('subject', "Informasi Perubahan Sampel")
            ->where('bcc', $bcc ?: [])
            ->where('body', View::make('order-changes', compact('orders'))->render())
            ->where('karyawan', env('MAIL_NOREPLY_USERNAME'))
            ->noReply()
            ->send();
    }

    private function identifyRemovals($dataLama, $dataBaru)
    {
        $noSampelBaru = $dataBaru->pluck('no_sampel')->toArray();

        return $dataLama
            ->filter(function ($itemLama) use ($noSampelBaru) {
                return !in_array($itemLama['no_sampel'], $noSampelBaru);
            })->values()->all();
    }

    private function identifyAdditions($dataLama, $dataBaru)
    {
        $noSampelLama = $dataLama->pluck('no_sampel')->toArray();

        return $dataBaru
            ->filter(function ($itemBaru) use ($noSampelLama) {
                return !in_array($itemBaru['no_sampel'], $noSampelLama);
            })->values()->all();
    }

    private function identifyChanges($dataLama, $dataBaru)
    {
        $penambahan_data = [];
        $pengurangan_data = [];
        $perubahan_data = [];

        $sampelLama = [];
        $sampelBaru = [];

        foreach ($dataLama as $item) {
            $sampelLama[$item['no_sampel']] = $item;
        }

        foreach ($dataBaru as $item) {
            $sampelBaru[$item['no_sampel']] = $item;
        }

        $matchedLama = [];
        $matchedBaru = [];

        $no_order = $dataBaru->toArray()[0]['no_order'];
        // ======================= Perubahaan Data pada No Sample sama =======================
        foreach ($sampelBaru as $noSampel => $itemBaru) {
            if (isset($sampelLama[$noSampel])) {
                $itemLama = $sampelLama[$noSampel];
                $changes = [];

                foreach ($itemBaru as $field => $value) {
                    if ($field === 'no_sampel' || $field === 'no_order') {
                        continue; // Skip primary keys
                    }

                    if ($itemLama[$field] !== $value) {
                        $changes[$field] = [
                            'old' => $itemLama[$field],
                            'new' => $value
                        ];
                    }
                }

                if (!empty($changes)) {
                    $perubahan_data[] = [
                        'no_sampel' => $noSampel,
                        'before' => $itemLama,
                        'after' => $itemBaru,
                        'changes' => $changes,
                        'type' => 'update_data'
                    ];
                }

                $matchedLama[$noSampel] = true;
                $matchedBaru[$noSampel] = true;
            }
        }

        // ======================= Perubahan No Sampel pada Data Sama =======================
        foreach ($sampelBaru as $noSampelBaru => $itemBaru) {
            if (isset($matchedBaru[$noSampelBaru])) {
                continue;
            }

            $foundMatch = false;
            foreach ($sampelLama as $noSampelLama => $itemLama) {
                if (isset($matchedLama[$noSampelLama])) {
                    continue;
                }

                // Extract regulasi
                $regulasiLama = isset($itemLama['regulasi']) && is_array($itemLama['regulasi'])
                    ? array_map(fn($item) => explode('-', $item)[0], $itemLama['regulasi'])
                    : (isset($itemLama['regulasi']) ? [explode('-', $itemLama['regulasi'])[0]] : []);

                $regulasiBaru = isset($itemBaru['regulasi']) && is_array($itemBaru['regulasi'])
                    ? array_map(fn($item) => explode('-', $item)[0], $itemBaru['regulasi'])
                    : (isset($itemBaru['regulasi']) ? [explode('-', $itemBaru['regulasi'])[0]] : []);

                // Kriteria data sama
                $isSameBasic = ($itemLama['kategori_1'] ?? '') == ($itemBaru['kategori_1'] ?? '') &&
                    ($itemLama['kategori_2'] ?? '') == ($itemBaru['kategori_2'] ?? '') &&
                    ($itemLama['parameter'] ?? '') == ($itemBaru['parameter'] ?? '') &&
                    ($itemLama['penamaan_titik'] ?? '') == ($itemBaru['penamaan_titik'] ?? '');

                $isSameRegulasi = $regulasiLama == $regulasiBaru;

                if ($isSameBasic && $isSameRegulasi) {
                    // Hanya berubah No Sampel
                    $perubahan_data[] = [
                        'no_sampel' => $noSampelBaru,
                        'before' => $itemLama,
                        'after' => $itemBaru,
                        'changes' => [
                            'no_sampel' => [
                                'old' => $noSampelLama,
                                'new' => $noSampelBaru
                            ]
                        ],
                        'type' => 'no_sampel'
                    ];

                    $matchedLama[$noSampelLama] = true;
                    $matchedBaru[$noSampelBaru] = true;
                    $foundMatch = true;
                    break;

                } elseif ($isSameBasic && !$isSameRegulasi) {
                    // No Sampel & Regulasi berubah
                    $perubahan_data[] = [
                        'no_sampel' => $noSampelBaru,
                        'before' => $itemLama,
                        'after' => $itemBaru,
                        'changes' => [
                            'regulasi' => [
                                'old' => $itemLama['regulasi'] ?? null,
                                'new' => $itemBaru['regulasi'] ?? null
                            ],
                            'no_sampel' => [
                                'old' => $noSampelLama,
                                'new' => $noSampelBaru
                            ]
                        ],
                        'type' => 'no_sampel_dan_regulasi'
                    ];

                    $matchedLama[$noSampelLama] = true;
                    $matchedBaru[$noSampelBaru] = true;
                    $foundMatch = true;
                    break;
                }
            }

            // Tidak ada match (penambahan data baru)
            if (!$foundMatch) {
                $penambahan_data[] = $itemBaru;
                $matchedBaru[$noSampelBaru] = true;
            }
        }

        // Data tidak relevan (penghapusan data lama)
        foreach ($sampelLama as $noSampel => $itemLama) {
            if (!isset($matchedLama[$noSampel])) {
                $pengurangan_data[] = $itemLama;
            }
        }

        // ======================= Format data untuk disimpan ke DB =======================
        $no_sampel_changes = array_filter($perubahan_data, function ($item) {
            return in_array($item['type'], ['no_sampel']);
        });

        $grouped_by_periode = [];
        foreach ($no_sampel_changes as $change) {
            $periode = $change['after']['periode'] !== null ? $change['after']['periode'] : (string) Carbon::parse($change['after']['tanggal_sampling'])->format('Y-m');

            if (!isset($grouped_by_periode[$periode])) {
                $grouped_by_periode[$periode] = [];
            }

            $grouped_by_periode[$periode][] = [
                'old' => $change['changes']['no_sampel']['old'],
                'new' => $change['changes']['no_sampel']['new']
            ];
        }

        $final_data = [];
        foreach ($grouped_by_periode as $periode => $changes) {
            if (!empty($changes)) {
                $final_data[] = [
                    'no_order' => $no_order,
                    'periode' => $periode,
                    'perubahan' => $changes
                ];
            }
        }

        if (!empty($final_data)) {
            foreach ($final_data as $data) {
                PerubahanSampel::create([
                    'no_order' => $data['no_order'],
                    'periode' => $data['periode'],
                    'perubahan' => json_encode($data['perubahan'] ?? [])
                ]);
                $no_order = $data['no_order'];
            }
        }

        $this->no_order = $no_order;
        return [
            'penambahan' => $penambahan_data,
            'pengurangan' => $pengurangan_data,
            'perubahan' => array_map(function ($item) {
                return [
                    'no_sampel' => $item['changes']['no_sampel'] ?? null,
                    'regulasi' => $item['changes']['regulasi'] ?? null,
                    'type' => $item['type'] ?? null,
                ];
            }, $perubahan_data)
        ];
    }
}
