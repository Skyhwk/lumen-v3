<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\TemplateStp;
use App\Models\OrderDetail;
use App\Models\DataLimbah;
use App\Models\MasterCabang;
use App\Models\MasterKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
use DB;

class DataLimbahController extends Controller
{
    public function index(Request $request){
        // dd('masuk');
        try {
            $data = DataLimbah::with('order')
                    ->where('is_active', true)
                    ->orderBy('created_at', 'desc');

            return Datatables::of($data)
                ->addColumn('tanggal_sampling', function ($row) {
                    return $row->order ? $row->order->tanggal_sampling : '-';
                })
                ->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function showNoSample(Request $request){
        $orderQuery = OrderDetail::select('id', 'no_sampel', 'tanggal_sampling')
            ->where('is_active', true)
            ->where('kategori_2', 1)
            ->whereDate('created_at', '>=', DB::raw('DATE(NOW()) - INTERVAL 1 MONTH'));

        $dataLimbahQuery = DataLimbah::select('order_detail.id as id', 'data_limbah.no_sampel', 'order_detail.tanggal_sampling')
            ->join('order_detail', 'data_limbah.no_sampel', '=', 'order_detail.no_sampel')
            ->where('data_limbah.is_active', true);

        $query = DB::query()->fromSub(
            $orderQuery->unionAll($dataLimbahQuery),
            'tbl'
        )
        ->groupBy('id', 'no_sampel', 'tanggal_sampling')
        ->havingRaw('COUNT(*) = 1')
        ->orderByDesc('id');

        return Datatables::of($query)
            ->make(true);
    }

    public function moveToLimbah(Request $request){
        try {
            // $exp = explode("_", $request->no_sample);
            $status = ($request->status == 1) ? 'Memenuhi Baku Mutu' : 'Tidak Memenuhi Baku Mutu';
            $message = '';
            if(isset($request->id) && $request->id != ''){
                $data = DataLimbah::where('id', $request->id)->where('is_active', true)->first();
                $data->no_sampel = $request->no_sample;
                $data->status_limbah = $status;
                $data->updated_by = $this->karyawan;
                $data->updated_at = date('Y-m-d H:i:s');
                $data->save();

                $message = 'Data Limbah berhasil diupdate';
            } else {
                $data = new DataLimbah;
                $data->no_sampel = $request->no_sample;
                $data->status_limbah = $status;
                $data->created_by = $this->karyawan;
                $data->created_at = date('Y-m-d H:i:s');
                $data->save();
    
                $message = 'Data Limbah berhasil disimpan';
            }

            return response()->json([
                'message' => $message
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function destroy(Request $request){
        try {
            if(isset($request->id) && $request->id != ''){
                $data = DataLimbah::where('id', $request->id)->where('is_active', true)->first();

                $data->is_active = false;
                $data->deleted_by = $this->karyawan;
                $data->deleted_at = date('Y-m-d H:i:s');
                $data->save();

                return response()->json([
                    'message' => 'Data Limbah berhasil dihapus'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Data not Found'
                ], 404);
            }  
        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }
}