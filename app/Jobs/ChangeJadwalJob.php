<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use App\Models\{QuotationKontrakH, QuotationKontrakD, QuotationNonKontrak};
use App\Models\{SamplingPlan, Jadwal};
use Carbon\Carbon;

class ChangeJadwalJob extends Job
{
    protected $payload;
    protected $mode;
    protected $no_qt;
    protected $type_qt;

    public function __construct($payload, $mode, $no_qt, $type_qt)
    {
        $this->payload = $payload;
        $this->mode = $mode;
        $this->no_qt = $no_qt;
        $this->type_qt = $type_qt;
    }

    public function handle()
    {
        $payload = $this->payload;
        $mode = $this->mode;
        $no_qt = $this->no_qt;
        $type_qt = $this->type_qt;

        if ($type_qt === 'kontrak') {
            if ($mode === 'update') {
                $this->perubahanJadwalKontrakUpdate($payload, $no_qt);
            } else if ($mode === 'revisi') {
                $this->perubahanJadwalKontrakRevisi($payload, $no_qt);
            }
        } else if ($type_qt === 'non_kontrak') {
            if ($mode === 'update') {
                $this->perubahanJadwalNonKontrakUpdate($no_qt);
            } else if ($mode === 'revisi') {
                $this->perubahanJadwalNonKontrakRevisi($no_qt);
            }
        }
    }

    private function perubahanJadwalNonKontrakUpdate($no_qt)
    {
        $logging = [];

        $logging[] = "Start Pembacaan Perubahan Jadwal: " . $no_qt;

        $dataJadwal = Jadwal::where([
            ['no_quotation', $no_qt],
            ['is_active', true],
        ])->get();

        $qtBaru = QuotationNonKontrak::where('no_document', $no_qt)->first();

        $oldSampling = $dataJadwal->map(fn($l) => json_decode($l->kategori, true) ?? [])->flatten()->unique()->values()->toArray();

        $cek = $this->formatEntry([], json_decode($qtBaru->data_pendukung_sampling, true));
        $cek['lama'] = $oldSampling;
        $lamaNorm = $this->normalize($cek['lama']);
        $baruNorm = $this->normalize($cek['baru']);

        if ($lamaNorm !== $baruNorm) {
            $pengurangan = array_values(array_diff($lamaNorm, $baruNorm));
            if (!empty($pengurangan)) {
                Jadwal::where([
                    ['no_quotation', $no_qt],
                    ['is_active', true],
                ])->get()->each(function($jadwal) use ($pengurangan) {
                    $kategori = array_diff(json_decode($jadwal->kategori, true) ?? [], $pengurangan);
                    
                    if(!empty($kategori)) {
                        $jadwal->kategori = json_encode(array_values($kategori));
                    } else {
                        // $jadwal->is_active = false;
                    }
                    $jadwal->save();
                });
                $logging[] = "Pengurangan Kategori: " . json_encode($pengurangan);
            }
        }

        if (!empty($logging)) {
            $messages = collect($logging)->flatten(1)->values()->toArray();
            Log::channel('perubahan_jadwal')->info($messages);
        }
    }

