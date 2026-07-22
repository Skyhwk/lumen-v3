<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LhpBackfillService
{
    private const KPGI_TYPES = [
        'kebisingan',
        'kebisingan_personal',
        'pencahayaan',
        'getaran_personel',
        'iklim_kerja',
    ];

    private const TYPE_CONFIG = [
        'air' => [
            'header_table' => 'lhps_air_header',
            'detail_table' => 'lhps_air_detail',
            'grouping' => 'sample',
            'header_key' => 'no_sampel',
            'category_2' => '1-Air',
        ],
        'lk_sinar_uv' => [
            'header_table' => 'lhps_sinaruv_header',
            'detail_table' => 'lhps_sinaruv_detail',
            'grouping' => 'cfr',
            'header_key' => 'no_lhp',
            'category_2' => '4-Udara',
            'category_3' => '27-Udara Lingkungan Kerja',
            'parameter_json_contains' => '324;Sinar UV',
        ],
        'kebisingan' => [
            'header_table' => 'lhps_kebisingan_header',
            'detail_table' => 'lhps_kebisingan_detail',
            'grouping' => 'sample',
            'header_key' => 'no_lhp',
            'category_2' => '4-Udara',
            'category_3_in' => ['23-Kebisingan', '24-Kebisingan (24 Jam)', '25-Kebisingan (Indoor)', '26-Kualitas Udara Dalam Ruang'],
            'parameter_json_doesnt_contain' => '271;Kebisingan (P8J)',
        ],
        'kebisingan_personal' => [
            'header_table' => 'lhps_kebisingan_personal_header',
            'detail_table' => 'lhps_kebisingan_personal_detail',
            'grouping' => 'sample',
            'header_key' => 'no_lhp',
            'category_2' => '4-Udara',
            'category_3_in' => ['23-Kebisingan', '24-Kebisingan (24 Jam)', '25-Kebisingan (Indoor)', '26-Kualitas Udara Dalam Ruang'],
            'parameter_json_contains' => '271;Kebisingan (P8J)',
        ],
        'iklim_kerja' => [
            'header_table' => 'lhps_iklim_header',
            'detail_table' => 'lhps_iklim_detail',
            'grouping' => 'sample',
            'header_key' => 'no_lhp',
            'category_2' => '4-Udara',
            'category_3' => '21-Iklim Kerja',
        ],
        'getaran_personel' => [
            'header_table' => 'lhps_getaran_header',
            'detail_table' => 'lhps_getaran_detail',
            'grouping' => 'sample',
            'header_key' => 'no_lhp',
            'category_2' => '4-Udara',
            'category_3_in' => ['17-Getaran (Lengan & Tangan)', '20-Getaran (Seluruh Tubuh)'],
        ],
        'pencahayaan' => [
            'header_table' => 'lhps_pencahayaan_header',
            'detail_table' => 'lhps_pencahayaan_detail',
            'grouping' => 'sample',
            'header_key' => 'no_lhp',
            'category_2' => '4-Udara',
            'category_3' => '28-Pencahayaan',
        ],
        'medan_magnet' => [
            'header_table' => 'lhps_medanlm_header',
            'detail_table' => 'lhps_medanlm_detail',
            'grouping' => 'cfr',
            'header_key' => 'no_lhp',
            'category_2' => '4-Udara',
            'category_3' => '27-Udara Lingkungan Kerja',
            'parameter_like_any' => ['Power Density', 'Medan Magnit Statis', 'Medan Listrik'],
        ],
        'udara_lingkungan_hidup' => [
            'header_table' => 'lhps_ling_header',
            'detail_table' => 'lhps_ling_detail',
            'grouping' => 'cfr',
            'header_key' => 'no_lhp',
            'category_2' => '4-Udara',
            'category_3' => '11-Udara Ambient',
        ],
        'udara_lingkungan_kerja' => [
            'header_table' => 'lhps_ling_header',
            'detail_table' => 'lhps_ling_detail',
            'grouping' => 'cfr',
            'header_key' => 'no_lhp',
            'category_2' => '4-Udara',
            'category_3' => '27-Udara Lingkungan Kerja',
            'parameter_fdl_templates' => ['lingkungan_kerja', 'senyawa_volatile', 'debu_personal', 'sensoric_pm', 'direct_lain'],
        ],
        'gelombang_mikro' => [
            'header_table' => 'lhps_medanlm_header',
            'detail_table' => 'lhps_medanlm_detail',
            'grouping' => 'cfr',
            'header_key' => 'no_lhp',
            'category_2' => '4-Udara',
            'category_3' => '27-Udara Lingkungan Kerja',
            'parameter_json_any' => ['563;Medan Magnet', '316;Power Density', '277;Medan Listrik', '236;Gelombang Elektro'],
        ],
        'emisi_sumber_bergerak' => [
            'header_table' => 'lhps_emisi_header',
            'detail_table' => 'lhps_emisi_detail',
            'grouping' => 'cfr',
            'header_key' => 'no_lhp',
            'category_2' => '5-Emisi',
            'category_3_not_in' => ['34-Emisi Sumber Tidak Bergerak', '119-Emisi Isokinetik'],
        ],
        'emisi_isokinetik' => [
            'header_table' => 'lhps_emisi_isokinetik_header',
            'detail_table' => 'lhps_emisi_isokinetik_detail',
            'grouping' => 'cfr',
            'header_key' => 'no_lhp',
            'category_2' => '5-Emisi',
            'category_3_in' => ['34-Emisi Sumber Tidak Bergerak', '119-Emisi Isokinetik'],
            'parameter_contains' => 'Iso-',
        ],
    ];

    private $connection;
    private $orderConnection;

    public function __construct($connection = 'mysql', $orderConnection = null)
    {
        $this->connection = $connection;
        $this->orderConnection = $orderConnection ?: $connection;
    }

    public static function availableTypes()
    {
        return array_keys(self::TYPE_CONFIG);
    }

    public static function requiredTables($type = null)
    {
        $types = (!$type || $type === 'all') ? array_keys(self::TYPE_CONFIG) : [$type];
        $tables = [];

        foreach ($types as $typeName) {
            if (!isset(self::TYPE_CONFIG[$typeName])) {
                throw new \InvalidArgumentException('Type LHP tidak dikenal: ' . $typeName);
            }

            $tables[] = self::TYPE_CONFIG[$typeName]['header_table'];
            $tables[] = self::TYPE_CONFIG[$typeName]['detail_table'];
        }

        return array_values(array_unique($tables));
    }

    public function run(array $filters = [])
    {
        $types = $this->resolveTypes($filters['type'] ?? null, $filters['except'] ?? null);
        $summary = [
            'connection' => $this->connection,
            'order_connection' => $this->orderConnection,
            'dry_run' => !empty($filters['dry_run']),
            'before' => $filters['before'] ?? null,
            'types' => [],
            'total' => ['scanned' => 0, 'candidates' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0],
        ];

        foreach ($types as $type) {
            $result = $this->backfillType($type, $filters);
            $summary['types'][$type] = $result;
            foreach ($summary['total'] as $key => $value) {
                $summary['total'][$key] += $result[$key] ?? 0;
            }
        }

        return $summary;
    }

    private function backfillType($type, array $filters)
    {
        $config = self::TYPE_CONFIG[$type];
        $groups = $this->candidateGroups($config, $filters);
        $result = ['scanned' => count($groups), 'processed' => 0, 'candidates' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0, 'messages' => []];
        $this->reportProgress($type, $result, '-', $filters);

        foreach (['header_table', 'detail_table'] as $tableKey) {
            if (!Schema::connection($this->connection)->hasTable($config[$tableKey])) {
                $result['failed'] = count($groups);
                $result['messages'][] = "MISSING TABLE {$type}: " . $config[$tableKey];
                return $result;
            }
        }

        foreach ($groups as $groupKey => $details) {
            $result['processed']++;
            $this->reportProgress($type, $result, $groupKey, $filters);
            try {
                if ($this->headerExists($config, $groupKey, $details[0])) {
                    $result['skipped']++;
                    continue;
                }

                $result['candidates']++;

                if (!empty($filters['count_only'])) {
                    if (!empty($filters['missing_only']) && !empty($filters['limit']) && $result['candidates'] >= (int) $filters['limit']) {
                        break;
                    }
                    continue;
                }

                if (!empty($filters['dry_run'])) {
                    $releaseDate = $this->releaseDateFromParameterSources($type, $details);
                    $result['messages'][] = "DRY {$type}: {$groupKey} (" . count($details) . ' sampel, approved_at=' . ($releaseDate ?: '-') . ')';
                    if (!empty($filters['missing_only']) && !empty($filters['limit']) && $result['candidates'] >= (int) $filters['limit']) {
                        break;
                    }
                    continue;
                }

                DB::connection($this->connection)->transaction(function () use ($type, $config, $details, $groupKey, $filters) {
                    $headerId = $this->insertHeader($type, $config, $details, $groupKey);
                    $this->insertDetails($type, $config, $details, $headerId, $filters);
                });

                $result['created']++;
                if (!empty($filters['missing_only']) && !empty($filters['limit']) && $result['candidates'] >= (int) $filters['limit']) {
                    break;
                }
            } catch (\Throwable $th) {
                if (strpos($th->getMessage(), 'Full detail tidak ditemukan') !== false) {
                    $result['skipped']++;
                    $result['messages'][] = "SKIPPED {$type}: {$groupKey} - " . $th->getMessage();
                    continue;
                }

                $result['failed']++;
                $message = "FAILED {$type}: {$groupKey} - " . $th->getMessage();
                $result['messages'][] = $message;
                $this->reportFailure($message, $filters);
            }
        }

        return $result;
    }

    private function reportFailure($message, array $filters)
    {
        $callback = $filters['failure_callback'] ?? null;
        if ($callback) {
            $callback($message);
        }
    }
    private function reportProgress($type, array $result, $current, array $filters)
    {
        $callback = $filters['progress_callback'] ?? null;
        $every = (int) ($filters['progress_every'] ?? 25);

        if (!$callback || $every <= 0) {
            return;
        }

        $processed = (int) ($result['processed'] ?? 0);
        $scanned = (int) ($result['scanned'] ?? 0);
        if ($processed !== 0 && $processed < $scanned && $processed % $every !== 0) {
            return;
        }

        $callback([
            'type' => $type,
            'processed' => $processed,
            'scanned' => $scanned,
            'candidates' => (int) ($result['candidates'] ?? 0),
            'created' => (int) ($result['created'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'current' => $current,
        ]);
    }
    private function candidateGroups(array $config, array $filters)
    {
        $query = DB::connection($this->orderConnection)->table('order_detail as od')
            ->leftJoin('order_header as oh', 'oh.id', '=', 'od.id_order_header')
            ->where('od.is_active', 1)
            ->whereNotNull('od.no_sampel')
            ->whereRaw("TRIM(od.no_sampel) != ''")
            ->select(array_merge(['od.*'], $this->orderHeaderSelects()));

        if (!empty($filters['month'])) {
            $start = Carbon::createFromFormat('Y-m-d', $filters['month'] . '-01')->startOfMonth()->toDateString();
            $end = Carbon::createFromFormat('Y-m-d', $filters['month'] . '-01')->addMonth()->startOfMonth()->toDateString();
            $query->where(function ($q) use ($start, $end) {
                $q->where(function ($dateQuery) use ($start, $end) {
                    $dateQuery->whereDate('od.tanggal_sampling', '>=', $start)
                        ->whereDate('od.tanggal_sampling', '<', $end);
                })->orWhere(function ($dateQuery) use ($start, $end) {
                    $dateQuery->whereDate('od.tanggal_terima', '>=', $start)
                        ->whereDate('od.tanggal_terima', '<', $end);
                });
            });
        } else {
            if (!empty($filters['from'])) {
                $query->where(function ($q) use ($filters) {
                    $q->whereDate('od.tanggal_sampling', '>=', $filters['from'])
                        ->orWhereDate('od.tanggal_terima', '>=', $filters['from']);
                });
            }

            if (!empty($filters['to'])) {
                $query->where(function ($q) use ($filters) {
                    $q->whereDate('od.tanggal_sampling', '<', $filters['to'])
                        ->orWhereDate('od.tanggal_terima', '<', $filters['to']);
                });
            } elseif (!empty($filters['before'])) {
                $query->where(function ($q) use ($filters) {
                    $q->whereDate('od.tanggal_sampling', '<', $filters['before'])
                        ->orWhereDate('od.tanggal_terima', '<', $filters['before']);
                });
            }
        }

        if (!empty($filters['cfr'])) {
            $query->where('od.cfr', $filters['cfr']);
        }

        if (!empty($filters['no_sampel'])) {
            $query->where('od.no_sampel', $filters['no_sampel']);
        }

        $this->applyTypeFilter($query, $config);

        if (empty($filters['missing_only']) && !empty($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }

        $rows = $query->orderBy('od.cfr')->orderBy('od.no_sampel')->get()->all();
        $groups = [];

        foreach ($rows as $row) {
            $key = $config['grouping'] === 'sample'
                ? ($row->no_sampel ?: $row->id)
                : ($row->cfr ?: $row->no_sampel ?: $row->id);

            $groups[$key][] = $row;
        }

        return $groups;
    }

    private function orderHeaderSelects()
    {
        $columns = Schema::connection($this->orderConnection)->getColumnListing('order_header');
        $selects = [];

        foreach (['nama_perusahaan', 'nama_pelanggan', 'alamat_sampling', 'konsultan', 'nama_pic_order', 'email_pic_order', 'nama_pic_sampling', 'email_pic_sampling', 'jabatan_pic_order', 'jabatan_pic_sampling'] as $column) {
            $selects[] = in_array($column, $columns, true)
                ? 'oh.' . $column
                : DB::raw('NULL as ' . $column);
        }

        $selects[] = in_array('no_pic_order', $columns, true)
            ? DB::raw('oh.no_pic_order as no_pic_order')
            : DB::raw('NULL as no_pic_order');

        $selects[] = in_array('no_tlp_pic_sampling', $columns, true)
            ? DB::raw('oh.no_tlp_pic_sampling as no_pic_sampling')
            : DB::raw('NULL as no_pic_sampling');

        $selects[] = in_array('no_document', $columns, true)
            ? DB::raw('oh.no_document as no_penawaran')
            : DB::raw('NULL as no_penawaran');

        return $selects;
    }

    private function applyTypeFilter($query, array $config)
    {
        if (!empty($config['category_2'])) {
            $query->where('od.kategori_2', $config['category_2']);
        }

        if (!empty($config['category_3'])) {
            $query->where('od.kategori_3', $config['category_3']);
        }

        if (!empty($config['category_3_in'])) {
            $query->whereIn('od.kategori_3', $config['category_3_in']);
        }

        if (!empty($config['category_3_not_in'])) {
            $query->whereNotIn('od.kategori_3', $config['category_3_not_in']);
        }

        if (!empty($config['category_3_contains'])) {
            $query->where('od.kategori_3', 'LIKE', '%' . $config['category_3_contains'] . '%');
        }

        if (!empty($config['parameter_contains'])) {
            $query->where('od.parameter', 'LIKE', '%' . $config['parameter_contains'] . '%');
        }

        if (!empty($config['exclude_parameter_contains'])) {
            $query->where(function ($q) use ($config) {
                $q->whereNull('od.parameter')
                    ->orWhere('od.parameter', 'NOT LIKE', '%' . $config['exclude_parameter_contains'] . '%');
            });
        }

        if (!empty($config['parameter_json_contains'])) {
            $query->whereJsonContains('od.parameter', $config['parameter_json_contains']);
        }

        if (!empty($config['parameter_json_doesnt_contain'])) {
            $query->whereJsonDoesntContain('od.parameter', $config['parameter_json_doesnt_contain']);
        }

        if (!empty($config['parameter_not_like'])) {
            foreach ($config['parameter_not_like'] as $parameter) {
                $query->where('od.parameter', 'NOT LIKE', '%' . $parameter . '%');
            }
        }

        if (!empty($config['parameter_like_any'])) {
            $query->where(function ($parameterQuery) use ($config) {
                foreach ($config['parameter_like_any'] as $parameter) {
                    $parameterQuery->orWhere('od.parameter', 'LIKE', '%' . $parameter . '%');
                }
            });
        }

        if (!empty($config['parameter_json_any'])) {
            $query->where(function ($parameterQuery) use ($config) {
                foreach ($config['parameter_json_any'] as $parameter) {
                    $parameterQuery->orWhereJsonContains('od.parameter', $parameter);
                }
            });
        }

        if (!empty($config['parameter_fdl_templates'])) {
            $allowedParameters = $this->allowedParametersFromFdlTemplates($config['parameter_fdl_templates']);
            if ($allowedParameters) {
                $query->where(function ($parameterQuery) use ($allowedParameters) {
                    foreach ($allowedParameters as $parameter) {
                        $parameterQuery->orWhere('od.parameter', 'LIKE', '%' . $parameter . '%');
                    }
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

    }

    private function allowedParametersFromFdlTemplates(array $templates)
    {
        if (!Schema::connection($this->connection)->hasTable('parameter_fdl')) {
            return [];
        }

        $rows = DB::connection($this->connection)->table('parameter_fdl')
            ->whereIn('nama_fdl', $templates)
            ->pluck('parameters');
        $parameters = [];

        foreach ($rows as $raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $parameters = array_merge($parameters, $decoded);
            }
        }

        return array_values(array_unique(array_filter($parameters)));
    }

    private function headerExists(array $config, $groupKey, $firstDetail)
    {
        $query = DB::connection($this->connection)->table($config['header_table'])->where('is_active', 1);

        if ($config['header_key'] === 'no_sampel') {
            return $query->where('no_sampel', $firstDetail->no_sampel)->exists();
        }

        return $query->where('no_lhp', $firstDetail->cfr ?: $groupKey)->exists();
    }

    private function insertHeader($type, array $config, array $details, $groupKey)
    {
        $first = $details[0];
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $noSampel = implode(', ', array_values(array_unique(array_map(function ($detail) {
            return $detail->no_sampel;
        }, $details))));
        $tanggalSampling = implode(', ', array_values(array_unique(array_filter(array_map(function ($detail) {
            return $detail->tanggal_sampling;
        }, $details)))));
        $releaseDate = $this->releaseDateFromParameterSources($type, $details)
            ?: ($first->tanggal_terima ?: ($first->tanggal_sampling ?? null));

        $signer = $this->signerForDate($releaseDate);

        $row = [
            'no_order' => $first->no_order ?? null,
            'no_sampel' => $noSampel,
            'no_lhp' => $first->cfr ?: $groupKey,
            'no_qt' => $first->no_penawaran ?? null,
            'no_quotation' => $first->no_penawaran ?? null,
            'status_sampling' => $first->kategori_1 ?? null,
            'tanggal_sampling' => $tanggalSampling ?: ($first->tanggal_sampling ?? null),
            'tanggal_terima' => $first->tanggal_terima ?? null,
            'parameter_uji' => $this->parameterJson($details),
            'nama_pelanggan' => $first->nama_perusahaan ?: ($first->nama_pelanggan ?? null),
            'alamat_sampling' => $first->alamat_sampling ?? null,
            'sub_kategori' => $first->kategori_3 ?? null,
            'id_kategori_2' => $this->categoryId($first->kategori_2 ?? null),
            'id_kategori_3' => $this->categoryId($first->kategori_3 ?? null),
            'regulasi' => $this->jsonOrNull($first->regulasi ?? null),
            'tanggal_lhp' => $releaseDate,
            'tanggal_rilis_lhp' => $releaseDate,
            'is_active' => 1,
            'is_approve' => 0,
            'is_generated' => 0,
            'nama_karyawan' => $signer['nama_karyawan'],
            'jabatan_karyawan' => $signer['jabatan_karyawan'],
            'created_by' => 'System',
            'created_at' => $now,
        ];

        if (in_array($type, self::KPGI_TYPES, true)) {
            $row['no_lhp'] = $first->cfr ?: $first->no_sampel;
            $row['no_sampel'] = $first->no_sampel;
        }

        $row = array_merge($row, $this->headerEnrichment($type, $details, $row));

        return DB::connection($this->connection)
            ->table($config['header_table'])
            ->insertGetId($this->filterColumns($config['header_table'], $row));
    }

    private function signerForDate($date)
    {
        $fallback = [
            'nama_karyawan' => 'Abidah Walfathiyyah',
            'jabatan_karyawan' => 'Technical Control Supervisor',
        ];

        if (!$date || !Schema::connection($this->connection)->hasTable('pengesahan_lhp')) {
            return $fallback;
        }

        $row = DB::connection($this->connection)->table('pengesahan_lhp')
            ->where('berlaku_mulai', '<=', $date)
            ->orderByDesc('berlaku_mulai')
            ->first();

        return [
            'nama_karyawan' => $row->nama_karyawan ?? $fallback['nama_karyawan'],
            'jabatan_karyawan' => $row->jabatan_karyawan ?? $fallback['jabatan_karyawan'],
        ];
    }

    private function headerEnrichment($type, array $details, array $baseRow)
    {
        $first = $details[0];
        $samples = array_values(array_unique(array_filter(array_map(function ($detail) {
            return $detail->no_sampel ?? null;
        }, $details))));
        $firstSample = $samples[0] ?? ($first->no_sampel ?? null);

        $row = [
            'header_table' => $this->headerTableFromRegulasi($first->regulasi ?? null),
        ];

        if ($type === 'air') {
            $lap = $this->lapanganRow('data_lapangan_air', $firstSample);
            if ($lap) {
                $row = array_merge($row, [
                    'deskripsi_titik' => $lap->lokasi_titik_pengambilan ?? $lap->lokasi_sampling ?? $lap->keterangan ?? null,
                    'titik_koordinat' => $this->coordinateFromLapangan($lap),
                    'suhu_air' => $lap->suhu_air ?? null,
                    'suhu_udara' => $lap->suhu_udara ?? null,
                    'ph' => $lap->ph ?? null,
                    'dhl' => $lap->dhl ?? null,
                    'do' => $lap->do ?? null,
                    'bau' => $lap->bau ?? null,
                    'warna' => $lap->warna ?? null,
                    'keterangan' => !empty($lap->keterangan) ? json_encode([$lap->keterangan]) : null,
                ]);
            }
            return array_filter($row, function ($value) { return $value !== null && $value !== ''; });
        }

        if (in_array($type, ['udara_lingkungan_hidup', 'udara_lingkungan_kerja'], true)) {
            $row = array_merge($row, $this->lingHeaderEnrichment($type, $samples));
            return array_filter($row, function ($value) { return $value !== null && $value !== ''; });
        }

        if ($type === 'kebisingan' || $type === 'kebisingan_personal') {
            $lap = $this->lapanganRow($type === 'kebisingan_personal' ? 'data_lapangan_kebisingan_personal' : 'data_lapangan_kebisingan', $firstSample);
            $row = array_merge($row, [
                'deskripsi_titik' => $lap->lokasi_titik_sampling ?? $lap->keterangan ?? null,
                'suhu' => $lap->suhu_udara ?? null,
                'kelembapan' => $lap->kelembapan_udara ?? null,
                'jenis_sampel' => $lap->jenis_kategori_kebisingan ?? null,
            ]);
            return array_filter($row, function ($value) { return $value !== null && $value !== ''; });
        }

        if ($type === 'pencahayaan') {
            $lap = $this->lapanganRow('data_lapangan_cahaya', $firstSample);
            $row = array_merge($row, [
                'deskripsi_titik' => $lap->keterangan ?? null,
            ]);
            return array_filter($row, function ($value) { return $value !== null && $value !== ''; });
        }

        if ($type === 'lk_sinar_uv') {
            $lap = $this->lapanganRow('data_lapangan_sinaruv', $firstSample);
            $subKategori = $this->categoryName($first->kategori_3 ?? null) ?: ($lap->kategori_3 ?? null);
            return [
                'id_kategori_3' => null,
                'sub_kategori' => $subKategori,
                'jenis_sampel' => null,
                'metode_sampling' => $this->sinarUvMethodJson($details),
                'keterangan' => $lap->keterangan ?? null,
                'tanggal_sampling' => $this->distinctDateList($details, ['tanggal_sampling']) ?: ($baseRow['tanggal_sampling'] ?? null),
                'tanggal_sampling_text' => null,
            ];
        }

        if (in_array($type, ['medan_magnet', 'gelombang_mikro'], true)) {
            $lap = $this->lapanganRow('data_lapangan_medan_lm', $firstSample);
            $row = array_merge($row, [
                'id_kategori_2' => 4,
                'id_kategori_3' => 27,
                'sub_kategori' => 'Udara Lingkungan Kerja',
                'deskripsi_titik' => $lap->keterangan ?? $lap->lokasi ?? null,
                'informasi_sampling' => $this->jsonArrayValue($lap->sumber_radiasi ?? null),
                'keterangan' => $this->defaultLhpKeteranganJson(),
                'hasil_observasi' => $this->medanLmHasilObservasiJson($type, $details),
                'kesimpulan' => $this->medanLmKesimpulanJson($type, $details),
                'tanggal_sampling_text' => $baseRow['tanggal_sampling'] ?? null,
                'tanggal_sampling_awal' => $baseRow['tanggal_sampling'] ?? null,
                'tanggal_sampling_akhir' => $baseRow['tanggal_sampling'] ?? null,
            ]);
            return array_filter($row, function ($value) { return $value !== null && $value !== ''; });
        }

        if ($type === 'emisi_sumber_bergerak') {
            $row = array_merge($row, $this->emisiHeaderEnrichment($details, $baseRow));
            return array_filter($row, function ($value) { return $value !== null && $value !== ''; });
        }

        if ($type === 'emisi_isokinetik') {
            $row = array_merge($row, $this->isokinetikHeaderEnrichment($details, $baseRow));
            return array_filter($row, function ($value) { return $value !== null && $value !== ''; });
        }

        return array_filter($row, function ($value) { return $value !== null && $value !== ''; });
    }

    private function emisiHeaderEnrichment(array $details, array $baseRow)
    {
        $first = $details[0];

        return [
            'type_sampling' => $this->firstFilled($first->kategori_1 ?? null, $baseRow['status_sampling'] ?? null),
            'kategori' => $this->categoryName($first->kategori_2 ?? null) ?: 'Emisi',
            'tgl_tugas' => $baseRow['tanggal_sampling'] ?? null,
            'konsultan' => $this->cleanText($first->konsultan ?? null),
            'nama_pic' => $this->cleanText($this->firstFilled($first->nama_pic_sampling ?? null, $first->nama_pic_order ?? null)),
            'email_pic' => $this->cleanText($this->firstFilled($first->email_pic_sampling ?? null, $first->email_pic_order ?? null)),
            'jabatan_pic' => $this->cleanText($this->firstFilled($first->jabatan_pic_sampling ?? null, $first->jabatan_pic_order ?? null)),
            'no_pic' => $this->cleanText($this->firstFilled($first->no_pic_sampling ?? null, $first->no_pic_order ?? null)),
        ];
    }
    private function isokinetikHeaderEnrichment(array $details, array $baseRow)
    {
        $first = $details[0];
        $samples = array_values(array_unique(array_filter(array_map(function ($detail) {
            return $detail->no_sampel ?? null;
        }, $details))));
        $lap = $this->isokinetikLapangan($samples);

        return [
            'keterangan' => $this->lhpDefaultKeteranganJson(),
            'konsultan' => $this->cleanText($first->konsultan ?? null),
            'nama_pic' => $this->cleanText($this->firstFilled($first->nama_pic_sampling ?? null, $first->nama_pic_order ?? null)),
            'email_pic' => $this->cleanText($this->firstFilled($first->email_pic_sampling ?? null, $first->email_pic_order ?? null)),
            'jabatan_pic' => $this->cleanText($this->firstFilled($first->jabatan_pic_sampling ?? null, $first->jabatan_pic_order ?? null)),
            'no_pic' => $this->cleanText($this->firstFilled($first->no_pic_sampling ?? null, $first->no_pic_order ?? null)),
            'deskripsi_titik' => $this->firstFilled($lap->keterangan ?? null, $lap->sumber_emisi ?? null),
            'titik_koordinat' => $this->coordinateFromLapangan($lap),
            'velocity' => $this->firstFilled($lap->kecepatan ?? null, $lap->kecepatan_linier ?? null),
            'tanggal_tugas' => $baseRow['tanggal_sampling'] ?? null,
        ];
    }

    private function isokinetikLapangan(array $samples)
    {
        if (!$samples || !Schema::connection($this->connection)->hasTable('data_lapangan_isokinetik_survei_lapangan')) {
            return null;
        }

        $idLapangan = null;
        if (Schema::connection($this->connection)->hasTable('isokinetik_header')) {
            $columns = Schema::connection($this->connection)->getColumnListing('isokinetik_header');
            if (in_array('id_lapangan', $columns, true)) {
                $idLapangan = DB::connection($this->connection)->table('isokinetik_header')
                    ->whereIn('no_sampel', $samples)
                    ->where('is_active', 1)
                    ->whereNotNull('id_lapangan')
                    ->value('id_lapangan');
            }
        }

        if (!$idLapangan) {
            foreach (['data_lapangan_isokinetik_penentuan_kecepatan_linier', 'data_lapangan_isokinetik_penentuan_partikulat', 'data_lapangan_isokinetik_hasil', 'data_lapangan_isokinetik_kadar_air', 'data_lapangan_isokinetik_berat_molekul'] as $table) {
                if (!Schema::connection($this->connection)->hasTable($table)) {
                    continue;
                }
                $columns = Schema::connection($this->connection)->getColumnListing($table);
                if (!in_array('no_sampel', $columns, true) || !in_array('id_lapangan', $columns, true)) {
                    continue;
                }

                $query = DB::connection($this->connection)->table($table)
                    ->whereIn('no_sampel', $samples)
                    ->whereNotNull('id_lapangan');
                if (in_array('is_active', $columns, true)) {
                    $query->where('is_active', 1);
                }

                $idLapangan = $query->value('id_lapangan');
                if ($idLapangan) {
                    break;
                }
            }
        }

        if ($idLapangan) {
            $lap = DB::connection($this->connection)->table('data_lapangan_isokinetik_survei_lapangan')
                ->where('id', $idLapangan)
                ->where('is_active', 1)
                ->first();
            if ($lap) {
                return $lap;
            }
        }

        $surveiColumns = Schema::connection($this->connection)->getColumnListing('data_lapangan_isokinetik_survei_lapangan');
        if (in_array('no_survei', $surveiColumns, true)) {
            foreach (['data_lapangan_isokinetik_penentuan_kecepatan_linier'] as $table) {
                if (!Schema::connection($this->connection)->hasTable($table)) {
                    continue;
                }
                $columns = Schema::connection($this->connection)->getColumnListing($table);
                if (!in_array('no_sampel', $columns, true) || !in_array('no_survei', $columns, true)) {
                    continue;
                }
                $noSurvei = DB::connection($this->connection)->table($table)
                    ->whereIn('no_sampel', $samples)
                    ->whereNotNull('no_survei')
                    ->value('no_survei');
                if ($noSurvei) {
                    return DB::connection($this->connection)->table('data_lapangan_isokinetik_survei_lapangan')
                        ->where('no_survei', $noSurvei)
                        ->where('is_active', 1)
                        ->first();
                }
            }
        }

        return null;
    }

    private function lhpDefaultKeteranganJson()
    {
        return json_encode([
            "\u{25B2} Hasil Uji melampaui nilai ambang batas yang diperbolehkan.",
            "\u{2198} Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.",
            "\u{1E8D} Parameter belum terakreditasi.",
        ], JSON_UNESCAPED_UNICODE);
    }
    private function lingHeaderEnrichment($type, array $samples)
    {
        $detailTable = $type === 'udara_lingkungan_hidup' ? 'detail_lingkungan_hidup' : 'detail_lingkungan_kerja';
        $lapanganTable = $type === 'udara_lingkungan_hidup' ? 'data_lapangan_lingkungan_hidup' : 'data_lapangan_lingkungan_kerja';
        $detailRows = $this->sampleRows($detailTable, $samples);
        $lapanganRows = $this->sampleRows($lapanganTable, $samples);
        $pendukungRows = [];
        foreach ($lapanganRows as $lapangan) {
            $pendukungRows[] = $this->jsonObject($lapangan->data_pendukung ?? null);
        }

        $analysisRows = collect();
        if (Schema::connection($this->connection)->hasTable('lingkungan_header')) {
            $analysisRows = DB::connection($this->connection)->table('lingkungan_header')
                ->whereIn('no_sampel', $samples)
                ->where('is_active', 1)
                ->where(function ($query) {
                    $query->where('is_approved', 1)->orWhereNotNull('approved_at');
                })
                ->get(['no_sampel', 'approved_at', 'created_at']);
        }

        return [
            'keterangan' => json_encode([
                "\u{25B2} Hasil Uji melampaui nilai ambang batas yang diperbolehkan.",
                "\u{2198} Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.",
                "\u{1E8D} Parameter belum terakreditasi.",
            ], JSON_UNESCAPED_UNICODE),
            'deskripsi_titik' => $this->uniqueValues($detailRows, ['keterangan']) ?: $this->jsonFirst($pendukungRows, ['deskripsi_titik', 'penamaan_titik', 'lokasi']),
            'titik_koordinat' => $this->firstCoordinate($detailRows) ?: $this->firstCoordinate($lapanganRows) ?: $this->jsonFirst($pendukungRows, ['titik_koordinat', 'koordinat']),
            'waktu_pengukuran' => $this->firstRowValue($detailRows, ['waktu_pengukuran']) ?: $this->jsonFirst($pendukungRows, ['waktu_pengukuran', 'waktu']),
            'kec_angin' => $this->firstRowValue($detailRows, ['kecepatan_angin', 'kec_angin']) ?: $this->jsonFirst($pendukungRows, ['kecepatan_angin', 'kec_angin']),
            'cuaca' => $this->firstRowValue($detailRows, ['cuaca']) ?: $this->jsonFirst($pendukungRows, ['cuaca']),
            'arah_angin' => $this->firstRowValue($detailRows, ['arah_angin']) ?: $this->jsonFirst($pendukungRows, ['arah_angin']),
            'suhu' => $this->firstRowValue($detailRows, ['suhu']) ?: $this->jsonFirst($pendukungRows, ['suhu_lingkungan', 'suhu']),
            'tekanan_udara' => $this->firstRowValue($detailRows, ['tekanan_udara']) ?: $this->jsonFirst($pendukungRows, ['tekanan_udara']),
            'kelembapan' => $this->firstRowValue($detailRows, ['kelembapan']) ?: $this->jsonFirst($pendukungRows, ['kelembapan']),
            'tanggal_sampling_awal' => $this->minDateFromRows($detailRows, ['tanggal_sampling', 'created_at', 'approved_at']) ?: $this->minDateFromRows($lapanganRows, ['tanggal_sampling', 'created_at', 'approved_at']),
            'tanggal_sampling_akhir' => $this->maxDateFromRows($detailRows, ['tanggal_sampling', 'created_at', 'approved_at']) ?: $this->maxDateFromRows($lapanganRows, ['tanggal_sampling', 'created_at', 'approved_at']),
            'tanggal_analisa_awal' => $this->minDateFromRows($analysisRows, ['approved_at']),
            'tanggal_analisa_akhir' => $this->maxDateFromRows($analysisRows, ['approved_at']),
        ];
    }

    private function sampleRows($table, array $samples)
    {
        if (!$samples || !Schema::connection($this->connection)->hasTable($table)) {
            return collect();
        }

        $columns = Schema::connection($this->connection)->getColumnListing($table);
        if (!in_array('no_sampel', $columns, true)) {
            return collect();
        }

        $query = DB::connection($this->connection)->table($table)->whereIn('no_sampel', $samples);
        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', 1);
        }
        if (in_array('is_approve', $columns, true)) {
            $query->where(function ($q) {
                $q->where('is_approve', 1)->orWhereNotNull('approved_at');
            });
        }

        return $query->orderBy('created_at')->get();
    }

    private function firstRowValue($rows, array $columns)
    {
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                if (isset($row->$column) && trim((string) $row->$column) !== '') {
                    return $row->$column;
                }
            }
        }

        return null;
    }

    private function uniqueValues($rows, array $columns)
    {
        $values = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                if (isset($row->$column) && trim((string) $row->$column) !== '' && trim((string) $row->$column) !== '-') {
                    $values[] = trim((string) $row->$column);
                    break;
                }
            }
        }

        $values = array_values(array_unique($values));
        return $values ? implode(', ', $values) : null;
    }

    private function jsonFirst(array $rows, array $keys)
    {
        foreach ($rows as $row) {
            foreach ($keys as $key) {
                if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                    return $row[$key];
                }
            }
        }

        return null;
    }

    private function firstCoordinate($rows)
    {
        foreach ($rows as $row) {
            $coordinate = $this->coordinateFromLapangan($row);
            if ($coordinate) {
                return $coordinate;
            }
        }

        return null;
    }

    private function minDateFromRows($rows, array $columns)
    {
        return $this->edgeDateFromRows($rows, $columns, 'min');
    }

    private function maxDateFromRows($rows, array $columns)
    {
        return $this->edgeDateFromRows($rows, $columns, 'max');
    }

    private function edgeDateFromRows($rows, array $columns, $mode)
    {
        $dates = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                if (isset($row->$column) && trim((string) $row->$column) !== '') {
                    try {
                        $dates[] = Carbon::parse($row->$column)->format('Y-m-d');
                    } catch (\Throwable $th) {
                        // Skip invalid legacy date values.
                    }
                    break;
                }
            }
        }

        if (!$dates) {
            return null;
        }

        sort($dates);
        return $mode === 'max' ? end($dates) : reset($dates);
    }
    private function headerTableFromRegulasi($raw)
    {
        $ids = [];
        $decoded = json_decode($raw ?? '', true);
        $items = is_array($decoded) ? $decoded : [$raw];
        foreach ($items as $item) {
            if (is_numeric($item)) {
                $ids[] = (int) $item;
            } elseif (is_string($item) && preg_match('/^(\d+)/', $item, $matches)) {
                $ids[] = (int) $matches[1];
            }
        }

        if (!$ids || !Schema::connection($this->connection)->hasTable('master_regulasi')) {
            return null;
        }

        $names = DB::connection($this->connection)->table('master_regulasi')
            ->whereIn('id', array_values(array_unique($ids)))
            ->pluck('peraturan')
            ->all();

        return $names ? json_encode(array_slice($names, 0, 4)) : null;
    }

    private function coordinateFromLapangan($lap)
    {
        if (!$lap) {
            return null;
        }

        if (!empty($lap->titik_koordinat)) {
            return $lap->titik_koordinat;
        }

        if (!empty($lap->latitude) && !empty($lap->longitude)) {
            return trim($lap->latitude . ' ' . $lap->longitude);
        }

        return null;
    }

    private function jsonObject($raw)
    {
        $decoded = json_decode($raw ?? '', true);
        return is_array($decoded) ? $decoded : [];
    }
    private function insertDetails($type, array $config, array $details, $headerId, array $filters = [])
    {
        $fullRows = $this->fullDetailRows($type, $details, $headerId);
        if ($fullRows) {
            foreach ($fullRows as $row) {
                DB::connection($this->connection)
                    ->table($config['detail_table'])
                    ->insert($this->filterColumns($config['detail_table'], $row));
            }
            return;
        }

        if (!empty($filters['full_detail'])) {
            $samples = implode(', ', array_filter(array_map(function ($detail) {
                return $detail->no_sampel ?? null;
            }, $details)));
            throw new \RuntimeException('Full detail tidak ditemukan untuk ' . $type . ($samples ? ': ' . $samples : ''));
        }

        foreach ($details as $detail) {
            foreach ($this->parameters($detail->parameter ?? null) as $parameter) {
                $row = $this->detailRow($type, $detail, $parameter, $headerId);
                DB::connection($this->connection)
                    ->table($config['detail_table'])
                    ->insert($this->filterColumns($config['detail_table'], $row));
            }
        }
    }

    private function fullDetailRows($type, array $details, $headerId)
    {
        if ($type === 'air') {
            return $this->airDetailRows($details, $headerId);
        }

        if (in_array($type, self::KPGI_TYPES, true)) {
            return $this->kpgiDetailRows($type, $details, $headerId);
        }

        if ($type === 'lk_sinar_uv') {
            return $this->sinarUvDetailRows($details, $headerId);
        }

        if (in_array($type, ['medan_magnet', 'gelombang_mikro'], true)) {
            return $this->medanLmDetailRows($details, $headerId);
        }

        if (in_array($type, ['udara_lingkungan_hidup', 'udara_lingkungan_kerja'], true)) {
            return $this->lingDetailRows($details, $headerId);
        }

        if ($type === 'emisi_sumber_bergerak') {
            return $this->emisiBergerakDetailRows($details, $headerId);
        }

        if ($type === 'emisi_isokinetik') {
            return $this->isokinetikDetailRows($details, $headerId);
        }

        return [];
    }
    private function airDetailRows(array $details, $headerId)
    {
        $rows = [];
        foreach ($details as $detail) {
            $regulasiId = $this->firstRegulationId($detail->regulasi ?? null);
            $rows = array_merge($rows, $this->airWorksheetRows($detail, $headerId, $regulasiId));
            $rows = array_merge($rows, $this->airFieldRows($detail, $headerId, $regulasiId));
        }

        return $rows;
    }

    private function airWorksheetRows($detail, $headerId, $regulasiId)
    {
        $rows = [];
        $sources = [
            ['table' => 'gravimetri', 'fk' => 'id_gravimetri', 'approve' => 'is_approved'],
            ['table' => 'colorimetri', 'fk' => 'id_colorimetri', 'approve' => 'is_approved'],
            ['table' => 'titrimetri', 'fk' => 'id_titrimetri', 'approve' => 'is_approved'],
            ['table' => 'subkontrak', 'fk' => 'id_subkontrak', 'approve' => 'is_approve'],
        ];

        foreach ($sources as $source) {
            if (!Schema::connection($this->connection)->hasTable($source['table']) || !Schema::connection($this->connection)->hasTable('ws_value_air')) {
                continue;
            }

            $items = DB::connection($this->connection)->table($source['table'] . ' as src')
                ->join('ws_value_air as ws', 'ws.' . $source['fk'], '=', 'src.id')
                ->leftJoin('parameter as param', function ($join) {
                    $join->on('param.nama_lab', '=', 'src.parameter')
                        ->where('param.id_kategori', 1)
                        ->where('param.is_active', 1);
                })
                ->where('src.no_sampel', $detail->no_sampel)
                ->where('src.is_active', 1)
                ->where('src.' . $source['approve'], 1)
                ->where('ws.is_active', 1)
                ->select('src.parameter', 'ws.hasil', 'ws.hasil_json', 'ws.faktor_koreksi', 'param.nama_lhp', 'param.nama_regulasi', 'param.satuan', 'param.method', 'param.status')
                ->get();

            foreach ($items as $item) {
                $bakumutu = $this->bakuMutu($regulasiId, $item->parameter);
                $satuan = $this->firstFilled($bakumutu->satuan ?? null, $item->satuan ?? null, '-');
                $method = $this->firstFilled($bakumutu->method ?? null, $item->method ?? null, '-');
                $bakuMutu = [$bakumutu->baku_mutu ?? '-'];
                $hasil = $item->hasil !== null ? str_replace('_', ' ', $item->hasil) : null;

                if ($hasil === '##') {
                    $satuan = '-';
                    $method = '-';
                    $bakuMutu = ['-'];
                }

                $rows[] = [
                    'id_header' => $headerId,
                    'no_sampel' => $detail->no_sampel,
                    'akr' => ($item->status ?? null) === 'AKREDITASI' ? '' : 'ẍ',
                    'parameter_lab' => str_replace("'", '', $item->parameter),
                    'parameter' => $item->nama_lhp ?? $item->nama_regulasi ?? $item->parameter,
                    'hasil_uji' => $hasil ?? '',
                    'hasil_uji_json' => $item->hasil_json ?? null,
                    'attr' => $item->faktor_koreksi ?? null,
                    'satuan' => $satuan,
                    'methode' => $method,
                    'baku_mutu' => json_encode($bakuMutu),
                    'created_by' => 'System',
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ];
            }
        }

        return $rows;
    }

    private function airFieldRows($detail, $headerId, $regulasiId)
    {
        if (($detail->kategori_1 ?? null) === 'SD') {
            return $this->airSdFieldRows($detail, $headerId, $regulasiId);
        }

        if (!Schema::connection($this->connection)->hasTable('data_lapangan_air')) {
            return [];
        }

        $lapangan = DB::connection($this->connection)->table('data_lapangan_air')
            ->where('no_sampel', $detail->no_sampel)
            ->first();

        if (!$lapangan) {
            return [];
        }

        $rows = [];
        if (!empty($lapangan->ph) && $this->orderHasParameter($detail, '128;pH')) {
            $rows[] = $this->airFieldRow($detail, $headerId, $regulasiId, 'pH', 'pH', $lapangan->ph, '-');
        }

        if (!empty($lapangan->suhu_air) && $this->orderHasParameter($detail, '160;Suhu')) {
            $rows[] = $this->airFieldRow($detail, $headerId, $regulasiId, 'Suhu', 'Suhu', $lapangan->suhu_air, '�C');
        }

        if (!empty($lapangan->debit_air) && stripos((string) ($detail->parameter ?? ''), 'Debit Air') !== false) {
            $parameterName = $this->parameterNameContains($detail->parameter ?? null, 'Debit Air') ?: 'Debit Air';
            $nilai = $lapangan->debit_air;
            if (strpos($nilai, 'Data By Customer') !== false && preg_match('/\((.*?)\)/', $nilai, $matches)) {
                $nilai = $matches[1];
            }
            $debit = $this->parseDebitAirValue($nilai, $parameterName);
            $rows[] = $this->airFieldRow($detail, $headerId, $regulasiId, $parameterName, 'Debit Air', $debit['hasil'], $debit['satuan'], 'Debit Air', 'IKM/ISL/7.2.109 (Perhitungan)');
        }

        return $rows;
    }

    private function airSdFieldRows($detail, $headerId, $regulasiId)
    {
        $sd = $this->sampelDiantarInternalData($detail);
        if (!$sd) {
            return [];
        }

        $rows = [];
        if ($this->orderHasParameter($detail, '128;pH') && !empty($sd['ph'])) {
            $rows[] = $this->airFieldRow($detail, $headerId, $regulasiId, 'pH', 'pH', $sd['ph'], '-');
        }

        if ($this->orderHasParameter($detail, '160;Suhu') && !empty($sd['suhu'])) {
            $rows[] = $this->airFieldRow($detail, $headerId, $regulasiId, 'Suhu', 'Suhu', $sd['suhu'], '�C');
        }

        if (!empty($sd['debit']) && stripos((string) ($detail->parameter ?? ''), 'Debit Air') !== false) {
            $parameterName = $this->parameterNameContains($detail->parameter ?? null, 'Debit Air') ?: 'Debit Air';
            $debit = $this->parseDebitAirValue($sd['debit'], $parameterName);
            $rows[] = $this->airFieldRow($detail, $headerId, $regulasiId, $parameterName, 'Debit Air', $debit['hasil'], $debit['satuan'], 'Debit Air', 'IKM/ISL/7.2.109 (Perhitungan)');
        }

        return $rows;
    }

    private function sampelDiantarInternalData($detail)
    {
        if (!Schema::connection($this->connection)->hasTable('sampel_diantar') || !Schema::connection($this->connection)->hasTable('sampel_diantar_detail')) {
            return null;
        }

        $query = DB::connection($this->connection)->table('sampel_diantar')
            ->where('no_order', $detail->no_order ?? null);

        if (!empty($detail->periode)) {
            $query->where('periode_kontrak', $detail->periode);
        } else {
            $query->whereNull('periode_kontrak');
        }

        $headers = $query->pluck('id')->all();
        if (!$headers) {
            return null;
        }

        $rows = DB::connection($this->connection)->table('sampel_diantar_detail')
            ->whereIn('id_header', $headers)
            ->where('is_active', 1)
            ->pluck('internal_data');

        foreach ($rows as $raw) {
            $decoded = json_decode(html_entity_decode($raw), true);
            if (!is_array($decoded)) {
                continue;
            }
            foreach ($decoded as $item) {
                if (($item['no_sampel'] ?? null) === ($detail->no_sampel ?? null)) {
                    return $item;
                }
            }
        }

        return null;
    }
    private function parseDebitAirValue($value, $parameterName)
    {
        $raw = trim((string) $value);
        if (stripos($raw, 'Data By Customer') !== false && preg_match('/\((.*?)\)/', $raw, $matches)) {
            $raw = trim($matches[1]);
        }

        $unit = null;
        if (preg_match('/\((.*?)\)/', (string) $parameterName, $matches)) {
            $unit = trim($matches[1]);
        }

        $hasil = $raw;
        if (preg_match('/^([+-]?\d+(?:[.,]\d+)?)(?:\s*(.+))?$/', $raw, $matches)) {
            $hasil = str_replace(',', '.', $matches[1]);
            if (!$unit && !empty($matches[2])) {
                $unit = trim($matches[2]);
            }
        }

        return [
            'hasil' => $hasil,
            'satuan' => $unit ?: '-',
        ];
    }
    private function airFieldRow($detail, $headerId, $regulasiId, $parameterLab, $parameter, $hasil, $defaultSatuan, $keterangan = null, $fallbackMethod = null)
    {
        $bakumutu = $this->bakuMutu($regulasiId, $parameterLab);
        $master = DB::connection($this->connection)->table('parameter')
            ->where('nama_lab', $parameterLab)
            ->where('id_kategori', 1)
            ->where('is_active', 1)
            ->first();

        return [
            'id_header' => $headerId,
            'no_sampel' => $detail->no_sampel,
            'akr' => '',
            'parameter_lab' => $parameterLab,
            'parameter' => $parameter,
            'hasil_uji' => $hasil,
            'hasil_uji_json' => null,
            'attr' => '',
            'satuan' => $this->firstFilled($bakumutu->satuan ?? null, $master->satuan ?? null, $defaultSatuan),
            'methode' => $this->firstFilled($bakumutu->method ?? null, $fallbackMethod, $master->method ?? null, '-'),
            'baku_mutu' => json_encode([$bakumutu->baku_mutu ?? '-']),
            'created_by' => 'System',
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];
    }

    private function kpgiDetailRows($type, array $details, $headerId)
    {
        $map = [
            'pencahayaan' => ['table' => 'pencahayaan_header', 'approve' => 'is_approved'],
            'kebisingan' => ['table' => 'kebisingan_header', 'approve' => 'is_approved', 'lapangan' => 'data_lapangan_kebisingan'],
            'kebisingan_personal' => ['table' => 'kebisingan_header', 'approve' => 'is_approved', 'lapangan' => 'data_lapangan_kebisingan_personal'],
            'iklim_kerja' => ['table' => 'isbb_header', 'approve' => 'is_approve'],
            'getaran_personel' => ['table' => 'getaran_header', 'approve' => 'is_approve'],
        ];

        if (!isset($map[$type]) || !Schema::connection($this->connection)->hasTable($map[$type]['table']) || !Schema::connection($this->connection)->hasTable('ws_value_udara')) {
            return [];
        }

        $samples = array_values(array_unique(array_filter(array_map(function ($detail) {
            return $detail->no_sampel ?? null;
        }, $details))));

        if (!$samples) {
            return [];
        }

        $source = $map[$type];
        $items = DB::connection($this->connection)->table($source['table'] . ' as src')
            ->leftJoin('ws_value_udara as ws', 'ws.no_sampel', '=', 'src.no_sampel')
            ->whereIn('src.no_sampel', $samples)
            ->where('src.is_active', 1)
            ->where('src.' . $source['approve'], 1)
            ->select('src.*', 'ws.hasil1', 'ws.hasil2', 'ws.hasil3', 'ws.nab', 'ws.interpretasi')
            ->get();

        $rows = [];
        foreach ($items as $item) {
            $order = $this->findOrderDetail($details, $item->no_sampel);
            $tanggalSampling = $order->tanggal_sampling ?? null;

            if ($type === 'pencahayaan') {
                $lap = $this->lapanganRow('data_lapangan_cahaya', $item->no_sampel);
                $hasil = $this->decodeMaybeJson($item->hasil1);
                $rows[] = [
                    'id_header' => $headerId,
                    'no_sampel' => $item->no_sampel,
                    'param' => $item->parameter,
                    'hasil_uji' => is_array($hasil) ? json_encode($hasil) : str_replace(',', '', (string) $hasil),
                    'sumber_cahaya' => $lap->jenis_cahaya ?? null,
                    'jenis_pengukuran' => (($lap->kategori ?? '') === 'Pencahayaan Umum') ? 'Umum' : 'Lokal',
                    'lokasi_keterangan' => trim((string) ($lap->keterangan ?? '')),
                    'nab' => $item->nab,
                    'tanggal_sampling' => $tanggalSampling,
                ];
                continue;
            }

            if ($type === 'kebisingan_personal') {
                $lap = $this->lapanganRow($source['lapangan'], $item->no_sampel);
                $rows[] = [
                    'id_header' => $headerId,
                    'no_sampel' => $item->no_sampel,
                    'param' => $item->parameter,
                    'lokasi_keterangan' => $lap->departemen ?? null,
                    'paparan' => $this->kebisinganPersonalDurasiPaparan($lap->waktu_pengukuran ?? null),
                    'titik_koordinat' => $lap->titik_koordinat ?? null,
                    'nama_pekerja' => $lap->keterangan ?? null,
                    'leq_ls' => $item->leq_ls ?? null,
                    'leq_lm' => $item->leq_lm ?? null,
                    'leq_lsm' => $item->hasil1,
                    'hasil_uji' => $item->hasil1,
                    'nab' => $this->kebisinganPersonalNab($lap->waktu_pengukuran ?? null),
                    'tanggal_sampling' => $tanggalSampling,
                ];
                continue;
            }

            if ($type === 'kebisingan') {
                $lap = $this->lapanganRow($source['lapangan'], $item->no_sampel);
                $rows[] = [
                    'id_header' => $headerId,
                    'no_sampel' => $item->no_sampel,
                    'param' => $item->parameter,
                    'lokasi_keterangan' => $lap->lokasi_titik_sampling ?? $lap->keterangan ?? null,
                    'paparan' => $lap->jam_pemaparan ?? $lap->waktu_pengukuran ?? null,
                    'titik_koordinat' => $lap->titik_koordinat ?? null,
                    'nama_pekerja' => $lap->created_by ?? null,
                    'leq_ls' => $item->leq_ls ?? null,
                    'leq_lm' => $item->leq_lm ?? null,
                    'leq_lsm' => $item->hasil1,
                    'hasil_uji' => $item->hasil1,
                    'nab' => $item->nab,
                    'tanggal_sampling' => $tanggalSampling,
                ];
                continue;
            }
            if ($type === 'iklim_kerja') {
                $lapPanas = $this->lapanganRow('data_lapangan_iklim_panas', $item->no_sampel);
                $lapDingin = $this->lapanganRow('data_lapangan_iklim_dingin', $item->no_sampel);
                $isDingin = $item->parameter === 'IKD (CS)';
                $rows[] = [
                    'id_header' => $headerId,
                    'no_sampel' => $item->no_sampel,
                    'param' => $item->parameter,
                    'hasil' => $isDingin ? null : $this->iklimPanasHasil($item->hasil1),
                    'indeks_suhu_basah' => $isDingin ? $this->iklimDinginIndeks($item->hasil1) : null,
                    'kecepatan_angin' => $item->rata_kecepatan_angin ?? null,
                    'suhu_temperatur' => $item->rata_suhu ?? null,
                    'keterangan' => $isDingin ? ($lapDingin->keterangan ?? null) : ($lapPanas->keterangan ?? null),
                    'aktivitas_pekerjaan' => $isDingin ? ($lapDingin->aktifitas_kerja ?? null) : ($lapPanas->aktifitas ?? null),
                    'kondisi' => $isDingin ? $item->interpretasi : null,
                    'durasi_paparan' => $isDingin ? null : ($lapPanas->akumulasi_waktu_paparan ?? null),
                    'tanggal_sampling' => $tanggalSampling,
                ];
                continue;
            }

            if ($type === 'getaran_personel') {
                $lap = $this->lapanganRow('data_lapangan_getaran', $item->no_sampel);
                $personal = $this->lapanganRow('data_lapangan_getaran_personal', $item->no_sampel);
                $hasil = $this->decodeMaybeJson($item->hasil1);
                $isPersonal = in_array($item->parameter, ['Getaran (LK) ST', 'Getaran (LK) TL'], true);
                $rows[] = [
                    'id_header' => $headerId,
                    'no_sampel' => $item->no_sampel,
                    'param' => $item->parameter,
                    'keterangan' => $isPersonal
                        ? trim(($personal->keterangan ?? '') . (!empty($personal->nama_pekerja) ? ' (' . $personal->nama_pekerja . ')' : ''))
                        : trim(($lap->keterangan ?? '') . (!empty($lap->nama_pekerja) ? ' (' . $lap->nama_pekerja . ')' : '')),
                    'aktivitas' => $personal->aktifitas ?? null,
                    'sumber_get' => $isPersonal ? ($personal->sumber_getaran ?? null) : ($lap->sumber_getaran ?? null),
                    'hasil' => $this->getaranScalar($hasil),
                    'w_paparan' => $isPersonal ? $this->shortText($personal->durasi_paparan ?? null, 20) : null,
                    'x' => is_array($hasil) ? $this->axisValue($hasil, 'X') : null,
                    'y' => is_array($hasil) ? $this->axisValue($hasil, 'Y') : null,
                    'z' => is_array($hasil) ? $this->axisValue($hasil, 'Z') : null,
                    'nab' => $item->nab,
                    'tipe_getaran' => $isPersonal ? 'getaran personal' : 'getaran',
                    'kecepatan' => $this->shortText(is_array($hasil) ? ($hasil['Kecepatan'] ?? $this->formatGetaranAxis($hasil, 'Kecepatan')) : null, 20),
                    'percepatan' => $this->shortText(is_array($hasil) ? ($hasil['Percepatan'] ?? $this->formatGetaranAxis($hasil, 'Percepatan')) : null, 20),
                    'tanggal_sampling' => $tanggalSampling,
                ];
            }
        }

        return $rows;
    }

    private function sinarUvDetailRows(array $details, $headerId)
    {
        if (!Schema::connection($this->connection)->hasTable('sinaruv_header') || !Schema::connection($this->connection)->hasTable('ws_value_udara')) {
            return [];
        }

        $samples = array_values(array_unique(array_filter(array_map(function ($detail) { return $detail->no_sampel ?? null; }, $details))));
        $items = DB::connection($this->connection)->table('sinaruv_header as src')
            ->leftJoin('ws_value_udara as ws', 'ws.no_sampel', '=', 'src.no_sampel')
            ->leftJoin('parameter as param', function ($join) {
                $join->on('param.nama_lab', '=', 'src.parameter')->where('param.id_kategori', 4)->where('param.is_active', 1);
            })
            ->whereIn('src.no_sampel', $samples)
            ->where('src.is_active', 1)
            ->where('src.is_approved', 1)
            ->select('src.*', 'ws.hasil1', 'ws.hasil2', 'ws.hasil3', 'ws.nab', 'param.id as parameter_id', 'param.status', 'param.method')
            ->get();

        $rows = [];
        foreach ($items as $item) {
            $lap = $this->lapanganRow('data_lapangan_sinaruv', $item->no_sampel);
            $order = $this->findOrderDetail($details, $item->no_sampel);
            $hasil = $this->decodeMaybeJson($item->hasil1);
            $mata = $this->sinarUvHasilUji(1, $item->parameter_id ?? null, is_array($hasil) ? ($hasil['Mata'] ?? '') : ($item->hasil1 ?? ''));
            $siku = $this->sinarUvHasilUji(2, $item->parameter_id ?? null, is_array($hasil) ? ($hasil['Siku'] ?? '') : ($item->hasil2 ?? ''));
            $betis = $this->sinarUvHasilUji(3, $item->parameter_id ?? null, is_array($hasil) ? ($hasil['Betis'] ?? '') : ($item->hasil3 ?? ''));
            $regulasiId = $this->firstRegulationId($order->regulasi ?? null);
            $bakumutu = $this->bakuMutu($regulasiId, $item->parameter);
            $nab = $this->firstFilled(
                $item->nab ?? null,
                $this->sinarUvNab($lap->waktu_pemaparan ?? null),
                $bakumutu->baku_mutu ?? null
            );
            $keterangan = '';
            if ($lap) {
                if (($lap->keterangan_2 ?? null) === '-') {
                    $keterangan = $lap->keterangan ?? '';
                } else {
                    $parts = strpos((string) ($lap->keterangan_2 ?? ''), ':') !== false ? explode(':', $lap->keterangan_2) : [($lap->keterangan_2 ?? '')];
                    $keterangan = ($parts[1] ?? $parts[0] ?? '') . ' - ' . ($lap->aktivitas_pekerja ?? '');
                }
            }
            $rows[] = [
                'id_header' => $headerId,
                'parameter' => $item->parameter,
                'no_sampel' => $item->no_sampel,
                'keterangan' => $keterangan,
                'aktivitas_pekerjaan' => $lap->aktivitas_pekerja ?? '',
                'sumber_radiasi' => $lap->sumber_radiasi ?? '',
                'waktu_pemaparan' => $lap->waktu_pemaparan ?? '',
                'mata' => $mata,
                'siku' => $siku,
                'betis' => $betis,
                'nab' => $nab,
                'methode' => $this->firstFilled($item->method ?? null, $bakumutu->method ?? null),
                'tanggal_sampling' => $order->tanggal_sampling ?? null,
                'akr' => ($item->status ?? null) === 'AKREDITASI' ? '' : 'ẍ',
            ];
        }

        return $rows;
    }

    private function medanLmDetailRows(array $details, $headerId)
    {
        if (!Schema::connection($this->connection)->hasTable('medanlm_header') || !Schema::connection($this->connection)->hasTable('ws_value_udara')) {
            return [];
        }

        $samples = array_values(array_unique(array_filter(array_map(function ($detail) { return $detail->no_sampel ?? null; }, $details))));
        $items = DB::connection($this->connection)->table('medanlm_header as src')
            ->leftJoin('ws_value_udara as ws', 'ws.no_sampel', '=', 'src.no_sampel')
            ->leftJoin('parameter as param', function ($join) {
                $join->on('param.nama_lab', '=', 'src.parameter')->where('param.id_kategori', 4)->where('param.is_active', 1);
            })
            ->whereIn('src.no_sampel', $samples)
            ->where('src.is_active', 1)
            ->where('src.is_approve', 1)
            ->select('src.*', 'ws.hasil1', 'ws.nab', 'ws.nab_medan_listrik', 'ws.nab_medan_magnet', 'ws.nab_power_density', 'param.status', 'param.method')
            ->get();

        $rows = [];
        foreach ($items as $item) {
            $order = $this->findOrderDetail($details, $item->no_sampel);
            $hasil = $this->decodeMaybeJson($item->hasil1);
            $nilai = is_array($hasil)
                ? ($hasil['hasil_mwatt'] ?? $hasil['medan_magnet'] ?? $hasil['hasil_listrik'] ?? json_encode($hasil))
                : $hasil;
            $rows[] = [
                'id_header' => $headerId,
                'no_sampel' => $item->no_sampel,
                'parameter' => $item->parameter,
                'parameter_lab' => $item->parameter,
                'page' => 1,
                'hasil' => $nilai,
                'hasil_uji' => $nilai,
                'satuan' => $item->parameter === 'Power Density' ? 'mW/cm�' : ($item->parameter === 'Medan Magnit Statis' ? 'mT' : ($item->parameter === 'Medan Listrik' ? 'tesla' : '')),
                'nab' => $item->nab ?? $item->nab_power_density ?? $item->nab_medan_magnet ?? $item->nab_medan_listrik,
                'methode' => $item->method ?? null,
                'akr' => ($item->status ?? null) === 'AKREDITASI' ? '' : 'ẍ',
                'tanggal_sampling' => $order->tanggal_sampling ?? null,
            ];
        }

        return $rows;
    }

    private function emisiBergerakDetailRows(array $details, $headerId)
    {
        $rows = [];
        foreach ($details as $detail) {
            $lap = $this->lapanganRow('data_lapangan_emisi_kendaraan', $detail->no_sampel ?? null);
            if (!$lap) {
                continue;
            }

            $isOp = strpos((string) ($detail->kategori_3 ?? ''), '32-') === 0;
            $hasil = $isOp
                ? ['OP' => $lap->opasitas ?? null]
                : ['HC' => $lap->hc ?? null, 'CO' => $lap->co ?? null];
            $baku = $isOp ? ['OP' => '-'] : ['HC' => '-', 'CO' => '-'];
            $kendaraan = $this->emisiKendaraan($detail->no_sampel ?? null);
            $rows[] = [
                'id_header' => $headerId,
                'no_sampel' => $detail->no_sampel,
                'tanggal_sampling' => $detail->tanggal_sampling ?? $detail->tanggal_terima ?? null,
                'hasil_uji' => json_encode($hasil),
                'baku_mutu' => json_encode($baku),
                'nama_kendaraan' => $this->firstFilled($kendaraan->merk_kendaraan ?? null, $lap->nama_kendaraan ?? null, '-'),
                'bobot_kendaraan' => $this->firstFilled($kendaraan->bobot_kendaraan ?? null, $lap->bobot_kendaraan ?? null, '-'),
                'tahun_kendaraan' => $this->firstFilled($kendaraan->tahun_pembuatan ?? null, $lap->tahun_kendaraan ?? null, '-'),
            ];
        }

        return $rows;
    }

    private function emisiKendaraan($noSampel)
    {
        if (!$noSampel || !Schema::connection($this->connection)->hasTable('data_lapangan_emisi_order') || !Schema::connection($this->connection)->hasTable('master_kendaraan')) {
            return null;
        }

        return DB::connection($this->connection)->table('data_lapangan_emisi_order as deo')
            ->leftJoin('master_kendaraan as mk', 'mk.id', '=', 'deo.id_kendaraan')
            ->where('deo.no_sampel', $noSampel)
            ->where('deo.is_active', 1)
            ->where(function ($query) {
                $query->where('mk.is_active', 1)->orWhereNull('mk.id');
            })
            ->select('mk.merk_kendaraan', 'mk.bobot_kendaraan', 'mk.tahun_pembuatan')
            ->first();
    }
    private function lingDetailRows(array $details, $headerId)
    {
        if (!Schema::connection($this->connection)->hasTable('ws_value_lingkungan')) {
            return [];
        }

        $samples = array_values(array_unique(array_filter(array_map(function ($detail) { return $detail->no_sampel ?? null; }, $details))));
        if (!$samples) {
            return [];
        }

        $items = DB::connection($this->connection)->table('ws_value_lingkungan as ws')
            ->leftJoin('lingkungan_header as lh', 'lh.id', '=', 'ws.lingkungan_header_id')
            ->leftJoin('debu_personal_header as dh', 'dh.id', '=', 'ws.debu_personal_header_id')
            ->leftJoin('parameter as param', function ($join) {
                $join->on('param.id', '=', DB::raw('COALESCE(lh.id_parameter, dh.id_parameter)'));
            })
            ->whereIn('ws.no_sampel', $samples)
            ->where('ws.is_active', 1)
            ->where(function ($query) {
                $query->where('lh.is_approved', 1)
                    ->orWhereNotNull('lh.approved_at')
                    ->orWhere('dh.is_approved', 1)
                    ->orWhereNotNull('dh.approved_at');
            })
            ->select('ws.*', DB::raw('COALESCE(lh.parameter, dh.parameter, param.nama_lab) as source_parameter'), 'param.nama_lhp', 'param.nama_regulasi', 'param.satuan', 'param.method', 'param.status')
            ->get();

        $rows = [];
        foreach ($items as $item) {
            $order = $this->findOrderDetail($details, $item->no_sampel);
            $regulasiId = $this->firstRegulationId($order->regulasi ?? null);
            $parameter = $item->source_parameter ?: $item->nama_lab ?: '-';
            $bakumutu = $this->bakuMutu($regulasiId, $parameter);
            $hasil = $this->firstValue($item, ['f_koreksi_c', 'C', 'f_koreksi_c1', 'C1', 'f_koreksi_c2', 'C2']);
            $display = $item->nama_lhp ?? $item->nama_regulasi ?? $parameter;
            if (strtoupper(trim((string) $parameter)) === 'CO' && preg_match('/cobalt|kobalt/i', (string) $display)) {
                $display = 'Karbon Monoksida (CO)';
            }
            $rows[] = [
                'id_header' => $headerId,
                'no_sampel' => $item->no_sampel,
                'parameter_lab' => $parameter,
                'parameter' => $display,
                'hasil_uji' => $hasil,
                'satuan' => $this->firstFilled($bakumutu->satuan ?? null, $item->satuan ?? null, '-'),
                'methode' => $this->firstFilled($bakumutu->method ?? null, $item->method ?? null, '-'),
                'baku_mutu' => json_encode([$bakumutu->baku_mutu ?? '-']),
                'durasi' => $item->durasi ?? null,
                'akr' => ($item->status ?? null) === 'AKREDITASI' ? '' : 'ẍ',
                'tanggal_sampling' => $order->tanggal_sampling ?? null,
                'created_by' => 'System',
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];
        }

        return array_values(array_filter($rows, function ($row) {
            return $row['hasil_uji'] !== null && $row['hasil_uji'] !== '';
        }));
    }

    private function isokinetikDetailRows(array $details, $headerId)
    {
        if (!Schema::connection($this->connection)->hasTable('emisi_cerobong_header') || !Schema::connection($this->connection)->hasTable('ws_value_emisi_cerobong')) {
            return [];
        }

        $samples = array_values(array_unique(array_filter(array_map(function ($detail) { return $detail->no_sampel ?? null; }, $details))));
        if (!$samples) {
            return [];
        }

        $items = DB::connection($this->connection)->table('emisi_cerobong_header as src')
            ->join('ws_value_emisi_cerobong as ws', 'ws.id_emisi_cerobong_header', '=', 'src.id')
            ->leftJoin('parameter as param', function ($join) {
                $join->on('param.id', '=', 'src.id_parameter')
                    ->where('param.id_kategori', 5)
                    ->where('param.is_active', 1);
            })
            ->whereIn('src.no_sampel', $samples)
            ->where('src.is_active', 1)
            ->where('src.is_approved', 1)
            ->where('ws.is_active', 1)
            ->select('src.no_sampel', 'src.parameter as source_parameter', 'ws.C', 'ws.f_koreksi_c', 'ws.C1', 'ws.f_koreksi_c1', 'ws.C2', 'ws.f_koreksi_c2', 'ws.satuan as ws_satuan', 'param.nama_lab', 'param.nama_lhp', 'param.nama_regulasi', 'param.satuan', 'param.method', 'param.status')
            ->get();

        $rows = [];
        foreach ($items as $item) {
            $parameter = $item->source_parameter ?: ($item->nama_lab ?? '-');
            $hasil = $this->firstValue($item, ['f_koreksi_c', 'C', 'f_koreksi_c1', 'C1', 'f_koreksi_c2', 'C2']);
            $display = $item->nama_lhp ?? $item->nama_regulasi ?? $parameter;
            if (strtoupper(trim((string) $parameter)) === 'CO' && preg_match('/cobalt|kobalt/i', (string) $display)) {
                $display = 'Karbon Monoksida (CO)';
            }
            $rows[] = [
                'id_header' => $headerId,
                'parameter_lab' => $parameter,
                'parameter' => $display,
                'hasil_uji' => $hasil,
                'hasil_terkoreksi' => $item->f_koreksi_c ?? null,
                'satuan' => $this->firstFilled($item->satuan ?? null, $item->ws_satuan ?? null, '-'),
                'spesifikasi_metode' => $item->method ?? '-',
                'baku_mutu' => '-',
                'akr' => ($item->status ?? null) === 'AKREDITASI' ? '' : 'ẍ',
            ];
        }

        return array_values(array_filter($rows, function ($row) {
            return $row['hasil_uji'] !== null && $row['hasil_uji'] !== '';
        }));
    }

    private function firstValue($row, array $columns)
    {
        foreach ($columns as $column) {
            if (isset($row->$column) && $row->$column !== null && $row->$column !== '') {
                return $row->$column;
            }
        }

        return null;
    }
    private function formatGetaranAxis(array $hasil, $prefix)
    {
        $values = [];
        foreach ($hasil as $key => $value) {
            if (strpos($key, $prefix . '_') === 0 && $value !== null && $value !== '') {
                $label = strtoupper(substr(str_replace('_', ' ', substr($key, strlen($prefix) + 1)), 0, 1));
                $values[] = $label . ':' . (is_numeric($value) ? rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') : $value);
            }
        }

        return $values ? implode('; ', $values) : null;
    }
    private function iklimPanasHasil($value)
    {
        $decoded = $this->decodeMaybeJson($value);
        if (!is_array($decoded)) {
            return is_numeric($decoded) ? $this->formatOneDecimal((float) $decoded) : $this->shortText($decoded, 20);
        }

        $values = [];
        $isbb = $decoded['isbb'] ?? [];
        if (is_array($isbb)) {
            foreach (['in', 'out'] as $key) {
                if (isset($isbb[$key]) && is_numeric($isbb[$key])) {
                    $values[] = (float) $isbb[$key];
                }
            }
        }

        if (!$values) {
            $average = $decoded['average'] ?? [];
            if (is_array($average)) {
                foreach (['wbtgc_in', 'wbtgc_out'] as $key) {
                    if (isset($average[$key]) && is_numeric($average[$key])) {
                        $values[] = (float) $average[$key];
                    }
                }
            }
        }

        if ($values) {
            return $this->formatOneDecimal(array_sum($values) / count($values));
        }

        return null;
    }

    private function iklimDinginIndeks($value)
    {
        $decoded = $this->decodeMaybeJson($value);
        if (is_array($decoded)) {
            foreach (['hasil', 'indeks_suhu_basah', 'cold_stress', 'cs'] as $key) {
                if (isset($decoded[$key]) && !is_array($decoded[$key]) && $decoded[$key] !== '') {
                    return $this->shortText($decoded[$key], 20);
                }
            }
            foreach ($decoded as $item) {
                if (!is_array($item) && $item !== null && $item !== '') {
                    return $this->shortText($item, 20);
                }
            }
            return null;
        }

        return $this->shortText($value, 20);
    }

    private function formatOneDecimal($value)
    {
        return number_format((float) $value, 1, '.', '');
    }

    private function getaranScalar($hasil)
    {
        if (!is_array($hasil)) {
            return $this->shortText($hasil, 20);
        }

        foreach (['Aeq', 'aeq', 'Percepatan', 'Kecepatan', 'X', 'Y', 'Z'] as $key) {
            if (isset($hasil[$key]) && !is_array($hasil[$key]) && $hasil[$key] !== '') {
                return $this->shortText($hasil[$key], 20);
            }
        }

        foreach ($hasil as $value) {
            if (!is_array($value) && $value !== null && $value !== '') {
                return $this->shortText($value, 20);
            }
        }

        return null;
    }

    private function axisValue(array $hasil, $axis)
    {
        foreach ([$axis, strtolower($axis), 'axis_' . strtolower($axis)] as $key) {
            if (isset($hasil[$key]) && !is_array($hasil[$key]) && $hasil[$key] !== '') {
                return $this->shortText($hasil[$key], 20);
            }
        }

        return null;
    }

    private function shortText($value, $max)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $text = is_array($value) ? json_encode($value) : (string) $value;
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
    }
    private function lapanganRow($table, $noSampel)
    {
        if (!$noSampel || !Schema::connection($this->connection)->hasTable($table)) {
            return null;
        }

        $columns = Schema::connection($this->connection)->getColumnListing($table);
        if (!in_array('no_sampel', $columns, true)) {
            return null;
        }

        $query = DB::connection($this->connection)->table($table)->where('no_sampel', $noSampel);
        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', 1);
        }

        return $query->first();
    }

    private function findOrderDetail(array $details, $noSampel)
    {
        foreach ($details as $detail) {
            if (($detail->no_sampel ?? null) === $noSampel) {
                return $detail;
            }
        }

        return null;
    }

    private function decodeMaybeJson($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
    private function distinctDateList(array $rows, array $columns)
    {
        $dates = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                if (isset($row->$column) && trim((string) $row->$column) !== '') {
                    try {
                        $dates[] = Carbon::parse($row->$column)->format('Y-m-d');
                    } catch (\Throwable $th) {
                        $dates[] = trim((string) $row->$column);
                    }
                    break;
                }
            }
        }

        $dates = array_values(array_unique(array_filter($dates)));
        sort($dates);
        return $dates ? implode(', ', $dates) : null;
    }

    private function sinarUvMethodJson(array $details)
    {
        $ids = [];
        foreach ($details as $detail) {
            foreach ($this->parameters($detail->parameter ?? null) as $parameter) {
                if (($parameter['name'] ?? null) === 'Sinar UV' && !empty($parameter['id'])) {
                    $ids[] = (int) $parameter['id'];
                }
            }
        }

        if (!$ids || !Schema::connection($this->connection)->hasTable('parameter')) {
            return null;
        }

        $methods = DB::connection($this->connection)->table('parameter')
            ->whereIn('id', array_values(array_unique($ids)))
            ->where('is_active', 1)
            ->pluck('method')
            ->all();

        $methods = array_values(array_unique(array_filter(array_map('trim', $methods))));
        return $methods ? json_encode($methods) : null;
    }

    private function sinarUvHasilUji($index, $parameterId, $hasilUji)
    {
        if ($hasilUji === null || $hasilUji === '' || $hasilUji === '-' || strpos((string) $hasilUji, '<') !== false) {
            return $hasilUji;
        }

        if (!$parameterId || !Schema::connection($this->connection)->hasTable('mdl_udara')) {
            return $hasilUji;
        }

        $column = 'hasil' . ($index ?: 1);
        $mdlColumns = Schema::connection($this->connection)->getColumnListing('mdl_udara');
        if (!in_array($column, $mdlColumns, true)) {
            return $hasilUji;
        }

        $mdl = DB::connection($this->connection)->table('mdl_udara')
            ->where('parameter_id', $parameterId)
            ->whereNotNull($column)
            ->where('is_active', 1)
            ->value($column);

        if ($mdl !== null && is_numeric($mdl) && is_numeric($hasilUji) && (float) $mdl > (float) $hasilUji) {
            return '<' . $mdl;
        }

        return $hasilUji;
    }




    private function defaultLhpKeteranganJson()
    {
        return json_encode([
            html_entity_decode('&#x25B2;', ENT_QUOTES, 'UTF-8') . ' Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
            html_entity_decode('&#x2198;', ENT_QUOTES, 'UTF-8') . ' Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
            html_entity_decode('&#x1E8D;', ENT_QUOTES, 'UTF-8') . ' Parameter belum terakreditasi.',
        ], JSON_UNESCAPED_UNICODE);
    }

    private function medanLmHasilObservasiJson($type, array $details)
    {
        $rows = $this->medanLmObservationRows($type, $details);
        $observasi = [];
        foreach ($rows as $row) {
            $observasi[] = $row['observasi'] ?? [];
        }

        return $observasi ? json_encode($observasi, JSON_UNESCAPED_UNICODE) : null;
    }

    private function medanLmKesimpulanJson($type, array $details)
    {
        $rows = $this->medanLmObservationRows($type, $details);
        $kesimpulan = [];
        foreach ($rows as $row) {
            $kesimpulan[] = $row['kesimpulan'] ?? [];
        }

        return $kesimpulan ? json_encode($kesimpulan, JSON_UNESCAPED_UNICODE) : null;
    }

    private function medanLmObservationRows($type, array $details)
    {
        $rows = [];
        foreach ($details as $detail) {
            $sample = $detail->no_sampel ?? null;
            if (!$sample) {
                continue;
            }

            $lap = $this->lapanganRow('data_lapangan_medan_lm', $sample);
            if (!$lap) {
                continue;
            }

            $source = DB::connection($this->connection)->table('medanlm_header as src')
                ->leftJoin('ws_value_udara as ws', 'ws.no_sampel', '=', 'src.no_sampel')
                ->where('src.no_sampel', $sample)
                ->where('src.is_active', 1)
                ->where('src.is_approve', 1)
                ->first(['src.parameter', 'ws.hasil1', 'ws.nab', 'ws.nab_medan_listrik', 'ws.nab_medan_magnet', 'ws.nab_power_density']);

            if (!$source) {
                continue;
            }

            $parameter = $source->parameter ?? ($lap->parameter ?? null);
            $hasil = $this->medanLmNilaiHasil($source->hasil1 ?? null, $parameter);
            $lokasi = $this->cleanText($lap->keterangan ?? $lap->lokasi ?? $sample);
            $waktu = $this->cleanText($lap->waktu_pemaparan ?? null);
            $frekuensi = $this->medanLmFrekuensi($lap);

            if ($type === 'gelombang_mikro') {
                $rows[] = $this->gelombangMikroObservationRow($lokasi, $frekuensi, $waktu, $hasil, $source);
                continue;
            }

            $rows[] = $this->medanMagnetObservationRow($lokasi, $frekuensi, $waktu, $hasil, $source);
        }

        return array_values(array_filter($rows));
    }

    private function medanMagnetObservationRow($lokasi, $frekuensi, $waktu, $hasil, $source)
    {
        $nab = $this->firstFilled($source->nab ?? null, $source->nab_medan_magnet ?? null, $this->medanMagnetNab($frekuensi));
        $observasi = [
            'Untuk menentukan nilai NAB pada pengujian Medan Magnet dapat ditentukan dengan pengukuran frekuensi.',
        ];

        $freqText = $frekuensi !== null ? $this->formatNumber($frekuensi) . ' Hz' : '-';
        $timeText = $waktu !== '' ? ' dengan waktu pemaparan ' . $waktu . ' menit' : '';
        $observasi[] = 'Pengukuran frekuensi pada ' . $lokasi . ' didapatkan ' . $freqText . $timeText . '.';

        if ($nab !== null && $nab !== '-') {
            $observasi[] = 'Berdasarkan frekuensi yang didapat NAB Medan Magnet sebesar ' . $this->formatNumber($nab) . ' mT.';
            $kesimpulan = [
                'Berdasarkan frekuensi diatas, dapat disimpulkan bahwa hasil uji parameter Medan Magnet dengan nilai ' . $this->formatNumber($hasil) . ' mT ' . $this->memenuhiText($hasil, $nab) . ' NAB.',
            ];
        } else {
            $observasi[] = 'Berdasarkan data frekuensi yang diketahui, medan magnet tidak dapat dibandingkan dengan Nilai Ambang Batas (NAB).';
            $kesimpulan = [
                'Berdasarkan frekuensi di atas, diketahui bahwa frekuensi tersebut melebihi rentang yang diatur pada tabel Nilai Ambang Batas (NAB).',
                'Oleh karena itu, parameter medan magnet tidak dapat dibandingkan secara langsung dengan Nilai Ambang Batas (NAB).',
            ];
        }

        return ['observasi' => $observasi, 'kesimpulan' => $kesimpulan];
    }

    private function gelombangMikroObservationRow($lokasi, $frekuensi, $waktu, $hasil, $source)
    {
        $nab = $this->firstFilled($source->nab ?? null, $source->nab_power_density ?? null, $source->nab_medan_magnet ?? null, $source->nab_medan_listrik ?? null);
        $observasi = [
            'Untuk menentukan nilai NAB pada pengujian Gelombang Mikro dapat ditentukan dengan pengukuran frekuensi dan waktu pemaparan.',
        ];

        $freqText = $frekuensi !== null ? $this->formatNumber($frekuensi) . ' Hz' : '-';
        $timeText = $waktu !== '' ? ' dengan waktu pemaparan ' . $waktu . ' menit' : '';
        $observasi[] = 'Pengukuran frekuensi pada ' . $lokasi . ' didapatkan ' . $freqText . $timeText . '.';

        if ($nab !== null && $nab !== '-') {
            $observasi[] = 'Berdasarkan frekuensi yang didapat NAB gelombang mikro mengacu pada parameter power density.';
            $observasi[] = 'NAB untuk parameter power density didapatkan ' . $this->formatNumber($nab) . ' mW/cm�.';
            $kesimpulan = [
                'Berdasarkan NAB dengan parameter power density, Gelombang Mikro dapat disimpulkan ' . $this->memenuhiText($hasil, $nab) . ' NAB dengan nilai ' . $this->formatNumber($hasil) . ' mW/cm�.',
            ];
        } else {
            $observasi[] = 'Berdasarkan waktu pemaparan yang didapatkan gelombang mikro tidak dapat dibandingkan dengan NAB.';
            $kesimpulan = [
                'Berdasarkan waktu pemaparan di atas diketahui bahwa waktu tersebut melebihi waktu pemaparan (menit) pada tabel Nilai Ambang Batas (NAB).',
                'Oleh karena itu, parameter gelombang mikro tidak dapat dibandingkan secara langsung dengan Nilai Ambang Batas (NAB).',
            ];
        }

        return ['observasi' => $observasi, 'kesimpulan' => $kesimpulan];
    }

    private function medanLmNilaiHasil($raw, $parameter)
    {
        $hasil = $this->decodeMaybeJson($raw);
        if (is_array($hasil)) {
            return $hasil['hasil_mwatt'] ?? $hasil['medan_magnet'] ?? $hasil['hasil_listrik'] ?? reset($hasil);
        }

        return $hasil;
    }

    private function medanLmFrekuensi($lap)
    {
        $values = [];
        foreach (['frekuensi_3', 'frekuensi_30', 'frekuensi_100'] as $column) {
            $value = $this->cleanText($lap->{$column} ?? null);
            $numeric = str_replace(',', '.', $value);
            if ($value !== '' && is_numeric($numeric)) {
                $values[] = (float) $numeric;
            }
        }

        return $values ? array_sum($values) / count($values) : null;
    }

    private function medanMagnetNab($frekuensi)
    {
        if ($frekuensi === null || !is_numeric($frekuensi) || $frekuensi <= 0) {
            return null;
        }

        if ($frekuensi <= 100) {
            return 60 / $frekuensi;
        }

        if ($frekuensi <= 3000) {
            return 0.2;
        }

        return null;
    }

    private function memenuhiText($hasil, $nab)
    {
        $hasilNum = $this->numericValue($hasil);
        $nabNum = $this->numericValue($nab);
        if ($hasilNum !== null && $nabNum !== null && $hasilNum > $nabNum) {
            return 'tidak memenuhi';
        }

        return 'memenuhi';
    }

    private function numericValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = str_replace(',', '.', (string) $value);
        $text = ltrim($text, '<> ');
        return is_numeric($text) ? (float) $text : null;
    }

    private function formatNumber($value)
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (!is_numeric($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }
    private function jsonArrayValue($value)

    {
        $value = $this->cleanText($value);
        if ($value === '' || $value === '-') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode(is_array($decoded) ? $decoded : [$decoded]);
        }

        return json_encode([$value]);
    }
    private function kebisinganPersonalDurasiPaparan($waktuPaparan)

    {
        if (!$waktuPaparan || !is_string($waktuPaparan)) {
            return '-';
        }

        preg_match('/(\d+(\.\d+)?)\s*Jam/i', $waktuPaparan, $jamMatch);
        preg_match('/(\d+(\.\d+)?)\s*Menit/i', $waktuPaparan, $menitMatch);

        $jam = isset($jamMatch[1]) ? (float) $jamMatch[1] : 0;
        $menit = isset($menitMatch[1]) ? (float) $menitMatch[1] : 0;
        $durasi = $jam + ($menit / 60);

        if (!is_numeric($durasi)) {
            return '-';
        }

        return number_format($durasi, 1, '.', '');
    }

    private function kebisinganPersonalNab($waktuPaparan)
    {
        if (!$waktuPaparan || !is_string($waktuPaparan)) {
            return '-';
        }

        preg_match('/(\d+(\.\d+)?)\s*Jam/i', $waktuPaparan, $jamMatch);
        preg_match('/(\d+(\.\d+)?)\s*Menit/i', $waktuPaparan, $menitMatch);

        $jam = isset($jamMatch[1]) ? (float) $jamMatch[1] : 0;
        $menit = isset($menitMatch[1]) ? (float) $menitMatch[1] : 0;

        if (!isset($jamMatch[1]) && !isset($menitMatch[1])) {
            return '-';
        }
        if (!is_numeric($jam) || !is_numeric($menit)) {
            return '-';
        }

        $durasi = (float) number_format($jam + ($menit / 60), 1, '.', '');
        if ($durasi <= 0 || !is_numeric($durasi)) {
            return '-';
        }

        if ($durasi == 8) return 85;
        if ($durasi >= 4 && $durasi < 8) return 85;
        if ($durasi >= 3 && $durasi < 4) return 88;

        $m = $durasi * 60;
        if ($m == 30) return 97;
        if ($m > 15 && $m < 30) return 97;
        if ($m == 15) return 100;
        if ($m > 7.5 && $m < 15) return 100;
        if ($m == 7.5) return 103;
        if ($m > 3.75 && $m < 7.5) return 103;
        if ($m == 3.75) return 106;
        if ($m > 1.88 && $m < 3.75) return 106;
        if ($m == 1.88) return 109;
        if ($m > 0.94 && $m < 1.88) return 109;
        if ($m == 0.94) return 112;

        return '-';
    }
    private function sinarUvNab($waktu)

    {
        if ($waktu === null || $waktu === '') {
            return null;
        }

        $waktu = (float) $waktu;
        if ($waktu >= 480) {
            return 0.0001;
        }
        if ($waktu > 240 && $waktu < 480) {
            return 0.0001;
        }
        if ($waktu > 120 && $waktu <= 240) {
            return 0.0002;
        }
        if ($waktu > 60 && $waktu <= 120) {
            return 0.0004;
        }
        if ($waktu > 30 && $waktu <= 60) {
            return 0.0008;
        }
        if ($waktu > 15 && $waktu <= 30) {
            return 0.0017;
        }
        if ($waktu > 10 && $waktu <= 15) {
            return 0.0033;
        }
        if ($waktu > 5 && $waktu <= 10) {
            return 0.005;
        }
        if ($waktu > 1 && $waktu <= 5) {
            return 0.01;
        }
        if ($waktu > 0.5 && $waktu <= 1) {
            return 0.05;
        }
        if ($waktu > 0.1667 && $waktu <= 0.5) {
            return 0.1;
        }
        if ($waktu > 0.0167 && $waktu <= 0.1667) {
            return 0.3;
        }
        if ($waktu > 0.0083 && $waktu <= 0.0167) {
            return 3;
        }
        if ($waktu > 0.0017 && $waktu <= 0.0083) {
            return 6;
        }
        if ($waktu == 0.0017) {
            return 30;
        }

        return null;
    }
    private function cleanText($value)

    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/', ' ', trim((string) $value));
        return $value === '' ? null : $value;
    }
    private function firstFilled(...$values)
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }
    private function bakuMutu($regulasiId, $parameter)
    {
        if (!$regulasiId || !Schema::connection($this->connection)->hasTable('master_bakumutu')) {
            return null;
        }

        return DB::connection($this->connection)->table('master_bakumutu')
            ->where('id_regulasi', $regulasiId)
            ->where('parameter', $parameter)
            ->where('is_active', 1)
            ->first();
    }

    private function orderHasParameter($detail, $needle)
    {
        $params = json_decode($detail->parameter ?? '', true);
        if (!is_array($params)) {
            return stripos((string) ($detail->parameter ?? ''), $needle) !== false;
        }

        return in_array($needle, $params, true);
    }

    private function parameterNameContains($raw, $needle)
    {
        foreach ($this->parameters($raw) as $parameter) {
            if (stripos($parameter['name'], $needle) !== false) {
                return $parameter['name'];
            }
        }

        return null;
    }

    private function firstRegulationId($raw)
    {
        if (!$raw) {
            return null;
        }

        $decoded = json_decode($raw, true);
        $items = is_array($decoded) ? $decoded : [$raw];
        foreach ($items as $item) {
            if (is_numeric($item)) {
                return (int) $item;
            }
            if (is_string($item) && preg_match('/^(\d+)/', $item, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }
    private function detailRow($type, $detail, array $parameter, $headerId)
    {
        $base = [
            'id_header' => $headerId,
            'no_sampel' => $detail->no_sampel,
            'parameter' => $parameter['name'],
            'parameter_lab' => $parameter['name'],
            'param' => $parameter['name'],
            'tanggal_sampling' => $detail->tanggal_sampling ?? null,
            'keterangan' => $detail->keterangan_1 ?? null,
            'hasil_uji' => '-',
            'satuan' => null,
            'methode' => null,
            'baku_mutu' => null,
            'created_by' => 'System',
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        if ($type === 'pencahayaan') {
            $base['lokasi_keterangan'] = $detail->keterangan_1 ?? null;
            $base['sumber_cahaya'] = null;
            $base['jenis_pengukuran'] = null;
        }

        if ($type === 'lk_sinar_uv') {
            $base['aktivitas_pekerjaan'] = $detail->keterangan_1 ?? null;
            $base['sumber_radiasi'] = null;
            $base['waktu_pemaparan'] = null;
            $base['nab'] = null;
            $base['mata'] = null;
            $base['siku'] = null;
            $base['betis'] = null;
        }

        if ($type === 'emisi_sumber_bergerak') {
            if ($this->categoryId($detail->kategori_3 ?? null) === 32) {
                $base['hasil_uji'] = json_encode(['OP' => '-']);
                $base['baku_mutu'] = json_encode(['OP' => '-']);
            } else {
                $base['hasil_uji'] = json_encode(['HC' => '-', 'CO' => '-']);
                $base['baku_mutu'] = json_encode(['HC' => '-', 'CO' => '-']);
            }
            $base['nama_kendaraan'] = '-';
            $base['bobot_kendaraan'] = '-';
            $base['tahun_kendaraan'] = '-';
        }

        return $base;
    }

    private function releaseDateFromParameterSources($type, array $details)
    {
        $dates = [];
        $sampleNumbers = array_values(array_unique(array_filter(array_map(function ($detail) {
            return $detail->no_sampel ?? null;
        }, $details))));

        foreach ($this->sourceTablesForType($type) as $table) {
            foreach ($sampleNumbers as $noSampel) {
                $date = $this->latestApprovedAtForSample($table, $noSampel);
                if ($date) {
                    $dates[] = $date;
                }
            }
        }

        if (!$dates) {
            return null;
        }

        rsort($dates);
        return Carbon::parse($dates[0])->format('Y-m-d');
    }

    private function latestApprovedAtForSample($table, $noSampel)
    {
        if (!Schema::connection($this->connection)->hasTable($table)) {
            return null;
        }

        $columns = Schema::connection($this->connection)->getColumnListing($table);
        if (!in_array('approved_at', $columns, true)) {
            return null;
        }

        $sampleColumn = in_array('no_sampel', $columns, true)
            ? 'no_sampel'
            : (in_array('no_sample', $columns, true) ? 'no_sample' : null);

        if (!$sampleColumn) {
            return null;
        }

        $query = DB::connection($this->connection)->table($table)
            ->where($sampleColumn, $noSampel)
            ->whereNotNull('approved_at');

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', 1);
        }

        return $query->max('approved_at');
    }

    private function sourceTablesForType($type)
    {
        $map = [
            'air' => ['titrimetri', 'gravimetri', 'colorimetri', 'subkontrak', 'ws_value_air'],
            'lk_sinar_uv' => ['sinaruv_header', 'ws_value_udara'],
            'kebisingan' => ['kebisingan_header', 'ws_value_udara'],
            'kebisingan_personal' => ['kebisingan_header', 'ws_value_udara'],
            'pencahayaan' => ['pencahayaan_header', 'ws_value_udara'],
            'getaran_personel' => ['getaran_header', 'ws_value_udara'],
            'iklim_kerja' => ['isbb_header', 'ws_value_udara'],
            'medan_magnet' => ['medanlm_header', 'ws_value_udara'],
            'gelombang_mikro' => ['medanlm_header', 'ws_value_udara'],
            'udara_lingkungan_hidup' => ['lingkungan_header', 'ws_value_lingkungan', 'subkontrak'],
            'udara_lingkungan_kerja' => ['lingkungan_header', 'ws_value_lingkungan', 'subkontrak'],
            'emisi_sumber_bergerak' => ['emisi_cerobong_header', 'ws_value_emisi_cerobong', 'subkontrak'],
            'emisi_isokinetik' => ['isokinetik_header', 'ws_value_emisi_cerobong', 'subkontrak'],
        ];

        return $map[$type] ?? [];
    }

    private function filterColumns($table, array $row)
    {
        $columns = Schema::connection($this->connection)->getColumnListing($table);
        return array_intersect_key($row, array_flip($columns));
    }

    private function resolveTypes($type, $except = null)
    {
        if (!$type || $type === 'all') {
            $types = array_keys(self::TYPE_CONFIG);
        } else {
            if (!isset(self::TYPE_CONFIG[$type])) {
                throw new \InvalidArgumentException('Type LHP tidak dikenal: ' . $type);
            }
            $types = [$type];
        }

        $exceptTypes = array_filter(array_map('trim', explode(',', (string) $except)));
        foreach ($exceptTypes as $exceptType) {
            if (!isset(self::TYPE_CONFIG[$exceptType])) {
                throw new \InvalidArgumentException('Type LHP except tidak dikenal: ' . $exceptType);
            }
        }

        if ($exceptTypes) {
            $types = array_values(array_diff($types, $exceptTypes));
        }

        return $types;
    }

    private function parameterJson(array $details)
    {
        $params = [];
        foreach ($details as $detail) {
            foreach ($this->parameters($detail->parameter ?? null) as $parameter) {
                $params[] = $parameter['name'];
            }
        }

        $params = array_values(array_unique(array_filter($params)));
        return $params ? json_encode($params) : null;
    }

    private function parameters($raw)
    {
        if (!$raw) {
            return [['id' => null, 'name' => '-']];
        }

        $decoded = json_decode($raw, true);
        $items = is_array($decoded) ? $decoded : explode(',', $raw);
        $result = [];

        foreach ($items as $item) {
            $text = is_array($item) ? implode(';', $item) : (string) $item;
            $text = trim($text, " \t\n\r\0\x0B\"'");
            if ($text === '') {
                continue;
            }

            $parts = explode(';', $text, 2);
            $result[] = [
                'id' => isset($parts[1]) ? trim($parts[0]) : null,
                'name' => trim($parts[1] ?? $parts[0]),
            ];
        }

        return $result ?: [['id' => null, 'name' => '-']];
    }

    private function jsonOrNull($value)
    {
        if (!$value) {
            return null;
        }

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE ? $value : json_encode([$value]);
    }

    private function categoryName($value)
    {
        if (!$value || strpos($value, '-') === false) {
            return null;
        }

        return trim(explode('-', $value, 2)[1]);
    }
    private function categoryId($value)
    {
        if (!$value || strpos($value, '-') === false) {
            return null;
        }

        return (int) explode('-', $value, 2)[0];
    }
}

