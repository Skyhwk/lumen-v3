<?php

namespace App\Http\Controllers\api;

use App\Models\Subkontrak;
use App\Models\OrderDetail;
use App\Models\WsValueAir;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class BenthosController extends Controller
{
    public function index(Request $request){
        $data = Subkontrak::with('ws_value', 'order_detail')
            ->where('parameter', 'Benthos')
            ->where('is_approve', $request->approve)
            ->where('subkontrak.is_active', true)
            ->where('subkontrak.is_total', false)
            ->orderBy('subkontrak.created_at', 'desc')
            ->select('subkontrak.*');
        return Datatables::of($data)
            ->editColumn('hasil_json', function ($item) {
                $hasil = json_decode($item->ws_value->hasil_json ?? '{}', true);
                return $hasil ?? [];
            })
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
                                $query->where("subkontrak.$columnName", 'like', "%{$searchValue}%");
                            }

                        }
                    }
                }
            })
        ->make(true);
    }

    public function approveSampel(Request $request){
        DB::beginTransaction();
        try {
            $data = Subkontrak::where('id', $request->id)->first();
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->approved_by = $this->karyawan;
            $data->is_approve = 1;
            $data->save();

            DB::commit();

            return response()->json([
                'status' => true,
                "success" => true,
                'message' => 'Data colorimetri no sample ' . $data->no_sampel . ' berhasil disetujui .!'
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
            $data = Subkontrak::where('id', $request->id)->first();
            $data->is_active = false;
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->deleted_by = $this->karyawan;
            $data->is_retest = 1;
            $data->notes_reject_retest  = $request->note;
            $data->save();

            $ws_value = WsValueAir::where('id_colorimetri', $request->id)->where('is_active', true)->first();
            if($ws_value){
                $ws_value->is_active = false;
                $ws_value->save();
            }

            DB::commit();

            return response()->json([
                'status' => true,
                "success" => true,
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