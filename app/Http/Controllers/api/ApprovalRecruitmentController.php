<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\NewRecruitment;
use App\Services\GenerateToken;
use App\Services\RecruitmentStatusService;
use App\Services\SallaryOfferService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use App\Http\Controllers\api\Concerns\ServesAtsClientSideList;

class ApprovalRecruitmentController extends Controller
{
    use ServesAtsClientSideList;
    private function resolvePositionName($applicant)
    {
        if (!$applicant) {
            return '-';
        }

        $pos = null;
        $pr  = $applicant->personalRequest ?? null;

        if ($pr) {
            $masterJabatan = $pr->masterJabatan ?? null;
            if ($masterJabatan && !empty($masterJabatan->nama_jabatan)) {
                $pos = $masterJabatan->nama_jabatan;
            } elseif (!empty($pr->posisi_name)) {
                $pos = $pr->posisi_name;
            } elseif (!empty($pr->posisi) && !is_numeric($pr->posisi)) {
                $pos = $pr->posisi;
            }
        }

        if (!$pos && !empty($applicant->posisi_dilamar) && !is_numeric($applicant->posisi_dilamar)) {
            $pos = $applicant->posisi_dilamar;
        }

        return $pos ?: '-';
    }

    private function getCandidatesQuery($year = null)
    {
        return NewRecruitment::query()
            ->with([
                'personalRequest.masterJabatan',
                'personalRequest.masterDivisi',
                'sallaryOffer',
                'candidateDataOffer',
                'hrdInterview',
                'userInterview',
            ])
            ->whereNotNull('personnel_request_id')
            ->where('personnel_request_id', '!=', '')
            ->where('is_rejected_kandidat', 0)
            ->where('is_active', 1)
            ->when($year, function ($q) use ($year) {
                return $q->where(function ($sub) use ($year) {
                    $sub->whereYear('created_at', $year)
                        ->orWhereNull('created_at');
                });
            });
    }

    /**
     * Kategorisasi status offering salary kandidat secara ketat.
     */
    private function resolveOfferingCategory($row): array
    {
        $status = strtolower(trim((string) ($row->status ?? '')));
        $history = RecruitmentStatusService::parseMetaHistory($row);
        $decision = RecruitmentStatusService::getLatestDirectorSalaryDecision($history);

        $isHiredOrTraining = in_array($status, ['hired', 'training', 'accepted', 'completed'], true);
        $isRejectedDecision = (int) ($row->rejected_decision ?? 0) === 1 && $row->status != 'management_decision';
        $isAnyRejected = ($status === 'rejected' || (int) ($row->rejected_salary ?? 0) === 1 || $isRejectedDecision);
        $hasOffer = !empty(optional($row->sallaryOffer)->email_sent_at);

        $rawWaiting = RecruitmentStatusService::isAwaitingDirectorSalaryApproval($row);

        // Rejected HANYA jika rejected_decision === 1
        $isRejected = $isRejectedDecision;

        // Kandidat HANYA bisa 'waiting' jika belum hired/training, tidak berstatus rejected apapun, dan rawWaiting true
        $isWaiting = !$isHiredOrTraining && !$isAnyRejected && $rawWaiting;

        // Approved jika keputusan eksplisit approved, ATAU kandidat sudah hired/training yang pernah ada offering
        $isApproved = ($decision === 'approved' || ($isHiredOrTraining && $hasOffer)) && !$isWaiting && !$isRejected;

        $inOfferingFlow = $isWaiting || $isApproved || $isRejected;

        return [
            'in_flow'     => $inOfferingFlow,
            'is_waiting'  => $isWaiting,
            'is_approved' => $isApproved,
            'is_rejected' => $isRejected,
            'decision'    => $decision,
            'status'      => $status,
        ];
    }

    /**
     * Get counts for each tab in Approval Offering Salary.
     */
    public function counts(Request $request)
    {
        $year = $request->input('year');
        $candidates = $this->getCandidatesQuery($year)->get();

        $counts = [
            'waiting'  => 0,
            'approved' => 0,
            'rejected' => 0,
            'all'      => 0,
        ];

        foreach ($candidates as $row) {
            $cat = $this->resolveOfferingCategory($row);
            if (!$cat['in_flow']) {
                continue;
            }

            $counts['all']++;

            if ($cat['is_waiting']) {
                $counts['waiting']++;
            } elseif ($cat['is_approved']) {
                $counts['approved']++;
            } elseif ($cat['is_rejected']) {
                $counts['rejected']++;
            }
        }

        return response()->json([
            'data' => [
                'counts' => $counts,
            ],
            'message' => 'Approval Recruitment counts retrieved successfully',
        ], 200);
    }

