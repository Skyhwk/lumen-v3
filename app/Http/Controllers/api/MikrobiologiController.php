<?php

namespace App\Http\Controllers\api;

use App\Models\{ Colorimetri, WsValueAir };
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class MikrobiologiController extends Controller
{

    public function index(Request $request)
    {
        $data = Colorimetri::with('ws_value', 'order_detail')
            ->where('is_approved', $request->approve)
            ->where('is_active', true)
            ->where('is_total', false)
            ->where('template_stp', $request->template_stp)
            ->select('colorimetri.*', 'order_detail.tanggal_terima', 'order_detail.no_sampel','order_detail.kategori_3');
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
            ->orderColumn('tanggal_terima', function ($query, $order) {
                $query->orderBy('order_detail.tanggal_terima', $order);
            })
            ->orderColumn('created_at', function ($query, $order) {
                $query->orderBy('colorimetri.created_at', $order);
            })
            ->orderColumn('no_sampel', function ($query, $order) {
                $query->orderBy('order_detail.no_sampel', $order);
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
                                'no_sampel',
                                'parameter',
                                'jenis_pengujian'
                            ])) {
                                $query->where($columnName, 'like', "%{$searchValue}%");
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
