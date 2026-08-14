<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

use App\Models\PersonnelRequest;
use App\Models\NewRecruitment;
use App\Services\RecruitmentPictureService;

class PersonnelRequesthrdController extends Controller
{
    private function recruitmentStatusLabel($status)
    {
        $labels = [
            'assessment' => 'Assessment',
            'screening' => 'Screening HRD',
            'interview_hrd' => 'Interview HRD',
            'profile_completion' => 'Lengkapi Profil',
            'interview_user' => 'Interview User',
            'management_decision' => 'Keputusan Manajemen',
            'internal_sallary_offer' => 'Penawaran Gaji Internal',
            'salary_offer' => 'Penawaran Gaji',
            'sallary_offer' => 'Penawaran Gaji',
            'approved' => 'Disetujui',
            'hired' => 'Hired',
            'rejected' => 'Ditolak',
        ];

        return $labels[strtolower((string) $status)] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    private function countAnsweredQuestions($answersJson)
    {
        $answers = json_decode($answersJson ?: '{}', true) ?: [];

        return count(array_filter($answers, function ($value) {
            return $value !== null && $value !== '';
        }));
    }

    private function assessmentInProgressSummary($sessions)
    {
        foreach ($sessions as $session) {
            if ($session->status === 'in_progress') {
                $answered = $this->countAnsweredQuestions($session->answers_json);
                $total = (int) $session->question_count;

                return 'Sedang mengerjakan ' . $session->category_name . ' (' . $answered . '/' . $total . ' soal)';
            }

            if ($session->status === 'pending') {
                return 'Menunggu sesi ' . $session->category_name;
            }
        }

        return 'Assessment sedang berlangsung';
    }

    private function buildAssessmentProgress($recruitmentId)
    {
        $attempt = DB::table('assessment_attempts')
            ->where('recruitment_id', $recruitmentId)
            ->orderByDesc('id')
            ->first();

        if (!$attempt) {
            return [
                'has_attempt' => false,
                'attempt_status' => null,
                'overall_progress' => 0,
                'total_answered' => 0,
                'total_questions' => 0,
                'sessions' => [],
                'summary' => 'Belum memulai assessment',
            ];
        }

        $sessions = DB::table('assessment_sessions')
            ->where('assessment_attempt_id', $attempt->id)
            ->orderBy('session_order')
            ->get();

        $sessionData = [];
        $totalAnswered = 0;
        $totalQuestions = 0;

        foreach ($sessions as $session) {
            $answered = $this->countAnsweredQuestions($session->answers_json);
            $questions = json_decode($session->questions_json ?: '[]', true) ?: [];
            $questionCount = count($questions) ?: (int) $session->question_count;

            $totalAnswered += $answered;
            $totalQuestions += $questionCount;

            $sessionData[] = [
                'order' => (int) $session->session_order,
                'name' => $session->category_name,
                'status' => $session->status,
                'answered' => $answered,
                'total' => $questionCount,
                'progress_percent' => $questionCount > 0 ? round(($answered / $questionCount) * 100) : 0,
                'started_at' => $session->started_at,
                'completed_at' => $session->completed_at,
            ];
        }

        $summary = 'Assessment belum dimulai';
        if ($attempt->status === 'completed') {
            $summary = 'Assessment selesai';
        } elseif ($attempt->status === 'expired') {
            $summary = 'Assessment kedaluwarsa';
        } elseif ($attempt->status === 'in_progress') {
            $summary = $this->assessmentInProgressSummary($sessions);
        }

        return [
            'has_attempt' => true,
            'attempt_status' => $attempt->status,
            'started_at' => $attempt->started_at,
            'completed_at' => $attempt->completed_at,
            'overall_progress' => $totalQuestions > 0 ? round(($totalAnswered / $totalQuestions) * 100) : 0,
            'total_answered' => $totalAnswered,
            'total_questions' => $totalQuestions,
            'sessions' => $sessionData,
            'summary' => $summary,
        ];
    }

    private function decodeMetaHistory($metaHistory)
    {
        if (is_array($metaHistory)) {
            return $metaHistory;
        }

        if (is_string($metaHistory) && $metaHistory !== '') {
            $decoded = json_decode($metaHistory, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
    /**
     * Get list of personal requests for DataTables
     */
    public function index(Request $request)
    {
        try {
            $query = PersonnelRequest::with(['masterJabatan', 'masterDivisi'])
                ->withCount('newRecruitments as total_pelamar')
                ->orderBy('id', 'desc');

            if ($request->has('year') && !empty($request->year)) {
                $query->whereYear('created_at', $request->year);
            }

            return Datatables::of($query)
                ->editColumn('posisi', function ($row) {
                    if ($row->masterJabatan && !empty($row->masterJabatan->nama_jabatan)) {
                        return $row->masterJabatan->nama_jabatan;
                    }
                    return $row->posisi ?: '-';
                })
                ->filterColumn('posisi', function ($q, $keyword) {
                    $q->where(function ($sub) use ($keyword) {
                        $sub->where('posisi', 'like', "%{$keyword}%")
                            ->orWhereHas('masterJabatan', function ($j) use ($keyword) {
                                $j->where('nama_jabatan', 'like', "%{$keyword}%");
                            });
                    });
                })
                ->editColumn('divisi', function ($row) {
                    if ($row->masterDivisi && !empty($row->masterDivisi->nama_divisi)) {
                        return $row->masterDivisi->nama_divisi;
                    }
                    return $row->divisi_alias ?: ($row->divisi ?: '-');
                })
                ->filterColumn('divisi', function ($q, $keyword) {
                    $q->where(function ($sub) use ($keyword) {
                        $sub->where('divisi', 'like', "%{$keyword}%")
                            ->orWhere('divisi_alias', 'like', "%{$keyword}%")
                            ->orWhereHas('masterDivisi', function ($d) use ($keyword) {
                                $d->where('nama_divisi', 'like', "%{$keyword}%");
                            });
                    });
                })
                ->addColumn('status_label', function ($row) {
                    if (isset($row->is_publish) && $row->is_publish == 1) {
                        return 'Published';
                    }
                    if (isset($row->is_approve) && $row->is_approve == 1) {
                        return 'Approved';
                    }
                    if ((isset($row->is_rejected) && $row->is_rejected == 1) || (isset($row->is_reject) && $row->is_reject == 1)) {
                        return 'Rejected';
                    }
                    return 'Pending';
                })
                ->addColumn('request_by', function ($row) {
                    return $row->created_by ?: 'Admin / HRD';
                })
                ->filterColumn('total_pelamar', function ($q, $keyword) {
                    if ($keyword === '' || !is_numeric($keyword)) {
                        return;
                    }

                    $q->has('newRecruitments', '=', (int) $keyword);
                })
                ->filterColumn('no_request', function ($q, $keyword) {
                    $q->where('no_request', 'like', "%{$keyword}%");
                })
                ->filterColumn('request_type', function ($q, $keyword) {
                    $q->where('request_type', 'like', "%{$keyword}%");
                })
                ->filterColumn('jumlah_personal', function ($q, $keyword) {
                    $q->where('jumlah_personal', 'like', "%{$keyword}%");
                })
                ->filterColumn('prioritas', function ($q, $keyword) {
                    $q->where('prioritas', 'like', "%{$keyword}%");
                })
                ->filterColumn('status_label', function ($q, $keyword) {
                    $keyword = strtolower($keyword);
                    if (strpos('published', $keyword) !== false) {
                        $q->where('is_publish', 1);
                    } elseif (strpos('approved', $keyword) !== false) {
                        $q->where('is_approve', 1);
                    } elseif (strpos('rejected', $keyword) !== false) {
                        $q->where(function ($sub) {
                            $sub->where('is_rejected', 1)->orWhere('is_reject', 1);
                        });
                    } elseif (strpos('pending', $keyword) !== false) {
                        $q->where('is_approve', 0)->where('is_rejected', 0);
                    }
                })
                ->filterColumn('request_by', function ($q, $keyword) {
                    $q->where('created_by', 'like', "%{$keyword}%");
                })
                ->make(true);
        } catch (\Throwable $th) {
            return response()->json(["message"=>$th->getMessage(),"line"=>$th->getLine(),"file"=>$th->getFile()],501);
        }
    }


    /**
     * Get detail of a personal request
     */
    public function show(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['message' => 'ID request tidak ditemukan'], 400);
        }

        $data = DB::table('personnel_requests')->where('id', $id)->first();
        if (!$data) {
            return response()->json(['message' => 'Data personel request tidak ditemukan'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ], 200);
    }

    /**
     * Approve personal request
     */
    public function approve(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['message' => 'ID request tidak ditemukan'], 400);
        }

        $data = DB::table('personnel_requests')->where('id', $id)->first();
        if (!$data) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        try {
            DB::table('personnel_requests')->where('id', $id)->update([
                'is_approve' => 1,
                // 'is_rejected' => 0,
                'updated_at' => Carbon::now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Personel request {$data->no_request} berhasil disetujui (Approved).",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyetujui request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject personal request
     */
    public function reject(Request $request)
    {
        $id = $request->input('id');

        if (!$id) {
            return response()->json(['message' => 'ID request tidak ditemukan'], 400);
        }

        $data = DB::table('personnel_requests')->where('id', $id)->first();
        if (!$data) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        try {
            DB::table('personnel_requests')->where('id', $id)->update([
                'is_approve' => 0,
                'is_reject' => 1,
                'updated_at' => Carbon::now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Personel request {$data->no_request} berhasil ditolak (Rejected).",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menolak request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Publish personal request
     */
    public function publish(Request $request)
    {
        $id = $request->input('id');
        $divisiAlias = $request->input('divisi_alias');
        $minimumMatching = $request->input('minimum_matching');

        if (!$id) {
            return response()->json(['message' => 'ID request tidak ditemukan'], 400);
        }

        $data = DB::table('personnel_requests')->where('id', $id)->first();
        if (!$data) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        try {
            $updateData = [
                'is_publish' => 1,
                'published_at' => Carbon::now(),
                'published_by' => $this->karyawan,
                'updated_at' => Carbon::now(),
            ];

            if ($divisiAlias !== null) {
                $updateData['divisi_alias'] = $divisiAlias;
            }

            if ($minimumMatching !== null) {
                $updateData['minimum_matching'] = $minimumMatching;
            }

            DB::table('personnel_requests')->where('id', $id)->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => "Personnel request {$data->no_request} berhasil dipublikasikan.",
        ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mempublikasikan request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview candidates and process progress for published request
     */
    public function candidatePreview(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['message' => 'ID request tidak ditemukan'], 400);
        }

        $personnelRequest = PersonnelRequest::with(['masterJabatan', 'masterDivisi'])
            ->withCount('newRecruitments as total_pelamar')
            ->find($id);

        if (!$personnelRequest) {
            return response()->json(['message' => 'Data personel request tidak ditemukan'], 404);
        }

        if ((int) ($personnelRequest->is_publish ?? 0) !== 1) {
            return response()->json(['message' => 'Preview kandidat hanya tersedia untuk request yang sudah dipublish'], 422);
        }

        $candidates = NewRecruitment::where('personnel_request_id', $id)
            ->orderByDesc('created_at')
            ->get();

        $statusCounts = $candidates
            ->groupBy(function ($candidate) {
                return strtolower((string) $candidate->status);
            })
            ->map(function ($group) {
                return $group->count();
            })
            ->toArray();

        $pictureService = app(RecruitmentPictureService::class);

        $candidateItems = $candidates->map(function ($candidate) use ($pictureService) {
            $status = strtolower((string) $candidate->status);
            $metaHistory = $this->decodeMetaHistory($candidate->meta_history);
            $assessment = $this->buildAssessmentProgress($candidate->id);
            $pictureUrl = $pictureService->toDataUri($candidate->picture);

            return [
                'id' => $candidate->id,
                'nama_lengkap' => $candidate->nama_lengkap,
                'email' => $candidate->email,
                'no_telepon' => $candidate->no_telepon,
                'picture' => $candidate->picture,
                'picture_url' => $pictureUrl,
                'status' => $status,
                'status_label' => $this->recruitmentStatusLabel($status),
                'nilai_kecocokan' => $candidate->nilai_kecocokan,
                'applied_at' => $candidate->created_at,
                'updated_at' => $candidate->updated_at,
                'meta_history' => $metaHistory,
                'assessment' => $assessment,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'request' => [
                    'id' => $personnelRequest->id,
                    'no_request' => $personnelRequest->no_request,
                    'posisi' => optional($personnelRequest->masterJabatan)->nama_jabatan ?: $personnelRequest->posisi,
                    'divisi' => optional($personnelRequest->masterDivisi)->nama_divisi ?: ($personnelRequest->divisi_alias ?: $personnelRequest->divisi),
                    'jumlah_personal' => (int) $personnelRequest->jumlah_personal,
                    'divisi_alias' => $personnelRequest->divisi_alias,
                    'minimum_matching' => $personnelRequest->minimum_matching,
                    'published_at' => $personnelRequest->published_at,
                    'published_by' => $personnelRequest->published_by,
                    'total_pelamar' => (int) ($personnelRequest->total_pelamar ?? $candidates->count()),
                ],
                'summary' => [
                    'total_pelamar' => $candidates->count(),
                    'assessment' => (int) ($statusCounts['assessment'] ?? 0),
                    'screening' => (int) ($statusCounts['screening'] ?? 0),
                    'interview_hrd' => (int) ($statusCounts['interview_hrd'] ?? 0),
                    'profile_completion' => (int) ($statusCounts['profile_completion'] ?? 0),
                    'interview_user' => (int) ($statusCounts['interview_user'] ?? 0),
                    'management_decision' => (int) ($statusCounts['management_decision'] ?? 0),
                    'salary_offer' => (int) (($statusCounts['internal_sallary_offer'] ?? 0) + ($statusCounts['salary_offer'] ?? 0) + ($statusCounts['sallary_offer'] ?? 0)),
                    'hired' => (int) ($statusCounts['hired'] ?? 0),
                    'rejected' => (int) ($statusCounts['rejected'] ?? 0),
                ],
                'candidates' => $candidateItems,
            ],
        ], 200);
    }
}
