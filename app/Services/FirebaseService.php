<?php
namespace App\Services;

use Google\Auth\OAuth2;
use Illuminate\Support\Facades\Http;
use App\Models\NotificationFdl;
use App\Models\FcmTokenFdl;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;


// Cara Penggunaan

// $fcm = new FirebaseService();
// $fcm->sendNotification(
//     'device_fcm_token_here', // Token FCM
    // 'Halo!', // Judul notifikasi
//     'Notifikasi berhasil dikirim', // Isi notifikasi
//     ['custom_key' => 'custom_value'], // Data tambahan (kosongin aja dulu)
//     $user_id
// );

class FirebaseService
{
    protected $projectId;
    protected $messagingUrl;
    protected $accessToken;

    public function __construct()
    {
        // dd('masuk');
        $path = base_path('storage/app/firebase/service-account.json');
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

    public function sendNotification($token, $title, $body, $data = [], $user_id)
    {
        // Simpan ke database log
        NotificationFdl::create([
            'user_id' => $user_id,
            'title' => $title,
            'body' => $body,
            'extra_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'created_at' => Carbon::now(),
        ]);

        // Kalau token kosong, stop sampai sini
        if (empty($token)) {
            return ['message' => 'Notification saved to database only'];
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => $data
            ]
        ];

        $response = Http::withToken($this->accessToken)
            ->post($this->messagingUrl, $payload);

        if (!$response->successful()) {
            $responseBody = json_decode($response->body(), true);

            if (
                isset($responseBody['error']['details'][0]['errorCode']) &&
                $responseBody['error']['details'][0]['errorCode'] === 'UNREGISTERED'
            ) {
                // hapus token di DB
                FcmTokenFdl::where('user_id', $user_id)->where('fcm_token', $token)->delete();

                Log::info("Token FCM dihapus karena UNREGISTERED", [
                    'user_id' => $user_id,
                    'token' => $token
                ]);
            } else {
                // log error biasa
                Log::error('FCM failed to send', [
                    'user_id' => $user_id,
                    'token' => $token,
                    'title' => $title,
                    'body' => $body,
                    'response' => $response->body()
                ]);
            }
        }

        return true;
    }
}