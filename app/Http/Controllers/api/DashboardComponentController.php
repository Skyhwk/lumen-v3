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

protected $fillable = [
    'nama_komponen',
    'nama_dashboard',
    'owner',
    'owner_id',
    'is_active',
    'created_by',
    'updated_by'
];

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

        if (!empty($request->id)) {

            $data = DashboardComponent::find($request->id);

            if (!$data) {
                return response()->json([
                    'message' => 'Data not found'
                ], 404);
            }

            $data->update([
                'nama_komponen' => $request->nama_komponen,
                'nama_dashboard' => $request->nama_dashboard,
                'owner' => $request->owner,
                'owner_id' => $request->owner_id,
                'is_active' => $request->is_active,
                'updated_by' => $this->karyawan,
                'created_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

        } else {

            DashboardComponent::create([
                'nama_komponen' => $request->nama_komponen,
                'nama_dashboard' => $request->nama_dashboard,
                'owner' => $request->owner,
                'owner_id' => $request->owner_id,
                'is_active' => $request->is_active,
                'created_by' => $this->karyawan,
                'updated_by' => $this->karyawan,
            ]);
        }

        return response()->json([
            'message' => 'Success'
        ], 200);

    } catch (\Exception $e) {

        return response()->json([
            'error' => true,
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ], 500);
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