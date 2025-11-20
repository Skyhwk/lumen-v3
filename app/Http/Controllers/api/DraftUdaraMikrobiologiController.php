<?php

namespace App\Http\Controllers\api;

use App\Helpers\EmailLhpRilisHelpers;
use App\Helpers\HelperSatuan;
use App\Models\HistoryAppReject;
use App\Models\LhpsKebisinganHeader;
use App\Models\LhpsKebisinganDetail;
use App\Models\LhpsLingHeader;
use App\Models\LhpsLingDetail;
use App\Models\LhpsPencahayaanHeader;
use App\Models\LhpsGetaranHeader;
use App\Models\LhpsGetaranDetail;
use App\Models\LhpsPencahayaanDetail;
use App\Models\LhpsMedanLMHeader;
use App\Models\LhpsMedanLMDetail;

use App\Models\LhpsKebisinganHeaderHistory;
use App\Models\LhpsKebisinganDetailHistory;
use App\Models\LhpsGetaranHeaderHistory;
use App\Models\LhpsGetaranDetailHistory;
use App\Models\LhpsPencahayaanHeaderHistory;
use App\Models\LhpsPencahayaanDetailHistory;
use App\Models\LhpsMedanLMHeaderHistory;
use App\Models\LhpsMedanLMDetailHistory;
use App\Models\LhpSinarUVHeaderHistory;
use App\Models\LhpsSinarUVDetailHistory;
use App\Models\LhpsLingHeaderHistory;
use App\Models\LhpsLingDetailHistory;

use App\Models\MasterSubKategori;
use App\Models\OrderDetail;
use App\Models\MetodeSampling;
use App\Models\MasterBakumutu;
use App\Models\MasterKaryawan;
use App\Models\LingkunganHeader;
use App\Models\QrDocument;
use App\Models\PencahayaanHeader;
use App\Models\KebisinganHeader;
use App\Models\Subkontrak;
use App\Models\MedanLMHeader;
use App\Models\SinarUVHeader;
use App\Models\GetaranHeader;
use App\Models\DataLapanganErgonomi;
use App\Models\Parameter;
use App\Models\DirectLainHeader;
use App\Models\GenerateLink;
use App\Services\SendEmail;
use App\Services\TemplateLhps;
use App\Services\GenerateQrDocumentLhp;
use App\Jobs\RenderLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Jobs\CombineLHPJob;
use App\Models\KonfirmasiLhp;
use App\Models\LhpsMicrobiologiDetail;
use App\Models\LhpsMicrobiologiDetailHistory;
use App\Models\LhpsMicrobiologiHeader;
use App\Models\LhpsMicrobiologiHeaderHistory;
use App\Models\LinkLhp;
use App\Models\MicrobioHeader;
use App\Models\OrderHeader;
use App\Models\ParameterFdl;
use App\Models\PengesahanLhp;
use App\Models\TabelRegulasi;
use App\Services\LhpTemplate;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Header;
use PhpParser\Builder\Param;
use Yajra\Datatables\Datatables;

