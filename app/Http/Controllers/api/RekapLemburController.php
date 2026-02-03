<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use DataTables;

use App\Models\FormDetail;
use App\Models\MasterDivisi;
use App\Models\MasterKaryawan;

class RekapLemburController extends Controller
{
    public function index()
    {
        $rekap = FormDetail::on('intilab_apps')
            ->select('tanggal_mulai as tanggal', DB::raw('count(user_id) as jumlah'))
            ->whereNotNull('approved_finance_by')
            ->where('is_active', true)
            ->groupBy('tanggal_mulai')
            ->orderByDesc('tanggal_mulai');

        return DataTables::of($rekap)->make(true);
    }

    public function detail(Request $request)
    {
        $divisi = MasterDivisi::where('is_active', true)->get();

        $rekap = [];
        foreach ($divisi as $item) {
            $detail = FormDetail::on('intilab_apps')
                ->where('department_id', $item->id)
                ->where('tanggal_mulai', $request->tanggal)
                ->whereNotNull('approved_finance_by')
                ->where('is_active', true)
                ->get();

            if ($detail->isNotEmpty()) {
                $karyawan = MasterKaryawan::whereIn('id', $detail->pluck('user_id')->unique()->toArray())->get();
                $detail->map(function ($item) use ($karyawan) {
                    $item->karyawan = $karyawan->where('id', $item->user_id)->first();
                });

                $rekap[] = [
                    'kode_divisi' => $item->kode_divisi,
                    'nama_divisi' => $item->nama_divisi,
                    'detail' => $detail->toArray()
                ];
            }
        }

        return response()->json(['data' => $rekap, 'message' => 'Data retrieved successfully'], 200);
    }
}