    private function perubahanJadwalNonKontrakRevisi($no_qt)
    {
        $no_qt_lama = $no_qt['old'];
        $no_qt_baru = $no_qt['new'];
        $logging = [];

        $logging[] = "Start Pembacaan Perubahan Jadwal: " . $no_qt_lama . " ke " .  $no_qt_baru;

        $qtLama = QuotationNonKontrak::where('no_document', $no_qt_lama)->first();
        $qtBaru = QuotationNonKontrak::where('no_document', $no_qt_baru)->first();

        $cek = $this->formatEntry(json_decode($qtLama->data_pendukung_sampling, true), json_decode($qtBaru->data_pendukung_sampling, true));
        
        $lamaNorm = $this->normalize($cek['lama']);
        $baruNorm = $this->normalize($cek['baru']);

        if ($lamaNorm != $baruNorm) {
            $pengurangan = array_values(array_diff($lamaNorm, $baruNorm));
            if (!empty($pengurangan)) {
                Jadwal::where([
                    ['no_quotation', $no_qt_lama],
                    ['is_active', true],
                ])->get()->each(function($jadwal) use ($pengurangan) {
                    $kategori = array_diff(json_decode($jadwal->kategori, true) ?? [], $pengurangan);
                    
                    if(!empty($kategori)) {
                        $jadwal->kategori = json_encode(array_values($kategori));
                    } else {
                        // $jadwal->is_active = false;
                    }
                    $jadwal->save();
                });

                $logging[] = "Pengurangan Kategori: " . json_encode($pengurangan);
            }
        }

        SamplingPlan::where('no_quotation', $no_qt_lama)
            ->update([
                'no_quotation' => $qtBaru->no_document,
                'quotation_id' => $qtBaru->id 
            ]);

        Jadwal::where('no_quotation', $no_qt_lama)
            ->update([
                'no_quotation' => $qtBaru->no_document,
                'nama_perusahaan' => strtoupper(trim(htmlspecialchars_decode($qtBaru->nama_perusahaan)))
            ]);

        if (!empty($logging)) {
            $messages = collect($logging)->flatten(1)->values()->toArray();
            Log::channel('perubahan_jadwal')->info($messages);
        }
    }
    
