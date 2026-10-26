<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\ApprovalAppInboxService;
use App\Services\ApprovalAppPenyesuaianDetailService;
use Illuminate\Http\Request;

class ApprovalAppController extends Controller
{
    public function registerDevice(Request $request, ApprovalAppInboxService $service)
    {
        try {
            $service->registerDevice(
                (string) $request->input('email', ''),
                (string) $request->input('device_id', ''),
                $request->input('fcm_token') ? (string) $request->input('fcm_token') : null,
                (string) ($request->input('platform') ?? 'android')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function counts(ApprovalAppInboxService $service)
    {
        return response()->json($service->counts());
    }

    public function inbox(Request $request, ApprovalAppInboxService $service)
    {
        $domain = (string) $request->query('domain', '');
        $bucket = (string) $request->query('bucket', '');
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);

        try {
            $result = $service->inbox($domain, $bucket, $page, $perPage);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function kandidatDetail(int $id, ApprovalAppInboxService $service)
    {
        $detail = $service->findKandidat($id);
        if (!$detail) {
            return response()->json(['message' => 'Data kandidat tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $detail]);
    }

    public function penyesuaianDetail(Request $request, ApprovalAppPenyesuaianDetailService $service)
    {
        $detail = $service->findByToken((string) $request->query('token', ''));
        if (!$detail) {
            return response()->json(['message' => 'Data penyesuaian tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $detail]);
    }

    public function penyesuaianAttendanceDaily(Request $request, ApprovalAppPenyesuaianDetailService $service)
    {
        $result = $service->findAttendanceDailyByToken(
            (string) $request->query('token', ''),
            (string) $request->query('month', '')
        );

        if (!$result) {
            return response()->json(['message' => 'Data absensi harian tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $result]);
    }
}
