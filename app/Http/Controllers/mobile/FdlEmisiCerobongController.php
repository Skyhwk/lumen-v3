<?php

namespace App\Http\Controllers\mobile;

use App\Models\DataLapanganEmisiCerobong;

// MASTER DATA
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\ParameterFdl;
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

class FdlEmisiCerobongController extends Controller
{
    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
            ->where('kategori_2', '5-Emisi')
            ->where('is_active', 1)->first();
            
            $partikulat = json_decode(
                ParameterFdl::where('is_active', 1)
                    ->where('nama_fdl', 'partikulat_emisi')
                    ->where('kategori', '5-Emisi')
                    ->value('parameters'),
                true
            );

            $lokasi_sampling = json_decode(
                ParameterFdl::where('is_active', 1)
                    ->where('nama_fdl', 'lokasi_sampling_emisi')
                    ->where('kategori', '5-Emisi')
                    ->value('parameters'),
                true
            );

            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample tidak ditemukan..'
                ], 401);
            } else {
                // 1. Pastikan parameter sudah dalam bentuk array, kalau masih string JSON:
                $raw = is_string($data->parameter) ? json_decode($data->parameter, true) : $data->parameter;

                // 2. Ambil hanya nama parameter setelah tanda `;`
                $cleanedParams = array_map(function ($item) {
                    $parts = explode(';', $item);
                    return isset($parts[1]) ? trim($parts[1]) : trim($item);
                }, $raw);

                $emisiC = DataLapanganEmisiCerobong::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                $cek = MasterSubKategori::where('id', explode('-', $data->kategori_3)[0])->first();
                return response()->json([
                    'no_sample'         => $data->no_sampel,
                    'jenis'             => $cek->nama_sub_kategori,
                    'keterangan'        => $data->keterangan_1,
                    'id_ket'            => explode('-', $data->kategori_3)[0],
                    'id_ket2'           => explode('-', $data->kategori_2)[0],
                    'data'              => $emisiC,
                    'partikulat'        => $partikulat,
                    'lokasi_sampling'   => $lokasi_sampling,
                    'param'             => $cleanedParams
                ], 200);
                
            }
        } else {
            return response()->json([
                'message' => 'Fatal Error'
            ], 401);
        }
    }

    public function store(Request $request)
    {
            $emisi = DataLapanganEmisiCerobong::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
            if ($request->tipe == 1) {
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
                    if ($request->diameter != '') $data->diameter_cerobong                   = $request->diameter;
                    if ($request->durasiOp != '') $data->durasi_operasi                   = $request->durasiOp . ' ' . $request->satDur;
                    if ($request->filtrasi != '') $data->proses_filtrasi                   = $request->filtrasi;
                    if ($request->waktu_pengambilan != '') $data->waktu_pengambilan = $request->waktu_pengambilan;
                    if ($request->koordinat != '') $data->titik_koordinat              = $request->koordinat;
                    if ($request->latitude != '') $data->latitude                             = $request->latitude;
                    if ($request->longitude != '') $data->longitude                         = $request->longitude;
                    if ($request->suhu != '') $data->suhu                           = $request->suhu;
                    if ($request->kelem != '') $data->kelembapan                         = $request->kelem;
                    if ($request->tekU != '') $data->tekanan_udara                      = $request->tekU;
                    if ($request->kapasitas != '') $data->kapasitas                 = $request->kapasitas;
                    // if ($request->metode != '') $data->metode                       = $request->metode;
                    $data->tipe                                             = 1;
                    $data->is_rejected                                      = 0;

                    $partikulat = [];
                    
                    // Data utama (non-debu)
                    if ($request->awalp !== null) {
                        $partikulat[] = [
                            'flow_awal' => $request->awalp,
                            'flow_akhir' => $request->akhirp,
                            'durasi' => $request->durasip,
                            'volume' => $request->volumep != '' ? $request->volumep : '-',
                            'tekanan' => $request->tekanan_dryp != '' ? $request->tekanan_dryp : '-',
                        ];
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

                    // Simpan
                    if (!empty($partikulat)) {
                        $data->partikulat = json_encode($partikulat);
                    }
                    if ($request->permission != '') $data->permission_1                       = $request->permission;
                    if ($request->foto_lok != '') $data->foto_lokasi_sampel         = self::convertImg($request->foto_lok, 1, $this->user_id);
                    if ($request->foto_sampl != '') $data->foto_kondisi_sampel      = self::convertImg($request->foto_sampl, 2, $this->user_id);
                    if ($request->foto_lain != '') $data->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                    $data->created_by                                                   = $this->karyawan;
                    $data->created_at                                                  = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->where('is_active', 1)->first();

                    if($orderDetail->tanggal_terima == null){
                        $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d');
                        $orderDetail->save();
                    }

                    DB::commit();
                    return response()->json([
                        'message' => "Data Sampling EMISI CEROBONG Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan"
                    ], 200);
                }catch (Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'message' => $e->getMessage(),
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
                    
                    // Parameter WAJIB

                    if ($request->param != '') {
                        foreach ($request->param as $ke => $ve) {
                            if ($ve == 'O2') {
                                $data->O2 = $request->datPar[$ke];
                            }
                            if ($ve == 'CO') {
                                $data->CO = $request->datPar[$ke];
                            }
                            if ($ve == 'CO2') {
                                $data->CO2 = $request->datPar[$ke];
                            }
                            if ($ve == 'NO') {
                                $data->NO = $request->datPar[$ke];
                            }
                            if ($ve == 'NO2') {
                                $data->NO2 = $request->datPar[$ke];
                            }
                            if ($ve == 'SO2') {
                                $data->SO2 = $request->datPar[$ke];
                            }
                            if ($ve == 'T Flue/ T Stak') {
                                $data->T_Flue = $request->datPar[$ke];
                            }
                            if ($ve == 'NOx') {
                                $data->NOx = $request->datPar[$ke];
                            }
                            if ($ve == 'HC') {
                                $data->HC = $request->datPar[$ke];
                            }
                        }
                    }
                    
                    $velocity = [];

                    if ($request->has('velocity_dat') && is_array($request->velocity_dat)) {
                        foreach ($request->velocity_dat as $index => $val) {
                            $velocity[] = 'Data-' . ($index + 1) . ' : ' . $val;
                        }
                    }

                    // End Parameter Wajib

                    // Parameter Populasi

                    if ($request->filled('parameter_populasi') && is_array($request->parameter_populasi)) {
                        $mapParam = [
                            'O2 (P)' => 'o2_populasi',
                            'CO (P)' => 'co_populasi',
                            'CO2 (P)' => 'co2_populasi',
                            'NO (P)' => 'no_populasi',
                            'NO2 (P)' => 'no2_populasi',
                            'SO2 (P)' => 'so2_populasi',
                            'T Flue/ T Stak (P)' => 't_flue_populasi',
                            'NOx (P)' => 'nox_populasi',
                        ];
                    
                        $result = [];
                    
                        foreach ($request->parameter_populasi as $i => $param) {
                            if (isset($mapParam[$param])) {
                                $key = $mapParam[$param];
                                $dataVal = $request->data_populasi[$i] ?? null;
                    
                                if (!isset($result[$key])) {
                                    $result[$key] = [];
                                }
                    
                                $dataIndex = count($result[$key]) + 1;
                                $result[$key]["data-$dataIndex"] = $dataVal;
                            }
                        }
                    
                        // Simpan ke model
                        foreach ($result as $field => $val) {
                            $data->$field = json_encode($val);
                        }
                    }

                    $velocity_populasi = [];

                    if ($request->has('velocitypopulasi_dat') && is_array($request->velocitypopulasi_dat)) {
                        foreach ($request->velocitypopulasi_dat as $index => $val) {
                            $velocity_populasi[] = 'Data-' . ($index + 1) . ' : ' . $val;
                        }
                    }

                    $data->velocity                = count($velocity) > 0 ? json_encode($velocity) : null;
                    $data->velocity_populasi       = count($velocity_populasi) > 0 ? json_encode($velocity_populasi) : null;

                    if ($request->permission != '') $data->permission_2                       = $request->permission;
                    if ($request->waktu_selesai != '') $data->waktu_selesai           = $request->waktu_selesai;
                    if ($request->foto_struk != '') $data->foto_struk     = self::convertImg($request->foto_struk, 4, $this->user_id);
                    if ($request->foto_lain2 != '') $data->foto_lain2     = self::convertImg($request->foto_lain2, 5, $this->user_id);
                    $data->updated_by                                                   = $this->karyawan;
                    $data->updated_at                                                  = Carbon::now()->format('Y-m-d H:i:s');
                    $data->is_rejected                                      = 0;
                    $data->save();
                    
                    $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->where('is_active', 1)->first();

                    if($orderDetail->tanggal_terima == null){
                        $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d');
                        $orderDetail->save();
                    }

                    DB::commit();
                    $this->resultx = "Data Sampling EMISI CEROBONG Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan";
                    return response()->json([
                        'message' => $this->resultx
                    ], 200);
                }catch (Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'message' => $e->getMessage(),
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
                    if (count($request->nilOpas) > 0 ) $data->nilai_opasitas                  = json_encode($request->nilOpas);
                    if ($request->foto_asap != '') $data->foto_asap     = self::convertImg($request->foto_asap, 6, $this->user_id);
                    if ($request->foto_lain3 != '') $data->foto_lain3     = self::convertImg($request->foto_lain3, 7, $this->user_id);

                    $data->permission_3                     = (empty($request->permission)) ? 1 : $request->permission;
                    $data->updated_by                                                 = $this->karyawan;
                    $data->updated_at                                                = Carbon::now()->format('Y-m-d H:i:s');
                    $data->is_rejected                                      = 0;
                    $data->save();

                    $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->where('is_active', 1)->first();

                    if($orderDetail->tanggal_terima == null){
                        $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d');
                        $orderDetail->save();
                    }

                    DB::commit();
                    return response()->json([
                        'message' => "Data Sampling EMISI CEROBONG Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan"
                    ], 200);
                }catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'message' => $e->getMessage(),
                        'line'    => $e->getLine(),
                        'code'    => $e->getCode()
                    ]);
                }
            }
        
    }

    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganEmisiCerobong::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereIn('is_rejected', [0, 1])
            ->whereDate('created_at', '>=', Carbon::now()->subDays(7));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('no_sampel', 'like', "%$search%")
                ->orWhereHas('detail', function ($q2) use ($search) {
                    $q2->where('nama_perusahaan', 'like', "%$search%");
                });
            });
        }

        $data = $query->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($data);
    }

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

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

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();
            $cek2 = DataLapanganEmisiCerobong::where('no_sampel', $data->no_sampel)->get();
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
                InsertActivityFdl::by($this->user_id)->action('delete')->target("Emisi Cerobong pada nomor sampel $data->no_sampel")->save();

                $data->delete();

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

    public function deleteByType(Request $request)
    {
        if (!isset($request->no_sampel) || $request->no_sampel === null) {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }

        $data = DataLapanganEmisiCerobong::where('no_sampel', $request->no_sampel)->first();

        if (!$data) {
            return response()->json([
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        if ($request->tipe == 0) {
            // Hapus informasi lokasi sampling dan partikulat
            $data->fill([
                'keterangan' => null,
                'keterangan_2' => null,
                'sumber_emisi' => null,
                'merk' => null,
                'bahan_bakar' => null,
                'cuaca' => null,
                'kecepatan_angin' => null,
                'arah_pengamat' => null,
                'diameter_cerobong' => null,
                'durasi_operasi' => null,
                'proses_filtrasi' => null,
                'waktu_pengambilan' => null,
                'titik_koordinat' => null,
                'latitude' => null,
                'longitude' => null,
                'suhu' => null,
                'kelembapan' => null,
                'tekanan_udara' => null,
                'kapasitas' => null,
                'HCI' => null,
                'H2S' => null,
                'NH3' => null,
                'CI2' => null,
                'HF' => null,
                'permission_1' => 0,
                'foto_lokasi_sampel' => null,
                'foto_lain' => null,
                'partikulat' => null,
                'tipe_delete' => 1,
                'delete_by' => $this->karyawan,
                'delete_at' => Carbon::now(),
            ]);
            $data->save();
        } elseif ($request->tipe == 1) {
            // Hapus informasi gas sampling
            $data->fill([
                'metode' => null,
                'O2' => null,
                'CO' => null,
                'CO2' => null,
                'NO' => null,
                'NO2' => null,
                'SO2' => null,
                'T_Flue' => null,
                'velocity' => null,
                't_flue_populasi' => null,
                'velocity_populasi' => null,
                'o2_populasi' => null,
                'co_populasi' => null,
                'co2_populasi' => null,
                'no_populasi' => null,
                'no2_populasi' => null,
                'so2_populasi' => null,
                'permission_2' => 0,
                'waktu_selesai' => null,
                'foto_struk' => null,
                'foto_lain2' => null,
                'tipe_delete' => 2,
                'delete_by' => $this->karyawan,
                'delete_at' => Carbon::now(),
            ]);
            $data->save();
        } elseif ($request->tipe == 2) {
            // Hapus informasi opasitas
            $data->fill([
                'titik_pengamatan' => null,
                'tinggi_tanah' => null,
                'tinggi_relatif' => null,
                'status_uap' => null,
                'tekanan_udara_opasitas' => null,
                'suhu_bola' => null,
                'cuaca' => null,
                'kelembapan_opasitas' => null,
                'suhu_ambien' => null,
                'arah_utara' => null,
                'info_tambahan' => null,
                'status_konstan' => null,
                'jarak_pengamat' => null,
                'arah_pengamat_opasitas' => null,
                'deskripsi_emisi' => null,
                'warna_emisi' => null,
                'titik_penentuan' => null,
                'deskripsi_latar' => null,
                'warna_latar' => null,
                'kecepatan_angin' => null,
                'arah_pengamat' => null,
                'waktu_opasitas' => null,
                'nilai_opasitas' => null,
                'foto_asap' => null,
                'foto_lain3' => null,
                'permission_3' => 0,
                'tipe_delete' => 3,
                'delete_by' => $this->karyawan,
                'delete_at' => Carbon::now(),
            ]);
            $data->save();
        }

        // Jika data benar-benar kosong, hapus dari database
        if (
            $data->titik_koordinat === null &&
            $data->T_Flue === null &&
            $data->status_konstan === null
        ) {
            $data->delete();
        }

        return response()->json([
            'message' => 'Data Berhasil dihapus',
            'cat' => 4
        ], 201);
    }


    public function convertImg($foto = '', $type = '', $user = '')
    {
        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        $safeName = DATE('YmdHis') . '_' . $user . $type . '.jpeg';
        $destinationPath = public_path() . '/dokumentasi/sampling/';
        $success = file_put_contents($destinationPath . $safeName, $file);
        return $safeName;
    }
}