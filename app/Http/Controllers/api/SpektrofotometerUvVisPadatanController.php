<?php

namespace App\Http\Controllers\api;

use App\Models\Colorimetri;
use App\Models\OrderDetail;
use App\Models\WsValueAir;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class SpektrofotometerUvVisPadatanController extends Controller
{
    // public function index(Request $request){
    //     $data = Colorimetri::with('ws_value', 'order_detail')->where('is_approved', $request->approve)->where('is_active', true)->where('template_stp', $request->template_stp)->orderBy('created_at', 'desc');
    //     return Datatables::of($data)->make(true);
    // }

    // 20-03-2025
    public function index(Request $request){
        $data = Colorimetri::with('ws_value', 'order_detail')
        ->where('is_approved', $request->approve)
        ->where('colorimetri.is_active', true)
        ->where('template_stp', $request->template_stp)
        ->select('colorimetri.*');
        return Datatables::of($data)
            ->addColumn('tanggal_terima', function ($item) {
                return $item->order_detail->tanggal_terima ?? '-';
            })

            ->addColumn('kategori_3', function ($item) {
                return $item->order_detail->kategori_3 ?? '-';
            })

            ->filterColumn('tanggal_terima', function ($query, $keyword) {
                $query->whereHas('order_detail', function ($query) use ($keyword) {
                    $query->where('tanggal_terima', 'like', "%{$keyword}%");
                });
            })

            ->filterColumn('kategori_3', function ($query, $keyword) {
                $query->whereHas('order_detail', function ($query) use ($keyword) {
                    $query->where('kategori_3', 'like', "%{$keyword}%");
                });
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
                                $query->where("colorimetri.$columnName", 'like', "%{$searchValue}%");
                            }

                        }
                    }
                }
            })
        ->make(true);
    }

    public function approveData(Request $request){
        DB::beginTransaction();
        try {
            $data = Colorimetri::where('id', $request->id)->where('is_active', true)->first();
            if($data->is_approved == 1){
                return response()->json([
                    'status' => false,
                    'message' => 'Data colorimetri no sample ' . $data->no_sampel . ' sudah di approve'
                ],401);
            }
            $data->is_approved = 1;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->approved_by = $this->karyawan;
            $data->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Data colorimetri no sample ' . $data->no_sampel . ' berhasil di approve'
            ],200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan! ' . $th->getMessage()
            ],401);
        }
    }

    public function deleteData(Request $request){
        DB::beginTransaction();
        try {
            $data = Colorimetri::where('id', $request->id)->first();
            $data->is_active = false;
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->deleted_by = $this->karyawan;
            $data->save();

            $ws_value = WsValueAir::where('id_colorimetri', $request->id)->where('is_active', true)->first();
            if($ws_value){
                $ws_value->is_active = false;
                $ws_value->save();
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Data colorimetri no sample ' . $data->no_sampel . ' berhasil dihapus .!'
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