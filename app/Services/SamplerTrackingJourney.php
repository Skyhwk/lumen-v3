<?php

namespace App\Services;

use App\Models\SamplerTrackingEvent;
use App\Models\SamplerTrackingMember;
use Carbon\Carbon;

/** Read-only journey matching for trouble assessment. */
class SamplerTrackingJourney
{
    public function isComplete(SamplerTrackingMember $member, string $date): bool
    {
        $checkin = $member->events->where('event_type', 'checkin')->sortBy('event_at')->first();
        $checkout = $member->events->where('event_type', 'checkout')->sortByDesc('event_at')->first();
        if (!$member->sampler_id || !$checkin || !$checkout || !$checkin->event_at || !$checkout->event_at
            || Carbon::parse($checkout->event_at)->lt(Carbon::parse($checkin->event_at))) {
            return false;
        }

        $departure = $member->events->where('event_type', 'departure')->filter(function ($event) use ($checkin) {
            return $event->event_at && Carbon::parse($event->event_at)->lte(Carbon::parse($checkin->event_at));
        })->sortByDesc('event_at')->first();
        if (!$departure) {
            $departure = $this->eventsForSampler($member->sampler_id)
                ->where('event_type', 'departure')
                ->where('event_at', '<=', $checkin->event_at)
                ->whereHas('member.session', function ($query) use ($date) {
                    $query->whereDate('tanggal_sampling', '<=', $date);
                })
                ->orderByDesc('event_at')->orderByDesc('id')->first();
        }
        if (!$departure) return false;

        $departure->loadMissing('member.session');
        $departureDate = optional(optional($departure->member)->session)->tanggal_sampling;
        if (!$departureDate) return false;

        // A delayed return from an older assignment must not close a newer journey.
        // Match by the departure's assignment date, not the checkout's calendar date.
        $return = $this->eventsForSampler($member->sampler_id)
            ->where('event_type', 'return')
            ->where('event_at', '>=', $departure->event_at)
            ->whereHas('member.session', function ($query) use ($departureDate) {
                $query->whereDate('tanggal_sampling', '>=', $departureDate);
            })
            ->orderBy('event_at')->orderBy('id')->first();
        if (!$return || Carbon::parse($return->event_at)->lt(Carbon::parse($checkout->event_at))) {
            return false;
        }

        // Never use the return from a subsequent departure to finish an older stop.
        // Fan-out copies at the exact departure time belong to the same journey.
        $nextDeparture = $this->eventsForSampler($member->sampler_id)
            ->where('event_type', 'departure')
            ->where('event_at', '>', $departure->event_at)
            ->where(function ($query) use ($return) {
                $query->where('event_at', '<', $return->event_at)->orWhere(function ($sameTime) use ($return) {
                    $sameTime->where('event_at', $return->event_at)->where('id', '<', $return->id);
                });
            })->exists();

        return !$nextDeparture;
    }

    private function eventsForSampler($samplerId)
    {
        return SamplerTrackingEvent::whereNotNull('event_at')
            ->whereHas('member', function ($query) use ($samplerId) {
                $query->where('sampler_id', $samplerId)->where('is_active', true)
                    ->whereHas('session', function ($session) {
                        $session->where('is_active', true)->where(function ($company) {
                            $company->whereNull('nama_perusahaan')
                                ->orWhereRaw('LOWER(TRIM(nama_perusahaan)) != ?', ['cuti']);
                        });
                    });
            });
    }
}
