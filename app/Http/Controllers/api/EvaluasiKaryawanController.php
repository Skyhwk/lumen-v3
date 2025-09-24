<?php

namespace App\Http\Controllers\api;

use App\Models\{MasterKaryawan, Psikotes, PapiRule, DiscPattern, DiscResult, DiscRules, SoalPsikotes, PapiResults};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Datatables;

class EvaluasiKaryawanController extends Controller
{
    public function authKaryawan(Request $request)
    {
        if (strstr($request->email_karyawan, "intilab.com") !== FALSE) {
            $cek = MasterKaryawan::join('master_jabatan', 'master_karyawan.id_jabatan', '=', 'master_jabatan.id')
                ->select(
                    'master_karyawan.id',
                    'master_karyawan.atasan_langsung',
                    'master_karyawan.nama_lengkap',
                    'master_karyawan.nik_karyawan',
                    'master_jabatan.nama_jabatan',
                    'master_karyawan.grade',
                    'maser_karyawan.id_department',
                    'email'
                )
                ->where('master_karyawan.is_active', true)
                ->where('master_karyawan.email', $request->email_karyawan)
                ->where('master_karyawan.nik_karyawan', $request->nik_karyawan)
                ->first();

            if ($cek != null) {

                $check_soal = Psikotes::join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
                    ->select('soal_psikotes.kategori_soal')
                    ->groupBy('soal_psikotes.kategori_soal')
                    ->where('psikotes.id_karyawan', $cek->id)
                    ->get();

                // return response()->json($cek->id);
                $sudah_mengerjakan = [];
                foreach ($check_soal as $key => $val) {
                    array_push($sudah_mengerjakan, $val->kategori_soal);
                }
                if ($request->kategori_soal == '') {
                    $array_soal = ["IST", "KOSTICK PAPI", "DISC"];
                    $soal_send = array_diff($array_soal, $sudah_mengerjakan);
                    $soal = SoalPsikotes::whereIn('kategori_soal', $soal_send)->offset(0)->limit(10)->get();

                    return response()->json([
                        'message' => $cek,
                        'tipe_soal' => $soal_send,
                        'soal' => $soal
                    ], 200);
                } else {
                    $soal = SoalPsikotes::where('kategori_soal', $request->kategori_soal)->offset(0)->limit(10)->get();
                    $setatus = [];
                    $datauser = [];
                    if ($request->kategori_soal == 'EMPLOYEE SATISFACTION') {
                        if (in_array($request->kategori_soal, $sudah_mengerjakan)) {
                            array_push($setatus, 'sudah mengerjakan');
                        } else {
                            array_push($setatus, $request->kategori_soal);
                        }
                    } else if ($request->kategori_soal == 'MANAGEMENT EVALUATION') {
                        if ($cek->grade == 'MANAGER') {
                            $cek3 = MasterKaryawan::where('grade', 'MANAGER')
                                ->where('is_active', true)
                                ->where('id', '!=', $cek->id)
                                ->get();

                            foreach ($cek3 as $key => $val) {
                                $cek_ = Psikotes::where('id_evaluasi', $val->id)
                                    ->where('id_karyawan', $cek->id)
                                    ->first();
                                if (is_null($cek_)) {
                                    array_push($datauser, (object) [
                                        'id_evaluasi' => $val->id,
                                        'nama_evaluasi' => $val->nama_lengkap
                                    ]);
                                }
                            }
                            array_push($setatus, $request->kategori_soal);
                        } else {
                            array_push($setatus, 'bukan grade MANAGER');
                        }
                    } else if ($request->kategori_soal == 'EMPLOYEE EVALUATION') {
                        $atasan = '"' . $cek->id . '"';

                        // $idakses = [131,103,54,5,44,58,74,143,117,62,257,75,55,268,10,84,79,132,50,120,123,140,94,111,39,108,91,83,161,25,148,26,66,42,11,106,32,53,14,67,9,129,164,130,60,119,97,183,52,100,61,73,133,78,165,137,38,16,156,34,90,29,23,17,76,77,157,153,70,150,122,36,104,152,22,96,141,59,56,87,95,28,109,138,110,86,21,6,93,114,31,82,139,69,33,37,51,80,126,154,125,24,112,155,149,40,45,48,30,47,43,7,20];


                        if ($cek->grade == 'MANAGER') {
                            // $loop = [54,103,44,58,74,143,117,62,257,75,68,55,268,10,84,79,132,50,120,123,140,94,111,39,108,91,83,25,148,127,26,5,66,42,11,106,32,14,67,9,129,164,130,60,119,97,183,52,100,61,73,133,78,165,137,38,16,156,34,90,29,23,17,76,77,157,153,70,150,122,36,104,152,22,96,141,59,56,87,95,28,109,138,110,86,21,6,93,114,31,82,139,69,33,37,51,80,126,154,125,24,112,155,149,40,45,48,30,47,43,7,144, 20,161,53];
                            if ($cek->id == 15) {
                                // pak eko
                                $cek2 = MasterKaryawan::whereRaw("JSON_CONTAINS(atasan_langsung, '$atasan','$')")
                                    ->where('is_active', true)
                                    ->get();
                            } else if ($cek->id == 127) {
                                // pak eko
                                $cek2 = MasterKaryawan::whereRaw("JSON_CONTAINS(atasan_langsung, '$atasan','$')")
                                    ->where('is_active', true)
                                    ->get();
                            } else if ($cek->id == 13) {
                                // sucita
                                $cek2 = MasterKaryawan::whereIN('id_department', [19, 14])
                                    ->where('is_active', true)
                                    ->get();
                            } else if ($cek->id == 18) {
                                //ayu zeri
                                $cek2 = MasterKaryawan::whereIN('id_department', [6])
                                    ->where('is_active', true)
                                    ->get();
                            } else {
                                // semua staff dalam department
                                $cek2 = MasterKaryawan::where('id_department', $cek->id_department)
                                    ->where('id', '!=', $cek->id)
                                    ->where('is_active', true)
                                    ->get();
                            }
                        } else {
                            $cek2 = MasterKaryawan::whereRaw("JSON_CONTAINS(atasan_langsung, '$atasan','$')")
                                ->where('is_active', true)
                                ->get();
                        }

                        foreach ($cek2 as $key => $val) {
                            $cek_ = Psikotes::where('id_karyawan', $cek->id)
                                ->where('id_evaluasi', $val->id)
                                ->first();
                            if (is_null($cek_)) {
                                array_push($datauser, (object) [
                                    'id_evaluasi' => $val->id,
                                    'nama_evaluasi' => $val->nama_lengkap
                                ]);
                            }
                        }
                        array_push($setatus, $request->kategori_soal);
                    } else if ($request->kategori_soal == 'SATISFACTION OF LEADER') {
                        if (!empty($cek->atasan_langsung)) {
                            $dat = json_decode($cek->atasan_langsung);
                            if (!is_array($dat)) {
                                $user_atasan = array($cek->atasan_langsung);
                            } else {
                                $user_atasan = $dat;
                            }

                            $cek3 = MasterKaryawan::whereIn('id', $user_atasan)
                                ->get();

                            foreach ($cek3 as $key => $val) {
                                $cek_ = Psikotes::where('id_evaluasi', $val->id)
                                    ->where('id_karyawan', $cek->id)
                                    ->first();

                                if (is_null($cek_)) {
                                    array_push($datauser, (object) [
                                        'id_evaluasi' => $val->id,
                                        'nama_evaluasi' => $val->nama_lengkap
                                    ]);
                                }
                            }
                        }
                        array_push($setatus, $request->kategori_soal);
                    }
                    return response()->json([
                        'message' => $cek,
                        'tipe_soal' => $setatus,
                        'user_evaluasi' => $datauser,
                        'soal' => $soal
                    ], 200);
                }
            } else {
                return response()->json([
                    'message' => 'Data not Found!',
                ], 402);
            }

        } else {
            return response()->json([
                'message' => 'Tolong Masukan Email dengan domain @intilab.com',
            ], 401);
        }
    }

