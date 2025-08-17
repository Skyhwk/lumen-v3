<?php

namespace App\Http\Controllers\api;

use App\Models\EmisiCerobongHeader;
use App\Models\OrderDetail;
use App\Models\WsValueEmisiCerobong;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class IcpEmisiCerobongController extends Controller
{
    // public function index(Request $request){
    //     $data = EmisiCerobongHeader::with('ws_value', 'order_detail')
    //     ->where('is_approved', $request->approve)
    //     ->where('is_active', true)
    //     ->where('template_stp', $request->template_stp);
    //     // ->orderBy('id', 'desc');
    //     return Datatables::of($data)->make(true);
    // }

    // 20-03-2025
    public function index(Request $request){
        $data = EmisiCerobongHeader::with('ws_value_one', 'order_detail')
            ->where('is_approved', $request->approve)
            ->where('emisi_cerobong_header.is_active', true)
            ->where('template_stp', $request->template_stp);
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