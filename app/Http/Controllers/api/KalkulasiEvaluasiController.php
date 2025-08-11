<?php

namespace App\Http\Controllers\api;

use App\Models\{MasterKaryawan, Psikotes, PapiRule, DiscPattern, DiscResult, DiscRules};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;

class KalkulasiEvaluasiController extends Controller
{
    public function indexKalkulasi()
    {
        $data = MasterKaryawan::select([
            'image',
            'master_karyawan.id as id_karyawan',
            'shio',
            'elemen',
            'nik_ktp',
            'tempat_lahir',
            'tanggal_lahir',
            'jenis_kelamin',
            'agama',
            'status_pernikahan',
            'nama_panggilan',
            'tgl_nikah',
            'email',
            'tempat_nikah',
            'tgl_exp_identitas',
            'nama_lengkap as nama_lengkap',
            'nik_karyawan as nik_karyawan',
            'd.nama_divisi as department_karyawan',
            'e.nama_jabatan as posisi_karyawan',
            'master_karyawan.status_karyawan as status_karyawan',
            'c.nama_cabang'
        ])
            ->join('psikotes as b', function ($join) {
                $join->on('master_karyawan.id', '=', 'b.id_karyawan')
                    ->orOn('master_karyawan.id', '=', 'b.id_evaluasi');
            })
            ->join('master_cabang as c', 'master_karyawan.id_cabang', '=', 'c.id')
            ->join('master_divisi as d', 'master_karyawan.id_department', '=', 'd.id')
            ->join('master_jabatan as e', 'master_karyawan.id_jabatan', '=', 'e.id')
            ->groupBy('master_karyawan.id')
            ->get();

        return Datatables::of($data)->make(true);
    }

    public function getDISCResults($result, $line)
    {
        $D = DiscRules::select('d')
            ->where('line', $line)
            ->where('value', $result['D'][$line])
            ->get();

        $I = DiscRules::select('i')
            ->where('line', $line)
            ->where('value', $result['I'][$line])
            ->get();

        $S = DiscRules::select('s')
            ->where('line', $line)
            ->where('value', $result['S'][$line])
            ->get();

        $C = DiscRules::select('c')
            ->where('line', $line)
            ->where('value', $result['C'][$line])
            ->get();

        $data = [
            'd' => $D,
            'i' => $I,
            's' => $S,
            'c' => $C,
        ];

        return $data[0];

        // $sql = "
        // SELECT
        //     d.d,i.i,s.s,c.c
        // FROM
        //     (SELECT d FROM disc_rules WHERE line={$line} AND value={$result['D'][$line]}) d,
        //     (SELECT i FROM disc_rules WHERE line={$line} AND value={$result['I'][$line]}) i,
        //     (SELECT s FROM disc_rules WHERE line={$line} AND value={$result['S'][$line]}) s,
        //     (SELECT c FROM disc_rules WHERE line={$line} AND value={$result['C'][$line]}) c
        // ";
        // $data = DB::select($sql);
        // return $data[0];
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
        elseif ($D > 0 && $I > 0 && $S > 0 && $C > 0 && $I >= $S && $S >= $D && $D >= $C)
            $pattern = 25;
        elseif ($D > 0 && $I > 0 && $S > 0 && $C > 0 && $S >= $I && $I >= $D && $D >= $C)
            $pattern = 30;
        else
            $pattern = 0;

        $res = [];
        if ($pattern != 0) {
            $data = DiscPattern::where('id', $pattern)->get();
            $res = $data[0];
        }
        return array($disc, $res);
    }

