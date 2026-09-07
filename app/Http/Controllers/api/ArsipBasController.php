<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LogBas;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class ArsipBasController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = LogBas::query()
                ->whereNotNull('filename_bas')
                ->where('filename_bas', '!=', '');

            return Datatables::of($query)
                ->addColumn('nomor_quotation', fn ($row) => $row->no_quotation)
                ->addColumn('jadwal', fn ($row) => $row->tanggal_tugas)
                ->editColumn('kategori', function ($row) {
                    $kategori = $row->kategori;

                    if (is_string($kategori)) {
                        $kategori = json_decode($kategori, true);
                    }

                    return is_array($kategori) ? implode(',', $kategori) : ($kategori ?? '');
                })
                ->filterColumn('nomor_quotation', function ($query, $keyword) {
                    $query->where('no_quotation', 'like', "%{$keyword}%");
                })
                ->filterColumn('no_order', function ($query, $keyword) {
                    $query->where('no_order', 'like', "%{$keyword}%");
                })
                ->filterColumn('periode', function ($query, $keyword) {
                    $query->where('periode', 'like', "%{$keyword}%");
                })
                ->filterColumn('jadwal', function ($query, $keyword) {
                    $query->where('tanggal_tugas', 'like', "%{$keyword}%");
                })
                ->filterColumn('sampler', function ($query, $keyword) {
                    $query->where('sampler', 'like', "%{$keyword}%");
                })
                ->orderColumn('nomor_quotation', 'no_quotation $1')
                ->orderColumn('no_order', 'no_order $1')
                ->orderColumn('periode', 'periode $1')
                ->orderColumn('jadwal', 'tanggal_tugas $1')
                ->orderColumn('sampler', 'sampler $1')
                ->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line'    => $ex->getLine(),
            ], 500);
        }
    }
}
