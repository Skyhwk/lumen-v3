<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{LinkLhp, MasterKaryawan, QuotationKontrakH, QuotationNonKontrak};
use App\Services\{GetAtasan, SendEmail};

class RekapHasilPengujianController extends Controller
{
    public function index()
    {
        $linkLhp = LinkLhp::with('token')->where('is_emailed', true)->orderBy('emailed_at', 'desc');

        return Datatables::of($linkLhp)
        ->filterColumn('is_completed', function ($query, $keyword) {
            if($keyword != '') {
                $query->where('is_completed', $keyword);
            } 
        })
        ->make(true);
    }

    public function updateKeterangan(Request $request)
    {
        DB::beginTransaction();
        try {
            $linkLhp = LinkLhp::find($request->id);
            
            if(!$linkLhp) return response()->json(['message' => 'Link LHP tidak ditemukan harap hubungi IT'], 404);

            $linkLhp->keterangan = $request->keterangan;
            $linkLhp->save();

            DB::commit();
            return response()->json(['message' => 'Keterangan berhasil diupdate'], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $th->getMessage()], 500);
        }

    }



    public function reject(Request $request) 
    {
        $linkLhp = LinkLhp::find($request->id);
        $linkLhp->update(['is_emailed' => false]);

        return response()->json(['message' => 'Data berhasil direject'], 200);
    }
}
