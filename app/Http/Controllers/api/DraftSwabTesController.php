<?php
namespace App\Http\Controllers\api;

use App\Helpers\HelperSatuan;
use App\Http\Controllers\Controller;
use App\Models\LhpsSwabTesDetail;
use App\Models\LhpsSwabTesDetailHistory;
use App\Models\LhpsSwabTesHeader;
use App\Models\LhpsSwabTesHeaderHistory;
use App\Models\MasterBakumutu;
use App\Models\MicrobioHeader;
use App\Services\GenerateQrDocumentLhp;
use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;
use App\Models\SwabTestHeader;
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
            GROUP_CONCAT(tanggal_sampling SEPARATOR ",") as tanggal_tugas,
            GROUP_CONCAT(tanggal_terima SEPARATOR ",") as tanggal_terima
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
            $cekLhp = LhpsSwabTesHeader::where('no_lhp', $request->cfr)
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

            // Ambil data KebisinganHeader + relasinya
            $swabData = SwabTestHeader::with('ws_value')
                ->whereIn('no_sampel', $orders)
                ->where('is_approved', 1)
                ->where('is_active', 1)
                ->where('lhps', 1)
                ->get();

            if ($swabData->isEmpty()) {
                $swabData = MicrobioHeader::with('ws_value')
                    ->whereIn('no_sampel', $orders)
                    ->where('is_approved', 1)
                    ->where('is_active', 1)
                    ->where('lhps', 1)
                    ->get();
            }

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
                $tmpData = $swabData->map(function ($val) use ($id_regulasi, $nama_regulasi, $getSatuan) {
                    $ws    = $val->ws_value;
                    $hasil = $ws->toArray();

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
                        'no_sampel'         => $val->no_sampel ?? null,
                        'parameter'         => $val->parameter ?? null,
                        'jenis_persyaratan' => $bakumutu ? $bakumutu->nama_header : '-',
                        'nilai_persyaratan' => $bakumutu ? $bakumutu->baku_mutu : '-',
                        'satuan'            => (! empty($bakumutu->satuan)) ? $bakumutu->satuan : '-',
                        'hasil_uji'         => $nilai,
                        'tanggal_sampling'  => $tanggal_sampling,
                        'verifikator'       => $val->approved_by ?? null,
                        'id_regulasi'       => $id_regulasi,
                        'nama_regulasi'     => $nama_regulasi,
                    ];
                })->toArray();

                $mappedData = array_merge($mappedData, $tmpData);
            }

            // buang duplikat kalau perlu (misal no_sampel + parameter + id_regulasi sama)
            $mappedData = collect($mappedData)->values()->toArray();

            if ($cekLhp) {
                $detail          = LhpsSwabTesDetailSampel::where('id_header', $cekLhp->id)->get();
                $existingSamples = $detail->pluck('no_sampel')->toArray();

                $data_all = collect($mappedData)
                    ->reject(fn($item) => in_array($item['no_sampel'], $existingSamples))
                    ->map(fn($item) => array_merge($item, ['status' => 'belom_diadjust']))
                    ->values()
                    ->toArray();

                $detail = $detail->toArray();
                $detail = array_merge($detail, $data_all);

                $detail = collect($detail)
                    ->sortBy('tanggal_sampling')
                    ->values()
                    ->toArray();

                return response()->json([
                    'data'    => $cekLhp,
                    'detail'  => $detail,
                    'success' => true,
                    'status'  => 200,
                    'message' => 'Data berhasil diambil',
                ], 201);
            }

            $mappedData = collect($mappedData)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc'],
            ])->values()->toArray();

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
                $allParams = $parameterRaw;
            }

            $parameter                = $request->parameter;
            $header->no_order         = $request->no_order != '' ? $request->no_order : null;
            $header->no_sampel        = $request->no_sampel != '' ? $request->noSampel : null;
            $header->no_lhp           = $request->no_lhp != '' ? $request->no_lhp : null;
            $header->id_kategori_2    = $request->kategori_2 != '' ? explode('-', $request->kategori_2)[0] : null;
            $header->id_kategori_3    = $category != '' ? $category : null;
            $header->no_qt            = $request->no_penawaran != '' ? $request->no_penawaran : null;
            $header->parameter_uji    = ! empty($allParams) ? json_encode($allParams) : null;
            $header->nama_pelanggan   = $request->nama_perusahaan != '' ? $request->nama_perusahaan : null;
            $header->alamat_sampling  = $request->alamat_sampling != '' ? $request->alamat_sampling : null;
            $header->sub_kategori     = $request->jenis_sampel != '' ? $request->jenis_sampel : null;
            $header->deskripsi_titik  = $request->keterangan_1 != '' ? $request->keterangan_1 : null;
            $header->metode_sampling  = $request->metode_sampling ? $request->metode_sampling : null;
            $header->tanggal_sampling = $request->tanggal_terima != '' ? $request->tanggal_terima : null;
            $header->nama_karyawan    = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
            $header->jabatan_karyawan = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';
            $header->regulasi         = $request->regulasi != null ? json_encode($request->regulasi) : null;
            $header->regulasi_custom  = $request->regulasi_custom != null ? json_encode($request->regulasi_custom) : null;
            $header->tanggal_lhp      = $request->tanggal_lhp != '' ? $request->tanggal_lhp : null;
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

            $hasilUji = $request->hasil_uji ?? [];
            $satuan   = $request->satuan ?? [];


            foreach ($hasilUji as $noSampelKey => $paramResults) {

                $noSampelBersih = trim($noSampelKey, "'\"");
                foreach ($paramResults as $paramNameKey => $nilaiUji) {

                    $paramNameBersih = trim($paramNameKey, "'\"");
                    $nilaiUjiTrim    = trim($nilaiUji, "'\" \t\n\r\0\x0B");

                    $satuanParam = null;
                    if (isset($satuan[$noSampelKey][$paramNameKey])) {
                        $satuanParam = trim($satuan[$noSampelKey][$paramNameKey], "'\" \t\n\r\0\x0B");
                    }

                    $detail            = new LhpsSwabTesDetail;
                    $detail->id_header = $header->id;
                    $detail->no_lhp    = $header->no_lhp;
                    $detail->no_sampel = $noSampelBersih;  // <- pake yang BERSIH
                    $detail->parameter = $paramNameBersih; // <- pake yang BERSIH
                    $detail->hasil_uji = (string) $nilaiUjiTrim;
                    $detail->satuan    = $satuanParam;
                    $detail->save();

                }
            }

            // if ($header != null) {
            //     $file_qr = new GenerateQrDocumentLhp;
            //     $file_qr = $file_qr->insert('LHP_SWABTES', $header, $this->karyawan);
            //     if ($file_qr) {
            //         $header->file_qr = $file_qr;
            //         $header->save();
            //     }

            //     $id_regulasii = explode('-', (json_decode($header->regulasi)[0]))[0];

            //     $fileName = LhpTemplate::setDataDetail($detail)
            //         ->setDataHeader($header)
            //         ->setDataCustom($custom ?? [])
            //         ->useLampiran(true)
            //         ->whereView('DraftSwabTes')
            //         ->render('downloadLHPFinal');

            //     $header->file_lhp = $fileName;
            //     $header->save();
            // }

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
            $detail = LhpsSwabTesDetail::where('id_header', $dataHeader->id)->get();
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

}