class DraftUdaraMikrobiologiController extends Controller
{
    // done if status = 2
    // AmanghandleDatadetail
    public function index(Request $request)
    {
        $parameterAllowed = ParameterFdl::where('nama_fdl', 'microbiologi')->first();
        // dd(json_decode($parameterAllowed->parameters, true));
        $parameterAllowed = json_decode($parameterAllowed->parameters, true);
        // dd($parameterAllowed);
        DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
            'lhps_microbiologi',
            'orderHeader' => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where(function ($query) use ($parameterAllowed) {
                foreach ($parameterAllowed as $param) {
                    $query->orWhere('parameter', 'LIKE', "%;$param%");
                }
            })
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', ["12-Udara Angka Kuman", '33-Mikrobiologi Udara', '27-Udara Lingkungan Kerja'])
            ->groupBy('cfr')
            ->where('status', 2)
            ->get();

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

    public function handleDatadetail(Request $request)
    {
        $id_category = explode('-', $request->kategori_3)[0];

        try {
            // Ambil LHP yang sudah ada
            $cekLhp = LhpsMicrobiologiHeader::where('no_lhp', $request->cfr)
                ->where('id_kategori_3', $id_category)
                ->where('is_active', true)
                ->first();

            // Ambil list no_sampel dari order yang memenuhi syarat
            $orders = OrderDetail::where('cfr', $request->cfr)
                ->where('is_approve', 0)
                ->where('is_active', true)
                ->where('kategori_2', '4-Udara')
                ->where('kategori_3', $request->kategori_3)
                ->where('status', 2)
                ->pluck('no_sampel');
            // dd($orders);
            
            $subKontrak = Subkontrak::with('ws_udara', 'detail_lapangan_microbiologi')->whereIn('no_sampel', $orders)->where('is_active', true)->where('is_approve', true)->get();

            // Ambil data KebisinganHeader + relasinya
            $swabData = MicrobioHeader::with('ws_value', 'detail_lapangan')
                ->whereIn('no_sampel', $orders)
                ->where('is_approved', 1)
                ->where('is_active', 1)
                ->where('lhps', 1)
                ->get();

            // dd($swabData, $subKontrak);
            // dd($swabData);
            // if ($swabData->isEmpty()) {
            //     $swabData = MicrobioHeader::with('ws_value')
            //         ->whereIn('no_sampel', $orders)
            //         ->where('is_approved', 1)
            //         ->where('is_active', 1)
            //         ->where('lhps', 1)
            //         ->get();
            // }
            $allData = $subKontrak->merge($swabData);
            
            $regulasiList = is_array($request->regulasi) ? $request->regulasi : [];

            $getSatuan    = new HelperSatuan;

            $mappedData = [];

            // LOOP SETIAP REGULASI
            foreach ($regulasiList as $full_regulasi) {

                $parts_regulasi = explode('-', $full_regulasi, 2);
                $id_regulasi    = $parts_regulasi[0] ?? null;
                $nama_regulasi  = $parts_regulasi[1] ?? null;

                if (!$id_regulasi) {
                    continue;
                }

                // isi "detail" untuk regulasi ini
                $detailList = $allData->map(function ($val) use ($id_regulasi, $nama_regulasi, $getSatuan) {
                    $ws    = $val->ws_value ?? $val->ws_udara;
                    $lapangan = $val->detail_lapangan ?? $val->detail_lapangan_microbiologi;
                    $hasil = $ws->toArray();

                    $orderRow = OrderDetail::where('no_sampel', $val->no_sampel)
                        ->where('is_active', 1)
                        ->first();

                    $paramSearch = $val->parameter;
                    $parameters = json_decode($orderRow->parameter, true);

                    $result = collect($parameters)->first(function ($item) use ($paramSearch) {
                        return str_contains(strtolower($item), strtolower($paramSearch));
                    });

                    $param = Parameter::where('id', \explode(';', $result)[0])->first();

                    $tanggal_sampling = $orderRow->tanggal_sampling ?? null;

                    $bakumutu = MasterBakumutu::where('id_regulasi', $id_regulasi)
                        ->where('parameter', $val->parameter)
                        ->first();

                    $nilai = '-';
                    $index = $getSatuan->udara($bakumutu->satuan ?? null);

                    if ($index === null) {
                        for ($i = 1; $i <= 17; $i++) {
                            $key = "f_koreksi_$i";
                            if (!empty($hasil[$key])) {
                                $nilai = $hasil[$key];
                                break;
                            }
                        }
                        if ($nilai === '-' || $nilai === null) {
                            for ($i = 1; $i <= 17; $i++) {
                                $key = "hasil$i";
                                if (!empty($hasil[$key])) {
                                    $nilai = $hasil[$key];
                                    break;
                                }
                            }
                        }
                    } else {
                        $fk = "f_koreksi_$index";
                        $h  = "hasil$index";
                        $nilai = $hasil[$fk] ?? $hasil[$h] ?? '-';
                    }

                    return [
                        'no_sampel'         => $val->no_sampel,
                        'suhu'              => $lapangan->suhu ?? '-',
                        'kelembapan'        => $lapangan->kelembapan ?? '-',
                        'keterangan'        => $lapangan->keterangan ?? '-',
                        'parameter'         => $param->nama_lhp ?? $param->nama_regulasi ?? $val->parameter,
                        "parameter_lab"     => $val->parameter,
                        'jenis_persyaratan' => $bakumutu->nama_header ?? '-',
                        'nilai_persyaratan' => $bakumutu->baku_mutu ?? '-',
                        'satuan'            => $bakumutu->satuan ?? '-',
                        'hasil_uji'         => $nilai,
                        'tanggal_sampling'  => $tanggal_sampling,
                        'verifikator'       => $val->approved_by,
                    ];
                })->toArray();

                // → Push format final per regulasi
                $mappedData[] = [
                    "id_regulasi"  => $id_regulasi,
                    "nama_regulasi" => $nama_regulasi,
                    "detail"       => $detailList,
                ];
            }

            // buang duplikat kalau perlu (misal no_sampel + parameter + id_regulasi sama)
            $mappedData = collect($mappedData)->values()->toArray();
            // dd($mappedData);
            
            if ($cekLhp) {
                $detail          = LhpsMicrobiologiDetail::where('id_header', $cekLhp->id)->get();
                $existingSamples = $detail->pluck('no_sampel')->toArray();
                $grouped = [];
                // dd($detail);
                foreach (json_decode($cekLhp->regulasi, true) as $i => $regulasi) {

                    // Ambil detail yang page-nya = index + 1
                    $items = $detail->filter(function ($d) use ($i) {
                        return $d->page == ($i + 1);
                    });

                    // Convert item DB ke array detail
                    $convertedDetails = $items->map(function ($d) {
                        return [
                            "no_sampel"        => $d->no_sampel,
                            "suhu"             => $d->suhu,
                            "kelembapan"       => $d->kelembapan,
                            'keterangan'        => $d->keterangan ?? '-',
                            "parameter"        => $d->parameter,
                            "parameter_lab"        => $d->parameter_lab,
                            "jenis_persyaratan" => $d->jenis_persyaratan ?? "-",
                            "nilai_persyaratan" => $d->baku_mutu ?? "-",
                            "satuan"           => $d->satuan,
                            "hasil_uji"        => $d->hasil_uji,
                            "tanggal_sampling" => $d->tanggal_sampling ?? "-",
                            "verifikator"      => $d->verifikator,
                        ];
                    })->values()->toArray();

                    // Push ke hasil
                    $grouped[] = [
                        "nama_regulasi" => explode('-', $regulasi)[1],
                        "id_regulasi"   => explode('-', $regulasi)[0],
                        "detail"        => $convertedDetails
                    ];
                }
                // dd($grouped);
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

                        if (!isset($unique[$key])) {
                            $unique[$key] = true;
                            $result[] = $row;
                        }
                    }

                    $final[] = [
                        "id_regulasi"   => $group['id_regulasi'],
                        "nama_regulasi" => $group['nama_regulasi'],
                        "detail"        => array_values($result),
                    ];
                }

                return response()->json([
                    'data'    => $cekLhp,
                    'detail'  => $final,
                    'success' => true,
                    'status'  => 200,
                    'message' => 'Data berhasil diambil',
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
                'data'    => [],
                'detail'  => $mappedData,
                'status'  => 200,
                'success' => true,
                'message' => 'Data berhasil diambil !',
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
        // dd($request->all());
        $category = explode('-', $request->kategori_3)[0];
        DB::beginTransaction();
        try {
            // =========================
            // BAGIAN HEADER (punyamu)
            // =========================
            $header = LhpsMicrobiologiHeader::where('no_lhp', $request->no_lhp)
                ->where('no_order', $request->no_order)
                ->where('id_kategori_3', $category)
                ->where('is_active', true)
                ->first();

            if ($header == null) {
                $header             = new LhpsMicrobiologiHeader;
                $header->created_by = $this->karyawan;
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
            } else {
                $history = $header->replicate();
                $history->setTable((new LhpsMicrobiologiHeaderHistory())->getTable());
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
            $mergeRegulasi = [];
            foreach ($request->data as $data) {
                $mergeRegulasi[] = $data['regulasi_id'] . '-' . $data['regulasi'];
            }
            $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->tanggal_lhp)
                ->orderByDesc('berlaku_mulai')
                ->first();

            $header->no_order         = $request->no_order != '' ? $request->no_order : null;
            $header->no_sampel        = $request->no_sampel != '' ? $request->noSampel : null;
            $header->no_lhp           = $request->no_lhp != '' ? $request->no_lhp : null;
            $header->id_kategori_2    = $request->kategori_2 != '' ? explode('-', $request->kategori_2)[0] : null;
            $header->id_kategori_3    = $category != '' ? $category : null;
            $header->no_qt            = $request->no_penawaran != '' ? $request->no_penawaran : null;
            // $header->parameter_uji    = ! empty($allParams) ? json_encode($allParams) : null;
            $header->nama_pelanggan   = $request->nama_perusahaan != '' ? $request->nama_perusahaan : null;
            $header->alamat_sampling  = $request->alamat_sampling != '' ? $request->alamat_sampling : null;
            $header->sub_kategori     = $request->jenis_sampel != '' ? $request->jenis_sampel : null;
            $header->deskripsi_titik  = $request->keterangan_1 != '' ? $request->keterangan_1 : null;
            $header->metode_sampling  = $request->metode_sampling ? json_encode($request->metode_sampling, true) : null;
            $header->tanggal_sampling = $request->tanggal_terima != '' ? $request->tanggal_terima : null;
            $header->nama_karyawan    = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
            $header->jabatan_karyawan = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';
            $header->regulasi         = $mergeRegulasi != null ? json_encode($mergeRegulasi) : null;
            $header->tanggal_lhp      = $request->tanggal_lhp != '' ? $request->tanggal_lhp : null;
            $header->save();

            $existingDetails = LhpsMicrobiologiDetail::where('id_header', $header->id)->get();

            if ($existingDetails->isNotEmpty()) {
                foreach ($existingDetails as $oldDetail) {
                    $history = $oldDetail->replicate();
                    $history->setTable((new LhpsMicrobiologiDetailHistory())->getTable());
                    $history->created_by = $this->karyawan;
                    $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $history->save();
                }

                LhpsMicrobiologiDetail::where('id_header', $header->id)->delete();
            }

            foreach ($request->data as $key => $regulations) {
                // dd($regulations);
                foreach ($regulations['details'] as $lock => $sampel) {
                    // dd($sampel);
                    $detail            = new LhpsMicrobiologiDetail;
                    $detail->id_header = $header->id;
                    $detail->no_lhp    = $header->no_lhp;
                    $detail->no_sampel = $sampel['no_sampel'];
                    $detail->parameter = $sampel['parameter'];
                    $detail->parameter_lab = $sampel['parameter_lab'];
                    $detail->keterangan= $sampel['keterangan'];
                    $detail->baku_mutu = $sampel['baku_mutu'];
                    $detail->hasil_uji = (string) $sampel['hasil_uji'];
                    $detail->satuan    = $sampel['satuan'];
                    $detail->suhu      = $sampel['suhu'];
                    $detail->kelembapan = $sampel['kelembapan'];
                    $detail->page      = $key + 1;
                    $detail->save();
                }
            }

            $dataPage1 = LhpsMicrobiologiDetail::where('id_header', $header->id)->where('page', 1)->get();
            $groupedByPage = collect(LhpsMicrobiologiDetail::where('id_header', $header->id)->where('page', '!=', 1)->get())
                ->groupBy('page')
                ->toArray();

            $checkWithTable1ParamOrNoTable1ParamOr2Param = $this->checkWithTable1ParamOrNoTable1ParamOr2Param($dataPage1, json_decode($header->regulasi, true));

            $view = 'no table';
            $lampiran = false;

            switch ($checkWithTable1ParamOrNoTable1ParamOr2Param) {
                case 'dupe':
                    $view = 'DraftMicro2Param';
                    break;

                case 'table':
                    $view = 'DraftMicroTable';
                    $lampiran = true;
                    break;

                case 'no table':
                    $view = 'DraftMicroNoTable';
                    break;
            }
            $grouped = $dataPage1->groupBy('no_sampel');
            // dd($grouped);
            $fileName = LhpTemplate::setDataDetail($dataPage1)
            ->setDataHeader($header)
            ->setDataCustom($groupedByPage)
            ->useLampiran($lampiran)
            ->whereView($view)
            ->render('downloadLHPFinal');
            
            $header->file_lhp = $fileName;

            $header->save();
            DB::commit();
            return response()->json([
                'message' => 'Data draft swab tes no LHP ' . $request->no_lhp . ' berhasil disimpan',
                'status'  => true,
            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'status'  => false,
                'line'    => $th->getLine(),
                'file'    => $th->getFile(),
            ], 500);
        }
    }

    private function checkWithTable1ParamOrNoTable1ParamOr2Param($data, $regulasi)
    {
        $grouped = $data->groupBy('no_sampel');

        $dupe = $grouped->filter(fn($x) => $x->count() > 1)->first();

        if ($dupe) {
            return 'dupe';
        }
        $id_reg = [];

        foreach ($regulasi as $key => $value) {
            $id_reg[] = explode('-', $value)[0];
        }

        $isTable = TabelRegulasi::whereIn('id', $id_reg)->where('is_active', 1)->get();

        if (! $isTable->isEmpty()) {
            return 'table';
        }

        return 'no table';
    }

    public function updateTanggalLhp(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataHeader = LhpsMicrobiologiHeader::find($request->id);

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
            $detail = LhpsMicrobiologiDetail::where('id_header', $dataHeader->id)->get();
            $detail = collect($detail)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc'],
            ])->values()->toArray();

            $fileName = LhpTemplate::setDataDetail($detail)
                ->setDataHeader($dataHeader)
                ->useLampiran(true)
                ->whereView('DraftSwabTes')
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
                ->where('type_document', 'LHP_MIKROBIOLOGI')
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

                $cekDetail = OrderDetail::where('cfr', $data->no_lhp)->where('is_active', true)->first();
                $cekLink = LinkLhp::where('no_order', $data->no_order)->where('periode', $cekDetail->periode)->first();

                if($cekLink) {
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

            $lhps = LhpsMicrobiologiHeader::where('id', $request->id)
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
                $lhpsHistory->setTable((new LhpsMicrobiologiHeaderHistory())->getTable());
                $lhpsHistory->created_at = $lhps->created_at;
                $lhpsHistory->updated_at = $lhps->updated_at;
                $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $lhpsHistory->deleted_by = $this->karyawan;
                $lhpsHistory->save();

                // History Detail
                $oldDetails = LhpsMicrobiologiDetail::where('id_header', $lhps->id)->get();
                foreach ($oldDetails as $detail) {
                    $detailHistory = $detail->replicate();
                    $detailHistory->setTable((new LhpsMicrobiologiDetailHistory())->getTable());
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
}
