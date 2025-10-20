<?php

namespace App\Http\Controllers\api;

use App\Models\MasterFeeDriver;
use App\Models\MasterDriver;
use App\Models\FeeKaryawan;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Models\MasterFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;




class FeeDriverController extends Controller
{
    
    public function index()
    {
        $data = MasterFeeDriver::with('driver')->where('is_active', true)->whereHas('driver');

        return Datatables::of($data)->make(true);
    }

    public function getDetails(Request $request)
    {
        $data = MasterFeeDriver::where('driver_id', $request->driver_id)->orderBy('created_at', 'desc');

        return Datatables::of($data)->make(true);
    }
    
    public function getListDriver()
    {
        $drivers = MasterDriver::with('fee')->where('is_active', true)->whereDoesntHave('fee')->get();

        return response()->json([
            'success' => true,
            'data' => $drivers,
            'message' => 'Available driver data retrieved successfully',
        ], 201);
    }

    public function storeOrUpdate(Request $request)
    {
        try {
            DB::beginTransaction();
            MasterFeeDriver::where('driver_id', $request->driver_id)->update([
                'is_active' => false,
            ]);

            MasterFeeDriver::create([
                'driver_id' => $request->driver_id,
                'fee' => $request->fee,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now(),
                'is_active' => true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully store data',
            ]);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            DB::beginTransaction();
            MasterFeeDriver::where('id', $request->id)->update([
                'is_active' => false,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Successfully delete data',
            ]);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}