    /**
     * DataTables list for Approval Offering Salary.
     * Tabs: waiting | approved | rejected | all
     */
    public function index(Request $request)
    {
        $year = $request->input('year');
        $tab = $request->input('tab', 'waiting');

        $candidates = $this->getCandidatesQuery($year)->get();

        $filteredIds = $candidates->filter(function ($row) use ($tab) {
            $cat = $this->resolveOfferingCategory($row);
            if (!$cat['in_flow']) {
                return false;
            }

            if ($tab === 'waiting') {
                return $cat['is_waiting'];
            }

            if ($tab === 'approved') {
                return $cat['is_approved'];
            }

            if ($tab === 'rejected') {
                return $cat['is_rejected'];
            }

            if ($tab === 'all') {
                return true;
            }

            return false;
        })->pluck('id')->values()->all();

        $query = NewRecruitment::query()
            ->with([
                'personalRequest.masterJabatan',
                'personalRequest.masterDivisi',
                'sallaryOffer',
                'candidateDataOffer',
                'hrdInterview',
                'userInterview',
            ])
            ->whereIn('id', $filteredIds ?: [-1])
            ->orderBy('id', 'desc');

        $dt = DataTables::of($query)
            ->addColumn('no_request', function ($row) {
                return optional($row->personalRequest)->no_request ?? '-';
            })
            ->addColumn('posisi', function ($row) {
                return $this->resolvePositionName($row);
            })
            ->addColumn('divisi', function ($row) {
                return optional($row->personalRequest->masterDivisi)->nama_divisi
                    ?? optional($row->personalRequest)->divisi_alias
                    ?? optional($row->personalRequest)->divisi
                    ?? '-';
            })
            ->addColumn('request_by', function ($row) {
                return optional($row->personalRequest)->created_by ?: '-';
            })
            ->addColumn('gaji_pokok', function ($row) {
                $val = optional($row->candidateDataOffer)->gaji_pokok
                    ?? optional($row->sallaryOffer)->sallary_offer_hrd
                    ?? $row->ekspetasi_gaji
                    ?? 0;
                return (float) $val;
            })
            ->addColumn('tunjangan_kerja', function ($row) {
                $val = optional($row->candidateDataOffer)->tunjangan_kerja
                    ?? optional($row->sallaryOffer)->tunjangan_gaji_user
                    ?? 0;
                return (float) $val;
            })
            ->addColumn('total_offering', function ($row) {
                $gp = optional($row->candidateDataOffer)->gaji_pokok
                    ?? optional($row->sallaryOffer)->sallary_offer_hrd
                    ?? $row->ekspetasi_gaji
                    ?? 0;
                $tk = optional($row->candidateDataOffer)->tunjangan_kerja
                    ?? optional($row->sallaryOffer)->tunjangan_gaji_user
                    ?? 0;
                return (float) $gp + (float) $tk;
            })
            ->addColumn('tanggal_mulai_kerja', function ($row) {
                $val = optional($row->candidateDataOffer)->tanggal_mulai_kerja
                    ?? optional($row->hrdInterview)->tanggal_join
                    ?? null;
                return $val ? Carbon::parse($val)->format('Y-m-d') : null;
            })
            ->addColumn('email_sent_at', function ($row) {
                $val = optional($row->sallaryOffer)->email_sent_at ?? $row->updated_at;
                return $val ? Carbon::parse($val)->format('Y-m-d H:i:s') : null;
            })
            ->addColumn('director_status', function ($row) {
                $cat = $this->resolveOfferingCategory($row);

                if ($cat['is_waiting']) {
                    return [
                        'code' => 'waiting',
                        'label' => 'Menunggu Approval',
                        'badge_class' => 'badge-warning',
                    ];
                }

                if ($cat['is_approved']) {
                    $status = $cat['status'];
                    $label = in_array($status, ['hired', 'training'], true)
                        ? 'Disetujui (' . ucfirst($status) . ')'
                        : 'Disetujui';

                    return [
                        'code' => 'approved',
                        'label' => $label,
                        'badge_class' => 'badge-success',
                    ];
                }

                if ($cat['is_rejected']) {
                    return [
                        'code' => 'rejected',
                        'label' => 'Ditolak',
                        'badge_class' => 'badge-danger',
                    ];
                }

                return [
                    'code' => 'other',
                    'label' => ucfirst($row->status),
                    'badge_class' => 'badge-secondary',
                ];
            })
            ->addColumn('can_decide', function ($row) {
                $cat = $this->resolveOfferingCategory($row);
                return $cat['is_waiting'];
            })
            ->addColumn('reject_reason', function ($row) {
                if (!empty($row->rejected_decision_reason)) {
                    return $row->rejected_decision_reason;
                }
                if (!empty($row->rejected_salary_reason)) {
                    return $row->rejected_salary_reason;
                }
                return RecruitmentStatusService::getDirectorSalaryRejectReason($row) ?? '-';
            });

        if ($response = $this->serveAtsClientSideList($dt)) {
            return $response;
        }

        return $dt->make(true);
    }

