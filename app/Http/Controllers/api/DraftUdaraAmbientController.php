<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;
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
            $data = MetodeSampling::where('kategori', '4-UDARA')
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
                $detailHistory->id = $detail->id;
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


    public function handleDatadetail(Request $request)
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
  private function formatEntry($val, $regulasiId, &$methodsUsed = [])
    {
        $param = $val->parameter_udara;
        $entry = [
            'id' => $val->id,
            'parameter_lab' => $val->parameter,
            'no_sampel' => $val->no_sampel,
            'akr' => $param->status === "AKREDITASI" ? '' : 'ẍ',
            'parameter' => $param->nama_regulasi,
            'satuan' => $param->satuan,
            'hasil_uji' => $val->ws_value_linkungan->C	 ?? null,
            'durasi' => $val->ws_value_linkungan->durasi ?? null,
            'methode' => $param->method,
            'status' => $param->status
        ];

        $bakumutu = MasterBakumutu::where('id_regulasi', $regulasiId)
            ->where('parameter', $val->parameter)
            ->first();

        if ($bakumutu && $bakumutu->method) {
            $entry['satuan'] = $bakumutu->satuan;
            $entry['methode'] = $bakumutu->method;
            $entry['baku_mutu'][0] = $bakumutu->baku_mutu;
            $methodsUsed[] = $bakumutu->method;
        }

        return $entry;
    }


 
    public function handleApprove2(Request $request)
    {

        $category = explode('-', $request->kategori_3)[0];
        $data_order = OrderDetail::where('no_sampel', $request->no_lhp)
            ->where('id', $request->id)
            ->where('is_active', true)
            ->firstOrFail();

            try {
                $data = LhpsLingHeader::where('no_lhp', $request->no_lhp)
                    ->where('id_kategori_3', $category)
                    ->where('is_active', true)
                    ->first();
                // dd($data);
                $details = LhpsLingDetail::where('id_header', $data->id)->get();
                $qr = QrDocument::where('id_document', $data->id)
                    ->where('type_document', 'LHP_LINGKUNGAN')
                    ->where('is_active', 1)
                    ->where('file', $data->file_qr)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($data != null) {
                    $data_order->is_approve = 1;
                    $data_order->status = 3;
                    $data_order->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data_order->approved_by = $this->karyawan;
                    $data_order->save();

                    $data->is_approve = 1;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->approved_by = $this->karyawan;
                    $data->nama_karyawan = $this->karyawan;
                    $data->jabatan_karyawan = $request->attributes->get('user')->karyawan->jabatan;
                    $data->save();

                    HistoryAppReject::insert([
                        'no_lhp' => $data_order->cfr,
                        'no_sampel' => $data_order->no_sampel,
                        'kategori_2' => $data_order->kategori_2,
                        'kategori_3' => $data_order->kategori_3,
                        'menu' => 'Draft Udara',
                        'status' => 'approve',
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
                return response()->json([
                    'data' => $data,
                    'status' => true,
                    'message' => 'Data draft LHP air no sampel ' . $request->no_lhp . ' berhasil diapprove'
                ], 200);
            } catch (\Exception $th) {
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'line' => $th->getLine(),
                    'getFile' => $th->getFile(),
                    'status' => false
                ], 500);
            }
    }

    public function handleApprove(Request $request)
        {
            try {
                $data = LhpsLingHeader::where('id', $request->id)
                    ->where('is_active', true)
                    ->first();
                $noSampel = array_map('trim', explode(',', $request->no_sampel));
                $no_lhp = $data->no_lhp;
            
                $qr = QrDocument::where('id_document', $data->id)
                    ->where('type_document', 'LHP_LINGKUNGAN')
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
                
                    $data->is_approved = 1;
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
                    'message' => 'Data draft LHP Iklim dengan no LHP ' . $no_lhp . ' berhasil diapprove'
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
  
            $data = OrderDetail::where('id', $request->id)->first();

            $kategori3 = $data->kategori_3;
            $category = (int) explode('-', $kategori3)[0];


                $orderDetail = OrderDetail::where('id', $request->id)
                    ->where('is_active', true)
                    ->where('kategori_3', 'LIKE', "%{$category}%")
                    ->first();

                if ($orderDetail) {
                    $orderDetailParameter = json_decode($orderDetail->parameter); // array of strings

                    foreach ($orderDetailParameter as $item) {
                        // Pecah berdasarkan tanda ';'
                        $parts = explode(';', $item);
                        // Ambil bagian ke-1 (index 1) jika ada
                        if (isset($parts[1])) {
                            $parsedParam[] = trim($parts[1]); // "Medan Magnit Statis"
                        }
                    }

                        $lhps = LhpsLingHeader::where('no_sampel', $data->no_sampel)
                            ->where('no_order', $data->no_order)
                            ->where('id_kategori_3', $category)
                            ->where('is_active', true)
                            ->first();

                        if ($lhps) {
                            // History Header Lingkungan Kerja
                            $lhpsHistory = $lhps->replicate();
                            $lhpsHistory->setTable((new LhpsLingHeaderHistory())->getTable());
                            $lhpsHistory->created_at = $lhps->created_at;
                            $lhpsHistory->updated_at = $lhps->updated_at;
                            $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                            $lhpsHistory->deleted_by = $this->karyawan;
                            $lhpsHistory->save();

                            // History Detail Lingkungan Kerja
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
                'message' => 'Data draft no sample ' . $data->no_sampel . ' berhasil direject'
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
            $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            $quotation_status = "draft_lhp_lingkungan";
            if ($header != null) {
                $key = $header->no_sampel . str_replace('.', '', microtime(true));
                $gen = MD5($key);
                $gen_tahun = self::encrypt(DATE('Y-m-d'));
                $token = self::encrypt($gen . '|' . $gen_tahun);

                $insertData = [
                    'token' => $token,
                    'key' => $gen,
                    'id_quotation' => $header->id,
                    'quotation_status' => $quotation_status,
                    'type' => 'draft_ling_hidup',
                    'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                    'fileName_pdf' => $header->file_lhp,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s')
                ];

                $insert = GenerateLink::insertGetId($insertData);

                $header->id_token = $insert;
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
                'getFile' => $th->getFile(),

                'status' => false
            ], 500);
        }
    }

    // Amang
 

    // Amang
    public function getLink(Request $request)
    {
        try {
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'draft_lhp_lingkungan', 'type' => 'draft_ling_hidup'])->first();
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
 public function sendEmail(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id != '' || isset($request->id)) {
                 LhpsLingHeader::where('id', $request->id)->update([
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
