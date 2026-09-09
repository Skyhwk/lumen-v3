<?php

namespace App\Http\Controllers\api;

use App\Models\MesinAbsen;
use App\Models\Absensi;
use App\Models\Rfid;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use App\Models\ShiftKaryawan;
use App\Models\RekapLiburKalender;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;



class AbsensiController extends Controller
{
    private $workingDayIndexCache = [];

    // Tested - Clear 
    public function hari($tanggal)
    {
        $hari = date("D", strtotime($tanggal));

        switch ($hari) {
            case 'Sun':
                $hari_ini = "Minggu";
                break;

            case 'Mon':
                $hari_ini = "Senin";
                break;

            case 'Tue':
                $hari_ini = "Selasa";
                break;

            case 'Wed':
                $hari_ini = "Rabu";
                break;

            case 'Thu':
                $hari_ini = "Kamis";
                break;

            case 'Fri':
                $hari_ini = "Jumat";
                break;

            case 'Sat':
                $hari_ini = "Sabtu";
                break;

            default:
                $hari_ini = "Tidak di ketahui";
                break;
        }

        return $hari_ini;

    }
    // Tested - Clear
    public function SelectDivisi(Request $request)
    {
        $cek = MasterDivisi::where('id', $request->departement)->where('is_active', true)->first();
        if ($cek->nama_divisi == 'HRD') {
            $data = MasterDivisi::where('is_active', true)->get();
        } else {
            $data = MasterDivisi::where('id', $request->departement)->where('is_active', true)->get();
        }

        return response()->json([
            'data' => $data
        ], 200);
    }
    // Tested - Clear
    public function SelectUserbyDivisi(Request $request)
    {
        $data = MasterKaryawan::with('jabatan', 'divisi', 'rekap')
            ->whereIn('id_cabang', $this->privilageCabang)
            ->where('is_active', true);

        if ($request->id_jabatan) {
            $data->where('id_jabatan', $request->id_jabatan);
        } else if ($request->departement && $request->departement !== 'all') {
            $data->where('id_department', (int) $request->departement);
        }

        $data = $data->get();

        return datatables()->of($data)->make(true);
    }
    public function SelectUserbyDivisiShift(Request $request)
    {
        $data = MasterKaryawan::with('jabatan', 'divisi', 'rekap')
            ->whereIn('id_cabang', $this->privilageCabang)
            ->where('is_active', true);

        if ($request->departement && $request->departement !== 'all') {
            $data->where('id_department', $request->departement);
            if ($request->id_jabatan && $request->id_jabatan !== 'all') {
                $data->where('id_jabatan', $request->id_jabatan);
            }
        }

        $data = $data->get();

        return datatables()->of($data)->make(true);
    }
    // Tested - Clear
    public function generateAbsen(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');

        if ($request->mode == 'daily') {
            if (isset($request->tanggal)) {
                if ($request->tanggal != null || $request->tanggal != '') {
                    $db = DATE('Y', \strtotime($request->tanggal));
                } else {
                    $db = $this->db;
                }
            } else {
                $db = $this->db;
            }


            $tanggal = $request->tanggal;
            $data = [];

            $cekKaryawan = MasterKaryawan::where('is_active', true)
                ->whereIn('id_cabang', $this->privilageCabang);

            if ($request->departement && $request->departement !== 'all') {
                $cekKaryawan->where('id_department', $request->departement);
            }

            $cekKaryawan = $cekKaryawan->get();

            if (!$cekKaryawan->isEmpty()) {

                foreach ($cekKaryawan as $key => $value) {
                    $cekShift = ShiftKaryawan::where('tanggal', $tanggal)->where('karyawan_id', $value->id)->first();

                    if ($cekShift != null) {
                        $init = self::compareshift($value->id, $tanggal, $cekShift->shift, $cekShift->time_in, $cekShift->time_out, $value->nik_karyawan, $value->nama_lengkap);
                        $data[] = $init;
                    } else {
                        $gen = Absensi::select(
                            'absensi.tanggal', // Assuming tanggal is from Absensi
                            'master_karyawan.nik_karyawan',
                            'master_karyawan.nama_lengkap',
                            \DB::raw("CASE WHEN MIN(jam) <= '14:00:00' THEN MIN(jam) ELSE '' END as masuk"),
                            \DB::raw("CASE WHEN MAX(jam) > '14:00:00' THEN MAX(jam) ELSE '' END as keluar")
                        )
                            ->join('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id') // Adjust the join condition accordingly
                            ->where('absensi.karyawan_id', $value->id)
                            ->where('absensi.tanggal', $tanggal)
                            ->groupBy('absensi.tanggal', 'master_karyawan.nik_karyawan', 'master_karyawan.nama_lengkap')
                            ->first();

                        if ($gen != null) {
                            if ($gen->masuk < '08:00:00') {
                                // Calculate difference when arriving early
                                $selisih_masuk = date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' 08:00:00'));
                                $masuk = '+' . (int) (($selisih_masuk->h * 3600 + $selisih_masuk->i * 60 + $selisih_masuk->s) / 60) . 'm';
                            } else {
                                // Calculate difference when arriving late
                                $selisih_masuk = date_diff(date_create($gen->tanggal . ' 08:00:00'), date_create($gen->tanggal . ' ' . $gen->masuk));
                                $masuk = '-' . (int) (($selisih_masuk->h * 3600 + $selisih_masuk->i * 60 + $selisih_masuk->s) / 60) . 'm';
                            }

                            $total_jam_kerja = '0h 0m'; // Default value
                            if ($gen->keluar != '') {
                                $kerja = date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' ' . $gen->keluar));
                                $total_jam_kerja = $kerja->h . 'h ' . $kerja->i . 'm'; // Calculate working hours
                            }

                            $data[] = $this->applyCalendarLiburShift([
                                'nama' => $gen->nik_karyawan . ' - ' . $gen->nama_lengkap,
                                'tanggal' => $gen->tanggal,
                                'hari' => self::hari($gen->tanggal),
                                'masuk' => $gen->masuk,
                                'keluar' => $gen->keluar,
                                'selisih' => $masuk,
                                'jam_kerja' => $total_jam_kerja,
                                'shift' => 'SHREGULAR'
                            ]);
                        } else {
                            $data[] = $this->applyCalendarLiburShift([
                                'nama' => $value->nik_karyawan . ' - ' . $value->nama_lengkap,
                                'tanggal' => $tanggal,
                                'hari' => self::hari($tanggal),
                                'masuk' => '',
                                'keluar' => '',
                                'selisih' => '',
                                'jam_kerja' => '',
                                'shift' => ''
                            ]);
                        }
                    }
                }
            } else {
                $data = [];
            }
            return response()->json([
                'data' => $data
            ], 200);

        } else if ($request->mode == 'monthly') {
            $periode = self::parseBulanAbsensi($request->bulan, $request->tanggal);
            if ($periode === null) {
                return response()->json([
                    'message' => 'Format bulan tidak valid. Gunakan format YYYY-MM.'
                ], 422);
            }

            if (!$request->id) {
                return response()->json([
                    'message' => 'Karyawan belum dipilih.'
                ], 422);
            }

            $cekKaryawan = MasterKaryawan::where('id', $request->id)->first();
            if ($cekKaryawan === null) {
                return response()->json([
                    'message' => 'Data karyawan tidak ditemukan.'
                ], 404);
            }

            $month = $periode['month'];
            $year = $periode['year'];
            $lastDay = cal_days_in_month(CAL_GREGORIAN, (int) $month, (int) $year);

            $data = self::buildMonthlyAbsensiData(
                $cekKaryawan->id,
                $year,
                $month,
                $lastDay,
                $cekKaryawan->nik_karyawan,
                $cekKaryawan->nama_lengkap
            );

            return response()->json([
                'data' => $data
            ], 200);
        }
    }
    // Tested - Clear
    public function compareshift($id, $tanggal, $shift, $checkin = '08:00:00', $checkout = '17:00:00', $nik_karyawan, $nama_lengkap)
    {
        $db = ($tanggal != null && $tanggal != '') ? DATE('Y', \strtotime($tanggal)) : $this->db;
        $tanggalKey = date('Y-m-d', strtotime($tanggal));
        $workingDayIndex = $this->fetchWorkingDayIndex(date('Y', strtotime($tanggalKey)));

        if (!empty($workingDayIndex) && !isset($workingDayIndex[$tanggalKey])) {
            return $this->buildLiburAbsensiRow($nik_karyawan, $nama_lengkap, $tanggalKey);
        }

        if ($shift == '24jam') {
            $plus = DATE('Y-m-d', strtotime($tanggal . '+1day'));

            $gen = DB::select("select master_karyawan.nik_karyawan, master_karyawan.nama_lengkap, absensi.tanggal, MIN(jam) as masuk, CASE WHEN (SELECT min(jam) from absensi WHERE absensi.tanggal = '$plus' AND absensi.karyawan_id = '$id' group by absensi.tanggal) < '14:00:00' THEN (SELECT min(jam) from absensi WHERE absensi.tanggal = '$plus' AND absensi.karyawan_id = '$id' group by absensi.tanggal) ELSE '' END as keluar FROM absensi LEFT JOIN master_karyawan ON absensi.absensi.karyawan_id = master_karyawan.id WHERE absensi.tanggal = '$tanggal' AND absensi.karyawan_id = '$id' GROUP BY absensi.tanggal;");

            if ($gen != null) {
                $gen = $gen[0];
                if ($gen->masuk < DATE('H:i:s', strtotime($checkin))) {
                    $selisih_masuk = \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' ' . DATE('H:i:s', strtotime($checkin))));
                    $masuk = '+' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                } else {
                    $selisih_masuk = \date_diff(date_create($gen->tanggal . ' ' . DATE('H:i:s', strtotime($checkin))), date_create($gen->tanggal . ' ' . $gen->masuk));
                    $masuk = '-' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                }

                $total_jam_kerja = '';
                if ($gen->keluar != '') {
                    $kerja = \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' ' . $gen->keluar));
                    $total_jam_kerja = $kerja->h . 'h ' . $kerja->i . 'm';
                }
                $data = [
                    'nama' => $gen->nik_karyawan . ' - ' . $gen->nama_lengkap,
                    'tanggal' => $gen->tanggal,
                    'hari' => self::hari($gen->tanggal),
                    'masuk' => $gen->masuk,
                    'keluar' => $gen->keluar,
                    'selisih' => $masuk,
                    'jam_kerja' => $total_jam_kerja,
                    'shift' => '24JAM'
                ];
            } else {
                $data = [
                    'nama' => $nik_karyawan . ' - ' . $nama_lengkap,
                    'tanggal' => $tanggal,
                    'hari' => self::hari($tanggal),
                    'masuk' => '',
                    'keluar' => '',
                    'selisih' => '',
                    'jam_kerja' => '',
                    'shift' => '24JAM'
                ];
            }
        } else if ($shift == 'SHSECURITY2') {
            $plus = DATE('Y-m-d', strtotime($tanggal . '+1day'));

            $gen = DB::select("select master_karyawan.nik_karyawan, master_karyawan.nama_lengkap, absensi.tanggal, MAX(jam) as masuk, CASE WHEN (SELECT min(jam) from absensi WHERE absensi.tanggal = '$plus' AND absensi.karyawan_id = '$id' group by absensi.tanggal) < '14:00:00' THEN (SELECT min(jam) from absensi WHERE absensi.tanggal = '$plus' AND absensi.karyawan_id = '$id' group by absensi.tanggal) ELSE '' END as keluar FROM absensi LEFT JOIN master_karyawan ON absensi.karyawan_id = master_karyawan.id WHERE absensi.tanggal = '$tanggal' AND absensi.karyawan_id = '$id' GROUP BY absensi.tanggal");

            if ($gen != null) {
                $gen = $gen[0];
                if ($gen->masuk < DATE('H:i:s', strtotime($checkin))) {
                    $selisih_masuk = \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' ' . DATE('H:i:s', strtotime($checkin))));
                    $masuk = '+' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                } else {
                    $selisih_masuk = \date_diff(date_create($gen->tanggal . ' ' . DATE('H:i:s', strtotime($checkin))), date_create($gen->tanggal . ' ' . $gen->masuk));
                    $masuk = '-' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                }

                $total_jam_kerja = '';
                if ($gen->keluar != '') {
                    $kerja = \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' ' . $gen->keluar));
                    $total_jam_kerja = $kerja->h . 'h ' . $kerja->i . 'm';
                }
                $waktu_masuk = $gen->masuk;
                $masuk_ = $masuk;
                if ($gen->masuk <= '14:00:00') {
                    $waktu_masuk = '';
                    $masuk_ = '';
                    $total_jam_kerja = '';
                }

                $data = [
                    'nama' => $gen->nik_karyawan . ' - ' . $gen->nama_lengkap,
                    'tanggal' => $gen->tanggal,
                    'hari' => self::hari($gen->tanggal),
                    'masuk' => $waktu_masuk,
                    'keluar' => $gen->keluar,
                    'selisih' => $masuk_,
                    'jam_kerja' => $total_jam_kerja,
                    'shift' => 'SHSECURITY2'
                ];
            } else {
                $data = [
                    'nama' => $nik_karyawan . ' - ' . $nama_lengkap,
                    'tanggal' => $tanggal,
                    'hari' => self::hari($tanggal),
                    'masuk' => '',
                    'keluar' => '',
                    'selisih' => '',
                    'jam_kerja' => '',
                    'shift' => 'SHSECURITY2'
                ];
            }
        } else if ($shift == 'SHOB2') {
            $plus = DATE('Y-m-d', strtotime($tanggal . '+1day'));

            $gen = DB::select("select master_karyawan.nik_karyawan, master_karyawan.nama_lengkap, absensi.tanggal, MIN(jam) as masuk, CASE WHEN (SELECT min(jam) from absensi WHERE absensi.tanggal = '$plus' AND absensi.karyawan_id = '$id' group by absensi.tanggal) < '14:00:00' THEN (SELECT min(jam) from absensi WHERE absensi.tanggal = '$plus' AND absensi.karyawan_id = '$id' group by absensi.tanggal) ELSE '' END as keluar FROM absensi LEFT JOIN master_karyawan ON absensi.karyawan_id = master_karyawan.id WHERE absensi.tanggal = '$tanggal' AND absensi.karyawan_id = '$id' GROUP BY absensi.tanggal;");

            if ($gen != null) {
                $gen = $gen[0];
                if ($gen->masuk < DATE('H:i:s', strtotime($checkin))) {
                    $selisih_masuk = \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' ' . DATE('H:i:s', strtotime($checkin))));
                    $masuk = '+' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                } else {
                    $selisih_masuk = \date_diff(date_create($gen->tanggal . ' ' . DATE('H:i:s', strtotime($checkin))), date_create($gen->tanggal . ' ' . $gen->masuk));
                    $masuk = '-' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                }

                $total_jam_kerja = '';
                if ($gen->keluar != '') {
                    $kerja = \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' ' . $gen->keluar));
                    $total_jam_kerja = $kerja->h . 'h ' . $kerja->i . 'm';
                }
                $data = [
                    'nama' => $gen->nik_karyawan . ' - ' . $gen->nama_lengkap,
                    'tanggal' => $gen->tanggal,
                    'hari' => self::hari($gen->tanggal),
                    'masuk' => $gen->masuk,
                    'keluar' => $gen->keluar,
                    'selisih' => $masuk,
                    'jam_kerja' => $total_jam_kerja,
                    'shift' => 'SHOB2'
                ];
            } else {
                $data = [
                    'nama' => $nik_karyawan . ' - ' . $nama_lengkap,
                    'tanggal' => $tanggal,
                    'hari' => self::hari($tanggal),
                    'masuk' => '',
                    'keluar' => '',
                    'selisih' => '',
                    'jam_kerja' => '',
                    'shift' => 'SHOB2'
                ];
            }
        } else if ($shift == 'off') {
            $data = [
                'nama' => $nik_karyawan . ' - ' . $nama_lengkap,
                'tanggal' => $tanggal,
                'hari' => self::hari($tanggal),
                'masuk' => '',
                'keluar' => '',
                'selisih' => '',
                'jam_kerja' => '',
                'shift' => 'OFF'
            ];
        } else {
            $in = DATE('H:i:s', strtotime($checkin . '+4 hours'));
            $gen = Absensi::select(
                'master_karyawan.nik_karyawan',
                'master_karyawan.nama_lengkap',
                'absensi.tanggal',
                \DB::raw("CASE WHEN MIN(jam) <= '$in' THEN MIN(jam) ELSE '' END as masuk"),
                \DB::raw("CASE WHEN MAX(jam) > '$in' THEN MAX(jam) ELSE '' END as keluar")
            )
                ->where('absensi.karyawan_id', $id)
                ->where('absensi.tanggal', $tanggal)
                ->join('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id')
                ->groupBy('absensi.tanggal', 'absensi.karyawan_id')
                ->first();
            
            if ($gen != null) {
                if ($gen->masuk < DATE('H:i:s', strtotime($checkin))) {
                    $selisih_masuk = \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' ' . DATE('H:i:s', strtotime($checkin))));
                    $masuk = '+' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                } else {
                    $selisih_masuk = \date_diff(date_create($gen->tanggal . ' ' . DATE('H:i:s', strtotime($checkin))), date_create($gen->tanggal . ' ' . $gen->masuk));
                    $masuk = '-' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                }

                $total_jam_kerja = '';
                if ($gen->keluar != '') {
                    $kerja = \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' ' . $gen->keluar));
                    $total_jam_kerja = $kerja->h . 'h ' . $kerja->i . 'm';
                }

                $waktu_masuk = $gen->masuk;
                $masuk_ = $masuk;
                if ($gen->masuk == '') {
                    $waktu_masuk = '';
                    $masuk_ = '';
                    $total_jam_kerja = '';
                }

                $data = [
                    'nama' => $gen->nik_karyawan . ' - ' . $gen->nama_lengkap,
                    'tanggal' => $gen->tanggal,
                    'hari' => self::hari($gen->tanggal),
                    'masuk' => $waktu_masuk,
                    'keluar' => $gen->keluar,
                    'selisih' => $masuk_,
                    'jam_kerja' => $total_jam_kerja,
                    'shift' => $shift
                ];
            } else {
                $jadwal = $shift;
                if ($shift == '') {
                    $jadwal = '';
                }
                $data = [
                    'nama' => $nik_karyawan . ' - ' . $nama_lengkap,
                    'tanggal' => $tanggal,
                    'hari' => self::hari($tanggal),
                    'masuk' => '',
                    'keluar' => '',
                    'selisih' => '',
                    'jam_kerja' => '',
                    'shift' => $jadwal
                ];
            }
        }
        return $data;
    }
    // Tested - Clear
    public function exportAbsenDaily(Request $request)
    {
        if ($request->export == 'single') {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            self::setupAbsensiExportSheet($sheet);

            $data = [];
            $tanggal = $request->tanggal;
            $cekKaryawan = MasterKaryawan::leftJoin('master_divisi', 'master_karyawan.id_department', '=', 'master_divisi.id')
                ->select('master_karyawan.id', 'master_karyawan.nik_karyawan', 'master_karyawan.nama_lengkap', 'master_divisi.kode_divisi', 'master_divisi.nama_divisi')
                ->where('id_department', $request->idDivisi)
                ->whereIn('master_karyawan.id_cabang', $this->privilageCabang)
                ->where('master_karyawan.is_active', true)
                ->get();

            if ($cekKaryawan->isEmpty()) {
                return response()->json(['message' => 'Data karyawan tidak ditemukan.'], 404);
            }

            $deptCode = $cekKaryawan[0]->kode_divisi;
            $dept = $cekKaryawan[0]->nama_divisi;
            $data = [];
            $tanggal = $request->tanggal;

            foreach ($cekKaryawan as $value) {
                $cekShift = ShiftKaryawan::where('tanggal', $tanggal)->where('karyawan_id', $value->id)->first();

                if ($cekShift) {
                    $data[] = self::compareshift($value->id, $tanggal, $cekShift->shift, $cekShift->time_in, $cekShift->time_out, $value->nik_karyawan, $value->nama_lengkap);
                } else {
                    $gen = Absensi::select(
                        'nik_karyawan',
                        'nama_lengkap',
                        'tanggal',
                        \DB::raw("CASE WHEN MIN(jam) <= '14:00:00' THEN MIN(jam) ELSE '' END as masuk"),
                        \DB::raw("CASE WHEN MAX(jam) > '14:00:00' THEN MAX(jam) ELSE '' END as keluar")
                    )
                        ->where('karyawan_id', $value->id)
                        ->where('tanggal', $tanggal)
                        ->join('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id')
                        ->groupBy('tanggal', 'karyawan_id')
                        ->first();

                    if ($gen) {
                        $masuk = ($gen->masuk < '08:00:00')
                            ? '+' . (int) (\date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' 08:00:00'))->h * 60 + \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' 08:00:00'))->i)
                            : '-' . (int) (\date_diff(date_create($gen->tanggal . ' 08:00:00'), date_create($gen->tanggal . ' ' . $gen->masuk))->h * 60 + \date_diff(date_create($gen->tanggal . ' 08:00:00'), date_create($gen->tanggal . ' ' . $gen->masuk))->i);

                        $total_jam_kerja = ($gen->keluar)
                            ? \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' ' . $gen->keluar))->h . 'h ' . \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' ' . $gen->keluar))->i . 'm'
                            : '';

                        $data[] = $this->applyCalendarLiburShift([
                            'nama' => $gen->nik_karyawan . ' - ' . $gen->nama_lengkap,
                            'tanggal' => $gen->tanggal,
                            'hari' => self::hari($gen->tanggal),
                            'masuk' => $gen->masuk,
                            'keluar' => $gen->keluar,
                            'selisih' => $masuk,
                            'jam_kerja' => $total_jam_kerja,
                            'shift' => 'SHREGULAR'
                        ]);
                    } else {
                        $data[] = $this->applyCalendarLiburShift([
                            'nama' => $value->nik_karyawan . ' - ' . $value->nama_lengkap,
                            'tanggal' => $tanggal,
                            'hari' => self::hari($tanggal),
                            'masuk' => '',
                            'keluar' => '',
                            'selisih' => '',
                            'jam_kerja' => '',
                            'shift' => ''
                        ]);
                    }
                }
            }

            self::writeAbsensiExportRows($sheet, $data);
            $sheet->setTitle($this->sanitizeSheetTitle($sheet, $dept, $spreadsheet));

            $path = self::getAbsensiExportPath();
            $writer = new Xlsx($spreadsheet);
            $fileName = 'Daily-Absensi_' . $deptCode . '_' . $request->tanggal . '.xlsx';
            $writer->save($path . $fileName);

            return response()->json(['data' => $fileName], 200);
        } else {
            $cekUser = MasterDivisi::where('is_active', true)->orderBy('nama_divisi')->get();

            $spreadsheet = new Spreadsheet();
            $i = 0;
            foreach ($cekUser as $key => $val) {
                if ($i > 0) {
                    $spreadsheet->createSheet();
                }
                $sheet = $spreadsheet->getSheet($i);
                self::setupAbsensiExportSheet($sheet);

                $data = [];
                $cekKaryawan = self::getExportKaryawanByDepartment($val->id);
                $tanggal = $request->tanggal;
                foreach ($cekKaryawan as $keys => $value) {
                    $cekShift = ShiftKaryawan::where('tanggal', $tanggal)->where('karyawan_id', $value->id)->first();

                    if ($cekShift != null) {
                        $init = self::compareshift($value->id, $tanggal, $cekShift->shift, $cekShift->time_in, $cekShift->time_out, $value->nik_karyawan, $value->nama_lengkap);
                        $data[] = $init;
                    } else {
                        $gen = Absensi::select(
                            'master_karyawan.nik_karyawan',
                            'master_karyawan.nama_lengkap',
                            'absensi.tanggal',
                            \DB::raw("CASE WHEN MIN(jam) <= '14:00:00' THEN MIN(jam) ELSE '' END as masuk"),
                            \DB::raw("CASE WHEN MAX(jam) > '14:00:00' THEN MAX(jam) ELSE '' END as keluar")
                        )
                            ->where('absensi.karyawan_id', $value->id)
                            ->where('absensi.tanggal', $tanggal)
                            ->join('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id')
                            ->groupBy('absensi.tanggal', 'absensi.karyawan_id')
                            ->first();
                        if ($gen != null) {
                            if ($gen->masuk < '08:00:00') {
                                $selisih_masuk = \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' 08:00:00'));
                                $masuk = '+' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                            } else {
                                $selisih_masuk = \date_diff(date_create($gen->tanggal . ' 08:00:00'), date_create($gen->tanggal . ' ' . $gen->masuk));
                                $masuk = '-' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                            }

                            $total_jam_kerja = '';
                            if ($gen->keluar != '') {
                                $kerja = \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' ' . $gen->keluar));
                                $total_jam_kerja = $kerja->h . 'h ' . $kerja->i . 'm';
                            }
                            $data[] = $this->applyCalendarLiburShift([
                                'nama' => $gen->nik_karyawan . ' - ' . $gen->nama_lengkap,
                                'tanggal' => $gen->tanggal,
                                'hari' => self::hari($gen->tanggal),
                                'masuk' => $gen->masuk,
                                'keluar' => $gen->keluar,
                                'selisih' => $masuk,
                                'jam_kerja' => $total_jam_kerja,
                                'shift' => 'SHREGULAR'
                            ]);
                        } else {
                            $data[] = $this->applyCalendarLiburShift([
                                'nama' => $value->nik_karyawan . ' - ' . $value->nama_lengkap,
                                'tanggal' => $tanggal,
                                'hari' => self::hari($tanggal),
                                'masuk' => '',
                                'keluar' => '',
                                'selisih' => '',
                                'jam_kerja' => '',
                                'shift' => ''
                            ]);
                        }
                    }
                }

                self::writeAbsensiExportRows($sheet, $data);
                $sheet->setTitle($this->sanitizeSheetTitle($sheet, $val->nama_divisi, $spreadsheet));
                $i++;
            }

            $spreadsheet->setActiveSheetIndex(0);
            $path = self::getAbsensiExportPath();
            $writer = new Xlsx($spreadsheet);
            $fileName = 'Daily-Absensi_ALL_' . $request->tanggal . '.xlsx';
            $writer->save($path . $fileName);

            return response()->json([
                'data' => $fileName
            ], 200);
        }
    }
    // Tested - Clear
    private function getAbsensiExportPath()
    {
        $path = \public_path() . DIRECTORY_SEPARATOR . 'absensi' . DIRECTORY_SEPARATOR;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    private function parseBulanAbsensi($bulan, $tanggal = null)
    {
        $value = $bulan ?: $tanggal;
        if (empty($value)) {
            return null;
        }

        $parts = explode('-', $value);
        if (count($parts) < 2) {
            return null;
        }

        return [
            'year' => $parts[0],
            'month' => $parts[1],
        ];
    }

    private function getMonthlyDateRange($year, $month, $lastDay)
    {
        $monthPadded = str_pad($month, 2, '0', STR_PAD_LEFT);
        $startDate = $year . '-' . $monthPadded . '-01';
        $endDate = $year . '-' . $monthPadded . '-' . sprintf('%02d', $lastDay);
        $nextDate = date('Y-m-d', strtotime($endDate . ' +1 day'));

        return compact('startDate', 'endDate', 'nextDate', 'monthPadded');
    }

    private function fetchMonthlyShiftIndex(array $karyawanIds, $startDate, $endDate)
    {
        if (empty($karyawanIds)) {
            return [];
        }

        $rows = ShiftKaryawan::whereIn('karyawan_id', $karyawanIds)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->get();

        $index = [];
        foreach ($rows as $row) {
            $index[$row->karyawan_id][$row->tanggal] = $row;
        }

        return $index;
    }

    private function fetchMonthlyPunchIndex(array $karyawanIds, $startDate, $endDate)
    {
        if (empty($karyawanIds)) {
            return [];
        }

        $rows = Absensi::select(
            'karyawan_id',
            'tanggal',
            \DB::raw('MIN(jam) as min_jam'),
            \DB::raw('MAX(jam) as max_jam')
        )
            ->whereIn('karyawan_id', $karyawanIds)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->groupBy('karyawan_id', 'tanggal')
            ->get();

        $index = [];
        foreach ($rows as $row) {
            $index[$row->karyawan_id][$row->tanggal] = $row;
        }

        return $index;
    }

    private function getMonthlyPunch($punchIndex, $karyawanId, $tanggal)
    {
        return isset($punchIndex[$karyawanId][$tanggal]) ? $punchIndex[$karyawanId][$tanggal] : null;
    }

    private function calcSelisihMasuk($tanggal, $masuk, $checkin = '08:00:00')
    {
        if ($masuk === '' || $masuk === null) {
            return '';
        }

        $checkinTime = DATE('H:i:s', strtotime($checkin));
        if ($masuk < $checkinTime) {
            $selisih = \date_diff(date_create($tanggal . ' ' . $masuk), date_create($tanggal . ' ' . $checkinTime));
            return '+' . (int) ((($selisih->h * 3600) + ($selisih->i * 60) + $selisih->s) / 60) . 'm';
        }

        $selisih = \date_diff(date_create($tanggal . ' ' . $checkinTime), date_create($tanggal . ' ' . $masuk));
        return '-' . (int) ((($selisih->h * 3600) + ($selisih->i * 60) + $selisih->s) / 60) . 'm';
    }

    private function calcJamKerja($tanggal, $masuk, $keluar)
    {
        if ($masuk === '' || $keluar === '') {
            return '';
        }

        $kerja = \date_diff(date_create($tanggal . ' ' . $masuk), date_create($tanggal . ' ' . $keluar));
        return $kerja->h . 'h ' . $kerja->i . 'm';
    }

    private function extractMasukKeluarByThreshold($minJam, $maxJam, $threshold)
    {
        $masuk = ($minJam && $minJam <= $threshold) ? $minJam : '';
        $keluar = ($maxJam && $maxJam > $threshold) ? $maxJam : '';

        return [$masuk, $keluar];
    }

    private function parseAbsensiRowIdentity($row)
    {
        if (isset($row['nik']) && isset($row['nama_lengkap'])) {
            return [$row['nik'], $row['nama_lengkap']];
        }

        if (isset($row['nama']) && strpos($row['nama'], ' - ') !== false) {
            $parts = explode(' - ', $row['nama'], 2);
            return [$parts[0], $parts[1]];
        }

        return ['', isset($row['nama']) ? $row['nama'] : ''];
    }

    private function fetchWorkingDayIndex($year)
    {
        $year = (string) $year;

        if (isset($this->workingDayIndexCache[$year])) {
            return $this->workingDayIndexCache[$year];
        }

        $record = RekapLiburKalender::where('tahun', $year)
            ->where('is_active', true)
            ->first();

        if (!$record || empty($record->tanggal)) {
            $this->workingDayIndexCache[$year] = [];

            return $this->workingDayIndexCache[$year];
        }

        $decoded = json_decode($record->tanggal, true);
        if (!is_array($decoded)) {
            $this->workingDayIndexCache[$year] = [];

            return $this->workingDayIndexCache[$year];
        }

        $index = [];
        foreach ($decoded as $dates) {
            if (!is_array($dates)) {
                continue;
            }

            foreach ($dates as $date) {
                if (empty($date)) {
                    continue;
                }

                $index[date('Y-m-d', strtotime($date))] = true;
            }
        }

        $this->workingDayIndexCache[$year] = $index;

        return $this->workingDayIndexCache[$year];
    }

    private function buildLiburAbsensiRow($nikKaryawan, $namaLengkap, $tanggal)
    {
        $tanggalKey = date('Y-m-d', strtotime($tanggal));

        return [
            'nama' => $nikKaryawan . ' - ' . $namaLengkap,
            'tanggal' => $tanggalKey,
            'hari' => self::hari($tanggalKey),
            'masuk' => '',
            'keluar' => '',
            'selisih' => '',
            'jam_kerja' => '',
            'shift' => 'Libur',
        ];
    }

    private function applyCalendarLiburShift($row)
    {
        if (empty($row['tanggal'])) {
            return $row;
        }

        $tanggalKey = date('Y-m-d', strtotime($row['tanggal']));
        $workingDayIndex = $this->fetchWorkingDayIndex(date('Y', strtotime($tanggalKey)));

        if (!empty($workingDayIndex) && !isset($workingDayIndex[$tanggalKey])) {
            $row['shift'] = 'Libur';
            $row['masuk'] = '';
            $row['keluar'] = '';
            $row['selisih'] = '';
            $row['jam_kerja'] = '';
        }

        return $row;
    }

    private function buildEmptyMonthlyRow($nikKaryawan, $namaLengkap, $tanggal, $shift = '')
    {
        return [
            'nik' => $nikKaryawan,
            'nama_lengkap' => $namaLengkap,
            'nama' => $nikKaryawan . ' - ' . $namaLengkap,
            'tanggal' => $tanggal,
            'hari' => self::hari($tanggal),
            'masuk' => '',
            'keluar' => '',
            'selisih' => '',
            'jam_kerja' => '',
            'shift' => $shift,
        ];
    }

    private function buildMonthlyRow($nikKaryawan, $namaLengkap, $tanggal, $masuk, $keluar, $shift, $checkin = '08:00:00')
    {
        $selisih = self::calcSelisihMasuk($tanggal, $masuk, $checkin);
        $jamKerja = self::calcJamKerja($tanggal, $masuk, $keluar);

        return [
            'nik' => $nikKaryawan,
            'nama_lengkap' => $namaLengkap,
            'nama' => $nikKaryawan . ' - ' . $namaLengkap,
            'tanggal' => $tanggal,
            'hari' => self::hari($tanggal),
            'masuk' => $masuk,
            'keluar' => $keluar,
            'selisih' => $selisih,
            'jam_kerja' => $jamKerja,
            'shift' => $shift,
        ];
    }

    private function resolveMonthlyDayRow($karyawan, $tanggal, $shiftIndex, $punchIndex, $workingDayIndex = null)
    {
        $tanggalKey = date('Y-m-d', strtotime($tanggal));

        if ($workingDayIndex === null) {
            $workingDayIndex = $this->fetchWorkingDayIndex(date('Y', strtotime($tanggalKey)));
        }

        if (!empty($workingDayIndex) && !isset($workingDayIndex[$tanggalKey])) {
            return self::buildEmptyMonthlyRow($karyawan->nik_karyawan, $karyawan->nama_lengkap, $tanggalKey, 'Libur');
        }

        $shiftRow = isset($shiftIndex[$karyawan->id][$tanggal]) ? $shiftIndex[$karyawan->id][$tanggal] : null;
        $punch = self::getMonthlyPunch($punchIndex, $karyawan->id, $tanggal);
        $nextDate = date('Y-m-d', strtotime($tanggal . ' +1 day'));
        $nextPunch = self::getMonthlyPunch($punchIndex, $karyawan->id, $nextDate);

        if ($shiftRow && $shiftRow->shift === 'off') {
            return self::buildEmptyMonthlyRow($karyawan->nik_karyawan, $karyawan->nama_lengkap, $tanggal, 'OFF');
        }

        $checkin = ($shiftRow && $shiftRow->time_in) ? $shiftRow->time_in : '08:00:00';
        $shiftName = ($shiftRow && $shiftRow->shift) ? $shiftRow->shift : 'SHREGULAR';

        if ($shiftRow && $shiftRow->shift === '24jam') {
            $masuk = ($punch && $punch->min_jam) ? $punch->min_jam : '';
            $keluar = ($nextPunch && $nextPunch->min_jam && $nextPunch->min_jam < '14:00:00') ? $nextPunch->min_jam : '';
            if ($masuk === '' && $keluar === '') {
                return self::buildEmptyMonthlyRow($karyawan->nik_karyawan, $karyawan->nama_lengkap, $tanggal, '24JAM');
            }

            return self::buildMonthlyRow($karyawan->nik_karyawan, $karyawan->nama_lengkap, $tanggal, $masuk, $keluar, '24JAM', $checkin);
        }

        if ($shiftRow && $shiftRow->shift === 'SHSECURITY2') {
            $masuk = ($punch && $punch->max_jam) ? $punch->max_jam : '';
            $keluar = ($nextPunch && $nextPunch->min_jam && $nextPunch->min_jam < '14:00:00') ? $nextPunch->min_jam : '';
            if ($masuk !== '' && $masuk <= '14:00:00') {
                return self::buildEmptyMonthlyRow($karyawan->nik_karyawan, $karyawan->nama_lengkap, $tanggal, 'SHSECURITY2');
            }
            if ($masuk === '' && $keluar === '') {
                return self::buildEmptyMonthlyRow($karyawan->nik_karyawan, $karyawan->nama_lengkap, $tanggal, 'SHSECURITY2');
            }

            return self::buildMonthlyRow($karyawan->nik_karyawan, $karyawan->nama_lengkap, $tanggal, $masuk, $keluar, 'SHSECURITY2', $checkin);
        }

        if ($shiftRow && $shiftRow->shift === 'SHOB2') {
            $masuk = ($punch && $punch->min_jam) ? $punch->min_jam : '';
            $keluar = ($nextPunch && $nextPunch->min_jam && $nextPunch->min_jam < '14:00:00') ? $nextPunch->min_jam : '';
            if ($masuk === '' && $keluar === '') {
                return self::buildEmptyMonthlyRow($karyawan->nik_karyawan, $karyawan->nama_lengkap, $tanggal, 'SHOB2');
            }

            return self::buildMonthlyRow($karyawan->nik_karyawan, $karyawan->nama_lengkap, $tanggal, $masuk, $keluar, 'SHOB2', $checkin);
        }

        if ($shiftRow) {
            $threshold = DATE('H:i:s', strtotime($checkin . '+4 hours'));
            list($masuk, $keluar) = self::extractMasukKeluarByThreshold(
                $punch ? $punch->min_jam : null,
                $punch ? $punch->max_jam : null,
                $threshold
            );

            if ($masuk === '' && $keluar === '') {
                return self::buildEmptyMonthlyRow($karyawan->nik_karyawan, $karyawan->nama_lengkap, $tanggal, $shiftName);
            }

            $row = self::buildMonthlyRow($karyawan->nik_karyawan, $karyawan->nama_lengkap, $tanggal, $masuk, $keluar, $shiftName, $checkin);
            if ($masuk === '') {
                $row['selisih'] = '';
                $row['jam_kerja'] = '';
            }

            return $row;
        }

        list($masuk, $keluar) = self::extractMasukKeluarByThreshold(
            $punch ? $punch->min_jam : null,
            $punch ? $punch->max_jam : null,
            '14:00:00'
        );

        if ($masuk === '' && $keluar === '') {
            return self::buildEmptyMonthlyRow($karyawan->nik_karyawan, $karyawan->nama_lengkap, $tanggal, '');
        }

        return self::buildMonthlyRow($karyawan->nik_karyawan, $karyawan->nama_lengkap, $tanggal, $masuk, $keluar, 'SHREGULAR');
    }

    private function buildMonthlyAbsensiData($karyawanId, $year, $month, $lastDay, $nikKaryawan, $namaLengkap)
    {
        $karyawan = (object) [
            'id' => $karyawanId,
            'nik_karyawan' => $nikKaryawan,
            'nama_lengkap' => $namaLengkap,
        ];

        return self::buildMonthlyAbsensiDataForKaryawans(collect([$karyawan]), $year, $month, $lastDay);
    }

    private function buildMonthlyAbsensiDataForKaryawans($karyawans, $year, $month, $lastDay)
    {
        if ($karyawans->isEmpty()) {
            return [];
        }

        $dates = self::getMonthlyDateRange($year, $month, $lastDay);
        $karyawanIds = $karyawans->pluck('id')->all();

        $shiftIndex = self::fetchMonthlyShiftIndex($karyawanIds, $dates['startDate'], $dates['endDate']);
        $punchIndex = self::fetchMonthlyPunchIndex($karyawanIds, $dates['startDate'], $dates['nextDate']);
        $workingDayIndex = $this->fetchWorkingDayIndex($year);

        $data = [];
        foreach ($karyawans as $karyawan) {
            for ($a = 1; $a <= $lastDay; $a++) {
                $tanggal = $year . '-' . $dates['monthPadded'] . '-' . sprintf('%02d', $a);
                $data[] = self::resolveMonthlyDayRow($karyawan, $tanggal, $shiftIndex, $punchIndex, $workingDayIndex);
            }
        }

        return $data;
    }

    private function setupAbsensiExportSheet($sheet)
    {
        $sheet->mergeCells('A1:A2');
        $sheet->getStyle('A1:A2')->getAlignment()->setVertical('center');
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal('center');
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->mergeCells('B1:B2');
        $sheet->getStyle('B1:B2')->getAlignment()->setVertical('center');
        $sheet->getStyle('B1:B2')->getAlignment()->setHorizontal('center');
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->mergeCells('C1:C2');
        $sheet->getStyle('C1:C2')->getAlignment()->setVertical('center');
        $sheet->getStyle('C1:C2')->getAlignment()->setHorizontal('center');
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->mergeCells('D1:D2');
        $sheet->getStyle('D1:D2')->getAlignment()->setVertical('center');
        $sheet->getStyle('D1:D2')->getAlignment()->setHorizontal('center');
        $sheet->getColumnDimension('D')->setWidth(13);
        $sheet->mergeCells('E1:E2');
        $sheet->getStyle('E1:E2')->getAlignment()->setVertical('center');
        $sheet->getStyle('E1:E2')->getAlignment()->setHorizontal('center');
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->mergeCells('F1:G1');
        $sheet->getStyle('F:G')->getAlignment()->setHorizontal('center');
        $sheet->mergeCells('H1:J1');
        $sheet->getStyle('H:J')->getAlignment()->setHorizontal('center');
        $sheet->getColumnDimension('J')->setWidth(18);

        $sheet->getStyle('A1:J1')
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A2:J2')
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sheet->setCellValue('A1', 'No');
        $sheet->setCellValue('B1', 'NIK');
        $sheet->setCellValue('C1', 'Nama Karyawan');
        $sheet->setCellValue('D1', 'Tanggal');
        $sheet->setCellValue('E1', 'Hari');
        $sheet->setCellValue('F1', 'Absensi');
        $sheet->setCellValue('F2', 'Masuk');
        $sheet->setCellValue('G2', 'Keluar');
        $sheet->setCellValue('H1', 'Record');
        $sheet->setCellValue('H2', ' + / -');
        $sheet->setCellValue('I2', 'Jam Kerja');
        $sheet->setCellValue('J2', 'Shift');
    }

    private function writeAbsensiExportRows($sheet, $data)
    {
        $u = 3;
        foreach ($data as $row) {
            list($nik, $nama) = self::parseAbsensiRowIdentity($row);
            $sheet->setCellValue('A' . $u, ($u - 2));
            $sheet->setCellValue('B' . $u, $nik);
            $sheet->setCellValue('C' . $u, $nama);
            $sheet->setCellValue('D' . $u, $row['tanggal']);
            $sheet->setCellValue('E' . $u, $row['hari']);
            $sheet->setCellValue('F' . $u, $row['masuk']);
            $sheet->setCellValue('G' . $u, $row['keluar']);
            $sheet->setCellValue('H' . $u, $row['selisih']);
            $sheet->setCellValue('I' . $u, $row['jam_kerja']);
            $sheet->setCellValue('J' . $u, $row['shift']);
            $u++;
        }

        if ($u > 3) {
            $sheet->getStyle('A3:J' . ($u - 1))
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
        }

        return $u;
    }

    private function parsePrivilageCabang($privilageCabang)
    {
        if ($privilageCabang === null || $privilageCabang === '') {
            return [];
        }

        if (is_array($privilageCabang)) {
            return array_values(array_map('strval', $privilageCabang));
        }

        if (is_numeric($privilageCabang)) {
            return [(string) $privilageCabang];
        }

        if (!is_string($privilageCabang)) {
            return [];
        }

        $decoded = json_decode($privilageCabang, true);
        if (is_array($decoded)) {
            return array_values(array_map('strval', $decoded));
        }

        $trimmed = trim($privilageCabang);
        if ($trimmed !== '' && $trimmed[0] === '[') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', trim($trimmed, '[]" '));
        $parts = array_filter($parts, function ($part) {
            return $part !== '';
        });

        return array_values(array_map('strval', $parts));
    }

    private function isHeadOfficeFullPrivilege($privileges)
    {
        $normalized = array_values(array_unique(array_map('strval', $privileges)));
        sort($normalized);

        return $normalized === ['1', '4', '5'];
    }

    private function isBranchSupervisorDepartment($divisi)
    {
        if (!$divisi || !isset($divisi->nama_divisi)) {
            return false;
        }

        $namaDivisi = strtolower(trim($divisi->nama_divisi));

        return strpos($namaDivisi, 'branch') !== false && strpos($namaDivisi, 'supervisor') !== false;
    }

    private function isBranchSupervisorExportEligible($privilageCabang)
    {
        $privileges = self::parsePrivilageCabang($privilageCabang);

        if (empty($privileges) || self::isHeadOfficeFullPrivilege($privileges)) {
            return false;
        }

        $supervisorBranches = array_intersect($privileges, ['4', '5']);
        if (empty($supervisorBranches)) {
            return false;
        }

        $userBranches = array_map('strval', (array) $this->privilageCabang);
        $visibleBranches = array_intersect(['4', '5'], $userBranches);

        if (empty($visibleBranches)) {
            $visibleBranches = ['4', '5'];
        }

        return !empty(array_intersect($supervisorBranches, $visibleBranches));
    }

    private function isWeekendAbsensiRow($absensiRow)
    {
        $hari = isset($absensiRow['hari']) ? strtolower(trim($absensiRow['hari'])) : '';

        return in_array($hari, ['sabtu', 'minggu'], true);
    }

    private function groupAbsensiDataByKaryawan($data)
    {
        $grouped = [];

        foreach ($data as $row) {
            list($nik, $nama) = self::parseAbsensiRowIdentity($row);
            $key = $nik . '|' . $nama;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'nik' => $nik,
                    'nama' => $nama,
                    'rows' => [],
                ];
            }

            $grouped[$key]['rows'][] = $row;
        }

        return array_values($grouped);
    }

    private function writeAbsensiExportGroupedByKaryawan($sheet, $data, $branchLabel = null)
    {
        $sheet->getColumnDimension('A')->setWidth(3);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(18);

        $row = 1;

        if ($branchLabel) {
            $sheet->setCellValue('B' . $row, 'Cabang');
            $sheet->setCellValue('C' . $row, $branchLabel);
            $sheet->getStyle('B' . $row . ':C' . $row)->getFont()->setBold(true);
            $row += 2;
        }

        $groups = self::groupAbsensiDataByKaryawan($data);
        if (empty($groups)) {
            return $row;
        }

        foreach ($groups as $index => $group) {
            if ($index > 0) {
                $row++;
            }

            $nikRow = $row;
            $sheet->setCellValue('B' . $row, 'NIK Karyawan');
            $sheet->setCellValue('C' . $row, $group['nik']);
            $sheet->getStyle('B' . $nikRow . ':C' . $nikRow)->getFont()->setBold(true);
            $row++;

            $namaRow = $row;
            $sheet->setCellValue('B' . $row, 'Nama Karyawan');
            $sheet->setCellValue('C' . $row, $group['nama']);
            $sheet->getStyle('B' . $namaRow . ':C' . $namaRow)->getFont()->setBold(true);
            $row++;

            $headerRow = $row;
            $sheet->setCellValue('B' . $row, 'Tanggal');
            $sheet->setCellValue('C' . $row, 'Hari');
            $sheet->setCellValue('D' . $row, 'Masuk');
            $sheet->setCellValue('E' . $row, 'Keluar');
            $sheet->setCellValue('F' . $row, '+ / -');
            $sheet->setCellValue('G' . $row, 'Jam Kerja');
            $sheet->setCellValue('H' . $row, 'Shift');

            $sheet->getStyle('B' . $headerRow . ':H' . $headerRow)
                ->getFont()
                ->setBold(true);
            $sheet->getStyle('B' . $headerRow . ':H' . $headerRow)
                ->getAlignment()
                ->setHorizontal('center');
            $sheet->getStyle('B' . $headerRow . ':H' . $headerRow)
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            $row++;
            $dataStart = $row;

            foreach ($group['rows'] as $absensiRow) {
                $sheet->setCellValue('B' . $row, isset($absensiRow['tanggal']) ? $absensiRow['tanggal'] : '');
                $sheet->setCellValue('C' . $row, isset($absensiRow['hari']) ? $absensiRow['hari'] : '');
                $sheet->setCellValue('D' . $row, isset($absensiRow['masuk']) ? $absensiRow['masuk'] : '');
                $sheet->setCellValue('E' . $row, isset($absensiRow['keluar']) ? $absensiRow['keluar'] : '');
                $sheet->setCellValue('F' . $row, isset($absensiRow['selisih']) ? $absensiRow['selisih'] : '');
                $sheet->setCellValue('G' . $row, isset($absensiRow['jam_kerja']) ? $absensiRow['jam_kerja'] : '');
                $sheet->setCellValue('H' . $row, isset($absensiRow['shift']) ? $absensiRow['shift'] : '');

                if (self::isWeekendAbsensiRow($absensiRow)) {
                    $sheet->getStyle('B' . $row . ':C' . $row)
                        ->getFont()
                        ->getColor()
                        ->setARGB('FFFF0000');
                }

                $row++;
            }

            if ($row > $dataStart) {
                $sheet->getStyle('B' . $dataStart . ':H' . ($row - 1))
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            }
        }

        return $row;
    }

    private function getExportKaryawanByDepartment($deptId)
    {
        return MasterKaryawan::leftJoin('master_divisi', 'master_karyawan.id_department', '=', 'master_divisi.id')
            ->select(
                'master_karyawan.id',
                'master_karyawan.nik_karyawan',
                'master_karyawan.nama_lengkap',
                'master_karyawan.id_department',
                'master_karyawan.privilage_cabang',
                'master_divisi.kode_divisi',
                'master_divisi.nama_divisi'
            )
            ->where('master_karyawan.id_department', (int) $deptId)
            ->whereIn('master_karyawan.id_cabang', $this->privilageCabang)
            ->where('master_karyawan.is_active', true)
            ->orderBy('master_karyawan.nama_lengkap')
            ->get();
    }

    private function getBranchSupervisorExportKaryawans()
    {
        return MasterKaryawan::leftJoin('master_divisi', 'master_karyawan.id_department', '=', 'master_divisi.id')
            ->select(
                'master_karyawan.id',
                'master_karyawan.nik_karyawan',
                'master_karyawan.nama_lengkap',
                'master_karyawan.id_department',
                'master_karyawan.privilage_cabang',
                'master_karyawan.id_cabang',
                'master_divisi.kode_divisi',
                'master_divisi.nama_divisi'
            )
            ->where('master_karyawan.is_active', true)
            ->whereNotNull('master_karyawan.privilage_cabang')
            ->where('master_karyawan.privilage_cabang', '!=', '')
            ->orderBy('master_karyawan.nama_lengkap')
            ->get()
            ->filter(function ($karyawan) {
                return self::isBranchSupervisorExportEligible($karyawan->privilage_cabang);
            })
            ->values();
    }

    private function getBranchSupervisorBranchMap()
    {
        return [
            '4' => 'Karawang',
            '5' => 'Pemalang',
        ];
    }

    private function filterBranchSupervisorKaryawansByBranch($karyawans, $branchId)
    {
        $branchId = (string) $branchId;

        return $karyawans->filter(function ($karyawan) use ($branchId) {
            $privileges = self::parsePrivilageCabang($karyawan->privilage_cabang);

            return in_array($branchId, $privileges, true);
        })->values();
    }

    private function appendBranchSupervisorMonthlySheets($spreadsheet, &$sheetIndex, $year, $month, $lastDay)
    {
        $allSupervisors = self::getBranchSupervisorExportKaryawans();

        foreach (self::getBranchSupervisorBranchMap() as $branchId => $branchName) {
            if ($sheetIndex > 0) {
                $spreadsheet->createSheet();
            }

            $sheet = $spreadsheet->getSheet($sheetIndex);
            $karyawans = self::filterBranchSupervisorKaryawansByBranch($allSupervisors, $branchId);
            $data = $karyawans->isEmpty()
                ? []
                : self::buildMonthlyAbsensiDataForKaryawans($karyawans, $year, $month, $lastDay);

            self::writeAbsensiExportGroupedByKaryawan($sheet, $data, $branchName);
            $sheet->setTitle($this->sanitizeSheetTitle($sheet, 'Branch Sup ' . $branchName, $spreadsheet));

            $sheetIndex++;
        }
    }

    private function getMonthlyKaryawanQuery($deptId = null)
    {
        $query = MasterKaryawan::leftJoin('master_divisi', 'master_karyawan.id_department', '=', 'master_divisi.id')
            ->select(
                'master_karyawan.id',
                'master_karyawan.nik_karyawan',
                'master_karyawan.nama_lengkap',
                'master_karyawan.id_department',
                'master_divisi.kode_divisi',
                'master_divisi.nama_divisi'
            )
            ->whereIn('master_karyawan.id_cabang', $this->privilageCabang)
            ->where('master_karyawan.is_active', true);

        if ($deptId && $deptId !== 'all') {
            $query->where('master_karyawan.id_department', (int) $deptId);
        }

        return $query;
    }

    private function exportMonthlyAllDepartments($year, $month, $lastDay, $path)
    {
        $divisis = MasterDivisi::where('is_active', true)->orderBy('nama_divisi')->get();
        if ($divisis->isEmpty()) {
            return null;
        }

        $spreadsheet = new Spreadsheet();
        $i = 0;

        foreach ($divisis as $divisi) {
            if (self::isBranchSupervisorDepartment($divisi)) {
                self::appendBranchSupervisorMonthlySheets($spreadsheet, $i, $year, $month, $lastDay);
                continue;
            }

            $karyawans = self::getExportKaryawanByDepartment($divisi->id);

            if ($i > 0) {
                $spreadsheet->createSheet();
            }

            $sheet = $spreadsheet->getSheet($i);

            $data = $karyawans->isEmpty()
                ? []
                : self::buildMonthlyAbsensiDataForKaryawans($karyawans, $year, $month, $lastDay);
            self::writeAbsensiExportGroupedByKaryawan($sheet, $data);
            $sheet->setTitle($this->sanitizeSheetTitle($sheet, $divisi->nama_divisi, $spreadsheet));

            $i++;
        }

        $spreadsheet->setActiveSheetIndex(0);
        $fileName = 'Monthly-Absensi_ALL_' . $year . '-' . $month . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path . $fileName);

        return $fileName;
    }

    public function exportAbsenMonthly(Request $request)
    {
        try {
            $periode = self::parseBulanAbsensi($request->bulan, $request->tanggal);
            if ($periode === null) {
                return response()->json([
                    'message' => 'Format bulan tidak valid. Gunakan format YYYY-MM.'
                ], 422);
            }

            $year = $periode['year'];
            $month = $periode['month'];
            $lastDay = cal_days_in_month(CAL_GREGORIAN, (int) $month, (int) $year);
            $deptId = $request->id_department ?: $request->idDivisi;
            $path = self::getAbsensiExportPath();

            if ($request->export == 'single') {
                if (!$request->id_karyawan && ($deptId === 'all' || $deptId === null || $deptId === '')) {
                    $fileName = self::exportMonthlyAllDepartments($year, $month, $lastDay, $path);
                    if ($fileName === null) {
                        return response()->json([
                            'message' => 'Data karyawan tidak ditemukan.'
                        ], 404);
                    }

                    return response()->json([
                        'data' => $fileName
                    ], 200);
                }

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                self::setupAbsensiExportSheet($sheet);

                if ($request->id_karyawan) {
                    $cekKaryawan = MasterKaryawan::where('id', $request->id_karyawan)->where('is_active', true)->first();
                    if ($cekKaryawan === null) {
                        return response()->json([
                            'message' => 'Data karyawan tidak ditemukan.'
                        ], 404);
                    }

                    $data = self::buildMonthlyAbsensiData(
                        $cekKaryawan->id,
                        $year,
                        $month,
                        $lastDay,
                        $cekKaryawan->nik_karyawan,
                        $cekKaryawan->nama_lengkap
                    );
                    self::writeAbsensiExportRows($sheet, $data);
                    $sheet->setTitle($this->sanitizeSheetTitle($sheet, $cekKaryawan->nama_lengkap, $spreadsheet));
                    $fileName = 'Monthly-Absensi_' . $cekKaryawan->nik_karyawan . '_' . $year . '-' . $month . '.xlsx';
                } else {
                    $divisi = MasterDivisi::where('id', (int) $deptId)->where('is_active', true)->first();

                    if ($divisi && self::isBranchSupervisorDepartment($divisi)) {
                        $spreadsheet = new Spreadsheet();
                        $sheetIndex = 0;
                        self::appendBranchSupervisorMonthlySheets($spreadsheet, $sheetIndex, $year, $month, $lastDay);
                        $spreadsheet->setActiveSheetIndex(0);
                        $fileName = 'Monthly-Absensi_BranchSupervisor_' . $year . '-' . $month . '.xlsx';
                        $writer = new Xlsx($spreadsheet);
                        $writer->save($path . $fileName);

                        return response()->json([
                            'data' => $fileName
                        ], 200);
                    }

                    $karyawans = $divisi
                        ? self::getExportKaryawanByDepartment($divisi->id)
                        : self::getMonthlyKaryawanQuery($deptId)->get();

                    if ($karyawans->isEmpty()) {
                        return response()->json([
                            'message' => 'Data karyawan tidak ditemukan.'
                        ], 404);
                    }

                    $data = self::buildMonthlyAbsensiDataForKaryawans($karyawans, $year, $month, $lastDay);
                    self::writeAbsensiExportRows($sheet, $data);

                    $deptName = $karyawans[0]->nama_divisi ?: 'Departemen';
                    $deptCode = $karyawans[0]->kode_divisi ?: 'DEPT';
                    $sheet->setTitle($this->sanitizeSheetTitle($sheet, $deptName, $spreadsheet));
                    $fileName = 'Monthly-Absensi_' . $deptCode . '_' . $year . '-' . $month . '.xlsx';
                }

                $writer = new Xlsx($spreadsheet);
                $writer->save($path . $fileName);

                return response()->json([
                    'data' => $fileName
                ], 200);
            }

            $fileName = self::exportMonthlyAllDepartments($year, $month, $lastDay, $path);
            if ($fileName === null) {
                return response()->json([
                    'message' => 'Data karyawan tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'data' => $fileName
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }
    
    private function sanitizeSheetTitle($sheet, $title, $spreadsheet)
    {
        $title = str_replace(['\\', '/', '?', '*', ':', '[', ']'], '', $title);
        $title = substr($title, 0, 31);
        if ($title === '' || $title === false) {
            $title = 'Sheet';
        }
        $currentTitle = $sheet->getTitle();
        $existingTitles = [];
        foreach ($spreadsheet->getSheetNames() as $name) {
            if (strtolower($name) !== strtolower($currentTitle)) {
                $existingTitles[strtolower($name)] = true;
            }
        }
        $tempTitle = $title;
        $counter = 1;
        while (isset($existingTitles[strtolower($tempTitle)])) {
            $suffix = ' ' . $counter;
            $tempTitle = substr($title, 0, 31 - strlen($suffix)) . $suffix;
            $counter++;
        }
        return $tempTitle;
    }
    
    // Tested - Clear
    public function rangeMonth()
    {
        $datestr = DATE('Y-m-d');
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        return array(
            "start" => date('Y-m-d', strtotime('first day of this month', $dt)),
            "end" => date('Y-m-d', strtotime('last day of this month', $dt))
        );
    }
    // Tested - Clear
    public function rangeWeek()
    {
        $datestr = DATE('Y-m-d');
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        return array(
            "start" => date('N', $dt) == 1 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('last monday', $dt)),
            "end" => date('N', $dt) == 7 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('next sunday', $dt))
        );
    }
}