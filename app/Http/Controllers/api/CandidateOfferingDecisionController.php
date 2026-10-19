<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\CandidateOfferingDecisionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CandidateOfferingDecisionController extends Controller
{
    public function overview(Request $request)
    {
        $token = $request->input('token');

        return DB::transaction(function () use ($token) {
            $recruitment = $this->lockByToken($token);
            if (!$recruitment) {
                return response()->json(['message' => 'Link offering letter tidak valid.'], 404);
            }

            $expired = CandidateOfferingDecisionService::approveIfExpired($recruitment);
            if ($expired) {
                return response()->json($expired, 200);
            }

            $invalid = CandidateOfferingDecisionService::invalidState($recruitment);
            if ($invalid) {
                return response()->json($invalid, 403);
            }

            return response()->json([
                'result' => 'ready',
                'candidate' => CandidateOfferingDecisionService::candidatePayload($recruitment),
                'offer' => CandidateOfferingDecisionService::currentOffer($recruitment),
            ]);
        });
    }

    public function decide(Request $request)
    {
        $decision = strtolower(trim((string) $request->input('decision')));
        if (!in_array($decision, ['approve', 'reject', 'negotiate'], true)) {
            return response()->json(['message' => 'Keputusan offering letter tidak valid.'], 422);
        }

        $rejectReason = trim((string) $request->input('reject_reason'));
        if ($decision === 'reject' && $rejectReason === '') {
            return response()->json(['message' => 'Alasan penolakan wajib diisi.'], 422);
        }

        $salary = null;
        $tunjangan = null;
        $startDate = trim((string) $request->input('tanggal_mulai_kerja'));
        if ($decision === 'negotiate') {
            $salary = CandidateOfferingDecisionService::parseMoney($request->input('salary_offer_candidate'));
            $tunjangan = CandidateOfferingDecisionService::parseMoney($request->input('tunjangan_offer_candidate'));
            if ($tunjangan === null) {
                $tunjangan = 0.0;
            }
            if ($salary === null || $salary <= 0) {
                return response()->json(['message' => 'Usulan gaji wajib diisi dengan nominal yang valid.'], 422);
            }
            if ($tunjangan < 0) {
                return response()->json(['message' => 'Usulan tunjangan tidak valid.'], 422);
            }
            if ($startDate === '') {
                return response()->json(['message' => 'Tanggal mulai kerja wajib diisi.'], 422);
            }
        }

        return DB::transaction(function () use ($request, $decision, $rejectReason, $salary, $tunjangan, $startDate) {
            $recruitment = $this->lockByToken($request->input('token'));
            if (!$recruitment) {
                return response()->json(['message' => 'Link offering letter tidak valid.'], 404);
            }

            $expired = CandidateOfferingDecisionService::approveIfExpired($recruitment);
            if ($expired) {
                return response()->json($expired, 200);
            }

            $invalid = CandidateOfferingDecisionService::invalidState($recruitment);
            if ($invalid) {
                return response()->json($invalid, 403);
            }

            $now = Carbon::now();
            if ($decision === 'approve') {
                return response()->json(CandidateOfferingDecisionService::approve($recruitment, $now), 200);
            }
            if ($decision === 'reject') {
                return response()->json(CandidateOfferingDecisionService::reject($recruitment, $now, $rejectReason), 200);
            }

            try {
                return response()->json(
                    CandidateOfferingDecisionService::negotiate($recruitment, $now, $salary, $tunjangan, $startDate),
                    200
                );
            } catch (\InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });
    }

    private function lockByToken($token)
    {
        if (!$token) {
            return null;
        }

        return DB::table('new_recruitment')->where('token', $token)->lockForUpdate()->first();
    }
}