    public function detailKalkulasi(Request $request)
    {
        $users = MasterKaryawan::join('master_jabatan as posision', 'master_karyawan.id_jabatan', '=', 'posision.id')
            ->join('master_divisi as department', 'master_karyawan.id_department', '=', 'department.id')
            ->join('master_cabang as cabang', 'master_karyawan.id_cabang', '=', 'cabang.id')
            ->select(
                'master_karyawan.id',
                'master_karyawan.nama_lengkap',
                'master_karyawan.nik_karyawan',
                'posision.nama_jabatan as name_posisi',
                'department.nama_divisi as name_department',
                'cabang.nama_cabang',
                'master_karyawan.shio',
                'master_karyawan.elemen',
                'master_karyawan.image'
            )
            ->where('master_karyawan.is_active', 1)
            ->where('master_karyawan.id', $request->id_user)->first();

        $ist = Psikotes::join('soal_psikotes as b', 'psikotes.id_soal', '=', 'b.id')
            ->select(
                'psikotes.nama_karyawan',
                'b.kategori_soal',
                'b.kategori',
                DB::raw("SUM(CASE WHEN psikotes.jawaban = b.kunci_jawaban THEN 1 ELSE 0 END) as benar"),
                DB::raw("SUM(CASE WHEN psikotes.jawaban <> b.kunci_jawaban THEN 1 ELSE 0 END) as salah"),
                DB::raw("SUM(CASE WHEN psikotes.jawaban = b.kunci_jawaban THEN 1 ELSE 0 END) * 100 / COUNT(b.kategori) as persentase")
            )
            ->where('psikotes.id_karyawan', $request->id_user)
            ->where('b.kategori_soal', 'IST')
            ->groupBy('psikotes.nama_karyawan', 'b.kategori_soal', 'b.kategori')
            ->get();

        $papi_kostick = PapiRule::join('papi_roles as b', 'b.id', '=', 'papi_rules.role_id')
            ->join('papi_aspects as c', 'c.id', '=', 'b.aspect_id')
            ->join('papi_results as d', 'd.role_id', '=', 'b.id')
            ->select(
                'c.aspect',
                'b.code',
                'b.role',
                'd.value',
                'd.nama_karyawan',
                'papi_rules.interprestation'
            )
            ->where('d.id_karyawan', $request->id_user)
            ->whereBetween('d.value', [DB::raw('papi_rules.low_value'), DB::raw('papi_rules.high_value')])
            ->groupBy('c.aspect', 'b.code', 'b.role', 'd.nama_karyawan', 'papi_rules.interprestation', 'd.value', 'c.id', 'b.id')
            ->orderBy('c.id')
            ->orderBy('b.id')
            ->get();

        // $ist = Psikotes::join('soal_psikotes as b', 'psikotes.id_soal', '=', 'b.id')
        //     ->select(
        //         'psikotes.nama_karyawan',
        //         'b.kategori_soal',
        //         'b.kategori',
        //         DB::raw("SUM(CASE WHEN psikotes.jawaban = b.kunci_jawaban THEN 1 ELSE 0 END) as benar"),
        //         DB::raw("SUM(CASE WHEN psikotes.jawaban <> b.kunci_jawaban THEN 1 ELSE 0 END) as salah"),
        //         DB::raw("SUM(CASE WHEN psikotes.jawaban = b.kunci_jawaban THEN 1 ELSE 0 END) * 100 / COUNT(b.kategori) as persentase")
        //     )
        //     ->where('psikotes.id_karyawan', $request->id_user)
        //     ->where('b.kategori_soal', 'IST')
        //     ->groupBy('psikotes.nama_karyawan', 'b.kategori', 'b.kategori_soal')
        //     ->get();

        // $papi_kostick = PapiRule::join('papi_roles as b', 'papi_rules.role_id', '=', 'b.id')
        //     ->join('papi_aspects as c', 'c.id', '=', 'b.aspect_id')
        //     ->join('papi_results as d', 'd.role_id', '=', 'b.id')
        //     ->join('papi_rules as a', 'a.role_id', '=', 'b.id')
        //     ->select(
        //         'c.aspect',
        //         'b.code',
        //         'b.role',
        //         'd.value',
        //         'd.nama_karyawan',
        //         'a.interprestation'
        //     )
        //     ->where('d.id_karyawan', $request->id_user)
        //     ->whereBetween('d.value', [DB::raw('a.low_value'), DB::raw('a.high_value')])
        //     ->orderBy('c.id')
        //     ->orderBy('b.id')
        //     ->get();

        $disc = DiscResult::where('id_karyawan', $request->id_user)->get();
    // dd($disc);
        if(count($disc) == 0){
            return response()->json(['message' => 'Data Disc Result Tidak Ditemukan'], 404);
        }
        $respon = !empty($disc) ? $disc[0] : null;

        $result_disc = [];
        if ($respon !== null) {
            $jawaban = property_exists($respon, 'jawaban') ? json_decode($respon->jawaban) : null;
            if ($jawaban !== null) {
                $most = property_exists($jawaban, 'P') ? $jawaban->P : [];
                $least = property_exists($jawaban, 'K') ? $jawaban->K : [];

                $most = is_array($most) ? array_count_values($most) : [];
                $least = is_array($least) ? array_count_values($least) : [];

                $aspect = ['D', 'I', 'S', 'C', 'N'];

                foreach ($aspect as $a) {
                    $result_disc[$a][1] = isset($most[$a]) ? $most[$a] : 0;
                    $result_disc[$a][2] = isset($least[$a]) ? $least[$a] : 0;
                    $result_disc[$a][3] = ($a !== 'N' ? ($result_disc[$a][1] - $result_disc[$a][2]) : 0);
                }

                $disc = [];
                array_push($disc, self::getPattern($result_disc, 1));
                array_push($disc, self::getPattern($result_disc, 2));
                array_push($disc, self::getPattern($result_disc, 3));
            } else {
                $disc = [];
            }
        } else {
            $disc = [];
        }

        $employ_evaluation = Psikotes::join('master_karyawan', 'master_karyawan.id', '=', 'psikotes.id_karyawan')
            ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
            ->select(
                'master_karyawan.nama_lengkap',
                DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                DB::raw('(SUM(psikotes.jawaban) / (COUNT(psikotes.id_evaluasi) * 5) * 100) as nilai'),
                DB::raw('COUNT(psikotes.id_evaluasi) as jumlah_soal')
            )
            ->where('psikotes.id_evaluasi', $request->id_user)
            ->where('soal_psikotes.kategori_soal', 'EMPLOYEE EVALUATION')
            ->groupBy('psikotes.id_karyawan')
            ->get();

        $satisfaction_leader = Psikotes::join('master_karyawan', 'master_karyawan.id', '=', 'psikotes.id_karyawan')
            ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
            ->select(
                'master_karyawan.nama_lengkap',
                DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                DB::raw('(SUM(psikotes.jawaban) / (COUNT(psikotes.id_evaluasi) * 4) * 100) as nilai'),
                DB::raw('COUNT(psikotes.id_evaluasi) as jumlah_soal')
            )
            ->where('psikotes.id_evaluasi', $request->id_user)
            ->where('soal_psikotes.kategori_soal', 'SATISFACTION OF LEADER')
            ->groupBy('psikotes.id_karyawan')
            ->get();

        $management_evaluation = Psikotes::join('master_karyawan', 'master_karyawan.id', '=', 'psikotes.id_karyawan')
            ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
            ->select(
                'master_karyawan.nama_lengkap',
                DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                DB::raw('(SUM(psikotes.jawaban) / (COUNT(psikotes.id_karyawan) * 5) * 100) as nilai'),
                DB::raw('COUNT(psikotes.id_karyawan) as jumlah_soal')
            )
            ->where('psikotes.id_evaluasi', $request->id_user)
            ->where('soal_psikotes.kategori_soal', 'MANAGEMENT EVALUATION')
            ->groupBy('psikotes.id_karyawan')
            ->get();

        $employ_satisfaction = Psikotes::join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
            ->join('master_karyawan', 'master_karyawan.id', '=', 'psikotes.id_karyawan')
            ->select(
                'psikotes.id_karyawan as id_user',
                'soal_psikotes.kategori_soal',
                'master_karyawan.nama_lengkap as nama_user',
                DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                DB::raw('ROUND(SUM(psikotes.jawaban) / (COUNT(psikotes.id_karyawan) * 5) * 100, 2) as persentase'),
                DB::raw('COUNT(psikotes.id_karyawan) as total_soal')
            )
            ->where('soal_psikotes.kategori_soal', 'EMPLOYEE SATISFACTION')
            ->where('psikotes.id_karyawan', $request->id_user)
            ->groupBy('psikotes.id_karyawan', 'soal_psikotes.kategori_soal', 'master_karyawan.nama_lengkap')
            ->get();

        return response()->json([
            'karyawan' => $users,
            'ist' => $ist,
            'disc' => $disc,
            'result_disc' => $result_disc,
            'papi_kostick' => $papi_kostick,
            'employ_evaluation' => $employ_evaluation,
            'satisfaction_leader' => $satisfaction_leader,
            'management_evaluation' => $management_evaluation,
            'employ_satisfaction' => $employ_satisfaction,
            'message' => 'Data hasbeen show'
        ], 200);
    }

