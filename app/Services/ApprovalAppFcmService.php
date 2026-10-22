<?php

namespace App\Services;

use App\Models\ApprovalAppDevice;
use Google\Auth\OAuth2;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApprovalAppFcmService
{
    private ?string $projectId = null;

    private ?string $messagingUrl = null;

    private ?string $accessToken = null;

    public function notifyPendingKandidat(int $recruitmentId, string $candidateName): void
    {
        $this->broadcast(
            'Permintaan persetujuan kandidat',
            $candidateName . ' menunggu keputusan Anda.',
            [
                'type' => 'approval_pending',
                'domain' => 'kandidat',
                'id' => (string) $recruitmentId,
                'path' => '/kandidat/menunggu',
            ]
        );
    }

    /**
     * Push setelah email persetujuan kandidat terkirim ke antrian Ibu Direktur.
     */
    public function notifyPendingKandidatForIbuEmail(string $recipientEmail, int $recruitmentId, string $candidateName): void
    {
        if (!$this->isIbuApprovalRecipient($recipientEmail)) {
            return;
        }

        $this->safeNotifyPendingKandidat($recruitmentId, $candidateName);
    }

    public function notifyPendingPenyesuaian(int $requestId, string $employeeName): void
    {
        $this->broadcast(
            'Permintaan penyesuaian gaji',
            $employeeName . ' menunggu persetujuan Anda.',
            [
                'type' => 'approval_pending',
                'domain' => 'penyesuaian',
                'id' => (string) $requestId,
                'path' => '/penyesuaian/menunggu',
            ]
        );
    }

    public function safeNotifyPendingPenyesuaian(int $requestId, string $employeeName): void
    {
        try {
            $this->notifyPendingPenyesuaian($requestId, $employeeName);
        } catch (\Throwable $e) {
            Log::warning('ApprovalApp FCM penyesuaian: ' . $e->getMessage());
        }
    }

    public function safeNotifyPendingKandidat(int $recruitmentId, string $candidateName): void
    {
        try {
            $this->notifyPendingKandidat($recruitmentId, $candidateName);
        } catch (\Throwable $e) {
            Log::warning('ApprovalApp FCM kandidat: ' . $e->getMessage());
        }
    }

    private function isIbuApprovalRecipient(string $recipientEmail): bool
    {
        $recipientEmail = strtolower(trim($recipientEmail));
        if ($recipientEmail === '') {
            return false;
        }

        $ibuEmail = strtolower(trim((string) env('EMAIL_DIREKTUR_IBU', '')));
        if ($ibuEmail === '') {
            return true;
        }

        return $recipientEmail === $ibuEmail;
    }

    private function broadcast(string $title, string $body, array $data): void
    {
        if (!config('approval_app.fcm.enabled')) {
            return;
        }

        try {
            $this->ensureClient();
        } catch (\Throwable $e) {
            Log::warning('ApprovalApp FCM skipped: ' . $e->getMessage());

            return;
        }

        $deviceQuery = ApprovalAppDevice::query()
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '');

        $deviceEmails = config('approval_app.fcm.device_emails', []);
        if (is_array($deviceEmails) && $deviceEmails !== []) {
            $deviceQuery->whereIn('email', $deviceEmails);
        }

        $tokens = $deviceQuery
            ->pluck('fcm_token')
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $dataPayload = array_map('strval', $data);

        foreach ($tokens as $token) {
            try {
                $this->sendToToken($token, $title, $body, $dataPayload);
            } catch (\Throwable $e) {
                Log::warning('ApprovalApp FCM send failed', [
                    'token_prefix' => substr($token, 0, 12),
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function ensureClient(): void
    {
        if ($this->accessToken !== null) {
            return;
        }

        $path = (string) config('approval_app.fcm.credentials_path');

        if (!is_readable($path)) {
            throw new \RuntimeException('Firebase credentials Approval App tidak ditemukan: ' . $path);
        }

        $credentials = json_decode(file_get_contents($path), true);
        $this->projectId = $credentials['project_id'] ?? null;
        if (!$this->projectId) {
            throw new \RuntimeException('project_id tidak ada di service account Firebase.');
        }

        $this->messagingUrl = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $oauth = new OAuth2([
            'audience' => 'https://oauth2.googleapis.com/token',
            'tokenCredentialUri' => 'https://oauth2.googleapis.com/token',
            'scope' => ['https://www.googleapis.com/auth/firebase.messaging'],
            'signingAlgorithm' => 'RS256',
            'issuer' => $credentials['client_email'],
            'signingKey' => $credentials['private_key'],
        ]);

        $authResult = $oauth->fetchAuthToken();
        if (empty($authResult['access_token'])) {
            throw new \RuntimeException('Gagal mendapatkan access token Firebase.');
        }

        $this->accessToken = $authResult['access_token'];
    }

    private function sendToToken(string $token, string $title, string $body, array $data): void
    {
        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->post($this->messagingUrl, [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id' => 'approval',
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('FCM HTTP ' . $response->status() . ': ' . $response->body());
        }
    }
}
