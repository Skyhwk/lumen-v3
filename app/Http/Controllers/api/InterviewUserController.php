<?php
namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\DataKandidat;
use App\Models\ReviewUser;
use App\Models\MasterCabang;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\DB;
use App\Services\GenerateMessageHRD;
use App\Services\GenerateMessageWhatsapp;
use App\Services\SendWhatsapp;
use App\Services\SendEmail;


class InterviewUserController extends Controller
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

    function rejectInterviewUserConnection($data)
    {
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
            ->where('recruitment.id', $data->id)
            ->where('recruitment.is_active', true)
            ->first();

        return $datas;
    }

    // Tested - Clear
    public function index(Request $request)
    {
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
            ->where('recruitment.status', "INTERVIEW USER")
            ->whereYear('recruitment.created_at', $request->year)
            ->distinct();

        return Datatables::of($data)->make(true);
    }

    // Tested - Clear
    public function kalender(Request $request)
    {
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
            ->where('recruitment.status', $request->status)
            ->distinct();

        return Datatables::of($data)->make(true);
    }

    // Tested - Clear
    public function approveInterviewUser(Request $request)
    {
        DB::beginTransaction();
        try {
            $timestamp = Carbon::now()->format('Y-m-d H:i:s');

            $data = DataKandidat::where('id', $request->id)
                ->where('is_active', true)
                ->where('status', "INTERVIEW USER")
                ->first();

            if ($data) {
                $data->status = 'OFFERING SALLARY';
                $data->approve_interview_user_by = $this->karyawan;
                $data->approve_interview_user_at = $timestamp;
                $data->save();

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil melakukan approve data interview user.!',
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal melakukan approve data interview user.!',
                ], 400);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    // Tested - Clear
    public function insertReviewUser(Request $request)
    {
        DB::beginTransaction();
        try {
            $timestamp = Carbon::now()->format('Y-m-d H:i:s');

            if ($request->id != null) {
                $data = [
                    "user_competensi" => $request->user_competensi,
                    "kesimpulan_teknis" => $request->kesimpulan_teknis,
                    "catatan" => $request->catatan_user
                ];
                ReviewUser::where('id', $request->id)
                    ->update($data);
            } else {
                $data = [
                    "id_user" => $this->user_id,
                    "nama_user" => $this->karyawan,
                    "user_competensi" => $request->user_competensi,
                    "kesimpulan_teknis" => $request->kesimpulan_teknis,
                    "catatan" => $request->catatan_user,
                    "is_active" => true,
                    "created_at" => $timestamp,
                    "created_by" => $this->karyawan
                ];

                $id = ReviewUser::insertGetId($data);

                DataKandidat::where('id', $request->id_kandidat)->update(['id_review_user' => $id]);
            }
            DB::commit();
            return response()->json([
                "message" => "Berhasil menyimpan Review User.!"
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    // Tested - Clear
    public function reschedule(Request $request)
    {
        $data = self::rejectInterviewUserConnection($request);
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
                'alamat' => $alamat,
            ];

            $bodi = GenerateMessageHRD::bodyEmailRescheduleUser($dataArray);
            $email = SendEmail::where('to', $data->email)
                ->where('subject', 'Reschedule Interview USER')
                ->where('body', $bodi)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();

            // ============================== BEGIN WHATSAPP KANDIDAT ===================
            $message = new GenerateMessageWhatsapp($dataArray);
            $message = $message->RescheduleUser();

            $Send = new SendWhatsapp($data->no_hp, $message);
            $SendWhatsapp = $Send->send();
            // ============================== END WHATSAPP KANDIDAT ===================

            if ($email) {
                DataKandidat::where('id', $request->id)
                    ->update([
                        'tgl_interview_user' => $request->tgl_interview_user,
                        'jam_interview_user' => $request->jam_interview_user,
                        'jenis_interview_user' => $request->jenis_interview_user,
                        'link_gmeet_user' => ($request->jenis_interview_user == 'Online') ? $request->link_gmeet_user : null,
                    ]);

                if ($SendWhatsapp) {
                    DB::commit();
                    return response()->json([
                        'message' => 'Berhasil melakukan Reschedule Interview User.!',
                    ], 200);
                } else {
                    DB::commit();
                    return response()->json([
                        'message' => 'Berhasil melakukan Reschedule Interview User akan tetapi whatsapp tidak dapat dikirimkan.!',
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
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    // Tested - Clear
    public function rejectInterviewUser(Request $request)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

        $data = self::rejectInterviewUserConnection($request);

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
            $message = $message->RejectedHRD(); // pakai reject hrd karna sama persis

            $Send = new SendWhatsapp($data->no_hp, $message);
            $SendWhatsapp = $Send->send();
            // ============================== END WHATSAPP KANDIDAT ===================

            if ($email) {
                DataKandidat::where('id', $request->id)
                    ->update([
                        'status' => 'REJECT INTERVIEW USER',
                        'reject_interview_user_by' => $this->karyawan,
                        'reject_interview_user_at' => $timestamp,
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
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }
}