<?php

namespace App\Services;

use App\Models\SamplerTrackingEvent;
use App\Models\SamplerTrackingMember;
use Carbon\Carbon;

/** Read-only journey matching for trouble assessment. */
class SamplerTrackingJourney
{
    /** Only started assignments may extend an open journey; schedules alone cannot. */
    public function extension(SamplerTrackingMember $member, string $date, string $baseDue, $asOf = null): ?array
    {
        $checkin = $member->events->where('event_type', 'checkin')->sortBy('event_at')->first();
        if (!$member->sampler_id || !$checkin || !$checkin->event_at) return null;
        $asOf = $asOf ? Carbon::parse($asOf, 'Asia/Jakarta') : Carbon::now('Asia/Jakarta');
        $asOf = $asOf->min(Carbon::now('Asia/Jakarta'));
        if (Carbon::parse($checkin->event_at, 'Asia/Jakarta')->gt($asOf)) return null;
        $departure = $this->eventsForSampler($member->sampler_id)->with('member.session')
            ->where('event_type', 'departure')->where('event_at', '<=', $checkin->event_at)
            ->whereHas('member.session', function ($query) use ($date) {
                $query->whereDate('tanggal_sampling', '<=', $date);
            })->orderByDesc('event_at')->orderByDesc('id')->first();
        if (!$departure || !$departure->member || !$departure->member->session) return null;
        $departureDate = $departure->member->session->tanggal_sampling;
        if ($this->eventsForSampler($member->sampler_id)->where('event_type', 'return')
            ->where('event_at', '>=', $departure->event_at)->where('event_at', '<', $checkin->event_at)
            ->whereHas('member.session', function ($query) use ($departureDate) {
                $query->whereDate('tanggal_sampling', '>=', $departureDate);
            })->exists()) return null;
        $due = $baseDue;
        $cursor = (string) $checkin->event_at;
        $inclusive = true;
        $links = [];
        do {
            // Advance in deadline windows, never scan an unbounded year of history.
            $window = Carbon::parse($due, 'Asia/Jakarta')->endOfDay()->min($asOf)->toDateTimeString();
            if ($window < $cursor) break;
            $events = $this->eventsForSampler($member->sampler_id)->with('member.session')
                ->where('event_at', $inclusive ? '>=' : '>', $cursor)->where('event_at', '<=', $window)
                ->orderBy('event_at')->orderBy('id')->get();
            $stopped = false;
            foreach ($events as $event) {
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
