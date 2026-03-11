<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;
use App\Models\MasterRegulasi;

use App\Models\LhpsPencahayaanHeader;

use App\Models\LhpsPencahayaanDetail;

use App\Helpers\EmailLhpRilisHelpers;

use App\Models\LhpsPencahayaanHeaderHistory;
use App\Models\LhpsPencahayaanDetailHistory;


use App\Models\MasterSubKategori;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\QrDocument;
use App\Models\PencahayaanHeader;


use App\Models\GenerateLink;
use App\Services\PrintLhp;
use App\Services\SendEmail;
use App\Services\LhpTemplate;
use App\Services\GenerateQrDocumentLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Jobs\CombineLHPJob;
use App\Models\KonfirmasiLhp;
use App\Models\LhpsPencahayaanCustom;
use App\Models\LinkLhp;
use App\Models\PengesahanLhp;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DraftUdaraPencahayaanController extends Controller
{

    public function index(Request $request)
    {
        DB::statement("SET SESSION sql_mode = ''");
        DB::statement("SET SESSION group_concat_max_len = 1000000");
        $data = OrderDetail::with([
            'lhps_pencahayaan',
            'orderHeader'
            => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->where('kategori_3', "28-Pencahayaan")
            ->groupBy('cfr')
            ->where('status', 2)
            ->get();

        return Datatables::of($data)
            ->editColumn('lhps_pencahayaan', function ($data) {
                if (is_null($data->lhps_pencahayaan)) {
                    return null;
                } else {
                    $data->lhps_pencahayaan->metode_sampling = $data->lhps_pencahayaan->metode_sampling != null ? json_decode($data->lhps_pencahayaan->metode_sampling) : null;
                    return json_decode($data->lhps_pencahayaan, true);
                }
            })
            ->make(true);
    }

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
                $header = LhpsPencahayaanHeader::find($request->id_lhp);

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

    private function cleanArrayKeys($arr)
    {
        if (!$arr) return [];
        $cleanedKeys = array_map(fn($k) => trim($k, " '\""), array_keys($arr));
        return array_combine($cleanedKeys, array_values($arr));
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // Pencahayaan
            $header = LhpsPencahayaanHeader::where('no_lhp', $request->no_lhp)
                ->where('no_order', $request->no_order)
                ->where('id_kategori_3', 28)
                ->where('is_active', true)
                ->first();

            if ($header == null) {
                $header = new LhpsPencahayaanHeader;
                $header->created_by = $this->karyawan;
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
            } else {
                $history = $header->replicate();
                $history->setTable((new LhpsPencahayaanHeaderHistory())->getTable());
                $history->created_by = $this->karyawan;
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->updated_by = null;
                $history->updated_at = null;
                $history->save();
                $header->updated_by = $this->karyawan;
                $header->updated_at = Carbon::now()->format('Y-m-d H:i:s');
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

            $parameter_uji = !empty($request->parameter_header) ? explode(', ', $request->parameter_header) : [];

            $header->no_order         = $request->no_order ?: null;
            $header->no_sampel        = !empty($request->no_sampel) ? implode(', ', $request->no_sampel) : null;
            $header->no_lhp           = $request->no_lhp ?: null;
            $header->no_qt            = $request->no_penawaran ?: null;
            $header->nama_pelanggan   = $request->nama_perusahaan ?: null;
            $header->alamat_sampling  = $request->alamat_sampling ?: null;
            $header->parameter_uji    = json_encode($parameter_uji);
            $header->id_kategori_2    = 4;
            $header->id_kategori_3    = 28;
            $header->deskripsi_titik  = $request->deskripsi_titik ?: null;
            $header->sub_kategori     = $request->jenis_sampel ?: null;
            $header->metode_sampling  = $request->metode_sampling ? json_encode($request->metode_sampling) : null;
            $header->tanggal_sampling = $request->tanggal_terima ?: null;
            $header->nama_karyawan    = $nama_perilis;
            $header->jabatan_karyawan = $jabatan_perilis;
            $header->regulasi         = $request->regulasi ? json_encode($request->regulasi) : null;
            $header->regulasi_custom  = ($request->regulasi_custom != null) ? json_encode($request->regulasi_custom) : null;
            $header->tanggal_lhp      = $request->tanggal_lhp ?: null;
            $header->created_by       = $this->karyawan;
            $header->created_at       = Carbon::now()->format('Y-m-d H:i:s');
            $header->save();

            $detail = LhpsPencahayaanDetail::where('id_header', $header->id)->first();
            if ($detail != null) {
                $history = $detail->replicate();
                $history->setTable((new LhpsPencahayaanDetailHistory())->getTable());
                $history->created_by = $this->karyawan;
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->save();
                $detail = LhpsPencahayaanDetail::where('id_header', $header->id)->delete();
            }
            
            $cleaned_param              = $this->cleanArrayKeys($request->param) ?? [];
            $cleaned_lokasi             = $this->cleanArrayKeys($request->lokasi);
            $cleaned_sumber_cahaya      = $this->cleanArrayKeys($request->sumber_cahaya);
            $cleaned_noSampel           = $this->cleanArrayKeys($request->no_sampel);
            $cleaned_hasil_uji          = $this->cleanArrayKeys($request->hasil_uji ?? []);
            $cleaned_tanggal_sampling   = $this->cleanArrayKeys($request->tanggal_sampling ?? []);
            $cleaned_jenis_pengukuran   = $this->cleanArrayKeys($request->jenis_pengukuran);
            
            foreach ($request->no_sampel as $key => $val) {
                if (array_key_exists($val, $cleaned_noSampel)) {
                    $detail = new LhpsPencahayaanDetail;
                    $detail->id_header = $header->id;
                    $detail->param = $cleaned_param[$val];
                    $detail->no_sampel = $cleaned_noSampel[$val];
                    $detail->lokasi_keterangan = $cleaned_lokasi[$val];
                    $detail->hasil_uji = $cleaned_hasil_uji[$val];
                    $detail->sumber_cahaya = $cleaned_sumber_cahaya[$val];
                    $detail->jenis_pengukuran = $cleaned_jenis_pengukuran[$val];
                    $detail->tanggal_sampling = $cleaned_tanggal_sampling[$val];
                    $detail->save();
                }
            }

            // === 6. Handle custom ===
            LhpsPencahayaanCustom::where('id_header', $header->id)->delete();

            $custom = isset($request->regulasi_custom) && !empty($request->regulasi_custom);
            if($custom){
                foreach ($request->regulasi_custom as $key => $val) {
                    $custom_cleaned_param      = $this->cleanArrayKeys($request->custom_param[$key]);
                    $custom_cleaned_lokasi     = $this->cleanArrayKeys($request->custom_lokasi[$key]);
                    $custom_cleaned_noSampel   = $this->cleanArrayKeys($request->custom_no_sampel[$key]);
                    $custom_cleaned_hasil_uji  = $this->cleanArrayKeys($request->custom_hasil_uji[$key] ?? []);
                    $custom_cleaned_tanggal_sampling  = $this->cleanArrayKeys($request->custom_tanggal_sampling[$key] ?? []);
                    $custom_cleaned_jenis_pengukuran   = $this->cleanArrayKeys($request->custom_jenis_pengukuran[$key]);
                    $custom_cleaned_sumber_cahaya      = $this->cleanArrayKeys($request->custom_sumber_cahaya[$key]);
                    
                    foreach ($request->custom_no_sampel[$key] as $idx => $val) {
                        if (array_key_exists($val, $custom_cleaned_noSampel)) {
                            $custom = new LhpsPencahayaanCustom;
                            $custom->id_header = $header->id;
                            $custom->page = number_format($key);
                            $custom->no_sampel = $custom_cleaned_noSampel[$val];
                            $custom->param = $custom_cleaned_param[$val];
                            $custom->lokasi_keterangan = $custom_cleaned_lokasi[$val];
                            $custom->hasil_uji = $custom_cleaned_hasil_uji[$val];
                            $custom->sumber_cahaya = $custom_cleaned_sumber_cahaya[$val];
                            $custom->jenis_pengukuran = $custom_cleaned_jenis_pengukuran[$val];
                            $custom->tanggal_sampling = $custom_cleaned_tanggal_sampling[$val];
                            
                            $custom->save();
                        }
                    }
                }
            }
            

            if ($header != null) {
                $file_qr = new GenerateQrDocumentLhp();
                $file_qr = $file_qr->insert('LHP_PENCAHAYAAN', $header, $this->karyawan);
                if ($file_qr) {
                    $header->file_qr = $file_qr;
                    $header->save();
                }
            }

            if (!$header->file_qr) {
                $file_qr = new GenerateQrDocumentLhp();
                if ($path = $file_qr->insert('LHP_PENCAHAYAAN', $header, $this->karyawan)) {
                    $header->file_qr = $path;
                    $header->save();
                }
            }

            $groupedByPage = collect(LhpsPencahayaanCustom::where('id_header', $header->id)->get())
                ->groupBy('page')
                ->toArray();

            $renderDetail = LhpsPencahayaanDetail::where('id_header', $header->id)->orderBy('no_sampel')->get();

            $renderDetail = collect($renderDetail)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc']
            ])->values()->toArray();
            
            $fileName = LhpTemplate::setDataDetail($renderDetail)
                ->setDataHeader($header)
                ->setDataCustom($groupedByPage)
                ->useLampiran(true)
                ->whereView('DraftPencahayaan')
                ->render('downloadLHPFinal');

            $header->file_lhp = $fileName;

            $header->save();

            DB::commit();
            return response()->json([
                'message' => 'Data draft LHP Pencahayaan no LHP ' . $request->no_lhp . ' berhasil disimpan',
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
            $cek_lhp = LhpsPencahayaanHeader::with('lhpsPencahayaanDetail', 'lhpsPencahayaanCustom')
                ->where('is_active', true)
                ->where('no_lhp', $request->cfr)
                ->first();

            // ==============================
            // CASE 1: Jika ada cek_lhp
            // ==============================
            if ($cek_lhp) {
                $data_entry = [];
                $data_custom = [];
                $cek_regulasi = [];

                // Ambil data detail dari LHP (existing entry)
                foreach ($cek_lhp->lhpsPencahayaanDetail as $val) {
                    // if($val->no_sampel == 'AARG012503/024')dd($val);
                    $data_entry[] = [
                        'id' => $val->id,
                        'no_sampel' => $val->no_sampel,
                        'param' => $val->param,
                        'lokasi_keterangan' => $val->lokasi_keterangan,
                        'hasil_uji' => $val->hasil_uji,
                        'sumber_cahaya' => $val->sumber_cahaya,
                        'jenis_pengukuran' => $val->jenis_pengukuran,
                        'nab' => $val->nab,
                        'tanggal_sampling' => $val->tanggal_sampling,
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
                if (!empty($cek_lhp->lhpsPencahayaanDetail) && !empty($cek_lhp->regulasi_custom)) {
                    $regulasi_custom = json_decode($cek_lhp->regulasi_custom, true);

                    // Mapping regulasi id
                    if (!empty($cek_regulasi)) {
                        $mapRegulasi = collect($cek_regulasi)->pluck('id', 'regulasi')->toArray();
                        $regulasi_custom = array_map(function ($item) use (&$mapRegulasi) {
                            $regulasi_clean = preg_replace('/\*+/', '', $item);
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
                    foreach ($cek_lhp->lhpsPencahayaanCustom as $val) {
                        $groupedCustom[$val->page][] = $val;
                    }

                    // Urutkan regulasi_custom berdasarkan page
                    // usort($regulasi_custom, fn($a, $b) => $a['page'] <=> $b['page']);

                    // Bentuk data_custom
                    foreach ($regulasi_custom as $item) {
                        if (empty($item['page']))
                            continue;
                        // $id_regulasi = "id_" . $item['id'];
                        $id_regulasi = (string) "id_" . explode('-', $item['regulasi'])[0];
                        $page = $item['page'];

                        if (!empty($groupedCustom[$page])) {
                            foreach ($groupedCustom[$page] as $val) {
                                $data_custom[$id_regulasi][] = [
                                    'id' => $val->id,
                                    'no_sampel' => $val->no_sampel,
                                    'param' => $val->param,
                                    'lokasi_keterangan' => $val->lokasi_keterangan,
                                    'hasil_uji' => $val->hasil_uji,
                                    'sumber_cahaya' => $val->sumber_cahaya,
                                    'jenis_pengukuran' => $val->jenis_pengukuran,
                                    'nab' => $val->nab,
                                    'tanggal_sampling' => $val->tanggal_sampling,
                                ];
                            }
                        }
                    }
                }

                // ==============================
                // Ambil mainData & otherRegulations
                // ==============================
                $mainData = [];
                $otherRegulations = [];

                $data = PencahayaanHeader::with('ws_udara', 'lapangan_cahaya', 'master_parameter')
                    ->whereIn('no_sampel', $noSampel)
                    ->where('is_approved', 1)
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

                $bulanMap = [
                    'Januari' => 'January',
                    'Februari' => 'February',
                    'Maret' => 'March',
                    'April' => 'April',
                    'Mei' => 'May',
                    'Juni' => 'June',
                    'Juli' => 'July',
                    'Agustus' => 'August',
                    'September' => 'September',
                    'Oktober' => 'October',
                    'November' => 'November',
                    'Desember' => 'December',
                ];



                $data_entry = collect($data_entry)
                    ->sortBy(function ($item) use ($bulanMap) {
                        $tgl = str_replace(array_keys($bulanMap), array_values($bulanMap), $item['tanggal_sampling']);
                        return sprintf('%010d-%s', Carbon::parse($tgl)->timestamp, $item['no_sampel']);
                    })
                    ->values()
                    ->toArray();

                return response()->json([
                    'status' => true,
                    'data' => $data_entry,
                    'next_page' => $data_custom,
                ], 201);
            }

            // ==============================
            // CASE 2: Jika tidak ada cek_lhp
            // ==============================
            $mainData = [];
            $otherRegulations = [];

            $data = PencahayaanHeader::with('ws_udara', 'lapangan_cahaya', 'master_parameter')
                ->whereIn('no_sampel', $noSampel)
                ->where('is_approved', 1)
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

            // Sort mainData
            $mainData = collect($mainData)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc']
            ])->values()->toArray();
            // Sort otherRegulations
            foreach ($otherRegulations as $id => $regulations) {
                $otherRegulations[$id] = collect($regulations)->sortBy(fn($item) => mb_strtolower($item['no_sampel']))->values()->toArray();
            }

            return response()->json([
                'status' => true,
                'data' => $mainData,
                'next_page' => $otherRegulations,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }


    private function formatEntry($val)
    {
        $entry = [
            'id' => $val->id,
            'param' => $val->parameter,
        ];
        // Cek apakah getaran personal
        if (in_array($val->parameter, ["Pencahayaan"])) {
            $cahaya = isset($val->lapangan_cahaya) ? $val->lapangan_cahaya : null;
            $wsUdara = isset($val->ws_udara) ? $val->ws_udara : null;
            $tanggal_sampling = OrderDetail::where('no_sampel', $val->no_sampel)->where('is_active', 1)->first()->tanggal_sampling;

            return array_merge($entry, [
                'hasil_uji' => ($wsUdara && $wsUdara->hasil1)
                    ? (is_array(json_decode($wsUdara->hasil1, true))
                        ? json_decode($wsUdara->hasil1, true)
                        : str_replace(',', '', $wsUdara->hasil1))
                    : null,
                'param' => $val ? $val->parameter : null,
                'no_sampel' => $cahaya ? $cahaya->no_sampel : null,
                'sumber_cahaya' => $cahaya ? $cahaya->jenis_cahaya : null,
                'jenis_pengukuran' => $cahaya->kategori == 'Pencahayaan Umum' ? 'Umum' : 'Lokal',
                'lokasi_keterangan' => trim((($cahaya && $cahaya->keterangan) ? $cahaya->keterangan : '')),
                'nab' => $wsUdara ? $wsUdara->nab : null,
                'tanggal_sampling' => $tanggal_sampling
            ]);
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

            $data = LhpsPencahayaanHeader::where('no_lhp', $request->no_lhp)
                ->where('is_active', true)
                ->first();
            $noSampel = array_map('trim', explode(',', $request->noSampel));
            $no_lhp = $data->no_lhp;

            $detail = LhpsPencahayaanDetail::where('id_header', $data->id)->get();

            $qr = QrDocument::where('id_document', $data->id)
                ->where('type_document', 'LHP_PENCAHAYAAN')
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
                
                $orderHeader = OrderHeader::where('id', $cekDetail->id_order_header)
                ->first();

                EmailLhpRilisHelpers::run([
                    'cfr'              => $data->no_lhp,
                    'no_order'         => $data->no_order,
                    'nama_pic_order'   => $orderHeader->nama_pic_order ?? '-',
                    'nama_perusahaan'  => $data->nama_pelanggan,
                    'periode'          => $cekDetail->periode,
                    'karyawan'         => $this->karyawan
                ]);

            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Data draft LHP Pencahayaan no LHP ' . $no_lhp . ' tidak ditemukan',
                    'status' => false
                ], 404);
            }

            DB::commit();
            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Data draft LHP Pencahayaan no LHP ' . $no_lhp . ' berhasil diapprove'

            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'status' => false
            ], 500);
        }
    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {

            $lhps = LhpsPencahayaanHeader::where('id', $request->id)
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

                // History Header
                $lhpsHistory = $lhps->replicate();
                $lhpsHistory->setTable((new LhpsPencahayaanHeaderHistory())->getTable());
                $lhpsHistory->created_at = $lhps->created_at;
                $lhpsHistory->updated_at = $lhps->updated_at;
                $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $lhpsHistory->deleted_by = $this->karyawan;
                $lhpsHistory->save();

                // History Detail
                $oldDetails = LhpsPencahayaanDetail::where('id_header', $lhps->id)->get();
                foreach ($oldDetails as $detail) {
                    $detailHistory = $detail->replicate();
                    $detailHistory->setTable((new LhpsPencahayaanDetailHistory())->getTable());
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
                'message' => 'Data draft Pencahayaan no LHP ' . ($no_lhp ?? '-') . ' berhasil direject'
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
            $header = LhpsPencahayaanHeader::where('no_lhp', $request->no_lhp)
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
                        'quotation_status' => 'draft_pencahayaan',
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
            $header = LhpsPencahayaanHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();

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
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'draft_pencahayaan', 'type' => 'draft'])->first();

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
                LhpsPencahayaanHeader::where('id', $request->id)->update([
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
    public function updateTanggalLhp(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataHeader = LhpsPencahayaanHeader::find($request->id);

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
            $detail = LhpsPencahayaanDetail::where('id_header', $dataHeader->id)->get();
            $groupedByPage = collect(LhpsPencahayaanCustom::where('id_header', $dataHeader->id)->get())
                ->groupBy('page')
                ->toArray();

            $fileName = LhpTemplate::setDataDetail($detail)
                ->setDataHeader($dataHeader)
                ->setDataCustom($groupedByPage)
                ->useLampiran(true)
                ->whereView('DraftPencahayaan')
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
                'message' => 'Terjadi kesalahan: ' . $th->getMessage()
            ], 500);
        }
    }
}