    /**
     * Approve offering salary kandidat.
     */
    public function approve(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['message' => 'ID kandidat tidak ditemukan'], 400);
        }

        $applicant = NewRecruitment::find($id);
        if (!$applicant) {
            return response()->json(['message' => 'Data kandidat tidak ditemukan'], 404);
        }

        $cat = $this->resolveOfferingCategory($applicant);
        if (!$cat['is_waiting']) {
            return response()->json(['message' => 'Kandidat ini tidak sedang menunggu approval offering salary.'], 422);
        }

        if (empty($applicant->token_approval)) {
            $tokenService = new GenerateToken();
            $tokenKey = $applicant->id . ($applicant->nama_lengkap ?? '') . 'salary_approval' . str_replace('.', '', microtime(true));
            $applicant->token_approval = $tokenService->encryptv1(md5($tokenKey) . '|' . date('Y-m-d'));
            $applicant->save();
        }

        $salaryApprovalController = app(SalaryApprovalController::class);
        $decideRequest = Request::create('/api/route', 'POST', [
            'id' => $applicant->id,
            'token_approval' => $applicant->token_approval,
            'decision' => 'approve',
        ]);

        $response = $salaryApprovalController->decide($decideRequest);
        $data = json_decode($response->getContent(), true);

        if ($response->getStatusCode() >= 400) {
            return response()->json([
                'message' => $data['message'] ?? 'Gagal menyetujui offering salary kandidat.',
            ], $response->getStatusCode());
        }

        return response()->json([
            'status' => 200,
            'message' => 'Offering salary untuk ' . ($applicant->nama_lengkap ?? 'kandidat') . ' berhasil disetujui.',
            'data' => $data,
        ], 200);
    }

    /**
     * Reject offering salary kandidat.
     */
    public function reject(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['message' => 'ID kandidat tidak ditemukan'], 400);
        }

        $rejectReason = trim((string) ($request->input('reject_reason') ?? $request->input('reason') ?? ''));
        if ($rejectReason === '') {
            return response()->json(['message' => 'Alasan penolakan wajib diisi.'], 422);
        }

        $applicant = NewRecruitment::find($id);
        if (!$applicant) {
            return response()->json(['message' => 'Data kandidat tidak ditemukan'], 404);
        }

        $cat = $this->resolveOfferingCategory($applicant);
        if (!$cat['is_waiting']) {
            return response()->json(['message' => 'Kandidat ini tidak sedang menunggu approval offering salary.'], 422);
        }

        if (empty($applicant->token_approval)) {
            $tokenService = new GenerateToken();
            $tokenKey = $applicant->id . ($applicant->nama_lengkap ?? '') . 'salary_approval' . str_replace('.', '', microtime(true));
            $applicant->token_approval = $tokenService->encryptv1(md5($tokenKey) . '|' . date('Y-m-d'));
            $applicant->save();
        }

        $salaryApprovalController = app(SalaryApprovalController::class);
        $decideRequest = Request::create('/api/route', 'POST', [
            'id' => $applicant->id,
            'token_approval' => $applicant->token_approval,
            'decision' => 'reject',
            'reject_reason' => $rejectReason,
        ]);

        $response = $salaryApprovalController->decide($decideRequest);
        $data = json_decode($response->getContent(), true);

        if ($response->getStatusCode() >= 400) {
            return response()->json([
                'message' => $data['message'] ?? 'Gagal menolak offering salary kandidat.',
            ], $response->getStatusCode());
        }

        DB::table('new_recruitment')->where('id', $applicant->id)->update([
            'rejected_decision' => 1,
            'rejected_decision_reason' => $rejectReason,
            'updated_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Offering salary untuk ' . ($applicant->nama_lengkap ?? 'kandidat') . ' berhasil ditolak.',
            'data' => $data,
        ], 200);
    }
}
