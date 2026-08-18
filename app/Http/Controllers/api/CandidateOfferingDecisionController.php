<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Services\RecruitmentPictureService;
use App\Services\RecruitmentStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CandidateOfferingDecisionController extends Controller
{
    public function overview(Request $request)
    {
        $recruitment = DB::table('new_recruitment')->where('token', $request->input('token'))->first();
        if (!$recruitment) {
            return response()->json(['message' => 'Link offering letter tidak valid.'], 404);
        }

        $invalid = $this->invalidState($recruitment);
        if ($invalid) {
            return response()->json($invalid, 410);
        }

        return response()->json([
            'result' => 'ready',
            'candidate' => $this->candidate($recruitment),
        ]);
    }

    public function decide(Request $request)
    {
        $decision = strtolower(trim((string) $request->input('decision')));
        if (!in_array($decision, ['approve', 'reject'], true)) {
            return response()->json(['message' => 'Keputusan offering letter tidak valid.'], 422);
        }
        $rejectReason = trim((string) $request->input('reject_reason'));
        if ($decision === 'reject' && $rejectReason === '') {
            return response()->json(['message' => 'Alasan penolakan wajib diisi.'], 422);
        }

        return DB::transaction(function () use ($request, $decision, $rejectReason) {
            $recruitment = DB::table('new_recruitment')
                ->where('token', $request->input('token'))
                ->lockForUpdate()
                ->first();

            if (!$recruitment) {
                return response()->json(['message' => 'Link offering letter tidak valid.'], 404);
            }

            $invalid = $this->invalidState($recruitment);
            if ($invalid) {
                return response()->json($invalid, 410);
            }

            $now = Carbon::now();
            $nextStatus = $decision === 'approve' ? 'finance_review' : 'management_decision';
            $historyStatus = 'candidate_offering_' . ($decision === 'approve' ? 'approved' : 'rejected');

            (new RecruitmentStatusService())->update(
                $recruitment->id,
                $nextStatus,
                $now,
                $historyStatus,
                array_filter([
                    'decided_by' => 'Candidate',
                    'reject_reason' => $decision === 'reject' ? $rejectReason : null,
                ], fn ($value) => $value !== null)
            );

            return response()->json([
                'result' => $decision === 'approve' ? 'approved' : 'rejected',
                'message' => $decision === 'approve'
                    ? 'Offering letter berhasil Anda setujui.'
                    : 'Offering letter berhasil Anda tolak.',
                'decided_at' => $now->toDateTimeString(),
                'candidate' => $this->candidate($recruitment),
            ]);
        });
    }

    private function candidate($recruitment)
    {
        $birthDate = $recruitment->tanggal_lahir ?? $recruitment->tempat_tanggal_lahir ?? null;
        $shioElemen = ShioElemenHelper::resolve($birthDate, $recruitment->shio ?? null, $recruitment->elemen ?? null);

        return [
            'nama_lengkap' => $recruitment->nama_lengkap,
            'posisi_dilamar' => $this->positionLabel($recruitment),
            'shio' => $shioElemen['shio'] ?? $recruitment->shio ?? '-',
            'elemen' => $shioElemen['elemen'] ?? $recruitment->elemen ?? '-',
            'gaji_terakhir' => $recruitment->gaji_terakhir,
            'ekspetasi_gaji' => $recruitment->ekspetasi_gaji,
            'picture_base64' => app(RecruitmentPictureService::class)->toDataUri($recruitment->picture ?? null),
        ];
    }

    private function positionLabel($recruitment)
    {
        return DB::table('personnel_requests')
            ->where('id', $recruitment->personnel_request_id)
            ->value('divisi_alias') ?: $recruitment->posisi_dilamar;
    }

    private function invalidState($recruitment)
    {
        $history = json_decode($recruitment->meta_history ?: '[]', true);
        $history = is_array($history) ? $history : [];
        $lastHistory = !empty($history) ? $history[count($history) - 1] : [];
        $lastHistoryStatus = strtolower(trim((string) ($lastHistory['status'] ?? '')));

        if ($lastHistoryStatus !== '' && strpos($lastHistoryStatus, 'reject') !== false) {
            return [
                'result' => 'expired',
                'message' => 'Link offering letter sudah kedaluwarsa karena proses kandidat terakhir berstatus ditolak.',
            ];
        }

        if ((string) $recruitment->status !== 'salary_offer') {
            return [
                'result' => 'unavailable',
                'message' => 'Link offering letter sudah tidak valid untuk tahap proses kandidat saat ini.',
            ];
        }

        return null;
    }
}
