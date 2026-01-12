<?php

namespace App\Http\Controllers\api;

//models

use App\Helpers\EmailLhpRilisHelpers;
use App\Helpers\HelperSatuan;
use App\Http\Controllers\Controller;
use App\Jobs\CombineLHPJob;
use App\Models\EmisiCerobongHeader;
use App\Models\GenerateLink;
use App\Models\HistoryAppReject;
use App\Models\KonfirmasiLhp;
use App\Models\LhpsEmisiCCustom;
use App\Models\LhpsEmisiCDetail;
use App\Models\LhpsEmisiCDetailHistory;
use App\Models\LhpsEmisiCHeader;
use App\Models\LhpsEmisiCHeaderHistory;
use App\Models\LhpsEmisiDetail;
use App\Models\LhpsEmisiDetailHistory;
use App\Models\LhpsEmisiHeader;
use App\Models\LhpsEmisiHeaderHistory;
use App\Models\LinkLhp;
use App\Models\MasterBakumutu;
use App\Models\MasterKaryawan;
use App\Models\MasterRegulasi;
use App\Models\MdlEmisi;
use App\Models\MetodeSampling;
use App\Models\OrderDetail;

// service

use App\Models\Parameter;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;
use App\Models\Subkontrak;
use App\Services\GenerateQrDocumentLhp;
// job

use App\Services\LhpTemplate;
//iluminate

