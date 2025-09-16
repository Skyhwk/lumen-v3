<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganSenyawaVolatile;

// DETAIL LAPANGAN
use App\Models\DetailLingkunganHidup;
use App\Models\DetailLingkunganKerja;
use App\Models\DetailSenyawaVolatile;

// MASTER DATA
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\MasterSubKategori;
use App\Models\MasterKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\ParameterFdl;

// SERVICE
use App\Services\SendTelegram;
use App\Services\GetAtasan;
use App\Services\InsertActivityFdl;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlSenyawaVolatileController extends Controller
{
    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->whereIn('kategori_3', ['11-Udara Ambient', '27-Udara Lingkungan Kerja'])->where('is_active', true)->first();
            // dd($data);
            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sampel tidak ditemukan..'
                ], 401);
            } else {
                $senyawa = DataLapanganSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                if ($senyawa !== NULL) {
                    \DB::statement("SET SQL_MODE=''");
                    $param = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->groupBy('parameter')->get();
                    $parNonSes = array();
                    foreach ($param as $value) {
                        if ($value->shift_pengambilan != 'Sesaat') {
                            $p = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $value->parameter)->get();
                            $l = $value->shift_pengambilan;
                            $li = explode("-", $l);
                            $shift = '';

                            if (str_contains($value->parameter, 'PM')) {
                                if ($li[0] == '24 Jam') {
                                    $shift = 25;
                                } else if ($li[0] == '8 Jam') {
                                    $shift = 8;
                                } else if ($li[0] == '6 Jam') {
                                    $shift = 6;
                                }
                            } else if (str_contains($value->parameter, 'TSP')) {
                                if ($li[0] == '24 Jam') {
                                    $shift = 24;
                                } else if ($li[0] == '8 Jam') {
                                    $shift = 8;
                                }
                            } else {
                                if ($li[0] == '24 Jam') {
                                    $shift = 4;
                                } else if ($li[0] == '8 Jam') {
                                    $shift = 3;
                                }
                            }
                            if ($shift > count($p)) {
                                $parNonSes[] = $value->parameter;
                            }
                        }
                    }
                    $p = json_decode($data->parameter);
                    $nilai_param = array();
                    $nilai_param2 = array();
                    foreach ($param as $key => $value) {
                        $nilai_param[] =  $value->parameter;
                    }
                    $param1 = array_diff($p, $nilai_param);
                    foreach ($param1 as $ke => $val) {
                        $nilai_param2[] =  $val;
                    }
                    $pp1 = str_replace("[", "", json_encode($nilai_param2));
                    $pp2 = str_replace("]", "", $pp1);
                    $pp3 = str_replace("[", "", json_encode($parNonSes));
                    $pp4 = str_replace("]", "", $pp3);

                    if ($pp2 == '') {
                        $param_fin = json_encode($parNonSes);
                    } else if ($pp4 == "") {
                        $param_fin = '[' . $pp2 . ']';
                    } else if ($pp2 !== "") {
                        $param_fin = '[' . $pp4 . ',' . $pp2 . ']';
                    }
                    $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                    return response()->json([
                        'no_sampel'    => $data->no_sampel,
                        'jenis'        => $cek->nama_sub_kategori,
                        'keterangan' => $data->keterangan_1,
                        'id_ket' => explode('-', $data->kategori_3)[0],
                        'param' => $param_fin
                    ], 200);
                }else {
                    $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                    return response()->json([
                        'no_sampel'    => $data->no_sampel,
                        'jenis'        => $cek->nama_sub_kategori,
                        'keterangan' => $data->keterangan_1,
                        'id_ket' => explode('-', $data->kategori_3)[0],
                        'param' => $data->parameter
                    ], 200);
                }
            }
        } else {
            return response()->json([
                'message' => 'Fatal Error'
            ], 401);
        }
    }

    public function getShift(Request $request){
        // $importantKeyword = [
        //     "As", "Asam Asetat", "Asbestos", "Carbon Dust", "Ba", "Cl-", "Cl2", "Co", "Cr",
        //     "Cu", "Cd", "Fe", "H2S", "H2SO4", "HCl", "HF", "Hg", "Kelembaban", "Laju Ventilasi",
        //     "Mn", "NH3", "Ni", "NO2", "NOx", "O3", "Oil Mist", "Ox", "Passive NO2", "Passive SO2",
        //     "Pb", "Sb", "Se", "Sn", "SO2", "Suhu", "TSP", "Zn", "Pertukaran Udara",
        //     "Aluminium", "Silica Crystaline 8 Jam", "Ortho Cresol", "Dustfall"
        // ];

        $parameter_tsp = ParameterFdl::select("parameters")->where('is_active', 1)->where('nama_fdl','parameter_tsp_lk')->first();
        // $parameter_tsp = ["TSP", "TSP (24 Jam)", "TSP (6 Jam)", "TSP (8 Jam)", "As", "Cd", "Cr", "Cu", "Fe",
        //     "Fe (8 Jam)", "Hg", "Sb", "Se", "Sn", "Zn", "Pb", "Pb (24 Jam)", "Pb (6 Jam)", "Pb (8 Jam)", "Mn", "Ni"
        // ];

        $parameter_no2 = [
            "NO2", "NO2 (24 Jam)", "NO2 (8 Jam)", "NO2 (6 Jam)", "NOx"
        ];

        $data = DetailSenyawaVolatile::where('no_sampel', $request->no_sample)->where('shift_pengambilan', $request->shift)->first();
        $po = OrderDetail::where('no_sampel', $request->no_sample)->whereIn('kategori_3', ['11-Udara Ambient', '27-Udara Lingkungan Kerja'])->where('is_active', true)->first();
        \DB::statement("SET SQL_MODE=''");

        $param = DetailSenyawaVolatile::where('no_sampel', $request->no_sample)->groupBy('parameter')->get();
        $parNonSes = array();


        foreach ($param as $value) {
            // if ($value->kategori_pengujian != 'Sesaat') {
                $p = DetailSenyawaVolatile::where('no_sampel', $request->no_sample)->where('parameter', $value->parameter)->get();
                $l = $value->kategori_pengujian;
                $li = explode("-", $l);
                $shift = '';
                if (str_contains($value->parameter, 'TSP')) {
                    if ($li[0] == '24 Jam') {
                        $shift = 24; 
                    } else if ($li[0] == '8 Jam') {
                        $shift = 8;
                    }
                } else {
                    if ($li[0] == '24 Jam') {
                        $shift = 4;
                    } else if ($li[0] == '8 Jam') {
                        $shift = 3;
                    }else if ($li[0] == '3 Jam') {
                        $shift = 3;
                    }
                }
                if ($shift > count($p)) {
                    $parNonSes[] = $value->parameter;
                }
            // }
        }

        $p = json_decode($po->parameter);
        
        $nilai_param = array();
        $nilai_param2 = array();

        // Membersihkan array $p agar hanya menyimpan bagian setelah ";"
        $cleaned_p = array_map(function($item) {
            $parts = explode(";", $item);
            return $parts[1] ?? ''; // Ambil bagian setelah ";"
        }, $p);

        // Bandingkan dengan array yang sudah bersih
        $param1 = array_diff($cleaned_p, $nilai_param);

        foreach ($param1 as $ke => $val) {
            $nilai_param2[] =  $val;
        }

        $pp1 = str_replace("[", "", json_encode($nilai_param2));
        $pp2 = str_replace("]", "", $pp1);
        $pp3 = str_replace("[", "", json_encode($parNonSes));
        $pp4 = str_replace("]", "", $pp3);

        if ($pp2 == '') {
            $param_fin = json_encode($parNonSes);
        } else if ($pp4 == "") {
            $param_fin = '[' . $pp2 . ']';
        } else if ($pp2 !== "") {
            $param_fin = '[' . $pp4 . ',' . $pp2 . ']';
        }

        // Daftar durasi yang membuat shift tetap 'L1'
        $durasi = ['3 Jam', '6 Jam', '8 Jam', '24 Jam'];

        // Ambil semua parameter dari ketiga tabel dengan shift awal 'L1'
        $lh_params_check = DetailLingkunganHidup::where('no_sampel', $request->no_sample)
            ->where('shift_pengambilan', 'L1')
            ->pluck('parameter')
            ->toArray();

        $lk_params_check = DetailLingkunganKerja::where('no_sampel', $request->no_sample)
            ->where('shift_pengambilan', 'L1')
            ->pluck('parameter')
            ->toArray();

        $voc_params_check = DetailSenyawaVolatile::where('no_sampel', $request->no_sample)
            ->where('shift_pengambilan', 'L1')
            ->pluck('parameter')
            ->toArray();

        $combined = array_merge($lh_params_check, $lk_params_check, $voc_params_check);

        // Cek apakah mengandung durasi panjang
        $has_durasi = false;
        foreach ($combined as $param) {
            foreach ($durasi as $d) {
                if (stripos($param, $d) !== false) {
                    $has_durasi = true;
                    break 2;
                }
            }
        }

        // Ubah shift jika tidak ditemukan durasi panjang
        $final_shift = ($request->shift == 'L1' && !$has_durasi) ? 'Sesaat' : $request->shift;

        // Pakai $final_shift untuk query sebenarnya
        $lh_parameter = DetailLingkunganHidup::where('no_sampel', $request->no_sample)
            ->where('shift_pengambilan', $final_shift)
            ->pluck('parameter')
            ->toArray();

        $lk_parameter = DetailLingkunganKerja::where('no_sampel', $request->no_sample)
            ->where('shift_pengambilan', $final_shift)
            ->pluck('parameter')
            ->toArray();

        $voc_parameter = DetailSenyawaVolatile::where('no_sampel', $request->no_sample)
            ->where('shift_pengambilan', $final_shift)
            ->pluck('parameter')
            ->toArray();

        // Gabungkan parameter dari lingkungan hidup dan kerja
        $existing_parameters = array_merge($lk_parameter, $lh_parameter, $voc_parameter);

        // Hapus parameter yang ada di $existing_parameters
        $filtered_param = array_values(array_diff($nilai_param2, $existing_parameters));

        // Buat output JSON yang sesuai
        $param_fin = json_encode($filtered_param);
        $parameterList = ParameterFdl::select("parameters")->where('is_active', 1)->where('nama_fdl','senyawa_volatile')->first();

        if ($data) {
            return response()->json([
                'non'      => 1,
                'keterangan'            => $data->keterangan,
                'keterangan_2'          => $data->keterangan_2,
                'titik_koordinat'       => $data->titik_koordinat,
                'lat'                   => $data->latitude,
                'longi'                 => $data->longitude,
                'lokasi'                => $data->lokasi,
                'cuaca'                 => $data->cuaca,
                'waktu'                 => $data->waktu_pengukuran,
                'kecepatan'             => $data->kecepatan_angin,
                'arah_angin'            => $data->arah_angin,
                'jarak'                 => $data->jarak_sumber_cemaran,
                'suhu'                  => $data->suhu,
                'kelem'                 => $data->kelembapan,
                'intensitas'            => $data->intensitas,
                'tekanan_u'             => $data->tekanan_udara,
                'desk_bau'              => $data->deskripsi_bau,
                'metode'                => $data->metode_pengukuran,
                'satuan'                => $data->satuan,
                'catatan'               => $data->catatan_kondisi_lapangan,
                'durasi_pengambilan'    => $data->durasi_pengambilan,
                'foto_lokasi_sample'    => $data->foto_lokasi_sampel,
                'foto_kondisi_sample'   => $data->foto_kondisi_sampel,
                'foto_lain'             => $data->foto_lain,
                'permis'                => $data->permission,
                'param'                 => json_decode($param_fin, true),
                'parameterList' => json_decode($parameterList->parameters,true),
                'is_filled' => true,
                // 'important_keyword' => $importantKeyword,
                'parameter_tsp' => json_decode($parameter_tsp->parameters, true),
                'parameter_no2' => $parameter_no2
            ], 200);

            $this->resultx = 'get shift Senyawa Volatile success';
        
        }else {
            return response()->json([
                'non' => 2,
                'no_sampel'    => $po->no_sampel,
                'keterangan' => $po->keterangan_1,
                'parameterList' => json_decode($parameterList->parameters,true),
                'id_ket' => explode('-', $po->kategori_3)[0],
                'param' => json_decode($param_fin, true),
                'is_filled' => false,
                // 'important_keyword' => $importantKeyword,
                'parameter_tsp' => json_decode($parameter_tsp->parameters, true),
                'parameter_no2' => $parameter_no2
            ], 200);
        }
    }

    public function store(Request $request){
        DB::beginTransaction();
        try {
            
            if ($request->jam_pengambilan == '') {
                return response()->json([
                    'message' => 'Jam pengambilan tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lokasi_sampel == '') {
                return response()->json([
                    'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_alat == '') {
                return response()->json([
                    'message' => 'Foto Kondisi Sampel tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lain == '') {
                return response()->json([
                    'message' => 'Foto lain-lain tidak boleh kosong .!'
                ], 401);
            }
            if ($request->param != null) {
                foreach ($request->param as $en => $ab) {
                    $cek = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $ab)->get();
                    
                    if ($request->shift !== "Sesaat") {
                        $nilai_array = array();
                        foreach ($cek as $key => $value) {
                            $durasi = $value->kategori_pengujian;
                            if ($value->shift_pengambilan == 'Sesaat') {
                                $shift = $request->shift;
                                if($shift == 'L1') {
                                    $shift = "Sesaat";
                                }
                                if ($shift == $value->shift_pengambilan) {
                                    return response()->json([
                                        'message' => 'Shift sesaat sudah terinput di no sample ini .!'
                                    ], 401);
                                }
                            } else {
                                if (strpos($durasi, '-') !== false) {
                                    $durasi_parts = explode("-", $durasi);
                                    if (count($durasi_parts) > 1) {
                                        $durasi = $durasi_parts[1];
                                        $nilai_array[$key] = str_replace('"', "", $durasi);
                                    }
                                }
                            }
                        }
                        if (in_array($request->shift, $nilai_array)) {
                            return response()->json([
                                'message' => 'Pengambilan ' . $ab . ' Shift ' . $request->shift . ' sudah ada !'
                            ], 401);
                        }
                    }
                }
            }

            foreach ($request->param as $in => $a) {
                $pengukuran = array();
                $durasii = null;
                if ($a == 'TSP (24 Jam)' || $a == 'Pb (24 Jam)' || $a == 'PM 10 (24 Jam)' || $a == 'PM 10 (8 Jam)' || $a == 'PM 2.5 (24 Jam)' || $a == 'PM 2.5 (8 Jam)') {
                    if ($request->shift == 'L24') {
                        $pengukuran = [
                            'Flow' => $request->awal[$in],
                        ];
                        if ($request->durasi[$in] != '' || $request->durasi2[$in] != '') {
                            $jam = ($request->durasi[$in] != '' && $request->durasi[$in] != 0 && $request->durasi[$in] != '-') ? $request->durasi[$in] . ' Jam, ' : '';
                            $menit = ($request->durasi2[$in] != '' && $request->durasi2[$in] != 0 && $request->durasi2[$in] != '-') ? $request->durasi2[$in] . ' Menit' : '';
                            $durasii = $jam . $menit;
                        }
                    } else {
                        $pengukuran = [
                            'Flow' => $request->awal[$in],
                        ];
                    }
                } else if ($a == 'O3 (8 Jam)' || $a == 'O3') {
                    $pengukuran = [
                        'Flow Awal' => $request->awal[$in],
                        'Flow Akhir' => $request->akhir[$in],
                        'Durasi' => $request->durasi[$in] . ' menit',
                        'Flow Awal 2' => $request->awal2[$in],
                        'Flow Akhir 2' => $request->akhir2[$in],
                        'Durasi 2' => $request->durasi2[$in] . ' menit',
                    ];
                } else if (
                    $a == "NH3" ||
                    $a == "H2S" ||
                    $a == "Al. Hidrokarbon" ||
                    $a == "Al. Hidrokarbon (8 Jam)" ||
                    $a == "Alcohol" ||
                    $a == "Acetone" ||
                    $a == "Alkana Gas" ||
                    $a == "Asam Asetat" ||
                    $a == "Butanon" ||
                    $a == "Benzene" ||
                    $a == "Benzene (8 Jam)" ||
                    $a == "Cyclohexanone" ||
                    $a == "EA" ||
                    $a == "Ethanol" ||
                    $a == "HCL" ||
                    $a == "HCL (8 Jam)" ||
                    $a == "HF" ||
                    $a == "IPA" ||
                    $a == "MEK" ||
                    $a == "Stirena" ||
                    $a == "Stirena (8 Jam)" ||
                    $a == "Toluene" ||
                    $a == "Toluene (8 Jam)" ||
                    $a == "Xylene" ||
                    $a == "Xylene (8 Jam)"
                ) {
                    $pengukuran = [
                        'Flow Awal' => $request->awal[$in],
                        'Flow Akhir' => $request->akhir[$in],
                        'Durasi' => $request->durasi[$in] . ' menit',
                    ];
                } else {
                    $pengukuran = [
                        'Flow Awal' => $request->awal[$in],
                        'Flow Akhir' => $request->akhir[$in],
                        'Durasi' => $request->durasi[$in] . ' menit',
                    ];
                }
                
                $shift2 = $request->shift;
                if ($request->kateg_uji[$in] == null) {
                    $shift_peng = 'Sesaat';
                    $shift2 = 'Sesaat';
                } else if ($request->kateg_uji[$in] == '24 Jam') {
                    $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift);
                } else if ($request->kateg_uji[$in] == '8 Jam') {
                    $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift);
                } else if ($request->kateg_uji[$in] == '6 Jam') {
                    $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift);
                }else if ($request->kateg_uji[$in] == '3 Jam') {
                    $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift);
                }

                $fdl = DataLapanganSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                
                if (is_null($fdl)) {
                    $data = new DataLapanganSenyawaVolatile();
                    if ($request->categori != '') $data->kategori_3                 = $request->categori;
                    $data->no_sampel                                                = strtoupper(trim($request->no_sample));
                    $data->permission                                                = $request->permission;
                    $data->created_by                                                   = $this->karyawan;
                    $data->created_at                                                  = date('Y-m-d H:i:s');
                    $data->save();
                }

                $fdlvalue = new DetailSenyawaVolatile();
                $fdlvalue->no_sampel                 = strtoupper(trim($request->no_sample));
                if ($request->keterangan_4 != '') $fdlvalue->keterangan            = $request->keterangan_4;
                if ($request->keterangan_2 != '') $fdlvalue->keterangan_2          = $request->keterangan_2;
                if ($request->koordinat != '') $fdlvalue->titik_koordinat             = $request->koordinat;
                if ($request->latitude != '') $fdlvalue->latitude                            = $request->latitude;
                if ($request->longitude != '') $fdlvalue->longitude                        = $request->longitude;
                if ($request->lok != '') $fdlvalue->lokasi                         = $request->lok;
                $fdlvalue->parameter                                               = $a;

                if ($request->cuaca != '') $fdlvalue->cuaca                        = $request->cuaca;
                if ($request->ventilasi != '') $fdlvalue->laju_ventilasi                = $request->ventilasi;
                if ($request->intensitas != '') $fdlvalue->intensitas              = $request->intensitas;
                // if ($request->aktifitas != '') $fdlvalue->aktifitas                = $request->aktifitas;
                if ($request->jarak != '') $fdlvalue->jarak_sumber_cemaran                        = $request->jarak;
                if ($request->jam_pengambilan != '') $fdlvalue->waktu_pengukuran                        = $request->jam_pengambilan;
                if ($request->kec != '') $fdlvalue->kecepatan_angin                        = $request->kec;
                $fdlvalue->satuan                        = $request->satuan[$in];
                $fdlvalue->kategori_pengujian                                                   = $shift_peng;
                $fdlvalue->shift_pengambilan                   = $shift2;
                if ($request->catatan != '') $fdlvalue->catatan_kondisi_lapangan                          = $request->catatan;
                if ($request->suhu != '') $fdlvalue->suhu                          = $request->suhu;
                if ($request->kelem != '') $fdlvalue->kelembapan                        = $request->kelem;
                if ($request->tekU != '') $fdlvalue->tekanan_udara                     = $request->tekU;
                if ($request->desk_bau != '') $fdlvalue->deskripsi_bau                  = $request->desk_bau;
                if ($request->metode != '') $fdlvalue->metode_pengukuran                      = $request->metode;
                if ($request->arah_angin != '') $fdlvalue->arah_angin                      = $request->arah_angin;
                $fdlvalue->durasi_pengujian       = $durasii;
                $fdlvalue->pengukuran                                              = json_encode($pengukuran);
                if ($request->permission != '') $fdlvalue->permission                      = $request->permission;
                if ($request->statFoto == 'adaFoto') {
                    if ($request->foto_lokasi_sampel != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                    if ($request->foto_alat != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_alat, 2, $this->user_id);
                    if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                } else {
                    if ($request->foto_lokasi_sampel != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                    if ($request->foto_alat != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_alat, 2, $this->user_id);
                    if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                }
                $fdlvalue->created_by                                                  = $this->karyawan;
                $fdlvalue->created_at                                                 = Carbon::now()->format('Y-m-d H:i:s');
                $fdlvalue->save();
            }

            DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $header = DB::table('lingkungan_header')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $nama = $this->karyawan;
            $this->resultx = "Data Sampling Senyawa Volatile Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

            InsertActivityFdl::by($this->user_id)->action('input')->target("Senyawa Volatile pada nomor sampel $request->no_sample")->save();

            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage().$e->getLine()], 401);
        }
    }

    public function index(Request $request)
    { 
        try {
            $perPage = $request->input('limit', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search');

            $query = DataLapanganSenyawaVolatile::with(['orderDetail', 'detailSenyawaVolatile'])
                ->where('created_by', $this->karyawan)
                ->where(function ($q) {
                    $q->where('is_rejected', 1)
                    ->orWhere(function ($q2) {
                        $q2->where('is_rejected', 0)
                            ->whereDate('created_at', '>=', Carbon::now()->subDays(7));
                    });
                });


            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('no_sampel', 'like', "%$search%")
                    ->orWhereHas('orderDetail', function ($q2) use ($search) {
                        $q2->where('nama_perusahaan', 'like', "%$search%");
                    });
                });
            }

            $data = $query->orderBy('id', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $modified = $data->getCollection()->map(function ($item) {
                $item->grouped_shift = $item->detailSenyawaVolatile
                    ->groupBy('shift_pengambilan')
                    ->map(function ($group) {
                        return $group->values(); 
                    });
                return $item;
            });

            $data->setCollection($modified);

            return response()->json($data);
        } catch (\Exception $th) {
            return response()->json([
                'message' => 'Gagal Get Data'
            ]);
        }
    }

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganSenyawaVolatile::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = DATE('Y-m-d H:i:s');
            $data->save();

            return response()->json([
                'message' => 'Data has ben Approved',
                'cat' => 1
            ], 200);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function detail(Request $request)
    {
        if ($request->tip == 1) {
            $data = DataLapanganSenyawaVolatile::with('orderDetail')
                ->where('no_sampel', $request->id)
                ->first();
            $this->resultx = 'get Detail sample lingkuhan kerja success';

            return response()->json([
                'no_sample'      => $data->no_sampel,
                'no_order'       => $data->orderDetail->no_order,
                'categori'       => explode('-', $data->orderDetail->kategori_3)[1],
                'sampler'        => $data->created_by,
                'corp'           => $data->orderDetail->nama_perusahaan,
            ], 200);
        } else if ($request->tip == 2) {
            $data = DetailSenyawaVolatile::with('orderDetail')
                ->where('no_sampel', $request->id)
                ->get();
            $this->resultx = 'get Detail sample Senyawa Volatile success';
            // dd($data);
            return response()->json([
                'data'             => $data,
            ], 200);
        } else if ($request->tip == 3) {
            $data = DetailSenyawaVolatile::with('orderDetail')
                ->where('id', $request->id)
                ->first();
            $this->resultx = 'get Detail sample Senyawa Volatile success';
            return response()->json([
                'data'             => $data,
            ], 200);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            if (!$request->id) {
                return response()->json(['message' => 'Gagal Delete, ID tidak valid'], 400);
            }

            $header = DataLapanganSenyawaVolatile::find($request->id);
            if (!$header) {
                return response()->json(['message' => 'Data tidak ditemukan'], 404);
            }

            $no_sampel = strtoupper(trim($header->no_sampel));
            DetailSenyawaVolatile::where('no_sampel', $no_sampel)->delete();

            $this->resultx = "Data Sampling FDL Senyawa Volatile Dengan No Sample $no_sampel berhasil disimpan oleh $this->karyawan";

            $header->delete();

            InsertActivityFdl::by($this->user_id)->action('delete')->target("Microbiologi Udara pada nomor sampel $no_sampel")->save();

            DB::commit();

            return response()->json([
                'message' => $this->resultx,
            ]);
        } catch (\Throwable $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Gagal Delete',
                // 'error' => $e->getMessage(), // Aktifkan jika debugging
            ], 500);
        }
    }

    public function deleteParameter(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sampel)))
                ->where('id', $request->id)
                ->first();

            if (!$data) {
                return response()->json(['message' => 'Data tidak ditemukan'], 404);
            }

            $parameter = $data->parameter;

            $data->delete();

            InsertActivityFdl::by($this->user_id)
                ->action('delete')
                ->target("parameter $parameter di nomor sampel {$request->no_sampel}")
                ->save();

            DB::commit();

            return response()->json([
                'message' => "Fdl Senyawa Volatile parameter $parameter di no sample {$request->no_sampel} berhasil dihapus oleh {$this->karyawan}.!",
            ]);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal Delete',
            ], 500);
        }
    }

    public function deleteShift(Request $request)
    {
        DB::beginTransaction();
        try {
            DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sampel)))
            ->where('shift_pengambilan', $request->shift)
            ->delete();
            
            InsertActivityFdl::by($this->user_id)->action('delete')->target(" shift $request->shift di nomor sampel $request->no_sampel")->save();

            DB::commit();

            return response()->json([
                'message' => "Fdl Senyawa Volatile shift $request->shift di no sample $request->no_sampel berhasil dihapus oleh {$this->karyawan}.!",
            ]);
        } catch (\Exception $th) {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 500);
        }   
    }

    // public function delete(Request $request)
    // {
    //     try {
    //         DB::beginTransaction();

    //         if (isset($request->id) || $request->id != null || isset($request->shift) || $request->shift != null) {

    //             $data = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->get();

    //             if ($request->tip == 1) {
    //                 $convert_par = ["TSP", "TSP (24 Jam)", "TSP (6 Jam)", "TSP (8 Jam)", "As", "Cd", "Cr", "Cu", "Fe", "Fe (8 Jam)", "Hg", "Sb", "Se", "Sn", "Zn", "Pb", "Pb (24 Jam)", "Pb (6 Jam)", "Pb (8 Jam)", "Mn", "Ni"];
    //                 $convert_24jam = ["TSP (24 Jam)", "Pb (24 Jam)"];
    //                 $convert_8jam = ["TSP (8 Jam)", "Fe (8 Jam)", "Pb (8 Jam)"];
    //                 $convert_6jam = ["TSP (6 Jam)", "Pb (6 Jam)"];
    //                 $convert_sesaat = ["TSP", "As", "Cd", "Cr", "Cu", "Fe", "Hg", "Sb", "Se", "Sn", "Zn", "Pb", "Mn", "Ni"];
    //                 $status_par = '';

    //                 if (in_array($request->parameter, $convert_par)) {

    //                     if (in_array($request->parameter, $convert_sesaat)) {
    //                         $cek = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))
    //                             ->where('shift_pengambilan', $request->shift)
    //                             ->whereIn('parameter', $convert_sesaat)
    //                             ->get();
    //                         $cek->each->delete();
    //                         $status_par = json_encode($convert_sesaat);

    //                     } else if (in_array($request->parameter, $convert_24jam)) {
    //                         $cek = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))
    //                             ->where('shift_pengambilan', $request->shift)
    //                             ->whereIn('parameter', $convert_24jam)
    //                             ->get();
    //                         $cek->each->delete();
    //                         $status_par = json_encode($convert_24jam);

    //                     } else if (in_array($request->parameter, $convert_8jam)) {
    //                         $cek = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))
    //                             ->where('shift_pengambilan', $request->shift)
    //                             ->whereIn('parameter', $convert_8jam)
    //                             ->get();
    //                         $cek->each->delete();
    //                         $status_par = json_encode($convert_8jam);

    //                     } else if (in_array($request->parameter, $convert_6jam)) {
    //                         $cek = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))
    //                             ->where('shift_pengambilan', $request->shift)
    //                             ->whereIn('parameter', $convert_6jam)
    //                             ->get();
    //                         $cek->each->delete();
    //                         $status_par = json_encode($convert_6jam);
    //                     }

    //                     $cek2 = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->get();

    //                     if ($cek2->count() > 0) {
    //                         $nama = $this->karyawan;
    //                         $this->resultx = "Fdl SenyawaVolatile parameter $status_par di no sample $request->no_sample berhasil dihapus oleh $nama.!";
    //                         DB::commit();

    //                         return response()->json([
    //                             'message' => $this->resultx,
    //                             'cat' => 1
    //                         ], 201);
    //                     } else {
    //                         $cek4 = DataLapanganSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
    //                         $cek4->delete();
    //                         $nama = $this->karyawan;
    //                         $this->resultx = "Fdl SenyawaVolatile parameter $status_par di no sample $request->no_sample berhasil dihapus $nama.!";
    //                         DB::commit();

    //                         return response()->json([
    //                             'message' => $this->resultx,
    //                             'cat' => 2
    //                         ], 201);
    //                     }

    //                 } else {
    //                     $cek = DetailSenyawaVolatile::where('id', $request->id)->first();
    //                     if ($data->count() > 1) {
    //                         $cek->delete();
    //                         $nama = $this->karyawan;
    //                         $this->resultx = "Fdl Senyawa Volatile parameter $cek->parameter di no sample $cek->no_sample berhasil dihapus oleh $nama.!";
    //                         DB::commit();

    //                         return response()->json([
    //                             'message' => $this->resultx,
    //                             'cat' => 1
    //                         ], 201);
    //                     } else {
    //                         $cek2 = DataLapanganSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
    //                         $cek->delete();
    //                         $cek2->delete();
    //                         $nama = $this->karyawan;
    //                         $this->resultx = "Fdl Senyawa Volatile parameter $cek->parameter di no sample $cek->no_sample berhasil dihapus oleh $nama.!";
    //                         DB::commit();

    //                         return response()->json([
    //                             'message' => $this->resultx,
    //                             'cat' => 2
    //                         ], 201);
    //                     }
    //                 }

    //             } else if ($request->tip == 2) {
    //                 $cek = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))
    //                     ->where('shift_pengambilan', $request->shift)->get();

    //                 $shift = [];
    //                 foreach ($data as $dat) {
    //                     $shift[$dat['shift_pengambilan']][] = $dat;
    //                 }

    //                 if (count($shift) > 1) {
    //                     $cek->each->delete();
    //                     $nama = $this->karyawan;
    //                     $this->resultx = "Fdl Senyawa Volatile shift $request->shift di no sample $request->no_sample berhasil dihapus oleh $nama.!";
    //                     DB::commit();

    //                     return response()->json([
    //                         'message' => $this->resultx,
    //                         'cat' => 1
    //                     ], 201);
    //                 } else {
    //                     $cek2 = DataLapanganSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
    //                     $cek->each->delete();
    //                     $cek2->delete();
                        
    //                     $nama = $this->karyawan;
    //                     $this->resultx = "Fdl Senyawa Volatile shift $request->shift di no sample $request->no_sample berhasil dihapus oleh $nama.!";
    //                     DB::commit();

    //                     return response()->json([
    //                         'message' => $this->resultx,
    //                         'cat' => 2
    //                     ], 201);
    //                 }

    //             } else if ($request->tip == 3) {
    //                 $cek = DataLapanganSenyawaVolatile::where('id', $request->id)->first();
    //                 DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->delete();
    //                 $cek->delete();

    //                 $nama = $this->karyawan;
    //                 $this->resultx = "Fdl Senyawa Volatile dengan no sampel $request->no_sample berhasil dihapus oleh $nama.!";

    //                 DB::commit();

    //                 return response()->json([
    //                     'message' => $this->resultx
    //                 ], 201);
    //             }

    //         } else {
    //             return response()->json([
    //                 'message' => 'Gagal Delete'
    //             ], 401);
    //         }

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    //         ], 500);
    //     }

    // }
    
    // public function delete(Request $request)
    // {
    //     if (isset($request->id) || $request->id != null || isset($request->shift) || $request->shift != null) {
    //         $data = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
    //         if ($request->tip == 1) {
    //             $convert_par = ["TSP", "TSP (24 Jam)", "TSP (6 Jam)", "TSP (8 Jam)", "As", "Cd", "Cr", "Cu", "Fe", "Fe (8 Jam)", "Hg", "Sb", "Se", "Sn", "Zn", "Pb", "Pb (24 Jam)", "Pb (6 Jam)", "Pb (8 Jam)", "Mn", "Ni"];
    //             $convert_24jam = ["TSP (24 Jam)", "Pb (24 Jam)"];
    //             $convert_8jam = ["TSP (8 Jam)", "Fe (8 Jam)", "Pb (8 Jam)"];
    //             $convert_6jam = ["TSP (6 Jam)", "Pb (6 Jam)"];
    //             $convert_sesaat = ["TSP", "As", "Cd", "Cr", "Cu", "Fe", "Hg", "Sb", "Se", "Sn", "Zn", "Pb", "Mn", "Ni"];
    //             $status_par = '';

    //             if (in_array($request->parameter, $convert_par)) {
    //                 if (in_array($request->parameter, $convert_sesaat)) {
    //                     $cek = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_sesaat)->get();
    //                     $cek->each->delete();
    //                     $status_par = json_encode($convert_sesaat);
    //                 } else if (in_array($request->parameter, $convert_24jam)) {
    //                     $cek = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_24jam)->get();
    //                     $cek->each->delete();
    //                     $status_par = json_encode($convert_24jam);
    //                 } else if (in_array($request->parameter, $convert_8jam)) {
    //                     $cek = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_8jam)->get();
    //                     $cek->each->delete();
    //                     $status_par = json_encode($convert_8jam);
    //                 } else if (in_array($request->parameter, $convert_6jam)) {
    //                     $cek = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_6jam)->get();
    //                     $cek->each->delete();
    //                     $status_par = json_encode($convert_6jam);
    //                 }

    //                 $cek2 = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
    //                 if ($cek2->count() > 0) {
    //                     $nama = $this->karyawan;
    //                     $this->resultx = "Fdl SenyawaVolatile parameter $status_par di no sample $request->no_sample berhasil dihapus oleh $nama.!";

    //                     // if($this->pin!=null){
    //                     //     $telegram = new Telegram();
    //                     //     $telegram->send($this->pin, $this->resultx);
    //                     // }

    //                     return response()->json([
    //                         'message' => $this->resultx,
    //                         'cat' => 1
    //                     ], 201);
    //                 } else {
    //                     $cek4 = DataLapanganSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
    //                     $cek4->delete();
    //                     $nama = $this->karyawan;
    //                     $this->resultx = "Fdl SenyawaVolatile parameter $status_par di no sample $request->no_sample berhasil dihapus $nama.!";

    //                     // if($this->pin!=null){
    //                     //     $telegram = new Telegram();
    //                     //     $telegram->send($this->pin, $this->resultx);
    //                     // }

    //                     return response()->json([
    //                         'message' => $this->resultx,
    //                         'cat' => 2
    //                     ], 201);
    //                 }
    //             } else {
    //                 $cek = DetailSenyawaVolatile::where('id', $request->id)->first();
    //                 if ($data->count() > 1) {
    //                     $cek->delete();
    //                     $nama = $this->karyawan;
    //                     $this->resultx = "Fdl Senyawa Volatile parameter $cek->parameter di no sample $cek->no_sample berhasil dihapus oleh $nama.!";
    //                     // if($this->pin!=null){
    //                     //     $telegram = new Telegram();
    //                     //     $telegram->send($this->pin, $this->resultx);
    //                     // }
    //                     return response()->json([
    //                         'message' => $this->resultx,
    //                         'cat' => 1
    //                     ], 201);
    //                 } else {
    //                     $cek2 = DataLapanganSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
    //                     $cek->delete();
    //                     $cek2->delete();

    //                     $nama = $this->karyawan;
    //                     $this->resultx = "Fdl Senyawa Volatile parameter $cek->parameter di no sample $cek->no_sample berhasil dihapus oleh $nama.!";

    //                     // if($this->pin!=null){
    //                     //     $telegram = new Telegram();
    //                     //     $telegram->send($this->pin, $this->resultx);
    //                     // }

    //                     return response()->json([
    //                         'message' => $this->resultx,
    //                         'cat' => 2
    //                     ], 201);
    //                 }
    //             }
    //         } else if ($request->tip == 2) {
    //             $cek = DetailSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift_pengambilan', $request->shift)->get();
    //             $shift = array();
    //             foreach ($data as $dat) {
    //                 $shift[$dat['shift_pengambilan']][] = $dat;
    //             }
    //             if (count($shift) > 1) {
    //                 $cek->each->delete();

    //                 $this->resultx = "Fdl Senyawa Volatile shift $request->shift di no sample $request->no_sample berhasil dihapus oleh $nama.!";
    //                 // $nama = $this->karyawan;
    //                 // if($this->pin!=null){
    //                 //     $telegram = new Telegram();
    //                 //     $telegram->send($this->pin, $this->resultx);
    //                 // }

    //                 return response()->json([
    //                     'message' => $this->resultx,
    //                     'cat' => 1
    //                 ], 201);
    //             } else {
    //                 $cek2 = DataLapanganSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
    //                 $cek->each->delete();
    //                 $cek2->delete();

    //                 $this->resultx = "Fdl Senyawa Volatile shift $request->shift di no sample $request->no_sample berhasil dihapus oleh $nama.!";
    //                 // $nama = $this->karyawan;
    //                 // if($this->pin!=null){
    //                 //     $telegram = new Telegram();
    //                 //     $telegram->send($this->pin, $this->resultx);
    //                 // }

    //                 return response()->json([
    //                     'message' => $this->resultx,
    //                     'cat' => 2
    //                 ], 201);
    //             }
    //         } else if ($request->tip == 3) {
    //             $cek = DataLapanganSenyawaVolatile::where('id', $request->id)->first();
    //             $cek2 = DataLapanganSenyawaVolatile::where('no_sampel', strtoupper(trim($request->no_sample)))->delete();
    //             $cek->delete();
    //             $nama = $this->karyawan;
    //             $this->resultx = "Fdl Senyawa Volatile dengan no sampel $request->no_sample berhasil dihapus oleh $nama.!";
    //             // if ($this->pin != null) {

    //             //     $telegram = new Telegram();
    //             //     $telegram->send($this->pin, $this->resultx);
    //             // }

    //             return response()->json([
    //                 'message' => $this->resultx,
    //             ], 201);
    //         }
    //     } else {
    //         return response()->json([
    //             'message' => 'Gagal Delete'
    //         ], 401);
    //     }
    // }
    
    public function convertImg($foto = '', $type = '', $user = '')
    {
        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        // if (!file_exists(public_path() . '/dokumentasi/'.DATE('Ymd'))) {
        //     mkdir(public_path() . '/dokumentasi/'.DATE('Ymd') , 0777, true);
        // }
        $safeName = DATE('YmdHis') . '_' . $user . $type . '.jpeg';
        $destinationPath = public_path() . '/dokumentasi/sampling/';
        $success = file_put_contents($destinationPath . $safeName, $file);
        return $safeName;
    }
}