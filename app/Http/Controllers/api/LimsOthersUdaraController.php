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

class LimsOthersUdaraController extends Controller
{

    // 20-03-2025
    public function index(Request $request){
        $limsDb = DB::connection('lims')->getDatabaseName();

        $data = Subkontrak::with('ws_value')
            ->join(DB::raw("{$limsDb}.order_detail as order_detail"),'order_detail.no_sampel','=','subkontrak.no_sampel')
            ->where('subkontrak.is_approve', $request->approve)
            ->where('subkontrak.is_active', true)
            ->where('subkontrak.category_id',4)
            ->select('subkontrak.*', 'order_detail.tanggal_terima as od_tanggal_terima', 'order_detail.kategori_3 as od_kategori_3', 'order_detail.tanggal_sampling as os_tanggal_sampling')
            ->orderBy('subkontrak.created_at', 'desc');
        
        if ($request->filled('periode')) {
            $periode = explode('-', $request->periode);
            if (count($periode) == 2) {
                $data->whereYear('subkontrak.created_at', $periode[0])
                     ->whereMonth('subkontrak.created_at', $periode[1]);
            }
        }
        return Datatables::of($data)
            ->addColumn('tanggal_terima', function ($item) {
                return $item->od_tanggal_terima ?? '-';
            })
            ->addColumn('kategori_3', function ($item) {
                return $item->od_kategori_3 ?? '-';
            })
            ->addColumn('tanggal_sampling', function ($item) {
                return $item->os_tanggal_sampling ?? '-';
            })
            ->orderColumn('tanggal_terima', function ($query, $order) {
                $query->orderBy('order_detail.tanggal_terima', $order);
            })
            ->orderColumn('created_at', function ($query, $order) {
                $query->orderBy('subkontrak.created_at', $order);
            })
            ->orderColumn('no_sampel', function ($query, $order) {
                $query->orderBy('subkontrak.no_sampel', $order);
            })
            ->filter(function ($query) use ($request) {
                if ($request->has('columns')) {
                    $columns = $request->get('columns');
                    foreach ($columns as $column) {
                        if (isset($column['search']) && !empty($column['search']['value'])) {
                            $columnName = $column['name'] ?: $column['data'];
                            $searchValue = $column['search']['value'];
                            
                            // Skip columns that aren't searchable
                            if (isset($column['searchable']) && $column['searchable'] === 'false') {
                                continue;
                            }
                            
                            // Special handling for date fields
                            if ($columnName === 'tanggal_terima') {
                                $query->where('order_detail.tanggal_terima', 'like', "%{$searchValue}%");
                            } 
                            elseif ($columnName === 'tanggal_sampling') {
                                $query->where('order_detail.tanggal_sampling', 'like', "%{$searchValue}%");
                            }
                            elseif ($columnName === 'kategori_3') {
                                $query->where('order_detail.kategori_3', 'like', "%{$searchValue}%");
                            }
                            // Handle created_at separately if needed
                            elseif ($columnName === 'created_at') {
                                $query->whereDate('subkontrak.created_at', 'like', "%{$searchValue}%");
                            }
                            // Standard text fields
                            elseif (in_array($columnName, [
                                'no_sampel', 'parameter', 'jenis_pengujian'
                            ])) {
                                $query->where("subkontrak.$columnName", 'like', "%{$searchValue}%");
                            }
                        }
                    }
                }
            })
        ->make(true);
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