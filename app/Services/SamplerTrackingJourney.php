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

    /** Live team fan-out is valid. A retrospective copy is evidence of the old team, not a new journey. */
    public static function isJourneyBoundary($event): bool
    {
        if (!$event || !$event->event_at) return false;
        if ((bool) $event->is_auto && preg_match('/(?:Salinan otomatis tim|Koreksi anggota sejak awal).*sumber event #/s', (string) $event->note)) {
            $joinedAt = $event->member ? self::joinedAt($event->member) : null;
            if (!$joinedAt || (string) $event->event_at < $joinedAt) return false;
        }
        $session = optional($event->member)->session;
        return !$session || !$session->tanggal_sampling
            || substr((string) $event->event_at, 0, 10) >= substr((string) $session->tanggal_sampling, 0, 10);
    }

    public static function joinedAt(SamplerTrackingMember $member): ?string
    {
        $joinedAt = $member->created_at ? (string) $member->created_at : null;
        $history = json_decode(optional($member->session)->meta_history ?: '[]', true);
        foreach (is_array($history) ? $history : [] as $entry) {
            if (is_array($entry) && ($entry['type'] ?? null) === 'member_joined'
                && (int) ($entry['member_id'] ?? 0) === (int) $member->id) {
                $joinedAt = $entry['joined_at'] ?? $joinedAt;
            }
        }
        return $joinedAt;
    }

    /** The person's departure event is the stable journey identity, across orders and dates. */
    public function departureForMember(SamplerTrackingMember $member, string $date)
    {
        if (!$member->sampler_id) return null;
        $checkin = $member->events->where('event_type', 'checkin')->sortBy('event_at')->first();
        $at = $checkin ? (string) $checkin->event_at : Carbon::now('Asia/Jakarta')->toDateTimeString();
        $query = $this->eventsQueryForSampler($member->sampler_id)->with('member.session')
            ->where('event_at', '<=', $at)
            ->whereHas('member.session', function ($query) use ($date) {
                $query->whereDate('tanggal_sampling', '<=', $date);
            })->when($checkin, function ($query) use ($checkin) {
                $query->where(function ($before) use ($checkin) {
                    $before->where('event_at', '<', $checkin->event_at)->orWhere(function ($same) use ($checkin) {
                        $same->where('event_at', $checkin->event_at)->where('id', '<', $checkin->id);
                    });
                });
            });
        $ownDeparture = $member->events->where('event_type', 'departure')->filter(function ($event) use ($at) {
            return self::isJourneyBoundary($event) && (string) $event->event_at <= $at;
        })->sortByDesc('event_at')->first();
        // Stop at the nearest valid boundary instead of materializing a year of events per request.
        $departure = $ownDeparture ?: (clone $query)->where('event_type', 'departure')
            ->orderByDesc('event_at')->orderByDesc('id')->lazy(50)->first(function ($event) {
                return self::isJourneyBoundary($event);
            });
        if (!$departure) return null;
        $returned = (clone $query)->where('event_type', 'return')->where('event_at', '>=', $departure->event_at)
            ->whereHas('member.session', function ($query) use ($departure) {
                $query->whereDate('tanggal_sampling', '>=', $departure->member->session->tanggal_sampling);
            })->orderByDesc('event_at')->orderByDesc('id')->lazy(50)->contains(function ($event) use ($departure) {
                return self::isJourneyBoundary($event)
                    && ((string) $event->event_at > (string) $departure->event_at || (int) $event->id > (int) $departure->id);
            });
        return $returned ? null : $departure;
    }

    public function reference(SamplerTrackingMember $member, string $date): ?array
    {
        $departure = $this->departureForMember($member, $date);
        return $departure ? [
            'journey_id' => 'sampler-' . $member->sampler_id . '-departure-' . $departure->id,
            'departure_event_id' => (int) $departure->id,
            'departure_session_id' => (int) $departure->sampler_tracking_session_id,
            'departure_at' => (string) $departure->event_at,
        ] : null;
    }

    /** Only started assignments may extend an open journey; schedules alone cannot. */
    public function extension(SamplerTrackingMember $member, string $date, string $baseDue, $asOf = null): ?array
    {
        $checkin = $member->events->where('event_type', 'checkin')->sortBy('event_at')->first();
        if (!$member->sampler_id || !$checkin || !$checkin->event_at) return null;
        $asOf = $asOf ? Carbon::parse($asOf, 'Asia/Jakarta') : Carbon::now('Asia/Jakarta');
        $asOf = $asOf->min(Carbon::now('Asia/Jakarta'));
        if (Carbon::parse($checkin->event_at, 'Asia/Jakarta')->gt($asOf)) return null;
        $departure = $this->departureForMember($member, $date);
        if (!$departure || !$departure->member || !$departure->member->session) return null;
        $departureDate = $departure->member->session->tanggal_sampling;
        $due = $baseDue;
        $cursor = (string) $checkin->event_at;
        $inclusive = true;
        $links = [];
        do {
            // Advance in deadline windows, never scan an unbounded year of history.
            $window = Carbon::parse($due, 'Asia/Jakarta')->endOfDay()->min($asOf)->toDateTimeString();
            if ($window < $cursor) break;
            $events = $this->eventsQueryForSampler($member->sampler_id)->with('member.session')
                ->where('event_at', $inclusive ? '>=' : '>', $cursor)->where('event_at', '<=', $window)
                ->orderBy('event_at')->orderBy('id')->get();
            $stopped = false;
            foreach ($events as $event) {
                if (in_array($event->event_type, ['departure', 'return'], true) && !self::isJourneyBoundary($event)) continue;
                if ($event->event_type === 'departure' && (string) $event->event_at > (string) $departure->event_at) {
                    $stopped = true;
                    break;
                }
                $source = optional($event->member)->session;
                if (!$source) continue;
                if ($event->event_type === 'return' && $source->tanggal_sampling >= $departureDate) {
                    $stopped = true;
                    break;
                }
                if ($event->event_type !== 'checkin' || (string) $event->event_at < (string) $checkin->event_at
                    || $source->tanggal_sampling <= $date
                    || $source->tanggal_sampling > Carbon::parse($event->event_at)->toDateString()
                    || Carbon::parse($event->event_at)->toDateString() > $due) continue;
                $candidate = Carbon::parse($source->tanggal_sampling)->addDays(max(0, SamplerTrackingActivity::duration($event->member) - 1))->toDateString();
                if ($candidate <= $due) continue;
                $links[] = ['session_id' => (int) $source->id, 'order' => $source->no_order,
                    'event_id' => (int) $event->id, 'checkin_at' => (string) $event->event_at,
                    'duration' => SamplerTrackingActivity::duration($event->member), 'previous_due' => $due, 'due_date' => $candidate];
                $due = $candidate;
            }
            $cursor = $window;
            $inclusive = false;
        } while (!$stopped && $due > substr($window, 0, 10) && $window < $asOf->toDateTimeString());

        return $links ? ['sampler_id' => $member->sampler_id, 'name' => $member->sampler_name,
            'departure_event_id' => (int) $departure->id, 'departure_at' => (string) $departure->event_at,
            'base_due_date' => $baseDue, 'due_date' => $due, 'assignments' => $links] : null;
    }

    public function isComplete(SamplerTrackingMember $member, string $date): bool
    {
        $checkin = $member->events->where('event_type', 'checkin')->sortBy('event_at')->first();
        $checkout = $member->events->where('event_type', 'checkout')->sortByDesc('event_at')->first();
        if (!$member->sampler_id || !$checkin || !$checkout || !$checkin->event_at || !$checkout->event_at
            || Carbon::parse($checkout->event_at)->lt(Carbon::parse($checkin->event_at))) {
            return false;
        }

        $departure = $this->departureForMember($member, $date);
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
                if (!self::isJourneyBoundary($event) || (string) $event->event_at > $checkinAt) {
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
                if (!self::isJourneyBoundary($event) || (string) $event->event_at < $minAt) {
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
                if (!self::isJourneyBoundary($event) || $eventAt <= $departureAt) {
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
            self::$samplerEventsCache[$key] = $this->eventsQueryForSampler($samplerId)->with('member.session')
                ->orderBy('event_at')->orderBy('id')
                ->get();
        }

        return self::$samplerEventsCache[$key];
    }

    /** Keep the query separate from the materialized event cache. */
    protected function eventsQueryForSampler($samplerId): \Illuminate\Database\Eloquent\Builder
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
