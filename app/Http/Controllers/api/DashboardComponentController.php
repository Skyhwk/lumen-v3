<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DashboardComponent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;

Carbon::setLocale('id');

class DashboardComponentController extends Controller
{
    public function index(Request $request)
    {
        try {
            //code...
            $dashboardComponent = DashboardComponent::where('is_active', 1)->get();
            return DataTables::of($dashboardComponent)->make(true);
        } catch (\Throwable $th) {
            dd($th);
        }
    }
    
    public function store(Request $request)
    {
        try {
            if ($request->id == null || $request->id == '') {
                DashboardComponent::create([
                    'nama_komponen' => $request->nama_komponen,
                    'nama_dashboard' => $request->nama_dashboard,
                    'owner' => $request->owner,
                    'owner_id' => $request->owner_id,
                    'is_active' => $request->is_active,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_by' => $this->karyawan,
                ]);

            } else if ($request->id != null || $request->id != '') {
                DashboardComponent::where('id', $request->id)->update([
                    'nama_komponen' => $request->nama_komponen,
                    'nama_dashboard' => $request->nama_dashboard,
                    'owner' => $request->owner,
                    'owner_id' => $request->owner_id,
                    'is_active' => $request->is_active,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_by' => $this->karyawan,
                ]);
            }

            return response ()->json([
                'message' => 'Komponen berhasil disimpan.',
                'status' => '200'
            ], 200);

            
           
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 401);
        }
    }

      public function delete(Request $request)
    {
        try {
            DashboardComponent::where('id', $request->id)->update([
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'deleted_by' => $this->karyawan
            ]);
            return response()->json([
                'message' => 'Komponen berhasil dihapus.',
                'status' => '200'
            ], 200);
        } catch (\Exception $e) {
            \Log::error($e);

            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}