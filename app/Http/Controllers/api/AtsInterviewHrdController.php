<?php

namespace App\Http\Controllers\api;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\Controller;
use App\Models\NewRecruitment;
use App\Models\RecruitmentInterview;
use App\Services\GenerateMessageAtsEmail;
use App\Services\GenerateMessageAtsWhatsapp;
use App\Services\MpdfService;
use App\Services\RecruitmentStatusService;
use App\Services\SendEmail;
use App\Services\SendWhatsapp;
use App\Services\AtsNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Mpdf\Output\Destination;

class AtsInterviewHrdController extends Controller
{
    /**
     * Get Datatable list of candidates in HRD Interview stage
     * mode = 'today' for today's interviews, 'upcoming_past' for upcoming/past interviews
     */
    public function index(Request $request)
    {
        $mode = $request->input('mode', 'today');
        $todayStr = Carbon::today()->toDateString();

        $query = NewRecruitment::with(['personalRequest.masterJabatan', 'hrdInterview', 'userInterview'])
            ->where(function ($q) {
                $q->where('status', 'interview_hrd')
                  ->orWhereHas('hrdInterview');
            })
            ->where('is_approved_interview_hrd', '!=', 1)
            ->where(function ($q) use ($mode, $todayStr) {
                if ($mode === 'today') {
                    $q->whereHas('hrdInterview', function ($sub) use ($todayStr) {
                        $sub->whereDate('tgl_interview', '=', $todayStr);
                    });
                } else {
                    $q->where(function ($sub) use ($todayStr) {
                        $sub->whereHas('hrdInterview', function ($sub2) use ($todayStr) {
                            $sub2->whereDate('tgl_interview', '!=', $todayStr);
                        })->orWhereDoesntHave('hrdInterview');
                    });
                }
            })
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
            ->addColumn('request_by', function ($row) {
                return optional($row->personalRequest)->created_by ?: '-';
            })
            ->filterColumn('no_request', function ($q, $keyword) {
                $q->whereHas('personalRequest', function ($sub) use ($keyword) {
                    $sub->where('no_request', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('request_by', function ($q, $keyword) {
                $q->whereHas('personalRequest', function ($sub) use ($keyword) {
                    $sub->where('created_by', 'like', "%{$keyword}%");
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
            ->filterColumn('status', function ($q, $keyword) {
                $q->where('status', 'like', "%{$keyword}%");
            })
            ->addColumn('jadwal_interview', function ($row) {
                $hrd = $row->hrdInterview;
                if ($hrd && $hrd->tgl_interview) {
                    $dt = Carbon::parse($hrd->tgl_interview);
                    $modeStr = $hrd->jenis_interview === 'Online' ? 'Online (GMeet)' : 'Offline (' . ($hrd->ruangan_interview ?: 'Office Room') . ')';
                    return $dt->format('d M Y, H:i') . ' WIB - ' . $modeStr;
                }
                return '-';
            })
            ->filterColumn('jadwal_interview', function ($q, $keyword) {
                $q->whereHas('hrdInterview', function ($sub) use ($keyword) {
                    $sub->where('tgl_interview', 'like', "%{$keyword}%")
                        ->orWhere('jenis_interview', 'like', "%{$keyword}%")
                        ->orWhere('ruangan_interview', 'like', "%{$keyword}%")
                        ->orWhere('link_gmeet', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('hrd_interview', function ($row) {
                $hrd = $row->hrdInterview;
                if (!$hrd) {
                    $hrdRaw = DB::table('recruitment_interviews')
                        ->where('new_recruitment_id', $row->id)
                        ->where('stage', 'hrd')
                        ->where('is_active', 1)
                        ->orderBy('id', 'desc')
                        ->first();
                    if (!$hrdRaw) {
                        $hrdRaw = DB::table('recruitment_interviews')
                            ->where('new_recruitment_id', $row->id)
                            ->where('stage', 'hrd')
                            ->orderBy('id', 'desc')
                            ->first();
                    }
                    return $hrdRaw ? (array) $hrdRaw : null;
                }
                return $hrd ? $hrd->toArray() : null;
            })
            ->addColumn('usia', function ($row) {
                $birthYear = $this->extractBirthYear($row);
                if ($birthYear) {
                    $age = Carbon::now()->year - $birthYear;
                    return $age . ' Yrs';
                }
                return '-';
            })
            ->filterColumn('usia', function ($q, $keyword) {
                $cleanDigits = preg_replace('/[^0-9]/', '', $keyword);
                if (!empty($cleanDigits)) {
                    $targetYear = Carbon::now()->year - (int) $cleanDigits;
                    $q->where(function ($sub) use ($targetYear, $cleanDigits) {
                        $sub->whereYear('tanggal_lahir', $targetYear)
                            ->orWhere('tempat_tanggal_lahir', 'like', "%{$cleanDigits}%");
                    });
                } else {
                    $q->where('tempat_tanggal_lahir', 'like', "%{$keyword}%");
                }
            })
            ->editColumn('shio', function ($row) {
                $birthDate = $row->tanggal_lahir ?? $this->getTtlString($row);
                $shioElemen = ShioElemenHelper::resolve($birthDate, $row->shio, $row->elemen);
                $shio = $shioElemen['shio'] ?? null;
                $elemen = $shioElemen['elemen'] ?? null;
                if ($shio && $elemen) {
                    return "{$shio} ({$elemen})";
                }
                return $shio ?: ($elemen ?: '-');
            })
            ->filterColumn('shio', function ($q, $keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('shio', 'like', "%{$keyword}%")
                        ->orWhere('elemen', 'like', "%{$keyword}%")
                        ->orWhere('tempat_tanggal_lahir', 'like', "%{$keyword}%")
                        ->orWhere('tanggal_lahir', 'like', "%{$keyword}%");
                });
            })
            ->editColumn('nilai_kecocokan', function ($row) {
                $score = $row->nilai_kecocokan !== null && $row->nilai_kecocokan !== '' 
                    ? $row->nilai_kecocokan 
                    : ($row->matching_score ?? rand(70, 95));
                return $score . '%';
            })
            ->filterColumn('nilai_kecocokan', function ($q, $keyword) {
                $cleanVal = preg_replace('/[^0-9.]/', '', $keyword);
                if (!empty($cleanVal)) {
                    $q->where(function ($sub) use ($cleanVal) {
                        $sub->where('nilai_kecocokan', 'like', "%{$cleanVal}%")
                            ->orWhere('matching_score', 'like', "%{$cleanVal}%");
                    });
                }
            })
            ->editColumn('status', function ($row) {
                return $row->status ?: 'interview_hrd';
            })
            ->addColumn('pipeline_status', function ($row) {
                $pipelineStatus = RecruitmentStatusService::resolvePipelineStatus($row);

                return $pipelineStatus['code'] ?? null;
            })
            ->addColumn('pipeline_status_label', function ($row) {
                $pipelineStatus = RecruitmentStatusService::resolvePipelineStatus($row);

                return $pipelineStatus['label'] ?? null;
            })
            ->addColumn('reject_reason', function ($row) {
                return RecruitmentStatusService::getDirectorManagementRejectReason($row);
            })
            ->addColumn('has_reschedule_history', function ($row) {
                $count = DB::table('recruitment_interviews')
                    ->where('new_recruitment_id', $row->id)
                    ->where('stage', 'hrd')
                    ->count();
                return $count > 1;
            })
            ->make(true);
    }

    /**
     * Input or update HRD Interview score & evaluation results
     */
    public function inputHrdResult(Request $request, $id)
    {
        $applicant = NewRecruitment::find($id);

        if (!$applicant) {
            return response()->json([
                'status' => 404,
                'message' => 'Candidate data not found',
            ], 404);
        }

        $user = $this->karyawan ?? $request->header('user') ?? 'HRD Admin';

        $nilaiHrd = $request->input('nilai_interview');
        $statusResult = $request->input('status_result', 'evaluated');
        $catatan = $request->input('catatan') ?: $request->input('catatan_interview');

        $applicant->update([
            'is_input_review_hrd' => 1,
        ]);

        $interview = RecruitmentInterview::updateOrCreate(
            [
                'new_recruitment_id' => $applicant->id,
                'stage' => 'hrd',
                'is_active' => 1,
            ],
            [
                'nilai_interview' => $nilaiHrd,
                'status_result' => $statusResult,
                'catatan_interview' => $catatan,
                'updated_by' => $user,
            ]
        );

        return response()->json([
            'status' => 200,
            'message' => 'HRD Interview score and evaluation saved successfully.',
            'data' => $interview,
        ], 200);
    }

    /**
     * Reschedule HRD Interview schedule & notify candidate
     */
    public function reschedule(Request $request, $id)
    {
        $applicant = NewRecruitment::with('personalRequest.masterJabatan')->find($id);

        if (!$applicant) {
            return response()->json([
                'status' => 404,
                'message' => 'Candidate data not found',
            ], 404);
        }

        $user = $this->karyawan ?? $request->header('user') ?? 'HRD Admin';

        $tglInterview = $request->input('tgl_interview');
        $jenisInterview = $request->input('jenis_interview', 'Online');
        $linkGmeet = $request->input('link_gmeet');
        $ruanganInterview = $request->input('ruangan_interview');

        // Deactivate previous HRD interview schedules for this candidate
        RecruitmentInterview::where('new_recruitment_id', $applicant->id)
            ->where('stage', 'hrd')
            ->update([
                'is_active' => 0,
                'status_result' => 'rescheduled',
            ]);

        // Create new active schedule record
        $interview = RecruitmentInterview::create([
            'new_recruitment_id' => $applicant->id,
            'stage' => 'hrd',
            'is_active' => 1,
            'tgl_interview' => $tglInterview,
            'jenis_interview' => $jenisInterview,
            'link_gmeet' => $jenisInterview === 'Online' ? $linkGmeet : null,
            'ruangan_interview' => $jenisInterview === 'Offline' ? $ruanganInterview : null,
            'status_result' => 'pending',
            'created_by' => $user,
            'updated_by' => $user,
        ]);

        // Send Reschedule Email & WhatsApp notifications
        try {
            $dt = Carbon::parse($tglInterview);
            $days = [
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu',
                'Sunday' => 'Minggu'
            ];
            $hariIndonesia = $days[$dt->format('l')] ?? $dt->format('l');
            $tglInter = $dt->format('d F Y');
            $jamInterview = $dt->format('H:i');

            $posisiName = $this->resolvePositionName($applicant);

            $dataArray = (object) [
                'nama_lengkap' => $applicant->nama_lengkap,
                'jenis_kelamin' => $applicant->jenis_kelamin,
                'posisi_di_lamar' => $posisiName,
                'nama_jabatan' => $posisiName,
                'hariIndonesia' => $hariIndonesia,
                'tglInter' => $tglInter,
                'jam_interview' => $jamInterview,
                'jam_interview_hrd' => $jamInterview,
                'jenis_interview_hrd' => $jenisInterview,
                'link_gmeet_hrd' => $jenisInterview === 'Online' ? $linkGmeet : null,
                'alamat_cabang' => $jenisInterview === 'Offline' ? $ruanganInterview : 'Online Meeting',
                'kode_uniq' => $applicant->id,
            ];

            if (!empty($applicant->email)) {
                $bodyEmail = GenerateMessageAtsEmail::bodyEmailApproveKandidat($dataArray);
                SendEmail::where('to', $applicant->email)
                    ->where('subject', 'Reschedule Undangan Interview HRD - PT Inti Surya Laboratorium')
                    ->where('body', $bodyEmail)
                    ->where('karyawan', $user)
                    ->noReply()
                    ->replyToAtsHrd()
                    ->send();
            }

            $phone = $applicant->no_telepon ?: ($applicant->no_hp ?? null);
            if (!empty($phone)) {
                $waObj = new GenerateMessageAtsWhatsapp($dataArray);
                $waMessage = $waObj->PassedCandidateSelection();

                $sendWa = new SendWhatsapp($phone, $waMessage);
                $sendWa->send();
            }
        } catch (\Exception $e) {
            // Silence exception
        }

        app(AtsNotificationService::class)->hrdInterviewRescheduled($applicant);

        return response()->json([
            'status' => 200,
            'message' => 'HRD Interview rescheduled successfully and candidate notified.',
            'data' => $interview,
        ], 200);
    }

    /**
     * Approve candidate HRD Interview result — does NOT change candidate status.
     * Status will be changed to 'interview_user' only when the department/user schedules the next interview.
     */
    public function passToUser(Request $request, $id)
    {
        $applicant = NewRecruitment::with(['personalRequest'])->find($id);

        if (!$applicant) {
            return response()->json([
                'status' => 404,
                'message' => 'Candidate data not found',
            ], 404);
        }

        $user = $this->karyawan ?? $request->header('user') ?? 'HRD Admin';
        $skipProfileCompletion = RecruitmentStatusService::shouldSkipProfileCompletionOnHrdPass($applicant);
        $nextStatus = $skipProfileCompletion ? 'interview_user' : 'profile_completion';

        (new RecruitmentStatusService())->update($applicant->id, $nextStatus, Carbon::now());

        $applicant->update([
            'status'                    => $nextStatus,
            'is_approved_interview_hrd' => 1,
            'approved_interview_hrd_by' => $user,
            'approved_interview_hrd_at' => Carbon::now(),
        ]);

        // Mark HRD Interview stage result as 'passed'
        RecruitmentInterview::where('new_recruitment_id', $applicant->id)
            ->where('stage', 'hrd')
            ->where('is_active', 1)
            ->update([
                'status_result' => 'passed',
                'updated_by' => $user,
            ]);

        // Send email + system notification to personnel request creator
        try {
            $posisiName  = $this->resolvePositionName($applicant);
            $noRequest   = $applicant->personalRequest->no_request ?? '-';
            $prCreatedBy = $applicant->personalRequest->created_by ?? null;

            if ($prCreatedBy) {
                // Lookup creator from master_karyawan by name
                $mk = DB::table('master_karyawan')
                    ->where('nama_lengkap', $prCreatedBy)
                    ->first();

                app(AtsNotificationService::class)->hrdInterviewPassed(
                    $applicant,
                    $applicant->personalRequest
                );

                // 2. Email notification to PR creator
                $creatorEmail = ($mk && !empty($mk->email)) ? $mk->email : null;
                if ($creatorEmail) {
                    $bodyEmail = GenerateMessageAtsEmail::bodyEmailHrdApprovalNotifUser((object)[
                        'nama_user'     => $prCreatedBy,
                        'nama_kandidat' => $applicant->nama_lengkap,
                        'posisi'        => $posisiName,
                        'no_request'    => $noRequest,
                        'approved_by'   => $user,
                        'approved_at'   => Carbon::now()->format('d M Y, H:i') . ' WIB',
                    ]);

                    SendEmail::where('to', $creatorEmail)
                        ->where('subject', "HRD Interview Approval — {$applicant->nama_lengkap} ({$posisiName})")
                        ->where('body', $bodyEmail)
                        ->where('karyawan', $user)
                        ->noReply()
                        ->send();
                }
            }

            // 3. Email & WhatsApp notification to Candidate to complete data profile
            if (!$skipProfileCompletion) {
                $token = $applicant->token ?? '';
                $baseUrl = rtrim(env('PORTALV4', 'https://portal.intilab.com'), '/');
                $profileUrl = "{$baseUrl}/public/recruitment/complete-profile/" . rawurlencode($token);

                $candidateDataObj = (object) [
                    'id'                    => $applicant->id,
                    'nama_lengkap'          => $applicant->nama_lengkap,
                    'jenis_kelamin'         => $applicant->jenis_kelamin,
                    'posisi_di_lamar'       => $posisiName,
                    'nama_jabatan'          => $posisiName,
                    'link_complete_profile' => $profileUrl,
                ];

                // 3a. Email to candidate
                if (!empty($applicant->email)) {
                    $bodyCandidateEmail = GenerateMessageAtsEmail::bodyEmailCompleteProfileCandidate($candidateDataObj);
                    SendEmail::where('to', $applicant->email)
                        ->where('subject', "Permintaan Kelengkapan Data Diri - PT Inti Surya Laboratorium")
                        ->where('body', $bodyCandidateEmail)
                        ->where('karyawan', $user)
                        ->noReply()
                        ->replyToAtsHrd()
                        ->send();
                }

                // 3b. WhatsApp to candidate
                $candidatePhone = $applicant->no_telepon ?? $applicant->no_hp ?? $applicant->no_whatsapp ?? null;
                if (!empty($candidatePhone)) {
                    $genWa = new GenerateMessageAtsWhatsapp($candidateDataObj);
                    $waMessage = $genWa->CompleteProfileCandidate();

                    $sendWa = new SendWhatsapp($candidatePhone, $waMessage);
                    $sendWa->send();
                }
            }
        } catch (\Exception $e) {
            // Silence — notification/email/wa failure must not block the approval response
        }

        return response()->json([
            'status' => 200,
            'message' => $skipProfileCompletion
                ? 'HRD Interview approved. Kandidat langsung siap untuk penjadwalan Interview User (profil sudah lengkap).'
                : 'HRD Interview approved. Candidate is ready for User Interview scheduling.',
            'skipped_profile_completion' => $skipProfileCompletion,
            'data' => $applicant->fresh(),
        ], 200);
    }

    /**
     * Reject candidate during HRD Interview stage
     */
    public function reject(Request $request, $id)
    {
        $applicant = NewRecruitment::find($id);

        if (!$applicant) {
            return response()->json([
                'status' => 404,
                'message' => 'Candidate data not found',
            ], 404);
        }

        $user = $this->karyawan ?? $request->header('user') ?? 'HRD Admin';
        $reason = $request->input('alasan_reject') ?? 'Did not pass HRD Interview evaluation';

        // Update candidate status to 'rejected' with RecruitmentStatusService meta_history tracking
        (new RecruitmentStatusService())->update($applicant->id, 'rejected', Carbon::now());

        $applicant->update([
            // 'status' => 'interview_hrd',
            'rejected_by' => $user,
            'rejected_at' => Carbon::now(),
            'alasan_reject' => $reason,
        ]);

        RecruitmentInterview::where('new_recruitment_id', $applicant->id)
            ->where('stage', 'hrd')
            ->where('is_active', 1)
            ->update([
                'status_result' => 'failed',
                'catatan_interview' => $reason,
                'updated_by' => $user,
            ]);

        // Send dignified rejection email & WhatsApp
        try {
            $posisiName = $this->resolvePositionName($applicant);

            $dataArray = (object) [
                'nama_lengkap' => $applicant->nama_lengkap,
                'jenis_kelamin' => $applicant->jenis_kelamin,
                'posisi_di_lamar' => $posisiName,
                'nama_jabatan' => $posisiName,
                'alasan_reject' => $reason,
                'hrd_name' => $user,
            ];

            if (!empty($applicant->email)) {
                $bodyEmail = GenerateMessageAtsEmail::bodyEmailRejectKandidat($dataArray);
                SendEmail::where('to', $applicant->email)
                    ->where('subject', 'Selection Result Notification - PT Inti Surya Laboratorium')
                    ->where('body', $bodyEmail)
                    ->where('karyawan', $user)
                    ->noReply()
                    ->replyToAtsHrd()
                    ->send();
            }

            $phone = $applicant->no_telepon ?: ($applicant->no_hp ?? null);
            if (!empty($phone)) {
                $waObj = new GenerateMessageAtsWhatsapp($dataArray);
                $waMessage = $waObj->RejectedCandidateSelection();

                $sendWa = new SendWhatsapp($phone, $waMessage);
                $sendWa->send();
            }
        } catch (\Exception $e) {
            // Silence exception
        }

        $applicant->loadMissing('personalRequest');
        app(AtsNotificationService::class)->hrdInterviewRejected(
            $applicant,
            $applicant->personalRequest
        );

        return response()->json([
            'status' => 200,
            'message' => 'Candidate application has been rejected.',
            'data' => $applicant,
        ], 200);
    }

    /**
     * Helper to resolve TTL string
     */
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

    /**
     * Helper to extract birth year
     */
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

    /**
     * Helper to resolve applicant position title safely in PHP 7.4
     */
    private function resolvePositionName($applicant)
    {
        if (!$applicant) {
            return 'Applied Position';
        }

        $pos = null;
        $pr = $applicant->personalRequest ?? null;

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

        return $pos ?: 'Applied Position';
    }

    /**
     * Get interview history list for candidate
     */
    public function getHistory(Request $request, $id)
    {
        $applicant = NewRecruitment::with(['hrdInterviewHistories'])->find($id);

        if (!$applicant) {
            return response()->json([
                'status' => 404,
                'message' => 'Candidate data not found',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Interview history fetched successfully',
            'data' => [
                'candidate' => $applicant,
                'histories' => $applicant->hrdInterviewHistories,
            ],
        ], 200);
    }
}
