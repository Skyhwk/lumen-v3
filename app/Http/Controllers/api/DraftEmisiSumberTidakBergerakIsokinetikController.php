<?php
namespace App\Http\Controllers\api;

//models
use App\Http\Controllers\Controller;
use App\Jobs\CombineLHPJob;
use App\Models\EmisiCerobongHeader;
use App\Models\GenerateLink;
use App\Models\HistoryAppReject;
use App\Models\KonfirmasiLhp;
use App\Models\LhpsEmisiIsokinetikCustom;
use App\Models\LhpsEmisiIsokinetikDetail;
use App\Models\LhpsEmisiIsokinetikDetailHistory;
use App\Models\LhpsEmisiIsokinetikHeader;
use App\Models\LhpsEmisiIsokinetikHeaderHistory;
use App\Models\LinkLhp;
use App\Models\MasterBakumutu;
use App\Models\MasterKaryawan;
use App\Models\MasterRegulasi;
use App\Models\MetodeSampling;
use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\IsokinetikHeader;
// service

use App\Models\PengesahanLhp;
use App\Models\QrDocument;
use App\Models\Subkontrak;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
// job

use App\Services\SendEmail;
//iluminate

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

//Helper
use App\Helpers\EmailLhpRilisHelpers;
use App\Models\MdlEmisi;
use App\Models\OrderHeader;

class DraftEmisiSumberTidakBergerakIsokinetikController extends Controller
{
    public function index(Request $request)
    {
        $data1 = OrderDetail::with('lhps_emisi', 'orderHeader', 'lhps_emisi_isokinetik', 'dataLapanganEmisiCerobong')
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('status', 2)
            ->where('kategori_2', '5-Emisi')
            ->whereIn('kategori_3', [
                '34-Emisi Sumber Tidak Bergerak',
                '119-Emisi Isokinetik',
            ])
            ->where('parameter', 'like', '%Iso-%');
        return Datatables::of($data1)->make(true);
    }

