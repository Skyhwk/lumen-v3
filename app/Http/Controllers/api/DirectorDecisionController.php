<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\RecruitmentStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DirectorDecisionController extends Controller
{
    public function decide(Request $request)
    {
        $decision = strtolower(trim((string) $request->input('decision')));
        if (!in_array($decision, ['approve', 'reject'], true)) {
            return response()->json(['message' => 'Keputusan tidak valid.'], 422);
        }

        return DB::transaction(function () use ($request, $decision) {
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
                return response()->json($result);
            }

            if ($recruitment->status !== 'management_decision') {
                return response()->json([
                    'result' => 'unavailable',
                    'requested_decision' => $decision,
                    'message' => 'Kandidat tidak berada pada tahap keputusan direktur.',
                    'candidate' => $this->candidate($recruitment),
                ], 409);
            }

            $now = Carbon::now();
            $status = $decision === 'approve' ? 'internal_sallary_offer' : $recruitment->status;
            $historyStatus = $recruitment->status . '_' . ($decision === 'approve' ? 'approved' : 'rejected');
            DB::table('new_recruitment')->where('id', $recruitment->id)->update(
                $decision === 'approve'
                    ? ['approved_by' => 'Direktur', 'approved_at' => $now]
                    : ['rejected_by' => 'Direktur', 'rejected_at' => $now]
            );
            (new RecruitmentStatusService())->update($recruitment->id, $status, $now, $historyStatus);

            $result = $this->result($recruitment, $decision === 'approve' ? 'approved' : 'rejected', $now->toDateTimeString(), false);
            $result['requested_decision'] = $decision;
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
        return [
            'nama_lengkap' => $recruitment->nama_lengkap,
            'posisi_dilamar' => $this->positionLabel($recruitment),
            'email' => $recruitment->email,
            'no_telepon' => $recruitment->no_telepon,
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
