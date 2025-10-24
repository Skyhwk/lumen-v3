<?php

namespace App\Http\Controllers\api;

use App\Models\MasterKaryawan;
use App\Models\LhpsEmisiHeader;
use App\Models\LhpsEmisiDetail;

use App\Models\LhpsEmisiCHeader;
use App\Models\LhpsEmisiCDetail;

use App\Models\OrderDetail;
use App\Models\MetodeSampling;
use App\Models\MasterBakumutu;
use App\Models\DataLapanganEmisiCerobong;
use App\Models\DataLapanganEmisiKendaraan;
use App\Models\EmisiCerobongHeader;
use App\Models\MasterRegulasi;
use App\Models\Parameter;
use App\Models\GenerateLink;
use App\Models\QrDocument;
use App\Services\TemplateLhps;
use App\Services\GenerateQrDocumentLhp;
use App\Jobs\RenderLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

use App\Services\SendEmail;

class DraftPadatanController extends Controller
{
    public function index(Request $request)
    {
        $data1 = OrderDetail::select('cfr', 'no_order', 'nama_perusahaan', 'no_quotation', 'kategori_3', 'kategori_2', 'tanggal_sampling', 'tanggal_terima', DB::raw('group_concat(no_sampel) as no_sampel'))
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('status', 0)
            ->where('kategori_2', '6-Padatan')
            // ->whereNotIn('kategori_3', ['34-Emisi Sumber Tidak Bergerak'])
            ->groupBy('cfr', 'no_order', 'nama_perusahaan', 'no_quotation', 'kategori_3', 'kategori_2', 'tanggal_sampling', 'tanggal_terima');

        // if ($request->kategori == 'ESTB') {
        //     $data1 = OrderDetail::with('orderHeader', 'dataLapanganEmisiKendaraan', 'lhps_emisi_c')
        //         ->where('is_approve', 0)
        //         ->where('is_active', true)
        //         ->where('status', 2)
        //         ->where('kategori_2', '5-Emisi')
        //         ->where('kategori_3', '34-Emisi Sumber Tidak Bergerak');
        // }
        // dd($data1->get());
        return Datatables::of($data1)->make(true);
    }

    public function handleSubmitDraft(Request $request)
    {
        DB::beginTransaction();
        if ($request->category2 == 32 || $request->category2 == 31) {
            try {
                $header = LhpsEmisiHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();
                if (!$header) {
                    $header = new LhpsEmisiHeader();
                    $header->created_by = $this->karyawan;
                    $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
                } else {
                    $header->updated_by = $this->karyawan;
                    $header->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                }
                $parameter_uji = explode(', ', $request->parameter);
                $keterangan = [];
                if ($request->keterangan) {
                    foreach ($request->keterangan as $key => $value) {
                        if ($value != '')
                            array_push($keterangan, $value);
                    }
                }

                if ($request->tanggal_terima == null) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Tanggal Terima Harus Diisi!'
                    ], 400);
                }

