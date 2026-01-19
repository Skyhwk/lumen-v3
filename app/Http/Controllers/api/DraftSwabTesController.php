<?php
namespace App\Http\Controllers\api;

use App\Helpers\EmailLhpRilisHelpers;
use App\Helpers\HelperSatuan;
use App\Http\Controllers\Controller;
use App\Jobs\CombineLHPJob;
use App\Models\DataLapanganSwab;
use App\Models\HistoryAppReject;
use App\Models\KonfirmasiLhp;
use App\Models\LhpsSwabTesDetail;
use App\Models\LhpsSwabTesDetailHistory;
use App\Models\LhpsSwabTesHeader;
use App\Models\LhpsSwabTesHeaderHistory;
use App\Models\LinkLhp;
use App\Models\MasterBakumutu;
use App\Models\MicrobioHeader;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\Parameter;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;
use App\Models\SubKontrak;
use App\Models\SwabTestHeader;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class DraftSwabTesController extends Controller
{

    public function index(Request $request)
    {
        $data = OrderDetail::selectRaw('
            max(id) as id,
            max(id_order_header) as id_order_header,
            cfr,
            GROUP_CONCAT(no_sampel SEPARATOR ",") as no_sampel,
            MAX(nama_perusahaan) as nama_perusahaan,
            MAX(konsultan) as konsultan,
            MAX(no_quotation) as no_quotation,
            MAX(no_order) as no_order,
            MAX(parameter) as parameter,
            MAX(regulasi) as regulasi,
            GROUP_CONCAT(DISTINCT kategori_1 SEPARATOR ",") as kategori_1,
            MAX(kategori_2) as kategori_2,
            MAX(kategori_3) as kategori_3,
            GROUP_CONCAT(DISTINCT keterangan_1 SEPARATOR ",") as keterangan_1,
            GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ",") as tanggal_tugas,
            GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ",") as tanggal_terima
        ')
            ->with([
                'lhps_swab_udara',
                'orderHeader',
            ])
            ->where('is_active', true)
            ->where('kategori_3', '46-Udara Swab Test')
            ->where('status', 2)
            ->groupBy('cfr')
            ->get();
        $data = $data->map(function ($item) {
            // 1. Pecah no_sampel "S1,S2,S3" jadi array
            $noSampelList = array_filter(explode(',', $item->no_sampel));

            // 2. Ambil semua data lapangan untuk no_sampel tsb
            $lapangan = DataLapanganSwab::whereIn('no_sampel', $noSampelList)->get();

            // 3. Hitung min/max created_at
            $minDate = null;
            $maxDate = null;

            if ($lapangan->isNotEmpty()) {
                $minDate = $lapangan->min('created_at');
                $maxDate = $lapangan->max('created_at');
            }

            $lhps = $item->lhps_swab_udara;

            if (empty($lhps) || (
                empty($lhps->tanggal_sampling_awal) &&
                empty($lhps->tanggal_sampling_akhir) &&
                empty($lhps->tanggal_analisa_awal) &&
                empty($lhps->tanggal_analisa_akhir)
            )) {
                $item->tanggal_sampling_awal  = $minDate ? Carbon::parse($minDate)->format('Y-m-d') : null;
                $item->tanggal_sampling_akhir = $maxDate ? Carbon::parse($maxDate)->format('Y-m-d') : null;

                // tanggal_terima di hasil selectRaw bisa beberapa, pisah koma juga
                $tglTerima = $item->tanggal_terima;
                if (strpos($tglTerima, ',') !== false) {
                    $list = array_filter(explode(',', $tglTerima));
                    sort($list);
                    $tglTerima = $list[0]; // ambil paling awal
                }

                $item->tanggal_analisa_awal  = $tglTerima ?: null;
                $item->tanggal_analisa_akhir = Carbon::now()->format('Y-m-d');
            } else {
                $item->tanggal_sampling_awal  = $lhps->tanggal_sampling_awal;
                $item->tanggal_sampling_akhir = $lhps->tanggal_sampling_akhir;
                $item->tanggal_analisa_awal   = $lhps->tanggal_analisa_awal;
                $item->tanggal_analisa_akhir  = $lhps->tanggal_analisa_akhir;
            }

            return $item;
        });
        return Datatables::of($data)->make(true);
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
                'status'  => true,
                'message' => 'Available data retrieved successfully',
                'data'    => $method,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function handleMetodeParameter(Request $request)
    {
        $methode_parameter = Parameter::where('is_active', true)
            ->where('nama_kategori', 'Udara')->pluck('method')->toArray();

        return response()->json([
            'status'  => true,
            'message' => 'Available data retrieved successfully',
            'data'    => $methode_parameter,
        ], 200);
    }

    public function handleDatadetail(Request $request)
    {
        $id_category = explode('-', $request->kategori_3)[0];

        try {
            // Ambil LHP yang sudah ada
            $cekLhp = LhpsSwabTesHeader::where('no_lhp', $request->cfr)
                ->where('id_kategori_3', $id_category)
                ->where('is_active', true)
                ->first();

            $methode_parameter = Parameter::where('is_active', true)
                ->where('nama_kategori', 'Udara')->select('id', 'method')->limit(10)->get();

            // Ambil list no_sampel dari order yang memenuhi syarat
            $orders = OrderDetail::where('cfr', $request->cfr)
                ->where('is_approve', 0)
                ->where('is_active', true)
                ->where('kategori_2', '4-Udara')
                ->where('kategori_3', $request->kategori_3)
                ->where('status', 2)
                ->pluck('no_sampel');

            // Ambil data KebisinganHeader + relasinya
            $swabData = SwabTestHeader::with('ws_udara')
                ->whereIn('no_sampel', $orders)
                ->where('is_approved', 1)
                ->where('is_active', 1)
                ->where('lhps', 1)
                ->get();

            if ($swabData->isEmpty()) {
                $swabData = MicrobioHeader::with('ws_udara')
                    ->whereIn('no_sampel', $orders)
                    ->where('is_approved', 1)
                    ->where('is_active', 1)
                    ->where('lhps', 1)
                    ->get();
            }
            $swabData2 = Subkontrak::with('ws_udara', 'ws_value_linkungan')
                ->whereIn('no_sampel', $orders)
                ->where('is_approve', 1)
                ->where('is_active', 1)
                ->where('lhps', 1)
                ->get();

            $merge = $swabData->merge($swabData2);

            $regulasiList = is_array($request->regulasi) ? $request->regulasi : [];
            $getSatuan    = new HelperSatuan;

            // kalau cuma dikirim string, fallback ke array 1 elemen
            if (! is_array($request->regulasi) && ! empty($request->regulasi)) {
                $regulasiList = [$request->regulasi];
            }

            if (empty($regulasiList)) {
                $regulasiList = [null]; // Set null agar tetap loop sekali
            }

            $mappedData = [];

            // LOOP SETIAP REGULASI
            
            foreach ($regulasiList as $full_regulasi) {
                // contoh: "143-Peraturan Menteri Kesehatan Nomor 7 Tahun 2019"
                $id_regulasi   = null;
                $nama_regulasi = null;
                
                // Hanya parse jika regulasi tidak null
                if ($full_regulasi !== null) {
                    $parts_regulasi = explode('-', $full_regulasi, 2);
                    $id_regulasi    = $parts_regulasi[0] ?? null;
                    $nama_regulasi  = $parts_regulasi[1] ?? null;
                }

                // mapping setiap swabData terhadap regulasi ini
                $tmpData = $merge->map(function ($val) use ($id_regulasi, $nama_regulasi, $getSatuan) {
                    $keterangan        = OrderDetail::where('no_sampel', $val->no_sampel)->first()->keterangan_1 ?? null;
                    $parameterLab      = Parameter::where('nama_lab', $val->parameter)->first()->nama_lab ?? null;
                    $parameterRegulasi = Parameter::where('nama_lab', $val->parameter)->first()->nama_regulasi ?? null;
                    $parameterLhp      = Parameter::where('nama_lab', $val->parameter)->first()->nama_lhp ?? null;

                    $ws       = $val->ws_udara ?? $val->ws_value_linkungan ?? null;
                    $hasil    = $ws->toArray();
                    $orderRow = OrderDetail::where('no_sampel', $val->no_sampel)
                        ->where('is_active', 1)
                        ->first();

                    $tanggal_sampling = $orderRow->tanggal_sampling ?? null;

                    $bakumutu = MasterBakumutu::where('id_regulasi', $id_regulasi)
                        ->where('parameter', $val->parameter)
                        ->first();

                    $nilai = '-';
                    $index = $getSatuan->udara($bakumutu->satuan ?? null);

                    if ($index === null) {
                        // cari f_koreksi_1..17 dulu
                        for ($i = 1; $i <= 17; $i++) {
                            $key = "f_koreksi_$i";
                            if (isset($hasil[$key]) && $hasil[$key] !== '' && $hasil[$key] !== null) {
                                $nilai = $hasil[$key];
                                break;
                            }
                        }

                        // kalau masih kosong, cari hasil1..17
                        if ($nilai === '-' || $nilai === null || $nilai === '') {
                            for ($i = 1; $i <= 17; $i++) {
                                $key = "hasil$i";
                                if (isset($hasil[$key]) && $hasil[$key] !== '' && $hasil[$key] !== null) {
                                    $nilai = $hasil[$key];
                                    break;
                                }
                            }
                        }
                    } else {
                        $fKoreksiHasil = "f_koreksi_$index";
                        $fhasil        = "hasil$index";

                        if (isset($hasil[$fKoreksiHasil]) && $hasil[$fKoreksiHasil] !== '' && $hasil[$fKoreksiHasil] !== null) {
                            $nilai = $hasil[$fKoreksiHasil];
                        } elseif (isset($hasil[$fhasil]) && $hasil[$fhasil] !== '' && $hasil[$fhasil] !== null) {
                            $nilai = $hasil[$fhasil];
                        }
                    }
                    return [
                        'no_sampel'        => $val->no_sampel ?? null,
                        'parameter'        => $parameterLhp ?? $parameterRegulasi ?? null,
                        'nama_lab'         => $parameterLab ?? null,
                        'bakumutu'         => $bakumutu ? $bakumutu->baku_mutu : '-',
                        'satuan'           => (! empty($bakumutu->satuan)) ? $bakumutu->satuan : '-',
                        'methode'          => (! empty($bakumutu->method)) ? $bakumutu->method : (! empty($val->method) ? $val->method : '-'),
                        'hasil_uji'        => $nilai,
                        'tanggal_sampling' => $tanggal_sampling,
                        'keterangan'       => $keterangan,
                        'id_regulasi'      => $id_regulasi,
                        'nama_regulasi'    => $nama_regulasi,
                        'tanggal_terima'   => $val->tanggal_terima ?? null,
                        'akr'              => (! empty($bakumutu->akreditasi) && str_contains($bakumutu->akreditasi, 'AKREDITASI'))
                            ? ''
                            : 'ẍ',
                    ];
                })->toArray();

                $mappedData[] = [
                    "id_regulasi"   => $id_regulasi,
                    "nama_regulasi" => $nama_regulasi,
                    "detail"        => $tmpData,
                ];
            }

            $mappedData = collect($mappedData)->values()->toArray();

            if ($cekLhp) {
                $detail          = LhpsSwabTesDetail::where('id_header', $cekLhp->id)->get();
                $existingSamples = $detail->pluck('no_sampel')->toArray();
                $grouped         = [];

                $deskripsi_titik = json_decode($cekLhp->deskripsi_titik, true);

                foreach (json_decode($cekLhp->regulasi, true) as $i => $regulasi) {
                    $items = $detail->filter(function ($d) use ($i) {
                        return $d->page == ($i + 1);
                    });

                    $convertedDetails = $items->map(function ($d) use ($i, &$methode, &$method_suhu, &$method_kelembapan) {

                        $methode[str_replace(' ', '_', $d->parameter)] = $d->methode ?? '';
                        $method_suhu                                   = $d->method_suhu ?? '';
                        $method_kelembapan                             = $d->method_kelembapan ?? '';

                        if ($method_suhu === '' || $method_suhu == null) {
                            $method_suhu = $d->method_suhu;
                        }
                        if ($method_kelembapan === '' || $method_kelembapan == null) {
                            $method_kelembapan = $d->method_kelembapan;
                        }

                        return [
                            'id'               => $d->id,
                            'no_sampel'        => $d->no_sampel ?? null,
                            'parameter'        => $d->parameter ?? null,
                            'nama_lab'         => $d->parameter_lab ?? null,
                            'bakumutu'         => $d->baku_mutu ?? null,
                            'satuan'           => $d->satuan ?? null,
                            'methode'          => $d->methode ?? null,
                            'hasil_uji'        => $d->hasil_uji ?? null,
                            'tanggal_sampling' => $d->tanggal_sampling ?? null,
                            'keterangan'       => $d->keterangan ?? null,
                            'id_regulasi'      => $d->id_regulasi ?? null,
                            'nama_regulasi'    => $d->nama_regulasi ?? null,
                            'tanggal_terima'   => $d->tanggal_terima ?? null,
                            'akr'              => $d->akr ?? null,
                        ];
                    })->values()->toArray();

                    $grouped[] = [
                        "nama_regulasi"   => explode('-', $regulasi)[1],
                        "id_regulasi"     => explode('-', $regulasi)[0],
                        "deskripsi_titik" => $deskripsi_titik[$i] ?? null, // <-- ini dia
                        "detail"          => $convertedDetails,
                    ];

                }
                $final = [];

                foreach ($grouped as $index => $group) {

                    $groupDetails  = $group['detail'] ?? [];
                    $mappedDetails = $mappedData[$index]['detail'] ?? [];

                    // Normalizer
                    $normalize = function ($value) {
                        return strtolower(trim((string) $value));
                    };

                    // Gabung dulu
                    $merged = array_merge($groupDetails, $mappedDetails);

                    // Dedup berdasarkan key no_sampel|parameter yang sudah dinormalisasi
                    $unique = [];
                    $result = [];

                    foreach ($merged as $row) {

                        $key = $normalize($row['no_sampel']) . '|' . $normalize($row['parameter']);

                        if (! isset($unique[$key])) {
                            $unique[$key] = true;
                            $result[]     = $row;
                        }
                    }

                    $final[] = [
                        "id_regulasi"     => $group['id_regulasi'],
                        "nama_regulasi"   => $group['nama_regulasi'],
                        "deskripsi_titik" => $group['deskripsi_titik'],
                        "detail"          => array_values($result),
                    ];
                }

                return response()->json([
                    'data'       => $cekLhp,
                    'detail'     => $final,
                    'success'    => true,
                    'status'     => 200,
                    'keterangan' => [
                        '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                        '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                        'ẍ Parameter belum terakreditasi.',
                    ],
                    'message'    => 'Data berhasil diambil',
                ], 201);
            }

            foreach ($mappedData as &$regulasiGroup) {
                $regulasiGroup['detail'] = collect($regulasiGroup['detail'])
                    ->sortBy([
                        ['tanggal_sampling', 'asc'],
                        ['no_sampel', 'asc'],
                    ])
                    ->values()
                    ->toArray();
            }
            unset($regulasiGroup);

            return response()->json([
                'data'       => [],
                'detail'     => $mappedData,
                'status'     => 200,
                'success'    => true,
                'keterangan' => [
                    '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                    '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                    'ẍ Parameter belum terakreditasi.',
                ],
                'message'    => 'Data berhasil diambil !',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'getLine' => $e->getLine(),
                'getFile' => $e->getFile(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $category = explode('-', $request->kategori_3)[0];
        DB::beginTransaction();
        // dd($request->all());
        try {
            // =========================
            // BAGIAN HEADER (punyamu)
            // =========================
            $header = LhpsSwabTesHeader::where('no_lhp', $request->no_lhp)
                ->where('no_order', $request->no_order)
                ->where('id_kategori_3', $category)
                ->where('is_active', true)
                ->first();

            if ($header == null) {
                $header             = new LhpsSwabTesHeader;
                $header->created_by = $this->karyawan;
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
            } else {
                $history = $header->replicate();
                $history->setTable((new LhpsSwabTesHeaderHistory())->getTable());
                $history->created_by = $this->karyawan;
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->updated_by = null;
                $history->updated_at = null;
                $history->save();

                $header->updated_by = $this->karyawan;
                $header->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            }

            if (empty($request->tanggal_lhp)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Tanggal pengesahan LHP tidak boleh kosong',
                    'status'  => false,
                ], 400);
            }

            $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->tanggal_lhp)
                ->orderByDesc('berlaku_mulai')
                ->first();

            $parameterRaw = $request->parameter ?? [];
            $allParams    = [];

            if (is_array($parameterRaw)) {
                foreach ($parameterRaw as $noSampel => $params) {
                    if (is_array($params)) {
                        foreach ($params as $key => $value) {
                            if ($value !== null && $value !== '') {
                                $allParams[] = trim($value);
                            }
                        }
                    } elseif ($params !== null && $params !== '') {
                        $allParams[] = trim($params);
                    }
                }

                $allParams = array_values(array_unique($allParams));
            } else {
                $allParams = [$parameterRaw];
            }

            $keteranganRequest = $request->keterangan ?? [];

            $keteranganHeader = collect($keteranganRequest)
                ->filter(function ($value, $key) {
                    return is_int($key); // hanya 0,1,2
                })
                ->values()
                ->all();

            $mergeRegulasi = [];
            foreach ($request->data as $data) {
                $mergeRegulasi[] = $data['regulasi_id'] . '-' . $data['regulasi'];
            }

            $mergeDeskripsiTitik = [];
            foreach ($request->data as $data) {
                $mergeDeskripsiTitik[] = $data['deskripsi_titik'];
            }

            $parameter                      = $request->parameter;
            $header->no_order               = $request->no_order != '' ? $request->no_order : null;
            $header->no_sampel              = $request->no_sampel != '' ? $request->noSampel : null;
            $header->no_lhp                 = $request->no_lhp != '' ? $request->no_lhp : null;
            $header->id_kategori_2          = $request->kategori_2 != '' ? explode('-', $request->kategori_2)[0] : null;
            $header->id_kategori_3          = $category != '' ? $category : null;
            $header->no_qt                  = $request->no_penawaran != '' ? $request->no_penawaran : null;
            $header->parameter_uji          = ! empty($allParams) ? json_encode($allParams) : null;
            $header->nama_pelanggan         = $request->nama_perusahaan != '' ? $request->nama_perusahaan : null;
            $header->type_sampling          = $request->type_sampling != '' ? $request->type_sampling : null;
            $header->tanggal_sampling       = $request->tanggal_tugas != '' ? $request->tanggal_tugas : null;
            $header->tanggal_terima         = $request->tanggal_terima != '' ? $request->tanggal_terima : null;
            $header->tanggal_sampling_awal  = $request->tanggal_sampling_awal != '' ? $request->tanggal_sampling_awal : null;
            $header->tanggal_sampling_akhir = $request->tanggal_sampling_akhir != '' ? $request->tanggal_sampling_akhir : null;
            $header->tanggal_analisa_awal   = $request->tanggal_analisa_awal != '' ? $request->tanggal_analisa_awal : null;
            $header->tanggal_analisa_akhir  = $request->tanggal_analisa_akhir != '' ? $request->tanggal_analisa_akhir : null;
            $header->alamat_sampling        = $request->alamat_sampling != '' ? $request->alamat_sampling : null;
            $header->sub_kategori           = $request->jenis_sampel != '' ? $request->jenis_sampel : null;
            $header->deskripsi_titik        = json_encode($mergeDeskripsiTitik) ?? null;
            $header->metode_sampling        = $request->metode_sampling ? json_encode($request->metode_sampling) : null;
            $header->tanggal_sampling       = $request->tanggal_terima != '' ? $request->tanggal_terima : null;
            $header->nama_karyawan          = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
            $header->jabatan_karyawan       = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';
            $header->regulasi               = $request->regulasi != null ? json_encode($mergeRegulasi) : null;
            $header->regulasi_custom        = $request->regulasi_custom != null ? json_encode($request->regulasi_custom) : null;
            $header->tanggal_lhp            = $request->tanggal_lhp != '' ? $request->tanggal_lhp : null;
            $header->keterangan             = $request->keterangan != '' ? json_encode($keteranganHeader) : null;
            $header->save();

            $existingDetails = LhpsSwabTesDetail::where('id_header', $header->id)->get();

            if ($existingDetails->isNotEmpty()) {
                foreach ($existingDetails as $oldDetail) {
                    $history = $oldDetail->replicate();
                    $history->setTable((new LhpsSwabTesDetailHistory())->getTable());
                    $history->created_by = $this->karyawan;
                    $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $history->updated_by = null;
                    $history->updated_at = null;
                    $history->save();
                }

                LhpsSwabTesDetail::where('id_header', $header->id)->delete();
            }

            $pivot   = $request->pivot ?? [];
            $methode = $request->methode ?? [];

            foreach ($pivot as $key => $page) {
                foreach ($page as $noSampel => $row) {
                    $tanggal_sampling = $row['tanggal_sampling'];
                    $keterangan       = $row['keterangan'];

                    foreach ($row['hasil_uji'] as $paramName => $hasilUji) {
                        $bakumutu     = $row['bakumutu'][$paramName] ?? null;
                        $parameterLab = $row['parameter_lab'][$paramName] ?? null;
                        $satuan       = $row['satuan'][$paramName] ?? null;
                        $akr          = $row['akr'][$paramName] ?? null;

                        $metodeParam = $methode[$key][$noSampel][$paramName] ?? null;

                        $detail                   = new LhpsSwabTesDetail;
                        $detail->id_header        = $header->id;
                        $detail->no_lhp           = $header->no_lhp;
                        $detail->no_sampel        = $noSampel;
                        $detail->parameter        = $paramName;
                        $detail->parameter_lab    = $parameterLab;
                        $detail->satuan           = $satuan;
                        $detail->tanggal_sampling = $tanggal_sampling;
                        $detail->akr              = $akr;
                        $detail->keterangan       = $keterangan;
                        $detail->hasil_uji        = $hasilUji;
                        $detail->baku_mutu        = $bakumutu;
                        $detail->methode          = $metodeParam;
                        $detail->page             = $key + 1;

                        $detail->save();
                    }

                }
            }
            if ($header != null) {
                $file_qr = new GenerateQrDocumentLhp;
                $file_qr = $file_qr->insert('LHP_SWABTES', $header, $this->karyawan);
                if ($file_qr) {
                    $header->file_qr = $file_qr;
                    $header->save();
                }

                $id_regulasii           = explode('-', (json_decode($header->regulasi)[0]))[0];
                $detailCollection       = LhpsSwabTesDetail::where('id_header', $header->id)->where('page', 1)->get();
                $detailCollectionCustom = collect(LhpsSwabTesDetail::where('id_header', $header->id)->where('page', '!=', 1)->get())->groupBy('page')->toArray();

                $validasi        = LhpsSwabTesDetail::where('id_header', $header->id)->get();
                $groupedBySampel = $validasi->groupBy('no_sampel');
                $totalSampel     = $groupedBySampel->count();
                $parameters      = $validasi->pluck('parameter')->filter()->unique();
                $totalParam      = $parameters->count();

                $isSingleSampelMultiParam = $totalSampel === 1 && $totalParam > 2;
                $isMultiSampelOneParam    = $totalSampel >= 1 && $totalParam === 1;

                if ($isSingleSampelMultiParam) {
                    $fileName = LhpTemplate::setDataDetail($detailCollection)
                        ->setDataHeader($header)
                        ->setDataCustom($detailCollectionCustom)
                        ->useLampiran(true)
                        ->whereView('DraftSwab3Param')
                        ->render('downloadLHPFinal');
                    $header->file_lhp = $fileName;
                    $header->save();
                } else if ($isMultiSampelOneParam) {
                    $fileName = LhpTemplate::setDataDetail($detailCollection)
                        ->setDataHeader($header)
                        ->setDataCustom($detailCollectionCustom)
                        ->useLampiran(true)
                        ->whereView('DraftSwab1Param')
                        ->render('downloadLHPFinal');
                    $header->file_lhp = $fileName;
                    $header->save();
                } else {
                    $fileName = LhpTemplate::setDataDetail($detailCollection)
                        ->setDataHeader($header)
                        ->setDataCustom($detailCollectionCustom)
                        ->useLampiran(true)
                        ->whereView('DraftSwab2Param')
                        ->render('downloadLHPFinal');
                    $header->file_lhp = $fileName;
                    $header->save();
                }

            }
            DB::commit();

            return response()->json([
                'message' => 'Data Draft Swab Tes no LHP ' . $request->no_lhp . ' berhasil disimpan',
                'status'  => true,
            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            dd($th);
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'status'  => false,
                'line'    => $th->getLine(),
                'file'    => $th->getFile(),
            ], 500);
        }
    }

    private function cleanField($value)
    {
        if (! isset($value)) {
            return null;
        }

        $v = trim((string) $value, "'\" \t\n\r\0\x0B");

        return $v === '' ? null : $v;
    }

    public function updateTanggalLhp(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataHeader = LhpsSwabTesHeader::find($request->id);

            if (! $dataHeader) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Data tidak ditemukan, harap adjust data terlebih dahulu',
                ], 404);
            }

            $dataHeader->tanggal_lhp = $request->value;

            $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->value)
                ->orderByDesc('berlaku_mulai')
                ->first();

            $dataHeader->nama_karyawan    = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
            $dataHeader->jabatan_karyawan = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';

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
            $detail = LhpsSwabTesDetail::where('id_header', $dataHeader->id)->where('page', 1)->get();
            $custom = collect(LhpsSwabTesDetail::where('id_header', $dataHeader->id)->where('page', '!=', 1)->get())->groupBy('page')->toArray();

            $detail = collect($detail)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc'],
            ])->values()->toArray();

            $validasi        = LhpsSwabTesDetail::where('id_header', $dataHeader->id)->get();
            $groupedBySampel = $validasi->groupBy('no_sampel');
            $totalSampel     = $groupedBySampel->count();
            $parameters      = $validasi->pluck('parameter')->filter()->unique();
            $totalParam      = $parameters->count();

            $isSingleSampelMultiParam = $totalSampel === 1 && $totalParam > 2;
            $isMultiSampelOneParam    = $totalSampel >= 1 && $totalParam === 1;

            if ($isSingleSampelMultiParam) {
                $fileName = LhpTemplate::setDataDetail($detail)
                    ->setDataHeader($dataHeader)
                    ->setDataCustom($custom)
                    ->useLampiran(true)
                    ->whereView('DraftSwab3Param')
                    ->render('downloadLHPFinal');
                $dataHeader->file_lhp = $fileName;
                $dataHeader->save();
            } else if ($isMultiSampelOneParam) {
                $fileName = LhpTemplate::setDataDetail($detail)
                    ->setDataHeader($dataHeader)
                    ->setDataCustom($custom)
                    ->useLampiran(true)
                    ->whereView('DraftSwab1Param')
                    ->render('downloadLHPFinal');
                $dataHeader->file_lhp = $fileName;
                $dataHeader->save();
            } else {
                $fileName = LhpTemplate::setDataDetail($detail)
                    ->setDataHeader($dataHeader)
                    ->setDataCustom($custom)
                    ->useLampiran(true)
                    ->whereView('DraftSwab2Param')
                    ->render('downloadLHPFinal');
                $dataHeader->file_lhp = $fileName;
                $dataHeader->save();
            }

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

    public function handleApprove(Request $request, $isManual = true)
    {
        DB::beginTransaction();
        try {
            if ($isManual) {
                $konfirmasiLhp = KonfirmasiLhp::where('no_lhp', $request->cfr)->first();

                if (! $konfirmasiLhp) {
                    $konfirmasiLhp             = new KonfirmasiLhp();
                    $konfirmasiLhp->created_by = $this->karyawan;
                    $konfirmasiLhp->created_at = Carbon::now()->format('Y-m-d H:i:s');
                } else {
                    $konfirmasiLhp->updated_by = $this->karyawan;
                    $konfirmasiLhp->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                }

                $konfirmasiLhp->no_lhp                      = $request->cfr;
                $konfirmasiLhp->is_nama_perusahaan_sesuai   = $request->nama_perusahaan_sesuai;
                $konfirmasiLhp->is_alamat_perusahaan_sesuai = $request->alamat_perusahaan_sesuai;
                $konfirmasiLhp->is_no_sampel_sesuai         = $request->no_sampel_sesuai;
                $konfirmasiLhp->is_no_lhp_sesuai            = $request->no_lhp_sesuai;
                $konfirmasiLhp->is_regulasi_sesuai          = $request->regulasi_sesuai;
                $konfirmasiLhp->is_qr_pengesahan_sesuai     = $request->qr_pengesahan_sesuai;
                $konfirmasiLhp->is_tanggal_rilis_sesuai     = $request->tanggal_rilis_sesuai;

                $konfirmasiLhp->save();
            }

            $data = LhpsSwabTesHeader::where('no_lhp', $request->cfr)
                ->where('is_active', true)
                ->first();

            $noSampel = array_map('trim', explode(',', $request->noSampel));
            $no_lhp   = $data->no_lhp;

            $detail = LhpsSwabTesDetail::where('id_header', $data->id)->get();

            $qr = QrDocument::where('id_document', $data->id)
                ->where('type_document', 'LHP_SWAB_TES')
                ->where('is_active', 1)
                ->where('file', $data->file_qr)
                ->orderBy('id', 'desc')
                ->first();

            if ($data != null) {
                OrderDetail::where('cfr', $request->cfr)
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
                    'menu'        => 'Draft SWAB TES',
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
                if ($cekDetail && $cekDetail->periode) {
                    $cekLink = $cekLink->where('periode', $cekDetail->periode);
                }

                $cekLink = $cekLink->first();

                if ($cekLink) {
                    $job = new CombineLHPJob($data->no_lhp, $data->file_lhp, $data->no_order, $this->karyawan, $cekDetail->periode);
                    $this->dispatch($job);
                }

                $orderHeader = OrderHeader::where('id', $cekDetail->id_order_header)
                    ->first();

                EmailLhpRilisHelpers::run([
                    'cfr'             => $data->no_lhp,
                    'no_order'        => $data->no_order,
                    'nama_pic_order'  => $orderHeader->nama_pic_order ?? '-',
                    'nama_perusahaan' => $data->nama_pelanggan,
                    'periode'         => $cekDetail->periode,
                    'karyawan'        => $this->karyawan,
                ]);

            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Data Draft Swab Tes no LHP ' . $no_lhp . ' tidak ditemukan',
                    'status'  => false,
                ], 404);
            }

            DB::commit();
            return response()->json([
                'data'    => $data,
                'status'  => true,
                'message' => 'Data Draft Swab Tes no LHP ' . $no_lhp . ' berhasil diapprove',
            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'status'  => false,
            ], 500);
        }
    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {

            $lhps = LhpsSwabTesHeader::where('id', $request->id)
                ->where('is_active', true)
                ->first();

            if ($lhps) {
                HistoryAppReject::insert([
                    'no_lhp'      => $lhps->no_lhp,
                    'no_sampel'   => $request->noSampel,
                    'kategori_2'  => $lhps->id_kategori_2,
                    'kategori_3'  => $lhps->id_kategori_3,
                    'menu'        => 'Draft SWAB TES',
                    'status'      => 'rejected',
                    'rejected_at' => Carbon::now(),
                    'rejected_by' => $this->karyawan,
                ]);
                // History Header Kebisingan
                $lhpsHistory = $lhps->replicate();
                $lhpsHistory->setTable((new LhpsSwabTesHeaderHistory())->getTable());
                $lhpsHistory->created_at = $lhps->created_at;
                $lhpsHistory->updated_at = $lhps->updated_at;
                $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $lhpsHistory->deleted_by = $this->karyawan;
                $lhpsHistory->save();

                // History Detail Kebisingan
                $oldDetails = LhpsSwabTesDetail::where('id_header', $lhps->id)->get();
                foreach ($oldDetails as $detail) {
                    $detailHistory = $detail->replicate();
                    $detailHistory->setTable((new LhpsSwabTesDetailHistory())->getTable());
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
            OrderDetail::where('cfr', $request->no_lhp)
                ->whereIn('no_sampel', $noSampel)
                ->update([
                    'status' => 1,
                ]);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Data Draft Swab Tes no LHP ' . $request->no_lhp . ' berhasil direject',
            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            // dd($th);
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage() . ' On line ' . $th->getLine() . ' On File ' . $th->getFile(),
            ], 401);
        }
    }

}
