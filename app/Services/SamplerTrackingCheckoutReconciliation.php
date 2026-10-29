<?php

namespace App\Services;

use App\Models\SamplerTrackingEvent;
use App\Models\SamplerTrackingSession;
use Illuminate\Support\Facades\DB;

class SamplerTrackingCheckoutReconciliation
{
    /** Called only by an explicit schedule edit, after all member durations are saved. */
    public function reconcile(SamplerTrackingSession $session, $beforeRows, $afterRows): void
    {
        $old = $this->durations($beforeRows);
        $new = $this->durations($afterRows);
        $changed = array_filter($new, function ($duration, $key) use ($old) {
            return array_key_exists($key, $old) && $old[$key] !== $duration;
        }, ARRAY_FILTER_USE_BOTH);
        if (!$changed) return;

        DB::transaction(function () use ($session, $beforeRows, $afterRows, $old, $new, $changed) {
            $session = SamplerTrackingSession::where('id', $session->id)->lockForUpdate()->firstOrFail();
            if (!$session->is_active) return;
            // Never remove evidence when its audit cannot be persisted.
            if (!SamplerTrackingHistory::available()) {
                throw new \RuntimeException('Evaluasi checkout memerlukan kolom meta_history.');
            }
            $members = $session->activeMembers()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $events = SamplerTrackingEvent::where('sampler_tracking_session_id', $session->id)
                ->orderBy('id')->lockForUpdate()->get();
            $latest = $events->where('event_type', 'checkout')->whereIn('sampler_tracking_member_id', $members->keys()->all())
                ->groupBy('sampler_tracking_member_id')->map(function ($items) {
                    return $items->sortBy('id')->sortBy('event_at')->last();
                });
            $groups = $latest->groupBy(function ($event) {
                // Actor identifies one team action, but never owns its surviving checkout.
                return json_encode([(string) $event->event_at, $event->triggered_by_member_id, $event->movement_group]);
            });
            $decisions = [];
            $removed = [];
            foreach ($groups as $group) {
                if ($group->count() < 2 || !$group->first()->triggered_by_member_id) continue;
                $durations = $group->map(function ($event) use ($members) {
                    return SamplerTrackingActivity::duration($members->get($event->sampler_tracking_member_id));
                });
                if ($durations->unique()->count() < 2 || !$group->contains(function ($event) use ($members, $changed) {
                    return array_key_exists(SamplerTrackingActivity::samplerKey($members->get($event->sampler_tracking_member_id)), $changed);
                })) continue;

                $minimum = $durations->min();
                foreach ($group as $event) {
                    $member = $members->get($event->sampler_tracking_member_id);
                    $duration = SamplerTrackingActivity::duration($member);
                    $cancel = $duration > $minimum;
                    $returns = collect();
                    if ($cancel) {
                        // Preserve the complete row, including original actor, photo, note and coordinates.
                        $removed[$event->id] = $event->getAttributes();
                        $nextDeparture = $events->where('sampler_tracking_member_id', $member->id)
                            ->where('event_type', 'departure')->filter(function ($item) use ($event) {
                                return (string) $item->event_at > (string) $event->event_at;
                            })->min('event_at');
                        $returns = $events->where('sampler_tracking_member_id', $member->id)
                            ->where('event_type', 'return')->filter(function ($item) use ($event, $nextDeparture) {
                                return (string) $item->movement_group === (string) $event->movement_group
                                    && (string) $item->event_at >= (string) $event->event_at
                                    && (!$nextDeparture || (string) $item->event_at < (string) $nextDeparture);
                            });
                        foreach ($returns as $return) $removed[$return->id] = $return->getAttributes();
                    }
                    $decisions[] = [
                        'member_id' => (int) $member->id, 'sampler_id' => $member->sampler_id,
                        'name' => $member->sampler_name, 'effective_duration' => $duration,
                        'previous_duration' => $old[SamplerTrackingActivity::samplerKey($member)] ?? null,
                        'event_id' => (int) $event->id, 'event_at' => $event->event_at,
                        'checkout_event_before' => $event->getAttributes(),
                        'automatic' => (bool) $event->is_auto,
                        'triggered_by_member_id' => $event->triggered_by_member_id,
                        'triggered_by_name' => optional($members->get($event->triggered_by_member_id))->sampler_name,
                        'movement_group' => $event->movement_group,
                        'decision' => $cancel ? 'checkout_reopened' : 'checkout_retained',
                        'reason' => $cancel ? 'Durasi terbaru lebih panjang; wajib checkout kembali.' : 'Kelompok durasi terpendek mempertahankan checkout bersama.',
                        'return_events_before' => $events->where('sampler_tracking_member_id', $member->id)
                            ->where('event_type', 'return')->map->getAttributes()->values()->all(),
                        'reopened_return_ids' => $returns->pluck('id')->values()->all(),
                    ];
                }
            }
            $revision = hash('sha256', json_encode([$beforeRows->map->getAttributes()->all(), $afterRows->map->getAttributes()->all()]));
            $history = json_decode($session->meta_history ?: '[]', true);
            if (!is_array($history)) {
                $history = [];
            }
            $key = 'checkout-reconciliation:' . $revision;
            if (collect($history)->contains('evaluation_key', $key)) return;
            $actors = $afterRows->filter(function ($row) use ($beforeRows) {
                $previous = $beforeRows->firstWhere('id', $row->id);
                return !$previous || $previous->getAttributes() !== $row->getAttributes();
            })->pluck('updated_by')->filter()->unique()->values()->all();
            SamplerTrackingHistory::append((int) $session->id, 'checkout_schedule_evaluated', [
                'evaluation_key' => $key, 'trigger' => 'schedule_edit',
                'actor' => $actors ? implode(', ', $actors) : 'Tidak diketahui',
                'source_updated_at' => $afterRows->max('updated_at'),
                'before_schedules' => $beforeRows->map->getAttributes()->values()->all(),
                'after_schedules' => $afterRows->map->getAttributes()->values()->all(),
                'before_durations' => $old, 'after_durations' => $new,
                'after' => SamplerTrackingHistory::snapshot($session),
                'affected' => $decisions, 'removed_events' => array_values($removed),
                'summary' => $removed
                    ? 'Perubahan durasi memisahkan checkout tim. Checkout durasi terpendek dipertahankan; durasi lebih panjang wajib checkout kembali. Pulang terkait dibuka ulang jika ada.'
                    : 'Perubahan durasi dievaluasi; tidak ada checkout bersama yang perlu dibuka ulang.',
                'limitation' => 'Checkout bersama dikenali dari waktu, pemicu, dan movement group yang sama. Event berbeda tidak digabung hanya berdasarkan waktu yang berdekatan.',
            ]);
            // Audit and deletion share the transaction: either both persist or neither does.
            if ($removed) SamplerTrackingEvent::whereIn('id', array_keys($removed))->delete();
        });
    }

    private function durations($rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $key = $row->userid ? (string) $row->userid : mb_strtolower(trim((string) $row->sampler));
            $value = $row->durasi_personal;
            $duration = max(0, (int) (($value !== null && $value !== '') ? $value : $row->durasi));
            $result[$key] = max($result[$key] ?? 0, $duration);
        }
        ksort($result);
        return $result;
    }
}
