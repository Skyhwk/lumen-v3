<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\SelfRegistration\PortalSelfRegistrationService;
use Illuminate\Http\Request;

class PortalSelfRegistrationController extends Controller
{
    /** @var PortalSelfRegistrationService */
    private $service;

    public function __construct(PortalSelfRegistrationService $service = null)
    {
        $this->service = $service ?: new PortalSelfRegistrationService();
    }

    public function inspect(Request $request)
    {
        $token = (string) $request->input('token', '');
        $result = $this->service->inspectToken($token);

        return response()->json([
            'success' => $result['state'] === 'pending' || $result['state'] === 'signed',
            'state' => $result['state'],
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ], 200);
    }

    public function sign(Request $request)
    {
        $token = (string) $request->input('token', '');
        $signature = (string) $request->input('signature', '');
        $agreed = filter_var($request->input('agreed', false), FILTER_VALIDATE_BOOLEAN);
        $ip = (string) ($request->input('ip_address') ?: $request->ip());
        $userAgent = (string) ($request->input('user_agent') ?: $request->userAgent());

        // Redact large base64 signature before LogRequest middleware runs
        if ($request->isJson()) {
            $request->json()->set('signature', '[redacted]');
        }
        $request->request->set('signature', '[redacted]');

        if (!$agreed) {
            return response()->json([
                'success' => false,
                'message' => 'Centang persetujuan terlebih dahulu',
            ], 422);
        }

        $result = $this->service->completeSignature($token, $signature, $ip, $userAgent);
        if (empty($result['ok'])) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status'] ?? 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['data'],
        ], 200);
    }

    public function activate(Request $request)
    {
        $token = (string) $request->input('token', '');
        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');

        // Never allow plaintext credentials into LogRequest middleware
        if ($request->isJson()) {
            $request->json()->remove('password');
            $request->json()->remove('password_confirmation');
        }
        $request->request->remove('password');
        $request->request->remove('password_confirmation');
        $request->query->remove('password');
        $request->query->remove('password_confirmation');

        $result = $this->service->activate($token, $password, $passwordConfirmation);
        if (empty($result['ok'])) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status'] ?? 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'email' => $result['email'],
        ], 200);
    }

    public function document(Request $request)
    {
        $token = (string) $request->input('token', '');
        $file = $this->service->pdfBlobForToken($token);
        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'filename' => $file['filename'],
            'mime' => $file['mime'] ?: 'application/pdf',
            'content_base64' => base64_encode($file['binary']),
        ], 200);
    }
}
