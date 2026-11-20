<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DaftarMobil;
use App\Models\MasterDriver;
use App\Models\MasterKaryawan;
use App\Models\PenggunaanMobilDetail;
use App\Models\PenggunaanMobilDriver;
use App\Models\PenggunaanMobilHeader;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class PenggunaanMobilController extends Controller
{
  public function index(Request $request)
  {
    $tab = $this->normalizeTab($request->input('mode') ?? $request->input('tab'));
    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $data = $this->buildMobilPeriodIndexQuery($tab, $today);

    $driverDisplayCache = [];

    return Datatables::of($data)
      ->addColumn('mobil_display', function ($row) {
        return e(trim($row->plat_mobil . ' - ' . $row->merk_mobil . ' ' . $row->tipe_mobil));
      })
      ->addColumn('driver_display', function ($row) use (&$driverDisplayCache) {
        return $this->driverDisplayForHeaderId((int) $row->id, $driverDisplayCache);
      })
      ->addColumn('periode_display', function ($row) {
        return e(($row->tanggal_mulai ?? '-') . ' s/d ' . ($row->tanggal_selesai ?? '-'));
      })
      ->addColumn('requester', function ($row) {
        return ['nama_lengkap' => $row->requester_nama ?? '-'];
      })
      ->addColumn('keterangan', function ($row) {
        return $row->keterangan;
      })
      ->filterColumn('mobil_display', function ($query, $keyword) {
        $query->where(function ($q) use ($keyword) {
          $q->where('m.plat_mobil', 'like', "%{$keyword}%")
            ->orWhere('m.merk_mobil', 'like', "%{$keyword}%")
            ->orWhere('m.tipe_mobil', 'like', "%{$keyword}%");
        });
      })
      ->filterColumn('driver_display', function ($query, $keyword) {
        $query->whereExists(function ($sub) use ($keyword) {
          $sub->select(DB::raw(1))
            ->from('penggunaan_mobil_driver as pmd')
            ->join('master_driver as md', 'md.user_id', '=', 'pmd.driver_id')
            ->whereColumn('pmd.header_id', 'h.id')
            ->where('pmd.is_active', true)
            ->where('md.nama_driver', 'like', "%{$keyword}%");
        });
      })
      ->filterColumn('requester.nama_lengkap', function ($query, $keyword) {
        $query->where('r.nama_lengkap', 'like', "%{$keyword}%");
      })
      ->make(true);
  }

  public function show(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'id' => ['required', 'integer'],
    ]);

    if ($validator->fails()) {
      return response()->json([
        'message' => $validator->errors()->first(),
        'errors' => $validator->errors(),
      ], 422);
    }

    $header = PenggunaanMobilHeader::with([
      'requester',
      'drivers' => function ($query) {
        $query->where('is_active', true);
      },
      'drivers.driver',
      'details' => function ($query) {
        $query->where('is_active', true)->orderBy('tanggal_penggunaan');
      },
      'details.mobil',
    ])->where('is_active', true)->find($request->id);

    if (!$header) {
      return response()->json(['message' => 'Data penggunaan mobil tidak ditemukan.'], 404);
    }

    $periodRows = $this->mobilPeriodeFromRow($header);

    return response()->json([
      'message' => 'Data penggunaan mobil berhasil dimuat.',
      'data' => [
        'id' => $header->id,
        'requester_id' => $header->requester_id,
        'keterangan' => $header->keterangan,
        'created_at' => $header->created_at,
        'created_by' => $header->created_by,
        'requester' => $header->requester,
        'mobil_ids' => $this->mobilIdsFromRow($header),
        'driver_ids' => $this->driverIdsFromRow($header),
        'mobil_periode' => $periodRows,
        'date_mode' => $this->resolveDateModeFromRow($header),
        'tanggal_mulai' => optional($header->details->first())->tanggal_penggunaan,
        'tanggal_selesai' => optional($header->details->last())->tanggal_penggunaan,
      ],
    ], 200);
  }

  public function tabCounts(Request $request)
  {
    try {
      $today = Carbon::now('Asia/Jakarta')->toDateString();
      $tabs = ['sedang_digunakan', 'dijadwalkan', 'selesai_digunakan'];
      $counts = [];

      foreach ($tabs as $tab) {
        $counts[$tab] = DB::query()
          ->fromSub($this->buildMobilPeriodIndexQuery($tab, $today), 'mobil_periode')
          ->count();
      }

      return response()->json([
        'success' => true,
        'data' => $counts,
      ], 200);
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => [],
      ], 500);
    }
  }

  public function getOptions(Request $request)
  {
    $mobilQuery = DaftarMobil::where('is_active', true);
    if (Schema::hasColumn('daftar_mobil', 'kepemilikan')) {
      $mobilQuery->where('kepemilikan', 'internal');
    }
    $mobil = $mobilQuery->orderBy('plat_mobil')
      ->get()
      ->map(function ($item) {
        return [
          'id' => $item->id,
          'text' => trim($item->plat_mobil . ' - ' . $item->merk_mobil . ' ' . $item->tipe_mobil),
        ];
      });

    $driver = MasterDriver::where('is_active', true)
      ->whereNotNull('user_id')
      ->orderBy('nama_driver')
      ->get()
      ->unique('user_id')
      ->values()
      ->map(function ($item) {
        return [
          'id' => (int) $item->user_id,
          'text' => $item->nama_driver,
        ];
      });

    $karyawan = MasterKaryawan::where('is_active', true)
      ->orderBy('nama_lengkap')
      ->get(['id', 'nik_karyawan', 'nama_lengkap'])
      ->map(function ($item) {
        return [
          'id' => $item->id,
          'text' => trim($item->nik_karyawan . ' - ' . $item->nama_lengkap),
        ];
      });

    return response()->json([
      'message' => 'Options loaded successfully',
      'data' => [
        'mobil' => $mobil,
        'driver' => $driver,
        'karyawan' => $karyawan,
      ],
    ], 200);
  }

  public function checkAvailability(Request $request)
  {
    $schedule = $this->resolveMobilSchedules($request);

    if (isset($schedule['error'])) {
      return response()->json([
        'message' => $schedule['error'],
        'available' => false,
      ], 422);
    }

    $allConflicts = [];
    foreach ($schedule['items'] as $item) {
      $dates = $this->buildDateRange($item['tanggal_mulai'], $item['tanggal_selesai']);
      $conflicts = $this->getConflicts($item['mobil_id'], $dates, $request->id);
      if ($conflicts->isNotEmpty()) {
        $mobil = DaftarMobil::find($item['mobil_id']);
        $mobilLabel = $mobil
          ? trim($mobil->plat_mobil . ' - ' . $mobil->merk_mobil . ' ' . $mobil->tipe_mobil)
          : (string) $item['mobil_id'];

        $allConflicts[] = [
          'mobil_id' => $item['mobil_id'],
          'mobil_label' => $mobilLabel,
          'tanggal_mulai' => $item['tanggal_mulai'],
          'tanggal_selesai' => $item['tanggal_selesai'],
          'periode_label' => $this->formatIndoDateRange($item['tanggal_mulai'], $item['tanggal_selesai']),
          'message' => $this->buildConflictMessage($mobilLabel, $conflicts),
          'conflicts' => $conflicts,
        ];
      }
    }

    $available = empty($allConflicts);

    return response()->json([
      'message' => $available
        ? 'Semua mobil yang dipilih masih tersedia pada tanggal tersebut.'
        : 'Ada mobil yang sudah dipakai pada tanggal yang sama. Silakan pilih tanggal atau mobil lain.',
      'available' => $available,
      'data' => $allConflicts,
    ], 200);
  }

  public function store(Request $request)
  {
    $schedule = $this->resolveMobilSchedules($request);

    if (isset($schedule['error'])) {
      return response()->json([
        'message' => $schedule['error'],
        'errors' => ['schedule' => $schedule['error']],
      ], 422);
    }

    $validator = Validator::make($request->all(), [
      'id' => ['nullable', 'integer'],
      'requester_id' => ['required', 'integer'],
      'keterangan' => ['required', 'string'],
    ], [
      'requester_id.required' => 'Pilih requester terlebih dahulu.',
      'keterangan.required' => 'Isi keterangan penggunaan mobil.',
    ]);

    if ($validator->fails()) {
      return response()->json([
        'message' => $validator->errors()->first(),
        'errors' => $validator->errors(),
      ], 422);
    }

    if (empty($schedule['items'])) {
      return response()->json(['message' => 'Pilih minimal satu mobil terlebih dahulu.'], 422);
    }

    $driverIds = $this->normalizeIdList($request->driver_ids, $request->driver_id);

    foreach ($schedule['items'] as $item) {
      if (!$this->isInternalActiveMobil((int) $item['mobil_id'])) {
        return response()->json(['message' => 'Hanya mobil internal yang aktif yang bisa dipilih.'], 422);
      }

      $dates = $this->buildDateRange($item['tanggal_mulai'], $item['tanggal_selesai']);
      $conflicts = $this->getConflicts($item['mobil_id'], $dates, $request->id);

      if ($conflicts->isNotEmpty()) {
        $mobil = DaftarMobil::find($item['mobil_id']);
        $label = $mobil
          ? trim($mobil->plat_mobil . ' - ' . $mobil->merk_mobil . ' ' . $mobil->tipe_mobil)
          : (string) $item['mobil_id'];

        return response()->json([
          'message' => $this->buildConflictMessage($label, $conflicts),
          'data' => $conflicts,
        ], 422);
      }
    }

    try {
      DB::beginTransaction();

      if ($request->id) {
        $header = PenggunaanMobilHeader::where('id', $request->id)->where('is_active', true)->first();

        if (!$header) {
          DB::rollBack();
          return response()->json(['message' => 'Data penggunaan mobil tidak ditemukan.'], 404);
        }

        $header->requester_id = $request->requester_id;
        $header->keterangan = trim($request->keterangan);
        $header->updated_at = date('Y-m-d H:i:s');
        $header->updated_by = $this->karyawan;
        $header->save();

        PenggunaanMobilDetail::where('header_id', $header->id)->update([
          'is_active' => false,
          'deleted_at' => date('Y-m-d H:i:s'),
          'deleted_by' => $this->karyawan,
        ]);

        PenggunaanMobilDriver::where('header_id', $header->id)->update([
          'is_active' => false,
          'deleted_at' => date('Y-m-d H:i:s'),
          'deleted_by' => $this->karyawan,
        ]);
      } else {
        $header = PenggunaanMobilHeader::create([
          'requester_id' => $request->requester_id,
          'keterangan' => trim($request->keterangan),
          'created_at' => date('Y-m-d H:i:s'),
          'created_by' => $this->karyawan,
          'is_active' => true,
        ]);
      }

      foreach ($schedule['items'] as $item) {
        $dates = $this->buildDateRange($item['tanggal_mulai'], $item['tanggal_selesai']);
        foreach ($dates as $date) {
          PenggunaanMobilDetail::create([
            'header_id' => $header->id,
            'mobil_id' => $item['mobil_id'],
            'tanggal_penggunaan' => $date,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $this->karyawan,
            'is_active' => true,
          ]);
        }
      }

      foreach ($driverIds as $driverId) {
        PenggunaanMobilDriver::create([
          'header_id' => $header->id,
          'driver_id' => (int) $driverId,
          'created_at' => date('Y-m-d H:i:s'),
          'created_by' => $this->karyawan,
          'is_active' => true,
        ]);
      }

      DB::commit();

      return response()->json([
        'message' => $request->id
          ? 'Jadwal penggunaan mobil berhasil diperbarui.'
          : 'Jadwal penggunaan mobil berhasil disimpan.',
      ], $request->id ? 200 : 201);
    } catch (\Exception $th) {
      DB::rollBack();
      return response()->json(['message' => $th->getMessage()], 500);
    }
  }

  public function delete(Request $request)
  {
    if (!$request->id) {
      return response()->json(['message' => 'Data penggunaan mobil tidak ditemukan.'], 401);
    }

    try {
      DB::beginTransaction();

      $header = PenggunaanMobilHeader::where('id', $request->id)->where('is_active', true)->first();

      if (!$header) {
        DB::rollBack();
        return response()->json(['message' => 'Data penggunaan mobil tidak ditemukan.'], 404);
      }

      $header->deleted_at = date('Y-m-d H:i:s');
      $header->deleted_by = $this->karyawan;
      $header->is_active = false;
      $header->save();

      PenggunaanMobilDetail::where('header_id', $header->id)->update([
        'deleted_at' => date('Y-m-d H:i:s'),
        'deleted_by' => $this->karyawan,
        'is_active' => false,
      ]);

      PenggunaanMobilDriver::where('header_id', $header->id)->update([
        'deleted_at' => date('Y-m-d H:i:s'),
        'deleted_by' => $this->karyawan,
        'is_active' => false,
      ]);

      DB::commit();

      return response()->json(['message' => 'Jadwal penggunaan mobil berhasil dihapus.'], 200);
    } catch (\Exception $th) {
      DB::rollBack();
      return response()->json(['message' => $th->getMessage()], 500);
    }
  }

  protected function normalizeTab($mode): string
  {
    $mode = is_string($mode) ? trim($mode) : '';

    $legacyMap = [
      'active' => 'sedang_digunakan',
      'history' => 'selesai_digunakan',
    ];

    $mode = $legacyMap[$mode] ?? $mode;
    $allowed = ['dijadwalkan', 'sedang_digunakan', 'selesai_digunakan'];

    return in_array($mode, $allowed, true) ? $mode : 'sedang_digunakan';
  }

  /**
   * Satu baris = satu mobil dalam satu pengajuan (agregat detail aktif per mobil).
   * Tab per periode mobil (WIB), bukan per header.
   */
  protected function buildMobilPeriodIndexQuery(string $tab, string $today)
  {
    $query = DB::table('penggunaan_mobil_detail as d')
      ->join('penggunaan_mobil_header as h', function ($join) {
        $join->on('h.id', '=', 'd.header_id')->where('h.is_active', true);
      })
      ->join('daftar_mobil as m', 'm.id', '=', 'd.mobil_id')
      ->leftJoin('master_karyawan as r', 'r.id', '=', 'h.requester_id')
      ->where('d.is_active', true)
      ->groupBy(
        'h.id',
        'h.requester_id',
        'h.keterangan',
        'h.created_at',
        'h.created_by',
        'd.mobil_id',
        'm.plat_mobil',
        'm.merk_mobil',
        'm.tipe_mobil',
        'r.nama_lengkap'
      )
      ->select([
        'h.id',
        'h.requester_id',
        'h.keterangan',
        'h.created_at',
        'h.created_by',
        'd.mobil_id',
        'm.plat_mobil',
        'm.merk_mobil',
        'm.tipe_mobil',
        'r.nama_lengkap as requester_nama',
        DB::raw('MIN(d.tanggal_penggunaan) as tanggal_mulai'),
        DB::raw('MAX(d.tanggal_penggunaan) as tanggal_selesai'),
        DB::raw('COUNT(*) as total_hari'),
      ]);

    if ($tab === 'sedang_digunakan') {
      $query->havingRaw('MIN(d.tanggal_penggunaan) <= ? AND MAX(d.tanggal_penggunaan) >= ?', [$today, $today]);
    } elseif ($tab === 'dijadwalkan') {
      $query->havingRaw('MIN(d.tanggal_penggunaan) > ?', [$today]);
    } elseif ($tab === 'selesai_digunakan') {
      $query->havingRaw('MAX(d.tanggal_penggunaan) < ?', [$today]);
    }

    return $query->orderByDesc('h.id')->orderBy('d.mobil_id');
  }

  protected function driverDisplayForHeaderId(int $headerId, array &$cache): string
  {
    if (isset($cache[$headerId])) {
      return $cache[$headerId];
    }

    $names = PenggunaanMobilDriver::with('driver')
      ->where('header_id', $headerId)
      ->where('is_active', true)
      ->get()
      ->map(fn ($pivot) => optional($pivot->driver)->nama_driver)
      ->filter()
      ->values();

    $cache[$headerId] = $names->isEmpty() ? '-' : $names->implode(', ');

    return $cache[$headerId];
  }

  protected function normalizeIdList($ids, $single = null): array
  {
    if (is_array($ids)) {
      return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    if ($single !== null && $single !== '') {
      return [(int) $single];
    }

    return [];
  }

  protected function isInternalActiveMobil(int $mobilId): bool
  {
    $query = DaftarMobil::where('id', $mobilId)->where('is_active', true);

    if (Schema::hasColumn('daftar_mobil', 'kepemilikan')) {
      $query->where('kepemilikan', 'internal');
    }

    return $query->exists();
  }

  protected function resolveMobilSchedules(Request $request): array
  {
    $mobilIds = $this->normalizeIdList($request->mobil_ids, $request->mobil_id);

    if (empty($mobilIds)) {
      return ['error' => 'Pilih minimal satu mobil terlebih dahulu.'];
    }

    $dateMode = $request->date_mode ?: (count($mobilIds) > 1 ? 'sama' : 'sama');

    if (!in_array($dateMode, ['sama', 'berbeda'], true)) {
      return ['error' => 'Pilihan tanggal tidak valid. Pilih tanggal sama atau berbeda per mobil.'];
    }

    $items = [];

    if (count($mobilIds) === 1 || $dateMode === 'sama') {
      $validator = Validator::make($request->all(), [
        'tanggal_mulai' => ['required', 'date'],
        'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
      ], [
        'tanggal_mulai.required' => 'Isi tanggal mulai pemakaian.',
        'tanggal_selesai.required' => 'Isi tanggal selesai pemakaian.',
        'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
      ]);

      if ($validator->fails()) {
        return ['error' => $validator->errors()->first()];
      }

      foreach ($mobilIds as $mobilId) {
        $items[] = [
          'mobil_id' => (int) $mobilId,
          'tanggal_mulai' => $request->tanggal_mulai,
          'tanggal_selesai' => $request->tanggal_selesai,
        ];
      }
    } else {
      $periodeInput = $request->mobil_periode;
      if (!is_array($periodeInput) || empty($periodeInput)) {
        return ['error' => 'Isi tanggal mulai dan selesai untuk setiap mobil.'];
      }

      $periodeMap = [];
      foreach ($periodeInput as $row) {
        if (!is_array($row) || empty($row['mobil_id'])) {
          continue;
        }

        $mid = (int) $row['mobil_id'];
        $mulai = $row['tanggal_mulai'] ?? null;
        $selesai = $row['tanggal_selesai'] ?? null;

        if (!$mulai || !$selesai) {
          return ['error' => 'Isi tanggal mulai dan selesai untuk setiap mobil.'];
        }

        if ($selesai < $mulai) {
          return ['error' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.'];
        }

        $periodeMap[$mid] = [
          'mobil_id' => $mid,
          'tanggal_mulai' => $mulai,
          'tanggal_selesai' => $selesai,
        ];
      }

      foreach ($mobilIds as $mobilId) {
        if (!isset($periodeMap[$mobilId])) {
          return ['error' => 'Isi tanggal pemakaian untuk semua mobil yang dipilih.'];
        }
        $items[] = $periodeMap[$mobilId];
      }
    }

    return [
      'items' => $items,
    ];
  }

  protected function buildDateRange($startDate, $endDate): array
  {
    $period = CarbonPeriod::create(Carbon::parse($startDate), Carbon::parse($endDate));
    $dates = [];

    foreach ($period as $date) {
      $dates[] = $date->format('Y-m-d');
    }

    return $dates;
  }

  protected function getConflicts($mobilId, array $dates, $excludeHeaderId = null)
  {
    return PenggunaanMobilDetail::with(['header.requester'])
      ->where('mobil_id', $mobilId)
      ->where('is_active', true)
      ->whereIn('tanggal_penggunaan', $dates)
      ->when($excludeHeaderId, function ($query) use ($excludeHeaderId) {
        $query->where('header_id', '!=', $excludeHeaderId);
      })
      ->whereHas('header', function ($query) {
        $query->where('is_active', true);
      })
      ->orderBy('tanggal_penggunaan')
      ->get()
      ->map(function ($item) {
        return [
          'tanggal_penggunaan' => $item->tanggal_penggunaan,
          'tanggal_label' => $this->formatIndoDate($item->tanggal_penggunaan),
          'requester' => optional(optional($item->header)->requester)->nama_lengkap,
          'keterangan' => optional($item->header)->keterangan,
        ];
      });
  }

  protected function mobilIdsFromRow($row): array
  {
    $fromDetails = $row->details->pluck('mobil_id')->unique()->filter()->values()->all();

    return array_map('intval', $fromDetails);
  }

  protected function driverIdsFromRow($row): array
  {
    $fromPivot = $row->drivers
      ? $row->drivers->where('is_active', true)->pluck('driver_id')->map(fn ($id) => (int) $id)->values()->all()
      : [];

    return $fromPivot;
  }

  protected function mobilPeriodeFromRow($row): array
  {
    $grouped = $row->details->groupBy('mobil_id');
    $result = [];

    foreach ($grouped as $mobilId => $details) {
      $sorted = $details->sortBy('tanggal_penggunaan')->values();
      $result[] = [
        'mobil_id' => (int) $mobilId,
        'tanggal_mulai' => optional($sorted->first())->tanggal_penggunaan,
        'tanggal_selesai' => optional($sorted->last())->tanggal_penggunaan,
        'mobil_label' => $this->mobilLabel(optional($sorted->first())->mobil),
      ];
    }

    return $result;
  }

  protected function resolveDateModeFromRow($row): string
  {
    $periode = $this->mobilPeriodeFromRow($row);
    if (count($periode) <= 1) {
      return 'sama';
    }

    $firstRange = ($periode[0]['tanggal_mulai'] ?? '') . '|' . ($periode[0]['tanggal_selesai'] ?? '');
    foreach ($periode as $item) {
      $range = ($item['tanggal_mulai'] ?? '') . '|' . ($item['tanggal_selesai'] ?? '');
      if ($range !== $firstRange) {
        return 'berbeda';
      }
    }

    return 'sama';
  }

  protected function formatMobilDisplay($row): string
  {
    $labels = [];
    foreach ($this->mobilPeriodeFromRow($row) as $item) {
      $labels[] = $item['mobil_label'] ?? ('Mobil #' . $item['mobil_id']);
    }

    return implode('<br/>', array_map('e', $labels));
  }

  protected function formatDriverDisplay($row): string
  {
    $names = [];

    if ($row->relationLoaded('drivers')) {
      foreach ($row->drivers->where('is_active', true) as $pivot) {
        $name = optional($pivot->driver)->nama_driver;
        if ($name) {
          $names[] = $name;
        }
      }
    }

    return empty($names) ? '-' : implode(', ', $names);
  }

  protected function formatPeriodeDisplay($row): string
  {
    $lines = [];
    foreach ($this->mobilPeriodeFromRow($row) as $item) {
      $label = $item['mobil_label'] ?? ('Mobil #' . $item['mobil_id']);
      $lines[] = $label . ': ' . ($item['tanggal_mulai'] ?? '-') . ' s/d ' . ($item['tanggal_selesai'] ?? '-');
    }

    return implode('<br/>', array_map('e', $lines));
  }

  protected function mobilLabel($mobil): string
  {
    if (!$mobil) {
      return '-';
    }

    return trim($mobil->plat_mobil . ' - ' . $mobil->merk_mobil . ' ' . $mobil->tipe_mobil);
  }

  protected function formatIndoDate($date): string
  {
    if (!$date) {
      return '-';
    }

    $months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $parsed = Carbon::parse($date);

    return $parsed->format('j') . ' ' . $months[(int) $parsed->format('n')] . ' ' . $parsed->format('Y');
  }

  protected function formatIndoDateRange($startDate, $endDate): string
  {
    if (!$startDate && !$endDate) {
      return '-';
    }

    if ($startDate === $endDate || !$endDate) {
      return $this->formatIndoDate($startDate);
    }

    return $this->formatIndoDate($startDate) . ' s/d ' . $this->formatIndoDate($endDate);
  }

  protected function joinIndoList(array $items): string
  {
    $items = array_values(array_filter($items, fn ($item) => $item !== null && $item !== ''));

    if (count($items) === 0) {
      return '';
    }

    if (count($items) === 1) {
      return $items[0];
    }

    if (count($items) === 2) {
      return $items[0] . ' dan ' . $items[1];
    }

    $last = array_pop($items);

    return implode(', ', $items) . ', dan ' . $last;
  }

  protected function buildConflictMessage(string $mobilLabel, $conflicts): string
  {
    $dates = $conflicts->pluck('tanggal_penggunaan')->unique()->values()->all();
    $dateText = $this->joinIndoList(array_map(fn ($date) => $this->formatIndoDate($date), $dates));
    $requesters = $conflicts->pluck('requester')->filter()->unique()->values();
    $who = $requesters->count() === 1 ? ' oleh ' . $requesters->first() : '';

    return "Mobil {$mobilLabel} sudah digunakan{$who} pada {$dateText}. Silakan pilih tanggal atau mobil lain yang tidak tabrakan.";
  }
}