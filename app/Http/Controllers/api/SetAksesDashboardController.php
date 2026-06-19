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
            $userHaveAllAccess = $this->user_id === 1 || $this->user_id === 127 || $this->user_id === 152 || $this->user_id === 1010;
        
            if($userHaveAllAccess) {
                $DashboardComponent = DashboardComponent::where('is_active', 1)->get();
            } else {
                $DashboardComponent = DashboardComponent::where(function($query) {
                    $query->where('owner_id', '=', $this->user_id)
                          ->orWhereRaw("FIND_IN_SET(?, owner_id)", [$this->user_id]);
                })->where('is_active', 1)->get();
            }

            $DashboardComponent->transform(function($component) {
                $akses = SetAksesDashboard::where('nama_dashboard', $component->nama_dashboard)->whereNull('deleted_at')->first();
                $component->user_list = $akses ? $akses->user_list : [];
                $userVisibility = $akses ? $akses->user_visibility : null;
                $userId = $this->user_id;
                $isVisible = true;
                if (is_array($userVisibility) && isset($userVisibility[$userId])) {
                    $isVisible = (bool)$userVisibility[$userId];
                }
                $component->user_visibility_status = $isVisible;
                
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
            $userId = $this->user_id;

            $dashboardOwner = DashboardComponent::where('is_active', 1)->where(function($query) {
                $query->where('owner_id', $this->user_id)
                      ->orWhereRaw("FIND_IN_SET(?, owner_id)", [$this->user_id]);
            })->get();

            $dashboardAccess = SetAksesDashboard::whereJsonContains(
                'user_list',
                $this->karyawan
            )->whereNull('deleted_at')->get();

            $dashboardAccess->transform(function($item) {
                $component = DashboardComponent::where('nama_dashboard', $item->nama_dashboard)->first();
                $item->nama_komponen = $component ? $component->nama_komponen : null;
                return $item;
            });

            $dashboard = $dashboardOwner->merge($dashboardAccess)->unique('nama_dashboard')->values();

            // Filter out components where user_visibility for this user is false
            $dashboard = $dashboard->filter(function($item) use ($userId) {
                $userVisibility = null;
                if ($item instanceof SetAksesDashboard) {
                    $userVisibility = $item->user_visibility;
                } else {
                    $akses = SetAksesDashboard::where('nama_dashboard', $item->nama_dashboard)->whereNull('deleted_at')->first();
                    $userVisibility = $akses ? $akses->user_visibility : null;
                }
                
                if (is_array($userVisibility) && isset($userVisibility[$userId])) {
                    return (bool)$userVisibility[$userId] !== false;
                }
                return true;
            })->values();

             return response()->json([
                'data' => $dashboard
            ], 200);

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

    public function toggleVisibility(Request $request)
    {
        try {
            $namaDashboard = $request->nama_dashboard;
            $visible = filter_var($request->visible, FILTER_VALIDATE_BOOLEAN);
            $userId = $this->user_id;

            $akses = SetAksesDashboard::where('nama_dashboard', $namaDashboard)->whereNull('deleted_at')->first();

            if (!$akses) {
                $akses = SetAksesDashboard::create([
                    'nama_dashboard' => $namaDashboard,
                    'user_list' => [],
                    'created_by' => $this->karyawan,
                    'updated_by' => $this->karyawan,
                ]);
            }

            $userVisibility = $akses->user_visibility ?? [];
            if (!is_array($userVisibility)) {
                $userVisibility = [];
            }

            $userVisibility[$userId] = $visible;

            $akses->update([
                'user_visibility' => $userVisibility,
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            return response()->json([
                'message' => 'Status visibilitas berhasil diperbarui.',
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