<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\SamplerTrackingService;
use Illuminate\Http\Request;

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
            return response()->json($this->service->dataTableByDate(
                $request,
                $request->sampler_id,
                $request->sampler_name
            ));
        }

        $data = $this->service->listByDate(
            $request->tanggal,
            $request->sampler_id,
            $request->sampler_name
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function sync(Request $request)
    {
        $sessions = $this->service->sync($request->tanggal);

        return response()->json([
            'success' => true,
            'message' => 'Data tracking sampler berhasil disinkronkan.',
            'total_session' => $sessions->count(),
        ]);
    }

    public function storeEvent(Request $request)
    {
        $this->validate($request, [
            'member_id' => 'required',
            'event_type' => 'required|in:departure,checkin,checkout,return',
            'latitude' => 'nullable',
            'longitude' => 'nullable',
            'photo' => 'nullable',
            'note' => 'nullable',
            'vehicle_plate' => 'nullable',
            'event_at' => 'nullable',
        ]);

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
}
