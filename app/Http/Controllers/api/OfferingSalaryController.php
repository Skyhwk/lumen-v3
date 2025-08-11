<?php
namespace App\Http\Controllers\api;

use App\Models\{
    KeahlianBahasaKaryawan,
    DataKandidat,
    PendidikanKaryawan,
    MasterKaryawan,
    MedicalCheckup,
    User,
    OfferingSalary,
    SertifikatKaryawan,
    PengalamanKerjaKaryawan,
    KeahlianKaryawan,
    MasterCabang
};
use App\Services\GenerateMessageHRD;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Services\GenerateMessageWhatsapp;
use Illuminate\Database\QueryException;
use App\Services\SendWhatsapp;
use App\Services\SendEmail;

class OfferingSalaryController extends Controller
{
    function encrypt($string, $key)
    {
        $cipher = "AES-256-CBC";
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext = openssl_encrypt($string, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext, $key, true);
        return base64_encode($iv . $hmac . $ciphertext);
    }

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

    function offeringSalaryConnection($data)
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
            ->leftJoin('master_karyawan as f', 'recruitment.approve_interview_user_by', '=', 'f.nama_lengkap')
            ->leftJoin('offering_salary as g', 'recruitment.id_salary', '=', 'g.id')
            ->leftJoin('master_karyawan as h', 'recruitment.approve_offering_salary_by', '=', 'h.nama_lengkap')
            ->where('recruitment.id', $data->id_kandidat)
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
            ->leftJoin('master_karyawan as f', 'recruitment.approve_interview_user_by', '=', 'f.nama_lengkap')
            ->leftJoin('offering_salary as g', 'recruitment.id_salary', '=', 'g.id')
            ->leftJoin('master_karyawan as h', 'recruitment.approve_offering_salary_by', '=', 'h.nama_lengkap')
            ->whereIn('recruitment.id_cabang', $this->privilageCabang)
            ->where('recruitment.is_active', true)
            ->where('recruitment.flag', 0)
            ->where(function ($query) {
                $query->where('recruitment.status', '=', 'OFFERING SALLARY')
                    ->orWhere('recruitment.status', '=', 'APPROVE OFFERING SALARY HRD')
                    ->orWhere('recruitment.status', '=', 'PROBATION');
            })
            ->whereYear('recruitment.approve_interview_user_at', Carbon::now()->year)
            ->distinct();
        // ->get()
        // ->unique('id');

