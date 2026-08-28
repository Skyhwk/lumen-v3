<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Models\CandidateDataOffers;
use App\Models\NewRecruitment;
use App\Services\GenerateMessageAtsEmail;
use App\Services\GenerateToken;
use App\Services\RecruitmentStatusService;
use App\Services\SallaryOfferService;
use App\Services\SendEmail;
use App\Services\AtsNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class FinanceOfferingSallaryController extends Controller
{
    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function getTtlString($row)
    {
        if (!empty($row->tempat_tanggal_lahir)) {
            return $row->tempat_tanggal_lahir;
        }
        $parts = [];
        if (!empty($row->tempat_lahir)) $parts[] = $row->tempat_lahir;
        if (!empty($row->tanggal_lahir)) $parts[] = $row->tanggal_lahir;
        return count($parts) > 0 ? implode(', ', $parts) : null;
    }

    private function extractBirthYear($row)
    {
        $ttl = $this->getTtlString($row);
        if ($ttl && preg_match('/\b(19\d{2}|20\d{2})\b/', $ttl, $matches)) {
            return (int) $matches[1];
        }
        if (!empty($row->candidateProfile->tanggal_lahir)) {
            try {
                return Carbon::parse($row->candidateProfile->tanggal_lahir)->year;
            } catch (\Exception $e) {}
        }
        return null;
    }

    private function resolvePositionName($row)
    {
        $pos = optional(optional($row->personalRequest)->masterJabatan)->nama_jabatan
            ?: ($row->posisi_dilamar ?: null);
        return $pos ?: '-';
    }

    // ─── Index — DataTables list of candidates in finance_review ────────────

    /**
     * List candidates with status = finance_review
     */
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'pending');

        $query = NewRecruitment::with([
            'personalRequest.masterJabatan', 
            'hrdInterview', 
            'userInterview', 
            'sallaryOffer', 
            'candidateDataOffer', 
            'candidateProfile'
        ]);

        if ($tab === 'processed') {
            $query->where('status', '!=', 'finance_review')
                  ->where(function ($q) {
                      $q->where('meta_history', 'like', '%finance_approved%')
                        ->orWhere('meta_history', 'like', '%finance_rejected%');
                  });
        } else {
            $query->where('status', 'finance_review');
        }

        $query->when($request->filled('year'), function ($q) use ($request) {
            return $q->where(function ($sub) use ($request) {
                $sub->whereYear('created_at', $request->year)
                    ->orWhereNull('created_at');
            });
        })
        ->orderBy('id', 'desc');

        return DataTables::of($query)
            ->addColumn('no_request', function ($row) {
                return optional($row->personalRequest)->no_request ?? '-';
            })
            ->addColumn('sallary_offer', function ($row) {
                return $row->sallaryOffer;
            })
            ->addColumn('candidate_data_offer', function ($row) {
                return $row->candidateDataOffer;
            })
            ->addColumn('expected_salary', function ($row) {
                return optional($row->sallaryOffer)->sallary_offer_hrd ?? $row->ekspetasi_gaji ?? 0;
            })
            ->addColumn('sallary_offer_user', function ($row) {
                return optional($row->sallaryOffer)->sallary_offer_user ?? 0;
            })
            ->addColumn('sallary_offer_direktur', function ($row) {
                return optional($row->sallaryOffer)->sallary_offer_direktur ?? 0;
            })
            ->addColumn('posisi_dilamar', function ($row) {
                return $this->resolvePositionName($row);
            })
            ->filterColumn('posisi_dilamar', function ($q, $keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('posisi_dilamar', 'like', "%{$keyword}%")
                        ->orWhereHas('personalRequest.masterJabatan', function ($j) use ($keyword) {
                            $j->where('nama_jabatan', 'like', "%{$keyword}%");
                        });
                });
            })
            ->addColumn('usia', function ($row) {
                $birthYear = $this->extractBirthYear($row);
                if ($birthYear) {
                    return (Carbon::now()->year - $birthYear) . ' Yrs';
                }
                return '-';
            })
            ->editColumn('shio', function ($row) {
                $birthDate  = $row->tanggal_lahir ?? $this->getTtlString($row);
                $shioElemen = ShioElemenHelper::resolve($birthDate, $row->shio, $row->elemen);
                $shio       = $shioElemen['shio']   ?? null;
                $elemen     = $shioElemen['elemen'] ?? null;
                if ($shio && $elemen) {
                    return "{$shio} ({$elemen})";
                }
                return $shio ?: ($elemen ?: '-');
            })
            ->editColumn('nilai_kecocokan', function ($row) {
                $score = $row->nilai_kecocokan !== null && $row->nilai_kecocokan !== ''
                    ? $row->nilai_kecocokan
                    : ($row->matching_score ?? rand(75, 98));
                return $score . '%';
            })
            ->addColumn('finance_reject_reason', function ($row) {
                return RecruitmentStatusService::getFinanceRejectReason($row);
            })
            ->addColumn('is_reject_finance', function ($row) {
                return (bool) ($row->is_reject_finance ?? false);
            })
            ->addColumn('finance_reject_tracking', function ($row) {
                return RecruitmentStatusService::getFinanceRejectTracking($row);
            })
            ->editColumn('status', function ($row) {
                return $row->status ?: 'finance_review';
            })
            ->rawColumns([])
            ->make(true);
    }

    // ─── Update Finance Decision ─────────────────────────────────────────────

    /**
     * Approve or reject finance review for candidate salary
     */
    public function updateDecision(Request $request, $id = null)
    {
        $id = $id ?? $request->header('id') ?? $request->input('id');

        $applicant = NewRecruitment::find($id);

        if (!$applicant) {
            return response()->json([
                'status'  => 404,
                'message' => 'Data kandidat tidak ditemukan.',
            ], 404);
        }

        if ((string) $applicant->status !== 'finance_review') {
            return response()->json([
                'status' => 409,
                'message' => 'Kandidat tidak berada pada tahap review Finance.',
            ], 409);
        }

        $decision = $request->input('decision'); // 'approve' or 'reject'
        $user = $this->karyawan;
        $now = Carbon::now();

        DB::beginTransaction();
        try {
            if ($decision === 'approve') {
                $activeOffer = SallaryOfferService::getActive((int) $id);
                $approvedSalary = $request->input('approved_salary')
                    ?? optional($activeOffer)->sallary_offer_hrd
                    ?? $request->input('final_salary');

                if ($approvedSalary !== null && $approvedSalary !== '') {
                    $cleanSalary = preg_replace('/[^0-9.]/', '', str_replace(',', '.', str_replace('.', '', (string)$approvedSalary)));
                    $finalSalary = $cleanSalary !== '' ? $cleanSalary : $approvedSalary;

                    SallaryOfferService::upsertActive(
                        (int) $id,
                        [
                            'sallary_offer_hrd' => $finalSalary,
                            'final_sallary'     => $finalSalary,
                        ],
                        $user ?? 'Finance'
                    );
                }

                RecruitmentStatusService::clearFinanceRejected((int) $id, $now);

                (new RecruitmentStatusService())->update(
                    $id, 
                    'internal_sallary_offer', 
                    $now, 
                    'finance_approved', 
                    ['by' => $user ?? 'Finance']
                );

                $applicant = $applicant->fresh(['sallaryOffer', 'personalRequest.masterJabatan']);

                $directorEmailSent = $this->sendDirectorApprovalEmail($applicant, $user ?? 'Finance');

                DB::commit();

                app(AtsNotificationService::class)->financeDecisionMade($applicant, 'approve');

                return response()->json([
                    'status'  => 200,
                    'message' => $directorEmailSent
                        ? 'Persetujuan Finance berhasil diproses dan email persetujuan telah dikirim ke Approval (Salary).'
                        : 'Persetujuan Finance berhasil diproses. Email Approval (Salary) belum dikirim karena alamat email belum tersedia.',
                    'data'    => $applicant,
                ], 200);
            }

            if ($decision === 'reject') {
                $rejectReason = trim((string) $request->input('reject_reason', ''));

                if ($rejectReason === '') {
                    DB::rollBack();
                    return response()->json([
                        'status'  => 422,
                        'message' => 'Alasan penolakan wajib diisi.',
                    ], 422);
                }

                SallaryOfferService::markFinanceRejected(
                    (int) $id,
                    $rejectReason,
                    $user ?? 'Finance'
                );

                (new RecruitmentStatusService())->update(
                    $id,
                    'management_decision',
                    $now,
                    'finance_rejected',
                    [
                        'by'            => $user ?? 'Finance',
                        'reject_reason' => $rejectReason,
                    ]
                );

                RecruitmentStatusService::markFinanceRejected(
                    (int) $id,
                    $user ?? 'Finance',
                    $rejectReason,
                    $now
                );

                DB::commit();

                app(AtsNotificationService::class)->financeDecisionMade($applicant, 'reject');

                return response()->json([
                    'status'  => 200,
                    'message' => 'Penawaran gaji kandidat ditolak oleh Finance.',
                    'data'    => $applicant->fresh(),
                ], 200);
            }

            DB::rollBack();
            return response()->json([
                'status'  => 400,
                'message' => 'Keputusan tidak valid.',
            ], 400);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 500,
                'message' => 'Gagal memproses keputusan Finance: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function sendDirectorApprovalEmail(NewRecruitment $applicant, string $sender = 'Finance'): bool
    {
        if (empty($applicant->token_approval)) {
            $tokenService = new GenerateToken();
            $tokenKey = $applicant->id . ($applicant->nama_lengkap ?? '') . 'salary_approval' . str_replace('.', '', microtime(true));
            $token = $tokenService->encrypt(md5($tokenKey) . '|' . date('Y-m-d'));
            $applicant->token_approval = $token;
            $applicant->save();
        }

        $targetEmail = trim((string) env('EMAIL_DIREKTUR_BAPAK', ''));
        if ($targetEmail === '') {
            return false;
        }

        try {
            $buttons = GenerateMessageAtsEmail::buildSalaryDecisionButtons($applicant, $applicant->token_approval);
            SendEmail::where('to', $targetEmail)
                ->where('subject', 'Permohonan Persetujuan Offering Salary - ' . ($applicant->nama_lengkap ?? 'Kandidat'))
                ->where('body', GenerateMessageAtsEmail::bodyEmailSallaryOffer($applicant, $buttons))
                ->where('karyawan', $sender)
                ->noReply()
                ->send();

            SallaryOfferService::upsertActive((int) $applicant->id, ['email_sent_at' => Carbon::now()], $sender);
            return true;
        } catch (\Throwable $exception) {
            \Log::warning('Director salary approval email failed', [
                'recruitment_id' => $applicant->id,
                'message' => $exception->getMessage(),
            ]);
            return false;
        }
    }
}
