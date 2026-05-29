<?php

namespace App\Services;

use App\Models\customer\Notifications;
use App\Models\customer\Users;
use Carbon\Carbon;
use GuzzleHttp\Client;

class PortalNotificationService
{
    public function send($userId, string $for, array $extraData = []): array
    {
        try {
            if (!$userId) {
                return [
                    'status' => 'error',
                    'message' => 'User ID is required',
                ];
            }

            $user = Users::find($userId);
            if (!$user) {
                return [
                    'status' => 'error',
                    'message' => 'User not found',
                ];
            }

            $template = $this->resolveTemplate($for, $extraData, $user);
            $token = $user->fcm_device_token;
            
            PpiNotification::where('id', $userId)
                ->title($template['title'])
                ->message($template['body'])
                ->url($template['url'])
                ->data($template['data'])
                ->send();

            if (!$token) {
                return [
                    'status' => 'skipped',
                    'message' => 'Notification saved and MQTT dispatched, push skipped because fcm_device_token is null',
                    'template' => $template,
                ];
            }

            $client = new Client();
            $response = $client->post('https://exp.host/--/api/v2/push/send', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'to' => $token,
                    'title' => $template['title'],
                    'body' => $template['body'],
                    'data' => $template['data'],
                ],
            ]);

            return [
                'status' => 'success',
                'message' => 'Notification sent successfully',
                'template' => $template,
                'expo_response' => json_decode($response->getBody()->getContents(), true),
            ];
        } catch (\InvalidArgumentException $exception) {
            return [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 'error',
                'message' => 'Failed to send notification: ' . $exception->getMessage(),
            ];
        }
    }

    protected function resolveTemplate(string $for, array $extraData = [], ?Users $user = null): array
    {
        $key = strtolower(str_replace(['-', ' '], '_', trim($for)));

        $templates = [
            'pembelian' => fn () => $this->buildPurchaseTemplate($extraData, $user),
            'purchase' => fn () => $this->buildPurchaseTemplate($extraData, $user),
            'claim_reward_created' => fn () => $this->buildClaimRewardTemplate($extraData, 'created'),
            'claim_reward_updated' => fn () => $this->buildClaimRewardTemplate($extraData, 'updated'),
            'claim_reward_approved' => fn () => $this->buildApprovalTemplate($extraData, $user),
            'claim_reward_approve' => fn () => $this->buildApprovalTemplate($extraData, $user),
            'claim_reward_process' => fn () => $this->buildProcessTemplate($extraData, $user),
            'claim_reward_processed' => fn () => $this->buildProcessTemplate($extraData, $user),
            'claim_reward_shipping' => fn () => $this->buildProcessTemplate($extraData, $user),
            'claim_reward_delivered' => fn () => $this->buildDeliveredTemplate($extraData, $user),
            'claim_reward_rejected' => fn () => $this->buildRejectedTemplate($extraData, $user),
            'claim_reward_reject' => fn () => $this->buildRejectedTemplate($extraData, $user),
        ];

        if (!isset($templates[$key])) {
            throw new \InvalidArgumentException("Template notification untuk {$for} belum tersedia");
        }

        return $templates[$key]();
    }

    protected function buildPurchaseTemplate(array $extraData, ?Users $user = null): array
    {
        $orderNo = $extraData['order_no'] ?? $extraData['no_pesanan'] ?? $extraData['claim_code'] ?? null;
        $customerName = $extraData['customer_name'] ?? $extraData['name'] ?? $user->name ?? $user->nama ?? 'Customer';
        $totalPoints = (int) ($extraData['total_points'] ?? $extraData['total_poin'] ?? 0);

        return [
            'title' => 'Pembelian Berhasil',
            'body' => trim("Pembelian reward {$customerName}" . ($orderNo ? " dengan nomor {$orderNo}" : '') . ' berhasil dibuat.'),
            'url' => '/claim-reward',
            'data' => array_merge([
                'for' => 'pembelian',
                'type' => 'purchase_notification',
                'screen' => 'HistoryTukarPoin',
                'order_no' => $orderNo,
                'total_points' => $totalPoints,
            ], $this->normalizeData($extraData['data'] ?? [])),
        ];
    }

    protected function buildApprovalTemplate(array $extraData, ?Users $user = null): array
    {
        $orderNo = $extraData['order_no'] ?? $extraData['no_pesanan'] ?? $extraData['claim_code'] ?? null;
        $customerName = $extraData['customer_name'] ?? $extraData['name'] ?? $user->nama_lengkap ?? 'Customer';

        return [
            'title' => 'Pesanan Diproses',
            'body' => trim("Kabar baik, {$customerName}! Claim reward {$orderNo} sudah diproses."),
            'url' => '/rewards',
            'data' => array_merge([
                'type' => 'rewards',
                'screen' => 'HistoryTukarPoin',
                'order_no' => $orderNo,
                'status' => 'approved',
            ], $this->normalizeData($extraData['data'] ?? [])),
        ];
    }

    protected function buildProcessTemplate(array $extraData, ?Users $user = null): array
    {
        $orderNo = $extraData['order_no'] ?? $extraData['no_pesanan'] ?? $extraData['claim_code'] ?? null;
        $customerName = $extraData['customer_name'] ?? $extraData['name'] ?? $user->nama_lengkap ?? 'Customer';

        return [
            'title' => 'Pesanan Dikirim',
            'body' => trim("Reward kamu sudah jalan, {$customerName}! Claim {$orderNo} sedang dalam perjalanan."),
            'url' => '/rewards',
            'data' => array_merge([
                'type' => 'rewards',
                'screen' => 'HistoryTukarPoin',
                'order_no' => $orderNo,
                'status' => 'shipping',
            ], $this->normalizeData($extraData['data'] ?? [])),
        ];
    }

    protected function buildDeliveredTemplate(array $extraData, ?Users $user = null): array
    {
        $orderNo = $extraData['order_no'] ?? $extraData['no_pesanan'] ?? $extraData['claim_code'] ?? null;
        $customerName = $extraData['customer_name'] ?? $extraData['name'] ?? $user->nama_lengkap ?? 'Customer';

        return [
            'title' => 'Pesanan Tiba di Tujuan',
            'body' => trim("Kabar gembira, {$customerName}! Claim reward {$orderNo} kamu sudah tiba di tujuan."),
            'url' => '/rewards',
            'data' => array_merge([
                'type' => 'rewards',
                'screen' => 'HistoryTukarPoin',
                'order_no' => $orderNo,
                'status' => 'delivered',
            ], $this->normalizeData($extraData['data'] ?? [])),
        ];
    }

    protected function buildRejectedTemplate(array $extraData, ?Users $user = null): array
    {
        $orderNo = $extraData['order_no'] ?? $extraData['no_pesanan'] ?? $extraData['claim_code'] ?? null;
        $customerName = $extraData['customer_name'] ?? $extraData['name'] ?? $user->nama_lengkap ?? 'Customer';
        $reason = $extraData['reject_reason'] ?? $extraData['data']['reject_reason'] ?? null;

        $body = "Mohon maaf, {$customerName}! Claim reward {$orderNo} Anda ditolak" . ($reason ? " dengan alasan: {$reason}." : '.');

        return [
            'title' => 'Claim Reward Ditolak',
            'body' => trim($body),
            'url' => '/rewards',
            'data' => array_merge([
                'type' => 'rewards',
                'screen' => 'HistoryTukarPoin',
                'order_no' => $orderNo,
                'status' => 'rejected',
                'reject_reason' => $reason,
            ], $this->normalizeData($extraData['data'] ?? [])),
        ];
    }

    protected function buildClaimRewardTemplate(array $extraData, string $mode): array
    {
        $claimCode = $extraData['claim_code'] ?? $extraData['order_no'] ?? $extraData['no_pesanan'] ?? null;
        $claimText = $claimCode ? " dengan nomor {$claimCode}" : '';
        $templates = [
            'created' => [
                'title' => 'Claim Reward Berhasil Dibuat',
                'body' => "Yeay, claim reward Anda{$claimText} sudah kami terima. Tim kami akan segera cek dan proses ya.",
            ],
            'updated' => [
                'title' => 'Update Claim Reward',
                'body' => "Ada update baru untuk claim reward Anda{$claimText}. Yuk cek detailnya di aplikasi.",
            ],
            'approved' => [
                'title' => 'Claim Reward Disetujui',
                'body' => "Kabar baik, claim reward Anda{$claimText} sudah disetujui. Hadiahnya akan segera kami siapkan.",
            ],
            'process' => [
                'title' => 'Reward Sedang Dalam Perjalanan',
                'body' => "Claim reward Anda{$claimText} sedang dalam perjalanan. Semoga segera sampai dan selamat menikmati reward-nya.",
            ],
        ];

        $template = $templates[$mode] ?? $templates['updated'];

        return [
            'title' => $template['title'],
            'body' => $template['body'],
            'url' => '/claim-reward',
            'data' => array_merge([
                'for' => "claim_reward_{$mode}",
                'type' => 'claim_reward',
                'screen' => 'HistoryTukarPoin',
                'claim_code' => $claimCode,
            ], $this->normalizeData($extraData['data'] ?? [])),
        ];
    }

    protected function normalizeData($data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
