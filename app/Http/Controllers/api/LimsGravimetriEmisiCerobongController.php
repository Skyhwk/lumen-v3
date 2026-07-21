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

class LimsGravimetriEmisiCerobongController extends Controller
{

    // 20-03-2025
    public function index(Request $request){
        $limsDb = DB::connection('lims')->getDatabaseName();

        $data = EmisiCerobongHeader::with('ws_value')
            ->join(DB::raw("{$limsDb}.order_detail as order_detail"),'order_detail.no_sampel','=','emisi_cerobong_header.no_sampel')
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
            ->orderColumn('tanggal_terima', function ($query, $order) {
                $query->orderBy('tanggal_terima', $order);
            })
            ->orderColumn('created_at', function ($query, $order) {
                $query->orderBy('created_at', $order);
            })
            ->orderColumn('no_sampel', function ($query, $order) {
                $query->orderBy('no_sampel', $order);
            })
            ->editColumn('data_analis', function($item){
                return json_decode($item->data_analis, true) ?? [];
            })
            ->addColumn('tanggal_terima', function($item){
                return $item->od_tanggal_terima ?? $item->tanggal_terima;
            })
            ->addColumn('kategori_3', function($item){
                return $item->od_kategori_3 ?? $item->kategori_3;
            })
            ->addColumn('tanggal_sampling', function($item){
                return $item->od_tanggal_sampling ?? $item->tanggal_sampling;
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
                                // Assuming the search value is a date or part of a date
                                $query->whereDate('tanggal_terima', 'like', "%{$searchValue}%");
                            } 
                            // Handle created_at separately if needed
                            elseif ($columnName === 'created_at') {
                                $query->whereDate('created_at', 'like', "%{$searchValue}%");
                            }
                            // Standard text fields
                            elseif (in_array($columnName, [
                                'no_sampel', 'parameter', 'jenis_pengujian'
                            ])) {
                                $query->where($columnName, 'like', "%{$searchValue}%");
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