    private function perubahanJadwalKontrakUpdate($payload, $no_qt)
    {
        $logging = [];
        $logging[] = "Start Pembacaan Perubahan Jadwal: $no_qt";

        $dataQt = QuotationKontrakD::join('request_quotation_kontrak_H', 'request_quotation_kontrak_D.id_request_quotation_kontrak_h', '=', 'request_quotation_kontrak_H.id')
            ->where('request_quotation_kontrak_H.no_document', $no_qt)
            ->select('request_quotation_kontrak_D.*', 'request_quotation_kontrak_H.nama_perusahaan')
            ->get()
            ->keyBy('periode_kontrak');

        $dataJadwal = Jadwal::where([
            ['no_quotation', $no_qt],
            ['is_active', true],
        ])->get()->groupBy('periode');

        $oldPeriodes = $dataJadwal->keys()->toArray();
        $newPeriodes = $dataQt->keys()->toArray();
        $old = array_map(fn($x) => $x->old, $payload) ?? [];
        $prosesPerubahan = array_values(array_diff($oldPeriodes, $old));
        
        // Loop utama payload
        foreach ($payload as $item) {
            $lama = isset($dataJadwal[$item->old]) ? $dataJadwal[$item->old] : null;
            $baru = isset($dataQt[$item->new]) ? $dataQt[$item->new] : null;
            if (!$lama || !$baru) continue;

            $oldSampling = collect($lama)->map(fn($l) => json_decode($l->kategori, true) ?? [])->flatten()->unique()->values()->toArray();
            $newSampling = $this->extractSampling($baru->data_pendukung_sampling);
            $cek = $this->formatEntry([], $newSampling);
            $cek['lama'] = $oldSampling;
            $lamaNorm = $this->normalize($cek['lama']);
            $baruNorm = $this->normalize($cek['baru']);
            
            // Update periode bulk
            SamplingPlan::where([
                ['no_quotation', $no_qt],
                ['periode_kontrak', $item->old],
                ['is_active', true],
            ])->update(['periode_kontrak' => $item->new]);
            Jadwal::where([
                ['no_quotation', $no_qt],
                ['periode', $item->old],
                ['is_active', true],
            ])->update(['periode' => $item->new]);

            // Update kategori sample jika berkurang
            if ($lamaNorm !== $baruNorm) {
                $pengurangan = array_values(array_diff($lamaNorm, $baruNorm));
                if (!empty($pengurangan)) {
                    Jadwal::where([
                        ['no_quotation', $no_qt],
                        ['periode', $item->new],
                        ['is_active', true],
                    ])->get()->each(function ($jadwal) use ($pengurangan) {

                        $kategori = array_diff(json_decode($jadwal->kategori, true) ?? [], $pengurangan);
                        if(!empty($kategori)) {
                            $jadwal->kategori = json_encode(array_values($kategori));
                        } else {
                            // $jadwal->is_active = false;
                        }
                        $jadwal->save();
                    });
                    $logging[] = "Pengurangan Kategori: " . json_encode($pengurangan) . " Pada Periode " . $item->old . " dan dipindahkan ke periode " . $item->new;
                }

            } else {
                $logging[] = "Tidak ada perubahan Kategori,  Periode " . $item->old . " dipindahkan ke periode " . $item->new;
            }

            // Log perubahan periode
            DB::table('perubahan_periode_quotation')->insert([
                'no_quotation' => $no_qt,
                'periode_awal' => $item->old,
                'periode_revisi' => $item->new,
                'waktu_revisi' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
        }

        // Proses by prosesPerubahan
        foreach ($prosesPerubahan as $detail) {
            $lama = isset($dataJadwal[$detail]) ? $dataJadwal[$detail] : null;
            $baru = isset($dataQt[$detail]) ? $dataQt[$detail] : null;
            if (!$lama || !$baru) continue;

            $oldSampling = collect($lama)->map(fn($l) => json_decode($l->kategori, true) ?? [])->flatten()->unique()->values()->toArray();
            $newSampling = $this->extractSampling($baru->data_pendukung_sampling);
            $cek = $this->formatEntry([], $newSampling);
            $cek['lama'] = $oldSampling;
            $lamaNorm = $this->normalize($cek['lama']);
            $baruNorm = $this->normalize($cek['baru']);

            if ($lamaNorm !== $baruNorm) {
                $pengurangan = array_values(array_diff($lamaNorm, $baruNorm));
                if (!empty($pengurangan)) {
                    Jadwal::where([
                        ['no_quotation', $no_qt],
                        ['periode', $detail],
                        ['is_active', true],
                    ])->get()->each(function($jadwal) use ($pengurangan) {
                        $kategori = array_diff(json_decode($jadwal->kategori, true) ?? [], $pengurangan);
                        if(!empty($kategori)) {
                            $jadwal->kategori = json_encode(array_values($kategori));
                        } else {
                            // $jadwal->is_active = false;
                        }
                        $jadwal->save();
                    });

                    $logging[] = "Pengurangan Kategori: " . json_encode($pengurangan) . "Pada Periode " . $detail;
                }
            }
        }

        // Bulk update deaktif sampling yang tidak dipakai lagi
        $inactiveIds = SamplingPlan::where('no_quotation', $no_qt)
            ->whereNotIn('periode_kontrak', $newPeriodes)
            ->where('is_active', true)
            ->pluck('id');

        if ($inactiveIds->isNotEmpty()) {
            SamplingPlan::whereIn('id', $inactiveIds)->update([
                'is_active' => false,
                'status' => false,
                'status_jadwal' => 'cancel',
            ]);
            Jadwal::where('no_quotation', $no_qt)
                ->whereIn('id_sampling', $inactiveIds)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'canceled_by' => 'system',
                ]);
            
            $logging[] = "Proses Final, Mematikan Jadwal yang tidak valid: " . json_encode($inactiveIds);
        }

        if (!empty($logging)) {
            $messages = collect($logging)->flatten(1)->values()->toArray();
            Log::channel('perubahan_jadwal')->info($messages);
        }
    }

