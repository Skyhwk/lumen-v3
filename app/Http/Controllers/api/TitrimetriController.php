<?php

namespace App\Http\Controllers\api;

use App\Models\Titrimetri;
use App\Models\OrderDetail;
use App\Models\WsValueAir;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\ApproveAnalystService;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class TitrimetriController extends Controller
{
    // public function index(Request $request){
    //     $data = Titrimetri::with('ws_value', 'order_detail')
    //     ->where('is_approved', $request->approve)
    //     ->where('is_active', true)
    //     ->where('template_stp', $request->template_stp);
    //     // ->orderBy('created_at', 'desc');
    //     return Datatables::of($data)->make(true);
    // }

    // 20-03-2025
    public function index(Request $request)
    {
        $data = Titrimetri::with('ws_value', 'order_detail')
            ->where('titrimetri.is_approved', $request->approve)
            ->where('titrimetri.is_active', true)
            ->where('titrimetri.is_total', false)
            ->where('titrimetri.template_stp', $request->template_stp)
            ->select('titrimetri.*')
                ->orderByRaw("
                CASE 
                    WHEN titrimetri.tanggal_terima IS NULL THEN 1
                    ELSE 0
                END,
                titrimetri.tanggal_terima DESC
            ");
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

            ->filterColumn('hasil', function ($query, $keyword) {
                $query->whereHas('ws_value', function ($query) use ($keyword) {
                    $query->where('hasil', 'like', "%{$keyword}%");
                });
            })

            ->filterColumn('ws_value.hasil', function ($query, $keyword) {
                $query->whereHas('ws_value', function ($query) use ($keyword) {
                    $query->where('hasil', 'like', "%{$keyword}%");
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
                                'no_sampel',
                                'parameter',
                                'jenis_pengujian',
                                'approved_by',
                                'approved_at',
                                'created_at',
                                'created_by',
                                'note',
                                'notes_reject'
                            ])) {
                                $query->where("titrimetri.$columnName", 'like', "%{$searchValue}%");
                            }

                        }
                    }
                }
            })
            ->make(true);
    }

    public function approveData(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = Titrimetri::where('id', $request->id)->where('is_active', true)->first();
            if ($data->is_approved == 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data Titrimetri no sample ' . $data->no_sampel . ' sudah di approve'
                ], 401);
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
                'message' => 'Data Titrimetri no sample ' . $data->no_sampel . ' berhasil di approve'
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan! ' . $th->getMessage()
            ], 401);
        }
    }

    public function deleteData(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = Titrimetri::where('id', $request->id)->first();
            $data->is_active = false;
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->deleted_by = $this->karyawan;
            $data->is_retest = 1;
            $data->notes_reject_retest = $request->note;
            $data->save();

            $ws_value = WsValueAir::where('id_Titrimetri', $request->id)->where('is_active', true)->first();
            if ($ws_value) {
                $ws_value->is_active = false;
                $ws_value->save();
            }

            DB::commit();

            return response()->json([
                'status' => true,
                "success" => true,
                'message' => 'Data Titrimetri no sample ' . $data->no_sampel . ' berhasil dihapus .!'
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan! ' . $th->getMessage()
            ], 401);
        }
    }
}