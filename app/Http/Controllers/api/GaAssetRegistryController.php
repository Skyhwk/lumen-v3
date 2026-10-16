<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAsset;
use DataTables;
use Illuminate\Http\Request;

class GaAssetRegistryController extends Controller
{
    public function index(Request $request)
    {
        $query = GaAsset::query()
            ->where('ga_assets.is_active', true)
            ->with([
                'kategori:id,nama_kategori',
                'subKategori:id,nama_sub_kategori',
                'room:id,name',
                'cabang:id,nama_cabang,kode_cabang',
                'location:id,name',
            ])
            ->select('ga_assets.*')
            ->orderByDesc('ga_assets.id');

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
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('cabang', function ($sub) use ($keyword) {
                        $sub->where('nama_cabang', 'like', '%' . $keyword . '%');
                    })->orWhereHas('room', function ($sub) use ($keyword) {
                        $sub->where('name', 'like', '%' . $keyword . '%');
                    });
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
                    $query->where('ga_assets.label_legacy', 'V');
                    return;
                }
                if (stripos($kw, 'belum') !== false || stripos($kw, '-') !== false) {
                    $query->where(function ($q) {
                        $q->whereNull('ga_assets.label_legacy')->orWhere('ga_assets.label_legacy', '!=', 'V');
                    });
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
        $labeled = (clone $base)->where('label_legacy', 'V')->count();

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
}
