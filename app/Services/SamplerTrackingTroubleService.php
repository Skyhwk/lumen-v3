<?php

namespace App\Services;

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
        'technical_issue' => 'Kendala teknis aplikasi / perangkat',
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

    public static function startDate()
    {
        return max('2026-09-21', env('SAMPLER_TRACKING_TROUBLE_START_DATE', '2026-09-21'));
    }

    protected function ready()
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, 'tracking_session_id')) {
            throw new HttpException(503, 'Konfigurasi pemeriksaan activity sampler belum tersedia. Hubungi administrator.');
        }
    }

    protected function progress($samplerId, $date, $sessionId)
    {
        $sessions = SamplerTrackingSession::with('activeMembers.events')->where('is_active', true)
            ->whereDate('tanggal_sampling', $date)->whereHas('activeMembers', function ($query) use ($samplerId) {
                $query->where('sampler_id', $samplerId);
            })->get()->reject(function ($session) {
                return mb_strtoupper(trim((string) $session->nama_perusahaan)) === 'CUTI';
            });
        $session = $sessions->firstWhere('id', $sessionId);
        if (!$session) return ['complete' => true, 'due_date' => null];
        $members = $session->activeMembers->filter(function ($member) use ($samplerId) {
            return (string) $member->sampler_id === (string) $samplerId;
        });
        // Normal routes share daily journey events. Reopened assignments must
        // finish their own journey before recovery can be cleared.
        $dailyMembers = $sessions->flatMap(function ($item) use ($samplerId) {
            return $item->activeMembers->filter(function ($member) use ($samplerId) {
                return (string) $member->sampler_id === (string) $samplerId;
            });
        });
        $wasReopened = DB::table(self::TABLE)->where('sampler_id', $samplerId)
            ->where('tracking_session_id', $sessionId)
            ->whereNotNull('reopened_by')->whereNotNull('reopened_at')->exists();
        $journeyMembers = $wasReopened ? $members : $dailyMembers;
        $complete = $members->every(function ($member) {
            return SamplerTrackingActivity::hasEvent($member, 'checkin')
                && SamplerTrackingActivity::hasEvent($member, 'checkout');
        }) && $journeyMembers->contains(function ($member) { return SamplerTrackingActivity::hasEvent($member, 'departure'); })
           && $journeyMembers->contains(function ($member) { return SamplerTrackingActivity::hasEvent($member, 'return'); });
        $duration = $members->max(function ($member) { return SamplerTrackingActivity::duration($member); });
        return ['complete' => $complete, 'due_date' => Carbon::parse($date)->addDays(max(0, $duration - 1))->toDateString()];
    }

    public function collect($day = null, array $samplerIds = [])
    {
        $this->ready();
        $startDate = self::startDate();
        $day = $day ?: Carbon::now('Asia/Jakarta')->subDay()->toDateString();
        if ($day < $startDate) {
            return 0;
        }

        $assignments = DB::table('sampler_tracking_members as m')
            ->join('sampler_tracking_sessions as s', 's.id', '=', 'm.sampler_tracking_session_id')
            ->where('s.is_active', true)->where('m.is_active', true)
            ->whereDate('s.tanggal_sampling', '>=', $startDate)->whereDate('s.tanggal_sampling', '<=', $day)
            ->when($samplerIds, function ($query) use ($samplerIds) { $query->whereIn('m.sampler_id', $samplerIds); })
            ->whereNotNull('m.sampler_id')
            ->select('m.sampler_id', 's.id as tracking_session_id', 's.tanggal_sampling as activity_date')
            ->distinct()->orderBy('s.tanggal_sampling')->orderBy('s.id')->get();
        $count = 0;
        foreach ($assignments as $assignment) {
            if (!$assignment->sampler_id) continue;
            $progress = $this->progress($assignment->sampler_id, $assignment->activity_date, $assignment->tracking_session_id);
            if ($progress['complete'] || !$progress['due_date'] || $progress['due_date'] > $day) continue;
            $count += DB::table(self::TABLE)->insertOrIgnore([
                'sampler_id' => $assignment->sampler_id,
                'tracking_session_id' => $assignment->tracking_session_id,
                'activity_date' => $assignment->activity_date, 'is_clear' => 0,
                'created_at' => Carbon::now('Asia/Jakarta'), 'updated_at' => Carbon::now('Asia/Jakarta'),
            ]);
        }
        return $count;
    }

    public function unresolved($samplerId)
    {
        $this->ready();
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $troubles = DB::table(self::TABLE)->where('sampler_id', $samplerId)
            ->where(function ($query) {
                $query->where('is_clear', 0)->orWhere(function ($reopened) {
                    $reopened->whereNotNull('reopened_by')->whereNotNull('reopened_at');
                });
            })
            ->whereNotNull('tracking_session_id')
            ->where('activity_date', '<', $today)->orderBy('activity_date')->get();
        return $troubles->filter(function ($trouble) {
            $progress = $this->progress($trouble->sampler_id, $trouble->activity_date, $trouble->tracking_session_id);
            if (!$progress['complete']) {
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
            ->where('tracking_session_id', $sessionId)->where('is_clear', 0)
            ->whereNotNull('reopened_by')->whereNotNull('reopened_at')->exists();
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
            if (!$trouble && !$progress['complete'] && $progress['due_date'] >= $today && $troubles->isEmpty()) return;
            throw new HttpException(423, 'Penugasan #' . $sessionId . ' pada tanggal ' . $activityDate . ' terkunci. Silakan lapor kepada atasan.');
        }
        if ($troubles->isNotEmpty()) throw new HttpException(423, $this->message($troubles));
    }

    public function reopen($troubleId, $actorId, array $payload)
    {
        $this->ready();
        $note = trim((string) ($payload['note'] ?? ''));
        $reopenReason = (string) ($payload['reopen_reason'] ?? '');
        $followUp = (string) ($payload['sampler_follow_up_action'] ?? '');

        return DB::transaction(function () use ($troubleId, $actorId, $note, $reopenReason, $followUp) {
            $trouble = DB::table(self::TABLE)->where('id', $troubleId)->lockForUpdate()->first();
            if (!$trouble) throw new HttpException(404, 'Data trouble tidak ditemukan.');
            // The Tracking Sampler menu already determines who can use this
            // action. Do not additionally hide or lock a trouble based on the
            // sampler's direct-supervisor snapshot, because that data can be
            // stale after a team/supervisor change.
            if (!$actorId) throw new HttpException(403, 'Akses tidak diizinkan.');
            if (!$trouble->tracking_session_id) throw new HttpException(422, 'Kendala lama belum terhubung ke penugasan.');
            if ($trouble->is_clear) throw new HttpException(422, 'Aktivitas ini sudah selesai.');
            if ($trouble->reopened_at) {
                throw new HttpException(422, 'Activity ini sudah pernah di-unblock.');
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

            DB::table(self::TABLE)->where('id', $troubleId)->update($update);

            return DB::table(self::TABLE)->where('id', $troubleId)->first();
        });
    }
}
