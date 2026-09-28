<?php

namespace App\Services\Greatday;

use App\Support\Greatday\GreatdayAppData;
use Carbon\Carbon;
use Google\Auth\OAuth2;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected $projectId;

    protected $messagingUrl;

    protected $accessToken;

    public function __construct()
    {
        $path = base_path('storage/app/firebase/google-service.json');
        if (!is_readable($path)) {
            throw new \RuntimeException('Firebase credentials missing at storage/app/firebase/google-service.json');
        }

        $credentials = json_decode(file_get_contents($path), true);

        $this->projectId = $credentials['project_id'];
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
        if (!isset($authResult['access_token'])) {
            throw new \Exception('Gagal mendapatkan Firebase access token');
        }

        $this->accessToken = $authResult['access_token'];
    }

    public function sendNotification($token, $data = [], $user_id)
    {
        GreatdayAppData::notificationQuery()->create([
            'user_id' => $user_id,
            'title' => $data['title'],
            'body' => $data['body'],
            'extra_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at' => Carbon::now(),
        ]);

        GreatdayMqttPublisher::publishSync([(int) $user_id], $data);

        if (empty($token)) {
            return ['message' => 'Notification saved to database only'];
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $data['title'],
                    'body' => $data['body'],
                ],
                'data' => $data,
            ],
        ];

        $response = Http::withToken($this->accessToken)
            ->post($this->messagingUrl, $payload);

        if (!$response->successful()) {
            $responseBody = json_decode($response->body(), true);

            if (
                isset($responseBody['error']['details'][0]['errorCode'])
                && $responseBody['error']['details'][0]['errorCode'] === 'UNREGISTERED'
            ) {
                GreatdayAppData::fcmTokenQuery()->where('user_id', $user_id)->where('fcm_token', $token)->delete();

                Log::info('Token FCM dihapus karena UNREGISTERED', [
                    'user_id' => $user_id,
                    'token' => $token,
                ]);
            } else {
                Log::error('FCM failed to send', [
                    'user_id' => $user_id,
                    'token' => $token,
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'response' => $response->body(),
                ]);
            }
        }

        return true;
    }

    public function sendNotifications(array $userIds, array $data): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (empty($userIds)) {
            return [
                'success' => false,
                'message' => 'User IDs kosong',
                'summary' => [],
            ];
        }

        $tokens = GreatdayAppData::fcmTokenQuery()
            ->whereIn('user_id', $userIds)
            ->select('user_id', 'fcm_token')
            ->get();

        $tokensByUser = [];
        foreach ($tokens as $t) {
            $tokensByUser[$t->user_id][] = $t->fcm_token;
        }

        $now = Carbon::now();
        $rows = [];
        foreach ($userIds as $uid) {
            $rows[] = [
                'user_id' => $uid,
                'title' => $data['title'] ?? '',
                'body' => $data['body'] ?? '',
                'extra_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'created_at' => $now,
            ];
        }
        GreatdayAppData::notificationQuery()->insert($rows);

        GreatdayMqttPublisher::publishSync($userIds, $data);

        $targets = [];
        foreach ($userIds as $uid) {
            $userTokens = $tokensByUser[$uid] ?? [];
            foreach ($userTokens as $token) {
                if (!empty($token)) {
                    $targets[] = ['user_id' => $uid, 'token' => $token];
                }
            }
        }

        if (empty($targets)) {
            return [
                'success' => true,
                'message' => 'Notif tersimpan di DB, tapi tidak ada token untuk dikirim',
                'summary' => [
                    'users' => count($userIds),
                    'targets' => 0,
                    'sent' => 0,
                    'failed' => 0,
                    'unregistered_deleted' => 0,
                ],
            ];
        }

        $chunkSize = 100;
        $sent = 0;
        $failed = 0;
        $unregisteredDeleted = 0;

        foreach (array_chunk($targets, $chunkSize) as $batch) {
            $responses = Http::pool(function ($pool) use ($batch, $data) {
                foreach ($batch as $item) {
                    $payload = [
                        'message' => [
                            'token' => $item['token'],
                            'notification' => [
                                'title' => $data['title'] ?? '',
                                'body' => $data['body'] ?? '',
                            ],
                            'data' => $this->stringifyData($data),
                        ],
                    ];

                    $pool->withToken($this->accessToken)
                        ->post($this->messagingUrl, $payload);
                }
            });

            foreach ($responses as $i => $res) {
                $target = $batch[$i];

                if ($res->successful()) {
                    $sent++;
                    continue;
                }

                $failed++;
                $responseBody = $res->json();

                $errorCode = data_get($responseBody, 'error.details.0.errorCode');

                if ($errorCode === 'UNREGISTERED') {
                    GreatdayAppData::fcmTokenQuery()->where('user_id', $target['user_id'])
                        ->where('fcm_token', $target['token'])
                        ->delete();

                    $unregisteredDeleted++;

                    Log::info('Token FCM dihapus karena UNREGISTERED', [
                        'user_id' => $target['user_id'],
                        'token' => $target['token'],
                    ]);
                } else {
                    Log::error('FCM bulk failed', [
                        'user_id' => $target['user_id'],
                        'token' => $target['token'],
                        'title' => $data['title'] ?? '',
                        'body' => $data['body'] ?? '',
                        'response' => $res->body(),
                    ]);
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Bulk notification processed',
            'summary' => [
                'users' => count($userIds),
                'targets' => count($targets),
                'sent' => $sent,
                'failed' => $failed,
                'unregistered_deleted' => $unregisteredDeleted,
            ],
        ];
    }

    private function stringifyData(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_array($v) || is_object($v)) {
                $out[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            } elseif (is_bool($v)) {
                $out[$k] = $v ? '1' : '0';
            } elseif ($v === null) {
                $out[$k] = '';
            } else {
                $out[$k] = (string) $v;
            }
        }

        return $out;
    }
}
