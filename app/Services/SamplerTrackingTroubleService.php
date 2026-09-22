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

    public static function startDate()
    {
        return env('SAMPLER_TRACKING_TROUBLE_START_DATE', '2026-09-21');
    }

    protected function ready()
    {
        if (!Schema::hasTable(self::TABLE)) {
            throw new HttpException(503, 'Konfigurasi pemeriksaan activity sampler belum tersedia. Hubungi administrator.');
        }
    }

    protected function progress($samplerId, $date, $sessionId = null)
    {
        $query = SamplerTrackingSession::with('activeMembers.events')->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('nama_perusahaan')
                    ->orWhereRaw('LOWER(TRIM(nama_perusahaan)) != ?', ['cuti']);
            })
            ->whereDate('tanggal_sampling', $date);

        if ($sessionId) {
            $query->where('id', $sessionId);
        } else {
            $query->whereHas('activeMembers', function ($members) use ($samplerId) {
                $members->where('sampler_id', $samplerId);
            });
        }

        return SamplerTrackingActivity::progress((new SamplerTrackingService())->consolidateActivities($query->get()), $samplerId);
    }

    public function collect($day = null, array $samplerIds = [])
    {
        $this->ready();
        $startDate = self::startDate();
        $day = $day ?: Carbon::now('Asia/Jakarta')->subDay()->toDateString();
        if ($day < $startDate) {
            return 0;
        }

        $dates = SamplerTrackingSession::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('nama_perusahaan')
                    ->orWhereRaw('LOWER(TRIM(nama_perusahaan)) != ?', ['cuti']);
            })
            ->whereDate('tanggal_sampling', '>=', $startDate)
            ->whereDate('tanggal_sampling', '<=', $day)
            ->when($samplerIds, function ($query) use ($samplerIds) {
                $query->whereHas('activeMembers', function ($members) use ($samplerIds) {
                    $members->whereIn('sampler_id', $samplerIds);
                });
            })
            ->distinct()->orderBy('tanggal_sampling')->pluck('tanggal_sampling');
        $count = 0;
        foreach ($dates as $date) {
            if (Carbon::parse($date)->toDateString() < $startDate) {
                continue;
            }
            $members = DB::table('sampler_tracking_members as m')->join('sampler_tracking_sessions as s', 's.id', '=', 'm.sampler_tracking_session_id')
                ->where('s.is_active', true)->where('m.is_active', true)->where('s.tanggal_sampling', $date)
                ->where(function ($q) {
                    $q->whereNull('s.nama_perusahaan')
                        ->orWhereRaw('LOWER(TRIM(s.nama_perusahaan)) != ?', ['cuti']);
                })
                ->when($samplerIds, function ($query) use ($samplerIds) { $query->whereIn('m.sampler_id', $samplerIds); })
                ->whereNotNull('m.sampler_id')
                ->select('m.sampler_id', 's.id as tracking_session_id')
                ->distinct()
                ->get();
            foreach ($members as $member) {
                $samplerId = $member->sampler_id;
                $sessionId = $member->tracking_session_id;
                if (!$samplerId || !$sessionId) {
                    continue;
                }
                $exists = DB::table(self::TABLE)
                    ->where('sampler_id', $samplerId)
                    ->where('tracking_session_id', $sessionId)
                    ->exists();
                if ($exists) {
                    continue;
                }
                $progress = $this->progress($samplerId, $date, $sessionId);
                if ($progress['complete'] || $progress['due_date'] !== $day) {
                    continue;
                }
                $count += DB::table(self::TABLE)->insertOrIgnore([
                    'sampler_id' => $samplerId,
                    'tracking_session_id' => $sessionId,
                    'activity_date' => $date,
                    'is_clear' => 0,
                    'created_at' => Carbon::now('Asia/Jakarta'),
                    'updated_at' => Carbon::now('Asia/Jakarta'),
                ]);
            }
        }
        return $count;
    }

    public function unresolved($samplerId)
    {
        $this->ready();
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $troubles = DB::table(self::TABLE)->where('sampler_id', $samplerId)->where('is_clear', 0)
            ->where('activity_date', '<=', $today)->orderBy('activity_date')->get();
        return $troubles->filter(function ($trouble) {
            $sessionId = $trouble->tracking_session_id ?? null;
            $progress = $this->progress($trouble->sampler_id, $trouble->activity_date, $sessionId);
            if (!$progress['complete']) return true;
            DB::table(self::TABLE)->where('id', $trouble->id)->where('is_clear', 0)->update([
                'is_clear' => 1, 'cleared_at' => Carbon::now('Asia/Jakarta'), 'updated_at' => Carbon::now('Asia/Jakarta'),
            ]);
            return false;
        })->values();
    }

    public function message($troubles)
    {
        return 'Aktivitas sampling Anda pada tanggal ' . $troubles->pluck('activity_date')->implode(', ')
            . ' belum selesai. Silakan lapor kepada atasan untuk membuka aktivitas tersebut, lalu selesaikan sebelum melanjutkan aktivitas hari ini.';
    }

    protected function normalizeActivityDate($activityDate)
    {
        return Carbon::parse($activityDate)->toDateString();
    }

    protected function troubleOnDate($troubles, $activityDate, $sessionId = null)
    {
        $target = $this->normalizeActivityDate($activityDate);

        return $troubles->first(function ($trouble) use ($target, $sessionId) {
            if (Carbon::parse($trouble->activity_date)->toDateString() !== $target) {
                return false;
            }
            if ($sessionId && !empty($trouble->tracking_session_id)) {
                return (string) $trouble->tracking_session_id === (string) $sessionId;
            }

            return true;
        });
    }

    public function assertAllowed($samplerId, $activityDate, $sessionId = null)
    {
        $activityDate = $this->normalizeActivityDate($activityDate);
        $troubles = $this->unresolved($samplerId);
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        if ($activityDate > $today) throw new HttpException(422, 'Aktivitas hari mendatang belum dapat dijalankan.');
        if ($activityDate < $today) {
            $trouble = $this->troubleOnDate($troubles, $activityDate, $sessionId);
            if ($trouble && $trouble->reopened_by && $trouble->reopened_at) return;
            $progress = $this->progress($samplerId, $activityDate, $sessionId);
            if (!$trouble && !$progress['complete'] && $progress['due_date'] >= $today && $troubles->isEmpty()) return;
            throw new HttpException(423, 'Aktivitas hari sebelumnya terkunci. Silakan lapor kepada atasan untuk membuka aktivitas pada tanggal ' . $activityDate . '.');
        }
        if ($troubles->isNotEmpty()) throw new HttpException(423, $this->message($troubles));
    }

    public function reopen($troubleId, $actorId, array $payload)
    {
        $this->ready();
        $note = trim((string) ($payload['note'] ?? ''));
        $reopenReason = (string) ($payload['reopen_reason'] ?? '');
        $followUp = (string) ($payload['sampler_follow_up_action'] ?? '');

        return DB::transaction(function () use ($troubleId, $actorId, $note, $reopenReason, $followUp, $payload) {
            $trouble = DB::table(self::TABLE)->where('id', $troubleId)->lockForUpdate()->first();
            if (!$trouble) throw new HttpException(404, 'Data trouble tidak ditemukan.');
            if (!$actorId) throw new HttpException(403, 'Akses tidak diizinkan.');
            if ($trouble->is_clear) throw new HttpException(422, 'Aktivitas ini sudah selesai.');
            if ($trouble->reopened_at) {
                throw new HttpException(422, 'Activity ini sudah pernah di-unblock.');
            }

            $sessionIds = $this->normalizeSessionIds($payload['session_id'] ?? null, $payload['session_ids'] ?? []);
            if (empty($sessionIds) && !empty($trouble->tracking_session_id)) {
                $sessionIds = [(int) $trouble->tracking_session_id];
            }

            $targetQuery = DB::table(self::TABLE)
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

}
