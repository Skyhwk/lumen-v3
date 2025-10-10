<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;
use App\Models\KonfirmasiLhp;
use App\Models\LhpsLingCustom;
use App\Models\LhpsLingHeader;
use App\Models\LhpsLingDetail;

use App\Models\LhpsLingHeaderHistory;
use App\Models\LhpsLingDetailHistory;

use App\Models\MasterRegulasi;
use App\Models\MasterSubKategori;
use App\Models\OrderDetail;
use App\Models\MetodeSampling;
use App\Models\MasterBakumutu;
use App\Models\MasterKaryawan;
use App\Models\LingkunganHeader;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;
use App\Models\Subkontrak;

use App\Models\Parameter;
use App\Models\GenerateLink;
use App\Services\LhpTemplate;
use App\Services\SendEmail;
use App\Services\GenerateQrDocumentLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DraftUdaraAmbientController extends Controller
{
    // done if status = 2
    // AmanghandleDatadetail
    public function index(Request $request)
    {
        DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
            'lhps_ling','dataLapanganLingkunganHidup',
            'orderHeader' => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->where('kategori_3', "11-Udara Ambient")
            ->groupBy('cfr')
            ->where('status', 2)
            ->get();

            foreach ($data as $key => $value) {
                if(isset($value->lhps_ling) && $value->lhps_ling->methode_sampling != null ){
                    $data[$key]->lhps_ling->methode_sampling = json_decode($value->lhps_ling->methode_sampling);
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

  public function handleMetodeSampling(Request $request)
{
    try {
        $subKategori = explode('-', $request->kategori_3);

        // Data utama
        $data = MetodeSampling::where('kategori', '4-UDARA')
            ->where('sub_kategori', strtoupper($subKategori[1]))
            ->get();

        $result = $data->toArray();

        // Jika ada id_lhp, lakukan perbandingan array
        if ($request->filled('id_lhp')) {
            $header = LhpsLingHeader::find($request->id_lhp);

            if ($header) {
                $headerMetode = json_decode($header->methode_sampling, true) ?? [];

                foreach ($data as $key => $value) {
                    $valueMetode = array_map('trim', explode(',', $value->metode_sampling));

                    $missing = array_diff($headerMetode, $valueMetode);

                    if (!empty($missing)) {
                        foreach ($missing as $miss) {
                            $result[] = [
                                'id' => null,
                                'metode_sampling' => $miss,
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
            $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)
                ->where('is_active', true)
                ->first();

            if ($header) {
                // Backup ke history sebelum update
                $history = $header->replicate();
                $history->setTable((new LhpsLingHeaderHistory())->getTable());
                // $history->id = $header->id;
                $history->created_at = Carbon::now();
                $history->save();
            } else {
                $header = new LhpsLingHeader();
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
                'no_sampel'       => $request->no_sampel ?: null,
                'no_lhp'          => $request->no_lhp ?: null,
                'no_qt'           => $request->no_penawaran ?: null,
                'status_sampling' => $request->type_sampling ?: null,
                'tanggal_terima'  => $request->tanggal_terima ?: null,
                'parameter_uji'   => json_encode($parameter_uji),
                'nama_pelanggan'  => $request->nama_perusahaan ?: null,
                'alamat_sampling' => $request->alamat_sampling ?: null,
                'sub_kategori'    => $request->jenis_sampel ?: null,
                'id_kategori_2'    => 4,
                'id_kategori_3'    => 11,
                'deskripsi_titik' => $request->penamaan_titik ?: null,
                'methode_sampling'=> $request->metode_sampling ? json_encode($request->metode_sampling) : null,
                'titik_koordinat' => $request->titik_koordinat ?: null,
                'tanggal_sampling'=> $request->tanggal_terima ?: null,
                'nama_karyawan'   => $nama_perilis,
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
            $oldDetails = LhpsLingDetail::where('id_header', $header->id)->get();
            foreach ($oldDetails as $detail) {
                $detailHistory = $detail->replicate();
                $detailHistory->setTable((new LhpsLingDetailHistory())->getTable());
                // $detailHistory->id = $detail->id;
                $detailHistory->created_by = $this->karyawan;
                $detailHistory->created_at = Carbon::now();
                $detailHistory->save();
            }
            LhpsLingDetail::where('id_header', $header->id)->delete();

            foreach (($request->parameter ?? []) as $key => $val) {
                LhpsLingDetail::create([
                    'id_header'     => $header->id,
                    'akr'           => $request->akr[$key] ?? '',
                    'parameter_lab' => str_replace("'", '', $key),
                    'parameter'     => $val,
                    'hasil_uji'     => $request->hasil_uji[$key] ?? '',
                    'attr'          => $request->attr[$key] ?? '',
                    'satuan'        => $request->satuan[$key] ?? '',
                    'durasi'        => $request->durasi[$key] ?? '',
                    'methode'       => $request->methode[$key] ?? '',
                ]);
            }

            // === 6. Handle custom ===
            LhpsLingCustom::where('id_header', $header->id)->delete();

            if ($request->custom_parameter) {
                foreach ($request->custom_hasil_uji as $page => $params) {
                    foreach ($params as $param => $hasil) {
                        LhpsLingCustom::create([
                            'id_header'   => $header->id,
                            'page'        => $page,
                            'parameter_lab' => $request->custom_parameter[$page][$param] ?? '',
                            'akr'         => $request->custom_akr[$page][$param] ?? '',
                            'parameter'   =>  $request->custom_parameter_lab[$page][$param],
                            'hasil_uji'   => $hasil,
                            'attr'        => $request->custom_attr[$page][$param] ?? '',
                            'satuan'      => $request->custom_satuan[$page][$param] ?? '',
                            'durasi'      => $request->custom_durasi[$page][$param] ?? '',
                            'methode'     => $request->custom_methode[$page][$param] ?? '',
                        ]);
                    }
                }
            }

            // === 7. Generate QR & File ===
            if (!$header->file_qr) {
                $file_qr = new GenerateQrDocumentLhp();
                if ($path = $file_qr->insert('LHP_LINGKUNGAN_HIDUP', $header, $this->karyawan)) {
                    $header->file_qr = $path;
                    $header->save();
                }
            }

            $groupedByPage = collect(LhpsLingCustom::where('id_header', $header->id)->get())
                ->groupBy('page')
                ->toArray();

            $fileName = LhpTemplate::setDataDetail(LhpsLingDetail::where('id_header', $header->id)->get())
                ->setDataHeader($header)
                ->setDataCustom($groupedByPage)
                ->useLampiran(true)
                ->whereView('DraftUdaraAmbient')
                ->render();

            $header->file_lhp = $fileName;
            if ($header->is_revisi == 1) {
                $header->is_revisi = 0;
                $header->is_generated = 0;
                $header->count_revisi++;
                if ($header->count_revisi > 2) {
                    $this->handleApprove($request);
                }
            }
            $header->save();

            DB::commit();
            return response()->json([
                'message' => "Data draft lhp air no sampel {$request->no_sampel} berhasil disimpan",
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
            $dataHeader = LhpsLingHeader::find($request->id);

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
            $detail = LhpsLingDetail::where('id_header', $dataHeader->id)->get();
            $custom = LhpsLingCustom::where('id_header', $dataHeader->id)->get();

            $groupedByPage = [];
            foreach ($custom as $item) {
                $page = $item->page;
                $groupedByPage[$page][] = $item->toArray();
            }

            $fileName = LhpTemplate::setDataDetail($detail)
                ->setDataHeader($dataHeader)
                ->setDataCustom($groupedByPage)
                ->whereView('DraftUdaraAmbient')
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


    public function handleDatadetail2(Request $request)
    {
        try {
            $cek_lhp = LhpsLingHeader::with('lhpsLingDetail', 'lhpsLingCustom')->where('no_sampel', $request->no_sampel)->first();
            if ($cek_lhp) {
                $data_entry = array();
                $data_custom = array();
                $cek_regulasi = array();

                foreach ($cek_lhp->lhpsLingDetail->toArray() as $key => $val) {
                    $data_entry[$key] = [
                        'id' => $val['id'],
                        'parameter_lab' => $val['parameter_lab'],
                        'no_sampel' => $request->no_sampel,
                        'akr' => $val['akr'],
                        'parameter' => $val['parameter'],
                        'satuan' => $val['satuan'],
                        'hasil_uji' => $val['hasil_uji'],
                        'methode' => $val['methode'],
                        'durasi' => $val['durasi'],
                        'status' => $val['akr'] == 'ẍ' ? "BELUM AKREDITASI" : "AKREDITASI"
                    ];
                }

                if (isset($request->other_regulasi) && !empty($request->other_regulasi)) {
                    $cek_regulasi = MasterRegulasi::whereIn('id', $request->other_regulasi)->select('id', 'peraturan as regulasi')->get()->toArray();
                }

                if (!empty($cek_lhp->lhpsLingCustom) && !empty($cek_lhp->regulasi_custom)) {
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
                    foreach ($cek_lhp->lhpsLingCustom as $val) {
                        $groupedCustom[$val->page][] = $val;
                    }

                    // Isi data_custom
                    // Urutkan regulasi_custom berdasarkan page
                    usort($regulasi_custom, function ($a, $b) {
                        return $a['page'] <=> $b['page'];
                    });

                    foreach ($regulasi_custom as $item) {
                        if (empty($item['id']) || empty($item['page'])) continue;
                        $id_regulasi = (string)"id_" . $item['id'];
                        $page = $item['page'];

                        if (!empty($groupedCustom[$page])) {
                            foreach ($groupedCustom[$page] as $val) {
                                $data_custom[$id_regulasi][] = [
                                    'id' => $val['id'],
                                    'parameter_lab' => $val['parameter_lab'],
                                    'no_sampel' => $request->no_sampel,
                                    'akr' => $val['akr'],
                                    'parameter' => $val['parameter'],
                                    'satuan' => $val['satuan'],
                                    'hasil_uji' => $val['hasil_uji'],
                                    'methode' => $val['methode'],
                                    'durasi' => $val['durasi'],
                                    'status' => $val['akr'] == 'ẍ' ? "BELUM AKREDITASI" : "AKREDITASI"
                                ];
                            }
                        }
                    }
                }


                $defaultMethods = Parameter::where('is_active', true)
                    ->where('id_kategori', 4)
                    ->whereNotNull('method')
                    ->pluck('method')
                    ->unique()
                    ->values()
                    ->toArray();

                return response()->json([
                    'status' => true,
                    'data' => $data_entry,
                    'next_page' => $data_custom,
                    'spesifikasi_method' => $defaultMethods,
                  
                ], 201);
            } else {

                $mainData = [];
                $methodsUsed = [];
                $otherRegulations = [];

                $models = [
                    Subkontrak::class,
                    LingkunganHeader::class,
                ];

                foreach ($models as $model) {
                    $approveField = $model === Subkontrak::class ? 'is_approve' : 'is_approved';
                    $data = $model::with('ws_value_linkungan', 'parameter_udara')
                        ->where('no_sampel', $request->no_sampel)
                        ->where($approveField, 1)
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
                $methodsUsed = array_values(array_unique($methodsUsed));
                $defaultMethods = Parameter::where('is_active', true)->where('id_kategori', 4)
                    ->whereNotNull('method')->groupBy('method')
                    ->pluck('method')->toArray();

                $resultMethods = array_values(array_unique(array_merge($methodsUsed, $defaultMethods)));

                return response()->json([
                    'status' => true,
                    'data' => $mainData,
                    'next_page' => $otherRegulations,
                    'spesifikasi_method' => $resultMethods,
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
         public function handleDatadetail(Request $request)
    {
        try {
            $noSampel = explode(', ', $request->no_sampel);

            // Ambil data LHP jika ada
            $cek_lhp = LhpsLingHeader::with('lhpsLingDetail', 'lhpsLingCustom')
                ->where('no_sampel', $noSampel)
                ->first();
            
            // ==============================
            // CASE 1: Jika ada cek_lhp
            // ==============================
            if ($cek_lhp) {
                $data_entry   = [];
                $data_custom  = [];
                $cek_regulasi = [];

                // Ambil data detail dari LHP (existing entry)
                foreach ($cek_lhp->lhpsLingDetail as $val) {
                    // if($val->no_sampel == 'AARG012503/024')dd($val);
                    $data_entry[] = [
                        'id' => $val->id,
                        'parameter_lab' => $val->parameter_lab,
                        'no_sampel' => $request->no_sampel,
                        'akr' => $val->akr,
                        'parameter' => $val->parameter,
                        'satuan' => $val->satuan,
                        'hasil_uji' => $val->hasil_uji,
                        'methode' => $val->methode,
                        'durasi' => $val->durasi,
                        'status' => $val->akr == 'ẍ' ? "BELUM AKREDITASI" : "AKREDITASI"
                    ];
                }

                // Ambil regulasi tambahan jika ada
                if ($request->other_regulasi) {
                    $cek_regulasi = MasterRegulasi::whereIn('id', $request->other_regulasi)
                        ->select('id', 'peraturan as regulasi')
                        ->get()
                        ->toArray();
                }

                // Proses regulasi custom dari LHP
                if (!empty($cek_lhp->lhpsLingDetail) && !empty($cek_lhp->regulasi_custom)) {
                    $regulasi_custom = json_decode($cek_lhp->regulasi_custom, true);

                    // Mapping regulasi id
                    if (!empty($cek_regulasi)) {
                        $mapRegulasi = collect($cek_regulasi)->pluck('id', 'regulasi')->toArray();

                        $regulasi_custom = array_map(function ($item) use (&$mapRegulasi) {
                            $regulasi_clean = preg_replace('/\*+/', '', $item['regulasi']);
                            if (isset($mapRegulasi[$regulasi_clean])) {
                                $item['id'] = $mapRegulasi[$regulasi_clean];
                            } else {
                                $db = MasterRegulasi::where('peraturan', $regulasi_clean)->first();
                                if ($db) {
                                    $item['id'] = $db->id;
                                    $mapRegulasi[$regulasi_clean] = $db->id;
                                }
                            }
                            return $item;
                        }, $regulasi_custom);
                    }

                    // Group custom by page
                    $groupedCustom = [];
                    foreach ($cek_lhp->lhpsLingCustom as $val) {
                        $groupedCustom[$val->page][] = $val;
                    }

                    // Urutkan regulasi_custom berdasarkan page
                    usort($regulasi_custom, fn($a, $b) => $a['page'] <=> $b['page']);

                    // Bentuk data_custom
                    foreach ($regulasi_custom as $item) {
                        if (empty($item['page'])) continue;
                        // $id_regulasi = "id_" . $item['id'];
                            $id_regulasi = (string)"id_" . explode('-',$item['regulasi'])[0];
                            $page        = $item['page'];

                            if (!empty($groupedCustom[$page])) {
                                foreach ($groupedCustom[$page] as $val) {
                                    $data_custom[$id_regulasi][] = [
                                        'id' => $val->id,
                                        'parameter_lab' => $val->parameter_lab,
                                        'no_sampel' => $request->no_sampel,
                                        'akr' => $val->akr,
                                        'parameter' => $val->parameter,
                                        'satuan' => $val->satuan,
                                        'hasil_uji' => $val->hasil_uji,
                                        'methode' => $val->methode,
                                        'durasi' => $val->durasi,
                                        'status' => $val->akr == 'ẍ' ? "BELUM AKREDITASI" : "AKREDITASI"
                                    ];
                                }
                            }
                        }
                    }

                // ==============================
                // Ambil mainData & otherRegulations
                // ==============================
                $mainData         = [];
                $otherRegulations = [];
                $methodsUsed = [];
              
                $models = [
                    Subkontrak::class,
                    LingkunganHeader::class,
                ];

                foreach ($models as $model) {
                    $approveField = $model === Subkontrak::class ? 'is_approve' : 'is_approved';
                    $data = $model::with('ws_value_linkungan', 'parameter_udara')
                        ->where('no_sampel', $request->no_sampel)
                        ->where($approveField, 1)
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

                // Sort mainData
                $mainData = collect($mainData)->sortBy(fn($item) => mb_strtolower($item['parameter']))->values()->toArray();
          
                // Sort otherRegulations
                foreach ($otherRegulations as $id => $regulations) {
                    $otherRegulations[$id] = collect($regulations)->sortBy(fn($item) => mb_strtolower($item['parameter']))->values()->toArray();
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
                foreach ($data_custom as $group) {
                    foreach ($group as $row) {
                        $dataCustomSamples[] = $row['no_sampel'];
                    }
                }

                foreach ($otherRegulations as $id_regulasi => $entries) {
                    foreach ($entries as $other) {
                        if (!in_array($other['no_sampel'], $dataCustomSamples)) {
                            $data_custom["id_" . $id_regulasi][] = array_merge($other, ['status' => 'belom_diadjust']);
                        }
                    }
                }
                 $defaultMethods = Parameter::where('is_active', true)
                    ->where('id_kategori', 4)
                    ->whereNotNull('method')
                    ->pluck('method')
                    ->unique()
                    ->values()
                    ->toArray();

              

                return response()->json([
                    'status'    => true,
                    'data'      => $data_entry,
                    'next_page' => $data_custom,
                    'spesifikasi_method' => $defaultMethods,
                ], 201);
            }

            // ==============================
            // CASE 2: Jika tidak ada cek_lhp
            // ==============================
            $mainData         = [];
            $otherRegulations = [];
            $methodsUsed = [];

              $models = [
                    Subkontrak::class,
                    LingkunganHeader::class,
                ];

                foreach ($models as $model) {
                    $approveField = $model === Subkontrak::class ? 'is_approve' : 'is_approved';
                    $data = $model::with('ws_value_linkungan', 'parameter_udara')
                        ->where('no_sampel', $request->no_sampel)
                        ->where($approveField, 1)
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

            // Sort mainData
            $mainData = collect($mainData)->sortBy(fn($item) => mb_strtolower($item['parameter']))->values()->toArray();

            // Sort otherRegulations
            foreach ($otherRegulations as $id => $regulations) {
                $otherRegulations[$id] = collect($regulations)->sortBy(fn($item) => mb_strtolower($item['parameter']))->values()->toArray();
            }
              $methodsUsed = array_values(array_unique($methodsUsed));
                $defaultMethods = Parameter::where('is_active', true)->where('id_kategori', 4)
                    ->whereNotNull('method')->groupBy('method')
                    ->pluck('method')->toArray();

                $resultMethods = array_values(array_unique(array_merge($methodsUsed, $defaultMethods)));

            return response()->json([
                'status'    => true,
                'data'      => $mainData,
                'next_page' => $otherRegulations,
                'spesifikasi_method' => $resultMethods,
            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ], 500);
        }
    }

  private function formatEntry($val, $regulasiId, &$methodsUsed = [])
    {
        $param = $val->parameter_udara;
         $bakumutu = MasterBakumutu::where('id_regulasi', $regulasiId)
            ->where('parameter', $val->parameter)
            ->first();
        $entry = [
            'id' => $val->id,
            'parameter_lab' => $val->parameter,
            'no_sampel' => $val->no_sampel,
            'akr' => str_contains($bakumutu->akreditasi, 'akreditasi') ? 'ẍ' : '',
            'parameter' => $param->nama_regulasi,
            'satuan' => $param->satuan,
            'hasil_uji' => $val->ws_value_linkungan->C	 ?? null,
            'durasi' => $val->ws_value_linkungan->durasi ?? null,
            'methode' => $param->method,
            'status' => $param->status
        ];

        if ($bakumutu && $bakumutu->method) {
            $entry['satuan'] = $bakumutu->satuan;
            $entry['methode'] = $bakumutu->method;
            $entry['baku_mutu'][0] = $bakumutu->baku_mutu;
            $methodsUsed[] = $bakumutu->method;
        }

        return $entry;
    }


 
  

   public function handleApprove(Request $request, $isManual = true)
    {
        try {
            if($isManual) {
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
            $data = LhpsLingHeader::where('no_lhp', $request->no_lhp)
                    ->where('is_active', true)
                    ->first();
            $noSampel = array_map('trim', explode(',', $request->noSampel));
        
            $qr = QrDocument::where('id_document', $data->id)
                ->where('type_document', 'LHP_AMBIENT')
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

                
                $data->is_approved = 1;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->approved_by = $this->karyawan;
                 if ($data->count_print < 1) {
                    $data->is_printed = 1;
                    $data->count_print = $data->count_print + 1;
                }
                // dd($data->id_kategori_2);

                $data->save();
                HistoryAppReject::insert([
                    'no_lhp' => $request->no_lhp,
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
                'message' => 'Data draft Udara Ambient no LHP ' . $request->no_lhp . ' berhasil diapprove'
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
            $noSampel = array_map('trim', explode(',', $request->noSampel));
            $data = OrderDetail::where('cfr', $request->no_lhp)
                ->whereIn('no_sampel', $request->no_sampel)
                ->first();

                if ($data) {
                    $orderDetailParameter = json_decode($data->parameter); // array of strings
                    foreach ($orderDetailParameter as $item) {
                        $parts = explode(';', $item);
                        if (isset($parts[1])) {
                            $parsedParam[] = trim($parts[1]); // "Medan Magnit Statis"
                        }
                    }
                        $id_kategori = explode('-', $data->kategori_3);
                        $lhps = LhpsLingHeader::where('no_lhp', $data->cfr)
                            ->where('no_order', $data->no_order)
                            ->where('id_kategori_3', $id_kategori[0])
                            ->where('is_active', true)
                            ->first();

                        if ($lhps) {
                            $lhpsHistory = $lhps->replicate();
                            $lhpsHistory->setTable((new LhpsLingHeaderHistory())->getTable());
                            $lhpsHistory->created_at = $lhps->created_at;
                            $lhpsHistory->updated_at = $lhps->updated_at;
                            $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                            $lhpsHistory->deleted_by = $this->karyawan;
                            $lhpsHistory->save();

                            $oldDetails = LhpsLingDetail::where('id_header', $lhps->id)->get();
                            foreach ($oldDetails as $detail) {
                                $detailHistory = $detail->replicate();
                                $detailHistory->setTable((new LhpsLingDetailHistory())->getTable());
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

            $data->status = 1;
            $data->save();
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Data draft Udara Ambient no LHP ' . $data->no_sampel . ' berhasil direject'
            ]);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage(),
                'line' => $th->getLine(),
                'getFile' => $th->getFile()
            ]);
        }
    }

    // Amang
    public function generate(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsLingHeader::where('no_lhp', $request->no_lhp)
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
                        'quotation_status' => 'draft_ambient',
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

    // Amang
 

    // Amang
    public function getLink(Request $request)
    {
        try {
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'draft_ambient', 'type' => 'draft'])->first();
            if (!$link) {
                return response()->json(['message' => 'Link not found'], 404);
            }
            return response()->json(['link' => env('PORTALV3_LINK') . $link->token], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
    public function getUser(Request $request)
    {
        $users = MasterKaryawan::with(['department', 'jabatan'])->where('id', $request->id ?: $this->user_id)->first();

        return response()->json($users);
    }
    // Amang
   public function handleRevisi(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsLingHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();

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
                'line' => $th->getLine(),
                'getFile' => $th->getFile(),

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
