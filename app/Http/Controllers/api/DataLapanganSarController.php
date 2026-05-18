<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DataLapanganSARHeader;
use App\Models\MasterCabang;
use App\Models\MasterKaryawan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class DataLapanganSarController extends Controller
{
    public function index(Request $request)
    {
        try {
            $salesSubquery = DB::table('request_quotation')
                ->select('no_document', 'id_cabang', 'sales_id', 'tanggal_penawaran')
                ->where('is_active', true)
                ->unionAll(
                    DB::table('request_quotation_kontrak_H')
                        ->select('no_document', 'id_cabang', 'sales_id', 'tanggal_penawaran')
                        ->where('is_active', true)
                );

            $data = DataLapanganSARHeader::query()
                ->leftJoinSub($salesSubquery, 'qt', function ($join) {
                    $join->on('datalapangan_sar_header.no_quotation', '=', 'qt.no_document');
                })
                ->leftJoin('master_cabang as mc', 'qt.id_cabang', '=', 'mc.id')
                ->select([
                    'datalapangan_sar_header.id',
                    'datalapangan_sar_header.no_quotation',
                    'datalapangan_sar_header.no_order',
                    'datalapangan_sar_header.nama_pelanggan',
                    'datalapangan_sar_header.alamat_pelanggan',
                    'datalapangan_sar_header.email_pelanggan',
                    'datalapangan_sar_header.no_telpon',
                    'datalapangan_sar_header.jumlah_sampel',
                    'datalapangan_sar_header.filename',
                    'datalapangan_sar_header.updated_at',
                    'datalapangan_sar_header.updated_by',
                    'qt.id_cabang',
                    'qt.sales_id',
                    'qt.tanggal_penawaran',
                    'mc.nama_cabang',
                ])
                ->whereNotNull('datalapangan_sar_header.filename');

            if (!empty($request->cabang)) {
                $data->where('qt.id_cabang', $request->cabang);
            }

            if (!empty($request->year)) {
                $data->whereYear(DB::raw('COALESCE(qt.tanggal_penawaran, datalapangan_sar_header.updated_at)'), $request->year);
            }

            $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
            switch ($jabatan) {
                case 24:
                    $data->where('qt.sales_id', $this->user_id);
                    break;
                case 21:
                    $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                        ->pluck('id')
                        ->toArray();
                    array_push($bawahan, $this->user_id);
                    $data->whereIn('qt.sales_id', $bawahan);
                    break;
            }

            $data->orderBy('datalapangan_sar_header.updated_at', 'desc')
                ->orderBy('datalapangan_sar_header.id', 'desc');

            return DataTables::of($data)->make(true);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function getCabang()
    {
        return MasterCabang::where('is_active', true)->get(['id', 'nama_cabang']);
    }
}
