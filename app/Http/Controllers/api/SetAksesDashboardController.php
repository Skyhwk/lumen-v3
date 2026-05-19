<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\SetAksesDashboard;
use App\Models\DashboardComponent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;

Carbon::setLocale('id');

class SetAksesDashboardController extends Controller
{

protected $fillable = [
    'nama_dashboard',
    'user_list'
];

    public function index(Request $request)
    {
        try {
            $userHaveAllAccess = $this->user_id === 1 || $this->user_id === 127;
        
            if($userHaveAllAccess) {
                $DashboardComponent = DashboardComponent::where('is_active', 1)->get();
                $DashboardComponent = DashboardComponent::where('is_active', 1)->get();
                return DataTables::of($DashboardComponent)->make(true);
            } else {
                $DashboardComponent = DashboardComponent::where('owner_id', '=', $this->user_id)->where('is_active', 1)->get();
                return DataTables::of($DashboardComponent)->make(true);
            }
        } catch (\Throwable $th) {
            dd($th);
        }
    }
    
    public function store(Request $request)
{
    try {

        if (!empty($request->id)) {

            $data = SetAksesDashboard::find($request->id);

            if (!$data) {
                return response()->json([
                    'message' => 'Data not found'
                ], 404);
            }

            $data->update([
                'nama_dashboard' => $request->nama_dashboard,
                'user_list' => $request->user_list,
                'updated_by' => $this->karyawan,
                'created_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

        } else {
            SetAksesDashboard::create([
                'nama_dashboard' => $request->nama_dashboard,
                'user_list' => $request->user_list,
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
            SetAksesDashboard::where('id', $request->id)->update([
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