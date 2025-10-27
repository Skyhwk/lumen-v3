<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;
use App\Models\LhpsAirHeader;
use App\Models\QrDocument;
use App\Models\LhpsAirDetail;
use App\Models\LhpsAirCustom;
use App\Models\OrderDetail;
use App\Models\MetodeSampling;
use App\Models\MasterBakumutu;
use App\Models\MasterRegulasi;
use App\Models\Colorimetri;
use App\Models\Gravimetri;
use App\Models\Titrimetri;
use App\Models\Subkontrak;
use App\Models\Parameter;
use App\Models\GenerateLink;
use App\Models\MasterKaryawan;
use App\Models\LhpsAirHeaderHistory;
use App\Models\LhpsAirDetailHistory;
use App\Models\DataLapanganAir;

use App\Jobs\JobPrintLhp;

use App\Services\TemplateLhps;
use App\Services\SendEmail;
use App\Services\LhpTemplate;
use App\Services\GenerateQrDocumentLhp;
use App\Jobs\RenderLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DraftAirController extends Controller
{
    public function index(Request $request)
    {
        $data = OrderDetail::with('lhps_air', 'orderHeader', 'dataLapanganAir', 'sampleDiantar', "TrackingSatu")
            ->where('is_approve', false)
            ->where('is_active', true)
            ->where('kategori_2', '1-Air')
            ->where('status', 2)
            ->orderBy('tanggal_terima', 'desc');



        return Datatables::of($data)->make(true);
    }

    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::with('bakumutu')
            ->where('id', $request->id)
            ->get();
        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Data berhasil diambil',
        ], 200);
    }
    public function getAllRegulasi(Request $request)
    {
        $data = MasterRegulasi::where('is_active', 1)
            ->get();
        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Data berhasil diambil',
        ], 200);
    }

    public function handleSubmitDraft(Request $request)
    {
        DB::beginTransaction();
        try {

            $header = LhpsAirHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            // Kode Lama
            // if ($header == null) {
            //     $header = new LhpsAirHeader();
            // }

            if ($header == null) {
                $header = new LhpsAirHeader();
            } else {
                $history = $header->replicate();
                $history->setTable((new LhpsAirHeaderHistory())->getTable());
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->save();
            }

            $parameter_uji = \explode(', ', $request->parameter);
            $keterangan = [];
            if ($request->keterangan != null) {
                foreach ($request->keterangan as $key => $value) {
                    if ($value != '')
                        array_push($keterangan, $value);
                }
            }


            $table_header = [];
            foreach ($request->name_header_bakumutu as $key => $value) {
                if ($key == 4)
                    break;
                if ($value != '')
                    array_push($table_header, $value);
            }

            $regulasi_custom = $request->regulasi_custom ? array_map(function ($item) {
                $parts = explode('-', $item, 3);
                return [
                    'page' => (int) $parts[0],
                    'id' => (int) $parts[1],
                    'regulasi' => $parts[2],
                ];
            }, $request->regulasi_custom) : [];
            try {
                $header->no_order = ($request->no_order != '') ? $request->no_order : NULL;
                $header->no_sampel = ($request->no_sampel != '') ? $request->no_sampel : NULL;
                $header->no_lhp = ($request->no_lhp != '') ? $request->no_lhp : NULL;
                $header->no_quotation = ($request->no_penawaran != '') ? $request->no_penawaran : NULL;
                $header->parameter_uji = json_encode($parameter_uji);
                $header->nama_pelanggan = ($request->nama_perusahaan != '') ? $request->nama_perusahaan : NULL;
                $header->alamat_sampling = ($request->alamat_sampling != '') ? $request->alamat_sampling : NULL;
                $header->sub_kategori = ($request->jenis_sampel != '') ? $request->jenis_sampel : NULL;
                $header->deskripsi_titik = ($request->penamaan_titik != '') ? $request->penamaan_titik : NULL;
                $header->methode_sampling = ($request->metode_sampling != '') ? json_encode($request->metode_sampling) : NULL;
                $header->titik_koordinat = ($request->titik_koordinat != '') ? $request->titik_koordinat : NULL;
                $header->tanggal_sampling = ($request->tanggal_terima != '') ? $request->tanggal_terima : NULL;
                $header->periode_analisa = ($request->periode_analisa != '') ? $request->periode_analisa : NULL;
                $header->nama_karyawan = 'Kharina Waty';
                $header->jabatan_karyawan = 'Technical Control Manager';
                $header->regulasi = ($request->regulasi != null) ? json_encode($request->regulasi) : NULL;

                $header->regulasi_custom = $regulasi_custom ? json_encode($regulasi_custom) : NULL;
                $header->keterangan = ($keterangan != null) ? json_encode($keterangan) : NULL;
                $header->header_table = ($table_header != null) ? json_encode($table_header) : [];
                $header->suhu_air = ($request->suhu_air != null) ? $request->suhu_air : NULL;
                $header->suhu_udara = ($request->suhu_udara != null) ? $request->suhu_udara : NULL;
                $header->ph = ($request->ph != null) ? $request->ph : NULL;
                $header->dhl = ($request->dhl != null) ? $request->dhl : NULL;
                $header->do = ($request->do != null) ? $request->do : NULL;
                $header->bau = ($request->bau != null) ? $request->bau : NULL;
                $header->warna = ($request->warna != null) ? $request->warna : NULL;
                $header->tanggal_lhp = ($request->tanggal_lhp != null) ? $request->tanggal_lhp : NULL;
                $header->created_by = $this->karyawan;
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->save();
            } catch (\Exception $e) {
                throw new \Exception("Error in header assignment: " . $e->getMessage());
            }

            // Kode Lama
            // $detail = LhpsAirDetail::where('id_header', $header->id)->first();
            // if ($detail != null) {
            //     $detail = LhpsAirDetail::where('id_header', $header->id)->delete();
            // }

            if ($header->id) {
                $oldDetails = LhpsAirDetail::where('id_header', $header->id)->get();
                foreach ($oldDetails as $detail) {
                    $detailHistory = $detail->replicate();
                    $detailHistory->setTable((new LhpsAirDetailHistory())->getTable());
                    $detailHistory->created_by = $this->karyawan;
                    $detailHistory->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $detailHistory->save();
                }
                LhpsAirDetail::where('id_header', $header->id)->delete();
            }


            $param = $request->nama_parameter;
            $hasil_uji = $request->hasil_uji;

            if (!in_array("pH", $param) && !empty($request->ph)) {
                $param = array_merge($param, ["pH" => 'pH']);
                $hasil_uji = array_merge($request->hasil_uji ?? [], ["pH" => $request->ph]);
            } else if (in_array("pH", $param) == true) {
                $hasil_uji = array_merge($request->hasil_uji, ["pH" => isset($hasil_uji["pH"]) ? $hasil_uji["pH"] : '']);
            }

            if (in_array("Suhu / Temperatur", $param) == false && $request->suhu_air != null && $request->suhu_air != '') {
                $param = array_merge($param, ["Suhu" => 'Suhu / Temperatur']);
                $hasil_uji = array_merge($hasil_uji, ["Suhu" => $request->suhu_air]);
            } else if (in_array("Suhu / Temperatur", $param) == true) {
                $hasil_uji = array_merge($hasil_uji, ["Suhu" => isset($hasil_uji["Suhu"]) ? $hasil_uji["Suhu"] : '']);
            }

            try {
                foreach ($param as $key => $val) {
                    $baku_mutu = [];
                    if (isset($request->baku_mutu[$key]) && is_array($request->baku_mutu[$key])) {
                        foreach ($request->baku_mutu[$key] as $i => $o) {
                            if ($i == count($table_header))
                                break;
                            array_push($baku_mutu, $o);
                        }
                    } else {
                        $baku_mutu = [];
                    }

                    LhpsAirDetail::create([
                        'id_header' => $header->id,
                        'akr' => isset($request->akr[$key]) ? (is_array($request->akr[$key]) ? json_encode($request->akr[$key]) : $request->akr[$key]) : '',
                        'parameter_lab' => \str_replace(["'"], '', $key),
                        'parameter' => $val,
                        'hasil_uji' => isset($hasil_uji[$key]) ? (is_array($hasil_uji[$key]) ? json_encode($hasil_uji[$key]) : $hasil_uji[$key]) : '',
                        'attr' => isset($request->attr[$key]) ? (is_array($request->attr[$key]) ? json_encode($request->attr[$key]) : $request->attr[$key]) : '',
                        'satuan' => isset($request->satuan[$key]) ? (is_array($request->satuan[$key]) ? json_encode($request->satuan[$key]) : $request->satuan[$key]) : '',
                        'methode' => isset($request->methode[$key]) ? (is_array($request->methode[$key]) ? json_encode($request->methode[$key]) : $request->methode[$key]) : '',
                        'baku_mutu' => json_encode($baku_mutu)
                    ]);
                }
            } catch (\Exception $e) {
                throw new \Exception("Error in  detail assignment: " . $e->getMessage());
            }
            $custom = LhpsAirCustom::where('id_header', $header->id)->get();
            if ($custom != null) {
                $custom = LhpsAirCustom::where('id_header', $header->id)->delete();
            }

            if (isset($request->custom_parameter)) {
                try {
                    $structuredData = [];
                    foreach ($request->custom_hasil_uji as $page => $params) {
                        foreach ($params as $param => $hasil_uji) {

                            $structuredData[$page][$param] = [
                                'custom_hasil_uji' => $hasil_uji,
                                'custom_akr' => $request->custom_akr[$page][$param] ?? '',
                                'custom_attr' => $request->custom_attr[$page][$param] ?? '',
                                'custom_satuan' => $request->custom_satuan[$page][$param] ?? '',
                                'custom_methode' => $request->custom_methode[$page][$param] ?? '',
                                'custom_baku_mutu' => $request->custom_baku_mutu[$page][$param] ?? [],
                            ];
                        }
                    }

                    foreach ($structuredData as $page => $params) {
                        foreach ($params as $param => $data) {
                            $custom = new LhpsAirCustom();
                            $custom->id_header = $header->id;
                            $custom->page = $page;
                            $custom->parameter_lab = str_replace(["'"], '', htmlspecialchars_decode($param, ENT_QUOTES));
                            $custom->akr = $data['custom_akr'];
                            $custom->parameter = str_replace(["'"], '', htmlspecialchars_decode($param, ENT_QUOTES));
                            $custom->hasil_uji = $data['custom_hasil_uji'];
                            $custom->attr = $data['custom_attr'];
                            $custom->satuan = $data['custom_satuan'];
                            $custom->methode = $data['custom_methode'];
                            $custom->baku_mutu = json_encode($data['custom_baku_mutu']);
                            $custom->save();
                        }
                    }
                } catch (\Exception $e) {
                    dd($e);
                    throw new \Exception("Error in  custom assignment: " . $e->getMessage());
                }
            }

            $header = LhpsAirHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            $detail = LhpsAirDetail::where('id_header', $header->id)->get();
            $custom = LhpsAirCustom::where('id_header', $header->id)->get();

            if ($header != null) {
                $file_qr = new GenerateQrDocumentLhp();
                $file_qr_path = $file_qr->insert('LHP_AIR', $header, $this->karyawan);
                if ($file_qr_path) {
                    $header->file_qr = $file_qr_path;
                    $header->save();
                }

                $groupedByPage = [];
                if (!empty($custom)) {
                    foreach ($custom as $item) {
                        $page = $item['page'];
                        if (!isset($groupedByPage[$page])) {
                            $groupedByPage[$page] = [];
                        }
                        $groupedByPage[$page][] = $item;
                    }
                }

                $fileName = LhpTemplate::setDataDetail($detail)
                    ->setDataHeader($header)
                    ->setDataCustom($groupedByPage)
                    ->whereView('DraftAir')
                    ->render();
                // $job = new RenderLhp($header, $detail, 'downloadWSDraft', $groupedByPage);
                // $this->dispatch($job);

                // $job = new RenderLhp($header, $detail, 'downloadLHP', $groupedByPage);
                // $this->dispatch($job);

                // $job = new RenderLhp($header, $detail, 'downloadLHPFinal', $groupedByPage);
                // $this->dispatch($job);

                // $fileName = 'LHP-' . str_replace("/", "-", $header->no_lhp) . '.pdf';
                // $data = LhpsAirHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                // dd($fileName);
                $header->file_lhp = $fileName;
                $header->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Data draft lhp air no sampel ' . $request->no_sampel . ' berhasil disimpan',
                'status' => true
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            dd($th);
        }
    }

    // public function handleMetodeSampling(Request $request)
    // {
    //     $data = MetodeSampling::where('kategori', '1-AIR')->where('sub_kategori', 'like', '%' . $request->sub_kategori . '%')->get();
    //     return response()->json([
    //         'status' => true,
    //         'data' => $data
    //     ], 200);
    // }
    public function handleMetodeSampling(Request $request)
    {
        try {
            $subKategori = explode('-', $request->kategori_3);
            $data = MetodeSampling::where('kategori', '1-AIR')
                ->where('sub_kategori', strtoupper($subKategori[1]))->get();
            if ($data->isNotEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Available data retrieved successfully',
                    'data' => $data
                ], 200);
            } else {
                return response()->json([
                    'status' => true,
                    'message' => 'Belom ada method',
                    'data' => []
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function handleDatadetail(Request $request)
    {
        try {
            $data1 = array();
            $method_regulasi = array();
            $other_regulasi1 = array();
            $other_regulasi = array();
            $data = Gravimetri::with('ws_value', 'master_parameter')->where('no_sampel', $request->no_sampel)->where('is_approved', 1)->where('is_active', true)->where('lhps', 1)->get();
            $i = 0;

            
            if ($data->isNotEmpty()) {

                foreach ($data as $key => $val) {
                    $data1[$i]['id'] = $val->id;
                    $data1[$i]['name'] = $val->parameter;
                    $data1[$i]['no_sampel'] = $val->no_sampel;
                    $data1[$i]['akr'] = $val->master_parameter->status == "AKREDITASI" ? "" : "ẍ";
                    $data1[$i]['keterangan'] = $val->master_parameter->nama_regulasi;
                    $data1[$i]['satuan'] = $val->master_parameter->satuan;
                    $data1[$i]['hasil'] = $val->ws_value->hasil;
                    $data1[$i]['hasil_koreksi'] = $val->ws_value->faktor_koreksi;
                    $data1[$i]['methode'] = $val->master_parameter->method; //
                    $data1[$i]['baku_mutu'] = \explode('#', $val->master_parameter->nilai_minimum);
                    $data1[$i]['status'] = $val->master_parameter->status;

                    $bakumutu = MasterBakumutu::where('id_regulasi', $request->regulasi)
                        ->where('parameter', $val->parameter)
                        ->first();

                    if ($bakumutu != null && $bakumutu->method != '') {
                        $data1[$i]['satuan'] = $bakumutu->satuan;
                        $data1[$i]['methode'] = $bakumutu->method;
                        $data1[$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                        array_push($method_regulasi, $bakumutu->method);
                    }

                    if ($request->other_regulasi) {
                        foreach ($request->other_regulasi as $id_regulasi) {
                            $other_regulasi1[$id_regulasi]['id'] = $val->id;
                            $other_regulasi1[$id_regulasi]['name'] = $val->parameter;
                            $other_regulasi1[$id_regulasi]['no_sampel'] = $val->no_sampel;
                            $other_regulasi1[$id_regulasi]['akr'] = $val->master_parameter->status == "AKREDITASI" ? "" : "ẍ";
                            $other_regulasi1[$id_regulasi]['keterangan'] = $val->master_parameter->nama_regulasi;
                            $other_regulasi1[$id_regulasi]['satuan'] = $val->master_parameter->satuan;
                            $other_regulasi1[$id_regulasi]['hasil'] = $val->ws_value->hasil;
                            $other_regulasi1[$id_regulasi]['hasil_koreksi'] = $val->ws_value->faktor_koreksi;
                            $other_regulasi1[$id_regulasi]['methode'] = $val->master_parameter->method; //
                            $other_regulasi1[$id_regulasi]['baku_mutu'] = \explode('#', $val->master_parameter->nilai_minimum);
                            $other_regulasi1[$id_regulasi]['status'] = $val->master_parameter->status;

                            $bakumutu = MasterBakumutu::where('id_regulasi', $id_regulasi)
                                ->where('parameter', $val->parameter)
                                ->first();

                            if ($bakumutu != null && $bakumutu->method != '') {
                                $other_regulasi1[$id_regulasi][$i]['satuan'] = $bakumutu->satuan;
                                $other_regulasi1[$id_regulasi][$i]['methode'] = $bakumutu->method;
                                $other_regulasi1[$id_regulasi][$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                            }
                        }
                    }

                    $i++;
                }
                $hasil[] = $data1;
            }

            $data2 = array();
            $other_regulasi2 = array();
            $data = Colorimetri::with('ws_value', 'master_parameter')->where('no_sampel', $request->no_sampel)->where('is_approved', 1)->where('is_active', true)->where('lhps', 1)->get();
            $i = 0;
            if ($data->isNotEmpty()) {
                foreach ($data as $key => $val) {

                    $data2[$i]['id'] = $val->id;
                    $data2[$i]['name'] = $val->parameter;
                    $data2[$i]['no_sampel'] = $val->no_sampel;
                    $data2[$i]['akr'] = $val->master_parameter->status == "AKREDITASI" ? "" : "ẍ";
                    $data2[$i]['keterangan'] = $val->master_parameter->nama_regulasi;
                    $data2[$i]['satuan'] = $val->master_parameter->satuan;
                    $data2[$i]['hasil'] = $val->ws_value->hasil; //
                    $data2[$i]['hasil_koreksi'] = $val->ws_value->faktor_koreksi;
                    $data2[$i]['methode'] = $val->master_parameter->method; //
                    $data2[$i]['baku_mutu'] = \explode('#', $val->master_parameter->nilai_minimum);
                    $data2[$i]['status'] = $val->master_parameter->status;

                    $bakumutu = MasterBakumutu::where('id_regulasi', $request->regulasi)
                        ->where('parameter', $val->parameter)
                        ->first();

                    if ($bakumutu != null && $bakumutu->method != '') {
                        $data2[$i]['satuan'] = $bakumutu->satuan;
                        $data2[$i]['methode'] = $bakumutu->method;
                        // $data2[$i]['baku_mutu'] = json_decode($bakumutu->baku_mutu);
                        $data2[$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                        array_push($method_regulasi, $bakumutu->method);
                    }

                    if ($request->other_regulasi) {
                        foreach ($request->other_regulasi as $id_regulasi) {
                            $other_regulasi2[$id_regulasi]['id'] = $val->id;
                            $other_regulasi2[$id_regulasi]['name'] = $val->parameter;
                            $other_regulasi2[$id_regulasi]['no_sampel'] = $val->no_sampel;
                            $other_regulasi2[$id_regulasi]['akr'] = $val->master_parameter->status == "AKREDITASI" ? "" : "ẍ";
                            $other_regulasi2[$id_regulasi]['keterangan'] = $val->master_parameter->nama_regulasi;
                            $other_regulasi2[$id_regulasi]['satuan'] = $val->master_parameter->satuan;
                            $other_regulasi2[$id_regulasi]['hasil'] = $val->ws_value->hasil;
                            $other_regulasi2[$id_regulasi]['hasil_koreksi'] = $val->ws_value->faktor_koreksi;
                            $other_regulasi2[$id_regulasi]['methode'] = $val->master_parameter->method; //
                            $other_regulasi2[$id_regulasi]['baku_mutu'] = \explode('#', $val->master_parameter->nilai_minimum);
                            $other_regulasi2[$id_regulasi]['status'] = $val->master_parameter->status;

                            $bakumutu = MasterBakumutu::where('id_regulasi', $id_regulasi)
                                ->where('parameter', $val->parameter)
                                ->first();

                            if ($bakumutu != null && $bakumutu->method != '') {
                                $other_regulasi2[$id_regulasi][$i]['satuan'] = $bakumutu->satuan;
                                $other_regulasi2[$id_regulasi][$i]['methode'] = $bakumutu->method;
                                $other_regulasi2[$id_regulasi][$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                            }
                        }
                    }

                    $i++;
                }
                $hasil[] = $data2;
            }

            $data3 = array();
            $other_regulasi3 = array();
            $data = Titrimetri::with('ws_value', 'master_parameter')->where('no_sampel', $request->no_sampel)->where('is_approved', 1)->where('is_active', true)->where('lhps', 1)->get();
            $i = 0;
            if ($data->isNotEmpty()) {
                foreach ($data as $key => $val) {
                    $data3[$i]['id'] = $val->id;
                    $data3[$i]['name'] = $val->parameter;
                    $data3[$i]['no_sampel'] = $val->no_sampel;
                    $data3[$i]['akr'] = $val->master_parameter->status == "AKREDITASI" ? "" : "ẍ";
                    $data3[$i]['keterangan'] = $val->master_parameter->nama_regulasi;
                    $data3[$i]['satuan'] = $val->master_parameter->satuan;
                    $data3[$i]['hasil'] = $val->ws_value->hasil; //
                    $data3[$i]['hasil_koreksi'] = $val->ws_value->faktor_koreksi;
                    $data3[$i]['methode'] = $val->master_parameter->method; //
                    $data3[$i]['baku_mutu'] = \explode('#', $val->master_parameter->nilai_minimum);
                    $data3[$i]['status'] = $val->master_parameter->status;

                    $bakumutu = MasterBakumutu::where('id_regulasi', $request->regulasi)
                        ->where('parameter', $val->parameter)
                        ->first();

                    if ($bakumutu != null && $bakumutu->method != '') {
                        $data3[$i]['satuan'] = $bakumutu->satuan;
                        $data3[$i]['methode'] = $bakumutu->method;
                        // $data3[$i]['baku_mutu'] = json_decode($bakumutu->baku_mutu);
                        $data3[$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                        array_push($method_regulasi, $bakumutu->method);
                    }

                    if ($request->other_regulasi) {
                        foreach ($request->other_regulasi as $id_regulasi) {
                            $other_regulasi3[$id_regulasi]['id'] = $val->id;
                            $other_regulasi3[$id_regulasi]['name'] = $val->parameter;
                            $other_regulasi3[$id_regulasi]['no_sampel'] = $val->no_sampel;
                            $other_regulasi3[$id_regulasi]['akr'] = $val->master_parameter->status == "AKREDITASI" ? "" : "ẍ";
                            $other_regulasi3[$id_regulasi]['keterangan'] = $val->master_parameter->nama_regulasi;
                            $other_regulasi3[$id_regulasi]['satuan'] = $val->master_parameter->satuan;
                            $other_regulasi3[$id_regulasi]['hasil'] = $val->ws_value->hasil;
                            $other_regulasi3[$id_regulasi]['hasil_koreksi'] = $val->ws_value->faktor_koreksi;
                            $other_regulasi3[$id_regulasi]['methode'] = $val->master_parameter->method; //
                            $other_regulasi3[$id_regulasi]['baku_mutu'] = \explode('#', $val->master_parameter->nilai_minimum);
                            $other_regulasi3[$id_regulasi]['status'] = $val->master_parameter->status;

                            $bakumutu = MasterBakumutu::where('id_regulasi', $id_regulasi)
                                ->where('parameter', $val->parameter)
                                ->first();

                            if ($bakumutu != null && $bakumutu->method != '') {
                                $other_regulasi3[$id_regulasi][$i]['satuan'] = $bakumutu->satuan;
                                $other_regulasi3[$id_regulasi][$i]['methode'] = $bakumutu->method;
                                $other_regulasi3[$id_regulasi][$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                            }
                        }
                    }

                    $i++;
                }
                $hasil[] = $data3;
            }

            $subkontrak = array();
            $other_regulasi4 = array();
            $subkon = Subkontrak::with('ws_value', 'master_parameter')->where('no_sampel', $request->no_sampel)->where('is_approve', 1)->where('is_active', true)->where('lhps', 1)->get();
            // dd($subkon);
            $i = 0;
            if ($subkon != null) {
                foreach ($subkon as $key => $val) {
                    $subkontrak[$i]['id'] = $val->id;
                    $subkontrak[$i]['name'] = $val->parameter;
                    $subkontrak[$i]['no_sampel'] = $val->no_sampel;
                    $subkontrak[$i]['akr'] = $val->master_parameter->status == "AKREDITASI" ? "" : "ẍ";
                    $subkontrak[$i]['keterangan'] = $val->master_parameter->nama_regulasi;
                    $subkontrak[$i]['satuan'] = $val->master_parameter->satuan;
                    $subkontrak[$i]['hasil'] = $val->ws_value->hasil ?? null; //
                    $subkontrak[$i]['hasil_koreksi'] = $val->ws_value->faktor_koreksi ?? null;
                    $subkontrak[$i]['methode'] = $val->master_parameter->method; //
                    $subkontrak[$i]['baku_mutu'] = \explode('#', $val->master_parameter->nilai_minimum);
                    $subkontrak[$i]['status'] = $val->master_parameter->status;

                    $bakumutu = MasterBakumutu::where('id_regulasi', $request->regulasi)
                        ->where('parameter', $val->parameter)
                        ->first();

                    if ($bakumutu != null && $bakumutu->method != '') {
                        $subkontrak[$i]['satuan'] = $bakumutu->satuan;
                        $subkontrak[$i]['methode'] = $bakumutu->method;
                        // $subkontrak[$i]['baku_mutu'] = json_decode($bakumutu->baku_mutu);
                        $subkontrak[$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                        array_push($method_regulasi, $bakumutu->method);
                    }

                    if ($request->other_regulasi) {
                        foreach ($request->other_regulasi as $id_regulasi) {
                            $other_regulasi4[$id_regulasi]['id'] = $val->id;
                            $other_regulasi4[$id_regulasi]['name'] = $val->parameter;
                            $other_regulasi4[$id_regulasi]['no_sampel'] = $val->no_sampel;
                            $other_regulasi4[$id_regulasi]['akr'] = $val->master_parameter->status == "AKREDITASI" ? "" : "ẍ";
                            $other_regulasi4[$id_regulasi]['keterangan'] = $val->master_parameter->nama_regulasi;
                            $other_regulasi4[$id_regulasi]['satuan'] = $val->master_parameter->satuan;
                            $other_regulasi4[$id_regulasi]['hasil'] = $val->ws_value->hasil;
                            $other_regulasi4[$id_regulasi]['hasil_koreksi'] = $val->ws_value->faktor_koreksi;
                            $other_regulasi4[$id_regulasi]['methode'] = $val->master_parameter->method; //
                            $other_regulasi4[$id_regulasi]['baku_mutu'] = \explode('#', $val->master_parameter->nilai_minimum);
                            $other_regulasi4[$id_regulasi]['status'] = $val->master_parameter->status;

                            $bakumutu = MasterBakumutu::where('id_regulasi', $id_regulasi)
                                ->where('parameter', $val->parameter)
                                ->first();

                            if ($bakumutu != null && $bakumutu->method != '') {
                                $other_regulasi4[$id_regulasi][$i]['satuan'] = $bakumutu->satuan;
                                $other_regulasi4[$id_regulasi][$i]['methode'] = $bakumutu->method;
                                $other_regulasi4[$id_regulasi][$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                            }
                        }
                    }

                    $i++;
                }
                $hasil[] = $subkontrak;
            }

            $data_all = array();
            $a = 0;
            foreach ($hasil as $key => $value) {
                foreach ($value as $row => $col) {
                    $data_all[$a] = $col;
                    $a++;
                }
            }

            $data_all = collect($data_all)->sortBy('name')->values()->toArray();

            $other_data_all = array();

            $lapanganAir = DataLapanganAir::with('detail')->where('no_sampel', $request->no_sampel)->first();

            if ($lapanganAir) {
                if (!empty($lapanganAir->ph)) {
                    $bakumutu = MasterBakumutu::where('parameter', 'pH')
                        ->where('id_regulasi', $request->regulasi)
                        ->first();
                    $data_all[] = [
                        'name' => 'pH',
                        'no_sampel' => $request->no_sampel,
                        'akr' => '',
                        'satuan' => $bakumutu->satuan ?? '-',
                        'methode' => 'SM APHA 24th Ed., 4500-H⁺ B, 2023',
                        'baku_mutu' => $bakumutu->baku_mutu ?? '-',
                        'hasil' => $lapanganAir->ph,
                        'status' => 'AKREDITASI',
                        'hasil_koreksi' => '',
                        'keterangan' => "pH",
                    ];

                    array_push($method_regulasi, 'SM APHA 24th Ed., 4500-H⁺ B, 2023');
                }
                if (!empty($lapanganAir->suhu_air)) {
                    $bakumutu = MasterBakumutu::where('parameter', 'Suhu')
                        ->where('id_regulasi', $request->regulasi)
                        ->first();
                    $data_all[] = [
                        'name' => 'Suhu',
                        'no_sampel' => $request->no_sampel,
                        'akr' => '',
                        'satuan' => $bakumutu->satuan ?? '°C',
                        'methode' => 'SNI 06-6989.23-2005',
                        'baku_mutu' => $bakumutu->baku_mutu ?? 'Suhu Udara ± 3',
                        'hasil' => $lapanganAir->suhu_air,
                        'status' => 'AKREDITASI',
                        'hasil_koreksi' => '',
                        'keterangan' => "Suhu / Temperatur",
                    ];

                    array_push($method_regulasi, 'SNI 06-6989.23-2005');
                }
            }

            $method_regulasi = array_values(array_unique($method_regulasi));
            $method = Parameter::where('is_active', true)->where('id_kategori', 1)->whereNotNull('method')->select('method')->groupBy('method')->get()->toArray();
            $result_method = array_unique(array_values(array_merge($method_regulasi, array_column($method, 'method'))));

            $keterangan = [
                '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                'ẍ Parameter belum terakreditasi.'
            ];

            return response()->json([
                'status' => true,
                'data' => $data_all,
                'next_page' => [],
                'spesifikasi_method' => $result_method,
                'keterangan' => $keterangan
            ], 201);
        } catch (\Throwable $e) {
            dd($e);
        }
    }

    public function handleGenerateLink(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsAirHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();

            if ($header != null) {
                $key = $header->no_sampel . str_replace('.', '', microtime(true));
                $gen = MD5($key);
                $gen_tahun = self::encrypt(DATE('Y-m-d'));
                $token = self::encrypt($gen . '|' . $gen_tahun);

                $insertData = [
                    'token' => $token,
                    'key' => $gen,
                    'id_quotation' => $header->id,
                    'quotation_status' => "draft_air",
                    'type' => 'draft',
                    'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                    'fileName_pdf' => $header->file_lhp,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ];

                $insert = GenerateLink::insertGetId($insertData);

                $header->is_generated = true;
                $header->generated_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->generated_by = $this->karyawan;
                $header->id_token = $insert;
                $header->expired = Carbon::now()->addYear()->format('Y-m-d');
                $header->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Generate link success!',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function sendEmail(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id != '' || isset($request->id)) {
                $data = LhpsAirHeader::where('id', $request->id)->update([
                    'is_emailed' => true,
                    'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'emailed_by' => $this->karyawan
                ]);
            }

            $email = SendEmail::where('to', $request->to)
                ->where('subject', $request->subject)
                ->where('body', $request->content)
                ->where('cc', $request->cc)
                ->where('bcc', $request->bcc)
                ->where('attachments', $request->attachments)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();

            if ($email) {
                DB::commit();
                return response()->json([
                    'message' => 'Email berhasil dikirim'
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Email gagal dikirim'
                ], 400);
            }
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function getUser(Request $request)
    {
        $users = MasterKaryawan::with(['department', 'jabatan'])->where('id', $request->id ?: $this->user_id)->first();

        return response()->json($users);
    }

    public function getLink(Request $request)
    {
        try {
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'draft_air', 'type' => 'draft'])->first();

            if (!$link) {
                return response()->json(['message' => 'Link not found'], 404);
            }
            return response()->json(['link' => env('PORTALV3_LINK') . $link->token], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function handleApprove(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsAirHeader::where('no_sampel', $request->no_sampel)
                ->where('is_active', true)->firstOrFail();
            // $detail = LhpsAirDetail::where('id_header', $header->id)->get();
            // $custom = LhpsAirCustom::where('id_header', $header->id)->get();
            $qr = QrDocument::where('id_document', $header->id)
                ->where('type_document', 'LHP_AIR')
                ->where('is_active', 1)
                ->where('file', $header->file_qr)
                ->orderBy('id', 'desc')
                ->first();
            $data_order = OrderDetail::where('no_sampel', $request->no_sampel)
                ->where('id', $request->id)
                ->where('is_active', true)
                ->firstOrFail();

            if ($header != null) {
                $data_order->is_approve = 1;
                $data_order->status = 3;
                $data_order->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data_order->approved_by = $this->karyawan;
                $data_order->save();

                $header->is_approve = 1;
                $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->approved_by = $this->karyawan;
                $header->nama_karyawan = $this->karyawan;
                $header->jabatan_karyawan = $request->attributes->get('user')->karyawan->jabatan;
                $header->save();

                HistoryAppReject::insert([
                    'no_lhp' => $data_order->cfr,
                    'no_sampel' => $data_order->no_sampel,
                    'kategori_2' => $data_order->kategori_2,
                    'kategori_3' => $data_order->kategori_3,
                    'menu' => 'Draft Air',
                    'status' => 'approve',
                    'approved_at' => Carbon::now(),
                    'approved_by' => $this->karyawan
                ]);


                if ($qr != null) {
                    $dataQr = json_decode($qr->data);
                    $dataQr->Tanggal_Pengesahan = Carbon::now()->locale('id')->isoFormat('YYYY MMMM DD');
                    $dataQr->Disahkan_Oleh = $this->karyawan;
                    $dataQr->Jabatan = $request->attributes->get('user')->karyawan->jabatan;
                    $qr->data = json_encode($dataQr);
                    $qr->save();
                }
                $job = new JobPrintLhp($request->no_sampel);
                try {
                    $this->dispatch($job);
                    if (!$job) {
                        return response()->json(['message' => 'Failed to dispatch printing job', 'status' => '401'], 200);
                    }
                    return response()->json(['message' => 'Printing LHP job has been dispatched successfully', 'status' => '201'], 200);
                } catch (\Exception $e) {
                    return response()->json(['message' => 'Failed to dispatch printing job: ' . $e->getMessage(), 'status' => '401'], 200);
                }
                DB::commit();
                return response()->json([
                    'message' => 'Approve no sampel ' . $request->no_sampel . ' berhasil!',

                ], 200);
            }
        } catch (Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = OrderDetail::where('id', $request->id)->first();
            // if ($data) {

            // Kode Lama
            // $lhps = LhpsAirHeader::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();
            // dd($lhps);
            // if ($lhps) {
            //     $lhps->is_active = false;
            //     $lhps->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            //     $lhps->deleted_by = $this->karyawan;
            //     $lhps->save();
            // }


            // $data->status = 1;
            // $data->save();

            $lhps = LhpsAirHeader::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();

            if ($lhps) {
                $lhpsHistory = $lhps->replicate();
                $lhpsHistory->setTable((new LhpsAirHeaderHistory())->getTable());
                $lhpsHistory->created_at = $lhps->created_at;
                $lhpsHistory->updated_at = $lhps->updated_at;
                $lhpsHistory->deleted_at = Carbon::now();
                $lhpsHistory->deleted_by = $this->karyawan;
                $lhpsHistory->save();

                $oldDetails = LhpsAirDetail::where('id_header', $lhps->id)->get();
                if ($oldDetails->isNotEmpty()) {
                    foreach ($oldDetails as $detail) {
                        $detailHistory = $detail->replicate();
                        $detailHistory->setTable((new LhpsAirDetailHistory())->getTable());
                        $detailHistory->created_by = $this->karyawan;
                        $detailHistory->created_at = Carbon::now();
                        $detailHistory->save();
                    }
                    LhpsAirDetail::where('id_header', $lhps->id)->delete();
                }

                $lhps->delete();
            }

            $data->status = 1;
            $data->save();

            DB::commit();
            return response()->json([
                'status'  => 'success',
                'message' => "Data draft no sample {$data->no_sampel} berhasil direject",
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage()
            ]);
        }
    }

    /* public function handleDownload(Request $request)
    {
        try {
            $header = LhpsAirHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            if ($header != null && $header->file_lhp == null) {
                $detail = LhpsAirDetail::where('id_header', $header->id)->get();
                $custom = LhpsAirCustom::where('id_header', $header->id)->get();

                if ($header->file_qr == null) {
                    $header->file_qr = 'LHP-' . str_ireplace("/", "_", $header->no_lhp);
                    $header->save();
                    GenerateQrDocumentLhp::insert('LHP', $header, $this->karyawan);
                }

                $groupedByPage = [];
                if (!empty($custom)) {
                    foreach ($custom as $item) {
                        $page = $item['page'];
                        if (!isset($groupedByPage[$page])) {
                            $groupedByPage[$page] = [];
                        }
                        $groupedByPage[$page][] = $item;
                    }
                }

                $job = new RenderLhp($header, $detail, 'downloadWSDraft', $groupedByPage);
                $this->dispatch($job);

                $fileName = 'LHP-' . str_replace("/", "-", $header->no_lhp) . '.pdf';
                $data = LhpsAirHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                $data->file_lhp = $fileName;
                $data->save();

            } else if ($header != null && $header->file_lhp != null) {
                $fileName = $header->file_lhp;
            }

            return response()->json([
                'file_name' => env('APP_URL') . '/public/dokumen/LHPS/' . $fileName,
                'message' => 'Download file ' . $request->no_sampel . ' berhasil!'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error download file ' . $th->getMessage(),
            ], 401);
        }
    }*/

    public function getTechnicalControl(Request $request)
    {
        try {
            $data = MasterKaryawan::where('id_department', 17)->select('jabatan', 'nama_lengkap')->get();
            return response()->json([
                'status' => true,
                'data' => $data
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine()
            ], 500);
        }
    }

    public function setSignature(Request $request)
    {
        try {
            $header = LhpsAirHeader::where('id', $request->id)->first();

            if ($header != null) {
                $header->nama_karyawan = $this->karyawan;
                $header->jabatan_karyawan = $request->attributes->get('user')->karyawan->jabatan;
                $header->save();

                $detail = LhpsAirDetail::where('id_header', $header->id)->get();
                $custom = LhpsAirCustom::where('id_header', $header->id)->get();

                if ($header->file_qr == null) {
                    $header->file_qr = 'LHP-' . str_ireplace("/", "_", $header->no_lhp);
                    $header->save();

                    // GenerateQrDocumentLhp::insert('LHP_AIR', $header, $this->karyawan);
                } else {
                    // GenerateQrDocumentLhp::update('LHP_AIR', $header);
                }

                $groupedByPage = [];
                if (!empty($custom)) {
                    foreach ($custom as $item) {
                        $page = $item['page'];
                        if (!isset($groupedByPage[$page])) {
                            $groupedByPage[$page] = [];
                        }
                        $groupedByPage[$page][] = $item;
                    }
                }

                $job = new RenderLhp($header, $detail, 'downloadWSDraft', $groupedByPage);
                $this->dispatch($job);

                $job = new RenderLhp($header, $detail, 'downloadLHP', $groupedByPage);
                $this->dispatch($job);

                return response()->json([
                    'message' => 'Signature berhasil diubah'
                ], 200);
            }

            return response()->json([
                'message' => 'Data LHPS tidak ditemukan'
            ], 404);
        } catch (Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ]);
        }
    }

    public function getBakuMutu(Request $request)
    {
        try {
            $data = MasterBakuMutu::where('id_regulasi', $request->regulasi)->get();
            return response()->json([
                'status' => true,
                'data' => $data
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine()
            ], 500);
        }
    }

    public function encrypt($data)
    {
        $ENCRYPTION_KEY = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey = base64_decode($ENCRYPTION_KEY);
        $InitializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length($ENCRYPTION_ALGORITHM));
        $EncryptedText = openssl_encrypt($data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $return = base64_encode($EncryptedText . '::' . $InitializationVector);
        return $return;
    }

    public function decrypt($data = null)
    {
        $ENCRYPTION_KEY = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey = base64_decode($ENCRYPTION_KEY);
        list($Encrypted_Data, $InitializationVector) = array_pad(explode('::', base64_decode($data), 2), 2, null);
        $data = openssl_decrypt($Encrypted_Data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $extand = explode("|", $data);
        return $extand;
    }
}
