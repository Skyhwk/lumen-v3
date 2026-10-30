<?php

namespace App\Services;

use App\Models\SamplerTrackingEvent;
use App\Models\SamplerTrackingMember;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Read-only journey matching for trouble assessment. */
class SamplerTrackingJourney
{
    /** @var array<string, Collection> */
    protected static $samplerEventsCache = [];

    public static function clearCache(): void
    {
        self::$samplerEventsCache = [];
    }

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
            $departure = $this->findDepartureForSampler($member->sampler_id, (string) $checkin->event_at, $date);
        }
        if (!$departure) return false;

        $departure->loadMissing('member.session');
        $departureDate = optional(optional($departure->member)->session)->tanggal_sampling;
        if (!$departureDate) return false;

        // A delayed return from an older assignment must not close a newer journey.
        // Match by the departure's assignment date, not the checkout's calendar date.
        $return = $this->findReturnForSampler($member->sampler_id, (string) $departure->event_at, (string) $departureDate, (string) $checkout->event_at);
        if (!$return || Carbon::parse($return->event_at)->lt(Carbon::parse($checkout->event_at))) {
            return false;
        }

        // Never use the return from a subsequent departure to finish an older stop.
        // Fan-out copies at the exact departure time belong to the same journey.
        $nextDeparture = $this->hasNextDepartureForSampler($member->sampler_id, (string) $departure->event_at, $return);

        return !$nextDeparture;
    }

    protected function findDepartureForSampler($samplerId, string $checkinAt, string $date)
    {
        $events = $this->eventsForSampler($samplerId);

        return $events->where('event_type', 'departure')
            ->filter(function ($event) use ($checkinAt, $date) {
                if ((string) $event->event_at > $checkinAt) {
                    return false;
                }
                $sessionDate = optional(optional($event->member)->session)->tanggal_sampling;
                return $sessionDate && (string) $sessionDate <= $date;
            })
            ->sort(function ($a, $b) {
                $cmp = strcmp((string) $b->event_at, (string) $a->event_at);
                return $cmp !== 0 ? $cmp : ($b->id <=> $a->id);
            })
            ->first();
    }

    protected function findReturnForSampler($samplerId, string $departureAt, string $departureDate, string $checkoutAt = null)
    {
        $events = $this->eventsForSampler($samplerId);
        $minAt = $checkoutAt && $checkoutAt > $departureAt ? $checkoutAt : $departureAt;

        return $events->where('event_type', 'return')
            ->filter(function ($event) use ($minAt, $departureDate) {
                if ((string) $event->event_at < $minAt) {
                    return false;
                }
                $sessionDate = optional(optional($event->member)->session)->tanggal_sampling;
                return $sessionDate && (string) $sessionDate >= $departureDate;
            })
            ->sort(function ($a, $b) {
                $cmp = strcmp((string) $a->event_at, (string) $b->event_at);
                return $cmp !== 0 ? $cmp : ($a->id <=> $b->id);
            })
            ->first();
    }

    protected function hasNextDepartureForSampler($samplerId, string $departureAt, $return): bool
    {
        $events = $this->eventsForSampler($samplerId);
        $returnAt = (string) $return->event_at;
        $returnId = (int) $return->id;

        return $events->where('event_type', 'departure')
            ->contains(function ($event) use ($departureAt, $returnAt, $returnId) {
                $eventAt = (string) $event->event_at;
                if ($eventAt <= $departureAt) {
                    return false;
                }
                if ($eventAt < $returnAt) {
                    return true;
                }
                return $eventAt === $returnAt && (int) $event->id < $returnId;
            });
    }

    protected function eventsForSampler($samplerId): Collection
    {
        $key = (string) $samplerId;
        if (!isset(self::$samplerEventsCache[$key])) {
            self::$samplerEventsCache[$key] = SamplerTrackingEvent::with(['member.session'])
                ->whereNotNull('event_at')
                ->whereHas('member', function ($query) use ($samplerId) {
                    $query->where('sampler_id', $samplerId)->where('is_active', true)
                        ->whereHas('session', function ($session) {
                            $session->where('is_active', true)->where(function ($company) {
                                $company->whereNull('nama_perusahaan')
                                    ->orWhereRaw('LOWER(TRIM(nama_perusahaan)) != ?', ['cuti']);
                            });
                        });
                })
                ->orderBy('event_at')->orderBy('id')
                ->get();
        }

        return self::$samplerEventsCache[$key];
    }
}
