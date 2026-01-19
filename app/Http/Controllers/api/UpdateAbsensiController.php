<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use \App\Models\{Absensi, RekapMasukKerja, RekapLiburKalender, ShiftKaryawan, MasterKaryawan};
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;


class UpdateAbsensiController extends Controller
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
    // Need test from front-end
    public function updateJadwal(Request $request)
    {
        DB::beginTransaction();
        try {
            // dd($request->all());
            if ($request->masuk != '') {
                if ($request->id_masuk != '') {
                    Absensi::where('id', '!=', $request->id_masuk)
                        ->where('karyawan_id', $request->id)
                        ->where('tanggal', $request->tgl_masuk)
                        ->where('status', 'Masuk')->delete();

                    Absensi::where('id', $request->id_masuk)->update([
                        'kode_kartu' => NULL,
                        'jam' => $request->masuk,
                    ]);
                } else {
                    Absensi::insert([
                        'karyawan_id' => $request->id,
                        'tanggal' => $request->tgl,
                        'hari' => self::hari($request->tgl),
                        'jam' => $request->masuk,
                        'status' => 'Masuk',
                    ]);
                }
            }
            if ($request->keluar != '') {
                if ($request->id_keluar != '') {
                    Absensi::where('id', '!=', $request->id_keluar)
                        ->where('karyawan_id', $request->id)
                        ->where('tanggal', $request->tgl_keluar)
                        ->where('status', 'Keluar')->delete();

                    Absensi::where('id', $request->id_keluar)->update([
                        'kode_kartu' => NULL,
                        'jam' => $request->keluar,
                    ]);
                } else {
                    $tanggal = $request->tgl;
                    if ($request->shift == 'SHSECURITY2' || $request->shift == '24jam') {
                        $tanggal = DATE('Y-m-d', strtotime($request->tgl . '+1day'));
                    }
                    Absensi::insert([
                        'karyawan_id' => $request->id,
                        'tanggal' => $tanggal,
                        'hari' => self::hari($tanggal),
                        'jam' => $request->keluar,
                        'status' => 'Keluar',
                    ]);
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'Berhasil Update Absensi.!'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // Tested - Clear
    public function generateJadwal(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            if ($request->absensi) {
                foreach ($request->absensi as $key => $value) {
                    $dataAbsen = json_decode(json_encode($value));
                    if ($dataAbsen->masuk != '') {
                        if ($dataAbsen->id_masuk != '') {
                            Absensi::where('id', '!=', $dataAbsen->id_masuk)
                                ->where('karyawan_id', $dataAbsen->id)
                                ->where('tanggal', $dataAbsen->tgl_masuk)
                                ->where('status', 'Masuk')->delete();
        
                            Absensi::where('id', $dataAbsen->id_masuk)->update([
                                'kode_kartu' => NULL,
                                'jam' => $dataAbsen->masuk,
                            ]);
                        } else {
                            Absensi::insert([
                                'karyawan_id' => $dataAbsen->id,
                                'tanggal' => $dataAbsen->tgl,
                                'hari' => self::hari($dataAbsen->tgl),
                                'jam' => $dataAbsen->masuk,
                                'status' => 'Masuk',
                            ]);
                        }
                    }
                    if ($dataAbsen->keluar != '') {
                        if ($dataAbsen->id_keluar != '') {
                            Absensi::where('id', '!=', $dataAbsen->id_keluar)
                                ->where('karyawan_id', $dataAbsen->id)
                                ->where('tanggal', $dataAbsen->tgl_keluar)
                                ->where('status', 'Keluar')->delete();
        
                            Absensi::where('id', $dataAbsen->id_keluar)->update([
                                'kode_kartu' => NULL,
                                'jam' => $dataAbsen->keluar,
                            ]);
                        } else {
                            $tanggal = $dataAbsen->tgl;
                            if ($dataAbsen->shift == 'SHSECURITY2' || $dataAbsen->shift == '24jam') {
                                $tanggal = DATE('Y-m-d', strtotime($dataAbsen->tgl . '+1day'));
                            }
                            Absensi::insert([
                                'karyawan_id' => $dataAbsen->id,
                                'tanggal' => $tanggal,
                                'hari' => self::hari($tanggal),
                                'jam' => $dataAbsen->keluar,
                                'status' => 'Keluar',
                            ]);
                        }
                    }
                }
            }
            $bulan = explode("-", $request->bulan);
            $cek = RekapMasukKerja::where('karyawan_id', $request->id_karyawan)
                ->where('tahun', $bulan[0])
                ->where('bulan', $request->bulan)
                ->where('is_active', true)
                ->first();
            if ($cek) {
                RekapMasukKerja::where('karyawan_id', $request->id_karyawan)
                    ->where('tahun', $bulan[0])
                    ->where('bulan', $request->bulan)
                    ->where('is_active', true)
                    ->update([
                        'rejected_by' => $this->karyawan,
                        'rejected_at' => date('Y-m-d H:i:s'),
                        'is_active' => false
                    ]);
            }

            $karyawan_id = $request->id_karyawan;
            $hari_kerja = RekapLiburKalender::where('tahun', $bulan[0])
                ->where('is_active', true)
                ->first();

            if( !$hari_kerja ) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Kalender hari kerja belum di set untuk tahun ' . $bulan[0] . '.'
                ], 500);
            }
            $tgl_kerja = '';
            $masuk_kerja = [];

            

            foreach (json_decode($hari_kerja->tanggal) as $key => $value) {
                if ($key == $bulan[0] . '-' . $bulan[1]) {
                    $tgl_kerja = $value;
                }
            }

            // Rubah Kode menjadi for each Selesaikan Rabu
            // $datas = json_encode($request->data, JSON_PRETTY_PRINT);
            // $decoded = json_decode($datas, true);
            // dd(json_decode($request->data, true));
            // dd($data->keluar);
            foreach($request->data as $data) {
                if($data['keluar'] != '' && $data['masuk'] != ''){
                    if (in_array($data['shift'], ['SHOB', 'SHOB2', 'SHSECURITY', 'SHSECURITY2', '24jam'])) {
                        array_push($masuk_kerja, $data['tanggal']);
                    }else if ($data['shift'] != 'off') {
                        array_push($masuk_kerja, $data['tanggal']);
                    }
                }
            }

            // foreach ($request->tanggal as $key => $value) {
            //     if ($request->masuk[$key] != '' && $request->keluar[$key] != '') {
            //         if (in_array($request->shift[$key], ['SHOB', 'SHOB2', 'SHSECURITY', 'SHSECURITY2', '24jam'])) {
            //             array_push($masuk_kerja, $request->tanggal[$key]);
            //         } else if ($request->shift[$key] != 'off') {
            //             if (in_array($request->tanggal[$key], $tgl_kerja)) {
            //                 array_push($masuk_kerja, $request->tanggal[$key]);
            //             }
            //         }
            //     }
            // }

            RekapMasukKerja::insert([
                'karyawan_id' => $request->id_karyawan,
                'tahun' => $bulan[0],
                'bulan' => $request->bulan,
                'tanggal' => json_encode($masuk_kerja),
                'added_by' => $this->karyawan,
                'added_at' => date('Y-m-d H:i:s')
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Berhasil Generate Absensi.!'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function SelectUserbyDivisi(Request $request)
    {   
        $reqBulan = $request->bulan;
        $data = MasterKaryawan::with(['jabatan','divisi','rekap' => function ($query) use ($reqBulan){
            $query->where('bulan', $reqBulan)
            ->where('is_active', true)
            ->get();
        }])
            ->whereIn('id_cabang', $this->privilageCabang)
            ->whereRaw(('CASE WHEN is_active = 0 THEN CAST(NOW() as DATE) <= DATE_ADD(effective_date, INTERVAL 1 month) ELSE is_active = 1 END'));
            // ->where('is_active', true);

        if ($request->id_jabatan) {
            $data->where('id_jabatan', $request->id_jabatan);
        } else if ($request->departement && $request->departement !== 'all') {
            $data->where('id_department', $request->departement);
        }

        $datas = $data->get();

        return datatables()->of($data)->make(true);
    }

    public function generateJadwalBackup(Request $request)
    {
        DB::beginTransaction();
        try {
            $bulan = explode("-", $request->bulan);
            $cek = RekapMasukKerja::where('karyawan_id', $request->id_karyawan)
                ->where('tahun', $bulan[0])
                ->where('bulan', $request->bulan)
                ->where('is_active', true)
                ->first();
            if ($cek) {
                RekapMasukKerja::where('karyawan_id', $request->id_karyawan)
                    ->where('tahun', $bulan[0])
                    ->where('bulan', $request->bulan)
                    ->where('is_active', true)
                    ->update([
                        'rejected_by' => $this->karyawan,
                        'rejected_at' => date('Y-m-d H:i:s'),
                        'is_active' => false
                    ]);
            }

            $karyawan_id = $request->id_karyawan;
            $bulan = explode("-", $request->bulan);
            $hari_kerja = RekapLiburKalender::where('tahun', $bulan[0])
                ->where('is_active', true)
                ->first();
            $tgl_kerja = '';
            $masuk_kerja = [];

            foreach (json_decode($hari_kerja->tanggal) as $key => $value) {
                if ($key == $bulan[0] . '-' . $bulan[1]) {
                    $tgl_kerja = $value;
                }
            }

            foreach ($request->tanggal as $key => $value) {
                if ($request->masuk[$key] != '' && $request->keluar[$key] != '') {
                    if (in_array($request->shift[$key], ['SHOB', 'SHOB2', 'SHSECURITY', 'SHSECURITY2', '24jam'])) {
                        array_push($masuk_kerja, $request->tanggal[$key]);
                    } else if ($request->shift[$key] != 'off') {
                        if (in_array($request->tanggal[$key], $tgl_kerja)) {
                            array_push($masuk_kerja, $request->tanggal[$key]);
                        }
                    }
                }
            }

            RekapMasukKerja::insert([
                'karyawan_id' => $request->id_karyawan,
                'tahun' => $bulan[0],
                'bulan' => $request->bulan,
                'tanggal' => json_encode($masuk_kerja),
                'added_by' => $this->karyawan,
                'added_at' => date('Y-m-d H:i:s')
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Berhasil Generate Absensi.!'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // Tested - Clear
    public function indexAbsen(Request $request)
    {
        try {
            date_default_timezone_set('Asia/Jakarta');
            $nilai = explode("-", $request->tgl);
            $month = $nilai[1];
            $year = $nilai[0];

            $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $data = [];

            for ($i = 1; $i <= $lastDay; $i++) {
                $num = sprintf("%02d", $i);
                $tanggal = $year . '-' . $month . '-' . $num;
                $db = $year;
                $cekShift = ShiftKaryawan::where('tanggal', $tanggal)->where('karyawan_id', $request->id_karyawan)->first();
                $cekKaryawan = MasterKaryawan::where('id', $request->id_karyawan)->first();

                $id = $request->id_karyawan;
                // dd($cekKaryawan);
                $nik_karyawan = $cekKaryawan->nik_karyawan;
                $nama = $cekKaryawan->nama_lengkap;

                if ($cekShift != null) {
                    $shift = $cekShift->shift;
                    $checkin = '08:00:00';
                    $checkout = '17:00:00';
                    if ($shift == '24jam') {
                        $plus = DATE('Y-m-d', strtotime($tanggal . ' +1day'));

                        $masuk = Absensi::select('master_karyawan.nik_karyawan', 'master_karyawan.nama_lengkap', 'absensi.tanggal', 'absensi.id', 'absensi.jam', 'absensi.kode_kartu')
                            ->leftJoin('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id')
                            ->where('absensi.tanggal', $tanggal)
                            ->where('absensi.karyawan_id', $id)
                            ->where('absensi.jam', '>', '00:00:00')
                            ->whereIn('absensi.id', function ($query) use ($tanggal, $id) {
                                $query->select(DB::raw('MIN(id)'))
                                    ->from('absensi')
                                    ->where('tanggal', $tanggal)
                                    ->where('karyawan_id', $id);
                            })
                            ->first();

                        $keluar = Absensi::select('master_karyawan.nik_karyawan', 'master_karyawan.nama_lengkap', 'absensi.tanggal', 'absensi.id', 'absensi.jam', 'absensi.kode_kartu')
                            ->leftJoin('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id')
                            ->where('absensi.tanggal', $plus)
                            ->where('absensi.karyawan_id', $id)
                            ->where('absensi.jam', '<', '14:00:00')
                            ->whereIn('absensi.id', function ($query) use ($plus, $id) {
                                $query->select(DB::raw('MAX(id)'))
                                    ->from('absensi')
                                    ->where('tanggal', $plus)
                                    ->where('karyawan_id', $id);
                            })
                            ->orderBy('absensi.jam', 'desc')
                            ->first();

                        $tgl_masuk = '';
                        $tgl_keluar = '';
                        $jam_masuk = '';
                        $jam_keluar = '';
                        $id_masuk = '';
                        $id_keluar = '';
                        $kode_kartu_masuk = '';
                        $kode_kartu_keluar = '';

                        if ($masuk) {
                            $tgl_masuk = $masuk->tanggal;
                            $jam_masuk = $masuk->jam;
                            $id_masuk = $masuk->id;
                            $kode_kartu_masuk = $masuk->kode_kartu;
                        }
                        if ($keluar) {
                            $tgl_keluar = $keluar->tanggal;
                            $jam_keluar = $keluar->jam;
                            $id_keluar = $keluar->id;
                            $kode_kartu_keluar = $keluar->kode_kartu;
                        }

                        $masuk = '';
                        if ($jam_masuk < '08:00:00' && $jam_masuk != '-') {
                            $selisih_masuk = \date_diff(date_create($tanggal . ' ' . $jam_masuk), date_create($tanggal . ' 08:00:00'));
                            $masuk = '+' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                        } else if ($jam_masuk > '08:00:00' && $jam_masuk != '00:00:00') {
                            $selisih_masuk = \date_diff(date_create($tanggal . ' 08:00:00'), date_create($tanggal . ' ' . $jam_masuk));
                            $masuk = '-' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                        }

                        $total_jam_kerja = '';
                        if ($jam_masuk != '00:00:00' && $jam_keluar != '-') {
                            if ($jam_keluar != '-' && $jam_masuk != '-') {
                                $kerja = \date_diff(date_create($tgl_masuk . ' ' . $jam_masuk), date_create($tgl_keluar . ' ' . $jam_keluar));
                                $total_jam_kerja = $kerja->h . 'h ' . $kerja->i . 'm';
                            }
                        }

                        $data[] = [
                            'nama' => $nik_karyawan . ' - ' . $nama,
                            'karyawan_id' => $request->id_karyawan,
                            'tanggal' => $tanggal,
                            'hari' => self::hari($tanggal),
                            'masuk' => $jam_masuk,
                            'keluar' => $jam_keluar,
                            'tgl_masuk' => $tgl_masuk,
                            'tgl_keluar' => $tgl_keluar,
                            'id_masuk' => $id_masuk,
                            'id_keluar' => $id_keluar,
                            'selisih' => $masuk,
                            'jam_kerja' => $total_jam_kerja,
                            'kode_kartu_masuk' => $kode_kartu_masuk,
                            'kode_kartu_keluar' => $kode_kartu_keluar,
                            'shift' => '24JAM',
                        ];
                    } else if ($shift == 'SHSECURITY2') {
                        $plus = DATE('Y-m-d', strtotime($tanggal . ' +1day'));

                        $masuk = Absensi::leftJoin('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id')
                            ->select('master_karyawan.nik_karyawan', 'master_karyawan.nama_lengkap', 'absensi.tanggal', 'absensi.id', 'absensi.jam', 'absensi.kode_kartu')
                            ->where('absensi.tanggal', $tanggal)
                            ->where('absensi.karyawan_id', $id)
                            ->whereIn('absensi.id', function ($query) use ($tanggal, $id) {
                                $query->select(DB::raw('MAX(id)'))
                                    ->from('absensi')
                                    ->where('tanggal', $tanggal)
                                    ->where('karyawan_id', $id)
                                    ->where(DB::raw('jam'), DB::raw('(SELECT MAX(jam) FROM absensi WHERE tanggal = "' . $tanggal . '" AND karyawan_id = "' . $id . '")'));
                            })
                            ->first();

                        $keluar = Absensi::select(
                            'absensi.id',
                            'absensi.karyawan_id',
                            'master_karyawan.nik_karyawan',
                            'master_karyawan.nama_lengkap',
                            'absensi.tanggal',
                            'absensi.jam',
                            'absensi.kode_kartu'
                        )
                            ->where('karyawan_id', $id)
                            ->where('jam', '<', '14:00:00')
                            ->where('tanggal', $plus)
                            ->join('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id')
                            ->whereIn('absensi.id', function ($query) use ($id, $plus) {
                                $query->select(DB::raw('MIN(id)'))
                                    ->from('absensi')
                                    ->where('karyawan_id', $id)
                                    ->where('tanggal', $plus);
                            })
                            ->orderBy('jam', 'asc')
                            ->first();

                        $jam_masuk = '';
                        $jam_keluar = '';
                        $id_masuk = '';
                        $id_keluar = '';
                        $tgl_masuk = '';
                        $tgl_keluar = '';
                        $kode_kartu_masuk = '';
                        $kode_kartu_keluar = '';

                        if ($masuk) {
                            $tgl_masuk = $masuk->tanggal;
                            $jam_masuk = $masuk->jam;
                            $id_masuk = $masuk->id;
                            $kode_kartu_masuk = $masuk->kode_kartu;
                        }
                        if ($keluar) {
                            $tgl_keluar = $keluar->tanggal;
                            $jam_keluar = $keluar->jam;
                            $id_keluar = $keluar->id;
                            $kode_kartu_keluar = $keluar->kode_kartu;
                        }

                        $masuk = '';
                        if ($jam_masuk < '08:00:00' && $jam_masuk != '-') {
                            $selisih_masuk = \date_diff(date_create($tanggal . ' ' . $jam_masuk), date_create($tanggal . ' 08:00:00'));
                            $masuk = '+' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                        } else if ($jam_masuk > '08:00:00' && $jam_masuk != '00:00:00') {
                            $selisih_masuk = \date_diff(date_create($tanggal . ' 08:00:00'), date_create($tanggal . ' ' . $jam_masuk));
                            $masuk = '-' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                        }

                        $total_jam_kerja = '';
                        if ($jam_masuk != '00:00:00' && $jam_keluar != '-') {
                            if ($jam_keluar != '-' && $jam_masuk != '-') {
                                $kerja = \date_diff(date_create($tgl_masuk . ' ' . $jam_masuk), date_create($tgl_keluar . ' ' . $jam_keluar));
                                $total_jam_kerja = $kerja->h . 'h ' . $kerja->i . 'm';
                            }
                        }

                        $data[] = [
                            'nama' => $nik_karyawan . ' - ' . $nama,
                            'karyawan_id' => $request->id_karyawan,
                            'tanggal' => $tanggal,
                            'hari' => self::hari($tanggal),
                            'masuk' => $jam_masuk,
                            'keluar' => $jam_keluar,
                            'tgl_masuk' => $tgl_masuk,
                            'tgl_keluar' => $tgl_keluar,
                            'id_masuk' => $id_masuk,
                            'id_keluar' => $id_keluar,
                            'selisih' => $masuk,
                            'jam_kerja' => $total_jam_kerja,
                            'kode_kartu_masuk' => $kode_kartu_masuk,
                            'kode_kartu_keluar' => $kode_kartu_keluar,
                            'shift' => 'SHSECURITY2'
                        ];
                    } else if ($shift == 'off') {
                        $data[] = [
                            'nama' => $nik_karyawan . ' - ' . $nama,
                            'karyawan_id' => $request->id_karyawan,
                            'tanggal' => $tanggal,
                            'hari' => self::hari($tanggal),
                            'masuk' => '',
                            'keluar' => '',
                            'tgl_masuk' => '',
                            'tgl_keluar' => '',
                            'kode_kartu_masuk' => '',
                            'kode_kartu_keluar' => '',
                            'id_masuk' => '',
                            'id_keluar' => '',
                            'selisih' => '',
                            'jam_kerja' => '',
                            'shift' => 'OFF'
                        ];
                    } else {
                        $masuk = Absensi::select(
                            'absensi.id',
                            'absensi.karyawan_id',
                            'master_karyawan.nik_karyawan',
                            'master_karyawan.nama_lengkap',
                            'absensi.tanggal',
                            'absensi.jam',
                            'absensi.kode_kartu'
                        )
                            ->where('karyawan_id', $request->id_karyawan)
                            ->where('jam', '<=', '14:00:00')
                            ->where('tanggal', $tanggal)
                            ->join('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id')
                            ->whereIn('absensi.id', function ($query) use ($request, $tanggal) {
                                $query->select(DB::raw('MIN(id)'))
                                    ->from('absensi')
                                    ->where('karyawan_id', $request->id_karyawan)
                                    ->where('tanggal', $tanggal);
                            })
                            ->orderBy('jam', 'asc')
                            ->first();

                        $keluar = Absensi::select(
                            'absensi.id',
                            'absensi.karyawan_id',
                            'master_karyawan.nik_karyawan',
                            'master_karyawan.nama_lengkap',
                            'absensi.tanggal',
                            'absensi.jam',
                            'absensi.kode_kartu'
                        )
                            ->where('karyawan_id', $request->id_karyawan)
                            ->where('jam', '>', '14:00:00')
                            ->where('tanggal', $tanggal)
                            ->join('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id')
                            ->whereIn('absensi.id', function ($query) use ($request, $tanggal) {
                                $query->select(DB::raw('MAX(id)'))
                                    ->from('absensi')
                                    ->where('karyawan_id', $request->id_karyawan)
                                    ->where('tanggal', $tanggal);
                            })
                            ->orderBy('jam', 'desc')
                            ->first();

                        $jam_masuk = '';
                        $jam_keluar = '';
                        $id_masuk = '';
                        $id_keluar = '';
                        $tgl_masuk = '';
                        $tgl_keluar = '';
                        $kode_kartu_masuk = '';
                        $kode_kartu_keluar = '';

                        if ($masuk) {
                            $tgl_masuk = $masuk->tanggal;
                            $jam_masuk = $masuk->jam;
                            $id_masuk = $masuk->id;
                            $kode_kartu_masuk = $masuk->kode_kartu;
                        }
                        if ($keluar) {
                            $tgl_keluar = $keluar->tanggal;
                            $jam_keluar = $keluar->jam;
                            $id_keluar = $keluar->id;
                            $kode_kartu_keluar = $keluar->kode_kartu;
                        }

                        $masuk = '';
                        if ($jam_masuk < '08:00:00' && $jam_masuk != '-') {
                            $selisih_masuk = \date_diff(date_create($tanggal . ' ' . $jam_masuk), date_create($tanggal . ' 08:00:00'));
                            $masuk = '+' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                        } else if ($jam_masuk > '08:00:00' && $jam_masuk != '00:00:00') {
                            $selisih_masuk = \date_diff(date_create($tanggal . ' 08:00:00'), date_create($tanggal . ' ' . $jam_masuk));
                            $masuk = '-' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                        }

                        $total_jam_kerja = '';
                        if ($jam_masuk != '00:00:00' && $jam_keluar != '-') {
                            if ($jam_keluar != '-' && $jam_masuk != '-') {
                                $kerja = \date_diff(date_create($tgl_masuk . ' ' . $jam_masuk), date_create($tgl_keluar . ' ' . $jam_keluar));
                                $total_jam_kerja = $kerja->h . 'h ' . $kerja->i . 'm';
                            }
                        }

                        $data[] = [
                            'nama' => $nik_karyawan . ' - ' . $nama,
                            'karyawan_id' => $request->id_karyawan,
                            'tanggal' => $tanggal,
                            'hari' => self::hari($tanggal),
                            'masuk' => $jam_masuk,
                            'keluar' => $jam_keluar,
                            'tgl_masuk' => $tgl_masuk,
                            'tgl_keluar' => $tgl_keluar,
                            'id_masuk' => $id_masuk,
                            'id_keluar' => $id_keluar,
                            'selisih' => $masuk,
                            'jam_kerja' => $total_jam_kerja,
                            'kode_kartu_masuk' => $kode_kartu_masuk,
                            'kode_kartu_keluar' => $kode_kartu_keluar,
                            'shift' => $shift
                        ];
                    }
                } else {
                    $masuk = Absensi::select(
                        'absensi.id',
                        'absensi.karyawan_id',
                        'master_karyawan.nik_karyawan',
                        'master_karyawan.nama_lengkap',
                        'absensi.tanggal',
                        'absensi.jam',
                        'absensi.kode_kartu'
                    )
                        ->where('karyawan_id', $request->id_karyawan)
                        ->where('jam', '<=', '14:00:00')
                        ->where('tanggal', $tanggal)
                        ->join('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id')
                        ->whereIn('absensi.id', function ($query) use ($request, $tanggal) {
                            $query->select(DB::raw('MIN(id)'))
                                ->from('absensi')
                                ->where('karyawan_id', $request->id_karyawan)
                                ->where('tanggal', $tanggal);
                        })
                        ->orderBy('jam', 'asc')
                        ->first();
                    $keluar = Absensi::select(
                        'absensi.id',
                        'absensi.karyawan_id',
                        'master_karyawan.nik_karyawan',
                        'master_karyawan.nama_lengkap',
                        'absensi.tanggal',
                        'absensi.jam',
                        'absensi.kode_kartu'
                    )
                        ->where('karyawan_id', $request->id_karyawan)
                        ->where('jam', '>', '14:00:00')
                        ->where('tanggal', $tanggal)
                        ->join('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id')
                        ->whereIn('absensi.id', function ($query) use ($request, $tanggal) {
                            $query->select(DB::raw('MAX(id)'))
                                ->from('absensi')
                                ->where('karyawan_id', $request->id_karyawan)
                                ->where('tanggal', $tanggal);
                        })
                        ->orderBy('jam', 'desc')
                        ->first();

                    $jam_masuk = '';
                    $jam_keluar = '';
                    $id_masuk = '';
                    $id_keluar = '';
                    $tgl_masuk = '';
                    $tgl_keluar = '';
                    $kode_kartu_masuk = '';
                    $kode_kartu_keluar = '';

                    if ($masuk) {
                        $tgl_masuk = $masuk->tanggal;
                        $jam_masuk = $masuk->jam;
                        $id_masuk = $masuk->id;
                        $kode_kartu_masuk = $masuk->kode_kartu;
                    }
                    if ($keluar) {
                        $tgl_keluar = $keluar->tanggal;
                        $jam_keluar = $keluar->jam;
                        $id_keluar = $keluar->id;
                        $kode_kartu_keluar = $keluar->kode_kartu;
                    }

                    $masuk = '';
                    if ($jam_masuk < '08:00:00' && $jam_masuk != '-') {
                        $selisih_masuk = \date_diff(date_create($tanggal . ' ' . $jam_masuk), date_create($tanggal . ' 08:00:00'));
                        $masuk = '+' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                    } else if ($jam_masuk > '08:00:00' && $jam_masuk != '00:00:00') {
                        $selisih_masuk = \date_diff(date_create($tanggal . ' 08:00:00'), date_create($tanggal . ' ' . $jam_masuk));
                        $masuk = '-' . (int) ((($selisih_masuk->h * 3600) + ($selisih_masuk->i * 60) + $selisih_masuk->s) / 60) . 'm';
                    }

                    $total_jam_kerja = '';
                    if ($jam_masuk != '00:00:00' && $jam_keluar != '-') {
                        if ($jam_keluar != '-' && $jam_masuk != '-') {
                            $kerja = \date_diff(date_create($tgl_masuk . ' ' . $jam_masuk), date_create($tgl_keluar . ' ' . $jam_keluar));
                            $total_jam_kerja = $kerja->h . 'h ' . $kerja->i . 'm';
                        }
                    }

                    $data[] = [
                        'nama' => $nik_karyawan . ' - ' . $nama,
                        'karyawan_id' => $request->id_karyawan,
                        'tanggal' => $tanggal,
                        'hari' => self::hari($tanggal),
                        'masuk' => $jam_masuk,
                        'keluar' => $jam_keluar,
                        'tgl_masuk' => $tgl_masuk,
                        'tgl_keluar' => $tgl_keluar,
                        'id_masuk' => $id_masuk,
                        'id_keluar' => $id_keluar,
                        'selisih' => $masuk,
                        'jam_kerja' => $total_jam_kerja,
                        'kode_kartu_masuk' => $kode_kartu_masuk,
                        'kode_kartu_keluar' => $kode_kartu_keluar,
                        'shift' => 'SHREGULAR'
                    ];
                }
            }
            return response()->json([
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            dd($e);
            return response()->json([
                'message' => $e->getMessage()
            ], 401);
        }
    }
}
