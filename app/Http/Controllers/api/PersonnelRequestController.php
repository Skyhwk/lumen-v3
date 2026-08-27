<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\{PersonnelRequest,NewRecruitment,MasterKaryawan,MasterDivisi,MasterJabatan,MasterCabang,RecruitmentInterview,Question};
use App\Services\SallaryOfferService;
use App\Services\{GetBawahanAll,GetAtasan,GenerateMessageAtsEmail,SendEmail,GenerateToken,GenerateMessageAtsWhatsapp,SendWhatsapp,RecruitmentPictureService,AtsNotificationService,UserAssessmentCategoryService};
use App\Http\Controllers\api\Concerns\BuildsCandidateAssessmentPreview;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
Carbon::setLocale('id');
class PersonnelRequestController extends Controller
{
    use BuildsCandidateAssessmentPreview;

    public function candidatePreview(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['message' => 'ID request tidak ditemukan'], 400);
        }

        $personnelRequest = $this->ownedPersonnelRequestQuery()
            ->with(['detailPosisi', 'detailDivisi', 'masterJabatan', 'masterDivisi'])
            ->withCount('newRecruitments as total_pelamar')
            ->find($id);

        if (!$personnelRequest) {
            return response()->json(['message' => 'Data personel request tidak ditemukan'], 404);
        }

        $candidates = NewRecruitment::with(['hrdInterview', 'userInterview'])
            ->where('personnel_request_id', $id)
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
            return $this->formatCandidatePreviewItem($candidate, $pictureService);
        })->values();

        $posisiName = optional($personnelRequest->detailPosisi)->nama_jabatan 
            ?: (optional($personnelRequest->masterJabatan)->nama_jabatan ?: $personnelRequest->posisi);
        $divisiName = optional($personnelRequest->detailDivisi)->nama_divisi 
            ?: (optional($personnelRequest->masterDivisi)->nama_divisi ?: ($personnelRequest->divisi_alias ?: $personnelRequest->divisi));

        return response()->json([
            'status' => 'success',
            'data' => [
                'request' => [
                    'id' => $personnelRequest->id,
                    'no_request' => $personnelRequest->no_request,
                    'posisi' => $posisiName,
                    'divisi' => $divisiName,
                    'jumlah_personal' => (int) $personnelRequest->jumlah_personal,
                    'divisi_alias' => $personnelRequest->divisi_alias,
                    'minimum_matching' => $personnelRequest->minimum_matching,
                    'published_at' => $personnelRequest->published_at,
                    'published_by' => $personnelRequest->published_by,
                    'is_approve' => (int) $personnelRequest->is_approve,
                    'is_reject' => (int) $personnelRequest->is_reject,
                    'is_publish' => (int) $personnelRequest->is_publish,
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

    /**
     * Generate auto-increment no_request
     * Format: YYYYXXXX (e.g. 20260001)
     */
    private function generateNoRequest(): string
    {
        $microtime = str_replace('.', '', (string) microtime(true));

        return $microtime;
    }

    private function nullableValue($value)
    {
        return ($value === '' || $value === null) ? null : $value;
    }

    private function nullableInt($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return (int) $value;
    }

    private function ownedPersonnelRequestQuery()
    {
        // Dapatkan semua ID bawahan (termasuk diri sendiri)
        $allowedIds = $this->getAllowedEmployeeIds();

        // Cari nama_lengkap dari semua ID tersebut karena field created_by menggunakan nama_lengkap
        $allowedNames = \App\Models\MasterKaryawan::whereIn('id', $allowedIds)
            ->pluck('nama_lengkap')
            ->toArray();

        return PersonnelRequest::query()
            ->whereIn('created_by', $allowedNames)
            ->where(function($q) {
                $q->whereNull('is_active')->orWhere('is_active', '!=', 0);
            });
    }

    private function findOwnedPersonnelRequest($id)
    {
        return $this->ownedPersonnelRequestQuery()->find($id);
    }

    private function findOwnedRecruitment($newRecruitmentId)
    {
        return NewRecruitment::query()
            ->where('id', $newRecruitmentId)
            ->whereHas('personnelRequest', function ($query) {
                $query->where('created_by', $this->karyawan);
            })
            ->first();
    }

    private function parseFormBoolean($value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return filter_var($normalized, FILTER_VALIDATE_BOOLEAN);
    }

    private function getEffectiveKaryawanName()
    {
        $isDevMode = env('APP_ENV') !== 'production' && env('DEV_BYPASS_USER_ID') !== null;
        $devUserId = env('DEV_BYPASS_USER_ID');
        
        if ($isDevMode && $devUserId) {
            $devKaryawan = \App\Models\MasterKaryawan::where('id', $devUserId)->first();
            if ($devKaryawan) {
                return $devKaryawan->nama_lengkap;
            }
        }
        return $this->karyawan;
    }

    private function validatedUserAssessmentFlag(Request $request): int
    {
        return $this->parseFormBoolean($request->input('use_user_assessment'), false) ? 1 : 0;
    }

    private function managerHierarchyNames(): array
    {
        $allowedIds = $this->getAllowedEmployeeIds();
        $allowedNames = \App\Models\MasterKaryawan::whereIn('id', $allowedIds)
            ->pluck('nama_lengkap')
            ->toArray();
        $allowedNames[] = $this->getEffectiveKaryawanName();
        return array_unique($allowedNames);
    }

    private function syncUserAssessmentCategoryConfig(Request $request): void{
        $useAssessment = $this->parseFormBoolean($request->input('use_user_assessment'), false);
        if (!$useAssessment) {
            return;
        }

        $questionCount = (int) $request->input('user_assessment_question_count');
        $hasTimeLimit = $this->parseFormBoolean($request->input('user_assessment_has_time_limit'), false);
        $durationMinutes = $hasTimeLimit ? (int) $request->input('user_assessment_duration_minutes') : null;

        if ($questionCount < 1) {
            abort(422, 'Jumlah soal test user wajib diisi minimal 1.');
        }

        $categoryId = $request->input('assesment_question_category');
        if (!$categoryId) {
            abort(422, 'Kategori soal wajib dipilih apabila tes teknis diaktifkan.');
        }

        $hierarchyNames = $this->managerHierarchyNames();
        $category = \App\Models\QuestionCategory::where('id', $categoryId)
            ->where(function ($query) use ($hierarchyNames) {
                $query->whereIn('owner_karyawan', $hierarchyNames)
                      ->orWhereIn('assigned_manager', $hierarchyNames);
            })
            ->first();
            
        if (!$category) {
            abort(422, 'Kategori soal tidak valid atau Anda tidak memiliki akses.');
        }

        $availableQuestions = Question::query()
            ->where('question_category_id', $categoryId)
            ->where('question_scope', 'manager')
            ->where('status', 'active')
            ->where('is_active', 1)
            ->where('question_type', 'single_choice')
            ->count();

        if ($availableQuestions < 1) {
            abort(422, 'Bank Soal User Anda belum memiliki soal aktif. Silakan tambahkan soal terlebih dahulu.');
        }

        if ($questionCount > $availableQuestions) {
            abort(422, 'Jumlah soal test user tidak boleh melebihi total soal tersedia (' . $availableQuestions . ' soal).');
        }

        if ($hasTimeLimit && $durationMinutes < 1) {
            abort(422, 'Durasi test user wajib diisi minimal 1 menit apabila batas waktu aktif.');
        }

        $category->update([
            'question_count' => $questionCount,
            'has_time_limit' => $hasTimeLimit,
            'duration_minutes' => $hasTimeLimit ? $durationMinutes : 0,
        ]);
    }

    public function getUserAssessmentCategoryConfig()
    {
        $service = app(UserAssessmentCategoryService::class);
        $category = $service->findOwnerCategory((string) $this->karyawan);
        $config = $service->toConfigObject($category);

        return response()->json([
            'success' => true,
            'data' => $config ? [
                'question_count' => (int) $config->question_count,
                'has_time_limit' => (bool) $config->has_time_limit,
                'duration_minutes' => (int) $config->duration_minutes,
            ] : null,
        ], 200);
    }

    /**
     * Request dianggap selesai jika jumlah pelamar >= kebutuhan dan jumlah hired >= kebutuhan.
     */
    private function isPersonnelRequestFulfilled($row): bool
    {
        $required = (int) ($row->jumlah_personal ?? 0);
        if ($required <= 0) {
            return false;
        }

        $totalPelamar = (int) ($row->total_pelamar ?? 0);
        $totalHired = (int) ($row->total_hired ?? 0);

        return $totalPelamar >= $required && $totalHired >= $required;
    }

    private function applyCompletionFilter($query, ?string $filter)
    {
        if (!$filter || !in_array($filter, ['on_progress', 'completed'], true)) {
            return $query;
        }

        $query->where('is_reject', '!=', 1);

        if (Schema::hasColumn('personnel_requests', 'is_completed')) {
            if ($filter === 'completed') {
                $query->where('is_completed', 1);
            } else {
                $query->where(function ($q) {
                    $q->whereNull('is_completed')
                        ->orWhere('is_completed', '!=', 1);
                });
            }

            return $query;
        }

        $hiredSub = '(SELECT COUNT(*) FROM new_recruitment nr WHERE nr.personnel_request_id = personnel_requests.id AND nr.status = \'hired\')';
        $pelamarSub = '(SELECT COUNT(*) FROM new_recruitment nr WHERE nr.personnel_request_id = personnel_requests.id)';

        if ($filter === 'completed') {
            $query->where('is_approve', 1)
                ->where('jumlah_personal', '>', 0)
                ->whereRaw("{$pelamarSub} >= personnel_requests.jumlah_personal")
                ->whereRaw("{$hiredSub} >= personnel_requests.jumlah_personal");
        } else {
            $query->where(function ($q) use ($hiredSub, $pelamarSub) {
                $q->where('is_approve', 0)
                    ->orWhere(function ($q2) use ($hiredSub, $pelamarSub) {
                        $q2->where('is_approve', 1)
                            ->where(function ($q3) use ($hiredSub, $pelamarSub) {
                                $q3->where('jumlah_personal', '<=', 0)
                                    ->orWhereRaw("{$pelamarSub} < personnel_requests.jumlah_personal")
                                    ->orWhereRaw("{$hiredSub} < personnel_requests.jumlah_personal");
                            });
                    });
            });
        }

        return $query;
    }

    /**
     * Index - DataTables server-side
     */
    public function index(Request $request)
    {
        try {
            $completionFilter = $request->input('completion_filter');

            // Fetch records with counts for NewRecruitment and eager load relations
            $data = $this->ownedPersonnelRequestQuery()->select('personnel_requests.*')->with([
                'detailCabang', 
                'detailDivisi', 
                'detailPosisi',
                'newRecruitments' => function($q) {
                    $q->select('id', 'personnel_request_id', 'status');
                }
            ])->withCount([
                'newRecruitments as total_pelamar',
                'newRecruitments as total_hired' => function ($query) {
                    $query->where('status', 'hired');
                },
                'newRecruitments as total_keterima' => function($query) {
                    $query->whereIn('status', ['completed', 'hired']); 
                }
            ])->orderBy('id', 'desc');

            $data = $this->applyCompletionFilter($data, $completionFilter);
    
            $assessmentCategoryService = app(UserAssessmentCategoryService::class);

            return Datatables::of($data)
                ->editColumn('no_request', function ($row) use ($assessmentCategoryService) {
                    $assessmentCategoryService->appendLegacyConfigFields($row);

                    return $row->no_request;
                })
                ->addColumn('total_pelamar', function ($row) {
                    return $row->total_pelamar ?? 0;
                })
                ->addColumn('total_hired', function ($row) {
                    return (int) ($row->total_hired ?? 0);
                })
                ->addColumn('total_keterima', function ($row) {
                    return $row->total_keterima ?? 0;
                })
                ->addColumn('is_fulfilled', function ($row) {
                    if (isset($row->is_completed)) {
                        return (int) $row->is_completed === 1;
                    }

                    return $this->isPersonnelRequestFulfilled($row);
                })
                ->addColumn('highest_status', function ($row) {
                    if ($row->is_reject == 1) {
                        return 'pr_rejected';
                    }
                    if ($row->is_approve == 0) {
                        return 'pr_pending_approval';
                    }
                    if ($row->is_approve == 1 && $row->is_publish == 0) {
                        return 'pr_pending_publish';
                    }
                    return 'pr_published';
                })
                ->filterColumn('no_request', fn($q, $k) => $q->where('no_request', 'like', "%{$k}%"))
                ->filterColumn('request_type', fn($q, $k) => $q->where('request_type', 'like', "%{$k}%"))
                ->filterColumn('prioritas', fn($q, $k) => $q->where('prioritas', 'like', "%{$k}%"))
                ->filterColumn('tanggal_dibutuhkan', fn($q, $k) => $q->where('tanggal_dibutuhkan', 'like', "%{$k}%"))
                ->make(true);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(["message"=>$th->getMessage(),"line"=>$th->getLine(),"file"=>$th->getFile()],500);
        }
    }

    /**
     * Kanban - get all records for Kanban board
     */
    public function kanban()
    {
        try {
            $ownedRequestIds = $this->ownedPersonnelRequestQuery()->pluck('id');
            if ($ownedRequestIds->isEmpty()) {
                return response()->json([], 200);
            }

            // Fetch records milik user login; Kanban component will handle categorization
            $data = NewRecruitment::with([
                'personnelRequest.detailCabang',
                'personnelRequest.detailDivisi',
                'personnelRequest.detailPosisi',
                'userInterview',
                'hrdInterview',
                'candidateProfile',
            ])
                ->whereIn('personnel_request_id', $ownedRequestIds)
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($item) {
                    $item->has_completed_profile = $item->candidateProfile !== null;

                    return $item;
                })
                ->values();

            return response()->json($data, 200);
        } catch (\Throwable $th) {
            return response()->json(["message"=>$th->getMessage(),"line"=>$th->getLine(),"file"=>$th->getFile()], 400);
        }
    }

    /**
     * Store - insert new personal request
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $noRequest = $this->generateNoRequest();

            $useUserAssessment = $this->validatedUserAssessmentFlag($request);
            $this->syncUserAssessmentCategoryConfig($request);

            // === DEV MODE: Otomatis membaca konfigurasi dari .env ===
            // Menggunakan helper agar logic impersonasi lebih tersentralisasi
            $createdBy = $this->getEffectiveKaryawanName();

            $data = PersonnelRequest::create([
                'no_request'                => $noRequest,
                'request_type'              => $request->request_type,
                'karyawan_lama_nama'        => $this->nullableValue($request->karyawan_lama_nama),
                'karyawan_lama_nik'         => $this->nullableValue($request->karyawan_lama_nik),
                'alasan_replacement'        => $this->nullableValue($request->alasan_replacement),
                'alasan_replacement_lainnya'=> $this->nullableValue($request->alasan_replacement_lainnya),
                'divisi'                    => $request->divisi,
                'posisi'                    => $request->posisi,
                'jumlah_personal'           => $request->jumlah_personal,
                'lokasi_penempatan_cabang'  => $this->nullableInt($request->lokasi_penempatan_cabang),
                'grade_master_karyawan'     => $this->nullableValue($request->grade_master_karyawan),
                'alasan_kebutuhan'          => $this->nullableValue($request->alasan_kebutuhan),
                'job_description'           => $this->nullableValue($request->job_description),
                'pendidikan'                => $this->nullableValue($request->pendidikan),
                'pengalaman_kerja'          => $this->nullableValue($request->pengalaman_kerja),
                'usia_maksimum'             => $this->nullableInt($request->usia_maksimum),
                'minimum_matching'          => $this->nullableInt($request->minimum_matching),
                'assesment_question_category'=> $this->nullableInt($request->assesment_question_category),
                'gender'                    => $request->gender,
                'skill_wajib'               => $this->nullableValue($request->skill_wajib),
                'sertifikasi'               => $this->nullableValue($request->sertifikasi),
                'tanggal_dibutuhkan'        => $this->nullableValue($request->tanggal_dibutuhkan),
                'prioritas'                 => $request->prioritas,
                'max_salary'                => $this->nullableValue($request->max_salary),
                'use_user_assessment'       => $useUserAssessment,
                'user_assessment_question_count' => $useUserAssessment ? (int) $request->user_assessment_question_count : null,
                'user_assessment_has_time_limit' => $useUserAssessment ? $this->parseFormBoolean($request->user_assessment_has_time_limit, false) : false,
                'user_assessment_duration_minutes' => $useUserAssessment && $this->parseFormBoolean($request->user_assessment_has_time_limit, false) ? (int) $request->user_assessment_duration_minutes : null,
                'created_by'                => $createdBy,
            ]);

            DB::commit();
            app(AtsNotificationService::class)->personnelRequestSubmitted($data);

            return response()->json([
                'status'     => 'success',
                'message'    => 'Personal Request berhasil dibuat.',
                'no_request' => $noRequest,
            ], 201);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('PersonnelRequestController@store: ' . $th->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan sistem, silakan hubungi administrator.',
            ], 500);
        }
    }

    public function update(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $this->findOwnedPersonnelRequest($request->id);
            if (!$data) {
                return response()->json(['message' => 'Data tidak ditemukan'], 404);
            }
            if ($data->is_approve == 1) {
                return response()->json(['message' => 'Request yang sudah disetujui HR tidak dapat diubah.'], 400);
            }

            $useUserAssessment = $this->validatedUserAssessmentFlag($request);
            $this->syncUserAssessmentCategoryConfig($request);

            $data->update([
                'request_type'              => $request->request_type,
                'karyawan_lama_nama'        => $this->nullableValue($request->karyawan_lama_nama),
                'karyawan_lama_nik'         => $this->nullableValue($request->karyawan_lama_nik),
                'alasan_replacement'        => $this->nullableValue($request->alasan_replacement),
                'alasan_replacement_lainnya'=> $this->nullableValue($request->alasan_replacement_lainnya),
                'divisi'                    => $request->divisi,
                'posisi'                    => $request->posisi,
                'jumlah_personal'           => $request->jumlah_personal,
                'lokasi_penempatan_cabang'  => $this->nullableInt($request->lokasi_penempatan_cabang),
                'grade_master_karyawan'     => $this->nullableValue($request->grade_master_karyawan),
                'alasan_kebutuhan'          => $this->nullableValue($request->alasan_kebutuhan),
                'job_description'           => $this->nullableValue($request->job_description),
                'pendidikan'                => $this->nullableValue($request->pendidikan),
                'pengalaman_kerja'          => $this->nullableValue($request->pengalaman_kerja),
                'usia_maksimum'             => $this->nullableInt($request->usia_maksimum),
                'minimum_matching'          => $this->nullableInt($request->minimum_matching),
                'assesment_question_category'=> $this->nullableInt($request->assesment_question_category),
                'gender'                    => $request->gender,
                'skill_wajib'               => $this->nullableValue($request->skill_wajib),
                'sertifikasi'               => $this->nullableValue($request->sertifikasi),
                'tanggal_dibutuhkan'        => $this->nullableValue($request->tanggal_dibutuhkan),
                'prioritas'                 => $request->prioritas,
                'max_salary'                => $this->nullableValue($request->max_salary),
                'use_user_assessment'       => $useUserAssessment,
                'user_assessment_question_count' => $useUserAssessment ? (int) $request->user_assessment_question_count : null,
                'user_assessment_has_time_limit' => $useUserAssessment ? $this->parseFormBoolean($request->user_assessment_has_time_limit, false) : false,
                'user_assessment_duration_minutes' => $useUserAssessment && $this->parseFormBoolean($request->user_assessment_has_time_limit, false) ? (int) $request->user_assessment_duration_minutes : null,
                'updated_by'                => $this->getEffectiveKaryawanName(),
            ]);

            DB::commit();
            return response()->json([
                'status'     => 'success',
                'message'    => 'Personal Request berhasil diupdate.',
            ], 200);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('PersonnelRequestController@update: ' . $th->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan sistem, silakan hubungi administrator.',
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $this->findOwnedPersonnelRequest($request->id);
            if (!$data) {
                return response()->json(['message' => 'Data tidak ditemukan'], 404);
            }
            if ($data->is_approve == 1) {
                return response()->json(['message' => 'Request yang sudah disetujui HR tidak dapat dibatalkan.'], 400);
            }
            $data->update([
                'is_active' => 0,
                'cancled_at' => \Carbon\Carbon::now(),
                'cancled_by' => $this->getEffectiveKaryawanName()
            ]);
            DB::commit();
            return response()->json(['message' => 'Request berhasil dibatalkan']);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(["message" => $th->getMessage()], 500);
        }
    }

    /**
     * Show one record
     */
    public function show(Request $request)
    {
        $data = $this->findOwnedPersonnelRequest($request->id);
        if (!$data) {
            return response()->json(['message' => 'Data personel request tidak ditemukan'], 404);
        }

        app(UserAssessmentCategoryService::class)->appendLegacyConfigFields($data);

        return response()->json($data, 200);
    }

    /**
     * Get list of active karyawan for replacement dropdown (Select2)
     *
     * NOTE: master_karyawan has NO `id` column — its primary key is
     * `user_id`. The previous version did `->select('id', ...)`, which
     * throws "Unknown column 'id' in 'field list'" on every call. That
     * exception was swallowed by the catch block below (400 response),
     * so the frontend's .catch() silently set the options list to [] —
     * meaning the "Nama Karyawan Lama" dropdown was ALWAYS empty, and
     * therefore Divisi/Posisi could never auto-fill either, since there
     * was nothing to select in the first place.
     */
    /**
     * Helper to get allowed employee IDs based on hierarchy
     */
    private function getAllowedEmployeeIds()
    {
        // === DEV MODE: Otomatis membaca konfigurasi dari .env ===
        // Fitur ini otomatis MATI jika APP_ENV='production' di .env
        $isDevMode = env('APP_ENV') !== 'production' && env('DEV_BYPASS_USER_ID') !== null;
        $devUserId = env('DEV_BYPASS_USER_ID'); // Ambil dari .env
        
        $userId = $this->user_id;

        if ($isDevMode && $devUserId) {
            $userId = $devUserId;
        }

        // Get hierarchy (manager + all subordinates up to 3 levels deep)
        
        $bawahanAll = GetBawahanAll::where('id', $userId)->get();
        
        return $bawahanAll->pluck('id')->toArray();
    }

    public function getKaryawan()
    {
        try {
            $allowedIds = $this->getAllowedEmployeeIds();

            $list = MasterKaryawan::select('user_id', 'nik_karyawan', 'nama_lengkap', 'id_department', 'id_jabatan', 'id_cabang', 'grade')
                ->where('is_active', true)
                ->whereIn('id', $allowedIds)
                ->orderBy('nama_lengkap')
                ->get()
                ->map(function ($k) {
                    return [
                        'id'          => $k->user_id, // Select2 option value -> master_karyawan.user_id
                        'nik'         => $k->nik_karyawan,
                        'nama_lengkap'=> $k->nama_lengkap,
                        'grade_master_karyawan'=> $k->grade,
                        'divisi'      => $k->id_department, // -> master_divisi.id
                        'posisi'      => $k->id_jabatan,    // -> master_jabatan.id
                        'cabang'      => $k->id_cabang,     // -> master_cabang.id
                        'text'        => $k->nama_lengkap . ($k->nik_karyawan ? ' (' . $k->nik_karyawan . ')' : ''),
                    ];
                });

            return response()->json($list, 200);
        } catch (\Throwable $th) {
            return response()->json(["message"=>$th->getMessage(),"line"=>$th->getLine(),"file"=>$th->getFile()],400);
        }
    }

    /**
     * Get distinct grade list from master_karyawan (for Select2)
     */
    public function getGrade()
    {
        try {
            $allowedIds = $this->getAllowedEmployeeIds();

            $grades = MasterKaryawan::select('grade')
                ->where('is_active', true)
                ->whereIn('id', $allowedIds)
                ->whereNotNull('grade')
                ->where('grade', '!=', '')
                ->distinct()
                ->orderBy('grade')
                ->pluck('grade')
                ->map(fn($g) => ['id' => $g, 'text' => $g]);

            return response()->json($grades, 200);
        } catch (\Throwable $th) {
            return response()->json(["message"=>$th->getMessage(),"line"=>$th->getLine(),"file"=>$th->getFile()],400);
        }
    }

    /**
     * Get list of active divisi (for Select2)
     */
    public function getDivisi()
    {
        try {
            $allowedIds = $this->getAllowedEmployeeIds();
            $allowedDivisiIds = MasterKaryawan::whereIn('id', $allowedIds)->whereNotNull('id_department')->pluck('id_department')->unique()->toArray();

            $divisi = MasterDivisi::where('is_active', 1)
                ->whereIn('id', $allowedDivisiIds)
                ->orderBy('nama_divisi')
                ->get()
                ->map(fn($d) => ['id' => $d->id, 'text' => $d->nama_divisi]);

            return response()->json($divisi, 200);
        } catch (\Throwable $th) {
            return response()->json(["message"=>$th->getMessage(),"line"=>$th->getLine(),"file"=>$th->getFile()],400);
        }
    }

    /**
     * Get list of active posisi/jabatan (for Select2)
     */
    public function getPosisi(Request $request)
    {
        try {
            $divisiId = $request->input('divisi_id');

            $query = MasterJabatan::where('is_active', 1);

            if ($divisiId) {
                $query->where('id_divisi', $divisiId);
            } else {
                $allowedIds = $this->getAllowedEmployeeIds();
                $allowedDivisiIds = MasterKaryawan::whereIn('id', $allowedIds)
                    ->where('is_active',1)->whereNotNull('id_department')->pluck('id_department')->unique()->toArray();
                $query->whereIn('id_divisi', $allowedDivisiIds);
            }

            $posisi = $query->orderBy('nama_jabatan')
                ->get()
                ->map(fn($j) => ['id' => $j->id, 'text' => $j->nama_jabatan]);

            return response()->json($posisi, 200);
        } catch (\Throwable $th) {
            return response()->json(["message"=>$th->getMessage(),"line"=>$th->getLine(),"file"=>$th->getFile()],400);
        }
    }

    /**
     * Get list of all active cabang (for Select2)
     */
    public function getCabang()
    {
        try {
            $cabang = MasterCabang::where('is_active', 1)
                ->orderBy('nama_cabang')
                ->get()
                ->map(fn($c) => ['id' => $c->id, 'text' => $c->nama_cabang]);

            return response()->json($cabang, 200);
        } catch (\Throwable $th) {
            return response()->json(["message"=>$th->getMessage(),"line"=>$th->getLine(),"file"=>$th->getFile()],400);
        }
    }

    /**
     * Schedule Interview - save to recruitment_interviews
     */
    public function scheduleInterview(Request $request)
    {
        DB::beginTransaction();
        try {
            $recruitment = $this->findOwnedRecruitment($request->new_recruitment_id);
            if (!$recruitment) {
                return response()->json(['message' => 'Data kandidat tidak ditemukan'], 404);
            }

            if ((int) ($recruitment->is_approve_interview_user ?? 0) === 1) {
                return response()->json([
                    'message' => 'Jadwal interview tidak dapat diubah karena keputusan interview user sudah final.',
                ], 400);
            }

            $existingInterview = RecruitmentInterview::where('new_recruitment_id', $request->new_recruitment_id)
                ->where('stage', 'user')
                ->where('is_active', 1)
                ->orderBy('id', 'desc')
                ->first();

            $isReschedule = (bool) $existingInterview;
            $previousNotes = $existingInterview ? $existingInterview->catatan_interview : null;

            // Nonaktifkan jadwal interview user sebelumnya (jika ada reschedule)
            RecruitmentInterview::where('new_recruitment_id', $request->new_recruitment_id)
                ->where('stage', 'user')
                ->where('is_active', 1)
                ->update(['is_active' => 0]);

            // Saat reschedule, link/ruangan dari HR otomatis dihapus agar HR mengisi ulang
            $linkGmeet = $isReschedule ? null : $this->nullableValue($request->link_gmeet);
            $ruanganInterview = $isReschedule ? null : $this->nullableValue($request->ruangan_interview);

            // Save to recruitment_interviews
            $interview = RecruitmentInterview::create([
                'new_recruitment_id' => $request->new_recruitment_id,
                'stage'              => 'user',
                'tgl_interview'      => $request->tgl_interview,
                'jenis_interview'    => $request->jenis_interview,
                'link_gmeet'         => $linkGmeet,
                'ruangan_interview'  => $ruanganInterview,
                'catatan'            => $request->catatan,
                'catatan_interview'  => $previousNotes,
                'created_by'         => $this->karyawan ?? 'System',
                'is_active'          => 1,
            ]);

            $recruitment->load(['personnelRequest.detailDivisi', 'personnelRequest.detailPosisi', 'personnelRequest.detailCabang']);
            
            $recruitment->update([
                'status' => 'interview_user'
            ]);

            // Cari data HRD beserta atasannya berdasarkan ID dari environment
            $hrdId = env('HRD_ID', '');
            $hrds = !empty($hrdId) ? GetAtasan::where('id', $hrdId)->get() : collect([]);
            
            if ($hrds->isNotEmpty()) {
                // Siapkan data untuk template email
                $dataArray = (object)[
                    'nama_kandidat'     => $recruitment->nama_lengkap,
                    'divisi'            => $recruitment->personnelRequest->detailDivisi->nama_divisi ?? $recruitment->personnelRequest->divisi,
                    'posisi'            => $recruitment->personnelRequest->detailPosisi->nama_jabatan ?? $recruitment->personnelRequest->posisi,
                    'cabang'            => $recruitment->personnelRequest->detailCabang->nama_cabang ?? $recruitment->personnelRequest->lokasi_penempatan_cabang,
                    'tgl_interview'     => $request->tgl_interview,
                    'jenis_interview'   => $request->jenis_interview,
                    'catatan_interview' => $request->input('catatan'),
                ];

                foreach ($hrds as $user) {
                    if (!$user->email) continue; // Skip jika tidak ada email

                    $dataArray->nama_user = $user->nama_lengkap;
                    $bodyEmail = GenerateMessageAtsEmail::bodyEmailHrdSchaduled($dataArray);
                    
                    SendEmail::where('to', $user->email)
                        ->where('subject', 'Jadwal Interview User - PT Inti Surya Laboratorium')
                        ->where('body', $bodyEmail)
                        ->where('karyawan', $user->nama_lengkap)
                        ->noReply()
                        ->send();
                }
            }

            app(AtsNotificationService::class)->userInterviewScheduledByRequester(
                $recruitment,
                $recruitment->personnelRequest
            );

            DB::commit();
            return response()->json([
                'message' => $isReschedule
                    ? 'Berhasil mengubah jadwal interview. Link/ruangan dari HR direset dan perlu diisi ulang oleh HRD.'
                    : 'Berhasil menjadwalkan interview!',
                'data'    => $interview
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "message" => $th->getMessage(),
                "line"    => $th->getLine(),
                "file"    => $th->getFile()
            ], 500);
        }
    }

    /**
     * Save user interview notes
     */
    public function saveUserNotes(Request $request)
    {
        DB::beginTransaction();
        try {
            $recruitment = $this->findOwnedRecruitment($request->new_recruitment_id);
            if (!$recruitment) {
                return response()->json(['message' => 'Data kandidat tidak ditemukan'], 404);
            }

            $interview = RecruitmentInterview::where('new_recruitment_id', $request->new_recruitment_id)
                ->where('stage', 'user')
                ->where('is_active', 1)
                ->orderBy('id', 'desc')
                ->first();

            if (!$interview) {
                return response()->json(['message' => 'Data interview user tidak ditemukan'], 404);
            }

            $interview->update([
                'catatan_interview' => $request->catatan_interview_user
            ]);

            DB::commit();
            return response()->json(['message' => 'Berhasil menyimpan catatan interview user!']);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(["message" => $th->getMessage(), "line" => $th->getLine(), "file" => $th->getFile()], 500);
        }
    }

    /**
     * Approve or reject the candidate by User
     */
    public function submitUserDecision(Request $request)
    {
        
        DB::beginTransaction();
        try {

            
            $recruitment = $this->findOwnedRecruitment($request->new_recruitment_id);
            if (!$recruitment) {
                return response()->json(['message' => 'Data kandidat tidak ditemukan'], 404);
            }
            
            $isApproved = $request->decision === 'approve' ? 1 : 0; 
            
            // Update interview status_result as well
            $interview = RecruitmentInterview::where('new_recruitment_id', $request->new_recruitment_id)
                ->where('stage', 'user')
                ->where('is_active', 1)
                ->orderBy('id', 'desc')
                ->first();

            if ($interview) {
                $interview->update([
                    'status_result' => $request->decision === 'approve' ? 'lulus' : 'gagal'
                ]);
            }
            
            $pr = PersonnelRequest::with(['detailDivisi', 'detailPosisi', 'detailCabang'])->find($recruitment->personnel_request_id);
           
            if ($request->decision === 'approve') {
                $tokenService = new GenerateToken();
                $tokenKey = $pr->id . $recruitment->nama_lengkap. 'approval' . str_replace('.', '', microtime(true));
                $token = $tokenService->encrypt(md5($tokenKey) . '|' . $tokenService->encrypt(DATE('Y-m-d')));
                
                $recruitment->update([
                    'approved_interview_user' => $this->karyawan,
                    'approved_interview_user_at' => Carbon::now(),
                    'is_approve_interview_user' => $isApproved,
                    'token_approval' => $token,
                    'status' => 'management_decision'
                ]);

                // Simpan salary offer user jika diisi
                if ($request->filled('sallary_offer_user')) {
                    $salaryValue = preg_replace('/[^0-9.]/', '', str_replace(',', '.', str_replace('.', '', $request->input('sallary_offer_user'))));
                    SallaryOfferService::upsertActive(
                        (int) $recruitment->id,
                        ['sallary_offer_user' => $salaryValue ?: null],
                        $this->karyawan
                    );
                }

                // kirim email ke HRD (developer akan mengisi email asli nanti)
                $emailContent = GenerateMessageAtsEmail::bodyEmailHasilInterviewUser($recruitment, $pr, $interview, $request->decision);
                 
                $subject = "Kandidat Interview User - " . $recruitment->nama_lengkap;
                
                SendEmail::where('to', env('EMAIL_DIREKTUR_IBU'))
                            ->where('subject', $subject)
                            ->where('body', $emailContent)
                            ->noReply()
                            ->send();

                app(AtsNotificationService::class)->userInterviewApproved($recruitment, $pr);
            } else {
                $recruitment->update([
                    'reject_interview_user_by' => $this->karyawan,
                    'reject_interview_user_at' => Carbon::now(),
                    'is_approve_interview_user' => $isApproved
                ]);

                app(AtsNotificationService::class)->userInterviewRejected($recruitment, $pr);

                try {
                    // Set posisi untuk template
                    $recruitment->posisi_di_lamar = $pr->detailPosisi->nama_jabatan ?? $pr->posisi;

                    if (!empty($recruitment->email)) {
                        $emailContent = GenerateMessageAtsEmail::bodyEmailRejectKandidat($recruitment);
                        $subject = "Informasi Hasil Seleksi - PT Inti Surya Laboratorium";
                        
                        SendEmail::where('to', $recruitment->email)
                                    ->where('subject', $subject)
                                    ->where('body', $emailContent)
                                    ->noReply()
                                    ->replyToAtsHrd()
                                    ->send();
                    }

                    $phone = $recruitment->no_telepon ?: ($recruitment->no_hp ?? null);
                    if (!empty($phone)) {
                        $waGen = new GenerateMessageAtsWhatsapp($recruitment);
                        $waMessage = $waGen->RejectedCandidateSelection();
                        $sendWa = new SendWhatsapp($phone, $waMessage);
                        $sendWa->send();
                    }
                } catch (\Throwable $th) {
                    Log::error('Gagal mengirim notifikasi reject kandidat: ' . $th->getMessage());
                }
            }

            DB::commit();
            return response()->json(['message' => 'Keputusan berhasil disimpan!']);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(["message" => $th->getMessage(), "line" => $th->getLine(), "file" => $th->getFile()], 500);
        }
    }
}