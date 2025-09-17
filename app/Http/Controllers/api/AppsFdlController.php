<?php

namespace App\Http\Controllers\api;

// DATA LAPANGAN
use App\Models\DataLapanganAir;
use App\Models\DataLapanganCahaya;
use App\Models\DataLapanganDebuPersonal;
use App\Models\DataLapanganDirectLain;
use App\Models\DataLapanganEmisiCerobong;
use App\Models\DataLapanganEmisKendaraan;
use App\Models\DataLapanganErgonomi;
use App\Models\DataLapanganGetaran;
use App\Models\DataLapanganGetaranPersonal;
use App\Models\DataLapanganIklimDingin;
use App\Models\DataLapanganIklimPanas;
use App\Models\DataLapanganIsokinetikBeratMolekul;
use App\Models\DataLapanganIsokinetikPenentuanKecepatanLinier;
use App\Models\DataLapanganIsokinetikPenentuanPartikulat;
use App\Models\DataLapanganIsokinetikHasil;
use App\Models\DataLapanganIsokinetikKadarAir;
use App\Models\DataLapanganIsokinetikSurveiLapangan;
use App\Models\DataLapanganKebisingan;
use App\Models\DataLapanganKebisinganPersonal;
use App\Models\DataLapanganLingkunganKerja;
use App\Models\DataLapanganLingkunganHidup;
use App\Models\DataLapanganMedanLM;
use App\Models\DataLapanganMicrobiologi;
use App\Models\DataLapanganPartikulatMeter;
use App\Models\DataLapanganPsikologi;
use App\Models\DataLapanganSinarUV;
use App\Models\DataLapanganSwab;
use App\Models\AduanLapangan;
use App\Models\DataLapanganKebisinganBySoundMeter;
use App\Models\DeviceIntilab;

// DETAIL LAPANGAN
use App\Models\DetailLingkunganHidup;
use App\Models\DetailLingkunganKerja;
use App\Models\DetailMicrobiologi;
use App\Models\DetailSoundMeter;

// MASTER DATA
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

// SERVICE
use App\Services\SendTelegram;
use App\Services\GetAtasan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class AppsFdlController extends Controller
{
    public function getSample(Request $request)
    {
        // dd($request->all());
        if (isset($request->no_sample) && $request->no_sample != null) {
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->where('is_active', 1)->first();
            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample tidak ditemukan..'
                ], 401);
            } else {
                $direct = DataLapanganDirectLain::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $medan = DataLapanganMedanLM::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $partikulat = DataLapanganPartikulatMeter::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $sinuv = DataLapanganSinarUV::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $lingh = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $lingk = DetailLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $vallingh = DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $vallingk = DataLapanganLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $swab = DataLapanganSwab::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $microBio = DetailMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $emisiC = DataLapanganEmisiCerobong::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $debu = DataLapanganDebuPersonal::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift', 'like', '%L1%')->first();
                $psikologi = DataLapanganPsikologi::where('no_sampel', strtoupper(trim($request->no_sample)))->first();


                if ($direct !== NULL) {
                    \DB::statement("SET SQL_MODE=''");
                    $par = DataLapanganDirectLain::where('no_sampel', strtoupper(trim($request->no_sample)))->groupBy('parameter')->get();
                    $par2 = DataLapanganDirectLain::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift', '!=', 'Sesaat')->groupBy('parameter')->get();
                    $p = json_decode($data->parameter);
                    $nilai_param = array();
                    $nilai_param2 = array();
                    $nilai_param3 = array();
                    foreach ($par as $key => $value) {
                        $nilai_param[] =  $value->parameter;
                    }
                    $param1 = array_diff($p, $nilai_param);
                    foreach ($param1 as $ke => $val) {
                        $nilai_param2[] =  $val;
                    }
                    foreach ($par2 as $k => $v) {
                        $nilai_param3[] =  $v->parameter;
                    }
                    $pp1 = str_replace("[", "", json_encode($nilai_param2));
                    $pp2 = str_replace("]", "", $pp1);
                    $pp3 = str_replace("[", "", json_encode($nilai_param3));
                    $pp4 = str_replace("]", "", $pp3);

                    if ($pp2 == '') {
                        $param_fin = json_encode($nilai_param3);
                    } else if ($pp4 == "") {
                        $param_fin = '[' . $pp2 . ']';
                    } else if ($pp2 !== "") {
                        $param_fin = '[' . $pp4 . ',' . $pp2 . ']';
                    }
                    $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                    return response()->json([
                        'no_sample'    => $data->no_sampel,
                        'jenis'        => $cek->nama_sub_kategori,
                        'keterangan' => $data->keterangan_1,
                        'id_ket' => explode('-', $data->kategori_3)[0],
                        'param' => $param_fin
                    ], 200);
                } else if ($partikulat !== NULL) {
                    
                    \DB::statement("SET SQL_MODE=''");
                    $par = DataLapanganPartikulatMeter::where('no_sampel', strtoupper(trim($request->no_sample)))->groupBy('parameter')->get();
                    $par2 = DataLapanganPartikulatMeter::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift_pengambilan', '!=', 'Sesaat')->groupBy('parameter')->get();
                    $p = json_decode($data->parameter);
                    $nilai_param = array();
                    $nilai_param2 = array();
                    $nilai_param3 = array();
                    foreach ($par as $key => $value) {
                        $nilai_param[] =  $value->parameter;
                    }
                    $param1 = array_diff($p, $nilai_param);
                    foreach ($param1 as $ke => $val) {
                        $nilai_param2[] =  $val;
                    }
                    foreach ($par2 as $k => $v) {
                        $nilai_param3[] =  $v->parameter;
                    }
                    $pp1 = str_replace("[", "", json_encode($nilai_param2));
                    $pp2 = str_replace("]", "", $pp1);
                    $pp3 = str_replace("[", "", json_encode($nilai_param3));
                    $pp4 = str_replace("]", "", $pp3);
                    if ($pp2 == '') {
                        $param_fin = json_encode($nilai_param3);
                    } else if ($pp4 == "") {
                        $param_fin = '[' . $pp2 . ']';
                    } else if ($pp2 !== "") {
                        $param_fin = '[' . $pp4 . ',' . $pp2 . ']';
                    }
                    $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                    return response()->json([
                        'no_sample'    => $data->no_sampel,
                        'jenis'        => $cek->nama_sub_kategori,
                        'keterangan' => $data->keterangan_1,
                        'id_ket' => explode('-', $data->kategori_3)[0],
                        'param' => $param_fin
                    ], 200);
                } else if ($medan !== NULL) {

                    \DB::statement("SET SQL_MODE=''");
                    $par = DataLapanganMedanLM::where('no_sampel', strtoupper(trim($request->no_sample)))->groupBy('parameter')->get();
                    $p = json_decode($data->parameter);
                    $nilai_param = array();
                    $nilai_param2 = array();
                    foreach ($par as $key => $value) {
                        $nilai_param[] =  $value->parameter;
                    }
                    $param1 = array_diff($p, $nilai_param);
                    foreach ($param1 as $ke => $val) {
                        $nilai_param2[] =  $val;
                    }
                    // dd($param1);
                    $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                    return response()->json([
                        'no_sample'    => $data->no_sampel,
                        'jenis'        => $cek->nama_sub_kategori,
                        'keterangan' => $data->keterangan_1,
                        'id_ket' => explode('-', $data->kategori_3)[0],
                        'param' => json_encode($nilai_param2)
                    ], 200);
                } else if ($lingh !== NULL) {
                    // if ($vallingh && $this->karyawan != $vallingh->created_by) {
                    //     $user = MasterKaryawan::where('nama_lengkap', $vallingh->created_by)->first();
                    //     $samplerName = $user ? $user->nama_lengkap : "Unknown";

                    //     return response()->json([
                    //         'message' => "No Sample $request->no_sample harus di input oleh sampler $samplerName"
                    //     ], 401);
                    // }
                    // else {
                        \DB::statement("SET SQL_MODE=''");
                        $param = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))->groupBy('parameter')->get();
                        $parNonSes = array();
                        foreach ($param as $value) {
                            if ($value->shift_pengambilan != 'Sesaat') {
                                $p = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $value->parameter)->get();
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
                                        $shift = 25;
                                    } else if ($li[0] == '8 Jam') {
                                        $shift = 8;
                                    } else if ($li[0] == '6 Jam') {
                                        $shift = 6;
                                    }
                                } else {
                                    if ($li[0] == '24 Jam') {
                                        $shift = 4;
                                    } else if ($li[0] == '8 Jam') {
                                        $shift = 3;
                                    } else if ($li[0] == '6 Jam') {
                                        $shift = 6;
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
                            'no_sample'    => $data->no_sampel,
                            'jenis'        => $cek->nama_sub_kategori,
                            'keterangan' => $data->keterangan_1,
                            'id_ket' => explode('-', $data->kategori_3)[0],
                            'param' => $param_fin
                        ], 200);
                    // }
                } else if ($lingk !== NULL) {
                    // if ($this->karyawan != $vallingk->created_by) {
                    //     $user = MasterKaryawan::where('nama_lengkap', $vallingk->created_by)->first();
                    //     return response()->json([
                    //         'message' => "No Sample $request->no_sample harus di input oleh sampler $user->nama_lengkap"
                    //     ], 401);
                    // } else {
                        \DB::statement("SET SQL_MODE=''");
                        $param = DetailLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->groupBy('parameter')->get();
                        $parNonSes = array();
                        foreach ($param as $value) {
                            if ($value->shift_pengambilan != 'Sesaat') {
                                $p = DetailLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $value->parameter)->get();
                                $l = $value->shift_pengambilan;
                                $li = explode("-", $l);
                                $shift = '';
                                if ($li[0] == '24 Jam') {
                                    $shift = 25;
                                } else if ($li[0] == '8 Jam') {
                                    $shift = 8;
                                } else if ($li[0] == '6 Jam') {
                                    $shift = 6;
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
                            'no_sample'    => $data->no_sampel,
                            'jenis'        => $cek->nama_sub_kategori,
                            'keterangan' => $data->keterangan_1,
                            'id_ket' => explode('-', $data->kategori_3)[0],
                            'param' => $param_fin
                        ], 200);
                    // }
                } else if ($emisiC !== NULL) {
                    $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                    return response()->json([
                        'no_sample'     => $data->no_sampel,
                        'jenis'         => $cek->nama_sub_kategori,
                        'keterangan'    => $data->keterangan_1,
                        'id_ket'        => explode('-', $data->kategori_3)[0],
                        'id_ket2'       => explode('-', $data->kategori_2)[0],
                        'data'          => $emisiC,
                        'param'     => $data->parameter
                    ], 200);
                } else if ($microBio !== NULL) {
                    \DB::statement("SET SQL_MODE=''");
                    $param = DetailMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->groupBy('parameter')->get();
                    $parNonSes = array();
                    foreach ($param as $value) {
                        if ($value->shift_pengambilan != 'Sesaat') {
                            $p = DetailMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $value->parameter)->get();
                            if (3 > count($p)) {
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
                        'no_sample'    => $data->no_sampel,
                        'jenis'        => $cek->nama_sub_kategori,
                        'keterangan' => $data->keterangan_1,
                        'id_ket' => explode('-', $data->kategori_3)[0],
                        'param' => $param_fin
                    ], 200);
                } else if ($debu !== NULL) {
                    $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                    return response()->json([
                        'no_sample'    => $data->no_sampel,
                        'jenis'        => $cek->nama_sub_kategori,
                        'keterangan' => $debu->keterangan,
                        'id_ket' => explode('-', $data->kategori_3)[0],
                        'id_ket2' => explode('-', $data->kategori_2)[0],
                        'param' => $data->parameter
                    ], 200);
                } else if ($psikologi !== NULL) {
                    $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                    return response()->json([
                        'no_sample'    => $data->no_sampel,
                        'jenis'        => $cek->nama_sub_kategori,
                        'keterangan' => $data->keterangan,
                        'id_ket' => explode('-', $data->kategori_3)[0],
                        'id_ket2' => explode('-', $data->kategori_2)[0],
                        'param' => $data->parameter
                    ], 200);
                } else {
                    $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                    return response()->json([
                        'no_sample'    => $data->no_sampel,
                        'jenis'        => $cek->nama_sub_kategori,
                        'keterangan' => $data->keterangan_1,
                        'id_ket' => explode('-', $data->kategori_3)[0],
                        'id_ket2' => explode('-', $data->kategori_2)[0],
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

    // AIR
    public function addAir(Request $request)
    {
        DB::beginTransaction();
        try {
            if (isset($request->posisi) && $request->posisi != null) {
                if (!$request->no_sample || $request->no_sample == null) {
                    return response()->json([
                        'message' => 'NO Sample tidak boleh kosong!.'
                    ], 401);
                } else {
                    //==========Check no sample==========
                    $check = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->where('is_active', true)->first();
                    if (is_null($check)) {
                        return response()->json([
                            'message' => 'No Sample tidak ditemukan!.'
                        ], 401);
                    } else {
                        //==============final input=================
                        $cek = DataLapanganAir::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                        $u    = MasterKaryawan::where('nama_lengkap', $this->user_id)->first();

                        if ($request->jam == '') {
                            return response()->json([
                                'message' => 'Jam pengambilan tidak boleh kosong .!'
                            ], 401);
                        }
                        if ($request->foto_lok == '') {
                            return response()->json([
                                'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                            ], 401);
                        }
                        if ($request->foto_sampl == '') {
                            return response()->json([
                                'message' => 'Foto kondisi sample tidak boleh kosong .!'
                            ], 401);
                        }

                        if ($cek) {
                            return response()->json([
                                'message' => 'No Sample sudah diinput!.'
                            ], 401);
                        } else {
                            if (in_array('kimia', $request->parent_pengawet) == true) { // array in_array ('variable', array) == true
                                $pengawet = str_replace("[", "", json_encode($request->parent_pengawet));
                                $pengawet = str_replace("]", "", $pengawet);
                                $pengawet = str_replace('"', "", $pengawet);
                                $pengawet = str_replace(",", ", ", $pengawet);
                                $pengawet = $pengawet . '-' . json_encode($request->jenis_pengawet);
                            } else {
                                $pengawet = str_replace("[", "", json_encode($request->parent_pengawet));
                                $pengawet = str_replace("]", "", $pengawet);
                                $pengawet = str_replace('"', "", $pengawet);
                            }

                            if ($request->jam_pengamatan != null) {
                                $a = count($request->jam_pengamatan);
                                $pasang_surut = array();
                                for ($i = 0; $i < $a; $i++) {
                                    $pasang_surut[] = [
                                        'jam' => $request->jam_pengamatan[$i],
                                        'hasil_pengamatan' => $request->hasil_pengamatan[$i]
                                    ];
                                }
                            }
                            if ($request->jenis_sample2 != '') {
                                $cek = MasterSubKategori::where('id', $request->jenis_sample2)->first();
                                $jenis_sample = $cek->name;
                            } else {
                                $jenis_sample = $request->jenis_sample;
                            }

                            $data = new DataLapanganAir();

                            $data->no_sampel = strtoupper(trim($request->no_sample));
                            $data->jenis_sampel = $request->jenis_sample;
                            $data->kedalaman_titik = $request->kedalaman ?? '';
                            $data->jenis_produksi = $request->jenis_produksi ?? '';
                            $data->lokasi_titik_pengambilan = $request->titik_lokasi ?? '';
                            $data->jenis_fungsi_air = is_array($request->jenis_fungsi) ? json_encode($request->jenis_fungsi) : $request->jenis_fungsi ?? '';
                            $data->jumlah_titik_pengambilan = $request->jtpeng ?? $request->jumlah_titik ?? '';
                            $data->status_kesediaan_ipal = $request->ipal ?? '';
                            $data->lokasi_sampling = $request->lokasi_sampling ?? '';
                            $data->keterangan = $request->keterangan_1 ?? '';
                            $data->informasi_tambahan = $request->information ?? '';
                            $data->titik_koordinat = $request->posisi ?? '-';
                            $data->latitude = $request->lat ?? '';
                            $data->longitude = $request->longi ?? '';
                            $data->diameter_sumur = $request->diameter_sumur ?? '';
                            $data->kedalaman_sumur1 = $request->kedalaman_sumur_pertama ?? '';
                            $data->kedalaman_sumur2 = $request->kedalaman_sumur_kedua ?? '';
                            $data->kedalaman_air_terambil = $request->kedalaman_sumur_terambil ?? '';
                            $data->total_waktu = $request->total_waktu ?? '';
                            $data->teknik_sampling = $request->teknik_sampling ?? '';
                            $data->jam_pengambilan = $request->jam ?? '';
                            $data->volume = $request->volume ?? '';
                            $data->jenis_pengawet = $pengawet ?? '';
                            $data->perlakuan_penyaringan = $request->penyaringan ?? '';
                            $data->pengendalian_mutu = $request->mutu !== 'null' ? json_encode($request->mutu) : '';
                            // $data->pengendalian_mutu = json_encode($request->mutu) ?? '';
                            $data->teknik_pengukuran_debit = $request->pengukuran_debit ?? '';
                            $data->debit_air = $request->debit_air ?? null;

                            // 13/06/2025 - Goni
                            if ($request->sel_debit == 'Input Data' && $request->debit_air != '' && $request->satuan_debit != '') {
                                $data->debit_air = $request->debit_air . ' ' . $request->satuan_debit;
                            } else if ($request->sel_debit == 'Data By Customer') {
                                if ($request->sel_data_by_cust == 'Email') {
                                    $data->debit_air = 'Data By Customer( Email )';
                                } else if ($request->sel_data_by_cust == 'Input Data' && $request->debit_air_by_cust != '') {
                                    if ($request->satuan_debit_by_cust != '' && $request->debit_air_by_cust != '') {
                                        $data->debit_air = 'Data By Customer(' . $request->debit_air_by_cust . ' ' . $request->satuan_debit_by_cust . ')';
                                    } else if ($request->debit_air_by_cust != '') {
                                        $data->debit_air = 'Data By Customer(' . $request->debit_air_by_cust . ')';
                                    }
                                }
                            }

                            $data->do = $request->do ?? '';
                            $data->ph = $request->ph ?? '';
                            $data->suhu_air = $request->suhu_air ?? '';
                            $data->suhu_udara = $request->suhu_udara ?? '';
                            $data->dhl = $request->dhl ?? '';
                            $data->warna = $request->warna ?? '';
                            $data->bau = $request->bau ?? '';
                            $data->salinitas = $request->salinitas ?? '';
                            $data->kecepatan_arus = $request->kecepatan_arus ?? '';
                            $data->arah_arus = $request->arah_arus ?? '';
                            $data->pasang_surut = $request->jam_pengamatan != null ? json_encode($pasang_surut) : '';
                            $data->kecerahan = $request->kecerahan ?? '';
                            $data->lapisan_minyak = $request->minyak ?? '';
                            $data->cuaca = $request->cuaca ?? '';
                            $data->sampah = $request->sampah ?? '';
                            $data->lokasi_submit = $request->lok_submit ?? '';
                            $data->klor_bebas = $request->klor ?? '';
                            // $data->is_rejected = false;

                            if ($request->foto_lok != '') {
                                $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
                            }

                            if ($request->foto_sampl != '') {
                                $data->foto_kondisi_sampel = self::convertImg($request->foto_sampl, 2, $this->user_id);
                            }

                            if ($request->foto_lain != '') {
                                $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
                            }

                            $data->permission = $request->permis ?? '';
                            $data->created_by = $this->karyawan;
                            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');

                            $data->save();

                            // $update = DB::table('order_detail')
                            //     ->where('no_sampel', strtoupper(trim($request->no_sample)))
                            //     ->update(['tanggal_terima'=> Carbon::now()->format('Y-m-d H:i:s')]);

                            $nama = $this->karyawan;
                            $this->resultx = "Data Sampling AIR dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

                            DB::commit(); 
                            return response()->json([
                                'message' => $this->resultx
                            ], 200);
                        }
                    }
                }
            } else {
                return response()->json([
                    'message' => 'Lokasi tidak ditemukan'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollBack(); 
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function indexAir(Request $request)
    {
        $data = DataLapanganAir::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function detailAir(Request $request)
    {
        try {
            $data = DataLapanganAir::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail sample lapangan success';

            $po = OrderDetail::where('no_sampel', $data->no_sampel)->first();
            
            if($po){
                // dd($data);
                if ($data->debit_air == null) {
                    $debit = 'Data By Customer';
                } else {
                    $debit = $data->debit_air;
                }
    
                return response()->json([
                    'id'                        => $data->id,
                    'no_sample'                 => $data->no_sampel,
                    'no_order'                  => $data->detail->no_order,
                    'sampler'                   => $data->created_by,
                    'jam'                       => $data->jam_pengambilan,
                    'corp'                      => $data->detail->nama_perusahaan,
                    'jenis'                     => explode('-', $data->detail->kategori_3)[1],
                    'keterangan'                => $data->keterangan,
                    'jenis_produksi'            => $data->jenis_produksi,
                    'pengawet'                  => $data->jenis_pengawet,
                    'teknik'                    => $data->teknik_sampling,
                    'warna'                     => $data->warna,
                    'bau'                       => $data->bau,
                    'volume'                    => $data->volume,
                    'suhu_air'                  => $data->suhu_air,
                    'suhu_udara'                => $data->suhu_udara,
                    'ph'                        => $data->ph,
                    'tds'                       => $data->tds,
                    'dhl'                       => $data->dhl,
                    'do'                        => $data->do,
                    'debit'                     => $debit,
                    'lat'                       => $data->latitude,
                    'long'                      => $data->longitude,
                    'coor'                      => $data->titik_koordinat,
                    'massage'                   => $this->resultx,
                    'jumlah_titik_pengambilan'  => $data->jumlah_titik_pengambilan,
                    'jenis_fungsi_air'          => $data->jenis_fungsi_air,
                    'perlakuan_penyaringan'     => $data->perlakuan_penyaringan,
                    'pengendalian_mutu'         => $data->pengendalian_mutu,
                    'teknik_pengukuran_debit'   => $data->teknik_pengukuran_debit,
                    'klor_bebas'                => $data->klor_bebas,
                    'kat_id'                    => explode('-', $data->detail->kategori_3)[0],
                    'jenis_sample'              => $data->jenis_sample,
                    'ipal'                      => $data->status_kesediaan_ipal,
                    'lok_sampling'              => $data->lokasi_sampling,
                    'diameter'                  => $data->diameter_sumur,
                    'kedalaman1'                => $data->kedalaman_sumur1,
                    'kedalaman2'                => $data->kedalaman_sumur2,
                    'kedalamanair'              => $data->kedalaman_air_terambil,
                    'total_waktu'               => $data->total_waktu,
                    'kedalaman_titik'           => $data->kedalaman_titik,
                    'lokasi_pengambilan'        => $data->lokasi_titik_pengambilan,
                    'salinitas'                 => $data->salinitas,
                    'kecepatan_arus'            => $data->kecepatan_arus,
                    'arah_arus'                 => $data->arah_arus,
                    'pasang_surut'              => $data->pasang_surut,
                    'kecerahan'                 => $data->kecerahan,
                    'lapisan_minyak'            => $data->lapisan_minyak,
                    'cuaca'                     => $data->cuaca,
                    'info_tambahan'             => $data->informasi_tambahan,
                    'keterangan'                => $data->keterangan,
                    'foto_lok'                  => $data->foto_lokasi_sampel,
                    'foto_kondisi'              => $data->foto_kondisi_sampel,
                    'foto_lain'                 => $data->foto_lain,
                    'sampah'                    => $data->sampah,
                    'status'                    => '200'
                ], 200);
            }

        } catch (\exeption $err) {
            dd($err);
        }
    }

    public function approveAir(Request $request)
    {
        if (isset($request->id) && $request->id != null) {

            $data = DataLapanganAir::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin != null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function deleteAir(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganAir::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
                'cat' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // Direct Lain

    public function addDirectLain(Request $request)
    {
        DB::beginTransaction();
        try {
            $check = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->where('is_active', true)->first();
            if ($request->waktu == '') {
                return response()->json([
                    'message' => 'Jam pengambilan masing kosong .!'
                ], 401);
            }
            
            if ($request->param != null) {
                foreach ($request->param as $en => $ab) {
                    if ($request->foto_lain1[$en] == '') {
                        return response()->json([
                            'message' => 'Foto lain parameter ' . $ab . ' masing kosong .!'
                        ], 401);
                    }
                    if ($request->shift1[$en] !== "Sesaat") {
                        $nilai_array = array();
                        $cek = DataLapanganDirectLain::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $ab)->get();
                        foreach ($cek as $key => $value) {
                            if ($value->shift == 'Sesaat') {
                                if ($request->shift1 == $value->shift) {
                                    return response()->json([
                                        'message' => 'Shift sesaat sudah terinput di no sample ini .!'
                                    ], 401);
                                }
                            } else {
                                $durasi = $value->shift;
                                $durasi = explode("-", $durasi);
                                $durasi = $durasi[1];
                                $nilai_array[$key] = str_replace('"', "", $durasi);
                            }
                        }
                        if (in_array($request->shift1[$en], $nilai_array)) {
                            return response()->json([
                                'message' => 'Pengambilan' . $ab . ' Shift ' . $request->shift1[$en] . ' sudah ada !'
                            ], 401);
                        }
                    }
                }
            }
            
            if ($request->param2 != null) {
                foreach ($request->param2 as $en => $ab) {
                    if ($request->foto_lain2[$en] == '') {
                        return response()->json([
                            'message' => 'Foto lain parameter ' . $ab . ' masing kosong .!'
                        ], 401);
                    }
                    if ($request->shift2[$en] !== "Sesaat") {
                        $nilai_array = array();
                        $cek = DataLapanganDirectLain::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $ab)->get();
                        foreach ($cek as $key => $value) {
                            if ($value->shift == 'Sesaat') {
                                if ($request->shift2 == $value->shift) {
                                    return response()->json([
                                        'message' => 'Shift sesaat sudah terinput di no sample ini .!'
                                    ], 401);
                                }
                            } else {
                                $durasi = $value->shift;
                                $durasi = explode("-", $durasi);
                                $durasi = $durasi[1];
                                $nilai_array[$key] = str_replace('"', "", $durasi);
                            }
                        }
                        if (in_array($request->shift2[$en], $nilai_array)) {
                            return response()->json([
                                'message' => 'Pengambilan' . $ab . ' Shift ' . $request->shift2[$en] . ' sudah ada !'
                            ], 401);
                        }
                    }
                }
            }
            if ($request->param3 != null) {
                foreach ($request->param3 as $en => $ab) {
                    if ($request->foto_lain3[$en] == '') {
                        return response()->json([
                            'message' => 'Foto lain parameter ' . $ab . ' masing kosong .!'
                        ], 401);
                    }
                    if ($request->shift3[$en] !== "Sesaat") {
                        $nilai_array = array();
                        $cek = DataLapanganDirectLain::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $ab)->get();
                        foreach ($cek as $key => $value) {
                            if ($value->shift == 'Sesaat') {
                                if ($request->shift3 == $value->shift) {
                                    return response()->json([
                                        'message' => 'Shift sesaat sudah terinput di no sample ini .!'
                                    ], 401);
                                }
                            } else {
                                $durasi = $value->shift;
                                $durasi = explode("-", $durasi);
                                $durasi = $durasi[1];
                                $nilai_array[$key] = str_replace('"', "", $durasi);
                            }
                        }
                        if (in_array($request->shift3[$en], $nilai_array)) {
                            return response()->json([
                                'message' => 'Pengambilan' . $ab . ' Shift ' . $request->shift3[$en] . ' sudah ada !'
                            ], 401);
                        }
                    }
                }
            }

            if ($request->param != null) {
                $pe = 0;
                $pf = 10;
                foreach ($request->param as $in => $a) {
                    $pe++;
                    $pf++;
                    $pengukuran = array();
                    $pengukuran = [
                        'data-1' => $request->data1[$in],
                        'data-2' => $request->data2[$in],
                        'data-3' => $request->data3[$in],
                        'data-4' => $request->data4[$in],
                        'data-5' => $request->data5[$in],
                    ];

                    $img2 = str_replace('data:image/jpeg;base64,', '', $request->foto_lain1[$in]);
                    $file2 = base64_decode($img2);
                    $safeName2 = DATE('YmdHis') . '_' . $this->user_id . $pf . '.jpeg';
                    $destinationPath2 = public_path() . '/dokumentasi/sampling/';
                    $success2 = file_put_contents($destinationPath2 . $safeName2, $file2);

                    if ($request->kateg_uji[$in] == '24 Jam') {
                        $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift1[$in]);
                    } else if ($request->kateg_uji[$in] == '8 Jam') {
                        $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift1[$in]);
                    } else if ($request->kateg_uji[$in] == '6 Jam') {
                        $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift1[$in]);
                    } else {
                        $shift_peng = 'Sesaat';
                    }
                    $data = new DataLapanganDirectLain();
                    $data->no_sampel                 = strtoupper(trim($request->no_sample));
                    if ($request->keterangan_4 != '') $data->keterangan            = $request->keterangan_4;
                    if ($request->keterangan_2 != '') $data->keterangan_2          = $request->keterangan_2;
                    // if ($request->posisi != '') $data->titik_koordinat             = $request->posisi;
                    $data->titik_koordinat = $request->posisi ?? '-';
                    if ($request->lat != '') $data->latitude                            = $request->lat;
                    if ($request->longi != '') $data->longitude                        = $request->longi;
                    if ($request->categori != '') $data->kategori_3                = $request->categori;
                    if ($request->lok != '') $data->lokasi                         = $request->lok;
                    $data->parameter                         = $a;

                    if ($request->kon_lapangan != '') $data->kondisi_lapangan              = $request->kon_lapangan;
                    if ($request->jenis_peng != '') $data->jenis_pengukuran              = $request->jenis_peng;
                    if ($request->waktu != '') $data->waktu                        = $request->waktu;
                    $data->shift                   = $shift_peng;
                    if ($request->suhu != '') $data->suhu                          = $request->suhu;
                    if ($request->kelem != '') $data->kelembaban                        = $request->kelem;
                    if ($request->tekU != '') $data->tekanan_udara                     = $request->tekU;
                    $data->pengukuran     = json_encode($pengukuran);

                    if ($request->permis != '') $data->permission                      = $request->permis;
                    // $data->is_rejected = false;
                    $data->foto_lain        = $safeName2;
                    $data->created_by                     = $this->karyawan;
                    $data->created_at                     = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                }
            }
            if ($request->param2 != null) {
                $pe = 20;
                $pf = 30;
                foreach ($request->param2 as $in => $a) {
                    $pe++;
                    $pf++;
                    $pengukuran = array();
                    $pengukuran = [
                        'data-1' => $request->data6[$in],
                        'data-2' => $request->data7[$in],
                        'data-3' => $request->data8[$in],
                        'data-4' => $request->data9[$in],
                        'data-5' => $request->data10[$in],
                    ];

                    $img2 = str_replace('data:image/jpeg;base64,', '', $request->foto_lain2[$in]);
                    $file2 = base64_decode($img2);
                    $safeName2 = DATE('YmdHis') . '_' . $this->user_id . $pf . '.jpeg';
                    $destinationPath2 = public_path() . '/dokumentasi/sampling/';
                    $success2 = file_put_contents($destinationPath2 . $safeName2, $file2);
                    if ($request->kateg_uji2[$in] == '24 Jam') {
                        $shift_peng = $request->kateg_uji2[$in] . '-' . json_encode($request->shift2[$in]);
                    } else if ($request->kateg_uji2[$in] == '8 Jam') {
                        $shift_peng = $request->kateg_uji2[$in] . '-' . json_encode($request->shift2[$in]);
                    } else if ($request->kateg_uji2[$in] == '6 Jam') {
                        $shift_peng = $request->kateg_uji2[$in] . '-' . json_encode($request->shift2[$in]);
                    } else {
                        $shift_peng = 'Sesaat';
                    }
                    $data = new DataLapanganDirectLain();
                    $data->no_sampel                 = strtoupper(trim($request->no_sample));
                    if ($request->keterangan_4 != '') $data->keterangan            = $request->keterangan_4;
                    if ($request->keterangan_2 != '') $data->keterangan_2          = $request->keterangan_2;
                    // if ($request->posisi != '') $data->titik_koordinat             = $request->posisi;
                    $data->titik_koordinat = $request->posisi ?? '-';
                    if ($request->lat != '') $data->latitude                            = $request->lat;
                    if ($request->longi != '') $data->longitude                        = $request->longi;
                    if ($request->categori != '') $data->kategori_3                = $request->categori;
                    if ($request->lok != '') $data->lokasi                         = $request->lok;
                    $data->parameter                         = $a;

                    if ($request->jenis_peng != '') $data->jenis_pengukuran              = $request->jenis_peng;
                    if ($request->kon_lapangan != '') $data->kondisi_lapangan              = $request->kon_lapangan;
                    if ($request->waktu != '') $data->waktu                        = $request->waktu;
                    $data->shift                   = $shift_peng;
                    if ($request->suhu != '') $data->suhu                          = $request->suhu;
                    if ($request->kelem != '') $data->kelembaban                        = $request->kelem;
                    if ($request->tekU != '') $data->tekanan_udara                     = $request->tekU;
                    $data->pengukuran     = json_encode($pengukuran);

                    if ($request->permis != '') $data->permission                    = $request->permis;
                    $data->foto_lain        = $safeName2;
                    $data->created_by                     = $this->karyawan;
                    $data->created_at                     = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                }
            }
            if ($request->param3 != null) {
                $pe = 40;
                $pf = 50;
                foreach ($request->param3 as $in => $a) {
                    $pe++;
                    $pf++;
                    $pengukuran = array();
                    $pengukuran = [
                        'data-1' => $request->data11[$in],
                        'data-2' => $request->data12[$in],
                        'data-3' => $request->data13[$in],
                        'data-4' => $request->data14[$in],
                        'data-5' => $request->data15[$in],
                    ];

                    $img2 = str_replace('data:image/jpeg;base64,', '', $request->foto_lain3[$in]);
                    $file2 = base64_decode($img2);
                    $safeName2 = DATE('YmdHis') . '_' . $this->user_id . $pf . '.jpeg';
                    $destinationPath2 = public_path() . '/dokumentasi/sampling/';
                    $success2 = file_put_contents($destinationPath2 . $safeName2, $file2);
                    if ($request->kateg_uji3[$in] == '24 Jam') {
                        $shift_peng = $request->kateg_uji3[$in] . '-' . json_encode($request->shift3[$in]);
                    } else if ($request->kateg_uji3[$in] == '8 Jam') {
                        $shift_peng = $request->kateg_uji3[$in] . '-' . json_encode($request->shift3[$in]);
                    } else if ($request->kateg_uji3[$in] == '6 Jam') {
                        $shift_peng = $request->kateg_uji3[$in] . '-' . json_encode($request->shift3[$in]);
                    } else {
                        $shift_peng = 'Sesaat';
                    }
                    $data = new DataLapanganDirectLain();
                    $data->no_sampel                 = strtoupper(trim($request->no_sample));
                    if ($request->keterangan_4 != '') $data->keterangan            = $request->keterangan_4;
                    if ($request->keterangan_2 != '') $data->keterangan_2          = $request->keterangan_2;
                    // if ($request->posisi != '') $data->titik_koordinat             = $request->posisi;
                    $data->titik_koordinat = $request->posisi ?? '-';
                    if ($request->lat != '') $data->latitude                            = $request->lat;
                    if ($request->longi != '') $data->longitude                        = $request->longi;
                    if ($request->categori != '') $data->kategori_3                = $request->categori;
                    if ($request->lok != '') $data->lokasi                         = $request->lok;
                    $data->parameter                         = $a;

                    if ($request->jenis_peng != '') $data->jenis_pengukuran              = $request->jenis_peng;
                    if ($request->kon_lapangan != '') $data->kondisi_lapangan              = $request->kon_lapangan;
                    if ($request->waktu != '') $data->waktu                        = $request->waktu;
                    $data->shift                   = $shift_peng;
                    if ($request->suhu != '') $data->suhu                          = $request->suhu;
                    if ($request->kelem != '') $data->kelembaban                        = $request->kelem;
                    if ($request->tekU != '') $data->tekanan_udara                     = $request->tekU;
                    $data->pengukuran     = json_encode($pengukuran);

                    if ($request->permis != '') $data->permission                    = $request->permis;
                    $data->foto_lain        = $safeName2;
                    $data->created_by                     = $this->karyawan;
                    $data->created_at                     = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                }
            }

            $update = DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $nama = $this->karyawan;
            $this->resultx = "Data Sampling DIRECT LAIN Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

            // if ($this->pin != null) {

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $this->resultx);
            // }

            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
        }catch(\Exception $e){
            DB::rollback();
            return response()->json([
                'message' => $e.getMessage(),
                'line' => $e.getLine()
            ]);
        }
        
    }

    public function indexDirectLain(Request $request)
    {
        $data = DataLapanganDirectLain::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function detailDirectLain(Request $request)
    {
        $data = DataLapanganDirectLain::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample Direct lainnya success';

        return response()->json([
            'id'             => $data->id,
            'no_sample'      => $data->no_sampel,
            'no_order'       => $data->detail->no_order,
            'categori'       => explode('-', $data->detail->kategori_3)[1],
            'sampler'        => $data->created_by,
            'corp'           => $data->detail->nama_perusahaan,
            'keterangan'     => $data->keterangan,
            'keterangan_2'   => $data->keterangan_2,
            'lat'            => $data->latitude,
            'long'           => $data->longitude,
            'parameter'      => $data->parameter,
            'kon_lapangan'   => $data->kondisi_lapangan,

            'lokasi'         => $data->lokasi,
            'jenis_peng'     => $data->jenis_pengukuran,
            'waktu'          => $data->waktu,
            'shift'          => $data->shift,
            'suhu'           => $data->suhu,
            'kelem'          => $data->kelembaban,
            'tekanan_u'      => $data->tekanan_udara,
            'pengukuran'     => $data->pengukuran,

            'tikoor'         => $data->titik_koordinat,
            'foto_lok'       => $data->foto_lokasi_sampel,
            'foto_lain'      => $data->foto_lain,
            'coor'           => $data->titik_koordinat,
            'status'         => '200'
        ], 200);
    }

    public function approveDirectLain(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganDirectLain::where('id', $request->id)->first();
            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
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

    public function deleteDirectLain(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganDirectLain::where('id', $request->id)->first();
            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            return response()->json([
                'message' => 'Data has ben Delete',
                'cat' => 4
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // KEBISINGAN
    public function addKebisingan(Request $request)
    {
        // if ($request->id_kat == 23 || $request->id_kat == 24 || $request->id_kat == 25 || $request->id_kat == 26) {
        DB::beginTransaction();
        try {
            $cek = DataLapanganKebisingan::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
            $nilai_array = [];
            foreach ($cek as $key => $value) {
                if ($value->jenis_durasi_sampling == 'Sesaat') {
                    if ($request->jenis_durasi == $value->jenis_durasi_sampling) {
                        return response()->json([
                            'message' => 'Shift sesaat sudah terinput di no sample ini .!'
                        ], 401);
                    }
                } else {
                    $aa = $value->jenis_durasi_sampling;
                    $ab = explode("-", $aa);
                    array_push($nilai_array, str_replace('"', "", $ab[1]));
                }
            }

            if (in_array($request->durasi_sampl, $nilai_array)) {
                return response()->json([
                    'message' => 'Shift Pengambilan ' . $request->durasi_sampl . ' sudah ada !'
                ], 401);
            }

            $jendur = $request->jenis_durasi;
            if ($request->jenis_durasi == "24 Jam" || $request->jenis_durasi == '8 Jam') {
                $jendur = $request->jenis_durasi . '-' . json_encode($request->durasi_sampl);
            }

            $data = new DataLapanganKebisingan();

            $data->no_sampel = strtoupper(trim($request->no_sample));

            if ($request->keterangan_4) {
                $data->keterangan = $request->keterangan_4;
            }

            if ($request->information) {
                $data->informasi_tambahan = $request->information;
            }

            // if ($request->posisi) {
            //     $data->titik_koordinat = $request->posisi;
            // }

            $data->titik_koordinat = $request->posisi ?? '-';

            if ($request->lat) {
                $data->latitude = $request->lat;
            }

            if ($request->longi) {
                $data->longitude = $request->longi;
            }

            if ($request->jen_frek) {
                $data->jenis_frekuensi_kebisingan = $request->jen_frek;
            }

            if ($request->waktu) {
                $data->waktu = $request->waktu;
            }

            // Penambahan jam pemaparan
            if ($request->jam_pemaparan !== null && $request->jam_pemaparan !== '') {
                $data->jam_pemaparan = $request->jam_pemaparan;
            }

            if ($request->sumber_keb) {
                $data->sumber_kebisingan = $request->sumber_keb;
            }

            if ($request->jenis_kat) {
                $data->jenis_kategori_kebisingan = $request->jenis_kat;
            }

            if ($request->jenis_durasi) {
                $data->jenis_durasi_sampling = $jendur;
            }

            if ($request->kebisingan) {
                $data->value_kebisingan = json_encode($request->kebisingan);
            }

            if ($request->suhu_udara) {
                $data->suhu_udara = $request->suhu_udara;
            }

            if ($request->kelembapan_udara) {
                $data->kelembapan_udara = $request->kelembapan_udara;
            }

            if ($request->permis) {
                $data->permission = $request->permis;
            }

            if ($request->foto_lok) {
                $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
            }

            if ($request->foto_lain) {
                $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
            }

            // $data->is_rejected = false;

            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            $update = DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $nama = $this->karyawan;
            $this->resultx = "Data Sampling KEBISINGAN Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

            // if($this->pin!=null){

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $this->resultx);
            // }
            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e.getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine()
            ]);
        }

        // }
    }

    public function indexKebisingan(Request $request)
    {
        $data = DataLapanganKebisingan::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');
        // dd($data);

        return Datatables::of($data)->make(true);
    }

    public function detailKebisingan(Request $request)
    {
        $data = DataLapanganKebisingan::with('detail')->where('id', $request->id)->first();

        $this->resultx = 'get Detail sample lapangan Kebisingan success';

        return response()->json([
            'id'                        => $data->id,
            'no_sample'                 => $data->no_sampel,
            'no_order'                  => $data->detail->no_order,
            'sampler'                   => $data->created_by,
            'categori'              => explode('-', $data->detail->kategori_3)[1],
            // 'id_sub_kategori'           => explode('-', $data->detail->kategori_3)[0],
            'jam'                       => $data->waktu,
            'corp'                      => $data->detail->nama_perusahaan,
            'keterangan'                => $data->keterangan,
            'lat'                  => $data->latitude,
            'long'                 => $data->longitude,
            'coor'           => $data->titik_koordinat,
            'massage'                   => $this->resultx,
            'info_tambahan'             => $data->informasi_tambahan,
            'keterangan'                => $data->keterangan,
            'sumber_keb'         => $data->sumber_kebisingan,
            'jenis_frek' => $data->jenis_frekuensi_kebisingan,
            'jenis_kate' => $data->jenis_kategori_kebisingan,
            'jenis_durasi'     => $data->jenis_durasi_sampling,
            'suhu_udara'                => $data->suhu_udara,
            'kelem_udara'          => $data->kelembapan_udara,
            'val_kebisingan'          => $data->value_kebisingan,
            'tikoor'           => $data->titik_koordinat,
            'foto_lok'                  => $data->foto_lokasi_sampel,
            'foto_lain'                 => $data->foto_lain,
            'status'                    => '200'
        ], 200);
    }

    public function approveKebisingan(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKebisingan::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL KEBISINGAN dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function deleteKebisingan(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKebisingan::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL KEBISINGAN dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
                'cat' => 4
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // KEBISINGAN PERSONAL
    public function addKebisinganPersonal(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->foto_lok == '') {
                return response()->json([
                    'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                ], 401);
            }

            if ($request->foto_lain == '') {
                return response()->json([
                    'message' => 'Foto lain-lain tidak boleh kosong .!'
                ], 401);
            }

            $cek2 = DataLapanganKebisinganPersonal::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if ($cek2) {
                return response()->json([
                    'message' => 'No Sample sudah diinput!.'
                ], 401);
            } else {

                $data = new DataLapanganKebisinganPersonal;
                $data->no_sampel                 = strtoupper(trim($request->no_sample));
                if ($request->keterangan_4 != '') $data->keterangan       = $request->keterangan_4;
                // if ($request->posisi != '') $data->titik_koordinat        = $request->posisi;
                $data->titik_koordinat = $request->posisi ?? '-';
                if ($request->lat != '') $data->latitude                       = $request->lat;
                if ($request->longi != '') $data->longitude                   = $request->longi;
                if ($request->keterangan_2 != '') $data->keterangan_2     = $request->keterangan_2;
                if ($request->categori != '') $data->kategori_3             = $request->categori;

                if ($request->departemen != '') $data->departemen            = $request->departemen;
                // if ($request->waktu_paparan != '') $data->waktu_paparan             = $request->waktu_paparan;
                if ($request->sumber != '') $data->sumber_kebisingan             = $request->sumber;
                if ($request->mulai != '') $data->jam_mulai_pengujian             = $request->mulai;
                if ($request->istirahat != '') $data->total_waktu_istirahat_personal             = $request->istirahat;
                if ($request->selesai != '') $data->jam_akhir_pengujian             = $request->selesai;
                if ($request->total != '') $data->waktu_pengukuran                   = $request->total;
                if ($request->jarak != '') $data->jarak_sumber_kebisingan                   = $request->jarak;
                if ($request->aktifitas != '') $data->aktifitas                   = $request->aktifitas;

                if ($request->permis != '') $data->permission                 = $request->permis;
                // $data->is_rejected = false;
                if ($request->foto_lok != '') $data->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
                if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                $data->created_by                     = $this->karyawan;
                $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // Update Order Detail
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                $nama = $this->karyawan;
                $this->resultx = "Data Sampling KEBISINGAN PERSONAL Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

                DB::commit();

                // if ($this->pin != null) {

                //     $telegram = new Telegram();
                //     $telegram->send($this->pin, $this->resultx);
                // }

                return response()->json([
                    'message' => $this->resultx
                ], 200);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function indexKebisinganPersonal(Request $request)
    {
        $data = DataLapanganKebisinganPersonal::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function detailKebisinganPersonal(Request $request)
    {
        $data = DataLapanganKebisinganPersonal::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Kebisingan Personal success';

        return response()->json([
            'id'             => $data->id,
            'no_sample'      => $data->no_sampel,
            'no_order'       => $data->detail->no_order,
            'categori'       => explode('-', $data->detail->kategori_3)[1],
            'sampler'        => $data->created_by,
            'corp'           => $data->detail->nama_perusahaan,
            'keterangan'     => $data->keterangan,
            'keterangan_2'   => $data->keterangan_2,
            'waktu'          => $data->waktu_pengukuran,
            'mulai'          => $data->jam_mulai_pengujian,
            'selesai'        => $data->jam_akhir_pengujian,
            'istirahat'      => $data->total_waktu_istirahat_personal,
            'lat'            => $data->latitude,
            'long'           => $data->longitude,
            'massage'        => $this->resultx,
            'departemen'     => $data->departemen,
            'sumber'         => $data->sumber_kebisingan,
            'jarak'          => $data->jarak_sumber_kebisingan,
            'paparan'        => $data->waktu_pengukuran,
            'aktifitas'      => $data->aktifitas,
            'tikoor'         => $data->titik_koordinat,
            'foto_lok'       => $data->foto_lokasi_sampel,
            'foto_lain'      => $data->foto_lain,
            'coor'           => $data->titik_koordinat,
            'status'         => '200'
        ], 200);
    }

    public function approveKebisinganPersonal(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapangankebisinganPersonal::where('id', $request->id)->first();
            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Kebisingan Personal dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function deleteKebisinganPersonal(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKebisinganPersonal::where('id', $request->id)->first();
            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Kebisingan Personal dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
                'cat' => 4
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // CAHAYA
    public function addCahaya(Request $request)
    {
        DB::beginTransaction();
        try{
            $fdl = DataLapanganCahaya::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if ($fdl) {
                return response()->json([
                    'message' => 'No Sample sudah diinput!.'
                ], 401);
            } else {
                if ($request->foto_lok == '') {
                    return response()->json([
                        'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                    ], 401);
                }
                if ($request->foto_lain == '') {
                    return response()->json([
                        'message' => 'Foto Roadmap tidak boleh kosong .!'
                    ], 401);
                }

                if ($request->categori == 'Pencahayaan Umum') {
                    if ($request->mulai == '') {
                        return response()->json([
                            'message' => 'Jam mulai tidak boleh kosong .!'
                        ], 401);
                    }
                    if ($request->selesai == '') {
                        return response()->json([
                            'message' => 'Jam selesai tidak boleh kosong .!'
                        ], 401);
                    }
                    if ($request->ratarata != null) {
                        $a = count($request->ratarata);
                        $pengukuran = array();
                        for ($i = 0; $i < $a; $i++) {
                            $b = $i + 1;
                            $pengukuran['titik-' . $b . ''] =  $request->ratarata[$i] . '; ' . $request->ket_peng[$i] . '; ' . $request->ken_lampu[$i] . '; ' . $request->war_lampu[$i];
                        }
                    }
                    if ($request->ket_peng != null) {
                        $a = count($request->ket_peng);
                        $nilai_peng = array();
                        for ($i = 0; $i < $a; $i++) {
                            $nilai_peng[] = [
                                'ulangan-1' => $request->ulangan1[$i],
                                'ulangan-2' => $request->ulangan2[$i],
                                'ulangan-3' => $request->ulangan3[$i],
                                'rata-rata' => $request->ratarata[$i],
                                'keterangan' => $request->ket_peng[$i],
                                'kendala' => $request->ken_lampu[$i],
                                'warna' => $request->war_lampu[$i],
                            ];
                        }
                    }
                }

                if ($request->categori == 'Pencahayaan Setempat') {

                    if ($request->waktu == '') {
                        return response()->json([
                            'message' => 'Jam pengambilan tidak boleh kosong .!'
                        ], 401);
                    }

                    if ($request->ratarata != null) {
                        $a = count($request->ratarata);
                        $pengukuran = array();
                        for ($i = 0; $i < $a; $i++) {
                            $b = $i + 1;
                            $pengukuran['titik-' . $b . ''] =  $request->ratarata[$i] . '; ' . $request->ket_peng[$i] . '; ' . $request->ken_lampu[$i] . '; ' . $request->war_lampu[$i];
                        }
                    }

                    if ($request->ket_peng != null) {
                        $a = count($request->ket_peng);
                        $nilai_peng = array();
                        for ($i = 0; $i < $a; $i++) {
                            $nilai_peng[] = [
                                'ulangan-1' => $request->ulangan1[$i],
                                'ulangan-2' => $request->ulangan2[$i],
                                'ulangan-3' => $request->ulangan3[$i],
                                'rata-rata' => $request->ratarata[$i],
                                'keterangan' => $request->ket_peng[$i],
                                'kendala' => $request->ken_lampu[$i],
                                'warna' => $request->war_lampu[$i],
                            ];
                        }
                    }
                }

                $data = new DataLapanganCahaya;
                $data->no_sampel                                           = strtoupper(trim($request->no_sample));
                if ($request->keterangan_4 != '') $data->keterangan        = $request->keterangan_4;
                if ($request->information != '') $data->informasi_tambahan = $request->information;
                // if ($request->posisi != '') $data->titik_koordinat         = $request->posisi;
                $data->titik_koordinat = $request->posisi ?? '-';
                if ($request->lat != '') $data->latitude                        = $request->lat;
                if ($request->longi != '') $data->longitude                    = $request->longi;

                if ($request->waktu != '') $data->waktu_pengambilan                    = $request->waktu;
                if ($request->panjang != '') $data->panjang                = $request->panjang;
                if ($request->categori != '') $data->kategori              = $request->categori;
                if ($request->jenis_penem != '') $data->jenis_tempat_alat_sensor          = $request->jenis_penem;
                if ($request->lebar != '') $data->lebar                    = $request->lebar;
                if ($request->luas != '') $data->luas                      = $request->luas;
                if ($request->jenis_cahaya != '') $data->jenis_cahaya      = $request->jenis_cahaya;
                if($request->jenis_penem != ""){
                    if ($request->jml_titik_p != '') $data->titik_pengujian_sampler        = $request->jml_titik_p;
                }
                if ($request->titik_p_sampler != '') $data->titik_pengujian_sampler        = $request->titik_p_sampler;
                if ($request->jml_titik_p != '') $data->jumlah_titik_pengujian        = $request->jml_titik_p;
                if ($request->jenis_lamp != '') $data->jenis_lampu         = $request->jenis_lamp;
                if ($request->jml_kerja != '') $data->jumlah_tenaga_kerja            = $request->jml_kerja;
                if ($request->mulai != '') $data->jam_mulai_pengukuran                    = $request->mulai;
                if ($request->ratarata != null) $data->pengukuran          = json_encode($pengukuran);
                if ($request->ket_peng != null) $data->nilai_pengukuran          = json_encode($nilai_peng);
                if ($request->selesai != '') $data->jam_selesai_pengukuran                = $request->selesai;
                if ($request->aktifitas != '') $data->aktifitas            = $request->aktifitas;

                if ($request->permis != '') $data->permission                  = $request->permis;
                if ($request->foto_lok != '') $data->foto_lokasi_sampel    = self::convertImg($request->foto_lok, 1, $this->user_id);
                if ($request->foto_lain != '') $data->foto_lain            = self::convertImg($request->foto_lain, 3, $this->user_id);
                $data->created_by                                              = $this->karyawan;
                $data->created_at                                             = Carbon::now()->format('Y-m-d H:i:s');
                // $data->is_rejected = false;
                $data->save();

                // Update Order Detail
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]); 

                $nama = $this->karyawan;
                $this->resultx = "Data Sampling PENCAHAYAAN Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

                // if($this->pin!=null){

                //     $telegram = new Telegram();
                //     $telegram->send($this->pin, $this->resultx);
                // }
                DB::commit();
                return response()->json([
                    'message' => $this->resultx
                ], 200);
            }
        }catch(\Exception $e){
            DB::rollback();
            return response()->json([
                'message' => $e.getMessage(),
                'line' => $e->getLine(),
                'code' => $e->getCode()
            ]);
        }
    }

    public function indexCahaya(Request $request)
    {
        $data = DataLapanganCahaya::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function detailCahaya(Request $request)
    {
        $data = DataLapanganCahaya::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Cahaya success';


        return response()->json([
            'id'             => $data->id,
            'no_sample'      => $data->no_sampel,
            'no_order'       => $data->detail->no_order,
            'categori'       => $data->kategori,
            'sampler'        => $data->created_by,
            'corp'           => $data->detail->nama_perusahaan,
            'keterangan'     => $data->keterangan,
            'lat'            => $data->latitude,
            'long'           => $data->longitude,
            'massage'        => $this->resultx,
            'info_tambahan'  => $data->informasi_tambahan,
            'jenis_tem'      => $data->jenis_tempat_alat_sensor,
            'waktu'          => $data->waktu_pengambilan,
            'panjang'        => $data->panjang,
            'lebar'          => $data->lebar,
            'luas'           => $data->luas,
            'jml_titik_p'    => $data->jumlah_titik_pengujian,
            'titik_p_sampler'    => $data->titik_pengujian_sampler,
            'jenis_cahaya'   => $data->jenis_cahaya,
            'jenis_lampu'    => $data->jenis_lampu,
            'jml_kerja'      => $data->jumlah_tenaga_kerja,
            'mulai'          => $data->jam_mulai_pengukuran,
            'pengukuran'     => $data->pengukuran,
            'nilai_peng'     => $data->nilai_pengukuran,
            'selesai'        => $data->jam_selesai_pengukuran,
            'tikoor'         => $data->titik_koordinat,
            'foto_lok'       => $data->foto_lokasi_sampel,
            'foto_lain'      => $data->foto_lain,
            'aktifitas'      => $data->aktifitas,
            'coor'           => $data->titik_koordinat,
            'status'         => '200'
        ], 200);
    }

    public function approveCahaya(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganCahaya::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL CAHAYA dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function deleteCahaya(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganCahaya::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL CAHAYA dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
                'cat' => 4
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // GETARAN
    public function addGetaran(Request $request)
    {
        DB::beginTransaction();
        try{
            $fdl = DataLapanganGetaran::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if ($fdl) {
                return response()->json([
                    'message' => 'No Sample sudah diinput!.'
                ], 401);
            } else {

                if ($request->waktu == '') {
                    return response()->json([
                        'message' => 'Jam pengambilan tidak boleh kosong .!'
                    ], 401);
                }
                if ($request->foto_lok == '') {
                    return response()->json([
                        'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                    ], 401);
                }
                if ($request->foto_lain == '') {
                    return response()->json([
                        'message' => 'Foto lain-lain tidak boleh kosong .!'
                    ], 401);
                }
                try {
                    $nilai_peng = array();
                    if ($request->id_kateg == 13 || $request->id_kateg == 14 || $request->id_kateg == 15 || $request->id_kateg == 16 || $request->id_kateg == 18 || $request->id_kateg == 19) {
                        $a = count($request->min_per);
                        for ($i = 0; $i < $a; $i++) {
                            $no = $i + 1;
                            $nilai_peng['Data-' . $no] = [
                                'min_per' => $request->min_per[$i],
                                'max_per' => $request->max_per[$i],
                                'min_kec' => $request->min_kec[$i],
                                'max_kec' => $request->max_kec[$i],
                            ];
                        }
                    } else if ($request->id_kateg == 20) {
                        $a = count($request->perminT);
                        for ($i = 0; $i < $a; $i++) {
                            $no = $i + 1;
                            $nilai_peng['Data-' . $no] = [
                                'perminT' => $request->perminT[$i],
                                'permaxT' => $request->permaxT[$i],
                                'kecminT' => $request->kecminT[$i],
                                'kecmaxT' => $request->kecmaxT[$i],
                                'perminP' => $request->perminP[$i],
                                'permaxP' => $request->permaxP[$i],
                                'kecminP' => $request->kecminP[$i],
                                'kecmaxP' => $request->kecmaxP[$i],
                                'perminB' => $request->perminB[$i],
                                'permaxB' => $request->permaxB[$i],
                                'kecminB' => $request->kecminB[$i],
                                'kecmaxB' => $request->kecmaxB[$i],
                            ];
                        }
                    } else if ($request->id_kateg == 17) {
                        $a = count($request->perminT1);
                        for ($i = 0; $i < $a; $i++) {
                            $no = $i + 1;
                            $nilai_peng['Data-' . $no] = [
                                'perminT' => $request->perminT1[$i],
                                'permaxT' => $request->permaxT1[$i],
                                'kecminT' => $request->kecminT1[$i],
                                'kecmaxT' => $request->kecmaxT1[$i],
                                'perminP' => $request->perminP1[$i],
                                'permaxP' => $request->permaxP1[$i],
                                'kecminP' => $request->kecminP1[$i],
                                'kecmaxP' => $request->kecmaxP1[$i],
                                1
                            ];
                        }
                    }

                    $data = new DataLapanganGetaran();
                    $data->no_sampel                 = strtoupper(trim($request->no_sample));
                    if ($request->keterangan_4 != '') $data->keterangan          = $request->keterangan_4;
                    // if ($request->posisi != '') $data->titik_koordinat           = $request->posisi;
                    $data->titik_koordinat = $request->posisi ?? '-';
                    if ($request->lat != '') $data->latitude                          = $request->lat;
                    if ($request->longi != '') $data->longitude                      = $request->longi;

                    if ($request->keterangan_2 != '') $data->keterangan_2        = $request->keterangan_2;
                    if ($request->id_kateg != '') $data->kategori_3                = $request->id_kateg;
                    if ($request->waktu != '') $data->waktu_pengukuran                      = $request->waktu;
                    if ($request->sumber != '') $data->sumber_getaran                = $request->sumber;
                    if ($request->jarak != '') $data->jarak_sumber_getaran                  = $request->jarak;
                    if ($request->kondisi != '') $data->kondisi                  = $request->kondisi;
                    if ($request->intensitas != '') $data->intensitas            = $request->intensitas;
                    if ($request->frekuensi != '') $data->frekuensi                   = $request->frekuensi;

                    // Name di APPS KEBALIK satPer untuk satuan_kecepatan, satKec untuk satuan_percepatan
                    if ($request->satPer != '') $data->satuan_kecepatan                   = $request->satPer;
                    if ($request->satKec != '') $data->satuan_percepatan                   = $request->satKec;
                    if ($request->nama_pekerja != '') $data->nama_pekerja                   = $request->nama_pekerja;
                    if ($request->jenis_pekerja != '') $data->jenis_pekerja                   = $request->jenis_pekerja;
                    if ($request->lokasi_unit != '') $data->lokasi_unit                   = $request->lokasi_unit;
                    // $data->pengukuran         = json_encode($pengukuran);
                    // $data->is_rejected = false;
                    $data->nilai_pengukuran            = json_encode($nilai_peng);

                    if ($request->permis != '') $data->permission                    = $request->permis;
                    if ($request->foto_lok != '') $data->foto_lokasi_sampel      = self::convertImg($request->foto_lok, 1, $this->user_id);
                    if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    $data->created_by                     = $this->karyawan;
                    $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                } catch (Exception $e) {
                    dd($e);
                }

                // Update Order Detail
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);


                $nama = $this->karyawan;
                $this->resultx = "Data Sampling GETARAN LINGKUNGAN Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

                // if($this->pin!=null){
                //     $telegram = new Telegram();
                //     $telegram->send($this->pin, $this->resultx);
                // }
                DB::commit();
                return response()->json([
                    'message' => $this->resultx
                ], 200);
            }
        }catch(Exception $e){
            DB::rollBack();
            return response()->json([
               'message' => $e.getMessage(),
               'code' => $e.getCode(),
               'line' => $e.getLine()
            ]);
        }
        
    }

    public function indexGetaran(Request $request)
    {
        $data = DataLapanganGetaran::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approveGetaran(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganGetaran::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL GETARAN LINGKUNGAN dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function detailGetaran(Request $request)
    {
        $data = DataLapanganGetaran::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Getaran success';

        return response()->json([
            'id'             => $data->id,
            'id_kat'         => $data->kategori_3,
            'no_sample'      => $data->no_sampel,
            'no_order'       => $data->detail->no_order,
            'categori'       => explode('-', $data->detail->kategori_3)[1],
            'sampler'        => $data->created_by,
            'corp'           => $data->detail->nama_perusahaan,
            'keterangan'     => $data->keterangan,
            'keterangan_2'   => $data->keterangan_2,
            'waktu'          => $data->waktu_pengukuran,
            'lat'            => $data->latitude,
            'long'           => $data->longitude,
            'massage'        => $this->resultx,
            'sumber_get'     => $data->sumber_getaran,
            'jarak_get'      => $data->jarak_sumber_getaran,
            'kondisi'        => $data->kondisi,
            'intensitas'     => $data->intensitas,
            'frek'           => $data->frekuensi,
            'sat_kec'        => $data->satuan_kecepatan,
            'sat_per'        => $data->satuan_percepatan,
            'pengukuran'     => $data->pengukuran,
            'nilai_peng'     => $data->nilai_pengukuran,
            'tikoor'         => $data->titik_koordinat,
            'foto_lok'       => $data->foto_lokasi_sampel,
            'foto_lain'      => $data->foto_lain,
            'coor'           => $data->titik_koordinat,
            'nama_pekerja'   => $data->nama_pekerja,
            'jenis_pekerja'  => $data->jenis_pekerja,
            'lokasi_unit'    => $data->lokasi_unit,
            'status'         => '200'
        ], 200);
    }

    public function deleteGetaran(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganGetaran::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL GETARAN LINGKUNGAN dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
                'cat' => 4
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // GETARAN PERSONAL
    // public function addGetaranPersonal(Request $request)
    // {
    //     DB::beginTransaction();
    //     try{
    //         $fdl = DataLapanganGetaranPersonal::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
    //         if ($fdl) {
    //             return response()->json([
    //                 'message' => 'No Sample sudah diinput!.'
    //             ], 401);
    //         } else {
    //             if ($request->waktu == '') {
    //                 return response()->json([
    //                     'message' => 'Jam pengambilan tidak boleh kosong .!'
    //                 ], 401);
    //             }
    //             if ($request->foto_lok == '') {
    //                 return response()->json([
    //                     'message' => 'Foto lokasi sampling tidak boleh kosong .!'
    //                 ], 401);
    //             }
    //             if ($request->foto_lain == '') {
    //                 return response()->json([
    //                     'message' => 'Foto lain-lain tidak boleh kosong .!'
    //                 ], 401);
    //             }

    //             $pengukuran = [];
    //             $a = count($request->x1);
    //             for ($i = 0; $i < $a; $i++) {
    //                 $no = $i + 1;
    //                 $pengukuran['Data-' . $no] = [
    //                     'x1' => $request->x1[$i],
    //                     'x2' => $request->x2[$i],
    //                     'y1' => $request->y1[$i],
    //                     'y2' => $request->y2[$i],
    //                     'z1' => $request->z1[$i],
    //                     'z2' => $request->z2[$i]
    //                 ];
    //             }

    //             $data = new DataLapanganGetaranPersonal();
    //             $data->no_sampel                 = strtoupper(trim($request->no_sample));
    //             if ($request->keterangan_4 != '') $data->keterangan       = $request->keterangan_4;
    //             if ($request->posisi != '') $data->titik_koordinat        = $request->posisi;
    //             if ($request->lat != '') $data->latitude                       = $request->lat;
    //             if ($request->longi != '') $data->longitude                   = $request->longi;

    //             if ($request->id_kateg != '') $data->kategori_3             = $request->id_kateg;
    //             if ($request->metode_peng != '') $data->metode            = $request->metode_peng;
    //             if ($request->sumber != '') $data->sumber_getaran             = $request->sumber;
    //             if ($request->keterangan_2 != '') $data->keterangan_2     = $request->keterangan_2;
    //             if ($request->waktu != '') $data->waktu_pengukuran                   = $request->waktu;
    //             if ($request->paparan != '') $data->durasi_paparan              = $request->paparan;
    //             if ($request->kerja != '') $data->durasi_kerja                  = $request->kerja;
    //             if ($request->kondisi != '') $data->kondisi               = $request->kondisi;
    //             if ($request->intensitas != '') $data->intensitas         = $request->intensitas;
    //             if ($request->satPer != '') $data->satuan_percepatan                 = $request->satPer;
    //             if ($request->satKec != '') $data->satuan_kecepatan                 = $request->satKec;
    //             if ($request->satKecX != '') $data->satuan_kecepatan_x         = $request->satKecX;
    //             if ($request->satKecY != '') $data->satuan_kecepatan_y         = $request->satKecY;
    //             if ($request->satKecZ != '') $data->satuan_kecepatan_z         = $request->satKecZ;

    //             if ($request->nama_pekerja != '') $data->nama_pekerja         = $request->nama_pekerja;
    //             if ($request->jenis_pekerja != '') $data->jenis_pekerja         = $request->jenis_pekerja;
    //             if ($request->lokasi_unit != '') $data->lokasi_unit         = $request->lokasi_unit;
    //             if ($request->alat_ukur != '') $data->alat_ukur         = $request->alat_ukur;
    //             if ($request->dur_pengukuran != '') $data->durasi_pengukuran         = $request->dur_pengukuran;
    //             if ($request->adaptor != '') $data->adaptor         = $request->adaptor;
    //             if ($request->posisi_pengukuran != '') $data->posisi_pengukuran         = $request->posisi_pengukuran;
    //             $data->pengukuran     = json_encode($pengukuran);

    //             if ($request->permis != '') $data->permission                 = $request->permis;
    //             if ($request->foto_lok != '') $data->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
    //             if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
    //             $data->created_by                     = $this->karyawan;
    //             $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
    //             $data->save();

    //             $update = DB::table('order_detail')
    //                 ->where('no_sampel', strtoupper(trim($request->no_sample)))
    //                 ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

    //             $nama = $this->karyawan;
    //             $this->resultx = "Data Sampling GETARAN PERSONAL Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

    //             // if ($this->pin != null) {
    //             //     $telegram = new Telegram();
    //             //     $telegram->send($this->pin, $this->resultx);
    //             // }
    //             DB::commit();
    //             return response()->json([
    //                 'message' => $this->resultx
    //             ], 200);
    //         }
    //     }catch(\Exception $e){
    //         DB::rollback();
    //         return response()->json([
    //             'message' => $e.getMessage(),
    //             'code' => $e.getCode(),
    //             'line' => $e.getLine()
    //         ]);
    //     }
        
    // }

    // 15/06/2025 - Goni
    public function addGetaranPersonal(Request $request)
    {
        DB::beginTransaction();
        try{
            $fdl = DataLapanganGetaranPersonal::where('no_sampel', $request->no_sampel)->first();
            if ($fdl) {
                return response()->json([
                    'message' => 'No Sample sudah diinput!.'
                ], 401);
            } else {
                if ($request->waktu == '') {
                    return response()->json([
                        'message' => 'Jam pengambilan tidak boleh kosong .!'
                    ], 401);
                }
                if ($request->foto_lok == '') {
                    return response()->json([
                        'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                    ], 401);
                }
                if ($request->foto_lain == '') {
                    return response()->json([
                        'message' => 'Foto lain-lain tidak boleh kosong .!'
                    ], 401);
                }

                $pengukuran = [];
                $a = count($request->x1);
                for ($i = 0; $i < $a; $i++) {
                    $no = $i + 1;
                    $pengukuran['Data-' . $no] = !isset($request->x4) ? [
                        'x1' => $request->x1[$i],
                        'x2' => $request->x2[$i],
                        'y1' => $request->y1[$i],
                        'y2' => $request->y2[$i],
                        'z1' => $request->z1[$i],
                        'z2' => $request->z2[$i],
                        'percepatan1' => $request->percepatan1[$i],
                        'percepatan2' => $request->percepatan2[$i],
                        'durasi_paparan' => $request->paparan[$i],
                    ] : 
                    [
                        'x1' => $request->x1[$i],
                        'x2' => $request->x2[$i],
                        'x3' => $request->x3[$i],
                        'x4' => $request->x4[$i],
                        'y1' => $request->y1[$i],
                        'y2' => $request->y2[$i],
                        'y3' => $request->y3[$i],
                        'y4' => $request->y4[$i],
                        'z1' => $request->z1[$i],
                        'z2' => $request->z2[$i],
                        'z3' => $request->z3[$i],
                        'z4' => $request->z4[$i],
                        'percepatan1' => $request->percepatan1[$i],
                        'percepatan2' => $request->percepatan2[$i],
                        'percepatan3' => $request->percepatan3[$i],
                        'percepatan4' => $request->percepatan4[$i],
                        'durasi_paparan' => $request->paparan[$i],
                    ];
                }

                $data = new DataLapanganGetaranPersonal();
                $data->no_sampel                                                = strtoupper(trim($request->no_sample));
                if ($request->keterangan_4 != '') $data->keterangan             = $request->keterangan_4;
                // if ($request->posisi != '') $data->titik_koordinat              = $request->posisi;
                $data->titik_koordinat = $request->posisi ?? '-';
                if ($request->lat != '') $data->latitude                        = $request->lat;
                if ($request->longi != '') $data->longitude                     = $request->longi;
                if ($request->id_kateg != '') $data->kategori_3                 = $request->id_kateg;
                if ($request->metode_peng != '') $data->metode                  = $request->metode_peng;
                if ($request->sumber != '') $data->sumber_getaran               = $request->sumber;
                if ($request->keterangan_2 != '') $data->keterangan_2           = $request->keterangan_2;
                if ($request->paparan != '') $data->durasi_paparan              = json_encode($request->paparan);
                if ($request->waktu != '') $data->waktu_pengukuran              = $request->waktu;
                if ($request->kerja != '') $data->durasi_kerja                  = $request->kerja;
                if ($request->kondisi != '') $data->kondisi                     = $request->kondisi;
                if ($request->intensitas != '') $data->intensitas               = $request->intensitas;
                if ($request->satPer != '') $data->satuan_percepatan            = $request->satPer;
                if ($request->satKec != '') $data->satuan_kecepatan             = $request->satKec;
                if ($request->satKecX != '') $data->satuan_kecepatan_x          = $request->satKecX;
                if ($request->satKecY != '') $data->satuan_kecepatan_y          = $request->satKecY;
                if ($request->satKecZ != '') $data->satuan_kecepatan_z          = $request->satKecZ;
                if ($request->satAeq != '') $data->satuan_kecepatan_aeq          = $request->satAeq;
                if ($request->nama_pekerja != '') $data->nama_pekerja           = $request->nama_pekerja;
                if ($request->jenis_pekerja != '') $data->jenis_pekerja         = $request->jenis_pekerja;
                if ($request->lokasi_unit != '') $data->lokasi_unit             = $request->lokasi_unit;
                if ($request->alat_ukur != '') $data->alat_ukur                 = $request->alat_ukur;
                if ($request->dur_pengukuran != '') $data->durasi_pengukuran    = $request->dur_pengukuran;
                if ($request->adaptor != '') $data->adaptor                     = $request->adaptor;
                // $data->is_rejected = false;
                $data->pengukuran                                               = json_encode($pengukuran);
                if (isset($request->ke)) $data->bobot_frekuensi                 = json_encode(["ke" => $request->ke, "kd" => $request->kd, "kf" => $request->kf]);
                if ($request->posisi_pengukuran != '') $data->posisi_pengukuran = $request->posisi_pengukuran;
                if ($request->permis != '') $data->permission                   = $request->permis;
                if ($request->foto_lok != '') $data->foto_lokasi_sampel         = self::convertImg($request->foto_lok, 1, $this->user_id);
                if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                $data->created_by                                               = $this->karyawan;
                $data->created_at                                               = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // Update Order Detail
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                $nama = $this->karyawan;

                DB::commit();
                return response()->json([
                    'message' => "Data Sampling GETARAN PERSONAL Dengan No Sample $request->no_sample berhasil disimpan oleh $nama"
                ], 200);
            }
        }catch(\Exception $e){
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ],500);
        }
        
    }

    public function indexGetaranPersonal(Request $request)
    {
        $data = DataLapanganGetaranPersonal::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approveGetaranPersonal(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganGetaranPersonal::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL GETARAN Personal dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    // 15/06/2025 - Goni
    public function detailGetaranPersonal(Request $request)
    {
        $data = DataLapanganGetaranPersonal::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Getaran Personal success';

        return response()->json([
            'id'             => $data->id,
            'no_sample'      => $data->no_sampel,
            'no_order'       => $data->detail->no_order,
            'categori'       => explode('-', $data->detail->kategori_3)[1],
            'sampler'        => $data->created_by,
            'corp'           => $data->detail->nama_perusahaan,
            'keterangan'     => $data->keterangan,
            'keterangan_2'   => $data->keterangan_2,
            'waktu'          => $data->waktu_pengukuran,
            'lat'            => $data->latitude,
            'long'           => $data->longitude,
            'massage'        => $this->resultx,
            'sumber_get'     => $data->sumber_getaran,
            'metode'         => $data->metode,
            'posisi_peng'    => $data->posisi_penguji,
            'Dpaparan'       => $data->durasi_paparan,
            'Dkerja'         => $data->durasi_kerja,
            'kondisi'        => $data->kondisi,
            'intensitas'     => $data->intensitas,
            'satuan'         => $data->satuan,
            'pengukuran'     => $data->pengukuran,
            'tangan'     => $data->tangan,
            'pinggang'     => $data->pinggang,
            'betis'     => $data->betis,
            'satKec'     => $data->satuan_kecepatan,
            'satPer'     => $data->satuan_percepatan,
            'satKecX'     => $data->satuan_kecepatan_x,
            'satKecY'     => $data->satuan_kecepatan_y,
            'satKecZ'     => $data->satuan_kecepatan_z,
            'nama_pekerja'     => $data->nama_pekerja,
            'jenis_pekerja'     => $data->jenis_pekerja,
            'lokasi_unit'     => $data->lokasi_unit,
            'alat_ukur'     => $data->alat_ukur,
            'dur_pengukuran'     => $data->durasi_pengukuran,
            'adaptor'     => $data->adaptor,
            'tikoor'         => $data->titik_koordinat,
            'foto_lok'       => $data->foto_lokasi_sampel,
            'foto_lain'      => $data->foto_lain,
            'coor'            => $data->titik_koordinat,
            'status'         => '200'
        ], 200);
    }

    public function deleteGetaranPersonal(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganGetaranPersonal::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL GETARAN PERSONAL dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
                'cat' => 4
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // IKLIM PANAS
    public function addIklimPanas(Request $request)
    {
        DB::beginTransaction();
        try{
            if ($request->mulai == '') {
                return response()->json([
                    'message' => 'Jam mulai tidak boleh kosong .!'
                ], 401);
            }

            if ($request->selesai == '') {
                return response()->json([
                    'message' => 'Jam selesai tidak boleh kosong .!'
                ], 401);
            }

            if ($request->foto_lok == '') {
                return response()->json([
                    'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                ], 401);
            }

            if ($request->foto_lain == '') {
                return response()->json([
                    'message' => 'Foto lain-lain tidak boleh kosong .!'
                ], 401);
            }


                $nilai_array = [];
                $cek_nil = DataLapanganIklimPanas::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
                foreach ($cek_nil as $key => $value) {
                    // salah field 05/02/2025
                    // $durasi = $value->shift;
                    $durasi = $value->shift_pengujian;
                    $durasi = explode("-", $durasi);
                    $durasi = $durasi[1];
                    $nilai_array[$key] = str_replace('"', "", $durasi);
                }

                if (in_array($request->shift, $nilai_array)) {
                    return response()->json([
                        'message' => 'Pengambilan Shift ' . $request->shift . ' sudah ada !'
                    ], 401);
                }

                $shift_peng = $request->kateg_uji . '-' . $request->shift;

                $a = count($request->kerIn);
                $nilai_peng = array();
                for ($i = 0; $i < $a; $i++) {
                    $no = $i + 1;
                    $nilai_peng['Data-' . $no] = [
                        'tac_in' => $request->kerIn[$i],
                        'tac_out' => $request->kerOut[$i],
                        'tgc_in' => $request->globeIn[$i],
                        'tgc_out' => $request->globeOut[$i],
                        'rh_in' => $request->kelemIn[$i],
                        'rh_out' => $request->kelemOut[$i],
                        'wbtgc_in' => $request->buldglobeIn[$i],
                        'wbtgc_out' => $request->buldglobeOut[$i],
                        'wb_in' => $request->wbIn[$i],
                        'wb_out' => $request->wbOut[$i],
                    ];
                }

                $data = new DataLapanganIklimPanas;
                $data->no_sampel                 = strtoupper(trim($request->no_sample));
                if ($request->keterangan_4 != '') $data->keterangan            = $request->keterangan_4;
                if ($request->keterangan_2 != '') $data->keterangan_2            = $request->keterangan_2;
                // if ($request->posisi != '') $data->titik_koordinat             = $request->posisi;
                $data->titik_koordinat = $request->posisi ?? '-';
                if ($request->lat != '') $data->latitude                            = $request->lat;
                if ($request->longi != '') $data->longitude                        = $request->longi;
                if ($request->categori != '') $data->kategori_3                  = $request->categori;
                if ($request->lok != '') $data->lokasi                         = $request->lok;
                if ($request->sumber != '') $data->sumber_panas                      = $request->sumber;
                if ($request->jarak != '') $data->jarak_sumber_panas                        = $request->jarak;
                if ($request->paparan != '') $data->akumulasi_waktu_paparan                    = $request->paparan;
                if ($request->kerja != '') $data->waktu_kerja                        = $request->kerja;
                if ($request->mulai != '') $data->jam_awal_pengukuran                        = $request->mulai;
                if ($request->kateg_uji != '') $data->kategori_pengujian                  = $request->kateg_uji;
                $data->shift_pengujian = $shift_peng;
                $data->pengukuran = json_encode($nilai_peng);
                if ($request->cuaca != '') $data->cuaca                       = $request->cuaca;
                if ($request->pakaian != '') $data->pakaian_yang_digunakan                   = $request->pakaian;
                if ($request->matahari != '') $data->terpapar_panas_matahari                 = $request->matahari;
                if ($request->tipe_alat != '') $data->tipe_alat                = $request->tipe_alat;
                if ($request->selesai != '') $data->jam_akhir_pengukuran                      = $request->selesai;
                if ($request->aktifitas != '') $data->aktifitas                      = $request->aktifitas;
                // $data->is_rejected = false;
                if ($request->permis != '') $data->permission                      = $request->permis;
                if ($request->foto_lok != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lok, 1, $this->user_id);
                if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                $data->created_by                     = $this->karyawan;
                $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // Update Order Detail
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                $nama = $this->karyawan;
                $this->resultx = "Data Sampling IKLIM PANAS Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

                DB::commit();
                return response()->json([
                    'message' => $this->resultx
                ], 200);
        }catch(Exception $e){
            DB::rollback();
            return response()->json([
                'message' => $e.getMessage(),
                'code' => $e.getCode(),
                'line' => $e.getLine()
            ]);
        }
        
    }

    public function indexIklimPanas(Request $request)
    {
        $data = DataLapanganIklimPanas::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approveIklimPanas(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganIklimPanas::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Iklim Panas dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function detailIklimPanas(Request $request)
    {
        $data = DataLapanganIklimPanas::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Getaran success';

        if (isset($request->id) || $request->id != '') {
            return response()->json([
                'id'             => $data->id,
                'no_sample'      => $data->no_sampel,
                'no_order'       => $data->detail->no_order,
                'categori'       => explode('-', $data->detail->kategori_3)[1],
                'sampler'        => $data->created_by,
                'corp'           => $data->detail->nama_perusahaan,
                'keterangan'     => $data->keterangan,
                'keterangan_2'     => $data->keterangan_2,
                'lat'            => $data->latitude,
                'long'           => $data->longitude,

                'kateg_i'        => $data->kateg_i,
                'lokasi'         => $data->lokasi,
                'sumber'         => $data->sumber_panas,
                'jarak'          => $data->jarak_sumber_panas,
                'paparan'        => $data->akumulasi_waktu_paparan,
                'kerja'          => $data->waktu_kerja,
                'mulai'          => $data->jam_awal_pengukuran,
                'shift'          => $data->shift_pengujian,
                'tac_in'         => $data->tac_in,
                'tac_out'        => $data->tac_out,
                'tgc_in'         => $data->tgc_in,
                'tgc_out'        => $data->tgc_out,
                'wbtgc_in'       => $data->wbtgc_in,
                'wbtgc_out'      => $data->wbtgc_out,
                'rh_in'          => $data->rh_in,
                'rh_out'         => $data->rh_out,
                'ventilasi'      => $data->ventilasi,
                'akhir'          => $data->jam_akhir_pengukuran,
                'pengukuran'          => $data->pengukuran,
                'tipe_alat'          => $data->tipe_alat,
                'aktifitas'          => $data->aktifitas,

                'tikoor'         => $data->titik_koordinat,
                'foto_lok'       => $data->foto_lokasi_sampel,
                'foto_lain'      => $data->foto_lain,
                'coor'           => $data->titik_koordinat,
                'status'         => '200'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Data tidak ditemukan..'
            ], 401);
        }
    }

    public function deleteIklimPanas(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganIklimPanas::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL IKLIM PANAS dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
                'cat' => 4
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // IKLIM DINGIN
    public function addIklimDingin(Request $request)
    {

        DB::beginTransaction();
        try {
            if ($request->mulai == '') {
                return response()->json([
                    'message' => 'Jam mulai tidak boleh kosong .!'
                ], 401);
            }
            if ($request->selesai == '') {
                return response()->json([
                    'message' => 'Jam selesai tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lok == '') {
                return response()->json([
                    'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lain == '') {
                return response()->json([
                    'message' => 'Foto lain-lain tidak boleh kosong .!'
                ], 401);
            }

            $nilai_array = [];
            $cek_nil = DataLapanganIklimDingin::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
            foreach ($cek_nil as $key => $value) {
                $durasi = $value->shift_pengambilan;
                $durasi = explode("-", $durasi);
                $durasi = $durasi[1];
                $nilai_array[$key] = str_replace('"', "", $durasi);
            }

            if (in_array($request->shift, $nilai_array)) {
                return response()->json([
                    'message' => 'Pengambilan Shift ' . $request->shift . ' sudah ada !'
                ], 401);
            }

            $shift_peng = $request->kateg_uji . '-' . $request->shift;

            $a = count($request->kerIn);
            $nilai_peng = array();
            for ($i = 0; $i < $a; $i++) {
                $no = $i + 1;
                $nilai_peng['Data-' . $no] = [
                    'suhu_kering' => $request->kerIn[$i],
                    'kecepatan_angin' => $request->ventilasi[$i]
                ];
            }
            $data = new DataLapanganIklimDingin();
            $data->no_sampel                 = strtoupper(trim($request->no_sample));
            if ($request->keterangan_4 != '') $data->keterangan            = $request->keterangan_4;
            if ($request->keterangan_2 != '') $data->keterangan_2          = $request->keterangan_2;
            // if ($request->posisi != '') $data->titik_koordinat             = $request->posisi;
            $data->titik_koordinat = $request->posisi ?? '-';
            if ($request->lat != '') $data->latitude                            = $request->lat;
            if ($request->longi != '') $data->longitude                        = $request->longi;
            if ($request->id_kat != '') $data->kategori_3                  = $request->id_kat;
            if ($request->lok != '') $data->lokasi                         = $request->lok;
            if ($request->sumber != '') $data->sumber_dingin                      = $request->sumber;
            if ($request->jarak != '') $data->jarak_sumber_dingin                        = $request->jarak;
            if ($request->paparan != '') $data->akumulasi_waktu_paparan                    = $request->paparan;
            if ($request->kerja != '') $data->waktu_kerja                        = $request->kerja;
            if ($request->mulai != '') $data->jam_awal_pengukuran                        = $request->mulai;
            if ($request->apd != '') $data->apd_khusus                        = $request->apd;
            if ($request->tipe_alat != '') $data->tipe_alat                            = $request->tipe_alat;
            if ($request->aktifitas != '') $data->aktifitas                = $request->aktifitas;
            if ($request->aktifitas_kerja != '') $data->aktifitas_kerja                = $request->aktifitas_kerja;
            if ($request->kateg_uji != '') $data->kategori_pengujian                  = $request->kateg_uji;
            $data->shift_pengambilan = $shift_peng;
            $data->pengukuran = json_encode($nilai_peng);
            if ($request->selesai != '') $data->jam_akhir_pengujian                      = $request->selesai;
            // $data->is_rejected = false;
            if ($request->permis != '') $data->permission                      = $request->permis;
            if ($request->foto_lok != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lok, 1, $this->user_id);
            if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
            $data->created_by                     = $this->karyawan;
            $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();
    

            $update = DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $nama = $this->karyawan;
            $this->resultx = "Data Sampling IKLIM DINGIN Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

            // if($this->pin!=null){
            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $this->resultx);
            // }

            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
        }catch (\Exception $e) {
            DB::rollback();
            return response()->json([
               'message' => $e.getMessage(),
               'line' => $e->getLine(),
               'code' => $e->getCode()
            ]);
        }
    }

    public function indexIklimDingin(Request $request)
    {
        $data = DataLapanganIklimDingin::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approveIklimDingin(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganIklimDingin::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Iklim Dingin dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function detailIklimDingin(Request $request)
    {
        $data = DataLapanganIklimDingin::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Getaran success';

        if (isset($request->id) || $request->id != '') {
            return response()->json([
                'id'             => $data->id,
                'no_sample'      => $data->no_sampel,
                'no_order'       => $data->detail->no_order,
                'categori'       => explode('-', $data->detail->kategori_3)[1],
                'sampler'        => $data->created_by,
                'corp'           => $data->detail->nama_perusahaan,
                'keterangan'     => $data->keterangan,
                'keterangan_2'   => $data->keterangan_2,
                'lat'            => $data->latitude,
                'long'           => $data->longitude,

                'kateg_i'        => $data->kategori_pengujian,
                'apd'            => $data->apd_khusus,
                'aktifitas'      => $data->aktifitas,
                'lokasi'         => $data->lokasi,
                'sumber'         => $data->sumber_dingin,
                'jarak'          => $data->jarak_sumber_dingin,
                'paparan'        => $data->akumulasi_waktu_paparan,
                'kerja'          => $data->waktu_kerja,
                'mulai'          => $data->jam_awal_pengukuran,
                'shift'          => $data->shift_pengambilan,
                'tac_in'         => $data->tac_in,
                'tac_out'        => $data->tac_out,
                'ventilasi'      => $data->ventilasi,
                'akhir'          => $data->jam_akhir_pengujian,
                'pengukuran'     => $data->pengukuran,
                'tipe_alat'      => $data->tipe_alat,
                'aktifitas_kerja' => $data->aktifitas_kerja,

                'tikoor'         => $data->titik_koordinat,
                'foto_lok'       => $data->foto_lokasi_sampel,
                'foto_lain'      => $data->foto_lain,
                'coor'           => $data->titik_koordinat,
                'status'         => '200'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Data tidak ditemukan..'
            ], 401);
        }
    }

    public function deleteIklimDingin(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganIklimDingin::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL IKLIM Dingin dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
                'cat' => 4
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // DEBU PERSONAL
    public function addDebuPersonal(Request $request)
    {
        DB::beginTransaction();
        try {
            $nilai_array = [];
            $cek_nil = DataLapanganDebuPersonal::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
            foreach ($cek_nil as $key => $value) {
                $durasi = $value->shift;
                $durasi = explode("-", $durasi);
                $durasi = $durasi[1];
                $nilai_array[$key] = str_replace('"', "", $durasi);
            }

            if (in_array($request->shift, $nilai_array)) {
                return response()->json([
                    'message' => 'Pengambilan Shift ' . $request->shift . ' sudah ada !'
                ], 401);
            }

            $shift_peng = $request->kateg_uji . '-' . $request->shift;

            $data = new DataLapanganDebuPersonal;
            $data->no_sampel = strtoupper(trim($request->no_sample));
            if ($request->keterangan_4 != '')
                $data->keterangan = $request->keterangan_4;
            if ($request->keterangan_2 != '')
                $data->keterangan_2 = $request->keterangan_2;
            // if ($request->posisi != '')
            //     $data->titik_koordinat = $request->posisi;
            $data->titik_koordinat = $request->posisi ?? '-';
            if ($request->lat != '')
                $data->latitude = $request->lat;
            if ($request->longitude != '')
                $data->longi = $request->longi;
            if ($request->lok_submit != '')
                $data->lokasi_submit = $request->lok_submit;

            if ($request->categori != '')
                $data->kategori_3 = $request->categori;
            if ($request->nama_pekerja != '') $data->nama_pekerja = $request->nama_pekerja;
            if ($request->divisi != '') $data->divisi = $request->divisi;
            if ($request->suhu != '') $data->suhu = $request->suhu;
            if ($request->kelem != '') $data->kelembaban = $request->kelem;
            if ($request->tekU != '') $data->tekanan_udara = $request->tekU;
            if ($request->aktivitas != '') $data->aktivitas = $request->aktivitas;
            if ($request->apd != '') $data->apd = $request->apd;
            if ($request->jam_mulai != '') $data->jam_mulai = $request->jam_mulai;
            if ($request->jam_pengambilan != '') $data->jam_pengambilan = $request->jam_pengambilan;
            if ($request->flow != '') $data->flow = $request->flow;
            if ($request->jam_selesai != '') $data->jam_selesai = $request->jam_selesai;
            if ($request->total_waktu != '') $data->total_waktu = $request->total_waktu;
            // $data->is_rejected = false;
            $data->shift = $shift_peng;
            if ($request->permis != '')
                $data->permission = $request->permis;
            if ($request->foto_lok != '')
                $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
            if ($request->foto_sampl != '')
                $data->foto_alat = self::convertImg($request->foto_sampl, 2, $this->user_id);
            if ($request->foto_lain != '')
                $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // Update Order Detail
            DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $nama = $this->karyawan;
            $this->resultx = "Data Sampling FDL Debu Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

            // if ($this->pin != null) {
            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $this->resultx);
            // }

            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
               'message' => $e.getMessage(),
               'line' => $e->getLine(),
               'code' => $e->getCode()
            ]);
        }
    }

    public function indexDebuPersonal(Request $request)
    {
        $data = DataLapanganDebuPersonal::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approveDebuPersonal(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganDebuPersonal::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $data->is_approve = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Debu Personal dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function detailDebuPersonal(Request $request)
    {
        $data = DataLapanganDebuPersonal::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Debu Personal success';

        if (isset($request->id) || $request->id != '') {
            return response()->json([
                'id'             => $data->id,
                'no_sample'      => $data->no_sampel,
                'no_order'       => $data->detail->no_order,
                'categori'       => explode('-', $data->detail->kategori_3)[1],
                'sampler'        => $data->created_by,
                'corp'           => $data->detail->nama_perusahaan,
                'keterangan'     => $data->keterangan,
                'keterangan_2'   => $data->keterangan_2,
                'lat'            => $data->latitude,
                'long'           => $data->longitude,

                'nama_pekerja'   => $data->nama_pekerja,
                'divisi'         => $data->divisi,
                'suhu'           => $data->suhu,
                'kelem'          => $data->kelembaban,
                'tekanan_u'      => $data->tekanan_udara,
                'shift'          => $data->shift,
                'aktivitas'      => $data->aktivitas,
                'apd'            => $data->apd,
                'jam_mulai'      => $data->jam_mulai,
                'jam_pengambilan' => $data->jam_pengambilan,
                'flow'           => $data->flow,
                'jam_selesai'    => $data->jam_selesai,
                'total_waktu'    => $data->total_waktu,

                'tikoor'         => $data->titik_koordinat,
                'foto_lok'       => $data->foto_lokasi_sampel,
                'foto_lain'      => $data->foto_lain,
                'foto_alat'      => $data->foto_alat,
                'coor'           => $data->titik_koordinat,
                'status'         => '200'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Data tidak ditemukan..'
            ], 401);
        }
    }

    public function deleteDebuPersonal(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganDebuPersonal::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_alat = public_path() . '/dokumentasi/sampling/' . $data->foto_alat;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_alat)) {
                unlink($foto_alat);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Debu Personal dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
                'cat' => 4
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // EMISI CEROBONG
    // public function addEmisiCerobong(Request $request)
    // {
    //         $emisi = DataLapanganEmisiCerobong::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

    //         if ($request->tipe == '1') {
    //             DB::beginTransaction();
    //             try {
    //                 if ($request->waktu_pengambilan == '') {
    //                     return response()->json([
    //                         'message' => 'Jam pengambilan tidak boleh kosong!'
    //                     ], 401);
    //                 }

    //                 if ($emisi) {
    //                     $data = DataLapanganEmisiCerobong::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
    //                 } else {
    //                     $data = new DataLapanganEmisiCerobong();
    //                 }

    //                 $data->no_sampel                                                = strtoupper(trim($request->no_sample));
    //                 if ($request->id_kat != '') $data->kategori_3                   = $request->id_kat;

    //                 if ($request->keterangan != '') $data->keterangan               = $request->keterangan;
    //                 if ($request->keterangan_2 != '') $data->keterangan_2           = $request->keterangan_2;
    //                 if ($request->sumber != '') $data->sumber_emisi                       = $request->sumber;
    //                 if ($request->merk != '') $data->merk                           = $request->merk;
    //                 if ($request->bakar != '') $data->bahan_bakar                         = $request->bakar;
    //                 if ($request->cuaca != '') $data->cuaca                         = $request->cuaca;
    //                 if ($request->kec != '') $data->kecepatan_angin                       = $request->kec;
    //                 if ($request->arah != '') $data->arah_pengamat                           = $request->arah;
    //                 if ($request->diameter != '') $data->diameter_cerobong                   = $request->diameter;
    //                 if ($request->durasiOp != '') $data->durasi_operasi                   = $request->durasiOp . ' ' . $request->satDur;
    //                 if ($request->filtrasi != '') $data->proses_filtrasi                   = $request->filtrasi;
    //                 if ($request->waktu_pengambilan != '') $data->waktu_pengambilan = $request->waktu_pengambilan;
    //                 if ($request->posisi != '') $data->titik_koordinat              = $request->posisi;
    //                 if ($request->lat != '') $data->latitude                             = $request->lat;
    //                 if ($request->longi != '') $data->longitude                         = $request->longi;
    //                 if ($request->suhu != '') $data->suhu                           = $request->suhu;
    //                 if ($request->kelem != '') $data->kelembapan                         = $request->kelem;
    //                 if ($request->tekU != '') $data->tekanan_udara                      = $request->tekU;
    //                 if ($request->kapasitas != '') $data->kapasitas                 = $request->kapasitas;
    //                 if ($request->metode != '') $data->metode                       = $request->metode;
    //                 $data->tipe                                             = 1;

    //                 $partikulat = array();
    //                 if ($request->volumep != '') {
    //                     $vol = $request->volumep;
    //                 } else {
    //                     $vol = '-';
    //                 }
    //                 if ($request->tekanan_dryp != '') {
    //                     $tekanp = $request->tekanan_dryp;
    //                 } else {
    //                     $tekanp = '-';
    //                 }

    //                 if ($request->awalp != null) {
    //                     $partikulat[] = 'Flow Awal : ' . $request->awalp . '; Flow Akhir : ' . $request->akhirp . '; Durasi : ' . $request->durasip . '; Volume : ' . $vol . '; Tekanan : ' . $tekanp;
    //                 }

    //                 if ($request->awalp != '') $data->partikulat                    = json_encode($partikulat);

    //                 if ($request->param2 != '') {
    //                     foreach ($request->param2 as $k => $v) {
    //                         $paramVal = explode(';', $v)[1];
    //                         if ($paramVal == 'HCl') {
    //                             $hcl = array();
    //                             if ($request->volume[$k] != '') {
    //                                 $volu = $request->volume[$k];
    //                             } else {
    //                                 $volu = '-';
    //                             }
    //                             if ($request->tekanan_dry[$k] != '') {
    //                                 $tekan = $request->tekanan_dry[$k];
    //                             } else {
    //                                 $tekan = '-';
    //                             }
    //                             if ($request->awal[$k] != null) {
    //                                 $hcl[] = 'Flow Awal : ' . $request->awal[$k] . '; Flow Akhir : ' . $request->akhir[$k] . '; Durasi : ' . $request->durasiflow[$k] . '; Volume : ' . $volu . '; Tekanan : ' . $tekan;
    //                             }
    //                             $data->HCI = json_encode($hcl);
    //                         }

    //                         if ($paramVal == 'H2S') {
    //                             $h2s = array();
    //                             if ($request->volume[$k] != '') {
    //                                 $volu = $request->volume[$k];
    //                             } else {
    //                                 $volu = '-';
    //                             }
    //                             if ($request->tekanan_dry[$k] != '') {
    //                                 $tekan = $request->tekanan_dry[$k];
    //                             } else {
    //                                 $tekan = '-';
    //                             }

    //                             if ($request->awal[$k] != null) {
    //                                 $h2s[] = 'Flow Awal : ' . $request->awal[$k] . '; Flow Akhir : ' . $request->akhir[$k] . '; Durasi : ' . $request->durasiflow[$k] . '; Volume : ' . $volu . '; Tekanan : ' . $tekan;
    //                             }
    //                             $data->H2S = json_encode($h2s);
    //                         }

    //                         if ($paramVal == 'NH3') {
    //                             $nh3 = array();
    //                             if ($request->volume[$k] != '') {
    //                                 $volu = $request->volume[$k];
    //                             } else {
    //                                 $volu = '-';
    //                             }
    //                             if ($request->tekanan_dry[$k] != '') {
    //                                 $tekan = $request->tekanan_dry[$k];
    //                             } else {
    //                                 $tekan = '-';
    //                             }
    //                             if ($request->awal[$k] != null) {
    //                                 $nh3[] = 'Flow Awal : ' . $request->awal[$k] . '; Flow Akhir : ' . $request->akhir[$k] . '; Durasi : ' . $request->durasiflow[$k] . '; Volume : ' . $volu . '; Tekanan : ' . $tekan;
    //                             }
    //                             $data->NH3 = json_encode($nh3);
    //                         }

    //                         if ($paramVal == 'Cl2') {
    //                             $cl2 = array();
    //                             if ($request->volume[$k] != '') {
    //                                 $volu = $request->volume[$k];
    //                             } else {
    //                                 $volu = '-';
    //                             }
    //                             if ($request->tekanan_dry[$k] != '') {
    //                                 $tekan = $request->tekanan_dry[$k];
    //                             } else {
    //                                 $tekan = '-';
    //                             }
    //                             if ($request->awal[$k] != null) {
    //                                 $cl2[] = 'Flow Awal : ' . $request->awal[$k] . '; Flow Akhir : ' . $request->akhir[$k] . '; Durasi : ' . $request->durasiflow[$k] . '; Volume : ' . $volu . '; Tekanan : ' . $tekan;
    //                             }
    //                             $data->CI2 = json_encode($cl2);
    //                         }

    //                         if ($paramVal == 'HF') {
    //                             $hf = array();
    //                             if ($request->volume[$k] != '') {
    //                                 $volu = $request->volume[$k];
    //                             } else {
    //                                 $volu = '-';
    //                             }
    //                             if ($request->tekanan_dry[$k] != '') {
    //                                 $tekan = $request->tekanan_dry[$k];
    //                             } else {
    //                                 $tekan = '-';
    //                             }
    //                             if ($request->awal[$k] != null) {
    //                                 $hf[] = 'Flow Awal : ' . $request->awal[$k] . '; Flow Akhir : ' . $request->akhir[$k] . '; Durasi : ' . $request->durasiflow[$k] . '; Volume : ' . $volu . '; Tekanan : ' . $tekan;
    //                             }
    //                             $data->HF = json_encode($hf);
    //                         }
    //                     }
    //                 }

    //                 if ($request->permis != '') $data->permission_1                       = $request->permis;
    //                 if ($request->foto_lok != '') $data->foto_lokasi_sampel         = self::convertImg($request->foto_lok, 1, $this->user_id);
    //                 if ($request->foto_sampl != '') $data->foto_kondisi_sample      = self::convertImg($request->foto_sampl, 2, $this->user_id);
    //                 if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
    //                 $data->created_by                                                   = $this->karyawan;
    //                 $data->created_at                                                  = Carbon::now()->format('Y-m-d H:i:s');
    //                 $data->save();

    //                 $update = DB::table('order_detail')
    //                     ->where('no_sampel', strtoupper(trim($request->no_sample)))
    //                     // ->orwhere('koding_sampling', strtoupper(trim($request->no_sample)))
    //                     ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

    //                 $nama = $this->karyawan;
    //                 $this->resultx = "Data Sampling EMISI CEROBONG Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

    //                 // if($this->pin!=null){

    //                 //     $telegram = new Telegram();
    //                 //     $telegram->send($this->pin, $this->resultx);
    //                 // }
    //                 DB::commit();
    //                 return response()->json([
    //                     'message' => $this->resultx
    //                 ], 200);
    //             }catch (Exception $e) {
    //                 DB::rollBack();
    //                 return response()->json([
    //                    'message' => $e.getMessage(),
    //                    'line' => $e.getLine(),
    //                    'code' => $e.getCode()
    //                 ]);
    //             }
    //         } else if ($request->tipe == 2) {
    //             DB::beginTransaction();
    //             try {
    //                 if ($emisi) {
    //                     $data = DataLapanganEmisiCerobong::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
    //                 } else {
    //                     $data = new DataLapanganEmisiCerobong();
    //                 }

    //                 $data->no_sampel                                                = strtoupper(trim($request->no_sample));
    //                 if ($request->id_kat != '') $data->kategori_3                   = $request->id_kat;
    //                 $data->tipe                                             = 1;

    //                 if ($request->param != '') {
    //                     foreach ($request->param as $ke => $ve) {
    //                         if ($ve == 'O2') {
    //                             $data->O2 = $request->datPar[$ke];
    //                         }
    //                         if ($ve == 'CO') {
    //                             $data->CO = $request->datPar[$ke];
    //                         }
    //                         if ($ve == 'CO2') {
    //                             $data->CO2 = $request->datPar[$ke];
    //                         }
    //                         if ($ve == 'NO') {
    //                             $data->NO = $request->datPar[$ke];
    //                         }
    //                         if ($ve == 'NO2') {
    //                             $data->NO2 = $request->datPar[$ke];
    //                         }
    //                         if ($ve == 'SO2') {
    //                             $data->SO2 = $request->datPar[$ke];
    //                         }
    //                         if ($ve == 'T Flue/ T Stak') {
    //                             $data->T_Flue = $request->datPar[$ke];
    //                         }
    //                         if ($ve == 'NOx') {
    //                             $data->NOx = $request->datPar[$ke];
    //                         }
    //                     }
    //                 }

    //                 $velocity = array();
    //                 if ($request->dat1 != null) {
    //                     $velocity[] = 'Data-1 : ' . $request->dat1 . '; Data-2 : ' . $request->dat2 . '; Data-3 : ' . $request->dat3;
    //                 }
    //                 $data->velocity                = json_encode($velocity);

    //                 if ($request->permis != '') $data->permission_2                       = $request->permis;
    //                 if ($request->waktu_selesai != '') $data->waktu_selesai           = $request->waktu_selesai;
    //                 if ($request->foto_struk != '') $data->foto_struk     = self::convertImg($request->foto_struk, 4, $this->user_id);
    //                 if ($request->foto_lain2 != '') $data->foto_lain2     = self::convertImg($request->foto_lain2, 5, $this->user_id);
    //                 $data->created_by                                                   = $this->karyawan;
    //                 $data->created_at                                                  = Carbon::now()->format('Y-m-d H:i:s');
    //                 $data->save();
                    
    //                 $update = DB::table('order_detail')
    //                 ->where('no_sampel', strtoupper(trim($request->no_sample)))
    //                 // ->orwhere('koding_sampling', strtoupper(trim($request->no_sample)))
    //                 ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

    //                 DB::commit();
    //                 $this->resultx = "Data Sampling EMISI CEROBONG Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan";
    //                 return response()->json([
    //                     'message' => $this->resultx
    //                 ], 200);
    //             }catch (Exception $e) {
    //                 DB::rollBack();
    //                 return response()->json([
    //                    'message' => $e.getMessage(),
    //                    'line' => $e.getLine(),
    //                    'code' => $e.getCode()
    //                 ]);
    //             }
    //         } else if ($request->tipe == 3) {
    //             DB::beginTransaction();
    //             try{
    //                 if ($emisi) {
    //                     $data = DataLapanganEmisiCerobong::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
    //                 } else {
    //                     $data = new DataLapanganEmisiCerobong;
    //                 }

    //                 $data->no_sampel                                    = strtoupper(trim($request->no_sample));
    //                 if ($request->id_kat != '') $data->kategori_3       = $request->id_kat;

    //                 if ($request->titik_pengamatan != '') $data->titik_pengamatan   = $request->titik_pengamatan;
    //                 if ($request->tinggi_tanah != '') $data->tinggi_tanah           = $request->tinggi_tanah;
    //                 if ($request->tinggi_relatif != '') $data->tinggi_relatif       = $request->tinggi_relatif;
    //                 if ($request->status_uap != '') $data->status_uap       = $request->status_uap;
    //                 if ($request->tekanan_opas != '') $data->tekanan_udara_opasitas       = $request->tekanan_opas;
    //                 if ($request->suhu_bola != '') $data->suhu_bola       = $request->suhu_bola;
    //                 if ($request->cuaca != '') $data->cuaca = $request->cuaca;
    //                 if ($request->kelem_opas != '') $data->kelembapan_opasitas = $request->kelem_opas;
    //                 if ($request->suhu_ambien != '') $data->suhu_ambien = $request->suhu_ambien;
    //                 if ($request->arah_utara != '') $data->arah_utara             = $request->arah_utara;
    //                 if ($request->noteket != '') $data->info_tambahan             = $request->noteket;
    //                 if ($request->status_uap != '') $data->status_uap             = $request->status_uap;
    //                 if ($request->status_konstan != '') $data->status_konstan     = $request->status_konstan;
    //                 $data->tipe                                             = 1;

    //                 $jarak_pengamat = array();
    //                 if ($request->jarAwal != null && $request->jarAkhir != null) {
    //                     $jarak_pengamat[] = 'Jarak Awal : ' . $request->jarAwal . '; Jarak Akhir : ' . $request->jarAkhir;
    //                 }
    //                 $data->jarak_pengamat                               = json_encode($jarak_pengamat);

    //                 $arah_pengamat = array();
    //                 if ($request->arAwal != null && $request->arAkhir != null) {
    //                     $arah_pengamat[] = 'Arah Awal : ' . $request->arAwal . '; Arah Akhir : ' . $request->arAkhir;
    //                 }
    //                 $data->arah_pengamat_opasitas                                = json_encode($arah_pengamat);

    //                 $deskripsi_emisi = array();
    //                 if ($request->deskripAwal != null && $request->deskripAkhir != null) {
    //                     $deskripsi_emisi[] = 'Deskripsi Awal : ' . $request->deskripAwal . '; Deskripsi Akhir : ' . $request->deskripAkhir;
    //                 }
    //                 $data->deskripsi_emisi                                = json_encode($deskripsi_emisi);

    //                 $warna_emisi = array();
    //                 if ($request->warAwal != null && $request->warAkhir != null) {
    //                     $warna_emisi[] = 'Warna Awal : ' . $request->warAwal . '; Warna Akhir : ' . $request->warAkhir;
    //                 }
    //                 $data->warna_emisi                                  = json_encode($warna_emisi);

    //                 $titik_penentuan = array();
    //                 if ($request->titikPenentuanAwal != null && $request->titikPenentuanAkhir != null) {
    //                     $titik_penentuan[] = 'Titik Penentuan Awal : ' . $request->titikPenentuanAwal . '; Titik Penentuan Akhir : ' . $request->titikPenentuanAkhir;
    //                 }
    //                 $data->titik_penentuan                                  = json_encode($titik_penentuan);

    //                 $deskripsi_latar = array();
    //                 if ($request->deskripLatarAwal != null && $request->deskripLatarAkhir != null) {
    //                     $deskripsi_latar[] = 'Deskripsi Latar Awal : ' . $request->deskripLatarAwal . '; Deskripsi Latar Akhir : ' . $request->deskripLatarAkhir;
    //                 }
    //                 $data->deskripsi_latar                                  = json_encode($deskripsi_latar);

    //                 $warna_latar = array();
    //                 if ($request->warlaAwal != null && $request->warlaAkhir != null) {
    //                     $warna_latar[] = 'Warna Latar Awal : ' . $request->warlaAwal . '; Warna Latar Akhir : ' . $request->warlaAkhir;
    //                 }
    //                 $data->warna_latar                                  = json_encode($warna_latar);

    //                 $kecepatan = array();
    //                 if ($request->kecAwal != null && $request->kecAkhir != null) {
    //                     $kecepatan[] = 'Kecepatan Awal : ' . $request->kecAwal . '; Kecepatan Akhir : ' . $request->kecAkhir;
    //                 }
    //                 $data->kecepatan_angin                                  = json_encode($kecepatan);

    //                 $arah_angin = array();
    //                 if ($request->arahAnginAwal != null && $request->arahAnginAkhir != null) {
    //                     $arah_angin[] = 'Arah Angin Awal : ' . $request->arahAnginAwal . '; Arah Angin Akhir : ' . $request->arahAnginAkhir;
    //                 }
    //                 $data->arah_pengamat                                  = json_encode($arah_angin);

    //                 $waktu_opas = array();
    //                 if ($request->waktuAwal != null && $request->waktuAkhir != null) {
    //                     $waktu_opas[] = 'Waktu Awal : ' . $request->waktuAwal . '; Waktu Akhir : ' . $request->waktuAkhir;
    //                 }
    //                 $data->waktu_opasitas                                  = json_encode($waktu_opas);

    //                 // if ($request->nilOpas != '') $data->nilai_opasitas                  = json_encode($request->nilOpas);
    //                 if (count($request->nilOpas) > 0 ) $data->nilai_opasitas                  = json_encode($request->nilOpas);
    //                 if ($request->foto_asap != '') $data->foto_asap     = self::convertImg($request->foto_asap, 6, $this->user_id);
    //                 if ($request->foto_lain3 != '') $data->foto_lain3     = self::convertImg($request->foto_lain3, 7, $this->user_id);

    //                 $data->permission_3                     = (empty($request->permis)) ? 1 : $request->permis;
    //                 $data->created_by                                                 = $this->karyawan;
    //                 $data->created_at                                                = Carbon::now()->format('Y-m-d H:i:s');
    //                 // dd($data);   
    //                 $data->save();

    //                 $update = DB::table('order_detail')
    //                     ->where('no_sampel', strtoupper(trim($request->no_sample)))
    //                     // ->orwhere('koding_sampling', strtoupper(trim($request->no_sample)))
    //                     ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

    //                 $this->resultx = "Data Sampling EMISI CEROBONG Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan";

    //                 // if ($this->pin != null) {

    //                 //     $telegram = new Telegram();
    //                 //     $telegram->send($this->pin, $this->resultx);
    //                 // }

    //                 DB::commit();
    //                 return response()->json([
    //                     'message' => $this->resultx
    //                 ], 200);
    //             }catch (\Exception $e) {
    //                 DB::rollBack();
    //                 return response()->json([
    //                    'message' => $e.getMessage(),
    //                    'line'    => $e->getLine(),
    //                    'code'    => $e->getCode()
    //                 ]);
    //             }
    //         }
        
    // }

    // 6/13/2025 - Goni

    public function addEmisiCerobong(Request $request)
    {
            $emisi = DataLapanganEmisiCerobong::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if ($request->tipe == '1') {
                DB::beginTransaction();
                try {
                    if ($request->waktu_pengambilan == '') {
                        return response()->json([
                            'message' => 'Jam pengambilan tidak boleh kosong!'
                        ], 401);
                    }

                    if ($emisi) {
                        $data = DataLapanganEmisiCerobong::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                    } else {
                        $data = new DataLapanganEmisiCerobong();
                    }

                    $data->no_sampel                                                = strtoupper(trim($request->no_sample));
                    if ($request->id_kat != '') $data->kategori_3                   = $request->id_kat;

                    if ($request->keterangan != '') $data->keterangan               = $request->keterangan;
                    if ($request->keterangan_2 != '') $data->keterangan_2           = $request->keterangan_2;
                    if ($request->sumber != '') $data->sumber_emisi                       = $request->sumber;
                    if ($request->merk != '') $data->merk                           = $request->merk;
                    if ($request->bakar != '') $data->bahan_bakar                         = $request->bakar;
                    if ($request->cuaca != '') $data->cuaca                         = $request->cuaca;
                    if ($request->kec != '') $data->kecepatan_angin                       = $request->kec;
                    if ($request->arah != '') $data->arah_pengamat                           = $request->arah;
                    if ($request->diameter != '') $data->diameter_cerobong                   = $request->diameter;
                    if ($request->durasiOp != '') $data->durasi_operasi                   = $request->durasiOp . ' ' . $request->satDur;
                    if ($request->filtrasi != '') $data->proses_filtrasi                   = $request->filtrasi;
                    if ($request->waktu_pengambilan != '') $data->waktu_pengambilan = $request->waktu_pengambilan;
                    // if ($request->posisi != '') $data->titik_koordinat              = $request->posisi;
                    $data->titik_koordinat = $request->posisi ?? '-';
                    if ($request->lat != '') $data->latitude                             = $request->lat;
                    if ($request->longi != '') $data->longitude                         = $request->longi;
                    if ($request->suhu != '') $data->suhu                           = $request->suhu;
                    if ($request->kelem != '') $data->kelembapan                         = $request->kelem;
                    if ($request->tekU != '') $data->tekanan_udara                      = $request->tekU;
                    if ($request->kapasitas != '') $data->kapasitas                 = $request->kapasitas;
                    if ($request->metode != '') $data->metode                       = $request->metode;
                    // $data->is_rejected = false;
                    $data->tipe                                             = 1;

                    $partikulat = [];

                    // Simpan
                    if (!empty($partikulat)) {
                        $data->partikulat = json_encode($partikulat);
                    }
                    if ($request->volumep != '') {
                        $vol = $request->volumep;
                    } else {
                        $vol = '-';
                    }
                    if ($request->tekanan_dryp != '') {
                        $tekananDry = $request->tekanan_dryp;
                    } else {
                        $tekananDry = '-';
                    }
                    // Data utama (non-debu)
                    if ($request->awalp !== null) {
                        $dataUtama = [
                            'flow_awal' => $request->awalp,
                            'flow_akhir' => $request->akhirp,
                            'durasi' => $request->durasip,
                            'volume' => $vol,
                            'tekanan' => $tekananDry,
                        ];

                        $partikulat[] = $dataUtama;
                    }

                    // Data debu (berulang)
                    $awal = $request->awal_debu ?? [];
                    $akhir = $request->akhir_debu ?? [];
                    $durasi = $request->durasi_debu ?? [];
                    $volume = $request->volume_debu ?? [];
                    $tekanan = $request->tekanan_dry_debu ?? [];
                    $jam = $request->jam_pengambilan ?? [];

                    foreach ($jam as $index => $jamItem) {
                        $jamStr = $jamItem[0] ?? '';
                        if (!empty($jamStr)) {
                            $debu = [
                                'jam_pengambilan' => $jamStr,
                                'flow_awal' => $awal[$index][0] ?? '0',
                                'flow_akhir' => $akhir[$index][0] ?? '0',
                                'durasi' => $durasi[$index][0] ?? '0',
                                'volume' => $volume[$index][0] ?? '0',
                                'tekanan' => $tekanan[$index][0] ?? '0',
                            ];
                            $partikulat[] = $debu;
                        }
                    }
                    if (!empty($partikulat)) {
                        $data->partikulat = json_encode($partikulat);
                    }

                    if ($request->param2 != '') {
                        foreach ($request->param2 as $k => $v) {
                            if ($v == 'HCl') {
                                $hcl = array();
                                if ($request->volume[$k] != '') {
                                    $volu = $request->volume[$k];
                                } else {
                                    $volu = '-';
                                }
                                if ($request->tekanan_dry[$k] != '') {
                                    $tekan = $request->tekanan_dry[$k];
                                } else {
                                    $tekan = '-';
                                }
                                if ($request->awal[$k] != null) {
                                    $hcl[] = 'Flow Awal : ' . $request->awal[$k] . '; Flow Akhir : ' . $request->akhir[$k] . '; Durasi : ' . $request->durasiflow[$k] . '; Volume : ' . $volu . '; Tekanan : ' . $tekan;
                                }
                                $data->HCI = json_encode($hcl);
                            }

                            if ($v == 'H2S') {
                                $h2s = array();
                                if ($request->volume[$k] != '') {
                                    $volu = $request->volume[$k];
                                } else {
                                    $volu = '-';
                                }
                                if ($request->tekanan_dry[$k] != '') {
                                    $tekan = $request->tekanan_dry[$k];
                                } else {
                                    $tekan = '-';
                                }

                                if ($request->awal[$k] != null) {
                                    $h2s[] = 'Flow Awal : ' . $request->awal[$k] . '; Flow Akhir : ' . $request->akhir[$k] . '; Durasi : ' . $request->durasiflow[$k] . '; Volume : ' . $volu . '; Tekanan : ' . $tekan;
                                }
                                $data->H2S = json_encode($h2s);
                            }

                            if ($v == 'NH3') {
                                $nh3 = array();
                                if ($request->volume[$k] != '') {
                                    $volu = $request->volume[$k];
                                } else {
                                    $volu = '-';
                                }
                                if ($request->tekanan_dry[$k] != '') {
                                    $tekan = $request->tekanan_dry[$k];
                                } else {
                                    $tekan = '-';
                                }
                                if ($request->awal[$k] != null) {
                                    $nh3[] = 'Flow Awal : ' . $request->awal[$k] . '; Flow Akhir : ' . $request->akhir[$k] . '; Durasi : ' . $request->durasiflow[$k] . '; Volume : ' . $volu . '; Tekanan : ' . $tekan;
                                }
                                $data->NH3 = json_encode($nh3);
                            }

                            if ($v == 'Cl2') {
                                $cl2 = array();
                                if ($request->volume[$k] != '') {
                                    $volu = $request->volume[$k];
                                } else {
                                    $volu = '-';
                                }
                                if ($request->tekanan_dry[$k] != '') {
                                    $tekan = $request->tekanan_dry[$k];
                                } else {
                                    $tekan = '-';
                                }
                                if ($request->awal[$k] != null) {
                                    $cl2[] = 'Flow Awal : ' . $request->awal[$k] . '; Flow Akhir : ' . $request->akhir[$k] . '; Durasi : ' . $request->durasiflow[$k] . '; Volume : ' . $volu . '; Tekanan : ' . $tekan;
                                }
                                $data->CI2 = json_encode($cl2);
                            }

                            if ($v == 'HF') {
                                $hf = array();
                                if ($request->volume[$k] != '') {
                                    $volu = $request->volume[$k];
                                } else {
                                    $volu = '-';
                                }
                                if ($request->tekanan_dry[$k] != '') {
                                    $tekan = $request->tekanan_dry[$k];
                                } else {
                                    $tekan = '-';
                                }
                                if ($request->awal[$k] != null) {
                                    $hf[] = 'Flow Awal : ' . $request->awal[$k] . '; Flow Akhir : ' . $request->akhir[$k] . '; Durasi : ' . $request->durasiflow[$k] . '; Volume : ' . $volu . '; Tekanan : ' . $tekan;
                                }
                                $data->HF = json_encode($hf);
                            }
                        }
                    }
                    if ($request->permis != '') $data->permission_1                       = $request->permis;
                    if ($request->foto_lok != '') $data->foto_lokasi_sampel         = self::convertImg($request->foto_lok, 1, $this->user_id);
                    if ($request->foto_sampl != '') $data->foto_kondisi_sample      = self::convertImg($request->foto_sampl, 2, $this->user_id);
                    if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    $data->created_by                                                   = $this->karyawan;
                    $data->created_at                                                  = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    // Update Order Detail
                    DB::table('order_detail')
                        ->where('no_sampel', strtoupper(trim($request->no_sample)))
                        ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                    $nama = $this->karyawan;
                    $this->resultx = "Data Sampling EMISI CEROBONG Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

                    // if($this->pin!=null){

                    //     $telegram = new Telegram();
                    //     $telegram->send($this->pin, $this->resultx);
                    // }
                    DB::commit();
                    return response()->json([
                        'message' => $this->resultx
                    ], 200);
                }catch (Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'message' => $e.getMessage(),
                        'line' => $e.getLine(),
                        'code' => $e.getCode()
                    ]);
                }
            } else if ($request->tipe == 2) {
                DB::beginTransaction();
                try {
                    if ($emisi) {
                        $data = DataLapanganEmisiCerobong::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                    } else {
                        $data = new DataLapanganEmisiCerobong();
                    }

                    $data->no_sampel                                                = strtoupper(trim($request->no_sample));
                    if ($request->id_kat != '') $data->kategori_3                   = $request->id_kat;
                    $data->tipe                                             = 1;

                    if ($request->param != '') {
                        $paramUtama = ['O2' => null, 'CO' => null,  'SO2' => null, 'NO2' => null];
                        $paramPengulangan = ['O2' => [], 'CO' => [], 'SO2' => [], 'NO2' => []];
                        $indexMap = ['O2' => 1, 'CO' => 1, 'SO2' => 1, 'NO2' => 1];

                        foreach ($request->param as $ke => $ve) {
                            $val = $request->datPar[$ke];

                            if ($ve === 'O2') {
                                $paramUtama['O2'] = $val;
                            } elseif ($ve === 'O2 (P)') {
                                $paramPengulangan['O2']["data-" . $indexMap['O2']++] = $val;
                            }

                            if ($ve === 'CO') {
                                $paramUtama['CO'] = $val;
                            } elseif ($ve === 'CO (P)') {
                                $paramPengulangan['CO']["data-" . $indexMap['CO']++] = $val;
                            }

                            if ($ve === 'SO2') {
                                $paramUtama['SO2'] = $val;
                            } elseif ($ve === 'SO2 (P)') {
                                $paramPengulangan['SO2']["data-" . $indexMap['SO2']++] = $val;
                            }

                            if ($ve === 'NO2') {
                                $paramUtama['NO2'] = $val;
                            } elseif ($ve === 'NO2-Nox (P)') {
                                $paramPengulangan['NO2']["data-" . $indexMap['NO2']++] = $val;
                            }

                            if ($ve === 'CO2') {
                                $data->CO2 = $val;
                            } 
                            if ($ve === 'T Flue/ T Stak') {
                                $data->T_Flue = $val;
                            } 

                            if ($ve === 'NO') {
                                $data->NO = $val;
                            }
                            
                            if ($ve === 'NOx') {
                                $data->NOx = $val;
                            }
                            
                            if ($ve === 'HC') {
                                $data->HC = $val ?? null;
                            }
                        }

                        // Gabungkan ke dalam objek data
                        // $data->O2 = ['utama' => $paramUtama['O2'], 'o2_p' => $paramPengulangan['O2']];
                        // $data->CO = ['utama' => $paramUtama['CO'], 'co_p' => $paramPengulangan['CO']];
                        // $data->SO2 = ['utama' => $paramUtama['SO2'], 'so2_p' => $paramPengulangan['SO2']];
                        // $data->NO2 = ['utama' => $paramUtama['NO2'], 'no2_nox_p' => $paramPengulangan['NO2']];
                        // O2
                        if (!empty($paramPengulangan['O2'])) {
                            $data->O2 = json_encode([
                                'utama' => $paramUtama['O2'],
                                'o2_p' => $paramPengulangan['O2']
                            ]);
                        } elseif (!is_null($paramUtama['O2'])) {
                            $data->O2 = $paramUtama['O2'];
                        }

                        // CO
                        if (!empty($paramPengulangan['CO'])) {
                            $data->CO = json_encode([
                                'utama' => $paramUtama['CO'],
                                'co_p' => $paramPengulangan['CO']
                            ]);
                        } elseif (!is_null($paramUtama['CO'])) {
                            $data->CO = $paramUtama['CO'];
                        }

                        // SO2
                        if (!empty($paramPengulangan['SO2'])) {
                            $data->SO2 = json_encode([
                                'utama' => $paramUtama['SO2'],
                                'so2_p' => $paramPengulangan['SO2']
                            ]);
                        } elseif (!is_null($paramUtama['SO2'])) {
                            $data->SO2 = $paramUtama['SO2'];
                        }

                        // NO2
                        if (!empty($paramPengulangan['NO2'])) {
                            $data->NO2 = json_encode([
                                'utama' => $paramUtama['NO2'],
                                'no2_nox_p' => $paramPengulangan['NO2']
                            ]);
                        } elseif (!is_null($paramUtama['NO2'])) {
                            $data->NO2 = $paramUtama['NO2'];
                        }
                    }
                    
                    $velocity = array();
                    if ($request->dat1 != null) {
                        $velocity[] = 'Data-1 : ' . $request->dat1 . '; Data-2 : ' . $request->dat2 . '; Data-3 : ' . $request->dat3;
                    }
                    $data->velocity                = json_encode($velocity);

                    if ($request->permis != '') $data->permission_2                       = $request->permis;
                    if ($request->waktu_selesai != '') $data->waktu_selesai           = $request->waktu_selesai;
                    if ($request->foto_struk != '') $data->foto_struk     = self::convertImg($request->foto_struk, 4, $this->user_id);
                    if ($request->foto_lain2 != '') $data->foto_lain2     = self::convertImg($request->foto_lain2, 5, $this->user_id);
                    $data->created_by                                                   = $this->karyawan;
                    $data->created_at                                                  = Carbon::now()->format('Y-m-d H:i:s');
                    // $data->is_rejected = false;
                    $data->save();
                    
                    // Update Order Detail
                    DB::table('order_detail')
                        ->where('no_sampel', strtoupper(trim($request->no_sample)))
                        ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                    DB::commit();
                    $this->resultx = "Data Sampling EMISI CEROBONG Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan";
                    return response()->json([
                        'message' => $this->resultx
                    ], 200);
                }catch (Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'message' => $e.getMessage(),
                        'line' => $e.getLine(),
                        'code' => $e.getCode()
                    ]);
                }
            } else if ($request->tipe == 3) {
                DB::beginTransaction();
                try{
                    if ($emisi) {
                        $data = DataLapanganEmisiCerobong::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                    } else {
                        $data = new DataLapanganEmisiCerobong;
                    }

                    $data->no_sampel                                    = strtoupper(trim($request->no_sample));
                    if ($request->id_kat != '') $data->kategori_3       = $request->id_kat;

                    if ($request->titik_pengamatan != '') $data->titik_pengamatan   = $request->titik_pengamatan;
                    if ($request->tinggi_tanah != '') $data->tinggi_tanah           = $request->tinggi_tanah;
                    if ($request->tinggi_relatif != '') $data->tinggi_relatif       = $request->tinggi_relatif;
                    if ($request->status_uap != '') $data->status_uap       = $request->status_uap;
                    if ($request->tekanan_opas != '') $data->tekanan_udara_opasitas       = $request->tekanan_opas;
                    if ($request->suhu_bola != '') $data->suhu_bola       = $request->suhu_bola;
                    if ($request->cuaca != '') $data->cuaca = $request->cuaca;
                    if ($request->kelem_opas != '') $data->kelembapan_opasitas = $request->kelem_opas;
                    if ($request->suhu_ambien != '') $data->suhu_ambien = $request->suhu_ambien;
                    if ($request->arah_utara != '') $data->arah_utara             = $request->arah_utara;
                    if ($request->noteket != '') $data->info_tambahan             = $request->noteket;
                    if ($request->status_uap != '') $data->status_uap             = $request->status_uap;
                    if ($request->status_konstan != '') $data->status_konstan     = $request->status_konstan;
                    $data->tipe                                             = 1;

                    $jarak_pengamat = array();
                    if ($request->jarAwal != null && $request->jarAkhir != null) {
                        $jarak_pengamat[] = 'Jarak Awal : ' . $request->jarAwal . '; Jarak Akhir : ' . $request->jarAkhir;
                    }
                    $data->jarak_pengamat                               = json_encode($jarak_pengamat);

                    $arah_pengamat = array();
                    if ($request->arAwal != null && $request->arAkhir != null) {
                        $arah_pengamat[] = 'Arah Awal : ' . $request->arAwal . '; Arah Akhir : ' . $request->arAkhir;
                    }
                    $data->arah_pengamat_opasitas                                = json_encode($arah_pengamat);

                    $deskripsi_emisi = array();
                    if ($request->deskripAwal != null && $request->deskripAkhir != null) {
                        $deskripsi_emisi[] = 'Deskripsi Awal : ' . $request->deskripAwal . '; Deskripsi Akhir : ' . $request->deskripAkhir;
                    }
                    $data->deskripsi_emisi                                = json_encode($deskripsi_emisi);

                    $warna_emisi = array();
                    if ($request->warAwal != null && $request->warAkhir != null) {
                        $warna_emisi[] = 'Warna Awal : ' . $request->warAwal . '; Warna Akhir : ' . $request->warAkhir;
                    }
                    $data->warna_emisi                                  = json_encode($warna_emisi);

                    $titik_penentuan = array();
                    if ($request->titikPenentuanAwal != null && $request->titikPenentuanAkhir != null) {
                        $titik_penentuan[] = 'Titik Penentuan Awal : ' . $request->titikPenentuanAwal . '; Titik Penentuan Akhir : ' . $request->titikPenentuanAkhir;
                    }
                    $data->titik_penentuan                                  = json_encode($titik_penentuan);

                    $deskripsi_latar = array();
                    if ($request->deskripLatarAwal != null && $request->deskripLatarAkhir != null) {
                        $deskripsi_latar[] = 'Deskripsi Latar Awal : ' . $request->deskripLatarAwal . '; Deskripsi Latar Akhir : ' . $request->deskripLatarAkhir;
                    }
                    $data->deskripsi_latar                                  = json_encode($deskripsi_latar);

                    $warna_latar = array();
                    if ($request->warlaAwal != null && $request->warlaAkhir != null) {
                        $warna_latar[] = 'Warna Latar Awal : ' . $request->warlaAwal . '; Warna Latar Akhir : ' . $request->warlaAkhir;
                    }
                    $data->warna_latar                                  = json_encode($warna_latar);

                    $kecepatan = array();
                    if ($request->kecAwal != null && $request->kecAkhir != null) {
                        $kecepatan[] = 'Kecepatan Awal : ' . $request->kecAwal . '; Kecepatan Akhir : ' . $request->kecAkhir;
                    }
                    $data->kecepatan_angin                                  = json_encode($kecepatan);

                    $arah_angin = array();
                    if ($request->arahAnginAwal != null && $request->arahAnginAkhir != null) {
                        $arah_angin[] = 'Arah Angin Awal : ' . $request->arahAnginAwal . '; Arah Angin Akhir : ' . $request->arahAnginAkhir;
                    }
                    $data->arah_pengamat                                  = json_encode($arah_angin);

                    $waktu_opas = array();
                    if ($request->waktuAwal != null && $request->waktuAkhir != null) {
                        $waktu_opas[] = 'Waktu Awal : ' . $request->waktuAwal . '; Waktu Akhir : ' . $request->waktuAkhir;
                    }
                    $data->waktu_opasitas                                  = json_encode($waktu_opas);

                    // if ($request->nilOpas != '') $data->nilai_opasitas                  = json_encode($request->nilOpas);
                    if (count($request->nilOpas) > 0 ) $data->nilai_opasitas                  = json_encode($request->nilOpas);
                    if ($request->foto_asap != '') $data->foto_asap     = self::convertImg($request->foto_asap, 6, $this->user_id);
                    if ($request->foto_lain3 != '') $data->foto_lain3     = self::convertImg($request->foto_lain3, 7, $this->user_id);

                    $data->permission_3                     = (empty($request->permis)) ? 1 : $request->permis;
                    $data->created_by                                                 = $this->karyawan;
                    $data->created_at                                                = Carbon::now()->format('Y-m-d H:i:s');
                    // $data->is_rejected = false;
                    $data->save();

                    // Update Order Detail
                    DB::table('order_detail')
                        ->where('no_sampel', strtoupper(trim($request->no_sample)))
                        ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                    $this->resultx = "Data Sampling EMISI CEROBONG Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan";

                    // if ($this->pin != null) {

                    //     $telegram = new Telegram();
                    //     $telegram->send($this->pin, $this->resultx);
                    // }

                    DB::commit();
                    return response()->json([
                        'message' => $this->resultx
                    ], 200);
                }catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'message' => $e.getMessage(),
                        'line'    => $e->getLine(),
                        'code'    => $e->getCode()
                    ]);
                }
            }
        
    }
    

    public function indexEmisiCerobong(Request $request)
    {
        $data = DataLapanganEmisiCerobong::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    // 6/13/2025 - Goni
    public function detailEmisiCerobong(Request $request)
    {
        try {
            // Validate request
            if (!$request->has('id') || empty($request->id)) {
                return response()->json([
                    'message' => 'Data tidak ditemukan..'
                ], 404); // Changed from 401 to 404 as it's a "not found" scenario
            }

            $data = DataLapanganEmisiCerobong::with('detail')
                ->findOrFail($request->id); // Using findOrFail instead of where()->first()

            // Handle case where relation might not exist
            if (!$data->detail) {
                return response()->json([
                    'message' => 'Detail data tidak ditemukan'
                ], 404);
            }

             if($request->tipe == 1) {
                return response()->json([
                    'data'             => $data,
                ], 200);
            }else {
                return response()->json([
                    'id'             => $data->id,
                    'no_sample'      => $data->no_sampel,
                    'no_order'       => $data->detail->no_order,
                    'categori'       => $category,
                    'sampler'        => $data->created_by,
                    'corp'           => $data->detail->nama_perusahaan,
                    'keterangan'     => $data->keterangan,
                    'keterangan_2'   => $data->keterangan_2,
                    'lat'            => $data->latitude,
                    'long'           => $data->longitude,
                    'sumber'         => $data->sumber_emisi,
                    'merk'           => $data->merk,
                    'bakar'          => $data->bahan_bakar,
                    'cuaca'          => $data->cuaca,
                    'kecepatan'      => $data->kecepatan_angin,
                    'diameter'       => $data->diameter_cerobong,
                    'durasiOp'       => $data->durasi_operasi,
                    'filtrasi'       => $data->proses_filtrasi,
                    'metode'         => $data->metode,
                    'datT'           => $data->data_t_flue ?? $data->T_Flue,  // Using null coalescing operator
                    'velocity'       => $data->velocity,
                    'waktu'          => $data->waktu_pengukuran,
                    'suhu'           => $data->suhu,
                    'kelem'          => $data->kelembapan,
                    'tekanan_u'      => $data->tekanan_udara,
                    'opasitas'       => $data->nilai_opasitas,
                    'o2'             => $data->O2,
                    'co'             => $data->CO,
                    'co2'            => $data->CO2,
                    'no'             => $data->NO,
                    'no2'            => $data->NO2,
                    'nox'            => $data->NOx,
                    'so2'            => $data->SO2,
                    'partikulat'     => $data->partikulat,
                    'hf'             => $data->HF,
                    'hci'            => $data->HCI,
                    'h2s'            => $data->H2S,
                    'nh3'            => $data->NH3,
                    'ci2'            => $data->CI2,
                    'tikoor'         => $data->titik_koordinat,
                    'foto_lok'       => $data->foto_lokasi_sampel,
                    'foto_lain'      => $data->foto_lain,
                    'foto_kon'       => $data->foto_kondisi_sampel,
                    'coor'           => $data->titik_koordinat,
                    'status'         => 200
                ], 200);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan pada server',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine()
            ], 500);  // Added proper status code for server errors
        }
    }

    public function approveEmisiCerobong(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Emisi Cerobong dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function deleteEmisiCerobong(Request $request)
    {

        if (isset($request->id) && $request->id != null) {
            if($request->status != '') {
                $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();
                if($request->tipe == 0) {
                    $data->keterangan             = NULL;
                    $data->keterangan_2           = NULL;
                    $data->sumber_emisi                 = NULL;
                    $data->merk                   = NULL;
                    $data->bahan_bakar                  = NULL;
                    $data->cuaca                  = NULL;
                    $data->kecepatan_angin              = NULL;
                    $data->arah_pengamat                   = NULL;
                    $data->diameter_cerobong               = NULL;
                    $data->durasi_operasi               = NULL;
                    $data->proses_filtrasi               = NULL;
                    $data->waktu_pengambilan      = NULL;
                    $data->titik_koordinat        = NULL;
                    $data->latitude                    = NULL;
                    $data->longitude                  = NULL;
                    $data->suhu                   = NULL;
                    $data->kelembapan                  = NULL;
                    $data->tekanan_udara              = NULL;
                    $data->kapasitas              = NULL;
                    $data->HCI                    = NULL;
                    $data->H2S                    = NULL;
                    $data->NH3                    = NULL;
                    $data->CI2                    = NULL;
                    $data->HF                     = NULL;
                    $data->permission_1               = 0;
                    $data->foto_lokasi_sampel     = NULL;
                    $data->foto_lain              = NULL;
                    $data->partikulat             = NULL;
                    $data->tipe_delete              = 1;
                    $data->delete_by             = $this->karyawan;
                    $data->delete_at             = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    if($data->titik_koordinat == null && $data->T_Flue == null && $data->status_konstan == null){
                        $data->delete();
                    }
                }else if($request->tipe == 1) {
                    $data->metode                 = NULL;
                    $data->O2                     = NULL;
                    $data->CO                     = NULL;
                    $data->CO2                    = NULL;
                    $data->NO                     = NULL;
                    $data->NO2                    = NULL;
                    $data->SO2                    = NULL;
                    $data->T_Flue                 = NULL;
                    $data->velocity               = NULL;
                    $data->permission_2               = 0;
                    $data->waktu_selesai          = NULL;
                    $data->foto_struk             = NULL;
                    $data->foto_lain2             = NULL;
                    $data->tipe_delete              = 2;
                    $data->delete_by             = $this->karyawan;
                    $data->delete_at             = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    if($data->titik_koordinat == null && $data->T_Flue == null && $data->status_konstan == null){
                        $data->delete();
                    }
                }else if($request->tipe == 2) {
                    $data->titik_pengamatan       = NULL;
                    $data->tinggi_tanah           = NULL;
                    $data->tinggi_relatif         = NULL;
                    $data->status_uap             = NULL;
                    $data->tekanan_udara_opasitas           = NULL;
                    $data->suhu_bola              = NULL;
                    $data->cuaca                  = NULL;
                    $data->kelembapan_opasitas             = NULL;
                    $data->suhu_ambien            = NULL;
                    $data->arah_utara             = NULL;
                    $data->info_tambahan          = NULL;
                    $data->status_konstan         = NULL;
                    $data->jarak_pengamat         = NULL;
                    $data->arah_pengamat_opasitas          = NULL;
                    $data->deskripsi_emisi        = NULL;
                    $data->warna_emisi            = NULL;
                    $data->titik_penentuan        = NULL;
                    $data->deskripsi_latar        = NULL;
                    $data->warna_latar            = NULL;
                    $data->kecepatan_angin              = NULL;
                    $data->arah_pengamat                   = NULL;
                    $data->waktu_opasitas             = NULL;
                    $data->nilai_opasitas               = NULL;
                    $data->foto_asap              = NULL;
                    $data->foto_lain3             = NULL;
                    $data->permission_3                 = 0;
                    $data->tipe_delete              = 3;
                    $data->delete_by             = $this->karyawan;
                    $data->delete_at             = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    if($data->titik_koordinat == null && $data->T_Flue == null && $data->status_konstan == null){
                        $data->delete();
                    }
                }
                if($request->status == 'hapus_all') {
                    $data->delete();
                }
                return response()->json([
                    'message' => 'Data Berhasil di Hapus',
                    'cat' => 4
                ], 201);
            }else {
                $cek = DataLapanganEmisiCerobong::where('id', $request->id)->first();
                $foto_lok = public_path() .'/dokumentasi/sampling/'. $cek->foto_lokasi_sampel;
                $foto_kon = public_path() .'/dokumentasi/sampling/'. $cek->foto_kondisi_sampel;
                $foto_lain = public_path() .'/dokumentasi/sampling/'.$cek->foto_lain;
                if (is_file($foto_lok)) {
                    unlink($foto_lok);
                }
                if (is_file($foto_kon)) {
                    unlink($foto_kon);
                }
                if (is_file($foto_lain)) {
                    unlink($foto_lain);
                }
                $cek->delete();

                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 4
                ], 201);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // SWAB TEST
    public function addSwab(Request $request)
    {
        DB::beginTransaction();
        try {
            $chek2 = DataLapanganSwab::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

            if ($request->waktu == '') {
                return response()->json([
                    'message' => 'Jam pengambilan tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lok == '') {
                return response()->json([
                    'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_sampl == '') {
                return response()->json([
                    'message' => 'Foto lokasi alat tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lain == '') {
                return response()->json([
                    'message' => 'Foto lain-lain tidak boleh kosong .!'
                ], 401);
            }
            if ($chek2) {
                return response()->json([
                    'message' => 'No Sample sudah diinput!.'
                ], 401);
            } else {
                $data = new DataLapanganSwab();
                $data->no_sampel                 = strtoupper(trim($request->no_sample));
                if ($request->keterangan_4 != '') $data->keterangan            = $request->keterangan_4;
                if ($request->keterangan_2 != '') $data->keterangan_2          = $request->keterangan_2;
                if ($request->categori != '') $data->kategori_3                = $request->categori;
                if ($request->kondisi_tem != '') $data->kondisi_tempat_sampling            = $request->kondisi_tem;
                if ($request->kondisi != '') $data->kondisi_sampel                    = $request->kondisi;
                if ($request->waktu != '') $data->waktu_pengukuran                        = $request->waktu;
                if ($request->suhu != '') $data->suhu                          = $request->suhu;
                if ($request->kelem != '') $data->kelembapan                        = $request->kelem;
                if ($request->tekU != '') $data->tekanan_udara                     = $request->tekU;
                if ($request->luas != '') $data->luas_area_swab                          = $request->luas;
                if ($request->catatan != '') $data->catatan                          = $request->catatan;
                if ($request->permis != '') $data->permission                      = $request->permis;
                if ($request->foto_lok != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lok, 1, $this->user_id);
                if ($request->foto_sampl != '') $data->foto_kondisi_sampel     = self::convertImg($request->foto_sampl, 2, $this->user_id);
                if ($request->foto_lain != '') $data->foto_lain                = self::convertImg($request->foto_lain, 3, $this->user_id);
                $data->created_by                                                  = $this->karyawan;
                $data->created_at                                                 = Carbon::now()->format('Y-m-d H:i:s');
                // $data->is_rejected = false;
                $data->save();

                // Update Order Detail
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                $nama = $this->karyawan;
                $this->resultx = "Data Sampling FDL SWAB Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

                // if ($this->pin != null) {
                //     $telegram = new Telegram();
                //     $telegram->send($this->pin, $this->resultx);
                // }

                DB::commit();
                return response()->json([
                    'message' => $this->resultx
                ], 200);
            }
        
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function indexSwab(Request $request)
    {
        $data = DataLapanganSwab::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approveSwab(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganSwab::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Swab Test dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function detailSwab(Request $request)
    {
        $data = DataLapanganSwab::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Swab Test success';

        if (isset($request->id) || $request->id != '') {
            return response()->json([
                'id'             => $data->id,
                'no_sample'      => $data->no_sampel,
                'no_order'       => $data->detail->no_order ?? null,
                'categori'       => explode('-', $data->detail->kategori_3)[1],
                'sampler'        => $data->created_by,
                'corp'           => $data->detail->nama_perusahaan,
                'keterangan'     => $data->keterangan,
                'keterangan_2'   => $data->keterangan_2,
                // 'lat'            => $data->latitude,
                // 'long'           => $data->longitude,

                'waktu'          => $data->waktu_pengukuran,
                'kondisi_tem'    => $data->kondisi_tempat_sampling,
                'kondisi'        => $data->kondisi_sampel,
                'luas'           => $data->luas_area_swab,
                'suhu'           => $data->suhu,
                'kelem'          => $data->kelembapan,
                'catatan'        => $data->catatan,
                'tekanan_u'      => $data->tekanan_udara,

                'tikoor'         => $data->titik_koordinat,
                'foto_lok'       => $data->foto_lokasi_sampel,
                'foto_lain'      => $data->foto_lain,
                'foto_kon'       => $data->foto_kondisi_sampel,
                'coor'           => $data->titik_koordinat,
                'status'         => '200'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Data tidak ditemukan..'
            ], 401);
        }
    }

    public function deleteSwab(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganSwab::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;

            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }

            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Swab Test dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
                'cat' => 4
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // MICROBIOLOGI
    public function addMicrobiologi(Request $request)
    {
        DB::beginTransaction();
        try {
            $fdl = DataLapanganMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if ($request->waktu == '') {
                return response()->json([
                    'message' => 'Jam pengambilan tidak boleh kosong .!'
                ], 401);
            }
            if ($request->param != null) {
                foreach ($request->param as $en => $ab) {
                    $cek = DetailMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $ab)->get();

                    if ($request->shift[$en] !== "Sesaat") {
                        $nilai_array = array();
                        foreach ($cek as $key => $value) {
                            $nilai_array[$key] = $value->shift_pengambilan;
                        }
                        if (in_array($request->shift[$en], $nilai_array)) {
                            return response()->json([
                                'message' => 'Pengambilan' . $ab . ' Shift ' . $request->shift[$en] . ' sudah ada !'
                            ], 401);
                        }
                    }
                }
            }
            foreach ($request->param as $in => $a) {
                $pengukuran = [
                    'Flow Rate' => $request->flow[$in],
                    'Durasi' => $request->durasi[$in] . ' menit'
                ];
                $fdlvalue = new DetailMicrobiologi();
                $fdlvalue->parameter                         = $a;
                $fdlvalue->shift_pengambilan                   = $request->shift[$in];
                if($request->metode_uji[$in] != '')         $fdlvalue->metode_uji           = $request->metode_uji[$in];
                if($request->metode_sampling[$in] != '')    $fdlvalue->metode_sampling      = $request->metode_sampling[$in];
                if($request->nama_alat[$in] != '')          $fdlvalue->nama_alat            = $request->nama_alat[$in];
                if($request->nama_alat_manual[$in] != '')   $fdlvalue->nama_alat_manual     = $request->nama_alat_manual[$in];
                $fdlvalue->no_sampel                 = strtoupper(trim($request->no_sample));
                if ($request->keterangan_4 != '') $fdlvalue->keterangan            = $request->keterangan_4;
                if ($request->keterangan_2 != '') $fdlvalue->keterangan_2          = $request->keterangan_2;
                if ($request->kondisi != '') $fdlvalue->kondisi_ruangan                    = $request->kondisi;
                if ($request->ventilasi != '') $fdlvalue->ventilasi                = $request->ventilasi;
                if ($request->waktu != '') $fdlvalue->waktu_pengukuran                        = $request->waktu;
                if ($request->suhu != '') $fdlvalue->suhu                          = $request->suhu;
                if ($request->kelem != '') $fdlvalue->kelembapan                        = $request->kelem;
                if ($request->tekU != '') $fdlvalue->tekanan_udara                     = $request->tekU;
                $fdlvalue->pengukuran                          = json_encode($pengukuran);
                if ($request->catatan != '') $fdlvalue->catatan_sampling                      = $request->catatan;
                if ($request->permis != '') $fdlvalue->permission                     = $request->permis;

                // DOKUMENTASI
                if ($request->statFoto == 'adaFoto') {
                    if ($request->foto_lok != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
                    if ($request->foto_sampl != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_sampl, 2, $this->user_id);
                    if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                } else {
                    if ($request->foto_lok != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
                    if ($request->foto_sampl != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_sampl, 2, $this->user_id);
                    if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                }
                $fdlvalue->created_by                                                  = $this->karyawan;
                $fdlvalue->created_at                                                 = Carbon::now()->format('Y-m-d H:i:s');
                $fdlvalue->save();
            }

            if (is_null($fdl)) {
                $data = new DataLapanganMicrobiologi();
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            } else {
                $data = $fdl;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            }

            if ($request->categori != '') {
                $data->kategori_3 = $request->categori;
            }

            $data->no_sampel    = strtoupper(trim($request->no_sample));
            $data->permission   = $request->permis;
            $data->created_by   = $this->karyawan;
            // $data->is_rejected  = false;
            $data->save();
            
            // if (is_null($fdl)) {
            //     $data = new DataLapanganMicrobiologi();
            //     if ($request->id_kat != '') $data->kategori_3                 = $request->id_kat;
            //     $data->no_sampel                                              = strtoupper(trim($request->no_sample));
            //     $data->created_by                                             = $this->karyawan;
            //     $data->created_at                                            = Carbon::now()->format('Y-m-d H:i:s');
                // $data->is_rejected = false;
            //     $data->save();
            // }

            $this->resultx = "Data Sampling FDL MICROBIOLOGI Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan";

            DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
        }catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'code' => $e->getCode()
            ], 401);
        }
    }

    public function indexMicrobiologi(Request $request)
    {
        $data = DataLapanganMicrobiologi::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approveMicrobiologi(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganMicrobiologi::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $data->is_approve     = 1;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Microbiologi dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function detailMicrobiologi(Request $request)
    {
        if ($request->tip == 1) {
            $data = DataLapanganMicrobiologi::with('detail')->where('no_sampel', $request->no_sample)->first();
            $this->resultx = 'get Detail sample lapangan Microbio success';
            return response()->json([
                'no_sample'      => $data->no_sampel,
                'no_order'       => $data->detail->no_order,
                'categori'       => explode('-', $data->detail->kategori_3)[1],
                'sampler'        => $data->created_by,
                'corp'           => $data->detail->nama_perusahaan,
            ], 200);
        } else if ($request->tip == 2) {
            $data = DetailMicrobiologi::with('detail')->where('no_sampel', $request->no_sample)->get();
            $this->resultx = 'get Detail sample lapangan Microbiologi success';
            return response()->json([
                'data'             => $data,
            ], 200);
        } else if ($request->tip == 3) {
            $data = DetailMicrobiologi::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail sample lapangan Microbiologi success';
            return response()->json([
                'data'             => $data,
            ], 200);
        }
    }

    public function deleteMicrobiologi(Request $request)
    {
        if (isset($request->id) || $request->id != null || isset($request->shift) || $request->shift != null) {
            $data = DetailMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
            if ($request->tip == 1) {
                $cek = DetailMicrobiologi::where('id', $request->id)->first();
                if ($data->count() > 1) {
                    $cek->delete();
                    $this->resultx = "Fdl Microbio parameter $cek->parameter di no sample $cek->no_sample berhasil dihapus.!";
                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 1
                    ], 201);
                } else {
                    $cek2 = DataLapanganMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                    $cek->delete();
                    $cek2->delete();
                    $this->resultx = "Fdl Microbio parameter $cek->parameter di no sample $cek->no_sampel berhasil dihapus.!";
                    Helpers::saveToLogRequest($this->pathinfo, $this->globaldate, $this->param, $this->useragen, $this->resultx, $this->ip);
                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 2
                    ], 201);
                }
            } else if ($request->tip == 2) {
                $cek = DetailMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift', $request->shift)->get();
                $shift = array();
                foreach ($data as $dat) {
                    $shift[$dat['shift_pengambilan']][] = $dat;
                }
                if (count($shift) > 1) {
                    $cek->each->delete();
                    $this->resultx = "Fdl Microbio shift $request->shift di no sample $request->no_sample berhasil dihapus.!";
                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 1
                    ], 201);
                } else {
                    $cek2 = DataLapanganMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                    $cek->each->delete();
                    $cek2->delete();
                    $this->resultx = "Fdl Microbio shift $request->shift di no sample $request->no_sample berhasil dihapus.!";
                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 2
                    ], 201);
                }
            } else if ($request->tip == 3) {
                $cek = DataLapanganMicrobiologi::where('id', $request->id)->first();
                $cek2 = DetailMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->delete();
                $cek->delete();
                $this->resultx = "Fdl Microbio no sample $request->no_sample berhasil dihapus.!";
                return response()->json([
                    'message' => $this->resultx,
                ], 201);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // LINGKUNGAN KERJA
    public function addLingkunganKerja(Request $request)
    {
        DB::beginTransaction();
        try {
            $fdl = DataLapanganLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if ($request->waktu == '') {
                return response()->json([
                    'message' => 'Jam pengambilan tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lok == '') {
                return response()->json([
                    'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_sampl == '') {
                return response()->json([
                    'message' => 'Foto lokasi alat tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lain == '') {
                return response()->json([
                    'message' => 'Foto lain-lain tidak boleh kosong .!'
                ], 401);
            }
            if ($request->param != null) {
                foreach ($request->param as $en => $ab) {
                    $cek = DetailLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $ab)->get();
                    if ($request->shift !== "Sesaat") {
                        $nilai_array = array();
                        foreach ($cek as $key => $value) {
                            $durasi = $value->kategori_pengujian;
                            // Check if $durasi contains a hyphen and has at least two parts
                            if (strpos($durasi, '-') !== false) {
                                $durasi_parts = explode("-", $durasi);
                                if (count($durasi_parts) > 1) {
                                    $durasi = $durasi_parts[1];
                                    $nilai_array[$key] = str_replace('"', "", $durasi);
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
            if($request->param != null){
                foreach ($request->param as $in => $a) {
                    $pengukuran = array();
                    $durasii = null;
                    if ($a == 'TSP (24 Jam)' || $a == 'Pb (24 Jam)' || $a == 'PM 10 (24 Jam)' || $a == 'PM 10 (8 Jam)' || $a == 'PM 2.5 (24 Jam)' || $a == 'PM 2.5 (8 Jam)') {
                        if ($request->shift == 'L25') {
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
                            'Flow Tengah' => $request->tengah[$in],
                            'Flow Akhir' => $request->akhir[$in],
                            'Durasi' => $request->durasi[$in] . ' menit',
                            'Flow Awal 2' => $request->awal2[$in],
                            'Flow Tengah 2' => $request->tengah2[$in],
                            'Flow Akhir 2' => $request->akhir2[$in],
                            'Durasi 2' => $request->durasi2[$in] . ' menit',
                        ];
                    } else if (
                        $a == "Al. Hidrokarbon" ||
                        $a == "Al. Hidrokarbon (8 Jam)" ||
                        $a == "Acetone" ||
                        $a == "Alkana Gas" ||
                        $a == "Butanon" ||
                        $a == "Asam Asetat" ||
                        $a == "Benzene" ||
                        $a == "Benzene (8 Jam)" ||
                        $a == "Cyclohexanone" ||
                        $a == "EA" ||
                        $a == "Ethanol" ||
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
                    } else if($a == "Pertukaran Udara"){
                        $pengukuran = [
                            'volume_ruangan' => $request->volume_ruangan . ' M3',
                            'panjang_ruangan' => $request->panjang_ruangan . ' Meter',
                            'lebar_ruangan' => $request->lebar_ruangan . ' Meter',
                            'tinggi_ruangan' => $request->tinggi_ruangan . ' Meter',
                            'jumlah_pengukuran' => $request->jumlah_pengukuran,
                            'luas_penampang' => $request->luas_penampang,
                            'laju_ventilasi' => $request->laju_ventilasi,
                        ];
                    }else if($a == "Passive SO2" || $a == "Passive NO2"){
                        $pengukuran = [
                            'Durasi' => $request->durasi[$in] . ' menit',
                        ];
                    } else {
                        $pengukuran = [
                            'Flow Awal' => $request->awal[$in],
                            'Flow Tengah' => $request->tengah[$in],
                            'Flow Akhir' => $request->akhir[$in],
                            'Durasi' => $request->durasi[$in] . ' menit',
                        ];
                    }
                    $absorbansi = '';
                    if ($request->paramAb != null) {
                        foreach ($request->paramAb as $pr => $pa) {
                            if ($pa == $a) {
                                $absorbansi = array();
                                if ($pa == 'O3' || $pa == 'O3 (8 Jam)') {
                                    $absorbansi = [
                                        'blanko' => $request->blanko[$pr],
                                        'data-1' => $request->data1[$pr],
                                        'data-2' => $request->data2[$pr],
                                        'data-3' => $request->data3[$pr],
                                        'blanko2' => $request->blanko2[$pr],
                                        'data-4' => $request->data4[$pr],
                                        'data-5' => $request->data5[$pr],
                                        'data-6' => $request->data6[$pr],
                                    ];
                                } else {
                                    $absorbansi = [
                                        'blanko' => $request->blanko[$pr],
                                        'data-1' => $request->data1[$pr],
                                        'data-2' => $request->data2[$pr],
                                        'data-3' => $request->data3[$pr],
                                    ];
                                }
                            }
                        }
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
    
                    $fdlvalue = new DetailLingkunganKerja();
                    $fdlvalue->no_sampel                 = strtoupper(trim($request->no_sample));
                    if ($request->keterangan_4 != '') $fdlvalue->keterangan            = $request->keterangan_4;
                    if ($request->keterangan_2 != '') $fdlvalue->keterangan_2          = $request->keterangan_2;
                    // if ($request->posisi != '') $fdlvalue->titik_koordinat             = $request->posisi;
                    $fdlvalue->titik_koordinat = $request->posisi ?? '-';
                    if ($request->lat != '') $fdlvalue->latitude                            = $request->lat;
                    if ($request->longi != '') $fdlvalue->longitude                        = $request->longi;
                    if ($request->lok != '') $fdlvalue->lokasi                         = $request->lok;
                    $fdlvalue->parameter                                               = $a;
    
                    if ($request->cuaca != '') $fdlvalue->cuaca                        = $request->cuaca;
                    if ($request->ventilasi != '') $fdlvalue->laju_ventilasi                = $request->ventilasi;
                    if ($request->intensitas != '') $fdlvalue->intensitas              = $request->intensitas;
                    if ($request->aktifitas != '') $fdlvalue->aktifitas                = $request->aktifitas;
                    if ($request->jarak != '') $fdlvalue->jarak_sumber_cemaran                        = $request->jarak;
                    if ($request->waktu != '') $fdlvalue->waktu_pengukuran                        = $request->waktu;
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
                    $fdlvalue->durasi_pengujian       = $durasii;
                    $fdlvalue->pengukuran                                              = json_encode($pengukuran);
                    if ($absorbansi != '') $fdlvalue->absorbansi                       = json_encode($absorbansi);
    
                    if ($request->permis != '') $fdlvalue->permission                      = $request->permis;
    
                    if ($request->statFoto == 'adaFoto') {
                        if ($request->foto_lok != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
                        if ($request->foto_sampl != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_sampl, 2, $this->user_id);
                        if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    } else {
                        if ($request->foto_lok != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
                        if ($request->foto_sampl != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_sampl, 2, $this->user_id);
                        if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    }
                    $fdlvalue->created_by                                                  = $this->karyawan;
                    $fdlvalue->created_at                                                 = Carbon::now()->format('Y-m-d H:i:s');
                    $fdlvalue->save();
                }
            }else{
                $parameter = ['Suhu', 'Kelembaban'];
                foreach($parameter as $a){
                    $fdlvalue = new DetailLingkunganKerja();
                    $fdlvalue->no_sampel                 = strtoupper(trim($request->no_sample));
                    if ($request->keterangan_4 != '') $fdlvalue->keterangan            = $request->keterangan_4;
                    if ($request->keterangan_2 != '') $fdlvalue->keterangan_2          = $request->keterangan_2;
                    // if ($request->posisi != '') $fdlvalue->titik_koordinat             = $request->posisi;
                    $fdlvalue->titik_koordinat = $request->posisi ?? '-';
                    if ($request->lat != '') $fdlvalue->latitude                            = $request->lat;
                    if ($request->longi != '') $fdlvalue->longitude                        = $request->longi;
                    if ($request->lok != '') $fdlvalue->lokasi                         = $request->lok;
                    $fdlvalue->parameter                                               = $a;
                    if ($request->cuaca != '') $fdlvalue->cuaca                        = $request->cuaca;
                    if ($request->ventilasi != '') $fdlvalue->laju_ventilasi                = $request->ventilasi;
                    if ($request->intensitas != '') $fdlvalue->intensitas              = $request->intensitas;
                    if ($request->aktifitas != '') $fdlvalue->aktifitas                = $request->aktifitas;
                    if ($request->jarak != '') $fdlvalue->jarak_sumber_cemaran                        = $request->jarak;
                    if ($request->waktu != '') $fdlvalue->waktu_pengukuran                        = $request->waktu;
                    if ($request->kec != '') $fdlvalue->kecepatan_angin                        = $request->kec;
                    $fdlvalue->kategori_pengujian                                                   = 'Sesaat';
                    $fdlvalue->shift_pengambilan                   = 'Sesaat';
                    if ($request->catatan != '') $fdlvalue->catatan_kondisi_lapangan                          = $request->catatan;
                    if ($request->suhu != '') $fdlvalue->suhu                          = $request->suhu;
                    if ($request->kelem != '') $fdlvalue->kelembapan                        = $request->kelem;
                    if ($request->tekU != '') $fdlvalue->tekanan_udara                     = $request->tekU;
                    if ($request->desk_bau != '') $fdlvalue->deskripsi_bau                  = $request->desk_bau;
                    if ($request->metode != '') $fdlvalue->metode_pengukuran                      = $request->metode;
                    if ($request->permis != '') $fdlvalue->permission                      = $request->permis;
                    if ($request->statFoto == 'adaFoto') {
                        if ($request->foto_lok != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
                        if ($request->foto_sampl != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_sampl, 2, $this->user_id);
                        if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    } else {
                        if ($request->foto_lok != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
                        if ($request->foto_sampl != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_sampl, 2, $this->user_id);
                        if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    }
                    $fdlvalue->created_by                                                  = $this->karyawan;
                    $fdlvalue->created_at                                                 = Carbon::now()->format('Y-m-d H:i:s');
                    $fdlvalue->save();
                }
            }

            // if (is_null($fdl)) {
            //     $data = new DataLapanganLingkunganKerja();
            //     $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            // } else {
            //     $data = $fdl;
            //     $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            // }

            // if ($request->categori != '') {
            //     $data->kategori_3 = $request->categori;
            // }

            // $data->no_sampel    = strtoupper(trim($request->no_sample));
            // $data->permission   = $request->permis;
            // $data->created_by   = $this->karyawan;
            // // $data->is_rejected  = false;
            // $data->save();

            if (is_null($fdl)) {
                $data = new DataLapanganLingkunganKerja();
                $data->no_sampel                 = strtoupper(trim($request->no_sample));
                $data->permission                 = $request->permis;
                if ($request->categori != '') $data->kategori_3                 = $request->categori;
                $data->created_by                                                  = $this->karyawan;
                $data->created_at                                                 = Carbon::now()->format('Y-m-d H:i:s');
                $data->is_rejected = false;
                $data->save();
            }

            DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $header = DB::table('lingkungan_header')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $nama = $this->karyawan;
            $this->resultx = "Data Sampling LINGKUNGAN KERJA Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage().$e->getLine()], 401);
        }
    }

    public function indexLingkunganKerja(Request $request)
    {
        $data = DataLapanganLingkunganKerja::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approveLingkunganKerja(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganLingkunganKerja::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Lingkungan Kerja dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function detailLingkunganKerja(Request $request)
    {
        if ($request->tip == 1) {
            $data = DataLapanganLingkunganKerja::with('detail')
                ->where('no_sampel', $request->id)
                ->first();
            $this->resultx = 'get Detail sample lingkuhan kerja success';

            return response()->json([
                'no_sample'      => $data->no_sampel,
                'no_order'       => $data->detail->no_order,
                'categori'       => explode('-', $data->detail->kategori_3)[1],
                'sampler'        => $data->created_by,
                'corp'           => $data->detail->nama_perusahaan,
            ], 200);
        } else if ($request->tip == 2) {
            $data = DetailLingkunganKerja::with('detail')
                ->where('no_sampel', $request->id)
                ->get();
            $this->resultx = 'get Detail sample lapangan Lingkungan Kerja success';
            // dd($data);
            return response()->json([
                'data'             => $data,
            ], 200);
        } else if ($request->tip == 3) {
            $data = DetailLingkunganKerja::with('detail')
                ->where('id', $request->id)
                ->first();
            $this->resultx = 'get Detail sample lapangan Lingkungan Kerja success';
            return response()->json([
                'data'             => $data,
            ], 200);
        }
    }

    public function deleteLingkunganKerja(Request $request)
    {
        if (isset($request->id) || $request->id != null || isset($request->shift) || $request->shift != null) {
            $data = DetailLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
            if ($request->tip == 1) {
                $convert_par = ["TSP", "TSP (24 Jam)", "TSP (6 Jam)", "TSP (8 Jam)", "As", "Cd", "Cr", "Cu", "Fe", "Fe (8 Jam)", "Hg", "Sb", "Se", "Sn", "Zn", "Pb", "Pb (24 Jam)", "Pb (6 Jam)", "Pb (8 Jam)", "Mn", "Ni"];
                $convert_24jam = ["TSP (24 Jam)", "Pb (24 Jam)"];
                $convert_8jam = ["TSP (8 Jam)", "Fe (8 Jam)", "Pb (8 Jam)"];
                $convert_6jam = ["TSP (6 Jam)", "Pb (6 Jam)"];
                $convert_sesaat = ["TSP", "As", "Ba", "Cd", "Co", "Cr", "Cu", "Fe", "Hg", "Mn", "Ni","Pb", "Sb", "Se", "Sn", "Zn", "Aluminium (Al)"];
                $status_par = '';

                if (in_array($request->parameter, $convert_par)) {
                    if (in_array($request->parameter, $convert_sesaat)) {
                        $cek = DetailLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_sesaat)->get();
                        $cek->each->delete();
                        $status_par = json_encode($convert_sesaat);
                    } else if (in_array($request->parameter, $convert_24jam)) {
                        $cek = DetailLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_24jam)->get();
                        $cek->each->delete();
                        $status_par = json_encode($convert_24jam);
                    } else if (in_array($request->parameter, $convert_8jam)) {
                        $cek = DetailLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_8jam)->get();
                        $cek->each->delete();
                        $status_par = json_encode($convert_8jam);
                    } else if (in_array($request->parameter, $convert_6jam)) {
                        $cek = DetailLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_6jam)->get();
                        $cek->each->delete();
                        $status_par = json_encode($convert_6jam);
                    }

                    $cek2 = DetailLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
                    if ($cek2->count() > 0) {
                        $nama = $this->karyawan;
                        $this->resultx = "Fdl LK parameter $status_par di no sample $request->no_sample berhasil dihapus oleh $nama.!";

                        // if($this->pin!=null){
                        //     $telegram = new Telegram();
                        //     $telegram->send($this->pin, $this->resultx);
                        // }

                        return response()->json([
                            'message' => $this->resultx,
                            'cat' => 1
                        ], 201);
                    } else {
                        $cek4 = DataLapanganLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                        $cek4->delete();
                        $nama = $this->karyawan;
                        $this->resultx = "Fdl LK parameter $status_par di no sample $request->no_sample berhasil dihapus $nama.!";

                        // if($this->pin!=null){
                        //     $telegram = new Telegram();
                        //     $telegram->send($this->pin, $this->resultx);
                        // }

                        return response()->json([
                            'message' => $this->resultx,
                            'cat' => 2
                        ], 201);
                    }
                } else {
                    $cek = DetailLingkunganKerja::where('id', $request->id)->first();
                    if ($data->count() > 1) {
                        $cek->delete();
                        $nama = $this->karyawan;
                        $this->resultx = "Fdl LK parameter $cek->parameter di no sample $cek->no_sample berhasil dihapus oleh $nama.!";
                        // if($this->pin!=null){
                        //     $telegram = new Telegram();
                        //     $telegram->send($this->pin, $this->resultx);
                        // }
                        return response()->json([
                            'message' => $this->resultx,
                            'cat' => 1
                        ], 201);
                    } else {
                        $cek2 = DataLapanganLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                        $cek->delete();
                        $cek2->delete();

                        $nama = $this->karyawan;
                        $this->resultx = "Fdl LK parameter $cek->parameter di no sample $cek->no_sample berhasil dihapus oleh $nama.!";

                        // if($this->pin!=null){
                        //     $telegram = new Telegram();
                        //     $telegram->send($this->pin, $this->resultx);
                        // }

                        return response()->json([
                            'message' => $this->resultx,
                            'cat' => 2
                        ], 201);
                    }
                }
            } else if ($request->tip == 2) {
                $cek = DetailLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift_pengambilan', $request->shift)->get();
                $shift = array();
                foreach ($data as $dat) {
                    $shift[$dat['shift_pengambilan']][] = $dat;
                }
                if (count($shift) > 1) {
                    $cek->each->delete();

                    $nama = $this->karyawan;
                    $this->resultx = "Fdl LK shift $request->shift di no sample $request->no_sample berhasil dihapus oleh $nama.!";
                    // if($this->pin!=null){
                    //     $telegram = new Telegram();
                    //     $telegram->send($this->pin, $this->resultx);
                    // }

                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 1
                    ], 201);
                } else {
                    $cek2 = DataLapanganLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                    $cek->each->delete();
                    $cek2->delete();

                    $nama = $this->karyawan;
                    $this->resultx = "Fdl LK shift $request->shift di no sample $request->no_sample berhasil dihapus oleh $nama.!";
                    // if($this->pin!=null){
                    //     $telegram = new Telegram();
                    //     $telegram->send($this->pin, $this->resultx);
                    // }

                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 2
                    ], 201);
                }
            } else if ($request->tip == 3) {
                $cek = DataLapanganLingkunganKerja::where('id', $request->id)->first();
                $cek2 = DetailLingkunganKerja::where('no_sampel', strtoupper(trim($request->no_sample)))->delete();
                $cek->delete();

                $nama = $this->karyawan;
                $this->resultx = "Fdl LK no sample $request->no_sample berhasil dihapus oleh $nama.!";
                // if ($this->pin != null) {

                //     $telegram = new Telegram();
                //     $telegram->send($this->pin, $this->resultx);
                // }

                return response()->json([
                    'message' => $this->resultx,
                ], 201);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // LINGKUNGAN HIDUP
    public function addLingkunganHidup(Request $request)
    {
        DB::beginTransaction();
        try {
            $fdl = DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if ($request->waktu == '') {
                return response()->json([
                    'message' => 'Jam pengambilan tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lok == '') {
                return response()->json([
                    'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_sampl == '') {
                return response()->json([
                    'message' => 'Foto lokasi alat tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lain == '') {
                return response()->json([
                    'message' => 'Foto lain-lain tidak boleh kosong .!'
                ], 401);
            }
            if ($request->param != null) {
                foreach ($request->param as $en => $ab) {
                    $cek = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sample)))->where('parameter', $ab)->get();
                    if ($request->shift !== "Sesaat") {
                        $nilai_array = array();
                        foreach ($cek as $key => $value) {
                            $durasi = $value->shift_pengambilan;
                            $durasi = explode("-", $durasi);
                            $durasi = $durasi;
                            $nilai_array[$key] = str_replace('"', "", $durasi);
                        }
                        if (in_array($request->shift, $nilai_array)) {
                            return response()->json([
                                'message' => 'Pengambilan' . $ab . ' Shift ' . $request->shift . ' sudah ada !'
                            ], 401);
                        }
                    }
                }
            }
            // foreach ($request->param as $in => $a) {
            //     $pengukuran = array();
            //     $durasii = null;
            //     if ($a == 'TSP (24 Jam)' || $a == 'Pb (24 Jam)' || $a == 'PM 10 (24 Jam)' || $a == 'PM 10 (8 Jam)' || $a == 'PM 2.5 (24 Jam)' || $a == 'PM 2.5 (8 Jam)') {
            //         if ($request->shift == 'L25') {
            //             $pengukuran = [
            //                 'Flow' => $request->flow1[$in],
            //             ];
            //             if ($request->durasi[$in] != '' || $request->durasi2[$in] != '') {
            //                 $jam = ($request->durasi[$in] != '' && $request->durasi[$in] != 0 && $request->durasi[$in] != '-') ? $request->durasi[$in] . ' Jam, ' : '';
            //                 $menit = ($request->durasi2[$in] != '' && $request->durasi2[$in] != 0 && $request->durasi2[$in] != '-') ? $request->durasi2[$in] . ' Menit' : '';
            //                 $durasii = $jam . $menit;
            //             }
            //         } else {
            //             $pengukuran = [
            //                 'Flow' => $request->flow1[$in],
            //             ];
            //         }
            //     } else if ($a == "Dustfall" || $a == "Dustfall (S)") {
                    
            //         if ($request->keterangan_alat[$a] != '') {
            //             if($request->keterangan_alat[$a] == 'pemasangan_alat'){
                            
            //                 $pengukuran = [
            //                     'keterangan' => $request->keterangan_alat[$a] ?? null,
            //                     'tanggal_pemasangan' => $request->tanggal_pemasangan[$a] ?? null,
            //                     'luas_botol' => $request->luas_botol[$a] . ' m2'?? null ,
            //                 ];
            //             }else{
                            
            //                 $pengukuran = [
            //                     'keterangan' => $request->keterangan_alat[$a] ?? null,
            //                     'tanggal_selesai' => $request->tanggal_selesai[$a] ?? null,
            //                     'volume_filtrat' => $request->volume_filtrat[$a]. ' liter' ?? null ,
            //                 ];
            //             }
            //         }
            //     } else if (
            //         $a == "Al. Hidrokarbon" ||
            //         $a == "Al. Hidrokarbon (8 Jam)" ||
            //         $a == "Acetone" ||
            //         $a == "Alkana Gas" ||
            //         $a == "Butanon" ||
            //         $a == "Asam Asetat" ||
            //         $a == "Benzene" ||
            //         $a == "Benzene (8 Jam)" ||
            //         $a == "Cyclohexanone" ||
            //         $a == "EA" ||
            //         $a == "Ethanol" ||
            //         $a == "HCl (8 Jam)" ||
            //         $a == "HCl" ||
            //         $a == "HF" ||
            //         $a == "IPA" ||
            //         $a == "MEK" ||
            //         $a == "Stirena" ||
            //         $a == "Stirena (8 Jam)" ||
            //         $a == "Toluene" ||
            //         $a == "Toluene (8 Jam)" ||
            //         $a == "Xylene" ||
            //         $a == "Xylene (8 Jam)"
            //     ) {

            //         $pengukuran = [
            //             'Flow 1' => $request->flow1[$in],
            //             'Flow 2' => $request->flow2[$in],
            //             'Durasi' => $request->durasi[$in] . ' menit',
            //         ];
            //     } else if ($a == 'O3' || $a == 'O3 (8 Jam)' || $a == 'Ox') {
            //         $pengukuran = [
            //             'Flow 1' => $request->flow1[$in],
            //             'Flow 2' => $request->flow2[$in],
            //             'Flow 3' => $request->flow3[$in],
            //             'Durasi' => $request->durasi[$in] . ' menit',
            //             'Flow 4' => $request->flow4[$in],
            //             'Flow 5' => $request->flow5[$in],
            //             'Flow 6' => $request->flow6[$in],
            //             'Durasi 2' => $request->durasi2[$in] . ' menit',
            //         ];
            //     } else if ($a == 'Passive SO2' || $a == 'Passive NO2') {
            //         $pengukuran = [
            //             'Durasi 2' => $request->durasi[$in] . ' menit',
            //         ];
            //     } else {
            //         $pengukuran = [
            //             'Flow 1' => $request->flow1[$in],
            //             'Flow 2' => $request->flow2[$in],
            //             'Flow 3' => $request->flow3[$in],
            //             'Flow 4' => $request->flow4[$in],
            //             'Durasi' => $request->durasi[$in] . ' menit',
            //         ];
            //     }
            //     $absorbansi = '';
            //     if ($request->paramAb != null) {
            //         foreach ($request->paramAb as $pr => $pa) {
            //             if ($pa == $a) {
            //                 $absorbansi = array();
            //                 if ($pa == 'O3' || $pa == 'O3 (8 Jam)' || $pa == 'Ox') {
            //                     $absorbansi = [
            //                         'blanko' => $request->blanko[$pr],
            //                         'data-1' => $request->data1[$pr],
            //                         'data-2' => $request->data2[$pr],
            //                         'data-3' => $request->data3[$pr],
            //                         'blanko2' => $request->blanko2[$pr],
            //                         'data-4' => $request->data4[$pr],
            //                         'data-5' => $request->data5[$pr],
            //                         'data-6' => $request->data6[$pr],
            //                     ];
            //                 } else {
            //                     $absorbansi = [
            //                         'blanko' => $request->blanko[$pr],
            //                         'data-1' => $request->data1[$pr],
            //                         'data-2' => $request->data2[$pr],
            //                         'data-3' => $request->data3[$pr],
            //                     ];
            //                 }
            //             }
            //         }
            //     }
                
            //     $shift2 = $request->shift;
            //     if ($request->kateg_uji[$in] == null || $request->kateg_uji[$in] == '') {
            //         $shift_peng = 'Sesaat';
            //         $shift2 = 'Sesaat';
            //     } else if ($request->kateg_uji[$in] == '24 Jam') {
            //         $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift);
            //     } else if ($request->kateg_uji[$in] == '8 Jam') {
            //         $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift);
            //     } else if ($request->kateg_uji[$in] == '6 Jam') {
            //         $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift);
            //     }

            //     $fdlvalue = new DetailLingkunganHidup();
            //     $fdlvalue->no_sampel                 = strtoupper(trim($request->no_sample));
            //     if ($request->keterangan_4 != '') $fdlvalue->keterangan            = $request->keterangan_4;
            //     if ($request->keterangan_2 != '') $fdlvalue->keterangan_2          = $request->keterangan_2;
            //     if ($request->posisi != '') $fdlvalue->titik_koordinat             = $request->posisi;
            //     if ($request->lat != '') $fdlvalue->latitude                            = $request->lat;
            //     if ($request->longi != '') $fdlvalue->longitude                        = $request->longi;
            //     if ($request->lok != '') $fdlvalue->lokasi                         = $request->lok;
            //     $fdlvalue->parameter                         = $a;

            //     if ($request->cuaca != '') $fdlvalue->cuaca              = $request->cuaca;
            //     if ($request->kecepatan != '') $fdlvalue->kecepatan_angin              = $request->kecepatan;
            //     if ($request->arah_angin != '') $fdlvalue->arah_angin              = $request->arah_angin;
            //     if ($request->jarak != '') $fdlvalue->jarak_sumber_cemaran              = $request->jarak;
            //     if ($request->waktu != '') $fdlvalue->waktu_pengukuran                        = $request->waktu;
            //     if ($request->intensitas != '') $fdlvalue->intensitas                        = $request->intensitas;
            //     $fdlvalue->satuan                        = $request->satuan[$in];
            //     $fdlvalue->kategori_pengujian                   = $shift_peng;
            //     $fdlvalue->shift_pengambilan                   = $shift2;
            //     if ($request->catatan != '') $fdlvalue->catatan_kondisi_lapangan                          = $request->catatan;
            //     if ($request->suhu != '') $fdlvalue->suhu                          = $request->suhu;
            //     if ($request->kelem != '') $fdlvalue->kelembapan                        = $request->kelem;
            //     if ($request->tekU != '') $fdlvalue->tekanan_udara                     = $request->tekU;
            //     if ($request->desk_bau != '') $fdlvalue->deskripsi_bau                     = $request->desk_bau;
            //     if ($request->metode != '') $fdlvalue->metode_pengukuran                     = $request->metode;
            //     $fdlvalue->durasi_pengambilan       = $durasii;
            //     $fdlvalue->pengukuran     = json_encode($pengukuran);
            //     if ($absorbansi != '') $fdlvalue->absorbansi     = json_encode($absorbansi);

            //     if ($request->permis != '') $fdlvalue->permission                      = $request->permis;
            //     if ($request->statFoto == 'adaFoto') {
            //         if ($request->foto_lok != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
            //         if ($request->foto_sampl != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_sampl, 2, $this->user_id);
            //         if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
            //     } else {
            //         if ($request->foto_lok != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
            //         if ($request->foto_sampl != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_sampl, 2, $this->user_id);
            //         if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
            //     }
            //     $fdlvalue->created_by                     = $this->karyawan;
            //     $fdlvalue->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
            //     // dd($fdlvalue);
            //     $fdlvalue->save();
            // }
            // 13/06/2025 - Goni
            if($request->param != null){
                foreach ($request->param as $in => $a) {
                    $pengukuran = array();
                    $durasii = null;
                    if ($a == 'TSP (24 Jam)' || $a == 'Pb (24 Jam)' || $a == 'PM 10 (24 Jam)' || $a == 'PM 10 (8 Jam)' || $a == 'PM 2.5 (24 Jam)' || $a == 'PM 2.5 (8 Jam)') {
                        if ($request->shift == 'L25') {
                            $pengukuran = [
                                'Flow' => $request->flow1[$in],
                            ];
                            if ($request->durasi[$in] != '' || $request->durasi2[$in] != '') {
                                $jam = ($request->durasi[$in] != '' && $request->durasi[$in] != 0 && $request->durasi[$in] != '-') ? $request->durasi[$in] . ' Jam, ' : '';
                                $menit = ($request->durasi2[$in] != '' && $request->durasi2[$in] != 0 && $request->durasi2[$in] != '-') ? $request->durasi2[$in] . ' Menit' : '';
                                $durasii = $jam . $menit;
                            }
                        } else {
                            $pengukuran = [
                                'Flow' => $request->flow1[$in],
                            ];
                        }
                    } else if (str_contains($a, 'Dustfall')) {
                        
                        if ($request->keterangan_alat[$a] != '') {
                            if($request->keterangan_alat[$a] == 'pemasangan_alat'){
                                
                                $pengukuran = [
                                    'keterangan' => $request->keterangan_alat[$a] ?? null,
                                    'tanggal_pemasangan' => $request->tanggal_pemasangan[$a] ?? null,
                                    'luas_botol' => $request->luas_botol[$a] . ' m2'?? null ,
                                ];
                            }else{
                                
                                $pengukuran = [
                                    'keterangan' => $request->keterangan_alat[$a] ?? null,
                                    'tanggal_selesai' => $request->tanggal_selesai[$a] ?? null,
                                    'volume_filtrat' => $request->volume_filtrat[$a]. ' liter' ?? null ,
                                ];
                            }
                        }
                    } else if (
                        $a == "Al. Hidrokarbon" ||
                        $a == "Al. Hidrokarbon (8 Jam)" ||
                        $a == "Acetone" ||
                        $a == "Alkana Gas" ||
                        $a == "Butanon" ||
                        $a == "Asam Asetat" ||
                        $a == "Benzene" ||
                        $a == "Benzene (8 Jam)" ||
                        $a == "Cyclohexanone" ||
                        $a == "EA" ||
                        $a == "Ethanol" ||
                        $a == "HCl (8 Jam)" ||
                        $a == "HCl" ||
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
                            'Flow 1' => $request->flow1[$in],
                            'Flow 2' => $request->flow2[$in],
                            'Durasi' => $request->durasi[$in] . ' menit',
                        ];
                    } else if ($a == 'O3' || $a == 'O3 (8 Jam)' || $a == 'Ox') {
                        $pengukuran = [
                            'Flow 1' => $request->flow1[$in],
                            'Flow 2' => $request->flow2[$in],
                            'Flow 3' => $request->flow3[$in],
                            'Durasi' => $request->durasi[$in] . ' menit',
                            'Flow 4' => $request->flow4[$in],
                            'Flow 5' => $request->flow5[$in],
                            'Flow 6' => $request->flow6[$in],
                            'Durasi 2' => $request->durasi2[$in] . ' menit',
                        ];
                    } else if ($a == 'Passive SO2' || $a == 'Passive NO2') {
                        $pengukuran = [
                            'Durasi 2' => $request->durasi[$in] . ' menit',
                        ];
                    } else {
                        $pengukuran = [
                            'Flow 1' => $request->flow1[$in],
                            'Flow 2' => $request->flow2[$in],
                            'Flow 3' => $request->flow3[$in],
                            'Flow 4' => $request->flow4[$in],
                            'Durasi' => $request->durasi[$in] . ' menit',
                        ];
                    }
                    $absorbansi = '';
                    if ($request->paramAb != null) {
                        foreach ($request->paramAb as $pr => $pa) {
                            if ($pa == $a) {
                                $absorbansi = array();
                                if ($pa == 'O3' || $pa == 'O3 (8 Jam)' || $pa == 'Ox') {
                                    $absorbansi = [
                                        'blanko' => $request->blanko[$pr],
                                        'data-1' => $request->data1[$pr],
                                        'data-2' => $request->data2[$pr],
                                        'data-3' => $request->data3[$pr],
                                        'blanko2' => $request->blanko2[$pr],
                                        'data-4' => $request->data4[$pr],
                                        'data-5' => $request->data5[$pr],
                                        'data-6' => $request->data6[$pr],
                                    ];
                                } else {
                                    $absorbansi = [
                                        'blanko' => $request->blanko[$pr],
                                        'data-1' => $request->data1[$pr],
                                        'data-2' => $request->data2[$pr],
                                        'data-3' => $request->data3[$pr],
                                    ];
                                }
                            }
                        }
                    }
                    
                    $shift2 = $request->shift;
                    if ($request->kateg_uji[$in] == null || $request->kateg_uji[$in] == '') {
                        $shift_peng = 'Sesaat';
                        $shift2 = 'Sesaat';
                    } else if ($request->kateg_uji[$in] == '24 Jam') {
                        $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift);
                    } else if ($request->kateg_uji[$in] == '8 Jam') {
                        $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift);
                    } else if ($request->kateg_uji[$in] == '6 Jam') {
                        $shift_peng = $request->kateg_uji[$in] . '-' . json_encode($request->shift);
                    }
    
                    $fdlvalue = new DetailLingkunganHidup();
                    $fdlvalue->no_sampel                 = strtoupper(trim($request->no_sample));
                    if ($request->keterangan_4 != '') $fdlvalue->keterangan            = $request->keterangan_4;
                    if ($request->keterangan_2 != '') $fdlvalue->keterangan_2          = $request->keterangan_2;
                    // if ($request->posisi != '') $fdlvalue->titik_koordinat             = $request->posisi;
                    $fdlvalue->titik_koordinat = $request->posisi ?? '-';
                    if ($request->lat != '') $fdlvalue->latitude                            = $request->lat;
                    if ($request->longi != '') $fdlvalue->longitude                        = $request->longi;
                    if ($request->lok != '') $fdlvalue->lokasi                         = $request->lok;
                    $fdlvalue->parameter                         = $a;
    
                    if ($request->cuaca != '') $fdlvalue->cuaca              = $request->cuaca;
                    if ($request->kecepatan != '') $fdlvalue->kecepatan_angin              = $request->kecepatan;
                    if ($request->arah_angin != '') $fdlvalue->arah_angin              = $request->arah_angin;
                    if ($request->jarak != '') $fdlvalue->jarak_sumber_cemaran              = $request->jarak;
                    if ($request->waktu != '') $fdlvalue->waktu_pengukuran                        = $request->waktu;
                    if ($request->intensitas != '') $fdlvalue->intensitas                        = $request->intensitas;
                    $fdlvalue->satuan                        = $request->satuan[$in];
                    $fdlvalue->kategori_pengujian                   = $shift_peng;
                    $fdlvalue->shift_pengambilan                   = $shift2;
                    if ($request->catatan != '') $fdlvalue->catatan_kondisi_lapangan                          = $request->catatan;
                    if ($request->suhu != '') $fdlvalue->suhu                          = $request->suhu;
                    if ($request->kelem != '') $fdlvalue->kelembapan                        = $request->kelem;
                    if ($request->tekU != '') $fdlvalue->tekanan_udara                     = $request->tekU;
                    if ($request->desk_bau != '') $fdlvalue->deskripsi_bau                     = $request->desk_bau;
                    if ($request->metode != '') $fdlvalue->metode_pengukuran                     = $request->metode;
                    $fdlvalue->durasi_pengambilan       = $durasii;
                    $fdlvalue->pengukuran     = json_encode($pengukuran);
                    if ($absorbansi != '') $fdlvalue->absorbansi     = json_encode($absorbansi);
    
                    if ($request->permis != '') $fdlvalue->permission                      = $request->permis;
                    if ($request->statFoto == 'adaFoto') {
                        if ($request->foto_lok != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
                        if ($request->foto_sampl != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_sampl, 2, $this->user_id);
                        if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    } else {
                        if ($request->foto_lok != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
                        if ($request->foto_sampl != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_sampl, 2, $this->user_id);
                        if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    }
                    $fdlvalue->created_by                     = $this->karyawan;
                    $fdlvalue->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                    // dd($fdlvalue);
                    $fdlvalue->save();
                }
            }else{
                $parameter = ['Suhu', 'Kelembaban'];
                foreach($parameter as $a){
                    $fdlvalue = new DetailLingkunganHidup();
                    $fdlvalue->no_sampel                 = strtoupper(trim($request->no_sample));
                    if ($request->keterangan_4 != '') $fdlvalue->keterangan            = $request->keterangan_4;
                    if ($request->keterangan_2 != '') $fdlvalue->keterangan_2          = $request->keterangan_2;
                    // if ($request->posisi != '') $fdlvalue->titik_koordinat             = $request->posisi;
                    $fdlvalue->titik_koordinat = $request->posisi ?? '-';
                    if ($request->lat != '') $fdlvalue->latitude                            = $request->lat;
                    if ($request->longi != '') $fdlvalue->longitude                        = $request->longi;
                    if ($request->lok != '') $fdlvalue->lokasi                         = $request->lok;
                    $fdlvalue->parameter                                               = $a;
                    if ($request->cuaca != '') $fdlvalue->cuaca                        = $request->cuaca;
                    if ($request->ventilasi != '') $fdlvalue->laju_ventilasi                = $request->ventilasi;
                    if ($request->intensitas != '') $fdlvalue->intensitas              = $request->intensitas;
                    if ($request->aktifitas != '') $fdlvalue->aktifitas                = $request->aktifitas;
                    if ($request->jarak != '') $fdlvalue->jarak_sumber_cemaran                        = $request->jarak;
                    if ($request->waktu != '') $fdlvalue->waktu_pengukuran                        = $request->waktu;
                    if ($request->kec != '') $fdlvalue->kecepatan_angin                        = $request->kec;
                    $fdlvalue->kategori_pengujian                                                   = 'Sesaat';
                    $fdlvalue->shift_pengambilan                   = 'Sesaat';
                    if ($request->catatan != '') $fdlvalue->catatan_kondisi_lapangan                          = $request->catatan;
                    if ($request->suhu != '') $fdlvalue->suhu                          = $request->suhu;
                    if ($request->kelem != '') $fdlvalue->kelembapan                        = $request->kelem;
                    if ($request->tekU != '') $fdlvalue->tekanan_udara                     = $request->tekU;
                    if ($request->desk_bau != '') $fdlvalue->deskripsi_bau                  = $request->desk_bau;
                    if ($request->metode != '') $fdlvalue->metode_pengukuran                      = $request->metode;
                    if ($request->permis != '') $fdlvalue->permission                      = $request->permis;
                    if ($request->statFoto == 'adaFoto') {
                        if ($request->foto_lok != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
                        if ($request->foto_sampl != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_sampl, 2, $this->user_id);
                        if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    } else {
                        if ($request->foto_lok != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lok, 1, $this->user_id);
                        if ($request->foto_sampl != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_sampl, 2, $this->user_id);
                        if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    }
                    $fdlvalue->created_by                                                  = $this->karyawan;
                    $fdlvalue->created_at                                                 = Carbon::now()->format('Y-m-d H:i:s');
                    $fdlvalue->save();
                }
            }

            // if (is_null($fdl)) {
            //     $data = new DataLapanganLingkunganHidup();
            //     $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            // } else {
            //     $data = $fdl;
            //     $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            // }

            // if ($request->categori != '') {
            //     $data->kategori_3 = $request->categori;
            // }

            // $data->no_sampel    = strtoupper(trim($request->no_sample));
            // $data->permission   = $request->permis;
            // $data->created_by   = $this->karyawan;
            // // $data->is_rejected  = false;
            // $data->save();

            if (is_null($fdl)) {
                $data = new DataLapanganLingkunganHidup();
                $data->no_sampel                 = strtoupper(trim($request->no_sample));
                $data->permission                 = $request->permis;
                if ($request->categori != '') $data->kategori_3                 = $request->categori;
                $data->created_by                                                  = $this->karyawan;
                $data->created_at                                                 = Carbon::now()->format('Y-m-d H:i:s');
                $data->is_rejected = false;
                $data->save();
            }


            DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $header = DB::table('lingkungan_header')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $nama = $this->karyawan;
            $this->resultx = "Data Sampling LINGKUNGAN HIDUP Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
            
        } catch (\Exception $e) {
            dd($e);
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage().$e->getLine()], 401);
        }
        
    }

    public function indexLingkunganHidup(Request $request)
    {
        $data = DataLapanganLingkunganHidup::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approveLingkunganHidup(Request $request)
    {
        if (isset($request->id) && $request->id != null) {

            $data = DataLapanganLingkunganHidup::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve  = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin != null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Lingkungan Hidup dengan No sample $no_sampel Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Approved',
                'master_kategori' => 1
            ], 200);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function detailLingkunganHidup(Request $request)
    {
        if ($request->tip == 1) {
            $data = DataLapanganLingkunganHidup::with('detail')
                ->where('no_sampel', $request->id)
                ->first();
            $this->resultx = 'get Detail sample lingkuhan Hidup success';

            return response()->json([
                'no_sample'      => $data->no_sampel,
                'no_order'       => $data->detail->no_order,
                'categori'       => explode('-', $data->detail->kategori_3)[1],
                'sampler'        => $data->created_by,
                'corp'           => $data->detail->nama_perusahaan,
            ], 200);
        } else if ($request->tip == 2) {
            $data = DetailLingkunganHidup::with('detail')
                ->where('no_sampel', $request->no_sample)
                ->get();
            $this->resultx = 'get Detail sample lapangan Lingkungan Hidup success';
            // dd($data);
            return response()->json([
                'data'             => $data,
            ], 200);
        } else if ($request->tip == 3) {
            $data = DetailLingkunganHidup::with('detail')
                ->where('id', $request->id)
                ->first();
            $this->resultx = 'get Detail sample lapangan Lingkungan Hidup success';
            return response()->json([
                'data'             => $data,
            ], 200);
        }
    }

    public function deleteLingkunganHidup(Request $request)
    {
        if (isset($request->id) || $request->id != null || isset($request->shift) || $request->shift != null) {
            if ($request->no_sampel == null) {
                return response()->json([
                    'message' => 'Aplikasi anda belum update...!'
                ], 401);
            }
            $detail = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->get();
            if ($request->tip == 1) {
                $convert_par = ["TSP", "TSP (24 Jam)", "TSP (6 Jam)", "TSP (8 Jam)", "Pb", "Pb (24 Jam)", "Pb (6 Jam)", "Pb (8 Jam)"];
                $convert_24jam = ["TSP (24 Jam)", "Pb (24 Jam)"];
                $convert_8jam = ["TSP (8 Jam)", "Pb (8 Jam)"];
                $convert_6jam = ["TSP (6 Jam)", "Pb (6 Jam)"];
                $convert_sesaat = ["TSP", "Pb", "As", "Ba", "Co","Cr","Cu","Fe","Mn","Ni","Pb","Sb","Se", "Sn","Zn", "Aluminium (Al)"];
                $status_par = '';

                if (in_array($request->parameter, $convert_par)) {
                    if (in_array($request->parameter, $convert_sesaat)) {
                        $detail6 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_sesaat)->get();
                        $detail6->each->delete();
                        $status_par = json_encode($convert_sesaat);
                    } else if (in_array($request->parameter, $convert_24jam)) {
                        $detail6 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_24jam)->get();
                        $detail6->each->delete();
                        $status_par = json_encode($convert_24jam);
                    } else if (in_array($request->parameter, $convert_8jam)) {
                        $detail6 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_8jam)->get();
                        $detail6->each->delete();
                        $status_par = json_encode($convert_8jam);
                    } else if (in_array($request->parameter, $convert_6jam)) {
                        $detail6 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_6jam)->get();
                        $detail6->each->delete();
                        $status_par = json_encode($convert_6jam);
                    }

                    $detail2 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->get();
                    if ($detail2->count() > 0) {
                        $nama = $this->karyawan;
                        $this->resultx = "Fdl LH parameter $status_par di no sample $request->no_sampel berhasil dihapus oleh $nama.!";
                        // if($this->pin!=null){

                        //     $telegram = new Telegram();
                        //     $telegram->send($this->pin, $this->resultx);
                        // }
                        return response()->json([
                            'message' => $this->resultx,
                            'kategori' => 1
                        ], 201);
                    } else {
                        $data = DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
                        $data->delete();
                        $nama = $this->karyawan;
                        $this->resultx = "Fdl LH parameter $status_par di no sample $request->no_sampel berhasil dihapus oleh oleh $nama.!";
                        // if($this->pin!=null){

                        //     $telegram = new Telegram();
                        //     $telegram->send($this->pin, $this->resultx);
                        // }
                        return response()->json([
                            'message' => $this->resultx,
                            'kategori' => 2
                        ], 201);
                    }
                } else {
                    $detail3 = DetailLingkunganHidup::where('id', $request->id)->first();
                    if ($detail->count() > 1) {
                        if($detail3){
                            $detail3->delete();
                        }
                        $nama = $this->karyawan;
                        return response()->json([
                            'message' => "Fdl LH parameter $detail->parameter di no sample $detail->no_sampel berhasil dihapus oleh $nama.!",
                            'kategori' => 1
                        ], 201);
                    } else {
                        $data2 = DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->delete();
                        if($detail3){
                            $detail3->delete();
                        }
                        // $detail3->delete();
                        // $data2->delete();
                        $nama = $this->karyawan;
                        $this->resultx = "Fdl LH parameter $detail->parameter di no sample $detail->no_sampel berhasil dihapus oleh $nama.!";
                        return response()->json([
                            'message' => $this->resultx,
                            'kategori' => 2
                        ], 201);
                    }
                }
            } else if ($request->tip == 2) {
                $detail4 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('shift_pengambilan', $request->shift)->get();
                $shift = array();
                foreach ($detail as $dat) {
                    $shift[$dat['shift_pengambilan']][] = $dat;
                }
                if (count($shift) > 1) {
                    $detail4->each->delete();

                    $nama = $this->karyawan;
                    $this->resultx = "Fdl LH shift $request->shift di no sample $request->no_sampel berhasil dihapus oleh $nama.!";

                    // if($this->pin!=null){

                    //     $telegram = new Telegram();
                    //     $telegram->send($this->pin, $this->resultx);
                    // }
                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 1
                    ], 201);
                } else {
                    $data3 = DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
                    $detail4->each->delete();
                    $data3->delete();

                    $nama = $this->karyawan;
                    $this->resultx = "Fdl LH shift $request->shift di no sample $request->no_sampel berhasil dihapus oleh $nama.!";

                    // if($this->pin!=null){

                    //     $telegram = new Telegram();
                    //     $telegram->send($this->pin, $this->resultx);
                    // }

                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 2
                    ], 201);
                }
            } else if ($request->tip == 3) {
                $data4 = DataLapanganLingkunganHidup::where('id', $request->id)->first();
                $detail5 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->delete();
                $data4->delete();

                $nama = $this->karyawan;
                $this->resultx = "Fdl LH no sample $request->no_sampel berhasil dihapus oleh $nama.!";

                // if($this->pin!=null){
                //     $telegram = new Telegram();
                //     $telegram->send($this->pin, $this->resultx);
                // }

                return response()->json([
                    'message' => $this->resultx,
                ], 201);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // SINAR UV
    public function addSinarUv(Request $request)
    {
        DB::beginTransaction();
        try{
            $cek = DataLapanganSinarUV::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if ($cek) {
                return response()->json([
                    'message' => 'No sample sudah terinput di data lapangan sinar UV.!'
                ], 401);
            }
            if ($request->waktu == '') {
                return response()->json([
                    'message' => 'Jam pengambilan tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lok == '') {
                return response()->json([
                    'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                ], 401);
            }
            if ($request->foto_lain == '') {
                return response()->json([
                    'message' => 'Foto lain-lain tidak boleh kosong .!'
                ], 401);
            }
            $mata = array();
            $o = 1;
            for ($i = 0; $i < 5; $i++) {
                $mata[] = [
                    'Data-' . $o++ => $request->mata[$i],
                ];
            }
            $betis = array();
            $p = 1;
            for ($i = 0; $i < 5; $i++) {
                $betis[] = [
                    'Data-' . $p++ => $request->betis[$i],
                ];
            }
            $q = 1;
            $siku = array();
            for ($i = 0; $i < 5; $i++) {
                $siku[] = [
                    'Data-' . $q++ => $request->siku[$i],
                ];
            }

            $data = new DataLapanganSinarUV();
            $data->no_sampel                 = strtoupper(trim($request->no_sample));
            if ($request->keterangan_4 != '') $data->keterangan            = $request->keterangan_4;
            if ($request->keterangan_2 != '') $data->keterangan_2            = $request->keterangan_2;
            // if ($request->posisi != '') $data->titik_koordinat             = $request->posisi;
            $data->titik_koordinat = $request->posisi ?? '-';
            if ($request->lat != '') $data->latitude                            = $request->lat;
            if ($request->longi != '') $data->longitude                        = $request->longi;
            if ($request->categori != '') $data->kategori_3                = $request->categori;
            if ($request->lok != '') $data->lokasi                         = $request->lok;
            if ($request->aktivitas != '') $data->aktivitas_pekerja                  = $request->aktivitas;
            if ($request->sumber != '') $data->sumber_radiasi                        = $request->sumber;
            if ($request->paparan != '') $data->waktu_pemaparan                        = $request->paparan;
            if ($request->waktu != '') $data->waktu_pengukuran                        = $request->waktu;
            if ($request->mata != '') $data->mata         = json_encode($mata);
            if ($request->siku != '') $data->siku         = json_encode($siku);
            if ($request->betis != '') $data->betis        = json_encode($betis);

            if ($request->permis != '') $data->permission                      = $request->permis;
            if ($request->foto_lok != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lok, 1, $this->user_id);
            if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
            $data->created_by                     = $this->karyawan;
            $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
            // $data->is_rejected = false;
            $data->save();

            DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $nama = $this->karyawan;
            $this->resultx = "Data Sampling SINAR UV Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json([
                'message' => $e.getMessage(),
                'line' => $e.getLine(),
                'code' => $e.getCode()
            ]);
        }
    }

    public function indexSinarUv(Request $request)
    {
        $data = DataLapanganSinarUV::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approveSinarUv(Request $request)
    {
        if (isset($request->id) && $request->id != null) {

            $data = DataLapanganSinarUV::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve  = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            return response()->json([
                'message' => 'Data has ben Approved',
                'master_kategori' => 1
            ], 200);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function detailSinarUv(Request $request)
    {
        $data = DataLapanganSinarUV::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample lapangan Getaran success';

        return response()->json([
            'id'             => $data->id,
            'no_sample'      => $data->no_sampel,
            'no_order'       => $data->detail->no_order,
            'categori'       => explode('-', $data->detail->kategori_3)[1],
            'sampler'        => $data->created_by,
            'corp'           => $data->detail->nama_perusahaan,
            'keterangan'     => $data->keterangan,
            'keterangan_2'   => $data->keterangan_2,
            'lat'            => $data->latitude,
            'long'           => $data->longitude,
            // 'parameter'           => $data->parameter,

            'lokasi'         => $data->lokasi,
            'aktivitas'      => $data->aktivitas_pekerja,
            'sumber'         => $data->sumber_radiasi,
            'paparan'        => $data->waktu_pemaparan,
            'waktu'          => $data->waktu_pengukuran,
            'mata'           => $data->mata,
            'siku'           => $data->siku,
            'betis'          => $data->betis,

            'tikoor'         => $data->titik_koordinat,
            'foto_lok'       => $data->foto_lokasi_sampel,
            'foto_lain'      => $data->foto_lain,
            'coor'           => $data->titik_koordinat,
            'status'         => '200'
        ], 200);
    }

    public function deleteSinarUv(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganSinarUV::where('id', $request->id)->first();
            $cek2 = DataLapanganSinarUv::where('no_sampel', $data->no_sampel)->get();
            if ($cek2->count() > 1) {
                $data->delete();
                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 4
                ], 201);
            } else {
                $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
                $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
                $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
                if (is_file($foto_lok)) {
                    unlink($foto_lok);
                }
                if (is_file($foto_kon)) {
                    unlink($foto_kon);
                }
                if (is_file($foto_lain)) {
                    unlink($foto_lain);
                }
                $data->delete();

                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 4
                ], 201);
            }
            $no_sample = $data->no_sampel;


        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // MEDAN LM
    public function addMedanLm(Request $request)
    {
        DB::beginTransaction();
        try{
            $fdl = DataLapanganMedanLM::where('no_sampel', $request->no_sample)->where('parameter', $request->parameter)->first();
            if ($fdl) {
                return response()->json([
                    'message' => 'No Sample dengan parameter ' . $request->parameter . ' Sudah terinput'
                ], 401);
            } else {
                if ($request->waktu == '') {
                    return response()->json([
                        'message' => 'Jam pengambilan tidak boleh kosong .!'
                    ], 401);
                }
                if ($request->foto_lok == '') {
                    return response()->json([
                        'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                    ], 401);
                }
                if ($request->foto_lain == '') {
                    return response()->json([
                        'message' => 'Foto lain-lain tidak boleh kosong .!'
                    ], 401);
                }
                $parrt = json_decode($request->parameter);
                foreach ($parrt as $k => $v) {
                    if ($v == 'Medan Magnit Statis' || $v == 'Medan Magnet') {
                        if ($request->magnet3 != '') {
                            $magnet3 = array();
                            $o = 1;
                            for ($i = 0; $i < 5; $i++) {
                                $magnet3[] = [
                                    'Data-' . $o++ => $request->magnet3[$i],
                                ];
                            }
                            $magnet30 = array();
                            $p = 1;
                            for ($i = 0; $i < 5; $i++) {
                                $magnet30[] = [
                                    'Data-' . $p++ => $request->magnet30[$i],
                                ];
                            }
                            $q = 1;
                            $magnet100 = array();
                            for ($i = 0; $i < 5; $i++) {
                                $magnet100[] = [
                                    'Data-' . $q++ => $request->magnet100[$i],
                                ];
                            }
                            $data = new DataLapanganMedanLM();
                            $data->no_sampel                 = strtoupper(trim($request->no_sample));
                            if ($request->keterangan_4 != '') $data->keterangan            = $request->keterangan_4;
                            if ($request->keterangan_2 != '') $data->keterangan_2            = $request->keterangan_2;
                            // if ($request->posisi != '') $data->titik_koordinat             = $request->posisi;
                            $data->titik_koordinat = $request->posisi ?? '-';
                            $data->parameter                = $v;
                            if ($request->lat != '') $data->latitude                            = $request->lat;
                            if ($request->longi != '') $data->longitude                        = $request->longi;
                            if ($request->categori != '') $data->kategori_3                = $request->categori;
                            if ($request->lok != '') $data->lokasi                         = $request->lok;
                            if ($request->aktivitas != '') $data->aktivitas_pekerja                  = $request->aktivitas;
                            if ($request->sumber != '') $data->sumber_radiasi                        = $request->sumber;
                            if ($request->paparan != '') $data->waktu_pemaparan                        = $request->paparan;
                            if ($request->waktu != '') $data->waktu_pengukuran                        = $request->waktu;
                            if ($request->magnet3 != '') $data->magnet_3     = json_encode($magnet3);
                            if ($request->magnet30 != '') $data->magnet_30    = json_encode($magnet30);
                            if ($request->magnet100 != '') $data->magnet_100   = json_encode($magnet100);

                            if ($request->frek3 != '') $data->frekuensi_3       = $request->frek3;
                            if ($request->frek30 != '') $data->frekuensi_30      = $request->frek30;
                            if ($request->frek100 != '') $data->frekuensi_100      = $request->frek100;

                            if ($request->permis != '') $data->permission                      = $request->permis;
                            if ($request->foto_lok != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lok, 1, $this->user_id);
                            if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                            $data->created_by                     = $this->karyawan;
                            $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                            $data->save();
                        }
                    } else if ($v == 'Medan Listrik') {
                        if ($request->listrik3 != '') {
                            $listrik3 = array();
                            $r = 1;
                            for ($i = 0; $i < 5; $i++) {
                                $listrik3[] = [
                                    'Data-' . $r++ => $request->listrik3[$i],
                                ];
                            }
                            $s = 1;
                            $listrik30 = array();
                            for ($i = 0; $i < 5; $i++) {
                                $listrik30[] = [
                                    'Data-' . $s++ => $request->listrik30[$i],
                                ];
                            }
                            $t = 1;
                            $listrik100 = array();
                            for ($i = 0; $i < 5; $i++) {
                                $listrik100[] = [
                                    'Data-' . $t++ => $request->listrik100[$i],
                                ];
                            }
                            $data = new DataLapanganMedanLM();
                            $data->no_sampel                 = strtoupper(trim($request->no_sample));
                            if ($request->keterangan_4 != '') $data->keterangan            = $request->keterangan_4;
                            if ($request->keterangan_2 != '') $data->keterangan_2            = $request->keterangan_2;
                            // if ($request->posisi != '') $data->titik_koordinat             = $request->posisi;
                            $data->titik_koordinat = $request->posisi ?? '-';
                            $data->parameter                = $v;

                            if ($request->lat != '') $data->latitude                            = $request->lat;
                            if ($request->longi != '') $data->longitude                        = $request->longi;
                            if ($request->categori != '') $data->kategori_3                = $request->categori;
                            if ($request->lok != '') $data->lokasi                         = $request->lok;
                            if ($request->aktivitas != '') $data->aktivitas_pekerja                  = $request->aktivitas;
                            if ($request->sumber != '') $data->sumber_radiasi                        = $request->sumber;
                            if ($request->paparan != '') $data->waktu_pemaparan                        = $request->paparan;
                            if ($request->waktu != '') $data->waktu_pengukuran                        = $request->waktu;

                            if ($request->listrik3 != '') $data->listrik_3    = json_encode($listrik3);
                            if ($request->listrik30 != '') $data->listrik_30   = json_encode($listrik30);
                            if ($request->listrik100 != '') $data->listrik_100  = json_encode($listrik100);

                            if ($request->frek3 != '') $data->frekuensi_3       = $request->frek3;
                            if ($request->frek30 != '') $data->frekuensi_30      = $request->frek30;
                            if ($request->frek100 != '') $data->frekuensi_100      = $request->frek100;

                            if ($request->permis != '') $data->permission                      = $request->permis;
                            if ($request->foto_lok != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lok, 1, $this->user_id);
                            if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                            $data->created_by                     = $this->karyawan;
                            $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                            $data->save();
                        }
                    } else {
                        if ($request->magnet3 != '' && $request->listrik3 != '') {
                            $magnet3 = array();
                            $o = 1;
                            for ($i = 0; $i < 5; $i++) {
                                $magnet3[] = [
                                    'Data-' . $o++ => $request->magnet3[$i],
                                ];
                            }
                            $magnet30 = array();
                            $p = 1;
                            for ($i = 0; $i < 5; $i++) {
                                $magnet30[] = [
                                    'Data-' . $p++ => $request->magnet30[$i],
                                ];
                            }
                            $q = 1;
                            $magnet100 = array();
                            for ($i = 0; $i < 5; $i++) {
                                $magnet100[] = [
                                    'Data-' . $q++ => $request->magnet100[$i],
                                ];
                            }
                            $listrik3 = array();
                            $r = 1;
                            for ($i = 0; $i < 5; $i++) {
                                $listrik3[] = [
                                    'Data-' . $r++ => $request->listrik3[$i],
                                ];
                            }
                            $s = 1;
                            $listrik30 = array();
                            for ($i = 0; $i < 5; $i++) {
                                $listrik30[] = [
                                    'Data-' . $s++ => $request->listrik30[$i],
                                ];
                            }
                            $t = 1;
                            $listrik100 = array();
                            for ($i = 0; $i < 5; $i++) {
                                $listrik100[] = [
                                    'Data-' . $t++ => $request->listrik100[$i],
                                ];
                            }
                            $data = new DataLapanganMedanLM();
                            $data->no_sampel                 = strtoupper(trim($request->no_sample));
                            if ($request->keterangan_4 != '') $data->keterangan            = $request->keterangan_4;
                            if ($request->keterangan_2 != '') $data->keterangan_2            = $request->keterangan_2;
                            // if ($request->posisi != '') $data->titik_koordinat             = $request->posisi;
                            $data->titik_koordinat = $request->posisi ?? '-';
                            $data->parameter                = $v;

                            if ($request->lat != '') $data->latitude                            = $request->lat;
                            if ($request->longi != '') $data->longitude                        = $request->longi;
                            if ($request->categori != '') $data->kategori_3                = $request->categori;
                            if ($request->lok != '') $data->lokasi                         = $request->lok;
                            if ($request->aktivitas != '') $data->aktivitas_pekerja                  = $request->aktivitas;
                            if ($request->sumber != '') $data->sumber_radiasi                        = $request->sumber;
                            if ($request->paparan != '') $data->waktu_pemaparan                        = $request->paparan;
                            if ($request->waktu != '') $data->waktu_pengukuran                        = $request->waktu;
                            if ($request->magnet3 != '') $data->magnet_3     = json_encode($magnet3);
                            if ($request->magnet30 != '') $data->magnet_30    = json_encode($magnet30);
                            if ($request->magnet100 != '') $data->magnet_100   = json_encode($magnet100);
                            if ($request->listrik3 != '') $data->listrik_3    = json_encode($listrik3);
                            if ($request->listrik30 != '') $data->listrik_30   = json_encode($listrik30);
                            if ($request->listrik100 != '') $data->listrik_100  = json_encode($listrik100);

                            if ($request->frek3 != '') $data->frekuensi_3       = $request->frek3;
                            if ($request->frek30 != '') $data->frekuensi_30      = $request->frek30;
                            if ($request->frek100 != '') $data->frekuensi_100      = $request->frek100;

                            if ($request->permis != '') $data->permission                      = $request->permis;
                            if ($request->foto_lok != '') $data->foto_lokasi_sampel        = self::convertImg($request->foto_lok, 1, $this->user_id);
                            if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                            $data->created_by                     = $this->karyawan;
                            $data->created_at                    = Carbon::now()->format('Y-m-d H:i:s');
                            $data->save();
                        }
                    };
                }

                DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                $nama = $this->karyawan;
                $this->resultx = "Data Sampling LISTRIK MAGNET Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

                DB::commit();
                return response()->json([
                    'message' => $this->resultx
                ], 200);
            }
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'code' => $e->getCode()
            ]);
        }
    }

    public function indexMedanLm(Request $request)
    {
        $data = DataLapanganMedanLM::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approveMedanLm(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganMedanLM::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Medan LM dengan No sample $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function detailMedanLm(Request $request)
    {
        $data = DataLapanganMedanLM::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample Medan LM success';

        return response()->json([
            'id'             => $data->id,
            'no_sample'      => $data->no_sampel,
            'no_order'       => $data->detail->no_order,
            'categori'       => explode('-', $data->detail->kategori_3)[1],
            'sampler'        => $data->created_by,
            'corp'           => $data->detail->nama_perusahaan,
            'keterangan'     => $data->keterangan,
            'keterangan_2'   => $data->keterangan_2,
            'lat'            => $data->latitude,
            'long'           => $data->longitude,

            'lokasi'         => $data->lokasi,
            'parameter'      => $data->parameter,
            'aktivitas'      => $data->aktivitas_pekerja,
            'sumber'         => $data->sumber_radiasi,
            'paparan'        => $data->waktu_pemaparan,
            'waktu'          => $data->waktu_pengukuran,
            'magnet_3'       => $data->magnet_3,
            'magnet_30'      => $data->magnet_30,
            'magnet_100'     => $data->magnet_100,
            'listrik_3'      => $data->listrik_3,
            'listrik_30'     => $data->listrik_30,
            'listrik_100'    => $data->listrik_100,
            'frek_3'         => $data->frekuensi_3,
            'frek_30'        => $data->frekuensi_30,
            'frek_100'        => $data->frekuensi_100,

            'tikoor'         => $data->titik_koordinat,
            'foto_lok'       => $data->foto_lokasi_sampel,
            'foto_lain'      => $data->foto_lain,
            'coor'           => $data->titik_koordinat,
            'status'         => '200'
        ], 200);
    }

    public function deleteMedanLm(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganMedanLM::where('id', $request->id)->first();
            $cek2 = DataLapanganMedanLM::where('no_sampel', $data->no_sampel)->get();
            if ($cek2->count() > 1) {
                $data->delete();
                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 4
                ], 201);
            } else {
                $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
                $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
                $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
                if (is_file($foto_lok)) {
                    unlink($foto_lok);
                }
                if (is_file($foto_kon)) {
                    unlink($foto_kon);
                }
                if (is_file($foto_lain)) {
                    unlink($foto_lain);
                }
                $data->delete();

                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 4
                ], 201);
            }
            $no_sample = $data->no_sampel;

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Medan LM dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // PARTIKULAT METER
    public function addPartikulatMeter(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validasi Input
            $requiredFields = [
                'waktu' => 'Jam pengambilan tidak boleh kosong .!',
                'foto_lok' => 'Foto lokasi sampling tidak boleh kosong .!',
                'foto_lain' => 'Foto lain-lain tidak boleh kosong .!'
            ];
            foreach ($requiredFields as $field => $message) {
                if (empty($request->$field)) {
                    return response()->json(['message' => $message], 401);
                }
            }

            // Pengecekan shift dan parameter
            if ($request->param != null) {
                $nilai_array = []; // Array untuk menyimpan shift yang sudah ada
                foreach ($request->param as $en => $ab) {
                    if ($request->shift[$en] !== "Sesaat") {
                        $cek = DataLapanganPartikulatMeter::where('no_sampel', strtoupper(trim($request->no_sample)))
                                                        ->where('parameter', $ab)
                                                        ->get();

                        foreach ($cek as $key => $value) {
                            // Jika shift yang diminta sudah ada, skip penyimpanan
                            if ($value->shift_pengambilan == 'Sesaat' && $request->shift[$en] == $value->shift_pengambilan) {
                                return response()->json(['message' => 'Shift sesaat sudah terinput di no sample ini .!'], 401);
                            }

                            // Proses durasi untuk pengecekan
                            $durasi = explode("-", $value->shift_pengambilan)[1];
                            $nilai_array[$key] = str_replace('"', "", $durasi);

                            // Jika parameter yang sama dan shift sudah ada, tidak perlu disimpan lagi
                            if (in_array($request->shift[$en], $nilai_array)) {
                                continue 2;  // Skip jika shift sudah ada
                            }
                        }
                    }
                }
            }

            // Proses Input Data
            if ($request->param != null) {
                foreach ($request->param as $in => $a) {
                    // Skip jika parameter sudah ada dengan shift yang diminta
                    if (in_array($request->shift[$in], $nilai_array)) {
                        continue;  // Skip parameter ini jika sudah ada
                    }

                    // Pengukuran data
                    $pengukuran = [
                        'data-1' => $request->dat1[$in],
                        'data-2' => $request->dat2[$in],
                        'data-3' => $request->dat3[$in],
                        'data-4' => $request->dat4[$in],
                        'data-5' => $request->dat5[$in],
                    ];

                    // Tentukan shift pengambilan berdasarkan kategori uji
                    $shift_peng = $this->getShiftPengambilan($request->kateg_uji[$in], $request->shift[$in]);

                    // Buat data untuk disimpan
                    $data = new DataLapanganPartikulatMeter();
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                    $data->keterangan = $request->keterangan_4 ?: null;
                    $data->keterangan_2 = $request->keterangan_2 ?: null;
                    $data->titik_koordinat = $request->posisi ?: '-';
                    $data->latitude = $request->lat ?: null;
                    $data->longitude = $request->longi ?: null;
                    $data->kategori_3 = $request->categori ?: null;
                    $data->parameter = $a;
                    $data->lokasi = $request->lok ?: null;
                    $data->waktu_pengukuran = $request->waktu;
                    $data->suhu = $request->suhu ?: null;
                    $data->kelembapan = $request->kelem ?: null;
                    $data->tekanan_udara = $request->tekU ?: null;
                    $data->shift_pengambilan = $shift_peng;
                    $data->pengukuran = json_encode($pengukuran);
                    $data->permission = $request->permis ?: null;
                    $data->foto_lokasi_sampel = $request->foto_lok ? self::convertImg($request->foto_lok, 1, $this->user_id) : null;
                    $data->foto_lain = $request->foto_lain ? self::convertImg($request->foto_lain, 3, $this->user_id) : null;
                    $data->created_by = $this->karyawan;
                    $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                }
            }

            // Update Order Detail
            DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $this->resultx = "Data Sampling PARTICULATE MATTER Dengan No Sample {$request->no_sample} berhasil disimpan oleh {$this->karyawan}";

            // if ($this->pin != null) {
            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $this->resultx);
            // }

            DB::commit();

            return response()->json(['message' => $this->resultx], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * Helper function to get shift pengambilan string
     */
    private function getShiftPengambilan($kateg_uji, $shift) {
        if (in_array($kateg_uji, ['24 Jam', '8 Jam', '6 Jam'])) {
            return "$kateg_uji-" . json_encode($shift);
        }
        return 'Sesaat';
    }

    public function indexPartikulatMeter(Request $request)
    {
        $data = DataLapanganPartikulatMeter::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function approvePartikulatMeter(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganPartikulatMeter::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve     = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Partikulat Meter dengan No sampel $no_sample Telah di Approve oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

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

    public function detailPartikulatMeter(Request $request)
    {
        $data = DataLapanganPartikulatMeter::with('detail')->where('id', $request->id)->first();
        $this->resultx = 'get Detail sample Medan LM success';

        return response()->json([
            'id'             => $data->id,
            'no_sample'      => $data->no_sampel,
            'no_order'       => $data->detail->no_order,
            'categori'       => explode('-', $data->detail->kategori_3)[1],
            'sampler'        => $data->created_by,
            'corp'           => $data->detail->nama_perusahaan,
            'keterangan'     => $data->keterangan,
            'keterangan_2'   => $data->keterangan_2,
            'lat'            => $data->latitude,
            'long'           => $data->longitude,

            'kateg_pm'       => $data->parameter,
            'lokasi'         => $data->lokasi,
            'waktu'          => $data->waktu_pengukuran,
            'shift'          => $data->shift_pengambilan,
            'suhu'           => $data->suhu,
            'kelembapan'     => $data->kelembapan,
            'tekanan_u'      => $data->tekanan_udara,
            'pengukuran'     => $data->pengukuran,

            'tikoor'         => $data->titik_koordinat,
            'foto_lok'       => $data->foto_lokasi_sampel,
            'foto_lain'      => $data->foto_lain,
            'coor'           => $data->titik_koordinat,
            'status'         => '200'
        ], 200);
    }

    public function deletePartikulatMeter(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganPartikulatMeter::where('id', $request->id)->first();
            $cek2 = DataLapanganPartikulatMeter::where('no_sampel', $data->no_sampel)->get();
            if ($cek2->count() > 1) {
                $data->delete();
                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 4
                ], 201);
            } else {
                $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
                $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
                $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
                if (is_file($foto_lok)) {
                    unlink($foto_lok);
                }
                if (is_file($foto_kon)) {
                    unlink($foto_kon);
                }
                if (is_file($foto_lain)) {
                    unlink($foto_lain);
                }
                $data->delete();

                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 4
                ], 201);
            }
            $no_sample = $data->no_sampel;

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL Partikulat Meter dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    // ERGONOMI
    
    public function addErgonomi(Request $request)
    {
        $po = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
        if ($request->method == 1) {
            DB::beginTransaction();
            try{
                $inputs = $request->all();

                $bagian_kiri = [
                    "bahu_kiri", "leher_atas", "pinggul", "lengan_atas_kiri", "siku_kiri", "lengan_bawah_kiri", "pergelangan_tangan_kiri",
                    "tangan_kiri", "paha_kiri", "lutut_kiri", "betis_kiri", "pergelangan_kaki_kiri", "kaki_kiri"
                ];

                $bagian_kanan = [
                    "bahu_kanan", "tengkuk", "punggung", "pinggang", "pantat", "lengan_atas_kanan", "siku_kanan", "lengan_bawah_kanan", "pergelangan_tangan_kanan",
                    "tangan_kanan", "paha_kanan", "lutut_kanan", "betis_kanan", "pergelangan_kaki_kanan", "kaki_kanan"
                ];

                function formatArrayKeysUnderscore($array) {
                    $result = [];

                    foreach ($array as $key => $value) {
                        // Ubah key ke lowercase dan ganti spasi dengan underscore
                        $newKey = strtolower(str_replace(' ', '_', $key));
                        $result[$newKey] = $value;
                    }

                    return $result;
                }

                // Ambil data dari request
                $sebelum = $request->sebelum_kerja ?? [];
                $setelah = $request->setelah_kerja ?? [];

                // Format key-nya
                $sebelum = formatArrayKeysUnderscore($sebelum);
                $setelah = formatArrayKeysUnderscore($setelah);


                /**
                * Proses skor keluhan berdasarkan bagian kiri dan kanan tubuh.
                */
                function prosesSkor(array $data, array $bagian_kiri, array $bagian_kanan): array {
                    $skor_kiri = 0;
                    $skor_kanan = 0;
                    $result = [];

                    foreach ($data as $bagian => $nilai) {
                        if (!is_string($nilai) || strpos($nilai, '-') === false) {
                            continue; // Skip data yang tidak sesuai format
                        }

                        [$skor, $keterangan] = explode('-', $nilai, 2);
                        $skor = (int) trim($skor);

                        // Gunakan nama bagian dalam format snake_case
                        $bagian_key = strtolower(str_replace(' ', '_', $bagian));
                        $result["skor_{$bagian_key}"] = $skor;

                        if (in_array($bagian, $bagian_kiri)) {
                            $skor_kiri += $skor;
                        } elseif (in_array($bagian, $bagian_kanan)) {
                            $skor_kanan += $skor;
                        }
                    }

                    $total_skor = $skor_kiri + $skor_kanan;

                    if ($total_skor <= 20) {
                        $tingkat_risiko = 0;
                        $kategori_risiko = 'Rendah';
                        $tindakan = 'Belum diperlukan adanya tindakan perbaikan';
                    } elseif ($total_skor <= 41) {
                        $tingkat_risiko = 1;
                        $kategori_risiko = 'Sedang';
                        $tindakan = 'Mungkin diperlukan tindakan dikemudian hari';
                    } elseif ($total_skor <= 62) {
                        $tingkat_risiko = 2;
                        $kategori_risiko = 'Tinggi';
                        $tindakan = 'Diperlukan tindakan segera';
                    } elseif ($total_skor <= 84) {
                        $tingkat_risiko = 3;
                        $kategori_risiko = 'Sangat Tinggi';
                        $tindakan = 'Diperlukan tindakan menyeluruh sesegera mungkin';
                    } else {
                        $tingkat_risiko = null;
                        $kategori_risiko = 'Tidak Diketahui';
                        $tindakan = '-';
                    }

                    return array_merge($result, [
                        'skor_kiri' => $skor_kiri,
                        'skor_kanan' => $skor_kanan,
                        'total_skor' => $total_skor,
                        'tingkat_risiko' => $tingkat_risiko,
                        'kategori_risiko' => $kategori_risiko,
                        'tindakan_perbaikan' => $tindakan,
                    ]);
                }


                // Proses sebelum dan setelah kerja
                $hasil_sebelum = prosesSkor($sebelum, $bagian_kiri, $bagian_kanan);
                $hasil_setelah = prosesSkor($setelah, $bagian_kiri, $bagian_kanan);

                // Simpan hasil pengukuran
                $pengukuran = [
                    'sebelum' => $hasil_sebelum,
                    'setelah' => $hasil_setelah
                ];

                
                $data = new DataLapanganErgonomi();
                if ($request->no_order != '')
                    $data->no_order = $request->no_order;
                if (strtoupper(trim($request->no_sample)) != '')
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                if ($request->pekerja != '')
                    $data->nama_pekerja = $request->pekerja;
                if ($request->divisi != '')
                    $data->divisi = $request->divisi;
                if ($request->usia != '')
                    $data->usia = $request->usia;
                $data->lama_kerja = json_encode($request->year . " Tahun" . ", " . $request->month . " Bulan");
                if ($request->kelamin != '')
                    $data->jenis_kelamin = $request->kelamin;
                if ($request->waktu_bekerja != '')
                    $data->waktu_bekerja = $request->waktu_bekerja;
                if ($request->aktivitas != '')
                    $data->aktivitas = $request->aktivitas;
                if ($request->foto_samping_kiri != '')
                    $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
                if ($request->foto_samping_kanan != '')
                    $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
                if ($request->foto_depan != '')
                    $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
                if ($request->foto_belakang != '')
                    $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
                if ($request->permis != '')
                    $data->permission = $request->permis;
                $data->method = $request->method;
                $data->sebelum_kerja = json_encode($sebelum);
                $data->setelah_kerja = json_encode($setelah);
                $data->pengukuran = json_encode($pengukuran);
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
                

                // UPDATE ORDER DETAIL
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->where('kategori_3', 'LIKE', '%27-%')
                    ->orWhere('kategori_3', 'LIKE', '%53-%')
                    ->where('parameter', 'LIKE', '%Ergonomi%')
                    ->update([
                        'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode()
                ], 401);
            }
        } else if ($request->method == 2) {
            DB::beginTransaction();
            try{
                $data = [
                    "skor_A" => $request->skor_A,
                    "skor_B" => $request->skor_B,
                    "skor_C" => $request->skor_C
                ];

                function extractScore($value) {
                    if (is_string($value) && preg_match('/^(\d+)-/', $value, $matches)) {
                        return (int) $matches[1];
                    }
                    return (int) $value; // karena kamu kirim int, bukan string "1-..."
                }

                // MATCHUP DATA
                // Tabel A dari gambar
                $tableA = [
                    1 => [ // badan 1
                        1 => [1 => 1, 2 => 2, 3 => 3, 4 => 4],
                        2 => [1 => 1, 2 => 2, 3 => 3, 4 => 4],
                        3 => [1 => 3, 2 => 3, 3 => 5, 4 => 6],
                    ],
                    2 => [ // badan 2
                        1 => [1 => 2, 2 => 3, 3 => 4, 4 => 5],
                        2 => [1 => 3, 2 => 4, 3 => 5, 4 => 6],
                        3 => [1 => 4, 2 => 5, 3 => 6, 4 => 7],
                    ],
                    3 => [ // badan 3
                        1 => [1 => 2, 2 => 4, 3 => 5, 4 => 6],
                        2 => [1 => 4, 2 => 5, 3 => 6, 4 => 7],
                        3 => [1 => 5, 2 => 6, 3 => 7, 4 => 8],
                    ],
                    4 => [ // badan 4
                        1 => [1 => 3, 2 => 5, 3 => 6, 4 => 7],
                        2 => [1 => 5, 2 => 6, 3 => 7, 4 => 8],
                        3 => [1 => 6, 2 => 7, 3 => 8, 4 => 9],
                    ],
                    5 => [ // badan 5
                        1 => [1 => 4, 2 => 6, 3 => 7, 4 => 8],
                        2 => [1 => 6, 2 => 7, 3 => 8, 4 => 9],
                        3 => [1 => 7, 2 => 8, 3 => 9, 4 => 9],
                    ],
                ];


                $tableB = [
                    1 => [ // lengan_atas = 1
                        1 => [1 => 1, 2 => 2, 3 => 2],
                        2 => [1 => 1, 2 => 2, 3 => 3],
                    ],
                    2 => [
                        1 => [1 => 1, 2 => 2, 3 => 3],
                        2 => [1 => 2, 2 => 3, 3 => 4],
                    ],
                    3 => [
                        1 => [1 => 3, 2 => 4, 3 => 5],
                        2 => [1 => 4, 2 => 5, 3 => 5],
                    ],
                    4 => [
                        1 => [1 => 4, 2 => 5, 3 => 5],
                        2 => [1 => 5, 2 => 6, 3 => 7],
                    ],
                    5 => [
                        1 => [1 => 6, 2 => 7, 3 => 8],
                        2 => [1 => 7, 2 => 8, 3 => 8],
                    ],
                    6 => [
                        1 => [1 => 7, 2 => 8, 3 => 8],
                        2 => [1 => 8, 2 => 9, 3 => 9],
                    ],
                ];

                $tableC = [
                    1 => [1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 3, 6 => 3, 7 => 4, 8 => 5, 9 => 6, 10 => 7, 11 => 7, 12 => 7],
                    2 => [1 => 1, 2 => 2, 3 => 2, 4 => 3, 5 => 4, 6 => 4, 7 => 5, 8 => 6, 9 => 6, 10 => 7, 11 => 7, 12 => 8],
                    3 => [1 => 2, 2 => 3, 3 => 3, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 7, 10 => 8, 11 => 8, 12 => 8],
                    4 => [1 => 3, 2 => 4, 3 => 4, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 8, 10 => 9, 11 => 9, 12 => 9],
                    5 => [1 => 4, 2 => 4, 3 => 4, 4 => 5, 5 => 6, 6 => 7, 7 => 8, 8 => 8, 9 => 9, 10 => 9, 11 => 9, 12 => 9],
                    6 => [1 => 6, 2 => 6, 3 => 6, 4 => 7, 5 => 8, 6 => 8, 7 => 9, 8 => 9, 9 => 10, 10 => 10, 11 => 10, 12 => 10],
                    7 => [1 => 7, 2 => 7, 3 => 7, 4 => 8, 5 => 9, 6 => 9, 7 => 9, 8 => 10, 9 => 10, 10 => 11, 11 => 11, 12 => 11],
                    8 => [1 => 8, 2 => 8, 3 => 8, 4 => 9, 5 => 10, 6 => 10, 7 => 10, 8 => 10, 9 => 10, 10 => 11, 11 => 11, 12 => 11],
                    9 => [1 => 9, 2 => 9, 3 => 9, 4 => 10, 5 => 10, 6 => 10, 7 => 11, 8 => 11, 9 => 11, 10 => 12, 11 => 12, 12 => 12],
                    10 => [1 => 10, 2 => 10, 3 => 10, 4 => 11, 5 => 11, 6 => 11, 7 => 11, 8 => 12, 9 => 12, 10 => 12, 11 => 12, 12 => 12],
                    11 => [1 => 11, 2 => 11, 3 => 11, 4 => 11, 5 => 12, 6 => 12, 7 => 12, 8 => 12, 9 => 12, 10 => 12, 11 => 12, 12 => 12],
                    12 => [1 => 12, 2 => 12, 3 => 12, 4 => 12, 5 => 12, 6 => 12, 7 => 12, 8 => 12, 9 => 12, 10 => 12, 11 => 12, 12 => 12],
                ];


                $hasilPerBagian = [];

                foreach ($data as $kategori => $bagian) {
                    foreach ($bagian as $key => $item) {
                        $hasilPerBagian[$kategori][$key] = 0;

                        if (is_array($item)) {
                            foreach ($item as $subKey => $val) {
                                if ($kategori == 'skor_B' && $key == 'lengan_atas' && $subKey == 'tambahan_3') {
                                    $hasilPerBagian[$kategori][$key] -= extractScore($val); // dikurang
                                } else {
                                    $hasilPerBagian[$kategori][$key] += extractScore($val);
                                }
                            }
                        } else {
                            $hasilPerBagian[$kategori][$key] += extractScore($item);
                        }
                    }
                }

                // dd($hasilPerBagian);
                

                // ✅ Ambil nilai skor_A dari tabel
                $leher = $hasilPerBagian['skor_A']['leher'] ?? 0;
                $badan = $hasilPerBagian['skor_A']['badan'] ?? 0;
                $kaki  = $hasilPerBagian['skor_A']['kaki'] ?? 0;
                $beban = $hasilPerBagian['skor_A']['beban'] ?? 0;

                $skorA_dari_tabel = $tableA[$badan][$leher][$kaki] ?? 0;
                $totalSkorA = $skorA_dari_tabel + $beban;

                // ✅ Ambil nilai skor_B dari tabel
                $lengan_atas  = $hasilPerBagian['skor_B']['lengan_atas'] ?? 0;
                $lengan_bawah = $hasilPerBagian['skor_B']['lengan_bawah'] ?? 0;
                $pergelangan  = $hasilPerBagian['skor_B']['pergelangan_tangan'] ?? 0;
                $pegangan     = $hasilPerBagian['skor_B']['pegangan'] ?? 0;

                $skorB_dari_tabel = $tableB[$lengan_atas][$lengan_bawah][$pergelangan] ?? 0;
                $totalSkorB = $skorB_dari_tabel + $pegangan;

                // ✅ Ambil nilai skor_C dari tabel
                $aktivitasi_otot = $hasilPerBagian['skor_C']['aktivitas_otot'] ?? 0;
                $skorC_dari_tabel = $tableC[$totalSkorA][$totalSkorB] ?? 0;
                $totalSkorC = $aktivitasi_otot + $skorC_dari_tabel;

                $pengukuran = [ 
                    "skor_A" => $request->skor_A,
                    'skor_leher' => $leher,
                    'skor_badan' => $badan,
                    'skor_kaki' => $kaki,
                    'skor_beban' => $beban,
                    'nilai_tabel_a' => $skorA_dari_tabel,
                    'total_skor_a' => $totalSkorA,
                    "skor_B" => $request->skor_B,
                    'skor_lengan_atas' => $lengan_atas,
                    'skor_lengan_bawah' => $lengan_bawah,
                    'skor_pergelangan_tangan' => $pergelangan,
                    'skor_pegangan' => $pegangan,
                    'nilai_tabel_b' => $skorB_dari_tabel,
                    'total_skor_b' => $totalSkorB,
                    "skor_C" => $request->skor_C,
                    'skor_aktivitas_otot' => $aktivitasi_otot,
                    'nilai_tabel_c' => $skorC_dari_tabel,
                    'final_skor_reba' => $totalSkorC
                ];
                // dd($pengukuran);

                $data = new DataLapanganErgonomi();
                if ($request->no_order != '')
                    $data->no_order = $request->no_order;
                
                if (strtoupper(trim($request->no_sample)) != '')
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                if ($request->pekerja != '')
                    $data->nama_pekerja = $request->pekerja;
                if ($request->divisi != '')
                    $data->divisi = $request->divisi;
                if ($request->usia != '')
                    $data->usia = $request->usia;
                $data->lama_kerja = json_encode($request->year . " Tahun" . ", " . $request->month . " Bulan");
                if ($request->kelamin != '')
                    $data->jenis_kelamin = $request->kelamin;
                if ($request->waktu_bekerja != '')
                    $data->waktu_bekerja = $request->waktu_bekerja;
                if ($request->aktivitas != '')
                    $data->aktivitas = $request->aktivitas;
                $data->method = $request->method;

                $data->pengukuran = json_encode($pengukuran);

                if ($request->foto_samping_kiri != '')
                    $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
                if ($request->foto_samping_kanan != '')
                    $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
                if ($request->foto_depan != '')
                    $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
                if ($request->foto_belakang != '')
                    $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
                $data->aktivitas_ukur = $request->aktivitas_ukur;
                $data->permission = $request->permis;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // UPDATE ORDER DETAIL
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->where('kategori_3', 'LIKE', '%27-%')
                    ->orWhere('kategori_3', 'LIKE', '%53-%')
                    ->where('parameter', 'LIKE', '%Ergonomi%')
                    ->update([
                        'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode()
                ], 401);
            }
        } else if ($request->method == 3) {
            DB::beginTransaction();
            try{
                $data = [
                    "skor_A" => $request->skor_A, 
                    "skor_B" => $request->skor_B
                ];

                function extractScore($value) {
                    if (is_string($value) && preg_match('/^(\d+)-/', $value, $matches)) {
                        return (int) $matches[1];
                    }
                    return (int) $value; // karena kamu kirim int, bukan string "1-..."
                }

                // Matchup Tabel A
                $tabelA = [
                    1 => [
                        1 => [1 => [1 => 1, 2 => 2], 2 => [1 => 2, 2 => 2], 3 => [1 => 2, 2 => 3], 4 => [1 => 3, 2 => 3]],
                        2 => [1 => [1 => 2, 2 => 2], 2 => [1 => 2, 2 => 2], 3 => [1 => 3, 2 => 3], 4 => [1 => 3, 2 => 3]],
                        3 => [1 => [1 => 2, 2 => 3], 2 => [1 => 3, 2 => 3], 3 => [1 => 3, 2 => 3], 4 => [1 => 4, 2 => 4]],
                    ],
                    2 => [
                        1 => [1 => [1 => 2, 2 => 3], 2 => [1 => 3, 2 => 3], 3 => [1 => 3, 2 => 4], 4 => [1 => 4, 2 => 4]],
                        2 => [1 => [1 => 3, 2 => 3], 2 => [1 => 3, 2 => 3], 3 => [1 => 3, 2 => 4], 4 => [1 => 4, 2 => 4]],
                        3 => [1 => [1 => 3, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 4], 4 => [1 => 5, 2 => 5]],
                    ],
                    3 => [
                        1 => [1 => [1 => 3, 2 => 3], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 4], 4 => [1 => 5, 2 => 5]],
                        2 => [1 => [1 => 3, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 4], 4 => [1 => 5, 2 => 5]],
                        3 => [1 => [1 => 4, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 5], 4 => [1 => 5, 2 => 5]],
                    ],
                    4 => [
                        1 => [1 => [1 => 4, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 5], 4 => [1 => 5, 2 => 5]],
                        2 => [1 => [1 => 4, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 5, 2 => 5], 4 => [1 => 5, 2 => 5]],
                        3 => [1 => [1 => 4, 2 => 4], 2 => [1 => 4, 2 => 5], 3 => [1 => 5, 2 => 5], 4 => [1 => 6, 2 => 6]],
                    ],
                    5 => [
                        1 => [1 => [1 => 5, 2 => 5], 2 => [1 => 5, 2 => 5], 3 => [1 => 5, 2 => 6], 4 => [1 => 6, 2 => 7]],
                        2 => [1 => [1 => 5, 2 => 6], 2 => [1 => 6, 2 => 6], 3 => [1 => 6, 2 => 7], 4 => [1 => 7, 2 => 7]],
                        3 => [1 => [1 => 6, 2 => 6], 2 => [1 => 6, 2 => 7], 3 => [1 => 7, 2 => 7], 4 => [1 => 7, 2 => 8]],
                    ],
                    6 => [
                        1 => [1 => [1 => 7, 2 => 7], 2 => [1 => 7, 2 => 7], 3 => [1 => 7, 2 => 8], 4 => [1 => 8, 2 => 9]],
                        2 => [1 => [1 => 8, 2 => 8], 2 => [1 => 8, 2 => 8], 3 => [1 => 8, 2 => 9], 4 => [1 => 9, 2 => 9]],
                        3 => [1 => [1 => 9, 2 => 9], 2 => [1 => 9, 2 => 9], 3 => [1 => 9, 2 => 9], 4 => [1 => 9, 2 => 9]],
                    ],
                ];

                // Matchup tabel B dari gambar
                $tabelB = [
                    1 => [ // leher = 1
                        1 => [1 => 1, 2 => 3],
                        2 => [1 => 2, 2 => 3],
                        3 => [1 => 3, 2 => 4],
                        4 => [1 => 5, 2 => 5],
                        5 => [1 => 6, 2 => 6],
                        6 => [1 => 7, 2 => 7],
                    ],
                    2 => [ // leher = 2
                        1 => [1 => 2, 2 => 3],
                        2 => [1 => 2, 2 => 3],
                        3 => [1 => 4, 2 => 5],
                        4 => [1 => 5, 2 => 5],
                        5 => [1 => 6, 2 => 7],
                        6 => [1 => 7, 2 => 7],
                    ],
                    3 => [
                        1 => [1 => 3, 2 => 3],
                        2 => [1 => 3, 2 => 4],
                        3 => [1 => 4, 2 => 5],
                        4 => [1 => 5, 2 => 6],
                        5 => [1 => 6, 2 => 7],
                        6 => [1 => 7, 2 => 7],
                    ],
                    4 => [
                        1 => [1 => 5, 2 => 5],
                        2 => [1 => 5, 2 => 6],
                        3 => [1 => 6, 2 => 7],
                        4 => [1 => 7, 2 => 7],
                        5 => [1 => 7, 2 => 7],
                        6 => [1 => 8, 2 => 8],
                    ],
                    5 => [
                        1 => [1 => 7, 2 => 7],
                        2 => [1 => 7, 2 => 7],
                        3 => [1 => 7, 2 => 8],
                        4 => [1 => 8, 2 => 8],
                        5 => [1 => 8, 2 => 8],
                        6 => [1 => 8, 2 => 8],
                    ],
                    6 => [
                        1 => [1 => 8, 2 => 8],
                        2 => [1 => 8, 2 => 8],
                        3 => [1 => 8, 2 => 8],
                        4 => [1 => 8, 2 => 9],
                        5 => [1 => 9, 2 => 9],
                        6 => [1 => 9, 2 => 9],
                    ],
                ];

                // Tabel C berdasarkan gambar
                $tabelC = [
                    1 => [1 => 1, 2 => 2, 3 => 3, 4 => 3, 5 => 4, 6 => 5, 7 => 5],
                    2 => [1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 4, 6 => 5, 7 => 5],
                    3 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 4, 6 => 5, 7 => 6],
                    4 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 6],
                    5 => [1 => 4, 2 => 4, 3 => 4, 4 => 5, 5 => 6, 6 => 7, 7 => 7],
                    6 => [1 => 4, 2 => 4, 3 => 5, 4 => 6, 5 => 6, 6 => 7, 7 => 7],
                    7 => [1 => 5, 2 => 5, 3 => 6, 4 => 6, 5 => 7, 6 => 7, 7 => 7],
                    8 => [1 => 5, 2 => 5, 3 => 6, 4 => 7, 5 => 7, 6 => 7, 7 => 7],
                ];

                $hasilPerBagian = [];
                foreach ($data as $kategori => $bagian) {
                    foreach ($bagian as $key => $item) {
                        $hasilPerBagian[$kategori][$key] = 0;
                        
                        if (is_array($item)) {
                            foreach ($item as $subKey => $val) {
                                if ($kategori == 'skor_A' && $key == 'lengan_atas' && $subKey == 'tambahan_3') {
                                    $hasilPerBagian[$kategori][$key] -= extractScore($val); // dikurang
                                } else {
                                    $hasilPerBagian[$kategori][$key] += extractScore($val);
                                }
                            }
                        } else {
                            $hasilPerBagian[$kategori][$key] += extractScore($item);
                        }
                    }
                }

                // ✅ Ambil nilai skor_A dari tabel
                $lengan_atas = $hasilPerBagian['skor_A']['lengan_atas'] ?? 0;
                $lengan_bawah = $hasilPerBagian['skor_A']['lengan_bawah'] ?? 0;
                $pergelangan_tangan  = $hasilPerBagian['skor_A']['pergelangan_tangan'] ?? 0;
                $tangan_memuntir = $hasilPerBagian['skor_A']['tangan_memuntir'] ?? 0;
                $aktivitas_otot = $hasilPerBagian['skor_A']['aktivitas_otot'] ?? 0;
                $bebanA = $hasilPerBagian['skor_A']['beban'] ?? 0;

                // Validasi nilai input agar tidak di luar batas tabel
                $lengan_atas = min(max(1, $lengan_atas), 6);
                $lengan_bawah = min(max(1, $lengan_bawah), 3);
                $pergelangan_tangan = min(max(1, $pergelangan_tangan), 4);
                $tangan_memuntir = min(max(1, $tangan_memuntir), 2);

                // Ambil skor dari tabel
                $nilaiTabelA = $tabelA[$lengan_atas][$lengan_bawah][$pergelangan_tangan][$tangan_memuntir] ?? null;
                $totalSkorA = $nilaiTabelA + $bebanA + $aktivitas_otot;
                
                // ✅ Ambil nilai skor_B dari tabel
                $leher = $hasilPerBagian['skor_B']['leher'] ?? 0;
                $badan = $hasilPerBagian['skor_B']['badan'] ?? 0;
                $kaki  = $hasilPerBagian['skor_B']['kaki'] ?? 0;
                $bebanB = $hasilPerBagian['skor_B']['beban'] ?? 0;
                $aktivitas_ototB = $hasilPerBagian['skor_B']['aktivitas_otot'] ?? 0;

                // Validasi agar dalam batas
                $leher = min(max(1, $leher), 6);
                $badan = min(max(1, $badan), 6);
                $kaki  = ($kaki > 1) ? 2 : 1; // hanya 1 atau 2

                // Ambil nilai dari tabel
                $nilaiTabelB = $tabelB[$leher][$badan][$kaki] ?? null;
                $totalSkorB = $nilaiTabelB + $bebanB + $aktivitas_ototB;

                // PENENTUAN SKOR C
                $baris = $totalSkorA > 8 ? 8 : $totalSkorA;
                $kolom = $totalSkorB > 7 ? 7 : $totalSkorB;
                // Ambil skor C
                $skorC = $tabelC[$baris][$kolom] ?? null;

                
                $pengukuran = [
                    "skor_A" => $request->skor_A, 
                    'lengan_atas' => $lengan_atas,
                    'lengan_bawah' => $lengan_bawah,
                    'pergelangan_tangan' => $pergelangan_tangan,
                    'tangan_memuntir' => $tangan_memuntir,
                    'aktivitas_otot_A' => $aktivitas_otot,
                    'beban_A' => $bebanA,
                    "skor_B" => $request->skor_B,
                    'leher' => $leher,
                    'badan' => $badan,
                    'kaki' => $kaki,
                    'aktivitas_otot_B' => $aktivitas_ototB,
                    'beban_B' => $bebanB,
                    'nilai_tabel_A' => $nilaiTabelA,
                    'nilai_tabel_B' => $nilaiTabelB,
                    'total_skor_A' => $totalSkorA,
                    'total_skor_B' => $totalSkorB,
                    'skor_rula' => $skorC
                ];

                // dd($pengukuran);
                $data = new DataLapanganErgonomi();
                if ($request->no_order != '')
                    $data->no_order = $request->no_order;
                
                if (strtoupper(trim($request->no_sample)) != '')
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                if ($request->pekerja != '')
                    $data->nama_pekerja = $request->pekerja;
                if ($request->divisi != '')
                    $data->divisi = $request->divisi;
                if ($request->usia != '')
                    $data->usia = $request->usia;
                $data->lama_kerja = json_encode($request->year . " Tahun" . ", " . $request->month . " Bulan");
                if ($request->kelamin != '')
                    $data->jenis_kelamin = $request->kelamin;
                if ($request->waktu_bekerja != '')
                    $data->waktu_bekerja = $request->waktu_bekerja;
                if ($request->aktivitas != '')
                    $data->aktivitas = $request->aktivitas;
                $data->method = $request->method;
                $data->pengukuran = json_encode($pengukuran);
                if ($request->foto_samping_kiri != '')
                    $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
                if ($request->foto_samping_kanan != '')
                    $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
                if ($request->foto_depan != '')
                    $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
                if ($request->foto_belakang != '')
                    $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
                $data->aktivitas_ukur = $request->aktivitas_ukur;
                $data->permission = $request->permis;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // UPDATE ORDER DETAIL
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->where('kategori_3', 'LIKE', '%27-%')
                    ->orWhere('kategori_3', 'LIKE', '%53-%')
                    ->where('parameter', 'LIKE', '%Ergonomi%')
                    ->update([
                        'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode()
                ], 401);
            }
        } else if ($request->method == 4) {
            DB::beginTransaction();
            try{
                // Matching Data
                $sectionA = [
                    2 => [2 => 2, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8],
                    3 => [2 => 2, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8],
                    4 => [2 => 3, 3 => 3, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8],
                    5 => [2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8],
                    6 => [2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                    7 => [2 => 6, 3 => 6, 4 => 6, 5 => 7, 6 => 8, 7 => 8, 8 => 8, 9 => 9],
                    8 => [2 => 7, 3 => 7, 4 => 7, 5 => 8, 6 => 8, 7 => 9, 8 => 9, 9 => 9],
                ];

                $sectionB = [
                    0 => [0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6],
                    1 => [0 => 1, 1 => 1, 2 => 2, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6],
                    2 => [0 => 1, 1 => 2, 2 => 2, 3 => 3, 4 => 3, 5 => 4, 6 => 6, 7 => 7],
                    3 => [0 => 2, 1 => 2, 2 => 3, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 8],
                    4 => [0 => 3, 1 => 3, 2 => 4, 3 => 4, 4 => 5, 5 => 6, 6 => 7, 7 => 8],
                    5 => [0 => 4, 1 => 4, 2 => 5, 3 => 5, 4 => 6, 5 => 7, 6 => 8, 7 => 9],
                    6 => [0 => 5, 1 => 5, 2 => 6, 3 => 7, 4 => 8, 5 => 8, 6 => 9, 7 => 9],
                ];

                $sectionC = [
                    0 => [0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6],
                    1 => [0 => 1, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7],
                    2 => [0 => 1, 1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7],
                    3 => [0 => 2, 1 => 3, 2 => 3, 3 => 3, 4 => 5, 5 => 6, 6 => 7, 7 => 8],
                    4 => [0 => 3, 1 => 4, 2 => 4, 3 => 5, 4 => 5, 5 => 6, 6 => 7, 7 => 8],
                    5 => [0 => 4, 1 => 5, 2 => 5, 3 => 6, 4 => 6, 5 => 7, 6 => 8, 7 => 9],
                    6 => [0 => 5, 1 => 6, 2 => 6, 3 => 7, 4 => 7, 5 => 8, 6 => 8, 7 => 9],
                    7 => [0 => 6, 1 => 7, 2 => 7, 3 => 8, 4 => 8, 5 => 9, 6 => 9, 7 => 9],
                ];

                $sectionD = [
                    1 => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                    2 => [1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                    3 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                    4 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                    5 => [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                    6 => [1 => 6, 2 => 6, 3 => 6, 4 => 6, 5 => 6, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                    7 => [1 => 7, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 7, 8 => 8, 9 => 9],
                    8 => [1 => 8, 2 => 8, 3 => 8, 4 => 8, 5 => 8, 6 => 8, 7 => 8, 8 => 8, 9 => 9],
                    9 => [1 => 9, 2 => 9, 3 => 9, 4 => 9, 5 => 9, 6 => 9, 7 => 9, 8 => 9, 9 => 9],
                ];

                $skorRosa = [
                    1 => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                    2 => [1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                    3 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                    4 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                    5 => [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                    6 => [1 => 6, 2 => 6, 3 => 6, 4 => 6, 5 => 6, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                    7 => [1 => 7, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                    8 => [1 => 8, 2 => 8, 3 => 8, 4 => 8, 5 => 8, 6 => 8, 7 => 8, 8 => 8, 9 => 9, 10 => 10],
                    9 => [1 => 9, 2 => 9, 3 => 9, 4 => 9, 5 => 9, 6 => 9, 7 => 9, 8 => 9, 9 => 9, 10 => 10],
                    10 => [1 => 10, 2 => 10, 3 => 10, 4 => 10, 5 => 10, 6 => 10, 7 => 10, 8 => 10, 9 => 10, 10 => 10],
                ];
                
                $data = [
                    "section_A" => $request->section_A, 
                    "section_B" => $request->section_B, 
                    "section_C" => $request->section_C
                ];

                function extractScore($value) {
                    if (is_string($value) && preg_match('/^(\d+)-/', $value, $matches)) {
                        return (int) $matches[1];
                    }
                    return (int) $value; // karena kamu kirim int, bukan string "1-..."
                }

                $hasilPerBagian = [];
                foreach ($data as $kategori => $bagian) {
                    foreach ($bagian as $key => $item) {
                        $hasilPerBagian[$kategori][$key] = 0;
                        
                        if (is_array($item)) {
                            foreach ($item as $subKey => $val) {
                                if ($kategori == 'skor_A' && $key == 'lengan_atas' && $subKey == 'tambahan_3') {
                                    $hasilPerBagian[$kategori][$key] -= extractScore($val); // dikurang
                                } else {
                                    $hasilPerBagian[$kategori][$key] += extractScore($val);
                                }
                            }
                        } else {
                            $hasilPerBagian[$kategori][$key] += extractScore($item);
                        }
                    }
                }

                
                // SECTION A
                $tinggi_kursi = $hasilPerBagian['section_A']['tinggi_kursi'] ?? 0;
                $lebar_dudukan = $hasilPerBagian['section_A']['lebar_dudukan'] ?? 0;
                $durasi_kerja_tinggi_kursi_dan_lebar_dudukan = $hasilPerBagian['section_A']['durasi_kerja_bagian_kursi'] ?? 0;
                $sandaran_lengan = $hasilPerBagian['section_A']['sandaran_lengan'] ?? 0;
                $sandaran_punggung = $hasilPerBagian['section_A']['sandaran_punggung'] ?? 0;
                // $durasi_kerja_bagian_sandaran_lengan_dan_sandaran_punggung = $hasilPerBagian['section_A']['durasi_kerja_bagian_sandaran_lengan_dan_sandaran_punggung'] ?? 0;

                $armRestAndBackSupport = $sandaran_lengan + $sandaran_punggung;
                $seatPanHeightOrdDepth = $tinggi_kursi + $lebar_dudukan;

                $totalTinggiDanLebarKursi = $seatPanHeightOrdDepth + $durasi_kerja_tinggi_kursi_dan_lebar_dudukan;
                $totalSandaranLenganDanPunggung = $armRestAndBackSupport;
                // $totalSandaranLenganDanPunggung = $armRestAndBackSupport + $durasi_kerja_bagian_sandaran_lengan_dan_sandaran_punggung;

                $nilai_section_A = $sectionA[$seatPanHeightOrdDepth][$armRestAndBackSupport];
                $totalSkorA = $nilai_section_A + $durasi_kerja_tinggi_kursi_dan_lebar_dudukan;

                // SECTION B
                $monitor = $hasilPerBagian['section_B']['monitor'] ?? 0;
                $durasi_kerja_monitor = $hasilPerBagian['section_B']['durasi_kerja_monitor'] ?? 0;
                $telepon = $hasilPerBagian['section_B']['telepon'] ?? 0;
                $durasi_kerja_telepon = $hasilPerBagian['section_B']['durasi_kerja_telepon'] ?? 0;

                $totalMonitor = $monitor + $durasi_kerja_monitor;
                $totalTelepon = $telepon + $durasi_kerja_telepon;
                
                $totalSkorB = $sectionB[$totalTelepon][$totalMonitor];

                // SECTION C
                $keyboard = $hasilPerBagian['section_C']['keyboard'] ?? 0;
                $mouse = $hasilPerBagian['section_C']['mouse'] ?? 0;
                $durasi_kerja_keyboard = $hasilPerBagian['section_C']['durasi_kerja_keyboard'] ?? 0;
                $durasi_kerja_mouse = $hasilPerBagian['section_C']['durasi_kerja_mouse'] ?? 0;

                $totalKeyboard = $keyboard + $durasi_kerja_keyboard;
                $totalMouse = $mouse + $durasi_kerja_mouse;

                $totalSkorC = $sectionC[$totalMouse][$totalKeyboard];

                // SECTION D
                $totalSkorD = $sectionD[$totalSkorB][$totalSkorC];

                // FINAL ROSA
                $finalRosa = $skorRosa[$totalSkorA][$totalSkorD];

                // SAVE DATA
                $pengukuran = [
                    "section_A" => $request->section_A, 
                    "section_B" => $request->section_B, 
                    "section_C" => $request->section_C,
                    'skor_mouse' => $mouse,
                    'skor_monitor' => $monitor,
                    'skor_telepon' => $telepon,
                    'nilai_table_a' => $nilai_section_A,
                    'skor_keyboard' => $keyboard,
                    'final_skor_rosa' => $finalRosa,
                    'total_section_a' => $totalSkorA,
                    'total_section_b' => $totalSkorB,
                    'total_section_c' => $totalSkorC,
                    'total_section_d' => $totalSkorD,
                    'skor_lebar_kursi' => $lebar_dudukan,
                    'total_skor_mouse' => $totalMouse,
                    'skor_tinggi_kursi' => $tinggi_kursi,
                    'total_skor_monitor' => $totalMonitor,
                    'total_skor_telepon' => $totalTelepon,
                    'total_skor_keyboard' => $totalKeyboard,
                    'skor_sandaran_lengan' => $sandaran_lengan,
                    'skor_sandaran_punggung' => $sandaran_punggung,
                    'skor_durasi_kerja_mouse' => $durasi_kerja_mouse,
                    'skor_durasi_kerja_monitor' => $durasi_kerja_monitor,
                    'skor_durasi_kerja_telepon' => $durasi_kerja_telepon,
                    'skor_durasi_kerja_keyboard' => $durasi_kerja_keyboard,
                    'skor_durasi_kerja_bagian_kursi' => $durasi_kerja_tinggi_kursi_dan_lebar_dudukan,
                    'skor_total_tinggi_kursi_dan_lebar_dudukan' => $seatPanHeightOrdDepth,
                    'skor_total_sandaran_lengan_dan_punggung' => $armRestAndBackSupport,
                ];

                // dd($pengukuran);
                $data = new DataLapanganErgonomi();
                if ($request->no_order != '')
                    $data->no_order = $request->no_order;
                
                if (strtoupper(trim($request->no_sample)) != '')
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                if ($request->pekerja != '')
                    $data->nama_pekerja = $request->pekerja;
                if ($request->divisi != '')
                    $data->divisi = $request->divisi;
                if ($request->usia != '')
                    $data->usia = $request->usia;
                $data->lama_kerja = json_encode($request->year . " Tahun" . ", " . $request->month . " Bulan");
                if ($request->kelamin != '')
                    $data->jenis_kelamin = $request->kelamin;
                if ($request->waktu_bekerja != '')
                    $data->waktu_bekerja = $request->waktu_bekerja;
                if ($request->aktivitas != '')
                    $data->aktivitas = $request->aktivitas;
                $data->method = $request->method;
                $data->pengukuran = json_encode($pengukuran);
                if ($request->foto_samping_kiri != '')
                    $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
                if ($request->foto_samping_kanan != '')
                    $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
                if ($request->foto_depan != '')
                    $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
                if ($request->foto_belakang != '')
                    $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
                $data->aktivitas_ukur = $request->aktivitas_ukur;
                $data->permission = $request->permis;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // UPDATE ORDER DETAIL
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->where('kategori_3', 'LIKE', '%27-%')
                    ->orWhere('kategori_3', 'LIKE', '%53-%')
                    ->where('parameter', 'LIKE', '%Ergonomi%')
                    ->update([
                        'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);
            }catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode()
                ], 401);
            }
        } else if ($request->method == 5) {
            DB::beginTransaction();
            try{
                $inputs = $request->all();
                $lok = [];
                $asimetris = [];
                
                // TABEL FREKUENSI
                $tabelFrekuensi = [
                    0.2 => ['<1 Jam' => ['<75' => 1, '>=75' => 1], '1-2 Jam' => ['<75' => 0.95, '>=75' => 0.95], '2-8 Jam' => ['<75' => 0.85, '>=75' => 0.85]],
                    0.5 => ['<1 Jam' => ['<75' => 0.97, '>=75' => 0.97], '1-2 Jam' => ['<75' => 0.92, '>=75' => 0.92], '2-8 Jam' => ['<75' => 0.81, '>=75' => 0.81]],
                    1   => ['<1 Jam' => ['<75' => 0.94, '>=75' => 0.94], '1-2 Jam' => ['<75' => 0.88, '>=75' => 0.88], '2-8 Jam' => ['<75' => 0.75, '>=75' => 0.75]],
                    2   => ['<1 Jam' => ['<75' => 0.91, '>=75' => 0.91], '1-2 Jam' => ['<75' => 0.84, '>=75' => 0.84], '2-8 Jam' => ['<75' => 0.65, '>=75' => 0.65]],
                    3   => ['<1 Jam' => ['<75' => 0.88, '>=75' => 0.88], '1-2 Jam' => ['<75' => 0.79, '>=75' => 0.79], '2-8 Jam' => ['<75' => 0.55, '>=75' => 0.55]],
                    4   => ['<1 Jam' => ['<75' => 0.84, '>=75' => 0.84], '1-2 Jam' => ['<75' => 0.72, '>=75' => 0.72], '2-8 Jam' => ['<75' => 0.45, '>=75' => 0.45]],
                    5   => ['<1 Jam' => ['<75' => 0.8, '>=75' => 0.8], '1-2 Jam' => ['<75' => 0.6, '>=75' => 0.6], '2-8 Jam' => ['<75' => 0.35, '>=75' => 0.35]],
                    6   => ['<1 Jam' => ['<75' => 0.75, '>=75' => 0.75], '1-2 Jam' => ['<75' => 0.5, '>=75' => 0.5], '2-8 Jam' => ['<75' => 0.27, '>=75' => 0.27]],
                    7   => ['<1 Jam' => ['<75' => 0.7, '>=75' => 0.7], '1-2 Jam' => ['<75' => 0.42, '>=75' => 0.42], '2-8 Jam' => ['<75' => 0.22, '>=75' => 0.22]],
                    8   => ['<1 Jam' => ['<75' => 0.6, '>=75' => 0.6], '1-2 Jam' => ['<75' => 0.35, '>=75' => 0.35], '2-8 Jam' => ['<75' => 0.18, '>=75' => 0.18]],
                    9   => ['<1 Jam' => ['<75' => 0.52, '>=75' => 0.52], '1-2 Jam' => ['<75' => 0.3, '>=75' => 0.3], '2-8 Jam' => ['<75' => 0, '>=75' => 0.15]],
                    10  => ['<1 Jam' => ['<75' => 0.45, '>=75' => 0.45], '1-2 Jam' => ['<75' => 0.26, '>=75' => 0.26], '2-8 Jam' => ['<75' => 0, '>=75' => 0.13]],
                    11  => ['<1 Jam' => ['<75' => 0.41, '>=75' => 0.41], '1-2 Jam' => ['<75' => 0, '>=75' => 0.23], '2-8 Jam' => ['<75' => 0, '>=75' => 0]],
                    12  => ['<1 Jam' => ['<75' => 0.37, '>=75' => 0.37], '1-2 Jam' => ['<75' => 0, '>=75' => 0.21], '2-8 Jam' => ['<75' => 0, '>=75' => 0]],
                    13  => ['<1 Jam' => ['<75' => 0, '>=75' => 0.34], '1-2 Jam' => ['<75' => 0, '>=75' => 0], '2-8 Jam' => ['<75' => 0, '>=75' => 0]],
                    14  => ['<1 Jam' => ['<75' => 0, '>=75' => 0.31], '1-2 Jam' => ['<75' => 0, '>=75' => 0], '2-8 Jam' => ['<75' => 0, '>=75' => 0]],
                    15  => ['<1 Jam' => ['<75' => 0, '>=75' => 0.28], '1-2 Jam' => ['<75' => 0, '>=75' => 0], '2-8 Jam' => ['<75' => 0, '>=75' => 0]],
                    16  => ['<1 Jam' => ['<75' => 0, '>=75' => 0], '1-2 Jam' => ['<75' => 0, '>=75' => 0], '2-8 Jam' => ['<75' => 0, '>=75' => 0]],
                ];

    
                foreach ($inputs as $key => $value) {
                    if (strpos($key, 'lokasi_tangan') === 0) {
                        $label = $key;
                        $lok[$label] = $value;
                    }
                    if (strpos($key, 'sudut_asimetris') === 0) {
                        $label = $key;
                        $asimetris[$label] = $value;
                    }
                };

                $A1 = 23;
                $A2 = 23;

                $H1 = (float)($request->jarak_vertikal);
                $H2 = $H1;

                $I1 = (float)($request->berat_beban);
                $I2 = $I1;

                $J1 = (float)(str_replace(',', '.', $request->frek_jml_angkatan));
                if($J1 < 0.2){
                    $J1 = 0.2;
                }else if($J1 > 0.2 && $J1 < 1){
                    $J1 = 0.5;
                }else if($J1 > 15){
                    $J1 = 16;
                }else{
                    $J1 = (int)floor($J1);
                }
                $J2 = $J1;

                if($J1 < 0.2){
                    $j_1_konversi = '<0.2';
                }else if($J1 > 0.2 && $J1 < 1){
                    $j_1_konversi = 0.5;
                }else if($J1 > 15){
                    $j_1_konversi = '>15';
                }else{
                    $j_1_konversi = (int)floor($J1);
                }
                $j_2_konversi = $j_1_konversi;

                $K1 = (float)($request->durasi_jam_kerja);

                if ($K1 < 1) {
                    $durasiKategori = '<1 Jam';
                } elseif ($K1 >= 1 && $K1 <= 2) {
                    $durasiKategori = '1-2 Jam';
                } elseif ($K1 > 2 && $K1 <= 8) {
                    $durasiKategori = '2-8 Jam';
                } else {
                    $durasiKategori = '>8 Jam'; // Opsional: Kalau lebih dari 8 jam
                }
                $k_1 =($durasiKategori);
                $K2 = $k_1;

                $L1 = $request->kopling_tangan;
                $L2 = $L1;

                $M1 = (float)($request->lokasi_tangan['Horizontal Awal']);
                $M2 = (float)($request->lokasi_tangan['Horizontal Akhir']);
                
                $N1 = (float)($request->lokasi_tangan['Vertikal Awal']);
                $N2 = (float)($request->lokasi_tangan['Vertikal Akhir']);

                $O1 = (float)($request->sudut_asimetris['Awal']);
                $O2 = (float)($request->sudut_asimetris['Akhir']);
                
                function getR($N, $L) {
                    if ($L === "Jelek") return 0.9;
                    if ($L === "Sedang" && $N < 75) return 0.95;
                    return 1;
                }

                $R1 = getR($N1, $L1);
                $R2 = getR($N2, $L2);

                $B1 = number_format((($M1 <= 25) ? 1 : (($M1 > 63) ? 0 : (25 / $M1))), 4);
                $B2 = number_format((($M2 <= 25) ? 1 : (($M2 > 63) ? 0 : (25 / $M2))), 4);

                $C1 = number_format((($N1 < 175) ? (1 - (0.003 * abs($N1 - 75))) : 0), 4);
                $C2 = number_format((($N2 < 175) ? (1 - (0.003 * abs($N2 - 75))) : 0), 4);

                $D1 = number_format((($H1 <= 25) ? 1 : (($H1 > 175) ? 0 : (0.82 + (4.5 / $H1)))), 4);
                $D2 = number_format((($H2 <= 25) ? 1 : (($H2 > 175) ? 0 : (0.82 + (4.5 / $H2)))), 4);

                $E1 = number_format((($O1 <= 135) ? (1 - (0.0032 * $O1)) : 0), 4);
                $E2 = number_format((($O2 <= 135) ? (1 - (0.0032 * $O2)) : 0), 4);

                if($N1 < 75){
                    $n_1 = '<75';
                }else{
                    $n_1 = '>=75';
                }
                
                if($N2 < 75){
                    $n_2 = '<75';
                }else{
                    $n_2 = '>=75';
                }

                $Q1 = $tabelFrekuensi[$J1][$k_1][$n_1];
                $Q2 = $tabelFrekuensi[$J2][$K2][$n_2];

                $G1 = $Q1;
                $G2 = $Q2;

                // NILAI RWL (BEBAN YANG DIANGKAT) 
                $rwl_awal = number_format($A1 * $B1 * $C1 * $D1 * $E1 * $G1 * $R1 , 4);
                $rwl_akhir = number_format($A2 * $B2 * $C2 * $D2 * $E2 * $G2 * $R2, 4);

                // NILAI LIFTING
                $li_awal = ($rwl_awal > 0) ? number_format($I1 / $rwl_awal, 4) : 0;
                $li_akhir = ($rwl_akhir > 0) ? number_format($I2 / $rwl_akhir, 4) : 0;

                // Kesimpuna LI
                if($li_awal < 1){
                    $kesimpulan_awal = "Tidak ada masalah dengan pekerjaan mengangkat, maka tidak diperlukan perbaikan terhadap pekerjaan, tetapi tetap terus mendapatkan perhatian sehingga nilai LI dapat dipertahankan <1";
                }else if($li_awal >= 1 && $li_awal < 3){
                    $kesimpulan_awal = "Ada beberapa masalah dari beberapa parameter angkat, sehingga perlu dilakukan pengecekan dan redesain segera pada parameter yang menyebabkan nilai RWL tinggi";
                }else{
                    $kesimpulan_awal = "Terdapat banyak permasalahan dari parameter angkat, sehingga diperlukan pengecekan dan perbaikan sesegera mungkin secara menyeluruh terhadap parameter yang menyebabkan nilai tinggi";
                }

                if($li_akhir < 1){
                    $kesimpulan_akhir = "Tidak ada masalah dengan pekerjaan mengangkat, maka tidak diperlukan perbaikan terhadap pekerjaan, tetapi tetap terus mendapatkan perhatian sehingga nilai LI dapat dipertahankan <1";
                }else if($li_akhir >= 1 && $li_akhir < 3){
                    $kesimpulan_akhir = "Ada beberapa masalah dari beberapa parameter angkat, sehingga perlu dilakukan pengecekan dan redesain segera pada parameter yang menyebabkan nilai RWL tinggi";
                }else{
                    $kesimpulan_akhir = "Terdapat banyak permasalahan dari parameter angkat, sehingga diperlukan pengecekan dan perbaikan sesegera mungkin secara menyeluruh terhadap parameter yang menyebabkan nilai tinggi";
                }

                $pengukuran = [
                    "lokasi_tangan" => $request->lokasi_tangan, 
                    "sudut_asimetris" => $request->sudut_asimetris,
                    'nilai_beban_rwl_awal' => $rwl_awal,
                    'nilai_beban_rwl_akhir' => $rwl_akhir,
                    'lifting_index_awal' => $li_awal,
                    'lifting_index_akhir' => $li_akhir,
                    'konstanta_beban_awal' => $A1,
                    'konstanta_beban_akhir' => $A2,
                    'pengali_horizontal_awal' => $B1,
                    'pengali_horizontal_akhir' => $B2,
                    'pengali_vertikal_awal' => $C1,
                    'pengali_vertikal_akhir' => $C2,
                    'pengali_jarak_awal' => $D1,
                    'pengali_jarak_akhir' => $D2,
                    'pengali_asimetris_awal' => $E1,
                    'pengali_asimetris_akhir' => $E2,
                    'pengali_frekuensi_awal' => $G1,
                    'pengali_frekuensi_akhir' => $G2,
                    'pengali_kopling_awal' => $R1,
                    'pengali_kopling_akhir' => $R2,
                    'durasi_jam_kerja_awal' => $k_1,
                    'durasi_jam_kerja_akhir' => $K2,
                    'frekuensi_jumlah_awal' => $j_1_konversi,
                    'frekuensi_jumlah_akhir' => $j_2_konversi,
                    'kesimpulan_nilai_li_awal' => $kesimpulan_awal,
                    'kesimpulan_nilai_li_akhir' => $kesimpulan_akhir
                ];

                $data = new DataLapanganErgonomi();
                if ($request->no_order != '')
                    $data->no_order = $request->no_order;
                
                if (strtoupper(trim($request->no_sample)) != '')
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                if ($request->pekerja != '')
                    $data->nama_pekerja = $request->pekerja;
                if ($request->divisi != '')
                    $data->divisi = $request->divisi;
                if ($request->usia != '')
                    $data->usia = $request->usia;
                $data->lama_kerja = json_encode($request->year . " Tahun" . ", " . $request->month . " Bulan");
                if ($request->kelamin != '')
                    $data->jenis_kelamin = $request->kelamin;
                if ($request->waktu_bekerja != '')
                    $data->waktu_bekerja = $request->waktu_bekerja;
                if ($request->aktivitas != '')
                    $data->aktivitas = $request->aktivitas;
                $data->method = $request->method;
                $data->berat_beban = $request->berat_beban;
                $data->pengukuran = json_encode($pengukuran);
                $data->frekuensi_jumlah_angkatan = str_replace(',', '.', $request->frek_jml_angkatan);
                $data->kopling_tangan = $request->kopling_tangan;
                $data->jarak_vertikal = $request->jarak_vertikal;
                $data->durasi_jam_kerja = $request->durasi_jam_kerja;
                if ($request->foto_samping_kiri != '')
                    $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
                if ($request->foto_samping_kanan != '')
                    $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
                if ($request->foto_depan != '')
                    $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
                if ($request->foto_belakang != '')
                    $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
                $data->aktivitas_ukur = $request->aktivitas_ukur;
                $data->permission = $request->permis;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // UPDATE ORDER DETAIL
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->where('kategori_3', 'LIKE', '%27-%')
                    ->orWhere('kategori_3', 'LIKE', '%53-%')
                    ->where('parameter', 'LIKE', '%Ergonomi%')
                    ->update([
                        'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);
            }catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode()
                ], 401);
            }
        } else if ($request->method == 6) {
            DB::beginTransaction();
            try {
                $inputs = $request->all();
                $tangan = [];
                $b_siku = [];
                $b_bahu = [];
                $b_leher = [];
                $b_punggung = [];
                $b_kaki = [];
                $b_pengaruh_fisik = [];

                foreach ($inputs as $key => $value) {
                    if (strpos($key, 'tangan_dam_pergelangan_tangan') === 0) {
                        $label = $key;
                        $tangan[$label] = $value;
                    }
                    if (strpos($key, 'siku') === 0) {
                        $label = $key;
                        $b_siku[$label] = $value;
                    }
                    if (strpos($key, 'bahu') === 0) {
                        $label = $key;
                        $b_bahu[$label] = $value;
                    }
                    if (strpos($key, 'leher') === 0) {
                        $label = $key;
                        $b_leher[$label] = $value;
                    }
                    if (strpos($key, 'punggung') === 0) {
                        $label = $key;
                        $b_punggung[$label] = $value;
                    }
                    if (strpos($key, 'kaki') === 0) {
                        $label = $key;
                        $b_kaki[$label] = $value;
                    }
                    if (strpos($key, 'pengaruh_fisik') === 0) {
                        $label = $key;
                        $b_pengaruh_fisik[$label] = $value;
                    }
                }

                $pengukuran = ["tangan_dan_pergelangan_tangan" => $request->tangan_dam_pergelangan_tangan, "siku" => $request->siku, "bahu" => $request->bahu, "leher" => $request->leher, "punggung" => $request->punggung, "kaki" => $request->kaki, "pengaruh_fisik" => $request->pengaruh_fisik];

                $data = new DataLapanganErgonomi();
                if ($request->no_order != '')
                    $data->no_order = $request->no_order;
                
                if (strtoupper(trim($request->no_sample)) != '')
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                if ($request->pekerja != '')
                    $data->nama_pekerja = $request->pekerja;
                if ($request->divisi != '')
                    $data->divisi = $request->divisi;
                if ($request->usia != '')
                    $data->usia = $request->usia;
                if ($request->lama_kerja != '')
                    $data->lama_kerja = $request->lama_kerja;
                if ($request->kelamin != '')
                    $data->jenis_kelamin = $request->kelamin;
                if ($request->waktu_bekerja != '')
                    $data->waktu_bekerja = $request->waktu_bekerja;
                if ($request->aktivitas != '')
                    $data->aktivitas = $request->aktivitas;
                $data->method = $request->method;
                $data->pengukuran = json_encode($pengukuran);
                $data->durasi_jam_kerja = $request->durasi_jam_kerja;
                if ($request->foto_samping_kiri != '')
                    $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
                if ($request->foto_samping_kanan != '')
                    $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
                if ($request->foto_depan != '')
                    $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
                if ($request->foto_belakang != '')
                    $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
                $data->aktivitas_ukur = $request->aktivitas_ukur;
                $data->permission = $request->permis;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // Update Order Detail
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->where('kategori_3', 'LIKE', '%27-%')
                    ->orWhere('kategori_3', 'LIKE', '%53-%')
                    ->where('parameter', 'LIKE', '%Ergonomi%')
                    ->update([
                        'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode()
                ], 401);
            }
        } else if ($request->method == 7) {
            DB::beginTransaction();
            try{
                $inputs = $request->all();
                $umum = [];
                $tubuh = [];

                foreach ($inputs as $key => $value) {
                    if (strpos($key, 'Identitas_Umum') === 0) {
                        $label = $key;
                        $umum[$label] = $value;
                    }
                    if (strpos($key, 'Keluhan_Bagian_Tubuh') === 0) {
                        $parts = explode('_', $key);
                        $bagian_tubuh = $parts[1];
                        $sub_key = $parts[2];

                        if (!isset($tubuh[$bagian_tubuh])) {
                            $tubuh[$bagian_tubuh] = [[]];
                        }

                        if (!isset($tubuh[$bagian_tubuh][$sub_key])) {
                            $tubuh[$bagian_tubuh][$sub_key] = [];
                        }

                        $tubuh[$bagian_tubuh][$sub_key][] = $value;
                    }
                }

                function hitungRisikoKeluhan($seberapaParah, $seberapaSering)
                {
                    // dd($seberapaParah, $seberapaSering);
                    // Tentukan nilai berdasarkan keparahan
                    $nilaiParah = 0;
                    if ($seberapaParah == 'Tidak ada masalah') {
                        $nilaiParah = 1;
                    } elseif ($seberapaParah == 'Tidak nyaman') {
                        $nilaiParah = 2;
                    } elseif ($seberapaParah == 'Sakit') {
                        $nilaiParah = 3;
                    } elseif ($seberapaParah == 'Sakit parah') {
                        $nilaiParah = 4;
                    }

                    // Tentukan nilai berdasarkan frekuensi
                    $nilaiSering = 0;
                    if ($seberapaSering == 'Tidak pernah') {
                        $nilaiSering = 1;
                    } elseif ($seberapaSering == 'Terkadang') {
                        $nilaiSering = 2;
                    } elseif ($seberapaSering == 'Sering') {
                        $nilaiSering = 3;
                    } elseif ($seberapaSering == 'Selalu') {
                        $nilaiSering = 4;
                    }

                    // Hitung risiko berdasarkan tabel
                    $risiko = $nilaiParah * $nilaiSering;

                    return $risiko;
                }

                $keluhanBagianTubuh = $request->Keluhan_Bagian_Tubuh;
                if (is_array($keluhanBagianTubuh) && !in_array("null", $keluhanBagianTubuh)) {
                    foreach ($keluhanBagianTubuh as $bagian => $keluhan) {
                        // Pastikan keluhan adalah array dan tidak bernilai "Tidak"
                        if (is_array($keluhan) && $keluhan != "Tidak") {
                            // Cek apakah kunci "Seberapa_Parah" dan "Seberapa_Sering" ada
                            if (isset($keluhan['Seberapa_Parah'], $keluhan['Seberapa_Sering'])) {
                                $seberapaParah = $keluhan['Seberapa_Parah'];
                                $seberapaSering = $keluhan['Seberapa_Sering'];

                                // Hitung risiko berdasarkan tabel
                                $risiko = hitungRisikoKeluhan($seberapaParah, $seberapaSering);

                                // Simpan array tanpa "nilai" terlebih dahulu
                                $keluhanBagianTubuh[$bagian] = $keluhan;

                                // Tambahkan risiko ke array keluhan
                                $keluhanBagianTubuh[$bagian]['Poin'] = $risiko;
                            } else {
                                // Jika data tidak lengkap
                                $keluhanBagianTubuh[$bagian]['Poin'] = 'Data tidak lengkap';
                            }
                        }
                    }
                }



                // $pengukuran = ["Identitas_Umum" => $request->Identitas_Umum, "Keluhan_Bagian_Tubuh" => $request->Keluhan_Bagian_Tubuh];
                $pengukuran = ["Identitas_Umum" => $request->Identitas_Umum, "Keluhan_Bagian_Tubuh" => $keluhanBagianTubuh];
                // dd($pengukuran);

                $data = new DataLapanganErgonomi();
                if ($request->no_order != '')
                    $data->no_order = $request->no_order;
                
                if (strtoupper(trim($request->no_sample)) != '')
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                if ($request->pekerja != '')
                    $data->nama_pekerja = $request->pekerja;
                if ($request->divisi != '')
                    $data->divisi = $request->divisi;
                if ($request->usia != '')
                    $data->usia = $request->usia;
                $data->lama_kerja = json_encode($request->year . " Tahun" . ", " . $request->month . " Bulan");
                if ($request->kelamin != '')
                    $data->jenis_kelamin = $request->kelamin;
                if ($request->waktu_bekerja != '')
                    $data->waktu_bekerja = $request->waktu_bekerja;
                if ($request->aktivitas != '')
                    $data->aktivitas = $request->aktivitas;
                $data->method = $request->method;
                $data->pengukuran = json_encode($pengukuran);
                if ($request->foto_samping_kiri != '')
                    $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
                if ($request->foto_samping_kanan != '')
                    $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
                if ($request->foto_depan != '')
                    $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
                if ($request->foto_belakang != '')
                    $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
                $data->aktivitas_ukur = $request->aktivitas_ukur;
                $data->permission = $request->permis;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // UPDATE ORDER DETAIL
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->where('kategori_3', 'LIKE', '%27-%')
                    ->orWhere('kategori_3', 'LIKE', '%53-%')
                    ->where('parameter', 'LIKE', '%Ergonomi%')
                    ->update([
                        'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);
            }catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e.getMessage(),
                    'line' => $e.getLine(),
                    'code' => $e.getCode()
                ], 401);
            }
            
        } else if ($request->method == 8) {
            DB::beginTransaction();
            try{
                function hitungRisiko($posisi, $berat)
                {
                    $poin = 0;
                    if ($posisi == 'Pengangkatan dengan jarak dekat') {
                        if ($berat == 'Berat benda >23Kg') {
                            $poin = 5;
                        } elseif ($berat == 'Berat benda Sekitar 7 - 23 Kg') {
                            $poin = 3;
                        } else {
                            $poin = 0;
                        }
                    } elseif ($posisi == 'Pengangkatan dengan jarak sedang') {
                        if ($berat == 'Berat benda >16Kg') {
                            $poin = 6;
                        } elseif ($berat == 'Berat benda Sekitar 5 - 16 Kg') {
                            $poin = 3;
                        } else {
                            $poin = 0;
                        }
                    } elseif ($posisi == 'Pengangkatan dengan jarak jauh') {
                        if ($berat == 'Berat benda >13Kg') {
                            $poin = 6;
                        } elseif ($berat == 'Berat benda Sekitar 4.5 - 13 Kg') {
                            $poin = 3;
                        } else {
                            $poin = 0;
                        }
                    }

                    return $poin;
                }

                // Ambil data Manual_Handling dari request
                $manualHandling = $request->input('Manual_Handling');

                // Hitung total skor
                $total_skor_1 = 0;
                if ($manualHandling !== 'Tidak') {
                    if (is_array($manualHandling) && isset($manualHandling['Posisi Angkat Beban']) && isset($manualHandling['Estimasi Berat Benda'])) {
                        $total_skor_1 = hitungRisiko($manualHandling['Posisi Angkat Beban'], $manualHandling['Estimasi Berat Benda']);
                    }
                }

                $total_skor_2 = 0;
                if ($manualHandling !== 'Tidak' && isset($manualHandling['Faktor Resiko']) && is_array($manualHandling['Faktor Resiko'])) {
                    foreach ($manualHandling['Faktor Resiko'] as $faktor => $nilai) {
                        // Periksa jika $nilai adalah array
                        if (is_array($nilai)) {
                            foreach ($nilai as $sub_nilai) {
                                // Pastikan nilai tidak 'Tidak' dan dalam format yang benar
                                if (is_string($sub_nilai) && $sub_nilai !== 'Tidak') {
                                    // Ambil nilai sebelum tanda '-'
                                    $skor = explode('-', $sub_nilai)[0];
                                    if (is_numeric($skor)) {
                                        $total_skor_2 += intval($skor);
                                    }
                                }
                            }
                        } elseif (is_string($nilai) && $nilai !== 'Tidak') {
                            // Tangani kasus jika $nilai adalah string sederhana
                            $skor = explode('-', $nilai)[0];
                            if (is_numeric($skor)) {
                                $total_skor_2 += intval($skor);
                            }
                        }
                    }
                }

                $total_skor = $total_skor_1 + $total_skor_2;

                // Menghitung total durasi untuk Tubuh_Bagian_Atas dan Tubuh_Bagian_Bawah
                $hitung = [
                    "Tubuh_Bagian_Atas" => $request->input('Tubuh_Bagian_Atas'),
                    "Tubuh_Bagian_Bawah" => $request->input('Tubuh_Bagian_Bawah'),
                ];

                $totalDurasiAtas = $this->calculateTotalDurasi($hitung['Tubuh_Bagian_Atas']);
                $totalDurasiBawah = $this->calculateTotalDurasi($hitung['Tubuh_Bagian_Bawah']);
                $totalSkor = $totalDurasiAtas + $totalDurasiBawah;
                // Tambahkan total skor ke array Manual_Handling jika bukan 'Tidak'
                if ($manualHandling !== 'Tidak') {
                    $manualHandling['Total Poin 1'] = $total_skor_1;
                    $manualHandling['Faktor Resiko']['Total Poin 2'] = $total_skor_2;
                    $manualHandling['Total Poin Akhir'] = $total_skor;
                }

                // Buat array pengukuran dengan data yang telah dimodifikasi
                $pengukuran = [
                    "Tubuh_Bagian_Atas" => $request->input('Tubuh_Bagian_Atas'),
                    "Tubuh_Bagian_Bawah" => $request->input('Tubuh_Bagian_Bawah'),
                    "Jumlah_Skor_Postur" => $totalSkor,
                    "Manual_Handling" => $manualHandling
                ];
                // dd($pengukuran);
                // Simpan data ke database
                $data = new DataLapanganErgonomi();
                if ($request->no_order != '') {
                    $data->no_order = $request->no_order;
                }
                
                if (strtoupper(trim($request->no_sample)) != '') {
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                }
                if ($request->pekerja != '') {
                    $data->nama_pekerja = $request->pekerja;
                }
                if ($request->divisi != '') {
                    $data->divisi = $request->divisi;
                }
                if ($request->usia != '') {
                    $data->usia = $request->usia;
                }
                $data->lama_kerja = json_encode($request->year . " Tahun" . ", " . $request->month . " Bulan");
                if ($request->kelamin != '') {
                    $data->jenis_kelamin = $request->kelamin;
                }
                if ($request->waktu_bekerja != '') {
                    $data->waktu_bekerja = $request->waktu_bekerja;
                }
                if ($request->aktivitas != '') {
                    $data->aktivitas = $request->aktivitas;
                }
                $data->method = $request->method;
                $data->pengukuran = json_encode($pengukuran);
                $data->aktivitas_ukur = $request->aktivitas_ukur;
                if ($request->foto_samping_kiri != '')
                    $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
                if ($request->foto_samping_kanan != '')
                    $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
                if ($request->foto_depan != '')
                    $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
                if ($request->foto_belakang != '')
                    $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
                $data->permission = $request->permis;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');

                // Simpan data ke database
                $data->save();

                // UPDATE ORDER DETAIL
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->where('kategori_3', 'LIKE', '%27-%')
                    ->orWhere('kategori_3', 'LIKE', '%53-%')
                    ->where('parameter', 'LIKE', '%Ergonomi%')
                    ->update([
                        'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);
            }catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode()
                ], 401);
            }
            
        } else if ($request->method == 9) {
            DB::beginTransaction();
            try{

                $inputs = $request->all();
                $sectionA = [];
                $sectionB = [];
                $sectionC = [];
    
                foreach ($inputs as $key => $value) {
                    if (strpos($key, 'section_A') === 0) {
                        $label = $key;
                        $sectionA[$label] = $value;
                    }
                    if (strpos($key, 'section_B') === 0) {
                        $label = $key;
                        $sectionB[$label] = $value;
                    }
                    if (strpos($key, 'section_C') === 0) {
                        $label = $key;
                        $sectionC[$label] = $value;
                    }
                }
    
                $pengukuran = ["section_A" => $request->section_A, "section_B" => $request->section_B, "section_C" => $request->section_C];
    
                $data = new DataLapanganErgonomi();
                if ($request->no_order != '')
                    $data->no_order = $request->no_order;
                
                if (strtoupper(trim($request->no_sample)) != '')
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                if ($request->pekerja != '')
                    $data->nama_pekerja = $request->pekerja;
                if ($request->divisi != '')
                    $data->divisi = $request->divisi;
                if ($request->usia != '')
                    $data->usia = $request->usia;
                $data->lama_kerja = json_encode($request->year . " Tahun" . ", " . $request->month . " Bulan");
                if ($request->kelamin != '')
                    $data->jenis_kelamin = $request->kelamin;
                if ($request->waktu_bekerja != '')
                    $data->waktu_bekerja = $request->waktu_bekerja;
                if ($request->aktivitas != '')
                    $data->aktivitas = $request->aktivitas;
                $data->method = $request->method;
                $data->pengukuran = json_encode($pengukuran);
                if ($request->foto_samping_kiri != '')
                    $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
                if ($request->foto_samping_kanan != '')
                    $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
                if ($request->foto_depan != '')
                    $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
                if ($request->foto_belakang != '')
                    $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
                $data->aktivitas_ukur = $request->aktivitas_ukur;
                $data->permission = $request->permis;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                // dd($data);
                $data->save();

                // UPDATE ORDER DETAIL
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->where('kategori_3', 'LIKE', '%27-%')
                    ->orWhere('kategori_3', 'LIKE', '%53-%')
                    ->where('parameter', 'LIKE', '%Ergonomi%')
                    ->update([
                        'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);
            }catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode()
                ], 401);
            }
        } else if ($request->method == 10) {
            DB::beginTransaction();
            try{
                
                $inputs = $request->all();
                $sectionA = [];
                $sectionB = [];
                $sectionC = [];
    
                foreach ($inputs as $key => $value) {
                    if (strpos($key, 'section_A') === 0) {
                        $label = $key;
                        $sectionA[$label] = $value;
                    }
                    if (strpos($key, 'section_B') === 0) {
                        $label = $key;
                        $sectionB[$label] = $value;
                    }
                    if (strpos($key, 'section_C') === 0) {
                        $label = $key;
                        $sectionC[$label] = $value;
                    }
                }
    
                $pengukuran = ["section_A" => $request->section_A, "section_B" => $request->section_B, "section_C" => $request->section_C];
    
                $data = new DataLapanganErgonomi();
                if ($request->no_order != '')
                    $data->no_order = $request->no_order;
                
                if (strtoupper(trim($request->no_sample)) != '')
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                if ($request->pekerja != '')
                    $data->nama_pekerja = $request->pekerja;
                if ($request->divisi != '')
                    $data->divisi = $request->divisi;
                if ($request->usia != '')
                    $data->usia = $request->usia;
                $data->lama_kerja = json_encode($request->year . " Tahun" . ", " . $request->month . " Bulan");
                if ($request->kelamin != '')
                    $data->jenis_kelamin = $request->kelamin;
                if ($request->waktu_bekerja != '')
                    $data->waktu_bekerja = $request->waktu_bekerja;
                if ($request->aktivitas != '')
                    $data->aktivitas = $request->aktivitas;
                $data->method = $request->method;
                $data->pengukuran = json_encode($pengukuran);
                if ($request->foto_samping_kiri != '')
                    $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
                if ($request->foto_samping_kanan != '')
                    $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
                if ($request->foto_depan != '')
                    $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
                if ($request->foto_belakang != '')
                    $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
                $data->aktivitas_ukur = $request->aktivitas_ukur;
                $data->permission = $request->permis;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // UPDATE ORDER DETAIL
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->where('kategori_3', 'LIKE', '%27-%')
                    ->orWhere('kategori_3', 'LIKE', '%53-%')
                    ->where('parameter', 'LIKE', '%Ergonomi%')
                    ->update([
                        'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);
            }catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode()
                ], 401);
            }
        }
        
    }

    public function indexErgonomi(Request $request)
    {
        try {
            $data = array();
            if ($request->tipe != '') {
                $data = DataLapanganErgonomi::with('detail')->where('created_by', $this->karyawan)->orderBy('id', 'desc');
            } else {
                if ($request->method == 1) {
                    $data = DataLapanganErgonomi::with('detail')->where('method', 1)->where('created_by', $this->karyawan)->whereDate('created_at', '>=', Carbon::now()->subDays(3))
                        ->orderBy('id', 'desc');
                } else if ($request->method == 2) {
                    $data = DataLapanganErgonomi::with('detail')->where('method', 2)->where('created_by', $this->karyawan)->whereDate('created_at', '>=', Carbon::now()->subDays(3))
                        ->orderBy('id', 'desc');
                } else if ($request->method == 3) {
                    $data = DataLapanganErgonomi::with('detail')->where('method', 3)->where('created_by', $this->karyawan)->whereDate('created_at', '>=', Carbon::now()->subDays(3))
                        ->orderBy('id', 'desc');
                } else if ($request->method == 4) {
                    $data = DataLapanganErgonomi::with('detail')->where('method', 4)->where('created_by', $this->karyawan)->whereDate('created_at', '>=', Carbon::now()->subDays(3))
                        ->orderBy('id', 'desc');
                } else if ($request->method == 5) {
                    $data = DataLapanganErgonomi::with('detail')->where('method', 5)->where('created_by', $this->karyawan)->whereDate('created_at', '>=', Carbon::now()->subDays(3))
                        ->orderBy('id', 'desc');
                } else if ($request->method == 6) {
                    $data = DataLapanganErgonomi::with('detail')->where('method', 6)->where('created_by', $this->karyawan)->whereDate('created_at', '>=', Carbon::now()->subDays(3))
                        ->orderBy('id', 'desc');;
                } else if ($request->method == 7) {
                    $data = DataLapanganErgonomi::with('detail')
                        ->where('method', 7)
                        ->whereDate('created_at', '>=', Carbon::now()->subDays(3))->where('created_by', $this->karyawan)
                        ->orderBy('id', 'desc');
                } else if ($request->method == 8) {
                    $data = DataLapanganErgonomi::with('detail')->where('method', 8)->where('created_by', $this->karyawan)->whereDate('created_at', '>=', Carbon::now()->subDays(3))
                        ->orderBy('id', 'desc');
                } else if ($request->method == 9) {
                    $data = DataLapanganErgonomi::with('detail')->where('method', 9)->where('created_by', $this->karyawan)->whereDate('created_at', '>=', Carbon::now()->subDays(3))
                        ->orderBy('id', 'desc');
                } else if ($request->method == 10) {
                    $data = DataLapanganErgonomi::with('detail')->where('method', 10)->where('created_by', $this->karyawan)->whereDate('created_at', '>=', Carbon::now()->subDays(3))
                        ->orderBy('id', 'desc');
                }
            }
            $this->resultx = 'Show Ergonomi Success';
            return Datatables::of($data)->make(true);
        } catch (Exception $e) {
            dd($e);
        }
    }

    public function approveErgonomi(Request $request)
    {
        try {
            if ($request->method == 1) {
                if (isset($request->id) && $request->id != null) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    // dd($data);
                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Gagal Approve'
                    ], 401);
                }
            } else if ($request->method == 2) {
                if (isset($request->id) && $request->id != null) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    // dd($data);
                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Gagal Approve'
                    ], 401);
                }
            } else if ($request->method == 3) {
                if (isset($request->id) && $request->id != null) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    // dd($data);
                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Gagal Approve'
                    ], 401);
                }
            } else if ($request->method == 4) {
                if (isset($request->id) && $request->id != null) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    // dd($data);
                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Gagal Approve'
                    ], 401);
                }
            } else if ($request->method == 5) {
                if (isset($request->id) && $request->id != null) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    // dd($data);
                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Gagal Approve'
                    ], 401);
                }
            } else if ($request->method == 6) {
                if (isset($request->id) && $request->id != null) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    // dd($data);
                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Gagal Approve'
                    ], 401);
                }
            } else if ($request->method == 7) {
                if (isset($request->id) && $request->id != null) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $no_sample = $data->no_sampel;
                    // $po = Po::where('id', $data->id_po)->first();
                    // $param = Parameter::where('name', json_decode($po->param))->first();
                    // $header = Ergonomiheader::where('no_sample', $no_sample)->where('active', 0)->first();
                    // dd($dat);

                    // $header = new Ergonomiheader;
                    // $header->no_sample = $no_sample;
                    // $header->id_po = $data->id_po;
                    // $header->id_parameter = $param->id;
                    // $header->parameter = $param->name;
                    // $header->add_by = $this->karyawan;
                    // $header->add_at = Carbon::now()->format('Y-m-d H:i:s');
                    // $header->par = 19;
                    // $header->is_approve = true;
                    // $header->approved_by = $this->karyawan;
                    // $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    // $header->id_lapangan = $data->id;
                    // $header->save();

                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Gagal Approve'
                    ], 401);
                }
            } else if ($request->method == 8) {
                if (isset($request->id) && $request->id != null) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    $no_sample = $data->no_sampel;
                    // $po = Po::where('id', $data->id_po)->first();
                    // $param = Parameter::where('name', json_decode($po->param))->first();
                    // $header = Ergonomiheader::where('no_sample', $no_sample)->where('active', 0)->where('id_lapangan', $request->id)->first();

                    // $header = new Ergonomiheader;
                    // $header->no_sample = $no_sample;
                    // $header->id_po = $data->id_po;
                    // $header->id_parameter = $param->id;
                    // $header->param = $param->name;
                    // $header->add_by = $this->karyawan;
                    // $header->add_at = Carbon::now()->format('Y-m-d H:i:s');
                    // $header->id_lapangan = $data->id;
                    // $header->par = 19;
                    // $header->is_approve = true;
                    // $header->approved_by = $this->karyawan;
                    // $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    // $header->id_lapangan = $data->id;
                    // // dd($header);
                    // $header->save();

                    // dd($dat);
                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Gagal Approve'
                    ], 401);
                }
            } else if ($request->method == 9) {
                if (isset($request->id) && $request->id != null) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    // dd($data);
                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Gagal Approve'
                    ], 401);
                }
            } else if ($request->method == 10) {
                if (isset($request->id) && $request->id != null) {
                    $data = DataLapanganErgonomi::where('id', $request->id)->first();
                    // dd($data);
                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                    return response()->json([
                        'message' => 'Data has ben Approved',
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Gagal Approve'
                    ], 401);
                }
            }
        } catch (Exception $e) {
            dd($e);
        }
    }

    public function detailErgonomi(Request $request)
    {
        if ($request->tipe != '') {
            $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
            $po = OrderDetail::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();
            $this->resultx = 'get Detail ergonomi success';
            return response()->json([
                'data_lapangan' => $data,
                'data_po' => $po,
            ], 200);
        } else {
            if ($request->method == 1) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'sebelum_kerja' => $data->sebelum_kerja,
                    'setelah_kerja' => $data->setelah_kerja,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                ], 200);
            } else if ($request->method == 2) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';
                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 3) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 4) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 5) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'berat_beban' => $data->berat_beban,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'frek_jml_angkatan' => $data->frek_jml_angkatan,
                    'kopling_tangan' => $data->kopling_tangan,
                    'jarak_vertikal' => $data->jarak_vertikal,
                    'durasi_jam_kerja' => $data->durasi_jam_kerja,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 6) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'durasi_jam_kerja' => $data->durasi_jam_kerja,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 7) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 8) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 9) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 10) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            }
        }
    }

    public function deleteErgonomi(Request $request)
    {
        if ($request->method == 1) {
            if (isset($request->id) && $request->id != null) {
                $cek = DataLapanganErgonomi::where('id', $request->id)->first();
                $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kiri;
                $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kanan;
                $foto_depan = public_path() . '/dokumentasi/sampling/' . $cek->foto_depan;
                $foto_belakang = public_path() . '/dokumentasi/sampling/' . $cek->foto_belakang;
                if (is_file($foto_samping_kiri)) {
                    unlink($foto_samping_kiri);
                }
                if (is_file($foto_samping_kanan)) {
                    unlink($foto_samping_kanan);
                }
                if (is_file($foto_depan)) {
                    unlink($foto_depan);
                }
                if (is_file($foto_belakang)) {
                    unlink($foto_belakang);
                }
                $cek->delete();
                return response()->json([
                    'message' => 'Data has ben Deleted',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        } else if ($request->method == 2) {
            if (isset($request->id) && $request->id != null) {
                $cek = DataLapanganErgonomi::where('id', $request->id)->first();
                $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kiri;
                $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kanan;
                $foto_depan = public_path() . '/dokumentasi/sampling/' . $cek->foto_depan;
                $foto_belakang = public_path() . '/dokumentasi/sampling/' . $cek->foto_belakang;
                if (is_file($foto_samping_kiri)) {
                    unlink($foto_samping_kiri);
                }
                if (is_file($foto_samping_kanan)) {
                    unlink($foto_samping_kanan);
                }
                if (is_file($foto_depan)) {
                    unlink($foto_depan);
                }
                if (is_file($foto_belakang)) {
                    unlink($foto_belakang);
                }
                $cek->delete();
                return response()->json([
                    'message' => 'Data has ben Deleted',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        } else if ($request->method == 3) {
            if (isset($request->id) && $request->id != null) {
                $cek = DataLapanganErgonomi::where('id', $request->id)->first();
                $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kiri;
                $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kanan;
                $foto_depan = public_path() . '/dokumentasi/sampling/' . $cek->foto_depan;
                $foto_belakang = public_path() . '/dokumentasi/sampling/' . $cek->foto_belakang;
                if (is_file($foto_samping_kiri)) {
                    unlink($foto_samping_kiri);
                }
                if (is_file($foto_samping_kanan)) {
                    unlink($foto_samping_kanan);
                }
                if (is_file($foto_depan)) {
                    unlink($foto_depan);
                }
                if (is_file($foto_belakang)) {
                    unlink($foto_belakang);
                }
                $cek->delete();
                return response()->json([
                    'message' => 'Data has ben Deleted',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        } else if ($request->method == 4) {
            if (isset($request->id) && $request->id != null) {
                $cek = DataLapanganErgonomi::where('id', $request->id)->first();
                $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kiri;
                $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kanan;
                $foto_depan = public_path() . '/dokumentasi/sampling/' . $cek->foto_depan;
                $foto_belakang = public_path() . '/dokumentasi/sampling/' . $cek->foto_belakang;
                if (is_file($foto_samping_kiri)) {
                    unlink($foto_samping_kiri);
                }
                if (is_file($foto_samping_kanan)) {
                    unlink($foto_samping_kanan);
                }
                if (is_file($foto_depan)) {
                    unlink($foto_depan);
                }
                if (is_file($foto_belakang)) {
                    unlink($foto_belakang);
                }
                $cek->delete();
                return response()->json([
                    'message' => 'Data has ben Deleted',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        } else if ($request->method == 5) {
            if (isset($request->id) && $request->id != null) {
                $cek = DataLapanganErgonomi::where('id', $request->id)->first();
                $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kiri;
                $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kanan;
                $foto_depan = public_path() . '/dokumentasi/sampling/' . $cek->foto_depan;
                $foto_belakang = public_path() . '/dokumentasi/sampling/' . $cek->foto_belakang;
                if (is_file($foto_samping_kiri)) {
                    unlink($foto_samping_kiri);
                }
                if (is_file($foto_samping_kanan)) {
                    unlink($foto_samping_kanan);
                }
                if (is_file($foto_depan)) {
                    unlink($foto_depan);
                }
                if (is_file($foto_belakang)) {
                    unlink($foto_belakang);
                }
                $cek->delete();
                return response()->json([
                    'message' => 'Data has ben Deleted',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        } else if ($request->method == 6) {
            if (isset($request->id) && $request->id != null) {
                $cek = DataLapanganErgonomi::where('id', $request->id)->first();
                $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kiri;
                $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kanan;
                $foto_depan = public_path() . '/dokumentasi/sampling/' . $cek->foto_depan;
                $foto_belakang = public_path() . '/dokumentasi/sampling/' . $cek->foto_belakang;
                if (is_file($foto_samping_kiri)) {
                    unlink($foto_samping_kiri);
                }
                if (is_file($foto_samping_kanan)) {
                    unlink($foto_samping_kanan);
                }
                if (is_file($foto_depan)) {
                    unlink($foto_depan);
                }
                if (is_file($foto_belakang)) {
                    unlink($foto_belakang);
                }
                $cek->delete();
                return response()->json([
                    'message' => 'Data has ben Deleted',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        } else if ($request->method == 7) {
            if (isset($request->id) && $request->id != null) {
                $cek = DataLapanganErgonomi::where('id', $request->id)->first();
                $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kiri;
                $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kanan;
                $foto_depan = public_path() . '/dokumentasi/sampling/' . $cek->foto_depan;
                $foto_belakang = public_path() . '/dokumentasi/sampling/' . $cek->foto_belakang;
                if (is_file($foto_samping_kiri)) {
                    unlink($foto_samping_kiri);
                }
                if (is_file($foto_samping_kanan)) {
                    unlink($foto_samping_kanan);
                }
                if (is_file($foto_depan)) {
                    unlink($foto_depan);
                }
                if (is_file($foto_belakang)) {
                    unlink($foto_belakang);
                }
                $cek->delete();
                return response()->json([
                    'message' => 'Data has ben Deleted',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        } else if ($request->method == 8) {
            if (isset($request->id) && $request->id != null) {
                $cek = DataLapanganErgonomi::where('id', $request->id)->first();
                $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kiri;
                $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kanan;
                $foto_depan = public_path() . '/dokumentasi/sampling/' . $cek->foto_depan;
                $foto_belakang = public_path() . '/dokumentasi/sampling/' . $cek->foto_belakang;
                if (is_file($foto_samping_kiri)) {
                    unlink($foto_samping_kiri);
                }
                if (is_file($foto_samping_kanan)) {
                    unlink($foto_samping_kanan);
                }
                if (is_file($foto_depan)) {
                    unlink($foto_depan);
                }
                if (is_file($foto_belakang)) {
                    unlink($foto_belakang);
                }
                $cek->delete();
                return response()->json([
                    'message' => 'Data has ben Deleted',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        } else if ($request->method == 9) {
            if (isset($request->id) && $request->id != null) {
                $cek = DataLapanganErgonomi::where('id', $request->id)->first();
                $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kiri;
                $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kanan;
                $foto_depan = public_path() . '/dokumentasi/sampling/' . $cek->foto_depan;
                $foto_belakang = public_path() . '/dokumentasi/sampling/' . $cek->foto_belakang;
                if (is_file($foto_samping_kiri)) {
                    unlink($foto_samping_kiri);
                }
                if (is_file($foto_samping_kanan)) {
                    unlink($foto_samping_kanan);
                }
                if (is_file($foto_depan)) {
                    unlink($foto_depan);
                }
                if (is_file($foto_belakang)) {
                    unlink($foto_belakang);
                }
                $cek->delete();
                return response()->json([
                    'message' => 'Data has ben Deleted',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        } else if ($request->method == 10) {
            if (isset($request->id) && $request->id != null) {
                $cek = DataLapanganErgonomi::where('id', $request->id)->first();
                $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kiri;
                $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kanan;
                $foto_depan = public_path() . '/dokumentasi/sampling/' . $cek->foto_depan;
                $foto_belakang = public_path() . '/dokumentasi/sampling/' . $cek->foto_belakang;
                if (is_file($foto_samping_kiri)) {
                    unlink($foto_samping_kiri);
                }
                if (is_file($foto_samping_kanan)) {
                    unlink($foto_samping_kanan);
                }
                if (is_file($foto_depan)) {
                    unlink($foto_depan);
                }
                if (is_file($foto_belakang)) {
                    unlink($foto_belakang);
                }
                $cek->delete();
                return response()->json([
                    'message' => 'Data has ben Deleted',
                    'cat' => 1
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }
        }
    }

    public function getErgonomi(Request $request)
    {

        $fdl = DataLapanganErgonomi::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

        // Check if method is valid and execute accordingly
        $method = $request->method;
        if ($method >= 1 && $method <= 10) {
            return $this->processMethod($request, $fdl, $method);
        }

        return response()->json(['message' => 'Method not found.'], 400);
    }

    private function processMethod($request, $fdl, $method)
    {
        try {
            // Check for the existence of the sample with the appropriate category and parameter
            $check = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
                ->whereIn('kategori_3', ['27-Udara Lingkungan Kerja', '11-Udara Ambient', '53-Ergonomi'])
                ->where('parameter', 'LIKE', '%Ergonomi%')
                ->where('is_active', true)
                ->first();


            // Check if the data for the given method already exists
            $data = DataLapanganErgonomi::where('no_sampel', strtoupper(trim($request->no_sample)))
                ->where('method', $method)
                ->first();

            // Respond based on whether the data already exists
            if ($check) {
                if ($data) {
                    return response()->json(['message' => 'No. Sample sudah di input.'], 401);
                } else {
                    return response()->json([
                        'message' => 'Successful.',
                        'data' => $fdl
                    ], 200);
                }
            } else {
                return response()->json(['message' => 'Tidak ada parameter ergonomi di No. Sample tersebut.'], 401);
            }
        } catch (Exception $e) {
            dd($e);
        }
    }

    // JUMLAH SKOR POSTUR TUBUH
    private function calculateTotalDurasi($data){
        $totalDurasi = 0;

        // Periksa apakah data adalah array
        if (is_array($data)) {
            foreach ($data as $section => $values) {
                // Periksa apakah $values adalah array
                if (is_array($values)) {
                    foreach ($values as $subSection => $details) {
                        // Periksa apakah detail memiliki 'Durasi Gerakan'
                        if (isset($details['Durasi Gerakan']) && $details['Durasi Gerakan'] !== 'Tidak') {
                            // Cek apakah nilai 'Durasi Gerakan' dapat dipisah dengan benar
                            $durasi = explode(';', $details['Durasi Gerakan'])[0];
                            
                            // Pastikan durasi adalah angka
                            if (is_numeric($durasi)) {
                                $totalDurasi += (int)$durasi;
                            } else {
                                // Tambahkan log untuk kasus durasi yang tidak valid
                                // Misalnya, jika nilai 'Durasi Gerakan' tidak bisa diproses
                                Log::warning("Durasi Gerakan tidak valid: {$details['Durasi Gerakan']}");
                            }
                        }
                    }
                }
            }
        }

        return $totalDurasi;
    }

    // Partikulat Isokinetik
    public function addPartikulatIsokinetik(Request $request)
    {
        if ($request->method == 1) {
            DB::beginTransaction();
            try {
                if ($request->waktu == '') {
                    return response()->json([
                        'message' => 'Waktu Pengambilan tidak boleh kosong.'
                    ], 401);
                }

                if ($request->durasiOp != null) {
                    $durasiOp = $request->durasiOp . ' ' . $request->satDur;
                }

                $data = new DataLapanganIsokinetikSurveiLapangan();
                $data->nama_perusahaan = $request->nama_perusahaan ?? '';
                $data->titik_koordinat = $request->posisi ?? '-';
                $data->latitude = $request->lat ?? '';
                $data->longitude = $request->longi ?? '';
                $data->keterangan = $request->keterangan ?? '';
                $data->sumber_emisi = $request->sumber ?? '';
                $data->merk = $request->merk ?? '';
                $data->bahan_bakar = $request->bakar ?? '';
                $data->cuaca = $request->cuaca ?? '';
                $data->kecepatan = $request->kecepatan ?? '';
                $data->diameter_cerobong = $request->diameter ?? '';
                $data->bentuk_cerobong = $request->bentuk ?? '';
                $data->jam_operasi = $durasiOp ?? '';
                $data->proses_filtrasi = $request->filtrasi ?? '';
                $data->waktu_survei = $request->waktu ?? '';
                $data->ukuran_lubang = $request->lubang ?? '';
                $data->jumlah_lubang_sampling = $request->jumlah_lubang ?? '';
                $data->lebar_platform = $request->lebar ?? '';
                $data->jarak_upstream = $request->jarakUp ?? '';
                $data->jarak_downstream = $request->jarakDown ?? '';
                $data->kategori_upstream = $request->kategUp ?? '';
                $data->kategori_downstream = $request->kategDown ?? '';
                $data->lintas_partikulat = $request->lintasPartikulat ?? '';
                $data->kecepatan_linier = $request->kecLinier ?? '';
                $data->lfw = $request->lfw ?? '';
                $data->lnw = $request->lnw ?? '';
                $data->titik_lintas_partikulat_s = $request->titikPar_s ?? '';
                $data->titik_lintas_kecepatan_linier_s = $request->titikLin_s ?? '';
                if ($request->jarakPar_s !== '') {
                    $data->jarak_partikulat_s = $request->jarakPar_s;
                } else {
                    $data->jarak_partikulat_s = $request->jarakpersegiPar_s;
                }

                if ($request->jarakLin_s !== '') {
                    $data->jarak_linier_s = $request->jarakLin_s;
                } else {
                    $data->jarak_linier_s = $request->jarakpersegiLin_s;
                }
                $data->filename_denah = $request->foto_convert ? self::convertImg($request->foto_convert, 0, $this->user_id) : '';
                $data->foto_lokasi_sampel = $request->foto_lok ? self::convertImg($request->foto_lok, 1, $this->user_id) : '';
                $data->foto_kondisi_sampel = $request->foto_sampl ? self::convertImg($request->foto_sampl, 2, $this->user_id) : '';
                $data->foto_lain = $request->foto_lain ? self::convertImg($request->foto_lain, 3, $this->user_id) : '';
                $data->permission = $request->permis ?? '';
                $data->no_survei = substr(str_replace(".", "", microtime(true)), 0, 8);
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // UPDATE ORDER DETEAIL
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->update([
                        'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage()
                ], 500);
            }
        } else if ($request->method == 2) {
            DB::beginTransaction();
            try {
                if ($request->waktu == '') {
                    return response()->json([
                        'message' => 'Waktu Pengambilan tidak boleh kosong.'
                    ], 401);
                }
                $check = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                if ($check) {
                    return response()->json([
                        'message' => 'No sample ' . strtoupper(trim($request->no_sample)) . ' Sudah Terinput Pada Method 2.!'
                    ], 401);
                } else {

                    $pengDb = array();
                    $tot = count($request->dataDp);
                    for ($p = 1; $p <= $tot; $p++) {
                        $pengu = [];
                        foreach ($request->dataDp[$p] as $key => $val) {
                            array_push($pengu, (object)[
                                'nilaiDp' => $request->dataDp[$p][$key],
                                'suhu' => $request->dataSuhu[$p][$key],
                                'paps' => $request->dataPaps[$p][$key],
                            ]);
                        }
                        array_push($pengDb, (object) [
                            'pengukuran' => $pengu,
                            'reratadp' => $request->reratadataDp[$p],
                            'reratasuhu' => $request->reratadataSuhu[$p],
                            'reratapaps' => $request->reratadataPaps[$p],
                        ]);
                    }
                    $ujialiran = [];
                    $pengalir = [];
                    if ($request->datadelta) {
                        foreach ($request->datadelta as $k => $v) {
                            array_push($pengalir, (object)[
                                'delta' => $request->datadelta[$key],
                                'sudut' => $request->datasudut[$key],
                            ]);
                        }
                        array_push($ujialiran, (object)[
                            'pengukuran' => $pengalir,
                            'rdelta' => $request->redelta,
                            'rsudut' => $request->resudut,
                        ]);
                    }

                    $data = new DataLapanganIsokinetikPenentuanKecepatanLinier();
                    if ($request->no_survei != '')
                        $data->no_survei = $request->no_survei;
                    if ($request->id_lapangan != '')
                        $data->id_lapangan = $request->id_lapangan;
                    if (strtoupper(trim($request->no_sample)) != '')
                        $data->no_sampel = strtoupper(trim($request->no_sample));
                    if ($request->diameter != '')
                        $data->diameter_cerobong = $request->diameter;
                    if ($request->suhu != '')
                        $data->suhu = $request->suhu;
                    if ($request->kelem != '')
                        $data->kelembapan = $request->kelem;
                    if ($request->tekU != '')
                        $data->tekanan_udara = $request->tekU;
                    if ($request->linKec != '')
                        $data->kecLinier = $request->linKec;
                    if ($request->kp != '')
                        $data->kp = $request->kp;
                    if ($request->cp != '')
                        $data->cp = $request->cp;
                    if ($request->tekPa != '')
                        $data->tekPa = $request->tekPa;
                    if ($request->waktu != '')
                        $data->waktu_pengukuran = $request->waktu;
                    if ($request->dP != '')
                        $data->dP = $request->dP;
                    if ($request->TM != '')
                        $data->TM = $request->TM;
                    if ($request->Ps != '')
                        $data->Ps = $request->Ps;
                    if ($request->nilCerobong != '')
                        $data->rerata_suhu = $request->nilCerobong;
                    if ($request->nilPaPs != '')
                        $data->rerata_paps = $request->nilPaPs;
                    if ($request->status_test != '')
                        $data->status_test = $request->status_test;
                    if ($request->jaminan_mutu != '')
                        $data->jaminan_mutu = json_encode($request->jaminan_mutu);
                    $data->dataDp = $pengDb;
                    $data->uji_aliran = $ujialiran;
                    if ($request->foto_lok != '')
                        $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
                    if ($request->foto_sampl != '')
                        $data->foto_kondisi_sampel = self::convertImg($request->foto_sampl, 2, $this->user_id);
                    if ($request->foto_lain != '')
                        $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
                    if ($request->permis != '')
                        $data->permission = $request->permis;
                    $data->created_by = $this->karyawan;
                    $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    // UPDATE ORDER DETAIL
                    DB::table('order_detail')
                        ->where('no_sampel', strtoupper(trim($request->no_sample)))
                        ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                    DB::commit();
                    return response()->json([
                        'message' => 'Data berhasil disimpan.'
                    ], 200);
                }
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e.getMessage(),
                    'line' => $e.getLineNumber(),
                    'code' => $e.getCode()
                ], 401);
            }
        } else if ($request->method == 3) {
            // DB::beginTransaction();
            // try{

            //     if ($request->waktu == '') {
            //         return response()->json([
            //             'message' => 'Waktu Pengambilan tidak boleh kosong.'
            //         ], 401);
            //     }
            //     $nilai_array = array();
            //     $jumc = 12;
    
            //     $cek = DataLapanganIsokinetikBeratMolekul::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
    
            //     foreach ($cek as $key => $value) {
            //         $durasi = $value->shift;
            //         $nilai_array[$key] = str_replace('"', "", $durasi);
            //     }
    
            //     if (in_array($request->shift, $nilai_array)) {
            //         return response()->json([
            //             'message' => 'Pengambilan Shift ' . $request->shift . ' sudah ada !'
            //         ], 401);
            //     }
            //     $c_count = $cek->count();
            //     if ($c_count > $jumc) {
            //         return response()->json([
            //             'message' => 'No Sample sudah dilakukan input sebanyak ' . $jumc . ' Kali'
            //         ], 401);
            //     }
            //     $data = new DataLapanganIsokinetikBeratMolekul();
            //     if ($request->id_lapangan != '')
            //         $data->id_lapangan = $request->id_lapangan;
            //     if (strtoupper(trim($request->no_sample)) != '')
            //         $data->no_sampel = strtoupper(trim($request->no_sample));
            //     if ($request->diameter != '')
            //         $data->diameter = $request->diameter;
            //     if ($request->waktu != '')
            //         $data->waktu = $request->waktu;
            //     if ($request->o2 != '')
            //         $data->O2 = $request->o2;
            //     if ($request->co != '')
            //         $data->CO = $request->co;
            //     if ($request->co2 != '')
            //         $data->CO2 = $request->co2;
            //     if ($request->no != '')
            //         $data->NO = $request->no;
            //     if ($request->nox != '')
            //         $data->NOx = $request->nox;
            //     if ($request->no2 != '')
            //         $data->NO2 = $request->no2;
            //     if ($request->so2 != '')
            //         $data->SO2 = $request->so2;
            //     if ($request->suhu != '')
            //         $data->suhu_cerobong = $request->suhu;
            //     if ($request->o2Mole != '')
            //         $data->O2Mole = $request->o2Mole;
            //     if ($request->co2Mole != '')
            //         $data->CO2Mole = $request->co2Mole;
            //     if ($request->coMole != '')
            //         $data->COMole = $request->coMole;
            //     if ($request->Ts != '')
            //         $data->Ts = $request->Ts;
            //     if ($request->n2Mole != '')
            //         $data->N2Mole = $request->n2Mole;
            //     if ($request->mdMole != '')
            //         $data->MdMole = $request->mdMole;
            //     if ($request->nCO2 != '')
            //         $data->nCO2 = $request->nCO2;
            //     if ($request->combustion != '')
            //         $data->combustion = $request->combustion;
            //     if ($request->shift != '')
            //         $data->shift = $request->shift;
            //     if ($request->foto_lok != '')
            //         $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
            //     if ($request->foto_sampl != '')
            //         $data->foto_kondisi_sampel = self::convertImg($request->foto_sampl, 2, $this->user_id);
            //     if ($request->foto_lain != '')
            //         $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
            //     if ($request->permis != '')
            //         $data->permission = $request->permis;
            //     $data->created_by = $this->karyawan;
            //     $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            //     $data->save();
                
            //     // UPDATE ORDER DETAIL

            //     $update = DB::table('order_detail')
            //         ->where('no_sampel', strtoupper(trim($request->no_sample)))
            //         ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            //     DB::commit();
            //     return response()->json([
            //         'message' => 'Data berhasil disimpan.'
            //     ], 200);
            // }catch(\Exception $e){
            //     DB::rollBack();
            //     return response()->json([
            //         'message' => 'Terjadi Kesalahan. '.$e.getMessage(),
            //         'line' => $e.getLineNumber(),
            //         'code' => $e.getCode()
            //     ], 401);
            // }
            DB::beginTransaction();
            try {
                if (empty($request->waktu)) {
                    return response()->json([
                        'message' => 'Waktu Pengambilan tidak boleh kosong.'
                    ], 401);
                }

                $noSample = strtoupper(trim($request->no_sample));
                $maxEntries = 12;

                $existingEntries = DataLapanganIsokinetikBeratMolekul::where('no_sampel', $noSample)->get();
                $existingShifts = $existingEntries->pluck('shift')->map(fn($shift) => str_replace('"', '', $shift))->toArray();

                if (in_array($request->shift, $existingShifts)) {
                    return response()->json([
                        'message' => 'Pengambilan Shift ' . $request->shift . ' sudah ada!'
                    ], 401);
                }

                if ($existingEntries->count() >= $maxEntries) {
                    return response()->json([
                        'message' => 'No Sample sudah dilakukan input sebanyak ' . $maxEntries . ' kali.'
                    ], 401);
                }
                
                $data = DataLapanganIsokinetikBeratMolekul::create([
                    'id_lapangan' => $request->id_lapangan ?: null,
                    'no_sampel' => $noSample,
                    'diameter' => $request->diameter,
                    'waktu' => $request->waktu,
                    'O2' => $request->o2,
                    'CO' => $request->co,
                    'CO2' => $request->co2,
                    'NO' => $request->no,
                    'NOx' => $request->nox,
                    'NO2' => $request->no2,
                    'SO2' => $request->so2,
                    'suhu_cerobong' => $request->suhu,
                    'O2Mole' => $request->o2Mole,
                    'CO2Mole' => $request->co2Mole,
                    'COMole' => $request->coMole,
                    'Ts' => $request->Ts,
                    'N2Mole' => $request->n2Mole,
                    'MdMole' => $request->mdMole,
                    'nCO2' => $request->nCO2,
                    'combustion' => $request->combustion,
                    'shift' => $request->shift,
                    'permission' => $request->permis,
                    'created_by' => $this->karyawan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

                

                // Handle images if provided
                if ($request->foto_lok) {
                    $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
                }
                if ($request->foto_sampl) {
                    $data->foto_kondisi_sampel = self::convertImg($request->foto_sampl, 2, $this->user_id);
                }
                if ($request->foto_lain) {
                    $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
                }

                $data->save();

                
                // Update order_detail
                DB::table('order_detail')
                    ->where('no_sampel', $noSample)
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d')]);
                
                DB::commit();

                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                dd($e);
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode()
                ], 500);
            }
        } else if ($request->method == 4) {
            if ($request->waktu == '') {
                return response()->json([
                    'message' => 'Waktu Pengambilan tidak boleh kosong.'
                ], 401);
            }

            $check = DataLapanganIsokinetikKadarAir::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if ($check) {
                return response()->json([
                    'message' => 'No sample ' . strtoupper(trim($request->no_sample)) . ' Sudah Terinput Pada Method 4.!'
                ], 401);
            } else {
                $data = new DataLapanganIsokinetikKadarAir();
                if ($request->id_lapangan != '')
                    $data->id_lapangan = $request->id_lapangan;
                if (strtoupper(trim($request->no_sample)) != '')
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                if ($request->waktu != '')
                    $data->waktu = $request->waktu;

                $data_impi = NULL;
                if ($request->impil) {
                    $data_impi = [];
                    foreach ($request->impil as $key => $val) {
                        array_push($data_impi, (object) [
                            'impinger_awal' => $request->impil[$key],
                            'impinger_akhir' => $request->impir[$key]
                        ]);
                    }
                }
                if ($request->metode_uji != '') $data->metode_uji = $request->metode_uji;
                if ($request->kadar_air != '') $data->kadar_air = $request->kadar_air;
                if ($request->laju_aliran != '') $data->laju_aliran = $request->laju_aliran;
                if ($request->impil) $data->data_impinger = $data_impi;
                if ($request->nily != '') $data->nilai_y = $request->nily;
                if ($request->nilpm != '') $data->Pm = $request->nilpm;
                if ($request->nilsuhu != '') $data->suhu_cerobong = $request->nilsuhu;
                if ($request->nildgmbaca != '') $data->data_dgmterbaca = $request->nildgmbaca;
                if ($request->nilaikaldgm != '') $data->data_kalkulasi_dgm = $request->nilaikaldgm;
                if ($request->jaminan_mutu != '') $data->jaminan_mutu = $request->jaminan_mutu;
                if ($request->nil_t_dgmawal != '') $data->data_dgm_test = ['dgm_awal' => $request->nil_t_dgmawal, 'dgm_akhir' => $request->nil_t_dgmakhir];
                if ($request->nil_t_kalkulasidgm != '') $data->dgm_test = $request->nil_t_kalkulasidgm;
                if ($request->nil_t_waktu != '') $data->waktu_test = $request->nil_t_waktu;
                if ($request->nil_t_laju != '') $data->laju_alir_test = $request->nil_t_laju;
                if ($request->nil_t_tekv != '') $data->tekV_test = $request->nil_t_tekv;
                if ($request->nil_t_hasil != '') $data->hasil_test = $request->nil_t_hasil;
                if ($request->nilvwc != '') $data->vwc = $request->nilvwc;
                if ($request->nilvmstd != '') $data->vmstd = $request->nilvmstd;
                if ($request->nilvwsg != '') $data->vwsg = $request->nilvwsg;
                if ($request->nil_p_bws != '') $data->bws = $request->nil_p_bws;
                if ($request->msMole != '') $data->ms = $request->msMole;
                if ($request->nil_vs != '') $data->vs = $request->nil_vs;
                if ($request->d_cerobong != '') $data->diameter_cerobong = $request->d_cerobong;
                if ($request->foto_lok != '') $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
                if ($request->foto_sampl != '')
                    $data->foto_kondisi_sampel = self::convertImg($request->foto_sampl, 2, $this->user_id);
                if ($request->foto_lain != '')
                    $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
                if ($request->permis != '')
                    $data->permission = $request->permis;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // Update Order Detail
                DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                return response()->json([
                    'message' => 'Data berhasil disimpan.'
                ], 200);
            }
        } else if ($request->method == 5) {
            DB::beginTransaction();
            try {

                $pengukuranDGM = [];
                if ($request->DGM != '') {
                    array_push($pengukuranDGM, (object) [
                        'nilaiDGM' => $request->DGM,
                        'avgDGM' => $request->lfDGM,
                    ]);
                }

                $pengukurandP = [];
                foreach ($request->dP as $key => $value) {

                    array_push($pengukurandP, (object) [
                        'lubang ' . $key => $request->dP[$key],
                        'avgDp lubang ' . $key => $request->avgDp[$key],
                    ]);
                }

                $pengukuranPaPs = [];

                // ZAKI
                foreach ($request->PaPs as $key => $value) {

                    array_push($pengukuranPaPs, (object) [
                        'lubang ' . $key => $request->PaPs[$key],
                        'avgPaPs lubang ' . $key => $request->avgPaPs[$key],
                    ]);
                }

                $pengukurandH = [];
                // ZAKI
                foreach ($request->dH as $key => $value) {

                    array_push($pengukurandH, (object) [
                        'lubang ' . $key => $request->dH[$key],
                        'avgdH lubang ' . $key => $request->avgDh[$key],
                    ]);
                }

                $pengukuranStack = [];
                foreach ($request->Stack as $key => $value) {

                    array_push($pengukuranStack, (object) [
                        'lubang ' . $key => $request->Stack[$key],
                        'avgStack lubang ' . $key => $request->avgStack[$key],
                    ]);
                }

                $pengukuranMeter = [];
                foreach ($request->Meter as $key => $value) {

                    array_push($pengukuranMeter, (object) [
                        'lubang ' . $key => $request->Meter[$key],
                        'avgMeter lubang ' . $key => $request->avgMeter[$key],
                    ]);
                }

                $pengukuranVp = [];
                foreach ($request->Vp as $key => $value) {

                    array_push($pengukuranVp, (object) [
                        'lubang ' . $key => $request->Vp[$key],
                        'avgVP lubang ' . $key => $request->avgVP[$key],
                    ]);
                }

                $pengukuranFilter = [];
                foreach ($request->Filter as $key => $value) {

                    array_push($pengukuranFilter, (object) [
                        'lubang ' . $key => $request->Filter[$key],
                        'avgFilter lubang ' . $key => $request->avgFilter[$key],
                    ]);
                }

                $pengukuranOven = [];
                foreach ($request->Oven as $key => $value) {

                    array_push($pengukuranOven, (object) [
                        'lubang ' . $key => $request->Oven[$key],
                        'avgOven lubang ' . $key => $request->avgOven[$key],
                    ]);
                }

                $pengukuranexit_impinger = [];
                foreach ($request->exit_impinger as $key => $value) {

                    array_push($pengukuranexit_impinger, (object) [
                        'lubang ' . $key => $request->exit_impinger[$key],
                        'avgExImp lubang ' . $key => $request->avgExImp[$key],
                    ]);
                }

                $pengukuranProbe = [];
                foreach ($request->Probe as $key => $value) {

                    array_push($pengukuranProbe, (object) [
                        'lubang ' . $key => $request->Probe[$key],
                        'avgProbe lubang ' . $key => $request->avgProbe[$key],
                    ]);
                }

                if ($request->waktu == '') {
                    return response()->json([
                        'message' => 'Waktu Pengambilan tidak boleh kosong.'
                    ], 401);
                }

                $arrsebelumpengujian = [
                    'volume_dgm' => $request->input('volumeDGMSebelum', null),
                    'total_waktu_test' => $request->input('totalWaktuTestSebelum', null),
                    'laju_alir' => $request->input('lajuAlirSebelum', null),
                    'tekanan_vakum' => $request->input('tekananVakumSebelum', null),
                    'hasil' => $request->input('hasilSebelum', null),
                ];

                $arrsesudahpengujian = [
                    'volume_dgm' => $request->input('volumeDGMSesudah', null),
                    'total_waktu_test' => $request->input('totalWaktuTestSesudah', null),
                    'laju_alir' => $request->input('lajuAlirSesudah', null),
                    'tekanan_vakum' => $request->input('tekananVakumSesudah', null),
                    'hasil' => $request->input('hasilSesudah', null),
                ];

                // AVERAGE STACK
                $totalAvgStack = 0;
                $count = 0;

                foreach ($request->Stack as $key => $value) {
                    // Menambahkan nilai avgStack ke total
                    $totalAvgStack += $request->avgStack[$key];
                    $count++; // Menghitung jumlah data
                }

                // Menghitung rata-rata jika ada data
                $average = $count > 0 ? $totalAvgStack / $count : 0;
                $TemperaturStack = $average +  273.15; // KONVERSI DARI CELSIUS KE KELVIN
                $TemperaturStackFormatted = number_format($TemperaturStack, 2);

                // END AVERAGE STACK

                // AVERAGE DGM VM
                // DGM AWAL
                $dgmAwal = (float) $request->dgmAwal; // atau sesuai dengan indeks yang diinginkan

                // RATA-RATA SELISIH DGM
                $selisihrataDGM = $request->rataRataSelisihDGM;

                if (!empty($request->DGM) && is_array($request->DGM)) {
                    $pengukuranDGMVM = [];
                    $totalSelisihKeseluruhan = 0; // Total selisih untuk seluruh data
                    $count = 0;

                    $allDGMData = [];
                    foreach ($request->DGM as $subArray) {
                        if (is_array($subArray)) {
                            $allDGMData = array_merge($allDGMData, $subArray);
                        }
                    }

                    // dd($allDGMData);
                    // Lakukan perhitungan selisih setelah menggabungkan
                    foreach ($allDGMData as $index => $value) {
                        $value = (float) $value; // Pastikan nilai adalah float
                        if ($index === 0) {
                            // Untuk data pertama, kurangkan dengan DGM Awal
                            $selisih = $value - $dgmAwal; // Menggunakan dgmAwal yang terpisah
                        } else {
                            // Untuk data selanjutnya, kurangkan dengan data sebelumnya
                            $previousValue = (float) $allDGMData[$index - 1];
                            $selisih = $value - $previousValue;
                        }


                        if ($value) { // hanya hitung jika ada nilai
                            $totalSelisihKeseluruhan += $selisih; // Tambahkan ke total keseluruhan
                            $count++;
                        }

                        // Menghitung persentase selisih
                        $persentaseSelisih = abs(($selisih - $selisihrataDGM) / $selisihrataDGM * 100);

                        // Simpan data ke dalam array
                        $selisihKey = "selisihDGM" . ($index + 1); // Menentukan kunci berdasarkan indeks
                        array_push($pengukuranDGMVM, (object) [
                            'nilaiDGM' . ($index + 1) => $value,
                            $selisihKey => number_format($persentaseSelisih, 1), // Menyimpan selisih per data
                        ]);
                    }
                }
                // END AVERAGE VM DGM


                // VS DP
                // AVERAGE
                $arraydP1 = []; // Array untuk menyimpan hasil akhir


                $totalCountPaPs = count($request->avgPaPs); // Hitung total count dari avgPaPs
                $totalAvgPaPs = 0; // Untuk menghitung total nilai avgPaPs

                foreach ($request->PaPs as $key => $value) {
                    // Pastikan avgPaPs[$key] ada
                    $avgPaPsValue = isset($request->avgPaPs[$key]) ? $request->avgPaPs[$key] : 0;
                    $totalAvgPaPs += $avgPaPsValue; // Tambahkan nilai avgPaPs ke total
                    $averageAvgPaPs = $totalCountPaPs > 0 ? $totalAvgPaPs / $totalCountPaPs : 0;
                }
                // Hitung rata-rata

                // Tambahkan rata-rata ke output
                // $pengukuranavgPaPs[] = (object) [
                //     'Rata-rata Avg PaPs' => number_format($averageAvgPaPs, 2) // Format dengan 2 desimal
                // ];
                $pengukuranavgPaPs = floatval(number_format($averageAvgPaPs, 2));

                $metode2 = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $metode4 = DataLapanganIsokinetikKadarAir::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                // Loop untuk menggabungkan data dan menerapkan rumus
                // dd($pengukurandP);
                $i = 1;
                foreach ($pengukurandP as $item) {
                    // dd($item);
                    foreach ($item as $key => $value) {
                        // Jika nilai adalah array, kita bisa menggabungkannya
                        if (is_array($value)) {
                            foreach ($value as $val) {
                                // Terapkan rumus
                                $dP = (float)$val; // Ambil nilai dP dari array
                                $hasil = floatval($metode2->kp) * floatval($metode2->cp) * pow((floatval($TemperaturStack) / ((floatval($metode2->tekanan_udara) - $pengukuranavgPaPs) * floatval($metode4->ms))), 0.5) * pow($dP, 0.5);
                                $hasil = round($hasil, 2);
                                // Simpan hasil ke dalam pengukurandP1
                                $arraydP1[] = [
                                    'nilai_' . $i => $dP,
                                    'hasil_' . $i => number_format($hasil, 1, '.', ''), // Menyimpan hasil perhitungan rumus
                                ];
                                $i++;
                            }
                        }
                    }
                }
                $check = DataLapanganIsokinetikPenentuanPartikulat::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                if ($check) {
                    return response()->json([
                        'message' => 'No sample ' . strtoupper(trim($request->no_sample)) . ' Sudah Terinput Pada Method 5.!'
                    ], 401);
                } else {
                    $data = new DataLapanganIsokinetikPenentuanPartikulat();
                    if ($request->id_lapangan != '')
                        $data->id_lapangan = $request->id_lapangan;
                    if (strtoupper(trim($request->no_sample)) != '')
                        $data->no_sampel = strtoupper(trim($request->no_sample));
                    if ($request->diameter != '')
                        $data->diameter = $request->diameter;
                    if ($request->titik_lintas_partikulat != '')
                        $data->titik_lintas_partikulat = $request->titik_lintas_partikulat;
                    if ($request->data_Y != '')
                        $data->data_Y = $request->data_Y;
                    if ($request->pbarm5 != '')
                        $data->pbar = $request->pbarm5;
                    if ($request->Delta_H != '')
                        $data->Delta_H = $request->Delta_H;
                    if ($request->dn_req != '')
                        $data->dn_req = $request->dn_req;
                    if ($request->k_iso != '')
                        $data->k_iso = $request->k_iso;
                    if ($request->delta_H_req != '')
                        $data->delta_H_req = $request->delta_H_req;
                    if ($request->dgmAwal != '')
                        $data->dgmAwal = $request->dgmAwal;
                    if ($request->waktu != '')
                        $data->waktu = $request->waktu;
                    if ($request->dn_actual != '')
                        $data->dn_actual = $request->dn_actual;
                    if ($request->impinger1 != '')
                        $data->impinger1 = $request->impinger1;
                    if ($request->impinger2 != '')
                        $data->impinger2 = $request->impinger2;
                    if ($request->impinger3 != '')
                        $data->impinger3 = $request->impinger3;
                    if ($request->impinger4 != '')
                        $data->impinger4 = $request->impinger4;
                    if ($request->Vs != '')
                        $data->Vs = $request->Vs;
                    if ($request->rataRataSelisihDGM != '')
                        $data->rataselisihdgm = $request->rataRataSelisihDGM;
                    $data->temperatur_stack = $TemperaturStackFormatted;
                    $data->data_total_vs = $arraydP1;
                    $data->delta_vm = $pengukuranDGMVM;
                    $data->DGM = $pengukuranDGM;
                    $data->dP = $pengukurandP;
                    $data->PaPs = $pengukuranPaPs;
                    $data->dH = $pengukurandH;
                    $data->Stack = $pengukuranStack;
                    $data->Meter = $pengukuranMeter;
                    $data->Vp = $pengukuranVp;
                    $data->Filter = $pengukuranFilter;
                    $data->Oven = $pengukuranOven;
                    $data->exit_impinger = $pengukuranexit_impinger;
                    $data->Probe = $pengukuranProbe;
                    $data->sebelumpengujian = $arrsebelumpengujian;
                    $data->sesudahpengujian = $arrsesudahpengujian;
                    if ($request->CO2 != '')
                        $data->CO2 = $request->CO2;
                    if ($request->CO != '')
                        $data->CO = $request->CO;
                    if ($request->NOx != '')
                        $data->NOx = $request->NOx;
                    if ($request->SO2 != '')
                        $data->SO2 = $request->SO2;
                    if ($request->Total_time != '')
                        $data->Total_time = $request->Total_time;
                    if ($request->foto_lok != '')
                        $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
                    if ($request->foto_sampl != '')
                        $data->foto_kondisi_sampel = self::convertImg($request->foto_sampl, 2, $this->user_id);
                    if ($request->foto_lain != '')
                        $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
                    if ($request->permis != '')
                        $data->permission = $request->permis;
                    $data->created_by = $this->karyawan;
                    $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    DB::table('order_detail')
                        ->where('no_sampel', strtoupper(trim($request->no_sample)))
                        ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);
                    
                    DB::commit();
                    return response()->json([
                        'message' => 'Data berhasil disimpan.'
                    ], 200);
                }
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e.getMessage(),
                    'line' => $e.getLine(),
                    'code' => $e.getCode()
                ], 401);
            }
        } else if ($request->method == 6) {
            DB::beginTransaction();
            try {
                $check = DataLapanganIsokinetikHasil::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                if ($check) {
                    return response()->json([
                        'message' => 'No sample ' . strtoupper(trim($request->no_sample)) . ' Sudah Terinput Pada Method 6.!'
                    ], 401);
                } else {
                    $data = new DataLapanganIsokinetikHasil();

                    if ($request->id_lapangan != '')
                        $data->id_lapangan = $request->id_lapangan;
                    if (strtoupper(trim($request->no_sample)) != '')
                        $data->no_sampel = strtoupper(trim($request->no_sample));
                    if ($request->impinger1 != '')
                        $data->impinger1 = $request->impinger1;
                    if ($request->impinger2 != '')
                        $data->impinger2 = $request->impinger2;
                    if ($request->impinger3 != '')
                        $data->impinger3 = $request->impinger3;
                    if ($request->impinger4 != '')
                        $data->impinger4 = $request->impinger4;
                    if ($request->totalBobot != '')
                        $data->totalBobot = $request->totalBobot;
                    if ($request->Collector != '')
                        $data->Collector = $request->Collector;
                    if ($request->v_wtr != '')
                        $data->v_wtr = $request->v_wtr;
                    if ($request->v_gas != '')
                        $data->v_gas = $request->v_gas;
                    if ($request->bws_frac != '')
                        $data->bws_frac = $request->bws_frac;
                    if ($request->bws_aktual != '')
                        $data->bws_aktual = $request->bws_aktual;
                    if ($request->ps != '')
                        $data->ps = $request->ps;
                    if ($request->avgVs != '')
                        $data->avgVs = $request->avgVs;
                    if ($request->qs != '')
                        $data->qs = $request->qs;
                    if ($request->qs_act != '')
                        $data->qs_act = $request->qs_act;
                    if ($request->recoveryacetone != '')
                        $data->recoveryacetone = $request->recoveryacetone;
                    if ($request->avg_Tm != '')
                        $data->avg_Tm = $request->avg_Tm;
                    if ($request->avgTS != '')
                        $data->avgTS = $request->avgTS;
                    if ($request->persenIso != '')
                        $data->persenIso = $request->persenIso;
                    if ($request->foto_lok != '')
                        $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
                    if ($request->foto_sampl != '')
                        $data->foto_kondisi_sampel = self::convertImg($request->foto_sampl, 2, $this->user_id);
                    if ($request->foto_lain != '')
                        $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
                    if ($request->permis != '')
                        $data->permission = $request->permis;
                    $data->created_by = $this->karyawan;
                    $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    // UPDATE ORDER DETAIL
                    DB::table('order_detail')
                        ->where('no_sampel', strtoupper(trim($request->no_sample)))
                        ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

                    DB::commit();
                    return response()->json([
                        'message' => 'Data berhasil disimpan.'
                    ], 200);
                }
            } catch (Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => $e.getMessage(),
                    'line' => $e.getLine(),
                ], 401);
            }
        }
    }

    public function indexPartikulatIsokinetik(Request $request)
    {
        try {
            $data = array();
            if ($request->method == 1) {
                $data = DataLapanganIsokinetikSurveiLapangan::with('detail')->where('created_by', $this->karyawan)->orderBy('id', 'desc');
            } else if ($request->method == 2) {
                $data = DataLapanganIsokinetikPenentuanKecepatanLinier::with('detail')->where('created_by', $this->karyawan)->orderBy('id', 'desc');
            } else if ($request->method == 3) {
                $data = DataLapanganIsokinetikBeratMolekul::with('detail')->where('created_by', $this->karyawan)->orderBy('id', 'desc');
            } else if ($request->method == 4) {
                $data = DataLapanganIsokinetikKadarAir::with('detail')->where('created_by', $this->karyawan)->orderBy('id', 'desc');
            } else if ($request->method == 5) {
                $data = DataLapanganIsokinetikPenentuanPartikulat::with('detail')->where('created_by', $this->karyawan)->orderBy('id', 'desc');
            } else if ($request->method == 6) {
                $data = DataLapanganIsokinetikHasil::with('detail')->where('created_by', $this->karyawan)->orderBy('id', 'desc');
            }

            $this->resultx = 'Show Partikulat Isokinetik Success';
            return Datatables::of($data)->make(true);
        } catch (Exception $e) {
            dd($e);
        }
    }

    public function approvePartikulatIsokinetik(Request $request)
    {
        if ($request->method == 1) {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganIsokinetikSurveiLapangan::where('id', $request->id)->first();
                $data->is_apporve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
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
        } else if ($request->method == 2) {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganIsokinetikPenentuanKecepatanLinier::where('id', $request->id)->first();
                $data->is_apporve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
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
        } else if ($request->method == 3) {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganIsokinetikBeratMolekul::where('id', $request->id)->first();
                $data->is_apporve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
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
        } else if ($request->method == 4) {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganIsokinetikKadarAir::where('id', $request->id)->first();
                $data->is_apporve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
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
        } else if ($request->method == 5) {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganIsokinetikPenentuanPartikulat::where('id', $request->id)->first();
                $data->is_apporve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
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
        } else if ($request->method == 6) {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganIsokinetikHasil::where('id', $request->id)->first();
                $data->is_apporve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
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
    }

    public function detailPartikulatIsokinetik(Request $request)
    {
        if ($request->method == 1) {
            $data = DataLapanganIsokinetikSurveiLapangan::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail partikulat isokinetik success';

            return response()->json([
                'id' => $data->id,
                'sampler' => $data->created_by,
                'no_survei' => $data->no_survei,
                'nama_titik' => $data->keterangan,
                'nama_perusahaan' => $data->nama_perusahaan,
                'sumber' => $data->sumber_emisi,
                'merk' => $data->merk,
                'bakar' => $data->bahan_bakar,
                'cuaca' => $data->cuaca,
                'kecepatan' => $data->kecepatan, // (m/s)
                'durasiOp' => $data->jam_operasi,
                'filtrasi' => $data->proses_filtrasi,
                'coor' => $data->titik_koordinat,
                'lat' => $data->latitude,
                'long' => $data->longitude,
                'waktu' => $data->waktu_survei,
                'diameter' => $data->diameter_cerobong, // (m)
                'lubang' => $data->ukuran_lubang, // (Cm)
                'jumlah_lubang' => $data->jumlah_lubang_sampling,
                'lebar' => $data->lebar_platform, // (m)
                'bentuk' => $data->bentuk_cerobong,
                'jarakUp' => $data->jarak_upstream, // (m)
                'jarakDown' => $data->jarak_downstream, // (m)
                'kategUp' => $data->kategori_upstream, // (D)
                'kategDown' => $data->kategori_downstream, // (D)
                'lintasPartikulat' => $data->lintas_partikulat, // (titik)
                'kecLinier' => $data->kecepatan_linier, // (titik)
                'foto_lok' => $data->foto_lokasi_sampel,
                'foto_kon' => $data->foto_kondisi_sampel,
                'foto_lain' => $data->foto_lain,
                'lfw' => $data->lfw,
                'lnw' => $data->lnw,
                'titikPar_s' => $data->titik_lintas_partikulat_s,
                'titikLin_s' => $data->titik_lintas_kecepatan_linier_s,
                'jarakPar_s' => $data->jarak_partikulat_s,
                'jarakLin_s' => $data->jarak_linier_s,
                'filename_denah' => $data->filename_denah,
                'status' => '200',
            ], 200);
        } else if ($request->method == 2) {
            $data = DataLapanganIsokinetikPenentuanKecepatanLinier::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail partikulat isokinetik success';

            $perusahaan = '-';
            if (!empty($data->detail)) {
                $perusahaan = $data->detail->nama_perusahaan;
            }
            return response()->json([
                'id' => $data->id,
                'no_survei' => $data->no_survei,
                'sampler' => $data->created_by,
                'no_sample' => $data->no_sampel,
                'nama' => $perusahaan,
                'diameter' => $data->diameter_cerobong, // (m)
                'suhu' => $data->suhu, // ('C)
                'kelem' => $data->kelembapan, // (%RH)
                'tekanan_u' => $data->tekanan_udara, // (mmHg)
                'kp' => $data->kp,
                'cp' => $data->cp,
                'waktu' => $data->waktu_pengukuran,
                'kecLinier' => $data->kecLinier,
                'tekPa' => $data->tekPa, // (mmH2O)
                'dataDp' => $data->dataDp,
                'dP' => $data->dP, // average dataDp
                'TM' => $data->TM, // (K)
                'Ps' => $data->Ps, // (mmHg)
                'foto_lok' => $data->foto_lokasi_sampel,
                'foto_kon' => $data->foto_kondisi_sampel,
                'foto_lain' => $data->foto_lain,
                'rerata_suhu' => $data->rerata_suhu,
                'rerata_paps' => $data->rerata_paps,
                'jaminan_mutu' => $data->jaminan_mutu,
                'status_test' => $data->status_test,
                'uji_aliran' => $data->uji_aliran,
                'status' => '200',
            ], 200);
        } else if ($request->method == 3) {
            $data = DataLapanganIsokinetikBeratMolekul::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail partikulat isokinetik success';

            $perusahaan = '-';
            if (!empty($data->detail)) {
                $perusahaan = $data->detail;
            }
            return response()->json([
                'id' => $data->id,
                'sampler' => $data->created_by,
                'no_sample' => $data->no_sampel,
                'diameter' => $data->diameter,
                'nama' => $perusahaan,
                'waktu' => $data->waktu,
                'o2' => $data->O2,
                'co' => $data->CO,
                'co2' => $data->CO2,
                'no' => $data->NO,
                'nox' => $data->NOx,
                'no2' => $data->NO2,
                'so2' => $data->SO2,
                'suhu' => $data->suhu_cerobong,
                'co2mole' => $data->CO2Mole,
                'comole' => $data->COMole,
                'o2mole' => $data->O2Mole,
                'n2mole' => $data->N2Mole,
                'md' => $data->MdMole,
                'ts' => $data->Ts,
                'foto_lok' => $data->foto_lokasi_sampel,
                'foto_kon' => $data->foto_kondisi_sampel,
                'foto_lain' => $data->foto_lain,
                'nCO2' => $data->nCO2,
                'shift' => $data->shift,
                'combustion' => $data->combustion,
            ], 200);
        } else if ($request->method == 4) {

            $data = DataLapanganIsokinetikKadarAir::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail partikulat isokinetik success';

            $perusahaan = '-';
            if (!empty($data->detail)) {
                $perusahaan = $data->detail->nama_perusahaan;
            }
            return response()->json([
                'id' => $data->id,
                'sampler' => $data->created_by,
                'no_sample' => $data->no_sampel,
                'id_lapangan' => $data->id_lapangan,
                'metode_uji' => $data->metode_uji,
                'kadar_air' => $data->kadar_air,
                'nama' =>  $perusahaan,
                'laju_aliran' => $data->laju_aliran,
                'data_impinger' => $data->data_impinger,
                'nilai_y' => $data->nilai_y,
                'pm' => $data->Pm,
                'suhu_cerobong' => $data->suhu_cerobong,
                'data_dgmterbaca' => $data->data_dgmterbaca,
                'data_kalkulasi_dgm' => $data->data_kalkulasi_dgm,
                'jaminan_mutu' => $data->jaminan_mutu,
                'data_dgm_test' => $data->data_dgm_test,
                'dgm_test' => $data->dgm_test,
                'waktu_test' => $data->waktu_test,
                'laju_alir_test' => $data->laju_alir_test,
                'tekV_test' => $data->tekV_test,
                'hasil_test' => $data->hasil_test,
                'vwc' => $data->vwc,
                'vmstd' => $data->vmstd,
                'vwsg' => $data->vwsg,
                'bws' => $data->bws,
                'ms' => $data->ms,
                'vs' => $data->vs,
                'foto_lok' => $data->foto_lokasi_sampel,
                'foto_kon' => $data->foto_kondisi_sampel,
                'foto_lain' => $data->foto_lain,
            ], 200);
        } else if ($request->method == 5) {
            try {
                $data = DataLapanganIsokinetikPenentuanPartikulat::with('detail')->where('id', $request->id)->first();
                $this->resultx = 'get Detail partikulat isokinetik success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    // 'no_order'                   => $dataLap->detail->no_order,
                    'no_sample' => $data->no_sampel,
                    'diameter' => $data->diameter,
                    'data_Y' => $data->data_Y,
                    'Delta_H' => $data->Delta_H,
                    'impinger1' => $data->impinger1,
                    'impinger2' => $data->impinger2,
                    'impinger3' => $data->impinger3,
                    'impinger4' => $data->impinger4,
                    'k_iso' => $data->k_iso,
                    'titik_lintas_partikulat' => $data->titik_lintas_partikulat,
                    'waktu' => $data->waktu,
                    // 'corp'                       => $dataLap->detail->nama,
                    'CO' => $data->CO,
                    'CO2' => $data->CO2,
                    'NOx' => $data->NOx,
                    'SO2' => $data->SO2,
                    'bobot' => $data->bobot,
                    'DGM' => $data->DGM,
                    'SelisihDGM' => $data->rataselisihdgm,
                    'dP' => $data->dP,
                    'PaPs' => $data->PaPs,
                    'dH' => $data->dH,
                    'Stack' => $data->Stack,
                    'Meter' => $data->Meter,
                    'Vp' => $data->Vp,
                    'SebelumPengujian' => $data->sebelumpengujian,
                    'SesudahPengujian' => $data->sesudahpengujian,
                    'Filter' => $data->Filter,
                    'Oven' => $data->Oven,
                    'exit_impinger' => $data->exit_impinger,
                    'Probe' => $data->Probe,
                    'Vs' => $data->Vs,
                    'data_total_vs' => $data->data_total_vs,
                    'delta_vm' => $data->delta_vm,
                    'pbar' => $data->pbar,
                    'temperatur_stack' => $data->temperatur_stack,
                    'Total_time' => $data->Total_time,
                    'foto_lok' => $data->foto_lokasi_sampel,
                    'foto_kon' => $data->foto_kondisi_sampel,
                    'foto_lain' => $data->foto_lain,
                ], 200);
            } catch (Exception $e) {
                dd($e);
            }
        } else if ($request->method == 6) {
            try {
                $data = DataLapanganIsokinetikHasil::with('detail')->where('id', $request->id)->first();
                $this->resultx = 'get Detail partikulat isokinetik success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'no_sample' => $data->no_sampel,
                    'impinger1' => $data->impinger1,
                    'impinger2' => $data->impinger2,
                    'impinger3' => $data->impinger3,
                    'impinger4' => $data->impinger4,
                    'totalBobot' => $data->totalBobot,
                    'Collector' => $data->Collector,
                    'v_wtr' => $data->v_wtr,
                    'v_gas' => $data->v_gas,
                    'bws_frac' => $data->bws_frac,
                    'bws_aktual' => $data->bws_aktual,
                    'ps' => $data->ps,
                    'avgVs' => $data->avgVs,
                    'qs' => $data->qs,
                    'qs_act' => $data->qs_act,
                    'avg_Tm' => $data->avg_Tm,
                    'avgTS' => $data->avgTS,
                    'persenIso' => $data->persenIso,
                    'recovery' => $data->recoveryacetone,
                    'foto_lok' => $data->foto_lokasi_sampel,
                    'foto_kon' => $data->foto_kondisi_sampel,
                    'foto_lain' => $data->foto_lain,
                ], 200);
            } catch (Exception $e) {
                dd($e);
            }
        }
    }

    public function deletePartikulatIsokinetik(Request $request)
    {
        try {
            if ($request->method == 1) {
                if (isset($request->id) && $request->id != null) {
                    $cek = DataLapanganIsokinetikSurveiLapangan::where('id', $request->id)->first();
                    $foto_lok = public_path() . '/dokumentasi/sampling/' . $cek->foto_lokasi_sampel;
                    $foto_kon = public_path() . '/dokumentasi/sampling/' . $cek->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $cek->foto_lain;
                    if (is_file($foto_lok)) {
                        unlink($foto_lok);
                    }
                    if (is_file($foto_kon)) {
                        unlink($foto_kon);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $cek->delete();
                    DataLapanganIsokinetikPenentuanKecepatanLinier::where('id_lapangan', $cek->id)->delete();
                    DataLapanganIsokinetikBeratMolekul::where('id_lapangan', $cek->id)->delete();
                    DataLapanganIsokinetikKadarAir::where('id_lapangan', $cek->id)->delete();
                    DataLapanganIsokinetikPenentuanPartikulat::where('id_lapangan', $cek->id)->delete();
                    DataLapanganIsokinetikHasil::where('id_lapangan', $cek->id)->delete();
                } else {
                    return response()->json([
                        'message' => 'Gagal Delete'
                    ], 401);
                }
            } else if ($request->method == 2) {
                if (isset($request->id) && $request->id != null) {
                    $cek = DataLapanganIsokinetikPenentuanKecepatanLinier::where('id', $request->id)->first();
                    $foto_lok = public_path() . '/dokumentasi/sampling/' . $cek->foto_lokasi_sampel;
                    $foto_kon = public_path() . '/dokumentasi/sampling/' . $cek->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $cek->foto_lain;
                    if (is_file($foto_lok)) {
                        unlink($foto_lok);
                    }
                    if (is_file($foto_kon)) {
                        unlink($foto_kon);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $cek->delete();
                    DataLapanganIsokinetikBeratMolekul::where('id_lapangan', $cek->id_lapangan)->delete();
                    DataLapanganIsokinetikKadarAir::where('id_lapangan', $cek->id_lapangan)->delete();
                    DataLapanganIsokinetikPenentuanPartikulat::where('id_lapangan', $cek->id_lapangan)->delete();
                    DataLapanganIsokinetikHasil::where('id_lapangan', $cek->id_lapangan)->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else {
                    return response()->json([
                        'message' => 'Gagal Delete'
                    ], 401);
                }
            } else if ($request->method == 3) {
                if (isset($request->id) && $request->id != null) {
                    $cek = DataLapanganIsokinetikBeratMolekul::where('id', $request->id)->first();
                    $foto_lok = public_path() . '/dokumentasi/sampling/' . $cek->foto_lokasi_sampel;
                    $foto_kon = public_path() . '/dokumentasi/sampling/' . $cek->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $cek->foto_lain;
                    if (is_file($foto_lok)) {
                        unlink($foto_lok);
                    }
                    if (is_file($foto_kon)) {
                        unlink($foto_kon);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $cek->delete();
                    DataLapanganIsokinetikKadarAir::where('id_lapangan', $cek->id_lapangan)->delete();
                    DataLapanganIsokinetikPenentuanPartikulat::where('id_lapangan', $cek->id_lapangan)->delete();
                    DataLapanganIsokinetikHasil::where('id_lapangan', $cek->id_lapangan)->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else {
                    return response()->json([
                        'message' => 'Gagal Delete'
                    ], 401);
                }
            } else if ($request->method == 4) {
                if (isset($request->id) && $request->id != null) {
                    $cek = DataLapanganIsokinetikKadarAir::where('id', $request->id)->first();
                    $foto_lok = public_path() . '/dokumentasi/sampling/' . $cek->foto_lokasi_sampel;
                    $foto_kon = public_path() . '/dokumentasi/sampling/' . $cek->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $cek->foto_lain;
                    if (is_file($foto_lok)) {
                        unlink($foto_lok);
                    }
                    if (is_file($foto_kon)) {
                        unlink($foto_kon);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $cek->delete();
                    DataLapanganIsokinetikPenentuanPartikulat::where('id_lapangan', $cek->id_lapangan)->delete();
                    DataLapanganIsokinetikHasil::where('id_lapangan', $cek->id_lapangan)->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else {
                    return response()->json([
                        'message' => 'Gagal Delete'
                    ], 401);
                }
            } else if ($request->method == 5) {
                if (isset($request->id) && $request->id != null) {
                    $cek = DataLapanganIsokinetikPenentuanPartikulat::where('id', $request->id)->first();
                    $foto_lok = public_path() . '/dokumentasi/sampling/' . $cek->foto_lokasi_sampel;
                    $foto_kon = public_path() . '/dokumentasi/sampling/' . $cek->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $cek->foto_lain;
                    if (is_file($foto_lok)) {
                        unlink($foto_lok);
                    }
                    if (is_file($foto_kon)) {
                        unlink($foto_kon);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $cek->delete();
                    DataLapanganIsokinetikHasil::where('id_lapangan', $cek->id_lapangan)->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else {
                    return response()->json([
                        'message' => 'Gagal Delete'
                    ], 401);
                }
            } else if ($request->method == 6) {
                if (isset($request->id) && $request->id != null) {
                    $cek = DataLapanganIsokinetikHasil::where('id', $request->id)->first();
                    $foto_lok = public_path() . '/dokumentasi/sampling/' . $cek->foto_lokasi_sampel;
                    $foto_kon = public_path() . '/dokumentasi/sampling/' . $cek->foto_kondisi_sampel;
                    $foto_lain = public_path() . '/dokumentasi/sampling/' . $cek->foto_lain;
                    if (is_file($foto_lok)) {
                        unlink($foto_lok);
                    }
                    if (is_file($foto_kon)) {
                        unlink($foto_kon);
                    }
                    if (is_file($foto_lain)) {
                        unlink($foto_lain);
                    }
                    $cek->delete();
                    return response()->json([
                        'message' => 'Data has ben Delete',
                        'cat' => 1
                    ], 201);
                } else {
                    return response()->json([
                        'message' => 'Gagal Delete'
                    ], 401);
                }
            }
        } catch (Exception $e) {
            dd($e);
        }
    }

    // PSIKOLOGI
    public function addPsikologi(Request $request)
    {
        DB::beginTransaction();
        try {
            $exist = DataLapanganPsikologi::where('no_sampel', $request->no_sample)->first();
            if ($exist) {
                return response()->json([
                    'message' => 'No sample sudah terdaftar .!'
                ], 401);
            }
            if ($request->foto_lok == '') {
                return response()->json([
                    'message' => 'Foto lokasi sampling tidak boleh kosong .!'
                ], 401);
            }
            $po = OrderDetail::where('no_sampel', $request->no_sample)->first();

            // simpan data pertanyaan dan jawaban dengan indeks yang sama pada satu objeck di index array yang sama
            // ex : [0] => ['pertanyaan' => 'pertanyaan 1', 'jawaban' => 'jawaban 1'], [1] => ['pertanyaan' => 'pertanyaan 2', 'jawaban' => 'jawaban 2'
            $pertanyaan = $request->pertanyaan;
            $jawaban = $request->jawaban;
            $dataPertanyaanJawaban = [];
            for ($i = 0; $i < count($pertanyaan); $i++) {
                $dataPertanyaanJawaban[$i]['pertanyaan'] = $pertanyaan[$i];
                $dataPertanyaanJawaban[$i]['jawaban'] = $jawaban[$i];
            }
            $pertanyaanJawabanJson = json_encode($dataPertanyaanJawaban);

            $data = new DataLapanganPsikologi();
            $data->no_sampel = $request->no_sample;
            $data->nama_pekerja = $request->nama_pekerja;
            $data->nama_perusahaan = $request->nama_perusahaan;
            $data->divisi = $request->divisi;
            $data->usia = $request->umur;
            $data->jenis_kelamin = $request->jenis_kelamin;
            $data->lama_kerja = $request->lama_bekerja;
            $data->hasil = $pertanyaanJawabanJson;
            $data->permission = $request->permis;
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            if ($request->foto_lok != '') $data->foto_lokasi_sampling = self::convertImg($request->foto_lok, 1, $this->user_id);
            if ($request->foto_lain != '') $data->foto_lainnya = self::convertImg($request->foto_lain, 3, $this->user_id);

            $data->save();

            $nama = $this->karyawan;
            $this->resultx = "Data Sampling PSIKOLOGI Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

            // if($this->pin!=null){

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $this->resultx);
            // }

            // UPDATE ORDER DETAIL
            $update = DB::table('order_detail')
                    ->where('no_sampel', strtoupper(trim($request->no_sample)))
                    ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 401);
        }
    }

    public function indexPsikologi(Request $request)
    {
        $data = DataLapanganPsikologi::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()
                ->subDays(3))
            ->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function detailPsikologi(Request $request)
    {
        try {
            $data = DataLapanganPsikologi::find($request->id);
            $data['foto_lokasi_sampling'] = $data['foto_lokasi_sampling'];
            $data['foto_lainnya'] = $data['foto_lainnya'] !== null ? $data['foto_lainnya'] : null;
            $data['hasil'] = json_decode($data['hasil'], true);
            $this->resultx = 'Detail FDL Psikologi Berhasil';

            return response()->json([
                'message' => $this->resultx,
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 401);
        }
    }

    public function deletePsikologi(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = DataLapanganPsikologi::find($request->id);
            if ($data) {
                $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampling;
                $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lainnya;
                if (is_file($foto_lok)) {
                    unlink($foto_lok);
                }
                if (is_file($foto_lain)) {
                    unlink($foto_lain);
                }
                $data->delete();
                DB::commit();
                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 1
                ], 201);
            } else {
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 401);
            }

            $no_sample = $data->no_sampel;

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            // $txt = "FDL PSIKOLOGI dengan No sampel $no_sample Telah di Hapus oleh $this->karyawan";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 401);
        }
    }

    public function approvePsikologi(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = DataLapanganPsikologi::find($request->id);
            $no_sample = $data->no_sampel;

            if ($data) {
                $data->update([
                    'is_approve' => true,
                    'approved_by' => $this->karyawan,
                    'approved_at' => Carbon::now()->format('Y-m-d H:i:s')
                ]);
                DB::commit();
                return response()->json([
                    'message' => "Data dengan No Sampel $data->no_sampel Telah di Approve oleh $this->karyawan",
                    'cat' => 1
                ], 201);
            } else {
                return response()->json([
                    'message' => "Data dengan No Sampel $data->no_sampel Gagal di Approve"
                ], 401);
            }

            // if($this->pin!=null){
            //     $nama = $this->karyawan;
            // $txt = "FDL PSIKOLOGI dengan No sampel $no_sample Telah di Hapus oleh $this->karyawan";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 401);
        }
    }

    // Survei
    public function getSurvei(Request $request)
    {
        if ($request->method == 2) {
            $data = DataLapanganIsokinetikSurveiLapangan::where('no_survei', $request->no_survei)->first();
            $check = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_survei', $request->no_survei)->first();

            if ($data) {
                if ($check) {
                    return response()->json([
                        'message' => 'No. Survei tidak boleh sama.'
                    ], 401);
                } else {
                    if ($data->kategUp !== 'Tidak dapat dilakukan sampling' || $data->kategDown !== 'Tidak dapat dilakukan sampling') {
                        return response()->json([
                            'id' => $data->id,
                            'diameter' => $data->diameter_cerobong,
                            'titik_lintas' => $data->titik_lintas_kecepatan_linier_s,
                            // pending
                            'jumlah_lubang' => $data->jumlah_lubang_sampling,
                            'jarakLin_s' => $data->jarak_linier_s,
                            // pending
                        ], 200);
                    } else {
                        return response()->json([
                            'message' => 'No. Survei tidak dapat dilakukan sampling.'
                        ], 401);
                    }
                }
            } else {
                return response()->json([
                    'message' => 'Tidak ada data berdasarkan No. Survei tersebut.'
                ], 401);
            }
        } else if ($request->method == 3) {
            $data = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

            if ($data) {
                return response()->json([
                    'id' => $data->id_lapangan,
                    'diameter' => $data->diameter_cerobong,
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Tidak ada data di Method 2 berdasarkan No. Sample tersebut.'
                ], 401);
            }
        } else if ($request->method == 4) {
            $data = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            $data2 = DataLapanganIsokinetikBeratMolekul::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift', 'L1')->first();
            $check = DataLapanganIsokinetikKadarAir::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

            if ($data2) {
                if ($check) {
                    return response()->json([
                        'message' => 'No. Sample sudah di input.'
                    ], 401);
                } else {
                    return response()->json([
                        'id' => $data2->id_lapangan,
                        'diameter' => $data2->diameter,
                        'Md' => $data2->MdMole,
                        'Ts' => $data2->Ts,
                        'Kp' => $data->kp,
                        'Cp' => $data->cp,
                        'dP' => $data->dP,
                        'Ps' => $data->Ps,
                    ], 200);
                }
            } else {
                return response()->json([
                    'message' => 'Tidak ada data di Method 3 berdasarkan No. Sample tersebut.'
                ], 401);
            }
        } else if ($request->method == 5) {
            try {
                $no_sample = strtoupper(trim($request->no_sample));
                $check = DataLapanganIsokinetikPenentuanPartikulat::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $data = DB::select("
                            SELECT 
                                data_lapangan_isokinetik_survei_lapangan.diameter_cerobong as diameter,
                                data_lapangan_isokinetik_survei_lapangan.id as id_lapangan,
                                data_lapangan_isokinetik_survei_lapangan.titik_lintas_partikulat_s as titikPar_s,
                                data_lapangan_isokinetik_survei_lapangan.jumlah_lubang_sampling as jumlah_lubang,
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.TM as tm,
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.cp as cp,
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.Ps as ps,
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.suhu as suhu,
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.dP as reratadp,
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.kp as kp,
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.tekanan_udara as pbar,
                                data_lapangan_isokinetik_berat_molekul.Ts as ts,
                                data_lapangan_isokinetik_berat_molekul.CO2 as CO2,
                                data_lapangan_isokinetik_berat_molekul.CO as CO,
                                data_lapangan_isokinetik_berat_molekul.NOx as NOx,
                                data_lapangan_isokinetik_berat_molekul.SO2 as SO2,
                                data_lapangan_isokinetik_berat_molekul.MdMole as md,
                                data_lapangan_isokinetik_kadar_air.bws as bws,
                                data_lapangan_isokinetik_kadar_air.ms as ms,
                                data_lapangan_isokinetik_kadar_air.vs as vs_m4
                            FROM 
                                data_lapangan_isokinetik_survei_lapangan
                            LEFT JOIN 
                                data_lapangan_isokinetik_penentuan_kecepatan_linier ON data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_penentuan_kecepatan_linier.id_lapangan
                            LEFT JOIN 
                                data_lapangan_isokinetik_kadar_air ON data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_kadar_air.id_lapangan
                            LEFT JOIN 
                                data_lapangan_isokinetik_berat_molekul ON data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_berat_molekul.id_lapangan
                            WHERE 
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.no_sampel = ?", [$no_sample]);
                // $data = DB::select("SELECT data_lapangan_isokinetik_survei_lapangan.diameter as diameter, data_lapangan_isokinetik_survei_lapangan.id as id_lapangan, data_lapangan_isokinetik_survei_lapangan.lintasPartikulat as lintasPartikulat, data_lapangan_isokinetik_survei_lapangan.jumlah_lubang as jumlah_lubang, data_lapangan_isokinetik_penentuan_kecepatan_linier.TM as tm, data_lapangan_isokinetik_penentuan_kecepatan_linier.cp as cp, data_lapangan_isokinetik_penentuan_kecepatan_linier.Ps as ps, data_lapangan_isokinetik_penentuan_kecepatan_linier.suhu as suhu,data_lapangan_isokinetik_penentuan_kecepatan_linier.dP as reratadp, data_lapangan_isokinetik_penentuan_kecepatan_linier.kp as kp, data_lapangan_isokinetik_penentuan_kecepatan_linier.tekanan_u as pbar, data_lapangan_isokinetik_berat_molekul.Ts as ts, data_lapangan_isokinetik_berat_molekul.CO2 as CO2,data_lapangan_isokinetik_berat_molekul.CO as CO,data_lapangan_isokinetik_berat_molekul.NOx as NOx,data_lapangan_isokinetik_berat_molekul.SO2 as SO2,data_lapangan_isokinetik_berat_molekul.MdMole as md, data_lapangan_isokinetik_kadar_air.bws as bws, data_lapangan_isokinetik_kadar_air.ms as ms  FROM `data_lapangan_isokinetik_survei_lapangan` LEFT JOIN data_lapangan_isokinetik_penentuan_kecepatan_linier on data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_penentuan_kecepatan_linier.id_lapangan LEFT JOIN data_lapangan_isokinetik_kadar_air on data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_kadar_air.id_lapangan LEFT JOIN data_lapangan_isokinetik_berat_molekul ON data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_berat_molekul.id_lapangan WHERE data_lapangan_isokinetik_penentuan_kecepatan_linier.no_sample = 'strtoupper(trim($request->no_sample))'");
                $data4 = DataLapanganIsokinetikKadarAir::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

                // dd($data);
                if ($data4) {
                    if ($check) {
                        return response()->json([
                            'message' => 'No. Sample sudah di input.'
                        ], 401);
                    } else {
                        return response()->json([
                            'data' => $data,
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'message' => 'Tidak ada data di Method 4 berdasarkan No. Sample tersebut.'
                    ], 401);
                }
            } catch (Exception $e) {
                dd($e);
            }
        } else if ($request->method == 6) {
            try {
                $check = DataLapanganIsokinetikHasil::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $data = DB::select("
                            SELECT 
                                data_lapangan_isokinetik_survei_lapangan.diameter_cerobong AS diameter, 
                                data_lapangan_isokinetik_survei_lapangan.lintas_partikulat AS lintasPartikulat, 
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.TM AS tm, 
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.cp AS cp, 
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.Ps AS ps, 
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.suhu AS suhu, 
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.dP AS reratadp, 
                                data_lapangan_isokinetik_berat_molekul.Ts AS ts, 
                                data_lapangan_isokinetik_berat_molekul.CO2 AS CO2, 
                                data_lapangan_isokinetik_berat_molekul.CO AS CO, 
                                data_lapangan_isokinetik_berat_molekul.NOx AS NOx, 
                                data_lapangan_isokinetik_berat_molekul.SO2 AS SO2, 
                                data_lapangan_isokinetik_berat_molekul.MdMole AS md,  
                                data_lapangan_isokinetik_kadar_air.bws AS bws, 
                                data_lapangan_isokinetik_kadar_air.ms AS ms, 
                                data_lapangan_isokinetik_penentuan_partikulat.pbar AS pbar, 
                                data_lapangan_isokinetik_penentuan_partikulat.impinger1 AS impinger1, 
                                data_lapangan_isokinetik_penentuan_partikulat.impinger2 AS impinger2, 
                                data_lapangan_isokinetik_penentuan_partikulat.impinger3 AS impinger3, 
                                data_lapangan_isokinetik_penentuan_partikulat.impinger4 AS impinger4, 
                                data_lapangan_isokinetik_penentuan_partikulat.data_Y AS data_Y, 
                                data_lapangan_isokinetik_penentuan_partikulat.dH AS dH, 
                                data_lapangan_isokinetik_penentuan_partikulat.DGM AS DGM,
                                data_lapangan_isokinetik_penentuan_partikulat.data_total_vs AS Vs, 
                                data_lapangan_isokinetik_penentuan_partikulat.dgmAwal AS dgmAwal,
                                data_lapangan_isokinetik_penentuan_partikulat.PaPs AS PaPs, 
                                data_lapangan_isokinetik_penentuan_partikulat.dn_req AS dn_req, 
                                data_lapangan_isokinetik_penentuan_partikulat.dn_actual AS dn_actual, 
                                data_lapangan_isokinetik_penentuan_partikulat.Meter AS Meter, 
                                data_lapangan_isokinetik_penentuan_partikulat.Stack AS Stack, 
                                data_lapangan_isokinetik_penentuan_partikulat.id_lapangan AS id_lapangan,
                                data_lapangan_isokinetik_penentuan_partikulat.Total_time AS Total_time,
                                data_lapangan_isokinetik_penentuan_partikulat.temperatur_stack AS temperatur_stack
                            FROM 
                                data_lapangan_isokinetik_survei_lapangan 
                            LEFT JOIN 
                                data_lapangan_isokinetik_penentuan_kecepatan_linier ON data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_penentuan_kecepatan_linier.id_lapangan 
                            LEFT JOIN 
                                data_lapangan_isokinetik_kadar_air ON data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_kadar_air.id_lapangan 
                            LEFT JOIN 
                                data_lapangan_isokinetik_berat_molekul ON data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_berat_molekul.id_lapangan 
                            LEFT JOIN 
                                data_lapangan_isokinetik_penentuan_partikulat ON data_lapangan_isokinetik_survei_lapangan.id = data_lapangan_isokinetik_penentuan_partikulat.id_lapangan 
                            WHERE 
                                data_lapangan_isokinetik_penentuan_kecepatan_linier.no_sampel = ?
                        ", [trim(strtoupper($request->no_sample))]);

                $data4 = DataLapanganIsokinetikPenentuanPartikulat::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

                if ($data4) {
                    if ($check) {
                        return response()->json([
                            'message' => 'No. Sample sudah di input.'
                        ], 401);
                    } else {
                        return response()->json([
                            'data' => $data,
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'message' => 'Tidak ada data di Method 5 berdasarkan No. Sample tersebut.'
                    ], 401);
                }
            } catch (Exception $e) {
                dd($e);
            }
        }
    }

    // KONVERIS GAMBAR
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

    // SELECT KATEGORI
    public function cmbCategory(Request $request)
    {
        echo "<option value=''>--Pilih Kategori--</option>";

        $data = MasterKategori::where('is_active', true)->get();


        foreach ($data as $q) {

            $id = $q->id;
            $nm = $q->nama_kategori;
            if ($id == $request->value) {
                echo "<option value='$id' selected> $nm </option>";
            } else {
                echo "<option value='$id'> $nm </option>";
            }
        }
    }

    // SELECT VOLUME
    public function SelectVolume(Request $request)
    {

        $vm = [];
        $a = 100;
        for ($i = 1; $i <= $a; $i++) {
            $nn = $i . '00';
            array_push($vm, $nn);
        }

        return response()->json([
            'data' => $vm
        ], 201);
    }

    // GET SHIFT
    public function getShift(Request $request)
    {
        if ($request->tip == 1) {
            $data = DetailLingkunganHidup::where('no_sampel', $request->no_sample)->where('shift_pengambilan', $request->shift)->first();
            $po = OrderDetail::where('no_sampel', $request->no_sample)->where('is_active', true)->first();
            \DB::statement("SET SQL_MODE=''");
            $param = DetailLingkunganHidup::where('no_sampel', $request->no_sample)->groupBy('parameter')->get();
            $parNonSes = array();
            foreach ($param as $value) {
                // pengecualian untuk Dustfall
                if (str_contains($value->parameter, 'Dustfall')) {
                    $p = DetailLingkunganHidup::where('no_sampel', $request->no_sample)
                        ->where('parameter', $value->parameter)->get();

                    $shift = 2; // Batas shift untuk Dustfall

                    if ($shift > count($p)) {
                        $parNonSes[] = $value->parameter;
                    }
                } else if ($value->kategori_pengujian != 'Sesaat') {
                    $p = DetailLingkunganHidup::where('no_sampel', $request->no_sample)
                        ->where('parameter', $value->parameter)->get();
                    $l = $value->kategori_pengujian;
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
                            $shift = 25;
                        } else if ($li[0] == '8 Jam') {
                            $shift = 8;
                        } else if ($li[0] == '6 Jam') {
                            $shift = 6;
                        }
                    } else {
                        if ($li[0] == '24 Jam') {
                            $shift = 4;
                        } else if ($li[0] == '8 Jam') {
                            $shift = 3;
                        } else if ($li[0] == '6 Jam') {
                            $shift = 6;
                        }else if ($li[0] == '3 Jam') {
                            $shift = 3;
                        }
                    }
                    if ($shift > count($p)) {
                        $parNonSes[] = $value->parameter;
                    }
                }
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

            $lh_parameter = DetailLingkunganHidup::where('no_sampel', $request->no_sample)
                ->where('shift_pengambilan', 'Sesaat')
                ->whereNotIn('parameter', ['Dustfall', 'Dustfall (S)', 'Dustfall-NS1'])   
                ->pluck('parameter')
                ->toArray();
            
            // Hapus parameter yang ada di $existing_parameters
            $filtered_param = array_values(array_diff($nilai_param2, $lh_parameter));
            // Buat output JSON yang sesuai
            $param_fin = json_encode($filtered_param);

            if ($data) {
                return response()->json([
                    'non'      => 1,
                    'keterangan'      => $data->keterangan,
                    'keterangan_2'    => $data->keterangan_2,
                    'titik_koordinat' => $data->titik_koordinat,
                    'lat'             => $data->latitude,
                    'longi'           => $data->longitude,
                    'lokasi'          => $data->lokasi,
                    'cuaca'           => $data->cuaca,
                    'waktu'           => $data->waktu_pengukuran,
                    'kecepatan'       => $data->kecepatan_angin,
                    'arah_angin'      => $data->arah_angin,
                    'jarak'           => $data->jarak_sumber_cemaran,
                    'suhu'            => $data->suhu,
                    'kelem'           => $data->kelembapan,
                    'intensitas'      => $data->intensitas,
                    'tekanan_u'       => $data->tekanan_udara,
                    'desk_bau'        => $data->deskripsi_bau,
                    'metode'          => $data->metode_pengukuran,
                    'satuan'          => $data->satuan,
                    'catatan'          => $data->catatan_kondisi_lapangan,
                    'durasi_pengambilan'          => $data->durasi_pengambilan,
                    'foto_lokasi_sample'          => $data->foto_lokasi_sampel,
                    'foto_kondisi_sample'          => $data->foto_kondisi_sampel,
                    'foto_lain'          => $data->foto_lain,
                    'param' => $param_fin
                ], 200);
                $this->resultx = 'get shift sample lingkuhan hidup success';
            } else {
                return response()->json([
                    'non'      => 2,
                    'no_sample'    => $po->no_sampel,
                    'keterangan' => $po->keterangan_1,
                    'id_ket' => explode('-', $po->kategori_3)[0],
                    'param' => $param_fin
                ], 200);
            }
        } else if ($request->tip == 2) {
            $data = DetailLingkunganKerja::where('no_sampel', $request->no_sample)->where('shift_pengambilan', $request->shift)->first();
            $po = OrderDetail::where('no_sampel', $request->no_sample)->where('is_active', true)->first();
            \DB::statement("SET SQL_MODE=''");
            $param = DetailLingkunganKerja::where('no_sampel', $request->no_sample)->groupBy('parameter')->get();
            $parNonSes = array();
            foreach ($param as $value) {
                if ($value->kategori_pengujian != 'Sesaat') {
                    $p = DetailLingkunganKerja::where('no_sampel', $request->no_sample)->where('parameter', $value->parameter)->get();
                    $l = $value->kategori_pengujian;
                    $li = explode("-", $l);
                    $shift = '';
                    if (str_contains($value->parameter, 'TSP')) {
                        if ($li[0] == '24 Jam') {
                            $shift = 25;
                        } else if ($li[0] == '8 Jam') {
                            $shift = 8;
                        } else if ($li[0] == '6 Jam') {
                            $shift = 6;
                        }
                    } else {
                        if ($li[0] == '24 Jam') {
                            $shift = 4;
                        } else if ($li[0] == '8 Jam') {
                            $shift = 3;
                        } else if ($li[0] == '6 Jam') {
                            $shift = 6;
                        }
                    }

                    if ($shift > count($p)) {
                        $parNonSes[] = $value->parameter;
                    }
                }
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

            $lk_parameter = DetailLingkunganKerja::where('no_sampel', $request->no_sample)
                ->where('shift_pengambilan', 'Sesaat')
                ->pluck('parameter')
                ->toArray();

            // Hapus parameter yang ada di $existing_parameters
            $filtered_param = array_values(array_diff($nilai_param2, $lk_parameter));

            // Buat output JSON yang sesuai
            $param_fin = json_encode($filtered_param);
            
            if ($data) {
                return response()->json([
                    'non'      => 1,
                    'keterangan'     => $data->keterangan,
                    'keterangan_2'   => $data->keterangan_2,
                    'waktu'          => $data->waktu_pengukuran,
                    'lat'            => $data->latitude,
                    'long'           => $data->longitude,
                    'lokasi'         => $data->lokasi,
                    'cuaca'          => $data->cuaca,
                    'ventilasi'      => $data->laju_ventilasi,
                    'intensitas'     => $data->intensitas,
                    'aktifitas'     => $data->aktifitas,
                    'jarak'          => $data->jarak_sumber_cemaran,
                    'suhu'           => $data->suhu,
                    'kelem'          => $data->kelembapan,
                    'tekanan_u'      => $data->tekanan_udara,
                    'desk_bau'       => $data->deskripsi_bau,
                    'metode'         => $data->metode_pengukuran,
                    'catatan'          => $data->catatan_kondisi_lapangan,
                    'durasi_pengambilan'          => $data->durasi_pengujian,
                    'kecepatan'         => $data->kecepatan_angin,
                    'titik_koordinat'         => $data->titik_koordinat,
                    'foto_lok'       => $data->foto_lokasi_sampel,
                    'foto_kon'       => $data->foto_kondisi_sampel,
                    'foto_lain'      => $data->foto_lain,
                    'param' => $param_fin
                ], 200);
                $this->resultx = 'get shift sample lingkuhan kerja success';
            } else {
                return response()->json([
                    'non'      => 2,
                    'no_sample'    => $po->no_sampel,
                    'keterangan' => $po->keterangan_1,
                    'id_ket' => explode('-', $po->kategori_3)[0],
                    'param' => $param_fin
                ], 200);
            }
        } else if ($request->tip == 3) {
            $data = DetailMicrobiologi::where('no_sampel', $request->no_sample)->where('shift_pengambilan', $request->shift)->first();
            $po = OrderDetail::where('no_sampel', $request->no_sample)->where('is_active', true)->first();
            if ($data) {
                return response()->json([
                    'non'      => 1,
                    'data'     => $data,
                    'id_ket' => explode('-', $po->kategori_3)[0],
                ], 200);
                $this->resultx = 'get shift sample lingkuhan kerja success';
            } else {
                $data = OrderDetail::where('no_sampel', $request->no_sample)->where('is_active', true)->first();
                return response()->json([
                    'non'      => 2,
                    'keterangan' => $data->keterangan_1,
                    'id_ket' => explode('-', $po->kategori_3)[0],
                ], 200);
            }
        }
    }

    public function aduan(Request $request){
        // dd($request->header('token'));
        $fileName = null;
        DB::beginTransaction();
        try {
            $data = new AduanLapangan;
            $waktu = Carbon::now()->format('Y-m-d H:i:s');
            $data->type_aduan = $request->type_aduan;

            if($request->type_aduan == 'Waktu Sampling'){
                $nama_customer = 'Customer Tidak Ditemukan.!';
                $cek_order = OrderDetail::where('no_order', strtoupper($request->no_order))->first();
                if($cek_order) $nama_customer = $cek_order->nama_perusahaan;

                $data->no_order = strtoupper($request->no_order);
                $data->tambahan = $request->waktu;
                
                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "No Order : $request->no_order \n";
                $message .= "Nama Customer : $nama_customer \n";
                $message .= "Status : $request->waktu \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";

            } else if ($request->type_aduan == 'Peralatan & Perlengkapan') {
                $data->nama_alat = $request->nama_alat;
                $data->koding = $request->koding;
                if($request->foto1 != ''){
                    $fileName = Self::saveImage($request->foto1, $this->user_id, 1);
                    $data->foto_1 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }
                if($request->foto2 != ''){$data->foto_2 = Self::saveImage($request->foto2, $this->user_id, 2);}

                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "Nama Alat : $request->nama_alat \n";
                $message .= "Koding : $request->koding \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";

            } else if ($request->type_aduan == 'Kendaraan') {
                $data->koding = $request->koding;
                if($request->foto1 != ''){
                    $fileName = Self::saveImage($request->foto1, $this->user_id, 1);
                    $data->foto_1 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }
                if($request->foto2 != ''){$data->foto_2 = Self::saveImage($request->foto2, $this->user_id, 2);}
                
                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "Kode Mobil / Plat No : $request->koding \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";

            } else if ($request->type_aduan == 'K3') {
                $nama_customer = 'Customer Tidak Ditemukan.!';
                $cek_order = OrderDetail::where('no_order', strtoupper($request->no_order))->first();
                if($cek_order) $nama_customer = $cek_order->nama_perusahaan;

                $data->no_order = strtoupper($request->no_order);
                if($request->foto1 != ''){
                    $fileName = Self::saveImage($request->foto1, $this->user_id, 1);
                    $data->foto_1 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }
                if($request->foto2 != ''){$data->foto_2 = Self::saveImage($request->foto2, $this->user_id, 2);}
                
                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "No Order : $request->no_order \n";
                $message .= "Nama Customer : $nama_customer \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";

            } else if ($request->type_aduan == 'Sales') {
                $nama_customer = 'Customer Tidak Ditemukan.!';
                $cek_order = OrderDetail::where('no_order', strtoupper($request->no_order))->first();
                if($cek_order) $nama_customer = $cek_order->nama_perusahaan;

                $data->no_order = strtoupper($request->no_order);

                if($request->foto1 != ''){
                    $fileName = Self::saveImage($request->foto1, $this->user_id, 1);
                    $data->foto_1 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }
                
                if($request->foto2 != ''){$data->foto_2 = Self::saveImage($request->foto2, $this->user_id, 2);}

                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "No Order : $request->no_order \n";
                $message .= "Nama Customer : $nama_customer \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";

            } else if ($request->type_aduan == 'Aduan Umum') {
                if($request->foto1 != ''){
                    $fileName = Self::saveImage($request->foto1, $this->user_id, 1);
                    $data->foto_1 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }

                if($request->foto2 != ''){
                    $fileName = Self::saveImage($request->foto2, $this->user_id, 2);
                    $data->foto_2 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }
                
                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";

            } else if ($request->type_aduan == 'BAS/CS') {
                $nama_customer = 'Customer Tidak Ditemukan.!';
                $cek_order = OrderDetail::where('no_order', strtoupper($request->no_order))->first();
                if($cek_order) $nama_customer = $cek_order->nama_perusahaan;

                $data->no_order = strtoupper($request->no_order);
                $fileName = null;
                if($request->foto1 != ''){
                    $fileName = Self::saveImage($request->foto1, $this->user_id, 1);
                    $data->foto_1 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }
                if($request->foto2 != ''){
                    $fileName = Self::saveImage($request->foto2, $this->user_id, 2);
                    $data->foto_2 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }

                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "No Order : $request->no_order \n";
                $message .= "Nama Customer : $nama_customer \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";
            }
            $data->keterangan = $request->keterangan;
            $data->kordinat = $request->koordinat;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->created_by = $this->karyawan;
            $data->save();

            DB::commit();

            if($request->type_aduan == 'Waktu Sampling'){
                // Helpers::sendToNew('-1002229600148', $message, null, $fileName);
                // Helpers::sendToNew('-1002197513895', $message, null, $fileName); //channel laporan waktu sampling
                $member = ['-1002229600148', '-1002197513895'];

                $telegram = new SendTelegram();
                $telegram = SendTelegram::text($message)
                ->to($member)->send();
            }

            // if($request->type_aduan == 'BAS/CS'){
            //     // Helpers::sendToNew('-1002199994008', $message, null, $fileName);
            //     Helpers::sendToNew('-1002183256259', $message, null, $fileName); //chanel bas / cs
            // }

            if($request->type_aduan == 'Aduan Umum'){
                // Helpers::sendTelegramAtasan($message, 13, null, $fileName); //Tele Bu Suci
                // Helpers::sendToNew('-1002245551834', $message, null, $fileName); //chanel aduan umum 
                $member = ['-1002245551834', '805208290'];

                $telegram = new SendTelegram();
                $telegram = SendTelegram::text($message)
                ->to($member)->send();
            }

            if($request->type_aduan == 'Peralatan & Perlengkapan'){
                // Helpers::sendTelegramAtasan($message, 13, null, $fileName); //Tele Bu Suci
                // Helpers::sendToNew('-1002249182981', $message, null, $fileName); //channel aduan peralatan & perlengkapan

                $member = ['-1002249182981', '805208290'];

                $telegram = new SendTelegram();
                $telegram = SendTelegram::text($message)
                ->to($member)->send();
                
            }

            if($request->type_aduan == 'Kendaraan'){
                // Helpers::sendTelegramAtasan($message, 13, null, $fileName); //Tele Bu Suci
                // Helpers::sendToNew('-1002184355599', $message, null, $fileName); //chanel aduan kendaraan
                $member = ['-1002184355599', '805208290'];

                $telegram = new SendTelegram();
                $telegram = SendTelegram::text($message)
                ->to($member)->send();
            }

            if($request->type_aduan == 'K3'){
                // Helpers::sendToNew('-1002167796966', $message, null, $fileName); //channel aduan k3
                // Helpers::sendTelegramAtasan($message, 13, null, $fileName); //Tele Bu Suci

                $member = ['-1002167796966', '805208290'];

                $telegram = new SendTelegram();
                $telegram = SendTelegram::text($message)
                ->to($member)->send();
            }

            // if($request->type_aduan == 'Sales'){
            //     Helpers::sendTelegramAtasan($message, 13, null, $fileName); //Tele Bu Suci
            // }

            // // Send Tele Pribadi
            // if($request->type_aduan == 'Sales' || $request->type_aduan == 'Waktu Sampling'){
            //     // Helpers::sendTelegramAtasan($message, 19, null, $fileName); //Tele Bu Faidhah
            //     Helpers::sendToNew('1463248619', $message, null, $fileName); //Tele Lani

            //     if($request->has('no_order') && $request->no_order != ''){
            //         $db = '20' . substr(strtoupper($request->no_order), 6, 2);
            //         $cek_order_header = OrderHeader::where('no_order', strtoupper($request->no_order))->first();
            //         if($cek_order_header != null){
            //             if(explode('/',$cek_order_header->no_document)[1] == 'QT') {
            //                 $cek_sales = QuotationNonKontrak::where('no_document', $cek_order_header->no_document)->first();
            //             } else {
            //                 $cek_sales = QuotationKontrakH::where('no_document', $cek_order_header->no_document)->first();
            //             }
                            
            //             if($cek_sales != null){
            //                 Helpers::sendTelegramAtasan($message, $cek_sales->sales_id, null, $fileName); //Tele Sales
            //             }
            //         }
            //     }
            // }

            if($request->type_aduan == 'BAS/CS'){
                // Helpers::sendToNew('787241230', $message, null, $fileName); //Tele Meisya
                // Helpers::sendToNew('6480425773', $message, null, $fileName); //Tele Noerita
                // Helpers::sendToNew('1184254359', $message, null, $fileName); //Tele Nisa Alkhaira
                // Helpers::sendToNew('1463248619', $message, null, $fileName); //Tele Lani
                
                if($request->has('no_order') && $request->no_order != ''){
                    $cek_order_header = OrderHeader::where('no_order', strtoupper($request->no_order))->first();
                    if($cek_order_header != null){
                        if(explode('/',$cek_order_header->no_document)[1] == 'QT') {
                            $cek_sales = QuotationNonKontrak::where('no_document', $cek_order_header->no_document)->first();
                        } else {
                            $cek_sales = QuotationKontrakH::where('no_document', $cek_order_header->no_document)->first();
                        }
                        
                        if($cek_sales != null){
                            // $atasan = GetAtasan::where('id', 508)->get();
                            $atasan = GetAtasan::where('id', $cek_sales->sales_id)->get();
                            $atasan = $atasan->pluck('pin_user')->toArray();
                            // dd($atasan);
                            // dd($atasan);
                            // Helpers::sendTelegramAtasan($message, $cek_sales->sales_id, null, $fileName); //Tele Sales
                            $telegram = new SendTelegram();
                            $telegram = SendTelegram::text($message)
                            ->to($atasan)->send();
                        }
                    }
                }
            }

            return response()->json([
                'message' => 'Data Berhasil Disimpan.!',
                'status' => '200'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage()
            ],401);
        }
    }

    public function saveImage($foto = '', $user = '', $type)
    {

        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        $safeName = DATE('YmdHis') . '_' . $type . '_' . $user . '.jpg';
        $destinationPath = public_path().'/dokumentasi/aduanSampler/';
        // dd($destinationPath);
        $success = file_put_contents($destinationPath . $safeName, $file);
        return $safeName;
    }

    public function logout(Request $request) {
        if(isset($this->user_id) && $this->user_id != '') {
            $Usertoken = UserToken::where('token', $request->token)->first();
            $Usertoken->is_logged_out = 0;
            // $Usertoken->date_logout = Carbon::now()->format('Y-m-d H:i:s');
            $Usertoken->save();


            $data = User::where('id', $this->user_id)->first();
            $data->is_active = 0;
            // $data->log = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            return response()->json([
                    'message' => 'Logout Success',
                    'status' => '200'
                ], 200);
        }
    }

    // KONDISI LAPANGAN SOUND METER
    public function getKondisiLapangan(Request $request)
    {
        if ($request->menu === 'soundMeter') {
            $sevenDaysAgo = Carbon::now()->subDays(7);

            $data = DetailSoundMeter::select('no_sampel', DB::raw('MAX(timestamp) as max_timestamp'))
                ->where('timestamp', '>=', $sevenDaysAgo)
                ->groupBy('no_sampel');

            return Datatables::of($data)->make(true);
        }

        return response()->json([
            'message' => 'Data Tidak Ditemukan'
        ], 404);
    }

    public function viewKondisiLapangan(Request $request)
    {
        if ($request->menu === 'soundMeter') {

            $data = DataLapanganKebisinganBySoundMeter::where('no_sampel', $request->no_sampel)->get();

            return response()->json([
            'message' => 'Success Get Data',
            'data' => $data
        ], 200);
        }

        return response()->json([
            'message' => 'Data Tidak Ditemukan'
        ], 404);
    }

    public function checkDevice(Request $request) {
        if (isset($request->kode) && $request->kode != null) {
            $data = DeviceIntilab::where('kode', $request->kode)->first();
            if($data){
                return response()->json([
                    'message' => 'Device Exist',
                    'success' => true
                ], 200);
            } else {
                return response()->json([
                    'message' => "Upss.. 🙊 Kode alat $request->kode yang anda masukkan belum terdaftar di sistem",
                    'success' => false
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'theres no device code u are inputing',
                'success' => false
            ], 401);
        }
    }
}