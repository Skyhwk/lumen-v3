<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Services\RecruitmentStatusService;
use App\Services\RecruitmentPictureService;
use App\Services\SendEmail;
use App\Services\SendWhatsapp;
use App\Services\GenerateMessageAtsWhatsapp;
use App\Services\AtsNotificationService;
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

        $state = $this->state($recruitment);
        if (($state['result'] ?? null) === 'unavailable') {
            return response()->json($state, 403);
        }

        return response()->json($state);
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
                return response()->json(['message' => 'Link persetujuan penawaran tidak valid.'], 404);
            }

            $state = $this->state($recruitment);
            if (($state['result'] ?? null) !== 'ready') {
                $state['requested_decision'] = $decision;
                return response()->json($state, ($state['result'] ?? null) === 'unavailable' ? 403 : 200);
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

            // Handle history ketika kandidat accept atau reject email HRD 

            // Handle Director Salary Approval Decision (n16)
            if ($decision !== 'reject') {
                $salaryOffer = DB::table('sallary_offer')
                    ->where('new_recruitment_id', $recruitment->id)
                    ->where('is_active', true)
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
                    $update['email_sent_at'] = null;
                }
                DB::table('sallary_offer')->where('id', $salaryOffer->id)->update($update);
            }

            $historyAction = $decision === 'approve' ? 'approved' : 'rejected';
            if ($decision === 'negotiate') {
                $historyAction = 'negotiated';
            }

            if ($decision === 'negotiate' || $decision === 'reject') {
                $nextStatus = 'internal_sallary_offer';
            } else {
                $nextStatus = 'hired';
            }

            $historyStatus = $recruitment->status . '_' . $historyAction;
            $extraData = $decision === 'negotiate'
                ? ['negotiated_amount' => $amount]
                : ($decision === 'reject' ? ['reject_reason' => $rejectReason] : []);

            if ($decision === 'reject') {
                DB::table('new_recruitment')->where('id', $recruitment->id)->update([
                    'rejected_salary' => 1,
                    'rejected_salary_reason' => $rejectReason,
                    'updated_at' => $now,
                ]);

                $salaryOffer = DB::table('sallary_offer')
                    ->where('new_recruitment_id', $recruitment->id)
                    ->where('is_active', true)
                    ->orderByDesc('id')
                    ->first();

                if ($salaryOffer) {
                    DB::table('sallary_offer')->where('id', $salaryOffer->id)->update([
                        'email_sent_at' => null,
                        'updated_by'    => 'Direktur',
                        'updated_at'    => $now,
                    ]);
                }
            }

            (new RecruitmentStatusService())->update(
                $recruitment->id,
                $nextStatus,
                $now,
                $historyStatus,
                $extraData
            );

            if ($decision === 'approve') {
                $this->sendHiringLetter($recruitment);
            }

            app(AtsNotificationService::class)->directorSalaryDecision($recruitment, $decision);

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

    private function sendHiringLetter($recruitment)
    {
        try {
            $applicant = \App\Models\NewRecruitment::with([
                'sallaryOffer',
                'candidateDataOffer',
                'personalRequest.masterJabatan',
                'personnelRequest.masterJabatan',
            ])->find($recruitment->id);
            if (!$applicant) {
                return;
            }

            $dataObj = \App\Services\GenerateMessageAtsEmail::buildOfferingLetterPayload($applicant);
            \App\Services\GenerateMessageAtsEmail::sendCandidateHiringLetterEmail($applicant, $dataObj, 'Direktur');
        } catch (\Throwable $exception) {
            \Log::warning('Candidate hiring letter failed after director approval', [
                'recruitment_id' => $recruitment->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function state($recruitment)
    {
        $history = json_decode($recruitment->meta_history ?: '[]', true);
        $history = is_array($history) ? $history : [];
        $last = !empty($history) ? $history[count($history) - 1] : [];
        $lastStatus = (string) ($last['status'] ?? '');
        $result = null;
        if (preg_match('/^internal_sallary_offer_(approved|rejected|negotiated)$/', $lastStatus, $matches)) {
            if ($matches[1] !== 'rejected' || (int) ($recruitment->rejected_salary ?? 0) === 1) {
                $result = $matches[1] === 'negotiated' ? 'negotiate' : ($matches[1] === 'approved' ? 'approve' : 'reject');
            }
        }
        if ($result) {
            return ['result' => $result, 'already_processed' => true, 'decided_at' => $last['at'] ?? null, 'candidate' => $this->candidate($recruitment)];
        }
        if ($recruitment->status !== 'internal_sallary_offer') {
            return [
                'result' => 'unavailable',
                'message' => 'Link persetujuan penawaran sudah kedaluwarsa atau kandidat tidak berada pada tahap persetujuan penawaran.',
                'candidate' => $this->candidate($recruitment),
            ];
        }
        return ['result' => 'ready', 'candidate' => $this->candidate($recruitment)];
    }

    private function candidate($recruitment, $salaryOffer = null)
    {
        $salaryOffer = $salaryOffer ?: DB::table('sallary_offer')
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

        $birthDate = $recruitment->tanggal_lahir ?? $recruitment->tempat_tanggal_lahir ?? null;
        $shioElemen = ShioElemenHelper::resolve($birthDate, $recruitment->shio ?? null, $recruitment->elemen ?? null);

        $expectedSalary = $salaryOffer->sallary_offer_hrd ?? $recruitment->ekspetasi_gaji ?? null;

        return [
            'nama_lengkap' => $recruitment->nama_lengkap,
            'posisi_dilamar' => $this->positionLabel($recruitment),
            'shio' => $shioElemen['shio'] ?? $recruitment->shio ?? '-',
            'elemen' => $shioElemen['elemen'] ?? $recruitment->elemen ?? '-',
            'gaji_terakhir' => $recruitment->gaji_terakhir,
            'ekspetasi_gaji' => $expectedSalary,
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

    private function notifyRejectedCandidate($recruitment)
    {
        if (!empty($recruitment->email)) {
            try {
                SendEmail::where('to', $recruitment->email)
                    ->where('subject', 'Informasi Proses Rekrutmen - PT Inti Surya Laboratorium')
                    ->where('body', $this->rejectionEmail($recruitment))
                    ->where('cc', [])
                    ->where('bcc', [])
                    ->where('karyawan', 'Recruitment System')
                    ->noReply('PT Inti Surya Laboratorium')
                    ->replyToAtsHrd()
                    ->send();
            } catch (\Throwable $exception) {
                \Log::warning('Salary offer rejection email failed', ['recruitment_id' => $recruitment->id, 'message' => $exception->getMessage()]);
            }
        }

        if (!empty($recruitment->no_telepon)) {
            try {
                $message = (new GenerateMessageAtsWhatsapp((object) [
                    'nama_lengkap' => $recruitment->nama_lengkap,
                    'posisi_di_lamar' => $this->positionLabel($recruitment),
                    'jenis_kelamin' => $recruitment->jenis_kelamin,
                ]))->RejectedCandidateSelection();
                (new SendWhatsapp($recruitment->no_telepon, $message))->send();
            } catch (\Throwable $exception) {
                \Log::warning('Salary offer rejection WhatsApp failed', ['recruitment_id' => $recruitment->id, 'message' => $exception->getMessage()]);
            }
        }
    }

    private function rejectionEmail($recruitment)
    {
        $name = htmlspecialchars($recruitment->nama_lengkap ?: 'Kandidat', ENT_QUOTES, 'UTF-8');
        $position = htmlspecialchars($this->positionLabel($recruitment) ?: 'posisi yang dilamar', ENT_QUOTES, 'UTF-8');
        $salutation = strtolower((string) ($recruitment->jenis_kelamin ?? $recruitment->gender ?? '')) === 'female' ? 'Saudari' : 'Saudara';

        return '<!doctype html><html><body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#344256">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f9"><tr><td align="center" style="padding:24px 16px">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#fff;border:1px solid #dfe5ec;border-radius:6px;overflow:hidden">'
            . '<tr><td style="padding:18px 20px;background:#1f2b3d;color:#fff"><div style="font-size:15px;font-weight:700">PT INTI SURYA LABORATORIUM</div><div style="margin-top:3px;font-size:11px;color:#b9c7d8">HRD &amp; Talent Acquisition Division</div></td></tr>'
            . '<tr><td style="padding:28px 22px 26px"><p style="margin:0 0 16px;font-size:13px;line-height:20px">Yth. ' . $salutation . ' <strong>' . $name . '</strong>,</p>'
            . '<p style="margin:0 0 16px;font-size:13px;line-height:21px">Terima kasih atas waktu dan partisipasi Anda dalam proses rekrutmen untuk posisi <strong>' . $position . '</strong>.</p>'
            . '<p style="margin:0 0 16px;font-size:13px;line-height:21px">Setelah melalui proses evaluasi, kami belum dapat melanjutkan lamaran Anda ke tahap berikutnya. Keputusan ini diambil berdasarkan pertimbangan kebutuhan posisi saat ini.</p>'
            . '<p style="margin:0;font-size:13px;line-height:21px">Kami menghargai minat Anda untuk bergabung bersama PT Inti Surya Laboratorium dan mendoakan yang terbaik untuk perjalanan karier Anda.</p></td></tr>'
            . '<tr><td style="padding:17px 22px;background:#f8fafc;border-top:1px solid #e2e8ef"><p style="margin:0 0 4px;font-size:12px;line-height:18px">Salam,</p><p style="margin:0;font-size:12px;font-weight:700;line-height:18px">Tim Recruitment<br><span style="font-weight:400">PT Inti Surya Laboratorium</span></p></td></tr>'
            . '</table></td></tr></table></body></html>';
    }

}
