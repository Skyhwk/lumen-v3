<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PersonnelRequest;
use App\Models\NewRecruitment;
use App\Models\MasterKaryawan;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterCabang;
use App\Models\RecruitmentInterview;
use App\Services\GetBawahanAll;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
Carbon::setLocale('id');
class PersonnelRequestController extends Controller
{
    /**
     * Generate auto-increment no_request
     * Format: PR-YYYY-XXXX (e.g. PR-2026-0001)
     */
    private function generateNoRequest(): string
    {
        $microtime = str_replace('.', '', (string) microtime(true));

        return $microtime;
    }

    /**
     * Index - DataTables server-side
     */
    public function index(Request $request)
    {
        try {
            // Fetch records with counts for NewRecruitment and eager load relations
            $data = PersonnelRequest::select('personnel_requests.*')->with([
                'detailCabang', 
                'detailDivisi', 
                'detailPosisi'
            ])->withCount([
                'newRecruitments as total_pelamar',
                'newRecruitments as total_keterima' => function($query) {
                    $query->whereIn('status', ['completed']); 
                }
            ])->orderBy('id', 'desc');
    
            return Datatables::of($data)
                ->addColumn('total_pelamar', function ($row) {
                    return $row->total_pelamar ?? 0;
                })
                ->addColumn('total_keterima', function ($row) {
                    return $row->total_keterima ?? 0;
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
            // Fetch all records, Kanban component will handle categorization
            // Eager load relations to display exact names in Kanban board
            $data = NewRecruitment::with([
                'personnelRequest.detailCabang', 
                'personnelRequest.detailDivisi', 
                'personnelRequest.detailPosisi'
            ])->orderBy('id', 'desc')->get();
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

            $data = PersonnelRequest::create([
                'no_request'                => $noRequest,
                'request_type'              => $request->request_type,
                'karyawan_lama_nama'        => $request->karyawan_lama_nama,
                'karyawan_lama_nik'         => $request->karyawan_lama_nik,
                'alasan_replacement'        => $request->alasan_replacement,
                'alasan_replacement_lainnya'=> $request->alasan_replacement_lainnya,
                'divisi'                    => $request->divisi,
                'posisi'                    => $request->posisi,
                'jumlah_personal'           => $request->jumlah_personal,
                'lokasi_penempatan_cabang'  => $request->lokasi_penempatan_cabang,
                'grade_master_karyawan'     => $request->grade_master_karyawan,
                'alasan_kebutuhan'          => $request->alasan_kebutuhan,
                'job_description'           => $request->job_description,
                'pendidikan'                => $request->pendidikan,
                'pengalaman_kerja'          => $request->pengalaman_kerja,
                'usia_maksimum'             => $request->usia_maksimum,
                'gender'                    => $request->gender,
                'skill_wajib'               => $request->skill_wajib,
                'sertifikasi'               => $request->sertifikasi,
                'tanggal_dibutuhkan'        => $request->tanggal_dibutuhkan,
                'prioritas'                 => $request->prioritas,
                'max_salary'                => $request->max_salary,
                'created_by'                => $this->karyawan ?? null,
            ]);

            DB::commit();
            return response()->json([
                'status'     => 'success',
                'message'    => 'Personal Request berhasil dibuat.',
                'no_request' => $noRequest,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('PersonnelRequestController@store: ' . $th->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Show one record
     */
    public function show(Request $request)
    {
        $data = PersonnelRequest::findOrFail($request->id);
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
        $userId = auth()->user()->id ?? $this->user_id;

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
                ->whereIn('user_id', $allowedIds)
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
                ->whereIn('user_id', $allowedIds)
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
    public function getPosisi()
    {
        try {
            $allowedIds = $this->getAllowedEmployeeIds();
            $allowedPosisiIds = MasterKaryawan::whereIn('id', $allowedIds)->whereNotNull('id_jabatan')->pluck('id_jabatan')->unique()->toArray();

            $posisi = MasterJabatan::where('is_active', 1)
                ->whereIn('id', $allowedPosisiIds)
                ->orderBy('nama_jabatan')
                ->get()
                ->map(fn($j) => ['id' => $j->id, 'text' => $j->nama_jabatan]);

            return response()->json($posisi, 200);
        } catch (\Throwable $th) {
            return response()->json(["message"=>$th->getMessage(),"line"=>$th->getLine(),"file"=>$th->getFile()],400);
        }
    }

    /**
     * Get list of cabang (for Select2)
     */
    public function getCabang()
    {
        try {
            $allowedIds = $this->getAllowedEmployeeIds();
            $allowedCabangIds = MasterKaryawan::whereIn('user_id', $allowedIds)->whereNotNull('id_cabang')->pluck('id_cabang')->unique()->toArray();

            $cabang = MasterCabang::where('is_active', 1)
                ->whereIn('id', $allowedCabangIds)
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

            // Save to recruitment_interviews
            $interview = RecruitmentInterview::create([
                'new_recruitment_id' => $request->new_recruitment_id,
                'stage'              => 'user',
                'tgl_interview'      => $request->tgl_interview,
                'jenis_interview'    => $request->jenis_interview,
                'link_gmeet'         => $request->link_gmeet,
                'created_by'      => $request->user()->name ?? 'System',
                'is_active'          => 1,
            ]);

            // Update NewRecruitment status
            $recruitment = NewRecruitment::findOrFail($request->new_recruitment_id);
            $recruitment->update([
                'status' => 'interview_user'
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Berhasil menjadwalkan interview!',
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
}
