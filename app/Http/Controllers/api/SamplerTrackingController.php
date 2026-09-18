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
            if ($request->tracking_status === 'trouble') {
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
        (new \App\Services\SamplerTrackingTroubleService())->assertAllowed($member->sampler_id, $member->session->tanggal_sampling);
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
        $this->validate($request, ['trouble_id' => 'required|integer', 'note' => 'required|string|max:2000']);
        $data = (new \App\Services\SamplerTrackingTroubleService())->reopen($request->trouble_id, $this->user_id, $request->note);
        return response()->json(['success' => true, 'message' => 'Akses activity lama dibuka. Sampler wajib menyelesaikannya sebelum melanjutkan hari ini.', 'data' => $data]);
    }

    public function teamTroubles(Request $request)
    {
        if (!$this->user_id) abort(403, 'Akses tidak diizinkan.');
        return response()->json(['success' => true, 'data' => $this->teamTroubleRows()]);
    }

    protected function supervisedSamplers()
    {
        // Trouble is an operational queue for every user allowed to open the
        // Tracking Sampler menu. It must not be filtered by atasan_langsung:
        // that relation is a snapshot and can be stale after team changes.
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $samplerIds = DB::table(SamplerTrackingTroubleService::TABLE)
            ->where('is_clear', 0)
            ->whereNull('reopened_at')
            ->where('activity_date', '<', $today)
            ->distinct()
            ->pluck('sampler_id');

        return \App\Models\MasterKaryawan::where('is_active', true)
            ->whereIn('id', $samplerIds)
            ->get(['id', 'nama_lengkap']);
    }

    protected function teamTroubleRows()
    {
        if (!$this->user_id) abort(403, 'Akses tidak diizinkan.');

        $samplers = $this->supervisedSamplers();
        $service = new SamplerTrackingTroubleService();
        return $samplers->flatMap(function ($sampler) use ($service) {
            return $service->unresolved($sampler->id)
                // An opened trouble remains unresolved for the sampler until
                // the old activity is completed, but no longer needs action
                // from the supervisor and must leave this supervisor table.
                ->filter(function ($trouble) {
                    return empty($trouble->reopened_at);
                })
                ->map(function ($trouble) use ($sampler) {
                    $trackingRows = $this->service->listTrackingRows(
                        $trouble->activity_date,
                        $sampler->id,
                        $sampler->nama_lengkap
                    )['data'];

                    return $trackingRows->map(function ($row) use ($trouble) {
                        $trouble->sampler_name = $row['sampler'] ?? null;
                        $row['trouble'] = $trouble;
                        $row['trouble_id'] = $trouble->id;
                        return $row;
                    });
                });
        })->flatten(1)->values();
    }

    protected function teamTroubleDataTable(Request $request)
    {
        $rows = $this->teamTroubleRows();
        $recordsTotal = $rows->count();
        $search = strtolower(trim((string) $request->input('search.value', '')));

        if ($search !== '') {
            $rows = $rows->filter(function ($row) use ($search) {
                return strpos(strtolower(implode(' ', [
                    $row['tanggal_sampling'] ?? '',
                    $row['nama_perusahaan'] ?? '',
                    $row['sampler'] ?? '',
                    $row['no_order'] ?? '',
                    $row['movement_group'] ?? '',
                ])), $search) !== false;
            })->values();
        }

        $recordsFiltered = $rows->count();
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
            'data' => $rows,
        ];
    }
}
