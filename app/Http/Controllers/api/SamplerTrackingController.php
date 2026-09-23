<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\SamplerTrackingService;
use App\Services\SamplerTrackingTroubleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            if ($request->tracking_status === 'trouble' || $request->tracking_status === 'trouble_selesai') {
                return response()->json($this->teamTroubleDataTable($request));
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
        $sessions = $this->service->sync($request->tanggal);

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
            'force_bas_checkout' => 'nullable',
            'vehicle_plate' => 'nullable',
            'event_at' => 'nullable',
        ]);

        if ($request->event_type === 'checkout' && !filter_var($request->force_bas_checkout, FILTER_VALIDATE_BOOLEAN)) {
            $basWarning = $this->service->checkoutBasWarning($request->member_id);
            if ($basWarning) {
                return response()->json([
                    'success' => false,
                    'requires_confirmation' => true,
                    'message' => $basWarning['message'],
                    'data' => $basWarning,
                ], 409);
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

        $userGrade = strtoupper(trim((string) $this->grade));
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
            'reopen_reason' => 'required|string|in:' . $reasonKeys,
            'sampler_follow_up_action' => 'required|string|in:' . $followUpKeys,
            'note' => 'required|string|max:2000',
        ]);

        $data = (new SamplerTrackingTroubleService())->reopen($request->trouble_id, $this->user_id, [
            'note' => $request->note,
            'reopen_reason' => $request->reopen_reason,
            'sampler_follow_up_action' => $request->sampler_follow_up_action,
        ]);
        
        $actor = \App\Models\MasterKaryawan::find($this->user_id);
        if ($actor && is_object($data)) {
            $data->reopened_by_name = $actor->nama_lengkap;
        }

        return response()->json([
            'success' => true,
            'message' => 'Akses activity lama berhasil dibuka (unblocked). Sampler dapat menyelesaikannya.',
            'data' => $data
        ]);
    }

    public function teamTroubles(Request $request)
    {
        if (!$this->user_id) abort(403, 'Akses tidak diizinkan.');
        $date = $request->input('tanggal');
        $active = $this->teamTroubleRows($date, 'trouble');
        $selesai = $this->teamTroubleRows($date, 'trouble_selesai');

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
        if ($parsed >= $today) {
            return null;
        }

        return $parsed;
    }

    protected function supervisedSamplerIds($date = null, $reopenedOnly = false)
    {
        $date = $this->normalizeTroubleFilterDate($date);
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $query = DB::table(SamplerTrackingTroubleService::TABLE)
            ->whereNotNull('tracking_session_id')
            ->where('activity_date', '<', $today);

        if ($reopenedOnly) {
            $query->whereNotNull('reopened_at');
        } else {
            $query->where('is_clear', 0)->whereNull('reopened_at');
        }

        if ($date) {
            $query->whereDate('activity_date', $date);
        }

        return $query->distinct()->pluck('sampler_id');
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

        $trackingRows = $trackingRows->filter(function ($row) use ($activityDate) {
            $tanggal = $row['tanggal_sampling'] ?? null;
            if (!$tanggal || $tanggal === '-') {
                return false;
            }

            try {
                return Carbon::parse($tanggal)->toDateString() === $activityDate;
            } catch (\Exception $exception) {
                return false;
            }
        })->values();

        if ($trackingRows->isEmpty()) {
            $troubleObj['sampler_name'] = $samplerName;

            return collect([$this->syntheticTroubleRow($trouble, $samplerName, $troubleObj)]);
        }

        return $trackingRows->map(function ($row) use ($trouble, $troubleObj, $samplerName) {
            $troubleObj['sampler_name'] = $row['sampler'] ?? $samplerName;
            $row['trouble'] = $troubleObj;
            $row['trouble_id'] = $trouble->id;
            $row['tracking_session_id'] = $trouble->tracking_session_id;
            $row['row_id'] = 'trouble-' . $trouble->id;

            return $row;
        });
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
            ->whereNotNull('tracking_session_id')
            ->where('activity_date', '<', $today)
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

        return $troubles->flatMap(function ($trouble) use ($reopenedByMap) {
            $sampler = $this->resolveSampler($trouble->sampler_id);
            $troubleObj = $this->troublePayload($trouble, $reopenedByMap);

            return $this->attachTroubleToTrackingRows($trouble, $sampler, $troubleObj);
        })->values();
    }

    protected function teamTroubleRows($date = null, $status = 'trouble')
    {
        if (!$this->user_id) abort(403, 'Akses tidak diizinkan.');

        $date = $this->normalizeTroubleFilterDate($date);

        if ($status === 'trouble_selesai') {
            return $this->teamTroubleRowsFromQuery($date);
        }

        $service = new SamplerTrackingTroubleService();
        $samplerIds = $this->supervisedSamplerIds($date, false);
        $samplers = \App\Models\MasterKaryawan::where('is_active', true)
            ->whereIn('id', $samplerIds)
            ->get();

        return $samplers->flatMap(function ($sampler) use ($service, $date) {
            return $service->unresolved($sampler->id)
                ->filter(function ($trouble) use ($date) {
                    if (!empty($trouble->reopened_at)) {
                        return false;
                    }

                    if (!$date) {
                        return true;
                    }

                    return Carbon::parse($trouble->activity_date)->toDateString()
                        === Carbon::parse($date)->toDateString();
                })
                ->flatMap(function ($trouble) use ($sampler) {
                    $troubleObj = $this->troublePayload($trouble);

                    return $this->attachTroubleToTrackingRows($trouble, $sampler, $troubleObj);
                });
        })->values();
    }

    protected function teamTroubleDataTable(Request $request)
    {
        $date = $request->input('tanggal');
        $status = $request->input('tracking_status', 'trouble');
        $activeRows = $this->teamTroubleRows($date, 'trouble');
        $selesaiRows = $this->teamTroubleRows($date, 'trouble_selesai');
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
