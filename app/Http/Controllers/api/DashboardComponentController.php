<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DashboardComponent;
use App\Models\SetAksesDashboard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use App\Models\MasterKaryawan;
use App\Services\GetBawahan;

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
            $dashboardComponent = DashboardComponent::where('is_active', 1)->get();
            return DataTables::of($dashboardComponent)->make(true);
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function getDashboardList(Request $request)
    {
        try {
            $userHaveAllAccess = $this->user_id === 1 || $this->user_id === 127;
        
            if($userHaveAllAccess) {
                $DashboardComponent = DashboardComponent::where('is_active', 1)->get();
    
                return DataTables::of($DashboardComponent)->make(true);
            } else {
                $DashboardComponent = DashboardComponent::where(function($query) {
                    $query->where('owner_id', '=', $this->user_id)
                          ->orWhereRaw("FIND_IN_SET(?, owner_id)", [$this->user_id]);
                })->where('is_active', 1)->get();
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
                $data = DashboardComponent::find($request->id);

                if (!$data) {
                    return response()->json([
                        'message' => 'Data not found'
                    ], 404);
                }

                $oldDashboardName = $data->nama_dashboard;

                $data->update([
                    'nama_komponen' => $request->nama_komponen,
                    'nama_dashboard' => $request->nama_dashboard,
                    'owner' => $request->owner,
                    'owner_id' => $request->owner_id,
                    'is_active' => $request->is_active ?? 1,
                    'updated_by' => $this->karyawan,
                    'created_by' => $this->karyawan,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

                if (!empty($request->nama_dashboard) && $oldDashboardName !== $request->nama_dashboard) {
                    SetAksesDashboard::where('nama_dashboard', $oldDashboardName)
                        ->whereNull('deleted_at')
                        ->update([
                            'nama_dashboard' => $request->nama_dashboard,
                            'updated_by' => $this->karyawan,
                            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        ]);
                }

            } else {
                DashboardComponent::create([
                    'nama_komponen' => $request->nama_komponen,
                    'nama_dashboard' => $request->nama_dashboard,
                    'owner' => $request->owner,
                    'owner_id' => $request->owner_id,
                    'is_active' => $request->is_active ?? 1,
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
            SetAksesDashboard::where('nama_dashboard', $request->nama_dashboard)->update([
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'deleted_by' => $this->karyawan
            ]);
            DashboardComponent::where('id', $request->id)->update([
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'deleted_by' => $this->karyawan,
                'is_active' => 0
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

    public function getKaryawan(Request $request){
        $data = MasterKaryawan::where('master_karyawan.is_active', true)
                ->leftJoin('akses_menu', 'master_karyawan.user_id', '=', 'akses_menu.user_id')
                ->select('master_karyawan.id', 'master_karyawan.user_id', 'master_karyawan.nama_lengkap')
                ->get();

        return response()->json([
            'message' => 'get data karyawan success.',
            'data' => $data
        ]);
    }

    public function getBawahan(Request $request)
    {
        $loggedInUserId = $this->user_id;

        if ($loggedInUserId == 1 || $loggedInUserId == 127) {
            $data = MasterKaryawan::where('master_karyawan.is_active', 1)
                    ->leftJoin('akses_menu', 'master_karyawan.user_id', '=', 'akses_menu.user_id')
                    ->select('master_karyawan.id', 'master_karyawan.user_id', 'master_karyawan.nama_lengkap')
                    ->get();
        } else {
            $owner_ids = explode(',', $request->owner_id);

            if (in_array((string)$loggedInUserId, $owner_ids) || in_array((int)$loggedInUserId, $owner_ids)) {
                $target_owner_ids = [$loggedInUserId];
            } else {
                $target_owner_ids = $owner_ids;
            }

            $allSubordinates = collect([]);

            foreach ($target_owner_ids as $owner_id) {
                if (!empty($owner_id)) {
                    $sub = GetBawahan::where('id', $owner_id)->get();
                    $allSubordinates = $allSubordinates->merge($sub);
                }
            }

            $subordinateUserIds = $allSubordinates->pluck('user_id')->unique()->toArray();
            $managerUserIds = MasterKaryawan::whereIn('id', $target_owner_ids)->pluck('user_id')->toArray();
            $subordinates = array_diff($subordinateUserIds, $managerUserIds);

            $data = MasterKaryawan::whereIn('master_karyawan.user_id', $subordinates)->where('master_karyawan.is_active', 1)
                    ->leftJoin('akses_menu', 'master_karyawan.user_id', '=', 'akses_menu.user_id')
                    ->select('master_karyawan.id', 'master_karyawan.user_id', 'master_karyawan.nama_lengkap')
                    ->get();
        }

        return response()->json([
            'message' => 'get data bawahan success.',
            'data' => $data
        ]); 
    }
}