<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Career;
use App\Models\MasterCabang;
use DB;
use Repository;

/**
 * API karir untuk website publik (Website-pak-eko).
 * Terpisah dari CareersController (admin/datatable frontend LUMEN-V3).
 */
class PublicCareersController extends Controller
{
    public function list(Request $request)
    {
        $careers = DB::table('company_profile.careers')
            ->leftJoin('intilab_produksi.master_cabang as cabang', 'careers.id_cabang', '=', 'cabang.id')
            ->where('careers.is_active', true)
            ->orderBy('careers.created_at', 'desc')
            ->select('careers.*', 'cabang.alamat_cabang', 'cabang.nama_cabang', 'cabang.id as cabang_id')
            ->get();

        $careers->map(function ($item) {
            $item->image = env('APP_URL') . '/public/profile/career/image/' . $item->image;
            unset($item->requirement);
            return $item;
        });

        return response()->json($careers, 200);
    }

    public function show(Request $request)
    {
        $data = Career::find($request->id);
        if (!$data) {
            return response()->json(['message' => 'Lowongan tidak ditemukan'], 404);
        }

        $cabang = MasterCabang::find($data->id_cabang);
        if (!$cabang) {
            return response()->json(['message' => 'Cabang tidak ditemukan'], 404);
        }

        $data->image = env('APP_URL') . '/public/profile/career/image/' . $data->image;
        $data->requirement = Repository::dir('career-requirements')->key(
            strtoupper(str_replace(' ', '_', $data->title . '_' . $data->type))
        )->get();
        $data->cabang = $cabang->nama_cabang;
        $data->alamat_cabang = $cabang->alamat_cabang;
        $data->type = ucfirst($data->type);

        return response()->json($data, 200);
    }
}