    public function handleSubmitDraft(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsEmisiIsokinetikHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();

            if ($header == null) {
                $header = new LhpsEmisiIsokinetikHeader();
            } else {
                $history = $header->replicate();
                $history->setTable((new LhpsEmisiIsokinetikHeaderHistory())->getTable());
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->save();
            }

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

            $parameter_uji = explode(', ', $request->parameter_uji);
            try {
                $regulasi_custom = collect($request->regulasi_custom ?? [])->map(function ($item, $page) use ($request) {
                    return ['page' => (int) $page, 'regulasi' => $request->regulasi ? $request->regulasi[0] : $item];
                })->values()->toArray();

                $header->id_kategori_2    = $request->category2 ?: null;
                $header->id_kategori_3    = $request->category ?: null;
                $header->kategori         = $request->kategori ?: null;
                $header->no_order         = $request->no_order ?: null;
                $header->no_lhp           = $request->no_lhp ?: null;
                $header->no_quotation     = $request->no_penawaran ?: null;
                $header->no_sampel        = $request->no_sampel ?: null;
                $header->parameter_uji    = json_encode($parameter_uji);
                $header->nama_pelanggan   = $request->nama_perusahaan ?: null;
                $header->alamat_sampling  = $request->alamat_sampling ?: null;
                $header->sub_kategori     = $request->sub_kategori ?: null;
                $header->metode_sampling  = $request->metode_sampling ? json_encode($request->metode_sampling) : null;
                $header->tanggal_lhp      = $request->tanggal_lhp;
                $header->konsultan        = $request->konsultan ?: null;
                $header->nama_pic         = $request->nama_pic ?: null;
                $header->jabatan_pic      = $request->jabatan_pic ?: null;
                $header->no_pic           = $request->no_pic ?: null;
                $header->email_pic        = $request->email_pic ?: null;
                $header->type_sampling    = $request->kategori_1 ?: null;
                // $header->tanggal_sampling = $request->tanggal_sampling ?: null;
                $header->tanggal_terima   = $request->tanggal_terima ?: null;
                $header->tanggal_tugas    = $request->tanggal_tugas ?: null;
                $header->periode_analisa  = $request->periode_analisa ?: null;
                $header->nama_karyawan    = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah'; //ok
                $header->jabatan_karyawan = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor'; //ok
                $header->keterangan       = $request->keterangan ? json_encode($request->keterangan) : null;
                $header->deskripsi_titik  = $request->penamaan_titik ?: null;
                $header->titik_koordinat  = $request->titik_koordinat ?: null;
                $header->regulasi         = $request->regulasi ? json_encode($request->regulasi) : null;
                $header->regulasi_custom  = isset($regulasi_custom) ? json_encode($regulasi_custom) : null;
                $header->created_by       = $this->karyawan;
                $header->created_at       = Carbon::now()->format('Y-m-d H:i:s');
                $header->save();

                $param     = $request->parameter;
                $allDetail = []; // tampung semua detail

                foreach ($param as $key => $val) {

                    $existing = LhpsEmisiIsokinetikDetail::where('id_header', $header->id)
                        ->where('parameter', $val)
                        ->first();

                    if ($existing) {
                        $detailHistory = $existing->replicate();
                        $detailHistory->setTable((new LhpsEmisiIsokinetikDetailHistory())->getTable());
                        $detailHistory->created_by = $this->karyawan;
                        $detailHistory->created_at = Carbon::now()->format('Y-m-d H:i:s');
                        $detailHistory->save();
                    }

                    if (! $existing) {
                        $detail            = new LhpsEmisiIsokinetikDetail();
                        $detail->id_header = $header->id;
                        $detail->parameter = $val;
                    } else {
                        $detail = $existing;
                    }

                    $detail->akr                = $request->akr[$key] ?? null;
                    $detail->parameter_lab      = $request->parameter_lab[$key] ?? null;
                    $detail->hasil_uji          = $request->hasil_uji[$key] ?? null;
                    $detail->hasil_terkoreksi   = $request->hasil_terkoreksi[$key] ?? null;
                    $detail->spesifikasi_metode = $request->spesifikasi_metode[$key] ?? null;
                    $detail->satuan             = $request->satuan[$key] ?? null;
                    $detail->baku_mutu          = $request->baku_mutu[$key] ?? null;

                    $detail->save();

                    $allDetail[] = $detail; // masukin setiap detail ke array
                }

                // dd($request->custom_parameter);

                LhpsEmisiIsokinetikCustom::where('id_header', $header->id)->delete();
                if (isset($request->custom_parameter)) {
                    foreach ($request->custom_parameter as $page => $values) {
                        foreach ($values as $param => $val) {
                            $custom                     = new LhpsEmisiIsokinetikCustom();
                            $custom->id_header          = $header->id;
                            $custom->page               = $page;
                            $custom->parameter          = $param;
                            $custom->akr                = $request->custom_akr[$page][$param] ?? null;
                            $custom->parameter_lab      = $param;
                            $custom->hasil_uji          = $request->custom_hasil_uji[$page][$param] ?? null;
                            $custom->spesifikasi_metode = $request->custom_methode[$page][$param] ?? null;
                            $custom->satuan             = $request->custom_satuan[$page][$param] ?? null;
                            $custom->baku_mutu          = $request->custom_baku_mutu[$page][$param] ?? null;
                            $custom->save();
                        }
                    }
                }
                if ($header != null) {

                    $file_qr = new GenerateQrDocumentLhp();
                    $file_qr = $file_qr->insert('LHP_EMISI_ISOKINETIK', $header, $this->karyawan);
                    if ($file_qr) {
                        $header->file_qr = $file_qr;
                        $header->save();
                    }

                    $detail = LhpsEmisiIsokinetikDetail::where('id_header', $header->id)->get();

                    $custom = LhpsEmisiIsokinetikCustom::where('id_header', $header->id)
                        ->get()
                        ->groupBy('page')
                        ->toArray();

                    $view = 'DraftESTBIsokinetik';

                    $fileName = LhpTemplate::setDataHeader($header)
                        ->setDataDetail($detail)
                        ->setDataCustom($custom)
                        ->whereView($view)
                        ->render('downloadLHPFinal');

                    $header->file_lhp = $fileName;
                    $header->save();
                }
            } catch (\Exception $e) {
                throw new \Exception("Error in header or detail assignment: " . $e->getMessage() . "line " . $e->getLine() . "file : " . $e->getFile());
            }

            DB::commit();
            return response()->json([
                'message' => 'Data draft LHP air no sampel ' . $request->no_sampel . ' berhasil disimpan',
                'status'  => true,
            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            dd($th);
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line'    => $th->getLine(),
                'status'  => false,
            ], 500);
        }
        // }
    }

    public function updateTanggalLhp(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataHeader = LhpsEmisiIsokinetikHeader::find($request->id);

            if (! $dataHeader) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Data tidak ditemukan',
                ], 404);
            }

            // Update tanggal LHP dan data pengesahan
            $dataHeader->tanggal_lhp = $request->value;

            $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->value)
                ->orderByDesc('berlaku_mulai')
                ->first();

            $dataHeader->nama_karyawan    = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
            $dataHeader->jabatan_karyawan = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';

            // Update QR Document jika ada
            $qr = QrDocument::where('file', $dataHeader->file_qr)->first();
            if ($qr) {
                $dataQr                       = json_decode($qr->data, true);
                $dataQr['Tanggal_Pengesahan'] = Carbon::parse($request->value)->locale('id')->isoFormat('DD MMMM YYYY');
                $dataQr['Disahkan_Oleh']      = $dataHeader->nama_karyawan;
                $dataQr['Jabatan']            = $dataHeader->jabatan_karyawan;
                $qr->data                     = json_encode($dataQr);
                $qr->save();
            }

            // Render ulang file LHP
            $detail = LhpsEmisiIsokinetikDetail::where('id_header', $dataHeader->id)->get();
            $custom = LhpsEmisiIsokinetikCustom::where('id_header', $dataHeader->id)->get();

            $groupedByPage = [];
            foreach ($custom as $item) {
                $page                   = $item->page;
                $groupedByPage[$page][] = $item->toArray();
            }

            $view = 'DraftESTB';

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
                'status'  => true,
                'message' => 'Tanggal LHP berhasil diubah',
                'data'    => $dataHeader,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function handleMetodeSampling(Request $request)
    {
        try {
            $subKategori = explode('-', $request->kategori_3);
            $data        = MetodeSampling::where('kategori', '5-EMISI')
                ->where('sub_kategori', strtoupper($subKategori[1]))->get();
            if ($data->isNotEmpty()) {
                return response()->json([
                    'status'  => true,
                    'message' => 'Available data retrieved successfully',
                    'data'    => $data,
                ], 200);
            } else {
                return response()->json([
                    'status'  => true,
                    'message' => 'Belom ada method',
                    'data'    => [],
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function handleDatadetail(Request $request)
    {
        try {
            $cek_lhp = LhpsEmisiIsokinetikHeader::with('LhpsEmisiIsokinetikDetail', 'LhpsEmisiIsokinetikCustom')->where('no_sampel', $request->no_sampel)->first();
            if ($cek_lhp) {
                $data_entry     = [];
                $data_custom    = [];
                $cek_regulasi   = [];
                $methodUsed     = [];
                $dataPage2      = [];
                $dataPage3      = [];

                foreach ($cek_lhp->LhpsEmisiIsokinetikDetail->toArray() as $key => $val) {
                    $data_entry[$key] = [
                        'id'            => $val['id'],
                        'no_sampel'     => $request->no_sampel,
                        'parameter'     => $val['parameter'],
                        'parameter_lab' => $val['parameter_lab'],
                        'C'             => $val['hasil_uji'],
                        'terkoreksi'    => $val['hasil_terkoreksi'],
                        'satuan'        => $val['satuan'],
                        'methode'       => $val['spesifikasi_metode'],
                        'baku_mutu'     => $val['baku_mutu'],
                    ];

                    $methodUsed[] = $val['spesifikasi_metode'];
                }

                if (! empty($cek_lhp->LhpsEmisiIsokinetikCustom) && ! empty($cek_lhp->regulasi_custom)) {
                    $regulasi_custom = json_decode($cek_lhp->regulasi_custom, true);

                    // Mapping id regulasi jika ada other_regulasi
                    if (! empty($cek_regulasi)) {
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
                                    $item['id']                   = $regulasi_db->id;
                                    $mapRegulasi[$regulasi_clean] = $regulasi_db->id;
                                }
                            }
                            return $item;
                        }, $regulasi_custom);
                    }

                    // Group custom berdasarkan page
                    $groupedCustom = [];
                    foreach ($cek_lhp->LhpsEmisiIsokinetikCustom as $val) {
                        $groupedCustom[$val->page][] = $val;
                    }
                    // Isi data_custom
                    // Urutkan regulasi_custom berdasarkan page
                    usort($regulasi_custom, function ($a, $b) {
                        return $a['page'] <=> $b['page'];
                    });
                    foreach ($regulasi_custom as $item) {
                        if (empty($item['page'])) {
                            continue;
                        }

                        $page        = $item['page'];
                        if (! empty($groupedCustom[$page])) {
                            foreach ($groupedCustom[$page] as $val) {
                                if($page == 2) {
                                    $dataPage2[] = [
                                        'parameter' => $val['parameter'],
                                        'hasil_uji' => $val['hasil_uji'],
                                        'baku_mutu' => $val['baku_mutu'],
                                        'satuan' => $val['satuan'],
                                        'spesifikasi_method' => $val['spesifikasi_metode'] ?? '-',
                                    ];
                                } elseif($page == 3){
                                    $dataPage3[] = [
                                        'parameter' => $val['parameter'],
                                        'hasil_uji' => $val['hasil_uji'],
                                        'baku_mutu' => $val['baku_mutu'],
                                        'satuan' => $val['satuan'],
                                        'spesifikasi_method' => $val['spesifikasi_metode'] ?? '-',
                                    ];
                                }
                            }
                        }
                    }
                }

                $defaultMethods = Parameter::where('is_active', true)
                    ->where('id_kategori', 5)
                    ->whereNotNull('method')
                    ->pluck('method')
                    ->unique()
                    ->values()
                    ->toArray();

                $methodUsed    = array_unique($methodUsed);
                $returnMethods = array_merge($defaultMethods, $methodUsed);

                return response()->json([
                    'status'             => true,
                    'data'               => $data_entry,
                    'next_page'          => $dataPage2,
                    'next_page_2'        => $dataPage3,
                    'spesifikasi_method' => $returnMethods,
                    'keterangan'         => [
                        '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                        '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                        'ẍ Parameter belum terakreditasi.',
                    ],
                ], 201);
            } else {

                $mainData         = [];
                $methodsUsed      = [];
                $otherRegulations = [];
                $dataPage2        = [];
                $dataPage3        = [];

                $models = [
                    EmisiCerobongHeader::class,
                    IsokinetikHeader::class,
                    Subkontrak::class,
                ];

                $parameter    = OrderDetail::where('no_sampel', $request->no_sampel)
                    ->where('is_active', true)
                    ->first()->parameter;

                $parameters = collect(json_decode($parameter))->map(fn($item) => ['id' => explode(";", $item)[0], 'parameter' => explode(";", $item)[1]]);
                $mdlEmisi = MdlEmisi::whereIn('parameter_id', $parameters->pluck('id'))->get();
                
                $getHasilUji = function ($index, $parameterId, $hasilUji) use ($mdlEmisi) {
                    if ($hasilUji && $hasilUji !== "-" && $hasilUji !== "##" && !str_contains($hasilUji, '<')) {
                        $colToSearch = "C$index";
                        $mdlEmisi = $mdlEmisi->where('parameter_id', $parameterId)->whereNotNull($colToSearch)->first();
                        if ($mdlEmisi && (float) $mdlEmisi->$colToSearch > (float) $hasilUji) {
                            $hasilUji = "<" . $mdlEmisi->$colToSearch;
                        }
                    }

                    return $hasilUji;
                };

                foreach ($models as $model) {
                    $approveField = $model === Subkontrak::class ? 'is_approve' : 'is_approved';
                    $data         = $model::with('ws_value_cerobong', 'parameter_emisi')
                        ->where('no_sampel', $request->no_sampel)
                        ->where($approveField, 1)
                        ->where('is_active', true)
                        ->where('lhps', 1)
                        ->get();
                    foreach ($data as $val) {
                        if($model === IsokinetikHeader::class){
                            $hasilIsokinetik = json_decode($val->ws_value_cerobong->hasil_isokinetik, true);
                            // dd($hasilIsokinetik);

                            // berat_molekul_kering
                            if (array_key_exists('berat_molekul_kering_method5', $hasilIsokinetik)) {
                                $dataPage2[] = [
                                    'parameter' => 'Berat Molekul Kering',
                                    'hasil_uji' => $hasilIsokinetik['berat_molekul_kering_method5'],
                                    'baku_mutu' => '-',
                                    'satuan' => 'g/gmol',
                                    'spesifikasi_metode' => 'SNI 7177.15:2009',
                                ];
                            }
                            // kadar_uap_air
                            if (array_key_exists('uap_air_dalam_aliran_gas', $hasilIsokinetik)) {
                                $dataPage2[] = [
                                    'parameter' => 'Kadar Uap Air',
                                    'hasil_uji' => $hasilIsokinetik['uap_air_dalam_aliran_gas'],
                                    'baku_mutu' => '-',
                                    'satuan' => '%',
                                    'spesifikasi_metode' => 'SNI 7177.16:2009',
                                ];
                            }
                            // kecepatan_volumetrik_aktual
                            if (array_key_exists('kecepatan_volumetrik_aktual', $hasilIsokinetik)) {
                                $dataPage2[] = [
                                    'parameter' => 'Kecepatan Linier (Laju Alir Volumetrik)',
                                    'hasil_uji' => $hasilIsokinetik['kecepatan_volumetrik_aktual'],
                                    'baku_mutu' => '-',
                                    'satuan' => 'm³/s',
                                    'spesifikasi_metode' => 'SNI 7177.14:2009',
                                ];
                            }
                            // traverse_poin_partikulat
                            if (array_key_exists('traverse_poin_partikulat', $hasilIsokinetik)) {
                                $dataPage2[] = [
                                    'parameter' => 'Lokasi dan Titik - Titik Lintas (Traverse Point)',
                                    'hasil_uji' => $hasilIsokinetik['traverse_poin_partikulat'],
                                    'baku_mutu' => '-',
                                    'satuan' => '-',
                                    'spesifikasi_metode' => 'SNI 7177.13:2009',
                                ];
                            }
                            // persen_sampling_isokinetik
                            if (array_key_exists('persen_sampling_isokinetik', $hasilIsokinetik)) {
                                $dataPage2[] = [
                                    'parameter' => 'Persen Isokinetik',
                                    'hasil_uji' => $hasilIsokinetik['persen_sampling_isokinetik'],
                                    'baku_mutu' => '90-110',
                                    'satuan' => '%',
                                    'spesifikasi_metode' => 'SNI 7177.17:2009',
                                ];
                            }

                            $dataPage3 = array_merge($dataPage3, $this->buildPage3($hasilIsokinetik));
                        } else {
                            $entry      = $this->formatEntry($val, $request->regulasi, $methodsUsed, $getHasilUji);
                            $mainData[] = $entry;
                        }
                    }
                }

                usort($dataPage2, function ($a, $b) {
                    return strcmp($a['parameter'], $b['parameter']);
                });
                usort($dataPage3, function ($a, $b) {
                    return strcmp($a['parameter'], $b['parameter']);
                });
                $mainData = collect($mainData)->sortBy(function ($item) {
                    return mb_strtolower($item['parameter']);
                })->values()->toArray();

                foreach ($otherRegulations as $id => $regulations) {
                    $otherRegulations[$id] = collect($regulations)->sortBy(function ($item) {
                        return mb_strtolower($item['parameter']);
                    })->values()->toArray();
                }
                $methodsUsed    = array_values(array_unique($methodsUsed));
                $defaultMethods = Parameter::where('is_active', true)->where('id_kategori', 5)
                    ->whereNotNull('method')->groupBy('method')
                    ->pluck('method')->toArray();

                $resultMethods = array_values(array_unique(array_merge($methodsUsed, $defaultMethods)));

                return response()->json([
                    'status'             => true,
                    'data'               => $mainData,
                    'next_page'          => $dataPage2,
                    'next_page_2'        => $dataPage3,
                    'spesifikasi_method' => $resultMethods,
                    'keterangan'         => [
                        '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                        '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                        'ẍ Parameter belum terakreditasi.',
                    ],
                ], 201);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line'    => $e->getLine(),
                'getFile' => $e->getFile(),
            ], 500);
        }
    }

    private function formatEntry($val, $regulasiId, &$methodsUsed = [], $getHasilUji)
    {
        $param = $val->parameter_emisi;

        $bakumutu = MasterBakumutu::where('id_regulasi', $regulasiId)
            ->where('id_parameter', $param->id)
            ->first();
        $satuan     = $bakumutu ? $bakumutu->satuan : null;
        $akreditasi = $bakumutu && isset($bakumutu->akreditasi) ? $bakumutu->akreditasi : '';

        $entry = [
            'id'            => $val->id,
            'no_sampel'     => $val->no_sampel,
            'parameter'     => $param->nama_lhp ?? $param->nama_regulasi,
            'parameter_lab' => $val->parameter,
            'C'             => self::getHasilUji($val, $satuan, $getHasilUji),
            // 'C1' => $val->ws_value_cerobong->C1,
            // 'C2' => $val->ws_value_cerobong->C2,
            'terkoreksi'    => self::getKoreksi($val, $satuan),
            'satuan'        => $param->satuan,
            'methode'       => $param->method,
            'baku_mutu'     => $val->baku_mutu->baku_mutu ?? '-',
            'akr'           => str_contains($akreditasi, 'akreditasi') ? '' : 'ẍ',
        ];

        if ($bakumutu && $bakumutu->method) {
            $entry['satuan']    = $bakumutu->satuan;
            $entry['methode']   = $bakumutu->method;
            $entry['baku_mutu'] = $bakumutu->baku_mutu;
            $methodsUsed[]      = $bakumutu->method;
        }

        return $entry;
    }

    private function buildPage3($data){
        // Mapping sesuai PDF
        $mapping = [
            'traverse_poin_partikulat' => ['Titik Lintas Partikulat', 'Titik', 'Perhitungan'],
            'traverse_poin_kecepatan_linier' => ['Titik Lintas Kecepatan Linier', 'Titik', 'Perhitungan'],
            'diameter_cerobong' => ['Diameter Cerobong', 'm', 'Pengukuran'],
            'ukuran_lubang_sampling' => ['Ukuran Lubang Sampling', 'm', 'Pengukuran'],
            'jumlah_lubang_sampling' => ['Jumlah Lubang Sampling', 'Unit', 'Pengukuran'],
            'jarak_upstream' => ['Jarak Upstream', 'm', 'Pengukuran'],
            'jarak_downstream' => ['Jarak Downstream', 'm', 'Pengukuran'],
            'kategori_upstream' => ['Kategori Upstream', 'D', 'Perhitungan'],
            'kategori_downstream' => ['Kategori Downstream', 'D', 'Perhitungan'],
            'kp' => ['Kp', '-', 'Ketetapan'],
            'cp' => ['Cp', '-', 'Ketetapan'],
            'selisih_tekanan_barometer_method_5' => ['Selisih Tekanan Udara Lingkungan dan Gas Buang', 'mmHg', 'Perhitungan'],
            'tekanan_barometer' => ['Tekanan Udara Lingkungan', 'mmHg', 'Pengukuran'],
            'kecepatan_linier_method_5' => ['Kecepatan Linier', 'm/s', 'Perhitungan'],
            'kecepatan_volumetrik_aktual' => ['Kecepatan Volumetrik Actual', 'm³/s', 'Pengukuran'],
            'berat_molekul_kering_method5' => ['Berat Molekul Kering', 'g/gmol', 'Perhitungan'],
            'berat_molekul_basah_method5' => ['Berat Molekul Basah', 'g/gmol', 'Perhitungan'],
            'durasi_waktu' => ['Durasi Sampling', 'Menit', 'Pengukuran'],
            'volume_uap_air' => ['Volume Uap Air Sampel Gas (Standar)', 'm³', 'Pengukuran'],
            'kecepatan_volumetrik_standar' => ['Kecepatan Volumetrik Standar', 'm³/s', 'Perhitungan'],
            'uap_air_dalam_aliran_gas' => ['Kadar Uap Air', '%', 'Perhitungan'],
            'koefisien_dry_gas' => ['Koefisien Dry Gas Meter', '-', 'Kalibrasi'], 
            'delta_h_calibrate' => ['Δh Calibrate', 'mm H₂O', 'Kalibrasi'],
            'volume_sampel_dari_dry_gas' => ['Volume Sampel dari Dry Gas Meter', 'm³', 'Pengukuran'],
            'volume_sampel_gas_standar' => ['Volume Sampel Gas (Standar)', 'm³', 'Perhitungan'],
            'rata_rata_suhu_gas_buang' => ['Rata-Rata Suhu Gas Buang', '°C', 'Perhitungan'],
            'tekanan_gas_buang' => ['Tekanan Gas Buang', 'mmHg', 'Pengukuran'],
            'diameter_nozzle' => ['Diameter Nozzle', 'm', 'Pengukuran'],
            'luas_penampang_nozzle' => ['Luas Penampang Nozzle', 'm²', 'Perhitungan'],
            'persen_sampling_isokinetik' => ['Persen Sampling Isokinetik', '%', 'Perhitungan'],
            'effisiensi_pembakaran' => ['Effisiensi Pembakaran', '%', 'Perhitungan'],
        ];

        // Key yang harus dibuang
        $removeKeys = [
            'luas_penampang_cerobong',
            'rata_rata_tekanan_pitot',
            'co_dmw',
            'co2_dmw',
            'co_mole',
            'o2_mole',
            'co2_mole',
            'n2_mole',
            'nox_dmw',
            'so2_dmw',
            'rata_suhu_cerobong',
            // 'volume_sampel_gas_standar',
            'rata_suhu_gas_standar',
            'uap_air_dalam_aliran_gas_hide',
            'konstanta_2',
            'konstanta_4',
            'konstanta_5',
            'konsentrasi_co',
            'konsentrasi_co2',
            'konsentrasi_o2',
            'konsentrasi_nox',
            'konsentrasi_so2',
            'co_method3',
            'co2_method3',
            'o2_method3',
            'n2_method3',
            'rata_rata_tekanan_pitot_method_2',
            'rata_rata_tekanan_pitot_method_5',
        ];

        // Cleanup unwanted keys
        foreach ($removeKeys as $key) {
            unset($data[$key]);
        }

        // Build structured output
        $result = [];

        foreach ($data as $key => $value) {
            if (!isset($mapping[$key])) {
                continue;
            }

            [$label, $satuan, $method] = $mapping[$key];

            $result[] = [
                'parameter' => $label,
                'hasil_uji' => $value,
                'baku_mutu' => '-',
                'satuan' => $satuan,
                'spesifikasi_metode' => $method,
            ];
        }

        return $result;
    }

    private function kumpulanSatuan($satuan)
    {
        $satuanIndexMap = [
            "μg/Nm³"   => "",
            "μg/Nm3"   => "",

            "mg/nm³"   => 1,
            "mg/nm3"   => 1,
            "mg/Mm³"   => 1,
            "mg/Nm3"   => 1,
            "mg/Nm³"   => 1,
            "mg/Nm³"   => 1,

            "ppm"      => 2,
            "PPM"      => 2,

            "ug/m3"    => 3,
            "ug/m³"    => 3,

            "mg/m3"    => 4,
            "mg/m³"    => 4,
            "mg/m³"    => 4,

            "%"        => 5,
            "°C"       => 6,
            "g/gmol"   => 7,
            "m3/s"     => 8,
            "m/s"      => 9,
            "kg/tahun" => 10,
        ];

        return $satuanIndexMap[$satuan] ?? null;
    }

    private function getHasilUji($val, $satuan, $getHasilUji)
    {

        $cerobong = $val->ws_value_cerobong;
        $ws       = $cerobong->toArray();
        $index    = $this->kumpulanSatuan($satuan);
        $nilai    = null;

        if ($index === null) {
            $nilai = null;
            for ($i = 0; $i <= 10; $i++) {
                $key = $i === 0 ? 'f_koreksi_c' : 'f_koreksi_c' . $i;
                if (! empty($ws[$key])) {
                    $nilai = $ws[$key];
                    break;
                }
            }
            
            // Kalau belum ketemu, cari dari C...C10
            for ($i = 0; $i <= 10; $i++) {
                $key = $i === 0 ? 'C' : "C$i";

                // Khusus C3, kalau kosong ambil dari C3_persen
                if ($i === 3) {
                    $nilai = ! empty($ws[$key]) ? $ws[$key] : ($ws['C3_persen'] ?? null);
                } elseif (! empty($ws[$key])) {
                    $nilai = $ws[$key];
                }

                if (! empty($nilai)) {
                    break;
                }

            }

            $nilai = $nilai ?? '-';
        } else {
            // $hasilKey = "C$index";

            // $nilai = $ws[$hasilKey] ?? $ws[$hasilKey] ?? '-';
            $hasilKey = "C$index";
            $fkoreksiKey = "f_koreksi_c$index";
            $nilai = $ws[$fkoreksiKey] ?? $ws[$hasilKey] ?? '-';
        }
        
        return $getHasilUji($index, Parameter::where(['id_kategori' => 5, 'nama_lab' => $val->parameter, 'is_active' => true])->first()->id, $nilai);

    }

    private function getKoreksi($val, $satuan)
    {

        $cerobong = $val->ws_value_cerobong;
        $ws       = $cerobong->toArray();
        $index    = $this->kumpulanSatuan($satuan);
        $nilai    = null;

        if ($index === null) {
            // Cari dari f_koreksi_c...f_koreksi_c10
            for ($i = 0; $i <= 10; $i++) {
                $key = $i === 0 ? 'f_koreksi_c' : "f_koreksi_c$i";
                if (! empty($ws[$key])) {
                    $nilai = $ws[$key];
                    break;
                }
            }

            // Kalau belum ketemu, cari dari C...C1

            $nilai = $nilai ?? '-';
        } else {
            $fKoreksiKey = "f_koreksi_c$index";

            $nilai = $ws[$fKoreksiKey] ?? $ws[$fKoreksiKey] ?? '-';
        }

        if ($ws['nil_koreksi'] !== null && $ws['nil_koreksi'] !== '') {
            $nilai = $ws['nil_koreksi'] ?? '-';
        }

        return $nilai;

    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {

            $data = OrderDetail::where('id', $request->id)->first();

            $kategori3 = $data->kategori_3;
            $category2 = (int) explode('-', $kategori3)[0];
            $lhps = LhpsEmisiIsokinetikHeader::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();

            if ($lhps) {
                $lhpsHistory = $lhps->replicate();
                $lhpsHistory->setTable((new LhpsEmisiIsokinetikHeaderHistory())->getTable());
                $lhpsHistory->created_at = $lhps->created_at;
                $lhpsHistory->update_at  = $lhps->updated_at;
                $lhpsHistory->delete_at  = Carbon::now()->format('Y-m-d H:i:s');
                $lhpsHistory->delete_by  = $this->karyawan;
                $lhpsHistory->save();

                $oldDetails = LhpsEmisiIsokinetikDetail::where('id_header', $lhps->id)->get();
                foreach ($oldDetails as $detail) {
                    $detailHistory = $detail->replicate();
                    $detailHistory->setTable((new LhpsEmisiIsokinetikDetailHistory())->getTable());
                    $detailHistory->created_by = $this->karyawan;
                    $detailHistory->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $detailHistory->save();
                }

                foreach ($oldDetails as $detail) {
                    $detail->delete();
                }

                $lhps->delete();
            }

            $data->status = 1;
            $data->save();

            DB::commit();
            return response()->json([
                'status'  => 'success',
                'message' => 'Data draft no sample ' . $data->no_sampel . ' berhasil direject',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ]);
        }
    }

    public function encrypt($data)
    {
        $ENCRYPTION_KEY       = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey        = base64_decode($ENCRYPTION_KEY);
        $InitializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length($ENCRYPTION_ALGORITHM));
        $EncryptedText        = openssl_encrypt($data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $return               = base64_encode($EncryptedText . '::' . $InitializationVector);
        return $return;
    }

    public function decrypt($data = null)
    {
        $ENCRYPTION_KEY                              = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM                        = 'AES-256-CBC';
        $EncryptionKey                               = base64_decode($ENCRYPTION_KEY);
        list($Encrypted_Data, $InitializationVector) = array_pad(explode('::', base64_decode($data), 2), 2, null);
        $data                                        = openssl_decrypt($Encrypted_Data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $extand                                      = explode("|", $data);
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
                $data             = LhpsEmisiHeader::where('id', $request->id)->first();
                $data->is_emailed = true;
                $data->emailed_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->emailed_by = $this->karyawan;
            } else if ($request->kategori == 34) {
                $data             = LhpsEmisiIsokinetikHeader::where('id', $request->id)->first();
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
                    'message' => 'Email berhasil dikirim',
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Email gagal dikirim',
                ], 400);
            }
        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function getTechnicalControl(Request $request)
    {
        try {
            $data = MasterKaryawan::where('id_department', 17)->select('jabatan', 'nama_lengkap')->get();
            return response()->json([
                'status' => true,
                'data'   => $data,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line'    => $th->getLine(),
            ], 500);
        }
    }

    public function handleApprove(Request $request, $isManual = true)
    {
        try {
            if ($isManual) {
                $konfirmasiLhp = KonfirmasiLhp::where('no_lhp', $request->no_lhp)->first();

                if (! $konfirmasiLhp) {
                    $konfirmasiLhp             = new KonfirmasiLhp();
                    $konfirmasiLhp->created_by = $this->karyawan;
                    $konfirmasiLhp->created_at = Carbon::now()->format('Y-m-d H:i:s');
                } else {
                    $konfirmasiLhp->updated_by = $this->karyawan;
                    $konfirmasiLhp->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                }

                $konfirmasiLhp->no_lhp                      = $request->no_lhp;
                $konfirmasiLhp->is_nama_perusahaan_sesuai   = $request->nama_perusahaan_sesuai;
                $konfirmasiLhp->is_alamat_perusahaan_sesuai = $request->alamat_perusahaan_sesuai;
                $konfirmasiLhp->is_no_sampel_sesuai         = $request->no_sampel_sesuai;
                $konfirmasiLhp->is_no_lhp_sesuai            = $request->no_lhp_sesuai;
                $konfirmasiLhp->is_regulasi_sesuai          = $request->regulasi_sesuai;
                $konfirmasiLhp->is_qr_pengesahan_sesuai     = $request->qr_pengesahan_sesuai;
                $konfirmasiLhp->is_tanggal_rilis_sesuai     = $request->tanggal_rilis_sesuai;

                $konfirmasiLhp->save();
            }
            $data = LhpsEmisiIsokinetikHeader::where('no_lhp', $request->no_lhp)
                ->where('is_active', true)
                ->first();

            $noSampel = array_map('trim', explode(',', $request->noSampel));
            $no_lhp   = $data->no_lhp;

            $detail = LhpsEmisiIsokinetikDetail::where('id_header', $data->id)->get();

            $qr = QrDocument::where('id_document', $data->id)
                ->where('type_document', 'LHP_EMISI_ISOKINETIK')
                ->where('is_active', 1)
                ->where('file', $data->file_qr)
                ->orderBy('id', 'desc')
                ->first();

            if ($data != null) {
                OrderDetail::where('cfr', $request->no_lhp)
                    ->whereIn('no_sampel', $noSampel)
                    ->where('is_active', true)
                    ->update([
                        'is_approve'  => 1,
                        'status'      => 3,
                        'approved_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'approved_by' => $this->karyawan,
                    ]);

                $data->is_approve  = 1;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->approved_by = $this->karyawan;

                $data->save();

                HistoryAppReject::insert([
                    'no_lhp'      => $data->no_lhp,
                    'no_sampel'   => $request->noSampel,
                    'kategori_2'  => $data->id_kategori_2,
                    'kategori_3'  => $data->id_kategori_3,
                    'menu'        => 'Draft Emisi Sumber Tidak Bergerak',
                    'status'      => 'approved',
                    'approved_at' => Carbon::now(),
                    'approved_by' => $this->karyawan,
                ]);

                if ($qr != null) {
                    $dataQr                     = json_decode($qr->data);
                    $dataQr->Tanggal_Pengesahan = Carbon::now()->format('Y-m-d H:i:s');
                    $dataQr->Disahkan_Oleh      = $data->nama_karyawan;
                    $dataQr->Jabatan            = $data->jabatan_karyawan;
                    $qr->data                   = json_encode($dataQr);
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

                $orderHeader = OrderHeader::where('id', $cekDetail->id_order_header)
                ->first();

                EmailLhpRilisHelpers::run([
                    'cfr'              => $request->cfr,
                    'no_order'         => $data->no_order,
                    'nama_pic_order'   => $orderHeader->nama_pic_order ?? '-',
                    'nama_perusahaan'  => $data->nama_pelanggan,
                    'periode'          => $cekDetail->periode,
                    'karyawan'         => $this->karyawan
                ]);
            }

            DB::commit();
            return response()->json([
                'data'    => $data,
                'status'  => true,
                'message' => 'Data draft Emisi Sumber Tidak Bergerak no LHP ' . $no_lhp . ' berhasil diapprove',
            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            dd($th);
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'status'  => false,
            ], 500);
        }
    }
}