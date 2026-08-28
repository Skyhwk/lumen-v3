<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Services\RecruitmentStatusService;
use App\Services\RecruitmentPictureService;
use App\Services\AtsNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DirectorDecisionController extends Controller
{
    public function overview(Request $request)
    {
        $recruitment = DB::table('new_recruitment')
            ->where('token_approval', $request->input('token_approval'))
            ->first();

        if (!$recruitment) {
            return response()->json(['message' => 'Link keputusan tidak valid.'], 404);
        }
        if ($recruitment->status !== 'management_decision') {
            return response()->json([
                'result' => 'unavailable',
                'message' => 'Link keputusan sudah kedaluwarsa atau kandidat tidak berada pada tahap keputusan direktur.',
            ], 403);
        }

        return response()->json(['result' => 'ready', 'candidate' => $this->candidate($recruitment)]);
    }

    public function decide(Request $request)
    {
        $decision = strtolower(trim((string) $request->input('decision')));
        if (!in_array($decision, ['approve', 'reject', 'keep'], true)) {
            return response()->json(['message' => 'Keputusan tidak valid.'], 422);
        }
        $rejectReason = trim((string) $request->input('reject_reason'));
        if ($decision === 'reject' && $rejectReason === '') {
            return response()->json(['message' => 'Alasan penolakan wajib diisi.'], 422);
        }

        return DB::transaction(function () use ($request, $decision, $rejectReason) {
            $recruitment = DB::table('new_recruitment')
                ->where('token_approval', $request->input('token_approval'))
                ->lockForUpdate()
                ->first();

            if (!$recruitment) {
                return response()->json(['message' => 'Link keputusan tidak valid.'], 404);
            }

            $history = json_decode($recruitment->meta_history ?: '[]', true);
            $history = is_array($history) ? $history : [];
            $lastHistory = !empty($history) ? $history[count($history) - 1] : [];
            $lastHistoryStatus = (string) ($lastHistory['status'] ?? '');
            if ($decision === 'keep' && $lastHistoryStatus === 'management_decision_kept' && (int) ($recruitment->is_keep ?? 0) === 1) {
                $result = $this->result($recruitment, 'kept', $lastHistory['at'] ?? null, true);
                $result['requested_decision'] = 'keep';
                $result['remind_at'] = !empty($lastHistory['at'])
                    ? Carbon::parse($lastHistory['at'])->addDays(7)->toDateTimeString()
                    : null;
                return response()->json($result);
            }

            $finalStatus = null;
            if (preg_match('/_(approved|rejected)$/', $lastHistoryStatus, $matches)) {
                $finalStatus = $matches[1];
            } elseif (in_array($lastHistoryStatus, ['approved', 'rejected'], true)) {
                $finalStatus = $lastHistoryStatus;
            } elseif (in_array($recruitment->status, ['approved', 'rejected'], true)) {
                $finalStatus = $recruitment->status;
            }

            if ($finalStatus) {
                $result = $this->result($recruitment, $finalStatus, $lastHistory['at'] ?? $this->decisionAt($recruitment, $finalStatus), true);
                $result['requested_decision'] = $decision;
                $result['returned_to_hrd'] = false;
                $result['next_status'] = $recruitment->status;
                return response()->json($result);
            }

            if ($recruitment->status !== 'management_decision') {
                return response()->json([
                    'result' => 'unavailable',
                    'requested_decision' => $decision,
                    'message' => 'Link keputusan sudah kedaluwarsa atau kandidat tidak berada pada tahap keputusan direktur.',
                    'candidate' => $this->candidate($recruitment),
                ], 403);
            }

            $now = Carbon::now();
            if ($decision === 'keep') {
                DB::table('new_recruitment')->where('id', $recruitment->id)->update([
                    'is_keep' => 1,
                    'updated_at' => $now,
                ]);
                (new RecruitmentStatusService())->update(
                    $recruitment->id,
                    'management_decision',
                    $now,
                    'management_decision_kept'
                );

                $result = $this->result($recruitment, 'kept', $now->toDateTimeString(), false);
                $result['requested_decision'] = 'keep';
                $result['remind_at'] = $now->copy()->addDays(7)->toDateTimeString();
                return response()->json($result);
            }

            $status = $decision === 'approve' ? 'internal_sallary_offer' : 'management_decision';
            $historyStatus = $recruitment->status . '_' . ($decision === 'approve' ? 'approved' : 'rejected');
            DB::table('new_recruitment')->where('id', $recruitment->id)->update(
                $decision === 'approve'
                    ? ['approved_by' => 'Direktur', 'approved_at' => $now, 'is_keep' => 0]
                    : [
                        'rejected_by' => 'Direktur',
                        'rejected_at' => $now,
                        'rejected_decision' => 1,
                        'rejected_decision_reason' => $rejectReason,
                        'is_keep' => 0,
                    ]
            );
            (new RecruitmentStatusService())->update(
                $recruitment->id,
                $status,
                $now,
                $historyStatus,
                $decision === 'reject' ? ['reject_reason' => $rejectReason] : []
            );

            if ($decision === 'reject') {
                app(AtsNotificationService::class)->notifyHrdTeam(
                    'Kandidat Ditolak Direktur',
                    "Direktur menolak kandidat {$recruitment->nama_lengkap}. Alasan: {$rejectReason}",
                    AtsNotificationService::URL_FINAL_DECISION
                );
            }

            $result = $this->result($recruitment, $decision === 'approve' ? 'approved' : 'rejected', $now->toDateTimeString(), false);
            $result['requested_decision'] = $decision;
            $result['returned_to_hrd'] = false;
            $result['next_status'] = $status;
            return response()->json($result);
        });
    }

    private function result($recruitment, $status, $at, $alreadyProcessed)
    {
        return [
            'result' => $status,
            'already_processed' => $alreadyProcessed,
            'decided_at' => $at,
            'candidate' => $this->candidate($recruitment),
        ];
    }

    private function candidate($recruitment)
    {
        $birthDate = $recruitment->tanggal_lahir ?? $recruitment->tempat_tanggal_lahir ?? null;
        $shioElemen = ShioElemenHelper::resolve($birthDate, $recruitment->shio ?? null, $recruitment->elemen ?? null);

        $salaryOffer = DB::table('sallary_offer')
            ->where('new_recruitment_id', $recruitment->id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (!$salaryOffer) {
            $salaryOffer = DB::table('sallary_offer')
                ->where('new_recruitment_id', $recruitment->id)
                ->orderByDesc('id')
                ->first();
        }

        $expectedSalary = $salaryOffer->sallary_offer_hrd ?? $recruitment->ekspetasi_gaji ?? null;

        return [
            'nama_lengkap' => $recruitment->nama_lengkap,
            'posisi_dilamar' => $this->positionLabel($recruitment),
            'shio' => $shioElemen['shio'] ?? $recruitment->shio ?? '-',
            'elemen' => $shioElemen['elemen'] ?? $recruitment->elemen ?? '-',
            'gaji_terakhir' => $recruitment->gaji_terakhir,
            'ekspetasi_gaji' => $expectedSalary,
            'sallary_offer_hrd' => $salaryOffer->sallary_offer_hrd ?? null,
            'email' => $recruitment->email,
            'no_telepon' => $recruitment->no_telepon,
            'picture_base64' => app(RecruitmentPictureService::class)->toDataUri($recruitment->picture ?? null),
        ];
    }

    private function decisionAt($recruitment, $status)
    {
        return $status === 'approved' ? $recruitment->approved_at : $recruitment->rejected_at;
    }

    private function positionLabel($recruitment)
    {
        $alias = DB::table('personnel_requests')
            ->where('id', $recruitment->personnel_request_id)
            ->value('divisi_alias');

        return $alias ?: $recruitment->posisi_dilamar;
    }

}
