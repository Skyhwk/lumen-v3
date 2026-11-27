<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\BlokLhp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class BlokLhpController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = BlokLhp::query()
                ->where('is_active', true)
                ->select([
                    'id',
                    'cfr',
                    'no_order',
                    'periode',
                    'nama_perusahaan',
                    'keterangan_titik',
                    'alasan',
                    'created_by',
                    'created_at',
                ])
                ->orderByDesc('id');

            return Datatables::of($query)
                ->editColumn('created_at', function ($row) {
                    return $row->created_at
                        ? Carbon::parse($row->created_at)->format('Y-m-d H:i:s')
                        : '-';
                })
                ->make(true);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function searchLhp(Request $request)
    {
        try {
            $term = trim((string) $request->input('term', $request->input('q', '')));
            $limit = 30;

            $blockedCfrs = BlokLhp::query()
                ->where('is_active', true)
                ->pluck('cfr')
                ->all();

            $rows = collect();

            if ($term === '') {
                $recentSub = DB::table('order_detail')
                    ->where('is_active', 1)
                    ->whereNotNull('cfr')
                    ->where('cfr', '!=', '')
                    ->orderByDesc('id')
                    ->limit(500);

                $query = DB::query()
                    ->fromSub($recentSub, 'od')
                    ->selectRaw('cfr, MAX(no_order) as no_order, MAX(periode) as periode, MAX(nama_perusahaan) as nama_perusahaan, GROUP_CONCAT(DISTINCT NULLIF(TRIM(keterangan_1), "") SEPARATOR ", ") as keterangan_titik, MAX(id) as max_id')
                    ->groupBy('cfr')
                    ->orderByDesc('max_id')
                    ->limit($limit);

                if (!empty($blockedCfrs)) {
                    $query->whereNotIn('cfr', $blockedCfrs);
                }

                $rows = $query->get();
            } else {
                // Pass 1: fast indexed prefix search on cfr (covers no_order and full/partial LHP number)
                $prefixQuery = DB::table('order_detail')
                    ->where('is_active', 1)
                    ->where('cfr', 'like', $term . '%')
                    ->selectRaw('cfr, MAX(no_order) as no_order, MAX(periode) as periode, MAX(nama_perusahaan) as nama_perusahaan, GROUP_CONCAT(DISTINCT NULLIF(TRIM(keterangan_1), "") SEPARATOR ", ") as keterangan_titik, MAX(id) as max_id')
                    ->groupBy('cfr')
                    ->orderByDesc('max_id')
                    ->limit($limit);

                if (!empty($blockedCfrs)) {
                    $prefixQuery->whereNotIn('cfr', $blockedCfrs);
                }

                $rows = $prefixQuery->get();

                // Pass 2: broader search on company name, point name, or inner CFR substring when needed
                $remaining = $limit - $rows->count();
                if ($remaining > 0 && mb_strlen($term) >= 3) {
                    $excludeCfrs = array_values(array_unique(array_merge(
                        $blockedCfrs,
                        $rows->pluck('cfr')->all()
                    )));

                    $broadQuery = DB::table('order_detail')
                        ->where('is_active', 1)
                        ->whereNotNull('cfr')
                        ->where('cfr', '!=', '')
                        ->where(function ($q) use ($term) {
                            $like = '%' . $term . '%';
                            $q->where('cfr', 'like', $like)
                                ->orWhere('nama_perusahaan', 'like', $like)
                                ->orWhere('keterangan_1', 'like', $like);
                        })
                        ->selectRaw('cfr, MAX(no_order) as no_order, MAX(periode) as periode, MAX(nama_perusahaan) as nama_perusahaan, GROUP_CONCAT(DISTINCT NULLIF(TRIM(keterangan_1), "") SEPARATOR ", ") as keterangan_titik, MAX(id) as max_id')
                        ->groupBy('cfr')
                        ->orderByDesc('max_id')
                        ->limit($remaining);

                    if (!empty($excludeCfrs)) {
                        $broadQuery->whereNotIn('cfr', $excludeCfrs);
                    }

                    $rows = $rows->concat($broadQuery->get());
                }
            }

            $results = $rows->map(function ($row) {
                $parts = [$row->cfr];
                if (!empty($row->keterangan_titik)) {
                    $parts[] = $row->keterangan_titik;
                }
                $label = implode(' - ', $parts);

                $meta = array_filter([
                    $row->nama_perusahaan ?: null,
                    $row->periode ?: null,
                ]);
                if (!empty($meta)) {
                    $label .= ' | ' . implode(' (', $meta) . (count($meta) > 1 ? ')' : '');
                }

                return [
                    'id' => $row->cfr,
                    'text' => $label,
                    'cfr' => $row->cfr,
                    'no_order' => $row->no_order,
                    'periode' => $row->periode,
                    'nama_perusahaan' => $row->nama_perusahaan,
                    'keterangan_titik' => $row->keterangan_titik,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'results' => $results,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'results' => [],
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $rawCfrs = $request->input('cfrs', []);
        if (is_string($rawCfrs)) {
            $rawCfrs = [$rawCfrs];
        }

        if (!is_array($rawCfrs)) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih minimal satu No. LHP yang akan diblok.',
            ], 422);
        }

        $cfrs = collect($rawCfrs)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($cfrs)) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih minimal satu No. LHP yang akan diblok.',
            ], 422);
        }

        $alasan = trim((string) $request->input('alasan', ''));
        $alasan = $alasan !== '' ? mb_substr($alasan, 0, 1000) : null;

        try {
            $metadataByCfr = DB::table('order_detail')
                ->where('is_active', 1)
                ->whereIn('cfr', $cfrs)
                ->selectRaw('cfr, MAX(no_order) as no_order, MAX(periode) as periode, MAX(nama_perusahaan) as nama_perusahaan, GROUP_CONCAT(DISTINCT NULLIF(TRIM(keterangan_1), "") SEPARATOR ", ") as keterangan_titik')
                ->groupBy('cfr')
                ->get()
                ->keyBy('cfr');

            $existingActive = BlokLhp::query()
                ->where('is_active', true)
                ->whereIn('cfr', $cfrs)
                ->pluck('cfr')
                ->all();

            $existingMap = array_flip($existingActive);
            $now = Carbon::now()->format('Y-m-d H:i:s');
            $added = 0;
            $skipped = 0;

            DB::transaction(function () use ($cfrs, $metadataByCfr, $existingMap, $alasan, $now, &$added, &$skipped) {
                $toInsert = [];

                foreach ($cfrs as $cfr) {
                    if (isset($existingMap[$cfr])) {
                        $skipped++;
                        continue;
                    }

                    $meta = $metadataByCfr->get($cfr);
                    $noOrder = $meta->no_order ?? (strpos($cfr, '/') !== false ? explode('/', $cfr)[0] : $cfr);

                    $toInsert[] = [
                        'cfr' => $cfr,
                        'no_order' => $noOrder,
                        'periode' => $meta->periode ?? null,
                        'nama_perusahaan' => $meta->nama_perusahaan ?? null,
                        'keterangan_titik' => $meta->keterangan_titik ?? null,
                        'alasan' => $alasan,
                        'is_active' => true,
                        'created_by' => $this->karyawan,
                        'updated_by' => $this->karyawan,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $added++;
                }

                if (!empty($toInsert)) {
                    BlokLhp::insert($toInsert);
                }
            });

            if ($added === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Semua No. LHP yang dipilih sudah berada dalam daftar blok.',
                ], 422);
            }

            $msg = "{$added} LHP berhasil ditambahkan ke daftar blok.";
            if ($skipped > 0) {
                $msg .= " ({$skipped} LHP sudah diblok sebelumnya.)";
            }

            return response()->json([
                'success' => true,
                'message' => $msg,
                'added' => $added,
                'skipped' => $skipped,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        $id = $request->input('id');
        $cfr = trim((string) $request->input('cfr', ''));

        if (empty($id) && $cfr === '') {
            return response()->json([
                'success' => false,
                'message' => 'Parameter id atau cfr wajib diisi.',
            ], 422);
        }

        $query = BlokLhp::query()->where('is_active', true);
        if (!empty($id)) {
            $query->where('id', $id);
        } else {
            $query->where('cfr', $cfr);
        }

        $row = $query->first();
        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Data blok LHP tidak ditemukan.',
            ], 404);
        }

        try {
            $now = Carbon::now()->format('Y-m-d H:i:s');
            $row->is_active = false;
            $row->deleted_by = $this->karyawan;
            $row->deleted_at = $now;
            $row->updated_by = $this->karyawan;
            $row->updated_at = $now;
            $row->save();

            return response()->json([
                'success' => true,
                'message' => "Blok LHP {$row->cfr} berhasil dihapus.",
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
