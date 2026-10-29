<?php

namespace App\Services;

use App\Models\SamplerTrackingSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SamplerTrackingHistory
{
    public static function available(): bool
    {
        return Schema::hasTable('sampler_tracking_sessions')
            && Schema::hasColumn('sampler_tracking_sessions', 'meta_history');
    }

    public static function snapshot(SamplerTrackingSession $session): array
    {
        $members = $session->activeMembers()->orderBy('id')->get()->map(function ($member) {
            return [
                'member_id' => (int) $member->id,
                'sampler_id' => $member->sampler_id,
                'name' => $member->sampler_name,
                'duration' => $member->duration,
                'personal_duration' => $member->durasi_personal,
                'effective_duration' => $member->effective_duration,
            ];
        })->all();

        return [
            'team_key' => $session->team_key,
            'date' => $session->tanggal_sampling,
            'start' => $session->jam_mulai,
            'end' => $session->jam_selesai,
            'company' => $session->nama_perusahaan,
            'order' => $session->no_order,
            'quotation' => $session->no_quotation,
            'sampling_id' => $session->id_sampling,
            'duration' => $session->durasi,
            'address' => $session->alamat_sampling,
            'vehicle' => $session->kendaraan,
            'driver' => $session->driver,
            'categories' => $session->kategori,
            'active' => (bool) $session->is_active,
            'members' => $members,
        ];
    }

    public static function append(int $sessionId, string $type, array $details): void
    {
        if (!self::available()) {
            return;
        }

        DB::transaction(function () use ($sessionId, $type, $details) {
            $row = DB::table('sampler_tracking_sessions')->where('id', $sessionId)
                ->lockForUpdate()->first(['meta_history']);
            if (!$row) return;

            $history = json_decode($row->meta_history ?: '[]', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($history) || array_values($history) !== $history) {
                throw new \RuntimeException('Invalid tracking meta_history for session #' . $sessionId);
            }
            $history[] = array_merge([
                'schema_version' => 1,
                'type' => $type,
                'recorded_at' => \Carbon\Carbon::now('Asia/Jakarta')->toDateTimeString(),
                'session_id' => $sessionId,
            ], $details);
            DB::table('sampler_tracking_sessions')->where('id', $sessionId)
                ->update(['meta_history' => json_encode($history, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        });
    }

    public static function link(int $oldId, int $newId, string $reason): void
    {
        if ($oldId === $newId) {
            return;
        }
        DB::transaction(function () use ($oldId, $newId, $reason) {
            self::append($oldId, 'session_replaced', [
                'from_session_id' => $oldId, 'to_session_id' => $newId, 'reason' => $reason,
            ]);
            self::append($newId, 'session_replaces', [
                'from_session_id' => $oldId, 'to_session_id' => $newId, 'reason' => $reason,
            ]);
        });
    }
}
