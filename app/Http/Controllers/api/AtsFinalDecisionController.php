<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Models\CandidateDataOffers;
use App\Models\NewRecruitment;
use App\Models\RecruitmentInterview;
use App\Services\GenerateMessageAtsEmail;
use App\Services\RecruitmentStatusService;
use App\Services\SallaryOfferService;
use App\Services\SendEmail;
use App\Services\AtsNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class AtsFinalDecisionController extends Controller
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
        $ttl = is_string($row) ? $row : $this->getTtlString($row);

        if (is_object($row) && !empty($row->tanggal_lahir)) {
            try {
                $dt = Carbon::parse($row->tanggal_lahir);
                $year = (int) $dt->year;
                if ($year >= 1930 && $year <= Carbon::now()->year) {
                    return $year;
                }
                if ($year > 0) {
                    $last2 = $year % 100;
                    $currentYY = Carbon::now()->year % 100;
                    return $last2 <= $currentYY ? (2000 + $last2) : (1900 + $last2);
                }
            } catch (\Exception $e) {}
        }

        if (!$ttl) return null;

        if (preg_match('/\b(19\d\d|20\d\d)\b/', $ttl, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\b(\d{4})\b/', $ttl, $matches)) {
            $year = (int) $matches[1];
            if ($year >= 1930 && $year <= Carbon::now()->year) {
                return $year;
            }
            if ($year > 0) {
                $last2 = $year % 100;
                $currentYY = Carbon::now()->year % 100;
                return $last2 <= $currentYY ? (2000 + $last2) : (1900 + $last2);
            }
        }

        return null;
    }

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

    private function scopeFinalDecisionCandidates($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('status', [
                'management_decision',
                'internal_sallary_offer',
                'salary_offer',
                'finance_review',
                'hired',
                'rejected',
            ])
            ->orWhere(function ($sub) {
                $sub->where('status', 'rejected')
                    ->where(function ($meta) {
                        $meta->where('meta_history', 'like', '%management_decision%')
                            ->orWhere('meta_history', 'like', '%internal_sallary_offer%')
                            ->orWhere('meta_history', 'like', '%interview_user%')
                            ->orWhere('meta_history', 'like', '%finance_review%')
                            ->orWhere('meta_history', 'like', '%finance_rejected%')
                            ->orWhere('meta_history', 'like', '%candidate_rejected%')
                            ->orWhere('is_approve_interview_user', 1);
                    });
            });
        });
    }

    // ─── Index — DataTables list of management_decision candidates ───────────

    /**
     * List candidates with status = management_decision (or final_decision)
     */
    public function index(Request $request)
    {
        $query = $this->scopeFinalDecisionCandidates(
            NewRecruitment::with(['personalRequest.masterJabatan', 'hrdInterview', 'userInterview', 'sallaryOffer', 'candidateDataOffer', 'candidateProfile'])
        )
            ->when($request->filled('year'), function ($q) use ($request) {
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
            ->addColumn('sallary_offer_direktur', function ($row) {
                return optional($row->sallaryOffer)->sallary_offer_direktur ?? 0;
            })
            ->addColumn('sallary_offer_user', function ($row) {
                return optional($row->sallaryOffer)->sallary_offer_user ?? 0;
            })
            ->addColumn('offering_status', function ($row) {
                $offer = $row->sallaryOffer;
                $emailSentAt = $offer->email_sent_at ?? null;
                $status = strtolower(trim((string) $row->status));

                $history = json_decode($row->meta_history ?: '[]', true);
                $history = is_array($history) ? $history : [];
                $lastHistory = !empty($history) ? end($history) : [];
                $lastHistoryStatus = (string) ($lastHistory['status'] ?? '');

                if ($lastHistoryStatus === 'candidate_offering_sent' && $status === 'internal_sallary_offer') {
                    return [
                        'code' => 'waiting_candidate_approval',
                        'label' => 'Menunggu Respon Kandidat',
                        'email_sent_at' => $emailSentAt,
                    ];
                }

                if ($lastHistoryStatus === 'candidate_rejected' || ($status === 'rejected' && strpos((string)$row->meta_history, 'candidate_rejected') !== false)) {
                    return [
                        'code' => 'candidate_rejected',
                        'label' => 'Ditolak Kandidat (Re-input HRD)',
                        'email_sent_at' => $emailSentAt,
                    ];
                }

                if ($lastHistoryStatus === 'finance_rejected' || ($status === 'rejected' && strpos((string)$row->meta_history, 'finance_rejected') !== false)) {
                    return [
                        'code' => 'finance_rejected',
                        'label' => 'Ditolak Finance (Re-input HRD)',
                        'email_sent_at' => $emailSentAt,
                    ];
                }

                if ($lastHistoryStatus === 'internal_sallary_offer_rejected' || ($status === 'rejected' && strpos((string)$row->meta_history, 'internal_sallary_offer_rejected') !== false)) {
                    return [
                        'code' => 'director_rejected',
                        'label' => 'Ditolak Direktur (Re-input HRD)',
                        'email_sent_at' => $emailSentAt,
                    ];
                }

                if ($status === 'finance_review') {
                    return [
                        'code' => 'finance_review',
                        'label' => 'Disetujui Kandidat (Dalam Review Finance)',
                        'email_sent_at' => $emailSentAt,
                    ];
                }

                if (RecruitmentStatusService::isAwaitingDirectorSalaryApproval($row)) {
                    return [
                        'code' => 'awaiting_direktur',
                        'label' => 'Menunggu Direktur (Salary)',
                        'email_sent_at' => $emailSentAt,
                    ];
                }

                $history = json_decode($row->meta_history ?: '[]', true);
                $history = is_array($history) ? $history : [];

                $decision = null;
                if (!empty($history)) {
                    for ($i = count($history) - 1; $i >= 0; $i--) {
                        $hStatus = (string) ($history[$i]['status'] ?? '');
                        if (preg_match('/^internal_sallary_offer_(approved|rejected|negotiated)$/', $hStatus, $m)) {
                            $decision = $m[1];
                            break;
                        }
                    }
                }

                if ($decision === 'approved' || $status === 'hired' || $status === 'accepted') {
                    return [
                        'code' => 'approved',
                        'label' => 'Disetujui / Accepted',
                        'email_sent_at' => $emailSentAt,
                    ];
                }

                if ($decision === 'rejected') {
                    return [
                        'code' => 'rejected',
                        'label' => 'Ditolak Direktur',
                        'email_sent_at' => $emailSentAt,
                    ];
                }

                if ($decision === 'negotiated' || $status === 'management_decision') {
                    $amount = $offer->sallary_offer_direktur ?? null;
                    return [
                        'code' => 'negotiated',
                        'label' => 'Dinegosiasikan Direktur',
                        'negotiated_amount' => $amount,
                        'email_sent_at' => $emailSentAt,
                    ];
                }

                if (!$emailSentAt) {
                    return [
                        'code' => 'not_sent',
                        'label' => 'Belum Dikirim',
                        'email_sent_at' => null,
                    ];
                }

                return [
                    'code' => 'email_sent',
                    'label' => 'Email Terkirim',
                    'email_sent_at' => $emailSentAt,
                ];
            })
            ->addColumn('finance_reject_reason', function ($row) {
                return \App\Services\RecruitmentStatusService::getFinanceRejectReason($row);
            })
            ->filterColumn('no_request', function ($q, $keyword) {
                $q->whereHas('personalRequest', function ($sub) use ($keyword) {
                    $sub->where('no_request', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('nama_lengkap', function ($q, $keyword) {
                $q->where('nama_lengkap', 'like', "%{$keyword}%");
            })
            ->editColumn('posisi_dilamar', function ($row) {
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
            ->editColumn('status', function ($row) {
                return $row->status ?: 'management_decision';
            })
            ->rawColumns([])
            ->make(true);
    }

    private function isOfferingRejected($applicant)
    {
        $history = json_decode($applicant->meta_history ?: '[]', true);
        $history = is_array($history) ? $history : [];
        if (!empty($history)) {
            for ($i = count($history) - 1; $i >= 0; $i--) {
                $hStatus = (string) ($history[$i]['status'] ?? '');
                if (preg_match('/^internal_sallary_offer_(approved|rejected|negotiated)$/', $hStatus, $m)) {
                    return $m[1] === 'rejected';
                }
            }
        }
        return false;
    }

    public function updateExpectedSalary(Request $request, $id = null)
    {
        $id = $id ?? $request->header('id') ?? $request->input('id');

        $applicant = NewRecruitment::find($id);

        if (!$applicant) {
            return response()->json([
                'status'  => 404,
                'message' => 'Candidate data not found.',
            ], 404);
        }

        $applicantStatus = strtolower(trim((string) $applicant->status));
        if ($applicantStatus === 'finance_review') {
            return response()->json([
                'status'  => 422,
                'message' => 'Data tidak dapat diedit karena sedang dalam review Finance.',
            ], 422);
        }

        if (RecruitmentStatusService::isAwaitingIbuDirekturApproval($applicant)) {
            return response()->json([
                'status'  => 422,
                'message' => 'Data tidak dapat diedit karena masih menunggu keputusan manajemen.',
            ], 422);
        }

        if (RecruitmentStatusService::isAwaitingDirectorSalaryApproval($applicant)) {
            return response()->json([
                'status'  => 422,
                'message' => 'Data tidak dapat diedit karena masih menunggu persetujuan Direktur atas Offering Salary.',
            ], 422);
        }

        $decision = $request->input('decision');

        if (!$decision && RecruitmentStatusService::isFinanceSalaryLocked($applicant)) {
            return response()->json([
                'status'  => 422,
                'message' => 'Gaji tidak dapat diubah setelah disetujui Finance.',
            ], 422);
        }

        if ($this->isOfferingRejected($applicant)) {
            return response()->json([
                'status'  => 400,
                'message' => 'Salary offer for this candidate has been rejected. Actions are disabled.',
            ], 400);
        }

        $user = $this->karyawan;
        $now = Carbon::now();

        if ($decision === 'approve') {
            $reqFinalSalary = $request->input('final_salary') ?? $request->input('final_sallary');
            $offer = SallaryOfferService::getActive((int) $id);

            if ($reqFinalSalary !== null && $reqFinalSalary !== '') {
                $cleanSalary = preg_replace('/[^0-9.]/', '', str_replace(',', '.', str_replace('.', '', $reqFinalSalary)));
                $finalSalary = $cleanSalary !== '' ? $cleanSalary : $reqFinalSalary;
            } else {
                $finalSalary = optional($offer)->sallary_offer_direktur ?? optional($offer)->sallary_offer_hrd ?? $applicant->ekspetasi_gaji;
            }

            SallaryOfferService::upsertActive(
                (int) $id,
                ['final_sallary' => $finalSalary],
                $user ?? 'HRD'
            );

            $cleanNumber = function ($val) {
                if ($val === null || $val === '') return 0;
                $clean = preg_replace('/[^0-9.]/', '', str_replace(',', '.', str_replace('.', '', (string)$val)));
                return $clean !== '' ? (float) $clean : 0;
            };

            $gajiPokok       = $cleanNumber($request->input('gaji_pokok')) ?: (float)$finalSalary;
            $potBpjsKes      = $cleanNumber($request->input('potongan_bpjs_kes'));
            $potBpjsTk       = $cleanNumber($request->input('potongan_bpjs_tk'));
            $potPph21        = $cleanNumber($request->input('pot_pph21'));
            $pencadanganUpah = $cleanNumber($request->input('pencadangan_upah'));

            $startDate = $request->input('start_date');
            if (!empty($startDate)) {
                try {
                    $startDateTime = Carbon::parse($startDate)->startOfDay()->toDateTimeString();
                } catch (\Exception $e) {
                    $startDateTime = $startDate . ' 00:00:00';
                }

                CandidateDataOffers::updateOrCreate(
                    ['new_recruitment_id' => $id],
                    [
                        'gaji_pokok'          => $gajiPokok,
                        'potongan_bpjs_kes'   => $potBpjsKes,
                        'potongan_bpjs_tk'    => $potBpjsTk,
                        'pot_pph21'           => $potPph21,
                        'tanggal_mulai_kerja' => $startDateTime,
                        'pencadangan_upah'    => $pencadanganUpah,
                        'created_by'          => $user ?? 'HRD',
                        'updated_by'          => $user ?? 'HRD',
                    ]
                );
            }

            $applicant->approved_by = $user ?? 'HRD';
            $applicant->approved_at = $now;
            $applicant->save();

            (new \App\Services\RecruitmentStatusService())->update($id, 'hired', $now, 'internal_sallary_offer_approved');

            try {
                $applicant->loadMissing([
                    'sallaryOffer',
                    'candidateDataOffer',
                    'personalRequest.masterJabatan',
                    'personnelRequest.masterJabatan',
                ]);

                $dataObj = GenerateMessageAtsEmail::buildOfferingLetterPayload($applicant, [
                    'gaji_pokok'          => $gajiPokok,
                    'potongan_bpjs_kes'   => $potBpjsKes,
                    'potongan_bpjs_tk'    => $potBpjsTk,
                    'pot_pph21'           => $potPph21,
                    'pencadangan_upah'    => $pencadanganUpah,
                    'tanggal_mulai_kerja' => !empty($startDate) ? Carbon::parse($startDate)->translatedFormat('d F Y') : '-',
                ]);

                GenerateMessageAtsEmail::sendCandidateHiringLetterEmail(
                    $applicant,
                    $dataObj,
                    $user ?? 'HRD'
                );
            } catch (\Exception $e) {
                \Log::warning('Hiring letter email failed on internal salary approve', [
                    'recruitment_id' => $id,
                    'message'        => $e->getMessage(),
                ]);
            }

            $applicant->loadMissing('personalRequest', 'personnelRequest');
            app(AtsNotificationService::class)->candidateHired(
                $applicant,
                $applicant->personalRequest ?? $applicant->personnelRequest
            );

            return response()->json([
                'status'  => 200,
                'message' => 'Negotiated salary offer approved successfully with candidate data offer.',
                'data'    => $applicant->fresh(['candidateDataOffer']),
            ], 200);
        }

        if ($decision === 'reject') {
            (new \App\Services\RecruitmentStatusService())->update($id, 'rejected', $now, 'internal_sallary_offer_rejected');

            try {
                if (!empty($applicant->email)) {
                    $posisiName = $this->resolvePositionName($applicant);
                    $dataObj = (object) [
                        'nama_lengkap'    => $applicant->nama_lengkap,
                        'jenis_kelamin'   => $applicant->jenis_kelamin,
                        'nama_jabatan'    => $posisiName,
                        'posisi_di_lamar' => $posisiName,
                        'alasan_reject'   => 'Negotiated salary offer rejected',
                    ];

                    $bodyEmail = GenerateMessageAtsEmail::bodyEmailRejectKandidat($dataObj);
                    SendEmail::where('to', $applicant->email)
                        ->where('subject', 'Selection Result Notification - PT Inti Surya Laboratorium')
                        ->where('body', $bodyEmail)
                        ->where('karyawan', $user)
                        ->noReply()
                        ->replyToAtsHrd()
                        ->send();
                }
            } catch (\Exception $e) {}

            return response()->json([
                'status'  => 200,
                'message' => 'Negotiated salary offer rejected.',
                'data'    => $applicant->fresh(),
            ], 200);
        }

        if ($decision === 'resubmit' || $decision === 'ajukan_ulang') {
            (new \App\Services\RecruitmentStatusService())->update($id, 'internal_sallary_offer', $now, 'hrd_resubmitted_offer');

            return response()->json([
                'status'  => 200,
                'message' => 'Status kandidat dikembalikan untuk pengajuan ulang gaji & potongan.',
                'data'    => $applicant->fresh(),
            ], 200);
        }

        $expectedSalary = $request->input('expected_salary') ?? $request->input('ekspetasi_gaji');

        $cleanSalary = function ($salary) {
            if ($salary === null || $salary === '') return '';
            return preg_replace('/[^0-9.]/', '', str_replace(',', '.', str_replace('.', '', (string)$salary)));
        };

        if ($expectedSalary !== null) {
            $cleanExpectedSalary = $cleanSalary($expectedSalary);
            $valueToSave = $cleanExpectedSalary !== '' ? $cleanExpectedSalary : $expectedSalary;

            $user = $this->karyawan;

            $applicant->loadMissing('userInterview');
            $userReferenceSalary = SallaryOfferService::resolveUserReferenceSalary($applicant);

            $offerData = [
                'sallary_offer_hrd' => $valueToSave,
                'updated_by'        => $user,
            ];

            if ($userReferenceSalary !== null) {
                $offerData['sallary_offer_user'] = $userReferenceSalary;
            }

            if ($request->has('sallary_offer_direktur')) {
                $offerData['sallary_offer_direktur'] = $cleanSalary($request->input('sallary_offer_direktur'));
            }

            if ($request->has('final_sallary')) {
                $offerData['final_sallary'] = $cleanSalary($request->input('final_sallary'));
            }

            $forceNewOffer = RecruitmentStatusService::hasFinanceRejected($applicant)
                || RecruitmentStatusService::isAwaitingHrdResubmitAfterDirectorNegotiation($applicant);

            SallaryOfferService::upsertActive(
                (int) $id,
                $offerData,
                $user,
                $forceNewOffer
            );

            $cleanNumber = function ($val) use ($cleanSalary) {
                if ($val === null || $val === '') return 0;
                $clean = $cleanSalary($val);
                return $clean !== '' ? (float) $clean : 0;
            };

            $gajiPokok       = $cleanNumber($request->input('gaji_pokok')) ?: (float)$valueToSave;
            $potBpjsKes      = $cleanNumber($request->input('potongan_bpjs_kes'));
            $potBpjsTk       = $cleanNumber($request->input('potongan_bpjs_tk'));
            $potPph21        = $cleanNumber($request->input('pot_pph21'));
            $pencadanganUpah = $cleanNumber($request->input('pencadangan_upah'));
            $startDate       = $request->input('start_date');

            $startDateTime = null;
            if (!empty($startDate)) {
                try {
                    $startDateTime = Carbon::parse($startDate)->startOfDay()->toDateTimeString();
                } catch (\Exception $e) {
                    $startDateTime = $startDate . ' 00:00:00';
                }
            }

            CandidateDataOffers::updateOrCreate(
                ['new_recruitment_id' => $id],
                [
                    'gaji_pokok'          => $gajiPokok,
                    'potongan_bpjs_kes'   => $potBpjsKes,
                    'potongan_bpjs_tk'    => $potBpjsTk,
                    'pot_pph21'           => $potPph21,
                    'tanggal_mulai_kerja' => $startDateTime,
                    'pencadangan_upah'    => $pencadanganUpah,
                    'created_by'          => $user ?? 'HRD',
                    'updated_by'          => $user ?? 'HRD',
                ]
            );
        }

        $action = $request->input('action', 'save');
        $user = $this->karyawan;

        if ($action === 'approve_data' || $action === 'approve_hrd') {
            (new RecruitmentStatusService())->update(
                $applicant->id,
                'internal_sallary_offer',
                Carbon::now(),
                'hrd_salary_approved',
                ['by' => $user]
            );

            return response()->json([
                'status'  => 200,
                'message' => 'Data penawaran gaji berhasil di-approve. Tombol kirim email kini aktif.',
                'data'    => $applicant->fresh(),
            ], 200);
        }

        if ($action === 'approve' || $action === 'submit' || $action === 'send_email') {
            if (empty($applicant->token_approval)) {
                $tokenService = new \App\Services\GenerateToken();
                $tokenKey = $applicant->id . ($applicant->nama_lengkap ?? '') . 'salary_approval' . str_replace('.', '', microtime(true));
                $applicant->token_approval = $tokenService->encrypt(md5($tokenKey) . '|' . date('Y-m-d'));
                $applicant->save();
            }

            (new RecruitmentStatusService())->update(
                $applicant->id,
                'internal_sallary_offer',
                Carbon::now(),
                'candidate_offering_sent',
                ['by' => $user]
            );

            try {
                if (!empty($applicant->email)) {
                    $btn = GenerateMessageAtsEmail::buildCandidateOfferingButtons($applicant, $applicant->token_approval);
                    $bodyEmail = GenerateMessageAtsEmail::bodyEmailCandidateOfferingSalary($applicant->fresh(['candidateDataOffer', 'sallaryOffer', 'personalRequest.masterJabatan']), $btn);
                    SendEmail::where('to', $applicant->email)
                        ->where('subject', 'Penawaran Gaji - PT Inti Surya Laboratorium')
                        ->where('body', $bodyEmail)
                        ->where('karyawan', $user ?? 'HRD')
                        ->noReply()
                        ->replyToAtsHrd()
                        ->send();
                }
            } catch (\Exception $e) {
                \Log::warning('Candidate salary offer email send failed', ['id' => $id, 'error' => $e->getMessage()]);
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Penawaran gaji berhasil di-approve dan email telah dikirim ke kandidat.',
                'data'    => $applicant->fresh(),
            ], 200);
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Data penawaran gaji berhasil disimpan.',
            'data'    => $applicant->fresh(),
        ], 200);
    }

    public function sendOfferingSalaryEmail(Request $request, $id = null)
    {
        $id = $id ?? $request->header('id') ?? $request->input('id');

        $applicant = NewRecruitment::with([
            'personnelRequest.masterJabatan', 
            'masterJabatan', 
            'sallaryOffer', 
            'hrdInterview', 
            'userInterview',
            'candidateProfile', 
            'candidateEducations', 
            'candidateWorkExperiences',
            'candidateMedicalInformation'
        ])->find($id);

        if (!$applicant) {
            return response()->json([
                'status'  => 404,
                'message' => 'Candidate data not found.',
            ], 404);
        }

        if ($this->isOfferingRejected($applicant)) {
            return response()->json([
                'status'  => 400,
                'message' => 'Salary offer for this candidate has been rejected. Sending email is disabled.',
            ], 400);
        }

        $applicantStatus = strtolower((string) $applicant->status);
        if ($applicantStatus === 'finance_review' || $applicantStatus === 'finance review') {
            return response()->json([
                'status'  => 400,
                'message' => 'Status kandidat saat ini sedang dalam Finance Review. Pengiriman email ke Direktur dinonaktifkan.',
            ], 400);
        }

        if (!\App\Services\RecruitmentStatusService::hasFinanceApproved($applicant)) {
            return response()->json([
                'status'  => 400,
                'message' => 'Email ke Direktur hanya dapat dikirim setelah Finance menyetujui gaji yang diajukan HRD.',
            ], 400);
        }

        if (empty($applicant->token_approval)) {
            $tokenService = new \App\Services\GenerateToken();
            $tokenKey = $applicant->id . ($applicant->nama_lengkap ?? '') . 'salary_approval' . str_replace('.', '', microtime(true));
            $token = $tokenService->encrypt(md5($tokenKey) . '|' . date('Y-m-d'));
            $applicant->token_approval = $token;
            $applicant->save();
        } else {
            $token = $applicant->token_approval;
        }

        $btn = GenerateMessageAtsEmail::buildSalaryDecisionButtons($applicant, $token);

        $targetEmail = env('EMAIL_DIREKTUR_BAPAK');
        $user = $this->karyawan;

        try {
            $bodyEmail = GenerateMessageAtsEmail::bodyEmailSallaryOffer($applicant, $btn);

            SendEmail::where('to', $targetEmail)
                ->where('subject', 'Permohonan Persetujuan Offering Salary - ' . ($applicant->nama_lengkap ?? 'Kandidat'))
                ->where('body', $bodyEmail)
                ->where('karyawan', $user)
                ->noReply()
                ->send();

            SallaryOfferService::upsertActive(
                (int) $applicant->id,
                ['email_sent_at' => Carbon::now()],
                $user ?? 'System'
            );

            return response()->json([
                'status'  => 200,
                'message' => 'Offering salary approval email sent successfully to ' . $targetEmail,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 500,
                'message' => 'Failed to send email: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function approveCandidate(Request $request, $id = null)
    {
        $id = $id ?? $request->header('id') ?? $request->input('id');

        $applicant = NewRecruitment::find($id);

        if (!$applicant) {
            return response()->json([
                'status'  => 404,
                'message' => 'Candidate data not found.',
            ], 404);
        }

        $startDate = $request->input('start_date');

        if (empty($startDate)) {
            return response()->json([
                'status'  => 422,
                'message' => 'Tanggal mulai kerja wajib diisi.',
            ], 422);
        }

        $cleanNumber = function ($val) {
            if ($val === null || $val === '') return 0;
            $clean = preg_replace('/[^0-9.]/', '', str_replace(',', '.', str_replace('.', '', (string)$val)));
            return $clean !== '' ? (float) $clean : 0;
        };

        $gajiPokok       = $cleanNumber($request->input('gaji_pokok'));
        $potBpjsKes      = $cleanNumber($request->input('potongan_bpjs_kes'));
        $potBpjsTk       = $cleanNumber($request->input('potongan_bpjs_tk'));
        $potPph21        = $cleanNumber($request->input('pot_pph21'));
        $pencadanganUpah = $cleanNumber($request->input('pencadangan_upah'));

        $user = $this->karyawan;
        $now = Carbon::now();

        try {
            $startDateTime = Carbon::parse($startDate)->startOfDay()->toDateTimeString();
        } catch (\Exception $e) {
            $startDateTime = $startDate . ' 00:00:00';
        }

        CandidateDataOffers::updateOrCreate(
            ['new_recruitment_id' => $id],
            [
                'gaji_pokok'          => $gajiPokok,
                'potongan_bpjs_kes'   => $potBpjsKes,
                'potongan_bpjs_tk'    => $potBpjsTk,
                'pot_pph21'           => $potPph21,
                'tanggal_mulai_kerja' => $startDateTime,
                'pencadangan_upah'    => $pencadanganUpah,
                'created_by'          => $user ?? 'HRD',
                'updated_by'          => $user ?? 'HRD',
            ]
        );

        SallaryOfferService::upsertActive(
            (int) $id,
            ['final_sallary' => $gajiPokok],
            $user ?? 'HRD'
        );

        $applicant->approved_by = $user ?? 'HRD';
        $applicant->approved_at = $now;
        $applicant->save();

        (new \App\Services\RecruitmentStatusService())->update($id, 'hired', $now, 'candidate_approved');

        $emailSent = false;
        if (!empty($applicant->email)) {
            $applicant->loadMissing([
                'sallaryOffer',
                'candidateDataOffer',
                'personalRequest.masterJabatan',
                'personnelRequest.masterJabatan',
            ]);

            $dataObj = GenerateMessageAtsEmail::buildOfferingLetterPayload($applicant, [
                'gaji_pokok'          => $gajiPokok,
                'potongan_bpjs_kes'   => $potBpjsKes,
                'potongan_bpjs_tk'    => $potBpjsTk,
                'pot_pph21'           => $potPph21,
                'pencadangan_upah'    => $pencadanganUpah,
                'tanggal_mulai_kerja' => Carbon::parse($startDate)->translatedFormat('d F Y'),
            ]);

            $emailSent = GenerateMessageAtsEmail::sendCandidateHiringLetterEmail(
                $applicant,
                $dataObj,
                $user ?? 'HRD'
            );
        }

        $applicant->loadMissing('personalRequest', 'personnelRequest');
        app(AtsNotificationService::class)->candidateHired(
            $applicant,
            $applicant->personalRequest ?? $applicant->personnelRequest
        );

        return response()->json([
            'status'  => 200,
            'message' => $emailSent
                ? 'Kandidat berhasil disetujui. Surat Keputusan Penerimaan (Hiring Letter) telah dikirim ke email kandidat.'
                : (empty($applicant->email)
                    ? 'Kandidat berhasil disetujui, namun email kandidat tidak tersedia sehingga Hiring Letter tidak terkirim.'
                    : 'Kandidat berhasil disetujui, namun pengiriman Hiring Letter ke email kandidat gagal.'),
            'data'    => $applicant->fresh(['candidateDataOffer']),
        ], 200);
    }
}
