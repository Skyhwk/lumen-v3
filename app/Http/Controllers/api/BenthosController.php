<?php

namespace App\Http\Controllers\api;


use App\Models\Colorimetri;
use App\Models\WsValueAir;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class BenthosController extends Controller
{

    public function index(Request $request)
    {
        // dd($request->all());
        $data = Colorimetri::with('ws_value')->where('parameter', 'Benthos')
            ->where('is_active', true)
            ->where('is_approved', $request->approve)
            ->orderBy('created_at', 'desc');
        return Datatables::of($data)
            ->addColumn('hasil', function ($data) {
                return $data->ws_value ? [json_decode($data->ws_value->hasil)]  : null;
            })
            ->removeColumn('ws_value')
            ->make(true);
    }



    public function approveSampel(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = Colorimetri::where('id', $request->id)->where('is_active', true)->first();
            if ($data->is_approved == 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data Benthos dengan no sample ' . $data->no_sampel . ' sudah di approve'
                ], 401);
            }
            $data->is_approved = 1;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->approved_by = $this->karyawan;
            $data->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Data Benthos dengan no sample ' . $data->no_sampel . ' berhasil di approve'
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan! ' . $th->getMessage()
            ], 401);
        }
    }

    public function rejectSampel(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = Colorimetri::where('id', $request->id)->where('is_active', true)->first();
            $data->is_active = false;
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->deleted_by = $this->karyawan;
            $data->notes_reject_retest = $request->note;
            $data->is_retest = true;
            $data->save();

            WsValueAir::where('id_colorimetri', $data->id)->update([
                'is_active' => false
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Success Reject Data Benthos Dengan No Sampel ' . $request->no_sampel,
                'success' => true
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed Process data' . $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

}