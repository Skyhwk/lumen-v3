<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\SamplerTrackingService;
use App\Services\SamplerTrackingTroubleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SamplerTrackingController extends Controller
{
    protected $service;

    public function __construct(Request $request, SamplerTrackingService $service)
    {
        parent::__construct($request);
        $this->service = $service;
    }

    public function index(Request $request)
    {
        if ($request->has('draw')) {
            if ($request->tracking_status === 'trouble_selesai') {
                return response()->json($this->teamTroubleDataTable($request));
            }

            if ($request->tracking_status === 'overdue') {
                $this->ensurePastDueTroublesCollected(null);
                $today = Carbon::now('Asia/Jakarta')->toDateString();
                $trouble = $this->teamTroubleRows(null, 'trouble');
                $overdue = $this->service->listTrackingRows($today, $request->sampler_id, $request->sampler_name, 'overdue')['data'];
                $combined = $this->uniqueTrackingRows(
                    $this->mergeOverdueWithTrouble($overdue, $trouble)->filter(function ($row) {
                        return strtolower(trim($row['nama_perusahaan'] ?? '')) !== 'cuti';
                    })
                );
                $combined = $this->excludeUnblockedFromBlockedTab($combined, null);

                $recordsTotal = $combined->count();
                $filteredRows = $this->service->filterTrackingRows($combined, $request);
                $recordsFiltered = $filteredRows->count();
                $sortedRows = $this->service->sortTrackingRows($filteredRows, $request)->values();
                $start = (int) ($request->start ?? 0);
                $length = (int) ($request->length ?? 25);
                if ($length > -1) {
                    $sortedRows = $sortedRows->slice($start, $length)->values();
                }
                return response()->json([
                    'draw' => (int) ($request->draw ?? 0),
                    'recordsTotal' => $recordsTotal,
                    'recordsFiltered' => $recordsFiltered,
                    'tracking_status_counts' => [
                        'overdue' => $recordsTotal,
                    ],
                    'trouble_tab_counts' => [
                        'trouble' => $recordsTotal,
                    ],
                    'data' => $sortedRows,
                ]);
            }

            return response()->json($this->service->dataTableByDate(
                $request,
                $request->sampler_id,
                $request->sampler_name
            ));
        }

        $data = $this->service->listTrackingRows(
            $request->tanggal,
            $request->sampler_id,
            $request->sampler_name,
            $request->tracking_status
        );

        return response()->json([
            'success' => true,
            'data' => $data['data'],
            'tracking_status_counts' => $data['tracking_status_counts'],
        ]);
    }

    public function previewSync(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->previewSync($request->tanggal),
        ]);
    }

    public function sync(Request $request)
    {
        try {
            $sessions = $this->service->sync($request->tanggal);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            return response()->json([
                'message' => collect($errors)->flatten()->first() ?: 'Sync tracking sampler gagal karena data jadwal tidak valid.',
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data tracking sampler berhasil disinkronkan.',
            'total_session' => $sessions->count(),
            'data' => $this->service->previewSync($request->tanggal),
        ]);
    }
    public function storeEvent(Request $request)
    {
        $member = \App\Models\SamplerTrackingMember::with('session')->where('id', $request->member_id)->where('is_active', true)->firstOrFail();
        if (!$member->session || !$member->session->is_active) abort(422, 'Activity sampling sudah tidak aktif.');
        (new \App\Services\SamplerTrackingTroubleService())->assertAllowed($member->sampler_id, $member->session->tanggal_sampling, $member->sampler_tracking_session_id);
        $this->validate($request, [
            'member_id' => 'required',
            'event_type' => 'required|in:departure,checkin,checkout,return',
            'latitude' => 'nullable',
            'longitude' => 'nullable',
            'photo' => 'nullable',
            'photos' => 'nullable',
            'note' => 'nullable',
            'vehicle_plate' => 'nullable',
            'event_at' => 'nullable',
        ]);

        if ($request->event_type === 'checkout') {
            $basWarning = $this->service->checkoutBasWarning($request->member_id);
            if ($basWarning) {
                return response()->json([
                    'success' => false,
                    'message' => $basWarning['message'],
                    'data' => $basWarning,
                ], 422);
            }
        }

        $events = $this->service->storeEvent($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Tracking sampler berhasil disimpan.',
            'total_event' => $events->count(),
            'data' => $events,
        ]);
    }

    public function updateRouteOrder(Request $request)
    {
        $this->validate($request, [
            'tanggal' => 'required',
            'reason' => 'required',
            'items' => 'required|array',
            'items.*.session_id' => 'required',
            'items.*.route_order' => 'nullable',
        ]);

        $data = $this->service->updateRouteOrder($request->all(), $request->sampler_name);

        return response()->json([
            'success' => true,
            'message' => 'Urutan tujuan sampling berhasil disimpan.',
            'data' => $data,
        ]);
    }
    public function updateMovementGroup(Request $request)
    {
        $this->validate($request, [
            'member_ids' => 'required|array',
            'movement_group' => 'nullable',
        ]);

        $movementGroup = $this->service->updateMovementGroup(
            $request->member_ids,
            $request->movement_group
        );

        return response()->json([
            'success' => true,
            'message' => 'Movement group berhasil diupdate.',
            'movement_group' => $movementGroup,
        ]);
    }

    public function reopenTrouble(Request $request)
    {
        $allowedGradesConfig = env('SAMPLER_TRACKING_UNBLOCK_GRADES', 'MANAGER,SENIOR MANAGER');
        $allowedGrades = array_filter(array_map('trim', explode(',', strtoupper((string) $allowedGradesConfig))));

        $userGrade = $this->effectiveGrade();
        if (!$userGrade || !in_array($userGrade, $allowedGrades, true)) {
            $allowedStr = implode(', ', $allowedGrades);
            return response()->json([
                'success' => false,
                'message' => "Akses ditolak. Fitur unblock activity sampler hanya dapat dilakukan oleh user dengan grade: {$allowedStr}.",
            ], 403);
        }

        $reasonKeys = implode(',', SamplerTrackingTroubleService::reopenReasonKeys());
        $followUpKeys = implode(',', SamplerTrackingTroubleService::samplerFollowUpKeys());

        $this->validate($request, [
            'trouble_id' => 'required|integer',
            'session_id' => 'nullable|integer',
            'session_ids' => 'nullable|array',
            'session_ids.*' => 'integer',
            'reopen_reason' => 'required|string|in:' . $reasonKeys,
            'sampler_follow_up_action' => 'required|string|in:' . $followUpKeys,
            'note' => 'required|string|max:2000',
        ]);

        $service = new SamplerTrackingTroubleService();
        $lampiran = [];
        try {
            $lampiran = $service->storeLampiran($request->file('lampiran'), $request->trouble_id);
            $data = $service->reopen($request->trouble_id, $this->user_id, [
                'note' => $request->note,
                'reopen_reason' => $request->reopen_reason,
                'sampler_follow_up_action' => $request->sampler_follow_up_action,
                'session_id' => $request->session_id,
                'session_ids' => $request->session_ids,
                'lampiran' => $lampiran,
            ]);
        } catch (\Throwable $e) {
            if ($lampiran) {
                $service->deleteLampiranFiles($lampiran);
            }
            throw $e;
        }

        $actor = \App\Models\MasterKaryawan::find($this->user_id);
        if ($actor && is_object($data)) {
            $data->reopened_by_name = $actor->nama_lengkap;
        }

        return response()->json([
            'success' => true,
            'message' => 'Akses activity lama berhasil dibuka (unblocked) untuk seluruh anggota sesi. Sampler dapat menyelesaikannya.',
            'data' => $data
        ]);
    }

    public function teamTroubles(Request $request)
    {
        if (!$this->user_id) abort(403, 'Akses tidak diizinkan.');
        $this->ensurePastDueTroublesCollected(null);
        $active = $this->teamTroubleRows(null, 'trouble');
        $selesai = $this->teamTroubleRows(null, 'trouble_selesai');

        return response()->json([
            'success' => true,
            'data' => $active,
            'trouble_selesai' => $selesai,
            'counts' => [
                'trouble' => $active->count(),
                'trouble_selesai' => $selesai->count(),
            ],
        ]);
    }

    private function effectiveGrade(): string
    {
        $grade = $this->grade;
        $isDevMode = env('APP_ENV') !== 'production' && env('DEV_BYPASS_USER_ID') !== null;
        $devUserId = env('DEV_BYPASS_USER_ID');

        if ($isDevMode && $devUserId) {
            $devKaryawan = \App\Models\MasterKaryawan::where('id', $devUserId)->first();
            if ($devKaryawan && $devKaryawan->grade) {
                $grade = $devKaryawan->grade;
            }
        }

        return strtoupper(trim((string) $grade));
    }

    protected function normalizeTroubleFilterDate($date)
    {
        if (!$date) {
            return null;
        }

        try {
            $parsed = Carbon::parse($date)->toDateString();
        } catch (\Exception $exception) {
            return null;
        }

        $today = Carbon::now('Asia/Jakarta')->toDateString();
        if ($parsed > $today) {
            return null;
        }

        return $parsed;
    }

    protected function resolveSampler($samplerId)
    {
        $sampler = \App\Models\MasterKaryawan::where('id', $samplerId)->first();
        if ($sampler) {
            return $sampler;
        }

        return \App\Models\MasterKaryawan::where('id', (string) $samplerId)->first();
    }

    protected function troublePayload($trouble, $reopenedByMap = null)
    {
        $troubleObj = (array) $trouble;
        $troubleObj['reopened_by_name'] = $reopenedByMap
            ? ($reopenedByMap->get($trouble->reopened_by) ?? null)
            : null;
        $troubleObj['reopen_reason_label'] = SamplerTrackingTroubleService::reopenReasonLabel($trouble->reopen_reason ?? null);
        $troubleObj['sampler_follow_up_action_label'] = SamplerTrackingTroubleService::samplerFollowUpLabel($trouble->sampler_follow_up_action ?? null);
        $lampiran = $trouble->lampiran ?? null;
        if (is_string($lampiran)) {
            $decoded = json_decode($lampiran, true);
            $lampiran = is_array($decoded) ? $decoded : [];
        }
        $troubleObj['lampiran'] = is_array($lampiran) ? $lampiran : [];

        return $troubleObj;
    }

    protected function attachTroubleToTrackingRows($trouble, $sampler, $troubleObj)
    {
        $samplerId = $sampler ? $sampler->id : $trouble->sampler_id;
        $samplerName = $sampler ? $sampler->nama_lengkap : (string) $trouble->sampler_id;
        $activityDate = Carbon::parse($trouble->activity_date)->toDateString();

        $trackingRows = $this->service->listTrackingRows(
            $activityDate,
            $samplerId,
            $samplerName,
            null,
            $trouble->tracking_session_id ? [$trouble->tracking_session_id] : []
        )['data'];

        $troubleSessionId = $trouble->tracking_session_id ?? null;
        $trackingRows = $trackingRows->filter(function ($row) use ($activityDate, $troubleSessionId) {
            $tanggal = $row['tanggal_sampling'] ?? null;
            if (!$tanggal || $tanggal === '-') {
                return false;
            }

            try {
                if (Carbon::parse($tanggal)->toDateString() !== $activityDate) {
                    return false;
                }
            } catch (\Exception $exception) {
                return false;
            }

            if (!$troubleSessionId) {
                return true;
            }

            $rowSessionIds = collect($row['sessions'] ?? [])
                ->map(function ($session) {
                    return is_object($session) ? ($session->id ?? null) : ($session['id'] ?? null);
                })
                ->merge([
                    is_object($row['session'] ?? null) ? $row['session']->id : ($row['session']['id'] ?? null),
                ])
                ->filter()
                ->map(function ($id) {
                    return (string) $id;
                });

            return $rowSessionIds->contains((string) $troubleSessionId);
        })->values();

        if ($trackingRows->isEmpty()) {
            $troubleObj['sampler_name'] = $samplerName;

            return collect([$this->syntheticTroubleRow($trouble, $samplerName, $troubleObj)]);
        }

        return $trackingRows->map(function ($row) use ($trouble, $troubleObj, $samplerName, $samplerId, $troubleSessionId) {
            $sessionIds = collect($row['sessions'] ?? [])
                ->map(function ($session) {
                    return is_object($session) ? ($session->id ?? null) : ($session['id'] ?? null);
                })
                ->merge([
                    is_object($row['session'] ?? null) ? $row['session']->id : ($row['session']['id'] ?? null),
                ])
                ->filter()
                ->unique()
                ->values()
                ->all();

            $troubleObj['sampler_id'] = $samplerId;
            $troubleObj['sampler_name'] = $samplerName;
            $troubleObj['session_id'] = $troubleSessionId ?: ($sessionIds[0] ?? null);
            $troubleObj['session_ids'] = $troubleSessionId ? [(int) $troubleSessionId] : $sessionIds;
            $row['trouble'] = $troubleObj;
            $row['trouble_id'] = $trouble->id;
            $row['tracking_session_id'] = $trouble->tracking_session_id;
            $row['row_id'] = 'trouble-' . $trouble->id;
            $row['session_id'] = $troubleObj['session_id'];
            $row['session_ids'] = $troubleObj['session_ids'];

            return $row;
        });
    }

    /**
     * Satu session: gabung seluruh tim jika semua yang kendala belum pulang;
     * jika ada rekan yang sudah pulang, tampilkan hanya sampler yang masih belum pulang.
     */
    protected function consolidateBlockedTeamRows($rows)
    {
        $rows = collect($rows);
        $withoutSession = $rows->filter(function ($row) {
            return empty($row['tracking_session_id']);
        })->values();

        $grouped = $rows->filter(function ($row) {
            return !empty($row['tracking_session_id']);
        })->groupBy(function ($row) {
            $date = $row['tanggal_sampling'] ?? '-';
            try {
                $date = Carbon::parse($date)->toDateString();
            } catch (\Exception $exception) {
                // keep raw date token for grouping
            }

            return (string) $row['tracking_session_id'] . '|' . $date;
        });

        $merged = $grouped->map(function ($groupRows) {
            return $this->mergeBlockedTeamGroup($groupRows);
        })->filter()->values();

        return $withoutSession->concat($merged)->values();
    }

    protected function mergeBlockedTeamGroup($groupRows)
    {
        $groupRows = collect($groupRows);
        $baseRow = $groupRows->sortByDesc(function ($row) {
            return count($row['members'] ?? []);
        })->first();

        $troubleSamplerIds = $groupRows->map(function ($row) {
            return $row['trouble']['sampler_id'] ?? null;
        })->filter()->unique()->values();

        $pendingIds = $troubleSamplerIds->filter(function ($samplerId) use ($baseRow) {
            return !$this->blockedSamplerHasReturn($baseRow, $samplerId);
        })->values();

        if ($pendingIds->isEmpty()) {
            return null;
        }

        $samplerNames = $pendingIds->map(function ($samplerId) use ($groupRows, $baseRow) {
            return $this->blockedSamplerDisplayName($samplerId, $groupRows, $baseRow);
        })->unique()->values()->all();

        $merged = $this->pinBlockedRowToSamplers($baseRow, $pendingIds->all(), $samplerNames);
        $sessionId = $baseRow['tracking_session_id'];
        $pendingTroubleRows = $groupRows->filter(function ($row) use ($pendingIds) {
            return $pendingIds->contains((string) ($row['trouble']['sampler_id'] ?? ''));
        })->values();

        $hasReturnedTeammate = collect($baseRow['members'] ?? [])->contains(function ($member) use ($baseRow, $pendingIds) {
            $samplerId = (string) $this->trackingRowMemberField($member, 'sampler_id');
            if ($pendingIds->contains($samplerId)) {
                return false;
            }

            return $this->blockedSamplerHasReturn($baseRow, $samplerId);
        });

        $wholeTeamStillOut = !$hasReturnedTeammate
            && $pendingIds->count() === $troubleSamplerIds->count()
            && $troubleSamplerIds->count() > 1;

        if ($wholeTeamStillOut) {
            $merged['row_id'] = 'trouble-session-' . $sessionId;
            $merged['trouble_id'] = $pendingTroubleRows->pluck('trouble_id')->filter()->min();
            $merged['trouble_ids'] = $pendingTroubleRows->pluck('trouble_id')->filter()->values()->all();
            $merged['trouble'] = $pendingTroubleRows->first()['trouble'] ?? $merged['trouble'] ?? null;
        } else {
            $primary = $pendingTroubleRows->sortBy('trouble_id')->first();
            $merged['row_id'] = 'trouble-' . ($primary['trouble_id'] ?? $groupRows->first()['trouble_id']);
            $merged['trouble_id'] = $primary['trouble_id'] ?? null;
            $merged['trouble'] = $primary['trouble'] ?? null;
            unset($merged['trouble_ids']);
        }

        return $merged;
    }

    protected function blockedSamplerDisplayName($samplerId, $groupRows, $baseRow)
    {
        $fromTrouble = $groupRows->first(function ($row) use ($samplerId) {
            return (string) ($row['trouble']['sampler_id'] ?? '') === (string) $samplerId;
        });
        if ($fromTrouble && !empty($fromTrouble['trouble']['sampler_name'])) {
            return $fromTrouble['trouble']['sampler_name'];
        }

        $member = collect($baseRow['members'] ?? [])->first(function ($member) use ($samplerId) {
            return (string) $this->trackingRowMemberField($member, 'sampler_id') === (string) $samplerId;
        });
        if ($member) {
            $name = $this->trackingRowMemberField($member, 'sampler_name');
            if ($name) {
                return $name;
            }
        }

        return (string) $samplerId;
    }

    protected function blockedSamplerHasReturn(array $row, $samplerId)
    {
        $members = collect($row['members'] ?? [])->filter(function ($member) use ($samplerId) {
            return (string) $this->trackingRowMemberField($member, 'sampler_id') === (string) $samplerId;
        });

        foreach ($members as $member) {
            if (is_object($member) && $member->relationLoaded('events')
                && \App\Services\SamplerTrackingActivity::hasEvent($member, 'return')) {
                return true;
            }
        }

        $memberIds = $members->map(function ($member) {
            return (string) $this->trackingRowMemberField($member, 'id');
        })->filter()->values();

        return collect($row['events'] ?? [])->contains(function ($event) use ($memberIds) {
            return $this->trackingRowEventField($event, 'event_type') === 'return'
                && $memberIds->contains((string) $this->trackingRowEventField($event, 'sampler_tracking_member_id'));
        });
    }

    /**
     * Scope baris blocked ke sampler tertentu; event/status tidak ikut rekan yang sudah pulang.
     */
    protected function pinBlockedRowToSamplers(array $row, array $samplerIds, array $samplerNames)
    {
        $samplerKeys = collect($samplerIds)->map(function ($id) {
            return (string) $id;
        })->values();

        $members = collect($row['members'] ?? [])->filter(function ($member) use ($samplerKeys) {
            return $samplerKeys->contains((string) $this->trackingRowMemberField($member, 'sampler_id'));
        })->values();

        $memberIds = $members->map(function ($member) {
            return (string) $this->trackingRowMemberField($member, 'id');
        })->filter()->values();

        $events = collect($row['events'] ?? [])->filter(function ($event) use ($memberIds) {
            $eventMemberId = $this->trackingRowEventField($event, 'sampler_tracking_member_id');

            return $eventMemberId && $memberIds->contains((string) $eventMemberId);
        })->values();

        if ($events->isEmpty()) {
            foreach ($members as $member) {
                if (!is_object($member) || !$member->relationLoaded('events')) {
                    continue;
                }
                $events = $events->merge($member->events ?: collect());
            }
            $events = $events->unique(function ($event) {
                return $this->trackingRowEventField($event, 'id');
            })->values();
        }

        $durationValue = $members->map(function ($member) {
            foreach (['effective_duration', 'durasi_personal', 'duration', 'durasi'] as $field) {
                $value = $this->trackingRowMemberField($member, $field);
                if ($value !== null && $value !== '') {
                    return (int) $value;
                }
            }

            return null;
        })->filter(function ($value) {
            return $value !== null;
        })->max();

        $names = collect($samplerNames)->filter()->unique()->values();
        $row['sampler'] = $names->implode(', ');
        $row['sampler_list'] = $names->all();
        $row['samplers'] = $names->all();
        $row['members'] = $members->values()->all();
        $row['events'] = $events->values()->all();
        $row['total_event'] = $events->count();

        $lastEvent = $events->sortByDesc(function ($event) {
            $at = $this->trackingRowEventField($event, 'event_at');

            return $at ? strtotime($at) : 0;
        })->first();
        $row['last_event'] = $lastEvent
            ? (($this->trackingRowEventField($lastEvent, 'event_type') ?: '-') . ' - ' . ($this->trackingRowEventField($lastEvent, 'event_at') ?: '-'))
            : '-';

        $row['tracking_status'] = 'overdue';
        if (!empty($row['trouble']['reopened_at'] ?? null)) {
            $row['tracking_status'] = $this->service->resolveTrackingStatus(
                $events,
                $row['tanggal_sampling'] ?? null,
                $row['jam_mulai'] ?? null,
                $row['jam_selesai'] ?? null,
                $durationValue
            );
        }

        if (!empty($row['trouble']) && is_array($row['trouble'])) {
            $row['trouble']['sampler_name'] = $row['sampler'];
        }

        return $row;
    }

    protected function trackingRowMemberField($member, $field)
    {
        return is_object($member) ? ($member->$field ?? null) : ($member[$field] ?? null);
    }

    protected function trackingRowEventField($event, $field)
    {
        return is_object($event) ? ($event->$field ?? null) : ($event[$field] ?? null);
    }

    protected function syntheticTroubleRow($trouble, $samplerName, $troubleObj)
    {
        $activityDate = Carbon::parse($trouble->activity_date)->toDateString();

        return [
            'row_id' => 'trouble-' . $trouble->id,
            'tanggal_sampling' => $activityDate,
            'nama_perusahaan' => '-',
            'perusahaan_list' => [],
            'sampler' => $samplerName,
            'sampler_list' => [$samplerName],
            'durasi' => '-',
            'jam' => '- - -',
            'jam_mulai' => null,
            'jam_selesai' => null,
            'no_order' => '-',
            'movement_group' => '-',
            'last_event' => '-',
            'total_event' => 0,
            'tracking_status' => 'overdue',
            'events' => [],
            'sessions' => [],
            'members' => [],
            'trouble_id' => $trouble->id,
            'trouble' => $troubleObj,
        ];
    }

    protected function teamTroubleRowsFromQuery($date = null)
    {
        $date = $this->normalizeTroubleFilterDate($date);
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $query = DB::table(SamplerTrackingTroubleService::TABLE)
            ->where('activity_date', '<=', $today)
            ->whereNotNull('reopened_at');

        if ($date) {
            $query->whereDate('activity_date', $date);
        }

        $troubles = $query->orderBy('reopened_at', 'desc')->get();
        if ($troubles->isEmpty()) {
            return collect();
        }

        $reopenedByIds = $troubles->pluck('reopened_by')->filter()->unique()->values();
        $reopenedByMap = $reopenedByIds->isNotEmpty()
            ? \App\Models\MasterKaryawan::whereIn('id', $reopenedByIds)->pluck('nama_lengkap', 'id')
            : collect();

        $rows = $troubles->flatMap(function ($trouble) use ($reopenedByMap) {
            $sampler = $this->resolveSampler($trouble->sampler_id);
            $troubleObj = $this->troublePayload($trouble, $reopenedByMap);

            return $this->attachTroubleToTrackingRows($trouble, $sampler, $troubleObj);
        })->filter(function ($row) {
            return strtolower(trim($row['nama_perusahaan'] ?? '')) !== 'cuti';
        })->values();

        return $this->uniqueTrackingRows($this->consolidateBlockedTeamRows($rows));
    }

    protected function teamTroubleRows($date = null, $status = 'trouble')
    {
        if (!$this->user_id) abort(403, 'Akses tidak diizinkan.');

        $date = $this->normalizeTroubleFilterDate($date);

        if ($status === 'trouble_selesai') {
            return $this->teamTroubleRowsFromQuery($date);
        }

        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $query = DB::table(SamplerTrackingTroubleService::TABLE)
            ->where('activity_date', '<=', $today)
            ->where('is_clear', 0)
            ->whereNull('reopened_at')
            ->whereNotNull('tracking_session_id');

        if ($date) {
            $query->whereDate('activity_date', $date);
        }

        $troubles = $query->orderBy('activity_date')->orderBy('id')->get();
        if ($troubles->isEmpty()) {
            return collect();
        }

        $rows = $troubles->flatMap(function ($trouble) {
            $sampler = $this->resolveSampler($trouble->sampler_id);
            $troubleObj = $this->troublePayload($trouble);

            return $this->attachTroubleToTrackingRows($trouble, $sampler, $troubleObj);
        })->filter(function ($row) {
            return strtolower(trim($row['nama_perusahaan'] ?? '')) !== 'cuti';
        })->values();

        return $this->uniqueTrackingRows($this->consolidateBlockedTeamRows($rows));
    }

    protected function uniqueTrackingRows($rows)
    {
        return collect($rows)
            ->sortByDesc(function ($row) {
                return $this->hasActiveTrouble($row) ? 1 : 0;
            })
            ->unique(function ($row) {
                return $this->trackingMergeKey($row);
            })
            ->values();
    }

    protected function excludeUnblockedFromBlockedTab($rows, $date = null)
    {
        $rows = collect($rows)->reject(function ($row) {
            return !empty($row['trouble']['reopened_at'] ?? null);
        })->values();

        if ($rows->isEmpty()) {
            return $rows;
        }

        $date = $this->normalizeTroubleFilterDate($date);
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $query = DB::table(SamplerTrackingTroubleService::TABLE)
            ->where('activity_date', '<=', $today)
            ->where('is_clear', 0)
            ->whereNotNull('reopened_at');

        if ($date) {
            $query->whereDate('activity_date', $date);
        }

        $unblocked = $query->get(['sampler_id', 'activity_date']);
        if ($unblocked->isEmpty()) {
            return $rows;
        }

        $unblockedKeys = [];
        foreach ($unblocked as $trouble) {
            $unblockedKeys[(string) $trouble->sampler_id . '|' . Carbon::parse($trouble->activity_date)->toDateString()] = true;
        }

        return $rows->reject(function ($row) use ($unblockedKeys) {
            if ($this->hasActiveTrouble($row)) {
                return false;
            }

            $activityDate = $row['tanggal_sampling'] ?? null;
            if (!$activityDate || $activityDate === '-') {
                return false;
            }

            try {
                $activityDate = Carbon::parse($activityDate)->toDateString();
            } catch (\Exception $exception) {
                return false;
            }

            foreach (collect($row['members'] ?? []) as $member) {
                $samplerId = is_object($member) ? ($member->sampler_id ?? null) : ($member['sampler_id'] ?? null);
                if ($samplerId && isset($unblockedKeys[(string) $samplerId . '|' . $activityDate])) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    protected function hasActiveTrouble($row)
    {
        return !empty($row['trouble_id']) && empty($row['trouble']['reopened_at'] ?? null);
    }

    protected function trackingMergeKey($row)
    {
        if (!empty($row['row_id'])) {
            return $row['row_id'];
        }

        if (!empty($row['tracking_session_id'])) {
            return 'trouble-session-' . $row['tracking_session_id'];
        }

        return implode('|', [
            $row['tanggal_sampling'] ?? '',
            $row['no_order'] ?? '',
            $row['nama_perusahaan'] ?? '',
            'trouble-' . ($row['trouble_id'] ?? spl_object_hash((object) $row)),
        ]);
    }

    protected function mergeOverdueWithTrouble($overdue, $trouble)
    {
        $troubleByKey = collect($trouble)->keyBy(function ($row) {
            return $this->trackingMergeKey($row);
        });

        $usedKeys = [];
        $merged = collect($overdue)->map(function ($row) use ($troubleByKey, &$usedKeys) {
            $key = $this->trackingMergeKey($row);
            if (!$troubleByKey->has($key)) {
                return $row;
            }

            $troubleRow = $troubleByKey->get($key);
            $usedKeys[$key] = true;
            $row['trouble_id'] = $troubleRow['trouble_id'] ?? $row['trouble_id'] ?? null;
            $row['trouble'] = $troubleRow['trouble'] ?? $row['trouble'] ?? null;

            return $row;
        });

        $remainingTrouble = collect($trouble)->reject(function ($row) use ($usedKeys) {
            return isset($usedKeys[$this->trackingMergeKey($row)]);
        })->values();

        return $merged->concat($remainingTrouble)->values();
    }

    protected function ensurePastDueTroublesCollected($date)
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $deadline = $date && $date < $today
            ? Carbon::parse($date)->toDateString()
            : Carbon::now('Asia/Jakarta')->subDay()->toDateString();

        if ($deadline >= $today) {
            return;
        }

        try {
            return true;
        } catch (\Throwable $exception) {
            return;
        }
    }

    protected function teamTroubleDataTable(Request $request)
    {
        $status = $request->input('tracking_status', 'trouble');
        $activeRows = $this->teamTroubleRows(null, 'trouble');
        $selesaiRows = $this->teamTroubleRows(null, 'trouble_selesai');
        $rows = $status === 'trouble_selesai' ? $selesaiRows : $activeRows;
        $recordsTotal = $rows->count();

        $rows = $this->service->filterTrackingRows($rows, $request);
        $recordsFiltered = $rows->count();

        $rows = $this->service->sortTrackingRows($rows, $request)->values();

        $start = (int) ($request->start ?? 0);
        $length = (int) ($request->length ?? 25);
        if ($length > -1) {
            $rows = $rows->slice($start, $length)->values();
        }

        return [
            'draw' => (int) ($request->draw ?? 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'tracking_status_counts' => [],
            'trouble_tab_counts' => [
                'trouble' => $activeRows->count(),
                'trouble_selesai' => $selesaiRows->count(),
            ],
            'data' => $rows,
        ];
    }
}
