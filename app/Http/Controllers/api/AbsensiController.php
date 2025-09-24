<?php

namespace App\Http\Controllers\api;

use App\Models\MesinAbsen;
use App\Models\Absensi;
use App\Models\Rfid;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use App\Models\ShiftKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;



class AbsensiController extends Controller
{
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
            $data->where('id_department', $request->departement);
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
            // $cekKaryawan = MasterKaryawan::on($db)->where('id_department', $request->departement)
            $cekKaryawan = MasterKaryawan::where('id_department', $request->departement)
                ->where('is_active', true)
                ->get();
            

            if (!$cekKaryawan->isEmpty()) {

                foreach ($cekKaryawan as $key => $value) {
                    $cekShift = ShiftKaryawan::where('tanggal', $tanggal)->where('karyawan_id', $value->id)->first();

                    $shift = 'SHREGULAR';
                    if ($cekShift != null) {
                        $init = self::compareshift($value->id, $tanggal, $cekShift->shift, $cekShift->time_in, $cekShift->time_out, $value->nik, $value->nama_lengkap);
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

                            $data[] = [
                                'nama' => $gen->nik_karyawan . ' - ' . $gen->nama_lengkap,
                                'tanggal' => $gen->tanggal,
                                'hari' => self::hari($gen->tanggal),
                                'masuk' => $gen->masuk,
                                'keluar' => $gen->keluar,
                                'selisih' => $masuk,
                                'jam_kerja' => $total_jam_kerja,
                                'shift' => 'SHREGULAR'
                            ];
                        } else {
                            $data[] = [
                                'nama' => $value->nik_karyawan . ' - ' . $value->nama_lengkap,
                                'tanggal' => $tanggal,
                                'hari' => self::hari($tanggal),
                                'masuk' => '',
                                'keluar' => '',
                                'selisih' => '',
                                'jam_kerja' => '',
                                'shift' => ''
                            ];
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
            $nilai = explode("-", $request->tanggal);
            $month = $nilai[1];
            $year = $nilai[0];
            $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $data = [];


            for ($i = 1; $i <= $lastDay; $i++) {
                $num = sprintf("%02d", $i);
                $tanggal = $year . '-' . $month . '-' . $num;

                $cekShift = ShiftKaryawan::where('tanggal', $tanggal)->where('karyawan_id', $request->id)->first();
                // dd
                $cekKaryawan = MasterKaryawan::where('id', $request->id)->first();

                if ($cekShift != null && $cekShift != 'null') {
                    $init = self::compareshift($request->id, $tanggal, $cekShift->shift, $cekShift->time_in, $cekShift->time_out, $cekKaryawan->nik_karyawan, $cekKaryawan->nama_lengkap);

                    $data[] = $init;
                } else {
                    $gen = Absensi::select(
                        'absensi.karyawan_id',
                        'master_karyawan.nik_karyawan',
                        'master_karyawan.nama_lengkap',
                        'absensi.tanggal',
                        \DB::raw("CASE WHEN MIN(jam) <= '14:00:00' THEN MIN(jam) ELSE '' END as masuk"),
                        \DB::raw("CASE WHEN MAX(jam) > '14:00:00' THEN MAX(jam) ELSE '' END as keluar")
                    )
                        ->where('absensi.karyawan_id', $request->id)
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

                        $data[] = [
                            'nama' => $gen->nik_karyawan . ' - ' . $gen->nama_lengkap,
                            'karyawan_id' => $gen->karyawan_id,
                            'tanggal' => $gen->tanggal,
                            'hari' => self::hari($gen->tanggal),
                            'masuk' => $gen->masuk,
                            'keluar' => $gen->keluar,
                            'selisih' => $masuk,
                            'jam_kerja' => $total_jam_kerja,
                            'shift' => 'SHREGULAR'
                        ];
                    } else {
                        $data[] = [
                            'nama' => $cekKaryawan->nik_karyawan . ' - ' . $cekKaryawan->nama_lengkap,
                            'karyawan_id' => $cekKaryawan->id,
                            'tanggal' => $tanggal,
                            'hari' => self::hari($tanggal),
                            'masuk' => '',
                            'keluar' => '',
                            'selisih' => '',
                            'jam_kerja' => '',
                            'shift' => ''
                        ];
                    }
                }
            }
            return response()->json([
                'data' => $data
            ], 200);
        }
    }
    // Tested - Clear
    public function compareshift($id, $tanggal, $shift, $checkin = '08:00:00', $checkout = '17:00:00', $nik_karyawan, $nama_lengkap)
    {
        $db = ($tanggal != null && $tanggal != '') ? DATE('Y', \strtotime($tanggal)) : $this->db;

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

            $sheet->mergeCells('A1:A2');
            $sheet->getStyle('A1:A2')->getAlignment()->setVertical('center');
            $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal('center');
            $sheet->getColumnDimension('A')->setWidth(6);
            $sheet->mergeCells('B1:B2');
            $sheet->getStyle('B1:B2')->getAlignment()->setVertical('center');
            $sheet->getStyle('B1:B2')->getAlignment()->setHorizontal('center');
            $sheet->getColumnDimension('B')->setWidth(35);
            $sheet->mergeCells('C1:C2');
            $sheet->getStyle('C1:C2')->getAlignment()->setVertical('center');
            $sheet->getStyle('C1:C2')->getAlignment()->setHorizontal('center');
            $sheet->getColumnDimension('C')->setWidth(13);
            $sheet->mergeCells('D1:D2');
            $sheet->getStyle('D1:D2')->getAlignment()->setVertical('center');
            $sheet->getStyle('D1:D2')->getAlignment()->setHorizontal('center');
            $sheet->getColumnDimension('D')->setWidth(10);
            $sheet->mergeCells('E1:F1');
            $sheet->getStyle('E:F')->getAlignment()->setHorizontal('center');
            $sheet->mergeCells('G1:I1');
            $sheet->getStyle('G:I')->getAlignment()->setHorizontal('center');
            $sheet->getColumnDimension('I')->setWidth(25);

            $sheet->getStyle('A1:I1')
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A2:I2')
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            $sheet->setCellValue('A1', 'No');
            $sheet->setCellValue('B1', 'Nama Karyawan');
            $sheet->setCellValue('C1', 'Tanggal');
            $sheet->setCellValue('D1', 'Hari');
            $sheet->setCellValue('E1', 'Absensi');
            $sheet->setCellValue('E2', 'Masuk');
            $sheet->setCellValue('F2', 'Keluar');
            $sheet->setCellValue('G1', 'Record');
            $sheet->setCellValue('G2', ' + / -');
            $sheet->setCellValue('H2', 'Jam Kerja');
            $sheet->setCellValue('I2', 'Shift');

            $data = [];
            $tanggal = $request->tanggal;
            $cekKaryawan = MasterKaryawan::leftJoin('master_divisi', 'master_karyawan.id_department', '=', 'master_divisi.id')
                ->select('master_karyawan.id', 'master_karyawan.nik_karyawan', 'master_karyawan.nama_lengkap', 'master_divisi.kode_divisi', 'master_divisi.nama_divisi')
                ->where('id_department', $request->idDivisi)
                ->whereIn('master_karyawan.id_cabang', $this->privilageCabang)
                ->where('master_karyawan.is_active', true)
                ->get();
            $deptCode = $cekKaryawan[0]->kode_divisi;

            foreach ($cekKaryawan as $value) {
                $dept = $value->nama_divisi;
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

                        $data[] = [
                            'nama' => $gen->nik_karyawan . ' - ' . $gen->nama_lengkap,
                            'tanggal' => $gen->tanggal,
                            'hari' => self::hari($gen->tanggal),
                            'masuk' => $gen->masuk,
                            'keluar' => $gen->keluar,
                            'selisih' => $masuk,
                            'jam_kerja' => $total_jam_kerja,
                            'shift' => 'SHREGULAR'
                        ];
                    } else {
                        $data[] = [
                            'nama' => $value->nik_karyawan . ' - ' . $value->nama_lengkap,
                            'tanggal' => $tanggal,
                            'hari' => self::hari($tanggal),
                            'masuk' => '',
                            'keluar' => '',
                            'selisih' => '',
                            'jam_kerja' => '',
                            'shift' => ''
                        ];
                    }
                }
            }

            // Fill data into the sheet
            $u = 3;
            foreach ($data as $row) {
                $sheet->setCellValue('A' . $u, ($u - 2))
                    ->setCellValue('B' . $u, $row['nama'])
                    ->setCellValue('C' . $u, $row['tanggal'])
                    ->setCellValue('D' . $u, $row['hari'])
                    ->setCellValue('E' . $u, $row['masuk'])
                    ->setCellValue('F' . $u, $row['keluar'])
                    ->setCellValue('G' . $u, $row['selisih'])
                    ->setCellValue('H' . $u, $row['jam_kerja'])
                    ->setCellValue('I' . $u, $row['shift']);
                $u++;
            }

            $sheet->getStyle('A3:I' . ($u - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->setTitle($dept);

            $path = \public_path() . '/absensi/';
            $writer = new Xlsx($spreadsheet);
            $fileName = 'Daily-Absensi_' . $deptCode . '_' . $request->tanggal . '.xlsx';
            $writer->save($path . $fileName);

            return response()->json(['data' => $fileName], 200);
        } else {
            $cekUser = MasterDivisi::where('is_active', true)->get();

            $spreadsheet = new Spreadsheet();
            $i = 0;
            foreach ($cekUser as $key => $val) {
                $spreadsheet->createSheet();
                $sheet = $spreadsheet->getSheet($i);
                $sheet->mergeCells('A1:A2');
                $sheet->getStyle('A1:A2')->getAlignment()->setVertical('center');
                $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal('center');
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->mergeCells('B1:B2');
                $sheet->getStyle('B1:B2')->getAlignment()->setVertical('center');
                $sheet->getStyle('B1:B2')->getAlignment()->setHorizontal('center');
                $sheet->getColumnDimension('B')->setWidth(35);
                $sheet->mergeCells('C1:C2');
                $sheet->getStyle('C1:C2')->getAlignment()->setVertical('center');
                $sheet->getStyle('C1:C2')->getAlignment()->setHorizontal('center');
                $sheet->getColumnDimension('C')->setWidth(13);
                $sheet->mergeCells('D1:D2');
                $sheet->getStyle('D1:D2')->getAlignment()->setVertical('center');
                $sheet->getStyle('D1:D2')->getAlignment()->setHorizontal('center');
                $sheet->getColumnDimension('D')->setWidth(10);
                $sheet->mergeCells('E1:F1');
                $sheet->getStyle('E:F')->getAlignment()->setHorizontal('center');
                $sheet->mergeCells('G1:I1');
                $sheet->getStyle('G:I')->getAlignment()->setHorizontal('center');
                $sheet->getColumnDimension('I')->setWidth(25);


                $sheet->getStyle('A1:I1')
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A2:I2')
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->setCellValue('A1', 'No');
                $sheet->setCellValue('B1', 'Nama Karyawan');
                $sheet->setCellValue('C1', 'Tanggal');
                $sheet->setCellValue('D1', 'Hari');
                $sheet->setCellValue('E1', 'Absensi');
                $sheet->setCellValue('E2', 'Masuk');
                $sheet->setCellValue('F2', 'Keluar');
                $sheet->setCellValue('G1', 'Record');
                $sheet->setCellValue('G2', ' + / -');
                $sheet->setCellValue('H2', 'Jam Kerja');
                $sheet->setCellValue('I2', 'Shift');

                $data = [];
                $cekKaryawan = MasterKaryawan::join('master_divisi', function ($join) {
                    $join->on('master_karyawan.id_department', '=', 'master_divisi.id')
                        ->on('master_divisi.is_active', '=', DB::raw(true));
                })->select('master_karyawan.id', 'master_karyawan.nik_karyawan', 'master_karyawan.nama_lengkap', 'master_divisi.nama_divisi')->where('master_karyawan.id_department', $val->id)->whereIn('master_karyawan.id_cabang', $this->privilageCabang)->where('master_karyawan.is_active', true)->get();
                $tanggal = $request->tanggal;
                foreach ($cekKaryawan as $keys => $value) {
                    $dept = $value->nama_divisi;
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
                            $data[] = [
                                'nama' => $gen->nik_karyawan . ' - ' . $gen->nama_lengkap,
                                'tanggal' => $gen->tanggal,
                                'hari' => self::hari($gen->tanggal),
                                'masuk' => $gen->masuk,
                                'keluar' => $gen->keluar,
                                'selisih' => $masuk,
                                'jam_kerja' => $total_jam_kerja,
                                'shift' => 'SHREGULAR'
                            ];
                        } else {
                            $data[] = [
                                'nama' => $value->nik_karyawan . ' - ' . $value->nama_lengkap,
                                'tanggal' => $tanggal,
                                'hari' => self::hari($tanggal),
                                'masuk' => '',
                                'keluar' => '',
                                'selisih' => '',
                                'jam_kerja' => '',
                                'shift' => ''
                            ];
                        }
                    }
                }


                $u = 3;
                foreach ($data as $row) {
                    $sheet->setCellValue('A' . $u, ($u - 2));
                    $sheet->setCellValue('B' . $u, $row['nama']);
                    $sheet->setCellValue('C' . $u, $row['tanggal']);
                    $sheet->setCellValue('D' . $u, $row['hari']);
                    $sheet->setCellValue('E' . $u, $row['masuk']);
                    $sheet->setCellValue('F' . $u, $row['keluar']);
                    $sheet->setCellValue('G' . $u, $row['selisih']);
                    $sheet->setCellValue('H' . $u, $row['jam_kerja']);
                    $sheet->setCellValue('I' . $u, $row['shift']);
                    $u++;
                }
                $sheet->getStyle('A3:I' . ($u - 1))
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->setTitle($val->nama_divisi);
                $i++;
            }

            $path = \public_path() . '/absensi/';
            $writer = new Xlsx($spreadsheet);
            $fileName = 'Daily-Absensi_ALL_' . $request->tanggal . '.xlsx';
            $writer->save($path . $fileName);

            return response()->json([
                'data' => $fileName
            ], 200);
        }
    }
    // Tested - Clear
    public function exportAbsenMonthly(Request $request)
    {
        try {
            if ($request->export == 'single') {
                $cekUser = MasterKaryawan::where('id', $request->id_karyawan)->where('is_active', true)->first();
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
    
                $sheet->mergeCells('A1:A2');
                $sheet->getStyle('A1:A2')->getAlignment()->setVertical('center');
                $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal('center');
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->mergeCells('B1:B2');
                $sheet->getStyle('B1:B2')->getAlignment()->setVertical('center');
                $sheet->getStyle('B1:B2')->getAlignment()->setHorizontal('center');
                $sheet->getColumnDimension('B')->setWidth(35);
                $sheet->mergeCells('C1:C2');
                $sheet->getStyle('C1:C2')->getAlignment()->setVertical('center');
                $sheet->getStyle('C1:C2')->getAlignment()->setHorizontal('center');
                $sheet->getColumnDimension('C')->setWidth(13);
                $sheet->mergeCells('D1:D2');
                $sheet->getStyle('D1:D2')->getAlignment()->setVertical('center');
                $sheet->getStyle('D1:D2')->getAlignment()->setHorizontal('center');
                $sheet->getColumnDimension('D')->setWidth(10);
                $sheet->mergeCells('E1:F1');
                $sheet->getStyle('E:F')->getAlignment()->setHorizontal('center');
                $sheet->mergeCells('G1:I1');
                $sheet->getStyle('G:I')->getAlignment()->setHorizontal('center');
                $sheet->getColumnDimension('I')->setWidth(25);
    
                $sheet->getStyle('A1:I1')
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A2:I2')
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
    
                $sheet->setCellValue('A1', 'No');
                $sheet->setCellValue('B1', 'Nama Karyawan');
                $sheet->setCellValue('C1', 'Tanggal');
                $sheet->setCellValue('D1', 'Hari');
                $sheet->setCellValue('E1', 'Absensi');
                $sheet->setCellValue('E2', 'Masuk');
                $sheet->setCellValue('F2', 'Keluar');
                $sheet->setCellValue('G1', 'Record');
                $sheet->setCellValue('G2', ' + / -');
                $sheet->setCellValue('H2', 'Jam Kerja');
                $sheet->setCellValue('I2', 'Shift');
    
    
                $nilai = explode("-", $request->bulan);
                // dd($nilai);
                $month = $nilai[1];
                $year = $nilai[0];
                $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $data = [];

                for ($a = 1; $a <= $lastDay; $a++) {
                    $split = sprintf("%02d", $a);
                    $tanggal = $year.'-'.$month.'-'.$split;
    
                    $cekShift = ShiftKaryawan::where('tanggal', $tanggal)->where('karyawan_id', $request->id_karyawan)->first();
                    $cekKaryawan = MasterKaryawan::where('id', $request->id_karyawan)->where('is_active', true)->first();
    
                    $shift = 'Pagi';
                    if ($cekShift != null) {
                        $init = self::compareshift($request->id_karyawan, $tanggal, $cekShift->shift, $cekShift->time_in, $cekShift->time_out, $cekKaryawan->nik_karyawan, $cekKaryawan->nama_lengkap);
                        $data[] = $init;
                    } else {
    
                        $gen = Absensi::select(
                            'master_karyawan.nik_karyawan',
                            'master_karyawan.nama_lengkap',
                            'absensi.tanggal',
                            \DB::raw("CASE WHEN MIN(jam) <= '14:00:00' THEN MIN(jam) ELSE '' END as masuk"),
                            \DB::raw("CASE WHEN MAX(jam) > '14:00:00' THEN MAX(jam) ELSE '' END as keluar")
                        )
                            ->where('absensi.karyawan_id', $request->id_karyawan)
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
                            $data[] = [
                                'nama' => $gen->nik_karyawan . ' - ' . $gen->nama_lengkap,
                                'tanggal' => $gen->tanggal,
                                'hari' => self::hari($gen->tanggal),
                                'masuk' => $gen->masuk,
                                'keluar' => $gen->keluar,
                                'selisih' => $masuk,
                                'jam_kerja' => $total_jam_kerja,
                                'shift' => 'SHREGULAR'
                            ];
                        } else {
                            $data[] = [
                                'nama' => $cekKaryawan->nik_karyawan . ' - ' . $cekKaryawan->nama_lengkap,
                                'tanggal' => $tanggal,
                                'hari' => self::hari($tanggal),
                                'masuk' => '',
                                'keluar' => '',
                                'selisih' => '',
                                'jam_kerja' => '',
                                'shift' => ''
                            ];
                        }
                    }
                }
    
                $u = 3;
                foreach ($data as $row) {
                    $sheet->setCellValue('A' . $u, ($u - 2));
                    $sheet->setCellValue('B' . $u, $row['nama']);
                    $sheet->setCellValue('C' . $u, $row['tanggal']);
                    $sheet->setCellValue('D' . $u, $row['hari']);
                    $sheet->setCellValue('E' . $u, $row['masuk']);
                    $sheet->setCellValue('F' . $u, $row['keluar']);
                    $sheet->setCellValue('G' . $u, $row['selisih']);
                    $sheet->setCellValue('H' . $u, $row['jam_kerja']);
                    $sheet->setCellValue('I' . $u, $row['shift']);
                    $u++;
                }
    
                $sheet->getStyle('A3:I' . ($u - 1))
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
    
                $sheet->setTitle($cekKaryawan->nama_lengkap);
                $path = \public_path() . '/absensi/';
    
                $writer = new Xlsx($spreadsheet);
                $fileName = 'Monthly-Absensi_' . $cekKaryawan->nik_karyawan . '_' .$request->bulan . '.xlsx';
                // $fileName = $cekKaryawan->nama_lengkap.'-'.$request->bulan.'.xlsx';
                $writer->save($path . $fileName);
    
                return response()->json([
                    'data' => $fileName
                ], 200);
    
            } else {
                
                // $dept = MasterDivisi::where('id', $request->id_department)
                //     ->where('is_active', true)->first();
    
                // $karyawan = MasterKaryawan::where('id_department', $request->id_department)
                //     ->where('is_active', true)->get();
                // $shiftKaryawan = [];
                
                // foreach ($karyawan as $key => $val) {
                //     dd($val);
                //     $nilai = explode("-", DATE('Y-m', \strtotime($request->tanggal)));
                //     $month = $nilai[1];
                //     $year = $nilai[0];
                //     $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                //     $data = [];
                //     for ($a = 1; $a <= $lastDay; $a++) {
                //         $split = sprintf("%02d", $a);
                //         $tanggal = $year . '-' . $month . '-' . $split;
    
                //         $cekShift = ShiftKaryawan::where('tanggal', $tanggal)->where('karyawan_id', $val->id)->first();
    
                //         $shift = 'Pagi';
                //         if ($cekShift != null) {
                //             $init = self::compareshift($val->id, $tanggal, $cekShift->shift, $cekShift->time_in, $cekShift->time_out, $val->nik_karyawan, $val->nama_lengkap);
    
                //             $data[] = $init;
                //         } else {
                //             $gen = Absensi::select(
                //                 'master_karyawan.nik_karyawan',
                //                 'master_karyawan.nama_lengkap',
                //                 'absensi.tanggal',
                //                 \DB::raw("CASE WHEN MIN(jam) <= '14:00:00' THEN MIN(jam) ELSE '' END as masuk"),
                //                 \DB::raw("CASE WHEN MAX(jam) > '14:00:00' THEN MAX(jam) ELSE '' END as keluar")
                //             )
                //                 ->where('absensi.karyawan_id', $val->id)
                //                 ->where('absensi.tanggal', $tanggal)
                //                 ->join('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id')
                //                 ->groupBy('absensi.tanggal', 'absensi.karyawan_id')
                //                 ->first();
    
                //             if ($gen != null) {
                //                 if ($gen->masuk < '08:00:00') {
                //                     $selisih_masuk = \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' 08:00:00'));
                //                     $masuk = '+' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                //                 } else {
                //                     $selisih_masuk = \date_diff(date_create($gen->tanggal . ' 08:00:00'), date_create($gen->tanggal . ' ' . $gen->masuk));
                //                     $masuk = '-' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                //                 }
    
                //                 $total_jam_kerja = '';
                //                 if ($gen->keluar != '') {
                //                     $kerja = \date_diff(date_create($gen->tanggal . ' ' . $gen->masuk), date_create($gen->tanggal . ' ' . $gen->keluar));
                //                     $total_jam_kerja = $kerja->h . 'h ' . $kerja->i . 'm';
                //                 }
                //                 $data[] = [
                //                     'nama' => $gen->nik_karyawan . ' - ' . $gen->nama_lengkap,
                //                     'tanggal' => $gen->tanggal,
                //                     'hari' => self::hari($gen->tanggal),
                //                     'masuk' => $gen->masuk,
                //                     'keluar' => $gen->keluar,
                //                     'selisih' => $masuk,
                //                     'jam_kerja' => $total_jam_kerja,
                //                     'shift' => 'SHREGULAR'
                //                 ];
                //             } else {
                //                 $data[] = [
                //                     'nama' => $val->nik_karyawan . ' - ' . $val->nama_lengkap,
                //                     'tanggal' => $tanggal,
                //                     'hari' => self::hari($tanggal),
                //                     'masuk' => '',
                //                     'keluar' => '',
                //                     'selisih' => '',
                //                     'jam_kerja' => '',
                //                     'shift' => ''
                //                 ];
                //             }
                //         }
                //     }
                //     dd($data);
                //     $shiftKaryawan[$val->id] = $data;
                //     // $data = [];
                // }
                // dd($shiftKaryawan);
                // $spreadsheet = new Spreadsheet();
                // $spreadsheet->createSheet();
                // $sheet = $spreadsheet->getSheet(0);
    
                // $i = 1;
                // foreach ($shiftKaryawan as $key => $val) {
                //     $sheet->mergeCells('A' . ($i) . ':A' . ($i + 1));
                //     $sheet->getStyle('A' . ($i) . ':A' . ($i + 1))->getAlignment()->setVertical('center');
                //     $sheet->getStyle('A' . ($i) . ':A' . ($i + 1))->getAlignment()->setHorizontal('center');
                //     $sheet->getColumnDimension('A')->setWidth(6);
                //     $sheet->mergeCells('B' . ($i) . ':B' . ($i + 1));
                //     $sheet->getStyle('B' . ($i) . ':B' . ($i + 1))->getAlignment()->setVertical('center');
                //     $sheet->getStyle('B' . ($i) . ':B' . ($i + 1))->getAlignment()->setHorizontal('center');
                //     $sheet->getColumnDimension('B')->setWidth(35);
                //     $sheet->mergeCells('C' . ($i) . ':C' . ($i + 1));
                //     $sheet->getStyle('C' . ($i) . ':C' . ($i + 1))->getAlignment()->setVertical('center');
                //     $sheet->getStyle('C' . ($i) . ':C' . ($i + 1))->getAlignment()->setHorizontal('center');
                //     $sheet->getColumnDimension('C')->setWidth(13);
                //     $sheet->mergeCells('D' . ($i) . ':D' . ($i + 1));
                //     $sheet->getStyle('D' . ($i) . ':D' . ($i + 1))->getAlignment()->setVertical('center');
                //     $sheet->getStyle('D' . ($i) . ':D' . ($i + 1))->getAlignment()->setHorizontal('center');
                //     $sheet->getColumnDimension('D')->setWidth(10);
                //     $sheet->mergeCells('E' . ($i) . ':F' . ($i));
                //     $sheet->getStyle('E:F')->getAlignment()->setHorizontal('center');
                //     $sheet->mergeCells('G' . ($i) . ':I' . ($i));
                //     $sheet->getStyle('G:I')->getAlignment()->setHorizontal('center');
                //     $sheet->getColumnDimension('I')->setWidth(25);
    
                //     $sheet->getStyle('A' . ($i) . ':I' . ($i))
                //         ->getBorders()
                //         ->getAllBorders()
                //         ->setBorderStyle(Border::BORDER_THIN);
                //     $sheet->getStyle('A' . ($i + 1) . ':I' . ($i + 1))
                //         ->getBorders()
                //         ->getAllBorders()
                //         ->setBorderStyle(Border::BORDER_THIN);
    
                //     $sheet->setCellValue('A' . ($i), 'No');
                //     $sheet->setCellValue('B' . ($i), 'Nama Karyawan');
                //     $sheet->setCellValue('C' . ($i), 'Tanggal');
                //     $sheet->setCellValue('D' . ($i), 'Hari');
                //     $sheet->setCellValue('E' . ($i), 'Absensi');
                //     $sheet->setCellValue('E' . ($i + 1), 'Masuk');
                //     $sheet->setCellValue('F' . ($i + 1), 'Keluar');
                //     $sheet->setCellValue('G' . ($i), 'Record');
                //     $sheet->setCellValue('G' . ($i + 1), ' + / -');
                //     $sheet->setCellValue('H' . ($i + 1), 'Jam Kerja');
                //     $sheet->setCellValue('I' . ($i + 1), 'Shift');
    
                //     $u = $i + 2;
                //     $num = 1;
                //     foreach ($val as $row) {
                //         $sheet->setCellValue('A' . $u, $num);
                //         $sheet->setCellValue('B' . $u, $row['nama']);
                //         $sheet->setCellValue('C' . $u, $row['tanggal']);
                //         $sheet->setCellValue('D' . $u, $row['hari']);
                //         $sheet->setCellValue('E' . $u, $row['masuk']);
                //         $sheet->setCellValue('F' . $u, $row['keluar']);
                //         $sheet->setCellValue('G' . $u, $row['selisih']);
                //         $sheet->setCellValue('H' . $u, $row['jam_kerja']);
                //         $sheet->setCellValue('I' . $u, $row['shift']);
                //         $u++;
                //         $num++;
                //     }
    
                //     $sheet->getStyle('A' . ($i + 2) . ':I' . ($u - 1))
                //         ->getBorders()
                //         ->getAllBorders()
                //         ->setBorderStyle(Border::BORDER_THIN);
    
                //     $i = $u + 2;
                // }
                // $sheet->setTitle($dept->nama_divisi);
    
                // $path = \public_path() . "/absensi/";
                // $fileName = 'Monthly-Absensi_' . $dept->kode_divisi . '_' . DATE('Y-m', \strtotime($request->tanggal)) . '.xlsx';
                // $writer = new Xlsx($spreadsheet);
                // $writer->save($path . $fileName);
    
                // return response()->json([
                //     'data' => $fileName
                // ], 200);

                $cekUser = MasterKaryawan::whereIn('id_cabang', $this->privilages)->where('active', 0)->get();

                $spreadsheet = new Spreadsheet();
                $i = 0;
                foreach($cekUser as $key => $val){
                    $spreadsheet->createSheet();
                    $sheet = $spreadsheet->getSheet($i);
                    $sheet->mergeCells('A1:A2');
                    $sheet->getStyle('A1:A2')->getAlignment()->setVertical('center');
                    $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal('center');
                    $sheet->getColumnDimension('A')->setWidth(6);
                    $sheet->mergeCells('B1:B2');
                    $sheet->getStyle('B1:B2')->getAlignment()->setVertical('center');
                    $sheet->getStyle('B1:B2')->getAlignment()->setHorizontal('center');
                    $sheet->getColumnDimension('B')->setWidth(35);
                    $sheet->mergeCells('C1:C2');
                    $sheet->getStyle('C1:C2')->getAlignment()->setVertical('center');
                    $sheet->getStyle('C1:C2')->getAlignment()->setHorizontal('center');
                    $sheet->getColumnDimension('C')->setWidth(13);
                    $sheet->mergeCells('D1:D2');
                    $sheet->getStyle('D1:D2')->getAlignment()->setVertical('center');
                    $sheet->getStyle('D1:D2')->getAlignment()->setHorizontal('center');
                    $sheet->getColumnDimension('D')->setWidth(10);
                    $sheet->mergeCells('E1:F1');
                    $sheet->getStyle('E:F')->getAlignment()->setHorizontal('center');
                    $sheet->mergeCells('G1:I1');
                    $sheet->getStyle('G:I')->getAlignment()->setHorizontal('center');
                    $sheet->getColumnDimension('I')->setWidth(25);


                    $sheet->getStyle('A1:I1')
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle('A2:I2')
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                    $sheet->setCellValue('A1', 'No');
                    $sheet->setCellValue('B1', 'Nama Karyawan');
                    $sheet->setCellValue('C1', 'Tanggal');
                    $sheet->setCellValue('D1', 'Hari');
                    $sheet->setCellValue('E1', 'Absensi');
                    $sheet->setCellValue('E2', 'Masuk');
                    $sheet->setCellValue('F2', 'Keluar');
                    $sheet->setCellValue('G1', 'Record');
                    $sheet->setCellValue('G2', ' + / -');
                    $sheet->setCellValue('H2', 'Jam Kerja');
                    $sheet->setCellValue('I2', 'Shift');


                    $nilai = explode("-", $request->bulan);
                    $month = $nilai[0];
                    $year = $nilai[1];
                    $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                    $data = [];
                    
                    for($a=1; $a<=$lastDay; $a++){
                        $split = sprintf("%02d", $a);
                        $tanggal = $year.'-'.$month.'-'.$split;

                        $cekShift = DB::table('shift_karyawan')->where('tanggal', $tanggal)->where('userid', $val->id)->first();
                        $cekKaryawan = DB::table('users')->where('id', $val->id)->first();
                        
                        $shift = 'Pagi';
                        if($cekShift!=null){
                            $init = self::compareshift($val->id, $tanggal, $cekShift->shift, $cekShift->time_in, $cekShift->time_out, $cekKaryawan->nik, $cekKaryawan->nama_lengkap);

                            $data[] =$init;
                        } else {
                            
                            // $date = DATE('Y-m-d', strtotime($tanggal));
                            
                            $gen = Checkinout::select(
                                'nik',
                                'nama_lengkap',
                                'tanggal',
                                \DB::raw("CASE WHEN MIN(jam) <= '14:00:00' THEN MIN(jam) ELSE '' END as masuk"),
                                \DB::raw("CASE WHEN MAX(jam) > '14:00:00' THEN MAX(jam) ELSE '' END as keluar")
                                )
                                ->where('userid', $val->id)
                                ->where('tanggal', $tanggal)
                                ->join('users', 'check_in_out.userid', '=', 'users.id')
                                ->groupBy('tanggal', 'userid')
                                ->first();
                            if($gen != null){
                                if($gen->masuk < '08:00:00'){
                                    $selisih_masuk = \date_diff(date_create($gen->tanggal.' '.$gen->masuk), date_create($gen->tanggal.' 08:00:00'));
                                    $masuk = '+'.(int)((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60).'m';
                                } else {
                                    $selisih_masuk = \date_diff(date_create($gen->tanggal.' 08:00:00'), date_create($gen->tanggal.' '.$gen->masuk));
                                    $masuk = '-'.(int)((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60).'m';
                                }

                                $total_jam_kerja = '';
                                    if($gen->keluar != ''){
                                        $kerja = \date_diff(date_create($gen->tanggal.' '.$gen->masuk), date_create($gen->tanggal.' '.$gen->keluar));
                                            $total_jam_kerja = $kerja->h .'h '.$kerja->i .'m';
                                    }
                                $data[] = [
                                    'nama' => $gen->nik. ' - ' .$gen->nama_lengkap,
                                    'tanggal' => $gen->tanggal,
                                    'hari' => self::hari($gen->tanggal),
                                    'masuk' => $gen->masuk,
                                    'keluar' => $gen->keluar,
                                    'selisih' => $masuk,
                                    'jam_kerja' => $total_jam_kerja,
                                    'shift' => 'SHREGULAR'
                                ];
                            } else {
                                $data[] = [
                                    'nama' => $cekKaryawan->nik. ' - ' .$cekKaryawan->nama_lengkap,
                                    'tanggal' => $tanggal,
                                    'hari' => self::hari($tanggal),
                                    'masuk' => '',
                                    'keluar' => '',
                                    'selisih' => '',
                                    'jam_kerja' => '',
                                    'shift' => ''
                                ];
                            }
                        }
                    }
                    
                    $u = 3;
                    foreach($data as $row){
                        $sheet->setCellValue('A'.$u, ($u - 2));
                        $sheet->setCellValue('B'.$u, $row['nama']);
                        $sheet->setCellValue('C'.$u, $row['tanggal']);
                        $sheet->setCellValue('D'.$u, $row['hari']);
                        $sheet->setCellValue('E'.$u, $row['masuk']);
                        $sheet->setCellValue('F'.$u, $row['keluar']);
                        $sheet->setCellValue('G'.$u, $row['selisih']);
                        $sheet->setCellValue('H'.$u, $row['jam_kerja']); 
                        $sheet->setCellValue('I'.$u, $row['shift']); 
                        $u++;
                    }
                    
                    $sheet->getStyle('A3:I'.($u - 1))
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                    
                    $sheet->setTitle($val->nama_lengkap);
                    $i++;
                }
                    
                $path = \public_path()."/absensi/";
                $fileName = 'Absensi Karyawan Periode '. $request->bulan .'.xlsx';
                $writer = new Xlsx($spreadsheet);
                $writer->save($fileName);

                return response()->json([
                    'data' => $fileName
                ], 200);
            }
        } catch (\Throwable $th) {
            dd($th);
        }
        
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