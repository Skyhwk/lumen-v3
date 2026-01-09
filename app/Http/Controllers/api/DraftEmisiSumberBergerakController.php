<?php

namespace App\Http\Controllers\api;

// model

use App\Helpers\EmailLhpRilisHelpers;
use App\Models\{HistoryAppReject,KonfirmasiLhp,MasterKaryawan,LhpsEmisiHeader,LhpsEmisiDetail,LhpsEmisiHeaderHistory,LhpsEmisiDetailHistory,LhpsEmisiCHeader,LhpsEmisiCDetail,LhpsEmisiCHeaderHistory,LhpsEmisiCDetailHistory,OrderDetail,MetodeSampling,MasterBakumutu,PengesahanLhp,Subkontrak,DataLapanganEmisiCerobong,DataLapanganEmisiKendaraan,EmisiCerobongHeader,MasterRegulasi,Parameter,GenerateLink,QrDocument,LhpsEmisiCustom,LinkLhp};

// service
use App\Services\{PrintLhp,TemplateLhps,GenerateQrDocumentLhp,LhpTemplate,SendEmail,CombineLHPService};

// job
use App\Jobs\RenderLhp;
use App\Jobs\CombineLHPJob;

//iluminate
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DraftEmisiSumberBergerakController extends Controller
{
    public function index(Request $request)
    {
        DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
            'lhps_emisi',
            'dataLapanganEmisiKendaraan',
            'lhps_emisi_c',
            'orderHeader'
            => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('kategori_2', '5-Emisi')
            ->whereNotIn('kategori_3', ['34-Emisi Sumber Tidak Bergerak','119-Emisi Isokinetik'])
            ->groupBy('cfr')
            ->where('status', 2)
            ->get();

        return Datatables::of($data)
            ->editColumn('lhps_emisi', function ($data) {
                if (is_null($data->lhps_emisi)) {
                    return null;
                } else {
                    $data->lhps_emisi->metode_sampling = $data->lhps_emisi->metode_sampling != null ? json_decode($data->lhps_emisi->metode_sampling) : null;
                    return json_decode($data->lhps_emisi, true);
                }
            })
            ->make(true);
    }

    public function handleSubmitDraft(Request $request)
    {
        DB::beginTransaction();
        if ($request->category2 == 32 || $request->category2 == 31 || $request->category2 == 116) {
            try {
                $header = LhpsEmisiHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();
                if (!$header) {
                    $header = new LhpsEmisiHeader();
                    $header->created_by = $this->karyawan;
                    $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
                } else {
                    $history = $header->replicate();
                    $history->setTable((new LhpsEmisiHeaderHistory())->getTable());
                    $history->created_by = $this->karyawan;
                    $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $history->updated_by = null;
                    $history->updated_at = null;
                    $history->save();
                    $header->updated_by = $this->karyawan;
                    $header->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                }


                $parameter_uji = explode(', ', $request->parameter_uji);
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

                $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->tanggal_lhp)
                ->orderByDesc('berlaku_mulai')
                ->first();
                
                try {
                    $regulasi_custom = collect($request->regulasi_custom ?? [])->map(function ($item, $page) {
                        return ['page' => (int) $page, 'regulasi' => $item];
                    })->values()->toArray();

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
                    $header->tanggal_lhp = $request->tanggal_lhp;
                    $header->tgl_tugas = $request->tanggal_terima ?: NULL;
                    $header->tanggal_sampling = $request->tanggal_terima ?: NULL;
                    $header->periode_analisa = $request->periode_analisa ?: NULL;
                    $header->konsultan = $request->konsultan != '' ? $request->konsultan : NULL;
                    $header->nama_pic = $request->nama_pic ?: NULL;
                    $header->jabatan_pic = $request->jabatan_pic ?: NULL;
                    $header->no_pic = $request->no_pic ?: NULL;
                    $header->email_pic = $request->email_pic ?: NULL;
                    $header->nama_karyawan = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
                    $header->jabatan_karyawan = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';
                    $header->regulasi = isset($request->regulasi) ? json_encode($request->regulasi) : NULL;
                    $header->regulasi_custom = isset($regulasi_custom) ? json_encode($regulasi_custom) : NULL;
                    $header->save();

                    // Kode Lama
                    // $detail = LhpsEmisiDetail::where('id_header', $header->id)->first();
                    // if ($detail != null) {
                    //     $detail = LhpsEmisiDetail::where('id_header', $header->id)->delete();
                    // }

                    if ($header->id) {
                        $oldDetails = LhpsEmisiDetail::where('id_header', $header->id)->get();
                        foreach ($oldDetails as $detail) {
                            $detailHistory = $detail->replicate();
                            $detailHistory->setTable((new LhpsEmisiDetailHistory())->getTable());
                            $detailHistory->created_by = $this->karyawan;
                            $detailHistory->created_at = Carbon::now()->format('Y-m-d H:i:s');
                            $detailHistory->save();
                        }
                        LhpsEmisiDetail::where('id_header', $header->id)->delete();
                        LhpsEmisiCustom::where('id_header', $header->id)->delete();
                    }

                    $idDetail = [];
                    if ($request->category2 == 31 || $request->category2 == 116) { // Bensin || GAS
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
                                )),
                                'tanggal_sampling' => $request->tanggal_sampling[$key],

                            ]);
                            array_push($idDetail, $detail);
                        }

                        if ($request->custom_no_sampel_detail) {
                            foreach ($request->custom_no_sampel_detail as $page => $val) {
                                LhpsEmisiCustom::create([
                                    'id_header' => $header->id,
                                    'page' => $page,
                                    'no_sampel' => $request->custom_no_sampel_detail[$page],
                                    'nama_kendaraan' => $request->custom_nama_kendaraan[$page],
                                    'bobot_kendaraan' => $request->custom_bobot_kendaraan[$page],
                                    'tahun_kendaraan' => $request->custom_tahun_kendaraan[$page],
                                    'hasil_uji' => json_encode(array(
                                        "HC" => $request->custom_hasil_uji_hc[$page],
                                        "CO" => $request->custom_hasil_uji_co[$page]
                                    )),
                                    'baku_mutu' => json_encode(array(
                                        "HC" => $request->custom_baku_mutu_hc[$page],
                                        "CO" => $request->custom_baku_mutu_co[$page]
                                    )),
                                    'tanggal_sampling' => $request->tanggal_sampling[$key],

                                ]);
                            }
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
                                )),
                                'tanggal_sampling' => $request->tanggal_sampling[$key],
                            ]);
                            array_push($idDetail, $detail);
                        }

                        if ($request->custom_no_sampel_detail) {
                            foreach ($request->custom_no_sampel_detail as $page => $val) {
                                LhpsEmisiCustom::create([
                                    'id_header' => $header->id,
                                    'page' => $page,
                                    'no_sampel' => $request->custom_no_sampel_detail[$page],
                                    'nama_kendaraan' => $request->custom_nama_kendaraan[$page],
                                    'bobot_kendaraan' => $request->custom_bobot_kendaraan[$page],
                                    'tahun_kendaraan' => $request->custom_tahun_kendaraan[$page],
                                    'hasil_uji' => json_encode(array(
                                        "OP" => $request->custom_hasil_uji[$page]
                                    )),
                                    'baku_mutu' => json_encode(array(
                                        "OP" => $request->custom_baku_mutu[$page]
                                    )),
                                    'tanggal_sampling' => $request->tanggal_sampling[$key],

                                ]);
                            }
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

                        $custom = LhpsEmisiCustom::where('id_header', $header->id)
                            ->get()
                            ->groupBy('page')
                            ->toArray();

                        // $view = str_contains($header->sub_kategori, 'Bensin') ? 'DraftEmisiBensin' : 'DraftEmisiSolar';

                        if (
                            str_contains($header->sub_kategori, 'Bensin') ||
                            str_contains($header->sub_kategori, 'Emisi Kendaraan (Gas)')
                        ) {
                            $view = 'DraftEmisiBensin';
                        } else {
                            $view = 'DraftEmisiSolar';
                        }


                        $fileName = LhpTemplate::setDataHeader($header)
                            ->setDataDetail($detail)
                            ->setDataCustom($custom)
                            ->whereView($view)
                            ->render('downloadLHPFinal');

                        $header->file_lhp = $fileName;

                        if ($header->is_revisi == 1) {
                            $header->is_revisi = 0;
                            $header->is_generated = 0;
                            $header->count_revisi++;
                            if ($header->count_revisi > 2) {
                                $this->handleApprove($request, false);

                            }
                        }

                        $header->save();
                    }
                } catch (\Exception $e) {
                    throw new \Exception("Error in header or detail assignment: " . $e->getMessage() . ' on line ' . $e->getLine() . ' in file ' . $e->getFile());
                }

                DB::commit();
                return response()->json([
                    'message' => 'Data draft LHP emisi no sampel ' . $request->no_sampel . ' berhasil disimpan',
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
        }
    }

    public function handleMetodeSampling(Request $request)
    {
        try {
            $subKategori = explode('-', $request->kategori_3);
            $paramData = $request->parameter;

            if (is_string($paramData)) {
                $decoded = json_decode($paramData, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $paramData = $decoded;
                }
            }
            $param = explode(';', $paramData[0])[0];
            $result = [];
            $data = Parameter::where('id_kategori', '5')
                ->where('id', $param)
                ->get();

            $bakumutu = MasterBakumutu::where('id_regulasi', $request->regulasi)
                ->where('is_active', true)
                ->get();
            $resultx = $bakumutu->toArray();
            foreach ($resultx as $key => $value) {
                // $result[$key]['id'] = $value['id'];
                $result[$key]['metode_sampling'] = $value['method'] ?? '';
                // $result[$key]['kategori'] = $value['nama_kategori'];
                // $result[$key]['sub_kategori'] = $subKategori[1];
            }

            // $result = $resultx;

            if ($request->filled('id_lhp')) {
                $header = LhpsEmisiHeader::find($request->id_lhp);

                if ($header) {
                    $headerMetode = is_array($header->metode_sampling) ? $header->metode_sampling : json_decode($header->metode_sampling, true) ?? [];

                    foreach ($data as $key => $value) {
                        $valueMetode = array_map('trim', explode(',', $value->method));

                        $missing = array_diff($headerMetode, $valueMetode);

                        if (!empty($missing)) {
                            foreach ($missing as $miss) {
                                $result[] = [
                                    // 'id' => null,
                                    'metode_sampling' => $miss ?? '',
                                    // 'kategori' => $value->kategori,
                                    // 'sub_kategori' => $value->sub_kategori,
                                ];
                            }
                        }
                    }
                }
            }

            $result = array_values(array_unique($result, SORT_REGULAR));

            return response()->json([
                'status' => true,
                'message' => !empty($result) ? 'Available data retrieved successfully' : 'Belum ada method',
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function updateTanggalLhp(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataHeader = LhpsEmisiHeader::find($request->id);

            if (!$dataHeader) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            // Update tanggal LHP dan data pengesahan
            $dataHeader->tanggal_lhp = $request->value;

            $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->value)
                ->orderByDesc('berlaku_mulai')
                ->first();

            $dataHeader->nama_karyawan = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
            $dataHeader->jabatan_karyawan = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';

            // Update QR Document jika ada
            $qr = QrDocument::where('file', $dataHeader->file_qr)->first();
            if ($qr) {
                $dataQr = json_decode($qr->data, true);
                $dataQr['Tanggal_Pengesahan'] = Carbon::parse($request->value)->locale('id')->isoFormat('DD MMMM YYYY');
                $dataQr['Disahkan_Oleh'] = $dataHeader->nama_karyawan;
                $dataQr['Jabatan'] = $dataHeader->jabatan_karyawan;
                $qr->data = json_encode($dataQr);
                $qr->save();
            }

            // Render ulang file LHP
            $detail = LhpsEmisiDetail::where('id_header', $dataHeader->id)->get();
            $custom = LhpsEmisiCustom::where('id_header', $dataHeader->id)->get();

            $groupedByPage = [];
            foreach ($custom as $item) {
                $page = $item->page;
                $groupedByPage[$page][] = $item->toArray();

            }


            $view = str_contains($dataHeader->sub_kategori, 'Bensin') ? 'DraftEmisiBensin' : 'DraftEmisiSolar';


            $fileName = LhpTemplate::setDataDetail($detail)
                ->setDataHeader($dataHeader)
                ->setDataCustom($groupedByPage)
                ->whereView($view)
                ->render();

            if ($dataHeader->file_lhp != $fileName) {
                // ada perubahan nomor lhp yang artinya di token harus di update
                GenerateLink::where('id_quotation', $dataHeader->id_token)->update(['fileName_pdf' => $fileName]);
            }

            $dataHeader->file_lhp = $fileName;
            $dataHeader->save();

            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Tanggal LHP berhasil diubah',
                'data' => $dataHeader
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage()
            ], 500);
        }
    }

    public function handleDatadetail(Request $request)
    {
        try {
            $cek_lhp = LhpsEmisiHeader::with('lhpsEmisiDetail', 'lhpsEmisiCustom')
                ->where('no_lhp', $request->cfr)->get();


            if ($cek_lhp->isNotEmpty()) {
                $data_entry = [];
                $data_custom = [];
                $cek_regulasi = [];

                foreach ($cek_lhp as $lhp) {
                    foreach ($lhp->lhpsEmisiDetail->toArray() as $key => $val) {

                        $hasilUji = json_decode($val['hasil_uji'], true);
                        $bakuMutu = json_decode($val['baku_mutu'], true);

                        $data_entry[$key] = [
                            'id' => $val['id'],
                            'no_sampel' => $val['no_sampel'],
                            'nama_kendaraan' => $val['nama_kendaraan'] ?? '-',
                            'bobot' => $val['bobot_kendaraan'] ?? '-',
                            'tahun' => $val['tahun_kendaraan'] ?? '-',
                            'hasil_co' => $hasilUji['CO'] ?? null,
                            'hasil_hc' => $hasilUji['HC'] ?? null,
                            'hasil_op' => $hasilUji['OP'] ?? null,
                            'baku_co' => $bakuMutu['CO'] ?? null,
                            'baku_hc' => $bakuMutu['HC'] ?? null,
                            'baku_op' => $bakuMutu['OP'] ?? null,
                            'regulasi' => $val['peraturan'] ?? null,
                            'tanggal_sampling' => $val['tanggal_sampling'] ?? null,
                            'status' => ($val['akr'] ?? null) == 'ẍ'
                                ? "BELUM AKREDITASI"
                                : "AKREDITASI",
                        ];
                    }


                }

                // --- Other regulasi kalau ada
                if (!empty($request->other_regulasi)) {
                    $cek_regulasi = MasterRegulasi::whereIn('id', $request->other_regulasi)
                        ->select('id', 'peraturan as regulasi')
                        ->get()->toArray();
                }


                foreach ($cek_lhp as $lhp) {
                    if (!empty($lhp->lhpsEmisiCustom)) {
                        // Group by page
                        $groupedCustom = [];
                        foreach ($lhp->lhpsEmisiCustom as $val) {
                            $groupedCustom[$val->page][] = $val;
                        }
                    }

                    // Buat mapping regulasi berdasarkan urutan halaman
                    foreach ($cek_regulasi as $item) {
                        $id_regulasi = "id_" . $item['id'];
                        $page = array_search($item, $cek_regulasi) + 1;

                        if (!empty($groupedCustom[$page])) {
                            foreach ($groupedCustom[$page] as $val) {

                                $hasil_uji = json_decode($val['hasil_uji'], true);
                                $baku_mutu = json_decode($val['baku_mutu'], true);

                                $data_custom[$id_regulasi][] = [
                                    'id' => $val['id'],
                                    'no_sampel' => $val['no_sampel'],
                                    'nama_kendaraan' => $val['nama_kendaraan'] ?? '-',
                                    'bobot' => $val['bobot_kendaraan'] ?? '-',
                                    'tahun' => $val['tahun_kendaraan'] ?? '-',
                                    'hasil_co' => $hasil_uji['CO'] ?? null,
                                    'hasil_hc' => $hasil_uji['HC'] ?? null,
                                    'hasil_op' => $hasil_uji['OP'] ?? null,
                                    'baku_co' => $baku_mutu['CO'] ?? null,
                                    'baku_hc' => $baku_mutu['HC'] ?? null,
                                    'baku_op' => $baku_mutu['OP'] ?? null,
                                    'tanggal_sampling' => $val['tanggal_sampling'] ?? null,
                                    'status' => $val['akr'] == 'ẍ' ? "BELUM AKREDITASI" : "AKREDITASI",
                                ];
                            }
                        }
                    }
                }



                return response()->json([
                    'status' => true,
                    'data' => $data_entry,
                    'next_page' => json_encode($data_custom),
                    'keterangan' => [
                        '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                        '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                        'ẍ Parameter belum terakreditasi.'
                    ]
                ], 200);
            }

            // =======================================
            // 2️⃣ CASE: Jika BELUM ADA LHP EMISI
            // =======================================

            $mainData = [];
            $methodsUsed = [];
            $otherRegulations = [];

            // Ambil data dari lapangan (hasil uji kendaraan)
            $order_details = OrderDetail::where('cfr', $request->cfr)
                ->where('is_active', true)->get();
            // dd($order_details);
            $no_sampel = $order_details->pluck('no_sampel')->toArray();


            $lapangan = DataLapanganEmisiKendaraan::with('emisiOrder.kendaraan','detail')
                ->whereIn('no_sampel', $no_sampel)
                ->get();
            if ($lapangan->isNotEmpty()) {
                foreach ($lapangan as $lapangan) {

                    $kendaraan = $lapangan->emisiOrder->kendaraan;
                    $baku = MasterBakumutu::where('id_regulasi', $lapangan->emisiOrder->id_regulasi)
                        ->where('is_active', true)
                        ->get();
                    $hc = $co = $op = '-';
                    foreach ($baku as $xx) {
                        if (in_array($xx->parameter, ['HC', 'HC (Bensin)', 'HC (Gas)']))
                            $hc = $xx->baku_mutu;
                        if (in_array($xx->parameter, ['CO', 'CO (Bensin)', 'CO (Gas)']))
                            $co = $xx->baku_mutu;
                        if (in_array($xx->parameter, ['Opasitas', 'Opasitas (Solar)']))
                            $op = $xx->baku_mutu;
                    }

                    $tanggal_sampling = $order_details->where('no_sampel', $lapangan->no_sampel)->first()->tanggal_terima ?? null;

                    $mainData[] = [
                        'no_sampel' => $lapangan->no_sampel,
                        'nama_kendaraan' => $lapangan->detail->keterangan_1 ?? $kendaraan->merk_kendaraan ?? '-',
                        'bobot' => $kendaraan->bobot_kendaraan ?? '-',
                        'tahun' => $kendaraan->tahun_pembuatan ?? '-',
                        'hasil_co' => $lapangan->co,
                        'hasil_hc' => $lapangan->hc,
                        'hasil_op' => $lapangan->opasitas,
                        'baku_co' => $co,
                        'baku_hc' => $hc,
                        'baku_op' => $op,
                        'tanggal_sampling' => $tanggal_sampling,
                        'status' => 'AKREDITASI'
                    ];

                }
                ;
            }

            // --- handle other regulasi jika ada
            if (!empty($request->other_regulasi)) {
                foreach ($request->other_regulasi as $id_regulasi) {
                    $otherRegulations["id_" . $id_regulasi] = $mainData;
                }
            }


            return response()->json([
                'status' => true,
                'data' => $mainData,
                'next_page' => json_encode($otherRegulations),
                'keterangan' => [
                    '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                    '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                    'ẍ Parameter belum terakreditasi.'
                ]
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {

            // Kode Lama
            // $lhps = LhpsAirHeader::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();
            // dd($lhps);
            // if ($lhps) {
            //     $lhps->is_active = false;
            //     $lhps->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            //     $lhps->deleted_by = $this->karyawan;
            //     $lhps->save();
            // }

            $data = OrderDetail::where('id', $request->id)->first();

            $kategori3 = $data->kategori_3;
            $category2 = (int) explode('-', $kategori3)[0];

            if ($category2 == 31 || $category2 == 32) {
                $lhps = LhpsEmisiHeader::where('no_lhp', $data->cfr)->where('is_active', true)->first();
                if ($lhps) {
                    $lhpsHistory = $lhps->replicate();
                    $lhpsHistory->setTable((new LhpsEmisiHeaderHistory())->getTable());
                    $lhpsHistory->created_at = $lhps->created_at;
                    $lhpsHistory->updated_at = $lhps->updated_at;
                    $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                    $lhpsHistory->deleted_by = $this->karyawan;
                    $lhpsHistory->save();

                    $oldDetails = LhpsEmisiDetail::where('id_header', $lhps->id)->get();
                    foreach ($oldDetails as $detail) {
                        $detailHistory = $detail->replicate();
                        $detailHistory->setTable((new LhpsEmisiDetailHistory())->getTable());
                        $detailHistory->created_by = $this->karyawan;
                        $detailHistory->created_at = Carbon::now()->format('Y-m-d H:i:s');
                        $detailHistory->save();
                    }

                    foreach ($oldDetails as $detail) {
                        $detail->delete();
                    }

                    $lhps->delete();
                }
            } else if ($category2 === 34) {
                $lhps = LhpsEmisiCHeader::where('no_lhp', $data->no_sampel)->where('is_active', true)->first();

                if ($lhps) {
                    $lhpsHistory = $lhps->replicate();
                    $lhpsHistory->setTable((new LhpsEmisiCHeaderHistory())->getTable());
                    $lhpsHistory->created_at = $lhps->created_at;
                    $lhpsHistory->updated_at = $lhps->updated_at;
                    $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                    $lhpsHistory->deleted_by = $this->karyawan;
                    $lhpsHistory->save();

                    $oldDetails = LhpsEmisiCDetail::where('id_header', $lhps->id)->get();
                    foreach ($oldDetails as $detail) {
                        $detailHistory = $detail->replicate();
                        $detailHistory->setTable((new LhpsEmisiCDetailHistory())->getTable());
                        $detailHistory->created_by = $this->karyawan;
                        $detailHistory->created_at = Carbon::now()->format('Y-m-d H:i:s');
                        $detailHistory->save();
                    }

                    foreach ($oldDetails as $detail) {
                        $detail->delete();
                    }

                    $lhps->delete();
                }
            }

            OrderDetail::where('cfr', $data->cfr)
                ->update([
                    'status' => 1
                ]);

            $data->status = 1;
            $data->save();

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Data draft no sample ' . $data->no_sampel . ' berhasil direject'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ]);
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

    public function handleApprove(Request $request, $isManual = true)
    {
        DB::beginTransaction();
        try {
            if ($isManual) {
                $konfirmasiLhp = KonfirmasiLhp::where('no_lhp', $request->no_lhp)->first();

                if (!$konfirmasiLhp) {
                    $konfirmasiLhp = new KonfirmasiLhp();
                    $konfirmasiLhp->created_by = $this->karyawan;
                    $konfirmasiLhp->created_at = Carbon::now()->format('Y-m-d H:i:s');
                } else {
                    $konfirmasiLhp->updated_by = $this->karyawan;
                    $konfirmasiLhp->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                }

                $konfirmasiLhp->no_lhp = $request->no_lhp;
                $konfirmasiLhp->is_nama_perusahaan_sesuai = $request->nama_perusahaan_sesuai;
                $konfirmasiLhp->is_alamat_perusahaan_sesuai = $request->alamat_perusahaan_sesuai;
                $konfirmasiLhp->is_no_sampel_sesuai = $request->no_sampel_sesuai;
                $konfirmasiLhp->is_no_lhp_sesuai = $request->no_lhp_sesuai;
                $konfirmasiLhp->is_regulasi_sesuai = $request->regulasi_sesuai;
                $konfirmasiLhp->is_qr_pengesahan_sesuai = $request->qr_pengesahan_sesuai;
                $konfirmasiLhp->is_tanggal_rilis_sesuai = $request->tanggal_rilis_sesuai;

                $konfirmasiLhp->save();
            }
            $data = LhpsEmisiHeader::where('no_lhp', $request->no_lhp)
                ->where('is_active', true)
                ->first();
            $noSampel = array_map('trim', explode(',', $request->noSampel));
            $no_lhp = $data->no_lhp;

            $detail = LhpsEmisiDetail::where('id_header', $data->id)->get();

            $qr = QrDocument::where('id_document', $data->id)
                ->where('type_document', 'LHP_EMISI')
                ->where('is_active', 1)
                ->where('file', $data->file_qr)
                ->orderBy('id', 'desc')
                ->first();

            if ($data != null) {
                OrderDetail::where('cfr', $request->no_lhp)
                    ->whereIn('no_sampel', $noSampel)
                    ->where('is_active', true)
                    ->update([
                        'is_approve' => 1,
                        'status' => 3,
                        'approved_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'approved_by' => $this->karyawan
                    ]);


                $data->is_approve = 1;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->approved_by = $this->karyawan;

                $data->save();
                // if ($data->count_print < 1) {
                //     $data->is_printed = 1;
                //     $data->count_print = $data->count_print + 1;
                // }
                // dd($data->id_kategori_2);

                HistoryAppReject::insert([
                    'no_lhp' => $data->no_lhp,
                    'no_sampel' => $request->noSampel,
                    'kategori_2' => $data->id_kategori_2,
                    'kategori_3' => $data->id_kategori_3,
                    'menu' => 'Draft Emisi Sumber Bergerak',
                    'status' => 'approved',
                    'approved_at' => Carbon::now(),
                    'approved_by' => $this->karyawan
                ]);

                if ($qr != null) {
                    $dataQr = json_decode($qr->data);
                    $dataQr->Tanggal_Pengesahan = Carbon::now()->format('Y-m-d H:i:s');
                    $dataQr->Disahkan_Oleh = $this->karyawan;
                    $dataQr->Jabatan = $request->attributes->get('user')->karyawan->jabatan;
                    $qr->data = json_encode($dataQr);
                    $qr->save();
                }

                $cekDetail = OrderDetail::where('cfr', $data->no_lhp)
                    ->where('is_active', true)
                    ->first();

                $cekLink = LinkLhp::where('no_order', $data->no_order);
                if ($cekDetail && $cekDetail->periode) $cekLink = $cekLink->where('periode', $cekDetail->periode);
                $cekLink = $cekLink->first();

                if ($cekLink) {
                    $job = new CombineLHPJob($data->no_lhp, $data->file_lhp, $data->no_order, $this->karyawan, $cekDetail->periode);
                    $this->dispatch($job);
                }

                EmailLhpRilisHelpers::run([
                    'cfr'              => $request->cfr,
                    'no_order'         => $data->no_order,
                    'nama_pic_order'   => $orderHeader->nama_pic_order ?? '-',
                    'nama_perusahaan'  => $data->nama_pelanggan,
                    'periode'          => $cekDetail->periode,
                    'karyawan'         => $this->karyawan
                ]);

                // $servicePrint = new PrintLhp($data->file_lhp);
                // $servicePrint->printByFilename($data->file_lhp, $detail);

                // if (!$servicePrint) {
                //     DB::rollBack();
                //     return response()->json(['message' => 'Gagal Melakukan Reprint Data', 'status' => '401'], 401);
                // }
            }

            DB::commit();
            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Data draft Emisi Sumber Bergerak no LHP ' . $no_lhp . ' berhasil diapprove'
            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            dd($th);
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'status' => false,
                'trace' => $th->getTrace()
            ], 500);
        }
    }

    /* non aktif
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
            } else if ($request->category == 34) {
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
        public function handleRevisi(Request $request)
        {
            DB::beginTransaction();
            try {
                $header = LhpsEmisiHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
    
                if ($header != null) {
                    if ($header->is_revisi == 1) {
                        $header->is_revisi = 0;
                    } else {
                        $header->is_revisi = 1;
                    }
    
                    $header->save();
                }
    
                DB::commit();
                return response()->json([
                    'message' => 'Revisi updated successfully!',
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                ], 500);
            }
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
      
      // versi sebelumnya:
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
                    $cek_all = OrderDetail::with('orderHeader', 'lhps_emisi.lhpsEmisiCustom')->where('cfr', $cfr->cfr)->where('is_active', true)->get();

                    if ($cek_all) {
                        $data
                    }

                    $dataTable = [];
                    $dataTable2 = [];
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
                            'tanggal_sampling' => $value->tanggal_sampling ?? $values->tanggal_sampling ?? null,
                        ]);

                        $reqOtherRegs = $request->other_regulasi;
                        if ($reqOtherRegs && count($reqOtherRegs) > 0) {
                            foreach ($reqOtherRegs as $key => $idReg) {
                                if ($values->lhps_emisi && $values->lhps_emisi->lhpsEmisiCustom) {
                                    $page = $key + 1;
                                    $val = $values->lhps_emisi->lhpsEmisiCustom->where('page', $page)->first()->toArray();
                                    $orderDetail = OrderDetail::where('no_sampel', $val['no_sampel'])->first();

                                    $dataTable2["id_$idReg"][] = [
                                        'no_sampel' => $val['no_sampel'],
                                        'nama_kendaraan' => $orderDetail->keterangan_1 ?? $val['nama_kendaraan'],
                                        'bobot_kendaraan' => $val['bobot_kendaraan'],
                                        'tahun_kendaraan' => $val['tahun_kendaraan'],
                                        'hasil_uji' => $val['hasil_uji'],
                                        'baku_mutu' => $val['baku_mutu'],
                                        'tanggal_sampling' => $val['tanggal_sampling'] ?? $values->tanggal_sampling ?? null,
                                    ];
                                } else {
                                    $dataTable2["id_$idReg"][] = [
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
                                        'tanggal_sampling' => $value->tanggal_sampling ?? $values->tanggal_sampling ?? null,
                                    ];
                                }
                            }
                        }
                    }

                    return response()->json([
                        // 'data' => $cfr,
                        'dataAll' => $cek_all[0],
                        // 'datalapangan' => $datalapangan,
                        'data' => $dataTable,
                        'next_page' => json_encode($dataTable2),
                        'keterangan' => [
                            '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                            '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                            'ẍ Parameter belum terakreditasi.'
                        ],
                    ], 200);
                }

                return response()->json(['message' => 'Sub Kategori tidak sesuai.!'], 400);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ], 500);
            }
        }
        public function handleMetodeSampling(Request $request)
        {
            try {
                $id_parameter = array_map(fn($item) => explode(';', $item)[0], $request->parameter);

                $method = Parameter::where('id', $id_parameter)
                    ->pluck('method')
                    ->map(function ($item) {
                        return $item === null ? '-' : $item;
                    })
                    ->toArray();

                return response()->json([
                    'status' => true,
                    'message' => 'Available data retrieved successfully',
                    'data' => $method
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                    'line' => $e->getLine()
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
                        $dataQr->Disahkan_Oleh = $this->karyawan;
                        $dataQr->Jabatan = $request->attributes->get('user')->karyawan->jabatan;
                        $qr->data = json_encode($dataQr);
                        $qr->save();
                    }

                    OrderDetail::where('cfr', $request->cfr)
                        ->where('is_active', true)
                        ->update([
                            'status' => 3,
                            'is_approve' => 1,
                            'approved_at' => Carbon::now()->format('Y-m-d H:i:s'),
                            'approved_by' => $this->karyawan
                        ]);

                    DB::commit();
                    return response()->json([
                        'message' => 'Approve no LHP ' . $request->cfr . ' berhasil!',
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
        public function handleDownload(Request $request)
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
        }
    */
}
