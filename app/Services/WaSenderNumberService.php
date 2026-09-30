<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WaSenderNumberService
{
    public function availableSenders(): array
    {
        if (!Schema::hasTable('wa_sender_numbers')) {
            return $this->envSenders();
        }

        $today = Carbon::now('Asia/Jakarta')->toDateString();

        return DB::transaction(function () use ($today) {
            // Pengiriman pertama setelah lewat tengah malam mereset counter harian seluruh nomor.
            DB::table('wa_sender_numbers')
                ->where(function ($query) use ($today) {
                    $query->whereNull('daily_sent_date')
                        ->orWhere('daily_sent_date', '!=', $today);
                })
                ->update([
                    'daily_sent' => 0,
                    'daily_sent_date' => $today,
                    'updated_at' => Carbon::now('Asia/Jakarta'),
                ]);

            $senders = DB::table('wa_sender_numbers')
                ->where('is_active', 1)
                ->orderBy('daily_sent')
                ->orderBy('total_sent')
                ->get(['id', 'number', 'daily_sent', 'total_sent'])
                ->all();

            if (empty($senders)) {
                return [];
            }

            $first = $senders[0];
            $samePriority = array_values(array_filter($senders, function ($sender) use ($first) {
                return (int) $sender->daily_sent === (int) $first->daily_sent
                    && (int) $sender->total_sent === (int) $first->total_sent;
            }));
            shuffle($samePriority);

            $remaining = array_values(array_filter($senders, function ($sender) use ($first) {
                return !((int) $sender->daily_sent === (int) $first->daily_sent
                    && (int) $sender->total_sent === (int) $first->total_sent);
            }));

            return array_merge($samePriority, $remaining);
        });
    }

    public function markSuccess($senderId): void
    {
        if (!$senderId || !Schema::hasTable('wa_sender_numbers')) {
            return;
        }

        DB::table('wa_sender_numbers')->where('id', $senderId)->update([
            'total_sent' => DB::raw('total_sent + 1'),
            'daily_sent' => DB::raw('daily_sent + 1'),
            'daily_sent_date' => Carbon::now('Asia/Jakarta')->toDateString(),
            'last_success_at' => Carbon::now('Asia/Jakarta'),
            'updated_at' => Carbon::now('Asia/Jakarta'),
        ]);
    }

    public function markFailure($senderId, array $meta): void
    {
        if (!$senderId || !Schema::hasTable('wa_sender_numbers')) {
            return;
        }

        DB::table('wa_sender_numbers')->where('id', $senderId)->update([
            'failure_count' => DB::raw('failure_count + 1'),
            'last_failure_at' => Carbon::now('Asia/Jakarta'),
            'failure_meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => Carbon::now('Asia/Jakarta'),
        ]);
    }

    public function recordSend(array $entry): void
    {
        if (!Schema::hasTable('wa_send_logs')) {
            return;
        }

        try {
            $body = (string) ($entry['message'] ?? '');

            DB::table('wa_send_logs')->insert([
                'sender_number_id' => $entry['sender_id'] ?? null,
                'sender_number' => $entry['sender_number'] ?? null,
                'destination' => (string) ($entry['destination'] ?? ''),
                'status' => (string) ($entry['status'] ?? 'failed'),
                'attempt' => (int) ($entry['attempt'] ?? 1),
                'reason' => isset($entry['reason']) ? substr((string) $entry['reason'], 0, 500) : null,
                'response_meta' => isset($entry['response'])
                    ? json_encode($entry['response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'body' => $body !== '' ? $body : null,
                'created_at' => Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('WhatsApp send log failed.', ['message' => $e->getMessage()]);
        }
    }

    private function envSenders(): array
    {
        $numbers = collect(explode(',', (string) env('NUMBER', '')))
            ->map(fn ($number) => trim($number))
            ->filter()
            ->unique()
            ->values();

        return $numbers->map(function ($number) {
            return (object) ['id' => null, 'number' => $number];
        })->all();
    }
}
