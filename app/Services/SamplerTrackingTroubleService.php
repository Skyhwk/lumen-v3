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

    protected function ready()
    {
        if (!Schema::hasTable(self::TABLE)) {
            throw new HttpException(503, 'Konfigurasi pemeriksaan activity sampler belum tersedia. Hubungi administrator.');
        }
    }

    protected function progress($samplerId, $date)
    {
        $sessions = SamplerTrackingSession::with('activeMembers.events')->where('is_active', true)
            ->whereDate('tanggal_sampling', $date)->whereHas('activeMembers', function ($query) use ($samplerId) {
                $query->where('sampler_id', $samplerId);
            })->get();
        return SamplerTrackingActivity::progress((new SamplerTrackingService())->consolidateActivities($sessions), $samplerId);
    }

    public function collect($day = null, array $samplerIds = [])
    {
        $this->ready();
        $day = $day ?: Carbon::now('Asia/Jakarta')->subDay()->toDateString();
        // Also catches multi-day work whose deadline was yesterday, not just starts yesterday.
        $dates = SamplerTrackingSession::where('is_active', true)->whereDate('tanggal_sampling', '<=', $day)
            ->when($samplerIds, function ($query) use ($samplerIds) {
                $query->whereHas('activeMembers', function ($members) use ($samplerIds) {
                    $members->whereIn('sampler_id', $samplerIds);
                });
            })
            ->distinct()->orderBy('tanggal_sampling')->pluck('tanggal_sampling');
        $count = 0;
        foreach ($dates as $date) {
            $samplers = DB::table('sampler_tracking_members as m')->join('sampler_tracking_sessions as s', 's.id', '=', 'm.sampler_tracking_session_id')
                ->where('s.is_active', true)->where('m.is_active', true)->where('s.tanggal_sampling', $date)
                ->when($samplerIds, function ($query) use ($samplerIds) { $query->whereIn('m.sampler_id', $samplerIds); })
                ->whereNotNull('m.sampler_id')->distinct()->pluck('m.sampler_id');
            foreach ($samplers as $samplerId) {
                if (!$samplerId || DB::table(self::TABLE)->where('sampler_id', $samplerId)->where('activity_date', $date)->exists()) continue;
                $progress = $this->progress($samplerId, $date);
                if ($progress['complete'] || $progress['due_date'] !== $day) continue;
                $count += DB::table(self::TABLE)->insertOrIgnore([
                    'sampler_id' => $samplerId, 'activity_date' => $date, 'is_clear' => 0,
                    'created_at' => Carbon::now('Asia/Jakarta'), 'updated_at' => Carbon::now('Asia/Jakarta'),
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
            ->where('activity_date', '<', $today)->orderBy('activity_date')->get();
        return $troubles->filter(function ($trouble) {
            $progress = $this->progress($trouble->sampler_id, $trouble->activity_date);
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

    public function assertAllowed($samplerId, $activityDate)
    {
        $troubles = $this->unresolved($samplerId);
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        if ($activityDate > $today) throw new HttpException(422, 'Aktivitas hari mendatang belum dapat dijalankan.');
        if ($activityDate < $today) {
            $trouble = $troubles->firstWhere('activity_date', $activityDate);
            if ($trouble && $trouble->reopened_by && $trouble->reopened_at) return;
            // Legitimate multi-day work remains usable until its due date, unless older trouble blocks it.
            $progress = $this->progress($samplerId, $activityDate);
            if (!$trouble && !$progress['complete'] && $progress['due_date'] >= $today && $troubles->isEmpty()) return;
            throw new HttpException(423, 'Aktivitas hari sebelumnya terkunci. Silakan lapor kepada atasan untuk membuka aktivitas pada tanggal ' . $activityDate . '.');
        }
        if ($troubles->isNotEmpty()) throw new HttpException(423, $this->message($troubles));
    }

    public function reopen($troubleId, $actorId, $note)
    {
        $this->ready();
        return DB::transaction(function () use ($troubleId, $actorId, $note) {
            $trouble = DB::table(self::TABLE)->where('id', $troubleId)->lockForUpdate()->first();
            if (!$trouble) throw new HttpException(404, 'Data trouble tidak ditemukan.');
            // The Tracking Sampler menu already determines who can use this
            // action. Do not additionally hide or lock a trouble based on the
            // sampler's direct-supervisor snapshot, because that data can be
            // stale after a team/supervisor change.
            if (!$actorId) throw new HttpException(403, 'Akses tidak diizinkan.');
            if ($trouble->is_clear) throw new HttpException(422, 'Aktivitas ini sudah selesai.');
            DB::table(self::TABLE)->where('id', $troubleId)->update([
                'reopened_by' => $actorId, 'reopened_at' => Carbon::now('Asia/Jakarta'),
                'reopen_note' => $note, 'updated_at' => Carbon::now('Asia/Jakarta'),
            ]);
            return DB::table(self::TABLE)->where('id', $troubleId)->first();
        });
    }
}
