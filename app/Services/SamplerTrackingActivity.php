<?php

namespace App\Services;

use Carbon\Carbon;

class SamplerTrackingActivity
{
    // A logical stop retains all source sessions/orders and all historical events.
    // No schedule, member or event is deleted by consolidation.
    public static function consolidate($sessions, array $customers)
    {
        return $sessions->groupBy(function ($session) use ($customers) {
            $customer = $customers[$session->id] ?? null;
            if (!$customer) return 'session:' . $session->id;
            $team = $session->activeMembers->map(function ($member) {
                return self::samplerKey($member);
            })->unique()->sort()->values()->all();
            return json_encode([$session->tanggal_sampling, (string) $customer, $team]);
        })->map(function ($group) {
            $stop = clone $group->first();
            $stop->activity_session_ids = $group->pluck('id')->values()->all();
            $stop->activity_orders = $group->pluck('no_order')->filter()->unique()->values()->all();
            $stop->no_order = implode(', ', $stop->activity_orders);
            $members = $group->flatMap(function ($session) { return $session->activeMembers; });
            $stop->setRelation('activeMembers', $members->groupBy(function ($member) {
                return self::samplerKey($member);
            })->map(function ($sameSampler) {
                $member = clone $sameSampler->first();
                $member->activity_member_ids = $sameSampler->pluck('id')->values()->all();
                $member->effective_duration = $sameSampler->max(function ($item) { return self::duration($item); });
                $member->setRelation('events', $sameSampler->flatMap(function ($item) {
                    return $item->events;
                })->unique('id')->sortBy('event_at')->values());
                return $member;
            })->values());
            return $stop;
        })->values();
    }

    public static function samplerKey($member)
    {
        return $member->sampler_id ? (string) $member->sampler_id : mb_strtolower(trim((string) $member->sampler_name));
    }

    public static function duration($member)
    {
        foreach (['effective_duration', 'durasi_personal', 'duration', 'durasi'] as $field) {
            if ($member->$field !== null && $member->$field !== '') return max(0, (int) $member->$field);
        }
        return 0;
    }

    public static function hasEvent($member, $type)
    {
        return $member && $member->events->contains('event_type', $type);
    }

    public static function progress($activities, $samplerId)
    {
        $members = $activities->flatMap(function ($activity) use ($samplerId) {
            return $activity->activeMembers->filter(function ($member) use ($samplerId) {
                return (string) $member->sampler_id === (string) $samplerId;
            })->map(function ($member) use ($activity) {
                return ['member' => $member, 'date' => $activity->tanggal_sampling];
            });
        });
        if ($members->isEmpty()) return ['complete' => true, 'due_date' => null];
        $complete = $members->every(function ($item) {
            return self::hasEvent($item['member'], 'checkin') && self::hasEvent($item['member'], 'checkout');
        }) && $members->contains(function ($item) { return self::hasEvent($item['member'], 'departure'); })
           && $members->contains(function ($item) { return self::hasEvent($item['member'], 'return'); });
        $due = $members->map(function ($item) {
            return Carbon::parse($item['date'])->addDays(max(0, self::duration($item['member']) - 1))->toDateString();
        })->max();
        return ['complete' => $complete, 'due_date' => $due];
    }
}
