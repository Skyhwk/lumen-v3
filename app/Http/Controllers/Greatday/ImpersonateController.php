<?php

namespace App\Http\Controllers\Greatday;

use App\Models\MasterKaryawan;
use App\Services\Greatday\GreatdayImpersonationService;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller;

class ImpersonateController extends Controller
{
    public function search(Request $request)
    {
        $tokenRow = $request->attributes->get('greatday_token');
        $service = app(GreatdayImpersonationService::class);

        $actorId = $service->resolveActorKaryawanId($tokenRow);
        if (!$actorId || !$service->canImpersonate($actorId)) {
            return response()->json(['message' => 'Anda tidak memiliki akses impersonate'], 403);
        }

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []], 200);
        }

        $parts = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [$q];

        $builder = MasterKaryawan::query()->where('is_active', 1);

        foreach ($parts as $part) {
            $like = '%' . $part . '%';
            $builder->where(function ($query) use ($like) {
                $query->where('nama_lengkap', 'like', $like)
                    ->orWhere('nik_karyawan', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $rows = $builder
            ->orderBy('nama_lengkap')
            ->limit(25)
            ->get(['id', 'nama_lengkap', 'email', 'nik_karyawan', 'image', 'grade']);

        $data = $rows->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'name' => $row->nama_lengkap,
                'email' => $row->email,
                'nik_karyawan' => $row->nik_karyawan,
                'avatar' => $row->image,
                'grade' => $row->grade,
            ];
        })->values();

        return response()->json(['data' => $data], 200);
    }

    public function start(Request $request)
    {
        $rawToken = $request->bearerToken();
        if (!$rawToken) {
            return response()->json(['message' => 'Token not provided'], 403);
        }

        $targetId = (int) $request->input('karyawan_id');
        if ($targetId <= 0) {
            return response()->json(['message' => 'karyawan_id wajib diisi'], 422);
        }

        $requestedImpersonator = (int) $request->input('impersonator_karyawan_id');
        $requestedImpersonator = $requestedImpersonator > 0 ? $requestedImpersonator : null;

        $service = app(GreatdayImpersonationService::class);
        $result = $service->startImpersonation($rawToken, $targetId, $requestedImpersonator);

        if (!$result['ok']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'message' => $result['message'],
            'impersonator_karyawan_id' => $result['impersonator_karyawan_id'],
        ], 200);
    }

    public function stop(Request $request)
    {
        $rawToken = $request->bearerToken();
        if (!$rawToken) {
            return response()->json(['message' => 'Token not provided'], 403);
        }

        $requestedImpersonator = (int) $request->input('impersonator_karyawan_id');
        $requestedImpersonator = $requestedImpersonator > 0 ? $requestedImpersonator : null;

        $service = app(GreatdayImpersonationService::class);
        $result = $service->stopImpersonation($rawToken, $requestedImpersonator);

        if (!$result['ok']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['message' => $result['message']], 200);
    }
}
