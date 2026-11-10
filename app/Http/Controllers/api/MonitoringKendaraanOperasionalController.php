<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DaftarMobil;
use App\Models\Jadwal;
use App\Models\MonitoringKendaraanOperasional;
use App\Models\ServiceMobil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class MonitoringKendaraanOperasionalController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->input('tab', MonitoringKendaraanOperasional::TAB_TERSEDIA);

        if ($tab === MonitoringKendaraanOperasional::TAB_SEDANG_DIGUNAKAN) {
            return $this->indexSedangDigunakan($request);
        }

        return $this->indexTersedia($request);
    }

    public function tabCounts(Request $request)
    {
        $tanggal = $this->resolveTanggal($request);

        return response()->json([
            'message' => 'Tab counts loaded',
            'data' => [
                MonitoringKendaraanOperasional::TAB_TERSEDIA => (int) $this->tersediaQuery($tanggal)->count(),
                MonitoringKendaraanOperasional::TAB_SEDANG_DIGUNAKAN => $this->countJadwalSedangDigunakan($tanggal),
            ],
        ], 200);
    }

    private function indexTersedia(Request $request)
    {
        $tanggal = $this->resolveTanggal($request);
        $jadwalOverlay = $this->jadwalOverlayByPlat($tanggal);
        $serviceStatusByPlat = $this->serviceStatusByPlat($tanggal);
        $data = $this->tersediaQuery($tanggal, $jadwalOverlay);

        return Datatables::of($data)
            ->addColumn('status_kendaraan', function ($row) use ($jadwalOverlay, $serviceStatusByPlat) {
                return $this->resolveStatusTersedia(
                    $row->plat_mobil,
                    $jadwalOverlay,
                    $serviceStatusByPlat
                );
            })
            ->filterColumn('plat_mobil', function ($query, $keyword) {
                $query->where('plat_mobil', 'like', "%{$keyword}%");
            })
            ->filterColumn('status_kendaraan', function ($query, $keyword) use ($jadwalOverlay, $serviceStatusByPlat) {
                $this->filterStatusTersedia($query, $keyword, $jadwalOverlay, $serviceStatusByPlat);
            })
            ->make(true);
    }

    private function tersediaQuery(string $tanggal, ?array $jadwalOverlay = null)
    {
        $jadwalOverlay = $jadwalOverlay ?? $this->jadwalOverlayByPlat($tanggal);
        $fixKeys = $this->occupyingFixKeys($jadwalOverlay);

        $daftar = DB::table('daftar_mobil')
            ->where('is_active', true)
            ->select([
                'id',
                'plat_mobil',
                'merk_mobil',
                'tipe_mobil',
                'warna_mobil',
                'tahun_perakitan',
                'nomor_rangka',
                'nomor_mesin',
            ]);
        $this->excludeNormalizedPlats($daftar, $fixKeys, 'plat_mobil');

        $bookedMissing = DB::table('jadwal as j')
            ->select([
                DB::raw("CONCAT('j', MAX(j.id)) as id"),
                'j.kendaraan as plat_mobil',
                DB::raw('NULL as merk_mobil'),
                DB::raw('NULL as tipe_mobil'),
                DB::raw('NULL as warna_mobil'),
                DB::raw('NULL as tahun_perakitan'),
                DB::raw('NULL as nomor_rangka'),
                DB::raw('NULL as nomor_mesin'),
            ])
            ->where('j.is_active', true)
            ->whereNotNull('j.no_quotation')
            ->where('j.status', 0)
            ->whereNotNull('j.kendaraan')
            ->where('j.kendaraan', '!=', '');
        $this->applyBookedOverlayWindow($bookedMissing, $tanggal, 'j.tanggal', 'j.durasi');
        $bookedMissing
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('daftar_mobil as dm')
                    ->where('dm.is_active', 1)
                    ->whereRaw($this->platNormalizeSql('dm.plat_mobil') . ' = ' . $this->platNormalizeSql('j.kendaraan'));
            })
            ->whereNotExists(function ($query) use ($tanggal) {
                $query->select(DB::raw(1))
                    ->from('jadwal as jf')
                    ->where('jf.is_active', 1)
                    ->whereNotNull('jf.no_quotation')
                    ->where('jf.status', 1)
                    ->whereRaw($this->platNormalizeSql('jf.kendaraan') . ' = ' . $this->platNormalizeSql('j.kendaraan'));
                $this->applyOccupancyWindow($query, $tanggal, 'jf.tanggal', 'jf.durasi');
            })
            ->groupBy('j.kendaraan');

        $serviceMissing = DB::table('service_mobil as sm')
            ->select([
                DB::raw("CONCAT('s', MAX(sm.id)) as id"),
                'sm.plat_mobil',
                DB::raw('MAX(sm.merk_mobil) as merk_mobil'),
                DB::raw('MAX(sm.tipe_mobil) as tipe_mobil'),
                DB::raw('NULL as warna_mobil'),
                DB::raw('NULL as tahun_perakitan'),
                DB::raw('NULL as nomor_rangka'),
                DB::raw('NULL as nomor_mesin'),
            ])
            ->where('sm.is_active', true)
            ->where('sm.status', ServiceMobil::STATUS_DALAM_PERBAIKAN)
            ->whereNotNull('sm.plat_mobil')
            ->where('sm.plat_mobil', '!=', '')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('daftar_mobil as dm')
                    ->where('dm.is_active', 1)
                    ->whereRaw($this->platNormalizeSql('dm.plat_mobil') . ' = ' . $this->platNormalizeSql('sm.plat_mobil'));
            })
            ->groupBy('sm.plat_mobil');

        $data = DB::query()->fromSub(
            $daftar->union($bookedMissing)->union($serviceMissing),
            'mko_tersedia'
        );
        $this->excludeNormalizedPlats($data, $fixKeys, 'plat_mobil');

        return $data;
    }

    private function jadwalOverlayByPlat(string $tanggal): array
    {
        $query = Jadwal::query()
            ->select('kendaraan', 'status', 'tanggal', 'durasi')
            ->where('is_active', true)
            ->whereNotNull('no_quotation')
            ->whereNotNull('kendaraan')
            ->where('kendaraan', '!=', '');
        $this->applyBookedOverlayWindow($query, $tanggal, 'tanggal', 'durasi');
        $rows = $query->get(['kendaraan', 'status', 'tanggal', 'durasi']);

        $occupying = [];
        $booked = [];
        foreach ($rows as $row) {
            $status = (int) $row->status;
            $start = $this->toDateString($row->tanggal);
            if ($start === '') {
                continue;
            }

            $occupies = $this->occupiesDate($start, $row->durasi, $tanggal);
            $isUpcomingBook = $status === 0 && $start >= $tanggal;

            foreach ($this->platLookupKeys($row->kendaraan) as $key) {
                if ($occupies) {
                    $occupying[$key] = max($occupying[$key] ?? -1, $status);
                }
                if (($occupies && $status === 0) || $isUpcomingBook) {
                    $booked[$key] = true;
                }
            }
        }

        return [
            'occupying' => $occupying,
            'booked' => $booked,
        ];
    }

    private function serviceStatusByPlat(string $tanggal): array
    {
        $rows = ServiceMobil::where('is_active', true)
            ->where('status', ServiceMobil::STATUS_DALAM_PERBAIKAN)
            ->get(['plat_mobil', 'tanggal_selesai_estimasi']);

        $map = [];
        foreach ($rows as $row) {
            $key = $this->normalizePlatKey($row->plat_mobil);
            if ($key === '') {
                continue;
            }

            $overdue = $row->tanggal_selesai_estimasi
                && $row->tanggal_selesai_estimasi < $tanggal;
            $status = $overdue
                ? MonitoringKendaraanOperasional::STATUS_RUSAK
                : MonitoringKendaraanOperasional::STATUS_SEDANG_DI_MAINTENANCE;

            if (!isset($map[$key]) || $status === MonitoringKendaraanOperasional::STATUS_RUSAK) {
                $map[$key] = $status;
            }
        }

        return $map;
    }

    private function resolveStatusTersedia($plat, array $jadwalOverlay, array $serviceStatusByPlat): string
    {
        $serviceStatus = $this->lookupMappedValue($plat, $serviceStatusByPlat);
        if ($serviceStatus !== null) {
            return $serviceStatus;
        }

        $occupying = $this->lookupMappedValue($plat, $jadwalOverlay['occupying'] ?? []);
        if ($occupying !== null && (int) $occupying === 1) {
            return MonitoringKendaraanOperasional::STATUS_SEDANG_DI_GUNAKAN;
        }

        $isBooked = $this->lookupMappedValue($plat, $jadwalOverlay['booked'] ?? []) !== null;
        if ($isBooked || ($occupying !== null && (int) $occupying === 0)) {
            return MonitoringKendaraanOperasional::STATUS_SEDANG_DIBOOKING;
        }

        return MonitoringKendaraanOperasional::STATUS_TERSEDIA;
    }

    private function filterStatusTersedia($query, string $keyword, array $jadwalOverlay, array $serviceStatusByPlat = []): void
    {
        $needle = strtoupper(preg_replace('/\s+/', ' ', trim($keyword)));
        if ($needle === '') {
            return;
        }

        $booked = array_keys($jadwalOverlay['booked'] ?? []);
        $used = [];
        foreach ($jadwalOverlay['occupying'] ?? [] as $plat => $status) {
            if ((int) $status === 1) {
                $used[] = $plat;
            } elseif ((int) $status === 0) {
                $booked[] = $plat;
            }
        }
        $booked = array_values(array_unique($booked));

        $rusak = [];
        $maintenance = [];
        foreach ($serviceStatusByPlat as $plat => $status) {
            if ($status === MonitoringKendaraanOperasional::STATUS_RUSAK) {
                $rusak[] = $plat;
            } elseif ($status === MonitoringKendaraanOperasional::STATUS_SEDANG_DI_MAINTENANCE) {
                $maintenance[] = $plat;
            }
        }

        if (str_contains($needle, 'BOOK')) {
            $this->whereNormalizedPlats($query, $booked, true);
            return;
        }

        if (str_contains($needle, 'RUSAK')) {
            $this->whereNormalizedPlats($query, $rusak, true);
            return;
        }

        if (str_contains($needle, 'MAINTENANCE') || str_contains($needle, 'PERBAIKAN')) {
            $this->whereNormalizedPlats($query, $maintenance, true);
            return;
        }

        if (str_contains($needle, 'GUNA') || str_contains($needle, 'FIX')) {
            $this->whereNormalizedPlats($query, $used, true);
            return;
        }

        if (str_contains($needle, 'TERSEDIA')) {
            $this->whereNormalizedPlats(
                $query,
                array_values(array_unique(array_merge($booked, $used, $rusak, $maintenance))),
                false
            );
            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function whereNormalizedPlats($query, array $keys, bool $in): void
    {
        $keys = array_values(array_filter($keys));
        if ($keys === []) {
            if ($in) {
                $query->whereRaw('1 = 0');
            }
            return;
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $operator = $in ? 'IN' : 'NOT IN';
        $query->whereRaw($this->platNormalizeSql('plat_mobil') . " {$operator} ({$placeholders})", $keys);
    }

    private function excludeNormalizedPlats($query, array $keys, string $column): void
    {
        $keys = array_values(array_filter($keys));
        if ($keys === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $query->whereRaw($this->platNormalizeSql($column) . " NOT IN ({$placeholders})", $keys);
    }

    private function occupyingFixKeys(array $jadwalOverlay): array
    {
        $keys = [];
        foreach ($jadwalOverlay['occupying'] ?? [] as $plat => $value) {
            if ((int) $value === 1) {
                $keys[] = $plat;
            }
        }

        return array_values(array_unique($keys));
    }

    private function platLookupKeys(?string $value): array
    {
        $raw = strtoupper(trim((string) $value));
        $keys = [];
        $normalized = $this->normalizePlatKey($raw);
        if ($normalized !== '') {
            $keys[] = $normalized;
        }

        if (preg_match_all('/[A-Z]{1,2}\s*\d{1,4}\s*[A-Z]{1,3}/', $raw, $matches)) {
            foreach ($matches[0] as $plat) {
                $key = $this->normalizePlatKey($plat);
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    private function lookupMappedValue(?string $plat, array $map)
    {
        foreach ($this->platLookupKeys($plat) as $key) {
            if (array_key_exists($key, $map)) {
                return $map[$key];
            }
        }

        $platKey = $this->normalizePlatKey($plat);
        if (strlen($platKey) < 6) {
            return null;
        }

        foreach ($map as $key => $value) {
            if (strlen((string) $key) < 6) {
                continue;
            }
            if (str_contains((string) $key, $platKey) || str_contains($platKey, (string) $key)) {
                return $value;
            }
        }

        return null;
    }

    private function platNormalizeSql(string $column): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL({$column}, ''), ' ', ''), '-', ''), '.', ''), '/', ''))";
    }

    private function occupancyExtraDays($durasi): int
    {
        $value = (int) $durasi;

        return $value > 1 ? $value - 1 : 0;
    }

    private function occupancyEndDate($tanggal, $durasi): string
    {
        return Carbon::parse($tanggal)
            ->startOfDay()
            ->addDays($this->occupancyExtraDays($durasi))
            ->toDateString();
    }

    private function occupancyEndSql(string $tanggalColumn, string $durasiColumn): string
    {
        return "DATE_ADD({$tanggalColumn}, INTERVAL CASE WHEN IFNULL({$durasiColumn}, 0) <= 1 THEN 0 ELSE {$durasiColumn} - 1 END DAY)";
    }

    private function applyOccupancyWindow($query, string $tanggal, string $tanggalColumn, string $durasiColumn): void
    {
        $lookback = Carbon::parse($tanggal)->subDays(31)->toDateString();

        $query->whereDate($tanggalColumn, '<=', $tanggal)
            ->whereDate($tanggalColumn, '>=', $lookback)
            ->whereRaw($this->occupancyEndSql($tanggalColumn, $durasiColumn) . ' >= ?', [$tanggal]);
    }

    private function applyBookedOverlayWindow($query, string $tanggal, string $tanggalColumn, string $durasiColumn): void
    {
        $lookback = Carbon::parse($tanggal)->subDays(31)->toDateString();

        $query->where(function ($group) use ($tanggal, $lookback, $tanggalColumn, $durasiColumn) {
            $group->where(function ($occupying) use ($tanggal, $lookback, $tanggalColumn, $durasiColumn) {
                $occupying->whereDate($tanggalColumn, '<=', $tanggal)
                    ->whereDate($tanggalColumn, '>=', $lookback)
                    ->whereRaw($this->occupancyEndSql($tanggalColumn, $durasiColumn) . ' >= ?', [$tanggal]);
            })->orWhereDate($tanggalColumn, '>=', $tanggal);
        });
    }

    private function occupiesDate($tanggalJadwal, $durasi, string $selected): bool
    {
        $start = $this->toDateString($tanggalJadwal);
        if ($start === '') {
            return false;
        }

        $end = $this->occupancyEndDate($start, $durasi);

        return $start <= $selected && $end >= $selected;
    }

    private function toDateString($value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable $e) {
            return preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) ? substr($raw, 0, 10) : '';
        }
    }

    private function filterKendaraanByMobilField($query, string $field, string $keyword): void
    {
        $plats = DaftarMobil::where('is_active', true)
            ->where($field, 'like', "%{$keyword}%")
            ->pluck('plat_mobil')
            ->map(fn ($plat) => $this->normalizePlatKey($plat))
            ->filter()
            ->values()
            ->all();

        if ($plats === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $placeholders = implode(',', array_fill(0, count($plats), '?'));
        $query->whereRaw($this->platNormalizeSql('kendaraan') . " IN ({$placeholders})", $plats);
    }

    private function indexSedangDigunakan(Request $request)
    {
        $tanggal = $this->resolveTanggal($request);
        $mobilMap = $this->daftarMobilByPlat();
        $data = $this->jadwalSedangDigunakanQuery($tanggal)
            ->orderBy('kendaraan')
            ->orderBy('tanggal')
            ->orderBy('jam_mulai');

        return Datatables::of($data)
            ->addColumn('merk_mobil', function ($row) use ($mobilMap) {
                return optional($this->mobilFromKendaraan($mobilMap, $row->kendaraan))->merk_mobil;
            })
            ->addColumn('tipe_mobil', function ($row) use ($mobilMap) {
                return optional($this->mobilFromKendaraan($mobilMap, $row->kendaraan))->tipe_mobil;
            })
            ->addColumn('warna_mobil', function ($row) use ($mobilMap) {
                return optional($this->mobilFromKendaraan($mobilMap, $row->kendaraan))->warna_mobil;
            })
            ->addColumn('dipakai_sampai', function ($row) {
                return $this->occupancyEndDate($row->tanggal, $row->durasi);
            })
            ->filterColumn('kendaraan', function ($query, $keyword) {
                $query->where('kendaraan', 'like', "%{$keyword}%");
            })
            ->filterColumn('merk_mobil', function ($query, $keyword) {
                $this->filterKendaraanByMobilField($query, 'merk_mobil', $keyword);
            })
            ->filterColumn('tipe_mobil', function ($query, $keyword) {
                $this->filterKendaraanByMobilField($query, 'tipe_mobil', $keyword);
            })
            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->where('nama_perusahaan', 'like', "%{$keyword}%");
            })
            ->filterColumn('no_quotation', function ($query, $keyword) {
                $query->where('no_quotation', 'like', "%{$keyword}%");
            })
            ->filterColumn('driver', function ($query, $keyword) {
                $query->where('driver', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal', function ($query, $keyword) {
                $value = trim((string) $keyword);
                if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $query->whereDate('tanggal', $value);
                    return;
                }
                $query->where('tanggal', 'like', "%{$value}%");
            })
            ->filterColumn('jam_mulai', function ($query, $keyword) {
                $query->where('jam_mulai', 'like', "%{$keyword}%");
            })
            ->filterColumn('jam_selesai', function ($query, $keyword) {
                $query->where('jam_selesai', 'like', "%{$keyword}%");
            })
            ->filterColumn('wilayah', function ($query, $keyword) {
                $query->where('wilayah', 'like', "%{$keyword}%");
            })
            ->filterColumn('durasi', function ($query, $keyword) {
                $normalized = strtolower(trim((string) $keyword));
                if ($normalized === '') {
                    return;
                }
                if (preg_match('/^\d+$/', $normalized)) {
                    $query->where('durasi', (int) $normalized);
                    return;
                }
                if (str_contains($normalized, 'sesaat')) {
                    $query->where('durasi', 0);
                    return;
                }
                if (str_contains($normalized, '8 jam') || str_contains($normalized, '8jam')) {
                    $query->where('durasi', 1);
                    return;
                }
                if (preg_match('/(\d+)\s*x?\s*24/', $normalized, $matches)) {
                    $query->where('durasi', ((int) $matches[1]) + 1);
                    return;
                }
                $query->where('durasi', 'like', "%{$keyword}%");
            })
            ->filterColumn('status', function ($query, $keyword) {
                $normalized = strtolower(trim((string) $keyword));
                if ($normalized === '') {
                    return;
                }
                if (preg_match('/^[01]$/', $normalized)) {
                    $query->where('status', (int) $normalized);
                    return;
                }
                if (str_contains($normalized, 'book')) {
                    $query->where('status', 0);
                    return;
                }
                if (str_contains($normalized, 'fix')) {
                    $query->where('status', 1);
                    return;
                }
                $query->where('status', 'like', "%{$keyword}%");
            })
            ->filterColumn('sampler', function ($query, $keyword) {
                $table = $query->getModel()->getTable();
                $query->whereExists(function ($subquery) use ($keyword, $table) {
                    $subquery->select(DB::raw(1))
                        ->from("{$table} as sub")
                        ->whereRaw("(sub.id_sampling <=> {$table}.id_sampling)")
                        ->whereRaw("(sub.parsial <=> {$table}.parsial)")
                        ->whereRaw("(sub.no_quotation <=> {$table}.no_quotation)")
                        ->whereRaw("(sub.nama_perusahaan <=> {$table}.nama_perusahaan)")
                        ->whereRaw("(sub.tanggal <=> {$table}.tanggal)")
                        ->whereRaw("(sub.jam_mulai <=> {$table}.jam_mulai)")
                        ->whereRaw("(sub.jam_selesai <=> {$table}.jam_selesai)")
                        ->whereRaw("(sub.kendaraan <=> {$table}.kendaraan)")
                        ->whereRaw("(sub.id_cabang <=> {$table}.id_cabang)")
                        ->whereRaw("(sub.is_active <=> {$table}.is_active)")
                        ->where('sub.sampler', 'like', "%{$keyword}%");
                });
            })
            ->make(true);
    }

    private function jadwalSedangDigunakanQuery(string $tanggal)
    {
        $data = Jadwal::query()
            ->select(
                'id_sampling',
                'parsial',
                'no_quotation',
                'nama_perusahaan',
                'tanggal',
                'periode',
                'jam_mulai',
                'jam_selesai',
                'kategori',
                'durasi',
                'status',
                'driver',
                'id_cabang',
                'wilayah',
                'kendaraan',
                DB::raw('MAX(id) as id'),
                DB::raw('GROUP_CONCAT(DISTINCT sampler) as sampler')
            )
            ->where('is_active', true)
            ->whereNotNull('no_quotation')
            ->whereNotNull('kendaraan')
            ->where('kendaraan', '!=', '');
        $this->applyOccupancyWindow($data, $tanggal, 'tanggal', 'durasi');
        $data->groupBy(
                'id_sampling',
                'parsial',
                'no_quotation',
                'nama_perusahaan',
                'tanggal',
                'periode',
                'jam_mulai',
                'jam_selesai',
                'kategori',
                'durasi',
                'status',
                'driver',
                'id_cabang',
                'wilayah',
                'kendaraan'
            );

        $this->applyCabangScope($data);

        return $data;
    }

    private function countJadwalSedangDigunakan(string $tanggal): int
    {
        return (int) DB::query()
            ->fromSub($this->jadwalSedangDigunakanQuery($tanggal)->getQuery(), 'mko_used')
            ->distinct()
            ->count('kendaraan');
    }

    private function applyCabangScope($query): void
    {
        $privileges = array_map('strval', (array) $this->privilageCabang);
        $isPusat = in_array('1', $privileges, true);

        if ($isPusat) {
            return;
        }

        if ($privileges === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('id_cabang', $privileges);
    }

    private function resolveTanggal(Request $request): string
    {
        $tanggal = trim((string) $request->input('tanggal', ''));
        if ($tanggal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return $tanggal;
        }

        return Carbon::now('Asia/Jakarta')->toDateString();
    }

    private function daftarMobilByPlat(): array
    {
        $map = [];
        DaftarMobil::where('is_active', true)
            ->get(['plat_mobil', 'merk_mobil', 'tipe_mobil', 'warna_mobil'])
            ->each(function ($item) use (&$map) {
                $key = $this->normalizePlatKey($item->plat_mobil);
                if ($key !== '') {
                    $map[$key] = $item;
                }
            });

        return $map;
    }

    private function mobilFromKendaraan(array $map, ?string $kendaraan): ?DaftarMobil
    {
        $key = $this->normalizePlatKey($kendaraan);

        return $key !== '' ? ($map[$key] ?? null) : null;
    }

    private function normalizePlatKey(?string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $value));
    }

    public function getOptions(Request $request)
    {
        $mobil = DaftarMobil::where('is_active', true)
            ->orderBy('plat_mobil')
            ->get(['id', 'plat_mobil', 'merk_mobil', 'tipe_mobil', 'warna_mobil'])
            ->map(function ($item) {
                $nama = trim(implode(' ', array_filter([
                    $item->merk_mobil,
                    $item->warna_mobil,
                ])));

                return [
                    'id' => $item->id,
                    'plat_mobil' => $item->plat_mobil,
                    'nama_mobil' => $nama !== '' ? strtoupper($nama) : ($item->merk_mobil ?: '-'),
                    'text' => trim($item->plat_mobil . ' — ' . ($nama ?: $item->merk_mobil ?: '-')),
                ];
            });

        return response()->json([
            'message' => 'Options loaded successfully',
            'data' => [
                'mobil' => $mobil,
                'jenis_dimensi' => ['MVP', 'VAN', 'BOX', 'MOTOR', 'SEDAN'],
                'tipe_transmisi' => ['Manual', 'Otomatis'],
                'kepemilikan' => ['Internal', 'Eksternal'],
                'status_kendaraan' => [
                    [
                        'value' => MonitoringKendaraanOperasional::STATUS_TERSEDIA,
                        'label' => 'TERSEDIA',
                        'description' => 'Alat dalam kondisi layak pakai dan dapat digunakan untuk kegiatan sampling.',
                    ],
                    [
                        'value' => MonitoringKendaraanOperasional::STATUS_RUSAK,
                        'label' => 'RUSAK',
                        'description' => 'Alat mengalami kerusakan sehingga tidak dapat digunakan sebelum dilakukan penanganan.',
                    ],
                    [
                        'value' => MonitoringKendaraanOperasional::STATUS_SEDANG_DI_MAINTENANCE,
                        'label' => 'SEDANG DI MAINTENANCE',
                        'description' => 'Alat sedang menjalani proses pemeliharaan atau perbaikan.',
                    ],
                    [
                        'value' => MonitoringKendaraanOperasional::STATUS_SEDANG_DIBOOKING,
                        'label' => 'SEDANG DIBOOKING',
                        'description' => 'Kendaraan sudah terjadwalkan dengan penjelasan pada keterangan.',
                    ],
                    [
                        'value' => MonitoringKendaraanOperasional::STATUS_SEDANG_DI_GUNAKAN,
                        'label' => 'SEDANG DI GUNAKAN',
                        'description' => 'Alat masih digunakan dalam kegiatan sampling.',
                    ],
                ],
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'daftar_mobil_id' => ['required', 'integer'],
            'jenis_dimensi_kendaraan' => ['required', 'in:MVP,VAN,BOX,MOTOR,SEDAN'],
            'tipe_transmisi' => ['required', 'in:Manual,Otomatis'],
            'kepemilikan' => ['required', 'in:Internal,Eksternal'],
            'durasi_ketersediaan' => ['nullable', 'integer', 'min:1'],
            'status_kendaraan' => ['required', 'in:' . implode(',', [
                MonitoringKendaraanOperasional::STATUS_TERSEDIA,
                MonitoringKendaraanOperasional::STATUS_RUSAK,
                MonitoringKendaraanOperasional::STATUS_SEDANG_DI_MAINTENANCE,
                MonitoringKendaraanOperasional::STATUS_SEDANG_DIBOOKING,
                MonitoringKendaraanOperasional::STATUS_SEDANG_DI_GUNAKAN,
            ])],
            'keterangan' => ['nullable', 'string'],
            'code_aset' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if ($request->kepemilikan === 'Eksternal' && !$request->filled('durasi_ketersediaan')) {
            return response()->json([
                'message' => 'Durasi ketersediaan kendaraan wajib diisi untuk kepemilikan Eksternal.',
            ], 422);
        }

        $mobil = DaftarMobil::where('id', $request->daftar_mobil_id)
            ->where('is_active', true)
            ->first();

        if (!$mobil) {
            return response()->json([
                'message' => 'Data mobil tidak ditemukan.',
            ], 404);
        }

        $namaMobil = trim(implode(' ', array_filter([$mobil->merk_mobil, $mobil->warna_mobil])));
        if ($namaMobil === '') {
            $namaMobil = $mobil->merk_mobil;
        }
        $namaMobil = strtoupper((string) $namaMobil);

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $keterangan = trim((string) $request->input('keterangan', ''));
        if ($keterangan !== '') {
            $keterangan .= "\n\n[Dicatat: {$now} — {$this->karyawan}]";
        }

        MonitoringKendaraanOperasional::create([
            'daftar_mobil_id' => $mobil->id,
            'nama_mobil' => $namaMobil,
            'plat_mobil' => $mobil->plat_mobil,
            'code_aset' => $request->input('code_aset'),
            'jenis_dimensi_kendaraan' => $request->jenis_dimensi_kendaraan,
            'tipe_transmisi' => $request->tipe_transmisi,
            'kepemilikan' => $request->kepemilikan,
            'durasi_ketersediaan' => $request->kepemilikan === 'Eksternal'
                ? (int) $request->durasi_ketersediaan
                : null,
            'status_kendaraan' => $request->status_kendaraan,
            'keterangan' => $keterangan !== '' ? $keterangan : null,
            'created_by' => $this->karyawan,
            'created_at' => $now,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Data monitoring kendaraan berhasil ditambahkan.',
        ], 200);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $row = MonitoringKendaraanOperasional::where('id', $request->id)
            ->where('is_active', true)
            ->first();

        if (!$row) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $row->is_active = false;
        $row->updated_by = $this->karyawan;
        $row->updated_at = $now;
        $row->save();

        return response()->json([
            'message' => 'Data monitoring kendaraan berhasil dihapus.',
        ], 200);
    }
}
