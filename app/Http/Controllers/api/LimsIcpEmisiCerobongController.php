<?php

namespace App\Http\Controllers\api;

use App\Models\EmisiCerobongHeader;
use App\Models\OrderDetail;
use App\Models\WsValueEmisiCerobong;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\ApproveAnalystService;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class LimsIcpEmisiCerobongController extends Controller
{

    // 20-03-2025
    public function index(Request $request){
        $limsDb = \Illuminate\Support\Facades\DB::connection('lims')->getDatabaseName();

        $data = EmisiCerobongHeader::with('ws_value_one')
            ->join(\Illuminate\Support\Facades\DB::raw("{$limsDb}.order_detail as order_detail"),'order_detail.no_sampel','=','emisi_cerobong_header.no_sampel')
            ->where('is_approved', $request->approve)
            ->where('emisi_cerobong_header.is_active', true)
            ->where('template_stp', $request->template_stp)
            ->select('emisi_cerobong_header.*', 'order_detail.tanggal_terima as od_tanggal_terima', 'order_detail.kategori_3 as od_kategori_3', 'order_detail.tanggal_sampling as od_tanggal_sampling')
            ->orderByRaw("
                CASE 
                    WHEN order_detail.tanggal_terima IS NULL THEN 1
                    ELSE 0
                END,
                order_detail.tanggal_terima DESC
            ");
        
        if ($request->filled('periode')) {
            $periode = explode('-', $request->periode);
            if (count($periode) == 2) {
                $data->whereYear('emisi_cerobong_header.created_at', $periode[0])
                     ->whereMonth('emisi_cerobong_header.created_at', $periode[1]);
            }
        }
        return Datatables::of($data)
            ->addColumn('tanggal_terima', function ($item) {
                return $item->od_tanggal_terima ?? '-';
            })

            ->addColumn('tanggal_sampling', function ($item) {
                return $item->od_tanggal_sampling ?? '-';
            })

            ->addColumn('kategori_3', function ($item) {
                return $item->od_kategori_3 ?? '-';
            })

            ->filterColumn('tanggal_terima', function ($query, $keyword) {
                $query->where('order_detail.tanggal_terima', 'like', "%{$keyword}%");
            })

            ->filterColumn('tanggal_sampling', function ($query, $keyword) {
                $query->where('order_detail.tanggal_sampling', 'like', "%{$keyword}%");
            })

            ->filterColumn('kategori_3', function ($query, $keyword) {
                $query->where('order_detail.kategori_3', 'like', "%{$keyword}%");
            })

            ->filter(function ($query) use ($request) {

                if ($request->has('columns')) {
                    $columns = $request->get('columns');

                    foreach ($columns as $column) {

                        if (!empty($column['search']['value'])) {

                            $columnName = $column['name'] ?: $column['data'];
                            $searchValue = $column['search']['value'];

                            // HANYA BOLEH FILTER KOLOM colorimetri
                            if (in_array($columnName, [
                                'parameter',
                                'jenis_pengujian',
                                'created_at'
                            ])) {
                                $query->where("emisi_cerobong_header.$columnName", 'like', "%{$searchValue}%");
                            }

                        }
                    }
                }
            })
            ->removeColumn('od_tanggal_terima', 'od_kategori_3', 'od_tanggal_sampling')
        ->make(true);
    }

    public function approveData(Request $request){
        
        DB::beginTransaction();
        try {
            $data = EmisiCerobongHeader::where('id', $request->id)->where('is_active', true)->first();
            if($data->is_approved == 1){
                return response()->json([
                    'status' => false,
                    'message' => 'Data emisi cerobong no sample ' . $data->no_sampel . ' sudah di approve'
                ],401);
            }
            $data->is_approved = 1;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->approved_by = $this->karyawan;
            $data->save();

            ApproveAnalystService::noSampel($data->no_sampel)
                ->approvedBy($this->karyawan)
                ->menu('Analysis');

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Data emisi cerobong no sample ' . $data->no_sampel . ' berhasil di approve'
            ],200);

        } catch (\Throwable $th) {
            DB::rollBack();
            dd($th);
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan! ' . $th->getMessage()
            ],401);
        }
    }

    public function deleteData(Request $request){
        DB::beginTransaction();
        try {
            $data = EmisiCerobongHeader::where('id', $request->id)->first();
            $data->is_active = false;
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->deleted_by = $this->karyawan;
            $data->save();

            $ws_value = WsValueEmisiCerobong::where('id_emisi_cerobong_header', $request->id)->where('is_active', true)->first();
            if($ws_value){
                $ws_value->is_active = false;
                $ws_value->save();
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Data emisi cerobong no sample ' . $data->no_sampel . ' berhasil dihapus .!'
            ],200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan! ' . $th->getMessage()
            ],401);
        }
    }
}
