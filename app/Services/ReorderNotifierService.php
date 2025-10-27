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
        $this->no_order = $orderHeader->no_order;
        $dataLama = collect($log['data_lama']);
        $dataBaru = collect($log['data_baru']);

        // Identifikasi penambahan dan pengurangan sampel
        $add = $this->identifyAdditions($dataLama, $dataBaru);
        $sub = $this->identifyRemovals($dataLama, $dataBaru);
        if (str_contains($orderHeader->no_document, '/QTC/')) {
            $changes = $this->identifyChangesContract($dataLama, $dataBaru);
        } else if (str_contains($orderHeader->no_document, '/QT/')) {
            $changes = $this->identifyChanges($dataLama, $dataBaru);
        }

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
        // array_push($bcc, "afryan@intilab.com");
        // array_push($bcc, "dedi@intilab.com");
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

    private function identifyChangesContract($dataLama, $dataBaru)
    {
        // $no_order = $dataBaru->toArray()[0]['no_order'];

        $oldData = [];
        $oldPeriod = $dataLama->pluck('periode')->unique()->values()->toArray();
        foreach ($oldPeriod as $item) {
            $oldData[$item] = $dataLama->where('periode', $item)->pluck('no_sampel')->toArray();
        }

        $newData = [];
        $newPeriod = $dataBaru->pluck('periode')->unique()->values()->toArray();
        foreach ($newPeriod as $item) {
            $newData[$item] = $dataBaru->where('periode', $item)->pluck('no_sampel')->toArray();
        }

        $oldSample = [];
        foreach ($oldData as $periode => $samples) {
            $diff = array_diff($samples, $newData[$periode] ?? []);
            if (!empty($diff)) {
                $oldSample[$periode] = array_diff($samples, $newData[$periode] ?? []);
            }
        }

        $newSample = [];
        foreach ($newData as $periode => $samples) {
            $diff = array_diff($samples, $oldData[$periode] ?? []);
            if (!empty($diff)) {
                $newSample[$periode] = array_diff($samples, $oldData[$periode] ?? []);
            }
        }

        // dd($oldData, $newData, $oldSample, $newSample);
        $matchedLama = [];
        $matchedBaru = [];
        $perubahan_data = [];
        $penambahan_data = [];
        $pengurangan_data = [];
        $matchPeriod = array_intersect(array_keys($oldSample), array_keys($newSample));
        foreach ($matchPeriod as $periode) {
            $data_old = $dataLama->whereIn('no_sampel', $oldSample[$periode])->values();
            $data_new = $dataBaru->whereIn('no_sampel', $newSample[$periode])->values();

            // ======================= Perubahan No Sampel pada Data Sama =======================
            foreach ($data_new as $new) {
                if (isset($matchedBaru[$new["no_sampel"]])) {
                    continue;
                }

                $foundMatch = false;
                foreach ($data_old as $old) {
                    if (isset($matchedLama[$old["no_sampel"]])) {
                        continue;
                    }

                    // Extract regulasi
                    $regulasiLama = json_decode($old['regulasi'], true);
                    $regulasiBaru = json_decode($new['regulasi'], true);

                    $paramLama = json_decode($old['parameter'], true);
                    $paramBaru = json_decode($new['parameter'], true);

                    // Kriteria data sama
                    $isSameBasic = $old['kategori_2'] == $new['kategori_2'] &&
                        $old['kategori_3'] == $new['kategori_3'] &&
                        $paramLama == $paramBaru;

                    $isSameRegulasi = $regulasiLama == $regulasiBaru;
                    if ($isSameBasic && $isSameRegulasi) {
                        // Hanya berubah No Sampel
                        $perubahan_data[] = [
                            'no_sampel' => $new["no_sampel"],
                            'before' => $old,
                            'after' => $new,
                            'changes' => [
                                'no_sampel' => [
                                    'old' => $old["no_sampel"],
                                    'new' => $new["no_sampel"]
                                ]
                            ],
                            'type' => 'no_sampel'
                        ];

                        $matchedLama[$old["no_sampel"]] = true;
                        $matchedBaru[$new["no_sampel"]] = true;
                        $foundMatch = true;
                        break;

                    } elseif ($isSameBasic && !$isSameRegulasi) {
                        // No Sampel & Regulasi berubah
                        $perubahan_data[] = [
                            'no_sampel' => $new["no_sampel"],
                            'before' => $old,
                            'after' => $new,
                            'changes' => [
                                'regulasi' => [
                                    'old' => $old['regulasi'] ?? null,
                                    'new' => $new['regulasi'] ?? null
                                ],
                                'no_sampel' => [
                                    'old' => $old["no_sampel"],
                                    'new' => $new["no_sampel"]
                                ]
                            ],
                            'type' => 'no_sampel_dan_regulasi'
                        ];

                        $matchedLama[$old["no_sampel"]] = true;
                        $matchedBaru[$new["no_sampel"]] = true;
                        $foundMatch = true;
                        break;
                    }
                }

                // Tidak ada match (penambahan data baru)
                if (!$foundMatch) {
                    $penambahan_data[] = $new;
                    $matchedBaru[$new["no_sampel"]] = true;
                }
            }

            // Data tidak relevan (penghapusan data lama)
            foreach ($data_old as $old) {
                if (!isset($matchedLama[$old["no_sampel"]])) {
                    $pengurangan_data[] = $old;
                }
            }
        }

        // ======================= Format data untuk disimpan ke DB =======================
        $no_sampel_changes = array_filter($perubahan_data, function ($item) {
            return in_array($item['type'], ['no_sampel', 'no_sampel_dan_regulasi']);
        });

        $grouped_by_periode = [];
        foreach ($no_sampel_changes as $change) {
            $periode = $change['after']['periode'];

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
                    'no_order' => $this->no_order,
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
            }
        }

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

    private function identifyChanges($dataLama, $dataBaru)
    {
        $oldSample = $dataLama->pluck('no_sampel')->values()->toArray();
        $newSample = $dataBaru->pluck('no_sampel')->values()->toArray();

        $subSample = array_diff($oldSample, $newSample);
        $addSample = array_diff($newSample, $oldSample);

        $data_old = $dataLama->whereIn('no_sampel', $subSample)->values();
        $data_new = $dataBaru->whereIn('no_sampel', $addSample)->values();

        $penambahan_data = [];
        $pengurangan_data = [];
        $perubahan_data = [];

        foreach ($data_new as $new) {
            if (isset($matchedBaru[$new["no_sampel"]])) {
                continue;
            }

            $foundMatch = false;
            foreach ($data_old as $old) {
                if (isset($matchedLama[$old["no_sampel"]])) {
                    continue;
                }

                // Extract regulasi
                $regulasiLama = json_decode($old['regulasi'], true);
                $regulasiBaru = json_decode($new['regulasi'], true);

                $paramLama = json_decode($old['parameter'], true);
                $paramBaru = json_decode($new['parameter'], true);

                // Kriteria data sama
                $isSameBasic = $old['kategori_2'] == $new['kategori_2'] &&
                    $old['kategori_3'] == $new['kategori_3'] &&
                    $paramLama == $paramBaru;

                $isSameRegulasi = $regulasiLama == $regulasiBaru;
                if ($isSameBasic && $isSameRegulasi) {
                    // Hanya berubah No Sampel
                    $perubahan_data[] = [
                        'no_sampel' => $new["no_sampel"],
                        'before' => $old,
                        'after' => $new,
                        'changes' => [
                            'no_sampel' => [
                                'old' => $old["no_sampel"],
                                'new' => $new["no_sampel"]
                            ]
                        ],
                        'type' => 'no_sampel'
                    ];

                    $matchedLama[$old["no_sampel"]] = true;
                    $matchedBaru[$new["no_sampel"]] = true;
                    $foundMatch = true;
                    break;

                } elseif ($isSameBasic && !$isSameRegulasi) {
                    // No Sampel & Regulasi berubah
                    $perubahan_data[] = [
                        'no_sampel' => $new["no_sampel"],
                        'before' => $old,
                        'after' => $new,
                        'changes' => [
                            'regulasi' => [
                                'old' => $old['regulasi'] ?? null,
                                'new' => $new['regulasi'] ?? null
                            ],
                            'no_sampel' => [
                                'old' => $old["no_sampel"],
                                'new' => $new["no_sampel"]
                            ]
                        ],
                        'type' => 'no_sampel_dan_regulasi'
                    ];

                    $matchedLama[$old["no_sampel"]] = true;
                    $matchedBaru[$new["no_sampel"]] = true;
                    $foundMatch = true;
                    break;
                }
            }

            // Tidak ada match (penambahan data baru)
            if (!$foundMatch) {
                $penambahan_data[] = $new;
                $matchedBaru[$new["no_sampel"]] = true;
            }
        }

        // Data tidak relevan (penghapusan data lama)
        foreach ($data_old as $old) {
            if (!isset($matchedLama[$old["no_sampel"]])) {
                $pengurangan_data[] = $old;
            }
        }

        // ======================= Format data untuk disimpan ke DB =======================
        $no_sampel_changes = array_filter($perubahan_data, function ($item) {
            return in_array($item['type'], ['no_sampel', 'no_sampel_dan_regulasi']);
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
                    'no_order' => $this->no_order,
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
            }
        }

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