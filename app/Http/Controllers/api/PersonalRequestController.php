<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PersonalRequest;
use App\Models\MasterKaryawan;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterCabang;
use App\Services\GetBawahanAll;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
Carbon::setLocale('id');
class PersonalRequestController extends Controller
{
    /**
     * Generate auto-increment no_request
     * Format: PR-YYYY-XXXX (e.g. PR-2026-0001)
     */
    private function generateNoRequest(): string
    {
        $year = Carbon::now()->year;
        $prefix = "PR-{$year}-";

        $last = PersonalRequest::where('no_request', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if ($last) {
            $lastNumber = (int) substr($last->no_request, strlen($prefix));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $newNumber;
    }

    /**
     * Index - DataTables server-side
     */
    public function index(Request $request)
    {
        $data = PersonalRequest::query()->orderBy('id', 'desc');

        return Datatables::of($data)
            ->filterColumn('no_request', fn($q, $k) => $q->where('no_request', 'like', "%{$k}%"))
            ->filterColumn('request_type', fn($q, $k) => $q->where('request_type', 'like', "%{$k}%"))
            ->filterColumn('prioritas', fn($q, $k) => $q->where('prioritas', 'like', "%{$k}%"))
            ->filterColumn('tanggal_dibutuhkan', fn($q, $k) => $q->where('tanggal_dibutuhkan', 'like', "%{$k}%"))
            ->make(true);
    }

    /**
     * Store - insert new personal request
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $noRequest = $this->generateNoRequest();

            $data = PersonalRequest::create([
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
                // 'created_by'                => $this->karyawan ?? null,
            ]);

            DB::commit();
            return response()->json([
                'status'     => 'success',
                'message'    => 'Personal Request berhasil dibuat.',
                'no_request' => $noRequest,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('PersonalRequestController@store: ' . $th->getMessage());
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
        $data = PersonalRequest::findOrFail($request->id);
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
    public function getKaryawan()
    {
        try {
            // --- SIMULATION / IMPERSONATION LOGIC ---
            // Set to true to test as a specific manager
            $simulateMode = true; 
            // The user_id of the manager you want to simulate (e.g. 9999 for testing)
            // Replace 9999 with the real manager's user_id
            $simulateManagerId = 277; 

            $userId = $this->user_id;
            if ($simulateMode) {
                $userId = $simulateManagerId;
            }

            // Get hierarchy (manager + all subordinates up to 3 levels deep)
            $bawahanAll = GetBawahanAll::where('id', $userId)->get();
            $allowedIds = $bawahanAll->pluck('id')->toArray();

            $list = MasterKaryawan::select('user_id', 'nik_karyawan', 'nama_lengkap', 'id_department', 'id_jabatan', 'id_cabang')
                ->where('is_active', true)
                ->whereIn('user_id', $allowedIds)
                ->orderBy('nama_lengkap')
                ->get()
                ->map(function ($k) {
                    return [
                        'id'          => $k->user_id, // Select2 option value -> master_karyawan.user_id
                        'nik'         => $k->nik_karyawan,
                        'nama_lengkap'=> $k->nama_lengkap,
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
        $grades = MasterKaryawan::select('grade')
            ->where('is_active', true)
            ->whereNotNull('grade')
            ->where('grade', '!=', '')
            ->distinct()
            ->orderBy('grade')
            ->pluck('grade')
            ->map(fn($g) => ['id' => $g, 'text' => $g]);

        return response()->json($grades, 200);
    }

    /**
     * Get list of active divisi (for Select2)
     */
    public function getDivisi()
    {
        $divisi = MasterDivisi::where('is_active', 1)
            ->orderBy('nama_divisi')
            ->get()
            ->map(fn($d) => ['id' => $d->id, 'text' => $d->nama_divisi]);

        return response()->json($divisi, 200);
    }

    /**
     * Get list of active posisi/jabatan (for Select2)
     */
    public function getPosisi()
    {
        $posisi = MasterJabatan::where('is_active', 1)
            ->orderBy('nama_jabatan')
            ->get()
            ->map(fn($j) => ['id' => $j->id, 'text' => $j->nama_jabatan]);

        return response()->json($posisi, 200);
    }

    /**
     * Get list of cabang (for Select2)
     */
    public function getCabang()
    {
        // Adjust condition if master_cabang has is_active or soft deletes
        $cabang = MasterCabang::orderBy('nama_cabang')
            ->get()
            ->map(fn($c) => ['id' => $c->id, 'text' => $c->nama_cabang]);

        return response()->json($cabang, 200);
    }
}