        return Datatables::of($data)->make(true);
    }

    // Tested - Clear
    public function insertOfferingSalary(Request $request)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

        $data = DataKandidat::where('id', $request->id_kandidat)
            ->where('is_active', true)
            ->first();

        $cek_salary = OfferingSalary::where('id', $data->id_salary)
            ->first();

        if ($cek_salary) {
            $cek_salary->gaji_pokok = $request->gaji_pokok;
            $cek_salary->tunjangan = $request->tunjangan;
            $cek_salary->updated_at = $timestamp;
            $cek_salary->updated_by = $this->karyawan;
            $cek_salary->save();

            return response()->json([
                'message' => 'Berhasil mengubah Offering Salary',
            ], 200);
        } else {
            $id = OfferingSalary::insertGetId([
                'id_recruitment' => $data->id,
                'gaji_pokok' => $request->gaji_pokok,
                'tunjangan' => $request->tunjangan,
                'created_at' => $timestamp,
                'created_by' => $this->karyawan
            ]);

            $data->id_salary = $id;
            $data->save();

            return response()->json([
                'message' => 'Berhasil menambah Offering Salary',
            ], 200);
        }
    }

    // Tested - Clear
    public function sendBapakBoss(Request $request)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

        $data = self::offeringSalaryConnection($request);

        $approve = $data->id . "|" . $data->created_at . "|Approve Bapak Bos|" . env('PUBLIC_TOKEN');
        $reject = $data->id . "|" . $data->created_at . "|Reject Bapak Bos|" . env('PUBLIC_TOKEN');
        $key = 'skyhwk12';

        $approveencrypt = self::encrypt($approve, $key);
        $rejectencrypt = self::encrypt($reject, $key);

        $linkapprove = env('RECRUITMENT_API') . "/thankapproveSalary/" . $approveencrypt;
        $linkreject = env('RECRUITMENT_API') . "/thankrejectSalary/" . $rejectencrypt;

        DB::beginTransaction();
        try {
            $link_btn = (object) [
                'approve' => $linkapprove,
                'reject' => $linkreject,
            ];

            $bodi = GenerateMessageHRD::bodyEmailKeepApproveKandidat($data, $link_btn, 'Bapak Boss');

            $email = SendEmail::where('to', env('EMAIL_DIREKTUR_BAPAK'))
                ->where('subject', 'Kandidat Offering Salary')
                ->where('body', $bodi)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();

            if ($email) {
                DataKandidat::where('id', $request->id_kandidat)
                    ->update([
                        'status' => 'APPROVE OFFERING SALARY HRD',
                        'tgl_kerja' => $request->tgl_kerja,
                        'approve_offering_salary_by' => $this->karyawan,
                        'approve_offering_salary_at' => $timestamp,
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil melakukan approve data offering salary.!',
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
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    // Tested - Clear
    public function approveBapakBoss(Request $request)
    {
        $data = self::offeringSalaryConnection($request);
        if ($data->status == 'REJECT BAPAK BOS' || $data->status == 'PROBATION') {
            return response()->json([
                'message' => 'Kandidat sudah di approve.'
            ], 401);
        } else {
            $cabang = MasterCabang::where('id', $data->id_cabang)->first();
            $alamat = $cabang->alamat_cabang;
            $date = Carbon::parse($data->tgl_kerja);
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
                    'alamat' => $alamat,
                ];
                // ============================== BEGIN EMAIL PAK BOSS ===================
                $bodi = GenerateMessageHRD::bodyEmailApproveBapakBoss($dataArray);
                $email = SendEmail::where('to', env('EMAIL_DIREKTUR_BAPAK'))
                    ->where('subject', 'Approve Kandidat Offering Salary')
                    ->where('body', $bodi)
                    ->where('karyawan', $this->karyawan)
                    ->noReply()
                    ->send();
                // ============================== END EMAIL PAK BOSS ===================

                // ============================== BEGIN EMAIL KANDIDAT ===================
                $bodi1 = GenerateMessageHRD::bodyEmailApproveOSCalon($dataArray);
                $email1 = SendEmail::where('to', $data->email)
                    ->where('subject', 'Pemberitahuan masuk kerja')
                    ->where('body', $bodi1)
                    ->where('karyawan', $this->karyawan)
                    ->noReply()
                    ->send();
                // ============================== END EMAIL KANDIDAT ===================

                // ============================== BEGIN WHATSAPP KANDIDAT ===================
                $message = new GenerateMessageWhatsapp($dataArray);
                $message = $message->PassedOS();

                $Send = new SendWhatsapp($data->no_hp, $message);
                $SendWhatsapp = $Send->send();
                // ============================== END WHATSAPP KANDIDAT ===================

                if ($email && $email1) {
                    DataKandidat::where('id', $request->id_kandidat)
                        ->update([
                            'status' => 'PROBATION'
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
                        'message' => 'Message could not be sent notif ibu bos.'
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

    // Tested - Clear
    public function rejectOfferingSalary(Request $request)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $data = self::offeringSalaryConnection($request);

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
            $message = $message->RejectedHRD(); // Menggunakan method RejectedHRD() karna bodi messagesnya sama persis

            $Send = new SendWhatsapp($data->no_hp, $message);
            $SendWhatsapp = $Send->send();
            // ============================== END WHATSAPP KANDIDAT ===================

            if ($email) {
                DataKandidat::where('id', $request->id_kandidat)
                    ->update([
                        'status' => 'REJECT OFFERING SALARY HRD',
                        'reject_offering_salary_by' => $this->karyawan,
                        'reject_offering_salary_at' => $timestamp,
                        'keter_reject' => $request->catatan_reject,
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

    // Tested - Clear
    public function rejectBapakBoss(Request $request)
    {
        $data = self::offeringSalaryConnection($request);
        if ($data->status == 'REJECT BAPAK BOS' || $data->status == 'PROBATION') {
            return response()->json([
                'message' => 'Kandidat sudah di reject.'
            ], 401);
        } else {
            DB::beginTransaction();
            try {
                $dataArray = (object) [
                    'nama_lengkap' => $data->nama_lengkap,
                    'posisi_di_lamar' => $data->posisi_di_lamar,
                    'nama_jabatan' => $data->nama_jabatan,
                ];
                // ============================== BEGIN EMAIL KANDIDAT ===================
                $bodi = GenerateMessageHRD::bodyEmailRejectBapakBoss($dataArray);
                $email = SendEmail::where('to', env('EMAIL_DIREKTUR_BAPAK'))
                    ->where('subject', 'Reject Kandidat Offering Salary')
                    ->where('body', $bodi)
                    ->where('karyawan', $this->karyawan)
                    ->noReply()
                    ->send();

                // ============================== END EMAIL PAK BOSS ===================

                // ============================== BEGIN EMAIL KANDIDAT ===================
                $bodi1 = GenerateMessageHRD::bodyEmailRejectKandidat($dataArray);
                $email1 = SendEmail::where('to', $data->email)
                    ->where('subject', 'Lamaran Ditolak')
                    ->where('body', $bodi1)
                    ->where('karyawan', $this->karyawan)
                    ->noReply()
                    ->send();
                // ============================== END EMAIL KANDIDAT ===================

                // ============================== BEGIN WHATSAPP KANDIDAT ===================
                $message = new GenerateMessageWhatsapp($dataArray);
                $message = $message->RejectedHRD(); // menggunakan method rejected hrd karna bodi messages sama

                $Send = new SendWhatsapp($data->no_hp, $message);
                $SendWhatsapp = $Send->send();
                // ============================== END WHATSAPP KANDIDAT ===================

                if ($email && $email1) {
                    DataKandidat::where('id', $request->id_kandidat)
                        ->update([
                            'status' => 'REJECT BAPAK BOS',
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

    public function inputMasterKaryawan(Request $request)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        DB::beginTransaction();
        try {
            // dd($request->all());
            $karyawan = new MasterKaryawan;
            $karyawan->where('nik_ktp', $request->personal['nik_ktp'])->first();
            if (!$karyawan) {
                $karyawan = new MasterKaryawan;
                $karyawan->created_by = $request->personal['id_kandidat'] ? null : $this->karyawan;
                $karyawan->created_at = $request->personal['id_kandidat'] ? null : $timestamp;
            } else {
                $karyawan->updated_by = $request->personal['id_kandidat'] ? $this->karyawan : null;
                $karyawan->updated_at = $request->personal['id_kandidat'] ? $timestamp : null;
            }

            if ($request->hasFile('personal.image')) {
                $profilePicture = $request->file('personal.image');
                $imageName = $request->personal['nik_ktp'] . '_' . str_replace(' ', '_', $request->personal['nama_lengkap']) . '.' . $profilePicture->getClientOriginalExtension();
                $destinationPath = public_path('/Foto_Karyawan');
                $profilePicture->move($destinationPath, $imageName);
            }

            /* Update menambahkan Trim by 565 : 2025-05-08
            $karyawan->nama_lengkap = $request->personal['nama_lengkap'] != '' ? $request->personal['nama_lengkap'] : $karyawan->nama_lengkap;*/
            $karyawan->nama_lengkap = $request->personal['nama_lengkap'] != '' ? trim($request->personal['nama_lengkap']) : trim($karyawan->nama_lengkap);
            $karyawan->nik_ktp = $request->personal['nik_ktp'] != '' ? $request->personal['nik_ktp'] : $karyawan->nik_ktp;
            $karyawan->nama_panggilan = $request->personal['salutation'] != '' ? $request->personal['salutation'] : $karyawan->nama_panggilan;
            $karyawan->kebangsaan = $request->personal['nationality'] != '' ? $request->personal['nationality'] : $karyawan->kebangsaan;
            $karyawan->tempat_lahir = $request->personal['birth_place'] != '' ? $request->personal['birth_place'] : $karyawan->tempat_lahir;
            $karyawan->tanggal_lahir = $request->personal['date_birth'] != '' ? $request->personal['date_birth'] : $karyawan->tanggal_lahir;
            $karyawan->jenis_kelamin = $request->personal['gender'] != '' ? $request->personal['gender'] : $karyawan->jenis_kelamin;
            $karyawan->agama = $request->personal['religion'] != '' ? $request->personal['religion'] : $karyawan->agama;
            $karyawan->status_pernikahan = $request->personal['marital_status'] != '' ? $request->personal['marital_status'] : $karyawan->status_pernikahan;
            $karyawan->tempat_nikah = $request->personal['marital_place'] != '' ? $request->personal['marital_place'] : $karyawan->tempat_nikah;
            $karyawan->tgl_nikah = $request->personal['marital_date'] != '' ? $request->personal['marital_date'] : $karyawan->tgl_nikah;
            $karyawan->shio = $request->personal['shio'] != '' ? $request->personal['shio'] : $karyawan->shio;
            $karyawan->elemen = $request->personal['elemen'] != '' ? $request->personal['elemen'] : $karyawan->elemen;

            $karyawan->nik_karyawan = $request->employee['nik'] != '' ? $request->employee['nik'] : $karyawan->nik_karyawan;
            $karyawan->email = $request->employee['email'] != '' ? $request->employee['email'] : $karyawan->email;
            $karyawan->email_pribadi = $request->employee['email_pribadi'] != '' ? $request->employee['email_pribadi'] : $karyawan->email_pribadi;
            $karyawan->id_cabang = $request->employee['branch'] != '' ? $request->employee['branch'] : $karyawan->id_cabang;
            $karyawan->status_karyawan = $request->employee['estatus'] != '' ? $request->employee['estatus'] : $karyawan->status_karyawan;
            $karyawan->tgl_mulai_kerja = $request->employee['sdate'] != '' ? $request->employee['sdate'] : $karyawan->tgl_mulai_kerja;
            $karyawan->tgl_berakhir_kontrak = $request->employee['ecdate'] != '' ? $request->employee['ecdate'] : $karyawan->tgl_berakhir_kontrak;
            $karyawan->id_jabatan = $request->employee['position'] != '' ? $request->employee['position'] : $karyawan->id_jabatan;
            $karyawan->kategori_grade = $request->employee['gradec'] != '' ? $request->employee['gradec'] : $karyawan->kategori_grade;
            $karyawan->grade = $request->employee['grade'] != '' ? $request->employee['grade'] : $karyawan->grade;
            $karyawan->status_pekerjaan = $request->employee['jstatus'] != '' ? $request->employee['jstatus'] : $karyawan->status_pekerjaan;
            $karyawan->id_department = $request->employee['departement'] != '' ? $request->employee['departement'] : $karyawan->id_department;
            $karyawan->atasan_langsung = json_encode($request->employee['dsupervisor'] != '' ? $request->employee['dsupervisor'] : $karyawan->atasan_langsung);
            $karyawan->cost_center = $request->employee['ccenter'] != '' ? $request->employee['ccenter'] : $karyawan->cost_center;
            $karyawan->tgl_pra_pensiun = $request->employee['ppdate'] != '' ? $request->employee['ppdate'] : $karyawan->tgl_pra_pensiun;
            $karyawan->tgl_pensiun = $request->employee['pdate'] != '' ? $request->employee['pdate'] : $karyawan->tgl_pensiun;

            $karyawan->alamat = $request->contact['address'] != '' ? $request->contact['address'] : $karyawan->alamat;
            $karyawan->negara = $request->contact['country'] != '' ? $request->contact['country'] : $karyawan->negara;
            $karyawan->provinsi = $request->contact['province'] != '' ? $request->contact['province'] : $karyawan->provinsi;
            $karyawan->kota = $request->contact['city'] != '' ? $request->contact['city'] : $karyawan->kota;
            $karyawan->no_telpon = $request->contact['phone'] != '' ? $request->contact['phone'] : $karyawan->no_telpon;
            $karyawan->kode_pos = $request->contact['postal_code'] != '' ? $request->contact['postal_code'] : $karyawan->kode_pos;
            $karyawan->privilage_cabang = json_encode($request->access['priv_branch'] ?? $karyawan->privilage_cabang);
            $karyawan->image = $imageName ?? $karyawan->image;
            $karyawan->save();

            $medis = MedicalCheckup::where('id', $request->medical['id'] ?? null)->first();
            if (!$medis) {
                $medis = new MedicalCheckup;
            }
            $medis->karyawan_id = $karyawan->id;
            $medis->tinggi_badan = $request->medical['tinggi_badan'] != '' ? $request->medical['tinggi_badan'] : $medis->tinggi_badan;
            $medis->berat_badan = $request->medical['berat_badan'] != '' ? $request->medical['berat_badan'] : $medis->berat_badan;
            $medis->rate_mata = $request->medical['rate_mata'] != '' ? $request->medical['rate_mata'] : $medis->rate_mata;
            $medis->golongan_darah = $request->medical['golongan_darah'] != '' ? $request->medical['golongan_darah'] : $medis->golongan_darah;
            $medis->penyakit_bawaan_lahir = $request->medical['penyakit_bawaan_lahir'] != '' ? $request->medical['penyakit_bawaan_lahir'] : $medis->penyakit_bawaan_lahir;
            $medis->penyakit_kronis = $request->medical['penyakit_kronis'] != '' ? $request->medical['penyakit_kronis'] : $medis->penyakit_kronis;
            $medis->riwayat_kecelakaan = $request->medical['riwayat_kecelakaan'] != '' ? $request->medical['riwayat_kecelakaan'] : $medis->riwayat_kecelakaan;
            $medis->keterangan_mata = $request->medical['keterangan_mata'] != 'true' ? $request->medical['keterangan_mata'] : $medis->keterangan_mata;
            $medis->save();
            // dd($medis);
            if ($request->has('education')) {
                foreach ($request->education as $education) {
                    $pendidikan = PendidikanKaryawan::where('id', $education['id'] ?? null)->first();
                    if (!$pendidikan) {
                        $pendidikan = new PendidikanKaryawan;
                        $pendidikan->created_by = $this->karyawan;
                        $pendidikan->created_at = $timestamp;
                    } else {
                        $pendidikan->updated_by = $this->karyawan;
                        $pendidikan->updated_at = $timestamp;
                    }
                    $pendidikan->karyawan_id = $karyawan->id;
                    $pendidikan->institusi = $education['institusi'] != '' ? $education['institusi'] : $pendidikan->institusi;
                    $pendidikan->jenjang = $education['jenjang'] != '' ? $education['jenjang'] : $pendidikan->jenjang;
                    $pendidikan->jurusan = $education['jurusan'] != '' ? $education['jurusan'] : $pendidikan->jurusan;
                    $pendidikan->tahun_masuk = $education['tahun_masuk'] != '' ? $education['tahun_masuk'] : $pendidikan->tahun_masuk;
                    $pendidikan->tahun_lulus = $education['tahun_lulus'] != '' ? $education['tahun_lulus'] : $pendidikan->tahun_lulus;
                    $pendidikan->kota = $education['kota'] != '' ? $education['kota'] : $pendidikan->kota;
                    $pendidikan->save();
                }
            }

            if ($request->has('certificate')) {
                foreach ($request->certificate as $certificate) {
                    $sertifikat = SertifikatKaryawan::where('id', $certificate['id'] ?? null)->first();
                    if (!$sertifikat) {
                        $sertifikat = new SertifikatKaryawan;
                        $sertifikat->created_by = $this->karyawan;
                        $sertifikat->created_at = $timestamp;
                    } else {
                        $sertifikat->updated_by = $this->karyawan;
                        $sertifikat->updated_at = $timestamp;
                    }
                    $sertifikat->karyawan_id = $karyawan->id;
                    $sertifikat->nama_sertifikat = $certificate['nama_sertifikat'] != '' ? $certificate['nama_sertifikat'] : $sertifikat->nama_sertifikat;
                    $sertifikat->tipe_sertifikat = $certificate['tipe_sertifikat'] != '' ? $certificate['tipe_sertifikat'] : $sertifikat->tipe_sertifikat;
                    $sertifikat->nomor_sertifikat = $certificate['nomor_sertifikat'] != '' ? $certificate['nomor_sertifikat'] : $sertifikat->nomor_sertifikat;
                    $sertifikat->deskripsi_sertifikat = $certificate['deskripsi_sertifikat'] != '' ? $certificate['deskripsi_sertifikat'] : $sertifikat->deskripsi_sertifikat;
                    $sertifikat->tgl_sertifikat = $certificate['tgl_sertifikat'] != '' ? $certificate['tgl_sertifikat'] : $sertifikat->tgl_sertifikat;
                    $sertifikat->tgl_exp_sertifikat = $certificate['tgl_exp_sertifikat'] != '' ? $certificate['tgl_exp_sertifikat'] : $sertifikat->tgl_exp_sertifikat;
                    $sertifikat->save();
                }
            }

            if ($request->has('experience')) {
                // dd($request->all());
                foreach ($request->experience as $experience) {
                    $pengalaman = PengalamanKerjaKaryawan::where('id', $experience['id'] ?? null)->first();
                    if (!$pengalaman) {
                        $pengalaman = new PengalamanKerjaKaryawan;
                        $pengalaman->created_by = $this->karyawan;
                        $pengalaman->created_at = $timestamp;
                    } else {
                        $pengalaman->updated_by = $this->karyawan;
                        $pengalaman->updated_at = $timestamp;
                    }
                    // dd($karyawan);
                    $pengalaman->karyawan_id = $karyawan->id;
                    $pengalaman->nama_perusahaan = $experience['nama_perusahaan'] != '' ? $experience['nama_perusahaan'] : $pengalaman->nama_perusahaan;
                    $pengalaman->lokasi_perusahaan = $experience['lokasi_perusahaan'] != '' ? $experience['lokasi_perusahaan'] : $pengalaman->lokasi_perusahaan;
                    $pengalaman->posisi_kerja = $experience['posisi_kerja'] != '' ? $experience['posisi_kerja'] : $pengalaman->posisi_kerja;
                    $pengalaman->tgl_mulai_kerja = $experience['tgl_mulai_kerja'] != '' ? $experience['tgl_mulai_kerja'] : $pengalaman->tgl_mulai_kerja;
                    $pengalaman->tgl_berakhir_kerja = $experience['tgl_berakhir_kerja'] != '' ? $experience['tgl_berakhir_kerja'] : $pengalaman->tgl_berakhir_kerja;
                    $pengalaman->alasan_keluar = $experience['alasan_keluar'] != '' ? $experience['alasan_keluar'] : $pengalaman->alasan_keluar;
                    // dd($pengalaman);
                    $pengalaman->save();

                }
            }

            if ($request->has('skill')) {
                foreach ($request->skill as $skill) {
                    $keahlian = KeahlianKaryawan::where('id', $skill['id'] ?? null)->first();
                    if (!$keahlian) {
                        $keahlian = new KeahlianKaryawan;
                    }
                    $keahlian->karyawan_id = $karyawan->id;
                    $keahlian->keahlian = $skill['keahlian'] != '' ? $skill['keahlian'] : $keahlian->keahlian;
                    $keahlian->rate = $skill['rate'] != '' ? $skill['rate'] : $keahlian->rate;
                    $keahlian->save();
                }
            }

            if ($request->has('languages')) {
                foreach ($request->languages as $language) {
                    $bahasa = KeahlianBahasaKaryawan::where('id', $language['id'] ?? null)->first();
                    if (!$bahasa) {
                        $bahasa = new KeahlianBahasaKaryawan;
                    }
                    $bahasa->karyawan_id = $karyawan->id;
                    $bahasa->bahasa = $language['bahasa'] != '' ? $language['bahasa'] : $bahasa->bahasa;
                    $bahasa->baca = $language['baca'] != '' ? $language['baca'] : $bahasa->baca;
                    $bahasa->tulis = $language['tulis'] != '' ? $language['tulis'] : $bahasa->tulis;
                    $bahasa->dengar = $language['dengar'] != '' ? $language['dengar'] : $bahasa->dengar;
                    $bahasa->bicara = $language['bicara'] != '' ? $language['bicara'] : $bahasa->bicara;
                    $bahasa->save();
                }
            }

            // ================================= BEGIN ACCESS =================================
            $user = User::where('email', $request->employee['email'])->first();
            if (!$user) {
                $user = new User;
                $user->created_by = $this->karyawan;
                $user->created_at = $timestamp;
            } else {
                $user->updated_by = $this->karyawan;
                $user->updated_at = $timestamp;
            }
            $user->username = $request->access['username'];
            $user->email = $request->employee['email'];

            if ($request->access['password'] != $user->password && $request->access['password'])
                $user->password = Hash::make($request->access['password']);
            $user->save();
            // ================================= END ACCESS =================================
            $karyawan = MasterKaryawan::where('id', $karyawan->id)->first();
            $karyawan->user_id = $user->id;
            $karyawan->save();
            // dd($karyawan);
            $kandidat = DataKandidat::where('nik_ktp', $request->personal['nik_ktp'])->where('is_active', true)->first();
            $kandidat->status = 'PROBATION';
            $kandidat->flag = true;
            $kandidat->save();

            DB::commit();
            return response()->json([
                'message' => $request->personal['id_kandidat'] ? 'Karyawan updated successfully' : 'Karyawan created successfully'
            ], 200);
        } catch (QueryException $qe) {
            DB::rollBack();
            if ($qe->getCode() == 23000) {
                return response()->json([
                    'message' => 'Terdapat duplikasi pada email atau username.!'
                ], 500);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terdapat kesalahan ' . $e->getMessage()
            ], 500);
        }
    }
}