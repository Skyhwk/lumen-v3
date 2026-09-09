<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Services\DashboardAbsensiKaryawanService;
use Illuminate\Http\Request;

class DashboardAbsensiKaryawanController extends Controller
{
    public function index(Request $request)
    {
        $managerId = $this->resolveManagerId();
        if (!$managerId) {
            return response()->json(['message' => 'Data manager tidak ditemukan.'], 401);
        }

        $period = strtolower((string) $request->input('period', 'weekly'));
        $offset = (int) $request->input('offset', 0);
        $month = $request->input('month');

        return response()->json(
            app(DashboardAbsensiKaryawanService::class)->build($managerId, $period, $offset, $month),
            200
        );
    }

    private function resolveManagerId(): ?int
    {
        $isDevMode = env('APP_ENV') !== 'production' && env('DEV_BYPASS_USER_ID') !== null && env('DEV_BYPASS_USER_ID') !== '';
        $devUserId = env('DEV_BYPASS_USER_ID');

        if ($isDevMode && $devUserId) {
            $devKaryawan = MasterKaryawan::where('id', $devUserId)->first();
            if ($devKaryawan) {
                return (int) $devKaryawan->id;
            }
        }

        return $this->user_id ? (int) $this->user_id : null;
    }
}