    public function reportKalkulasi(Request $request)
    {
        $users = MasterKaryawan::select(
            'master_karyawan.id',
            'master_karyawan.nama_lengkap',
            'master_karyawan.nik_karyawan',
            'posision.nama_jabatan as name_posisi',
            'department.nama_divisi as name_department',
            'cabang.nama_cabang',
            'master_karyawan.shio',
            'master_karyawan.elemen'
        )
            ->join('master_jabatan as posision', 'master_karyawan.id_jabatan', '=', 'posision.id')
            ->join('master_divisi as department', 'master_karyawan.id_department', '=', 'department.id')
            ->join('master_cabang as cabang', 'master_karyawan.id_cabang', '=', 'cabang.id')
            ->where('master_karyawan.is_active', 1)
            ->where('master_karyawan.id', $request->id_user)
            ->first();

        $ist = Psikotes::join('soal_psikotes as b', 'psikotes.id_soal', '=', 'b.id')
            ->select(
                'psikotes.nama_karyawan',
                'b.kategori_soal',
                'b.kategori',
                DB::raw("SUM(CASE WHEN psikotes.jawaban = b.kunci_jawaban THEN 1 ELSE 0 END) as benar"),
                DB::raw("SUM(CASE WHEN psikotes.jawaban <> b.kunci_jawaban THEN 1 ELSE 0 END) as salah"),
                DB::raw("SUM(CASE WHEN psikotes.jawaban = b.kunci_jawaban THEN 1 ELSE 0 END) * 100 / COUNT(b.kategori) as persentase")
            )
            ->where('psikotes.id_karyawan', $request->id_user)
            ->where('b.kategori_soal', 'IST')
            ->groupBy('psikotes.nama_karyawan', 'b.kategori_soal', 'b.kategori')
            ->get();

        $papi_kostick = PapiRule::join('papi_roles as b', 'b.id', '=', 'papi_rules.role_id')
            ->join('papi_aspects as c', 'c.id', '=', 'b.aspect_id')
            ->join('papi_results as d', 'd.role_id', '=', 'b.id')
            ->select(
                'c.aspect',
                'b.code',
                'b.role',
                'd.value',
                'd.nama_karyawan',
                'papi_rules.interprestation'
            )
            ->where('d.id_karyawan', $request->id_user)
            ->whereBetween('d.value', [DB::raw('papi_rules.low_value'), DB::raw('papi_rules.high_value')])
            ->orderBy('c.id')
            ->orderBy('b.id')
            ->get();

        $disc = DiscResult::where('id_karyawan', $request->id_user)->get();
        $respon = !empty($disc) ? $disc[0] : null;

        $result_disc = []; // Declare $result_disc outside the if statement

        if ($respon !== null) {
            $jawaban = property_exists($respon, 'jawaban') ? json_decode($respon->jawaban) : null;
            if ($jawaban !== null) {
                $most = property_exists($jawaban, 'P') ? $jawaban->P : [];
                $least = property_exists($jawaban, 'K') ? $jawaban->K : [];

                $most = is_array($most) ? array_count_values($most) : [];
                $least = is_array($least) ? array_count_values($least) : [];

                $aspect = ['D', 'I', 'S', 'C', 'N'];

                foreach ($aspect as $a) {
                    $result_disc[$a][1] = isset($most[$a]) ? $most[$a] : 0;
                    $result_disc[$a][2] = isset($least[$a]) ? $least[$a] : 0;
                    $result_disc[$a][3] = ($a !== 'N' ? ($result_disc[$a][1] - $result_disc[$a][2]) : 0);
                }

                $disc = [];
                array_push($disc, self::getPattern($result_disc, 1));
                array_push($disc, self::getPattern($result_disc, 2));
                array_push($disc, self::getPattern($result_disc, 3));
            } else {
                // Handle the case where JSON decoding of 'jawaban' fails.
                $disc = [];
            }
        } else {
            // Handle the case where $disc is empty.
            $disc = [];
        }

        $employ_satisfaction = Psikotes::join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
            ->join('master_karyawan as users', 'users.id', '=', 'psikotes.id_karyawan')
            ->select(
                'psikotes.id_karyawan as id_user',
                'soal_psikotes.kategori_soal',
                'users.nama_lengkap as nama_user',
                DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                DB::raw('ROUND(SUM(psikotes.jawaban) / (COUNT(psikotes.id_karyawan) * 5) * 100, 2) as persentase'),
                DB::raw('COUNT(psikotes.id_karyawan) as total_soal')
            )
            ->where('soal_psikotes.kategori_soal', 'EMPLOYEE SATISFACTION')
            ->where('psikotes.id_karyawan', $request->id_user)
            ->groupBy('psikotes.id_karyawan', 'soal_psikotes.kategori_soal', 'users.nama_lengkap')
            ->get();

        $employ_evaluation = Psikotes::join('master_karyawan as users', 'users.id', '=', 'psikotes.id_karyawan')
            ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
            ->select(
                'users.nama_lengkap',
                DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                DB::raw('(SUM(psikotes.jawaban) / (COUNT(psikotes.id_evaluasi) * 5) * 100) as nilai'),
                DB::raw('COUNT(psikotes.id_evaluasi) as jumlah_soal')
            )
            ->where('psikotes.id_evaluasi', $request->id_user)
            ->where('soal_psikotes.kategori_soal', 'EMPLOYEE EVALUATION')
            ->groupBy('psikotes.id_karyawan')
            ->get();

        $satisfaction_leader = Psikotes::join('master_karyawan as users', 'users.id', '=', 'psikotes.id_karyawan')
            ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
            ->select(
                'users.nama_lengkap',
                DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                DB::raw('(SUM(psikotes.jawaban) / (COUNT(psikotes.id_evaluasi) * 4) * 100) as nilai'),
                DB::raw('COUNT(psikotes.id_evaluasi) as jumlah_soal')
            )
            ->where('psikotes.id_evaluasi', $request->id_user)
            ->where('soal_psikotes.kategori_soal', 'SATISFACTION OF LEADER')
            ->groupBy('psikotes.id_karyawan')
            ->get();

        $management_evaluation = Psikotes::join('master_karyawan as users', 'users.id', '=', 'psikotes.id_karyawan')
            ->join('soal_psikotes', 'soal_psikotes.id', '=', 'psikotes.id_soal')
            ->select(
                'users.nama_lengkap',
                DB::raw('SUM(psikotes.jawaban) as total_jawaban'),
                DB::raw('(SUM(psikotes.jawaban) / (COUNT(psikotes.id_karyawan) * 5) * 100) as nilai'),
                DB::raw('COUNT(psikotes.id_karyawan) as jumlah_soal')
            )
            ->where('psikotes.id_evaluasi', $request->id_user)
            ->where('soal_psikotes.kategori_soal', 'MANAGEMENT EVALUATION')
            ->groupBy('psikotes.id_karyawan')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        //Style

        $styleBorder = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
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


        $title1 = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => true,
                'size' => 24,
                'name' => 'Calibri',
            ],
        ];
        $title2 = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => true,
                'size' => 16,
                'name' => 'Calibri',
            ],
        ];
        $title3 = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => true,
                'size' => 14,
                'name' => 'Calibri',
            ],
        ];
        $datapribadi = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => true,
                'size' => 12,
                'name' => 'Calibri',
            ],
        ];

        $titlecolumn = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => true,
                'size' => 12,
                'name' => 'Calibri',
            ],
        ];
        $datatable = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => false,
                'size' => 12,
                'name' => 'Calibri',
            ],
        ];
        $dataleft = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => false,
                'size' => 12,
                'name' => 'Calibri',
            ],
        ];

        $ttdata = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => true,
                'size' => 12,
                'name' => 'Calibri',
            ],
        ];
        $bev = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => true,
                'size' => 12,
                'name' => 'Calibri',
            ],
        ];
        //Column Widt

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(false);
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

        // TITLE
        $sheet->setCellValue('A1', 'EVALUASI KARYAWAN TAHUNAN ' . date('Y'));
        $spreadsheet->getActiveSheet()->getStyle('A1')->applyFromArray($title1);


        // =========================================== DATA PRIBADI ============================================================


        $sheet->setCellValue('A3', 'NIK');
        $sheet->setCellValue('A4', 'Nama Karyawan');
        $sheet->setCellValue('A5', 'Department');
        $sheet->setCellValue('A6', 'Posision');
        $sheet->setCellValue('A7', 'Cabang');
        $sheet->setCellValue('A8', 'Shio');
        $sheet->setCellValue('A9', 'Elemen');

        $sheet->setCellValue('C3', ':');
        $sheet->setCellValue('C4', ':');
        $sheet->setCellValue('C5', ':');
        $sheet->setCellValue('C6', ':');
        $sheet->setCellValue('C7', ':');
        $sheet->setCellValue('C8', ':');
        $sheet->setCellValue('C9', ':');

        $sheet->setCellValue('D3', $users->nik_karyawan);
        $sheet->setCellValue('D4', $users->nama_lengkap);
        $sheet->setCellValue('D5', $users->name_department);
        $sheet->setCellValue('D6', $users->name_posisi);
        $sheet->setCellValue('D7', $users->nama_cabang);
        $sheet->setCellValue('D8', $users->shio);
        $sheet->setCellValue('D9', $users->elemen);

        $sheet->mergeCells('A1:O1');
        $sheet->mergeCells('A3:B3');
        $sheet->mergeCells('A4:B4');
        $sheet->mergeCells('A5:B5');
        $sheet->mergeCells('A6:B6');
        $sheet->mergeCells('A7:B7');
        $sheet->mergeCells('A8:B8');
        $sheet->mergeCells('A9:B9');

        $sheet->mergeCells('D3:G3');
        $sheet->mergeCells('D4:G4');
        $sheet->mergeCells('D5:G5');
        $sheet->mergeCells('D6:G6');
        $sheet->mergeCells('D7:G7');
        $sheet->mergeCells('D8:G8');
        $sheet->mergeCells('D9:G9');
        $spreadsheet->getActiveSheet()->getStyle('A3:G9')->applyFromArray($datapribadi);

        // // =========================================== IST ============================================================
        $i = 11;
        if (!empty($ist)) {
            // Header untuk tabel PENILAIAN IST
            $sheet->setCellValue('B' . $i, 'PENILAIAN IST');
            $sheet->mergeCells('B' . $i . ':O' . $i);
            $spreadsheet->getActiveSheet()->getStyle('B' . $i . ':O' . $i)->applyFromArray($title2);

            $i += 2;
            $sheet->setCellValue('D' . $i, 'No');
            $sheet->setCellValue('E' . $i, 'Kategori');
            $sheet->setCellValue('H' . $i, 'Benar');
            $sheet->setCellValue('I' . $i, 'Salah');
            $sheet->setCellValue('J' . $i, 'Persentase');
            $sheet->mergeCells('E' . $i . ':G' . $i);
            $sheet->mergeCells('J' . $i . ':K' . $i);
            $sheet->getStyle('D' . $i)->applyFromArray($titlecolumn);
            $sheet->getStyle('E' . $i)->applyFromArray($titlecolumn);
            $sheet->getStyle('H' . $i)->applyFromArray($titlecolumn);
            $sheet->getStyle('I' . $i)->applyFromArray($titlecolumn);
            $sheet->getStyle('J' . $i)->applyFromArray($titlecolumn);
            $spreadsheet->getActiveSheet()->getStyle('D' . $i . ':K' . $i)->applyFromArray($styleBorder);

            $no = 1;
            $j = $i + 1;

            foreach ($ist as $data) {

                // Menggunakan $i - 10 untuk menghitung nomor
                $sheet->setCellValue('D' . $j, $no++);

                // Merge sel pada kolom Kategori
                $sheet->mergeCells('E' . $j . ':G' . $j);
                $sheet->mergeCells('J' . $j . ':K' . $j);

                // Mengisi data
                $kategori = str_replace('_', ' ', $data->kategori);
                $kategori = ucwords($kategori);

                $sheet->setCellValue('E' . $j, $kategori);
                $sheet->setCellValue('H' . $j, $data->benar);
                $sheet->setCellValue('I' . $j, $data->salah);
                $sheet->setCellValue('J' . $j, $data->persentase . " %");

                // Ubah gaya sel untuk data

                $sheet->getStyle('D' . $j)->applyFromArray($datatable);
                $sheet->getStyle('H' . $j . ':J' . $j)->applyFromArray($datatable);
                $sheet->getStyle('D' . $j . ':J' . $j)->applyFromArray($datatable);
                $sheet->getStyle('E' . $j . ':G' . $j)->applyFromArray($dataleft);
                $spreadsheet->getActiveSheet()->getStyle('D' . $j . ':K' . $j)->applyFromArray($styleBorder);
                $j++;
            }
            $i = $j;
        }

        // // // ============================================== KOSTICK PAPI =========================================================
        if (!empty($papi_kostick)) {
            $a = $i + 2;
            $sheet->setCellValue('B' . $a, 'PENILAIAN KOSTICK PAPI');
            $sheet->mergeCells('B' . $a . ':O' . $a);
            $spreadsheet->getActiveSheet()->getStyle('B' . $a . ':O' . $a)->applyFromArray($title2);

            $l = $a + 2;
            $sheet->setCellValue('B' . $l, 'Sikap & Gaya Kerja');
            $sheet->setCellValue('G' . $l, 'Code');
            $sheet->setCellValue('H' . $l, 'Score');
            $sheet->setCellValue('I' . $l, 'Interpretasi');
            $sheet->mergeCells('B' . $l . ':F' . $l);
            $sheet->mergeCells('I' . $l . ':O' . $l);

            $spreadsheet->getActiveSheet()->getStyle('B' . $l . ':I' . $l)->applyFromArray($titlecolumn);
            $spreadsheet->getActiveSheet()->getStyle('B' . $l . ':I' . $l)->getAlignment()->setWrapText(true);
            $spreadsheet->getActiveSheet()->getStyle('B' . $l . ':O' . $l)->applyFromArray($styleBorder);

            $m = $l + 1;
            foreach ($papi_kostick as $data) {
                $roleProperCase = ucwords($data->role);
                $interprestasionProperCase = ucwords($data->interprestation);

                $sheet->setCellValue('B' . $m, $roleProperCase);
                $sheet->setCellValue('G' . $m, $data->code);
                $sheet->setCellValue('H' . $m, $data->value);
                $sheet->setCellValue('I' . $m, $interprestasionProperCase);

                $sheet->getStyle('G' . $m)->applyFromArray($datatable);
                $sheet->getStyle('G' . $m)->getAlignment()->setWrapText(true);
                $sheet->getStyle('H' . $m)->applyFromArray($datatable);
                $sheet->getStyle('H' . $m)->getAlignment()->setWrapText(true);

                // Merge cells for 'Role' and 'Interpretasi' columns
                $sheet->mergeCells("B$m:F$m");
                $sheet->mergeCells("I$m:O$m");
                $spreadsheet->getActiveSheet()->getStyle('B' . $m . ':O' . $m)->applyFromArray($styleBorder);
                $m++;
            }
            $i = $m;
        }

        // // // ============================================== DISCN =========================================================
        if (!empty($result_disc)) {
            $b = $i + 2;
            $sheet->setCellValue('B' . $b, 'PENILAIAN D.I.S.C.N');
            $sheet->mergeCells('B' . $b . ':O' . $b);
            $spreadsheet->getActiveSheet()->getStyle('B' . $b . ':O' . $b)->applyFromArray($title2);

            $o = $b + 3;
            $sheet->setCellValue('B' . $o, 'Type');
            $sheet->mergeCells('B' . $o . ':B' . ($o + 1));
            $sheet->setCellValue('C' . $o, 'Graph I');
            $sheet->setCellValue('D' . $o, 'Graph II');
            $sheet->setCellValue('E' . $o, 'Graph III');
            $sheet->setCellValue('C' . ($o + 1), 'Most');
            $sheet->setCellValue('D' . ($o + 1), 'Least');
            $sheet->setCellValue('E' . ($o + 1), 'Change');
            $sheet->getStyle('B' . $o . ':E' . ($o + 1))->applyFromArray($titlecolumn);
            $spreadsheet->getActiveSheet()->getStyle('B' . $o . ':E' . ($o + 1))->applyFromArray($styleBorder);

            $p = $o + 2; // Initialize the p variable

            $types = ['D', 'I', 'S', 'C', 'N'];

            foreach ($types as $type) {
                $sheet->setCellValue('B' . $p, $type);
                $sheet->getStyle('B' . $p)->applyFromArray($titlecolumn);

                $col = 'C';
                $data = $result_disc[$type];
                foreach ($data as $key => $value) {
                    $sheet->setCellValue($col . $p, $value);
                    $sheet->getStyle($col . $p)->applyFromArray($datatable);
                    $col++;
                }
                $p++;
            }

            $endp = $p - 1;

            // Apply the border style to the entire table
            $spreadsheet->getActiveSheet()->getStyle('B' . ($o + 2) . ':E' . $endp)->applyFromArray($styleBorder);
        }


        // // //===================================================== GRAFIK ================================================
        if (!empty($result_disc)) {
            $g = $i + 4;
            // dd($g);
            $sheet->setCellValue('H' . $g, 'Grafik');
            $sheet->mergeCells('H' . $g . ':L' . $g);
            $sheet->getStyle('H' . $g . ':L' . $g)->applyFromArray($titlecolumn);

            $img = str_replace('data:image/png;base64,', '', $request->chart);
            $file = base64_decode($img);
            $safeName = $request->id_user . '_.png';
            $destinationPath = public_path() . '/recruitment/foto/';
            $success = file_put_contents($destinationPath . $safeName, $file);
            $filePath = public_path() . '/recruitment/foto/' . $safeName;
            $pe = $g + 1;
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Paid');
            $drawing->setDescription('Paid');
            $drawing->setPath(public_path() . '/recruitment/foto/' . $safeName); /* put your path and image here */
            $drawing->setCoordinates('I' . $pe);
            $drawing->getShadow()->setVisible(true);
            $drawing->getShadow()->setDirection(45);
            $drawing->setWorksheet($spreadsheet->getActiveSheet());

            $i = $i + 14;
        }

        // // // // ============================================== PENILAIAN INTERPRETASI D.I.S.C.N =========================================================

        if (!empty($disc)) {
            $e = $i + 2;
            $sheet->setCellValue('B' . $e, 'PENILAIAN INTERPRETASI D.I.S.C.N');
            $sheet->mergeCells('B' . $e . ':O' . $e);
            $spreadsheet->getActiveSheet()->getStyle('B' . $e . ':O' . $e)->applyFromArray($title2);
            $i = $e;
        }

        // // // // ============================================== Gambaran Karakter =======================================================================

        if (!empty($disc)) {
            $f = $i + 2;
            $sheet->setCellValue('B' . $f, 'Gambaran Karakter');
            $sheet->mergeCells('B' . $f . ':F' . $f);
            $sheet->getStyle('B' . $f . ':F' . $f)->applyFromArray($title3);

            $t = $f + 1;


            // dd($disc); 
            $data = $disc[0];
            $pattern = $data[1]->pattern;
            $behaviors = explode(',', $data[1]->behaviour);

            $sheet->setCellValue('B' . $t, 'Kepribadian di Muka Umum');
            $t++;

            $sheet->setCellValue('B' . $t, $pattern);
            $sheet->getStyle('B' . $t)->applyFromArray($bev);
            $t++;

            foreach ($behaviors as $index => $behavior) {
                $sheet->mergeCells('B' . $t . ':D' . $t);
                $sheet->setCellValue('B' . $t, ($index + 1) . '. ' . $behavior);
                // Menghapus wrapText style
                $sheet->getStyle('B' . $t)->getAlignment()->setWrapText(false);
                $t++;
            }


            $s = $t + 1;

            $data = $disc[1];
            if ($data[1] != null) {
                $pattern = $data[1]->pattern;
                $behaviors = explode(',', $data[1]->behaviour);

                $sheet->setCellValue('B' . $s, 'Kepribadian saat mendapat tekanan');
                $s++;

                $sheet->setCellValue('B' . $s, $pattern);
                $sheet->getStyle('B' . $s)->applyFromArray($bev);
                $s++;

                foreach ($behaviors as $index => $behavior) {
                    $sheet->mergeCells('B' . $s . ':D' . $s);
                    $sheet->setCellValue('B' . $s, ($index + 1) . '. ' . $behavior);
                    // Menghapus wrapText style
                    $sheet->getStyle('B' . $s)->getAlignment()->setWrapText(false);
                    $s++;
                }
            }

            $u = $s + 1;

            $data = $disc[2];
            $pattern = $data[1]->pattern;
            $behaviors = explode(',', $data[1]->behaviour);

            $sheet->setCellValue('B' . $u, 'Kepribadian asli yang tersembunyi');
            $u++;

            $sheet->setCellValue('B' . $u, $pattern);
            $sheet->getStyle('B' . $u)->applyFromArray($bev);
            $u++;

            foreach ($behaviors as $index => $behavior) {
                $sheet->mergeCells('B' . $u . ':D' . $u);
                $sheet->setCellValue('B' . $u, ($index + 1) . '. ' . $behavior);
                // Menghapus wrapText style
                $sheet->getStyle('B' . $u)->getAlignment()->setWrapText(false);
                $u++;
            }
        }


        // //     // // ============================================== DESKRIPSI KEPRIBADIAN ===================================================================
        if (!empty($disc)) {
            $s = $i + 3;

            $sheet->setCellValue('H' . $s, 'Deskripsi Kepribadian');
            $sheet->mergeCells('H' . $s . ':O' . $s);
            $sheet->getStyle('H' . $s . ':O' . $s)->applyFromArray($title3);
            $sheet->getStyle('H' . $s . ':O' . $s)->getAlignment()->setWrapText(true);

            $data = $disc[2];
            $description = $data[1]->description;

            $sheet->setCellValue('H' . ($s + 1), $description);
            $sheet->mergeCells('H' . ($s + 1) . ':O' . ($s + 10));

            $sheet->getStyle('H' . ($s + 1) . ':O' . ($s + 10))->getAlignment()->setWrapText(true);
            // $i = $s;
        }

        // //     // // ============================================== JOB MATCH ===============================================================================
        if (!empty($disc)) {
            $k = $i + 15;
            $sheet->setCellValue('H' . $k, 'Job Match');
            $sheet->mergeCells('H' . $k . ':O' . $k);
            $sheet->getStyle('H' . $k . ':O' . $k)->applyFromArray($title3);

            $data = $disc[2];
            $jobs = explode(',', $data[1]->jobs);
            foreach ($jobs as $index => $job) {
                $sheet->mergeCells('H' . ($k + 1) . ':O' . ($k + 1));
                $sheet->setCellValue('H' . ($k + 1), ($index + 1) . '. ' . $job);
                $k++;
            }
            $i = $k;
        }


        // // // // ============================================== PENILAIAN Yearly Employee Satisfaction ===============================================================================
        if (!$employ_satisfaction->isEmpty()) {
            $h = $i + 2;
            $sheet->setCellValue('B' . $h, 'PENILAIAN YEARLY EMPLOYEE SATISFACTION');
            $sheet->mergeCells('B' . $h . ':O' . $h);
            $sheet->getStyle('B' . $h . ':O' . $h)->applyFromArray($title2);

            // TABLE
            $t = $h + 2;
            $sheet->setCellValue('F' . $t, 'Total Soal');
            $sheet->setCellValue('H' . $t, 'Total Jawaban');
            $sheet->setCellValue('J' . $t, 'Persentase');

            $sheet->mergeCells('F' . $t . ':G' . $t);
            $sheet->mergeCells('H' . $t . ':I' . $t);
            $sheet->mergeCells('J' . $t . ':K' . $t);
            $spreadsheet->getActiveSheet()->getStyle('F' . $t . ':K' . $t)->applyFromArray($titlecolumn);


            // DATA TABLE
            $y = $t + 1;
            $data = $employ_satisfaction[0];
            $sheet->setCellValue('F' . $y, $data->total_soal);
            $sheet->setCellValue('H' . $y, $data->total_jawaban);
            $sheet->setCellValue('J' . $y, $data->persentase);

            $sheet->mergeCells('F' . $y . ':G' . $y);
            $sheet->mergeCells('H' . $y . ':I' . $y);
            $sheet->mergeCells('J' . $y . ':K' . $y);

            $spreadsheet->getActiveSheet()->getStyle('F' . $y . ':K' . $y)->applyFromArray($datatable);
            $spreadsheet->getActiveSheet()->getStyle('F' . $t . ':K' . $y)->applyFromArray($styleBorder);
            $i = $y;
        }

        // // // // // ============================================== PENILAIAN Yearly Employee Evaluation ===============================================================================
        if (!$employ_evaluation->isEmpty()) {
            $u = $i + 2;
            $sheet->setCellValue('B' . $u, 'PENILAIAN YEARLY EMPLOYEE EVALUATION');
            $sheet->mergeCells('B' . $u . ':O' . $u);
            $sheet->getStyle('B' . $u . ':O' . $u)->applyFromArray($title2);

            // TABLE HEADER
            $headerRow = $u + 1;
            $sheet->setCellValue('E' . $headerRow, 'No');
            $sheet->setCellValue('F' . $headerRow, 'Total Soal');
            $sheet->setCellValue('H' . $headerRow, 'Nama Atasan');
            $sheet->setCellValue('J' . $headerRow, 'Total Jawaban');
            $sheet->setCellValue('L' . $headerRow, 'Persentase');


            // Merge cells for header columns
            $sheet->mergeCells('F' . $headerRow . ':G' . $headerRow);
            $sheet->mergeCells('H' . $headerRow . ':I' . $headerRow);
            $sheet->mergeCells('J' . $headerRow . ':K' . $headerRow);
            $sheet->mergeCells('L' . $headerRow . ':M' . $headerRow);

            $spreadsheet->getActiveSheet()->getStyle('E' . $headerRow . ':M' . $headerRow)->applyFromArray($titlecolumn);


            // DATA TABLE
            $nomorAwal = 1; // Inisialisasi nomor awal
            $nomorBaris = $u + 2; // Inisialisasi nomor baris
            $totalPersentase = 0; // Inisialisasi total persentase
            foreach ($employ_evaluation as $index => $item) {
                $sheet->setCellValue('E' . $nomorBaris, $nomorAwal); // Menambahkan nomor awal
                $sheet->setCellValue('F' . $nomorBaris, $item->jumlah_soal);
                $sheet->setCellValue('H' . $nomorBaris, $item->nama_lengkap);
                $sheet->setCellValue('J' . $nomorBaris, $item->total_jawaban);
                $sheet->setCellValue('L' . $nomorBaris, number_format($item->nilai, 2) . ' %');

                // Menggabungkan sel untuk data tabel
                $spreadsheet->getActiveSheet()->mergeCells('E' . $nomorBaris . ':E' . $nomorBaris);
                $spreadsheet->getActiveSheet()->mergeCells('F' . $nomorBaris . ':G' . $nomorBaris);
                $spreadsheet->getActiveSheet()->mergeCells('H' . $nomorBaris . ':I' . $nomorBaris);
                $spreadsheet->getActiveSheet()->mergeCells('J' . $nomorBaris . ':K' . $nomorBaris);
                $spreadsheet->getActiveSheet()->mergeCells('L' . $nomorBaris . ':M' . $nomorBaris);

                $totalPersentase += $item->nilai; // Menambahkan ke total persentase
                $nomorAwal++;
                $nomorBaris++;
            }
            $sheet->setCellValue('E' . $nomorBaris, 'Total Persentase : ');
            $sheet->setCellValue('L' . $nomorBaris, number_format($totalPersentase / count($employ_evaluation), 2) . ' %');
            $sheet->mergeCells('E' . $nomorBaris . ':K' . $nomorBaris);
            $spreadsheet->getActiveSheet()->mergeCells('L' . $nomorBaris . ':M' . $nomorBaris);
            $spreadsheet->getActiveSheet()->getStyle('E' . $nomorBaris . ':K' . $nomorBaris)->applyFromArray($ttdata);
            $spreadsheet->getActiveSheet()->getStyle('E' . $headerRow . ':M' . $nomorBaris)->applyFromArray($styleBorder);

            $i = $nomorBaris;
        }


        // // // // ============================================== PENILAIAN YEARLY SATISFACTION OF LEADER ========================================================
        if (!$satisfaction_leader->isEmpty()) {
            $v = $i + 2;
            $sheet->setCellValue('B' . $v, 'PENILAIAN YEARLY SATISFACTION OF LEADER');
            $sheet->mergeCells('B' . $v . ':O' . $v);
            $sheet->getStyle('B' . $v . ':O' . $v)->applyFromArray($title2);

            // TABLE HEADER
            $headerRow = $v + 1;
            $sheet->setCellValue('E' . $headerRow, 'No');
            $sheet->setCellValue('F' . $headerRow, 'Total Soal');
            $sheet->setCellValue('H' . $headerRow, 'Nama Bawahan');
            $sheet->setCellValue('J' . $headerRow, 'Total Jawaban');
            $sheet->setCellValue('L' . $headerRow, 'Persentase');


            // Merge cells for header columns
            $sheet->mergeCells('F' . $headerRow . ':G' . $headerRow);
            $sheet->mergeCells('H' . $headerRow . ':I' . $headerRow);
            $sheet->mergeCells('J' . $headerRow . ':K' . $headerRow);
            $sheet->mergeCells('L' . $headerRow . ':M' . $headerRow);

            // Apply styles to header cells
            $spreadsheet->getActiveSheet()->getStyle('E' . $headerRow . ':M' . $headerRow)->applyFromArray($titlecolumn);

            $nomorAwal = 1; // Inisialisasi nomor awal
            $nomorBaris = $v + 2; // Inisialisasi nomor baris
            $totalPersentase = 0; // Inisialisasi total persentase

            foreach ($satisfaction_leader as $index => $item) {
                $sheet->setCellValue('E' . $nomorBaris, $nomorAwal); // Menambahkan nomor awal
                $sheet->setCellValue('F' . $nomorBaris, $item->jumlah_soal);
                $sheet->setCellValue('H' . $nomorBaris, $item->nama_lengkap);
                $sheet->setCellValue('J' . $nomorBaris, $item->total_jawaban);
                $sheet->setCellValue('L' . $nomorBaris, number_format($item->nilai, 2) . ' %');

                // Menggabungkan sel untuk data tabel
                $spreadsheet->getActiveSheet()->mergeCells('E' . $nomorBaris . ':E' . $nomorBaris);
                $spreadsheet->getActiveSheet()->mergeCells('F' . $nomorBaris . ':G' . $nomorBaris);
                $spreadsheet->getActiveSheet()->mergeCells('H' . $nomorBaris . ':I' . $nomorBaris);
                $spreadsheet->getActiveSheet()->mergeCells('J' . $nomorBaris . ':K' . $nomorBaris);
                $spreadsheet->getActiveSheet()->mergeCells('L' . $nomorBaris . ':M' . $nomorBaris);

                $totalPersentase += $item->nilai; // Menambahkan ke total persentase
                $nomorAwal++;
                $nomorBaris++;
            }
            $sheet->setCellValue('E' . $nomorBaris, 'Total Persentase: ');
            $spreadsheet->getActiveSheet()->getStyle('E' . $nomorBaris . ':K' . $nomorBaris)->applyFromArray($ttdata);
            $spreadsheet->getActiveSheet()->getStyle('E' . $headerRow . ':M' . $nomorBaris)->applyFromArray($styleBorder);
            $sheet->mergeCells('E' . $nomorBaris . ':K' . $nomorBaris);
            $sheet->mergeCells('L' . $nomorBaris . ':M' . $nomorBaris);
            // Set total persentase value
            $sheet->setCellValue('L' . $nomorBaris, number_format($totalPersentase / count($satisfaction_leader), 2) . ' %');

            $i = $nomorBaris;
        }


        // // // // ============================================== PENILAIAN Yearly Management Evaluation ==============================================

        // $w = $v + 10;
        if (!$management_evaluation->isEmpty()) {
            $w = $i + 2;
            $sheet->setCellValue('B' . $w, 'PENILAIAN YEARLY MANAGEMENT EVALUATION');
            $sheet->mergeCells('B' . $w . ':O' . $w);
            $sheet->getStyle('B' . $w . ':O' . $w)->applyFromArray($title2);

            // TABLE HEADER
            $headerRow = $w + 1;
            $sheet->setCellValue('E' . $headerRow, 'No');
            $sheet->setCellValue('F' . $headerRow, 'Total Soal');
            $sheet->setCellValue('H' . $headerRow, 'Nama Management');
            $sheet->setCellValue('J' . $headerRow, 'Total Jawaban');
            $sheet->setCellValue('L' . $headerRow, 'Persentase');

            // Merge cells for header columns
            $sheet->mergeCells('F' . $headerRow . ':G' . $headerRow);
            $sheet->mergeCells('H' . $headerRow . ':I' . $headerRow);
            $sheet->mergeCells('J' . $headerRow . ':K' . $headerRow);
            $sheet->mergeCells('L' . $headerRow . ':M' . $headerRow);

            // Apply styles to header cells
            $spreadsheet->getActiveSheet()->getStyle('E' . $headerRow . ':M' . $headerRow)->applyFromArray($titlecolumn);


            // DATA TABLE
            $nomorAwal = 1; // Inisialisasi nomor awal
            $nomorBaris = $w + 2; // Inisialisasi nomor baris
            $totalPersentase = 0; // Inisialisasi total persentase

            foreach ($management_evaluation as $index => $item) {
                $sheet->setCellValue('E' . $nomorBaris, $nomorAwal); // Menambahkan nomor awal
                $sheet->setCellValue('F' . $nomorBaris, $item->jumlah_soal);
                $sheet->setCellValue('H' . $nomorBaris, $item->nama_lengkap);
                $sheet->setCellValue('J' . $nomorBaris, $item->total_jawaban);
                $sheet->setCellValue('L' . $nomorBaris, number_format($item->nilai, 2) . ' %');

                // Menggabungkan sel untuk data tabel
                $spreadsheet->getActiveSheet()->mergeCells('E' . $nomorBaris . ':E' . $nomorBaris);
                $spreadsheet->getActiveSheet()->mergeCells('F' . $nomorBaris . ':G' . $nomorBaris);
                $spreadsheet->getActiveSheet()->mergeCells('H' . $nomorBaris . ':I' . $nomorBaris);
                $spreadsheet->getActiveSheet()->mergeCells('J' . $nomorBaris . ':K' . $nomorBaris);
                $spreadsheet->getActiveSheet()->mergeCells('L' . $nomorBaris . ':M' . $nomorBaris);

                $totalPersentase += $item->nilai; // Menambahkan ke total persentase
                $nomorAwal++;
                $nomorBaris++;
            }
            $sheet->setCellValue('E' . $nomorBaris, 'Total Persentase: ');
            $spreadsheet->getActiveSheet()->getStyle('E' . $nomorBaris . ':K' . $nomorBaris)->applyFromArray($ttdata);
            $spreadsheet->getActiveSheet()->getStyle('E' . $headerRow . ':M' . $nomorBaris)->applyFromArray($styleBorder);
            $sheet->mergeCells('E' . $nomorBaris . ':K' . $nomorBaris);
            $sheet->mergeCells('L' . $nomorBaris . ':M' . $nomorBaris);
            // Set total persentase value
            $sheet->setCellValue('L' . $nomorBaris, number_format($totalPersentase / count($management_evaluation), 2) . ' %');

            // Menggabungkan sel untuk total persentase
        }
        // =========================================================================================================================================================================
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LEGAL);
        $writer = new Xlsx($spreadsheet);
        $fileName = $users->nama_lengkap . ".xlsx";
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($fileName);
        return response()->json([
            'message' => 'Export Excel Berhasil',
            'link' => $fileName
        ], 200);
    }
}