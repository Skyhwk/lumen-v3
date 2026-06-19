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

    public static function parameterSourceClasses(): array
    {
        return self::PARAMETER_SOURCES;
    }

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

        $headerId = self::upsertHeader($orderDetail);
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
                    'no_sampel' => $noSampel,
                    'parameter_lab' => self::limit($parameterLab, 70),
                ], [
                    'no_sampel' => $noSampel,
                    'parameter_regulasi' => self::limit($parameterRegulasi, 100),
                    'hasil' => self::limit(self::extractResult($source), 50),
                ]);
            }
        } else {
            self::deleteParameterDetail($headerId, $noSampel, $parameterLab);
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

        self::deleteParameterDetail(self::upsertHeader($orderDetail), $noSampel, $parameterLab);
    }

    public static function finalizeSample(OrderDetail $orderDetail, bool $approved, ?string $approvedBy = null): void
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

        foreach (self::lhpOrderDetails($orderDetail) as $detail) {
            self::syncApprovedParameters($detail->no_sampel);
            self::syncAirFieldParameters($detail);
        }

        self::refreshApprovalStatus($orderDetail, $approvedBy);
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
        $orderDetails = self::lhpOrderDetails($orderDetail);
        $headerKeyColumn = self::headerKeyColumn();
        $headerKeyValue = self::headerKeyValue($orderDetail);

        $requiredParameterCount = $orderDetails
            ->flatMap(function (OrderDetail $detail) {
                return collect(self::arrayValue($detail->parameter))
                    ->map(function ($parameter) use ($detail) {
                        return self::sampleParameterKey($detail->no_sampel, $parameter);
                    });
            })
            ->filter()
            ->unique()
            ->count();

        $row = array_merge([
            'no_order' => self::limit($orderDetail->no_order, 50),
            $headerKeyColumn => self::limit($headerKeyValue, 50),
            'periode' => self::limit($orderDetail->periode, 50),
            'parameter' => self::jsonValue(
                $orderDetails
                    ->flatMap(fn (OrderDetail $detail) => self::arrayValue($detail->parameter))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all()
            ),
            'kategori' => self::limit(self::categoryName($orderDetail->kategori_2), 70),
            'sub_kategori' => self::limit(self::categoryName($orderDetail->kategori_3), 70),
            'regulasi' => self::jsonValue(
                $orderDetails
                    ->flatMap(fn (OrderDetail $detail) => self::arrayValue($detail->regulasi))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all()
            ),
            'nama_titik' => self::limit(
                $orderDetails
                    ->pluck('keterangan_1')
                    ->filter()
                    ->unique()
                    ->implode(', '),
                50
            ),
        ], $approval);

        if (Schema::hasColumn('ws_final_approval_header', 'jumlah_parameter')) {
            $row['jumlah_parameter'] = $requiredParameterCount;
        }

        $columns = array_keys($row);
        $quotedColumns = implode(', ', array_map(function ($column) {
            return "`{$column}`";
        }, $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $updates = implode(', ', array_map(function ($column) {
            return "`{$column}` = VALUES(`{$column}`)";
        }, array_filter($columns, function ($column) {
            return $column !== self::headerKeyColumn();
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

    private static function lhpOrderDetails(OrderDetail $orderDetail)
    {
        $noLhp = self::headerKeyValue($orderDetail);

        if (!$noLhp) {
            return collect([$orderDetail]);
        }

        $query = OrderDetail::where('is_active', true);

        if (self::stringValue($orderDetail->cfr)) {
            $query->where('cfr', $noLhp);
        } else {
            $query->where('no_sampel', $orderDetail->no_sampel);
        }

        return $query->orderBy('id')->get();
    }

    private static function headerKeyColumn(): string
    {
        return Schema::hasColumn('ws_final_approval_header', 'no_lhp')
            ? 'no_lhp'
            : 'no_sampel';
    }

    private static function headerKeyValue(OrderDetail $orderDetail): ?string
    {
        return self::stringValue($orderDetail->cfr)
            ?: self::stringValue($orderDetail->no_sampel);
    }

    private static function deleteParameterDetail(int $headerId, ?string $noSampel, ?string $parameterLab): void
    {
        if ($parameterLab !== null) {
            DB::table('ws_final_approval_detail')
                ->where('ws_final_approval_header_id', $headerId)
                ->where('no_sampel', $noSampel)
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

        foreach (self::airFieldParameterValues($orderDetail, $fieldData) as $parameterLab => $result) {
            DB::table('ws_final_approval_detail')->updateOrInsert([
                'ws_final_approval_header_id' => $headerId,
                'no_sampel' => $orderDetail->no_sampel,
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
        $orderDetails = self::lhpOrderDetails($orderDetail);

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

        $approvedParameters = collect();

        foreach ($orderDetails as $detail) {
            $approvedParameters = $approvedParameters->merge(
                collect(self::approvedParameterNames($detail->no_sampel))
                    ->map(function ($parameter) use ($detail) {
                        return self::sampleParameterKey($detail->no_sampel, $parameter);
                    })
            );

            if (mb_strtolower((string) self::categoryName($detail->kategori_2)) === 'air') {
                $fieldData = self::approvedAirFieldData($detail->no_sampel);

                if ($fieldData) {
                    $approvedParameters = $approvedParameters->merge(
                        collect(array_keys(self::airFieldParameterValues($detail, $fieldData)))
                            ->map(function ($parameter) use ($detail) {
                                return self::sampleParameterKey($detail->no_sampel, $parameter);
                            })
                    );
                }
            }
        }

        $approved = $approvedParameters
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

    private static function approvalColumn(string $table): ?string
    {
        foreach (['lhps', 'is_approve', 'is_approved'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private static function extractResult(Model $source): ?string
    {
        foreach (['hasil', 'hasil1', 'hasil_akhir', 'hasil_uji', 'hasil_pengujian', 'nilai', 'C', 'C1', 'C2'] as $field) {
            if (!array_key_exists($field, $source->getAttributes())) {
                continue;
            }

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

            foreach (['hasil', 'hasil1', 'nilai', 'C', 'C1', 'C2'] as $field) {
                if (!array_key_exists($field, $value->getAttributes())) {
                    continue;
                }

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

    private static function sampleParameterKey($noSampel, $parameter): string
    {
        return self::stringValue($noSampel) . '|' . self::normalizeParameter($parameter);
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
}
