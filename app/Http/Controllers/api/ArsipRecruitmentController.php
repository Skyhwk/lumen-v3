<?php
namespace App\Http\Controllers\api;

use App\Models\DataKandidat;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Date;
use App\Models\Recruitment;
use Validator;
use Carbon\Carbon;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\DB;


class ArsipRecruitmentController extends Controller
{
    public function index(Request $request)
    {
        $searchYear = $request->year;
        $db = isset($searchYear) ? date('Y', strtotime($searchYear)) : $this->db;

        $data = DataKandidat::select(
                'recruitment.*',
                'cabang.nama_cabang as nama_cabang',
                'posision.nama_jabatan as name_posisi',
                'a.nama_lengkap as user_interview',
                'b.nama_lengkap as approveinterviewhrd',
                'c.nama_lengkap as approvekandidat',
                'd.kepercayaan_diri as kepercayaan_diri',
                'd.pengetahuan_perusahaan as pengetahuan_perusahaan',
                'd.kemampuan_komunikasi as kemampuan_komunikasi',
                'd.pengetahuan_jobs as pengetahuan_jobs',
                'd.antusias_perusahaan as antusias_perusahaan',
                'd.motivasi_kerja as motivasi_kerja',
                'd.kesimpulan as kesimpulan',
                'd.catatan as catatan',
                'd.nama_hrd as nama_hrd',
                'e.nama_user as nama_user',
                'e.user_competensi as user_competensi',
                'e.kesimpulan_teknis as kesimpulan_teknis',
                'e.catatan as catatan_user',
                'f.nama_lengkap as approveinterviewuser',
                'g.gaji_pokok as gaji_pokok',
                'g.tunjangan as tunjangan',
                'h.status_karyawan as status_karyawan',
                'h.tgl_mulai_kerja as tgl_mulai_kerja',
                'h.tgl_berakhir_kontrak as tgl_berakhir_kontrak',
                'h.cost_center as cost_center',
                'h.grade as grade'
            )
            ->leftjoin('master_jabatan as posision', 'recruitment.bagian_di_lamar', '=', 'posision.id')
            ->leftjoin('master_cabang as cabang', 'recruitment.id_cabang', '=', 'cabang.id')
            ->leftjoin('master_karyawan as a', 'recruitment.user_interview_by', '=', 'a.nama_lengkap')
            ->leftjoin('master_karyawan as b', 'recruitment.approve_interview_hrd_by', '=', 'b.nama_lengkap')
            ->leftjoin('master_karyawan as c', 'recruitment.approve_kandidat_by', '=', 'c.nama_lengkap')
            ->leftjoin('review_recruitment as d', 'recruitment.id_review_recruitment', '=', 'd.id')
            ->leftjoin('review_user as e', 'recruitment.id_review_user', '=', 'e.id')
            ->leftjoin('master_karyawan as f', 'recruitment.approve_interview_user_by', '=', 'f.nama_lengkap')
            ->leftjoin('offering_salary as g', 'recruitment.id_salary', '=', 'g.id')
            ->leftjoin('master_karyawan as h', 'recruitment.approve_offering_salary_by', '=', 'h.nama_lengkap')
            ->whereIn('recruitment.id_cabang', $this->privilageCabang)
            ->where('recruitment.is_active', true)
            ->where('recruitment.flag', true);
        if (isset($searchYear))
            $data->whereYear('recruitment.created_at', $searchYear ?? date('Y'));
        $data->get();

        return Datatables::of($data)->make(true);
    }
}