                try {
                    $header->id_kategori_2 = $request->category ?: NULL;
                    $header->id_kategori_3 = $request->category2 ?: NULL;
                    $header->kategori = $request->kategori ?: NULL;
                    $header->no_order = $request->no_order ?: NULL;
                    $header->no_lhp = $request->no_lhp ?: NULL;
                    $header->no_quotation = $request->no_penawaran ?: NULL;
                    $header->parameter_uji = json_encode($parameter_uji) ?: NULL;
                    $header->nama_pelanggan = $request->nama_perusahaan ?: NULL;
                    $header->alamat_sampling = $request->alamat_sampling ?: NULL;
                    $header->sub_kategori = $request->sub_kategori ?: NULL;
                    $header->type_sampling = $request->kategori_1 ?: NULL;
                    $header->metode_sampling = isset($request->metode_sampling) ? json_encode($request->metode_sampling) : NULL;
                    $header->tgl_lhp = $request->tanggal_terima;
                    $header->tanggal_sampling = $request->tanggal_tugas ?: NULL;
                    $header->periode_analisa = $request->periode_analisa ?: NULL;
                    $header->konsultan = $request->konsultan != '' ? $request->konsultan : NULL;
                    $header->nama_pic = $request->nama_pic ?: NULL;
                    $header->jabatan_pic = $request->jabatan_pic ?: NULL;
                    $header->no_pic = $request->no_pic ?: NULL;
                    $header->email_pic = $request->email_pic ?: NULL;
                    $header->nama_karyawan = 'Abidah Walfathiyyah';
                    $header->jabatan_karyawan = 'Technical Control Supervisor';
                    // $header->nama_karyawan = 'Kharina Waty';
                    // $header->jabatan_karyawan = 'Technical Control Manager';
                    $header->regulasi = isset($request->regulasi) ? json_encode($request->regulasi) : NULL;
                    $header->save();

                    $detail = LhpsEmisiDetail::where('id_header', $header->id)->first();
                    if ($detail != null) {
                        $detail = LhpsEmisiDetail::where('id_header', $header->id)->delete();
                    }
                    $idDetail = [];
                    if ($request->category2 == 31) { // Bensin
                        foreach ($request->no_sampel_detail as $key => $val) {
                            $detail = LhpsEmisiDetail::insertGetId([
                                'id_header' => $header->id,
                                'no_sampel' => $request->no_sampel_detail[$key],
                                'nama_kendaraan' => $request->nama_kendaraan[$key],
                                'bobot_kendaraan' => $request->bobot_kendaraan[$key],
                                'tahun_kendaraan' => $request->tahun_kendaraan[$key],
                                'hasil_uji' => json_encode(array(
                                    "HC" => $request->hasil_uji_hc[$key],
                                    "CO" => $request->hasil_uji_co[$key]
                                )),
                                'baku_mutu' => json_encode(array(
                                    "HC" => $request->baku_mutu_hc[$key],
                                    "CO" => $request->baku_mutu_co[$key]
                                ))
                            ]);
                            array_push($idDetail, $detail);
                        }
                    } else if ($request->category2 == 32) { // Solar
                        foreach ($request->no_sampel_detail as $key => $val) {
                            $detail = LhpsEmisiDetail::insertGetId([
                                'id_header' => $header->id,
                                'no_sampel' => $request->no_sampel_detail[$key],
                                'nama_kendaraan' => $request->nama_kendaraan[$key],
                                'bobot_kendaraan' => $request->bobot_kendaraan[$key],
                                'tahun_kendaraan' => $request->tahun_kendaraan[$key],
                                'hasil_uji' => json_encode(array(
                                    "OP" => $request->hasil_uji[$key]
                                )),
                                'baku_mutu' => json_encode(array(
                                    "OP" => $request->baku_mutu[$key]
                                ))
                            ]);
                            array_push($idDetail, $detail);
                        }
                    }

                    if ($header != null) {
                        $file_qr = new GenerateQrDocumentLhp();

                        $file_qr = $file_qr->insert('LHP_EMISI', $header, $this->karyawan);
                        if ($file_qr) {
                            $header->file_qr = $file_qr;
                            $header->save();
                        }

                        $detail = LhpsEmisiDetail::whereIn('id', $idDetail)->get();

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

                        $job = new RenderLhp($header, $detail, 'downloadLHPFinal', $groupedByPage);
                        $this->dispatch($job);

                        $fileName = 'LHP-' . str_replace("/", "-", $header->no_lhp) . '.pdf';

                        $data = LhpsEmisiHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();
                        $data->file_lhp = $fileName;
                        $data->save();
                    }

                } catch (\Exception $e) {
                    throw new \Exception("Error in header or detail assignment: " . $e->getMessage() . ' on line ' . $e->getLine() . ' in file ' . $e->getFile());
                }

