<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;

use App\Models\LhpsGetaranCustom;
use App\Models\LhpsGetaranHeader;
use App\Models\LhpsGetaranDetail;


use App\Models\LhpsGetaranHeaderHistory;
use App\Models\LhpsGetaranDetailHistory;

use App\Models\MasterRegulasi;
use App\Models\MasterSubKategori;
use App\Models\OrderDetail;
use App\Models\MetodeSampling;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;

use App\Models\GetaranHeader;

use App\Models\GenerateLink;
use App\Services\SendEmail;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DraftUdaraGetaranController extends Controller
{
    // done if status = 2
    // AmanghandleDatadetail
    public function index(Request $request)
    {
        $kategori = ["13-Getaran", "14-Getaran (Bangunan)", "15-Getaran (Kejut Bangunan)", "16-Getaran (Kejut Bangunan)", "17-Getaran (Lengan & Tangan)", "18-Getaran (Lingkungan)", "19-Getaran (Mesin)", "20-Getaran (Seluruh Tubuh)"];

        DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
            'lhps_getaran',
            'orderHeader'
            => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', $kategori)
            ->groupBy('cfr')
            ->where('status', 2)
            ->get();

            foreach ($data as $key => $value) {
                if(isset($value->lhps_getaran) && $value->lhps_getaran->metode_sampling != null ){
                    $value->lhps_getaran->metode_sampling = json_decode($value->lhps_getaran->metode_sampling);
                }
            }

        return Datatables::of($data)->make(true);
    }

    // Amang
    public function getKategori(Request $request)
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

    public function handleRevisi(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsGetaranHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();

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
                    $header = LhpsGetaranHeader::find($request->id_lhp);

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
         $header = LhpsGetaranHeader::where([
            'no_lhp'        => $request->no_lhp,
            'no_order'      => $request->no_order,
            'is_active'     => true
        ])->first();
        $is_revisi = false;
            if ($header) {
                $is_revisi = $header->is_revisi == 1 ? true : false;
                // Backup ke history sebelum update
                $history = $header->replicate();
                $history->setTable((new LhpsGetaranHeaderHistory())->getTable());
                // $history->id = $header->id;
                $history->created_at = Carbon::now();
                $history->save();
            } else {
                $header = new LhpsGetaranHeader();
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
            $keterangan    = array_values(array_filter($request->keterangan ?? []));

            $regulasi_custom = collect($request->regulasi_custom ?? [])->map(function ($item, $page) {
                return ['page' => (int)$page, 'regulasi' => $item];
            })->values()->toArray();
            // === 4. Simpan / update header ===
            $header->fill([
                'no_order'        => $request->no_order ?: null,
                'no_sampel'       => implode(', ',$request->no_sampel) ?: null,
                'no_lhp'          => $request->no_lhp ?: null,
                'no_qt'           => $request->no_penawaran ?: null,
                'status_sampling' => $request->type_sampling ?: null,
                // 'tanggal_sampling'=> $request->tanggal_sampling ?: null,
                'tanggal_terima'  => $request->tanggal_terima ?: null,
                'parameter_uji'   => json_encode($parameter_uji),
                'nama_pelanggan'  => $request->nama_perusahaan ?: null,
                'alamat_sampling' => $request->alamat_sampling ?: null,
                'sub_kategori'    => $request->jenis_sampel ?: null,
                'id_kategori_2'    => 4,
                'id_kategori_3'    => null,
                'metode_sampling'=> $request->metode_sampling ? json_encode($request->metode_sampling) : null,
                'nama_karyawan'   => $nama_perilis,
                'is_revisi'       => false,
                'count_revisi'    => $is_revisi ? $header->count_revisi + 1 : $header->count_revisi,
                'is_generated'    => $is_revisi ? false : $header->is_generated,
                'jabatan_karyawan'=> $jabatan_perilis,
                'regulasi'        => $request->regulasi ? json_encode($request->regulasi) : null,
                'regulasi_custom' => $regulasi_custom ? json_encode($regulasi_custom) : null,
                'keterangan'      => $keterangan ? json_encode($keterangan) : null,
                'tanggal_lhp'     => $request->tanggal_lhp ?: null,
                'created_by'      => $this->karyawan,
                'created_at'      => Carbon::now(),
            ]);
            $header->save();

            // === 5. Backup & replace detail ===
            $oldDetails = LhpsGetaranDetail::where('id_header', $header->id)->get();
            foreach ($oldDetails as $detail) {
                $detailHistory = $detail->replicate();
                $detailHistory->setTable((new LhpsGetaranDetailHistory())->getTable());
                // $detailHistory->id = $detail->id;
                $detailHistory->created_by = $this->karyawan;
                $detailHistory->created_at = Carbon::now();
                $detailHistory->save();
            }
            LhpsGetaranDetail::where('id_header', $header->id)->delete();
    foreach ($request->no_sampel ?? [] as $key => $val) {
        //   dd($request->all());
        if (in_array("Getaran (LK) TL", $request->param) || in_array("Getaran (LK) ST", $request->param)) {
                $detail = new LhpsGetaranDetail([
                    'id_header'   => $header->id,
                    'no_sampel'   => $request->no_sampel[$val] ?? null,
                    'param'       =>$request->param[$val] ?? null,
                    'keterangan'  => $request->keterangan_detail[$val] ?? null,
                    'sumber_get'  =>$request->sumber_get[$val] ?? null,
                    'w_paparan'   => $request->w_paparan[$val] ?? null,
                    'hasil'       => $request->hasil[$val] ?? null,
                    'tipe_getaran'=>$request->tipe_getaran[$val] ?? null,
                    'nab'         => $request->nab[$val] ?? null,
                    'tanggal_sampling'         => $request->tanggal_sampling[$val] ?? null,
                ]);
                $detail->save();
            } else {
                $detail = new LhpsGetaranDetail([
                    'id_header'   => $header->id,
                    'no_sampel'   => $val,
                    'param'       => $request->param[$val] ?? null,
                    'keterangan'  => $request->keterangan_detail[$val] ?? null,
                    'percepatan'  => $request->percepatan[$val] ?? null,
                    'kecepatan'   => $request->kecepatan[$val] ?? null,
                    'tipe_getaran'=> $request->tipe_getaran[$val] ?? null,
                    'tanggal_sampling'  => $request->tanggal_sampling[$val] ?? null,
                ]);
                $detail->save();
            }
        }
            // === 6. Handle custom ===
            LhpsGetaranCustom::where('id_header', $header->id)->delete();

            if ($request->custom_no_sampel) {
                foreach ($request->custom_no_sampel as $page => $sampel) {
                    foreach ($sampel as $sampel => $hasil) {
                        LhpsGetaranCustom::create([
                            'id_header'   => $header->id,
                            'page'        => $page,
                            'no_sampel' => $request->custom_no_sampel[$page][$sampel] ?? null,
                            'keterangan'   =>  $request->custom_keterangan_detail[$page][$sampel],
                            'hasil'   => $request->custom_hasil[$page][$sampel] ?? null,
                            'sumber_get'      => $request->custom_sumber_get[$page][$sampel] ?? null,
                            'w_paparan'      => $request->custom_w_paparan[$page][$sampel] ?? null,
                            'param'     => $request->custom_parameter[$page][$sampel] ?? null,
                            'nab'     => $request->custom_nab[$page][$sampel] ?? null,
                            'tanggal_sampling'     => $request->custom_tanggal_sampling[$page][$sampel] ?? null,
                            'tipe_getaran'     => $request->custom_tipe_getaran[$page][$sampel] ?? null,
                            'percepatan'     => $request->custom_percepatan[$page][$sampel] ?? null,
                            'kecepatan'     => $request->custom_kecepatan[$page][$sampel] ?? null,
                        ]);
                    }
                }
            }

            // === 7. Generate QR & File ===
            if (!$header->file_qr) {
                $file_qr = new GenerateQrDocumentLhp();
                if ($path = $file_qr->insert('LHP_Getaran', $header, $this->karyawan)) {
                    $header->file_qr = $path;
                    $header->save();
                }
            }

            $groupedByPage = collect(LhpsGetaranCustom::where('id_header', $header->id)->get())
                ->groupBy('page')
                ->toArray();
            if (in_array("Getaran (LK) TL", $request->param) || in_array("Getaran (LK) ST", $request->param)) {
                $fileName = LhpTemplate::setDataDetail(LhpsGetaranDetail::where('id_header', $header->id)->get())
                            ->setDataHeader($header)
                            ->useLampiran(true)
                            ->setDataCustom($groupedByPage)
                            ->whereView('DraftGetaranPersonal')
                            ->render();
            } else {
                $fileName = LhpTemplate::setDataDetail(LhpsGetaranDetail::where('id_header', $header->id)->get())
                            ->setDataHeader($header)
                            ->useLampiran(true)
                            ->setDataCustom($groupedByPage)
                            ->whereView('DraftGetaran')
                            ->render();
            }

         
            $header->file_lhp = $fileName;
            $header->save();

            DB::commit();
            return response()->json([
                'message' => "Data draft lhp air no sampel {$request->no_lhp} berhasil disimpan",
                'status'  => true
            ], 201);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'status'  => false,
                'getLine' => $th->getLine(),
                'getFile' => $th->getFile(),
            ], 500);
        }
    }

     public function updateTanggalLhp(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataHeader = LhpsGetaranHeader::find($request->id);

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
            $detail = LhpsGetaranDetail::where('id_header', $dataHeader->id)->get();
            if($dataHeader->sub_kategori == "Getaran (Lengan & Tangan)" || $dataHeader->sub_kategori == "Getaran (Seluruh Tubuh)"){
                $fileName = LhpTemplate::setDataDetail($detail)
                    ->setDataHeader($dataHeader)
                    ->whereView('DraftGetaranPersonal')
                    ->render();
            } else {
                $fileName = LhpTemplate::setDataDetail($detail)
                ->setDataHeader($dataHeader)
                ->whereView('DraftGetaran')
                ->render();
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
            $noSampel = explode(', ', $request->no_sampel);
            $cek_lhp = LhpsGetaranHeader::with('lhpsGetaranDetail', 'lhpsGetaranCustom')
                ->where('is_active', true)
                ->where('no_lhp', $request->cfr)
                ->first();
            if ($cek_lhp) {
                $data_entry = [];
                $data_custom = [];
                $cek_regulasi = [];

                foreach ($cek_lhp->lhpsGetaranDetail->toArray() as $key => $val) {
                    $data_entry[$key] = [
                        'id' => $val['id'],
                        'no_sampel' => $val['no_sampel'],
                        'keterangan' => $val['keterangan'],
                        'aktivitas' => $val['aktivitas'],
                        'sumber_get' => $val['sumber_get'],
                        'hasil' => $val['hasil'],
                        'w_paparan' => $val['w_paparan'],
                        'param' => $val['param'],
                        'nab' => $val['nab'],
                        'tanggal_sampling' => $val['tanggal_sampling'],
                        'tipe_getaran' => $val['tipe_getaran'],
                        'percepatan' => $val['percepatan'],
                        'kecepatan' => $val['kecepatan'],
                    ];
                }

                if (isset($request->other_regulasi) && !empty($request->other_regulasi)) {
                    $cek_regulasi = MasterRegulasi::whereIn('id', $request->other_regulasi)->select('id', 'peraturan as regulasi')->get()->toArray();
                }

                if (!empty($cek_lhp->lhpsGetaranDetail) && !empty($cek_lhp->regulasi_custom)) {
                    $regulasi_custom = json_decode($cek_lhp->regulasi_custom, true);

                    // Mapping id regulasi jika ada other_regulasi
                    if (!empty($cek_regulasi)) {
                        // Buat mapping regulasi => id
                        $mapRegulasi = collect($cek_regulasi)->pluck('id', 'regulasi')->toArray();

                        // Cari regulasi yang belum ada id-nya
                        $regulasi_custom = array_map(function ($item) use (&$mapRegulasi) {
                            $regulasi_clean = preg_replace('/\*+/', '', $item['regulasi']);
                            if (isset($mapRegulasi[$regulasi_clean])) {
                                $item['id'] = $mapRegulasi[$regulasi_clean];
                            } else {
                                // Cari id regulasi jika belum ada di mapping
                                $regulasi_db = MasterRegulasi::where('peraturan', $regulasi_clean)->first();
                                if ($regulasi_db) {
                                    $item['id'] = $regulasi_db->id;
                                    $mapRegulasi[$regulasi_clean] = $regulasi_db->id;
                                }
                            }
                            return $item;
                        }, $regulasi_custom);
                    }

                    // Group custom berdasarkan page
                    $groupedCustom = [];
                    foreach ($cek_lhp->lhpsGetaranCustom as $val) {
                        $groupedCustom[$val->page][] = $val;
                    }


                    // Isi data_custom
                    // Urutkan regulasi_custom berdasarkan page
                    usort($regulasi_custom, function ($a, $b) {
                        return $a['page'] <=> $b['page'];
                    });

                    foreach ($regulasi_custom as $item) {
                        if (empty($item['page'])) continue;
                        $id_regulasi = (string)"id_" . explode('-',$item['regulasi'])[0];
                        $page = $item['page'];
                        if (!empty($groupedCustom[$page])) {
                            foreach ($groupedCustom[$page] as $val) {
                                $data_custom[$id_regulasi][] = [
                                    'id' => $val['id'],
                                    'no_sampel' => $val['no_sampel'],
                                    'keterangan' => $val['keterangan'],
                                    'aktivitas' => $val['aktivitas'],
                                    'sumber_get' => $val['sumber_get'],
                                    'hasil' => $val['hasil'],
                                    'w_paparan' => $val['w_paparan'],
                                    'param' => $val['param'],
                                    'nab' => $val['nab'],
                                    'tanggal_sampling' => $val['tanggal_sampling'],
                                    'tipe_getaran' => $val['tipe_getaran'],
                                    'percepatan' => $val['percepatan'],
                                    'kecepatan' => $val['kecepatan'],
                                ];
                            }
                        }
                    }
                }
                        // dd($data_custom);   

                  $mainData         = [];
                $otherRegulations = [];

                $data = GetaranHeader::with('ws_udara', 'lapangan_getaran', 'master_parameter', 'lapangan_getaran_personal')
                        ->whereIn('no_sampel', $noSampel)
                        ->where('is_approve', 1)
                        ->where('is_active', true)
                        ->where('lhps', 1)
                        ->get();

                foreach ($data as $val) {
                    $entry     = $this->formatEntry($val);
                    $mainData[] = $entry;

                    if ($request->other_regulasi) {
                        foreach ($request->other_regulasi as $id_regulasi) {
                            $otherRegulations[$id_regulasi][] = $this->formatEntry($val);
                        }
                    }
                }

                // Sort mainData
                $mainData = collect($mainData)->sortBy(fn($item) => mb_strtolower($item['param']))->values()->toArray();

                // Sort otherRegulations
                foreach ($otherRegulations as $id => $regulations) {
                    $otherRegulations[$id] = collect($regulations)->sortBy(fn($item) => mb_strtolower($item['param']))->values()->toArray();
                }

                // ==============================
                // Sinkronisasi data_entry dengan mainData
                // ==============================
                $dataEntrySamples = array_column($data_entry, 'no_sampel');

                foreach ($mainData as $main) {

                    if (!in_array($main['no_sampel'], $dataEntrySamples)) {
                        $data_entry[] = array_merge($main, ['status' => 'belom_diadjust']);
                    }
                }

                // ==============================
                // Sinkronisasi data_custom dengan otherRegulations
                // ==============================
                $dataCustomSamples = [];
                // dd($data_custom, $dataCustomSamples); 
                foreach ($data_custom as $group) {
                    foreach ($group as $row) {
                        $dataCustomSamples[] = $row['no_sampel'];
                    }
                }

                foreach ($otherRegulations as $id_regulasi => $entries) {
                    foreach ($entries as $other) {
                        // dd($entries,$other, $dataCustomSamples); 
                        if (!in_array($other['no_sampel'], $dataCustomSamples)) {
                            $data_custom["id_" . $id_regulasi][] = array_merge($other, ['status' => 'belom_diadjust']);
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
                $otherRegulations = [];

                $data = GetaranHeader::with('ws_udara', 'lapangan_getaran', 'master_parameter', 'lapangan_getaran_personal')
                    ->whereIn('no_sampel', $noSampel)
                    ->where('is_approve', 1)
                    ->where('is_active', true)
                    ->where('lhps', 1)
                    ->get();
                foreach ($data as $val) {
                    $entry = $this->formatEntry($val);
                    $mainData[] = $entry;

                    if ($request->other_regulasi) {
                        foreach ($request->other_regulasi as $id_regulasi) {
                            $otherRegulations[$id_regulasi][] = $this->formatEntry($val);
                        }
                    }
                }
        
                $mainData = collect($mainData)->sortBy(function ($item) {
                    return mb_strtolower($item['param']);
                })->values()->toArray();

                foreach ($otherRegulations as $id => $regulations) {
                    $otherRegulations[$id] = collect($regulations)->sortBy(function ($item) {
                        return mb_strtolower($item['param']);
                    })->values()->toArray();
                }   
           
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
   private function formatEntry($val)
    {
        $entry = [
            'id'        => $val->id,
            'param' => $val->parameter,
        ];

        // Cek apakah getaran personal
        if (in_array($val->parameter, ["Getaran (LK) ST", "Getaran (LK) TL"])) {
            $personal = isset($val->lapangan_getaran_personal) ? $val->lapangan_getaran_personal : null;
            $wsUdara  = isset($val->ws_udara) ? $val->ws_udara : null;

            return array_merge($entry, [
                'w_paparan'    => ($personal && $personal->durasi_paparan) ? json_decode($personal->durasi_paparan, true) : null,
                'hasil'        => ($wsUdara && $wsUdara->hasil1) ? json_decode($wsUdara->hasil1, true) : null,
                'no_sampel'    => $personal ? $personal->no_sampel : null,
                'sumber_get'   => $personal ? $personal->sumber_getaran : null,
                'keterangan'   => trim(
                    (($personal && $personal->keterangan) ? $personal->keterangan : '') .
                    (($personal && !empty($personal->nama_pekerja)) ? ' (' . $personal->nama_pekerja . ')' : '')
                ),
                'nab'          => $wsUdara ? $wsUdara->nab : null,
                'tipe_getaran' => 'getaran personal',
            ]);
        }

        // Default: getaran umum
        $lapangan = isset($val->lapangan_getaran) ? $val->lapangan_getaran : null;
        $wsUdara  = isset($val->ws_udara) ? $val->ws_udara : null;
        $hasilWs  = ($wsUdara && $wsUdara->hasil1) ? json_decode($wsUdara->hasil1, true) : [];

        return array_merge($entry, [
            'no_sampel'    => $lapangan ? $lapangan->no_sampel : null,
            'keterangan'   => trim(
                (($lapangan && $lapangan->keterangan) ? $lapangan->keterangan : '') .
                (($lapangan && !empty($lapangan->nama_pekerja)) ? ' (' . $lapangan->nama_pekerja . ')' : '')
            ),
            'tipe_getaran' => 'getaran',
            'kecepatan'    => isset($hasilWs['Kecepatan']) ? $hasilWs['Kecepatan'] : null,
            'percepatan'   => isset($hasilWs['Percepatan']) ? $hasilWs['Percepatan'] : null,
        ]);
    }



      public function handleApprove(Request $request)
    {
            try {
                $data = LhpsGetaranHeader::where('id', $request->id)
                    ->where('is_active', true)
                    ->first();
                $noSampel = array_map('trim', explode(',', $request->no_sampel));
                $no_lhp = $data->no_lhp;
            
                $qr = QrDocument::where('id_document', $data->id)
                    ->where('type_document', 'LHP_GETARAN')
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
                    $data->nama_karyawan = $this->karyawan;
                    $data->jabatan_karyawan = $request->attributes->get('user')->karyawan->jabatan;
                    $data->save();
                    HistoryAppReject::insert([
                        'no_lhp' => $data->no_lhp,
                        'no_sampel' => $request->no_sampel,
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
                        $dataQr->Disahkan_Oleh = $this->karyawan;
                        $dataQr->Jabatan = $request->attributes->get('user')->karyawan->jabatan;
                        $qr->data = json_encode($dataQr);
                        $qr->save();
                    }
                }

                DB::commit();
                return response()->json([
                    'data' => $data,
                    'status' => true,
                    'message' => 'Data draft LHP air no sampel ' . $no_lhp . ' berhasil diapprove'
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

            
            $lhps = LhpsGetaranHeader::where('id', $request->id)
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
                $lhpsHistory->setTable((new LhpsGetaranHeaderHistory())->getTable());
                $lhpsHistory->created_at = $lhps->created_at;
                $lhpsHistory->updated_at = $lhps->updated_at;
                $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $lhpsHistory->deleted_by = $this->karyawan;
                $lhpsHistory->save();

                $oldDetails = LhpsGetaranDetail::where('id_header', $lhps->id)->get();
                foreach ($oldDetails as $detail) {
                    $detailHistory = $detail->replicate();
                    $detailHistory->setTable((new LhpsGetaranDetailHistory())->getTable());
                    $detailHistory->created_by = $this->karyawan;
                    $detailHistory->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $detailHistory->save();
                }

                foreach ($oldDetails as $detail) {
                    $detail->delete();
                }

                $lhps->delete();
            }
            $noSampel = array_map('trim', explode(",", $request->no_sampel));
            OrderDetail::where('cfr', $lhps->no_lhp)
                    ->whereIn('no_sampel', $noSampel)
                    ->update([
                        'status' => 1
                    ]);


            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Data draft no sample ' . $no_lhp . ' berhasil direject'
            ]);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage()
            ]);
        }
    }

    // Amang
 public function generate(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsGetaranHeader::where('no_lhp', $request->no_lhp)
                ->where('is_active', true)
                ->where('id', $request->id)
                ->first();
            if ($header != null) {
                $key = $header->no_lhp . str_replace('.', '', microtime(true));
                $gen = MD5($key);
                $gen_tahun = self::encrypt(DATE('Y-m-d'));
                $token = self::encrypt($gen . '|' . $gen_tahun);

                $cek = GenerateLink::where('fileName_pdf', $header->file_lhp)->first();
                if($cek) {
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
                        'quotation_status' => 'draft_lhp_getaran',
                        'type' => 'draft_getaran',
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
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'draft_lhp_getaran', 'type' => 'draft_getaran'])->first();
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
                 LhpsGetaranHeader::where('id', $request->id)->update([
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

    

    // Amang
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
