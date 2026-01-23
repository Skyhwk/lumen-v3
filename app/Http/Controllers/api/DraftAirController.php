<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;
use App\Models\LhpsAirHeader;
use App\Models\QrDocument;
use App\Models\LhpsAirDetail;
use App\Models\LhpsAirCustom;
use App\Models\OrderHeader;
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
use App\Models\DataLapanganAir;
use App\Models\LhpsAirHeaderHistory;
use App\Models\LhpsAirDetailHistory;
use App\Models\PengesahanLhp;
use App\Models\KonfirmasiLhp;

use App\Helpers\EmailLhpRilisHelpers;

use App\Services\TemplateLhps;
use App\Services\SendEmail;
use App\Services\LhpTemplate;
use App\Services\GenerateQrDocumentLhp;
use App\Services\PrintLhp;

use App\Jobs\RenderLhp;
use App\Jobs\CombineLHPJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\LinkLhp;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DraftAirController extends Controller
{
    public function index(Request $request)
    {
        $data = OrderDetail::with('lhps_air', 'orderHeader', 'dataLapanganAir', 'sampleDiantar')
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
        // dd($request->hasil_uji_json, $request->custom_hasil_uji_json);
        DB::beginTransaction();
        try {
            // === 1. Ambil header / buat baru ===
            $header = LhpsAirHeader::where('no_sampel', $request->no_sampel)
                ->where('is_active', true)
                ->first();

            if ($header) {
                // Backup ke history sebelum update
                $history = $header->replicate();
                $history->setTable((new LhpsAirHeaderHistory())->getTable());
                $history->id = $header->id;
                $history->created_at = Carbon::now();
                $history->save();
            } else {
                $header = new LhpsAirHeader();
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
            $parameter_uji = !empty($request->parameter) ? explode(', ', $request->parameter) : [];
            $keterangan    = array_values(array_filter($request->keterangan ?? []));
            $table_header  = array_slice(array_filter($request->name_header_bakumutu ?? []), 0, 4);

            $regulasi_custom = collect($request->regulasi_custom ?? [])->map(function ($item, $page) {
                return ['page' => (int)$page, 'regulasi' => $item];
            })->values()->toArray();

            // === 4. Simpan / update header ===
            $header->fill([
                'no_order'        => $request->no_order ?: null,
                'no_sampel'       => $request->no_sampel ?: null,
                'no_lhp'          => $request->no_lhp ?: null,
                'no_quotation'    => $request->no_penawaran ?: null,
                'status_sampling' => $request->type_sampling ?: null,
                'tanggal_terima'  => $request->tanggal_terima ?: null,
                'parameter_uji'   => json_encode($parameter_uji),
                'nama_pelanggan'  => $request->nama_perusahaan ?: null,
                'alamat_sampling' => $request->alamat_sampling ?: null,
                'sub_kategori'    => $request->jenis_sampel ?: null,
                'deskripsi_titik' => $request->penamaan_titik ?: null,
                'methode_sampling' => $request->metode_sampling ? json_encode($request->metode_sampling) : null,
                // 'methode_sampling' => $request->metode_sampling ? json_encode(
                //     array_map(function($item) {
                //         if (strpos($item, 'custom;') === 0) {
                //             $parts = explode(';', $item, 2);
                //             return isset($parts[1]) ? $parts[1] : '';
                //         }
                //         return $item;
                //     }, $request->metode_sampling)
                // ) : null,
                'titik_koordinat' => $request->titik_koordinat ?: null,
                'tanggal_sampling' => $request->tanggal_terima ?: null,
                'periode_analisa' => $request->periode_analisa ?: null,
                'nama_karyawan'   => $nama_perilis,
                'jabatan_karyawan' => $jabatan_perilis,
                'regulasi'        => $request->regulasi ? json_encode($request->regulasi) : null,
                'regulasi_custom' => $regulasi_custom ? json_encode($regulasi_custom) : null,
                'keterangan'      => $keterangan ? json_encode($keterangan) : null,
                'header_table'    => $table_header ? json_encode($table_header) : null,
                'suhu_air'        => $request->suhu_air ?: null,
                'suhu_udara'      => $request->suhu_udara ?: null,
                'ph'              => $request->ph ?: null,
                'dhl'             => $request->dhl ?: null,
                'do'              => $request->do ?: null,
                'bau'             => $request->bau ?: null,
                'warna'           => $request->warna ?: null,
                'tanggal_lhp'     => $request->tanggal_lhp ?: null,
                'created_by'      => $this->karyawan,
                'created_at'      => Carbon::now(),
            ]);
            $header->save();

            // === 5. Backup & replace detail ===
            $oldDetails = LhpsAirDetail::where('id_header', $header->id)->get();
            foreach ($oldDetails as $detail) {
                $detailHistory = $detail->replicate();
                $detailHistory->setTable((new LhpsAirDetailHistory())->getTable());
                $detailHistory->id = $detail->id;
                $detailHistory->created_by = $this->karyawan;
                $detailHistory->created_at = Carbon::now();
                $detailHistory->save();
            }
            LhpsAirDetail::where('id_header', $header->id)->delete();

            foreach (($request->nama_parameter ?? []) as $key => $val) {
                $baku_mutu = [];
                if (isset($request->baku_mutu[$key]) && is_array($request->baku_mutu[$key])) {
                    $baku_mutu = array_slice($request->baku_mutu[$key], 0, count($table_header));
                }
                LhpsAirDetail::create([
                    'id_header'     => $header->id,
                    'akr'           => $request->akr[$key] ?? '',
                    'parameter_lab' => str_replace("'", '', $key),
                    'parameter'     => $val,
                    'hasil_uji'     => $request->hasil_uji[$key] ?? '',
                    'hasil_uji_json' => isset($request->hasil_uji_json[$key]) ? $request->hasil_uji_json[$key] : null,
                    'attr'          => $request->attr[$key] ?? '',
                    'satuan'        => $request->satuan[$key] ?? '',
                    'methode'       => $request->methode[$key] ?? '',
                    'baku_mutu'     => json_encode($baku_mutu),
                    'metode_sampling' => isset($request->metode_sampling_biota[$key])
                        ? $request->metode_sampling_biota[$key]
                        : null,
                    'kesimpulan' => $request->kesimpulan_biota[$key] ?? null
                ]);
            }
            // dd('--------------------');
            // === 6. Handle custom ===
            LhpsAirCustom::where('id_header', $header->id)->delete();

            if ($request->custom_parameter) {
                foreach ($request->custom_parameter as $page => $params) {
                    foreach ($params as $param => $hasil) {
                        // if($param === 'Benthos'){
                        //     dd($request->custom_hasil_uji_json[$page][$param]);
                        // }
                        LhpsAirCustom::create([
                            'id_header'   => $header->id,
                            'page'        => $page,
                            'parameter_lab' => $request->custom_parameter[$page][$param] ?? '',
                            'akr'         => $request->custom_akr[$page][$param] ?? '',
                            'parameter'   => str_replace("'", '', htmlspecialchars_decode($param, ENT_QUOTES)),
                            'hasil_uji'   => $request->custom_hasil_uji[$page][$param] ?? '',
                            'hasil_uji_json' => isset($request->custom_hasil_uji_json[$page][$param]) ? $request->custom_hasil_uji_json[$page][$param] : null,
                            'attr'        => $request->custom_attr[$page][$param] ?? '',
                            'satuan'      => $request->custom_satuan[$page][$param] ?? '',
                            'methode'     => $request->custom_methode[$page][$param] ?? '',
                            'baku_mutu'   => json_encode($request->custom_baku_mutu[$page][$param] ?? []),
                            'metode_sampling' => $request->metode_sampling_biota_custom[$page][$param] ?? '',
                            'kesimpulan' => $request->kesimpulan_biota_custom[$page][$param] ?? null
                        ]);
                    }
                }
            }

            // === 7. Generate QR & File ===
            if (!$header->file_qr) {
                $file_qr = new GenerateQrDocumentLhp();
                if ($path = $file_qr->insert('LHP_AIR', $header, $this->karyawan)) {
                    $header->file_qr = $path;
                    $header->save();
                }
            }
            $groupedByPage = collect(LhpsAirCustom::where('id_header', $header->id)->get())
                ->groupBy('page')
                ->toArray();

            // dd($groupedByPage, LhpsAirDetail::where('id_header', $header->id)->get());
            $fileName = LhpTemplate::setDataDetail(LhpsAirDetail::where('id_header', $header->id)->get())
                ->setDataHeader($header)
                ->setDataCustom($groupedByPage)
                ->whereView('DraftAir')
                ->render('downloadLHPFinal');

                // dd($fileName);
            $header->file_lhp = $fileName;

            // if ($header->is_revisi == 1) {
            //     $header->is_revisi = 0;
            //     $header->is_generated = 0;
            //     $header->count_revisi++;
            //     if ($header->count_revisi > 2) {
            //         $this->handleApprove($request, false);
            //     }
            // }
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
                'line'    => $th->getLine()
            ], 500);
        }
    }

    public function updateTanggalLhp(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataHeader = LhpsAirHeader::find($request->id);

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
            $detail = LhpsAirDetail::where('id_header', $dataHeader->id)->get();
            $custom = LhpsAirCustom::where('id_header', $dataHeader->id)->get();

            $groupedByPage = [];
            foreach ($custom as $item) {
                $page = $item->page;
                $groupedByPage[$page][] = $item->toArray();
            }

            $fileName = LhpTemplate::setDataDetail($detail)
                ->setDataHeader($dataHeader)
                ->setDataCustom($groupedByPage)
                ->whereView('DraftAir')
                ->render('downloadLHPFinal');

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
            $cek_lhp = LhpsAirHeader::with('lhpsAirDetail', 'lhpsAirCustom')->where('no_sampel', $request->no_sampel)->first();
            // dd($cek_lhp->lhpsAirCustom);
            if ($cek_lhp) {
                $data_entry = array();
                $data_custom = array();
                $cek_regulasi = array();

                foreach ($cek_lhp->lhpsAirDetail->toArray() as $key => $val) {
                    $data_entry[$key] = [
                        'id' => $val['id'],
                        'name' => $val['parameter_lab'],
                        'no_sampel' => $request->no_sampel,
                        'akr' => $val['akr'],
                        'keterangan' => $val['parameter'],
                        'satuan' => $val['satuan'],
                        'hasil' => $val['hasil_uji'],
                        'hasil_json' => $val['hasil_uji_json'] ? json_decode($val['hasil_uji_json'], true) : null,
                        'methode' => $val['methode'],
                        'baku_mutu' => json_decode($val['baku_mutu']),
                        'status' => $val['akr'] == 'ẍ' ? "BELUM AKREDITASI" : "AKREDITASI"
                    ];
                }

                if (isset($request->other_regulasi) && !empty($request->other_regulasi)) {
                    $cek_regulasi = MasterRegulasi::whereIn('id', $request->other_regulasi)->select('id', 'peraturan as regulasi')->get()->toArray();
                }

                if (!empty($cek_lhp->lhpsAirCustom) && !empty($cek_lhp->regulasi_custom)) {
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
                    foreach ($cek_lhp->lhpsAirCustom as $val) {
                        $groupedCustom[$val->page][] = $val;
                    }
                    // dd('-----------------------------');
                    // Isi data_custom
                    // Urutkan regulasi_custom berdasarkan page
                    usort($regulasi_custom, function ($a, $b) {
                        return $a['page'] <=> $b['page'];
                    });
                    // dd($groupedCustom[1]);
                    foreach ($regulasi_custom as $item) {
                        if (empty($item['page'])) continue;
                        // $id_regulasi = (string)"id_" . $item['id'];
                        $page = $item['page'];

                        if (!empty($groupedCustom[$page])) {
                            foreach ($groupedCustom[$page] as $val) {
                                $data_custom[$page][] = [
                                    'id' => $val['id'],
                                    'name' => $val['parameter_lab'],
                                    'no_sampel' => $request->no_sampel,
                                    'akr' => $val['akr'],
                                    'keterangan' => $val['parameter'],
                                    'satuan' => $val['satuan'],
                                    'hasil' => $val['hasil_uji'],
                                    'hasil_json' => $val['hasil_uji_json'] ? json_decode($val['hasil_uji_json'], true) : null,
                                    'methode' => $val['methode'],
                                    'baku_mutu' => json_decode($val['baku_mutu']),
                                    'status' => $val['akr'] == 'ẍ' ? "BELUM AKREDITASI" : "AKREDITASI"
                                ];
                            }
                        }
                    }
                }

                // dd($data_custom);
                $defaultMethods = Parameter::where('is_active', true)
                    ->where('id_kategori', 1)
                    ->whereNotNull('method')
                    ->pluck('method')
                    ->unique()
                    ->values()
                    ->toArray();
                
                array_push($defaultMethods, '-');

                return response()->json([
                    'status' => true,
                    'data' => $data_entry,
                    'next_page' => $data_custom,
                    'spesifikasi_method' => $defaultMethods,
                    'keterangan' => [
                        '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                        '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                        'ẍ Parameter belum terakreditasi.'
                    ]
                ], 201);
            } else {

                $mainData = [];
                $methodsUsed = [];
                $otherRegulations = [];

                $models = [
                    Gravimetri::class,
                    Colorimetri::class,
                    Titrimetri::class,
                    Subkontrak::class
                ];

                foreach ($models as $model) {
                    $approveField = $model === Subkontrak::class ? 'is_approve' : 'is_approved';
                    $data = $model::with('ws_value', 'master_parameter_air')
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

                // Data lapangan (pH dan Suhu)
                $lapanganAir = DataLapanganAir::with('detail')
                    ->where('no_sampel', $request->no_sampel)
                    ->first();

                if ($lapanganAir) {
                    $mainData = array_merge($mainData, $this->handleLapangan($lapanganAir, $request->regulasi, $methodsUsed));
                }

                if ($lapanganAir && !empty($otherRegulations)) {
                    foreach ($otherRegulations as $id_regulasi => $data) {
                        $acc = array_merge($data, $this->handleLapangan($lapanganAir, $id_regulasi, $methodsUsed));
                        $otherRegulations[$id_regulasi] = $acc;
                    }
                }
                // Urutkan dan kumpulkan metode
                $mainData = collect($mainData)->sortBy(function ($item) {
                    return mb_strtolower($item['keterangan']);
                })->values()->toArray();

                foreach ($otherRegulations as $id => $regulations) {
                    $otherRegulations[$id] = collect($regulations)->sortBy(function ($item) {
                        return mb_strtolower($item['keterangan']);
                    })->values()->toArray();
                }
                $methodsUsed = array_values(array_unique($methodsUsed));
                $defaultMethods = Parameter::where('is_active', true)->where('id_kategori', 1)
                    ->whereNotNull('method')->groupBy('method')
                    ->pluck('method')->toArray();

                $resultMethods = array_unique(array_merge($methodsUsed, $defaultMethods));
                array_push($resultMethods, '-');

                return response()->json([
                    'status' => true,
                    'data' => $mainData,
                    'next_page' => $otherRegulations,
                    'spesifikasi_method' => $resultMethods,
                    'keterangan' => [
                        '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                        '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                        'ẍ Parameter belum terakreditasi.'
                    ]
                ], 201);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    private function formatEntry($val, $regulasiId, &$methodsUsed = [])
    {
        $param = $val->master_parameter_air;
        $entry = [
            'id' => $val->id,
            'name' => $val->parameter,
            'no_sampel' => $val->no_sampel,
            'akr' => $param->status === "AKREDITASI" ? '' : 'ẍ',
            'keterangan' => $param->nama_lhp ?? $param->nama_regulasi,
            'satuan' => $param->satuan,
            'hasil' => \str_replace('_', ' ', $val->ws_value->hasil) ?? null,
            'hasil_koreksi' => $val->ws_value->faktor_koreksi ?? null,
            'methode' => $param->method,
            'baku_mutu' => ["-"],
            'status' => $param->status,
            'hasil_json' => $val->ws_value->hasil_json ? json_decode($val->ws_value->hasil_json, true) : null
        ];

        $bakumutu = MasterBakumutu::where('id_regulasi', $regulasiId)
            ->where('parameter', $val->parameter)
            ->where('is_active', true)
            ->first();

        if ($bakumutu && $bakumutu->method) {
            $entry['satuan'] = $bakumutu->satuan;
            $entry['methode'] = $bakumutu->method;
            $entry['baku_mutu'] = [$bakumutu->baku_mutu];
            $methodsUsed[] = $bakumutu->method;
        }

        if($entry['hasil'] == '##'){
            $entry['satuan'] = '-';
            $entry['methode'] = '-';
            $entry['baku_mutu'] = ['-'];
        }

        return $entry;
    }

    private function handleLapangan($lapanganAir, $regulasiId, &$methodsUsed)
    {
        $results = [];
        $cekOrderDetailPH = OrderDetail::where('no_sampel', $lapanganAir->no_sampel)->whereJsonContains('parameter', "128;pH")->first();
        $cekOrderDetaiSUHU = OrderDetail::where('no_sampel', $lapanganAir->no_sampel)->whereJsonContains('parameter', "160;Suhu")->first();
        $cekOrderDetaiDebit = OrderDetail::where('no_sampel', $lapanganAir->no_sampel)->where('parameter', 'LIKE', "%Debit Air%")->first();

        if (!empty($lapanganAir->ph) && $cekOrderDetailPH) {
            $bakumutu = MasterBakumutu::where('parameter', 'pH')
                ->where('id_regulasi', $regulasiId)
                ->where('is_active', true)
                ->first();

            $masterParameter = Parameter::where('nama_lab', 'pH')->where('id_kategori', 1)->where('is_active', true)->first();

            $results[] = [
                'id' => 1,
                'name' => 'pH',
                'no_sampel' => $lapanganAir->no_sampel,
                'akr' => '',
                'satuan' => $bakumutu->satuan ?? '-',
                'methode' => $bakumutu->method ?? $masterParameter->method,
                'baku_mutu' => [$bakumutu->baku_mutu ?? '-'],
                'hasil' => $lapanganAir->ph,
                'status' => 'AKREDITASI',
                'hasil_koreksi' => '',
                'keterangan' => "pH"
            ];

            $methodsUsed[] = $bakumutu->method ?? '-';
        }
        if (!empty($lapanganAir->debit_air) && $cekOrderDetaiDebit) {

            $parameters = json_decode($cekOrderDetaiDebit->parameter, true);
            $parameter = array_filter($parameters, function ($item) {
                return str_contains($item, 'Debit Air');
            });
            $parameter = array_values($parameter);
            $parameter = $parameter[0];
            $parameterName = explode(';', $parameter)[1];


            $bakumutu = MasterBakumutu::where('parameter', $parameterName)
                ->where('id_regulasi', $regulasiId)
                ->where('is_active', true)
                ->first();
            if (str_contains($lapanganAir->debit_air, 'Data By Customer') && preg_match('/\((.*?)\)/', $lapanganAir->debit_air, $matches)) {
                $nilai = $matches[1];
            } else {
                $nilai = $lapanganAir->debit_air;
            }

            $results[] = [
                'name' => $parameterName,
                'no_sampel' => $lapanganAir->no_sampel,
                'akr' => '',
                'satuan' => $bakumutu->satuan ?? '-',
                'methode' => $bakumutu->method ?? 'IKM/ISL/7.2.109 (Perhitungan)',
                'baku_mutu' => [$bakumutu->baku_mutu ?? '-'],
                'hasil' => $nilai,
                'status' => 'AKREDITASI',
                'hasil_koreksi' => '',
                'keterangan' => "Debit Air"
            ];

            $methodsUsed[] = $bakumutu->method ?? '-';
        }

        if (!empty($lapanganAir->suhu_air) && $cekOrderDetaiSUHU) {
            $bakumutu = MasterBakumutu::where('parameter', 'Suhu')
                ->where('id_regulasi', $regulasiId)
                ->where('is_active', true)
                ->first();

            $masterParameterSuhu = Parameter::where('nama_lab', 'Suhu')->where('id_kategori', 1)->where('is_active', true)->first();

            $results[] = [
               'id' => 2,
                'name' => 'Suhu',
                'no_sampel' => $lapanganAir->no_sampel,
                'akr' => '',
                'satuan' => $bakumutu->satuan ?? '°C',
                'methode' => $bakumutu->method ?? $masterParameterSuhu->method,
                'baku_mutu' => [$bakumutu->baku_mutu ?? '-'],
                'hasil' => $lapanganAir->suhu_air,
                'status' => 'AKREDITASI',
                'hasil_koreksi' => '',
                'keterangan' => "Suhu"
            ];

            $methodsUsed[] = $bakumutu->method ?? '-';
        }

        return $results;
    }

    public function handleGenerateLink(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsAirHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();

            if ($header != null) {
                if ($header->count_revisi > 0) {
                    $header->is_generated = true;
                    $header->generated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $header->generated_by = $this->karyawan;
                } else {
                    $history = LhpsAirHeaderHistory::where('no_sampel', $header->no_sampel)->whereNotNull('id_token')->orderBy('id', 'desc')->first();
                    if ($history != null) {

                        $header->id_token = $history->id_token;
                        $header->generated_at = Carbon::now()->format('Y-m-d H:i:s');
                        $header->generated_by = $this->karyawan;
                        $header->is_generated = true;

                        // update link agar id di berikan yang original
                        $link = GenerateLink::where('id', $history->id_token)->update([
                            'id_quotation' => $header->id,
                            'fileName_pdf' => $header->file_lhp
                        ]);
                    } else {
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
                    }
                }
            }
            // dd($header);
            $header->save();
            DB::commit();
            return response()->json([
                'message' => 'Generate link success!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }
    public function handleRevisi(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsAirHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();

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

    // public function handleApprove(Request $request, $isManual = true)
    // {
    //     DB::beginTransaction();
    //     try {
    //         if ($isManual) {
    //             $konfirmasiLhp = KonfirmasiLhp::where('no_lhp', $request->cfr)->first();

    //             if (!$konfirmasiLhp) {
    //                 $konfirmasiLhp = new KonfirmasiLhp();
    //                 $konfirmasiLhp->created_by = $this->karyawan;
    //                 $konfirmasiLhp->created_at = Carbon::now()->format('Y-m-d H:i:s');
    //             } else {
    //                 $konfirmasiLhp->updated_by = $this->karyawan;
    //                 $konfirmasiLhp->updated_at = Carbon::now()->format('Y-m-d H:i:s');
    //             }

    //             $konfirmasiLhp->no_lhp = $request->cfr;
    //             $konfirmasiLhp->is_nama_perusahaan_sesuai = $request->nama_perusahaan_sesuai;
    //             $konfirmasiLhp->is_alamat_perusahaan_sesuai = $request->alamat_perusahaan_sesuai;
    //             $konfirmasiLhp->is_no_sampel_sesuai = $request->no_sampel_sesuai;
    //             $konfirmasiLhp->is_no_lhp_sesuai = $request->no_lhp_sesuai;
    //             $konfirmasiLhp->is_regulasi_sesuai = $request->regulasi_sesuai;
    //             $konfirmasiLhp->is_qr_pengesahan_sesuai = $request->qr_pengesahan_sesuai;
    //             $konfirmasiLhp->is_tanggal_rilis_sesuai = $request->tanggal_rilis_sesuai;

    //             $konfirmasiLhp->save();
    //         };

    //         $header = LhpsAirHeader::where('no_sampel', $request->no_sampel)
    //             ->where('is_active', true)->firstOrFail();

    //         $data_order = OrderDetail::where('no_sampel', $request->no_sampel)
    //             ->where('id', $request->id)
    //             ->where('is_active', true)
    //             ->firstOrFail();

    //         if ($header != null) {
    //             $data_order->is_approve = 1;
    //             $data_order->status = 3;
    //             $data_order->approved_at = Carbon::now()->format('Y-m-d H:i:s');
    //             $data_order->approved_by = $this->karyawan;
    //             $data_order->save();

    //             $header->is_approve = 1;
    //             $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
    //             $header->approved_by = $this->karyawan;

    //             $header->save();

    //             HistoryAppReject::insert([
    //                 'no_lhp' => $data_order->cfr,
    //                 'no_sampel' => $data_order->no_sampel,
    //                 'kategori_2' => $data_order->kategori_2,
    //                 'kategori_3' => $data_order->kategori_3,
    //                 'menu' => 'Draft Air',
    //                 'status' => 'approve',
    //                 'approved_at' => Carbon::now(),
    //                 'approved_by' => $this->karyawan
    //             ]);


    //             if ($header->file_qr == null) {
    //                 $dataQr = json_decode($qr->data);
    //                 $dataQr->Tanggal_Pengesahan = Carbon::parse($header->tanggal_lhp)->locale('id')->isoFormat('DD MMMM YYYY');
    //                 $dataQr->Disahkan_Oleh = $header->nama_karyawan;
    //                 $dataQr->Jabatan = $header->jabatan_karyawan;
    //                 $qr->data = json_encode($dataQr);
    //                 $qr->save();
    //             }

    //             $cekDetail = OrderDetail::where('cfr', $header->no_lhp)->where('is_active', true)->first();
    //             $cekLink = LinkLhp::where('no_order', $header->no_order)->where('periode', $periode)->first();
    //             $orderHeader = OrderHeader::where('id', $cekDetail->id_order_header)->where('is_active', true)->first();
    //             if($cekLink) {
    //                 $job = new CombineLHPJob($header->no_lhp, $header->file_lhp, $header->no_order, $this->karyawan, $cekDetail->periode);
    //                 $this->dispatch($job);
    //             }

    //             EmailLhpRilisHelpers::run([
    //                 'cfr' => $header->no_lhp,
    //                 'no_order' => $header->no_order,
    //                 'nama_pic_order' => $orderHeader->nama_pic_order,
    //                 'nama_perusahaan' => $header->nama_pelanggan,
    //                 'periode' => $cekDetail->periode,
    //                 'karyawan' => $this->karyawan
    //             ]);

    //             DB::commit();
    //             return response()->json([
    //                 'message' => 'Approve no sampel ' . $request->no_sampel . ' berhasil!'
    //             ], 200);
    //         }
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         dd($e);
    //         return response()->json([
    //             'message' => $e->getMessage(),
    //         ], 401);
    //     }
    // }

    public function handleApprove(Request $request, $isManual = true)
    {
        DB::beginTransaction();

        try {

            // ------------------------------------------------------
            // 1. Handle Konfirmasi LHP (Manual)
            // ------------------------------------------------------
            if ($isManual) {

                $konfirmasi = KonfirmasiLhp::firstOrNew(['no_lhp' => $request->cfr]);

                $konfirmasi->fill([
                    'is_nama_perusahaan_sesuai' => $request->nama_perusahaan_sesuai,
                    'is_alamat_perusahaan_sesuai' => $request->alamat_perusahaan_sesuai,
                    'is_no_sampel_sesuai' => $request->no_sampel_sesuai,
                    'is_no_lhp_sesuai' => $request->no_lhp_sesuai,
                    'is_regulasi_sesuai' => $request->regulasi_sesuai,
                    'is_qr_pengesahan_sesuai' => $request->qr_pengesahan_sesuai,
                    'is_tanggal_rilis_sesuai' => $request->tanggal_rilis_sesuai,
                ]);

                // created_by / updated_by otomatis
                if (!$konfirmasi->exists) {
                    $konfirmasi->created_by = $this->karyawan;
                } else {
                    $konfirmasi->updated_by = $this->karyawan;
                }

                $konfirmasi->save();
            }


            // ------------------------------------------------------
            // 2. Ambil Data Utama (HEADER + ORDER DETAIL)
            // ------------------------------------------------------
            $header = LhpsAirHeader::where('no_sampel', $request->no_sampel)
                ->where('is_active', true)
                ->firstOrFail();

            $detail = OrderDetail::where('no_sampel', $request->no_sampel)
                ->where('id', $request->id)
                ->where('is_active', true)
                ->firstOrFail();


            // ------------------------------------------------------
            // 3. Update Status Detail dan Header
            // ------------------------------------------------------
            $now = Carbon::now()->format('Y-m-d H:i:s');

            $detail->update([
                'is_approve' => 1,
                'status'     => 3,
                'approved_at' => $now,
                'approved_by' => $this->karyawan
            ]);

            $header->update([
                'is_approve' => 1,
                'approved_at' => $now,
                'approved_by' => $this->karyawan
            ]);

            // ------------------------------------------------------
            // 4. Insert History Approve
            // ------------------------------------------------------
            HistoryAppReject::create([
                'no_lhp'       => $detail->cfr,
                'no_sampel'    => $detail->no_sampel,
                'kategori_2'   => $detail->kategori_2,
                'kategori_3'   => $detail->kategori_3,
                'menu'         => 'Draft Air',
                'status'       => 'approve',
                'approved_at'  => $now,
                'approved_by'  => $this->karyawan
            ]);

            // ------------------------------------------------------
            // 6. Cek Link + Dispatch Combine Job
            // ------------------------------------------------------
            $cekDetail = OrderDetail::where('cfr', $header->no_lhp)
                ->where('is_active', true)
                ->first();

            $periode = $cekDetail->periode ?? null;

            $cekLink = LinkLhp::where('no_order', $header->no_order);
            if ($cekDetail && $cekDetail->periode) $cekLink = $cekLink->where('periode', $cekDetail->periode);
            $cekLink = $cekLink->first();

            if ($cekLink) {
                $job = new CombineLHPJob($header->no_lhp, $header->file_lhp, $header->no_order, $this->karyawan, $cekDetail->periode);
                $this->dispatch($job);
            }

            // ------------------------------------------------------
            // 7. Kirim Email (Helper baru yang sudah OK)
            // ------------------------------------------------------
            $orderHeader = OrderHeader::where('id', $cekDetail->id_order_header)
                ->first();

            EmailLhpRilisHelpers::run([
                'cfr'              => $header->no_lhp,
                'no_order'         => $header->no_order,
                'nama_pic_order'   => $orderHeader->nama_pic_order ?? '-',
                'nama_perusahaan'  => $header->nama_pelanggan,
                'periode'          => $periode,
                'karyawan'         => $this->karyawan
            ]);

            DB::commit();

            return response()->json([
                'message' => "Approve no sampel {$request->no_sampel} berhasil!"
            ], 200);
        } catch (\Throwable $e) {

            DB::rollBack();

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
                $lhpsHistory->id = $lhps->id;
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
                        $detailHistory->id = $detail->id;
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
                'status' => 'success',
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
