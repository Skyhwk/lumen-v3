<?php

namespace App\Services;

use Carbon\Carbon;

class SamplerTrackingActivity
{
    // Each source session/STPS remains one logical stop. No schedule, member,
    // or event is deleted or merged with another STPS.
    public static function consolidate($sessions, array $customers)
    {
        return $sessions->values();
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
