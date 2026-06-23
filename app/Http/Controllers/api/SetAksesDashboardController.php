<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DashboardComponent;
use App\Models\DashboardUserOrder;
use App\Models\SetAksesDashboard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Yajra\Datatables\Datatables;

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

            if ($userHaveAllAccess) {
                $DashboardComponent = DashboardComponent::where('is_active', 1)->orderBy('id', 'asc')->get();
            } else {
                $DashboardComponent = DashboardComponent::where(function ($query) {
                    $query->where('owner_id', '=', $this->user_id)
                        ->orWhereRaw("FIND_IN_SET(?, owner_id)", [$this->user_id]);
                })->where('is_active', 1)->orderBy('id', 'asc')->get();
            }

            $DashboardComponent->transform(function ($component) {
                $akses = $this->findAccessByComponent($component);
                $component->dashboard_id = $component->id;
                $component->user_list = $akses ? $akses->user_list : [];
                $component->user_visibility = $this->hasUserVisibilityColumn() && $akses ? ($akses->user_visibility ?? []) : [];
                $component->user_visibility_status = $this->resolveVisibilityStatus($akses);

                return $component;
            });

            return DataTables::of($DashboardComponent)->make(true);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    public function getDashboardByUser(Request $request)
    {
        try {
            $dashboardOwner = DashboardComponent::where('is_active', 1)->where(function ($query) {
                $query->where('owner_id', $this->user_id)
                    ->orWhereRaw("FIND_IN_SET(?, owner_id)", [$this->user_id]);
            })->get();

            $dashboardOwner->transform(function ($component) {
                $component->dashboard_component_id = $component->id;
                $component->user_list = [];
                $component->user_visibility_status = true;

                return $component;
            });

            $dashboardAccess = SetAksesDashboard::whereJsonContains('user_list', $this->karyawan)
                ->whereNull('deleted_at')
                ->get();

            $dashboardAccess->transform(function ($item) {
                $component = $this->findComponentByAccess($item);
                $item->dashboard_component_id = $component ? $component->id : ($item->id_dashboard_component ?? $item->id);
                $item->nama_komponen = $component ? $component->nama_komponen : null;
                $item->nama_dashboard = $component ? $component->nama_dashboard : $item->nama_dashboard;
                $item->owner_id = $component ? $component->owner_id : ($item->owner_id ?? null);
                $item->user_visibility_status = $this->resolveVisibilityStatus($item);

                return $item;
            });

            $dashboard = $dashboardOwner
                ->merge($dashboardAccess)
                ->filter(function ($item) {
                    return !empty($item->nama_komponen) && $item->user_visibility_status !== false;
                })
                ->unique('dashboard_component_id')
                ->values();

            return response()->json([
                'data' => $this->applyDashboardOrder($dashboard)
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

    public function store(Request $request)
    {
        try {
            $component = DashboardComponent::where('id', $request->dashboard_id)->first();
            if (!$component && !empty($request->nama_dashboard)) {
                $component = DashboardComponent::where('nama_dashboard', $request->nama_dashboard)
                    ->where('is_active', 1)
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if (!$component) {
                return response()->json([
                    'message' => 'Dashboard component not found'
                ], 404);
            }

            $query = SetAksesDashboard::query();
            if (Schema::hasColumn('set_akses_dashboard', 'id_dashboard_component')) {
                $query->where('id_dashboard_component', $component->id);
            } else {
                $query->where('nama_dashboard', $component->nama_dashboard);
            }

            $dashboardIsExist = $query->first();

            $payload = [
                'nama_dashboard' => $component->nama_dashboard,
                'user_list' => $request->user_list ?? [],
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'deleted_at' => null,
                'deleted_by' => null,
            ];

            if (Schema::hasColumn('set_akses_dashboard', 'id_dashboard_component')) {
                $payload['id_dashboard_component'] = $component->id;
            }

            if ($dashboardIsExist) {
                $dashboardIsExist->update($payload);
            } else {
                $payload['created_by'] = $this->karyawan;
                $payload['created_at'] = Carbon::now()->format('Y-m-d H:i:s');
                SetAksesDashboard::create($payload);
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

    public function toggleVisibility(Request $request)
    {
        try {
            $component = DashboardComponent::where('id', $request->dashboard_id)->first();
            if (!$component && !empty($request->nama_dashboard)) {
                $component = DashboardComponent::where('nama_dashboard', $request->nama_dashboard)
                    ->where('is_active', 1)
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if (!$component) {
                return response()->json([
                    'message' => 'Dashboard component not found'
                ], 404);
            }

            $akses = $this->findAccessByComponent($component);
            if (!$akses) {
                return response()->json([
                    'message' => 'Dashboard access not found'
                ], 404);
            }

            if (!$this->hasUserVisibilityColumn()) {
                return response()->json([
                    'message' => 'Kolom user_visibility belum tersedia.'
                ], 422);
            }

            $visibility = $akses->user_visibility ?? [];
            $visibility[(string) $this->user_id] = filter_var($request->visible, FILTER_VALIDATE_BOOLEAN);

            $akses->update([
                'user_visibility' => $visibility,
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            return response()->json([
                'message' => 'Visibility dashboard berhasil diperbarui.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function saveUserOrder(Request $request)
    {
        try {
            $dashboardOrder = $request->dashboard_order ?? [];
            if (!is_array($dashboardOrder)) {
                return response()->json([
                    'message' => 'dashboard_order harus berupa array'
                ], 422);
            }

            if (!Schema::hasTable('dashboard_user_orders')) {
                return response()->json([
                    'message' => 'Tabel dashboard_user_orders belum tersedia.'
                ], 422);
            }

            $dashboardOrder = array_values(array_unique(array_map('intval', $dashboardOrder)));

            DashboardUserOrder::updateOrCreate(
                ['user_id' => $this->user_id],
                [
                    'user_name' => $this->karyawan,
                    'dashboard_order' => $dashboardOrder,
                    'updated_by' => $this->karyawan,
                    'created_by' => $this->karyawan,
                ]
            );

            return response()->json([
                'message' => 'Urutan dashboard berhasil disimpan.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            $component = DashboardComponent::where('id', $request->dashboard_id ?? $request->id)->first();
            $query = SetAksesDashboard::whereNull('deleted_at');

            if ($component && Schema::hasColumn('set_akses_dashboard', 'id_dashboard_component')) {
                $query->where('id_dashboard_component', $component->id);
            } else {
                $query->where('nama_dashboard', $request->nama_dashboard);
            }

            $query->update([
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

    private function findAccessByComponent($component)
    {
        $query = SetAksesDashboard::whereNull('deleted_at');

        if (Schema::hasColumn('set_akses_dashboard', 'id_dashboard_component')) {
            $akses = (clone $query)->where('id_dashboard_component', $component->id)->first();
            if ($akses) {
                return $akses;
            }
        }

        return $query->where('nama_dashboard', $component->nama_dashboard)->first();
    }

    private function findComponentByAccess($akses)
    {
        if (!empty($akses->id_dashboard_component)) {
            $component = DashboardComponent::where('id', $akses->id_dashboard_component)
                ->where('is_active', 1)
                ->first();

            if ($component) {
                return $component;
            }
        }

        return DashboardComponent::where('nama_dashboard', $akses->nama_dashboard)
            ->where('is_active', 1)
            ->orderBy('id', 'desc')
            ->first();
    }

    private function resolveVisibilityStatus($akses)
    {
        if (!$akses) {
            return true;
        }

        if (!$this->hasUserVisibilityColumn()) {
            return true;
        }

        $visibility = $akses->user_visibility ?? [];
        $key = (string) $this->user_id;

        return array_key_exists($key, $visibility) ? (bool) $visibility[$key] : true;
    }

    private function hasUserVisibilityColumn()
    {
        return Schema::hasColumn('set_akses_dashboard', 'user_visibility');
    }

    private function applyDashboardOrder($dashboard)
    {
        if (!Schema::hasTable('dashboard_user_orders')) {
            return $dashboard->sortBy(function ($item) {
                $dashboardId = (int) ($item->dashboard_component_id ?? $item->id_dashboard_component ?? $item->id);

                return sprintf('%06d-%06d', $this->getDefaultDashboardOrder($item), $dashboardId);
            })->values()->map(function ($item, $index) {
                $item->sort_order = $index;

                return $item;
            });
        }

        $savedOrder = DashboardUserOrder::where('user_id', $this->user_id)->first();
        $order = $savedOrder ? ($savedOrder->dashboard_order ?? []) : [];
        $orderMap = array_flip(array_map('intval', $order));

        return $dashboard->sortBy(function ($item) use ($orderMap) {
            $dashboardId = (int) ($item->dashboard_component_id ?? $item->id_dashboard_component ?? $item->id);
            $savedOrderIndex = array_key_exists($dashboardId, $orderMap) ? $orderMap[$dashboardId] : null;
            $defaultOrderIndex = $this->getDefaultDashboardOrder($item);

            return sprintf('%06d-%06d', $savedOrderIndex ?? $defaultOrderIndex, $dashboardId);
        })->values()->map(function ($item, $index) {
            $item->sort_order = $index;

            return $item;
        });
    }

    private function getDefaultDashboardOrder($item)
    {
        $defaultOrder = [
            'DashboardSales' => 1,
            'DashboardAdmSampling' => 2,
            'DashboardStaffTc' => 3,
            'DashboardAnalist' => 4,
            'DashboardHRD' => 5,
        ];

        return $defaultOrder[$item->nama_komponen ?? ''] ?? 999999;
    }
}
