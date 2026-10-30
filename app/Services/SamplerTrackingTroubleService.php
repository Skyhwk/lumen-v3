<?php

namespace App\Services;

use App\Models\SamplerTrackingEvent;
use App\Models\SamplerTrackingSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SamplerTrackingTroubleService
{
    const TABLE = 'sampler_tracking_troubles';

    const REOPEN_REASONS = [
        'operational_delay' => 'Activity belum selesai (kendala operasional)',
        // 'technical_issue' => 'Kendala teknis aplikasi / perangkat',
        'force_majeure' => 'Force majeure (cuaca, lalu lintas, dll.)',
        'sampler_procedure' => 'Kesalahan prosedur / kelalaian sampler',
        'other' => 'Lainnya',
    ];

    const SAMPLER_FOLLOW_UP_ACTIONS = [
        'verbal_reminder' => 'Teguran / pengingat lisan',
        'verbal_warning' => 'Peringatan lisan',
        'coaching_sop' => 'Coaching / arahan ulang SOP',
        'written_warning' => 'Surat peringatan (SP)',
        'none' => 'Tidak ada tindakan disiplin',
        'other' => 'Lainnya',
    ];

    public static function reopenReasonLabel($key)
    {
        return self::REOPEN_REASONS[$key] ?? ($key ?: '-');
    }

    public static function samplerFollowUpLabel($key)
    {
        return self::SAMPLER_FOLLOW_UP_ACTIONS[$key] ?? ($key ?: '-');
    }

    public static function reopenReasonKeys()
    {
        return array_keys(self::REOPEN_REASONS);
    }

    public static function samplerFollowUpKeys()
    {
        return array_keys(self::SAMPLER_FOLLOW_UP_ACTIONS);
    }

    public static function startDate($deadlineDay = null)
    {
        $end = $deadlineDay
            ? Carbon::parse($deadlineDay, 'Asia/Jakarta')
            : Carbon::now('Asia/Jakarta')->subDay();

        // Batasi 10 hari kalender terakhir (termasuk batas deadline)
        return $end->startOfDay()->subDays(9)->toDateString();
    }

    protected $journey;
    protected $progressSessionsCache = [];

    public function journey(): SamplerTrackingJourney
    {
        if (!$this->journey) {
            $this->journey = new SamplerTrackingJourney();
        }
        return $this->journey;
    }

    public function clearCaches(): void
    {
        $this->progressSessionsCache = [];
        SamplerTrackingJourney::clearCache();
    }

    protected function ready()
    {
        if (!Schema::hasTable(self::TABLE)
            || !Schema::hasColumn(self::TABLE, 'tracking_session_id')
            || !Schema::hasColumn(self::TABLE, 'is_active')) {
            throw new HttpException(503, 'Konfigurasi pemeriksaan activity sampler belum tersedia. Hubungi administrator.');
        }
    }

    protected function progress($samplerId, $date, $sessionId, $asOf = null)
    {
        $cacheKey = $samplerId . '|' . $date;
        if (!isset($this->progressSessionsCache[$cacheKey])) {
            $this->progressSessionsCache[$cacheKey] = SamplerTrackingSession::with('activeMembers.events')->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('nama_perusahaan')
                        ->orWhereRaw('LOWER(TRIM(nama_perusahaan)) != ?', ['cuti']);
                })
                ->where('tanggal_sampling', $date)->whereHas('activeMembers', function ($query) use ($samplerId) {
                    $query->where('sampler_id', $samplerId);
            })->get();
        }
        $sessions = $this->progressSessionsCache[$cacheKey];
        $session = $sessions->firstWhere('id', $sessionId);
        // A schedule correction can temporarily leave a legacy trouble
        // pointing at a session whose member has been superseded. Missing
        // from the active query is not proof that its checkout/return was
        // completed; treating it as complete silently clears the trouble.
        if (!$session) {
            return [
                'complete' => false,
                'stops_complete' => false,
                'due_date' => $this->dueDate($date, 0),
                'missing_active_assignment' => true,
            ];
        }
        $members = $session->activeMembers->filter(function ($member) use ($samplerId) {
            return (string) $member->sampler_id === (string) $samplerId;
        });
        $dailyMembers = $sessions->flatMap(function ($item) use ($samplerId) {
            return $item->activeMembers->filter(function ($member) use ($samplerId) {
                return (string) $member->sampler_id === (string) $samplerId;
            });
        });
        $wasReopened = DB::table(self::TABLE)->where('sampler_id', $samplerId)
            ->where('tracking_session_id', $sessionId)
            ->where('is_active', 1)
            ->whereNotNull('reopened_by')->whereNotNull('reopened_at')->exists();
        $stopsComplete = $members->every(function ($member) {
            return SamplerTrackingActivity::hasEvent($member, 'checkin')
                && SamplerTrackingActivity::hasEvent($member, 'checkout');
        });
        $maxDailyDuration = $dailyMembers->max(function ($member) {
            return SamplerTrackingActivity::duration($member);
        });
        $ownMaxDuration = $members->max(function ($member) {
            return SamplerTrackingActivity::duration($member);
        });
        // After recovery check-in/out, shorter PT on the same day follows the
        // normal shared journey (return on the longest assignment's timeline).
        $useDailyJourneyReturn = !$wasReopened
            || ($stopsComplete && $dailyMembers->isNotEmpty() && $ownMaxDuration < $maxDailyDuration);
        $journeyMembers = $useDailyJourneyReturn ? $dailyMembers : ($wasReopened ? $members : $dailyMembers);
        if ($useDailyJourneyReturn) {
            $journey = $this->journey();
            $complete = $stopsComplete && $members->every(function ($member) use ($journey, $date) {
                return $journey->isComplete($member, $date);
            });
        } else {
            // Explicit recovery still requires a return on this reopened assignment.
            $complete = $stopsComplete && $members->every(function ($member) use ($date) {
                return (new SamplerTrackingService())->departureForMember($member, $date) !== null;
            }) && $journeyMembers->contains(function ($member) {
                return SamplerTrackingActivity::hasEvent($member, 'return');
            });
        }
        $sessionDue = $this->dueDate($date, $members->max(function ($member) {
            return SamplerTrackingActivity::duration($member);
        }));
        // Departure/return are one daily journey. Sesaat + 1x24 jam on the same
        // date must not be overdue until the longest assignment is due.
        $journeyDue = $sessions->max(function ($item) use ($samplerId, $date) {
            $own = $item->activeMembers->filter(function ($member) use ($samplerId) {
                return (string) $member->sampler_id === (string) $samplerId;
            });

            return $this->dueDate($date, $own->max(function ($member) {
                return SamplerTrackingActivity::duration($member);
            }));
        });
        $dailyDue = ($wasReopened && !$useDailyJourneyReturn) ? $sessionDue : $journeyDue;
        $due = $dailyDue ?: $sessionDue;
        $extension = null;
        // A missing checkout keeps its own deadline. Continuation only defers the final return.
        if ($stopsComplete && !$wasReopened) {
            foreach ($members as $member) {
                $candidate = (new SamplerTrackingJourney())->extension($member, $date, $due, $asOf);
                if ($candidate && $candidate['due_date'] > $due) {
                    $extension = $candidate;
                    $due = $candidate['due_date'];
                }
            }
        }
        return ['complete' => $complete, 'stops_complete' => $stopsComplete, 'due_date' => $due,
            'journey_extension' => $extension];
    }

    protected function dueDate($date, $duration)
    {
        return Carbon::parse($date)->addDays(max(0, (int) $duration - 1))->toDateString();
    }

    protected function journeyKey($assignment)
    {
        return $assignment->sampler_id . '|' . Carbon::parse($assignment->activity_date)->toDateString();
    }

    protected function assignmentDurationColumns()
    {
        $columns = [
            'm.sampler_id',
            's.id as tracking_session_id',
            's.tanggal_sampling as activity_date',
        ];
        foreach (['effective_duration', 'durasi_personal', 'duration', 'durasi'] as $column) {
            if (Schema::hasColumn('sampler_tracking_members', $column)) {
                $columns[] = 'm.' . $column;
            }
        }
        if (Schema::hasColumn('sampler_tracking_sessions', 'durasi')) {
            $columns[] = 's.durasi as session_durasi';
        }

        return $columns;
    }

    protected function assignmentDuration($assignment)
    {
        foreach (['effective_duration', 'durasi_personal', 'duration', 'durasi', 'session_durasi'] as $field) {
            if (isset($assignment->$field) && $assignment->$field !== null && $assignment->$field !== '') {
                return max(0, (int) $assignment->$field);
            }
        }

        return 0;
    }

    protected function isPastDue($progress, $day)
    {
        return !empty($progress['due_date']) && $progress['due_date'] <= $day;
    }

    public function collect($day = null, array $samplerIds = [])
    {
        $this->ready();
        $this->clearCaches();
        $day = $day ?: Carbon::now('Asia/Jakarta')->subDay()->toDateString();
        $startDate = self::startDate($day);

        // Batasi pembersihan trouble stale ke rentang tanggal yang relevan (10 hari terakhir)
        $this->pruneStaleOpenTroubles($startDate);

        $trackingService = app(SamplerTrackingService::class);

        $assignments = DB::table('sampler_tracking_members as m')
            ->join('sampler_tracking_sessions as s', 's.id', '=', 'm.sampler_tracking_session_id')
            ->where('s.is_active', true)->where('m.is_active', true)
            ->where(function ($q) {
                $q->whereNull('s.nama_perusahaan')
                    ->orWhereRaw('LOWER(TRIM(s.nama_perusahaan)) != ?', ['cuti']);
            })
            ->whereRaw("(
                TRIM(COALESCE(s.nama_perusahaan, '')) != ''
                OR TRIM(COALESCE(s.no_order, '')) != ''
            )")
            ->whereBetween('s.tanggal_sampling', [$startDate, $day])
            ->when($samplerIds, function ($query) use ($samplerIds) { $query->whereIn('m.sampler_id', $samplerIds); })
            ->whereNotNull('m.sampler_id')
            ->select($this->assignmentDurationColumns())
            ->distinct()->orderBy('s.tanggal_sampling')->orderBy('s.id')->get();

        $journeyDueByKey = [];
        $journeyCountByKey = [];
        foreach ($assignments as $assignment) {
            $key = $this->journeyKey($assignment);
            $journeyCountByKey[$key] = ($journeyCountByKey[$key] ?? 0) + 1;
            $due = $this->dueDate($assignment->activity_date, $this->assignmentDuration($assignment));
            if (!isset($journeyDueByKey[$key]) || $due > $journeyDueByKey[$key]) {
                $journeyDueByKey[$key] = $due;
            }
        }

        $sessionIds = $assignments->pluck('tracking_session_id')->unique()->filter()->values()->all();
        $sessionsById = [];
        $existingTroubles = [];
        if (!empty($sessionIds)) {
            $sessionsById = SamplerTrackingSession::with('activeMembers')
                ->whereIn('id', $sessionIds)
                ->get()
                ->keyBy('id');

            $existingTroubles = DB::table(self::TABLE)
                ->whereIn('tracking_session_id', $sessionIds)
                ->select('sampler_id', 'tracking_session_id')
                ->get()
                ->mapWithKeys(function ($row) {
                    return [$row->sampler_id . '|' . $row->tracking_session_id => true];
                })
                ->all();
        }

        $count = 0;
        foreach ($assignments as $assignment) {
            if (!$assignment->sampler_id) continue;

            $troubleKey = $assignment->sampler_id . '|' . $assignment->tracking_session_id;
            if (isset($existingTroubles[$troubleKey])) continue;

            $key = $this->journeyKey($assignment);
            $journeyDue = $journeyDueByKey[$key] ?? null;
            $journeyCount = $journeyCountByKey[$key] ?? 1;
            // Sesaat + 1x24 jam (atau PT lain di hari yang sama) adalah 1 perjalanan.
            // Tunggu deadline PT terpanjang sebelum memeriksa semua kunjungan.
            if ($journeyCount > 1) {
                if (!$journeyDue || $journeyDue > $day) {
                    continue;
                }
            }

            $progress = $this->progress($assignment->sampler_id, $assignment->activity_date, $assignment->tracking_session_id,
                Carbon::parse($day, 'Asia/Jakarta')->endOfDay());
            if (!empty($progress['journey_extension'])) {
                $this->recordJourneyExtension((int) $assignment->tracking_session_id, $progress['journey_extension']);
            }
            if ($progress['complete'] || !$this->isPastDue($progress, $day)) continue;
            // A completed short stop shares the final journey return; missing
            // check-in/checkout must still receive its own recovery ticket.
            $ownDue = $this->dueDate($assignment->activity_date, $this->assignmentDuration($assignment));
            if ($journeyCount > 1 && $ownDue < $journeyDue && $progress['stops_complete']) continue;
            $inserted = DB::table(self::TABLE)->insertOrIgnore([
                'sampler_id' => $assignment->sampler_id,
                'tracking_session_id' => $assignment->tracking_session_id,
                'activity_date' => $assignment->activity_date, 'is_active' => 1, 'is_clear' => 0,
                'created_at' => Carbon::now('Asia/Jakarta'), 'updated_at' => Carbon::now('Asia/Jakarta'),
            ]);
            if ($inserted) {
                $count++;
                $existingTroubles[$troubleKey] = true;
            }
        }
        return $count;
    }

    /** Close obsolete trouble records whose assignment is gone, empty, or complete. */
    public function pruneStaleOpenTroubles($startDate = null): int
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, 'is_active')) {
            return 0;
        }

        $trackingService = app(SamplerTrackingService::class);
        $cleared = 0;

        $query = DB::table(self::TABLE)
            ->where('is_active', 1)
            ->where(function ($query) {
                $query->where('is_clear', 0)->orWhereNotNull('reopened_at');
            });

        if ($startDate) {
            $query->where('activity_date', '>=', $startDate);
        }

        $query->orderBy('id')
            ->chunkById(200, function ($troubles) use (&$cleared, $trackingService) {
                $sessionIds = $troubles->pluck('tracking_session_id')->filter()->unique()->values()->all();
                $sessionsById = [];
                $sessionsWithEvents = [];
                if (!empty($sessionIds)) {
                    $sessionsById = SamplerTrackingSession::with('activeMembers')
                        ->whereIn('id', $sessionIds)
                        ->get()
                        ->keyBy('id');
                    $sessionsWithEvents = SamplerTrackingEvent::whereIn('sampler_tracking_session_id', $sessionIds)
                        ->select('sampler_tracking_session_id')
                        ->distinct()
                        ->pluck('sampler_tracking_session_id')
                        ->flip()
                        ->all();
                }

                foreach ($troubles as $trouble) {
                    if (!$this->shouldAutoClearOpenTrouble($trouble, $trackingService, $sessionsById, $sessionsWithEvents)) {
                        continue;
                    }
                    $this->markTroubleCleared((int) $trouble->id);
                    $cleared++;
                }
            });

        return $cleared;
    }

    protected function recordJourneyExtension(int $sessionId, array $extension): void
    {
        if (!SamplerTrackingHistory::available()) return;
        DB::transaction(function () use ($sessionId, $extension) {
            $session = DB::table('sampler_tracking_sessions')->where('id', $sessionId)->lockForUpdate()->first(['meta_history']);
            if (!$session) return;
            $history = json_decode($session->meta_history ?: '[]', true, 512, JSON_THROW_ON_ERROR);
            $key = 'journey-extension:' . hash('sha256', json_encode($extension));
            if (collect($history)->contains('extension_key', $key)) return;
            SamplerTrackingHistory::append($sessionId, 'journey_deadline_extended', [
                'actor' => 'Sistem — pengecekan trouble', 'extension_key' => $key,
                'reason' => 'Penugasan lanjutan sudah Check In sebelum deadline, tanpa Pulang atau Berangkat baru.',
                'summary' => $extension['name'] . ': batas Pulang ' . $extension['base_due_date'] . ' → ' . $extension['due_date']
                    . '. Checkout tiap order tetap wajib.',
                'journey_extension' => $extension,
            ]);
        });
    }

    public function shouldAutoClearOpenTrouble($trouble, SamplerTrackingService $trackingService = null, $sessionsById = null, $sessionsWithEvents = null): bool
    {
        $trackingService = $trackingService ?: app(SamplerTrackingService::class);
        $sessionId = (int) ($trouble->tracking_session_id ?? 0);

        if ($sessionId <= 0) {
            $this->deactivateTrouble((int) $trouble->id);
            return true;
        }

        $session = $sessionsById !== null
            ? ($sessionsById[$sessionId] ?? null)
            : SamplerTrackingSession::with('activeMembers')->find($sessionId);

        if (!$session || !(bool) $session->is_active) {
            $this->deactivateTrouble((int) $trouble->id);
            return true;
        }

        $hasEvents = $sessionsWithEvents !== null
            ? isset($sessionsWithEvents[$sessionId])
            : SamplerTrackingEvent::where('sampler_tracking_session_id', $sessionId)->exists();

        if (!$hasEvents && !$trackingService->sessionHasMeaningfulTrackingData($session)) {
            $this->deactivateTrouble((int) $trouble->id);
            return true;
        }

        if (!empty($trouble->sampler_id) && !empty($trouble->activity_date)) {
            $progress = $this->progress($trouble->sampler_id, $trouble->activity_date, $sessionId);
            if (!empty($progress['missing_active_assignment'])) {
                $this->deactivateTrouble((int) $trouble->id);
                return true;
            }
            if (!empty($progress['complete'])) {
                return !(bool) ($trouble->is_clear ?? false);
            }
        }

        return false;
    }

    public function markTroubleCleared(int $troubleId): void
    {
        $update = [
            'is_clear' => 1,
            'updated_at' => Carbon::now('Asia/Jakarta'),
        ];
        if (Schema::hasColumn(self::TABLE, 'cleared_at')) {
            $update['cleared_at'] = Carbon::now('Asia/Jakarta');
        }

        DB::table(self::TABLE)->where('id', $troubleId)->where('is_clear', 0)->update($update);
    }

    public function deactivateForSessions($sessionIds): int
    {
        if (!Schema::hasTable(self::TABLE)
            || !Schema::hasColumn(self::TABLE, 'tracking_session_id')
            || !Schema::hasColumn(self::TABLE, 'is_active')) {
            return 0;
        }

        $sessionIds = array_values(array_unique(array_filter(array_map('intval', (array) $sessionIds))));
        if (!$sessionIds) {
            return 0;
        }

        $update = [
            'is_active' => 0,
            'is_clear' => 1,
            'updated_at' => Carbon::now('Asia/Jakarta'),
        ];
        if (Schema::hasColumn(self::TABLE, 'cleared_at')) {
            $update['cleared_at'] = Carbon::now('Asia/Jakarta');
        }

        return DB::table(self::TABLE)->whereIn('tracking_session_id', $sessionIds)
            ->where('is_active', 1)->update($update);
    }

    public function deactivateForSamplerSession($samplerId, int $sessionId): int
    {
        if (!Schema::hasTable(self::TABLE)
            || !Schema::hasColumn(self::TABLE, 'tracking_session_id')
            || !Schema::hasColumn(self::TABLE, 'is_active')) {
            return 0;
        }

        $update = [
            'is_active' => 0,
            'is_clear' => 1,
            'updated_at' => Carbon::now('Asia/Jakarta'),
        ];
        if (Schema::hasColumn(self::TABLE, 'cleared_at')) {
            $update['cleared_at'] = Carbon::now('Asia/Jakarta');
        }

        return DB::table(self::TABLE)->where('sampler_id', $samplerId)
            ->where('tracking_session_id', $sessionId)->where('is_active', 1)->update($update);
    }

    public function deactivateTrouble(int $troubleId): int
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, 'is_active')) {
            return 0;
        }

        $update = [
            'is_active' => 0,
            'is_clear' => 1,
            'updated_at' => Carbon::now('Asia/Jakarta'),
        ];
        if (Schema::hasColumn(self::TABLE, 'cleared_at')) {
            $update['cleared_at'] = Carbon::now('Asia/Jakarta');
        }

        return DB::table(self::TABLE)->where('id', $troubleId)
            ->where('is_active', 1)->update($update);
    }

    public function unresolved($samplerId)
    {
        $this->ready();
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $troubles = DB::table(self::TABLE)->where('sampler_id', $samplerId)
            ->where('is_active', 1)
            ->where(function ($query) {
                $query->where('is_clear', 0)->orWhere(function ($reopened) {
                    $reopened->whereNotNull('reopened_by')->whereNotNull('reopened_at');
                });
            })
            ->whereNotNull('tracking_session_id')
            ->where('activity_date', '<', $today)->orderBy('activity_date')->get();
        return $troubles->filter(function ($trouble) use ($today) {
            $progress = $this->progress($trouble->sampler_id, $trouble->activity_date, $trouble->tracking_session_id);
            // A reopened trouble must not resurrect after its schedule was deleted,
            // deactivated, or replaced with another active assignment.
            if (!empty($progress['missing_active_assignment'])) {
                $this->deactivateTrouble((int) $trouble->id);
                return false;
            }
            if (!$progress['complete']) {
                if (!$this->isPastDue($progress, Carbon::parse($today, 'Asia/Jakarta')->subDay()->toDateString())) {
                    return false;
                }
                // Recover assignments prematurely cleared using another team's return.
                if ($trouble->is_clear) {
                    DB::table(self::TABLE)->where('id', $trouble->id)->where('is_clear', 1)->update([
                        'is_clear' => 0, 'cleared_at' => null, 'updated_at' => Carbon::now('Asia/Jakarta'),
                    ]);
                    $trouble->is_clear = 0;
                    $trouble->cleared_at = null;
                }
                return true;
            }
            DB::table(self::TABLE)->where('id', $trouble->id)->where('is_clear', 0)->update([
                'is_clear' => 1, 'cleared_at' => Carbon::now('Asia/Jakarta'), 'updated_at' => Carbon::now('Asia/Jakarta'),
            ]);
            return false;
        })->values();
    }

    public function message($troubles)
    {
        $teamsByDate = $troubles->groupBy(function ($trouble) {
            return Carbon::parse($trouble->activity_date)->format('d-m-Y');
        })->map(function ($items, $date) {
            return $items->unique('tracking_session_id')->count() . ' tim pada tanggal ' . $date;
        })->values()->implode(' dan ');

        return 'Aktivitas sampling Anda belum selesai pada ' . $teamsByDate
            . '. Silakan lapor kepada atasan untuk membuka kembali aktivitas tersebut, lalu selesaikan sebelum melanjutkan aktivitas hari ini.';
    }

    protected function normalizeActivityDate($activityDate)
    {
        return Carbon::parse($activityDate)->toDateString();
    }

    public function isReopened($samplerId, $sessionId)
    {
        return DB::table(self::TABLE)->where('sampler_id', $samplerId)
            ->where('tracking_session_id', $sessionId)->where('is_active', 1)->where('is_clear', 0)
            ->whereNotNull('reopened_by')->whereNotNull('reopened_at')->exists();
    }

    /** Whether this sampler may submit events (same rules as assertAllowed, without throwing). */
    public function isRecordingBlocked($samplerId, $activityDate, $sessionId = null): bool
    {
        try {
            $this->assertAllowed($samplerId, $activityDate, $sessionId);

            return false;
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 423) {
                return true;
            }

            throw $exception;
        }
    }

    public function assertAllowed($samplerId, $activityDate, $sessionId = null)
    {
        $activityDate = $this->normalizeActivityDate($activityDate);
        $troubles = $this->unresolved($samplerId);
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        if ($activityDate > $today) throw new HttpException(422, 'Aktivitas hari mendatang belum dapat dijalankan.');
        if ($activityDate < $today) {
            if (!$sessionId) throw new HttpException(423, 'Pilih penugasan yang telah dibuka oleh atasan.');
            $trouble = $troubles->first(function ($item) use ($sessionId) {
                return (string) $item->tracking_session_id === (string) $sessionId;
            });
            if ($trouble && $trouble->reopened_by && $trouble->reopened_at) return;
            $progress = $this->progress($samplerId, $activityDate, $sessionId);
            // 1x24 / 2x24 may still be actioned after tanggal_sampling while within due_date.
            if (!$trouble && !$progress['complete'] && $progress['due_date'] >= $today
                && $this->troublesBlockingCurrentActivity($samplerId)->isEmpty()) {
                return;
            }
            throw new HttpException(423, 'Penugasan #' . $sessionId . ' pada tanggal ' . $activityDate . ' terkunci. Silakan lapor kepada atasan.');
        }
        if ($this->troublesBlockingCurrentActivity($samplerId)->isNotEmpty()) {
            throw new HttpException(423, $this->message($troubles));
        }
    }

    /**
     * Reopened trouble that still needs check-in/check-out on the unblocked session.
     */
    public function activeMobileRecovery($samplerId)
    {
        return $this->unresolved($samplerId)->first(function ($trouble) use ($samplerId) {
            if (empty($trouble->reopened_by) || empty($trouble->reopened_at)) {
                return false;
            }
            $progress = $this->progress($samplerId, $trouble->activity_date, $trouble->tracking_session_id);

            return !$progress['stops_complete'];
        });
    }

    /**
     * Unresolved troubles that still block today's sampling activity.
     */
    public function troublesBlockingCurrentActivity($samplerId)
    {
        return $this->unresolved($samplerId)->filter(function ($trouble) use ($samplerId) {
            $progress = $this->progress($samplerId, $trouble->activity_date, $trouble->tracking_session_id);
            if ($progress['complete']) {
                return false;
            }
            if (!empty($trouble->reopened_at) && $progress['stops_complete']) {
                return false;
            }

            return true;
        })->values();
    }

    public function reopen($troubleId, $actorId, array $payload)
    {
        $this->ready();
        $note = trim((string) ($payload['note'] ?? ''));
        $reopenReason = (string) ($payload['reopen_reason'] ?? '');
        $followUp = (string) ($payload['sampler_follow_up_action'] ?? '');

        return DB::transaction(function () use ($troubleId, $actorId, $note, $reopenReason, $followUp, $payload) {
            $trouble = DB::table(self::TABLE)->where('id', $troubleId)->where('is_active', 1)->lockForUpdate()->first();
            if (!$trouble) throw new HttpException(404, 'Data trouble tidak ditemukan.');
            if (!$actorId) throw new HttpException(403, 'Akses tidak diizinkan.');
            if (!$trouble->tracking_session_id) throw new HttpException(422, 'Kendala lama belum terhubung ke penugasan.');
            if ($trouble->is_clear) throw new HttpException(422, 'Aktivitas ini sudah selesai.');
            if ($trouble->reopened_at) {
                throw new HttpException(422, 'Activity ini sudah pernah di-unblock.');
            }

            $sessionIds = $this->normalizeSessionIds($payload['session_id'] ?? null, $payload['session_ids'] ?? []);
            if (empty($sessionIds) && !empty($trouble->tracking_session_id)) {
                $sessionIds = [(int) $trouble->tracking_session_id];
            }

            $targetQuery = DB::table(self::TABLE)
                ->where('is_active', 1)
                ->where('is_clear', 0)
                ->whereNull('reopened_at');

            if (!empty($sessionIds)) {
                $targetQuery->whereIn('tracking_session_id', $sessionIds);
            } else {
                $targetQuery->where('id', $troubleId);
            }

            $targetIds = $targetQuery->lockForUpdate()->pluck('id')->all();

            if (!in_array((int) $troubleId, array_map('intval', $targetIds), true)) {
                $targetIds[] = $troubleId;
            }

            $update = [
                'reopened_by' => $actorId,
                'reopened_at' => Carbon::now('Asia/Jakarta'),
                'reopen_note' => $note,
                'updated_at' => Carbon::now('Asia/Jakarta'),
            ];

            if (Schema::hasColumn(self::TABLE, 'reopen_reason')) {
                $update['reopen_reason'] = $reopenReason;
            }
            if (Schema::hasColumn(self::TABLE, 'sampler_follow_up_action')) {
                $update['sampler_follow_up_action'] = $followUp;
            }
            if (!empty($payload['lampiran']) && Schema::hasColumn(self::TABLE, 'lampiran')) {
                $update['lampiran'] = json_encode(array_values($payload['lampiran']));
            }

            DB::table(self::TABLE)->whereIn('id', $targetIds)->update($update);

            return DB::table(self::TABLE)->where('id', $troubleId)->first();
        });
    }

    protected function normalizeSessionIds($sessionId, $sessionIds)
    {
        $ids = is_array($sessionIds) ? $sessionIds : [];
        if ($sessionId) {
            $ids[] = $sessionId;
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    public function storeLampiran($files, $troubleId)
    {
        $files = $this->normalizeUploads($files);
        if (!$files) {
            return [];
        }
        if (count($files) > 10) {
            throw new HttpException(422, 'Lampiran maksimal 10 file.');
        }
        if (!Schema::hasColumn(self::TABLE, 'lampiran')) {
            throw new HttpException(500, 'Kolom lampiran belum tersedia.');
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];
        $directory = public_path('samplingtrackingtrouble');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new HttpException(500, 'Folder lampiran tidak dapat dibuat.');
        }

        $stored = [];
        try {
            foreach ($files as $file) {
                if (!$file || !$file->isValid()) {
                    throw new HttpException(422, 'Salah satu lampiran gagal diunggah.');
                }
                $extension = strtolower((string) $file->getClientOriginalExtension());
                if (!in_array($extension, $allowed, true)) {
                    throw new HttpException(422, 'Lampiran hanya boleh gambar, PDF, DOC, atau DOCX.');
                }
                $size = (int) $file->getSize();
                if ($size > 8 * 1024 * 1024) {
                    throw new HttpException(422, 'Ukuran tiap lampiran maksimal 8 MB.');
                }

                $fileName = 'trouble_' . (int) $troubleId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                $file->move($directory, $fileName);
                $stored[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => 'samplingtrackingtrouble/' . $fileName,
                    'extension' => $extension,
                    'size' => $size,
                ];
            }
        } catch (\Throwable $e) {
            $this->deleteLampiranFiles($stored);
            throw $e;
        }

        return $stored;
    }

    public function deleteLampiranFiles(array $stored)
    {
        foreach ($stored as $item) {
            $relative = ltrim((string) ($item['path'] ?? ''), '/');
            if ($relative === '' || strpos($relative, 'samplingtrackingtrouble/') !== 0) {
                continue;
            }
            $full = public_path($relative);
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    protected function normalizeUploads($files)
    {
        if (!$files) {
            return [];
        }
        if ($files instanceof \Illuminate\Http\UploadedFile) {
            return [$files];
        }
        if (!is_array($files)) {
            return [];
        }

        $flat = [];
        array_walk_recursive($files, function ($file) use (&$flat) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $flat[] = $file;
            }
        });

        return $flat;
    }

}
