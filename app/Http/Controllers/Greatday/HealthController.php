<?php

namespace App\Http\Controllers\Greatday;

use Illuminate\Http\Request;

class HealthController extends Controller
{
    /** Slice pilot Phase 0: HealthController + ping */
    public function ping(Request $request)
    {
        $employee = $this->karyawan;

        return response()->json([
            'message' => 'greatday ok',
            'data' => [
                'employee_id' => $employee ? $employee->id : null,
                'name' => $employee ? $employee->nama_lengkap : null,
                'path' => $request->path(),
            ],
        ], 200);
    }
}
