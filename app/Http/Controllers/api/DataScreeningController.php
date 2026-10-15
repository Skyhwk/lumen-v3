<?php

namespace App\Http\Controllers\api;

use App\Models\NewRecruitment;
use App\Services\RecruitmentStatusService;
use App\Services\SendEmail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DataScreeningController extends DataApplicantsController
{
    protected function applicantStatus(): string
    {
        return 'review';
    }

    public function approve(Request $request, $id = null)
    {
        $id = $id ?? $request->input('id');
        $now = Carbon::now();

        $applicant = DB::transaction(function () use ($id, $now) {
            $applicant = NewRecruitment::with('personalRequest.masterJabatan')
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (!$applicant) {
                return null;
            }

            if ($applicant->status !== 'review') {
                throw new \RuntimeException('Kandidat sudah diproses atau tidak berada pada tahap review.');
            }

            (new RecruitmentStatusService())->update($applicant->id, 'assessment', $now);
            $applicant->update([
                'assessment_delivery_choice' => 'later',
                'assessment_delivery_chosen_at' => $now,
                'assessment_invitation_sent_at' => $now,
                'approved_by' => $this->karyawan ?: 'Recruitment HRD',
                'approved_at' => $now,
                'updated_at' => $now,
            ]);

            return $applicant->fresh(['personalRequest.masterJabatan']);
        });

        if (!$applicant) {
            return response()->json(['message' => 'Data kandidat tidak ditemukan.'], 404);
        }

        $position = optional(optional($applicant->personalRequest)->masterJabatan)->nama_jabatan
            ?: optional($applicant->personalRequest)->divisi_alias
            ?: $applicant->posisi_dilamar
            ?: 'Posisi yang dilamar';
        $assessmentUrl = rtrim(env('PORTALV4', 'https://portal.intilab.com'), '/')
            . '/public/recruitment/assessment/' . rawurlencode($applicant->token);

        try {
            SendEmail::where('to', $applicant->email)
                ->where('subject', 'Undangan Career Assessment - PT Inti Surya Laboratorium')
                ->where('body', view('Email.recruitment-assessment-invitation', [
                    'nama_lengkap' => $applicant->nama_lengkap,
                    'posisi_dilamar' => $position,
                    'assessment_url' => $assessmentUrl,
                ])->render())
                ->where('karyawan', $this->karyawan ?: 'Recruitment HRD')
                ->noReply('PT Inti Surya Laboratorium')
                ->replyToAtsHrd()
                ->send();
        } catch (\Throwable $exception) {
            (new RecruitmentStatusService())->update($applicant->id, 'review', Carbon::now());
            DB::table('new_recruitment')->where('id', $applicant->id)->update([
                'assessment_delivery_choice' => null,
                'assessment_delivery_chosen_at' => null,
                'assessment_invitation_sent_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'updated_at' => Carbon::now(),
            ]);

            return response()->json([
                'message' => 'Kandidat disetujui, tetapi email assessment gagal dikirim: ' . $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Kandidat disetujui dan link assessment berhasil dikirim.',
            'assessment_url' => $assessmentUrl,
        ]);
    }
}
