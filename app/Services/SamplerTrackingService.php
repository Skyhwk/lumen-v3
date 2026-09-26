<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\SamplingPlan;
use App\Models\OrderHeader;
use App\Models\PersiapanSampelHeader;
use App\Models\SamplerTrackingEvent;
use App\Models\SamplerTrackingMember;
use App\Models\SamplerTrackingSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SamplerTrackingService
{
    protected $columnsByTable = [];
    protected $orderNumbersByQuotation = [];

    protected function now()
    {
        return Carbon::now('Asia/Jakarta');
    }

    protected function today()
    {
        return $this->now()->toDateString();
    }

    public function sync($date = null)
    {
        $date = Carbon::parse($date ?: $this->today())->toDateString();

        return DB::transaction(function () use ($date) {
            foreach (SamplerTrackingSession::whereDate('tanggal_sampling', $date)->pluck('no_quotation')->filter()->unique() as $quotation) {
                $this->reconcileQuotationRevision($quotation);
            }
            $jadwals = Jadwal::where('is_active', true)
                ->whereDate('tanggal', $date)
                ->lockForUpdate()->get();

            return $this->syncJadwalRows($jadwals, $date, true);
        }, 5);
    }

    public function previewSync($date = null)
    {
        $date = $date ?: $this->today();
        $date = Carbon::parse($date)->toDateString();

        $jadwals = Jadwal::where('is_active', true)
            ->whereDate('tanggal', $date)
            ->get();

        $groups = $jadwals->groupBy(function ($row) {
            return $this->makeTeamKey($row);
        });

        $teamKeys = $groups->keys()->values();
        $existingSessions = SamplerTrackingSession::with('activeMembers')->whereDate('tanggal_sampling', $date)->get();
        $activeSessions = $existingSessions->where('is_active', true)->values();
        $existingByTeamKey = $activeSessions->keyBy('team_key');
        $replacementMapping = $this->replacementMapping($activeSessions, $jadwals);
        $reconciledTargetKeys = collect($replacementMapping)->values()->unique();
        $reconciliations = collect($replacementMapping)->map(function ($newKey, $oldKey) use ($existingByTeamKey, $groups) {
            $session = $existingByTeamKey->get($oldKey);
            $rows = $groups->get($newKey, collect());
            $first = $rows->first();
            $replacement = SamplerTrackingSession::where('team_key', $newKey)
                ->where('id', '!=', $session->id)
                ->first();

            return [
                'session_id' => $session->id,
                'replacement_session_id' => $replacement ? $replacement->id : null,
                'team_key' => $newKey,
                'no_quotation' => $first->no_quotation,
                'tanggal_sampling' => $first->tanggal,
                'jam' => trim(($first->jam_mulai ?: '-') . ' - ' . ($first->jam_selesai ?: '-')),
                'nama_perusahaan' => $first->nama_perusahaan,
                'sampler' => $rows->pluck('sampler')->filter()->unique()->values()->implode(', '),
                'perubahan' => 'Session #' . $session->id . ' dipertahankan; event dan trouble tetap di session ini.'
                    . ($replacement ? ' Session #' . $replacement->id . ' akan diarsipkan.' : ''),
            ];
        })->values();
        $missing = collect();
        $existing = collect();
        $changed = collect();

        foreach ($groups as $teamKey => $rows) {
            $first = $rows->first();
            $item = [
                'team_key' => $teamKey,
                'no_quotation' => $first->no_quotation,
                'tanggal_sampling' => $first->tanggal,
                'jam' => trim(($first->jam_mulai ?: '-') . ' - ' . ($first->jam_selesai ?: '-')),
                'nama_perusahaan' => $first->nama_perusahaan,
                'sampler' => $rows->pluck('sampler')->filter()->unique()->values()->implode(', '),
                'jumlah_jadwal' => $rows->count(),
            ];

            if ($existingByTeamKey->has($teamKey)) {
                $session = $existingByTeamKey->get($teamKey);
                $item['session_id'] = $session->id;
                $existing->push($item);
                $reasons = $this->syncDifferences($session, $rows);
                if ($reasons) {
                    $item['sampler_sebelumnya'] = $session->activeMembers->pluck('sampler_name')->implode(', ');
                    $item['perubahan'] = implode('; ', $reasons);
                    $changed->push($item);
                }
            } elseif (!$reconciledTargetKeys->contains($teamKey)) {
                $missing->push($item);
            }
        }

        $willDeactivate = $activeSessions
                ->filter(function ($session) use ($teamKeys, $replacementMapping) {
                    return !$teamKeys->contains($session->team_key)
                        && !array_key_exists($session->team_key, $replacementMapping);
                })
                ->map(function ($session) {
                    return [
                        'session_id' => $session->id,
                        'team_key' => $session->team_key,
                        'no_quotation' => $session->no_quotation,
                        'no_order' => $session->no_order,
                        'tanggal_sampling' => $session->tanggal_sampling,
                        'jam' => trim(($session->jam_mulai ?: '-') . ' - ' . ($session->jam_selesai ?: '-')),
                        'nama_perusahaan' => $session->nama_perusahaan,
                    ];
                })
                ->values();

        return [
            'tanggal' => $date,
            'total_jadwal' => $jadwals->count(),
            'total_team_jadwal' => $groups->count(),
            'total_session_aktif' => $activeSessions->count(),
            'sudah_ada' => $existing->count(),
            'sudah_sesuai' => $existing->count() - $changed->count(),
            'perlu_diperbarui' => $changed->count(),
            'akan_direkonsiliasi' => $reconciliations->count(),
            'belum_kebentuk' => $missing->count(),
            'akan_dinonaktifkan' => $willDeactivate->count(),
            'preview' => [
                'perlu_diperbarui' => $changed->values(),
                'akan_direkonsiliasi' => $reconciliations->take(20)->values(),
                'belum_kebentuk' => $missing->take(20)->values(),
                'akan_dinonaktifkan' => $willDeactivate->take(20)->values(),
            ],
        ];
    }

    protected function syncDifferences($session, $rows)
    {
        $expected = $rows->map(function ($row) {
            return [(string) $row->userid, (string) $row->sampler, (int) $row->durasi,
                (int) $row->durasi_personal, (int) $this->resolveEffectiveDuration($row->durasi_personal, $row->durasi)];
        })->unique()->sort()->values()->all();
        $actual = $session->activeMembers->map(function ($member) {
            return [(string) $member->sampler_id, (string) $member->sampler_name, (int) ($member->duration ?? $member->durasi),
                (int) $member->durasi_personal, (int) $member->effective_duration];
        })->sort()->values()->all();
        $reasons = $expected !== $actual ? ['Anggota atau durasi tim berubah'] : [];
        $first = $rows->first();
        foreach (['nama_perusahaan' => 'nama_perusahaan', 'alamat_sampling' => 'alamat', 'driver' => 'driver', 'durasi' => 'durasi'] as $target => $source) {
            if ((string) $session->$target !== (string) $first->$source) $reasons[] = $target . ' berubah';
        }
        if (json_decode($session->kategori ?: 'null', true) != json_decode($this->normalizeJson($first->kategori) ?: 'null', true)) $reasons[] = 'Kategori berubah';
        return $reasons;
    }

    public function syncByPersiapanHeader(PersiapanSampelHeader $psh)
    {
        if (!$psh->no_quotation || !$psh->tanggal_sampling) {
            return collect();
        }

        // Only saving preparation may create sessions. Select its teams, then
        // synchronize complete team membership rather than a sampler subset.
        return DB::transaction(function () use ($psh) {
            $samplers = $this->parseSamplerNames($psh->sampler_jadwal ?? null);
            $date = Carbon::parse($psh->tanggal_sampling)->toDateString();
            $creationKeys = $this->snapshotSchedules($psh->no_quotation)
                ->filter(function ($row) use ($date, $samplers) {
                    return $row->tanggal === $date
                        && (count($samplers) === 0 || in_array($row->sampler, $samplers, true));
                })->map(function ($row) { return $this->makeTeamKey($row); })->unique()->all();

            return $this->syncQuotation($psh->no_quotation, $creationKeys);
        });
    }

    public function snapshotSchedules($quotation)
    {
        return Jadwal::where('no_quotation', $quotation)
            ->where('is_active', true)->get();
    }

    public function syncQuotation($quotation, array $creationKeys = [])
    {
        return DB::transaction(function () use ($quotation, $creationKeys) {
            $revised = $this->reconcileQuotationRevision($quotation);
            $rows = $this->snapshotSchedules($quotation);
            $dates = $rows->pluck('tanggal')->merge(
                SamplerTrackingSession::where('no_quotation', $quotation)->pluck('tanggal_sampling')
            )->filter()->unique();
            $sessions = collect();
            foreach ($dates as $date) {
                $sessions = $sessions->merge($this->syncJadwalRows(
                    $rows->where('tanggal', $date), $date, true, $quotation, $creationKeys
                ));
            }

            foreach ($revised as $target) {
                if ($target !== $quotation) $sessions = $sessions->merge($this->syncQuotation($target));
            }
            return $sessions;
        });
    }

    /** Revision recovery requires the same sampling plan and exact original visit key. */
    protected function reconcileQuotationRevision($quotation)
    {
        $plans = Jadwal::where('no_quotation', $quotation)->where('is_active', true)->pluck('id_sampling')->filter()->unique()->all();
        $sessions = SamplerTrackingSession::where(function ($query) use ($quotation, $plans) {
            $query->where('no_quotation', $quotation);
            if ($plans) $query->orWhereIn('id_sampling', $plans);
        // A superseded revision has already been archived. It must not be
        // reconciled again merely because it shares the sampling-plan ID with
        // the current preparation; doing so can roll back the real active
        // session's revision update.
        })->where('is_active', true)->lockForUpdate()->get();
        $revised = [];
        foreach ($sessions as $session) {
            if (!$session->id_sampling || !$session->no_quotation) continue;
            $root = preg_replace('/R\d+$/', '', $session->no_quotation);
            preg_match('/R(\d+)$/', $session->no_quotation, $oldRevision);
            $candidates = Jadwal::where('id_sampling', $session->id_sampling)->where('is_active', true)
                ->where('no_quotation', '!=', $session->no_quotation)->lockForUpdate()->get()
                ->filter(function ($row) use ($session, $root, $oldRevision) {
                    if (preg_replace('/R\d+$/', '', $row->no_quotation) !== $root) return false;
                    preg_match('/R(\d+)$/', $row->no_quotation, $revision);
                    if ((int) ($revision[1] ?? 0) <= (int) ($oldRevision[1] ?? 0)) return false;
                    $old = clone $row;
                    $old->no_quotation = $session->no_quotation;

                    // Sessions created before order-based identity still have
                    // the quotation-based key. Accept that legacy key here so
                    // a QT revision can be migrated without losing its events.
                    return $this->makeTeamKey($old) === $session->team_key
                        || $this->makeLegacyTeamKey($old) === $session->team_key;
                })->groupBy(function ($row) { return $this->makeTeamKey($row); });
            if ($candidates->isEmpty()) continue;
            if ($candidates->count() !== 1) {
                throw ValidationException::withMessages(['jadwal' => ['Revisi QT memiliki beberapa kandidat activity. Riwayat tidak dipindahkan.']]);
            }
            $target = $candidates->first()->first();
            $this->rekeySessions([$session->team_key => $this->makeTeamKey($target)]);
            SamplerTrackingSession::where('id', $session->id)->update([
                'no_quotation' => $target->no_quotation,
                'no_order' => $this->resolveOrderNumber($target),
            ]);
            $revised[] = $target->no_quotation;
        }
        return array_values(array_unique($revised));
    }

    /** Call after jadwal di-void; snapshot diambil saat baris jadwal masih aktif. */
    public function syncAfterScheduleVoid($quotation, $beforeSnapshot)
    {
        $before = $beforeSnapshot instanceof Collection
            ? $beforeSnapshot
            : collect($beforeSnapshot);

        return $this->syncScheduleEdit($before, $quotation);
    }

    /**
     * Penutup void dari menu jadwal: nonaktifkan session yang tersisa bila visit/tanggal
     * sudah tidak punya jadwal aktif (mis. team_key lama vs baru karena kendaraan null).
     */
    public function finalizeMenuJadwalVoid(Collection $voidedRows): void
    {
        if ($voidedRows->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($voidedRows) {
            foreach ($voidedRows->groupBy(function ($row) {
                $date = $row->tanggal ? Carbon::parse($row->tanggal)->toDateString() : 'date-null';

                return ($row->no_quotation ?? '') . '|' . $date;
            }) as $group) {
                $first = $group->first();
                $quotation = $first->no_quotation ?? null;
                if (!$quotation || !$first->tanggal) {
                    continue;
                }
                $date = Carbon::parse($first->tanggal)->toDateString();

                $stillActiveOnDate = Jadwal::where('no_quotation', $quotation)
                    ->whereDate('tanggal', $date)
                    ->where('is_active', true)
                    ->exists();

                if (!$stillActiveOnDate) {
                    $this->deactivateAllSessionsForQuotationDate($quotation, $date);
                    continue;
                }

                foreach ($group as $row) {
                    $this->deactivateSessionIfNoActiveJadwalForTeamKey($quotation, $row);
                }
            }
        }, 5);
    }

    protected function deactivateAllSessionsForQuotationDate(string $quotation, string $date): void
    {
        $sessionUpdate = $this->onlyExistingColumns((new SamplerTrackingSession())->getTable(), ['is_active' => false]);
        if ($sessionUpdate === []) {
            return;
        }

        $sessionIds = SamplerTrackingSession::where('no_quotation', $quotation)
            ->whereDate('tanggal_sampling', $date)
            ->where('is_active', true)
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return;
        }

        SamplerTrackingSession::whereIn('id', $sessionIds)->update($sessionUpdate);

        $memberUpdate = $this->onlyExistingColumns((new SamplerTrackingMember())->getTable(), ['is_active' => false]);
        if ($memberUpdate !== []) {
            SamplerTrackingMember::whereIn('sampler_tracking_session_id', $sessionIds)->update($memberUpdate);
        }
    }

    protected function deactivateSessionIfNoActiveJadwalForTeamKey(string $quotation, $row): void
    {
        $teamKey = $this->makeTeamKey($row);
        $legacyKey = $this->makeLegacyTeamKey($row);
        $visit = $this->visitIdentity($row);
        $date = Carbon::parse($row->tanggal)->toDateString();

        $stillActiveForTeam = Jadwal::where('no_quotation', $quotation)
            ->where('is_active', true)
            ->get()
            ->contains(function ($jadwal) use ($teamKey) {
                return $this->makeTeamKey($jadwal) === $teamKey;
            });

        if ($stillActiveForTeam) {
            return;
        }

        $sessions = SamplerTrackingSession::where('no_quotation', $quotation)
            ->whereDate('tanggal_sampling', $date)
            ->where('is_active', true)
            ->get()
            ->filter(function ($session) use ($teamKey, $legacyKey, $visit) {
                return $session->team_key === $teamKey
                    || $session->team_key === $legacyKey
                    || $this->visitIdentity($session) === $visit;
            });

        foreach ($sessions as $session) {
            $session->fill($this->onlyExistingColumns($session->getTable(), ['is_active' => false]));
            $session->save();

            $memberUpdate = $this->onlyExistingColumns((new SamplerTrackingMember())->getTable(), ['is_active' => false]);
            if ($memberUpdate !== []) {
                SamplerTrackingMember::where('sampler_tracking_session_id', $session->id)->update($memberUpdate);
            }
        }
    }

    /** Call after the schedule transaction commits, using its pre-edit snapshot. */
    public function syncScheduleEdit($before, $quotation)
    {
        $after = $this->snapshotSchedules($quotation);
        $affectedScopes = $this->changedScheduleScopes($before, $after);
        if (empty($affectedScopes)) {
            return collect();
        }
        $affectedDates = array_keys($affectedScopes);

        $previousMembers = SamplerTrackingSession::with('activeMembers.events')
            ->where('no_quotation', $quotation)
            ->whereIn('tanggal_sampling', $affectedDates)
            ->where('is_active', true)->get()
            ->mapWithKeys(function ($session) { return [$session->id => $session->activeMembers]; });
        $keyFor = function ($row) { return $this->makeTeamKey($row); };
        $mapping = SamplerTrackingScheduleIdentity::replacements($before, $after, $keyFor);
        $afterById = $after->keyBy('id');
        $editedKeys = $before->filter(function ($row) use ($afterById) {
            $current = $afterById->get($row->id);
            return !$current || $current->getAttributes() !== $row->getAttributes();
        })->map(function ($row) use ($keyFor, $mapping) {
            $key = $keyFor($row);
            return $mapping[$key] ?? $key;
        });
        $this->rekeySessions($mapping);

        $sessions = $this->syncQuotationDates($quotation, $after, $affectedScopes);
        foreach ($sessions as $session) {
            if ($editedKeys->contains($session->team_key) && $previousMembers->has($session->id)) {
                $this->inheritCorrectedTeamEvents($session, $previousMembers->get($session->id));
            }
        }

        return $sessions;
    }

    /** A schedule edit corrects the original team; a new visit never uses this path. */
    protected function inheritCorrectedTeamEvents($session, $previousMembers)
    {
        $members = $session->activeMembers()->with('events')->get();
        $activeIds = $members->pluck('id');
        $donors = $previousMembers->filter(function ($member) use ($activeIds) {
            return $activeIds->contains($member->id) && $member->events->isNotEmpty();
        });
        if ($donors->isEmpty()) {
            $donors = $previousMembers->filter(function ($member) { return $member->events->isNotEmpty(); });
        }

        foreach ($members as $member) {
            $wasActive = $previousMembers->contains('id', $member->id);
            if ($wasActive && $member->events->isNotEmpty()) {
                continue;
            }
            $events = $donors->flatMap(function ($donor) use ($member) {
                return $donor->events->filter(function ($event) use ($donor, $member) {
                    return $this->canInheritTeamEvent(
                        $event->event_type,
                        $donor->effective_duration,
                        $member->effective_duration
                    );
                });
            })->sortBy('id')->sortBy('event_at')->unique('event_type');

            foreach ($events as $event) {
                $existing = $member->events()->where('event_type', $event->event_type)->first();
                if ($existing) {
                    if ((int) $existing->is_auto === 1
                        && strpos((string) $existing->note, 'sumber event #' . $event->id) === false) {
                        $existing->note = $this->inheritedTeamEventNote($event, 'Koreksi anggota sejak awal');
                        $existing->save();
                    }
                    continue;
                }
                $values = $event->getAttributes();
                unset($values['id'], $values['created_at'], $values['updated_at']);
                $values['sampler_tracking_member_id'] = $member->id;
                $values['triggered_by_member_id'] = $event->triggered_by_member_id ?: $event->sampler_tracking_member_id;
                $values['is_auto'] = true;
                $values['sequence_no'] = $this->nextSequence($member->id);
                $values['note'] = $this->inheritedTeamEventNote($event, 'Koreksi anggota sejak awal');
                SamplerTrackingEvent::create($this->onlyExistingColumns((new SamplerTrackingEvent())->getTable(), $values));
            }
        }
    }

    protected function rekeySessions(array $mapping)
    {
        foreach ($mapping as $oldKey => $newKey) {
            $session = SamplerTrackingSession::where('team_key', $oldKey)->lockForUpdate()->first();
            if (!$session) {
                continue;
            }

            $replacement = SamplerTrackingSession::where('team_key', $newKey)
                ->where('id', '!=', $session->id)
                ->lockForUpdate()
                ->first();

            if ($replacement) {
                // A generic sync may already have created the replacement
                // session. Keep the original session as the canonical record
                // because events and trouble rows reference its ID, then fold
                // the empty/replacement members into it before changing keys.
                $this->mergeDuplicateSession($session, $replacement);

                // is_active does not release the unique team_key. Archive the
                // duplicate key before assigning the replacement identity to
                // the canonical session.
                $replacement->team_key = sha1('archived|' . $replacement->id . '|' . $newKey);
                $replacement->save();
            }

            $session->team_key = $newKey;
            $session->save();
        }
    }

    /** New visits must not inherit events from cancelled visits with the same times. */
    public function syncScheduleCreation($before, $quotation)
    {
        $after = $this->snapshotSchedules($quotation);
        $affectedScopes = $this->changedScheduleScopes($before, $after);
        if (empty($affectedScopes)) {
            return collect();
        }
        $affectedDates = array_keys($affectedScopes);
        $beforeIds = $before->pluck('id');
        $afterIds = $after->pluck('id');
        $creationKeys = [];
        // Adding a partial visit may also change the parent reference of
        // existing rows. Preserve those visits using their unchanged row IDs.
        $this->rekeySessions(SamplerTrackingScheduleIdentity::replacements(
            $before->whereIn('id', $afterIds->all()),
            $after->whereIn('id', $beforeIds->all()),
            function ($row) { return $this->makeTeamKey($row); }
        ));
        foreach ($after->groupBy(function ($row) { return $this->makeTeamKey($row); }) as $key => $rows) {
            if ($rows->pluck('id')->intersect($beforeIds)->isNotEmpty()) {
                continue;
            }
            $creationKeys[] = $key;
            $previous = SamplerTrackingSession::where('team_key', $key)->lockForUpdate()->first();
            if ($previous) {
                $previous->team_key = sha1('archived|' . $previous->id . '|' . $key);
                $previous->is_active = false;
                $previous->save();
            }
        }

        return $this->syncQuotationDates(
            $quotation,
            $after,
            $affectedScopes,
            array_values(array_unique($creationKeys))
        );
    }

    /**
     * Limit a Jadwal Service change to its touched visits. The schedule rows
     * still contain every sampler in those visits, so a team event remains
     * consistent without re-syncing other orders on the same day.
     */
    protected function syncQuotationDates($quotation, $after, array $scopesByDate, array $creationKeys = [])
    {
        $sessions = collect();
        foreach ($scopesByDate as $date => $scopeKeys) {
            $rows = $after->filter(function ($row) use ($date, $scopeKeys) {
                return Carbon::parse($row->tanggal)->toDateString() === $date
                    && in_array($this->makeTeamKey($row), $scopeKeys, true);
            });
            $sessions = $sessions->merge($this->syncJadwalRows(
                $rows,
                $date,
                true,
                $quotation,
                $creationKeys,
                $scopeKeys
            ));
        }

        return $sessions;
    }

    /** Return only old/new activity keys whose schedule or sampler membership changed. */
    protected function changedScheduleScopes($before, $after)
    {
        $beforeById = collect($before)->keyBy('id');
        $afterById = collect($after)->keyBy('id');
        $scopes = [];

        $addScope = function ($row) use (&$scopes) {
            if (!$row || !$row->tanggal) return;
            $date = Carbon::parse($row->tanggal)->toDateString();
            $scopes[$date] = $scopes[$date] ?? [];
            $scopes[$date][] = $this->makeTeamKey($row);
        };

        $beforeById->each(function ($row, $id) use ($afterById, $addScope) {
            $current = $afterById->get($id);
            if (!$current || $this->scheduleImpactFingerprint($row) !== $this->scheduleImpactFingerprint($current)) {
                $addScope($row);
                $addScope($current);
            }
        });

        $afterById->each(function ($row, $id) use ($beforeById, $addScope) {
            if (!$beforeById->has($id)) {
                $addScope($row);
            }
        });

        return collect($scopes)->map(function ($keys) {
            return array_values(array_unique($keys));
        })->all();
    }

    /** Includes the sampler, so member changes only touch the affected visit. */
    protected function scheduleImpactFingerprint($row)
    {
        return implode('|', [
            $this->makeTeamKey($row),
            $row->userid ?: 'sampler-id-null',
            $row->sampler ?: 'sampler-name-null',
            $row->durasi ?: 'duration-null',
            $row->durasi_personal ?: 'personal-duration-null',
        ]);
    }

    protected function syncJadwalRows($jadwals, $date, $deactivateMissingSessions = false, $quotation = null, array $creationKeys = [], array $scopeTeamKeys = [])
    {
        $now = $this->now();
        $sessions = [];
        $activeTeamKeys = [];

        DB::transaction(function () use ($jadwals, $now, &$sessions, &$activeTeamKeys, $date, $deactivateMissingSessions, $quotation, $creationKeys, $scopeTeamKeys) {
            $this->reconcileReplacedSessions($jadwals, $date, $quotation);

            $jadwals->groupBy(function ($row) {
                return $this->makeTeamKey($row);
            })->each(function ($rows, $teamKey) use ($now, &$sessions, &$activeTeamKeys, $creationKeys) {
                $activeTeamKeys[] = $teamKey;
                $session = $this->findSession($teamKey);
                if (!$session->exists && !in_array($teamKey, $creationKeys, true)) {
                    return;
                }
                $first = $rows->first();
                $orderHeader = OrderHeader::where('no_document', $first->no_quotation)
                    ->where('is_active', true)
                    ->first();
                $samplingPlan = SamplingPlan::find($first->id_sampling);

                $session->fill($this->onlyExistingColumns($session->getTable(), [
                    'id_sampling' => $first->id_sampling,
                    'parsial' => $first->parsial,
                    'no_quotation' => $first->no_quotation,
                    'no_order' => $this->resolveOrderNumber($first),
                    'tanggal_sampling' => $first->tanggal,
                    'jam_mulai' => $first->jam_mulai,
                    'jam_selesai' => $first->jam_selesai,
                    'durasi' => $first->durasi,
                    'kendaraan' => $first->kendaraan,
                    'driver' => $first->driver,
                    'nama_perusahaan' => $first->nama_perusahaan,
                    'alamat_sampling' => $first->alamat ?? ($orderHeader->alamat_sampling ?? null),
                    'google_maps_url' => $samplingPlan->google_maps_url ?? null,
                    'kategori' => $this->normalizeJson($first->kategori),
                    'status' => $session->status ?: 'scheduled',
                    'is_active' => true,
                    'synced_at' => $now,
                ]));
                $session->save();

                $activeMemberIds = [];
                $movementGroup = $this->makeMovementGroupCode($session);

                foreach ($rows as $row) {
                    $member = $this->findMember($session->id, $row);
                    $effectiveDuration = $this->resolveEffectiveDuration($row->durasi_personal, $row->durasi);

                    $memberValues = $this->onlyExistingColumns($member->getTable(), [
                        'sampler_tracking_session_id' => $session->id,
                        'sampler_id' => $row->userid,
                        'sampler_name' => $row->sampler,
                        'duration' => $row->durasi,
                        'durasi' => $row->durasi,
                        'durasi_personal' => $row->durasi_personal,
                        'effective_duration' => $effectiveDuration,
                        'current_movement_group' => $member->current_movement_group ?: $movementGroup,
                        'is_active' => true,
                    ]);

                    if ($member->exists) {
                        $member->fill($memberValues);
                        $member->save();
                    } else {
                        try {
                            $member = SamplerTrackingMember::create($memberValues);
                        } catch (\Illuminate\Database\QueryException $exception) {
                            $member = $this->findMember($session->id, $row);
                            if (!$member->exists) {
                                throw $exception;
                            }

                            $member->fill($memberValues);
                            $member->save();
                        }
                    }

                    // A sampler can be assigned after the team has already
                    // departed or checked in. Give the new/current member the
                    // same completed team milestones so their activity starts
                    // from the team's actual progress, not from an empty form.
                    $this->backfillTeamEventsForMember($member);

                    $activeMemberIds[] = $member->id;
                }

                $inactiveUpdate = $this->onlyExistingColumns((new SamplerTrackingMember())->getTable(), ['is_active' => false]);
                if (count($inactiveUpdate) > 0) {
                    SamplerTrackingMember::where('sampler_tracking_session_id', $session->id)
                        ->when(count($activeMemberIds) > 0, function ($query) use ($activeMemberIds) {
                            $query->whereNotIn('id', $activeMemberIds);
                        })
                        ->update($inactiveUpdate);
                }

                $sessions[] = $session;
            });

            if ($deactivateMissingSessions) {
                $sessionInactiveUpdate = $this->onlyExistingColumns((new SamplerTrackingSession())->getTable(), ['is_active' => false]);
                if (count($sessionInactiveUpdate) > 0) {
                    SamplerTrackingSession::where('tanggal_sampling', $date)
                        ->when($quotation !== null, function ($query) use ($quotation) {
                            $query->where('no_quotation', $quotation);
                        })
                        ->when(count($activeTeamKeys) > 0, function ($query) use ($activeTeamKeys) {
                            $query->whereNotIn('team_key', $activeTeamKeys);
                        })
                        ->when(count($scopeTeamKeys) > 0, function ($query) use ($scopeTeamKeys) {
                            $query->whereIn('team_key', $scopeTeamKeys);
                        })
                        ->update($sessionInactiveUpdate);
                    $inactiveSessionIds = SamplerTrackingSession::whereDate('tanggal_sampling', $date)
                        ->where('is_active', false)->pluck('id');
                    SamplerTrackingMember::whereIn('sampler_tracking_session_id', $inactiveSessionIds)
                        ->update(['is_active' => false]);
                }
            }
        }, 5);

        return collect($sessions);
    }

    /**
     * Reconcile a previously-created replacement during a manual/date sync.
     * Only a one-to-one match of the same visit may carry history forward;
     * ambiguous same-day visits remain separate instead of sharing events.
     */
    protected function reconcileReplacedSessions($jadwals, $date, $quotation = null)
    {
        if ($jadwals->isEmpty()) {
            return;
        }

        $sessions = SamplerTrackingSession::whereDate('tanggal_sampling', $date)
            ->where('is_active', true)
            ->when($quotation !== null, function ($query) use ($quotation) {
                $query->where('no_quotation', $quotation);
            })
            ->lockForUpdate()
            ->get();

        $mapping = $this->replacementMapping($sessions, $jadwals);
        if ($mapping) {
            $this->rekeySessions($mapping);
        }
    }

    protected function replacementMapping($sessions, $jadwals)
    {
        $currentKeys = $jadwals->map(function ($row) {
            return $this->makeTeamKey($row);
        })->unique();

        $replacementKeysByVisit = $jadwals->groupBy(function ($row) {
            return $this->visitIdentity($row);
        })->map(function ($rows) {
            return $rows->map(function ($row) {
                return $this->makeTeamKey($row);
            })->unique()->values();
        });

        $legacyByVisit = collect($sessions)->filter(function ($session) use ($currentKeys) {
            return !$currentKeys->contains($session->team_key);
        })->groupBy(function ($session) {
            return $this->visitIdentity($session);
        });

        $mapping = [];
        foreach ($legacyByVisit as $visit => $legacySessions) {
            $replacementKeys = $replacementKeysByVisit->get($visit, collect());
            // Do not guess when one visit was split, combined, or duplicated.
            if ($legacySessions->count() !== 1 || $replacementKeys->count() !== 1) {
                continue;
            }

            $mapping[$legacySessions->first()->team_key] = $replacementKeys->first();
        }

        return $mapping;
    }

    protected function visitIdentity($row)
    {
        $date = isset($row->tanggal_sampling) && $row->tanggal_sampling
            ? $row->tanggal_sampling
            : (isset($row->tanggal) && $row->tanggal ? $row->tanggal : 'date-null');

        return implode('|', [
            $row->id_sampling ?: 'sampling-null',
            $row->parsial ?: 'parsial-null',
            $this->resolveOrderNumber($row) ?: ('qt:' . ($row->no_quotation ?: 'null')),
            $date,
            $row->jam_mulai ?: 'start-null',
            $row->jam_selesai ?: 'end-null',
            mb_strtolower(trim((string) ($row->nama_perusahaan ?: 'company-null'))),
        ]);
    }
    public function listByDate($date = null, $samplerId = null, $samplerName = null, $sessionIds = null)
    {
        $date = !empty($date)
            ? Carbon::parse($date)->toDateString()
            : $this->today();
        $hasSamplerFilter = !empty($samplerId) || !empty($samplerName);
        $memberFilter = function ($query) use ($samplerId, $samplerName) {
            if ($samplerId) {
                $query->where('sampler_id', $samplerId);
            }

            if ($samplerName) {
                $query->where('sampler_name', 'like', '%' . $samplerName . '%');
            }
        };

        $dates = [$date];
        if ($hasSamplerFilter) {
            // Read only duration metadata for older work, not its full photo/event history.
            $ongoing = SamplerTrackingMember::with('session')->where('is_active', true)->where($memberFilter)
                ->whereHas('session', function ($query) use ($date) {
                    $query->where('is_active', true)
                        ->where(function ($q) {
                            $q->whereNull('nama_perusahaan')
                                ->orWhereRaw('LOWER(TRIM(nama_perusahaan)) != ?', ['cuti']);
                        })
                        ->whereDate('tanggal_sampling', '<', $date);
                })->get();
            foreach ($ongoing as $member) {
                if (Carbon::parse($member->session->tanggal_sampling)->addDays(max(0, SamplerTrackingActivity::duration($member) - 1))->toDateString() >= $date) {
                    $dates[] = $member->session->tanggal_sampling;
                }
            }
        }

        $sessions = SamplerTrackingSession::with([
            'activeMembers.events' => function ($query) {
                $query->orderBy('event_at')->orderBy('id');
            },
            'activeMembers.events.triggeredBy',
        ])
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('nama_perusahaan')
                    ->orWhereRaw('LOWER(TRIM(nama_perusahaan)) != ?', ['cuti']);
            })
            ->whereHas('activeMembers', $memberFilter)
            ->whereIn('tanggal_sampling', array_unique($dates))
            ->when($sessionIds !== null, function ($query) use ($sessionIds) { $query->whereIn('id', $sessionIds); })
            ->orderBy('tanggal_sampling')
            ->orderBy('jam_mulai')
            ->orderBy('nama_perusahaan')
            ->get();

        foreach ($sessions as $session) {
            foreach ($session->activeMembers as $member) {
                if (SamplerTrackingActivity::hasEvent($member, 'departure')) continue;
                $departure = $this->departureForMember($member, $session->tanggal_sampling);
                if ($departure) {
                    $reference = clone $departure;
                    $reference->is_journey_reference = true;
                    $member->setRelation('events', $member->events->prepend($reference)->values());
                }
            }
        }

        // Consolidate first so shorter orders at the same stop are not dropped during multi-day work.
        return $this->consolidateActivities($this->applyRouteOverrides($sessions, $date, $samplerId, $samplerName))
            ->filter(function ($session) use ($date, $samplerId, $samplerName) {
                if ($session->tanggal_sampling === $date) return true;
                return $session->activeMembers->contains(function ($member) use ($session, $date, $samplerId, $samplerName) {
                    $own = $samplerId ? (string) $member->sampler_id === (string) $samplerId : $member->sampler_name === $samplerName;
                    return $own && !SamplerTrackingActivity::hasEvent($member, 'return')
                        && Carbon::parse($session->tanggal_sampling)->addDays(max(0, SamplerTrackingActivity::duration($member) - 1))->toDateString() >= $date;
                });
            })->values();
    }
    public function departureForMember($member, $date)
    {
        $own = $member->events->firstWhere('event_type', 'departure');
        if ($own) return $own;
        if (!$member->sampler_id) return null;
        // A recorded check-in binds this visit to the journey active at arrival.
        $checkin = $member->events->where('event_type', 'checkin')->sortBy('event_at')->first();
        $at = $checkin ? $checkin->event_at : $this->now()->toDateTimeString();
        $latest = SamplerTrackingEvent::whereIn('event_type', ['departure', 'return'])
            ->where('event_at', '<=', $at)
            ->whereHas('member', function ($query) use ($member, $date) {
                $query->where('sampler_id', $member->sampler_id)->where('is_active', true)
                    ->whereHas('session', function ($session) use ($date) {
                        $session->where('is_active', true)->whereDate('tanggal_sampling', $date);
                    });
            })
            ->when($checkin, function ($query) use ($checkin) {
                $query->where(function ($before) use ($checkin) {
                    $before->where('event_at', '<', $checkin->event_at)
                        ->orWhere(function ($sameTime) use ($checkin) {
                            $sameTime->where('event_at', $checkin->event_at)->where('id', '<', $checkin->id);
                        });
                });
            })
            ->orderByDesc('event_at')->orderByDesc('id')->first();
        return $latest && $latest->event_type === 'departure' ? $latest : null;
    }

    public function consolidateActivities($sessions)
    {
        if ($sessions->isEmpty()) return $sessions;
        $orders = OrderHeader::whereIn('no_order', $sessions->pluck('no_order')->filter()->all())
            ->where('is_active', true)->get()->keyBy('no_order');
        $missingOrderSessions = $sessions->filter(function ($session) use ($orders) { return !$orders->has($session->no_order); });
        $quotations = $missingOrderSessions->isEmpty() ? collect() : OrderHeader::whereIn('no_document', $missingOrderSessions->pluck('no_quotation')->filter()->all())
            ->where('is_active', true)->get()->keyBy('no_document');
        $customers = [];
        foreach ($sessions as $session) {
            $customers[$session->id] = optional($orders->get($session->no_order) ?: $quotations->get($session->no_quotation))->id_pelanggan;
        }
        return SamplerTrackingActivity::consolidate($sessions, $customers);
    }
    public function dataTableByDate($request, $samplerId = null, $samplerName = null)
    {
        $date = $request->input('tanggal') ?: $this->today();
        $rows = $this->releaseUnblockedOverdueRows(
            $this->buildTrackingRows($this->listByDate($date, $samplerId, $samplerName))
        );
        $trackingStatusCounts = $this->trackingStatusCounts($rows);

        $rows = $this->filterByTrackingStatus($rows, $request->input('tracking_status'));
        $recordsTotal = $rows->count();

        $rows = $this->filterTrackingRows($rows, $request);
        $recordsFiltered = $rows->count();
        $rows = $this->sortTrackingRows($rows, $request)->values();

        $start = (int) ($request->start ?? 0);
        $length = (int) ($request->length ?? 25);
        if ($length > -1) {
            $rows = $rows->slice($start, $length)->values();
        }

        return [
            'draw' => (int) ($request->draw ?? 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'tracking_status_counts' => $trackingStatusCounts,
            'data' => $rows->values(),
        ];
    }

    public function listTrackingRows($date = null, $samplerId = null, $samplerName = null, $trackingStatus = null, $sessionIds = null)
    {
        $rows = $this->buildTrackingRows($this->listByDate($date, $samplerId, $samplerName, $sessionIds));
        $trackingStatusCounts = $this->trackingStatusCounts($rows);
        $rows = $this->filterByTrackingStatus($rows, $trackingStatus);

        return [
            'data' => $rows->values(),
            'tracking_status_counts' => $trackingStatusCounts,
        ];
    }

    // app/Services/SamplerTrackingService.php

public function buildTrackingRows($sessions)
{
    $sessionsByTeam = collect();

    foreach ($sessions as $session) {
        if (strtolower(trim($session->nama_perusahaan ?? '')) === 'cuti') {
            continue;
        }

        $date = $session->tanggal_sampling ?: '-';

        // A sampler with a different effective duration has an independent
        // checkout/return lifecycle, so it must be a separate monitor row.
        $session->activeMembers->groupBy(function ($member) {
            return (string) $this->firstFilledValue([
                $member->effective_duration,
                $member->durasi_personal,
                $member->duration,
                $member->durasi,
            ]);
        })->each(function ($members) use ($date, $session, $sessionsByTeam) {
            // Retain daily-route consolidation, but only for team members
            // whose effective duration is the same.
            $memberKeys = $members->map(function ($member) {
                return $member->sampler_id
                    ? 'id:' . $member->sampler_id
                    : 'name:' . mb_strtolower(trim((string) $member->sampler_name));
            })->unique()->sort()->values()->all();
            $duration = $this->firstFilledValue([
                optional($members->first())->effective_duration,
                optional($members->first())->durasi_personal,
                optional($members->first())->duration,
                optional($members->first())->durasi,
            ]);
            $teamKey = $memberKeys
                ? json_encode([$date, $memberKeys, 'duration:' . (string) $duration])
                : ($date . '|session-' . $session->id . '|duration:' . (string) $duration);

            if (!$sessionsByTeam->has($teamKey)) {
                $sessionsByTeam->put($teamKey, [
                    'group_key' => $teamKey,
                    'date' => $date,
                    'session' => $session,
                    'sessions' => collect(),
                    'members' => collect(),
                    'samplers' => collect(),
                    'events' => collect(),
                    'no_orders' => collect(),
                    'perusahaan' => collect(),
                    'durations' => collect(),
                    'duration_values' => collect(),
                    'movement_groups' => collect(),
                    'statuses' => collect(),
                    'jam_mulai' => null,
                    'jam_selesai' => null,
                ]);
            }

            $item = $sessionsByTeam->get($teamKey);
            $item['sessions']->push($session);

            foreach ($members as $member) {
                $samplerName = $member->sampler_name ?: ($member->sampler_id ?: '-');
                $item['members']->push($member);
                $item['samplers']->push($samplerName);
                $item['events'] = $item['events']->merge($member->events ?: collect());
                $item['durations']->push($this->durationLabel($this->firstFilledValue([$member->effective_duration, $member->durasi_personal, $member->duration, $member->durasi])));
                $item['duration_values']->push($this->firstFilledValue([$member->effective_duration, $member->durasi_personal, $member->duration, $member->durasi]));
                $item['movement_groups']->push($member->current_movement_group ?: '-');
            }

            $item['no_orders']->push($session->no_order ?: ($session->no_quotation ?: '-'));
            $item['perusahaan']->push($session->nama_perusahaan ?: '-');
            $item['statuses']->push($session->status ?: '-');

            if ($session->jam_mulai && (!$item['jam_mulai'] || $session->jam_mulai < $item['jam_mulai'])) {
                $item['jam_mulai'] = $session->jam_mulai;
            }

            if ($session->jam_selesai && (!$item['jam_selesai'] || $session->jam_selesai > $item['jam_selesai'])) {
                $item['jam_selesai'] = $session->jam_selesai;
            }

            $sessionsByTeam->put($teamKey, $item);
        });
    }

    // Map output per baris
    return $sessionsByTeam->values()->map(function ($row) {
        $row['events'] = $row['events']->unique('id')->values();
        $noOrders = $this->uniqueValues($row['no_orders']);
        $perusahaan = $this->uniqueValues($row['perusahaan']);
        $samplers = $this->uniqueValues($row['samplers']);
        $durations = $this->uniqueValues($row['durations']);
        $statuses = $this->uniqueValues($row['statuses']);
        $movementGroups = $this->uniqueValues($row['movement_groups']);
        $lastEvent = $this->latestEvent($row['events']);

        return [
            'row_id' => $row['date'] . '-' . md5($row['group_key']),
            'group_key' => $row['group_key'],
            'session' => $row['session'],
            'member' => $row['members']->first(),
            'sessions' => $row['sessions']->values(),
            'members' => $row['members']->unique('id')->values(),
            'events' => $row['events']->values(),
            'tanggal_sampling' => $row['date'],
            'jam' => ($row['jam_mulai'] ?: '-') . ' - ' . ($row['jam_selesai'] ?: '-'),
            'jam_mulai' => $row['jam_mulai'],
            'jam_selesai' => $row['jam_selesai'],
            'no_order' => count($noOrders) > 0 ? implode(', ', $noOrders) : '-',
            'perusahaan_list' => $perusahaan,
            'nama_perusahaan' => count($perusahaan) > 0 ? implode(', ', $perusahaan) : '-',
            'sampler_list' => $samplers,
            'samplers' => $samplers,
            'sampler' => count($samplers) > 0 ? implode(', ', $samplers) : '-',
            'durasi' => count($durations) > 0 ? implode(', ', $durations) : '-',
            'movement_group' => $this->teamMovementCode($row['date'], $row['group_key']),
            'internal_movement_groups' => $movementGroups,
            'total_event' => $row['events']->count(),
            'last_event' => $lastEvent ? (($lastEvent->event_type ?: '-') . ' - ' . ($lastEvent->event_at ?: '-')) : '-',
            'status' => count($statuses) > 0 ? implode(', ', $statuses) : '-',
            'tracking_status' => $this->resolveTrackingStatus(
                $row['events'],
                $row['date'],
                $row['jam_mulai'],
                $row['jam_selesai'],
                $row['duration_values']->max()
            ),
        ];
    })->filter(function ($row) {
        return strtolower(trim($row['nama_perusahaan'] ?? '')) !== 'cuti';
    })->values();
}

    public function filterTrackingRows($rows, $request)
    {
        $globalSearch = strtolower(trim($request->input('search.value', '')));
        $columns = $request->input('columns', []);

        return $rows->filter(function ($row) use ($globalSearch, $columns) {
            $searchText = strtolower(implode(' ', [
                $row['tanggal_sampling'] ?? '',
                $row['jam'] ?? '',
                $row['no_order'] ?? '',
                $row['nama_perusahaan'] ?? '',
                $row['sampler'] ?? '',
                $row['durasi'] ?? '',
                $row['movement_group'] ?? '',
                $row['last_event'] ?? '',
                $row['status'] ?? '',
                $row['tracking_status'] ?? '',
            ]));

            if ($globalSearch && strpos($searchText, $globalSearch) === false) {
                return false;
            }

            foreach ($columns as $column) {
                $columnSearch = strtolower(trim($column['search']['value'] ?? ''));
                $data = $column['data'] ?? null;

                if (!$columnSearch || !$data || !array_key_exists($data, $row)) {
                    continue;
                }

                $value = strtolower(is_array($row[$data]) ? implode(' ', $row[$data]) : (string) $row[$data]);
                if (strpos($value, $columnSearch) === false) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    public function sortTrackingRows($rows, $request)
    {
        $order = $request->input('order.0');
        $columns = $request->input('columns', []);

        if (!$order) {
            return $rows->sortBy(function ($row) {
                return ($row['jam_mulai'] ?: '') . '|' . ($row['nama_perusahaan'] ?: '');
            });
        }

        $columnIndex = (int) ($order['column'] ?? 0);
        $direction = strtolower($order['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $data = $columns[$columnIndex]['data'] ?? 'tanggal_sampling';

        return $direction === 'desc'
            ? $rows->sortByDesc(function ($row) use ($data) {
                return $row[$data] ?? '';
            })
            : $rows->sortBy(function ($row) use ($data) {
                return $row[$data] ?? '';
            });
    }

    protected function uniqueValues($values)
    {
        return collect($values)->filter(function ($value) {
            return $value !== null && $value !== '' && $value !== '-';
        })
            ->unique()
            ->values()
            ->all();
    }

    protected function latestEvent($events)
    {
        return collect($events)->sortByDesc(function ($event) {
            return $event->event_at ? strtotime($event->event_at) : 0;
        })->first();
    }

    protected function parseSamplerNames($value)
    {
        if (!$value) {
            return [];
        }

        if (is_array($value)) {
            $items = $value;
        } else {
            $decoded = json_decode($value, true);
            $items = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? $decoded
                : preg_split('/[,;|]/', $value);
        }

        return collect($items)
            ->map(function ($item) {
                if (is_array($item)) {
                    return $item['sampler'] ?? $item['nama'] ?? $item['name'] ?? null;
                }

                return $item;
            })
            ->filter(function ($item) {
                return $item !== null && trim((string) $item) !== '';
            })
            ->map(function ($item) {
                return trim((string) $item);
            })
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveEffectiveDuration($personalDuration, $defaultDuration)
    {
        // Nilai 0 adalah durasi personal yang sah (sesaat), bukan nilai kosong.
        // Ini yang memungkinkan anggota tim punya durasi berbeda dari durasi tim.
        if ($personalDuration !== null && trim((string) $personalDuration) !== '') {
            return $personalDuration;
        }

        return $defaultDuration;
    }
    protected function firstFilledValue(array $values)
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
    protected function durationLabel($value)
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (!is_numeric($value)) {
            return $value;
        }

        $numberValue = (int) $value;
        if ($numberValue === 0) {
            return 'Sesaat';
        }

        if ($numberValue === 1) {
            return '8 Jam';
        }

        return ($numberValue - 1) . ' x 24 Jam';
    }

    public function resolveTrackingStatus($events, $tanggalSampling = null, $jamMulai = null, $jamSelesai = null, $durationValue = null, $now = null)
    {
        $hasReturn = collect($events)->contains(function ($event) {
            return $this->eventValue($event, 'event_type') === 'return';
        });

        if ($hasReturn) {
            return 'completed';
        }

        $dueAt = $this->resolveTrackingDueAt($events, $tanggalSampling, $jamMulai, $jamSelesai, $durationValue);
        $now = $now instanceof Carbon ? $now->copy() : $this->now();

        if ($dueAt && $now->gt($dueAt)) {
            return 'overdue';
        }

        return 'ongoing';
    }

    protected function normalizeTrackingStatus($value)
    {
        $status = strtolower(trim((string) $value));
        $aliases = [
            'ongoinh' => 'ongoing',
            'on-going' => 'ongoing',
            'on_going' => 'ongoing',
            'selesai' => 'completed',
            'belum_pulang' => 'overdue',
        ];
        $status = $aliases[$status] ?? $status;

        return in_array($status, ['ongoing', 'completed', 'overdue'], true) ? $status : null;
    }

    protected function filterByTrackingStatus($rows, $trackingStatus)
    {
        $status = $this->normalizeTrackingStatus($trackingStatus);
        if (!$status) {
            return $rows;
        }

        return $rows->filter(function ($row) use ($status) {
            return ($row['tracking_status'] ?? null) === $status;
        })->values();
    }

    /**
     * Activity yang sudah di-unblock atasan tidak boleh tetap berstatus overdue.
     * Pindahkan ke ongoing agar hilang dari tab Data Blocked.
     */
    public function releaseUnblockedOverdueRows($rows)
    {
        $rows = collect($rows);
        if ($rows->isEmpty() || !Schema::hasTable(SamplerTrackingTroubleService::TABLE)) {
            return $rows->values();
        }

        $samplerIds = $rows->flatMap(function ($row) {
            return $this->rowSamplerIds($row);
        })->unique()->values();
        $dates = $rows->map(function ($row) {
            return $this->rowActivityDate($row);
        })->filter()->unique()->values();

        if ($samplerIds->isEmpty() || $dates->isEmpty()) {
            return $rows->values();
        }

        $troubles = DB::table(SamplerTrackingTroubleService::TABLE)
            ->whereIn('sampler_id', $samplerIds)
            ->whereIn('activity_date', $dates)
            ->where('is_clear', 0)
            ->get(['sampler_id', 'activity_date', 'reopened_at']);

        $blocked = [];
        $unblocked = [];
        foreach ($troubles as $trouble) {
            $date = Carbon::parse($trouble->activity_date)->toDateString();
            $key = (string) $trouble->sampler_id . '|' . $date;
            if (!empty($trouble->reopened_at)) {
                $unblocked[$key] = true;
            } else {
                $blocked[$key] = true;
            }
        }

        if (empty($unblocked)) {
            return $rows->values();
        }

        return $rows->map(function ($row) use ($blocked, $unblocked) {
            if (($row['tracking_status'] ?? null) !== 'overdue') {
                return $row;
            }

            $date = $this->rowActivityDate($row);
            $memberIds = $this->rowSamplerIds($row);
            if (!$date || $memberIds->isEmpty()) {
                return $row;
            }

            $hasBlocked = $memberIds->contains(function ($samplerId) use ($blocked, $date) {
                return isset($blocked[(string) $samplerId . '|' . $date]);
            });
            if ($hasBlocked) {
                return $row;
            }

            $hasUnblocked = $memberIds->contains(function ($samplerId) use ($unblocked, $date) {
                return isset($unblocked[(string) $samplerId . '|' . $date]);
            });
            if ($hasUnblocked) {
                $row['tracking_status'] = 'ongoing';
            }

            return $row;
        })->values();
    }

    protected function rowSamplerIds($row)
    {
        return collect($row['members'] ?? [])
            ->map(function ($member) {
                return is_object($member) ? ($member->sampler_id ?? null) : ($member['sampler_id'] ?? null);
            })
            ->merge([
                is_object($row['member'] ?? null) ? ($row['member']->sampler_id ?? null) : ($row['member']['sampler_id'] ?? null),
            ])
            ->filter()
            ->unique()
            ->values();
    }

    protected function rowActivityDate($row)
    {
        $date = $row['tanggal_sampling'] ?? null;
        if (!$date || $date === '-') {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Exception $exception) {
            return null;
        }
    }

    protected function trackingStatusCounts($rows)
    {
        $counts = [
            'ongoing' => 0,
            'completed' => 0,
            'overdue' => 0,
        ];

        foreach ($rows as $row) {
            $status = $row['tracking_status'] ?? null;
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        $counts['all'] = $rows->count();

        return $counts;
    }

    protected function resolveTrackingDueAt($events, $tanggalSampling, $jamMulai, $jamSelesai, $durationValue)
    {
        $date = $this->parseTrackingDate($tanggalSampling);
        if (!$date) {
            return null;
        }

        $duration = is_numeric($durationValue) ? (int) $durationValue : null;

        if ($duration === null || $duration <= 1) {
            $end = $this->combineTrackingDateAndTime($date, '23:59:59');
            if ($end) {
                return $end;
            }
        }

        $endDate = $date->copy()->addDays($duration - 1);
        $end = $this->combineTrackingDateAndTime($endDate, '23:59:59');

        return $end ?: $endDate->copy()->endOfDay();
    }

    protected function parseTrackingDate($value)
    {
        if ($value instanceof Carbon) {
            return $value->copy()->timezone('Asia/Jakarta')->startOfDay();
        }

        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        try {
            return Carbon::parse($value, 'Asia/Jakarta')->startOfDay();
        } catch (\Exception $exception) {
            return null;
        }
    }

    protected function combineTrackingDateAndTime($date, $time)
    {
        if (!$date || $time === null || $time === '' || $time === '-') {
            return null;
        }

        $time = trim((string) $time);

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $time)) {
                return Carbon::parse($time, 'Asia/Jakarta');
            }

            return Carbon::parse($date->toDateString() . ' ' . $time, 'Asia/Jakarta');
        } catch (\Exception $exception) {
            return null;
        }
    }

    protected function firstEventAt($events, $eventType)
    {
        $event = collect($events)->first(function ($item) use ($eventType) {
            return $this->eventValue($item, 'event_type') === $eventType;
        });

        $eventAt = $this->eventValue($event, 'event_at');
        if (!$eventAt) {
            return null;
        }

        try {
            return Carbon::parse($eventAt, 'Asia/Jakarta');
        } catch (\Exception $exception) {
            return null;
        }
    }

    protected function eventValue($event, $field)
    {
        if (!$event) {
            return null;
        }

        if (is_array($event)) {
            return $event[$field] ?? null;
        }

        return $event->{$field} ?? null;
    }

    protected function teamRouteKey($date, $sessions)
    {
        $route = collect($sessions)->map(function ($session) {
            return implode('~', [
                $session->jam_mulai ?: '-',
                $session->jam_selesai ?: '-',
                $session->no_order ?: ($session->no_quotation ?: '-'),
                $session->nama_perusahaan ?: '-',
            ]);
        })
            ->sort()
            ->values()
            ->implode('|');

        return ($date ?: '-') . '|' . $route;
    }

    protected function teamMovementCode($date, $groupKey)
    {
        $compactDate = $date ? substr(str_replace('-', '', $date), 2) : $this->now()->format('ymd');

        return 'TRK-' . $compactDate . '-' . strtoupper(substr(sha1($groupKey), 0, 8));
    }
    public function storeEvent(array $payload)
    {
        $source = SamplerTrackingMember::with('session')->where('id', $payload['member_id'])->where('is_active', true)->firstOrFail();
        if (!$source->session || !$source->session->is_active) {
            throw ValidationException::withMessages([
                'member_id' => ['Activity sampling sudah tidak aktif.'],
            ]);
        }
        // Run clearance outside the event transaction: a later validation failure must not undo it.
        (new SamplerTrackingTroubleService())->assertAllowed($source->sampler_id, $source->session->tanggal_sampling, $source->sampler_tracking_session_id);
        return DB::transaction(function () use ($payload) {
            $member = SamplerTrackingMember::with('session')
                ->where('id', $payload['member_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            // Use the same source -> session -> member lock order as a sync.
            if ($member->session) {
                $this->snapshotSchedules($member->session->no_quotation);
            }
            $session = SamplerTrackingSession::where('id', $member->sampler_tracking_session_id)
                ->lockForUpdate()->first();
            $member = SamplerTrackingMember::where('id', $member->id)->lockForUpdate()->firstOrFail();
            $member->setRelation('session', $session);
            if (!$session || !$session->is_active || !$member->is_active || !$this->hasActiveSchedule($session, $member)) {
                throw ValidationException::withMessages([
                    'member_id' => ['Jadwal activity sudah berubah atau dinonaktifkan. Muat ulang Sampling Activity.'],
                ]);
            }

            $eventType = $payload['event_type'];
            $this->ensureEventSequence($member, $eventType);
            $movementGroup = $member->current_movement_group ?: $this->makeMovementGroupCode($member->session);

            $activities = $this->eventActivities($member);
            $activity = $activities->first(function ($item) use ($member) {
                return in_array((int) $member->sampler_tracking_session_id, array_map('intval', $item->activity_session_ids ?? [$item->id]), true);
            });
            $members = SamplerTrackingMember::whereIn('sampler_tracking_session_id', $activity->activity_session_ids ?? [$member->sampler_tracking_session_id])
                ->where('is_active', true)
                // Anggota dengan durasi lebih pendek dapat menyelesaikan
                // aktivitasnya sendiri. Checkout dan Pulang tidak boleh
                // menutup anggota tim yang masih punya durasi lanjutan.
                ->get();
            $ownActivityMember = $activity ? $this->sessionMemberForSampler($activity, $member) : $member;
            $members = $members->filter(function ($target) use ($activity, $ownActivityMember, $eventType, $member) {
                $logical = $activity ? $this->sessionMemberForSampler($activity, $target) : $target;
                if (in_array($eventType, ['checkout', 'return'], true) && SamplerTrackingActivity::duration($logical) !== SamplerTrackingActivity::duration($ownActivityMember)) return false;
                if ((string) $target->id !== (string) $member->id) {
                    try {
                        (new SamplerTrackingTroubleService())->assertAllowed($target->sampler_id, $member->session->tanggal_sampling, $target->sampler_tracking_session_id);
                    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                        if ($e->getStatusCode() !== 423) throw $e;
                        return false;
                    }
                }
                return !$this->memberHasTrackingEvent($target->id, $eventType);
            });

            $basWarning = $eventType === 'checkout' ? $this->checkoutBasWarning($member->id) : null;
            if ($basWarning) {
                throw ValidationException::withMessages([
                    'event_type' => [$basWarning['message']],
                ]);
            }

            $events = [];
            $eventModel = new SamplerTrackingEvent();
            $photos = $this->storePhotos($payload['photos'] ?? null);

            if (count($photos) === 0 && !empty($payload['photo'])) {
                $singlePhoto = $this->storePhoto($payload['photo']);
                if ($singlePhoto) {
                    $photos[] = $singlePhoto;
                }
            }

            $photo = $photos[0] ?? null;
            $latitude = $this->normalizeCoordinate($payload['latitude'] ?? $payload['lat'] ?? null);
            $longitude = $this->normalizeCoordinate($payload['longitude'] ?? $payload['longi'] ?? $payload['long'] ?? null);

            foreach ($members as $targetMember) {
                $events[] = SamplerTrackingEvent::create($this->onlyExistingColumns($eventModel->getTable(), [
                    'sampler_tracking_session_id' => $targetMember->sampler_tracking_session_id,
                    'sampler_tracking_member_id' => $targetMember->id,
                    'triggered_by_member_id' => $member->id,
                    'event_type' => $eventType,
                    'movement_group' => $movementGroup,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'photo' => $photo,
                    'photos' => count($photos) > 0 ? json_encode($photos) : null,
                    'note' => $payload['note'] ?? null,
                    'vehicle_plate' => $payload['vehicle_plate'] ?? null,
                    'bas_not_completed' => 0,
                    'bas_forced_checkout' => 0,
                    'bas_warning_message' => null,
                    'is_auto' => $targetMember->id !== $member->id,
                    'sequence_no' => $this->nextSequence($targetMember->id),
                    'event_at' => $payload['event_at'] ?? $this->now(),
                ]));
            }

            return collect($events);
        });
    }

    /**
     * Only one sampling stop may be in progress for a sampler. The route
     * sequence follows a saved route override when one exists, otherwise the
     * scheduled order. This is enforced server-side so every Apps FDL screen
     * follows the same rule.
     */
    protected function eventActivities(SamplerTrackingMember $member)
    {
        $recovery = (new SamplerTrackingTroubleService())->isReopened($member->sampler_id, $member->sampler_tracking_session_id);
        return $this->listByDate($member->session->tanggal_sampling, $member->sampler_id, null,
            $recovery ? [$member->sampler_tracking_session_id] : null);
    }

    protected function ensureEventSequence(SamplerTrackingMember $member, string $eventType): void
    {
        if (!$member->session || !in_array($eventType, ['checkin', 'checkout', 'return'], true)) {
            return;
        }

        $sessions = $this->eventActivities($member)->values();
        $currentIndex = $sessions->search(function ($session) use ($member) {
            return in_array((int) $member->sampler_tracking_session_id, array_map('intval', $session->activity_session_ids ?? [$session->id]), true);
        });
        if ($currentIndex === false) {
            return;
        }

        if ($eventType === 'checkout') {
            if (!SamplerTrackingActivity::hasEvent($this->sessionMemberForSampler($sessions->get($currentIndex), $member), 'checkin')) {
                throw ValidationException::withMessages(['event_type' => ['Check in harus dilakukan sebelum check out.']]);
            }
            return;
        }

        if ($eventType === 'checkin') {
            if (!$this->departureForMember($this->sessionMemberForSampler($sessions->get($currentIndex), $member), $member->session->tanggal_sampling)) {
                throw ValidationException::withMessages(['event_type' => ['Berangkat sampling harus dilakukan sebelum check in.']]);
            }
            if ($currentIndex === 0) {
                if (!SamplerTrackingActivity::hasEvent($this->sessionMemberForSampler($sessions->first(), $member), 'departure')) {
                    throw ValidationException::withMessages([
                        'event_type' => ['Berangkat sampling harus dilakukan sebelum check in lokasi pertama.'],
                    ]);
                }
                return;
            }

            $previousSession = $sessions->get($currentIndex - 1);
            $previousMember = $this->sessionMemberForSampler($previousSession, $member);
            if (!SamplerTrackingActivity::hasEvent($previousMember, 'checkout')) {
                throw ValidationException::withMessages([
                    'event_type' => ['Selesaikan check out lokasi sebelumnya terlebih dahulu sebelum check in lokasi ini.'],
                ]);
            }
            return;
        }

        $unfinishedSessions = $sessions->filter(function ($session) use ($member) {
            $sessionMember = $this->sessionMemberForSampler($session, $member);
            return !SamplerTrackingActivity::hasEvent($sessionMember, 'checkout');
        });
        if ($unfinishedSessions->isNotEmpty()) {
            throw ValidationException::withMessages([
                'event_type' => ['Semua lokasi sampling harus check out terlebih dahulu sebelum pulang.'],
            ]);
        }
    }

    protected function sessionMemberForSampler($session, SamplerTrackingMember $member)
    {
        if (!$session) {
            return null;
        }

        if ($session->relationLoaded('activeMembers')) {
            return $session->activeMembers->first(function ($item) use ($member) {
                return SamplerTrackingActivity::samplerKey($item) === SamplerTrackingActivity::samplerKey($member);
            });
        }

        return SamplerTrackingMember::where('sampler_tracking_session_id', $session->id)
            ->where('is_active', true)
            ->where(function ($query) use ($member) {
                if ($member->sampler_id) {
                    $query->where('sampler_id', $member->sampler_id);
                    return;
                }
                $query->where('sampler_name', $member->sampler_name);
            })
            ->first();
    }

    protected function memberHasTrackingEvent($memberId, string $eventType): bool
    {
        return SamplerTrackingEvent::where('sampler_tracking_member_id', $memberId)
            ->where('event_type', $eventType)
            ->exists();
    }

    protected function canInheritTeamEvent(string $eventType, $donorEffectiveDuration, $recipientEffectiveDuration): bool
    {
        if (in_array($eventType, ['departure', 'checkin'], true)) {
            return true;
        }

        if (in_array($eventType, ['checkout', 'return'], true)) {
            return (string) $donorEffectiveDuration === (string) $recipientEffectiveDuration;
        }

        return false;
    }

    protected function inheritedTeamEventNote($sourceEvent, string $reason): string
    {
        return trim(($sourceEvent->note ?: '') . "\n" . $reason . '; sumber event #' . $sourceEvent->id . '.');
    }

    /**
     * Copy each already-completed milestone in a session to a member who was
     * assigned later. The original event stays untouched and remains the
     * source of its time, photo, coordinates, and actor.
     */
    protected function backfillTeamEventsForMember(SamplerTrackingMember $member): void
    {
        if (!$member->exists || !$member->sampler_tracking_session_id) {
            return;
        }

        $existingTypes = SamplerTrackingEvent::where('sampler_tracking_member_id', $member->id)
            ->pluck('event_type')
            ->filter()
            ->unique()
            ->values();

        $sourceEvents = SamplerTrackingEvent::where('sampler_tracking_session_id', $member->sampler_tracking_session_id)
            ->when($existingTypes->isNotEmpty(), function ($query) use ($existingTypes) {
                $query->whereNotIn('event_type', $existingTypes->all());
            })
            ->orderBy('event_type')
            ->orderBy('is_auto')
            ->orderBy('event_at')
            ->orderBy('id')
            ->get()
            ->groupBy('event_type')
            ->map(function ($events) {
                // Prefer the manually recorded event over its automatic team copies.
                return $events->first();
            })
            ->values();

        if ($sourceEvents->isEmpty()) {
            return;
        }

        $donorDurations = SamplerTrackingMember::whereIn(
            'id',
            $sourceEvents->pluck('sampler_tracking_member_id')->filter()->unique()->all()
        )->pluck('effective_duration', 'id');

        $eventModel = new SamplerTrackingEvent();
        foreach ($sourceEvents as $sourceEvent) {
            $donorDuration = $donorDurations->get($sourceEvent->sampler_tracking_member_id);
            if (!$this->canInheritTeamEvent($sourceEvent->event_type, $donorDuration, $member->effective_duration)) {
                continue;
            }

            SamplerTrackingEvent::create($this->onlyExistingColumns($eventModel->getTable(), [
                'sampler_tracking_session_id' => $member->sampler_tracking_session_id,
                'sampler_tracking_member_id' => $member->id,
                'triggered_by_member_id' => $sourceEvent->triggered_by_member_id ?: $sourceEvent->sampler_tracking_member_id,
                'event_type' => $sourceEvent->event_type,
                'movement_group' => $member->current_movement_group ?: $sourceEvent->movement_group,
                'latitude' => $sourceEvent->latitude,
                'longitude' => $sourceEvent->longitude,
                'photo' => $sourceEvent->photo,
                'photos' => $sourceEvent->photos,
                'note' => $this->inheritedTeamEventNote($sourceEvent, 'Salinan otomatis tim'),
                'vehicle_plate' => $sourceEvent->vehicle_plate,
                'bas_not_completed' => $sourceEvent->bas_not_completed,
                'bas_forced_checkout' => $sourceEvent->bas_forced_checkout,
                'bas_warning_message' => $sourceEvent->bas_warning_message,
                'is_auto' => true,
                'sequence_no' => $this->nextSequence($member->id),
                'event_at' => $sourceEvent->event_at,
            ]));
        }
    }

    protected function normalizeCoordinate($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        return is_numeric($value) ? $value : null;
    }

    public function checkoutBasWarning($memberId)
    {
        $member = SamplerTrackingMember::with('session')
            ->where('id', $memberId)
            ->where('is_active', true)
            ->first();

        if (!$member || !$member->session) {
            return null;
        }

        $session = $member->session;
        $query = PersiapanSampelHeader::where('is_active', true)
            ->whereDate('tanggal_sampling', $session->tanggal_sampling);

        if ($session->no_order) {
            $query->where('no_order', $session->no_order);
        } elseif ($session->no_quotation) {
            $query->where('no_quotation', $session->no_quotation);
        }

        $table = (new PersiapanSampelHeader())->getTable();
        if ($member->sampler_name && $this->hasColumn($table, 'sampler_jadwal')) {
            $query->where('sampler_jadwal', 'like', '%' . $member->sampler_name . '%');
        }

        $headers = $query->get();
        $hasCompletedBas = $headers->contains(function ($header) {
            return (int) $header->is_emailed_bas === 1;
        });

        if ($hasCompletedBas) {
            return null;
        }

        return [
            'message' => 'Anda tidak dapat checkout dikarenakan BAS belum disubmit.',
            'no_order' => $session->no_order,
            'tanggal_sampling' => $session->tanggal_sampling,
            'sampler' => $member->sampler_name,
        ];
    }

    protected function isBasDocumentFilled($value)
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || $trimmed === '[]' || strtolower($trimmed) === 'null') {
                return false;
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return count($decoded) > 0;
            }

            return true;
        }

        if (is_array($value)) {
            return count($value) > 0;
        }

        return !empty($value);
    }

    public function updateMovementGroup(array $memberIds, $movementGroup = null)
    {
        $movementGroup = $movementGroup ?: ('TRK-' . $this->now()->format('ymd') . '-GRP-' . mt_rand(100, 999));
        $member = new SamplerTrackingMember();
        $movementUpdate = $this->onlyExistingColumns($member->getTable(), ['current_movement_group' => $movementGroup]);

        if (count($movementUpdate) > 0) {
            SamplerTrackingMember::whereIn('id', $memberIds)
                ->where('is_active', true)
                ->update($movementUpdate);
        }

        return $movementGroup;
    }

    public function updateRouteOrder(array $payload, $actorName = null)
    {
        $table = 'sampler_tracking_route_overrides';
        if (!Schema::hasTable($table)) {
            throw new \Exception('Tabel sampler_tracking_route_overrides belum ada.');
        }

        $date = $this->today();
        $samplerId = $payload['sampler_id'] ?? null;
        $samplerName = $payload['sampler_name'] ?? $actorName;
        $samplerKey = $this->samplerRouteKey($samplerId, $samplerName);
        $reason = trim($payload['reason'] ?? '');
        $items = collect($payload['items'] ?? [])->values();
        $now = $this->now();

        if (!$samplerKey || $items->isEmpty() || $reason === '') {
            throw new \Exception('Urutan tujuan dan keterangan wajib diisi.');
        }

        DB::transaction(function () use ($table, $date, $samplerKey, $samplerId, $samplerName, $reason, $items, $now, $actorName) {
            // Lock the same member rows used by storeEvent before checking the route.
            $sessions = $this->listByDate($date, $samplerId, $samplerName);
            $memberIds = $sessions->flatMap(function ($session) use ($samplerId, $samplerName) {
                return $session->activeMembers->filter(function ($member) use ($samplerId, $samplerName) {
                    return $samplerId ? (string) $member->sampler_id === (string) $samplerId
                        : $member->sampler_name === $samplerName;
                })->flatMap(function ($member) {
                    return $member->activity_member_ids ?? [$member->id];
                });
            })->unique()->values();
            SamplerTrackingMember::whereIn('id', $memberIds)->orderBy('id')->lockForUpdate()->get();
            if (SamplerTrackingEvent::whereIn('sampler_tracking_member_id', $memberIds)
                ->whereIn('event_type', ['departure', 'checkin'])->exists()) {
                throw ValidationException::withMessages([
                    'items' => ['Urutan tujuan tidak dapat diubah setelah berangkat atau check in.'],
                ]);
            }
            DB::table($table)
                ->where('tanggal_sampling', $date)
                ->where('sampler_key', $samplerKey)
                ->update([
                    'is_active' => 0,
                    'updated_by' => $actorName,
                    'updated_at' => $now,
                ]);

            foreach ($items as $index => $item) {
                $sessionId = $item['session_id'] ?? null;
                if (!$sessionId) {
                    continue;
                }

                $keys = [
                    'tanggal_sampling' => $date,
                    'sampler_key' => $samplerKey,
                    'sampler_tracking_session_id' => $sessionId,
                ];

                $values = [
                    'sampler_id' => $samplerId,
                    'sampler_name' => $samplerName,
                    'route_order' => (int) ($item['route_order'] ?? ($index + 1)),
                    'reason' => $reason,
                    'is_active' => 1,
                    'updated_by' => $actorName,
                    'updated_at' => $now,
                ];

                $existing = DB::table($table)->where($keys)->first();
                if ($existing) {
                    DB::table($table)->where('id', $existing->id)->update($values);
                    continue;
                }

                DB::table($table)->insert(array_merge($keys, $values, [
                    'created_by' => $actorName,
                    'created_at' => $now,
                ]));
            }
        }, 5);

        return $this->listByDate($date, $samplerId, $samplerName);
    }

    protected function applyRouteOverrides($sessions, $date, $samplerId = null, $samplerName = null)
    {
        $table = 'sampler_tracking_route_overrides';
        $samplerKey = $this->samplerRouteKey($samplerId, $samplerName);

        if (!$samplerKey || !Schema::hasTable($table)) {
            return $sessions;
        }

        $orders = DB::table($table)
            ->where('tanggal_sampling', $date)
            ->where('sampler_key', $samplerKey)
            ->where('is_active', 1)
            ->pluck('route_order', 'sampler_tracking_session_id');

        if ($orders->isEmpty()) {
            return $sessions;
        }

        return $sessions->sortBy(function ($session) use ($orders) {
            $order = $orders[$session->id] ?? 999999;

            return str_pad($order, 6, '0', STR_PAD_LEFT)
                . '|' . ($session->jam_mulai ?: '')
                . '|' . ($session->nama_perusahaan ?: '')
                . '|' . str_pad($session->id, 10, '0', STR_PAD_LEFT);
        })->values();
    }

    protected function samplerRouteKey($samplerId = null, $samplerName = null)
    {
        if ($samplerId) {
            return (string) $samplerId;
        }

        return $samplerName ? trim((string) $samplerName) : null;
    }
    protected function makeMovementGroupCode($session, $suffix = null)
    {
        $date = $session && $session->tanggal_sampling
            ? Carbon::parse($session->tanggal_sampling)->format('ymd')
            : $this->now()->format('ymd');

        $sequence = $session && $session->id
            ? str_pad($session->id, 4, '0', STR_PAD_LEFT)
            : mt_rand(1000, 9999);

        $code = 'TRK-' . $date . '-' . $sequence;

        return $suffix ? $code . '-' . $suffix . '-' . mt_rand(100, 999) : $code;
    }

    protected function makeMovementGroupCodeFromRow($row)
    {
        $date = $row && $row->tanggal
            ? Carbon::parse($row->tanggal)->format('ymd')
            : $this->now()->format('ymd');

        $samplerKey = $row && ($row->userid || $row->sampler)
            ? ($row->userid ?: $row->sampler)
            : 'sampler-null';

        return 'TRK-' . $date . '-' . strtoupper(substr(sha1($date . '|' . $samplerKey), 0, 8));
    }
    protected function storePhotos($photos)
    {
        if (!$photos) {
            return [];
        }

        if (is_string($photos)) {
            $decoded = json_decode($photos, true);
            $photos = json_last_error() === JSON_ERROR_NONE ? $decoded : [$photos];
        }

        if (!is_array($photos)) {
            return [];
        }

        $stored = [];
        foreach ($photos as $photo) {
            $path = $this->storePhoto($photo);
            if ($path) {
                $stored[] = $path;
            }
        }

        return $stored;
    }

    protected function storePhoto($photo)
    {
        if (!$photo || strpos($photo, 'data:image') !== 0) {
            return $photo;
        }

        if (strpos($photo, ',') === false) {
            return null;
        }

        [$meta, $data] = explode(',', $photo, 2);
        $extension = strpos($meta, 'image/png') !== false ? 'png' : 'jpg';
        $directory = public_path('sampler_tracking');

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $fileName = 'tracking_' . $this->now()->format('YmdHis') . '_' . uniqid() . '.' . $extension;
        $path = $directory . DIRECTORY_SEPARATOR . $fileName;

        file_put_contents($path, base64_decode($data));

        return 'sampler_tracking/' . $fileName;
    }
    protected function onlyExistingColumns($table, array $values)
    {
        if (!isset($this->columnsByTable[$table])) {
            $this->columnsByTable[$table] = Schema::getColumnListing($table);
        }

        return array_intersect_key($values, array_flip($this->columnsByTable[$table]));
    }

    protected function hasColumn($table, $column)
    {
        if (!isset($this->columnsByTable[$table])) {
            $this->columnsByTable[$table] = Schema::getColumnListing($table);
        }

        return in_array($column, $this->columnsByTable[$table], true);
    }

    protected function hasActiveSchedule($session, $member)
    {
        return Jadwal::where('is_active', true)
            ->where('no_quotation', $session->no_quotation)
            ->whereDate('tanggal', $session->tanggal_sampling)
            ->when($member->sampler_id, function ($query) use ($member) {
                $query->where('userid', $member->sampler_id);
            }, function ($query) use ($member) {
                $query->where('sampler', $member->sampler_name);
            })->lockForUpdate()->get()->contains(function ($row) use ($session) {
                return $this->makeTeamKey($row) === $session->team_key;
            });
    }

    protected function makeTeamKey($row)
    {
        $sourceKey = implode('|', [
            $row->id_sampling ?: 'sampling-null',
            $row->parsial ?: 'parsial-null',
            $this->resolveOrderNumber($row) ?: ('qt:' . ($row->no_quotation ?: 'null')),
            $row->tanggal ?: 'date-null',
            $row->jam_mulai ?: 'start-null',
            $row->jam_selesai ?: 'end-null',
            $row->kendaraan ?: 'vehicle-null',
            $row->id_cabang ?: 'branch-null',
        ]);

        return sha1($sourceKey);
    }

    /**
     * Keep tracking continuity across QT revisions. A quotation is mutable,
     * while its order number remains the business identity of the sampling.
     */
    protected function resolveOrderNumber($row)
    {
        if (isset($row->no_order) && $row->no_order) {
            return $row->no_order;
        }

        $quotation = $row->no_quotation ?? null;
        if (!$quotation) {
            return null;
        }

        if (!array_key_exists($quotation, $this->orderNumbersByQuotation)) {
            $this->orderNumbersByQuotation[$quotation] = OrderHeader::where('no_document', $quotation)
                ->orderByDesc('is_active')
                ->value('no_order');
        }

        return $this->orderNumbersByQuotation[$quotation];
    }

    /** The pre-order-identity key, used only to safely upgrade existing sessions. */
    protected function makeLegacyTeamKey($row)
    {
        return sha1(implode('|', [
            $row->id_sampling ?: 'sampling-null',
            $row->parsial ?: 'parsial-null',
            $row->no_quotation ?: 'qt-null',
            $row->tanggal ?: 'date-null',
            $row->jam_mulai ?: 'start-null',
            $row->jam_selesai ?: 'end-null',
            $row->kendaraan ?: 'vehicle-null',
            $row->id_cabang ?: 'branch-null',
        ]));
    }
    protected function findSession($teamKey)
    {
        $sessions = SamplerTrackingSession::withCount('events')
            ->where('team_key', $teamKey)
            ->orderByDesc('events_count')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($sessions->isEmpty()) {
            $session = new SamplerTrackingSession();
            $session->team_key = $teamKey;

            return $session;
        }

        $keeper = $sessions->first();
        $duplicates = $sessions->where('id', '!=', $keeper->id);

        foreach ($duplicates as $duplicate) {
            $this->mergeDuplicateSession($keeper, $duplicate);
        }

        return $keeper;
    }

    protected function mergeDuplicateSession($keeper, $duplicate)
    {
        DB::transaction(function () use ($keeper, $duplicate) {
            $duplicateMembers = SamplerTrackingMember::where('sampler_tracking_session_id', $duplicate->id)->get();

            foreach ($duplicateMembers as $duplicateMember) {
                $targetMember = SamplerTrackingMember::where('sampler_tracking_session_id', $keeper->id)
                    ->when($duplicateMember->sampler_id, function ($query) use ($duplicateMember) {
                        $query->where('sampler_id', $duplicateMember->sampler_id);
                    }, function ($query) use ($duplicateMember) {
                        $query->where('sampler_name', $duplicateMember->sampler_name);
                    })
                    ->first();

                if ($targetMember) {
                    SamplerTrackingEvent::where('sampler_tracking_member_id', $duplicateMember->id)
                        ->update([
                            'sampler_tracking_session_id' => $keeper->id,
                            'sampler_tracking_member_id' => $targetMember->id,
                        ]);

                    $duplicateMember->fill($this->onlyExistingColumns($duplicateMember->getTable(), ['is_active' => false]));
                    $duplicateMember->save();
                } else {
                    $duplicateMember->sampler_tracking_session_id = $keeper->id;
                    $duplicateMember->save();

                    SamplerTrackingEvent::where('sampler_tracking_member_id', $duplicateMember->id)
                        ->update(['sampler_tracking_session_id' => $keeper->id]);
                }
            }

            SamplerTrackingEvent::where('sampler_tracking_session_id', $duplicate->id)
                ->update(['sampler_tracking_session_id' => $keeper->id]);

            $duplicate->fill($this->onlyExistingColumns($duplicate->getTable(), ['is_active' => false]));
            $duplicate->save();
        });
    }
    protected function findMember($sessionId, $row)
    {
        $query = SamplerTrackingMember::where('sampler_tracking_session_id', $sessionId);

        if ($row->userid) {
            return $query->where('sampler_id', $row->userid)->first() ?: new SamplerTrackingMember();
        }

        return $query->where('sampler_name', $row->sampler)->first() ?: new SamplerTrackingMember();
    }

    protected function normalizeJson($value)
    {
        if (!$value) {
            return null;
        }

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE ? $value : json_encode($value);
    }

    protected function nextSequence($memberId)
    {
        return ((int) SamplerTrackingEvent::where('sampler_tracking_member_id', $memberId)->max('sequence_no')) + 1;
    }
}