    public function writeKuisioner(Reqeust $request)
    {
        if (isset($request->jawaban) && $request->jawaban != null) {
            $id_evaluasi = null;
            if ($request->id_evaluasi != '') {
                $dat = explode("_", $request->id_evaluasi);
                $id_evaluasi = $dat[0];
            }
            try {
                foreach ($request->jawaban as $key => $value) {
                    $data = Psikotes::insert([
                        'periode' => $this->db,
                        'id_karyawan' => $request->id_karyawan,
                        'id_evaluasi' => $id_evaluasi,
                        'nama_karyawan' => $request->nama_lengkap,
                        'id_soal' => $request->id_soal[$key],
                        'jawaban' => $request->jawaban[$key],
                        'added_at' => DATE('Y-m-d H:i:s')
                    ]);
                }
                return response()->json([
                    'message' => 'Jawaban Berhasil disimpan.!',
                ], 200);
            } catch (Exeption $err) {
                $error_code = $err->getCode();
                return response()->json([
                    'message' => 'Error =' . $error_code
                ], 401);
            }
        } else if (isset($request->jawabanP) && $request->jawabanP != null) {
            try {
                $jawaban_detailP = [];
                $jawaban_detailK = [];
                foreach ($request->jawabanP as $key => $value) {
                    $jawaban = [];
                    array_push($jawaban, (object) [
                        'P' => $request->jawabanP[$key],
                        'K' => $request->jawabanK[$key],
                    ]);
                    array_push($jawaban_detailP, $request->jawabanP[$key]);
                    array_push($jawaban_detailK, $request->jawabanK[$key]);

                    $data = Psikotes::insert([
                        'periode' => $this->db,
                        'id_karyawan' => $request->id_karyawan,
                        'nama_karyawan' => $request->nama_lengkap,
                        'id_soal' => $request->id_soal[$key],
                        'jawaban' => json_encode($jawaban),
                        'added_at' => DATE('Y-m-d H:i:s')
                    ]);
                }

                $jawaban_fin = [
                    "P" => $jawaban_detailP,
                    "K" => $jawaban_detailK
                ];

                $fin = DiscResult::insert([
                    'id_user' => $request->id_user,
                    'nama_lengkap' => $request->nama_lengkap,
                    'jawaban' => json_encode($jawaban_fin)
                ]);

                return response()->json([
                    'message' => 'Jawaban Berhasil disimpan.!',
                ], 200);

            } catch (Exeption $err) {
                $error_code = $err->getCode();
                return response()->json([
                    'message' => 'Error =' . $error_code
                ], 401);
            }
        } else if (isset($request->jawaban_papikostik) && $request->jawaban_papikostik != null) {
            try {
                foreach ($request->jawaban_papikostik as $key => $value) {
                    $data = Psikotes::insert([
                        'periode' => $this->db,
                        'id_karyawan' => $request->id_karyawan,
                        'nama_karyawan' => $request->nama_lengkap,
                        'id_soal' => $request->id_soal[$key],
                        'jawaban' => $request->jawaban_papikostik[$key],
                        'added_at' => DATE('Y-m-d H:i:s')
                    ]);
                }

                $format = [];
                foreach ($request->jawaban_papikostik as $k => $v) {

                    if (!isset($format[$v])) {
                        $format[$v] = 0;
                    }
                    $format[$v] += 1;
                }

                $reformat = [];
                foreach ($format as $k => $v) {
                    $reformat[] = [
                        "id_karyawan" => $request->id_user,
                        "nama_karyawan" => $request->nama_lengkap,
                        "role_id" => $k,
                        "value" => $v
                    ];
                }

                $query = PapiResults::insert($reformat);

                return response()->json([
                    'message' => 'Jawaban Berhasil disimpan.!',
                ], 200);
            } catch (Exeption $err) {
                $error_code = $err->getCode();
                return response()->json([
                    'message' => 'Error =' . $error_code
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'Something Wrong.!'
            ], 401);
        }
    }

    public function indexEvaluasi(Request $request)
    {
        if ($request->periode != null) {
            if ($request->kategori == 'IST' || $request->kategori == 'DISC' || $request->kategori == 'KOSTICK PAPI') {
                $data = Psikotes::select(
                    'psikotes.id_karyawan',
                    'psikotes.nama_karyawan',
                    'soal_psikotes.kategori_soal',
                    \DB::raw('COUNT(soal_psikotes.kategori_soal) as total_soal'),
                    \DB::raw('SUM(CASE WHEN psikotes.jawaban = soal_psikotes.kunci_jawaban THEN 1 ELSE 0 END) as benar'),
                    \DB::raw('SUM(CASE WHEN psikotes.jawaban <> soal_psikotes.kunci_jawaban THEN 1 ELSE 0 END) as salah'),
                    \DB::raw('CASE WHEN COUNT(soal_psikotes.kategori_soal) > 0 THEN SUM(CASE WHEN psikotes.jawaban = soal_psikotes.kunci_jawaban THEN 1 ELSE 0 END) * 100 / COUNT(soal_psikotes.kategori_soal) ELSE 0 END as persentase')
                )
                    ->join('soal_psikotes', 'psikotes.id_soal', '=', 'soal_psikotes.id')
                    ->where('soal_psikotes.kategori_soal', $request->kategori)
                    ->groupBy('psikotes.id_karyawan', 'psikotes.nama_karyawan', 'soal_psikotes.kategori_soal')
                    ->get();

                return Datatables::of($data)->make(true);
            } else if ($request->kategori == 'EMPLOYEE EVALUATION') {
                $data = Psikotes::select(
                    'psikotes.id_evaluasi as id_karyawan',
                    'soal_psikotes.kategori_soal',
                    'master_karyawan.nama_lengkap as nama_karyawan',
                    \DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                    \DB::raw('CASE WHEN COUNT(psikotes.id_evaluasi) > 0 THEN ROUND(SUM(psikotes.jawaban) / (COUNT(psikotes.id_evaluasi) * 5) * 100, 2) ELSE 0 END as persentase'),
                    \DB::raw('COUNT(psikotes.id_evaluasi) as total_soal')
                )
                    ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
                    ->join('master_karyawan', 'master_karyawan.id', '=', 'psikotes.id_evaluasi')
                    ->where('soal_psikotes.kategori_soal', $request->kategori)
                    ->groupBy('psikotes.id_evaluasi', 'soal_psikotes.kategori_soal', 'master_karyawan.nama_lengkap')
                    ->get();

                return Datatables::of($data)->make(true);
            } else if ($request->kategori == 'SATISFACTION OF LEADER') {
                $data = Psikotes::select(
                    'psikotes.id_evaluasi as id_karyawan',
                    'soal_psikotes.kategori_soal',
                    'master_karyawan.nama_lengkap as nama_karyawan',
                    \DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                    \DB::raw('CASE WHEN COUNT(psikotes.id_evaluasi) > 0 THEN ROUND(SUM(psikotes.jawaban) / (COUNT(psikotes.id_evaluasi) * 4) * 100, 2) ELSE 0 END as persentase'),
                    \DB::raw('COUNT(psikotes.id_evaluasi) as total_soal')
                )
                    ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
                    ->join('master_karyawan', 'master_karyawan.id', '=', 'psikotes.id_evaluasi')
                    ->where('soal_psikotes.kategori_soal', $request->kategori)
                    ->groupBy('psikotes.id_evaluasi', 'soal_psikotes.kategori_soal', 'master_karyawan.nama_lengkap')
                    ->get();

                return Datatables::of($data)->make(true);
            } else if ($request->kategori == 'MANAGEMENT EVALUATION') {
                $data = Psikotes::select(
                    'psikotes.id_evaluasi as id_karyawan',
                    'soal_psikotes.kategori_soal',
                    'master_karyawan.nama_lengkap as nama_karyawan',
                    \DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                    \DB::raw('CASE WHEN COUNT(psikotes.id_evaluasi) > 0 THEN ROUND(SUM(psikotes.jawaban) / (COUNT(psikotes.id_evaluasi) * 5) * 100, 2) ELSE 0 END as persentase'),
                    \DB::raw('COUNT(psikotes.id_evaluasi) as total_soal')
                )
                    ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
                    ->join('master_karyawan', 'master_karyawan.id', '=', 'psikotes.id_evaluasi')
                    ->where('soal_psikotes.kategori_soal', $request->kategori)
                    ->groupBy('psikotes.id_evaluasi', 'soal_psikotes.kategori_soal', 'master_karyawan.nama_lengkap')
                    ->get();

                return Datatables::of($data)->make(true);
            } else if ($request->kategori == 'EMPLOYEE SATISFACTION') {
                $data = Psikotes::select(
                    'psikotes.id_karyawan as id_karyawan',
                    'soal_psikotes.kategori_soal',
                    'master_karyawan.nama_lengkap as nama_karyawan',
                    \DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                    \DB::raw('CASE WHEN COUNT(psikotes.id_karyawan) > 0 THEN ROUND(SUM(psikotes.jawaban) / (COUNT(psikotes.id_karyawan) * 5) * 100, 2) ELSE 0 END as persentase'),
                    \DB::raw('COUNT(psikotes.id_karyawan) as total_soal')
                )
                    ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
                    ->join('master_karyawan', 'master_karyawan.id', '=', 'psikotes.id_karyawan')
                    ->where('soal_psikotes.kategori_soal', $request->kategori)
                    ->groupBy('psikotes.id_karyawan', 'soal_psikotes.kategori_soal', 'master_karyawan.nama_lengkap')
                    ->get();

                return Datatables::of($data)->make(true);
            } else {
                return Datatables::of([])->make(true);
            }
        } else {
            return Datatables::of([])->make(true);
        }
    }

    public function detailEvaluasi(Request $request)
    {
        if ($request->periode != null && $request->id_karyawan != null) {
            if ($request->kategori != null && $request->id_karyawan != null) {
                if ($request->kategori == 'IST') {
                    $data = Psikotes::select(
                        'psikotes.nama_karyawan',
                        'soal_psikotes.kategori_soal',
                        'soal_psikotes.kategori',
                        \DB::raw('SUM(CASE WHEN psikotes.jawaban = soal_psikotes.kunci_jawaban THEN 1 ELSE 0 END) as benar'),
                        \DB::raw('SUM(CASE WHEN psikotes.jawaban <> soal_psikotes.kunci_jawaban THEN 1 ELSE 0 END) as salah'),
                        \DB::raw('SUM(CASE WHEN psikotes.jawaban = soal_psikotes.kunci_jawaban THEN 1 ELSE 0 END) * 100 / COUNT(soal_psikotes.kategori_soal) as persentase')
                    )
                        ->join('soal_psikotes', 'psikotes.id_soal', '=', 'soal_psikotes.id')
                        ->where('psikotes.id_karyawan', $request->id_karyawan)
                        ->where('soal_psikotes.kategori_soal', $request->kategori)
                        ->groupBy('psikotes.nama_karyawan', 'soal_psikotes.kategori_soal', 'soal_psikotes.kategori')
                        ->get();
                } else if ($request->kategori == 'EMPLOYEE EVALUATION') {
                    if ($request->mode == 'reverse') {
                        $data = Psikotes::select(
                            'soal_psikotes.pertanyaan',
                            'psikotes.nama_karyawan',
                            'psikotes.jawaban'
                        )
                            ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
                            ->where('soal_psikotes.kategori_soal', $request->kategori)
                            ->where('psikotes.id_karyawan', $request->id_karyawan)
                            ->where('psikotes.id_evaluasi', $request->id_evaluasi)
                            ->get();
                    } else {
                        $data = Psikotes::select(
                            'psikotes.id_karyawan',
                            'psikotes.id_evaluasi',
                            'master_karyawan.nama_lengkap',
                            \DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                            \DB::raw('(SUM(psikotes.jawaban) / (COUNT(psikotes.id_evaluasi) * 5) * 100) as nilai'),
                            \DB::raw('COUNT(psikotes.id_evaluasi) as jumlah_soal')
                        )
                            ->join('master_karyawan', 'master_karyawan.id', '=', 'psikotes.id_karyawan')
                            ->join('soal_psikotes', 'psikotes.id_soal', '=', 'soal_psikotes.id')
                            ->where('psikotes.id_evaluasi', $request->id_karyawan)
                            ->where('soal_psikotes.kategori_soal', 'EMPLOYEE EVALUATION')
                            ->groupBy('psikotes.id_karyawan')
                            ->get();
                    }
                } else if ($request->kategori == 'SATISFACTION OF LEADER') {
                    if ($request->mode == 'reverse') {
                        $data = Psikotes::select(
                            'soal_psikotes.pertanyaan',
                            'psikotes.nama_karyawan',
                            'psikotes.jawaban'
                        )
                            ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
                            ->where('psikotes.id_karyawan', $request->id_karyawan)
                            ->where('psikotes.id_evaluasi', $request->id_evaluasi)
                            ->where('soal_psikotes.kategori_soal', $request->kategori)
                            ->get();
                    } else {
                        $data = Psikotes::select(
                            'psikotes.id_karyawan',
                            'psikotes.id_evaluasi',
                            'master_karyawan.nama_lengkap',
                            DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                            DB::raw('(SUM(psikotes.jawaban) / (COUNT(psikotes.id_evaluasi) * 4) * 100) as nilai'),
                            DB::raw('COUNT(psikotes.id_evaluasi) as jumlah_soal')
                        )
                            ->join('master_karyawan', 'master_karyawan.id', '=', 'psikotes.id_karyawan')
                            ->join('soal_psikotes', 'psikotes.id_soal', '=', 'soal_psikotes.id')
                            ->where('psikotes.id_evaluasi', $request->id_karyawan)
                            ->where('soal_psikotes.kategori_soal', 'SATISFACTION OF LEADER')
                            ->groupBy('psikotes.id_karyawan')
                            ->get();
                    }
                } else if ($request->kategori == 'MANAGEMENT EVALUATION') {
                    if ($request->mode == 'reverse') {
                        $data = Psikotes::select(
                            'soal_psikotes.pertanyaan',
                            'psikotes.nama_karyawan',
                            'psikotes.jawaban'
                        )
                            ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
                            ->where('psikotes.id_karyawan', $request->id_karyawan)
                            ->where('psikotes.id_evaluasi', $request->id_evaluasi)
                            ->where('soal_psikotes.kategori_soal', $request->kategori)
                            ->get();
                    } else {
                        $data = Psikotes::select(
                            'psikotes.id_karyawan',
                            'psikotes.id_evaluasi',
                            'master_karyawan.nama_lengkap',
                            DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                            DB::raw('(SUM(psikotes.jawaban) / (COUNT(psikotes.id_evaluasi) * 5) * 100) as nilai'),
                            DB::raw('COUNT(psikotes.id_evaluasi) as jumlah_soal')
                        )
                            ->join('master_karyawan', 'master_karyawan.id', '=', 'psikotes.id_karyawan')
                            ->where('psikotes.id_evaluasi', $request->id_karyawan)
                            ->where('master_karyawan.grade', 'MANAGER')
                            ->groupBy('psikotes.id_karyawan')
                            ->get();
                    }
                } else {
                    $data = Psikotes::select(
                        'soal_psikotes.pertanyaan',
                        'psikotes.nama_karyawan',
                        'psikotes.jawaban'
                    )
                        ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
                        ->where('psikotes.id_karyawan', $request->id_karyawan)
                        ->where('soal_psikotes.kategori_soal', $request->kategori)
                        ->get();
                }
                return response()->json([
                    'data' => $data,
                    'message' => 'Data has been shown'
                ], 200);

            } else {
                return response()->json([
                    'data' => [],
                ], 200);
            }
        } else {
            return response()->json([
                'data' => [],
            ], 200);
        }
    }

    public function getKategoriSoal(Request $request)
    {
        $data = SoalPsikotes::select('kategori_soal')
            ->groupBy('kategori_soal')
            ->orderBy('kategori_soal', 'ASC')
            ->get();

        return response()->json([
            'message' => 'Kategori Soal has been shown',
            'data' => $data,
        ], 200);
    }

    public function detailPapikostik(Request $request)
    {
        $data = PapiResults::select(
            'papi_aspects.aspect',
            'papi_roles.code',
            'papi_roles.role',
            'papi_results.value',
            'papi_results.nama_karyawan',
            'papi_rules.interprestation'
        )
            ->join('papi_roles', 'papi_roles.id', '=', 'papi_results.role_id')
            ->join('papi_aspects', 'papi_aspects.id', '=', 'papi_roles.aspect_id')
            ->join('papi_rules', 'papi_rules.role_id', '=', 'papi_roles.id')
            ->where('papi_results.id_karyawan', $request->id_karyawan)
            ->where('papi_results.value', '>=', \DB::raw('papi_rules.low_value'))
            ->where('papi_results.value', '<=', \DB::raw('papi_rules.high_value'))
            ->orderBy('papi_aspects.id')
            ->orderBy('papi_roles.id')
            ->get();

        return response()->json([
            'message' => 'Detail Papi Kostick has been shown',
            'data' => $data,
        ], 200);
    }

    public function detailDisc(Request $request)
    {
        // $data = DiscResult::where('id_karyawan', $request->id_karyawan)
        //     ->get();
        // $respon = $data[0];
        // $jawaban = $respon->jawaban;
        // $jawaban = json_decode($jawaban);
        // $most = $jawaban->P; // jawaban P
        // $least = $jawaban->K;// jawaban K
        // $most = array_count_values($most);
        // $least = array_count_values($least);
        // $result = array();
        // $aspect = array('D', 'I', 'S', 'C', 'N');
        // foreach ($aspect as $a) {
        //     $result[$a][1] = isset($most[$a]) ? $most[$a] : 0;
        //     $result[$a][2] = isset($least[$a]) ? $least[$a] : 0;
        //     $result[$a][3] = ($a != 'N' ? $result[$a][1] - $result[$a][2] : 0);
        // }

        // $data = array();
        // array_push($data, self::getPattern($result, 1));
        // array_push($data, self::getPattern($result, 2));
        // array_push($data, self::getPattern($result, 3));

        // $message = 'Detail DISC has been shown';

        // return response()->json([
        //     'data' => $data,
        //     'result' => $result,
        //     'message' => $message
        // ], 200);
        $data = DiscResult::where('id_karyawan', $request->id_karyawan)->first();

        if (!$data) {
            return response()->json([
                'message' => 'Data not found for the given karyawan ID',
            ], 404);
        }

        $jawaban = json_decode($data->jawaban);

        $most = isset($jawaban->P) ? array_count_values($jawaban->P) : [];
        $least = isset($jawaban->K) ? array_count_values($jawaban->K) : [];

        $result = [];
        $aspect = ['D', 'I', 'S', 'C', 'N'];
        foreach ($aspect as $a) {
            $result[$a] = [
                1 => isset($most[$a]) ? $most[$a] : 0, // Most answers count
                2 => isset($least[$a]) ? $least[$a] : 0, // Least answers count
                3 => ($a !== 'N') ? (isset($most[$a]) ? $most[$a] : 0) - (isset($least[$a]) ? $least[$a] : 0) : 0, // Difference, except for 'N'
            ];
        }

        // Prepare data patterns for each
        $dataPatterns = [
            self::getPattern($result, 1),
            self::getPattern($result, 2),
            self::getPattern($result, 3),
        ];


        // Return the response with data
        return response()->json([
            'data' => $dataPatterns,
            'result' => $result,
            'message' => 'Detail DISC has been shown',
        ], 200);


    }

    public function getDISCResults($result, $line)
    {
        // Retrieve the corresponding value from the DiscRules table for each aspect
        $D = DiscRules::select('d')
            ->where('line', $line)
            ->where('value', $result['D'][$line])
            ->first();

        $I = DiscRules::select('i')
            ->where('line', $line)
            ->where('value', $result['I'][$line])
            ->first();

        $S = DiscRules::select('s')
            ->where('line', $line)
            ->where('value', $result['S'][$line])
            ->first();

        $C = DiscRules::select('c')
            ->where('line', $line)
            ->where('value', $result['C'][$line])
            ->first();

        $data = [
            'd' => $D ? $D->d : 0,
            'i' => $I ? $I->i : 0,
            's' => $S ? $S->s : 0,
            'c' => $C ? $C->c : 0,
        ];

        return (object) $data;
    }


    public function getPattern($result, $line)
    {
        $disc = self::getDISCResults($result, $line);
        $D = $disc->d;
        $I = $disc->i;
        $S = $disc->s;
        $C = $disc->c;
        if ($D <= 0 && $I <= 0 && $S <= 0 && $C > 0)
            $pattern = 1;
        elseif ($D > 0 && $I <= 0 && $S <= 0 && $C <= 0)
            $pattern = 2;
        elseif ($D > 0 && $I <= 0 && $S <= 0 && $C > 0 && $C >= $D)
            $pattern = 3;
        elseif ($D > 0 && $I > 0 && $S <= 0 && $C <= 0 && $I >= $D)
            $pattern = 4;
        elseif ($D > 0 && $I > 0 && $S <= 0 && $C > 0 && $I >= $D && $D >= $C)
            $pattern = 5;
        elseif ($D > 0 && $I > 0 && $S > 0 && $C <= 0 && $I >= $D && $D >= $S)
            $pattern = 6;
        elseif ($D > 0 && $I > 0 && $S > 0 && $C <= 0 && $I >= $S && $S >= $D)
            $pattern = 7;
        elseif ($D > 0 && $I <= 0 && $S > 0 && $C > 0 && $S >= $D && $D >= $C)
            $pattern = 8;
        elseif ($D > 0 && $I > 0 && $S <= 0 && $C <= 0 && $D >= $I)
            $pattern = 9;
        elseif ($D > 0 && $I > 0 && $S > 0 && $C <= 0 && $D >= $I && $I >= $S)
            $pattern = 10;
        elseif ($D > 0 && $I <= 0 && $S > 0 && $C <= 0 && $D >= $S)
            $pattern = 11;
        elseif ($D <= 0 && $I > 0 && $S > 0 && $C > 0 && $C >= $I && $I >= $S)
            $pattern = 12;
        elseif ($D <= 0 && $I > 0 && $S > 0 && $C > 0 && $C >= $S && $S >= $I)
            $pattern = 13;
        elseif ($D <= 0 && $I > 0 && $S > 0 && $C > 0 && $I >= $S && $I >= $C)
            $pattern = 14;
        elseif ($D <= 0 && $I <= 0 && $S > 0 && $C <= 0)
            $pattern = 15;
        elseif ($D <= 0 && $I <= 0 && $S > 0 && $C > 0 && $C >= $S)
            $pattern = 16;
        elseif ($D <= 0 && $I <= 0 && $S > 0 && $C > 0 && $S >= $C)
            $pattern = 17;
        elseif ($D > 0 && $I <= 0 && $S <= 0 && $C > 0 && $D >= $C)
            $pattern = 18;
        elseif ($D > 0 && $I > 0 && $S <= 0 && $C > 0 && $D >= $I && $I >= $C)
            $pattern = 19;
        elseif ($D > 0 && $I > 0 && $S > 0 && $C <= 0 && $D >= $S && $S >= $I)
            $pattern = 20;
        elseif ($D > 0 && $I <= 0 && $S > 0 && $C > 0 && $D >= $S && $S >= $C)
            $pattern = 21;
        elseif ($D > 0 && $I > 0 && $S <= 0 && $C > 0 && $D >= $C && $C >= $I)
            $pattern = 22;
        elseif ($D > 0 && $I <= 0 && $S > 0 && $C > 0 && $D >= $C && $C >= $S)
            $pattern = 23;
        elseif ($D <= 0 && $I > 0 && $S <= 0 && $C <= 0)
            $pattern = 24;
        elseif ($D <= 0 && $I > 0 && $S > 0 && $C <= 0 && $I >= $S)
            $pattern = 25;
        elseif ($D <= 0 && $I > 0 && $S <= 0 && $C > 0 && $I >= $C)
            $pattern = 26;
        elseif ($D > 0 && $I > 0 && $S <= 0 && $C > 0 && $I >= $C && $C >= $D)
            $pattern = 27;
        elseif ($D <= 0 && $I > 0 && $S > 0 && $C > 0 && $I >= $C && $C >= $S)
            $pattern = 28;
        elseif ($D > 0 && $I <= 0 && $S > 0 && $C <= 0 && $S >= $D)
            $pattern = 29;
        elseif ($D <= 0 && $I > 0 && $S > 0 && $C <= 0 && $S >= $I)
            $pattern = 30;
        elseif ($D > 0 && $I > 0 && $S > 0 && $C <= 0 && $S >= $D && $D >= $I)
            $pattern = 31;
        elseif ($D > 0 && $I > 0 && $S > 0 && $C <= 0 && $S >= $I && $I >= $D)
            $pattern = 32;
        elseif ($D <= 0 && $I > 0 && $S > 0 && $C > 0 && $S >= $I && $I >= $C)
            $pattern = 33;
        elseif ($D > 0 && $I <= 0 && $S > 0 && $C > 0 && $S >= $C && $C >= $D)
            $pattern = 34;
        elseif ($D <= 0 && $I > 0 && $S > 0 && $C > 0 && $S >= $C && $C >= $I)
            $pattern = 35;
        elseif ($D <= 0 && $I > 0 && $S <= 0 && $C > 0 && $C >= $I)
            $pattern = 36;
        elseif ($D > 0 && $I > 0 && $S <= 0 && $C > 0 && $C >= $D && $D >= $I)
            $pattern = 37;
        elseif ($D > 0 && $I <= 0 && $S > 0 && $C > 0 && $C >= $D && $D >= $S)
            $pattern = 38;
        elseif ($D > 0 && $I > 0 && $S <= 0 && $C > 0 && $C >= $I && $I >= $D)
            $pattern = 39;
        elseif ($D > 0 && $I <= 0 && $S > 0 && $C > 0 && $C >= $S && $S >= $D)
            $pattern = 40;
        else
            $pattern = 0;

        $res = [];
        if ($pattern != 0) {
            $data = DiscPattern::where('id', $pattern)->get();
            $res = $data[0];
        }

        return array($disc, $res);
    }

    public function cetakEvaluasi(Request $request)
    {
        if ($request->kategori == 'EMPLOYEE EVALUATION' || $request->kategori == 'MANAGEMENT EVALUATION' || $request->kategori == 'SATISFACTION OF LEADER') {
            $cek_karyawan = MasterKaryawan::where('is_active', true)->get();
            $data = [];
            $a = 0;
            $nil_ = 0;
            if ($request->kategori == 'EMPLOYEE EVALUATION' || $request->kategori == 'MANAGEMENT EVALUATION') {
                $nil_ = 5;
            } else if ($request->kategori == 'SATISFACTION OF LEADER') {
                $nil_ = 4;
            }

            $karyawanIds = $cek_karyawan->pluck('id');

            $cek = Psikotes::select(
                'psikotes.id_karyawan',
                'c.nik_karyawan as nik_evaluator',
                'c.nama_lengkap as nama_evaluator',
                'd.nama_divisi as dept_evaluator',
                'b.nik_karyawan',
                'b.nama_lengkap as nama_karyawan',
                'e.nama_divisi as dept_karyawan',
                DB::raw('COUNT(psikotes.id_evaluasi) as jumlah_soal'),
                DB::raw('SUM(psikotes.jawaban) as evaluasi'),
                DB::raw('(SUM(psikotes.jawaban) / (COUNT(psikotes.id_evaluasi) * ' . (int) $nil_ . ') * 100) as nilai')
            )
                ->leftJoin('master_karyawan as b', 'psikotes.id_evaluasi', '=', 'b.id')
                ->rightJoin('master_karyawan as c', 'psikotes.id_karyawan', '=', 'c.id')
                ->leftJoin('master_divisi as d', 'c.id_department', '=', 'd.id')
                ->leftJoin('master_divisi as e', 'b.id_department', '=', 'e.id')
                ->join('soal_psikotes as f', 'psikotes.id_soal', '=', 'f.id')
                ->whereIn('psikotes.id_evaluasi', $karyawanIds)
                ->where('f.kategori_soal', $request->kategori)
                ->groupBy(
                    'psikotes.id_karyawan',
                    'c.nama_lengkap',
                    'b.nik_karyawan',
                    'b.nama_lengkap',
                    'd.nama_divisi',
                    'e.nama_divisi'
                )
                ->get();


            foreach ($cek as $v) {
                $data[$a] = $v;
                $a++;
            }

            // foreach ($cek_karyawan as $k => $v) {
            //     $cek = Psikotes::select(
            //         'psikotes.id_karyawan',
            //         'c.nik_karyawan as nik_evaluator',
            //         'c.nama_lengkap as nama_evaluator',
            //         'd.nama_divisi as dept_evaluator',
            //         'b.nik_karyawan',
            //         'b.nama_lengkap as nama_karyawan',
            //         'e.nama_divisi as dept_karyawan',
            //         DB::raw('COUNT(psikotes.id_evaluasi) as jumlah_soal'),
            //         DB::raw('SUM(psikotes.jawaban) as evaluasi'),
            //         DB::raw('(SUM(psikotes.jawaban) / (COUNT(psikotes.id_evaluasi) * ' . (int) $nil_ . ') * 100) as nilai')
            //     )
            //         ->leftJoin('master_karyawan as b', 'psikotes.id_evaluasi', '=', 'b.id')
            //         ->rightJoin('master_karyawan as c', 'psikotes.id_karyawan', '=', 'c.id')
            //         ->leftJoin('master_divisi as d', 'c.id_department', '=', 'd.id')
            //         ->leftJoin('master_divisi as e', 'b.id_department', '=', 'e.id')
            //         ->join('soal_psikotes as f', 'psikotes.id_soal', '=', 'f.id')
            //         ->where('psikotes.id_evaluasi', $v->id)
            //         ->where('f.kategori_soal', $request->kategori)
            //         ->groupBy(
            //             'psikotes.id_karyawan',
            //             'c.nama_lengkap',
            //             'b.nik_karyawan',
            //             'b.nama_lengkap',
            //             'd.nama_divisi',
            //             'e.nama_divisi'
            //         )
            //         ->get();

            //     if ($cek != null) {
            //         $data[$a] = $cek;
            //         $a++;
            //     }
            // }

            // =========================================================================
            // ------------------------------INI SPREADSHEET----------------------------
            // =========================================================================
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            //Style
            $styleBorder = [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ];
            $styleBorderB = [
                'font' => [
                    'size' => 12,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];
            $judul1 = [
                'font' => [
                    'size' => 12,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
                    'rotation' => 90,
                    'startColor' => [
                        'argb' => 'ffe1f50a',
                    ],
                    'endColor' => [
                        'argb' => 'ffe1f50a',
                    ],
                ],
            ];
            $judul2 = [
                'font' => [
                    'size' => 12,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
                    'rotation' => 90,
                    'startColor' => [
                        'argb' => 'ff2883fa',
                    ],
                    'endColor' => [
                        'argb' => 'ff2883fa',
                    ],
                ],
            ];
            $stylecenter = [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
                'font' => [
                    'bold' => true,
                    'size' => 24,
                ],
            ];
            $stylecenter2 = [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ];
            //Column Width
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);
            $sheet->getColumnDimension('C')->setAutoSize(true);
            $sheet->getColumnDimension('D')->setAutoSize(true);
            $sheet->getColumnDimension('E')->setAutoSize(true);
            $sheet->getColumnDimension('F')->setAutoSize(true);
            $sheet->getColumnDimension('G')->setAutoSize(true);
            $sheet->getColumnDimension('H')->setAutoSize(true);
            $sheet->getColumnDimension('I')->setAutoSize(true);
            $sheet->getColumnDimension('J')->setAutoSize(true);
            $sheet->getColumnDimension('K')->setAutoSize(true);
            $sheet->getColumnDimension('L')->setAutoSize(true);
            $sheet->getColumnDimension('M')->setAutoSize(true);
            $sheet->getColumnDimension('N')->setAutoSize(true);
            $sheet->getColumnDimension('O')->setAutoSize(true);
            $sheet->getColumnDimension('P')->setAutoSize(true);
            $sheet->getColumnDimension('Q')->setAutoSize(true);
            $sheet->getColumnDimension('R')->setAutoSize(true);
            $sheet->getColumnDimension('S')->setAutoSize(true);
            $sheet->getColumnDimension('T')->setAutoSize(true);

            //Header Title
            $sheet->setCellValue('A1', 'LIST REKAP EVALUASI ' . $request->kategori . " " . $request->periode);

            $i = 2;
            foreach ($data as $key => $value) {
                // LOOP Atasan
                $sheet->setCellValue('A' . $i, ' Evaluator');
                $sheet->mergeCells('A' . $i . ':F' . $i);
                $spreadsheet->getActiveSheet()->getStyle('A' . $i)->applyFromArray($judul1);
                // $i++;
                $p = $i + 1;
                $sheet->setCellValue('A' . $p, ' No');
                $sheet->setCellValue('B' . $p, ' NIK');
                $sheet->setCellValue('C' . $p, ' Nama Karyawan');
                $sheet->setCellValue('D' . $p, ' Departement');
                $sheet->setCellValue('E' . $p, ' Evaluasi');
                $sheet->setCellValue('F' . $p, ' Persentase');
                $spreadsheet->getActiveSheet()->getStyle('A' . $p . ':F' . $p)->applyFromArray($judul2);

                $q = $p + 1;
                $nilai = 0;
                $no = 1;
                $no_ = 1;
                foreach ($value as $k => $v) {
                    $nilai += floatval($v->nilai);
                    $sheet->setCellValue('A' . $q, $no++);
                    $sheet->setCellValue('B' . $q, $v->nik_evaluator);
                    $sheet->setCellValue('C' . $q, $v->nama_evaluator);
                    $sheet->setCellValue('D' . $q, $v->dept_evaluator);
                    $sheet->setCellValue('E' . $q, $v->jumlah_soal);
                    $sheet->setCellValue('F' . $q, number_format($v->nilai, 2) . " %");
                    $spreadsheet->getActiveSheet()->getStyle('A2:F' . $q)->applyFromArray($styleBorderB);
                    $spreadsheet->getActiveSheet()->getStyle('D' . $q . ':F' . $q)->applyFromArray($stylecenter2);

                    $nik_karyawan = $v->nik_karyawan;
                    $nama_karyawan = $v->nama_karyawan;
                    $departmanet_karyawan = $v->dept_karyawan;

                    $q++;
                }
                $r = $q;
                $sheet->setCellValue('B' . $r, ' Karyawan Evaluasi');
                $sheet->mergeCells('B' . $r . ':F' . $r);
                $spreadsheet->getActiveSheet()->getStyle('B' . $r)->applyFromArray($judul1);

                $s = $r + 1;
                $t = $s + 1;
                $persentase = $nilai / count($value);
                $sheet->setCellValue('B' . $s, ' No');
                $sheet->setCellValue('C' . $s, ' NIK');
                $sheet->setCellValue('D' . $s, ' Nama Karyawan');
                $sheet->setCellValue('E' . $s, ' Departement');
                $sheet->setCellValue('F' . $s, ' Persentase');
                $spreadsheet->getActiveSheet()->getStyle('B' . $s . ':F' . $s)->applyFromArray($judul2);
                $sheet->setCellValue('B' . $t, $no_++);
                $sheet->setCellValue('C' . $t, $nik_karyawan);
                $sheet->setCellValue('D' . $t, $nama_karyawan);
                $sheet->setCellValue('E' . $t, $departmanet_karyawan);
                $sheet->setCellValue('F' . $t, \number_format($persentase, 2) . " %");
                $spreadsheet->getActiveSheet()->getStyle('B' . $r . ':F' . $t)->applyFromArray($styleBorderB);
                $spreadsheet->getActiveSheet()->getStyle('D' . $t . ':F' . $t)->applyFromArray($stylecenter2);

                $i = $t + 1;
            }
            $spreadsheet->getActiveSheet()->getStyle('A1')->applyFromArray($stylecenter);
            $sheet->mergeCells('A1:F1');

            $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
            $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LEGAL);
            $writer = new Xlsx($spreadsheet);
            $fileName = $request->kategori . ".xlsx";
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($fileName);
            return response()->json([
                'message' => 'Export Excel Berhasil',
                'link' => $fileName
            ], 200);
        } else if ($request->kategori == 'EMPLOYEE SATISFACTION') {
            $data = Psikotes::select(
                'psikotes.id_karyawan as id_karyawan',
                'master_divisi.nama_divisi as nama_divisi',
                'soal_psikotes.kategori_soal',
                'master_karyawan.nama_lengkap as nama_karyawan',
                'master_karyawan.nik_karyawan as nik_karyawan',
                DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                DB::raw('ROUND(SUM(psikotes.jawaban) / (COUNT(psikotes.id_karyawan) * 5) * 100, 2) as persentase'),
                DB::raw('COUNT(psikotes.id_karyawan) as total_soal')
            )
                ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
                ->join('master_karyawan', 'master_karyawan.id', '=', 'psikotes.id_karyawan')
                ->leftJoin('master_divisi', 'master_karyawan.id_department', '=', 'master_divisi.id')
                ->where('soal_psikotes.kategori_soal', $request->kategori)
                ->groupBy('psikotes.id_karyawan', 'soal_psikotes.kategori_soal')
                ->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            //Style
            $styleBorder = [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ];

            $styleBorderB = [
                'font' => [
                    'size' => 12,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];
            $judul1 = [
                'font' => [
                    'size' => 12,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
                    'rotation' => 90,
                    'startColor' => [
                        'argb' => 'ffe1f50a',
                    ],
                    'endColor' => [
                        'argb' => 'ffe1f50a',
                    ],
                ],
            ];
            $judul2 = [
                'font' => [
                    'size' => 12,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
                    'rotation' => 90,
                    'startColor' => [
                        'argb' => 'ff2883fa',
                    ],
                    'endColor' => [
                        'argb' => 'ff2883fa',
                    ],
                ],
            ];
            $stylecenter = [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
                'font' => [
                    'bold' => true,
                    'size' => 24,
                ],
            ];
            $stylecenter2 = [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ];

            //Column Width
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);
            $sheet->getColumnDimension('C')->setAutoSize(true);
            $sheet->getColumnDimension('D')->setAutoSize(true);
            $sheet->getColumnDimension('E')->setAutoSize(true);
            $sheet->getColumnDimension('F')->setAutoSize(true);
            $sheet->getColumnDimension('G')->setAutoSize(true);
            $sheet->getColumnDimension('H')->setAutoSize(true);
            $sheet->getColumnDimension('I')->setAutoSize(true);
            $sheet->getColumnDimension('J')->setAutoSize(true);
            $sheet->getColumnDimension('K')->setAutoSize(true);
            $sheet->getColumnDimension('L')->setAutoSize(true);
            $sheet->getColumnDimension('M')->setAutoSize(true);
            $sheet->getColumnDimension('N')->setAutoSize(true);
            $sheet->getColumnDimension('O')->setAutoSize(true);
            $sheet->getColumnDimension('P')->setAutoSize(true);
            $sheet->getColumnDimension('Q')->setAutoSize(true);
            $sheet->getColumnDimension('R')->setAutoSize(true);
            $sheet->getColumnDimension('S')->setAutoSize(true);
            $sheet->getColumnDimension('T')->setAutoSize(true);

            //Header Title
            $sheet->setCellValue('A1', 'LIST REKAP EVALUASI ' . $request->kategori . " " . $request->periode);
            $sheet->setCellValue('A2', ' No');
            $sheet->setCellValue('B2', ' NIK');
            $sheet->setCellValue('C2', ' Nama Karyawan');
            $sheet->setCellValue('D2', ' Departement');
            $sheet->setCellValue('E2', ' Total Soal');
            $sheet->setCellValue('F2', ' Total Jawaban');
            $sheet->setCellValue('G2', ' Persentase');
            $spreadsheet->getActiveSheet()->getStyle('A2:G2')->applyFromArray($styleBorder);
            $i = 3;
            $no = 1;
            foreach ($data as $key => $value) {
                $sheet->setCellValue('A' . $i, $no++);
                $sheet->setCellValue('B' . $i, $value->nik_karyawan);
                $sheet->setCellValue('C' . $i, $value->nama_karyawan);
                $sheet->setCellValue('D' . $i, $value->nama_divisi);
                $sheet->setCellValue('E' . $i, $value->total_soal);
                $sheet->setCellValue('F' . $i, $value->total_jawaban);
                $sheet->setCellValue('G' . $i, number_format($value->persentase, 2) . " %");
                $spreadsheet->getActiveSheet()->getStyle('A3:G' . $i)->applyFromArray($styleBorderB);
                $spreadsheet->getActiveSheet()->getStyle('E' . $i . ':G' . $i)->applyFromArray($stylecenter2);
                $i++;
            }
            $spreadsheet->getActiveSheet()->getStyle('A1')->applyFromArray($stylecenter);
            $sheet->mergeCells('A1:G1');

            $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
            $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LEGAL);
            $writer = new Xlsx($spreadsheet);
            $fileName = $request->kategori . ".xlsx";
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($fileName);
            return response()->json([
                'message' => 'Export Excel Berhasil',
                'link' => $fileName
            ], 200);
            // =========================================================================
            // ------------------------------INI SPREADSHEET----------------------------
            // =========================================================================
        }
    }
}