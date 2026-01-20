<?php
namespace App\Http\Controllers\api;

use App\Helpers\EmailLhpRilisHelpers;
use App\Helpers\HelperSatuan;
use App\Http\Controllers\Controller;
use App\Jobs\CombineLHPJob;
use App\Models\DataLapanganMedanLM;
use App\Models\HistoryAppReject;
use App\Models\KonfirmasiLhp;
use App\Models\LhpsMedanLMDetail;
use App\Models\LhpsMedanLMDetailHistory;
use App\Models\LhpsMedanLMHeader;
use App\Models\LhpsMedanLMHeaderHistory;
use App\Models\LinkLhp;
use App\Models\MasterBakumutu;
use App\Models\MedanLmHeader;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\Parameter;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class DraftGelombangMikroController extends Controller
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
                'lhps_medanlm',
                'orderHeader',
            ])
            ->where('is_active', true)
            ->where('kategori_3', '27-Udara Lingkungan Kerja')
            ->where('status', 2)
            ->where(function ($q) {
                $q->whereJsonContains('parameter', "563;Medan Magnet")
                    ->orWhereJsonContains('parameter', "316;Power Density")
                    ->orWhereJsonContains('parameter', "277;Medan Listrik")
                    ->orWhereJsonContains('parameter', "236;Gelombang Elektro");
            })
            ->groupBy('cfr')
            ->get();

        $data = $data->map(function ($item) {
            // 1. Pecah no_sampel "S1,S2,S3" jadi array
            $noSampelList = array_filter(explode(',', $item->no_sampel));

            // 2. Ambil semua data lapangan untuk no_sampel tsb
            $lapangan = DataLapanganMedanLM::whereIn('no_sampel', $noSampelList)->get();

            // 3. Hitung min/max created_at
            $minDate = null;
            $maxDate = null;

            if ($lapangan->isNotEmpty()) {
                $minDate = $item->tanggal_tugas;
                $maxDate = $lapangan->max('created_at');
            }

            $lhps = $item->lhps_medanlm;

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
            $cekLhp = LhpsMedanLMHeader::where('no_lhp', $request->cfr)
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
            $dataHeader = MedanLmHeader::with('ws_udara')
                ->whereIn('no_sampel', $orders)
                ->where('is_approve', 1)
                ->where('is_active', 1)
                ->where('lhps', 1)
                ->get();

            $regulasiList = is_array($request->regulasi) ? $request->regulasi : [];
            $getSatuan    = new HelperSatuan;

            // kalau cuma dikirim string, fallback ke array 1 elemen
            if (! is_array($request->regulasi) && ! empty($request->regulasi)) {
                $regulasiList = [$request->regulasi];
            }

            $mappedData = [];

            // LOOP SETIAP REGULASI
            foreach ($regulasiList as $full_regulasi) {
                // contoh: "143-Peraturan Menteri Kesehatan Nomor 7 Tahun 2019"
                $parts_regulasi = explode('-', $full_regulasi, 2);
                $id_regulasi    = $parts_regulasi[0] ?? null;
                $nama_regulasi  = $parts_regulasi[1] ?? null;

                if (! $id_regulasi) {
                    continue;
                }

                // mapping setiap swabData terhadap regulasi ini
                $tmpData = $dataHeader->map(function ($val) use ($id_regulasi, $nama_regulasi, $getSatuan) {
                    $keterangan        = OrderDetail::where('no_sampel', $val->no_sampel)->first()->keterangan_1 ?? null;
                    $parameterLab      = Parameter::where('id', $val->id_parameter)->first()->nama_lab ?? null;
                    $parameterRegulasi = Parameter::where('id', $val->id_parameter)->first()->nama_regulasi ?? null;
                    $parameterLhp      = Parameter::where('id', $val->id_parameter)->first()->nama_lhp ?? null;
                    $ws                = $val->ws_udara;
                    $hasil             = $ws->toArray();
                    $dataLapangan      = DataLapanganMedanLM::where('no_sampel', $val->no_sampel)->where('is_approve', true)->first();
                    $orderRow          = OrderDetail::where('no_sampel', $val->no_sampel)
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

                    $nilaiDecode          = json_decode($nilai, true);
                    $hasil_uji            = '-';
                    $nab                  = '-';
                    $rata_frekuensi_raw   = $nilaiDecode['rata_frekuensi'] ?? 0;
                    $rata_frekuensi_clean = str_replace(',', '', $rata_frekuensi_raw);
                    $frekuensiHz          = floatval($rata_frekuensi_clean);
                    // $frekuensiMhz = floatval($rata_frekuensi_clean) / 1000000;
                    if ($val->id_parameter == 236 || $val->id_parameter == 316 || $val->id_parameter == 563) {
                        $hasil_uji            = $nilaiDecode['hasil_mwatt'] ?? null;
                        $medan_magnet         = $nilaiDecode['medan_magnet_am'] ?? $nilaiDecode['rata_magnet'] ?? $nilaiDecode['medan_magnet'] ?? null;
                        $rata_listrik         = $nilaiDecode['rata_listrik'] ?? $nilaiDecode['medan_listrik'] ?? null;
                        $rata_frekuensi       = $nilaiDecode['rata_frekuensi'] ?? null;
                        $nab                  = $ws->nab_medan_magnet;
                        $hasil_sumber_radiasi = $dataLapangan['sumber_radiasi'] ?? null;
                        $waktu_pemaparan      = $dataLapangan['waktu_pemaparan'] ?? null;
                        $frekuensi_area       = $frekuensiHz ?? null;
                    } else if ($val->id_parameter == 277) {
                        $hasil_uji            = $nilaiDecode['rata_listrik'] ?? null;
                        $medan_magnet         = $nilaiDecode['medan_magnet_am'] ?? $nilaiDecode['rata_magnet'] ?? $nilaiDecode['medan_magnet'] ?? null;
                        $rata_listrik         = $nilaiDecode['rata_listrik'] ?? $nilaiDecode['medan_listrik'] ?? null;
                        $rata_frekuensi       = $nilaiDecode['rata_frekuensi'] ?? null;
                        $nab                  = $ws->nab_medan_listrik;
                        $hasil_sumber_radiasi = $dataLapangan['sumber_radiasi'] ?? null;
                        $waktu_pemaparan      = $dataLapangan['waktu_pemaparan'] ?? null;
                        $frekuensi_area       = $frekuensiHz ?? null;
                    }

                    return [
                        'no_sampel'            => $val->no_sampel ?? null,
                        'parameter'            => $parameterLhp ?? $parameterRegulasi ?? null,
                        'nama_lab'             => $parameterLab ?? null,
                        'bakumutu'             => $bakumutu ? $bakumutu->baku_mutu : '-',
                        'satuan'               => (! empty($bakumutu->satuan)) ? $bakumutu->satuan : '-',
                        'methode'              => (! empty($bakumutu->method)) ? $bakumutu->method : (! empty($val->method) ? $val->method : '-'),
                        'hasil_uji'            => $hasil_uji ?? '-',
                        'medan_magnet'         => $medan_magnet ?? '-',
                        'rata_listrik'         => $rata_listrik ?? '-',
                        'rata_frekuensi'       => $rata_frekuensi ?? '-',
                        'hasil_sumber_radiasi' => $hasil_sumber_radiasi ?? '-',
                        'waktu_pemaparan'      => $waktu_pemaparan ?? '-',
                        'frekuensi_area'       => $frekuensi_area ?? '-',
                        'akr'                  => (! empty($bakumutu->akreditasi) && str_contains($bakumutu->akreditasi, 'AKREDITASI'))
                            ? ''
                            : 'ẍ',
                        'nab'                  => $nab ?? '-',
                    ];
                })->toArray();

                $data_detail = collect($tmpData)->flatMap(function ($item) {

                    return [
                        [
                            'no_sampel' => $item['no_sampel'],
                            'parameter' => 'Power Density',
                            'hasil_uji' => $item['hasil_uji'],
                            'nama_lab'  => $item['nama_lab'],
                            'bakumutu'  => $item['bakumutu'],
                            'satuan'    => 'mW/cm²',
                            'methode'   => 'IKM/ISL/7.2.183 (Electromagnetic Field Meter)',
                            'akr'       => $item['akr'],
                            'nab'       => $item['nab'],
                        ],
                        [
                            'no_sampel' => $item['no_sampel'],
                            'parameter' => 'Kekuatan Medan Listrik',
                            'hasil_uji' => $item['rata_listrik'],
                            'nama_lab'  => $item['nama_lab'],
                            'bakumutu'  => $item['bakumutu'],
                            'satuan'    => 'V/m',
                            'methode'   => 'IKM/ISL/7.2.64 (Elektrometri)',
                            'akr'       => $item['akr'],
                            'nab'       => $item['nab'],
                        ],
                        [
                            'no_sampel' => $item['no_sampel'],
                            'parameter' => 'Kekuatan Medan Magnet',
                            'hasil_uji' => $item['medan_magnet'],
                            'nama_lab'  => $item['nama_lab'],
                            'bakumutu'  => $item['bakumutu'],
                            'satuan'    => 'A/m',
                            'methode'   => 'IKM/ISL/7.2.62 (Elektrometri)',
                            'akr'       => $item['akr'],
                            'nab'       => $item['nab'],
                        ],
                    ];
                })->values()->toArray();

                $informasi_sampling = collect($tmpData)->flatMap(function ($item) {

                    return [
                        [
                            'no_sampel' => $item['no_sampel'],
                            'parameter' => 'Sumber Radiasi',
                            'data'      => $item['hasil_sumber_radiasi'],
                        ],
                        [
                            'no_sampel' => $item['no_sampel'],
                            'parameter' => 'Waktu Pemaparan(Per menit)',
                            'data'      => $item['waktu_pemaparan'],
                        ],
                        [
                            'no_sampel' => $item['no_sampel'],
                            'parameter' => 'Frekuensi Area(Hz)',
                            'data'      => $item['frekuensi_area'],
                        ],
                    ];
                })->values()->toArray();

                $mappedData[] = [
                    "id_regulasi"        => $id_regulasi,
                    "nama_regulasi"      => $nama_regulasi,
                    "detail"             => $data_detail,
                    "informasi_sampling" => $informasi_sampling,
                ];
            }

            $mappedData = collect($mappedData)->values()->toArray();

            if ($cekLhp) {
                $detail          = LhpsMedanLMDetail::where('id_header', $cekLhp->id)->get();
                $existingSamples = $detail->pluck('no_sampel')->toArray();
                $grouped         = [];

                $observasi         = json_decode($cekLhp->hasil_observasi, true);
                $kesimpulan        = json_decode($cekLhp->kesimpulan, true);
                $informasiSampling = json_decode($cekLhp->informasi_sampling, true);

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

                        // $rata_frekuensi_clean = str_replace(',', '', $d->rata_frekuensi);

                        return [
                            'id'             => $d->id,
                            'no_sampel'      => $d->no_sampel ?? null,
                            'parameter'      => $d->parameter ?? null,
                            'nama_lab'       => $d->parameter_lab ?? null,
                            'satuan'         => $d->satuan ?? null,
                            'methode'        => $d->methode ?? null,
                            'hasil_uji'      => $d->hasil_uji ?? null,
                            'medan_magnet'   => $d->medan_magnet ?? null,
                            'rata_listrik'   => $d->rata_listrik ?? null,
                            'rata_frekuensi' => $d->rata_frekuensi ?? null,
                            'akr'            => $d->akr ?? null,
                            'nab'            => $d->nab ?? null,
                        ];
                    })->values()->toArray();

                    $grouped[] = [
                        "nama_regulasi"      => explode('-', $regulasi)[1],
                        "id_regulasi"        => explode('-', $regulasi)[0],
                        "detail"             => $convertedDetails,
                        'observasi'          => $observasi[$i] ?? null,
                        'kesimpulan'         => $kesimpulan[$i] ?? null,
                        'informasi_sampling' => $informasiSampling[$i] ?? null,
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
                        "id_regulasi"        => $group['id_regulasi'],
                        "nama_regulasi"      => $group['nama_regulasi'],
                        "observasi"          => $group['observasi'],
                        "kesimpulan"         => $group['kesimpulan'],
                        "informasi_sampling" => $group['informasi_sampling'],
                        "detail"             => array_values($result),
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
            $header = LhpsMedanLMHeader::where('no_lhp', $request->no_lhp)
                ->where('no_order', $request->no_order)
                ->where('id_kategori_3', $category)
                ->where('is_active', true)
                ->first();

            if ($header == null) {
                $header             = new LhpsMedanLMHeader;
                $header->created_by = $this->karyawan;
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
            } else {
                $history = $header->replicate();
                $history->setTable((new LhpsMedanLMHeaderHistory())->getTable());
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

            $mergeInformasiSampling = [];
            foreach ($request->data as $data) {
                $mergeInformasiSampling[] = $data['informasi_sampling'];
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
            $header->tanggal_sampling       = $request->tanggal_tugas != '' ? $request->tanggal_tugas : null;
            $header->tanggal_terima         = $request->tanggal_terima != '' ? $request->tanggal_terima : null;
            $header->tanggal_sampling_awal  = $request->tanggal_sampling_awal != '' ? $request->tanggal_sampling_awal : null;
            $header->tanggal_sampling_akhir = $request->tanggal_sampling_akhir != '' ? $request->tanggal_sampling_akhir : null;
            $header->tanggal_analisa_awal   = $request->tanggal_analisa_awal != '' ? $request->tanggal_analisa_awal : null;
            $header->tanggal_analisa_akhir  = $request->tanggal_analisa_akhir != '' ? $request->tanggal_analisa_akhir : null;
            $header->alamat_sampling        = $request->alamat_sampling != '' ? $request->alamat_sampling : null;
            $header->sub_kategori           = $request->jenis_sampel != '' ? $request->jenis_sampel : null;
            $header->hasil_observasi        = $request->observasi != '' ? json_encode($request->observasi) : null;
            $header->kesimpulan             = $request->kesimpulan != '' ? json_encode($request->kesimpulan) : null;
            $header->metode_sampling        = $request->metode_sampling ? json_encode($request->metode_sampling) : null;
            $header->tanggal_sampling       = $request->tanggal_terima != '' ? $request->tanggal_terima : null;
            $header->nama_karyawan          = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
            $header->jabatan_karyawan       = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';
            $header->regulasi               = $request->regulasi != null ? json_encode($mergeRegulasi) : null;
            $header->tanggal_lhp            = $request->tanggal_lhp != '' ? $request->tanggal_lhp : null;
            $header->keterangan             = $request->keterangan != '' ? json_encode($keteranganHeader) : null;
            $header->deskripsi_titik        = $request->deskripsi_titik != '' ? $request->deskripsi_titik : null;
            $header->informasi_sampling     = json_encode($mergeInformasiSampling) ?? null;
            $header->save();

            // dd($header);

            $existingDetails = LhpsMedanLMDetail::where('id_header', $header->id)->get();

            if ($existingDetails->isNotEmpty()) {
                foreach ($existingDetails as $oldDetail) {
                    $history = $oldDetail->replicate();
                    $history->setTable((new LhpsMedanLMDetailHistory())->getTable());
                    $history->created_by = $this->karyawan;
                    $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $history->updated_by = null;
                    $history->updated_at = null;
                    $history->save();
                }

                LhpsMedanLMDetail::where('id_header', $header->id)->delete();
            }
            // dd($request->all(), $request->pivot, $request->data, $request->methode);
            $data = $request->data ?? [];
            foreach ($data as $pageIndex => $value) {
                foreach ($value['details'] as $details) {

                    $detail                = new LhpsMedanLMDetail;
                    $detail->id_header     = $header->id;
                    $detail->no_sampel     = $details['no_sampel'];
                    $detail->parameter     = $details['parameter'];
                    $detail->parameter_lab = $details['nama_lab'] ?? null;
                    $detail->satuan        = $details['satuan'];
                    $detail->akr           = $details['akr'];
                    $detail->nab           = $details['nab'];
                    $detail->methode       = $details['methode'];
                    $detail->hasil_uji     = $details['hasil_uji'];
                    $detail->page          = $pageIndex + 1;
                    $detail->save();

                }
            }

            if ($header != null) {
                $file_qr = new GenerateQrDocumentLhp;
                $file_qr = $file_qr->insert('LHP_GELOMBANGMIKRO', $header, $this->karyawan);
                if ($file_qr) {
                    $header->file_qr = $file_qr;
                    $header->save();
                }

                $id_regulasii           = explode('-', (json_decode($header->regulasi)[0]))[0];
                $detailCollection       = LhpsMedanLMDetail::where('id_header', $header->id)->where('page', 1)->get();
                $detailCollectionCustom = collect(LhpsMedanLMDetail::where('id_header', $header->id)->where('page', '!=', 1)->get())->groupBy('page')->toArray();

                $fileName = LhpTemplate::setDataDetail($detailCollection)
                    ->setDataHeader($header)
                    ->setDataCustom($detailCollectionCustom)
                    ->useLampiran(true)
                    ->whereView('DraftGelombangMikro')
                    ->render('downloadLHPFinal');
                $header->file_lhp = $fileName;
                $header->save();

            }
            DB::commit();
            return response()->json([
                'message' => 'Data Draft Gelombang Mikro no LHP ' . $request->no_lhp . ' berhasil disimpan',
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
            $dataHeader = LhpsMedanLMHeader::find($request->id);

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
            $detail = LhpsMedanLMDetail::where('id_header', $dataHeader->id)->where('page', 1)->get();
            $custom = collect(LhpsMedanLMDetail::where('id_header', $dataHeader->id)->where('page', '!=', 1)->get())->groupBy('page')->toArray();

            $detail = collect($detail)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc'],
            ])->values()->toArray();

            $fileName = LhpTemplate::setDataDetail($detail)
                ->setDataHeader($dataHeader)
                ->setDataCustom($custom)
                ->useLampiran(true)
                ->whereView('DraftGelombangMikro')
                ->render('downloadLHPFinal');
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

            $data = LhpsMedanLMHeader::where('no_lhp', $request->cfr)
                ->where('is_active', true)
                ->first();

            $noSampel = array_map('trim', explode(',', $request->noSampel));
            $no_lhp   = $data->no_lhp;

            $detail = LhpsMedanLMDetail::where('id_header', $data->id)->get();

            $qr = QrDocument::where('id_document', $data->id)
                ->where('type_document', 'LHP_GELOMBANGMIKRO')
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
                    'menu'        => 'Draft Gelombang Mikro',
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
                    'message' => 'Data Draft Gelombang Mikro no LHP ' . $no_lhp . ' tidak ditemukan',
                    'status'  => false,
                ], 404);
            }

            DB::commit();
            return response()->json([
                'data'    => $data,
                'status'  => true,
                'message' => 'Data Draft Gelombang Mikro no LHP ' . $no_lhp . ' berhasil diapprove',
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

            $lhps = LhpsMedanLMHeader::where('id', $request->id)
                ->where('is_active', true)
                ->first();

            if ($lhps) {
                HistoryAppReject::insert([
                    'no_lhp'      => $lhps->no_lhp,
                    'no_sampel'   => $request->noSampel,
                    'kategori_2'  => $lhps->id_kategori_2,
                    'kategori_3'  => $lhps->id_kategori_3,
                    'menu'        => 'Draft Gelombang Mikro',
                    'status'      => 'rejected',
                    'rejected_at' => Carbon::now(),
                    'rejected_by' => $this->karyawan,
                ]);
                // History Header Kebisingan
                $lhpsHistory = $lhps->replicate();
                $lhpsHistory->setTable((new LhpsMedanLMHeaderHistory())->getTable());
                $lhpsHistory->created_at = $lhps->created_at;
                $lhpsHistory->updated_at = $lhps->updated_at;
                $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $lhpsHistory->deleted_by = $this->karyawan;
                $lhpsHistory->save();

                // History Detail Kebisingan
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

            $noSampel = array_map('trim', explode(",", $request->no_sampel));
            OrderDetail::where('cfr', $request->no_lhp)
                ->whereIn('no_sampel', $noSampel)
                ->update([
                    'status' => 1,
                ]);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Data Draft Gelombang Mikro no LHP ' . $request->no_lhp . ' berhasil direject',
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
