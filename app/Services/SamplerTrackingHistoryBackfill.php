<?php

namespace App\Services;

use App\Models\SamplerTrackingMember;
use App\Models\SamplerTrackingSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SamplerTrackingHistoryBackfill
{
    public function runSession(int $sessionId, bool $dryRun = false): int
    {
        if (!SamplerTrackingHistory::available()) {
            throw new \RuntimeException('Kolom sampler_tracking_sessions.meta_history belum tersedia.');
        }
        if (!SamplerTrackingSession::where('id', $sessionId)->exists()) {
            throw new \RuntimeException('Session tracking tidak ditemukan: ' . $sessionId);
        }
        return $this->backfillSession($sessionId, $dryRun);
    }

    public function run(string $from, string $to, bool $dryRun = false): array
    {
        if (!SamplerTrackingHistory::available()) {
            throw new \RuntimeException('Kolom sampler_tracking_sessions.meta_history belum tersedia.');
        }

        $stats = ['sessions' => 0, 'entries' => 0];
        // Include inactive sessions; existing evidence must remain traceable.
        SamplerTrackingSession::whereBetween('tanggal_sampling', [$from, $to])
            ->select('id')->chunkById(100, function ($sessions) use (&$stats, $dryRun) {
                foreach ($sessions as $session) {
                    $stats['entries'] += $this->backfillSession((int) $session->id, $dryRun);
                    $stats['sessions']++;
                }
            });

        return $stats;
    }

    private function backfillSession(int $sessionId, bool $dryRun): int
    {
        return DB::transaction(function () use ($sessionId, $dryRun) {
            $query = SamplerTrackingSession::where('id', $sessionId);
            if (!$dryRun) $query->lockForUpdate();
            $session = $query->first();
            if (!$session) return 0;

            $history = json_decode($session->meta_history ?: '[]', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($history) || array_values($history) !== $history) {
                throw new \RuntimeException('Invalid tracking meta_history for session #' . $sessionId);
            }
            $keys = [];
            $knownEventIds = [];
            foreach ($history as $entry) {
                if (!empty($entry['backfill_key'])) $keys[$entry['backfill_key']] = true;
                if (!empty($entry['event_id'])) $knownEventIds[(int) $entry['event_id']] = true;
                foreach ($entry['affected'] ?? [] as $affected) {
                    if (!empty($affected['event_id'])) $knownEventIds[(int) $affected['event_id']] = true;
                }
            }

            $now = Carbon::now('Asia/Jakarta')->toDateTimeString();
            $base = ['schema_version' => 1, 'session_id' => $sessionId,
                'recorded_at' => $now, 'backfilled' => true];
            $added = [];
            $snapshotKey = 'backfill:session:' . $sessionId;
            if (!isset($keys[$snapshotKey])) {
                $added[] = array_merge($base, [
                    'type' => 'session_snapshot_imported', 'backfill_key' => $snapshotKey,
                    'source_created_at' => $session->created_at ? (string) $session->created_at : null,
                    'source_updated_at' => $session->updated_at ? (string) $session->updated_at : null,
                    'after' => SamplerTrackingHistory::snapshot($session),
                    'all_members' => $session->members()->orderBy('id')->get([
                        'id', 'sampler_id', 'sampler_name', 'effective_duration', 'is_active', 'created_at', 'updated_at',
                    ])->toArray(),
                    'limitation' => 'Kondisi session dan durasi dibaca saat backfill. Bukan kondisi awal atau riwayat perubahan jadwal lama.',
                ]);
            }

            $events = $session->events()->orderBy('event_at')->orderBy('id')->get()
                ->reject(function ($event) use ($knownEventIds) { return isset($knownEventIds[(int) $event->id]); });
            $memberIds = $events->pluck('sampler_tracking_member_id')
                ->merge($events->pluck('triggered_by_member_id'))->filter()->unique()->all();
            $members = SamplerTrackingMember::whereIn('id', $memberIds)->get()->keyBy('id');
            $groups = $events->groupBy(function ($event) {
                return json_encode([$event->event_type, (string) $event->event_at,
                    $event->triggered_by_member_id, $event->movement_group]);
            });
            foreach ($groups as $group) {
                $first = $group->first();
                $trigger = $members->get($first->triggered_by_member_id);
                $added[] = array_merge($base, [
                    'type' => 'activity_imported',
                    'backfill_key' => 'backfill:events:' . implode(',', $group->pluck('id')->sort()->all()),
                    'event_type' => $first->event_type,
                    'event_at' => $first->event_at ? (string) $first->event_at : null,
                    'triggered_by' => [
                        'member_id' => $first->triggered_by_member_id,
                        'sampler_id' => $trigger ? $trigger->sampler_id : null,
                        'name' => $trigger ? $trigger->sampler_name : 'Tidak diketahui dari record event',
                    ],
                    'affected' => $group->map(function ($event) use ($members) {
                        $member = $members->get($event->sampler_tracking_member_id);
                        return [
                            'event_id' => (int) $event->id,
                            'member_id' => (int) $event->sampler_tracking_member_id,
                            'sampler_id' => $member ? $member->sampler_id : null,
                            'name' => $member ? $member->sampler_name : null,
                            'automatic' => (bool) $event->is_auto,
                            'source_created_at' => $event->created_at ? (string) $event->created_at : null,
                            'source_updated_at' => $event->updated_at ? (string) $event->updated_at : null,
                            'source_note' => $event->note,
                        ];
                    })->values()->all(),
                    'limitation' => 'Direkonstruksi dari event yang masih tersimpan. Durasi saat event dan perubahan jadwal sebelumnya tidak dapat dipastikan.',
                ]);
            }

            if (!$dryRun && $added) {
                DB::table('sampler_tracking_sessions')->where('id', $sessionId)->update([
                    'meta_history' => json_encode(array_merge($history, $added), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
            }
            return count($added);
        });
    }
}
