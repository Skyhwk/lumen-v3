<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class SendWhatsapp
{
    private $number;
    private $message;

    public function __construct($number, $message)
    {
        $this->number = $number;
        $this->message = $message;
    }

    private function getToken()
    {
        $response = Http::post(env('TOKEN_API').'/api/get-token',
            [
            'secret' => env('SECRET')
            ]
        );

        $res = json_decode($response->getBody());
        return $res->data->access_token;
    }

    private function getSeason(string $senderNumber)
    {
        $token = $this->getToken();
        $response = Http::withHeaders(['Authorization' => 'Bearer '.$token])->get(env('TOKEN_API').'/api/get_season',
            [
            'number' => $senderNumber
            ]
        );

        $res = json_decode($response->getBody());
        return $res->data->name;
    }

    private function formatNumber()
    {
        $number = preg_replace('/[^0-9]/', '', $this->number);
        if (substr($number, 0, 2) === '08') {
            return '62' . substr($number, 1);
        }
        if (substr($number, 0, 1) === '8') {
            return '62' . $number;
        }
        return $number;
    }

    public function send()
    {
        $number = $this->formatNumber();
        $senderService = new WaSenderNumberService();

        foreach ($senderService->availableSenders() as $sender) {
            $senderNumber = $sender->number;
            try {
                $seasonID = $this->getSeason($senderNumber);
                $response = Http::withHeaders(
                    [
                        'Content-Type' => 'application/json'
                    ])->post(env('WHATSAPP_API').'/'.$seasonID.'/messages/send',
                    [
                    'jid' => $number.'@s.whatsapp.net',
                    'type' => 'number',
                    'message' => ['text' => $this->message]
                    ]
                );

                $res = json_decode($response->getBody());
                if ($this->wasSent($res)) {
                    $senderService->markSuccess($sender->id);
                    return true;
                }

                $senderService->markFailure($sender->id, [
                    'reason' => 'Provider rejected message',
                    'at' => Carbon::now('Asia/Jakarta')->toDateTimeString(),
                    'response' => $this->responseSummary($res),
                ]);
            } catch (\Throwable $e) {
                $senderService->markFailure($sender->id, [
                    'reason' => $e->getMessage(),
                    'at' => Carbon::now('Asia/Jakarta')->toDateTimeString(),
                    'type' => get_class($e),
                ]);
                \Log::warning('WhatsApp sender failed, trying next sender.', [
                    'sender' => $senderNumber,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return false;
    }

    private function wasSent($response): bool
    {
        if (!is_object($response)) {
            return false;
        }

        $status = $response->status ?? null;
        if (in_array($status, [true, 1, '1', 'true'], true)) {
            return true;
        }

        // Provider returns PENDING once it has accepted and queued the message.
        return is_string($status) && in_array(strtoupper($status), [
            'PENDING', 'QUEUED', 'SENT', 'SUCCESS', 'DELIVERED',
        ], true);
    }

    private function responseSummary($response): array
    {
        if (!is_object($response)) {
            return ['body' => 'Invalid JSON response'];
        }

        $summary = ['status' => $response->status ?? null];
        foreach (['message', 'error'] as $field) {
            $value = $response->{$field} ?? null;
            if (is_scalar($value) && $value !== '') {
                $summary[$field] = substr((string) $value, 0, 500);
            }
        }

        return array_filter($summary, fn ($value) => $value !== null && $value !== '');
    }
}
