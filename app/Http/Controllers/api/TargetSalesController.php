<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use DataTables;
use Carbon\Carbon;

Carbon::setLocale('id');

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\TargetSales;
use App\Models\MasterKaryawan;

class TargetSalesController extends Controller
{
    public function index(Request $request)
    {
        $targetSales = TargetSales::with('sales')
            ->where('year', $request->year)
            ->where('is_active', true)
            ->latest();

        return DataTables::of($targetSales)->make(true);
    }

    public function getAllSales()
    {
        $sales = MasterKaryawan::where('is_active', true)
            ->whereIn('id_jabatan', [24, 21]) // STAFF & SPV
            ->orWhere('id', 41) // Novva Novita Ayu Putri Rukmana 
            ->orderBy('nama_lengkap', 'asc')
            ->get();

        return response()->json($sales, 200);
    }

    public function save(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = [
                'user_id' => $request->user_id,
                'year' => $request->year,
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];

            foreach (['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'] as $month) {
                if ($request->filled($month)) {
                    $data[$month] = str_replace(',', '', $request->$month);
                }
            }

            TargetSales::updateOrCreate(
                ['id' => $request->id ?? null],
                $data + [
                    'created_by' => $request->id ? TargetSales::find($request->id)->created_by : $this->karyawan,
                    'created_at' => $request->id ? TargetSales::find($request->id)->created_at : Carbon::now()->format('Y-m-d H:i:s'),
                ]
            );

            DB::commit();
            return response()->json(['message' => 'Saved Successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

    public function destroy(Request $request)
    {
        DB::beginTransaction();
        try {
            $targetSales = TargetSales::find($request->id);

            $targetSales->deleted_by = $this->karyawan;
            $targetSales->is_active = false;

            $targetSales->save();

            $targetSales->delete();

            DB::commit();
            return response()->json(['message' => 'Deleted Successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()]);
        }
    }
}
