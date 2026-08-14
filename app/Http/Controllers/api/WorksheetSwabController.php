<?php

namespace App\Http\Controllers\api;

use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\SwabTestHeader;
use App\Services\ApproveAnalystService;
use App\Models\WsValueUdara;
use App\Helpers\HelperSatuan;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class WorksheetSwabController extends Controller
{
    public function index(Request $request){
        $data = SwabTestHeader::with('ws_udara', 'order_detail')
            ->where('is_approved', $request->is_approved)
            ->where('is_active', true)
            ->where('template_stp', 22)
            ->orderByRaw("
                CASE 
                    WHEN tanggal_terima IS NULL THEN 1
                    ELSE 0
                END,
                tanggal_terima DESC
            ");
        $satuanUdaraMap = HelperSatuan::getSatuanUdaraMap();

        return Datatables::of($data)
            ->editColumn('data_pershift', function ($data) {
                return $data->data_pershift ? json_decode($data->data_pershift, true) : null;
            })
            ->editColumn('volume_shift', function ($data) {
                return $data->volume_shift ? json_decode($data->volume_shift, true) : null;
            })
            ->editColumn('data_shift', function ($data) {
                return $data->data_shift ? json_decode($data->data_shift, true) : null;
            })
            ->addColumn('tanggal_terima', function ($item) {
                return $item->order_detail->tanggal_terima ?? '-';
            })
            ->addColumn('hasil_dinamis', function ($data) use ($satuanUdaraMap) {
                $hasil = [];
                if ($data->ws_udara) {
                    $wsArray = $data->ws_udara->toArray();
                    foreach ($wsArray as $key => $value) {
                        if (preg_match('/^hasil(\d+)$/', $key, $matches)) {
                            if ($value !== null && $value !== '') {
                                $index = $matches[1];
                                $hasil[] = [
                                    'index'  => (int)$index,
                                    'label'  => 'C' . $index,
                                    'nilai'  => (string)$value,
                                    'satuan' => $satuanUdaraMap[$index] ?? ''
                                ];
                            }
                        }
                    }
                    usort($hasil, function($a, $b) {
                        return $a['index'] <=> $b['index'];
                    });
                }
                return $hasil;
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
                                $query->where("swabtest_header.$columnName", 'like', "%{$searchValue}%");
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
            $data = SwabTestHeader::where('id', $request->id)->where('is_active', true)->first();
            if($data->is_approved == 1){
                return response()->json([
                    'status' => false,
                    'message' => 'Data Swab no sample ' . $data->no_sampel . ' sudah di approve'
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
                'message' => 'Data Swab no sample ' . $data->no_sampel . ' berhasil di approve'
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
            $data = SwabTestHeader::where('id', $request->id)->first();
            $data->is_active = false;
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->deleted_by = $this->karyawan;
            $data->save();

            $ws_udara = WsValueUdara::where('id_swab_header', $request->id)->where('is_active', true)->first();
            if($ws_udara){
                $ws_udara->is_active = false;
                $ws_udara->save();
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Data Swab no sample ' . $data->no_sampel . ' berhasil dihapus .!'
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
