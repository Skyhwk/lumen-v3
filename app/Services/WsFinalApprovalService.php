<?php

namespace App\Services;

use App\Models\DataLapanganAir;
use App\Models\OrderDetail;
use App\Models\Parameter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WsFinalApprovalService
{
    private static $parameterRegulationCache = [];

    private const PARAMETER_SOURCES = [
        \App\Models\Colorimetri::class,
        \App\Models\DebuPersonalHeader::class,
        \App\Models\DirectLainHeader::class,
        \App\Models\DustFallHeader::class,
        \App\Models\EmisiCerobongHeader::class,
        \App\Models\ErgonomiHeader::class,
        \App\Models\GetaranHeader::class,
        \App\Models\Gravimetri::class,
        \App\Models\IklimHeader::class,
        \App\Models\IsokinetikHeader::class,
        \App\Models\KebisinganHeader::class,
        \App\Models\LingkunganHeader::class,
        \App\Models\MedanLmHeader::class,
        \App\Models\MicrobioHeader::class,
        \App\Models\PartikulatHeader::class,
        \App\Models\PencahayaanHeader::class,
        \App\Models\SinarUvHeader::class,
        \App\Models\Subkontrak::class,
        \App\Models\SwabTestHeader::class,
        \App\Models\Titrimetri::class,
    ];

    public static function syncParameter(Model $source): void
    {
        $noSampel = $source->getAttribute('no_sampel');
        if (!$noSampel || !$source->getAttribute('parameter')) {
            return;
        }

        $orderDetail = self::findOrderDetail($noSampel);

        if (!$orderDetail) {
            return;
        }

        // Bypass untuk subkategori Udara Lingkungan Kerja, Udara Lingkungan Hidup / Udara Ambient, dan Emisi Sumber Tidak Bergerak
        if ($orderDetail->kategori_3 && (
            str_contains(strtolower($orderDetail->kategori_3), 'lingkungan kerja') || 
            str_contains(strtolower($orderDetail->kategori_3), 'lingkungan hidup') || 
            str_contains(strtolower($orderDetail->kategori_3), 'ambient') || 
            str_contains(strtolower($orderDetail->kategori_3), 'tidak bergerak')
        )) {
            return;
        }

        $headerId = self::upsertHeader($orderDetail);
        if ($headerId === 0) {
            return;
        }
        $parameterLab = self::stringValue($source->getAttribute('parameter'));

        if (self::isParameterSource($source)) {
            if ($parameterLab !== null) {
                $parameterRegulasi = self::findParameterRegulasi(
                    $orderDetail,
                    $parameterLab,
                    $source
                ) ?: '';

                DB::table('ws_final_approval_detail')->updateOrInsert([
                    'ws_final_approval_header_id' => $headerId,
                    'parameter_lab' => self::limit($parameterLab, 70),
                ], [
                    'no_sampel' => $noSampel,
                    'parameter_regulasi' => self::limit($parameterRegulasi, 100),
                    'hasil' => self::limit(self::extractResult($source), 50),
                ]);
            }
        } else {
            self::deleteParameterDetail($headerId, $parameterLab);
            return;
        }

        self::refreshApprovalStatus($orderDetail);
    }

    public static function rejectParameter(Model $source): void
    {
        $noSampel = self::stringValue($source->getAttribute('no_sampel'));
        $parameterLab = self::stringValue($source->getAttribute('parameter'));

        if ($noSampel === null || $parameterLab === null) {
            return;
        }

        $orderDetail = self::findOrderDetail($noSampel);
        if (!$orderDetail) {
            return;
        }

        self::deleteParameterDetail(self::upsertHeader($orderDetail), $parameterLab);
    }

    public static function finalizeSample(OrderDetail $orderDetail, bool $approved, ?string $approvedBy = null): void
    {
        // Bypass untuk subkategori Udara Lingkungan Kerja, Udara Lingkungan Hidup / Udara Ambient, dan Emisi Sumber Tidak Bergerak
        if ($orderDetail->kategori_3 && (
            str_contains(strtolower($orderDetail->kategori_3), 'lingkungan kerja') || 
            str_contains(strtolower($orderDetail->kategori_3), 'lingkungan hidup') || 
            str_contains(strtolower($orderDetail->kategori_3), 'ambient') || 
            str_contains(strtolower($orderDetail->kategori_3), 'tidak bergerak')
        )) {
            return;
        }

        $headerId = self::upsertHeader($orderDetail);
        if ($headerId === 0) {
            return;
        }

        if (!$approved) {
            DB::table('ws_final_approval_detail')
                ->where('ws_final_approval_header_id', $headerId)
                ->delete();

            DB::table('ws_final_approval_header')
                ->where('id', $headerId)
                ->update([
                    'is_approved' => 0,
                    'approved_by' => null,
                    'approved_at' => null,
                ]);

            return;
        }

        self::syncApprovedParameters($orderDetail->no_sampel);
        self::syncAirFieldParameters($orderDetail);

        self::refreshApprovalStatus($orderDetail, $approvedBy);
    }

    public static function parameterSourceClasses(): array
    {
        return self::PARAMETER_SOURCES;
    }

    public static function finalizeLhpFromLhpTables(OrderDetail $orderDetail, bool $approved, ?string $approvedBy = null): void
    {
        $headerId = self::upsertHeader($orderDetail);

        if (!$approved) {
            DB::table('ws_final_approval_detail')
                ->where('ws_final_approval_header_id', $headerId)
                ->delete();

            DB::table('ws_final_approval_header')
                ->where('id', $headerId)
                ->update([
                    'is_approved' => 0,
                    'approved_by' => null,
                    'approved_at' => null,
                ]);

            return;
        }

        $orderDetails = self::lhpOrderDetails($orderDetail);

        // Eager load semua relasi LHP untuk performa maksimal (menghindari N+1 query)
        $orderDetails->load([
            'lhps_air', 'lhps_padatan', 'lhps_emisi', 'lhps_emisi_c', 'lhps_emisi_isokinetik',
            'lhps_getaran', 'lhps_kebisingan', 'lhps_kebisingan_personal', 'lhps_ling',
            'lhps_medanlm', 'lhps_pencahayaan', 'lhps_sinaruv', 'lhps_iklim', 'lhps_swab_udara',
            'lhps_microbiologi'
        ]);

        $lhpRelations = [
            'lhps_air' => ['detail' => 'lhpsAirDetail', 'custom' => 'lhpsAirCustom'],
            'lhps_padatan' => ['detail' => 'lhpsPadatanDetail', 'custom' => 'lhpsPadatanCustom'],
            'lhps_emisi' => ['detail' => 'lhpsEmisiDetail', 'custom' => null],
            'lhps_emisi_c' => ['detail' => 'lhpsEmisiCDetail', 'custom' => null],
            'lhps_emisi_isokinetik' => ['detail' => 'lhpsEmisiIsokinetikDetail', 'custom' => null],
            'lhps_getaran' => ['detail' => 'lhpsGetaranDetail', 'custom' => null],
            'lhps_kebisingan' => ['detail' => 'lhpsKebisinganDetail', 'custom' => null],
            'lhps_kebisingan_personal' => ['detail' => 'lhpsKebisinganPersonalDetail', 'custom' => null],
            'lhps_ling' => ['detail' => 'lhpsLingDetail', 'custom' => null],
            'lhps_medanlm' => ['detail' => 'lhpsMedanLMDetail', 'custom' => null],
            'lhps_pencahayaan' => ['detail' => 'lhpsPencahayaanDetail', 'custom' => null],
            'lhps_sinaruv' => ['detail' => 'lhpsSinarUVDetail', 'custom' => null],
            'lhps_iklim' => ['detail' => 'lhpsIklimDetail', 'custom' => null],
            'lhps_swab_udara' => ['detail' => 'lhpsSwabTesDetail', 'custom' => null],
            'lhps_microbiologi' => ['detail' => 'lhpsMicrobiologiDetailSampel', 'custom' => null],
        ];

        $orderDetails->each(function (OrderDetail $detail) use ($headerId, $lhpRelations) {
            foreach ($lhpRelations as $relationName => $config) {
                $lhpHeader = $detail->{$relationName};
                if (!$lhpHeader) {
                    continue;
                }

                $detailRelation = $config['detail'];
                $lhpDetails = $lhpHeader->{$detailRelation} ?? collect();
                $insertedParams = [];

                foreach ($lhpDetails as $lhpDetail) {
                    // Filter berdasarkan no_sampel jika kolom no_sampel tersedia di data detail
                    $lhpDetailNoSampel = $lhpDetail->no_sampel ?? null;
                    if ($lhpDetailNoSampel !== null && trim((string)$lhpDetailNoSampel) !== '' && trim((string)$lhpDetailNoSampel) !== trim((string)$detail->no_sampel)) {
                        continue;
                    }

                    $parameterLab = $lhpDetail->parameter_lab 
                        ?? $lhpDetail->parameter 
                        ?? $lhpDetail->param 
                        ?? $lhpDetail->parameter_uji 
                        ?? null;

                    if ($parameterLab === null || trim((string)$parameterLab) === '') {
                        $paramsArray = self::arrayValue($detail->parameter);
                        if (!empty($paramsArray)) {
                            $firstParam = $paramsArray[0];
                            if (str_contains($firstParam, ';')) {
                                $parameterLab = explode(';', $firstParam)[1];
                            } else {
                                $parameterLab = $firstParam;
                            }
                        }
                    }

                    $hasil = null;
                    if (isset($lhpDetail->hasil_terkoreksi) && trim((string)$lhpDetail->hasil_terkoreksi) !== '' && trim((string)$lhpDetail->hasil_terkoreksi) !== '-') {
                        $hasil = $lhpDetail->hasil_terkoreksi;
                    } elseif (isset($lhpDetail->terkoreksi) && trim((string)$lhpDetail->terkoreksi) !== '' && trim((string)$lhpDetail->terkoreksi) !== '-') {
                        $hasil = $lhpDetail->terkoreksi;
                    } elseif (isset($lhpDetail->hasil_uji) && trim((string)$lhpDetail->hasil_uji) !== '' && trim((string)$lhpDetail->hasil_uji) !== '-') {
                        $hasil = $lhpDetail->hasil_uji;
                    } elseif (isset($lhpDetail->C) && trim((string)$lhpDetail->C) !== '' && trim((string)$lhpDetail->C) !== '-') {
                        $hasil = $lhpDetail->C;
                    } elseif (isset($lhpDetail->hasil) && trim((string)$lhpDetail->hasil) !== '' && trim((string)$lhpDetail->hasil) !== '-') {
                        $hasil = $lhpDetail->hasil;
                    } else {
                        $hasil = $lhpDetail->hasil_terkoreksi 
                            ?? $lhpDetail->terkoreksi 
                            ?? $lhpDetail->hasil_uji 
                            ?? $lhpDetail->C 
                            ?? $lhpDetail->hasil 
                            ?? '';
                    }

                    if ($hasil !== null && $hasil !== '') {
                        $decoded = json_decode($hasil, true);
                        if (is_array($decoded)) {
                            $hasil = reset($decoded);
                        }
                    } else {
                        $hasil = '';
                    }

                    if (!$parameterLab) {
                        continue;
                    }

                    $parameterRegulasi = self::findParameterRegulasi($detail, $parameterLab) ?: '';

                    DB::table('ws_final_approval_detail')->updateOrInsert([
                        'ws_final_approval_header_id' => $headerId,
                        'no_sampel' => $detail->no_sampel,
                        'parameter_lab' => self::limit($parameterLab, 70),
                    ], [
                        'no_sampel' => $detail->no_sampel,
                        'parameter_regulasi' => self::limit($parameterRegulasi, 100),
                        'hasil' => self::limit($hasil, 50),
                    ]);

                    $insertedParams[strtolower(trim($parameterLab))] = true;
                }

                if (!empty($config['custom'])) {
                    $customRelation = $config['custom'];
                    $lhpCustoms = $lhpHeader->{$customRelation} ?? collect();

                    foreach ($lhpCustoms as $lhpCustom) {
                        // Filter berdasarkan no_sampel jika kolom no_sampel tersedia di data kustom
                        $lhpCustomNoSampel = $lhpCustom->no_sampel ?? null;
                        if ($lhpCustomNoSampel !== null && trim((string)$lhpCustomNoSampel) !== '' && trim((string)$lhpCustomNoSampel) !== trim((string)$detail->no_sampel)) {
                            continue;
                        }

                        $parameterLab = $lhpCustom->parameter_lab 
                            ?? $lhpCustom->parameter 
                            ?? $lhpCustom->param 
                            ?? $lhpCustom->parameter_uji 
                            ?? null;

                        if ($parameterLab === null || trim((string)$parameterLab) === '') {
                            $paramsArray = self::arrayValue($detail->parameter);
                            if (!empty($paramsArray)) {
                                $firstParam = $paramsArray[0];
                                if (str_contains($firstParam, ';')) {
                                    $parameterLab = explode(';', $firstParam)[1];
                                } else {
                                    $parameterLab = $firstParam;
                                }
                            }
                        }

                        $hasil = null;
                        if (isset($lhpCustom->hasil_terkoreksi) && trim((string)$lhpCustom->hasil_terkoreksi) !== '' && trim((string)$lhpCustom->hasil_terkoreksi) !== '-') {
                            $hasil = $lhpCustom->hasil_terkoreksi;
                        } elseif (isset($lhpCustom->terkoreksi) && trim((string)$lhpCustom->terkoreksi) !== '' && trim((string)$lhpCustom->terkoreksi) !== '-') {
                            $hasil = $lhpCustom->terkoreksi;
                        } elseif (isset($lhpCustom->hasil_uji) && trim((string)$lhpCustom->hasil_uji) !== '' && trim((string)$lhpCustom->hasil_uji) !== '-') {
                            $hasil = $lhpCustom->hasil_uji;
                        } elseif (isset($lhpCustom->C) && trim((string)$lhpCustom->C) !== '' && trim((string)$lhpCustom->C) !== '-') {
                            $hasil = $lhpCustom->C;
                        } elseif (isset($lhpCustom->hasil) && trim((string)$lhpCustom->hasil) !== '' && trim((string)$lhpCustom->hasil) !== '-') {
                            $hasil = $lhpCustom->hasil;
                        } else {
                            $hasil = $lhpCustom->hasil_terkoreksi 
                                ?? $lhpCustom->terkoreksi 
                                ?? $lhpCustom->hasil_uji 
                                ?? $lhpCustom->C 
                                ?? $lhpCustom->hasil 
                                ?? '';
                        }

                        if ($hasil !== null && $hasil !== '') {
                            $decoded = json_decode($hasil, true);
                            if (is_array($decoded)) {
                                $hasil = reset($decoded);
                            }
                        } else {
                            $hasil = '';
                        }

                        if (!$parameterLab) {
                            continue;
                        }

                        // Jika nama parameter sudah dimasukkan dari detail utama, lewati data custom ini
                        $paramKey = strtolower(trim($parameterLab));
                        if (isset($insertedParams[$paramKey])) {
                            continue;
                        }

                        $parameterRegulasi = self::findParameterRegulasi($detail, $parameterLab) ?: '';

                        DB::table('ws_final_approval_detail')->updateOrInsert([
                            'ws_final_approval_header_id' => $headerId,
                            'no_sampel' => $detail->no_sampel,
                            'parameter_lab' => self::limit($parameterLab, 70),
                        ], [
                            'no_sampel' => $detail->no_sampel,
                            'parameter_regulasi' => self::limit($parameterRegulasi, 100),
                            'hasil' => self::limit($hasil, 50),
                        ]);
                    }
                }
            }

            // Penanganan Ergonomi
            $isErgonomi = false;
            if ($detail->kategori_3 && str_contains(strtolower($detail->kategori_3), 'ergonomi')) {
                $isErgonomi = true;
            } elseif ($detail->kategori_2 && str_contains(strtolower($detail->kategori_2), 'ergonomi')) {
                $isErgonomi = true;
            }

            if ($isErgonomi) {
                $paramsArray = self::arrayValue($detail->parameter);
                
                // Fallback jika array parameter di order detail kosong, coba cari di lhps_ergonomi_header
                if (empty($paramsArray) && class_exists(\App\Models\LhpsErgonomiHeader::class)) {
                    $ergonomiHeader = \App\Models\LhpsErgonomiHeader::where('no_sampel', $detail->no_sampel)
                        ->where('is_active', true)
                        ->first();
                    if ($ergonomiHeader) {
                        $ergonomiDetails = $ergonomiHeader->lhpsErgonomiDetail ?? collect();
                        foreach ($ergonomiDetails as $eDetail) {
                            $paramName = $eDetail->parameter_lab ?? $eDetail->parameter ?? $eDetail->param ?? $eDetail->parameter_uji;
                            if ($paramName) {
                                $paramsArray[] = $paramName;
                            }
                        }
                    }
                }

                foreach ($paramsArray as $paramRaw) {
                    $parameterLab = $paramRaw;
                    if (str_contains($paramRaw, ';')) {
                        $parameterLab = explode(';', $paramRaw)[1];
                    }

                    if (!$parameterLab || trim((string)$parameterLab) === '') {
                        continue;
                    }

                    $parameterRegulasi = self::findParameterRegulasi($detail, $parameterLab) ?: '';
                    $hasil = 'Sudah dianalisa';

                    DB::table('ws_final_approval_detail')->updateOrInsert([
                        'ws_final_approval_header_id' => $headerId,
                        'no_sampel' => $detail->no_sampel,
                        'parameter_lab' => self::limit($parameterLab, 70),
                    ], [
                        'no_sampel' => $detail->no_sampel,
                        'parameter_regulasi' => self::limit($parameterRegulasi, 100),
                        'hasil' => self::limit($hasil, 50),
                    ]);
                }
            }
        });

        $orderDetails->each(function (OrderDetail $detail) use ($headerId) {
            self::syncAirFieldParametersToHeader($headerId, $detail);
        });

        self::refreshApprovalStatusFromDetailsFromHistory($headerId, $orderDetails, $orderDetail->cfr);
    }

    private static function refreshApprovalStatusFromDetailsFromHistory(int $headerId, $orderDetails, ?string $noLhp): void
    {
        $required = $orderDetails
            ->flatMap(function (OrderDetail $detail) {
                return collect(self::arrayValue($detail->parameter))
                    ->map(function ($parameter) use ($detail) {
                        return self::sampleParameterKey($detail->no_sampel, $parameter);
                    });
            })
            ->filter()
            ->unique()
            ->values();

        $approved = DB::table('ws_final_approval_detail')
            ->where('ws_final_approval_header_id', $headerId)
            ->get(['no_sampel', 'parameter_lab'])
            ->map(function ($detail) {
                return self::sampleParameterKey($detail->no_sampel, $detail->parameter_lab);
            })
            ->filter()
            ->unique()
            ->values();

        $complete = $required->isNotEmpty()
            && $required->diff($approved)->isEmpty();

        $approvedBy = null;
        $approvedAt = null;

        $sampleAndLhpNumbers = $orderDetails->pluck('no_sampel')
            ->merge($orderDetails->pluck('cfr'))
            ->filter()
            ->unique()
            ->all();

        $history = null;
        if (!empty($sampleAndLhpNumbers)) {
            $history = DB::table('history_app_reject')
                ->where(function($query) use ($sampleAndLhpNumbers) {
                    $query->whereIn('no_sampel', $sampleAndLhpNumbers)
                          ->orWhereIn('no_lhp', $sampleAndLhpNumbers);
                })
                ->where('status', 'approve')
                ->where('menu', 'like', '%WS Final%')
                ->orderBy('id', 'desc')
                ->first();
        }

        if ($history) {
            $approvedBy = $history->approved_by;
            $approvedAt = $history->approved_at;
        } elseif ($complete) {
            $approvedBy = 'system';
            $approvedAt = Carbon::now()->format('Y-m-d H:i:s');
        }

        DB::table('ws_final_approval_header')
            ->where('id', $headerId)
            ->update([
                'is_approved' => $complete ? 1 : 0,
                'approved_by' => self::limit($approvedBy, 100),
                'approved_at' => $approvedAt,
            ]);
    }

    private static function lhpOrderDetails(OrderDetail $orderDetail)
    {
        $cfr = $orderDetail->cfr;

        if ($cfr !== null && $cfr !== '') {
            return OrderDetail::where('cfr', $cfr)
                ->where('is_active', true)
                ->get();
        }

        return collect([$orderDetail]);
    }

    private static function sampleParameterKey(?string $noSampel, ?string $parameter): string
    {
        $noSampel = trim((string) $noSampel);
        $parameter = self::normalizeParameter($parameter);

        return "{$noSampel}#{$parameter}";
    }

    public static function finalizeLhp(OrderDetail $orderDetail, bool $approved, ?string $approvedBy = null): void
    {
        $headerId = self::upsertHeader($orderDetail);

        if (!$approved) {
            DB::table('ws_final_approval_detail')
                ->where('ws_final_approval_header_id', $headerId)
                ->delete();

            DB::table('ws_final_approval_header')
                ->where('id', $headerId)
                ->update([
                    'is_approved' => 0,
                    'approved_by' => null,
                    'approved_at' => null,
                ]);

            return;
        }

        $orderDetails = self::lhpOrderDetails($orderDetail);
        $detailsBySample = $orderDetails->keyBy('no_sampel');
        $samples = $detailsBySample->keys()->filter()->values();

        foreach (self::PARAMETER_SOURCES as $modelClass) {
            if (!class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass();
            $table = $model->getTable();

            if (!Schema::hasColumn($table, 'no_sampel') || !Schema::hasColumn($table, 'parameter')) {
                continue;
            }

            $approvalColumn = self::approvalColumn($table);
            if ($approvalColumn === null) {
                continue;
            }

            $query = $modelClass::whereIn('no_sampel', $samples)
                ->where($approvalColumn, 1);

            if (Schema::hasColumn($table, 'is_active')) {
                $query->where('is_active', true);
            }

            $query->get()->each(function (Model $source) use ($headerId, $detailsBySample) {
                self::upsertDetailFromSource($headerId, $detailsBySample, $source);
            });
        }

        $orderDetails->each(function (OrderDetail $detail) use ($headerId) {
            self::syncAirFieldParametersToHeader($headerId, $detail);
        });

        self::refreshApprovalStatusFromDetails($headerId, $orderDetails, $approvedBy);
    }

    public static function finalizeSamples(iterable $orderDetails, bool $approved, ?string $approvedBy = null): void
    {
        foreach ($orderDetails as $orderDetail) {
            if ($orderDetail instanceof OrderDetail) {
                self::finalizeSample($orderDetail, $approved, $approvedBy);
            }
        }
    }

    public static function progressBySample(iterable $orderDetails): array
    {
        $orderDetails = collect($orderDetails)
            ->filter(function ($orderDetail) {
                return $orderDetail instanceof OrderDetail && $orderDetail->no_sampel;
            })
            ->values();

        $noSampel = $orderDetails->pluck('no_sampel')->unique()->values();
        $approvedBySample = collect();

        $isLingkunganKerja = $orderDetails->contains(function ($od) {
            return $od->kategori_3 && (
                str_contains(strtolower($od->kategori_3), 'lingkungan kerja') || 
                str_contains(strtolower($od->kategori_3), 'lingkungan hidup') || 
                str_contains(strtolower($od->kategori_3), 'ambient') || 
                str_contains(strtolower($od->kategori_3), 'tidak bergerak')
            );
        });

        foreach (self::PARAMETER_SOURCES as $modelClass) {
            if (!class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass();
            $table = $model->getTable();

            if (!Schema::hasColumn($table, 'no_sampel') || !Schema::hasColumn($table, 'parameter')) {
                continue;
            }

            $approvalColumn = self::approvalColumn($table, $isLingkunganKerja);
            if ($approvalColumn === null) {
                continue;
            }

            $query = $modelClass::whereIn('no_sampel', $noSampel)
                ->where($approvalColumn, 1);

            if (Schema::hasColumn($table, 'is_active')) {
                $query->where('is_active', true);
            }

            $query->get(['no_sampel', 'parameter'])
                ->each(function (Model $source) use ($approvedBySample) {
                    $sample = $source->getAttribute('no_sampel');
                    $parameters = $approvedBySample->get($sample, []);
                    $parameters[] = $source->getAttribute('parameter');
                    $approvedBySample->put($sample, $parameters);
                });
        }

        return $orderDetails->mapWithKeys(function (OrderDetail $orderDetail) use ($approvedBySample) {
            $required = collect(self::arrayValue($orderDetail->parameter))
                ->map(function ($parameter) {
                    return self::normalizeParameter($parameter);
                })
                ->filter()
                ->unique()
                ->values();

            $approved = collect($approvedBySample->get($orderDetail->no_sampel, []))
                ->map(function ($parameter) {
                    return self::normalizeParameter($parameter);
                })
                ->filter()
                ->unique()
                ->values();

            $tested = $required->intersect($approved)->count();
            $total = $required->count();

            return [
                $orderDetail->no_sampel => [
                    'tested' => $tested,
                    'total' => $total,
                    'is_complete' => $total > 0 && $tested === $total,
                ],
            ];
        })->all();
    }

    public static function appendProgressAndFilter(iterable $rows, $request)
    {
        $rows = collect($rows)->values();
        $samples = $rows
            ->flatMap(function ($row) {
                return self::sampleNamesFromRow($row);
            })
            ->filter()
            ->unique()
            ->values();

        $orderDetails = OrderDetail::whereIn('no_sampel', $samples)
            ->where('is_active', true)
            ->get();

        $progressBySample = self::progressBySample($orderDetails);

        return $rows->filter(function ($row) use ($progressBySample, $request) {
            $summary = collect(self::sampleNamesFromRow($row))
                ->reduce(function ($carry, $sample) use ($progressBySample) {
                    $progress = $progressBySample[$sample] ?? [
                        'tested' => 0,
                        'total' => 0,
                        'is_complete' => false,
                    ];

                    $carry['tested'] += $progress['tested'];
                    $carry['total'] += $progress['total'];

                    return $carry;
                }, ['tested' => 0, 'total' => 0]);

            $row->progress = $summary['tested'] . ' / ' . $summary['total'];
            $isComplete = $summary['total'] > 0 && $summary['tested'] === $summary['total'];

            if ($request->uji_status === 'lengkap') {
                return $isComplete;
            }

            if ($request->uji_status === 'belum_lengkap') {
                return !$isComplete;
            }

            return true;
        })->values();
    }

    private static function sampleNamesFromRow($row): array
    {
        $value = data_get($row, 'no_sampel');

        if ($value === null || $value === '') {
            return [];
        }

        return collect(explode(',', (string) $value))
            ->map(fn ($sample) => trim($sample))
            ->filter()
            ->values()
            ->all();
    }

    private static function upsertHeader(OrderDetail $orderDetail, array $approval = []): int
    {
        if (!Schema::hasTable('ws_final_approval_header')) {
            return 0;
        }

        $row = array_merge([
            'no_order' => self::limit($orderDetail->no_order, 50),
            'no_lhp' => self::limit($orderDetail->cfr, 50),
            'periode' => self::limit($orderDetail->periode, 50),
            'parameter' => self::jsonValue($orderDetail->parameter),
            'kategori' => self::limit(self::categoryName($orderDetail->kategori_2), 70),
            'sub_kategori' => self::limit(self::categoryName($orderDetail->kategori_3), 70),
            'regulasi' => self::jsonValue($orderDetail->regulasi),
            'nama_titik' => self::limit($orderDetail->keterangan_1, 50),
        ], $approval);

        $columns = array_keys($row);
        $quotedColumns = implode(', ', array_map(function ($column) {
            return "`{$column}`";
        }, $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $updates = implode(', ', array_map(function ($column) {
            return "`{$column}` = VALUES(`{$column}`)";
        }, array_filter($columns, function ($column) {
            return $column !== 'no_lhp';
        })));

        DB::statement(
            "INSERT INTO `ws_final_approval_header` ({$quotedColumns})
             VALUES ({$placeholders})
             ON DUPLICATE KEY UPDATE
                `id` = LAST_INSERT_ID(`id`),
                {$updates}",
            array_values($row)
        );

        return (int) DB::connection()->getPdo()->lastInsertId();
    }

    private static function findOrderDetail(string $noSampel): ?OrderDetail
    {
        return OrderDetail::where('no_sampel', $noSampel)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    private static function deleteParameterDetail(int $headerId, ?string $parameterLab): void
    {
        if ($headerId === 0 || !Schema::hasTable('ws_final_approval_header')) {
            return;
        }

        if ($parameterLab !== null) {
            DB::table('ws_final_approval_detail')
                ->where('ws_final_approval_header_id', $headerId)
                ->where('parameter_lab', self::limit($parameterLab, 70))
                ->delete();
        }

        DB::table('ws_final_approval_header')
            ->where('id', $headerId)
            ->update([
                'is_approved' => 0,
                'approved_by' => null,
                'approved_at' => null,
            ]);
    }

    private static function isParameterSource(Model $source): bool
    {
        if (!$source->getAttribute('no_sampel') || !$source->getAttribute('parameter')) {
            return false;
        }

        if (array_key_exists('lhps', $source->getAttributes())) {
            return (int) $source->getAttribute('lhps') === 1;
        }

        return (int) $source->getAttribute('is_approve') === 1
            || (int) $source->getAttribute('is_approved') === 1;
    }

    private static function syncApprovedParameters(string $noSampel): void
    {
        foreach (self::PARAMETER_SOURCES as $modelClass) {
            if (!class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass();
            $table = $model->getTable();

            if (!Schema::hasColumn($table, 'no_sampel') || !Schema::hasColumn($table, 'parameter')) {
                continue;
            }

            $approvalColumn = self::approvalColumn($table);
            if ($approvalColumn === null) {
                continue;
            }

            $modelClass::where('no_sampel', $noSampel)
                ->where($approvalColumn, 1)
                ->get()
                ->each(function (Model $source) {
                    self::syncParameter($source);
                });
        }
    }

    private static function syncAirFieldParameters(OrderDetail $orderDetail): void
    {
        if (mb_strtolower((string) self::categoryName($orderDetail->kategori_2)) !== 'air') {
            return;
        }

        $fieldData = self::approvedAirFieldData($orderDetail->no_sampel);
        if (!$fieldData) {
            return;
        }

        $headerId = self::upsertHeader($orderDetail);
        if ($headerId === 0) {
            return;
        }

        foreach (self::airFieldParameterValues($orderDetail, $fieldData) as $parameterLab => $result) {
            DB::table('ws_final_approval_detail')->updateOrInsert([
                'ws_final_approval_header_id' => $headerId,
                'parameter_lab' => self::limit($parameterLab, 70),
            ], [
                'no_sampel' => $orderDetail->no_sampel,
                'parameter_regulasi' => self::limit(
                    self::findParameterRegulasi($orderDetail, $parameterLab) ?: '',
                    100
                ),
                'hasil' => self::limit($result, 50),
            ]);
        }
    }

    private static function refreshApprovalStatus(OrderDetail $orderDetail, ?string $approvedBy = null): void
    {
        $headerId = self::upsertHeader($orderDetail);
        if ($headerId === 0) {
            return;
        }
        $required = collect(self::arrayValue($orderDetail->parameter))
            ->map(function ($parameter) {
                return self::normalizeParameter($parameter);
            })
            ->filter()
            ->unique()
            ->values();

        $approvedParameters = self::approvedParameterNames($orderDetail->no_sampel);

        if (mb_strtolower((string) self::categoryName($orderDetail->kategori_2)) === 'air') {
            $fieldData = self::approvedAirFieldData($orderDetail->no_sampel);

            if ($fieldData) {
                $approvedParameters = array_merge(
                    $approvedParameters,
                    array_keys(self::airFieldParameterValues($orderDetail, $fieldData))
                );
            }
        }

        $approved = collect($approvedParameters)
            ->map(function ($parameter) {
                return self::normalizeParameter($parameter);
            })
            ->filter()
            ->unique()
            ->values();

        $complete = $required->isNotEmpty()
            && $required->diff($approved)->isEmpty();

        DB::table('ws_final_approval_header')
            ->where('id', $headerId)
            ->update([
                'is_approved' => $complete ? 1 : 0,
                'approved_by' => $complete
                    ? self::limit($approvedBy ?: self::currentUserName(), 100)
                    : null,
                'approved_at' => $complete
                    ? Carbon::now()->format('Y-m-d H:i:s')
                    : null,
            ]);
    }

    private static function approvedParameterNames(string $noSampel): array
    {
        $parameters = [];

        foreach (self::PARAMETER_SOURCES as $modelClass) {
            if (!class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass();
            $table = $model->getTable();

            if (!Schema::hasColumn($table, 'no_sampel') || !Schema::hasColumn($table, 'parameter')) {
                continue;
            }

            $approvalColumn = self::approvalColumn($table);
            if ($approvalColumn === null) {
                continue;
            }

            $parameters = array_merge(
                $parameters,
                $modelClass::where('no_sampel', $noSampel)
                    ->where($approvalColumn, 1)
                    ->pluck('parameter')
                    ->all()
            );
        }

        return $parameters;
    }

    private static function approvedAirFieldData(string $noSampel): ?DataLapanganAir
    {
        $query = DataLapanganAir::where('no_sampel', $noSampel);

        if (Schema::hasColumn((new DataLapanganAir())->getTable(), 'is_approve')) {
            $query->where('is_approve', 1);
        }

        return $query->orderByDesc('id')->first();
    }

    private static function airFieldParameterValues(OrderDetail $orderDetail, DataLapanganAir $fieldData): array
    {
        $values = [];

        foreach (self::arrayValue($orderDetail->parameter) as $parameter) {
            $parameterLab = self::stripIdentifier($parameter);
            $normalized = mb_strtolower(trim($parameterLab));
            $result = null;

            if ($normalized === 'ph') {
                $result = $fieldData->ph;
            } elseif ($normalized === 'suhu' || $normalized === 'suhu (na)') {
                $result = $fieldData->suhu_air;
            } elseif (str_contains($normalized, 'debit air')) {
                $result = $fieldData->debit_air;
            }

            if ($result !== null && $result !== '') {
                $values[$parameterLab] = self::fieldResultValue($result);
            }
        }

        return $values;
    }

    private static function fieldResultValue($value): ?string
    {
        $value = self::stringValue($value);

        if ($value !== null
            && str_contains($value, 'Data By Customer')
            && preg_match('/\((.*?)\)/', $value, $matches)
        ) {
            return trim($matches[1]);
        }

        return $value;
    }

    private static function approvalColumn(string $table, bool $isLingkunganKerja = false): ?string
    {
        $columns = $isLingkunganKerja
            ? ['is_approve', 'is_approved', 'lhps']
            : ['lhps', 'is_approve', 'is_approved'];

        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private static function extractResult(Model $source): ?string
    {
        foreach (['hasil', 'hasil_akhir', 'hasil_uji', 'hasil_pengujian', 'nilai', 'C', 'C1', 'C2'] as $field) {
            $value = $source->getAttribute($field);
            if ($value !== null && $value !== '') {
                return self::stringValue($value);
            }
        }

        foreach (['ws_value', 'ws_udara', 'ws_value_linkungan', 'ws_value_cerobong'] as $relation) {
            if (!method_exists($source, $relation)) {
                continue;
            }

            try {
                $value = $source->{$relation}()->first();
            } catch (\Throwable $e) {
                continue;
            }
            if (!$value) {
                continue;
            }

            foreach (['hasil', 'nilai', 'C', 'C1', 'C2'] as $field) {
                $result = $value->getAttribute($field);
                if ($result !== null && $result !== '') {
                    return self::stringValue($result);
                }
            }
        }

        return null;
    }

    private static function findParameterRegulasi(
        OrderDetail $orderDetail,
        string $parameterLab,
        ?Model $source = null
    ): ?string
    {
        $headerParameters = collect(self::arrayValue($orderDetail->parameter))
            ->map(function ($parameter) {
                $value = self::stringValue($parameter) ?: '';
                $parts = explode(';', $value, 2);

                return [
                    'id' => isset($parts[1]) && ctype_digit(trim($parts[0]))
                        ? (int) trim($parts[0])
                        : null,
                    'nama_lab' => trim(isset($parts[1]) ? $parts[1] : $parts[0]),
                ];
            })
            ->filter(function ($parameter) {
                return $parameter['id'] !== null;
            })
            ->values();

        $sourceParameterId = $source
            ? self::numericValue($source->getAttribute('id_parameter'))
            : null;

        if ($sourceParameterId !== null
            && $headerParameters->contains('id', $sourceParameterId)
        ) {
            return self::parameterRegulationName($sourceParameterId);
        }

        $normalizedParameterLab = self::normalizeParameter($parameterLab);
        $headerParameter = $headerParameters->first(function ($parameter) use ($normalizedParameterLab) {
            return self::normalizeParameter($parameter['nama_lab']) === $normalizedParameterLab;
        });

        if (!$headerParameter) {
            return null;
        }

        return self::parameterRegulationName($headerParameter['id']);
    }

    private static function parameterRegulationName(int $parameterId): ?string
    {
        if (!array_key_exists($parameterId, self::$parameterRegulationCache)) {
            self::$parameterRegulationCache[$parameterId] = Parameter::where('id', $parameterId)
                ->value('nama_regulasi');
        }

        return self::$parameterRegulationCache[$parameterId];
    }

    private static function arrayValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $value === null || $value === '' ? [] : [$value];
    }

    private static function jsonValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && json_decode($value, true) !== null) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    private static function stringValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    private static function numericValue($value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function stripIdentifier($value): string
    {
        $value = self::stringValue($value) ?: '';
        $parts = explode(';', $value, 2);

        return trim(count($parts) === 2 ? $parts[1] : $parts[0]);
    }

    private static function normalizeParameter($value): string
    {
        return mb_strtolower(trim(self::stripIdentifier($value)));
    }

    private static function categoryName($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parts = explode('-', (string) $value, 2);

        return trim(count($parts) === 2 ? $parts[1] : $parts[0]);
    }

    private static function limit($value, int $length): ?string
    {
        $value = self::stringValue($value);

        return $value === null ? null : mb_substr($value, 0, $length);
    }

    private static function currentUserName(): ?string
    {
        $request = app('request');
        $user = $request->attributes->get('user');

        if ($user && isset($user->karyawan) && $user->karyawan) {
            return $user->karyawan->nama_lengkap;
        }

        return $request->header('token');
    }

    public static function validateAndApprove(array $data, ?string $karyawan): array
    {
        $id = $data['id'] ?? null;
        $keterangan_1 = $data['keterangan_1'] ?? null;
        $detailData = $data['detail_data'] ?? [];

        if (!$id) {
            return [
                'success' => false,
                'message' => 'Data Not Found.!',
                'status' => 401
            ];
        }

        DB::beginTransaction();
        try {
            $orderDetail = OrderDetail::where('id', $id)->first();
            if (!$orderDetail) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Data Not Found.!',
                    'status' => 401
                ];
            }

            $orderDetail->status = 1;
            $orderDetail->keterangan_1 = $keterangan_1;
            $orderDetail->save();

            $kategori = self::categoryName($orderDetail->kategori_2);
            $subKategori = self::categoryName($orderDetail->kategori_3);
            $menu = 'WS Final ' . ($kategori ?? 'Udara');

            \App\Models\HistoryAppReject::insert([
                'no_lhp'      => $orderDetail->cfr,
                'no_sampel'   => $orderDetail->no_sampel,
                'kategori_2'  => $orderDetail->kategori_2,
                'kategori_3'  => $orderDetail->kategori_3,
                'menu'        => $menu,
                'status'      => 'approve',
                'approved_at' => Carbon::now(),
                'approved_by' => $karyawan,
            ]);

            if (Schema::hasTable('ws_final_approval_header')) {
                $existingHeader = DB::table('ws_final_approval_header')
                    ->where('no_lhp', $orderDetail->cfr)
                    ->first();

                if ($existingHeader) {
                    DB::table('ws_final_approval_header')
                        ->where('no_lhp', $orderDetail->cfr)
                        ->update([
                            'is_approved' => 1,
                            'approved_by' => self::limit($karyawan, 100),
                            'approved_at' => Carbon::now(),
                        ]);
                    $headerId = $existingHeader->id;
                } else {
                    $headerId = DB::table('ws_final_approval_header')->insertGetId([
                        'no_order'     => self::limit($orderDetail->no_order, 50),
                        'no_lhp'       => self::limit($orderDetail->cfr, 50),
                        'periode'      => self::limit($orderDetail->periode ?? '', 50),
                        'parameter'    => self::jsonValue($orderDetail->parameter),
                        'kategori'     => self::limit($kategori, 70),
                        'sub_kategori' => self::limit($subKategori, 70),
                        'regulasi'     => self::jsonValue($orderDetail->regulasi),
                        'nama_titik'   => self::limit($keterangan_1, 50),
                        'is_approved'  => 1,
                        'approved_by'  => self::limit($karyawan, 100),
                        'approved_at'  => Carbon::now(),
                    ]);
                }

                if (!empty($detailData) && is_array($detailData)) {
                    // Extract id_kategori from kategori_2 (e.g. "4-Udara" -> 4, "5-Emisi" -> 5)
                    $idKategori = 4;
                    if ($orderDetail->kategori_2) {
                        $idKategori = (int) explode('-', $orderDetail->kategori_2)[0];
                    }

                    foreach ($detailData as $detail) {
                        $parameterLab = isset($detail['parameter']) ? trim($detail['parameter']) : null;
                        if (!$parameterLab) {
                            continue;
                        }

                        $parameterRegulasi = '';
                        $parameterId       = isset($detail['id_parameter']) ? $detail['id_parameter'] : null;
                        if ($parameterId) {
                            $parameterRegulasi = DB::table('parameter')
                                ->where('id', $parameterId)
                                ->value('nama_regulasi') ?? '';
                        } else {
                            $parameterRegulasi = DB::table('parameter')
                                ->where('nama_lab', $parameterLab)
                                ->where('id_kategori', $idKategori)
                                ->value('nama_regulasi') ?? '';
                        }

                        $hasil = isset($detail['nilai_uji']) ? trim($detail['nilai_uji']) : '';

                        $existingDetail = DB::table('ws_final_approval_detail')
                            ->where('ws_final_approval_header_id', $headerId)
                            ->where('parameter_lab', self::limit($parameterLab, 70))
                            ->first();

                        if ($existingDetail) {
                            DB::table('ws_final_approval_detail')
                                ->where('id', $existingDetail->id)
                                ->update([
                                    'parameter_regulasi' => self::limit($parameterRegulasi, 100),
                                    'hasil'              => self::limit($hasil, 50),
                                ]);
                        } else {
                            DB::table('ws_final_approval_detail')->insert([
                                'ws_final_approval_header_id' => $headerId,
                                'no_sampel'                   => $orderDetail->no_sampel,
                                'parameter_lab'               => self::limit($parameterLab, 70),
                                'parameter_regulasi'          => self::limit($parameterRegulasi, 100),
                                'hasil'                       => self::limit($hasil, 50),
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Data hasbeen Approved.!',
                'status'  => 200,
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status'  => 400,
            ];
        }
    }
}
