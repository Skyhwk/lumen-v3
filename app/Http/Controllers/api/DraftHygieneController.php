<?php

namespace App\Http\Controllers\api;

use App\Helpers\HelperSatuan;
use App\Http\Controllers\Controller;
use App\Jobs\CombineLHPJob;
// Models
use App\Models\GenerateLink;
use App\Models\HistoryAppReject;
use App\Models\KonfirmasiLhp;
use App\Models\LhpsLingCustom;
use App\Models\LhpsLingDetail;
use App\Models\LhpsLingDetailHistory;
use App\Models\LhpsHygieneSanitasiHeaderHistory;
use App\Models\LinkLhp;
use App\Models\MasterBakumutu;
use App\Models\MasterKaryawan;
use App\Models\MasterRegulasi;
use App\Models\MasterSubKategori;
use App\Models\MetodeSampling;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\Parameter;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;
use App\Models\DetailSenyawaVolatile;
// Services
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use App\Helpers\EmailLhpRilisHelpers;
use App\Models\DataLapanganDirectLain;
use App\Models\DataLapanganLingkunganHidup;
use App\Models\DataLapanganLingkunganKerja;
use App\Models\DetailLingkunganKerja;
use App\Models\LhpsHygieneSanitasiHeader;
use App\Models\ParameterFdl;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class DraftHygieneController extends Controller
{

    public function index(Request $request)
    {
        $parameterAllowed = [
            'K3-KB',
            'K3-KFK',
            'K3-KFS',
            'K3-KFPBP',
            'K3-KRU',
            'K3-KTRTHK',
        ];



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
                'lhps_hygene',
                'orderHeader',
            ])
            ->where('is_active', true)
            ->where('kategori_3', '27-Udara Lingkungan Kerja')
            ->where('status', 2)
            ->where(function ($query) use ($parameterAllowed) {
                foreach ($parameterAllowed as $param) {
                    $query->where('parameter', 'LIKE', "%;$param%");
                }
            })
            ->groupBy('cfr')
            ->get();
        $data = $data->map(function ($item) {
            // 1. Pecah no_sampel "S1,S2,S3" jadi array
            $noSampelList = array_filter(explode(',', $item->no_sampel));

            // 2. Ambil semua data lapangan untuk no_sampel tsb
            $lapanganLing = (object)[];
            $lapanganDirect = (object)[];

            $lapangan = (object)[];
            // 3. Hitung min/max created_at
            $minDate = null;
            $maxDate = null;

            $lapangan = collect($lapangan);

            if ($lapangan->isNotEmpty()) {
                $minDate = $lapangan->min('created_at');
                $maxDate = $lapangan->max('created_at');
            }


            $lhps = $item->lhps_ling;

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
            $item->data_lapangan_lingkungan_kerja = $lapangan->first();

            return $item;
        });
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
            'data'    => $kategori,
            'message' => 'Available data category retrieved successfully',
        ], 201);
    }

    public function handleMetodeSampling(Request $request)
    {

        try {
            $subKategori = explode('-', $request->kategori_3);
            $regulasi = json_decode($request->regulasi, true) ?? [];

            $hasil_regulasi = array_map(function ($item) {
                return explode(';', $item)[0] ?? null;
            }, $regulasi);
            // Data utama
            $parameter = json_decode($request->parameter, true);

            $hasil = array_map(function ($item) {
                return explode(';', $item)[1] ?? null;
            }, $parameter);

            if (count($hasil) > 1) {
                $data = MetodeSampling::where('kategori', '4-UDARA')
                    ->where('sub_kategori', strtoupper($subKategori[1]))
                    ->get();
            } else {
                $data = MasterBakumutu::whereIn('id_regulasi', $hasil_regulasi)->where('is_active', true)->select('method as metode_sampling')->distinct()->get();
            }


            $result = $data->toArray();

            // Jika ada id_lhp, lakukan perbandingan array
            if ($request->filled('id_lhp')) {
                $header = LhpsHygieneSanitasiHeader::find($request->id_lhp);

                if ($header) {
                    $headerMetode = json_decode($header->methode_sampling, true) ?? [];

                    foreach ($data as $key => $value) {
                        $valueMetode = array_map('trim', explode(',', $value->metode_sampling));

                        $missing = array_diff($headerMetode, $valueMetode);

                        if (! empty($missing)) {
                            foreach ($missing as $miss) {
                                $result[] = [
                                    'id'              => null,
                                    'metode_sampling' => $miss,
                                    'kategori'        => $value->kategori,
                                    'sub_kategori'    => $value->sub_kategori,
                                ];
                            }
                        }
                    }
                }
            }

            return response()->json([
                'status'  => true,
                'message' => ! empty($result) ? 'Available data retrieved successfully' : 'Belum ada method',
                'data'    => $result,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    // public function store(Request $request)
    // {
    //     // dd($request->all());
    //     DB::beginTransaction();
    //     try {
    //         // === 1. Ambil header / buat baru ===
    //         $header = LhpsHygieneSanitasiHeader::where('no_lhp', $request->no_lhp)
    //             ->where('is_active', true)
    //             ->first();

    //         if ($header) {
    //             // Backup ke history sebelum update
    //             $history = $header->replicate();
    //             $history->setTable((new LhpsHygieneSanitasiHeaderHistory())->getTable());
    //             // $history->id = $header->id;
    //             $history->created_at = Carbon::now();
    //             $history->save();
    //         } else {
    //             $header = new LhpsHygieneSanitasiHeader();
    //         }

    //         // === 2. Validasi tanggal LHP ===
    //         if (empty($request->tanggal_lhp)) {
    //             DB::rollBack();
    //             return response()->json([
    //                 'message' => 'Tanggal pengesahan LHP tidak boleh kosong',
    //                 'status'  => false,
    //             ], 400);
    //         }

    //         $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->tanggal_lhp)
    //             ->orderByDesc('berlaku_mulai')
    //             ->first();

    //         $nama_perilis    = $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah';
    //         $jabatan_perilis = $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor';

    //         // === 3. Persiapan data header ===
    //         $parameter_uji = ! empty($request->parameter_header) ? explode(', ', $request->parameter_header) : [];
    //         $keterangan    = array_values(array_filter($request->keterangan ?? []));

    //         // $regulasi_custom = collect($request->regulasi_custom ?? [])->map(function ($item, $page) {
    //         //     return ['page' => (int) $page, 'regulasi' => $item];
    //         // })->values()->toArray();

    //         $regulasi_custom = collect($request->regulasi_custom ?? [])->map(function ($item, $page) use ($request) {

    //             // [$id, $regulasi] = explode(';', $item);

    //             // Hilangkan prefix id_ bila ada
    //             // $id = (int) str_replace('id_', '', $id);

    //             return [
    //                 'page'     => (int) $page,
    //                 'regulasi' => trim($item),
    //                 'id'       => $request->regulasi_custom_id[$page],
    //             ];
    //         })->values()->toArray();

    //         // === 4. Simpan / update header ===
    //         $header->fill([
    //             'no_order'               => $request->no_order ?: null,
    //             'no_sampel'              => $request->no_sampel ?: null,
    //             'no_lhp'                 => $request->no_lhp ?: null,
    //             'no_qt'                  => $request->no_penawaran ?: null,
    //             'status_sampling'        => $request->type_sampling ?: null,
    //             'tanggal_terima'         => $request->tanggal_terima ?: null,
    //             'tanggal_sampling'       => $request->tanggal_tugas ?: null,
    //             'parameter_uji'          => json_encode($parameter_uji),
    //             'nama_pelanggan'         => $request->nama_perusahaan ?: null,
    //             'alamat_sampling'        => $request->alamat_sampling ?: null,
    //             'sub_kategori'           => $request->jenis_sampel ?: null,
    //             'id_kategori_2'          => 4,
    //             'id_kategori_3'          => 27,
    //             'deskripsi_titik'        => $request->penamaan_titik ?: null,
    //             'methode_sampling'       => $request->metode_sampling ? json_encode($request->metode_sampling) : null,
    //             'titik_koordinat'        => $request->titik_koordinat ?: null,
    //             'tanggal_sampling'       => $request->tanggal_terima ?: null,
    //             'nama_karyawan'          => $nama_perilis,
    //             'jabatan_karyawan'       => $jabatan_perilis,
    //             'regulasi'               => $request->regulasi ? json_encode($request->regulasi) : null,
    //             'regulasi_custom'        => $regulasi_custom ? json_encode($regulasi_custom) : null,
    //             'keterangan'             => $keterangan ? json_encode($keterangan) : null,
    //             'tanggal_lhp'            => $request->tanggal_lhp ?: null,
    //             'created_by'             => $this->karyawan,
    //             'created_at'             => Carbon::now(),
    //             'keterangan'             => json_encode($request->keterangan) ?: null,
    //             'suhu'                   => $request->suhu_lingkungan,
    //             'tekanan_udara'          => $request->tekanan_udara,
    //             'kelembapan'             => $request->kelembapan,
    //             'metode_sampling'        => $request->metode_sampling ? json_encode($request->metode_sampling) : null,
    //             'periode_analisa'        => $request->periode_analisa ?: null,
    //             'tanggal_sampling_awal'  => $request->tanggal_sampling_awal ?: null,
    //             'tanggal_sampling_akhir' => $request->tanggal_sampling_akhir ?: null,
    //             'tanggal_analisa_awal'   => $request->tanggal_analisa_awal ?: null,
    //             'tanggal_analisa_akhir'  => $request->tanggal_analisa_akhir ?: null,
    //             'is_many_sampel'         => $request->is_many_sampel ?: false,
    //         ]);
    //         $header->save();
    //         // dd($header);
    //         // === 5. Backup & replace detail ===
    //         $oldDetails = LhpsLingDetail::where('id_header', $header->id)->get();
    //         foreach ($oldDetails as $detail) {
    //             $detailHistory = $detail->replicate();
    //             $detailHistory->setTable((new LhpsLingDetailHistory())->getTable());
    //             // $detailHistory->id = $detail->id;
    //             $detailHistory->created_by = $this->karyawan;
    //             $detailHistory->created_at = Carbon::now();
    //             $detailHistory->save();
    //         }
    //         LhpsLingDetail::where('id_header', $header->id)->delete();
    //         // dd($request->data['methode']);
    //         foreach (($request->data['details'] ?? []) as $key => $val) {
    //             // dd($val);
    //             LhpsLingDetail::create([
    //                 'id_header'     => $header->id,
    //                 'akr'           => $val['akr'] ?? '',
    //                 'parameter_lab' => $val['parameter_lab'] ?? '',
    //                 'parameter'     => $val['parameter'] ?? '',
    //                 'no_sampel'     => $val['no_sampel'] ?? '',
    //                 'hasil_uji'     => $val['hasil_uji'] ?? '',
    //                 'attr'          => $val['attr'] ?? '',
    //                 'baku_mutu'     => $val['nilai_persyaratan'] ?? '',
    //                 'nama_header'   => $val['jenis_persyaratan'] ?? '',
    //                 'tanggal_sampling' => $val['tanggal_sampling'] ?? '',
    //                 'deskripsi_titik' => $val['penamaan_titik'] ?? '',
    //                 'satuan'        => $val['satuan'] ?? '',
    //                 'durasi'        => $val['durasi'] ?? '',
    //                 'methode'       => $val['methode'] ?? $request->data['methode'] ?? '',
    //             ]);
    //         }

    //         // dd(LhpsLingDetail::where('id_header', $header->id)->get());

    //         // === 6. Handle custom ===
    //         LhpsLingCustom::where('id_header', $header->id)->delete();
    //         // dd($request->data_custom);
    //         if ($request->data_custom) {
    //             foreach ($request->data_custom ?? [] as $page => $params) {
    //                 // dd($params);
    //                 foreach ($params['details'] as $param => $hasil) {
    //                     // dd($hasil);
    //                     LhpsLingCustom::create([
    //                         'id_header'     => $header->id,
    //                         'page'          => $page + 1,
    //                         'parameter_lab' => $hasil['parameter_lab'] ?? '',
    //                         'no_sampel'     => $hasil['no_sampel'] ?? '',
    //                         'akr'           => $hasil['akr'] ?? '',
    //                         'parameter'     => $hasil['parameter'] ?? '',
    //                         'hasil_uji'     => $hasil['hasil_uji'] ?? '',
    //                         'attr'          => $hasil['attr'] ?? '',
    //                         'baku_mutu'     => $hasil['nilai_persyaratan'] ?? '',
    //                         'tanggal_sampling' => $hasil['tanggal_sampling'] ?? '',
    //                         'deskripsi_titik' => $hasil['penamaan_titik'] ?? '',
    //                         'nama_header'   => $hasil['jenis_persyaratan'] ?? '',
    //                         'satuan'        => $hasil['satuan'] ?? '',
    //                         'durasi'        => $hasil['durasi'] ?? '',
    //                         'methode'       => $hasil['methode'] ?? $params['methode'] ?? '',
    //                     ]);

    //                     // dd($data);
    //                 }
    //             }
    //         }
    //         // dd($header->id);
    //         // dd('header detail aman');
    //         // === 7. Generate QR & File ===
    //         if (! $header->file_qr) {
    //             $file_qr = new GenerateQrDocumentLhp();
    //             if ($path = $file_qr->insert('LHP_LINGKUNGAN_HIDUP', $header, $this->karyawan)) {
    //                 $header->file_qr = $path;
    //                 $header->save();
    //             }
    //         }

    //         $groupedByPage = collect(LhpsLingCustom::where('id_header', $header->id)->get())
    //             ->groupBy('page')
    //             ->toArray();
    //         // dd($groupedByPage, LhpsLingDetail::where('id_header', $header->id)->get());
    //         $fileName = LhpTemplate::setDataDetail(LhpsLingDetail::where('id_header', $header->id)->get())
    //             ->setDataHeader($header)
    //             ->setDataCustom($groupedByPage)
    //             ->useLampiran(true)
    //             ->whereView('DraftUdaraLingkunganKerja')
    //             ->render('downloadLHPFinal');

    //             // dd($fileName);
    //         $header->file_lhp = $fileName;
    //         $header->save();

    //         DB::commit();
    //         return response()->json([
    //             'message' => "Data draft Lingkungan Kerja no LHP $request->no_lhp berhasil disimpan",
    //             'status'  => true,
    //         ], 201);
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
    //             'status'  => false,
    //             'getLine' => $th->getLine(),
    //             'getFile' => $th->getFile(),
    //         ], 500);
    //     }
    // }

    // public function handleDatadetail(Request $request)
    // {
    //     try {
    //         // $noSampel = explode(', ', $request->no_sampel);

    //         // Ambil data LHP jika ada
    //         $cek_lhp = LhpsHygieneSanitasiHeader::with('lhpsLingDetail', 'lhpsLingCustom')
    //             ->where('no_lhp', $request->cfr)
    //             ->first();
    //         // dd($cek_lhp->is_many_sampel);
    //         // ==============================
    //         // CASE 1: Jika ada cek_lhp
    //         // ==============================
    //         if ($cek_lhp) {
    //             $data_entry   = [];
    //             $data_custom  = [];
    //             $cek_regulasi = [];

    //             // Ambil data detail dari LHP (existing entry)
    //             foreach ($cek_lhp->lhpsLingDetail as $val) {
    //                 // if($val->no_sampel == 'AARG012503/024')dd($val);
    //                 // dd($val);
    //                 $data_entry[] = [
    //                     'id'                => $val->id,
    //                     'parameter_lab'     => $val->parameter_lab,
    //                     'no_sampel'         => $val->no_sampel,
    //                     'akr'               => $val->akr,
    //                     'parameter'         => $val->parameter,
    //                     'satuan'            => $val->satuan,
    //                     'nilai_persyaratan' => $val->baku_mutu ?? '-',
    //                     'jenis_persyaratan' => $val->nama_header ?? '-',
    //                     'hasil_uji'         => $val->hasil_uji,
    //                     'methode'           => $val->methode,
    //                     'durasi'            => $val->durasi,
    //                     'status'            => $val->akr == 'ẍ' ? "BELUM AKREDITASI" : "AKREDITASI",
    //                     'penamaan_titik'    => $val->deskripsi_titik,
    //                     'tanggal_sampling'  => $val->tanggal_sampling,
    //                 ];
    //             }

    //             // Ambil regulasi tambahan jika ada
    //             if ($request->other_regulasi) {
    //                 $cek_regulasi = MasterRegulasi::whereIn('id', $request->other_regulasi)
    //                     ->select('id', 'peraturan as regulasi')
    //                     ->get()
    //                     ->toArray();
    //             }

    //             // Proses regulasi custom dari LHP
    //             if (! empty($cek_lhp->lhpsLingDetail) && ! empty($cek_lhp->regulasi_custom)) {
    //                 $regulasi_custom = json_decode($cek_lhp->regulasi_custom, true);

    //                 // Mapping id regulasi jika ada other_regulasi
    //                 if (! empty($cek_regulasi)) {
    //                     // Buat mapping regulasi => id
    //                     $mapRegulasi = collect($cek_regulasi)->pluck('id', 'regulasi')->toArray();
    //                     // Cari regulasi yang belum ada id-nya
    //                     $regulasi_custom = array_map(function ($item) use (&$mapRegulasi) {
    //                         $regulasi_clean = preg_replace('/\*+/', '', $item['regulasi']);
    //                         if (isset($mapRegulasi[$regulasi_clean])) {
    //                             $item['id'] = $mapRegulasi[$regulasi_clean];
    //                         } else {
    //                             // Cari id regulasi jika belum ada di mapping
    //                             $regulasi_db = MasterRegulasi::where('peraturan', $regulasi_clean)->first();
    //                             if ($regulasi_db) {
    //                                 $item['id']                   = $regulasi_db->id;
    //                                 $mapRegulasi[$regulasi_clean] = $regulasi_db->id;
    //                             }
    //                         }
    //                         return $item;
    //                     }, $regulasi_custom);
    //                 }
    //                 // Group custom berdasarkan page
    //                 $groupedCustom = [];
    //                 foreach ($cek_lhp->lhpsLingCustom as $val) {
    //                     $groupedCustom[$val->page][] = $val;
    //                 }
    //                 // Isi data_custom
    //                 // Urutkan regulasi_custom berdasarkan page
    //                 usort($regulasi_custom, function ($a, $b) {
    //                     return $a['page'] <=> $b['page'];
    //                 });
    //                 // Bentuk data_custom
    //                 foreach ($regulasi_custom as $item) {
    //                     // dd($item['page']);
    //                     if (empty($item['page'])) {
    //                         continue;
    //                     }

    //                     $id_regulasi = (string) "id_" . $item['id'] . '-' . $item['page'];
    //                     $page        = $item['page'];

    //                     if (! empty($groupedCustom[$page])) {
    //                         foreach ($groupedCustom[$page] as $val) {
    //                             $data_custom[$id_regulasi][] = [
    //                                 'id'                => $val->id,
    //                                 'parameter_lab'     => $val->parameter_lab,
    //                                 'no_sampel'         => $val->no_sampel,
    //                                 'akr'               => $val->akr,
    //                                 'parameter'         => $val->parameter,
    //                                 'nilai_persyaratan' => $val->baku_mutu ?? '-',
    //                                 'jenis_persyaratan' => $val->nama_header ?? '-',
    //                                 'satuan'            => $val->satuan,
    //                                 'hasil_uji'         => $val->hasil_uji,
    //                                 'methode'           => $val->methode,
    //                                 'durasi'            => $val->durasi,
    //                                 'status'            => $val->akr == 'ẍ' ? "BELUM AKREDITASI" : "AKREDITASI",
    //                                 'penamaan_titik'    => $val->deskripsi_titik,
    //                                 'tanggal_sampling'  => $val->tanggal_sampling,
    //                             ];
    //                         }
    //                     }
    //                 }
    //             }

    //             $defaultMethods = Parameter::where('is_active', true)
    //                 ->where('id_kategori', 4)
    //                 ->whereNotNull('method')
    //                 ->pluck('method')
    //                 ->unique()
    //                 ->values()
    //                 ->toArray();

    //             $data_entry = collect($data_entry)->sortBy([
    //                 ['tanggal_sampling', 'asc'],
    //                 ['no_sampel', 'asc'],
    //                 ['parameter', 'asc']
    //             ])->values()->toArray();

    //             $data_custom = collect($data_custom)->sortBy([
    //                 ['tanggal_sampling', 'asc'],
    //                 ['no_sampel', 'asc'],
    //                 ['parameter', 'asc']
    //             ])->values()->toArray();

    //             return response()->json([
    //                 'status'             => true,
    //                 'data'               => $data_entry,
    //                 'next_page'          => $data_custom,
    //                 'spesifikasi_method' => $defaultMethods,
    //                 'many_no_sampel'     => $cek_lhp->is_many_sampel,
    //                 'keterangan'         => [
    //                     '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
    //                     '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
    //                     'ẍ Parameter belum terakreditasi.',
    //                 ],
    //             ], 201);
    //         } else {
    //             $mainData         = [];
    //             $otherRegulations = [];
    //             $methodsUsed      = [];

    //             $validasi = OrderDetail::with([
    //                 'udaraLingkungan',
    //                 'udaraMicrobio',
    //                 'udaraSubKontrak',
    //                 'udaraDirect',
    //                 'udaraPartikulat',
    //                 'udaraDebu'
    //             ])
    //                 ->where('cfr', $request->cfr)
    //                 ->get();
    //             $manyNoSampel = count($validasi) > 1 ? true : false;
    //             $listData = collect(); // <- PENTING: pakai collect


    //             foreach ($validasi as $items) {
    //                 $lingkungan = $items->udaraLingkungan;
    //                 $microbio   = $items->udaraMicrobio;
    //                 $subKontrak = $items->udaraSubKontrak;
    //                 $direct     = $items->udaraDirect;
    //                 $partikulat = $items->udaraPartikulat;
    //                 $debu       = $items->udaraDebu;

    //                 $detail = collect()
    //                     ->merge($lingkungan)
    //                     ->merge($microbio)
    //                     ->merge($subKontrak)
    //                     ->merge($direct)
    //                     ->merge($partikulat)
    //                     ->merge($debu);

    //                 // MERGE ke $listData, bukan replace
    //                 $listData = $listData->merge(
    //                     $detail->map(function ($item) {
    //                         $newQuery = Parameter::where('nama_lab', $item->parameter)
    //                             ->where('id_kategori', '4')
    //                             ->where('is_active', true)
    //                             ->first();
    //                         // dump($item->no_sampel);
    //                         return [
    //                             'id'            => $item->id,
    //                             'parameter'     => $newQuery->nama_lhp ?? $newQuery->nama_regulasi,
    //                             'nama_lab'      => $item->parameter,
    //                             'penamaan_titik'    => $item->ws_udara->detailLingkunganKerja->keterangan ?? null,
    //                             'tanggal_sampling'    => $item->order_detail->tanggal_sampling ?? null,
    //                             'satuan'        => $newQuery->satuan,
    //                             'method'        => $newQuery->method,
    //                             'status'        => $newQuery->status,
    //                             'no_sampel'     => $item->no_sampel,
    //                             'durasi'        => $item->ws_udara->durasi ?? null,
    //                             'ws_udara'      => collect($item->ws_udara)->toArray(),
    //                             'ws_lingkungan' => collect($item->ws_value_linkungan)->toArray(),
    //                         ];
    //                     })
    //                 );
    //             }
    //             // dd('---------------------------');
    //             foreach ($listData as $item) {
    //                 $entry      = $this->formatEntry((object) $item, $request->regulasi, $methodsUsed);
    //                 $mainData[] = $entry;

    //                 if ($request->other_regulasi) {
    //                     foreach ($request->other_regulasi as $id_regulasi) {
    //                         $otherRegulations[$id_regulasi][] = $this->formatEntry((object) $item, $id_regulasi);
    //                     }
    //                 }
    //             }
    //             // Sort mainData
    //             $mainData = collect($mainData)->sortBy([
    //                 ['tanggal_sampling', 'asc'],
    //                 ['no_sampel', 'asc'],
    //                 ['parameter', 'asc']
    //             ])->values()->toArray();

    //             // Sort otherRegulations
    //             foreach ($otherRegulations as $id => $regulations) {
    //                 $otherRegulations[$id] = collect($regulations)->sortBy(fn($item) => mb_strtolower($item['parameter']))->values()->toArray();
    //             }
    //             $methodsUsed    = array_values(array_unique($methodsUsed));
    //             $defaultMethods = Parameter::where('is_active', true)->where('id_kategori', 4)
    //                 ->whereNotNull('method')->groupBy('method')
    //                 ->pluck('method')->toArray();
    //             $resultMethods = array_values(array_unique(array_merge($methodsUsed, $defaultMethods)));

    //             return response()->json([
    //                 'status'             => true,
    //                 'data'               => $mainData,
    //                 'next_page'          => $otherRegulations,
    //                 'spesifikasi_method' => $resultMethods,
    //                 'many_no_sampel'     => $manyNoSampel,
    //                 'keterangan'         => [
    //                     '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
    //                     '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
    //                     'ẍ Parameter belum terakreditasi.',
    //                 ],
    //             ], 201);
    //         }
    //     } catch (\Throwable $e) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
    //             'line'    => $e->getLine(),
    //             'file'    => $e->getFile(),
    //         ], 500);
    //     }
    // }

    private function formatEntry($val, $regulasiId, &$methodsUsed = [])
    {
        $bakumutu = MasterBakumutu::where('id_regulasi', $regulasiId)
            ->where('parameter', $val->nama_lab)
            ->first();

        $parameter = $val->parameter;
        $entry     = [
            'id'                => $val->id,
            'parameter_lab'     => $val->nama_lab,
            'no_sampel'         => $val->no_sampel,
            'akr'               => (
                ! empty($bakumutu)
                ? (str_contains($bakumutu->akreditasi, 'AKREDITASI') ? '' : 'ẍ')
                : 'ẍ'
            ),
            'parameter'         => $parameter,
            // 'satuan' => $param->satuan,
            'jenis_persyaratan' => $bakumutu ? $bakumutu->nama_header : '-',
            'nilai_persyaratan' => $bakumutu ? $bakumutu->baku_mutu : '-',
            // 'hasil_uji' => $val->ws_value_linkungan->C ?? null,
            'satuan'            => (! empty($bakumutu->satuan))
                ? $bakumutu->satuan
                : 'µg/Nm³',
            'durasi'            => ! empty($bakumutu->durasi_pengukuran) ? $bakumutu->durasi_pengukuran : (! empty($val->durasi) ? $val->durasi : '-'),
            'methode'           => ! empty($bakumutu->method) ? $bakumutu->method : (! empty($val->method) ? $val->method : '-'),
            'status'            => $val->status,
            'penamaan_titik'    => $val->penamaan_titik,
            'tanggal_sampling'  => $val->tanggal_sampling,
        ];

        $getSatuan = new HelperSatuan;

        $index    = $getSatuan->udara($bakumutu->satuan ?? null) ?? 1;
        $ws_udara = (object) $val->ws_udara;

        $ws_value_lingkungan = (object) $val->ws_lingkungan;
        $ws          = null;
        if ($ws_udara != null) {
            $fKoreksiKey = "f_koreksi_$index";
            $hasilKey    = "hasil$index";
            $ws = $ws_udara;
        } else {
            $i           = ($index - 1);
            $i           = ($i == 0) ? '' : $i;
            $fKoreksiKey = "f_koreksi_c{$i}";
            $hasilKey    = "C{$i}";
            $ws = $ws_value_lingkungan;
        }


        $entry['hasil_uji'] = $ws->$fKoreksiKey ?? $ws->$hasilKey ?? null;
        if ($bakumutu && in_array($bakumutu->satuan, ["mg/m³", "mg/m³", "mg/m3"]) && ($entry['hasil_uji'] === null || $entry['hasil_uji'] === '-')) {
            $fKoreksi2          = $ws->f_koreksi_2 ?? null;
            $hasil2             = $ws->hasil2 ?? null;
            $entry['hasil_uji'] = $fKoreksi2 ?? $hasil2 ?? $entry['hasil_uji'];
        }
        if ($bakumutu && in_array($bakumutu->satuan, ["BDS", "bds"]) && ($entry['hasil_uji'] === null || $entry['hasil_uji'] === '-')) {
            $fKoreksi3          = $ws->f_koreksi_3 ?? null;
            $hasil3             = $ws->hasil3 ?? null;
            $entry['hasil_uji'] = $fKoreksi3 ?? $hasil3 ?? $entry['hasil_uji'];
        }

        if ($bakumutu && in_array($bakumutu->satuan, ["µg/m³", "µg/m3"]) && ($entry['hasil_uji'] === null || $entry['hasil_uji'] === '-')) {
            $fKoreksi1          = $ws->f_koreksi_1 ?? null;
            $hasil1             = $ws->hasil1 ?? null;
            $entry['hasil_uji'] = $fKoreksi1 ?? $hasil1 ?? $entry['hasil_uji'];
        }

        if ($bakumutu && $bakumutu->method) {
            $entry['satuan']       = $bakumutu->satuan;
            $entry['methode']      = $bakumutu->method;
            $entry['baku_mutu'][0] = $bakumutu->baku_mutu;
            $methodsUsed[]         = $bakumutu->method;
        }

        return $entry;
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
            $data = LhpsHygieneSanitasiHeader::where('no_lhp', $request->no_lhp)
                ->where('is_active', true)
                ->first();
            $noSampel = array_map('trim', explode(',', $request->noSampel));

            $qr = QrDocument::where('id_document', $data->id)
                ->where('type_document', 'LHP_AMBIENT')
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

                $data->is_approved = 1;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->approved_by = $this->karyawan;

                $data->save();
                HistoryAppReject::insert([
                    'no_lhp'      => $request->no_lhp,
                    'no_sampel'   => $request->noSampel,
                    'kategori_2'  => $data->id_kategori_2,
                    'kategori_3'  => $data->id_kategori_3,
                    'menu'        => 'Draft Udara',
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
                // dd($cekLink);
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
                    'message' => 'Data draft Udara Ambient no LHP ' . $request->no_lhp . ' tidak ditemukan',
                    'status'  => false,
                ], 404);
            }

            DB::commit();
            return response()->json([
                'data'    => $data,
                'status'  => true,
                'message' => 'Data draft Udara Ambient no LHP ' . $request->no_lhp . ' berhasil diapprove',
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

    // Amang
    // public function handleReject(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {
    //         // $noSampel = array_map('trim', explode(',', $request->noSampel));
    //         $data = OrderDetail::where('cfr', $request->no_lhp)
    //             // ->where('no_sampel', $request->no_sampel)
    //             ->where('is_active', true)
    //             ->get();

    //         if ($data->isNotEmpty()) {
    //             $lhps = LhpsHygieneSanitasiHeader::where('no_lhp', $request->no_lhp)
    //                 ->where('is_active', true)
    //                 ->first();

    //             if ($lhps) {
    //                 $lhpsHistory = $lhps->replicate();
    //                 $lhpsHistory->setTable((new LhpsHygieneSanitasiHeaderHistory())->getTable());
    //                 $lhpsHistory->created_at = $lhps->created_at;
    //                 $lhpsHistory->updated_at = $lhps->updated_at;
    //                 $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
    //                 $lhpsHistory->deleted_by = $this->karyawan;
    //                 $lhpsHistory->save();

    //                 $oldDetails = LhpsLingDetail::where('id_header', $lhps->id)->get();
    //                 foreach ($oldDetails as $detail) {
    //                     $detailHistory = $detail->replicate();
    //                     $detailHistory->setTable((new LhpsLingDetailHistory())->getTable());
    //                     $detailHistory->created_by = $this->karyawan;
    //                     $detailHistory->created_at = Carbon::now()->format('Y-m-d H:i:s');
    //                     $detailHistory->save();
    //                 }

    //                 foreach ($oldDetails as $detail) {
    //                     $detail->delete();
    //                 }

    //                 $lhps->delete();
    //             }
    //         }

    //         $update = OrderDetail::where('cfr', $request->no_lhp)
    //             ->where('is_active', true)
    //             ->update([
    //                 'status' => 1
    //             ]);

    //         DB::commit();
    //         return response()->json([
    //             'status'  => 'success',
    //             'message' => 'Data draft Udara Ambient no LHP ' . $request->no_lhp . ' berhasil direject',
    //         ]);
    //     } catch (\Exception $th) {
    //         DB::rollBack();
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => 'Terjadi kesalahan ' . $th->getMessage(),
    //             'line'    => $th->getLine(),
    //             'getFile' => $th->getFile(),
    //         ]);
    //     }
    // }

    // Amang
    public function generate(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsHygieneSanitasiHeader::where('no_lhp', $request->no_lhp)
                ->where('is_active', true)
                // ->where('id', $request->id)
                ->first();

            if ($header != null) {
                $key       = $header->no_lhp . str_replace('.', '', microtime(true));
                $gen       = MD5($key);
                $gen_tahun = self::encrypt(DATE('Y-m-d'));
                $token     = self::encrypt($gen . '|' . $gen_tahun);

                $cek = GenerateLink::where('fileName_pdf', $header->file_lhp)->first();
                if ($cek) {
                    $cek->id_quotation = $header->id;
                    $cek->expired      = Carbon::now()->addYear()->format('Y-m-d');
                    $cek->created_by   = $this->karyawan;
                    $cek->created_at   = Carbon::now()->format('Y-m-d H:i:s');
                    $cek->save();

                    $header->id_token = $cek->id;
                } else {
                    $insertData = [
                        'token'            => $token,
                        'key'              => $gen,
                        'id_quotation'     => $header->id,
                        'quotation_status' => 'draft_ambient',
                        'type'             => 'draft',
                        'expired'          => Carbon::now()->addYear()->format('Y-m-d'),
                        'fileName_pdf'     => $header->file_lhp,
                        'created_by'       => $this->karyawan,
                        'created_at'       => Carbon::now()->format('Y-m-d H:i:s'),
                    ];

                    $insert = GenerateLink::insertGetId($insertData);

                    $header->id_token = $insert;
                }

                $header->is_generated = true;
                $header->generated_by = $this->karyawan;
                $header->generated_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->expired      = Carbon::now()->addYear()->format('Y-m-d');
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
                'line'    => $e->getLine(),
                'status'  => false,
            ], 500);
        }
    }

    // Amang

    // Amang
    public function getLink(Request $request)
    {
        try {
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'draft_ambient', 'type' => 'draft'])->first();
            if (! $link) {
                return response()->json(['message' => 'Link not found'], 404);
            }
            return response()->json(['link' => env('PORTALV3_LINK') . $link->token], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
    public function getUser(Request $request)
    {
        $users = MasterKaryawan::with(['department', 'jabatan'])->where('id', $request->id ?: $this->user_id)->first();

        return response()->json($users);
    }
    // Amang
    public function handleRevisi(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsHygieneSanitasiHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();

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
                'getFile' => $th->getFile(),

            ], 500);
        }
    }

    // Amang
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

    // Amang
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

    public function updateTanggalLhp(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataHeader = LhpsHygieneSanitasiHeader::find($request->id);

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
            $detail = LhpsLingDetail::where('id_header', $dataHeader->id)->get();
            $custom = LhpsLingCustom::where('id_header', $dataHeader->id)->get();

            $groupedByPage = [];
            foreach ($custom as $item) {
                $page                   = $item->page;
                $groupedByPage[$page][] = $item->toArray();
            }

            $fileName = LhpTemplate::setDataDetail($detail)
                ->setDataHeader($dataHeader)
                ->setDataCustom($groupedByPage)
                ->whereView('DraftUdaraLingkunganKerja')
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

    public function uploadFile(Request $request)
    {
        DB::beginTransaction();
        try {
            $file = $request->file('file_input');

            // Validasi file
            if (!$file || $file->getClientOriginalExtension() !== 'pdf') {
                return response()->json(['error' => 'File tidak valid. Harus .pdf'], 400);
            }

            $Lhp = LhpsHygieneSanitasiHeader::updateOrCreate([
                'no_lhp' => $request->no_lhp,
                'no_order' => explode('/', $request->no_lhp)[0]
            ]);

            // Pastikan folder invoice ada
            $folder = public_path('dokumen/LHP_DOWNLOAD/');
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            $fileName = 'LHP-' . str_replace("/", "-", $request->no_lhp) . '.pdf';

            // Simpan file
            $file->move($folder, $fileName);

            $Lhp->file_lhp = $fileName;
            $Lhp->save();

            DB::commit();
            return response()->json([
                'success'  => 'Sukses menyimpan file upload',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Terjadi kesalahan server',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
