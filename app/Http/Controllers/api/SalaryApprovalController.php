<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\RecruitmentStatusService;
use App\Services\RecruitmentPictureService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalaryApprovalController extends Controller
{
    public function overview(Request $request)
    {
        $recruitment = $this->recruitment($request->input('token_approval'));
        if (!$recruitment) {
            return response()->json(['message' => 'Link persetujuan penawaran tidak valid.'], 404);
        }

        return response()->json($this->state($recruitment));
    }

    public function decide(Request $request)
    {
        $decision = strtolower(trim((string) $request->input('decision')));
        if ($decision === 'nego') {
            $decision = 'negotiate';
        }
        if (!in_array($decision, ['approve', 'reject', 'negotiate'], true)) {
            return response()->json(['message' => 'Keputusan penawaran tidak valid.'], 422);
        }

        return DB::transaction(function () use ($request, $decision) {
            $recruitment = DB::table('new_recruitment')
                ->where('token_approval', $request->input('token_approval'))
                ->lockForUpdate()
                ->first();
            if (!$recruitment) {
                return response()->json(['message' => 'Link persetujuan penawaran tidak valid.'], 404);
            }

            $state = $this->state($recruitment);
            if (($state['result'] ?? null) !== 'ready') {
                $state['requested_decision'] = $decision;
                return response()->json($state, ($state['result'] ?? null) === 'unavailable' ? 409 : 200);
            }

            $amount = null;
            if ($decision === 'negotiate') {
                $amount = $this->amount($request->input('negotiated_amount'));
                if ($amount === null || $amount <= 0) {
                    return response()->json(['message' => 'Nominal negosiasi wajib diisi dengan nilai yang valid.'], 422);
                }
            }

            $now = Carbon::now();
            $salaryOffer = null;
            if ($decision !== 'reject') {
                $salaryOffer = DB::table('sallary_offer')
                    ->where('new_recruitment_id', $recruitment->id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (!$salaryOffer || $salaryOffer->sallary_offer_hrd === null) {
                    return response()->json(['message' => 'Penawaran salary dari HR belum tersedia.'], 422);
                }

                $finalSalary = $decision === 'approve' ? $salaryOffer->sallary_offer_hrd : $amount;
                $update = [
                    'final_sallary' => $finalSalary,
                    'updated_by' => 'Direktur',
                    'updated_at' => $now,
                ];
                if ($decision === 'negotiate') {
                    $update['sallary_offer_direktur'] = $amount;
                }
                DB::table('sallary_offer')->where('id', $salaryOffer->id)->update($update);
            }

            $historyAction = $decision === 'approve' ? 'approved' : 'rejected';
            if ($decision === 'negotiate') {
                $historyAction = 'negotiated';
            }
            $nextStatus = in_array($decision, ['approve', 'negotiate'], true)
                ? $this->salaryOfferStatus()
                : $recruitment->status;
            (new RecruitmentStatusService())->update($recruitment->id, $nextStatus, $now, $recruitment->status . '_' . $historyAction);

            return response()->json([
                'result' => $decision,
                'already_processed' => false,
                'requested_decision' => $decision,
                'decided_at' => $now->toDateTimeString(),
                'negotiated_amount' => $amount,
                'candidate' => $this->candidate($recruitment, $salaryOffer),
            ]);
        });
    }

    private function state($recruitment)
    {
        $history = json_decode($recruitment->meta_history ?: '[]', true);
        $history = is_array($history) ? $history : [];
        $last = !empty($history) ? $history[count($history) - 1] : [];
        $lastStatus = (string) ($last['status'] ?? '');
        $result = null;
        if (preg_match('/^internal_sallary_offer_(approved|rejected|negotiated)$/', $lastStatus, $matches)) {
            $result = $matches[1] === 'negotiated' ? 'negotiate' : ($matches[1] === 'approved' ? 'approve' : 'reject');
        }
        if ($result) {
            return ['result' => $result, 'already_processed' => true, 'decided_at' => $last['at'] ?? null, 'candidate' => $this->candidate($recruitment)];
        }
        if ($recruitment->status !== 'internal_sallary_offer') {
            return ['result' => 'unavailable', 'message' => 'Kandidat tidak berada pada tahap persetujuan penawaran.', 'candidate' => $this->candidate($recruitment)];
        }
        return ['result' => 'ready', 'candidate' => $this->candidate($recruitment)];
    }

    private function candidate($recruitment, $salaryOffer = null)
    {
        $salaryOffer = $salaryOffer ?: DB::table('sallary_offer')
            ->where('new_recruitment_id', $recruitment->id)
            ->orderByDesc('id')
            ->first();

        return [
            'nama_lengkap' => $recruitment->nama_lengkap,
            'posisi_dilamar' => $this->positionLabel($recruitment),
            'gaji_terakhir' => $recruitment->gaji_terakhir,
            'ekspetasi_gaji' => $recruitment->ekspetasi_gaji,
            'sallary_offer_hrd' => $salaryOffer->sallary_offer_hrd ?? null,
            'sallary_offer_direktur' => $salaryOffer->sallary_offer_direktur ?? null,
            'final_sallary' => $salaryOffer->final_sallary ?? null,
            'picture_base64' => app(RecruitmentPictureService::class)->toDataUri($recruitment->picture ?? null),
        ];
    }

    private function recruitment($tokenApproval)
    {
        return DB::table('new_recruitment')->where('token_approval', $tokenApproval)->first();
    }

    private function positionLabel($recruitment)
    {
        return DB::table('personnel_requests')->where('id', $recruitment->personnel_request_id)->value('divisi_alias') ?: $recruitment->posisi_dilamar;
    }

    private function amount($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $clean = preg_replace('/[^\d,.-]/', '', (string) $value);
        if (strpos($clean, ',') !== false && strpos($clean, '.') !== false) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }
        return is_numeric($clean) ? $clean : null;
    }

    private function salaryOfferStatus()
    {
        $column = DB::selectOne("SHOW COLUMNS FROM new_recruitment WHERE Field = 'status'");
        return strpos((string) ($column->Type ?? ''), "'salary_offer'") !== false ? 'salary_offer' : ' salary_offer';
    }

}
