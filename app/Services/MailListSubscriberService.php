<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MailListSubscriberService
{
    private const MAIL_LIST = 'promotion@intilab.com';
    private const MAIL_API_TOKEN = 'lC16g5AzgC7M2ODh7lWedWGSL3rYPS';
    private const MAIL_API_BASE = 'https://mail.intilab.com/api/';

    public function subscribersUrl(): string
    {
        return self::MAIL_API_BASE . self::MAIL_LIST . '/subscribers';
    }

    public function http()
    {
        return Http::withHeaders([
            'X-MLMMJADMIN-API-AUTH-TOKEN' => self::MAIL_API_TOKEN,
        ])->withOptions([
            'verify' => false,
        ]);
    }

    public function isApiSuccess($response): bool
    {
        if (!$response->successful()) {
            return false;
        }

        $json = $response->json();

        if (is_array($json) && array_key_exists('_success', $json)) {
            return (bool) $json['_success'];
        }

        return true;
    }

    public function addSubscriber(string $email)
    {
        return $this->http()
            ->timeout(10)
            ->asForm()
            ->post($this->subscribersUrl(), [
                'add_subscribers' => $email,
                'require_confirm' => 'no',
            ]);
    }

    public function removeSubscriber(string $email)
    {
        return $this->http()
            ->asForm()
            ->post($this->subscribersUrl(), [
                'remove_subscribers' => $email,
            ]);
    }

    public function normalizeEmails(array $emails): array
    {
        $valid = [];

        foreach ($emails as $email) {
            $email = trim((string) $email);
            if ($email === '') {
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $valid[] = strtolower($email);
        }

        return array_values(array_unique($valid));
    }

    public function collectFromCustomerRequest(Request $request): array
    {
        $emails = [];

        if ($request->has('kontak_pelanggan')) {
            $list = $request->kontak_pelanggan['email_perusahaan'] ?? [];
            if (is_array($list)) {
                foreach ($list as $email) {
                    if (!empty($email)) {
                        $emails[] = $email;
                    }
                }
            }
        }

        if ($request->has('pic_pelanggan')) {
            $list = $request->pic_pelanggan['email_pic'] ?? [];
            if (is_array($list)) {
                foreach ($list as $email) {
                    if (!empty($email)) {
                        $emails[] = $email;
                    }
                }
            }
        }

        return $this->normalizeEmails($emails);
    }

    /**
     * Daftarkan email sebagai subscriber jika belum ada. Error tidak di-throw.
     */
    public function syncIfMissing(array $emails): void
    {
        $emails = $this->normalizeEmails($emails);

        if (empty($emails)) {
            return;
        }

        foreach ($emails as $email) {
            try {
                $response = $this->addSubscriber($email);

                if ($this->isApiSuccess($response)) {
                    continue;
                }

                if ($response->status() === 409) {
                    continue;
                }

                Log::warning('MailListSubscriber: gagal menambahkan subscriber', [
                    'email' => $email,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('MailListSubscriber: error menambahkan subscriber', [
                    'email' => $email,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    public function syncFromCustomerRequest(Request $request): void
    {
        $this->syncIfMissing($this->collectFromCustomerRequest($request));
    }

    /**
     * @return array{added: int, duplicate: int, invalid: array, failed: array}
     */
    public function addSubscribersWithReport(array $emails): array
    {
        $invalid = [];
        $valid = [];

        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;
                continue;
            }

            $valid[] = strtolower(trim($email));
        }

        $valid = array_values(array_unique($valid));

        $added = 0;
        $duplicate = 0;
        $failed = [];

        foreach ($valid as $email) {
            try {
                $response = $this->addSubscriber($email);

                if ($this->isApiSuccess($response)) {
                    $added++;
                    continue;
                }

                if ($response->status() === 409) {
                    $duplicate++;
                    continue;
                }

                $failed[] = [
                    'email' => $email,
                    'status' => $response->status(),
                    'message' => $response->body(),
                ];
            } catch (\Throwable $th) {
                $failed[] = [
                    'email' => $email,
                    'status' => 500,
                    'message' => $th->getMessage(),
                ];
            }
        }

        return [
            'added' => $added,
            'duplicate' => $duplicate,
            'invalid' => $invalid,
            'failed' => $failed,
        ];
    }
}
