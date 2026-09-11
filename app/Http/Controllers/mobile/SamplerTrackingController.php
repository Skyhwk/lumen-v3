<?php

namespace App\Http\Controllers\mobile;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\MasterKaryawan;
use App\Models\SamplerTrackingMember;

class SamplerTrackingController extends \App\Http\Controllers\api\SamplerTrackingController
{
    private const PREVIEWER_USER_ID = 601;

    public function index(Request $request)
    {
        $this->validate($request, [
            'preview_sampler_id' => 'nullable|integer',
        ]);

        $isPreview = false;
        $samplerId = $this->user_id;
        $samplerName = $this->karyawan;
        $previewSampler = null;

        if ($request->filled('preview_sampler_id')) {
            if ((int) $this->user_id !== self::PREVIEWER_USER_ID) {
                abort(403, 'Mode preview hanya tersedia untuk user yang berwenang.');
            }

            $previewSampler = MasterKaryawan::where('id', $request->preview_sampler_id)
                ->where('is_active', true)
                ->firstOrFail();
            $samplerId = $previewSampler->id;
            $samplerName = $previewSampler->nama_lengkap;
            $isPreview = true;
        }

        $data = $this->service->listByDate(
            Carbon::now('Asia/Jakarta')->toDateString(),
            $samplerId,
            $samplerName
        );

        // Aktivitas yang bisa dijalankan dari Apps FDL hanya milik sampler
        // yang sedang login. Informasi anggota tim tetap dikirim terpisah agar
        // kartu tim masih dapat ditampilkan, tanpa membuat event anggota lain
        // mengunci alur action sampler ini.
        $data->each(function ($session) use ($samplerId, $samplerName) {
            $teamMembers = $session->activeMembers->values();
            $ownMembers = $teamMembers->filter(function ($member) use ($samplerId, $samplerName) {
                if ($samplerId) {
                    return (string) $member->sampler_id === (string) $samplerId;
                }

                return mb_strtolower(trim((string) $member->sampler_name))
                    === mb_strtolower(trim((string) $samplerName));
            })->values();

            $session->setRelation('teamMembers', $teamMembers);
            $session->setRelation('activeMembers', $ownMembers);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'can_preview_sampler' => (int) $this->user_id === self::PREVIEWER_USER_ID,
            'is_preview' => $isPreview,
            'preview_sampler' => $previewSampler ? [
                'id' => $previewSampler->id,
                'name' => $previewSampler->nama_lengkap,
            ] : null,
        ]);
    }

    public function storeEvent(Request $request)
    {
        $member = SamplerTrackingMember::where('id', $request->member_id)
            ->where('is_active', true)
            ->firstOrFail();

        $isOwner = $this->user_id
            ? (string) $member->sampler_id === (string) $this->user_id
            : mb_strtolower(trim((string) $member->sampler_name)) === mb_strtolower(trim((string) $this->karyawan));

        if (!$isOwner) {
            abort(403, 'Activity sampler lain hanya dapat dilihat melalui mode preview.');
        }

        return parent::storeEvent($request);
    }

    public function previewSamplers(Request $request)
    {
        if ((int) $this->user_id !== self::PREVIEWER_USER_ID) {
            abort(403, 'Mode preview hanya tersedia untuk user yang berwenang.');
        }

        $this->validate($request, [
            'q' => 'nullable|string|max:100',
        ]);

        $samplers = MasterKaryawan::where('is_active', true)
            ->when(trim((string) $request->q) !== '', function ($query) use ($request) {
                $query->where('nama_lengkap', 'like', '%' . trim($request->q) . '%');
            })
            ->orderBy('nama_lengkap')
            ->limit(30)
            ->get(['id', 'nama_lengkap'])
            ->map(function ($sampler) {
                return [
                    'id' => $sampler->id,
                    'name' => $sampler->nama_lengkap,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $samplers,
        ]);
    }

    public function updateRouteOrder(Request $request)
    {
        $this->validate($request, [
            'tanggal' => 'nullable',
            'reason' => 'required',
            'items' => 'required|array',
            'items.*.session_id' => 'required',
            'items.*.route_order' => 'nullable',
        ]);

        $payload = $request->all();
        $payload['sampler_name'] = $this->karyawan;

        $data = $this->service->updateRouteOrder($payload, $this->karyawan);

        return response()->json([
            'success' => true,
            'message' => 'Urutan tujuan sampling berhasil disimpan.',
            'data' => $data,
        ]);
    }
}
