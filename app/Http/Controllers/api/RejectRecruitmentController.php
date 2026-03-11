<?php
namespace App\Http\Controllers\api;

use App\Models\DataKandidat;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;


class RejectRecruitmentController extends Controller
{
    // public function index(Request $request)
    // {
    //     $searchYear = $request->search;
    //     $db = isset($searchYear) ? date('Y', strtotime($searchYear)) : $this->db;

    //     $data = DataKandidat::select(
    //             'recruitment.*',
    //             'cabang.nama_cabang as nama_cabang',
    //             'posision.nama_jabatan as name_posisi',
    //             'a.nama_lengkap as user_interview',
    //             'b.nama_lengkap as rejectinterviewhrd',
    //             'c.nama_lengkap as rejectkandidat',
    //             'f.nama_lengkap as rejectinterviewuser',
    //             'h.nama_lengkap as rejectofferingsalary'
    //         )
    //         ->leftjoin('master_jabatan as posision', 'recruitment.bagian_di_lamar', '=', 'posision.id')
    //         ->leftjoin('master_cabang as cabang', 'recruitment.id_cabang', '=', 'cabang.id')
    //         ->leftjoin('master_karyawan as a', 'recruitment.user_interview_by', '=', 'a.nama_lengkap')
    //         ->leftjoin('master_karyawan as b', 'recruitment.reject_interview_hrd_by', '=', 'b.nama_lengkap')
    //         ->leftjoin('master_karyawan as c', 'recruitment.reject_kandidat_by', '=', 'c.nama_lengkap')
    //         ->leftjoin('master_karyawan as f', 'recruitment.reject_interview_user_by', '=', 'f.nama_lengkap')
    //         ->leftjoin('master_karyawan as h', 'recruitment.reject_offering_salary_by', '=', 'h.nama_lengkap')
    //         ->whereIn('recruitment.id_cabang', $this->privilageCabang)
    //         ->where('recruitment.is_active', '=', 0)
    //         ->whereYear('recruitment.created_at', $searchYear ?? date('Y'));

    //     return Datatables::of($data)->make(true);
    // }

    public function index(Request $request)
    {
        $year = $request->input('year', date('Y'));

        if (is_array($year)) {
            $year = $year[0];
        }

        $year = (int) $year;

        $query = DataKandidat::query()
            ->select(
                'recruitment.*',
                'cabang.nama_cabang as nama_cabang',
                'posision.nama_jabatan as name_posisi',
                'a.nama_lengkap as user_interview',
                'b.nama_lengkap as rejectinterviewhrd',
                'c.nama_lengkap as rejectkandidat',
                'f.nama_lengkap as rejectinterviewuser',
                'h.nama_lengkap as rejectofferingsalary'
            )
            ->leftJoin('master_jabatan as posision', 'recruitment.bagian_di_lamar', '=', 'posision.id')
            ->leftJoin('master_cabang as cabang', 'recruitment.id_cabang', '=', 'cabang.id')
            ->leftJoin('master_karyawan as a', 'recruitment.user_interview_by', '=', 'a.nama_lengkap')
            ->leftJoin('master_karyawan as b', 'recruitment.reject_interview_hrd_by', '=', 'b.nama_lengkap')
            ->leftJoin('master_karyawan as c', 'recruitment.reject_kandidat_by', '=', 'c.nama_lengkap')
            ->leftJoin('master_karyawan as f', 'recruitment.reject_interview_user_by', '=', 'f.nama_lengkap')
            ->leftJoin('master_karyawan as h', 'recruitment.reject_offering_salary_by', '=', 'h.nama_lengkap')
            ->whereIn('recruitment.id_cabang', $this->privilageCabang)
            ->where('recruitment.is_active', 0)
            ->whereYear('recruitment.created_at', $year);

        return datatables()
            ->eloquent($query)
            ->addIndexColumn()
            ->make(true);
    }

    public function BlacklistKandidat(Request $request)
    {
        $searchYear = $request->search;
        $db = isset($searchYear) ? date('Y', strtotime($searchYear)) : $this->db;

        $data = DataKandidat::where('id', $request->id)
            ->where('is_active', false)
            ->first();

        DB::beginTransaction();
        try {
            $data->is_blacklist = true;
            $data->save();

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil melakukan blacklist kandidat.!'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


}