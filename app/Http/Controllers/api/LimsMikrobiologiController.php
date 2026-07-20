<?php

namespace App\Http\Controllers\api;

use App\Models\{ Colorimetri, WsValueAir };
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\ApproveAnalystService;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class LimsMikrobiologiController extends Controller
{

    public function index(Request $request)
    {
        $limsDb = DB::connection('lims')->getDatabaseName();

        $data = Colorimetri::with('ws_value')
            ->join(DB::raw("{$limsDb}.order_detail as order_detail"),'order_detail.no_sampel','=','colorimetri.no_sampel')
            ->where('colorimetri.is_approved', $request->approve)
            ->where('colorimetri.is_active', true)
            ->where('colorimetri.is_total', false)
            ->where('colorimetri.template_stp', $request->template_stp)
            ->select('colorimetri.*')
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
                $data->whereYear('colorimetri.created_at', $periode[0])
                     ->whereMonth('colorimetri.created_at', $periode[1]);
            }
        }
        return Datatables::of($data)
            ->addColumn('hasil', function ($row) {
                $hasil = optional($row->ws_value)->hasil ?? null;

                // Cek apakah hasil berupa JSON (dimulai dengan '{' atau '[')
                if (is_string($hasil) && (strpos(trim($hasil), '{') === 0 || strpos(trim($hasil), '[') === 0)) {
                    $decoded = json_decode($hasil, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        return $decoded;
                    }
                }

                return $hasil;
            })
            ->addColumn('tanggal_terima', function ($item) {
                return $item->order_detail->tanggal_terima ?? '-';
            })

            ->addColumn('kategori_3', function ($item) {
                return $item->order_detail->kategori_3 ?? '-';
            })

            ->addColumn('tanggal_sampling', function ($item) {
                return $item->order_detail->tanggal_sampling ?? '-';
            })

            ->filterColumn('tanggal_terima', function ($query, $keyword) {
                $query->where('order_detail.tanggal_terima', 'like', "%{$keyword}%");
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
                                $query->where("colorimetri.$columnName", 'like', "%{$searchValue}%");
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
            $data = Colorimetri::where('id', $request->id)->where('is_active', true)->first();
            if ($data->is_approved == 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data microbiologi no sample ' . $data->no_sampel . ' sudah di approve'
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
                'message' => 'Data microbiologi no sample ' . $data->no_sampel . ' berhasil di approve'
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
            $data = Colorimetri::where('id', $request->id)->first();
            $data->is_active = false;
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->deleted_by = $this->karyawan;
            $data->is_retest = 1;
            $data->notes_reject_retest  = $request->note;
            $data->save();

            $ws_value = WsValueAir::where('id_colorimetri', $request->id)->where('is_active', true)->first();
            if ($ws_value) {
                $ws_value->is_active = false;
                $ws_value->save();
            }

            DB::commit();

            return response()->json([
                'status' => true,
                "success" => true,
                'message' => 'Data colorimetri no sample ' . $data->no_sampel . ' berhasil dihapus .!'
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
