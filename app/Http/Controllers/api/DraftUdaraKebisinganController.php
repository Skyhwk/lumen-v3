<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;
use App\Models\KonfirmasiLhp;
use App\Models\LhpsKebisinganHeader;
use App\Models\LhpsKebisinganDetail;
use App\Models\LhpsKebisinganCustom;

use App\Helpers\EmailLhpRilisHelpers;

use App\Models\LhpsKebisinganHeaderHistory;
use App\Models\LhpsKebisinganDetailHistory;


use App\Models\MasterSubKategori;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\MasterRegulasi;
use App\Models\MasterKaryawan;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;
use App\Models\KebisinganHeader;
use App\Models\Parameter;

use App\Models\GenerateLink;
use App\Services\PrintLhp;
use App\Services\SendEmail;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Jobs\CombineLHPJob;
use App\Models\LinkLhp;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DraftUdaraKebisinganController extends Controller
{
    // done if status = 2
    // AmanghandleDatadetail
    public function index(Request $request)
    {
        DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
            'lhps_kebisingan',
            'orderHeader' =>
            function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', ["23-Kebisingan", '24-Kebisingan (24 Jam)', '25-Kebisingan (Indoor)', '26-Kualitas Udara Dalam Ruang'])
            ->whereJsonDoesntContain('parameter', '271;Kebisingan (P8J)')
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

    // Tidak digunakan sekarang, gatau nanti
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
                'status' => true,
                'message' => 'Available data retrieved successfully',
                'data' => $method
            ], 200);
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
        // dd($request->all());
        $category = explode('-', $request->kategori_3)[0];
        DB::beginTransaction();
        try {
            $header = LhpsKebisinganHeader::where('no_lhp', $request->no_lhp)->where('no_order', $request->no_order)->where('id_kategori_3', $category)->where('is_active', true)->first();
            if ($header == null) {
                $header = new LhpsKebisinganHeader;
                $header->created_by = $this->karyawan;
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
            } else {
                $history = $header->replicate();
                $history->setTable((new LhpsKebisinganHeaderHistory())->getTable());
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
                    'status' => false
                ], 400);
            }

            $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->tanggal_lhp)
                ->orderByDesc('berlaku_mulai')
                ->first();

            $parameter = \explode(', ', $request->parameter); //ok
            $header->no_order = ($request->no_order != '') ? $request->no_order : NULL; //ok
            $header->no_sampel = ($request->no_sampel != '') ? $request->noSampel : NULL; //ok
            $header->no_lhp = ($request->no_lhp != '') ? $request->no_lhp : NULL; //ok
            $header->id_kategori_2 = ($request->kategori_2 != '') ? explode('-', $request->kategori_2)[0] : NULL; //ok
            $header->id_kategori_3 = ($category != '') ? $category : NULL; //ok
            $header->no_qt = ($request->no_penawaran != '') ? $request->no_penawaran : NULL; //ok
            $header->parameter_uji = json_encode($parameter); //ok
            $header->nama_pelanggan = ($request->nama_perusahaan != '') ? $request->nama_perusahaan : NULL; //ok
            $header->alamat_sampling = ($request->alamat_sampling != '') ? $request->alamat_sampling : NULL; //ok
            $header->sub_kategori = ($request->jenis_sampel != '') ? $request->jenis_sampel : NULL; //ok
            $header->deskripsi_titik = ($request->keterangan_1 != '') ? $request->keterangan_1 : NULL; //ok
            $header->metode_sampling = ($request->metode_sampling) ? $request->metode_sampling : NULL; //ok
            $header->tanggal_sampling = ($request->tanggal_terima != '') ? $request->tanggal_terima : NULL; //ok
            // $header->suhu = ($request->suhu != '') ? $request->suhu : NULL;
            // $header->kelembapan = ($request->kelembapan != '') ? $request->kelembapan : NULL;
            // $header->periode_analisa = ($request->periode_analisa != '') ? $request->periode_analisa : NULL;
            $header->nama_karyawan = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah'; //ok
            $header->jabatan_karyawan = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor'; //ok
            $header->regulasi = ($request->regulasi != null) ? json_encode($request->regulasi) : NULL; //ok
            $header->regulasi_custom = ($request->regulasi_custom != null) ? json_encode($request->regulasi_custom) : NULL; //ok
            $header->tanggal_lhp = ($request->tanggal_lhp != '') ? $request->tanggal_lhp : NULL; //ok
            $header->save();



            $detail = LhpsKebisinganDetail::where('id_header', $header->id)->first();
            if ($detail != null) {
                $history = $detail->replicate();
                $history->setTable((new LhpsKebisinganDetailHistory())->getTable());
                $history->created_by = $this->karyawan;
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->save();
                $detail = LhpsKebisinganDetail::where('id_header', $header->id)->delete();
            }

            $cleaned_param      = $this->cleanArrayKeys($request->param) ?? [];
            $cleaned_paparan    = $this->cleanArrayKeys($request->paparan) ?? [];
            $cleaned_titik_koordinat    = $this->cleanArrayKeys($request->titik_koordinat) ?? [];
            $cleaned_lokasi     = $this->cleanArrayKeys($request->lokasi);
            $cleaned_noSampel   = $this->cleanArrayKeys($request->no_sampel);
            $cleaned_hasil_uji  = $this->cleanArrayKeys($request->hasil_uji ?? []);
            $cleaned_ls         = $this->cleanArrayKeys($request->leq_ls ?? []);
            $cleaned_lsm         = $this->cleanArrayKeys($request->leq_lsm ?? []);
            $cleaned_lm         = $this->cleanArrayKeys($request->leq_lm ?? []);
            $cleaned_tanggal_sampling = $this->cleanArrayKeys($request->tanggal_sampling ?? []);
            $cleaned_nama_pekerja = [];
            $cleaned_nab        = [];
            $cleaned_nama_pekerja = $this->cleanArrayKeys($request->nama_pekerja ?? []);
            $cleaned_nab          = $this->cleanArrayKeys($request->nab ?? []);

            foreach ($request->no_sampel as $key => $val) {
                if (array_key_exists($val, $cleaned_noSampel)) {
                    $detail = new LhpsKebisinganDetail;
                    $detail->id_header = $header->id;
                    $detail->no_sampel = $cleaned_noSampel[$val];
                    $detail->param = $cleaned_param[$val] ?? null;
                    $detail->lokasi_keterangan = $cleaned_lokasi[$val];
                    $detail->tanggal_sampling = $cleaned_tanggal_sampling[$val];
                    $detail->min = $cleaned_min[$val] ?? null;
                    $detail->max = $cleaned_max[$val] ?? null;
                    $detail->leq_ls = $cleaned_ls[$val] ?? null;
                    $detail->leq_lsm = $cleaned_lsm[$val] ?? null;
                    $detail->leq_lm = $cleaned_lm[$val] ?? null;
                    $detail->titik_koordinat = $cleaned_titik_koordinat[$val] ?? null;
                    $detail->paparan = $cleaned_paparan[$val] ?? null;
                    $detail->hasil_uji = $cleaned_hasil_uji[$val] ?? null;
                    $detail->nama_pekerja = $cleaned_nama_pekerja[$val] ?? null;
                    $detail->nab = $cleaned_nab[$val] ?? null;
                    $detail->save();
                }
            }

            LhpsKebisinganCustom::where('id_header', $header->id)->delete();

            $custom = isset($request->regulasi_custom) && !empty($request->regulasi_custom);

            if ($custom) {
                foreach ($request->regulasi_custom as $key => $value) {
                    $custom_cleaned_param      = $this->cleanArrayKeys($request->custom_param[$key]);
                    $custom_cleaned_paparan    = $this->cleanArrayKeys($request->custom_paparan[$key] ?? []);
                    // dd($request->custom_lokasi);
                    $custom_cleaned_lokasi     = $this->cleanArrayKeys($request->custom_lokasi[$key]);
                    $custom_cleaned_noSampel   = $this->cleanArrayKeys($request->custom_no_sampel[$key]);
                    $custom_cleaned_hasil_uji  = $this->cleanArrayKeys($request->custom_hasil_uji[$key] ?? []);
                    $custom_cleaned_tanggal_sampling  = $this->cleanArrayKeys($request->custom_tanggal_sampling[$key] ?? []);
                    $custom_cleaned_ls         = $this->cleanArrayKeys($request->custom_ls[$key] ?? []);
                    $custom_cleaned_lsm         = $this->cleanArrayKeys($request->custom_lsm[$key] ?? []);
                    $custom_cleaned_lm         = $this->cleanArrayKeys($request->custom_lm[$key] ?? []);
                    $custom_cleaned_titik_koordinat         = $this->cleanArrayKeys($request->custom_titik_koordinat[$key] ?? []);
                    $custom_cleaned_nama_pekerja = [];
                    $custom_cleaned_nab        = [];
                    $custom_cleaned_nama_pekerja = $this->cleanArrayKeys($request->custom_nama_pekerja[$key] ?? []);
                    $custom_cleaned_nab          = $this->cleanArrayKeys($request->custom_nab[$key] ?? []);

                    foreach ($request->custom_no_sampel[$key] as $idx => $val) {
                        // dd($idx, $val, $key);
                        // dd(array_key_exists($val, $custom_cleaned_noSampel), $custom_cleaned_noSampel, $val);
                        if (array_key_exists($val, $custom_cleaned_noSampel)) {
                            $custom = new LhpsKebisinganCustom;
                            $custom->id_header = $header->id;
                            $custom->page = number_format($key) + 1;
                            $custom->no_sampel = $custom_cleaned_noSampel[$val];
                            $custom->param = $custom_cleaned_param[$val];
                            $custom->lokasi_keterangan = $custom_cleaned_lokasi[$val];
                            $custom->tanggal_sampling = $custom_cleaned_tanggal_sampling[$val];
                            $custom->min = $custom_cleaned_min[$val] ?? null;
                            $custom->max = $custom_cleaned_max[$val] ?? null;
                            $custom->leq_ls = $custom_cleaned_ls[$val] ?? null;
                            $custom->leq_lm = $custom_cleaned_lm[$val] ?? null;
                            $custom->leq_lsm = $custom_cleaned_lsm[$val] ?? null;
                            $custom->titik_koordinat = $custom_cleaned_titik_koordinat[$val] ?? null;
                            $custom->paparan = $custom_cleaned_paparan[$val] ?? null;
                            $custom->hasil_uji = $custom_cleaned_hasil_uji[$val] ?? null;
                            $custom->nama_pekerja = $custom_cleaned_nama_pekerja[$val] ?? null;
                            $custom->nab = $custom_cleaned_nab[$val] ?? null;
                            $custom->save();
                        }
                    }
                }
            }

            $details = LhpsKebisinganDetail::where('id_header', $header->id)->get();
            $custom = collect(LhpsKebisinganCustom::where('id_header', $header->id)->get())
                ->groupBy('page')
                ->toArray();
            
            if ($header != null) {

                $file_qr = new GenerateQrDocumentLhp();
                $file_qr = $file_qr->insert('LHP_KEBISINGAN', $header, $this->karyawan);
                if ($file_qr) {
                    $header->file_qr = $file_qr;
                    $header->save();
                }
                $id_regulasii = explode('-', (json_decode($header->regulasi)[0]))[0];
                $fileName = null;
                if (in_array($id_regulasii, [46, 54, 151, 167, 168, 382, 1321])) {

                    $parameter = json_decode($header->parameter_uji)[0];
                    if (strpos($parameter, '24 Jam') !== false) {
                        $is_sesaat = false;
                    } else {
                        $is_sesaat = true;
                    }
                    if ($is_sesaat) {
                        $fileName = LhpTemplate::setDataDetail($details)
                            ->setDataHeader($header)
                            ->setDataCustom($custom)
                            ->useLampiran(true)
                            ->whereView('DraftKebisinganLh')
                            ->render('downloadLHPFinal');
                    } else {
                        $fileName = LhpTemplate::setDataDetail($details)
                            ->setDataHeader($header)
                            ->setDataCustom($custom)
                            ->useLampiran(true)
                            ->whereView('DraftKebisinganLh24Jam')
                            ->render('downloadLHPFinal');
                    }
                } else {

                    $fileName = LhpTemplate::setDataDetail($details)
                        ->setDataHeader($header)
                        ->setDataCustom($custom)
                        ->useLampiran(true)
                        ->whereView('DraftKebisingan')
                        ->render('downloadLHPFinal');
                }
                $header->file_lhp = $fileName;

                $header->save();
            }
            
            DB::commit();
            return response()->json([
                'message' => 'Data draft Kebisingan udara no LHP ' . $request->no_lhp . ' berhasil disimpan',
                'status' => true
            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'status' => false,
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    private function cleanArrayKeys($arr)
    {
        if (!$arr) return [];
        $cleanedKeys = array_map(fn($k) => trim($k, " '\""), array_keys($arr));
        return array_combine($cleanedKeys, array_values($arr));
    }

    public function updateTanggalLhp(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataHeader = LhpsKebisinganHeader::find($request->id);

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
            $detail = LhpsKebisinganDetail::where('id_header', $dataHeader->id)->get();
            $detail = collect($detail)->sortBy([
                    ['tanggal_sampling', 'asc'],
                    ['no_sampel', 'asc']
                ])->values()->toArray();
            $custom = collect(LhpsKebisinganCustom::where('id_header', $dataHeader->id)->get())
                ->groupBy('page')
                ->toArray();

            foreach ($custom as $idx => $cstm) {
                $custom[$idx] = collect($cstm)->sortBy([
                    ['tanggal_sampling', 'asc'],
                    ['no_sampel', 'asc']
                ])->values()->toArray();
            }

            $id_regulasii = explode('-', (json_decode($dataHeader->regulasi)[0]))[0];
            if (in_array($id_regulasii, [54, 151, 167, 168, 382])) {

                $master_regulasi = MasterRegulasi::find($id_regulasii);
                if ($master_regulasi->deskripsi == 'Kebisingan Lingkungan' || $master_regulasi->deskripsi == 'Kebisingan LH') {
                    $fileName = LhpTemplate::setDataDetail($detail)
                        ->setDataHeader($dataHeader)
                        ->setDataCustom($custom)
                        ->useLampiran(true)
                        ->whereView('DraftKebisinganLh')
                        ->render('downloadLHPFinal');
                } else if ($master_regulasi->deskripsi == 'Kebisingan LH - 24 Jam' || $master_regulasi->deskripsi == 'Kebisingan Lingkungan (24 Jam)') {
                    $fileName = LhpTemplate::setDataDetail($detail)
                        ->setDataHeader($dataHeader)
                        ->setDataCustom($custom)
                        ->useLampiran(true)
                        ->whereView('DraftKebisinganLh24Jam')
                        ->render('downloadLHPFinal');
                }
            } else {
                $fileName = LhpTemplate::setDataDetail($detail)
                    ->setDataHeader($dataHeader)
                    ->setDataCustom($custom)
                    ->useLampiran(true)
                    ->whereView('DraftKebisingan')
                    ->render('downloadLHPFinal');
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

    //Amang
    public function handleDatadetail(Request $request)
    {
        $id_category = explode('-', $request->kategori_3)[0];

        try {
            // Ambil LHP yang sudah ada
            $cekLhp = LhpsKebisinganHeader::where('no_lhp', $request->cfr)
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
            $kebisinganData = KebisinganHeader::with('ws_udara', 'data_lapangan', 'data_lapangan_personal')
                ->whereIn('no_sampel', $orders)
                ->where('is_approved', 1)
                ->where('is_active', 1)
                ->where('lhps', 1)
                ->get();

            // Mapping data ke array associative
            $mappedData = $kebisinganData->map(function ($val) {
                $tanggal_sampling = OrderDetail::where('no_sampel', $val->no_sampel)->where('is_active', 1)->first()->tanggal_sampling;
                return [
                    'lokasi_keterangan' => $val->data_lapangan->lokasi_titik_sampling
                        ?? $val->data_lapangan->keterangan
                        ?? $val->data_lapangan_personal->keterangan
                        ?? null,
                    'paparan'          => $val->data_lapangan->jam_pemaparan
                        ?? $val->data_lapangan_personal->waktu_pengukuran
                        ?? null,
                    'titik_koordinat'  => $val->data_lapangan->titik_koordinat
                        ?? $val->data_lapangan_personal->titik_koordinat
                        ?? null,
                    'nama_pekerja'     => $val->data_lapangan_personal->created_by ?? null,
                    'id'              => $val->id,
                    'no_sampel'       => $val->no_sampel ?? null,
                    'param'           => $val->parameter ?? null,
                    'leq_ls'             => $val->leq_ls ?? null,
                    'leq_lm'             => $val->leq_lm ?? null,
                    'leq_lsm'             => $val->ws_udara->hasil1 ?? null,
                    'hasil_uji'       => $val->ws_udara->hasil1 ?? null,
                    'nab'             => $val->ws_udara->nab ?? null,
                    'tanggal_sampling' => $tanggal_sampling ?? null,
                ];
            })->values()->toArray();

            $jumlah_custom = count($request->regulasi) - 1;
            
            if ($cekLhp) {
                $detail = LhpsKebisinganDetail::where('id_header', $cekLhp->id)->get();
                $custom = LhpsKebisinganCustom::where('id_header', $cekLhp->id)
                    ->get()
                    ->groupBy('page')
                    ->toArray();

                $existingSamples = $detail->pluck('no_sampel')->toArray();

                // Filter data yang belum ada di detail
                $data_all = collect($mappedData)
                    ->reject(fn($item) => in_array($item['no_sampel'], $existingSamples))
                    ->map(fn($item) => array_merge($item, ['status' => 'belom_diadjust']))
                    ->values()
                    ->toArray();

                // Gabungkan dengan detail lama
                $detail = $detail->toArray();
                $detail = array_merge($detail, $data_all);

                // Tambahkan data ke custom sesuai page
                foreach ($custom as $idx => $cstm) {
                    foreach ($data_all as $value) {
                        $value['page'] = $idx;
                        $custom[$idx][] = $value;
                    }
                }
                
                if (count($custom) < $jumlah_custom) {
                    $custom[] = $detail;
                }

                $detail = collect($detail)
                ->sortBy('tanggal_sampling')
                ->values()
                ->toArray();

                foreach ($custom as $idx => $cstm) {
                    $custom[$idx] = collect($cstm)
                        ->sortBy('tanggal_sampling')
                        ->values()
                        ->toArray();
                }

                return response()->json([
                    'data'    => $cekLhp,
                    'detail'  => $detail,
                    'custom'  => $custom,
                    'success' => true,
                    'status'  => 200,
                    'message' => 'Data berhasil diambil'
                ], 201);
            }

            // Jika belum ada LHP -> buat custom baru jika perlu
            $custom = [];
            if ($jumlah_custom > 0) {
                for ($i = 0; $i < $jumlah_custom; $i++) {
                    $custom[$i + 1] = $mappedData;
                }
            }

            $mappedData = collect($mappedData)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc']
            ])->values()->toArray();

            foreach ($custom as $idx => $cstm) {
                $custom[$idx] = collect($cstm)->sortBy([
                    ['tanggal_sampling', 'asc'],
                    ['no_sampel', 'asc']
                ])->values()->toArray();
            }

            return response()->json([
                'data'    => [],
                'detail'  => $mappedData,
                'custom'  => $custom,
                'status'  => 200,
                'success' => true,
                'message' => 'Data berhasil diambil !'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'getLine' => $e->getLine(),
                'getFile' => $e->getFile()
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

            $data = LhpsKebisinganHeader::where('no_lhp', $request->cfr)
                ->where('is_active', true)
                ->first();

            $noSampel = array_map('trim', explode(',', $request->noSampel));
            $no_lhp = $data->no_lhp;

            $detail = LhpsKebisinganDetail::where('id_header', $data->id)->get();

            $qr = QrDocument::where('id_document', $data->id)
                ->where('type_document', 'LHP_KEBISINGAN')
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
                    'cfr'              => $request->cfr,
                    'no_order'         => $data->no_order,
                    'nama_pic_order'   => $orderHeader->nama_pic_order ?? '-',
                    'nama_perusahaan'  => $data->nama_pelanggan,
                    'periode'          => $cekDetail->periode,
                    'karyawan'         => $this->karyawan
                ]);

            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Data draft Kebisingan no LHP ' . $no_lhp . ' tidak ditemukan',
                    'status' => false
                ], 404);
            }

            DB::commit();
            return response()->json([
                'data' => $data,
                'status' => true,
                'message' => 'Data draft Kebisingan no LHP ' . $no_lhp . ' berhasil diapprove'
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

            $lhps = LhpsKebisinganHeader::where('id', $request->id)
                ->where('is_active', true)
                ->first();

            if ($lhps) {
                HistoryAppReject::insert([
                    'no_lhp' => $lhps->no_lhp,
                    'no_sampel' => $request->noSampel,
                    'kategori_2' => $lhps->id_kategori_2,
                    'kategori_3' => $lhps->id_kategori_3,
                    'menu' => 'Draft Udara',
                    'status' => 'rejected',
                    'rejected_at' => Carbon::now(),
                    'rejected_by' => $this->karyawan
                ]);
                // History Header Kebisingan
                $lhpsHistory = $lhps->replicate();
                $lhpsHistory->setTable((new LhpsKebisinganHeaderHistory())->getTable());
                $lhpsHistory->created_at = $lhps->created_at;
                $lhpsHistory->updated_at = $lhps->updated_at;
                $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $lhpsHistory->deleted_by = $this->karyawan;
                $lhpsHistory->save();

                // History Detail Kebisingan
                $oldDetails = LhpsKebisinganDetail::where('id_header', $lhps->id)->get();
                foreach ($oldDetails as $detail) {
                    $detailHistory = $detail->replicate();
                    $detailHistory->setTable((new LhpsKebisinganDetailHistory())->getTable());
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
                    'status' => 1
                ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data draft Kebisingan no LHP ' . $request->no_lhp . ' berhasil direject'
            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            // dd($th);
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage() . ' On line ' . $th->getLine() . ' On File ' . $th->getFile()
            ], 401);
        }
    }

    // Amang
    public function generate(Request $request)
    {

        DB::beginTransaction();
        try {
            $header = LhpsKebisinganHeader::where('no_lhp', $request->no_lhp)
                ->where('is_active', true)
                ->where('id', $request->id)
                ->first();
            if ($header != null) {
                if ($header->count_revisi > 0) {
                    $header->is_generated = true;
                    $header->generated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $header->generated_by = $this->karyawan;
                } else {
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
                            'quotation_status' => 'draft_kebisingan',
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
                }

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
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'draft_kebisingan', 'type' => 'draft'])->first();

            if (!$link) {
                return response()->json(['message' => 'Link not found'], 404);
            }
            return response()->json(['link' => env('PORTALV3_LINK') . $link->token], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    // Amang
    public function sendEmail(Request $request)
    {
        DB::beginTransaction();
        try {


            if ($request->id != '' || isset($request->id)) {
                LhpsKebisinganHeader::where('id', $request->id)->update([
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

    public function handleRevisi(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsKebisinganHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();

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