use App\Services\SendEmail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class DraftEmisiSumberTidakBergerakController extends Controller
{
    public function index(Request $request)
    {
        $data1 = OrderDetail::with('lhps_emisi', 'orderHeader', 'lhps_emisi_c', 'dataLapanganEmisiCerobong')
            // ->select('cfr', 'no_order', 'nama_perusahaan', 'no_quotation', 'kategori_3', 'kategori_2', 'tanggal_sampling', 'tanggal_terima', DB::raw('group_concat(no_sampel) as no_sampel'))
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('status', 2)
            ->where('kategori_2', '5-Emisi')
            ->whereIn('kategori_3', ['34-Emisi Sumber Tidak Bergerak'])
            ->where('parameter', 'not like', '%Iso-%');
        // ->groupBy('cfr', 'no_order', 'nama_perusahaan', 'no_quotation', 'kategori_3', 'kategori_2', 'tanggal_sampling', 'tanggal_terima');

        // if ($request->kategori == 'ESTB') {
        //     $data1 = OrderDetail::with('orderHeader', 'dataLapanganEmisiKendaraan', 'lhps_emisi_c')
        //         ->where('is_approve', 0)
        //         ->where('is_active', true)
        //         ->where('status', 2)
        //         ->where('kategori_2', '5-Emisi')
        //         ->where('kategori_3', '34-Emisi Sumber Tidak Bergerak');
        // }
        return Datatables::of($data1)->make(true);
    }

    public function handleSubmitDraft(Request $request)
    {
        DB::beginTransaction();
        // if ($request->category2 == 34) {
        try {
            $header = LhpsEmisiCHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();

            // Kode Lama
            // if (!$header) {
            //     $header = new LhpsEmisiCHeader();
            // }

            if ($header == null) {
                $header = new LhpsEmisiCHeader();
            } else {
                $history = $header->replicate();
                $history->setTable((new LhpsEmisiCHeaderHistory())->getTable());
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->save();
            }

            // dd($request->all());
            $parameter_uji = explode(', ', $request->parameter_uji);
            // $keterangan = [];
            // if ($request->keterangan) {
            //     foreach ($request->keterangan as $key => $value) {
            //         if ($value != '')
            //             array_push($keterangan, $value);
            //     }
            // }
            try {
                $regulasi_custom = collect($request->regulasi_custom ?? [])->map(function ($item, $page) use ($request) {
                    return [
                        'page'     => (int) $page,
                        'regulasi' => trim($item),
                        'id'       => $request->regulasi_custom_id[$page],
                    ];
                })->values()->toArray();

                $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->tanggal_lhp)
                    ->orderByDesc('berlaku_mulai')
                    ->first();

                $nama_perilis = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
                $jabatan_perilis = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';

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
                $header->tanggal_sampling = $request->tanggal_sampling ?: null;
                $header->tanggal_terima   = $request->tanggal_terima ?: null;
                $header->tanggal_tugas    = $request->tanggal_tugas ?: null;
                $header->periode_analisa  = $request->periode_analisa ?: null;
                $header->nama_karyawan    = $nama_perilis;
                $header->jabatan_karyawan = $jabatan_perilis;
                $header->header_table     = $request->header_table ? $request->header_table : null;
                $header->keterangan       = $request->keterangan ? json_encode($request->keterangan) : null;
                $header->deskripsi_titik  = $request->penamaan_titik ?: null;
                $header->titik_koordinat  = $request->titik_koordinat ?: null;
                //     $header->nama_karyawan = 'Dwi Meisya Batari';
                //     $header->jabatan_karyawan = 'Technical Control Manager';
                $header->regulasi        = $request->regulasi ? json_encode($request->regulasi) : null;
                $header->regulasi_custom = isset($regulasi_custom) ? json_encode($regulasi_custom) : null;
                $header->created_by      = $this->karyawan;
                $header->created_at      = Carbon::now()->format('Y-m-d H:i:s');
                $header->save();

                $param     = $request->parameter;
                $allDetail = []; // tampung semua detail

                foreach ($param as $key => $val) {
                    // Kode Lama
                    // $detail = LhpsEmisiCDetail::where('id_header', $header->id)
                    //     ->where('parameter', $val)
                    //     ->first();

                    // if (!$detail) {
                    //     $detail = new LhpsEmisiCDetail();
                    //     $detail->id_header = $header->id;
                    //     $detail->parameter = $val;
                    // }

                    $existing = LhpsEmisiCDetail::where('id_header', $header->id)
                        ->where('parameter', $val)
                        ->first();

                    if ($existing) {
                        $detailHistory = $existing->replicate();
                        $detailHistory->setTable((new LhpsEmisiCDetailHistory())->getTable());
                        $detailHistory->created_by = $this->karyawan;
                        $detailHistory->created_at = Carbon::now()->format('Y-m-d H:i:s');
                        $detailHistory->save();
                    }

                    if (! $existing) {
                        $detail            = new LhpsEmisiCDetail();
                        $detail->id_header = $header->id;
                        $detail->parameter = $val;
                    } else {
                        $detail = $existing;
                    }

                    $detail->akr           = $request->akr[$key] ?? null;
                    $detail->parameter_lab = $request->parameter_lab[$key] ?? null;
                    $detail->C             = $request->C[$key] ?? null;
                    // $detail->C1 = $request->C1[$key] ?? null;
                    // $detail->C2 = $request->C2[$key] ?? null;
                    $detail->terukur            = $request->terukur[$key] ?? null;
                    $detail->terkoreksi         = $request->terkoreksi[$key] ?? null;
                    $detail->attr               = $request->attr[$key] ?? null;
                    $detail->spesifikasi_metode = $request->spesifikasi_metode[$key] ?? null;
                    $detail->satuan             = $request->satuan[$key] ?? null;
                    $detail->baku_mutu          = $request->baku_mutu[$key] ?? null;

                    $detail->save();

                    $allDetail[] = $detail; // masukin setiap detail ke array
                }

                // dd($request->custom_parameter);

                LhpsEmisiCCustom::where('id_header', $header->id)->delete();
                if (isset($request->custom_parameter)) {
                    foreach ($request->custom_parameter as $page => $values) {
                        foreach ($values as $param => $val) {
                            $custom                = new LhpsEmisiCCustom();
                            $custom->id_header     = $header->id;
                            $custom->page          = $page;
                            $custom->parameter     = $param;
                            $custom->akr           = $request->custom_akr[$page][$param] ?? null;
                            $custom->parameter_lab = $request->custom_parameter_lab[$page][$param] ?? null;
                            $custom->C             = $request->custom_C[$page][$param] ?? null;
                            // $custom->C1 = $request->custom_C1[$page][$param] ?? null;
                            // $custom->C2 = $request->custom_C2[$page][$param] ?? null;
                            $custom->terukur            = $request->custom_terukur[$page][$param] ?? null;
                            $custom->terkoreksi         = $request->custom_terkoreksi[$page][$param] ?? null;
                            $custom->attr               = $request->custom_attr[$page][$param] ?? null;
                            $custom->spesifikasi_metode = $request->custom_methode[$page][$param] ?? null;
                            $custom->satuan             = $request->custom_satuan[$page][$param] ?? null;
                            $custom->baku_mutu          = $request->custom_baku_mutu[$page][$param] ?? null;

                            $custom->save();
                        }
                    }
                }

                if ($header != null) {

                    $file_qr = new GenerateQrDocumentLhp();
                    $file_qr = $file_qr->insert('LHP_EMISI_C', $header, $this->karyawan);
                    if ($file_qr) {
                        $header->file_qr = $file_qr;
                        $header->save();
                    }

                    $detail = LhpsEmisiCDetail::where('id_header', $header->id)->get();

                    $custom = LhpsEmisiCCustom::where('id_header', $header->id)
                        ->get()
                        ->groupBy('page')
                        ->toArray();

                    $view = 'DraftESTB';

                    $fileName = LhpTemplate::setDataHeader($header)
                        ->setDataDetail($detail)
                        ->setDataCustom($custom)
                        ->whereView($view)
                        ->render('downloadLHPFinal');

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
            $dataHeader = LhpsEmisiCHeader::find($request->id);

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
            $detail = LhpsEmisiCDetail::where('id_header', $dataHeader->id)->get();
            $custom = LhpsEmisiCCustom::where('id_header', $dataHeader->id)->get();

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
            $cek_lhp = LhpsEmisiCHeader::with('lhpsEmisiCDetail', 'lhpsEmisiCCustom')->where('no_sampel', $request->no_sampel)->first();
            if ($cek_lhp) {
                $data_entry   = [];
                $data_custom  = [];
                $cek_regulasi = [];
                $methodUsed   = [];

                foreach ($cek_lhp->lhpsEmisiCDetail->toArray() as $key => $val) {
                    $data_entry[$key] = [
                        'id'            => $val['id'],
                        'no_sampel'     => $request->no_sampel,
                        'parameter'     => $val['parameter'],
                        'parameter_lab' => $val['parameter_lab'],
                        'C'             => $val['C'],
                        'terkoreksi'    => $val['terkoreksi'],
                        'satuan'        => $val['satuan'],
                        'methode'       => $val['spesifikasi_metode'],
                        'baku_mutu'     => $val['baku_mutu'],
                        'akr'           => $val['akr'],
                    ];

                    $methodUsed[] = $val['spesifikasi_metode'];
                }

                if (isset($request->other_regulasi) && ! empty($request->other_regulasi)) {
                    $cek_regulasi = MasterRegulasi::whereIn('id', $request->other_regulasi)->select('id', 'peraturan as regulasi')->get()->toArray();
                }

                if (! empty($cek_lhp->lhpsEmisiCCustom) && ! empty($cek_lhp->regulasi_custom)) {
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
                    foreach ($cek_lhp->lhpsEmisiCCustom as $val) {
                        $groupedCustom[$val->page][] = $val;
                    }

                    // Isi data_custom
                    // Urutkan regulasi_custom berdasarkan page
                    usort($regulasi_custom, function ($a, $b) {
                        return $a['page'] <=> $b['page'];
                    });

                    foreach ($regulasi_custom as $item) {
                        if (empty($item['id']) || empty($item['page'])) {
                            continue;
                        }

                        $id_regulasi = (string) "id_" . $item['id'];
                        $page        = $item['page'];

                        if (! empty($groupedCustom[$page])) {
                            foreach ($groupedCustom[$page] as $val) {
                                $data_custom[$id_regulasi][] = [
                                    'id'            => $val['id'],
                                    'no_sampel'     => $request->no_sampel,
                                    'parameter'     => $val['parameter'],
                                    'parameter_lab' => $val['parameter_lab'],
                                    'C'             => $val['C'],
                                    'terkoreksi'    => $val['terkoreksi'],
                                    'satuan'        => $val['satuan'],
                                    'methode'       => $val['spesifikasi_metode'],
                                    'baku_mutu'     => $val['baku_mutu'],
                                    'akr'           => $val->akr,
                                ];
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
                    'next_page'          => $data_custom,
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

                $models = [
                    EmisiCerobongHeader::class,
                    Subkontrak::class,
                ];

                foreach ($models as $model) {
                    $approveField = $model === Subkontrak::class ? 'is_approve' : 'is_approved';
                    $parameter    = OrderDetail::where('no_sampel', $request->no_sampel)
                        ->where('is_active', true)
                        ->first()->parameter;

                    $parameterArray = json_decode($parameter, true);

                    $parameterNames = array_map(function ($param) {
                        $parts = explode(';', $param);
                        return trim(end($parts));
                    }, $parameterArray);

                    $data = $model::with('ws_value_cerobong', 'parameter_emisi')
                        ->where('no_sampel', $request->no_sampel)
                        ->where($approveField, 1)
                        ->where('is_active', true)
                        ->where('lhps', 1)
                        ->whereIn('parameter', $parameterNames)
                        ->get();

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

                    foreach ($data as $val) {
                        $entry      = $this->formatEntry($val, $request->regulasi, $methodsUsed, $getHasilUji);
                        $mainData[] = $entry;

                        if ($request->other_regulasi) {
                            foreach ($request->other_regulasi as $id_regulasi) {
                                $otherRegulations[$id_regulasi][] = $this->formatEntry($val, $id_regulasi, $methodsUsed, $getHasilUji);
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
                $methodsUsed    = array_values(array_unique($methodsUsed));
                $defaultMethods = Parameter::where('is_active', true)->where('id_kategori', 5)
                    ->whereNotNull('method')->groupBy('method')
                    ->pluck('method')->toArray();

                $resultMethods = array_values(array_unique(array_merge($methodsUsed, $defaultMethods)));

                return response()->json([
                    'status'             => true,
                    'data'               => $mainData,
                    'next_page'          => $otherRegulations,
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
        $bakumutu = MasterBakumutu::where('id_regulasi', $regulasiId)->where('parameter', $val->parameter_emisi->nama_lab)->first();
        $satuan     = $bakumutu ? $bakumutu->satuan : null;
        $akreditasi = $bakumutu && isset($bakumutu->akreditasi) ? $bakumutu->akreditasi : '';

        $entry = [
            'id'            => $val->id,
            'no_sampel'     => $val->no_sampel,
            'parameter'     => $val->parameter_emisi->nama_lhp ?? $val->parameter_emisi->nama_regulasi,
            'parameter_lab' => $val->parameter,
            'C'             => self::getHasilUji($val, $satuan, $getHasilUji),
            'terkoreksi'    => self::getKoreksi($val, $satuan),
            'satuan'        => $satuan,
            'methode'       => ! empty($bakumutu->method) ? $bakumutu->method : (! empty($val->method) ? $val->method : '-'),
            'baku_mutu'     => $val->baku_mutu->baku_mutu ?? '-',
            'akr'           => (
                ! empty($bakumutu)
                ? (str_contains($bakumutu->akreditasi, 'AKREDITASI') ? '' : 'ẍ')
                : 'ẍ'
            ),
        ];

        if ($bakumutu && $bakumutu->method) {
            $entry['satuan']    = $bakumutu->satuan;
            $entry['methode']   = $bakumutu->method;
            $entry['baku_mutu'] = $bakumutu->baku_mutu;
            $methodsUsed[]      = $bakumutu->method;
        }

        if($entry['C'] == '##'){
            $entry['terkoreksi'] = '-';
            $entry['methode'] = '-';
            $entry['baku_mutu'] = ['-'];
        }

        return $entry;
    }

    private function getHasilUji($val, $satuan, $getHasilUji)
    {

        $cerobong  = $val->ws_value_cerobong;
        $ws        = $cerobong->toArray();
        $getSatuan = new HelperSatuan;
        $index     = $getSatuan->emisi($satuan);
        $nilai     = null;
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
            if($nilai === null) {
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
            }

            $nilai = $nilai ?? '-';
        } else {
            $hasilKey = "C$index";
            $fkoreksiKey = "f_koreksi_c$index";
            $nilai = $ws[$fkoreksiKey] ?? $ws[$hasilKey] ?? '-';
        }
        
        return $getHasilUji($index, Parameter::where(['id_kategori' => 5, 'nama_lab' => $val->parameter, 'is_active' => true])->first()->id, $nilai);
    }
    
    private function getKoreksi($val, $satuan)
    {

        $cerobong  = $val->ws_value_cerobong;
        $ws        = $cerobong->toArray();
        $getSatuan = new HelperSatuan;
        $index     = $getSatuan->emisi($satuan);
        $nilai     = null;

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
                $lhps = LhpsEmisiHeader::where('no_lhp', $data->no_sampel)->where('is_active', true)->first();

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
                $lhps = LhpsEmisiCHeader::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();

                if ($lhps) {
                    $lhpsHistory = $lhps->replicate();
                    $lhpsHistory->setTable((new LhpsEmisiCHeaderHistory())->getTable());
                    $lhpsHistory->created_at = $lhps->created_at;
                    $lhpsHistory->update_at  = $lhps->updated_at;
                    $lhpsHistory->delete_at  = Carbon::now()->format('Y-m-d H:i:s');
                    $lhpsHistory->delete_by  = $this->karyawan;
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
                $data             = LhpsEmisiCHeader::where('id', $request->id)->first();
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
            $data = LhpsEmisiCHeader::where('no_lhp', $request->no_lhp)
                ->where('is_active', true)
                ->first();
            $noSampel = array_map('trim', explode(',', $request->noSampel));
            $no_lhp   = $data->no_lhp;

            $detail = LhpsEmisiCDetail::where('id_header', $data->id)->get();

            $qr = QrDocument::where('id_document', $data->id)
                ->where('type_document', 'LHP_EMISI_CEROBONG')
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
                // if ($data->count_print < 1) {
                //     $data->is_printed = 1;
                //     $data->count_print = $data->count_print + 1;
                // }
                // dd($data->id_kategori_2);

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

                EmailLhpRilisHelpers::run([
                    'cfr'              => $request->cfr,
                    'no_order'         => $data->no_order,
                    'nama_pic_order'   => $orderHeader->nama_pic_order ?? '-',
                    'nama_perusahaan'  => $data->nama_pelanggan,
                    'periode'          => $cekDetail->periode,
                    'karyawan'         => $this->karyawan
                ]);
                // $servicePrint = new PrintLhp();
                // $servicePrint->printByFilename($data->file_lhp, $detail);

                // if (!$servicePrint) {
                //     DB::rollBack();
                //     return response()->json(['message' => 'Gagal Melakukan Reprint Data', 'status' => '401'], 401);
                // }
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

    /* non active 
       public function handleRevisi(Request $request)
        {
            DB::beginTransaction();
            try {
                $header = LhpsEmisiCHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();

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
            } else if ($request->category == 34) {
                try {
                    $header = LhpsEmisiCHeader::where('no_sampel', $request->no_sampel)->where('is_active', 1)->first();
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
       versi sebelumnya :
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
                $method_regulasi = [];
                if ($request->category2 == 34) {
                    $cek_all = OrderDetail::with('orderHeader', 'lhps_emisi_c')->where('cfr', $cfr->cfr)->where('is_active', true)->get();

                    $regulasi = explode("-", json_decode($cfr->regulasi)[0]);
                    $datreg = MasterRegulasi::where('peraturan', $regulasi[1])->first();
                    $datas = array();

                    $datas1 = EmisiCerobongHeader::with([
                        'ws_value_cerobong',
                        'master_parameter'
                    ])
                        ->where('no_sampel', $cfr->no_sampel)
                        ->get();

                    if ($datas1->count() == 0) {
                        $datas = Subkontrak::with([
                            'ws_value_cerobong',
                            'master_parameter'
                        ])
                            ->where('no_sampel', $cfr->no_sampel)
                            ->get();
                    } else {
                        $datas2 = Subkontrak::with([
                            'ws_value_cerobong',
                            'master_parameter'
                        ])
                            ->where('no_sampel', $cfr->no_sampel)
                            ->get();

                        $datas = array_merge($datas1->toArray(), $datas2->toArray());
                    }

                    $i = 0;
                    $detailTable = array();

                    if ($datas != null) {
                        foreach ($datas as $key => $val) {
                            $detailTable[$i]['no_sampel'] = $val['no_sampel'] ?? null;
                            $detailTable[$i]['parameter'] = isset($val['master_parameter']) ? ($val['master_parameter']['nama_regulasi'] ?? null) : null;
                            $detailTable[$i]['parameter_lab'] = isset($val['master_parameter']) ? ($val['master_parameter']['nama_lab'] ?? null) : null;
                            $detailTable[$i]['C'] = $val['ws_value_cerobong']['C'] ?? null;
                            $detailTable[$i]['C1'] = $val['ws_value_cerobong']['C1'] ?? null;
                            $detailTable[$i]['C2'] = $val['ws_value_cerobong']['C2'] ?? null;
                            $detailTable[$i]['satuan'] = isset($val['master_parameter']) ? ($val['master_parameter']['satuan'] ?? null) : null;
                            $detailTable[$i]['spesifikasi_method'] = isset($val['master_parameter']) ? ($val['master_parameter']['method'] ?? null) : null;
                            $detailTable[$i]['baku_mutu'] = isset($val['master_parameter']) ? ($val['master_parameter']['baku_mutu'] ?? null) : null;

                            if ($datreg != null) {
                                $bakumutu = MasterBakumutu::where('id_regulasi', $datreg->id)
                                    ->where('parameter', $val['parameter'])
                                    ->first();

                                if ($bakumutu != null && $bakumutu->method != '') {
                                    $detailTable[$i]['satuan'] = $bakumutu->satuan;
                                    $detailTable[$i]['spesifikasi_method'] = $bakumutu->method;
                                    $detailTable[$i]['baku_mutu'] = json_decode($bakumutu->baku_mutu);
                                    array_push($method_regulasi, $bakumutu->method);
                                }
                            }
                            $i++;
                        }

                    }

                    $method_regulasi = array_values(array_unique($method_regulasi));

                    $method = Parameter::where('is_active', true)
                        ->where('id_kategori', 5)
                        ->whereNotNull('method')
                        ->select('method')
                        ->groupBy('method')
                        ->get()
                        ->toArray();
                    $result_method = array_unique(array_values(array_merge($method_regulasi, array_column($method, 'method'))));

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
                        'spesifikasi_method' => $result_method,
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
        public function index(Request $request)
        {
            DB::statement("SET SESSION sql_mode = ''");
            $data = OrderDetail::with([
                'lhps_emisi',
                'dataLapanganEmisiCerobong',
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
                ->whereIn('kategori_3', ['34-Emisi Sumber Tidak Bergerak'])
                ->groupBy('cfr')
                ->where('status', 2)
                ->get();

            return Datatables::of($data)
                ->editColumn('lhps_emisi_c', function ($data) {
                    if (is_null($data->lhps_emisi_c)) {
                        return null;
                    } else {
                        $data->lhps_emisi_c->metode_sampling = $data->lhps_emisi_c->metode_sampling != null ? json_decode($data->lhps_emisi_c->metode_sampling) : null;
                        return json_decode($data->lhps_emisi_c, true);
                    }
                })
                ->make(true);
        }
    */
}
