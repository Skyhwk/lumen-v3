<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use App\Models\SamplerTrackingMember;
use App\Models\SamplerTrackingSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SamplerTrackingActivityOpenService
{
    public const TABLE = 'sampler_tracking_activity_opens';

    public function ready(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            throw new HttpException(500, 'Tabel activity open belum tersedia. Jalankan migrasi terbaru.');
        }
    }

    public function hasActiveGrant($samplerId, $targetSessionId, array $activitySessionIds = []): bool
    {
        if (!$samplerId || !$targetSessionId || !Schema::hasTable(self::TABLE)) {
            return false;
        }

        $sessionIds = array_values(array_unique(array_filter(array_map('intval', array_merge(
            [(int) $targetSessionId],
            $activitySessionIds
        )))));

        return DB::table(self::TABLE)
            ->where('sampler_id', $samplerId)
            ->whereIn('target_session_id', $sessionIds)
            ->where('is_active', 1)
            ->exists();
    }

    public function activeGrant($samplerId, $targetSessionId, array $activitySessionIds = [])
    {
        if (!$samplerId || !$targetSessionId || !Schema::hasTable(self::TABLE)) {
            return null;
        }

        $sessionIds = array_values(array_unique(array_filter(array_map('intval', array_merge(
            [(int) $targetSessionId],
            $activitySessionIds
        )))));

        return $this->activeGrantForMember($samplerId, null, $sessionIds, (int) $targetSessionId);
    }

    public function activeGrantForMember($samplerId, $memberId = null, array $sessionIds = [], $memberSessionId = null, array $memberIds = [])
    {
        if (!$samplerId || !Schema::hasTable(self::TABLE)) {
            return null;
        }

        $memberIds = array_values(array_unique(array_filter(array_map('intval', array_merge(
            $memberId ? [(int) $memberId] : [],
            $memberIds
        )))));

        $sessionIds = array_values(array_unique(array_filter(array_map('intval', array_merge(
            $sessionIds,
            $memberSessionId ? [(int) $memberSessionId] : []
        )))));

        $query = DB::table(self::TABLE)
            ->where('is_active', 1)
            ->where(function ($samplerQuery) use ($samplerId) {
                $samplerQuery->where('sampler_id', $samplerId)
                    ->orWhere('sampler_id', (string) $samplerId);
            });

        $query->where(function ($match) use ($memberIds, $sessionIds) {
            $applied = false;
            if ($memberIds) {
                $match->whereIn('target_member_id', $memberIds);
                $applied = true;
            }
            if ($sessionIds) {
                if ($applied) {
                    $match->orWhereIn('target_session_id', $sessionIds);
                } else {
                    $match->whereIn('target_session_id', $sessionIds);
                }
            }
        });

        return $query->orderByDesc('id')->first();
    }

    public function hasActiveGrantForMember($samplerId, $memberId, array $sessionIds = [], $memberSessionId = null, array $memberIds = []): bool
    {
        return $this->activeGrantForMember($samplerId, $memberId, $sessionIds, $memberSessionId, $memberIds) !== null;
    }

    /**
     * @return array{blocked:bool,blocking_session_id:?int,grant:?object}
     */
    public function parallelCheckinState(SamplerTrackingMember $member, SamplerTrackingSession $targetSession, $sessionsOnDate): array
    {
        $activitySessionIds = array_map('intval', $targetSession->activity_session_ids ?? [$targetSession->id]);
        $grant = $this->activeGrantForMember(
            $member->sampler_id,
            (int) $member->id,
            $activitySessionIds,
            (int) $member->sampler_tracking_session_id,
            SamplerTrackingActivity::grantMemberIds($member)
        );
        if ($grant) {
            return [
                'blocked' => false,
                'blocking_session_id' => $grant->blocking_session_id ? (int) $grant->blocking_session_id : null,
                'grant' => $grant,
            ];
        }

        $blockingSessionId = null;
        foreach (collect($sessionsOnDate) as $session) {
            if ((int) $session->id === (int) $targetSession->id) {
                continue;
            }

            $sessionMember = $this->sessionMemberForSampler($session, $member);
            if (!$sessionMember) {
                continue;
            }

            if (!SamplerTrackingActivity::hasEvent($sessionMember, 'checkin')
                || SamplerTrackingActivity::hasEvent($sessionMember, 'checkout')) {
                continue;
            }

            if (SamplerTrackingActivity::sameCustomer($session, $targetSession)) {
                continue;
            }

            $blockingSessionId = (int) $session->id;
            break;
        }

        return [
            'blocked' => $blockingSessionId !== null,
            'blocking_session_id' => $blockingSessionId,
            'grant' => null,
        ];
    }

    public function open($targetMemberId, $actorId, string $note)
    {
        $this->ready();
        $note = trim($note);
        if ($note === '') {
            throw new HttpException(422, 'Catatan wajib diisi.');
        }
        if (!$actorId) {
            throw new HttpException(403, 'Akses tidak diizinkan.');
        }

        return DB::transaction(function () use ($targetMemberId, $actorId, $note) {
            $member = SamplerTrackingMember::with('session')
                ->where('id', $targetMemberId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $session = $member->session;
            if (!$session || !$session->is_active) {
                throw new HttpException(422, 'Activity sampling sudah tidak aktif.');
            }

            if (!SamplerTrackingActivity::isSesaat($member)) {
                throw new HttpException(422, 'Open activity hanya untuk penugasan sesaat.');
            }

            $activitySessionIds = array_map('intval', $session->activity_session_ids ?? [$session->id]);
            if ($this->hasActiveGrantForMember(
                $member->sampler_id,
                (int) $member->id,
                $activitySessionIds,
                (int) $member->sampler_tracking_session_id,
                SamplerTrackingActivity::grantMemberIds($member)
            )) {
                throw new HttpException(422, 'Activity ini sudah dibuka untuk sampler.');
            }

            $tracking = app(SamplerTrackingService::class);
            $sessions = $tracking->listByDate(
                $session->tanggal_sampling,
                $member->sampler_id,
                $member->sampler_name
            );

            if (!$tracking->samplerHasNonSesaatAssignment($sessions, $member->sampler_id, $member->sampler_name)) {
                throw new HttpException(422, 'Open activity hanya untuk kombinasi sesaat dengan penugasan 8 jam / multi hari di hari yang sama.');
            }

            $state = $this->parallelCheckinState($member, $session, $sessions);
            if (!$state['blocked']) {
                throw new HttpException(422, 'Sampler tidak terkunci check-in paralel pada penugasan ini.');
            }

            $now = Carbon::now('Asia/Jakarta');
            $id = DB::table(self::TABLE)->insertGetId([
                'sampler_id' => $member->sampler_id,
                'target_session_id' => $session->id,
                'target_member_id' => $member->id,
                'blocking_session_id' => $state['blocking_session_id'],
                'opened_by' => $actorId,
                'open_note' => $note,
                'is_active' => 1,
                'opened_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            SamplerTrackingHistory::append((int) $session->id, 'activity_parallel_opened', [
                'sampler_id' => $member->sampler_id,
                'sampler_name' => $member->sampler_name,
                'opened_by' => $actorId,
                'blocking_session_id' => $state['blocking_session_id'],
                'note' => $note,
            ]);

            if ($state['blocking_session_id']) {
                SamplerTrackingHistory::append((int) $state['blocking_session_id'], 'activity_parallel_opened', [
                    'sampler_id' => $member->sampler_id,
                    'sampler_name' => $member->sampler_name,
                    'opened_by' => $actorId,
                    'target_session_id' => $session->id,
                    'note' => $note,
                ]);
            }

            return DB::table(self::TABLE)->where('id', $id)->first();
        });
    }

    public function clearActiveGrantForMember(SamplerTrackingMember $member, string $reason = 'checkout'): void
    {
        if (!Schema::hasTable(self::TABLE) || !$member->sampler_id) {
            return;
        }

        $rows = DB::table(self::TABLE)
            ->where('sampler_id', $member->sampler_id)
            ->where('target_session_id', $member->sampler_tracking_session_id)
            ->where('is_active', 1)
            ->lockForUpdate()
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $now = Carbon::now('Asia/Jakarta');
        DB::table(self::TABLE)->whereIn('id', $rows->pluck('id')->all())->update([
            'is_active' => 0,
            'cleared_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function payload($grant)
    {
        if (!$grant) {
            return null;
        }

        $openedByName = null;
        if (!empty($grant->opened_by)) {
            $openedByName = MasterKaryawan::where('id', $grant->opened_by)->value('nama_lengkap');
        }

        return [
            'id' => (int) $grant->id,
            'opened_at' => $grant->opened_at,
            'opened_by' => $grant->opened_by ? (int) $grant->opened_by : null,
            'opened_by_name' => $openedByName,
            'open_note' => $grant->open_note,
            'blocking_session_id' => $grant->blocking_session_id ? (int) $grant->blocking_session_id : null,
            'target_session_id' => $grant->target_session_id ? (int) $grant->target_session_id : null,
        ];
    }

    protected function sessionMemberForSampler($session, SamplerTrackingMember $member)
    {
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
}
