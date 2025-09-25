<?php

namespace App\Http\Controllers\api;
use App\Models\HistoryAppReject;


use App\Models\KonfirmasiLhp;
use App\Models\LhpsMedanLMHeader;
use App\Models\LhpsMedanLMDetail;


use App\Models\LhpsMedanLMHeaderHistory;
use App\Models\LhpsMedanLMDetailHistory;

use App\Models\MasterRegulasi;
use App\Models\MasterSubKategori;
use App\Models\OrderDetail;
use App\Models\MetodeSampling;
use App\Models\MasterBakumutu;
use App\Models\MasterKaryawan;
use App\Models\QrDocument;

use App\Models\MedanLMHeader;

use App\Models\Parameter;
use App\Models\GenerateLink;
use App\Services\SendEmail;
use App\Services\LhpTemplate;
use App\Services\GenerateQrDocumentLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DraftUlkMedanMagnetController extends Controller
{
    
    public function index()
    {
        DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
            'lhps_getaran',
            'lhps_kebisingan',
            'lhps_ling',
            'lhps_medanlm',
            'lhps_pencahayaan',
            'lhps_sinaruv',
            'orderHeader'
            => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel, GROUP_CONCAT(regulasi SEPARATOR "||") as regulasi_all')
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->where('kategori_3', "27-Udara Lingkungan Kerja")
            ->where('status', 2)
            ->groupBy('cfr')
            ->where(function ($query) {
                $query->where('parameter', 'like', '%Power Density%')
                    ->orWhere('parameter', 'like', '%Medan Magnit Statis%')
                    ->orWhere('parameter', 'like', '%Medan Listrik%');
            })->get();

            foreach ($data as $item) {
            $regsRaw = explode("||", $item->regulasi_all ?? '');
            $allRegs = [];

            foreach ($regsRaw as $reg) {
                if (empty($reg)) continue;

                // Decode JSON array misal: ["127-Peraturan...", "213-Peraturan..."]
                $decoded = json_decode($reg, true);

                if (is_array($decoded)) {
                    foreach ($decoded as $r) {
                        $allRegs[] = $r;
                    }
                }
            }

            // Hilangin duplikat berdasarkan ID
            $unique = [];
            foreach ($allRegs as $r) {
                [$id, $text] = explode("-", $r, 2);
                $unique[$id] = $r;
            }

            $item->regulasi_all = array_values($unique); // hasil array unik, rapi
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

        // Data utama
        $data = MetodeSampling::where('kategori', '4-UDARA')
            ->where('sub_kategori', strtoupper($subKategori[1]))
            ->get();

        $result = $data->toArray();

        if ($request->filled('id_lhp')) {
            $header = LhpsMedanLMHeader::find($request->id_lhp);

            if ($header) {
                $headerMetode = json_decode($header->metode_sampling, true) ?? [];

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

                $id_kategori3 = explode('-', $request->kategori_3)[0];
                $header = LhpsMedanLMHeader::where('no_lhp', $request->no_lhp)->where('no_order', $request->no_order)->where('id_kategori_3', $id_kategori3)->where('is_active', true)->first();


                if ($header == null) {
                    $header = new LhpsMedanLMHeader;
                    $header->created_by = $this->karyawan;
                    $header->created_at = DATE('Y-m-d H:i:s');
                } else {
                    $history = $header->replicate();
                    $history->setTable((new LhpsMedanLMHeaderHistory())->getTable());
                    $history->created_by = $this->karyawan;
                    $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $history->updated_by = null;
                    $history->updated_at = null;
                    $history->save();
                    $header->updated_by = $this->karyawan;
                    $header->updated_at = DATE('Y-m-d H:i:s');
                }
                $parameter = \explode(', ', $request->parameter);
                // dd($parameter);
                $keterangan = [];
                if (is_array($request->keterangan)) {
                    foreach ($request->keterangan as $key => $value) {
                        if ($value != '') {
                            array_push($keterangan, $value);
                        }
                    }
                }
                $header->no_order = ($request->no_order != '') ? $request->no_order : NULL;
                $header->no_lhp = ($request->no_lhp != '') ? $request->no_lhp : NULL;
                $header->no_sampel = ($request->noSampel != '') ? $request->noSampel : NULL;
                $header->no_qt = ($request->no_penawaran != '') ? $request->no_penawaran : NULL;
                $header->jenis_sampel = ($request->kategori_3 != '') ? explode("-", $request->kategori_3)[1] : NULL;
                $header->parameter_uji = json_encode($parameter);
                $header->kesimpulan = json_encode($request->kesimpulan);
                $header->hasil_observasi = json_encode($request->observasi);
                $header->nama_karyawan = 'Abidah Walfathiyyah';
                $header->jabatan_karyawan = 'Technical Control Supervisor';
                $header->nama_pelanggan = ($request->nama_perusahaan != '') ? $request->nama_perusahaan : NULL;
                $header->alamat_sampling = ($request->alamat_sampling != '') ? $request->alamat_sampling : NULL;
                $header->id_kategori_3 = ($id_kategori3 != '') ? $id_kategori3 : NULL;
                $header->sub_kategori = ($request->kategori_3 != '') ? explode("-", $request->kategori_3)[1] : NULL;
                $header->metode_sampling = ($request->metode_sampling != '') ? $request->metode_sampling : NULL;
                $header->keterangan = json_encode($keterangan) ?? $request->keterangan ?? NULL;
                $header->tanggal_lhp = ($request->tanggal_lhp != '') ? $request->tanggal_lhp : NULL;
                $header->tanggal_sampling = ($request->tgl_terima != '') ? $request->tgl_terima : NULL;
                $header->tanggal_sampling_text = ($request->tgl_terima_hide != '') ? $request->tgl_terima_hide : NULL;
                $header->periode_analisa = ($request->periode_analisa != '') ? $request->periode_analisa : NULL;
                $header->regulasi = ($request->regulasi != null) ? json_encode($request->regulasi) : NULL;
                if (count(array_filter($request->regulasi)) > 0) {
                    $header->id_regulasi = ($request->regulasi1 != null) ? $request->regulasi1 : NULL;
                }

                $header->save();
                $detail = LhpsMedanLMDetail::where('id_header', $header->id)->first();
                if ($detail != null) {
                    $history = $detail->replicate();
                    $history->setTable((new LhpsMedanLMDetailHistory())->getTable());
                    $history->created_by = $this->karyawan;
                    $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $history->save();
                }
                $detail = LhpsMedanLMDetail::where('id_header', $header->id)->delete();
                foreach (explode(',', $request->parameter) as $key => $val) {
                    $hasil = '';
                    $no_sampel = '';
                    $akr = '';
                    $satuan = '';
                    $attr = '';
                    $methode = '';
                    $val = trim($val, " '\"");
                    // dd($request->all());
                    if ($request->hasil_uji) {
                        $cleaned_key_hasil_uji = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil_uji));
                        $cleaned_hasil_uji = array_combine($cleaned_key_hasil_uji, array_values($request->hasil_uji));
                    }
                    $cleaned_key_no_sampel = array_map(fn($k) => trim($k, " '\""), array_keys($request->no_sampel));
                    $cleaned_no_sampel = array_combine($cleaned_key_no_sampel, array_values($request->no_sampel));
                    $cleaned_key_nama_parameter = array_map(fn($k) => trim($k, " '\""), array_keys($request->nama_parameter));
                    $cleaned_nama_parameter = array_combine($cleaned_key_nama_parameter, array_values($request->nama_parameter));
                    $cleaned_key_nab = array_map(fn($k) => trim($k, " '\""), array_keys($request->nab));
                    $cleaned_nab = array_combine($cleaned_key_nab, array_values($request->nab));
                    $cleaned_key_satuan = array_map(fn($k) => trim($k, " '\""), array_keys($request->satuan));
                    $cleaned_satuan = array_combine($cleaned_key_satuan, array_values($request->satuan));
                    $cleaned_key_hasil = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil));
                    $cleaned_hasil = array_combine($cleaned_key_hasil, array_values($request->hasil));
                    $cleaned_key_methode = array_map(fn($k) => trim($k, " '\""), array_keys($request->methode ?? []));
                    $cleaned_methode = array_combine($cleaned_key_methode, array_values($request->methode ?? []));
                    if (!empty($cleaned_hasil[$val])) {
                        $hasil = $cleaned_hasil[$val];
                    }
                    if (array_key_exists($val, $cleaned_nama_parameter)) {
                        $parame = Parameter::where('id_kategori', 4)->where('nama_lab', $val)->where('is_active', true)->first();
                        $detail = new LhpsMedanLMDetail;
                        $detail->id_header = $header->id;
                        $detail->no_sampel = $cleaned_no_sampel[$val];
                        $detail->parameter = $parame->nama_lab;
                        $detail->hasil = $hasil;
                        $detail->satuan = $cleaned_satuan[$val];
                        $detail->nab = $cleaned_nab[$val];
                        $detail->methode = (isset($cleaned_methode[$val]) ? $cleaned_methode[$val] : '');
                        $detail->save();

                    }
                }
                $details = LhpsMedanLMDetail::where('id_header', $header->id)->get();
                if ($header != null) {
                    $file_qr = new GenerateQrDocumentLhp();
                    $file_qr = $file_qr->insert('LHP_MEDAN_LM', $header, $this->karyawan);
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

    
                $fileName = LhpTemplate::setDataDetail($details)
                        ->setDataHeader($header)
                        ->whereView('DraftUlkMedanMagnet')
                        ->render();

                  
                    $header->file_lhp = $fileName;
                    $header->save();
                }

            // dd($header);
            DB::commit();
            return response()->json([
                'message' => 'Data draft LHP Lingkungan no sampel ' . $request->no_lhp . ' berhasil disimpan',
                'status' => true
            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            dd($th);
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
                'status' => false
            ], 500);
        }
    }

    
   
        public function handleDatadetail(Request $request)
    {
        try {
            $noSampel = explode(', ', $request->no_sampel);

            // Ambil data LHP jika ada
            $cek_lhp = LhpsMedanLMHeader::with('lhpsMedanLMDetail', 'lhpsMedanLMCustom')
                ->where('is_active', true)
                ->where('no_lhp', $request->cfr)
                ->first();
            
            // ==============================
            // CASE 1: Jika ada cek_lhp
            // ==============================
            if ($cek_lhp) {
                $data_entry   = [];
                $data_custom  = [];
                $cek_regulasi = [];

                // Ambil data detail dari LHP (existing entry)
                foreach ($cek_lhp->lhpsMedanLMDetail as $val) {
                    // if($val->no_sampel == 'AARG012503/024')dd($val);
                    $data_entry[] = [
                        'id'                => $val->id,
                        'no_sampel'         => $val->no_sampel,
                        'parameter'         => $val->parameter,
                        'satuan'            => $val->satuan,
                        'hasil'             => $val->hasil,
                        'nab'               => $val->nab,
                        'tanggal_sampling'  => $val->tanggal_sampling,
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
                if (!empty($cek_lhp->lhpsMedanLMDetail) && !empty($cek_lhp->regulasi_custom)) {
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
                    foreach ($cek_lhp->lhpsMedanLMCustom as $val) {
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
                                        'id'                => $val->id,
                                        'no_sampel'         => $val->no_sampel,
                                        'parameter'         => $val->parameter,
                                        'satuan'            => $val->satuan,
                                        'hasil'             => $val->hasil,
                                        'nab'               => $val->nab,
                                        'tanggal_sampling'  => $val->tanggal_sampling,
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

                $data = MedanLmHeader::with('ws_udara', 'datalapangan', 'master_parameter')
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

                return response()->json([
                    'status'    => true,
                    'data'      => $data_entry,
                    'next_page' => $data_custom,
                ], 201);
            }

            // ==============================
            // CASE 2: Jika tidak ada cek_lhp
            // ==============================
            $mainData         = [];
            $otherRegulations = [];

            $data = MedanLmHeader::with('ws_udara', 'datalapangan', 'master_parameter')
                ->whereIn('no_sampel', $noSampel)
                ->where('is_approve', 1)
                ->where('is_active', true)
                ->where('lhps', 1)
                ->get();
                
                foreach ($data as $val) {
                    $entry      = $this->formatEntry($val);
                    $mainData[] = $entry;
                
                if ($request->other_regulasi) {
                    foreach ($request->other_regulasi as $id_regulasi) {
                        $otherRegulations[$id_regulasi][] = $this->formatEntry($val);
                    }
                }
            }

            // Sort mainData
            $mainData = collect($mainData)->sortBy(fn($item) => mb_strtolower($item['parameter']))->values()->toArray();

            // Sort otherRegulations
            foreach ($otherRegulations as $id => $regulations) {
                $otherRegulations[$id] = collect($regulations)->sortBy(fn($item) => mb_strtolower($item['parameter']))->values()->toArray();
            }

            return response()->json([
                'status'    => true,
                'data'      => $mainData,
                'next_page' => $otherRegulations,
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


    private function formatEntry($val)
    {
        $entry = [
            'id'        => $val->id,
            'parameter'     => $val->parameter,
        ];
        // Cek apakah getaran personal
        if (in_array($val->parameter, ["Medan Magnit Statis", "Medan Listrik", "Power Density"])) {

            $wsUdara  = isset($val->ws_udara) ? $val->ws_udara : null;
            $hasil2 = json_decode($wsUdara->hasil1) ?? null;
            return array_merge($entry, [
                'hasil'             => $hasil2->hasil_mwatt ?? $hasil2->medan_magnet ?? $hasil2->hasil_listrik ?? $hasil2,
                'parameter'         => $val ? $val->parameter : null,
                'no_sampel'         => $val ? $val->no_sampel : null,
                'keterangan'        => $val->master_parameter->nama_regulasi ?? null,
                'satuan'            =>  $val->parameter == "Power Density"
                                        ? "mW/cm²"
                                        : ($val->parameter == "Medan Magnit Statis"
                                            ? "mT"
                                            : ($val->parameter == "Medan Listrik"
                                                ? "tesla"
                                                : "")),
                'status'            => $val->master_parameter->status ?? null,
                'nab'               => $wsUdara ? $wsUdara->nab : null
            ]);
        }
    }




    // public function handleApprove(Request $request)
    // {
    //     $category = explode('-', $request->kategori_3)[0];
    //     $sub_category = explode('-', $request->kategori_3)[1];
    //     $data_order = OrderDetail::where('no_sampel', $request->no_sampel)
    //         ->where('id', $request->id)
    //         ->where('is_active', true)
    //         ->firstOrFail();

    //         try {
    //             $data = LhpsMedanLMHeader::where('no_sampel', $request->no_sampel)
    //                 ->where('id_kategori_3', $category)
    //                 ->where('is_active', true)
    //                 ->first();
    //             // dd($data);
    //             $details = LhpsMedanLMDetail::where('id_header', $data->id)->get();
    //             $qr = QrDocument::where('id_document', $data->id)
    //                 ->where('type_document', 'LHP_LINGKUNGAN')
    //                 ->where('is_active', 1)
    //                 ->where('file', $data->file_qr)
    //                 ->orderBy('id', 'desc')
    //                 ->first();

    //             if ($data != null) {
    //                 $data_order->is_approve = 1;
    //                 $data_order->status = 3;
    //                 $data_order->approved_at = Carbon::now()->format('Y-m-d H:i:s');
    //                 $data_order->approved_by = $this->karyawan;
    //                 $data_order->save();

    //                 $data->is_approve = 1;
    //                 $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
    //                 $data->approved_by = $this->karyawan;
    //                 $data->nama_karyawan = $this->karyawan;
    //                 $data->jabatan_karyawan = $request->attributes->get('user')->karyawan->jabatan;
    //                 $data->save();

    //                 HistoryAppReject::insert([
    //                     'no_lhp' => $data_order->cfr,
    //                     'no_sampel' => $data_order->no_sampel,
    //                     'kategori_2' => $data_order->kategori_2,
    //                     'kategori_3' => $data_order->kategori_3,
    //                     'menu' => 'Draft Udara',
    //                     'status' => 'approve',
    //                     'approved_at' => Carbon::now(),
    //                     'approved_by' => $this->karyawan
    //                 ]);
    //                 if ($qr != null) {
    //                     $dataQr = json_decode($qr->data);
    //                     $dataQr->Tanggal_Pengesahan = Carbon::now()->format('Y-m-d H:i:s');
    //                     $dataQr->Disahkan_Oleh = $this->karyawan;
    //                     $dataQr->Jabatan = $request->attributes->get('user')->karyawan->jabatan;
    //                     $qr->data = json_encode($dataQr);
    //                     $qr->save();
    //                 }
    //             }
    //             return response()->json([
    //                 'data' => $data,
    //                 'status' => true,
    //                 'message' => 'Data draft LHP air no sampel ' . $request->no_lhp . ' berhasil diapprove'
    //             ], 200);
    //         } catch (\Exception $th) {
    //             return response()->json([
    //                 'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
    //                 'line' => $th->getLine(),
    //                 'status' => false
    //             ], 500);
    //         }
    // }

     public function handleApprove(Request $request, $isManual = true)
    {
        try {
            if($isManual) {
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
            $data = LhpsMedanLMHeader::where('no_lhp', $request->cfr)
                    ->where('is_active', true)
                    ->first();
            $noSampel = array_map('trim', explode(',', $request->noSampel));
            $no_lhp = $data->no_lhp;
        
            $qr = QrDocument::where('id_document', $data->id)
                ->where('type_document', 'LHP_IKLIM')
                ->where('is_active', 1)
                ->where('file', $data->file_qr)
                ->orderBy('id', 'desc')
                ->first();

            if ($data != null) {
                OrderDetail::where('cfr', $request->cfr)
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
                 if ($data->count_print < 1) {
                    $data->is_printed = 1;
                    $data->count_print = $data->count_print + 1;
                }
                // dd($data->id_kategori_2);

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
                'message' => 'Data draft Iklim no LHP ' . $no_lhp . ' berhasil diapprove'
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

                if ($data) {
                    $orderDetailParameter = json_decode($data->parameter); // array of strings
                    foreach ($orderDetailParameter as $item) {
                        $parts = explode(';', $item);
                        if (isset($parts[1])) {
                            $parsedParam[] = trim($parts[1]); // "Medan Magnit Statis"
                        }
                    }
                        $id_kategori = explode('-', $data->kategori_3);
                        $lhps = LhpsMedanLMHeader::where('no_lhp', $data->cfr)
                            ->where('no_order', $data->no_order)
                            ->where('id_kategori_3', $id_kategori[0])
                            ->where('is_active', true)
                            ->first();

                        if ($lhps) {
                            $lhpsHistory = $lhps->replicate();
                            $lhpsHistory->setTable((new LhpsMedanLMHeaderHistory())->getTable());
                            $lhpsHistory->created_at = $lhps->created_at;
                            $lhpsHistory->updated_at = $lhps->updated_at;
                            $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                            $lhpsHistory->deleted_by = $this->karyawan;
                            $lhpsHistory->save();

                            $oldDetails = LhpsMedanLMDetail::where('id_header', $lhps->id)->get();
                            foreach ($oldDetails as $detail) {
                                $detailHistory = $detail->replicate();
                                $detailHistory->setTable((new LhpsMedanLMDetailHistory())->getTable());
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
            $header = LhpsMedanLMHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            $quotation_status = "draft_lhp_medanlm";
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
                    'type' => 'draft',
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
                // dd('masuk');
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
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'draft_lhp_air', 'type' => 'draft_air'])->first();

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
                LhpsMedanLMHeader::where('id', $request->id)->update([
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
