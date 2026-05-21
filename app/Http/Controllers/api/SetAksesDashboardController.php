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
            $userHaveAllAccess = $this->user_id === 1 || $this->user_id === 127 || $this->user_id === 152;
        
            if($userHaveAllAccess) {
                $DashboardComponent = DashboardComponent::where('is_active', 1)->get();
            } else {
                $DashboardComponent = DashboardComponent::where('owner_id', '=', $this->user_id)->where('is_active', 1)->get();
            }

            $DashboardComponent->transform(function($component) {
                $akses = SetAksesDashboard::where('nama_dashboard', $component->nama_dashboard)->whereNull('deleted_at')->first();
                $component->user_list = $akses ? $akses->user_list : [];
                return $component;
            });

            return DataTables::of($DashboardComponent)->make(true);
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function getDashboardByUser(Request $request)
    {
        try {
            // dd($this->karyawan);
            $dashboard = SetAksesDashboard::whereJsonContains(
                'user_list',
                $this->karyawan
            )->whereNull('deleted_at')->get();

            $dashboard->transform(function($item) {
                $component = DashboardComponent::where('nama_dashboard', $item->nama_dashboard)->where('is_active', 1)->first();
                $item->nama_komponen = $component ? $component->nama_komponen : null;
                return $item;
            });

            if ($dashboard) {
                return response()->json([
                    'data' => $dashboard
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Dashboard not found',
                    'status' => '404'
                ], 404);
            }
        } catch (\Exception $e) {
            \Log::error($e);

            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
    
    public function store(Request $request)
    {
        try {
            $dashboardIsExist = SetAksesDashboard::where('nama_dashboard', $request->nama_dashboard)->first() ?? null;

            if ($dashboardIsExist) {
                SetAksesDashboard::where('nama_dashboard', $dashboardIsExist->nama_dashboard)->update([
                    'user_list' => $request->user_list,
                    'updated_by' => $this->karyawan,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'deleted_at' => null,
                    'deleted_by' => null,
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
            SetAksesDashboard::where('nama_dashboard', $request->nama_dashboard)->whereNull('deleted_at')->update([
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