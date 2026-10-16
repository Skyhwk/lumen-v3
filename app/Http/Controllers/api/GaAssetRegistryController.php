<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Jobs\CreateGaAssetJob;
use App\Models\GaAsset;
use App\Models\GaAssetRoom;
use App\Models\MasterKategoriAset;
use App\Models\MasterSubKategoriAset;
use App\Services\GaAsset\GaAssetQrPdfService;
use App\Services\GaAsset\GaAssetSequenceService;
use App\Services\GaAsset\GaAssetWriteService;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GaAssetRegistryController extends Controller
{
    public function index(Request $request)
    {
        $query = GaAsset::query()
            ->where('ga_assets.is_active', true)
            ->with([
                'kategori:id,nama_kategori',
                'subKategori:id,nama_sub_kategori',
                'room:id,name,location_id',
                'room.location:id,name,branch_id',
                'room.location.cabang:id,nama_cabang',
                'cabang:id,nama_cabang,kode_cabang',
                'location:id,name',
                'pic:id,nama_lengkap',
            ])
            ->select('ga_assets.*')
            ->orderByDesc('ga_assets.id');

        $this->applySummaryFilter($query, $request);

        return DataTables::of($query)
            ->filterColumn('cs_code', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('ga_assets.cs_code', 'like', '%' . $keyword . '%')
                        ->orWhere('ga_assets.asset_code', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('name', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('ga_assets.name', 'like', '%' . $keyword . '%')
                        ->orWhere('ga_assets.brand', 'like', '%' . $keyword . '%')
                        ->orWhere('ga_assets.model_spec', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('nama_kategori', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('kategori', function ($sub) use ($keyword) {
                        $sub->where('nama_kategori', 'like', '%' . $keyword . '%');
                    })->orWhereHas('subKategori', function ($sub) use ($keyword) {
                        $sub->where('nama_sub_kategori', 'like', '%' . $keyword . '%');
                    });
                });
            })
            ->filterColumn('lokasi', function ($query, $keyword) {
                $query->whereHas('room', function ($sub) use ($keyword) {
                    $sub->where('name', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('acquisition_origin', function ($query, $keyword) {
                $query->where('ga_assets.acquisition_origin', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('condition', function ($query, $keyword) {
                $query->where('ga_assets.condition', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('label_legacy', function ($query, $keyword) {
                $kw = trim($keyword);
                if ($kw === '') {
                    return;
                }
                if (stripos($kw, 'v') !== false || stripos($kw, 'label') !== false || stripos($kw, 'sudah') !== false) {
                    $this->constrainLabeled($query);
                    return;
                }
                if (stripos($kw, 'belum') !== false || stripos($kw, '-') !== false) {
                    $this->constrainUnlabeled($query);
                    return;
                }
                $query->where('ga_assets.label_legacy', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function summary()
    {
        $base = GaAsset::query()->where('is_active', true);

        $total = (clone $base)->count();
        $labeledQuery = clone $base;
        $this->constrainLabeled($labeledQuery);
        $labeled = $labeledQuery->count();

        $byCondition = (clone $base)
            ->reorder()
            ->selectRaw('`condition`, COUNT(*) as total')
            ->groupBy('condition')
            ->pluck('total', 'condition');

        return response()->json([
            'total_assets' => $total,
            'labeled_assets' => $labeled,
            'unlabeled_assets' => max(0, $total - $labeled),
            'by_condition' => $byCondition,
        ]);
    }

    public function formOptions()
    {
        return response()->json([
            'categories' => MasterKategoriAset::query()
                ->where('is_active', 1)
                ->orderBy('nama_kategori')
                ->get(['id', 'nama_kategori']),
            'sub_categories' => MasterSubKategoriAset::query()
                ->where('is_active', 1)
                ->orderBy('nama_sub_kategori')
                ->get(['id', 'id_kategori', 'nama_sub_kategori']),
            'rooms' => DB::table('ga_asset_rooms')
                ->leftJoin('ga_asset_locations', 'ga_asset_locations.id', '=', 'ga_asset_rooms.location_id')
                ->leftJoin('master_cabang', 'master_cabang.id', '=', 'ga_asset_locations.branch_id')
                ->where('ga_asset_rooms.is_active', 1)
                ->orderBy('ga_asset_rooms.name')
                ->get([
                    'ga_asset_rooms.id',
                    'ga_asset_rooms.name',
                    'ga_asset_locations.name as location_name',
                    'master_cabang.nama_cabang as cabang_name',
                ]),
            'employees' => DB::table('master_karyawan')
                ->where('is_active', 1)
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nik_karyawan']),
        ]);
    }

    public function previewCsCode(Request $request)
    {
        $subCategory = $this->resolveSubCategoryForPreview($request);
        if (!$subCategory) {
            return response()->json(['preview' => null]);
        }

        $nextNumber = $this->sequences()->peekNext((int) ($subCategory->id ?? 0), $subCategory->nama_sub_kategori);

        return response()->json([
            'preview' => $this->sequences()->formatCsCode($subCategory->nama_sub_kategori, $nextNumber),
            'next_number' => $nextNumber,
        ]);
    }

    public function show(Request $request)
    {
        $asset = GaAsset::query()
            ->where('is_active', true)
            ->with([
                'kategori:id,nama_kategori',
                'subKategori:id,id_kategori,nama_sub_kategori',
                'room:id,name,location_id,is_active',
                'room.location:id,name,branch_id',
                'room.location.cabang:id,nama_cabang',
                'cabang:id,nama_cabang,kode_cabang',
                'location:id,name',
            ])
            ->find($request->id);

        if (!$asset) {
            return response()->json(['message' => 'Aset tidak ditemukan.'], 404);
        }

        $pic = null;
        if ($asset->pic_employee_id) {
            $pic = DB::table('master_karyawan')
                ->where('id', $asset->pic_employee_id)
                ->first(['id', 'nama_lengkap', 'nik_karyawan', 'is_active']);
        }

        return response()->json([
            'data' => $asset,
            'pic' => $pic,
        ]);
    }

    public function filteredIds(Request $request)
    {
        $ids = $this->filteredAssetIds($request);

        return response()->json([
            'ids' => $ids,
            'count' => count($ids),
        ]);
    }

    public function exportQrPdf(Request $request)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $ids = $this->exportIdList($request);

        try {
            $result = app(GaAssetQrPdfService::class)->export($ids);

            return response()->json([
                'message' => $result['count'] . ' QR aset berhasil diexport.',
                'data' => $result['url'],
                'filename' => $result['filename'],
                'count' => $result['count'],
            ]);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            return response()->json(['message' => 'Gagal export QR: ' . $exception->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $invalid = $this->validateAssetPayload($request);
        if ($invalid) {
            return $invalid;
        }

        $invalidRef = $this->assertStoreReferences($request);
        if ($invalidRef) {
            return $invalidRef;
        }

        $actor = trim((string) $this->karyawan) ?: 'GA Asset System';
        $job = new CreateGaAssetJob($request->all(), $actor);
        $this->dispatch($job);

        sleep(3);

        return response()->json([
            'message' => 'Aset berhasil ditambahkan. NO CS digenerate otomatis.',
        ], 200);
    }

    public function update(Request $request)
    {
        $invalid = $this->validateAssetPayload($request, true);
        if ($invalid) {
            return $invalid;
        }

        try {
            $actor = trim((string) $this->karyawan) ?: 'GA Asset System';
            $asset = app(GaAssetWriteService::class)->update($request, $actor);

            return response()->json([
                'message' => 'Aset berhasil diupdate. NO CS ' . $asset->cs_code . '.',
                'data' => $asset,
            ]);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            return response()->json(['message' => 'Gagal mengupdate aset: ' . $exception->getMessage()], 500);
        }
    }

    private function validateAssetPayload(Request $request, bool $isUpdate = false)
    {
        $rules = [
            'kategori_aset_id' => 'nullable|integer',
            'kategori_baru' => 'nullable|string|max:191',
            'sub_kategori_aset_id' => 'nullable|integer',
            'sub_kategori_baru' => 'nullable|string|max:191',
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:150',
            'model_spec' => 'nullable|string|max:255',
            'acquisition_origin' => 'required|in:new,second,take_over,unknown',
            'purchase_date' => 'nullable|date',
            'condition' => 'required|in:hilang,kendala,normal,rusak,rusak_berat,transfer',
            'condition_note' => 'nullable|string',
            'is_unallocated' => 'required|boolean',
            'usage_state' => 'nullable|in:in_use,not_use,store,unknown',
            'room_id' => 'nullable|integer',
            'pic_employee_id' => 'nullable|integer',
        ];
        if ($isUpdate) {
            $rules['id'] = 'required|integer';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }
        if (!$request->filled('kategori_aset_id') && !$request->filled('kategori_baru')) {
            return response()->json(['message' => 'Kategori aset wajib dipilih atau diisi.'], 422);
        }
        if (!$request->filled('sub_kategori_aset_id') && !$request->filled('sub_kategori_baru')) {
            return response()->json(['message' => 'Sub kategori aset wajib dipilih atau diisi.'], 422);
        }
        if ($request->condition !== 'normal' && trim((string) $request->condition_note) === '') {
            return response()->json(['message' => 'Catatan kondisi wajib diisi jika kondisi aset bukan Normal.'], 422);
        }

        return null;
    }

    private function assertStoreReferences(Request $request)
    {
        if ($request->filled('kategori_aset_id')) {
            $exists = MasterKategoriAset::where('id', $request->kategori_aset_id)->where('is_active', 1)->exists();
            if (!$exists) {
                return response()->json(['message' => 'Kategori aset tidak aktif atau tidak ditemukan.'], 422);
            }
        }
        if ($request->filled('sub_kategori_aset_id')) {
            $subQuery = MasterSubKategoriAset::where('id', $request->sub_kategori_aset_id)->where('is_active', 1);
            if ($request->filled('kategori_aset_id')) {
                $subQuery->where('id_kategori', $request->kategori_aset_id);
            }
            if (!$subQuery->exists()) {
                return response()->json(['message' => 'Sub kategori tidak aktif atau tidak sesuai dengan kategori.'], 422);
            }
        }
        if ($request->boolean('is_unallocated')) {
            return null;
        }
        if (!$request->filled('room_id')) {
            return response()->json(['message' => 'Ruangan wajib dipilih jika aset dialokasikan.'], 422);
        }
        if (!GaAssetRoom::where('id', $request->room_id)->where('is_active', 1)->exists()) {
            return response()->json(['message' => 'Ruangan tidak aktif atau tidak ditemukan.'], 422);
        }
        if ($request->filled('pic_employee_id')) {
            $picExists = DB::table('master_karyawan')
                ->where('id', $request->pic_employee_id)
                ->where('is_active', 1)
                ->exists();
            if (!$picExists) {
                return response()->json(['message' => 'Penanggung jawab / pengguna aset tidak aktif atau tidak ditemukan.'], 422);
            }
        }

        return null;
    }

    private function resolveSubCategoryForPreview(Request $request)
    {
        if ($request->filled('sub_kategori_aset_id')) {
            return MasterSubKategoriAset::where('id', $request->sub_kategori_aset_id)
                ->where('is_active', 1)
                ->first();
        }

        if ($request->filled('sub_kategori_baru')) {
            return (object) [
                'id' => 0,
                'nama_sub_kategori' => trim((string) $request->sub_kategori_baru),
            ];
        }

        return null;
    }

    private function exportIdList(Request $request): array
    {
        if ($request->boolean('select_all')) {
            return $this->filteredAssetIds($request);
        }

        $ids = $request->input('ids_json', $request->input('ids', []));
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : array_filter(explode(',', $ids));
        }
        if (!is_array($ids)) {
            $ids = [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    private function filteredAssetIds(Request $request): array
    {
        $query = GaAsset::query()
            ->where('ga_assets.is_active', true)
            ->whereNotNull('ga_assets.cs_code')
            ->where('ga_assets.cs_code', '!=', '')
            ->select('ga_assets.id')
            ->orderByDesc('ga_assets.id');

        $this->applySummaryFilter($query, $request);
        $this->applyRequestColumnFilters($query, $request);

        return $query->pluck('ga_assets.id')->map(function ($id) {
            return (int) $id;
        })->all();
    }

    private function applySummaryFilter($query, Request $request)
    {
        $summaryFilter = trim((string) $request->input('summary_filter', 'all'));
        if ($summaryFilter === 'labeled') {
            $this->constrainLabeled($query);
        } elseif ($summaryFilter === 'unlabeled') {
            $this->constrainUnlabeled($query);
        } elseif ($summaryFilter === 'attention') {
            $query->whereIn('ga_assets.condition', [
                'kendala',
                'rusak',
                'rusak_berat',
                'hilang',
                'issue',
                'damaged',
                'heavy_damage',
                'missing',
            ]);
        }
    }

    private function applyRequestColumnFilters($query, Request $request)
    {
        $columns = $request->input('columns', []);
        if (!is_array($columns)) {
            return;
        }

        foreach ($columns as $column) {
            $name = $column['name'] ?? '';
            $keyword = trim((string) ($column['search']['value'] ?? ''));
            if ($name === '' || $keyword === '') {
                continue;
            }

            if ($name === 'cs_code') {
                $query->where(function ($q) use ($keyword) {
                    $q->where('ga_assets.cs_code', 'like', '%' . $keyword . '%')
                        ->orWhere('ga_assets.asset_code', 'like', '%' . $keyword . '%');
                });
            } elseif ($name === 'name') {
                $query->where(function ($q) use ($keyword) {
                    $q->where('ga_assets.name', 'like', '%' . $keyword . '%')
                        ->orWhere('ga_assets.brand', 'like', '%' . $keyword . '%')
                        ->orWhere('ga_assets.model_spec', 'like', '%' . $keyword . '%');
                });
            } elseif ($name === 'nama_kategori') {
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('kategori', function ($sub) use ($keyword) {
                        $sub->where('nama_kategori', 'like', '%' . $keyword . '%');
                    })->orWhereHas('subKategori', function ($sub) use ($keyword) {
                        $sub->where('nama_sub_kategori', 'like', '%' . $keyword . '%');
                    });
                });
            } elseif ($name === 'lokasi') {
                $query->whereHas('room', function ($sub) use ($keyword) {
                    $sub->where('name', 'like', '%' . $keyword . '%');
                });
            } elseif ($name === 'acquisition_origin') {
                $query->where('ga_assets.acquisition_origin', 'like', '%' . $keyword . '%');
            } elseif ($name === 'condition') {
                $query->where('ga_assets.condition', 'like', '%' . $keyword . '%');
            } elseif ($name === 'label_legacy') {
                if (stripos($keyword, 'v') !== false || stripos($keyword, 'label') !== false || stripos($keyword, 'sudah') !== false) {
                    $this->constrainLabeled($query);
                } elseif (stripos($keyword, 'belum') !== false || stripos($keyword, '-') !== false) {
                    $this->constrainUnlabeled($query);
                } else {
                    $query->where('ga_assets.label_legacy', 'like', '%' . $keyword . '%');
                }
            }
        }
    }

    private function constrainLabeled($query)
    {
        return $query->where(function ($q) {
            $q->where('ga_assets.label_legacy', 1)
                ->orWhere('ga_assets.label_legacy', '1')
                ->orWhere('ga_assets.label_legacy', 'V');
        });
    }

    private function constrainUnlabeled($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('ga_assets.label_legacy')
                ->orWhere('ga_assets.label_legacy', 0)
                ->orWhere('ga_assets.label_legacy', '0')
                ->orWhere('ga_assets.label_legacy', '');
        });
    }

    private function sequences(): GaAssetSequenceService
    {
        return app(GaAssetSequenceService::class);
    }
}