    private function perubahanJadwalKontrakRevisi($payload, $no_qt)
    {
        $no_qt_lama = $no_qt['old'];
        $no_qt_baru = $no_qt['new'];
        $logging = [];

        $logging[] = "Start Pembacaan Perubahan Jadwal: " . $no_qt_lama . " ke " .  $no_qt_baru;

        $qtLama = QuotationKontrakD::join('request_quotation_kontrak_H', 'request_quotation_kontrak_D.id_request_quotation_kontrak_h', '=', 'request_quotation_kontrak_H.id')
            ->where('request_quotation_kontrak_H.no_document', $no_qt_lama)
            ->select('request_quotation_kontrak_D.*')
            ->get()
            ->keyBy('periode_kontrak');

        $qtBaru = QuotationKontrakD::join('request_quotation_kontrak_H', 'request_quotation_kontrak_D.id_request_quotation_kontrak_h', '=', 'request_quotation_kontrak_H.id')
            ->where('request_quotation_kontrak_H.no_document', $no_qt_baru)
            ->select('request_quotation_kontrak_D.*', 'request_quotation_kontrak_H.nama_perusahaan')
            ->get()
            ->keyBy('periode_kontrak');

        $oldPeriodes = $qtLama->keys()->toArray();
        $newPeriodes = $qtBaru->keys()->toArray();
        $old = array_map(fn($x) => $x->old, $payload) ?? [];
        $prosesPerubahan = array_values(array_diff($oldPeriodes, $old));

        foreach ($payload as $item) {
            $lama = isset($qtLama[$item->old]) ? $qtLama[$item->old] : null;
            $baru = isset($qtBaru[$item->new]) ? $qtBaru[$item->new] : null;
            if (!$lama || !$baru) continue;

            $oldSampling = $this->extractSampling($lama->data_pendukung_sampling);
            $newSampling = $this->extractSampling($baru->data_pendukung_sampling);
            $cek = $this->formatEntry($oldSampling, $newSampling);
            $lamaNorm = $this->normalize($cek['lama']);
            $baruNorm = $this->normalize($cek['baru']);

            SamplingPlan::where([
                ['no_quotation', $no_qt_lama],
                ['periode_kontrak', $item->old],
                ['is_active', true],
            ])->update(['periode_kontrak' => $item->new]);

            Jadwal::where([
                ['no_quotation', $no_qt_lama],
                ['periode', $item->old],
                ['is_active', true],
            ])->update(['periode' => $item->new]);

            if ($lamaNorm !== $baruNorm) {
                $pengurangan = array_values(array_diff($lamaNorm, $baruNorm));
                if (!empty($pengurangan)) {
                    Jadwal::where([
                        ['no_quotation', $no_qt_lama],
                        ['periode', $item->new],
                        ['is_active', true],
                    ])->get()->each(function($jadwal) use ($pengurangan) {
                        $kategori = array_diff(json_decode($jadwal->kategori, true) ?? [], $pengurangan);
                        if(!empty($kategori)) {
                            $jadwal->kategori = json_encode(array_values($kategori));
                        } else {
                            // $jadwal->is_active = false;
                        }
                        $jadwal->save();
                    });
                    $logging[] = "Pengurangan Kategori: " . json_encode($pengurangan) . " Pada Periode " . $item->old . " dan dipindahkan ke periode " . $item->new;
                }
            } else {
                $logging[] = "Tidak ada perubahan Kategori,  Periode " . $item->old . " dipindahkan ke periode " . $item->new;
            }

            DB::table('perubahan_periode_quotation')->insert([
                'no_quotation' => $no_qt_baru,
                'periode_awal' => $item->old,
                'periode_revisi' => $item->new,
                'waktu_revisi' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
        }

        foreach ($prosesPerubahan as $detail) {
            $lama = isset($qtLama[$detail]) ? $qtLama[$detail] : null;
            $baru = isset($qtBaru[$detail]) ? $qtBaru[$detail] : null;
            if (!$lama || !$baru) continue;

            $oldSampling = $this->extractSampling($lama->data_pendukung_sampling);
            $newSampling = $this->extractSampling($baru->data_pendukung_sampling);
            $cek = $this->formatEntry($oldSampling, $newSampling);
            $lamaNorm = $this->normalize($cek['lama']);
            $baruNorm = $this->normalize($cek['baru']);

            if ($lamaNorm !== $baruNorm) {
                $pengurangan = array_values(array_diff($lamaNorm, $baruNorm));
                if (!empty($pengurangan)) {
                    Jadwal::where([
                        ['no_quotation', $no_qt_lama],
                        ['periode', $detail],
                        ['is_active', true],
                    ])->get()->each(function($jadwal) use ($pengurangan) {
                        $kategori = array_diff(json_decode($jadwal->kategori, true) ?? [], $pengurangan);
                        if(!empty($kategori)) {
                            $jadwal->kategori = json_encode(array_values($kategori));
                        } else {
                            // $jadwal->is_active = false;
                        }
                        $jadwal->save();
                    });

                    $logging[] = "Pengurangan Kategori: " . json_encode($pengurangan) . "Pada Periode " . $detail;
                }
            }
        }

        // Global Update no dokumen SamplingPlan dan Jadwal
        $firstBaru = $qtBaru->first();
        SamplingPlan::where('no_quotation', $no_qt_lama)->update([
            'no_quotation' => $no_qt_baru,
            'quotation_id' => $firstBaru ? $firstBaru->id_request_quotation_kontrak_h : null,
        ]);
        Jadwal::where('no_quotation', $no_qt_lama)->update([
            'no_quotation' => $no_qt_baru,
            'nama_perusahaan' => $firstBaru ? strtoupper(trim(htmlspecialchars_decode($firstBaru->nama_perusahaan))) : null,
        ]);

        // Bulk update deaktif sampling yang tidak dipakai lagi
        $inactiveIds = SamplingPlan::where('no_quotation', $no_qt_baru)
            ->whereNotIn('periode_kontrak', $newPeriodes)
            ->where('is_active', true)
            ->pluck('id');
        if ($inactiveIds->isNotEmpty()) {
            SamplingPlan::whereIn('id', $inactiveIds)->update([
                'is_active' => false,
                'status' => false,
                'status_jadwal' => 'cancel',
            ]);
            Jadwal::where('no_quotation', $no_qt_baru)
                ->whereIn('id_sampling', $inactiveIds)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'canceled_by' => 'system',
                ]);

            $logging[] = "Proses Final, Mematikan Jadwal yang tidak valid: " . json_encode($inactiveIds);
        }

        if (!empty($logging)) {
            $messages = collect($logging)->flatten(1)->values()->toArray();
            Log::channel('perubahan_jadwal')->info($messages);
        }
    }

    private function extractSampling($json)
    {
        $result = [];
        
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) return [];
        
        foreach ($decoded as $detail) {
            if (!empty($detail['data_sampling'])) {
                $result = array_merge($result, (array)$detail['data_sampling']);
            }
        }
        
        return $result;
    }

    private function normalize(array $arr): array
    {
        sort($arr);
        return array_values(array_map('trim', $arr));
    }

    private function formatEntry($dataLama, $dataBaru){
        $dataFormatLama = array_merge(...array_map(function ($item) {
            $kategori = trim(explode('-', $item['kategori_2'])[1]);
            return array_map(function ($xx) use ($kategori) {
                return $kategori . ' - ' . array_key_first($xx);
            }, $item['penamaan_titik']);
        }, $dataLama));
        
        $dataFormatBaru = array_merge(...array_map(function ($item) {
            $kategori = trim(explode('-', $item['kategori_2'])[1]);
            return array_map(function ($xx) use ($kategori) {
                return $kategori . ' - ' . array_key_first($xx);
            }, $item['penamaan_titik']);
        }, $dataBaru));

        return [
            'lama' => $dataFormatLama,
            'baru' => $dataFormatBaru
        ];
    }

}
