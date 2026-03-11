<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\{DataKandidat, MasterKaryawan, ReviewRecruitment, MasterCabang};
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use PHPMailer\PHPMailer\Exception;
use Yajra\Datatables\Datatables;
use App\Services\GenerateMessageHRD;
use App\Services\GenerateMessageWhatsapp;
use App\Services\SendWhatsapp;
use App\Services\SendEmail;
use Illuminate\Support\Facades\DB;


class InterviewHRDController extends Controller
{
    function konversiHari($hariInggris)
    {
        $hari = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu'
        ];

        return $hari[$hariInggris];
    }

    function encrypt($string, $key)
    {
        $cipher = "AES-256-CBC";
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext = openssl_encrypt($string, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext, $key, true);
        return base64_encode($iv . $hmac . $ciphertext);
    }

    function approveInterviewHRDConnection($data, $mark = true)
    {
        $searchYear = isset($data->search) ? date('Y', strtotime($data->search)) : date('Y');
        $datas = DataKandidat::select(
            'recruitment.*',
            'cabang.nama_cabang as nama_cabang',
            'posision.nama_jabatan as nama_jabatan',
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
            'e.catatan as catatan_user'
        )
            ->leftjoin('master_jabatan as posision', 'recruitment.bagian_di_lamar', '=', 'posision.id')
            ->leftjoin('master_cabang as cabang', 'recruitment.id_cabang', '=', 'cabang.id')
            ->leftjoin('master_karyawan as a', 'recruitment.user_interview_by', '=', 'a.nama_lengkap')
            ->leftjoin('master_karyawan as b', 'recruitment.approve_interview_hrd_by', '=', 'b.nama_lengkap')
            ->leftjoin('master_karyawan as c', 'recruitment.approve_kandidat_by', '=', 'c.nama_lengkap')
            ->leftjoin('review_recruitment as d', 'recruitment.id_review_recruitment', '=', 'd.id')
            ->leftjoin('review_user as e', 'recruitment.id_review_user', '=', 'e.id')
            ->where('recruitment.id', $data->id_kandidat)
            ->where('recruitment.is_active', true)
            // ->whereYear('recruitment.created_at', $searchYear)
            ->first();

        if ($mark) {
            $clienInetrnal = json_decode($data->id_interviewUser);
            $dataClient = MasterKaryawan::where('id', $clienInetrnal[0])
                ->first();

        }
        $result = (object) [
            'data' => $datas,
            'dataClient' => $dataClient ?? null,
        ];

        return $result;
    }

    function rejectInterviewHRDConnection($data)
    {
        $searchYear = isset($data->search) ? date('Y', strtotime($data->search)) : date('Y');
        $result = DataKandidat::select(
            'recruitment.*',
            'cabang.nama_cabang as nama_cabang',
            'posision.nama_jabatan as nama_jabatan',
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
            'e.catatan as catatan_user'
        )
            ->leftjoin('master_jabatan as posision', 'recruitment.bagian_di_lamar', '=', 'posision.id')
            ->leftjoin('master_cabang as cabang', 'recruitment.id_cabang', '=', 'cabang.id')
            ->leftjoin('master_karyawan as a', 'recruitment.user_interview_by', '=', 'a.nama_lengkap')
            ->leftjoin('master_karyawan as b', 'recruitment.approve_interview_hrd_by', '=', 'b.nama_lengkap')
            ->leftjoin('master_karyawan as c', 'recruitment.approve_kandidat_by', '=', 'c.nama_lengkap')
            ->leftjoin('review_recruitment as d', 'recruitment.id_review_recruitment', '=', 'd.id')
            ->leftjoin('review_user as e', 'recruitment.id_review_user', '=', 'e.id')
            ->where('recruitment.id', $data->id_kandidat)
            ->where('recruitment.is_active', true)
            // ->whereYear('recruitment.created_at', $searchYear)
            ->first();

        return $result;
    }

    // Tested - Clear
    public function index(Request $request)
    {
        try {
            $data = DataKandidat::select(
                'recruitment.*',
                'cabang.nama_cabang as nama_cabang',
                'posision.nama_jabatan as nama_jabatan',
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
                'e.catatan as catatan_user'
            )
                ->leftjoin('master_jabatan as posision', 'recruitment.bagian_di_lamar', '=', 'posision.id')
                ->leftjoin('master_cabang as cabang', 'recruitment.id_cabang', '=', 'cabang.id')
                ->leftjoin('master_karyawan as a', 'recruitment.user_interview_by', '=', 'a.nama_lengkap')
                ->leftjoin('master_karyawan as b', 'recruitment.approve_interview_hrd_by', '=', 'b.nama_lengkap')
                ->leftjoin('master_karyawan as c', 'recruitment.approve_kandidat_by', '=', 'c.nama_lengkap')
                ->leftjoin('review_recruitment as d', 'recruitment.id_review_recruitment', '=', 'd.id')
                ->leftjoin('review_user as e', 'recruitment.id_review_user', '=', 'e.id')
                ->whereIn('recruitment.id_cabang', $this->privilageCabang)
                ->where('recruitment.is_active', true)
                ->where('recruitment.flag', 0)
                ->whereYear('recruitment.created_at', $request->year)
                ->where(function ($query) {
                    $query->where('recruitment.status', '=', 'APPROVE INTERVIEW HRD')
                        ->orWhere('recruitment.status', '=', 'INTERVIEW HRD')
                        ->orWhere('recruitment.status', '=', 'INPUT REVIEW HRD')
                        ->orWhere('recruitment.status', '=', 'APPROVE IBU BOS');
                })
                // ->whereYear('recruitment.created_at', $searchYear)
                ->distinct();

            return Datatables::of($data)->make(true);
        } catch (\Exception $th) {
            return $th;
        }
    }

    // Tested - Clear
    public function kalender(Request $request)
    {
        $searchDate = explode("-", $request->search);
        $searchMonth = intval($searchDate[1]);

        $data = DataKandidat::select(
            'recruitment.*',
            'cabang.nama_cabang as nama_cabang',
            'posision.nama_jabatan as nama_jabatan',
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
            'e.catatan as catatan_user'
        )
            ->leftjoin('master_jabatan as posision', 'recruitment.bagian_di_lamar', '=', 'posision.id')
            ->leftjoin('master_cabang as cabang', 'recruitment.id_cabang', '=', 'cabang.id')
            ->leftjoin('master_karyawan as a', 'recruitment.user_interview_by', '=', 'a.nama_lengkap')
            ->leftjoin('master_karyawan as b', 'recruitment.approve_interview_hrd_by', '=', 'b.nama_lengkap')
            ->leftjoin('master_karyawan as c', 'recruitment.approve_kandidat_by', '=', 'c.nama_lengkap')
            ->leftjoin('review_recruitment as d', 'recruitment.id_review_recruitment', '=', 'd.id')
            ->leftjoin('review_user as e', 'recruitment.id_review_user', '=', 'e.id')
            ->whereIn('recruitment.id_cabang', $this->privilageCabang)
            ->where('recruitment.is_active', true)
            ->where('recruitment.flag', 0)
            ->where(function ($query) {
                $query->where('recruitment.status', '=', 'APPROVE INTERVIEW HRD')
                    ->orWhere('recruitment.status', '=', 'INTERVIEW HRD')
                    ->orWhere('recruitment.status', '=', 'INPUT REVIEW HRD')
                    ->orWhere('recruitment.status', '=', 'APPROVE IBU BOS');
            })
            ->whereMonth('recruitment.created_at', $searchMonth ?? Carbon::now()->month)
            ->distinct()
            ->get();

        $check = DataKandidat::select(
            'recruitment.*',
            'cabang.nama_cabang as nama_cabang',
            'posision.nama_jabatan as nama_jabatan',
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
            'e.catatan as catatan_user'
        )
            ->leftjoin('master_jabatan as posision', 'recruitment.bagian_di_lamar', '=', 'posision.id')
            ->leftjoin('master_cabang as cabang', 'recruitment.id_cabang', '=', 'cabang.id')
            ->leftjoin('master_karyawan as a', 'recruitment.user_interview_by', '=', 'a.nama_lengkap')
            ->leftjoin('master_karyawan as b', 'recruitment.approve_interview_hrd_by', '=', 'b.nama_lengkap')
            ->leftjoin('master_karyawan as c', 'recruitment.approve_kandidat_by', '=', 'c.nama_lengkap')
            ->leftjoin('review_recruitment as d', 'recruitment.id_review_recruitment', '=', 'd.id')
            ->leftjoin('review_user as e', 'recruitment.id_review_user', '=', 'e.id')
            ->whereIn('recruitment.id_cabang', $this->privilageCabang)
            ->where('recruitment.is_active', true)
            ->where('recruitment.flag', 0)
            ->where('recruitment.status', 'APPROVE INTERVIEW HRD')
            ->whereNotNull('recruitment.keep_interview_user')
            ->distinct()
            ->get();

        if (!empty($check)) {
            foreach ($check as $key => $value) {
                if ($value->keep_interview_user == date('Y-m-d')) {

                    $approve = $value->id . "|" . $value->created_at . "|Approve Ibu Bos|" . env('PUBLIC_TOKEN');
                    $reject = $value->id . "|" . $value->created_at . "|Reject Ibu Bos|" . env('PUBLIC_TOKEN');
                    $keep = $value->id . "|" . $value->created_at . "|Keep Ibu Bos|" . env('PUBLIC_TOKEN');
                    $key = 'skyhwk12';

                    $approveencrypt = self::encrypt($approve, $key);
                    $rejectencrypt = self::encrypt($reject, $key);
                    $keepencrypt = self::encrypt($keep, $key);

                    $linkapprove = env('RECRUITMENT_API') . "/thankapprove/" . str_replace("/", "_", $approveencrypt);
                    $linkreject = env('RECRUITMENT_API') . "/thankreject/" . str_replace("/", "_", $rejectencrypt);
                    $keephold = env('RECRUITMENT_API') . "/keephold/" . str_replace("/", "_", $keepencrypt);

                    try {
                        $link_btn = (object) [
                            'approve' => $linkapprove,
                            'reject' => $linkreject,
                            'keep' => $keephold,
                        ];

                        $bodi = GenerateMessageHRD::bodyEmailKeepApproveKandidat($value, $link_btn, 'Ibu Boss');

                        $email = SendEmail::where('to', env('EMAIL_DIREKTUR_IBU'))
                            ->where('subject', 'Kandidat Interview User')
                            ->where('body', $bodi)
                            ->where('karyawan', $this->karyawan)
                            ->noReply()
                            ->send();
                    } catch (Exception $e) {
                        return response()->json([
                            'message' => 'Message could not be sent.'
                        ], 401);
                    }
                }
            }
        }
        return Datatables::of($data)->make(true);
    }

    // Tested - Clear
    public function showDataDetail(Request $request)
    {
        $searchYear = isset($request->search) ? date('Y', strtotime($request->search)) : date('Y');
        $data = DataKandidat::leftJoin('master_karyawan as a', 'recruitment.approve_kandidat_by', '=', 'a.nama_lengkap')
            ->leftJoin('master_jabatan as posision', 'recruitment.bagian_di_lamar', '=', 'posision.id')
            ->leftJoin('review_recruitment', 'recruitment.id_review_recruitment', '=', 'review_recruitment.id')
            ->leftJoin('master_karyawan as c', 'review_recruitment.nama_hrd', '=', 'c.nama_lengkap')
            ->leftJoin('master_cabang as d', 'recruitment.id_cabang', '=', 'd.id')
            ->select(
                'recruitment.nama_lengkap as nama_kandidat',
                'a.nama_lengkap as approve_kandidat_by',
                'posision.nama_jabatan as bagian_di_lamar',
                'recruitment.id as id_recruitment',
                'recruitment.nama_panggilan',
                'recruitment.email',
                'recruitment.tempat_lahir',
                'recruitment.umur',
                'recruitment.gender',
                'recruitment.agama',
                'recruitment.no_hp',
                'recruitment.status_nikah',
                'recruitment.nik_ktp',
                'recruitment.alamat_ktp',
                'recruitment.alamat_domisili',
                'recruitment.posisi_di_lamar',
                'recruitment.bpjs_kesehatan',
                'recruitment.bpjs_ketenagakerjaan',
                'recruitment.referensi',
                'recruitment.tanggal_lahir',
                'recruitment.pendidikan',
                'recruitment.pengalaman_kerja',
                'recruitment.skill',
                'recruitment.skill_bahasa',
                'recruitment.organisasi',
                'recruitment.sertifikat',
                'recruitment.salary_user',
                'recruitment.id_cabang',
                'recruitment.kursus',
                'recruitment.minat',
                'recruitment.orang_dalam',
                'recruitment.shio',
                'recruitment.elemen',
                'recruitment.created_at',
                'recruitment.foto_selfie',
                'recruitment.approve_kandidat_at',
                'c.nama_lengkap as nama_hrd_review',
                'd.nama_cabang',
                'review_recruitment.kepercayaan_diri',
                'review_recruitment.pengetahuan_perusahaan',
                'review_recruitment.kemampuan_komunikasi',
                'review_recruitment.pengetahuan_jobs',
                'review_recruitment.antusias_perusahaan',
                'review_recruitment.motivasi_kerja',
                'review_recruitment.catatan',
            )
            ->where('recruitment.id', '=', $request->id)
            ->where('recruitment.is_active', '=', true)
            // ->whereYear('recruitment.created_at', $searchYear)
            ->first();

        $user = MasterKaryawan::where('id', $this->user_id)->first();

        return response()->json([
            'data' => $data,
            'id_hrd_approve' => $user->id,
            'nama_hrd_approve' => $user->nama_lengkap,
        ], 200);
    }

    // Tested - Clear
    public function insertReview(Request $request)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

        if ($request->id_review != '') {
            $data = ReviewRecruitment::where('id', $request->id_review)->first();
            $pesan = 'Berhasil update data review kandidat.!';
        } else {
            $data = new ReviewRecruitment;
            $pesan = 'Berhasil menambahkan data review kandidat.!';
        }

        $data->nama_hrd = $this->karyawan;
        $data->kepercayaan_diri = ($request->kepercayaan_diri != '') ? $request->kepercayaan_diri : null;
        $data->pengetahuan_perusahaan = ($request->pengetahuan_perusahaan != '') ? $request->pengetahuan_perusahaan : null;
        $data->kemampuan_komunikasi = ($request->kemampuan_komunikasi != '') ? $request->kemampuan_komunikasi : null;
        $data->pengetahuan_jobs = ($request->pengetahuan_jobs != '') ? $request->pengetahuan_jobs : null;
        $data->antusias_perusahaan = ($request->antusias_perusahaan != '') ? $request->antusias_perusahaan : null;
        $data->motivasi_kerja = ($request->motivasi_kerja != '') ? $request->motivasi_kerja : null;
        $data->kesimpulan = ($request->kesimpulan != '') ? $request->kesimpulan : null;
        $data->catatan = ($request->catatan != '') ? $request->catatan : null;
        $data->is_active = true;
        $data->created_at = $timestamp;
        $data->created_by = $this->karyawan;

        $data->save();

        $data2 = DataKandidat::where('id', $request->id_recruitment)->first();
        $data2->id_review_recruitment = $data->id;
        $data2->status = 'INPUT REVIEW HRD';
        $data2->save();

        return response()->json(["message" => $pesan]);
    }

    // Tested - Clear
    // action approve
    public function sendIbuBoss(Request $request)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

        $conn = self::approveInterviewHRDConnection($request, false);
        $data = $conn->data;

        $approve = $data->id . "|" . $data->created_at . "|Approve Ibu Bos|" . env('PUBLIC_TOKEN');
        $reject = $data->id . "|" . $data->created_at . "|Reject Ibu Bos|" . env('PUBLIC_TOKEN');
        $keep = $data->id . "|" . $data->created_at . "|Keep Ibu Bos|" . env('PUBLIC_TOKEN');
        $key = 'skyhwk12';

        $approveencrypt = self::encrypt($approve, $key);
        $rejectencrypt = self::encrypt($reject, $key);
        $keepencrypt = self::encrypt($keep, $key);

        $linkapprove = env('RECRUITMENT_API') . "/thankapprove/" . str_replace("/", "_", $approveencrypt);
        $linkreject = env('RECRUITMENT_API') . "/thankreject/" . str_replace("/", "_", $rejectencrypt);
        $keephold = env('RECRUITMENT_API') . "/keephold/" . str_replace("/", "_", $keepencrypt);
        DB::beginTransaction();
        try {
            $link_btn = (object) [
                'approve' => $linkapprove,
                'reject' => $linkreject,
                'keep' => $keephold,
            ];

            $bodi = GenerateMessageHRD::bodyEmailKeepApproveKandidat($data, $link_btn, 'Ibu Boss');
            $email = SendEmail::where('to', env('EMAIL_DIREKTUR_IBU'))
                ->where('subject', 'Kandidat Interview User')
                ->where('bcc', ['dedi@intilab.com'])
                ->where('body', $bodi)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();

            if ($email) {
                DataKandidat::where('id', $request->id_kandidat)->update([
                    'status' => 'APPROVE INTERVIEW HRD',
                    'approve_interview_hrd_by' => $this->karyawan,
                    'approve_interview_hrd_at' => $timestamp,
                ]);

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil melakukan approve data recruitment.!',
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Message could not be sent.'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    // Tested - Clear
    // button approve
    public function approveIbuBoss(Request $request)
    {
        $conn = self::approveInterviewHRDConnection($request, false);
        $data = $conn->data;

        if ($data->status == 'REJECT IBU BOS' || $data->status == 'APPROVE IBU BOS') {
            return response()->json([
                'message' => 'Kandidat sudah di approve.'
            ], 401);
        } else {
            DB::beginTransaction();
            try {
                $dataArray = (object) [
                    'nama_lengkap' => $data->nama_lengkap,
                    'posisi_di_lamar' => $data->posisi_di_lamar,
                    'nama_jabatan' => $data->nama_jabatan,
                ];

                $bodi = GenerateMessageHRD::bodyEmailApproveIbuBoss($dataArray);

                $email = SendEmail::where('to', env('EMAIL_DIREKTUR_IBU'))
                    ->where('subject', 'Approve Kandidat Interview HRD')
                    ->where('bcc', ['dedi@intilab.com'])
                    ->where('body', $bodi)
                    ->where('karyawan', $this->karyawan)
                    ->noReply()
                    ->send();

                if ($email) {
                    DataKandidat::where('id', $request->id_kandidat)->update(['status' => 'APPROVE IBU BOS',]);

                    DB::commit();
                    return response()->json([
                        'message' => 'Berhasil melakukan approve data recruitment.!',
                    ], 200);
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Message could not be sent notif ibu bos.'
                    ], 401);
                }
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json([
                    'error' => $e->getMessage(),
                    'line' => $e->getLine()
                ], 500);
            }
        }
    }

    // Tested - Clear
    public function inputTanggalUser(Request $request)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

        $conn = self::approveInterviewHRDConnection($request);
        $data = $conn->data;
        $dataClient = $conn->dataClient;

        $cabang = MasterCabang::where('id', $data->id_cabang)->first();
        $alamat = $cabang->alamat_cabang;

        $date = Carbon::parse($request->tgl_interview_user);
        $dayName = $date->format('l');
        $hariIndonesia = self::konversiHari($dayName);
        $tglInter = $date->format('d-m-Y');

        DB::beginTransaction();
        try {
            $dataArray = (object) [
                'nama_lengkap' => $data->nama_lengkap,
                'posisi_di_lamar' => $data->posisi_di_lamar,
                'nama_jabatan' => $data->nama_jabatan,
                'hariIndonesia' => $hariIndonesia,
                'tglInter' => $tglInter,
                'jam_interview_user' => $request->jam_interview_user,
                'jenis_interview_user' => $request->jenis_interview_user,
                'link_gmeet_user' => $request->link_gmeet_user,
                'alamat' => $alamat
            ];
            
            //  ============================== BEGIN EMAIL KANDIDAT ===================
            $bodi = GenerateMessageHRD::bodyEmailInterviewCalon($dataArray);
            $email = SendEmail::where('to', $data->email)
                ->where('subject', 'Interview User')
                ->where('body', $bodi)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();
            //  ============================== END EMAIL KANDIDAT ===================

            // ============================== BEGIN WHATSAPP KANDIDAT ===================
            $message = new GenerateMessageWhatsapp($dataArray);
            $message = $message->PassedHRD();

            $Send = new SendWhatsapp($data->no_hp, $message);
            $SendWhatsapp = $Send->send();
            // ============================== END WHATSAPP KANDIDAT ===================

            // =============================== BEGIN EMAIL USER =======================
            $bodi2 = GenerateMessageHRD::bodyEmailInterviewUser($data);
            $email2 = SendEmail::where('to', $dataClient->email)
                ->where('subject', 'Interview User')
                ->where('body', $bodi2)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();
            // =============================== END EMAIL USER =======================
            if ($email && $email2) {
                $inter_user = json_decode($request->id_interviewUser);
                if ($request->jenis_interview_user == 'Online') {
                    $gmeet = $request->link_gmeet_user;
                } else {
                    $gmeet = null;
                }

                DataKandidat::where('id', $request->id_kandidat)
                    ->update([
                        'status' => 'INTERVIEW USER',
                        'user_interview_by' => $inter_user[1],
                        'tgl_interview_user' => $request->tgl_interview_user,
                        'jam_interview_user' => $request->jam_interview_user,
                        'approve_interview_hrd_by' => $this->karyawan,
                        'approve_interview_hrd_at' => $timestamp,
                        'jenis_interview_user' => $request->jenis_interview_user,
                        'link_gmeet_user' => $gmeet,
                    ]);

                if ($SendWhatsapp) {
                    DB::commit();
                    return response()->json([
                        'message' => 'Berhasil melakukan approve data recruitment.!',
                    ], 200);
                } else {
                    DB::commit();
                    return response()->json([
                        'message' => 'Berhasil melakukan approve data recruitment akan tetapi whatsapp tidak dapat dikirimkan.!',
                    ], 200);
                }
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Message could not be sent.'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    // Tested - Clear
    public function bypassOffering(Request $request)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

        $conn = self::approveInterviewHRDConnection($request, false);
        $data = $conn->data;

        $subject = '';
        $innermessage = '';
        if ($request->status == 'Bypass-Offering') {
            $subject = 'Bypass to Offering Sallary';
            $innermessage = 'otomatis ke <strong>Offering Salary</strong>';
        } else if ($request->status == 'Bypass-Ibu') {
            $subject = 'Bypass System';
            $innermessage = 'otomatis';
        }

        DB::beginTransaction();
        try {
            $bodi = GenerateMessageHRD::bodyEmailBypassOffering($data, null, false, $innermessage);

            $email = SendEmail::where('to', env('EMAIL_DIREKTUR_IBU'))
                ->where('subject', $subject)
                ->where('body', $bodi)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();

            if ($email) {
                if ($request->status == 'Bypass-Offering') {
                    $data = DataKandidat::where('id', $request->id_kandidat)
                        ->update([
                            'approve_interview_user_by' => $this->karyawan,
                            'approve_interview_user_at' => $timestamp,
                            'user_interview_by' => $this->karyawan,
                            'status' => 'OFFERING SALLARY'
                        ]);
                } else if ($request->status == 'Bypass-Ibu') {
                    $data = DataKandidat::where('id', $request->id_kandidat)
                        ->update([
                            'status' => 'APPROVE IBU BOS'
                        ]);
                }

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil melakukan bypass kandidat'
                ], 200);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Message could not be sent.'
            ], 401);
        }
    }

    // Tested - Clear
    public function reschedule(Request $request)
    {
        $data = self::rejectInterviewHRDConnection($request);
        $cabang = MasterCabang::where('id', $data->id_cabang)->first();
        $alamat = $cabang->alamat_cabang;

        $date = Carbon::parse($request->tgl_interview);
        $dayName = $date->format('l');
        $hariIndonesia = self::konversiHari($dayName);
        $tglInter = $date->format('d-m-Y');

        DB::beginTransaction();
        try {
            $dataArray = (object) [
                'nama_lengkap' => $data->nama_lengkap,
                'posisi_di_lamar' => $data->posisi_di_lamar,
                'nama_jabatan' => $data->nama_jabatan,
                'hariIndonesia' => $hariIndonesia,
                'tglInter' => $tglInter,
                'jam_interview' => $request->jam_interview,
                'jenis_interview_hrd' => $request->jenis_interview_hrd,
                'link_gmeet_hrd' => $request->link_gmeet_hrd,
                'alamat' => $alamat,
            ];

            $bodi = GenerateMessageHRD::bodyEmailReschedule($dataArray);

            $email = SendEmail::where('to', $data->email)
                ->where('subject', 'Reschedule Interview HRD')
                ->where('body', $bodi)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();

            // ============================== BEGIN WHATSAPP KANDIDAT ===================
            $message = new GenerateMessageWhatsapp($dataArray);
            $message = $message->RescheduleHRD();

            $Send = new SendWhatsapp($data->no_hp, $message);
            $SendWhatsapp = $Send->send();
            // ============================== END WHATSAPP KANDIDAT ===================

            if ($email) {
                if ($request->jenis_interview_hrd == 'Online') {
                    $gmeet = $request->link_gmeet_hrd;
                } else {
                    $gmeet = null;
                }
                DataKandidat::where('id', $request->id_kandidat)
                    ->update([
                        'tgl_interview' => $request->tgl_interview,
                        'jam_interview' => $request->jam_interview,
                        'jenis_interview_hrd' => $request->jenis_interview_hrd,
                        'link_gmeet_hrd' => $gmeet,
                    ]);

                if ($SendWhatsapp) {
                    DB::commit();
                    return response()->json([
                        'message' => 'Berhasil melakukan Reschedule Interview HRD.!',
                    ], 200);
                } else {
                    DB::commit();
                    return response()->json([
                        'message' => 'Berhasil melakukan Reschedule Interview HRD akan tetapi whatsapp tidak dapat dikirimkan.!',
                    ], 200);
                }
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Message could not be sent.'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    // Tested - Clear
    public function rejectHRD(Request $request)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

        $data = self::rejectInterviewHRDConnection($request);

        DB::beginTransaction();
        try {
            $dataArray = (object) [
                'nama_lengkap' => $data->nama_lengkap,
                'posisi_di_lamar' => $data->posisi_di_lamar,
                'nama_jabatan' => $data->nama_jabatan,
            ];

            $bodi = GenerateMessageHRD::bodyEmailRejectHRD($dataArray);

            $email = SendEmail::where('to', $data->email)
                ->where('subject', 'Lamaran Ditolak')
                ->where('body', $bodi)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();

            // ============================== BEGIN WHATSAPP KANDIDAT ===================
            $message = new GenerateMessageWhatsapp($dataArray);
            $message = $message->RejectedHRD();

            $Send = new SendWhatsapp($data->no_hp, $message);
            $SendWhatsapp = $Send->send();
            // ============================== END WHATSAPP KANDIDAT ===================

            if ($email) {
                DataKandidat::where('id', $request->id_kandidat)
                    ->update([
                        'status' => 'REJECT HRD',
                        'reject_interview_hrd_by' => $this->karyawan,
                        'reject_interview_hrd_at' => $timestamp,
                        'is_active' => false,
                    ]);

                if ($SendWhatsapp) {
                    DB::commit();
                    return response()->json([
                        'message' => 'Berhasil melakukan reject data recruitment.!',
                    ], 200);
                } else {
                    DB::commit();
                    return response()->json([
                        'message' => 'Berhasil melakukan reject data recruitment akan tetapi whatsapp tidak dapat dikirimkan.!',
                    ], 200);
                }
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Message could not be sent.'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    // Tested - Clear
    public function rejectIbuBoss(Request $request)
    {
        $data = self::rejectInterviewHRDConnection($request);
        if ($data == null) {
            return response()->json([
                'message' => 'Kandidat sudah di reject.'
            ], 401);
        }
        if ($data->status == 'REJECT IBU BOS' || $data->status == 'APPROVE IBU BOS' || $data->status == 'REJECT HRD') {
            return response()->json([
                'message' => 'Kandidat sudah di reject.'
            ], 401);
        } else {
            $cabang = MasterCabang::where('id', $data->id_cabang)->first();
            $alamat = $cabang->alamat_cabang;

            function konversiHari($hariInggris)
            {
                $hari = [
                    'Monday' => 'Senin',
                    'Tuesday' => 'Selasa',
                    'Wednesday' => 'Rabu',
                    'Thursday' => 'Kamis',
                    'Friday' => 'Jumat',
                    'Saturday' => 'Sabtu',
                    'Sunday' => 'Minggu'
                ];

                return $hari[$hariInggris];
            }

            $date = Carbon::parse($request->tgl_interview);
            $dayName = $date->format('l');
            $hariIndonesia = self::konversiHari($dayName);
            $tglInter = $date->format('d-m-Y');

            DB::beginTransaction();
            try {
                // ============================== BEGIN EMAIL BU BOSS ===================
                $dataArray = (object) [
                    'nama_lengkap' => $data->nama_lengkap,
                    'posisi_di_lamar' => $data->posisi_di_lamar,
                    'nama_jabatan' => $data->nama_jabatan,
                    'hariIndonesia' => $hariIndonesia,
                    'tglInter' => $tglInter,
                    'jam_interview_user' => $data->jam_interview_user,
                    'alamat' => $alamat
                ];

                $bodi1 = GenerateMessageHRD::bodyEmailRejectIbuBoss($dataArray);

                $email1 = SendEmail::where('to', env('EMAIL_DIREKTUR_IBU'))
                    ->where('subject', 'Reject Kandidat Interview USER')
                    ->where('body', $bodi1)
                    ->where('karyawan', $this->karyawan)
                    ->noReply()
                    ->send();
                // ============================== END EMAIL BU BOSS ===================

                // ============================== BEGIN EMAIL KANDIDAT ===================
                $bodi = GenerateMessageHRD::bodyEmailRejectHRD($dataArray);

                $email = SendEmail::where('to', $data->email)
                    ->where('subject', 'Lamaran Ditolak')
                    ->where('body', $bodi)
                    ->where('karyawan', $this->karyawan)
                    ->noReply()
                    ->send();
                // ============================== END EMAIL KANDIDAT ===================

                // ============================== BEGIN WHATSAPP KANDIDAT ===================
                $message = new GenerateMessageWhatsapp($dataArray);
                $message = $message->RejectedHRD();

                $Send = new SendWhatsapp($data->no_hp, $message);
                $SendWhatsapp = $Send->send();
                // ============================== END WHATSAPP KANDIDAT ===================

                if ($email && $email1) {
                    DataKandidat::where('id', $request->id_kandidat)
                        ->update([
                            'status' => 'REJECT IBU BOS',
                            'is_active' => false,
                        ]);

                    if ($SendWhatsapp) {
                        DB::commit();
                        return response()->json([
                            'message' => 'Berhasil melakukan reject data recruitment.!',
                        ], 200);
                    } else {
                        DB::commit();
                        return response()->json([
                            'message' => 'Berhasil melakukan reject data recruitment akan tetapi whatsapp tidak dapat dikirimkan.!',
                        ], 200);
                    }
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Message could not be sent notif ibu bos.'
                    ], 401);
                }
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Terjadi kesalahan server!',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ], 500);
            }
        }
    }

    // Tested - Clear
    public function keepIbuBoss(Request $request)
    {
        $data = self::rejectInterviewHRDConnection($request);

        $cabang = MasterCabang::where('id', $data->id_cabang)->first();
        $alamat = $cabang->alamat_cabang;

        $nowday = Carbon::now();
        $hplus7 = $nowday->addDays(7);

        $date = Carbon::parse($request->tgl_interview);
        $dayName = $date->format('l');
        $hariIndonesia = self::konversiHari($dayName);
        $tglInter = $date->format('d-m-Y');
        DB::beginTransaction();
        try {
            $dataArray = (object) [
                'nama_lengkap' => $data->nama_lengkap,
                'posisi_di_lamar' => $data->posisi_di_lamar,
                'nama_jabatan' => $data->nama_jabatan,
                'hariIndonesia' => $hariIndonesia,
                'tglInter' => $tglInter,
                'jam_interview_user' => $data->jam_interview_user,
                'alamat' => $alamat
            ];

            $bodi = GenerateMessageHRD::bodyEmailKeepIbuBoss($dataArray);

            $email = SendEmail::where('to', env('EMAIL_DIREKTUR_IBU'))
                ->where('subject', 'Hold +7 Hari Kandidat')
                ->where('body', $bodi)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();

            if ($email) {
                DataKandidat::where('id', $request->id_kandidat)
                    ->update([
                        'keep_interview_user' => $hplus7
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil melakukan hold data recruitment.!',
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal melakukan hold data recruitment.!'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan server!',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}