                DB::commit();
                return response()->json([
                    'message' => 'Data draft LHP air no sampel ' . $request->no_sampel . ' berhasil disimpan',
                    'status' => true
                ], 201);
            } catch (\Exception $th) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'status' => false,
                    'line' => $th->getLine(),
                    'file' => $th->getFile()
                ], 500);
            }
        } else if ($request->category2 == 34) {
            try {
                $header = LhpsEmisiCHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();
                if (!$header) {
                    $header = new LhpsEmisiCHeader();
                }

                $detail = LhpsEmisiCDetail::where('id_header', $header->id)->first();
                if (!$detail) {
                    $detail = new LhpsEmisiCDetail();
                } else {
                    LhpsEmisiCDetail::where('id_header', $header->id)->delete();
                    $detail = new LhpsEmisiCDetail();
                }

                $parameter_uji = explode(', ', $request->parameter);
                $keterangan = [];
                if ($request->keterangan) {
                    foreach ($request->keterangan as $key => $value) {
                        if ($value != '')
                            array_push($keterangan, $value);
                    }
                }

                try {
                    $header->id_kategori_2 = $request->category2 ?: NULL;
                    $header->id_kategori_3 = $request->category ?: NULL;
                    $header->kategori = $request->kategori ?: NULL;
                    $header->no_order = $request->no_order ?: NULL;
                    $header->no_lhp = $request->no_lhp ?: NULL;
                    $header->no_quotation = $request->no_penawaran ?: NULL;
                    $header->no_sampel = $request->no_sampel ?: NULL;
                    $header->parameter_uji = json_encode($parameter_uji);
                    $header->nama_pelanggan = $request->nama_perusahaan ?: NULL;
                    $header->alamat_sampling = $request->alamat_sampling ?: NULL;
                    $header->sub_kategori = $request->sub_kategori ?: NULL;
                    // $header->metode_sampling    = $request->metode_sampling ? json_encode($request->metode_sampling) : NULL;
                    $header->konsultan = $request->konsultan ?: NULL;
                    $header->nama_pic = $request->nama_pic ?: NULL;
                    $header->jabatan_pic = $request->jabatan_pic ?: NULL;
                    $header->no_pic = $request->no_pic ?: NULL;
                    $header->email_pic = $request->email_pic ?: NULL;
                    $header->type_sampling = $request->kategori_1 ?: NULL;
                    $header->tanggal_sampling = $request->tanggal_sampling ?: NULL;
                    $header->tanggal_terima = $request->tanggal_terima ?: NULL;
                    $header->tanggal_tugas = $request->tanggal_tugas ?: NULL;
                    $header->periode_analisa = $request->periode_analisa ?: NULL;
                    $header->nama_karyawan = 'Abidah Walfathiyyah';
                    $header->jabatan_karyawan = 'Technical Control Supervisor';
                    // $header->nama_karyawan = 'Kharina Waty';
                    // $header->jabatan_karyawan = 'Technical Control Manager';
                    $header->regulasi = $request->regulasi ? json_encode($request->regulasi) : NULL;
                    $header->created_by = $this->karyawan;
                    $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $header->save();

                    // Mengisi data detail
                    $detail->id_header = $header->id;
                    $detail->akr = $request->akr ?: NULL;
                    $detail->parameter_lab = $request->parameter_lab ?: NULL;
                    $detail->parameter = $request->parameter ?: NULL;
                    $detail->terukur = $request->terukur ?: NULL;
                    $detail->terkoreksi = $request->terkoreksi ?: NULL;
                    $detail->attr = $request->attr ?: NULL;
                    $detail->spesifikasi_metode = $request->spesifikasi_metode ?: NULL;
                    $detail->satuan = $request->satuan ?: NULL;

                    $detail->baku_mutu = $request->baku_mutu
                        ? json_encode(['OP' => is_array($request->baku_mutu) ? implode(', ', $request->baku_mutu) : $request->baku_mutu])
                        : NULL;

                    $detail->save();
                    if ($header != null) {

                        $file_qr = new GenerateQrDocumentLhp();
                        $file_qr = $file_qr->insert('LHP_EMISI_C', $header, $this->karyawan);
                        if ($file_qr) {
                            $header->file_qr = $file_qr;
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

                        $job = new RenderLhp($header, $detail, 'downloadWSDraft', $groupedByPage);
                        $this->dispatch($job);

                        $job = new RenderLhp($header, $detail, 'downloadLHP', $groupedByPage);
                        $this->dispatch($job);

                        $job = new RenderLhp($header, $detail, 'downloadLHPFinal', $groupedByPage);
                        $this->dispatch($job);

                        $fileName = 'LHP-' . str_replace("/", "-", $header->no_lhp) . '.pdf';

                        $data = LhpsEmisiCHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                        $data->file_lhp = $fileName;
                        $data->save();

                    }
                } catch (\Exception $e) {
                    throw new \Exception("Error in header or detail assignment: " . $e->getMessage());
                }

                DB::commit();
                return response()->json([
                    'message' => 'Data draft LHP air no sampel ' . $request->no_sampel . ' berhasil disimpan',
                    'status' => true
                ], 201);
            } catch (\Exception $th) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'line' => $th->getLine(),
                    'status' => false
                ], 500);
            }
        }
    }

    public function handleMetodeSampling(Request $request)
    {
        // dd(,$request->sub_ketegori);

        try {
            $data = MetodeSampling::where('kategori', '5-EMISI')->where('sub_kategori', 'like', '%' . explode('-', $request->sub_ketegori)[1] . '%')->get();
            return response()->json([
                'status' => true,
                'data' => $data
            ], 200);
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
            $cfr = OrderDetail::with([
                'subCategory' => function ($query) {
                    $query->select('nama_sub_kategori');
                },
                'category' => function ($query) {
                    $query->select('nama_kategori');
                }
            ])
                ->where('cfr', $request->cfr)
                ->first();

            if ($request->category2 == 32 || $request->category2 == 31) {
                $cek_all = OrderDetail::with('orderHeader', 'lhps_emisi')->where('cfr', $cfr->cfr)->where('is_active', true)->get();

                $dataTable = [];
                foreach ($cek_all as $key => $values) {
                    $value = DataLapanganEmisiKendaraan::with('emisiOrder')->where('no_sampel', $values->no_sampel)->first();

                    $cek_bakumutu = MasterBakumutu::where('id_regulasi', $value->emisiOrder->id_regulasi)
                        ->where('is_active', true)
                        ->get();

                    $hc = '-';
                    $co = '-';
                    $op = '-';
                    foreach ($cek_bakumutu as $x => $xx) {
                        if ($xx->parameter == 'HC' || $xx->parameter == 'HC (Bensin)') {
                            $hc = $xx->baku_mutu;
                        } else if ($xx->parameter == 'CO' || $xx->parameter == 'CO (Bensin)') {
                            $co = $xx->baku_mutu;
                        } else if ($xx->parameter == 'Opasitas' || $xx->parameter == 'Opasitas (Solar)') {
                            $op = $xx->baku_mutu;
                        }
                    }

                    array_push($dataTable, (object) [
                        'no_sampel' => $value->no_sampel,
                        'nama_kendaraan' => $value->emisiOrder->kendaraan->merk_kendaraan,
                        'bobot' => $value->emisiOrder->kendaraan->bobot_kendaraan,
                        'tahun' => $value->emisiOrder->kendaraan->tahun_pembuatan,
                        'hasil_co' => $value->co,
                        'hasil_hc' => $value->hc,
                        'hasil_op' => $value->opasitas,
                        'baku_co' => $co,
                        'baku_hc' => $hc,
                        'baku_op' => $op,
                        'regulasi' => $value->peraturan,
                    ]);
                }
                return response()->json([
                    // 'data' => $cfr,
                    'dataAll' => $cek_all[0],
                    // 'datalapangan' => $datalapangan,
                    'data' => $dataTable,
                    'keterangan' => [
                        '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                        '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                        'ẍ Parameter belum terakreditasi.'
                    ],
                    'message' => 'Show Worksheet Success'
                ], 200);
            } else if ($request->category2 == 34) {
                $cek_all = OrderDetail::with('orderHeader', 'lhps_emisi_c')->where('cfr', $cfr->cfr)->where('is_active', true)->get();

                $datalapangan = DataLapanganEmisiCerobong::where('is_approve', true)
                    ->where('no_sampel', $request->no_sampel)
                    ->first();

                $regulasi = explode("-", json_decode($cfr->regulasi)[0]);
                $datreg = MasterRegulasi::where('peraturan', $regulasi[1])->first();
                $datas = array();
                $datas = EmisiCerobongHeader::with([
                    'ws_value' => function ($query) {
                        $query->select('C', 'C1', 'C2');
                    }
                ])
                    ->where('no_sampel', $cfr->no_sampel)
                    ->get();

                $i = 0;
                $detailTable = array();
                if ($datas != null) {
                    foreach ($datas as $key => $val) {
                        $detailTable[$i]['param'] = $val->param;
                        $detailTable[$i]['C'] = $val->C;
                        $detailTable[$i]['C1'] = $val->C1;
                        $detailTable[$i]['C2'] = $val->C2;
                        $detailTable[$i]['satuan'] = $val->satuan;
                        $detailTable[$i]['method'] = $val->method;
                        $detailTable[$i]['baku_mutu'] = $val->baku_mutu;
                        $detailTable[$i]['status'] = $val->status;

                        if ($datreg != null) {
                            $bakumutu = MasterBakumutu::where('id_regulasi', $datreg->id)
                                ->where('parameter', $val->param)
                                ->first();

                            if ($bakumutu != null && $bakumutu->method != '') {
                                $detailTable[$i]['satuan'] = $bakumutu->satuan;
                                $detailTable[$i]['methode'] = $bakumutu->method;
                                $detailTable[$i]['baku_mutu'] = json_decode($bakumutu->baku_mutu);
                            }
                        }
                        $i++;
                    }
                }

                $method = Parameter::where('is_active', true)
                    ->where('id_kategori', 5)
                    ->whereNotNull('method')->select('method')
                    ->groupBy('method')
                    ->get();

                return response()->json([
                    // 'data' => $cfr,
                    'dataAll' => $cek_all,
                    // 'datalapangan' => $datalapangan,
                    'data' => $detailTable,
                    'message' => 'Show Worksheet Success',
                    'keterangan' => [
                        '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                        '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                        'ẍ Parameter belum terakreditasi.'
                    ],
                    'method' => $method,
                ], 200);
            }
            dd('ss');
            return response()->json(['message' => 'Sub Kategori tidak sesuai HATI.!'], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }

    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = OrderDetail::where('id', $request->id)->first();
            if ($data) {
                $data->status = 1;
                $data->save();
                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Data draft no sample ' . $data->no_sampel . ' berhasil direject'
                ]);
            }
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage()
            ]);
        }
    }

    public function handleGenerateLink(Request $request)
    {
        DB::beginTransaction();
        if ($request->category == 32 || $request->category == 31) {
            try {
                $header = LhpsEmisiHeader::where('no_lhp', $request->cfr)->where('is_active', 1)->first();
                $detail = LhpsEmisiDetail::where('id_header', $header->id)->get();
                $fileName = $header->file_lhp;
                if ($header != null) {

                    $key = $header->no_lhp . str_replace('.', '', microtime(true));
                    $gen = MD5($key);
                    $gen_tahun = self::encrypt(DATE('Y-m-d'));
                    $token = self::encrypt($gen . '|' . $gen_tahun);

                    $insertData = [
                        'token' => $token,
                        'key' => $gen,
                        'id_quotation' => $header->id,
                        'quotation_status' => "draft_emisi",
                        'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                        'fileName_pdf' => $fileName,
                        'type' => 'draft',
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
        } else if ($request->category2 == 34) {
            try {
                $header = LhpsEmisiCHeader::where('no_lhp', $request->cfr)->where('is_active', 1)->first();
                $detail = LhpsEmisiCDetail::where('id_header', $header->id)->get();
                $fileName = $header->file_lhp;
                if ($header != null) {

                    $key = $header->no_lhp . str_replace('.', '', microtime(true));
                    $gen = MD5($key);
                    $gen_tahun = self::encrypt(DATE('Y-m-d'));
                    $token = self::encrypt($gen . '|' . $gen_tahun);

                    $insertData = [
                        'token' => $token,
                        'key' => $gen,
                        'id_quotation' => $header->id,
                        'quotation_status' => "draft_emisi_cerobong",
                        'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                        'fileName_pdf' => $fileName,
                        'type' => 'draft',
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
        } else {
            DB::rollBack();
            return response()->json([
                'message' => 'Kategori tidak ditemukan'
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
    public function getUser(Request $request)
    {
        $users = MasterKaryawan::with(['department', 'jabatan'])->where('id', $request->id ?: $this->user_id)->first();

        return response()->json($users);
    }

    public function getLink(Request $request)
    {
        try {
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => $request->category == 32 || $request->category == 31 ? 'draft_emisi' : 'draft_emisi_cerobong', 'type' => 'draft'])->first();

            if (!$link) {
                return response()->json(['message' => 'Link not found'], 404);
            }
            return response()->json(['link' => env('PORTALV3_LINK') . $link->token], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function sendEmail(Request $request)
    {
        try {
            if ($request->kategori == 32 || $request->kategori == 31) {
                $data = LhpsEmisiHeader::where('id', $request->id)->first();
                $data->is_emailed = true;
                $data->emailed_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->emailed_by = $this->karyawan;
            } else if ($request->kategori == 34) {
                $data = LhpsEmisiCHeader::where('id', $request->id)->first();
                $data->is_emailed = true;
                $data->emailed_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->emailed_by = $this->karyawan;
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
                $data->save();
                return response()->json([
                    'message' => 'Email berhasil dikirim'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Email gagal dikirim'
                ], 400);
            }
        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

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

    public function handleApprove(Request $request)
    {
        DB::beginTransaction();
        try {
            if (in_array($request->category, [32, 31])) {
                $header = LhpsEmisiHeader::where('id', $request->id)->where('is_active', true)->first();
                // $detail = LhpsEmisiDetail::where('id_header', $header->id)->get();
                $type = 'LHP_EMISI';
            } else if (in_array($request->category, [34])) {
                $header = LhpsEmisiCHeader::where('id', $request->id)->where('is_active', true)->first();
                // $detail = LhpsEmisiCDetail::where('id_header', $header->id)->get();
                $type = 'LHP_EMISI_C';
            } else {
                return response()->json([
                    'message' => 'Kategori tidak valid'
                ], 400);
            }

            $qr = QrDocument::where('id_document', $header->id)
                ->where('type_document', $type)
                ->where('is_active', 1)
                ->where('file', $header->file_qr)
                ->orderBy('id', 'desc')
                ->first();

            if ($header != null) {
                $header->is_approve = 1;
                $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->approved_by = $this->karyawan;
                $header->save();

                if ($qr != null) {
                    $dataQr = json_decode($qr->data);
                    $dataQr->Tanggal_Pengesahan = Carbon::now()->locale('id')->isoFormat('YYYY MMMM DD');
                    $dataQr->Disahkan_Oleh = $header->nama_karyawan;
                    $dataQr->Jabatan = $header->jabatan_karyawan;
                    $qr->data = json_encode($dataQr);
                    $qr->save();
                }

                OrderDetail::where('no_sampel', $request->no_sampel)
                    ->where('is_active', true)
                    ->update([
                        'status' => 3,
                        'is_approve' => 1,
                        'approved_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'approved_by' => $this->karyawan
                    ]);

                DB::commit();
                return response()->json([
                    'message' => 'Approve no sampel ' . $request->no_sampel . ' berhasil!',
                    // 'file_name' => $fileName
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Data LHPS tidak ditemukan'
                ], 404);
            }
        } catch (Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 401);
        }
    }

    /* UNUSED ENDPOINT
    public function setSignature(Request $request)
    {
        DB::beginTransaction();
        try {
            $order_detail = OrderDetail::where('no_sampel', $request->no_sampel)
                ->where('is_active', true)->first();
            if (in_array($request->category, [32, 31])) {
                $header = LhpsEmisiHeader::where('id', $request->id)->where('is_active', true)->first();
                $detail = LhpsEmisiDetail::where('id_header', $header->id)->get();
            } else if (in_array($request->category, [34])) {
                $header = LhpsEmisiCHeader::where('id', $request->id)->where('is_active', true)->first();
                $detail = LhpsEmisiCDetail::where('id_header', $header->id)->get();
            } else {
                return response()->json([
                    'message' => 'Kategori tidak valid'
                ], 400);
            }

            if ($header != null) {
                $header->is_approve = 1;
                $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->approved_by = $this->karyawan;
                $header->nama_karyawan = $this->karyawan;
                $header->jabatan_karyawan = $request->attributes->get('user')->karyawan->jabatan;
                $header->save();

                $file_qr = new GenerateQrDocumentLhp();

                $file_qr = $file_qr->insert('LHP_EMISI', $header, $this->karyawan);
                if ($file_qr) {
                    $header->file_qr = $file_qr;
                    $header->save();
                }

                if ($order_detail != null) {
                    $order_detail->status = 3;
                    $order_detail->is_approve = 1;
                    $order_detail->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $order_detail->approved_by = $this->karyawan;
                    $order_detail->save();
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

                $job = new RenderLhp($header, $detail, 'downloadLHPFinal', $groupedByPage);
                $this->dispatch($job);

                DB::commit();
                return response()->json([
                    'message' => 'Signature berhasil diubah'
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Data LHPS tidak ditemukan'
                ], 404);
            }
        } catch (Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ]);
        }
    }

    /*public function handleDownload(Request $request)
    {
        if ($request->category == 32 || $request->category == 36) {
            try {
                $header = LhpsEmisiHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
                if ($header != null && $header->file_lhp == null) {
                    $detail = LhpsEmisiDetail::where('id_header', $header->id)->get();
                    // $custom = LhpsAirCustom::where('id_header', $header->id)->get();

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
                    $data = LhpsEmisiHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
                    $data->file_lhp = $fileName;
                    $data->save();

                } else if ($header != null && $header->file_lhp != null) {
                    $fileName = $header->file_lhp;
                }

                return response()->json([
                    'file_name' => env('APP_URL') . '/public/dokumen/LHPS/' . $fileName,
                    'message' => 'Download file ' . $request->cfr . ' berhasil!'
                ]);
            } catch (\Exception $th) {
                return response()->json([
                    'message' => 'Error download file ' . $th->getMessage(),
                ], 401);
            }
        } else {
            try {
                $header = LhpsEmisiCHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
                if ($header != null && $header->file_lhp == null) {
                    $detail = LhpsEmisiCDetail::where('id_header', $header->id)->get();
                    // $custom = LhpsAirCustom::where('id_header', $header->id)->get();

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
                    $data = LhpsEmisiCHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
                    $data->file_lhp = $fileName;
                    $data->save();

                } else if ($header != null && $header->file_lhp != null) {
                    $fileName = $header->file_lhp;
                }

                return response()->json([
                    'file_name' => env('APP_URL') . '/public/dokumen/LHPS/' . $fileName,
                    'message' => 'Download file ' . $request->cfr . ' berhasil!'
                ]);
            } catch (\Exception $th) {
                return response()->json([
                    'message' => 'Error download file ' . $th->getMessage(),
                ], 401);
            }
        }
    }*/
}
