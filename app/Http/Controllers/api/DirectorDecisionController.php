<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Services\RecruitmentStatusService;
use App\Services\RecruitmentPictureService;
use App\Services\SendEmail;
use App\Services\SendWhatsapp;
use App\Services\GenerateMessageAtsWhatsapp;
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

            if ($decision === 'reject' && !empty($recruitment->email)) {
                try {
                    SendEmail::where('to', $recruitment->email)
                        ->where('subject', 'Informasi Proses Rekrutmen - PT Inti Surya Laboratorium')
                        ->where('body', $this->rejectionEmail($recruitment))
                        ->where('cc', [])
                        ->where('bcc', [])
                        ->where('karyawan', 'Recruitment System')
                        ->noReply('PT Inti Surya Laboratorium')
                        ->send();
                } catch (\Throwable $exception) {
                    \Log::warning('Recruitment rejection email failed', [
                        'recruitment_id' => $recruitment->id,
                        'email' => $recruitment->email,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            if ($decision === 'reject' && !empty($recruitment->no_telepon)) {
                try {
                    $whatsappData = (object) [
                        'nama_lengkap' => $recruitment->nama_lengkap,
                        'posisi_di_lamar' => $this->positionLabel($recruitment),
                        'jenis_kelamin' => $recruitment->jenis_kelamin,
                    ];
                    $message = (new GenerateMessageAtsWhatsapp($whatsappData))->RejectedCandidateSelection();
                    (new SendWhatsapp($recruitment->no_telepon, $message))->send();
                } catch (\Throwable $exception) {
                    \Log::warning('Recruitment rejection WhatsApp failed', [
                        'recruitment_id' => $recruitment->id,
                        'phone' => $recruitment->no_telepon,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

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
        $birthDate = $recruitment->tanggal_lahir ?? $recruitment->tempat_tanggal_lahir ?? null;
        $shioElemen = ShioElemenHelper::resolve($birthDate, $recruitment->shio ?? null, $recruitment->elemen ?? null);

        $salaryOffer = DB::table('sallary_offer')
            ->where('new_recruitment_id', $recruitment->id)
            ->orderByDesc('id')
            ->first();

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
