<?php

namespace App\Http\Controllers\api;

use App\Helpers\EmailLhpRilisHelpers;
use App\Models\HistoryAppReject;

use App\Models\lhpsSinarUVCustom;
use App\Models\LhpsSinarUVHeader;
use App\Models\LhpsSinarUVDetail;
use App\Models\LhpsSinarUVHeaderHistory;
use App\Models\LhpsSinarUVDetailHistory;


use App\Models\MasterRegulasi;
use App\Models\MasterSubKategori;
use App\Models\OrderDetail;
use App\Models\MasterKaryawan;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;

use App\Models\SinarUVHeader;

use App\Models\Parameter;
use App\Models\GenerateLink;
use App\Services\LhpTemplate;
use App\Services\SendEmail;
use App\Services\GenerateQrDocumentLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Jobs\CombineLHPJob;
use App\Models\KonfirmasiLhp;
use App\Models\LinkLhp;
use App\Models\OrderHeader;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DraftUlkSinarUvController extends Controller
{
    public function index()
    {
        DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
            'lhps_sinaruv',
            'orderHeader' => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->where('kategori_3', "27-Udara Lingkungan Kerja")
            ->whereJsonContains('parameter', '324;Sinar UV')
            ->groupBy('cfr')
            ->where('status', 2)
            ->get();

        foreach ($data as $key => $value) {
            if (isset($value->lhps_sinaruv) && $value->lhps_sinaruv->metode_sampling != null) {
                $data[$key]->lhps_sinaruv->metode_sampling = json_decode($value->lhps_sinaruv->metode_sampling);
            }
        }

        return Datatables::of($data)->make(true);
    }

    // Amang
    public function getKategori()
    {
        $kategori = MasterSubKategori::where('id_kategori', 4)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $kategori,
            'message' => 'Available data category retrieved successfully',
        ], 201);
    }


    public function handleMetodeSampling(Request $request)
    {
        try {
            $subKategori = explode('-', $request->kategori_3);
            $param = explode(';', (json_decode($request->parameter)[0]))[0];
            $result = [];
            // Data utama
            $data = Parameter::where('id_kategori', '4')
                ->where('id', $param)
                ->get();
            $resultx = $data->toArray();
            foreach ($resultx as $key => $value) {
                $result[$key]['id'] = $value['id'];
                $result[$key]['metode_sampling'] = $value['method'] ?? '';
                $result[$key]['kategori'] = $value['nama_kategori'];
                $result[$key]['sub_kategori'] = $subKategori[1];
            }

            // $result = $resultx;

            if ($request->filled('id_lhp')) {
                $header = LhpsSinarUVHeader::find($request->id_lhp);

                if ($header) {
                    $headerMetode = json_decode($header->metode_sampling, true) ?? [];

                    foreach ($data as $key => $value) {
                        $valueMetode = array_map('trim', explode(',', $value->method));

                        $missing = array_diff($headerMetode, $valueMetode);

                        if (!empty($missing)) {
                            foreach ($missing as $miss) {
                                $result[] = [
                                    'id' => null,
                                    'metode_sampling' => $miss ?? '',
                                    'kategori' => $value->kategori,
                                    'sub_kategori' => $value->sub_kategori,
                                ];
                            }
                        }
                    }
                }
            }

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



    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // === 1. Ambil header / buat baru ===
            $header = LhpsSinarUVHeader::where([
                'no_lhp' => $request->no_lhp,
                'no_order' => $request->no_order,
                'is_active' => true
            ])->first();

            if ($header) {
                $history = $header->replicate();
                $history->setTable((new LhpsSinarUVHeaderHistory())->getTable());
                // $history->id = $header->id;
                $history->created_at = Carbon::now();
                $history->save();
            } else {
                $header = new LhpsSinarUVHeader();
            }

            // === 2. Validasi tanggal LHP ===
            if (empty($request->tanggal_lhp)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Tanggal pengesahan LHP tidak boleh kosong',
                    'status' => false
                ], 400);
            }

            $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->tanggal_lhp)
                ->orderByDesc('berlaku_mulai')
                ->first();

            $nama_perilis = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
            $jabatan_perilis = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';

            // === 3. Persiapan data header ===
            $parameter_uji = !empty($request->parameter_header) ? explode(', ', $request->parameter_header) : [];
            $keterangan = array_values(array_filter($request->keterangan ?? []));

            $regulasi_custom = collect($request->regulasi_custom ?? [])->map(function ($item, $page) {
                return ['page' => (int) $page, 'regulasi' => $item];
            })->values()->toArray();
            // === 4. Simpan / update header ===
            $header->fill([
                'no_order' => $request->no_order ?: null,
                'no_sampel' => implode(', ', $request->no_sampel) ?: null,
                'no_lhp' => $request->no_lhp ?: null,
                'no_qt' => $request->no_penawaran ?: null,
                'status_sampling' => $request->type_sampling ?: null,
                'tanggal_sampling'=> implode(', ', $request->tanggal_sampling) ?: null,
                'tanggal_terima' => $request->tanggal_terima ?: null,
                'parameter_uji' => json_encode($parameter_uji),
                'nama_pelanggan' => $request->nama_perusahaan ?: null,
                'alamat_sampling' => $request->alamat_sampling ?: null,
                'sub_kategori' => $request->jenis_sampel ?: null,
                'id_kategori_2' => 4,
                'id_kategori_3' => null,
                'metode_sampling' => $request->metode_sampling ? json_encode($request->metode_sampling) : null,
                'nama_karyawan' => $nama_perilis,
                'jabatan_karyawan' => $jabatan_perilis,
                'regulasi' => $request->regulasi ? json_encode($request->regulasi) : null,
                'regulasi_custom' => $regulasi_custom ? json_encode($regulasi_custom) : null,
                'keterangan' => $keterangan ? json_encode($keterangan) : null,
                'tanggal_lhp' => $request->tanggal_lhp ?: null,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now(),
            ]);
            $header->save();

            // === 5. Backup & replace detail ===
            $oldDetails = LhpsSinarUVDetail::where('id_header', $header->id)->get();
            foreach ($oldDetails as $detail) {
                $detailHistory = $detail->replicate();
                $detailHistory->setTable((new LhpsSinarUVDetailHistory())->getTable());
                // $detailHistory->id = $detail->id;
                $detailHistory->created_by = $this->karyawan;
                $detailHistory->created_at = Carbon::now();
                $detailHistory->save();
            }
            LhpsSinarUVDetail::where('id_header', $header->id)->delete();

            foreach ($request->no_sampel ?? [] as $key => $val) {
                LhpsSinarUVDetail::create([
                    'id_header' => $header->id,
                    'no_sampel' => $val,
                    'keterangan' => $request->keterangan_detail[$key] ?? '',
                    'parameter' => $request->param[$key] ?? '',
                    'aktivitas_pekerjaan' => $request->aktivitas_pekerjaan[$key] ?? '',
                    'sumber_radiasi' => $request->sumber_radiasi[$key] ?? '',
                    'waktu_pemaparan' => $request->waktu_pemaparan[$key] ?? '',
                    'nab' => $request->nab[$key] ?? '',
                    'mata' => $request->mata[$key] ?? '',
                    'siku' => $request->siku[$key] ?? '',
                    'betis' => $request->betis[$key] ?? '',
                    'tanggal_sampling' => $request->tanggal_sampling[$key] ?? '',
                ]);
            }

            // === 6. Handle custom ===
            LhpsSinarUVCustom::where('id_header', $header->id)->delete();

            if ($request->custom_no_sampel) {
                foreach ($request->custom_no_sampel as $page => $sampel) {
                    foreach ($sampel as $sampel => $hasil) {
                        LhpsSinarUVCustom::create([
                            'id_header' => $header->id,
                            'page' => $page,
                            'no_sampel' => $request->custom_no_sampel[$page][$sampel] ?? null,
                            'keterangan' => $request->custom_keterangan_detail[$page][$sampel],
                            'parameter' => $request->custom_parameter[$page][$sampel] ?? null,
                            'aktivitas_pekerjaan' => $request->custom_aktivitas_pekerjaan[$page][$sampel] ?? null,
                            'sumber_radiasi' => $request->custom_sumber_radiasi[$page][$sampel] ?? null,
                            'nab' => $request->custom_nab[$page][$sampel] ?? null,
                            'waktu_pemaparan' => $request->custom_waktu_pemaparan[$page][$sampel] ?? null,
                            'mata' => $request->custom_mata[$page][$sampel] ?? null,
                            'siku' => $request->custom_siku[$page][$sampel] ?? null,
                            'betis' => $request->custom_betis[$page][$sampel] ?? null,
                            'tanggal_sampling' => $request->custom_tanggal_sampling[$page][$sampel] ?? null,
                        ]);
                    }
                }
            }

            // === 7. Generate QR & File ===
            if (!$header->file_qr) {
                $file_qr = new GenerateQrDocumentLhp();
                if ($path = $file_qr->insert('LHP_SINAR_UV', $header, $this->karyawan)) {
                    $header->file_qr = $path;
                    $header->save();
                }
            }

            $groupedByPage = collect(lhpsSinarUVCustom::where('id_header', $header->id)->get())
                ->groupBy('page')
                ->toArray();

            $fileName = LhpTemplate::setDataDetail(LhpsSinarUVDetail::where('id_header', $header->id)->get())
                ->setDataHeader($header)
                ->setDataCustom($groupedByPage)
                ->useLampiran(true)
                ->whereView('DraftUlkSinarUv')
                ->render('downloadLHPFinal');

            $header->file_lhp = $fileName;
            
            $header->save();

            DB::commit();
            return response()->json([
                'message' => "Data draft lhps sinar uv no LHP {$request->no_lhp} berhasil disimpan",
                'status' => true
            ], 201);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'status' => false,
                'getLine' => $th->getLine(),
                'getFile' => $th->getFile(),
            ], 500);
        }
    }



    public function handleDatadetail(Request $request)
    {
        try {
            $noSampel = explode(', ', $request->no_sampel);
            $cek_lhp = LhpsSinarUVHeader::with('lhpsSinaruvDetail', "lhpsSinaruvCustom")->where('no_lhp', $request->cfr)
                ->where('is_active', true)
                ->first();
            if ($cek_lhp) {
                $data_entry = array();
                $data_custom = array();
                $cek_regulasi = array();
                foreach ($cek_lhp->lhpsSinaruvDetail->toArray() as $key => $val) {
                    $data_entry[$key] = [
                        'id' => $val['id'],
                        'parameter' => $val['parameter'],
                        'no_sampel' => $val['no_sampel'],
                        'keterangan' => $val['keterangan'],
                        'aktivitas_pekerjaan' => $val['aktivitas_pekerjaan'],
                        'sumber_radiasi' => $val['sumber_radiasi'],
                        'waktu_pemaparan' => $val['waktu_pemaparan'],
                        'mata' => $val['mata'],
                        'siku' => $val['siku'],
                        'betis' => $val['betis'],
                        'nab' => $val['nab'],
                        'tanggal_sampling' => $val['tanggal_sampling'],
                    ];
                }

                if (isset($request->other_regulasi) && !empty($request->other_regulasi)) {
                    $cek_regulasi = MasterRegulasi::whereIn('id', $request->other_regulasi)->select('id', 'peraturan as regulasi')->get()->toArray();
                }

                if (!empty($cek_lhp->lhpsSinaruvCustom) && !empty($cek_lhp->regulasi_custom)) {
                    $regulasi_custom = json_decode($cek_lhp->regulasi_custom, true);

                    if (!empty($cek_regulasi)) {
                        $mapRegulasi = collect($cek_regulasi)->pluck('id', 'regulasi')->toArray();

                        $regulasi_custom = array_map(function ($item) use (&$mapRegulasi) {
                            $regulasi_clean = preg_replace('/\*+/', '', $item['regulasi']);
                            if (isset($mapRegulasi[$regulasi_clean])) {
                                $item['id'] = $mapRegulasi[$regulasi_clean];
                            } else {
                                $regulasi_db = MasterRegulasi::where('peraturan', $regulasi_clean)->first();
                                if ($regulasi_db) {
                                    $item['id'] = $regulasi_db->id;
                                    $mapRegulasi[$regulasi_clean] = $regulasi_db->id;
                                }
                            }
                            return $item;
                        }, $regulasi_custom);
                    }

                    $groupedCustom = [];
                    foreach ($cek_lhp->lhpsSinaruvCustom as $val) {
                        $groupedCustom[$val->page][] = $val;
                    }

                    usort($regulasi_custom, function ($a, $b) {
                        return $a['page'] <=> $b['page'];
                    });

                    foreach ($regulasi_custom as $item) {
                        if (empty($item['id']) || empty($item['page']))
                            continue;
                        $id_regulasi = (string) "id_" . $item['id'];
                        $page = $item['page'];

                        if (!empty($groupedCustom[$page])) {
                            foreach ($groupedCustom[$page] as $val) {
                                $data_custom[$id_regulasi][] = [
                                    'id' => $val['id'],
                                    'parameter' => $val['parameter'],
                                    'no_sampel' => $val['no_sampel'],
                                    'keterangan' => $val['keterangan'],
                                    'aktivitas_pekerjaan' => $val['aktivitas_pekerjaan'],
                                    'sumber_radiasi' => $val['sumber_radiasi'],
                                    'waktu_pemaparan' => $val['waktu_pemaparan'],
                                    'mata' => $val['mata'],
                                    'siku' => $val['siku'],
                                    'betis' => $val['betis'],
                                    'nab' => $val['nab'],
                                    'tanggal_sampling' => $val['tanggal_sampling'],
                                ];
                            }
                        }
                    }
                }

                return response()->json([
                    'status' => true,
                    'data' => $data_entry,
                    'next_page' => $data_custom,

                ], 201);
            } else {

                $mainData = [];
                $methodsUsed = [];
                $otherRegulations = [];

                $models = [
                    SinarUvHeader::class,
                ];

                foreach ($models as $model) {
                    $data = $model::with('ws_udara', 'master_parameter')
                        ->whereIn('no_sampel', $noSampel)
                        ->where('is_approved', 1)
                        ->where('is_active', true)
                        ->where('lhps', 1)
                        ->get();

                    foreach ($data as $val) {
                        $entry = $this->formatEntry($val, $request->regulasi, $methodsUsed);
                        $mainData[] = $entry;

                        if ($request->other_regulasi) {
                            foreach ($request->other_regulasi as $id_regulasi) {
                                $otherRegulations[$id_regulasi][] = $this->formatEntry($val, $id_regulasi);
                            }
                        }
                    }
                }

                $mainData = collect($mainData)->sortBy(function ($item) {
                    return mb_strtolower($item['parameter']);
                })->values()->toArray();

                foreach ($otherRegulations as $id => $regulations) {
                    $otherRegulations[$id] = collect($regulations)->sortBy(function ($item) {
                        return mb_strtolower($item['parameter']);
                    })->values()->toArray();
                }
                // $methodsUsed = array_values(array_unique($methodsUsed));


                return response()->json([
                    'status' => true,
                    'data' => $mainData,
                    'next_page' => $otherRegulations,
                ], 201);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'getFile' => $e->getFile(),
            ], 500);
        }
    }
    private function formatEntry($val, $regulasiId)
    {
        $param = $val->master_parameter;
        $lapangan = $val->datalapangan;
        $ws = $val->ws_udara;

        $mata = $siku = $betis = '';

        if ($ws && $ws->hasil1) {
            $hasil2 = json_decode($ws->hasil1);
            if (is_object($hasil2)) {
                $mata = $hasil2->Mata ?? '';
                $siku = $hasil2->Siku ?? '';
                $betis = $hasil2->Betis ?? '';
            } else {
                $mata = $ws->hasil1 ?? '';
                $siku = $ws->hasil2 ?? '';
                $betis = $ws->hasil3 ?? '';
            }
        }

        $keterangan = '';
        if ($lapangan) {
            if ($lapangan->keterangan_2 === '-') {
                $keterangan = $lapangan->aktivitas_pekerja ?? '';
            } else {
                $keter = strpos($lapangan->keterangan_2, ':') !== false
                    ? explode(":", $lapangan->keterangan_2)
                    : [$lapangan->keterangan_2];
                $keterangan = (isset($keter[1]) ? $keter[1] : $keter[0])
                    . ' - '
                    . ($lapangan->aktivitas_pekerja ?? '');
            }
        }
        return [
            'id' => $val->id,
            'parameter' => $param->nama_lab ?? '',
            'no_sampel' => $val->no_sampel ?? '',
            'keterangan' => $keterangan,
            'aktivitas_pekerjaan' => $lapangan->aktivitas_pekerja ?? '',
            'sumber_radiasi' => $lapangan->sumber_radiasi ?? '',
            'waktu_pemaparan' => $lapangan->waktu_pemaparan ?? '',
            'mata' => $mata,
            'siku' => $siku,
            'betis' => $betis,
            'nab' => $ws->nab ?? null,
        ];
    }


    public function updateTanggalLhp(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataHeader = LhpsSinarUVHeader::find($request->id);

            if (!$dataHeader) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak ditemukan, harap adjust data terlebih dahulu'
                ], 404);
            }

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
            $detail = LhpsSinarUVDetail::where('id_header', $dataHeader->id)->get();

            $fileName = LhpTemplate::setDataDetail($detail)
                ->setDataHeader($dataHeader)
                ->whereView('DraftUlkSinarUv')
                ->render();


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
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }
    public function handleApprove(Request $request, $isManual = true)
    {
        try {
            if ($isManual) {
                $konfirmasiLhp = KonfirmasiLhp::where('no_lhp', $request->cfr)->first();

                if (!$konfirmasiLhp) {
                    $konfirmasiLhp = new KonfirmasiLhp();
                    $konfirmasiLhp->created_by = $this->karyawan;
                    $konfirmasiLhp->created_at = Carbon::now()->format('Y-m-d H:i:s');
                } else {
                    $konfirmasiLhp->updated_by = $this->karyawan;
                    $konfirmasiLhp->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                }

                $konfirmasiLhp->no_lhp = $request->cfr;
                $konfirmasiLhp->is_nama_perusahaan_sesuai = $request->nama_perusahaan_sesuai;
                $konfirmasiLhp->is_alamat_perusahaan_sesuai = $request->alamat_perusahaan_sesuai;
                $konfirmasiLhp->is_no_sampel_sesuai = $request->no_sampel_sesuai;
                $konfirmasiLhp->is_no_lhp_sesuai = $request->no_lhp_sesuai;
                $konfirmasiLhp->is_regulasi_sesuai = $request->regulasi_sesuai;
                $konfirmasiLhp->is_qr_pengesahan_sesuai = $request->qr_pengesahan_sesuai;
                $konfirmasiLhp->is_tanggal_rilis_sesuai = $request->tanggal_rilis_sesuai;

                $konfirmasiLhp->save();
            }
            $data = LhpsSinarUVHeader::where('no_lhp', $request->no_lhp)
                ->where('is_active', true)
                ->first();
            $noSampel = array_map('trim', explode(',', $request->noSampel));
            $no_lhp = $data->no_lhp;

            $qr = QrDocument::where('id_document', $data->id)
                ->where('type_document', 'LHP_SINAR_UV')
                ->where('is_active', 1)
                ->where('file', $data->file_qr)
                ->orderBy('id', 'desc')
                ->first();

            if ($data != null) {
                OrderDetail::where('cfr', $data->no_lhp)
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
                // if ($data->count_print < 1) {
                //     $data->is_printed = 1;
                //     $data->count_print = $data->count_print + 1;
                // }
                $data->save();
                HistoryAppReject::insert([
                    'no_lhp' => $data->no_lhp,
                    'no_sampel' => $request->noSampel,
                    'kategori_2' => $data->id_kategori_2,
                    'kategori_3' => $data->id_kategori_3,
                    'menu' => 'Draft Udara',
                    'status' => 'approved',
                    'approved_at' => Carbon::now(),
                    'approved_by' => $this->karyawan
                ]);
                if ($qr != null) {
                    $dataQr = json_decode($qr->data);
                    $dataQr->Tanggal_Pengesahan = Carbon::now()->format('Y-m-d H:i:s');
                    $dataQr->Disahkan_Oleh = $data->nama_karyawan;
                    $dataQr->Jabatan = $data->jabatan_karyawan;
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

                $orderHeader = OrderHeader::where('id', $cekDetail->id_order_header)->first();

                EmailLhpRilisHelpers::run([
                    'cfr'              => $request->cfr,
                    'no_order'         => $data->no_order,
                    'nama_pic_order'   => $orderHeader->nama_pic_order ?? '-',
                    'nama_perusahaan'  => $data->nama_pelanggan,
                    'periode'          => $cekDetail->periode,
                    'karyawan'         => $this->karyawan
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Data draft LHP sinar uv dengan no LHP ' . $no_lhp . ' tidak ditemukan',
                    'status' => false
                ], 404);
            }

            DB::commit();
            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Data draft LHP sinar uv dengan no LHP ' . $no_lhp . ' berhasil diapprove'
            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            dd($th);
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'status' => false
            ], 500);
        }
    }

    // Amang
    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $lhps = LhpsSinarUVHeader::where('id', $request->id)
                ->where('is_active', true)
                ->first();
            $no_lhp = $lhps->no_lhp ?? null;
            if ($lhps) {
                HistoryAppReject::insert([
                    'no_lhp' => $lhps->no_lhp,
                    'no_sampel' => $request->no_sampel,
                    'kategori_2' => $lhps->id_kategori_2,
                    'kategori_3' => $lhps->id_kategori_3,
                    'menu' => 'Draft Udara',
                    'status' => 'rejected',
                    'rejected_at' => Carbon::now(),
                    'rejected_by' => $this->karyawan
                ]);
                $lhpsHistory = $lhps->replicate();
                $lhpsHistory->setTable((new LhpsSinarUVHeaderHistory())->getTable());
                $lhpsHistory->created_at = $lhps->created_at;
                $lhpsHistory->updated_at = $lhps->updated_at;
                $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $lhpsHistory->deleted_by = $this->karyawan;
                $lhpsHistory->save();

                $oldDetails = LhpsSinarUVDetail::where('id_header', $lhps->id)->get();
                $oldCustom = LhpsSinarUVCustom::where('id_header', $lhps->id)->get();

                foreach ($oldDetails as $detail) {
                    $detailHistory = $detail->replicate();
                    $detailHistory->setTable((new LhpsSinarUVDetailHistory())->getTable());
                    $detailHistory->created_by = $this->karyawan;
                    $detailHistory->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $detailHistory->save();
                }

                foreach ($oldDetails as $detail) {
                    $detail->delete();
                }

                foreach ($oldCustom as $custom) {
                    $custom->delete();
                }

                $lhps->delete();
            }
            $noSampel = array_map('trim', explode(",", $request->no_sampel));

            if ($no_lhp) {
                OrderDetail::where('cfr', $no_lhp)
                    ->whereIn('no_sampel', $noSampel)
                    ->update([
                        'status' => 1
                    ]);
            } else {
                // kalau tidak ada LHP, update tetap bisa dilakukan dengan kriteria lain
                // contoh: berdasarkan no_sampel saja
                OrderDetail::whereIn('no_sampel', $noSampel)
                    ->update([
                        'status' => 1
                    ]);
            }


            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Data draft dengan no LHP ' . $no_lhp . ' berhasil direject'
            ]);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage()
            ]);
        }
    }

    public function generate(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsSinarUVHeader::where('no_lhp', $request->no_lhp)
                ->where('is_active', true)
                // ->where('id', $request->id)
                ->first();
            if ($header != null) {
                $key = $header->no_lhp . str_replace('.', '', microtime(true));
                $gen = MD5($key);
                $gen_tahun = self::encrypt(DATE('Y-m-d'));
                $token = self::encrypt($gen . '|' . $gen_tahun);

                $cek = GenerateLink::where('fileName_pdf', $header->file_lhp)->first();
                if ($cek) {
                    $cek->id_quotation = $header->id;
                    $cek->expired = Carbon::now()->addYear()->format('Y-m-d');
                    $cek->created_by = $this->karyawan;
                    $cek->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $cek->save();

                    $header->id_token = $cek->id;
                } else {
                    $insertData = [
                        'token' => $token,
                        'key' => $gen,
                        'id_quotation' => $header->id,
                        'quotation_status' => 'draft_sinar_uv',
                        'type' => 'draft',
                        'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                        'fileName_pdf' => $header->file_lhp,
                        'created_by' => $this->karyawan,
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s')
                    ];

                    $insert = GenerateLink::insertGetId($insertData);

                    $header->id_token = $insert;
                }

                $header->is_generated = true;
                $header->generated_by = $this->karyawan;
                $header->generated_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->expired = Carbon::now()->addYear()->format('Y-m-d');
                $header->save();
            }
            DB::commit();
            return response()->json([
                'message' => 'Generate link success!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'status' => false
            ], 500);
        }
    }
    public function handleRevisi(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsSinarUVHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();

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

    // Amang
    public function getUser(Request $request)
    {
        $users = MasterKaryawan::with(['department', 'jabatan'])->where('id', $request->id ?: $this->user_id)->first();

        return response()->json($users);
    }

    // Amang
    public function getLink(Request $request)
    {
        try {
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'draft_sinar_uv', 'type' => 'draft'])->first();
            if (!$link) {
                return response()->json(['message' => 'Link not found'], 404);
            }
            return response()->json(['link' => env('PORTALV3_LINK') . $link->token], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    // Amang
    public function sendEmail(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id != '' || isset($request->id)) {
                LhpsSinarUVHeader::where('id', $request->id)->update([
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
            dd($th);
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

    // Amang
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
