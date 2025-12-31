<?php

namespace App\Http\Controllers\api;

use App\Models\LimitWithdraw;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;



class LimitWithdrawController extends Controller
{
    public function index()
    {
        $data = LimitWithdraw::where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingKaryawan = LimitWithdraw::where('is_active', true)->pluck('user_id')->toArray();

        $karyawan = MasterKaryawan::where('is_active', true)
            ->whereIn('id_jabatan', [
                24, // Sales Officer
                148, // Customer Relation Officer
            ])
            ->orWhere('nama_lengkap', 'Novva Novita Ayu Putri Rukmana')
            ->whereNotIn('id', $existingKaryawan)
            ->select('id', 'nama_lengkap')
            ->orderBy('nama_lengkap', 'asc')
            ->get();
        
            return response()->json([
                'success' => true,
                'data' => $karyawan,
                'message' => 'Available karyawan data retrieved successfully',
            ], 201);
    }

    public function getAllKaryawan()
    {
        $karyawan = MasterKaryawan::where('is_active', true)
            ->select('id', 'nama_lengkap')
            ->get();
        
            return response()->json([
                'success' => true,
                'data' => $karyawan,
                'message' => 'Available karyawan data retrieved successfully',
            ], 201);
    }

    public function delete(Request $request){
        try {
        $limitWd = LimitWithdraw::findOrFail($request->id);
        $limitWd->is_active = false;
        $limitWd->deleted_at = Carbon::now();
        $limitWd->deleted_by = $this->karyawan;
        $limitWd->save();

        return response()->json([
            'success' => true,
            'message' => 'data Limit Withdraw deleted successfully'
        ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try{
            if (strlen(str_replace(['Rp', '.', ','], '', $request->limit)) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal Terlalu Besar',
                ], 401);
            }
            $existingKaryawan = LimitWithdraw::where('is_active', true)->pluck('user_id')->toArray();

            $LimitWithdraw = new LimitWithdraw();
            $LimitWithdraw->limit = str_replace(['Rp', '.', ','], '', $request->limit);
            $LimitWithdraw->created_by = $this->karyawan;
            $LimitWithdraw->created_at = Carbon::now();

            if($request->id && in_array($request->user_id, $existingKaryawan)) {
                $oldLimitWithdraw = LimitWithdraw::findorFail($request->id);
                $oldLimitWithdraw->updated_at = Carbon::now();
                $oldLimitWithdraw->updated_by = $this->karyawan;
                $oldLimitWithdraw->is_active = false;
                $oldLimitWithdraw->save();

                $LimitWithdraw->nama = $oldLimitWithdraw->nama; 
                $LimitWithdraw->user_id = $oldLimitWithdraw->user_id;

                $message = 'LimitWithdraw data updated successfully';

            } else {
                $karyawan = MasterKaryawan::where('id', $request->user_id)->first();
                $LimitWithdraw->user_id = $karyawan->id;
                $LimitWithdraw->nama = $karyawan->nama_lengkap; 

                $message = 'LimitWithdraw data inserted successfully';
                
            }

            $LimitWithdraw->save();

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 201);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}