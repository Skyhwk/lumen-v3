<?php

namespace App\Http\Controllers\api;

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
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DraftUdaraAmbientController extends Controller
{
    // done if status = 2
    // AmanghandleDatadetail
    public function index(Request $request)
    {
        DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
            'lhps_getaran',
            'lhps_kebisingan',
            'lhps_ling',
            'lhps_medanlm',
            'lhps_pencahayaan',
            'lhps_sinaruv',
            'orderHeader' => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->where('kategori_3', "11-Udara Ambient")
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
        // dd($request->all());
        try {
            $kategori = explode('-', '4-UDARA');
            $subKategori = explode('-', $request->kategori_3);
            $data = MetodeSampling::where('kategori', $kategori[1])
                ->where('sub_kategori', $subKategori[1])->get();
            if ($data->isNotEmpty()) {
                return response()->json([
                    'status' => true,
                    'data' => $data
                ], 200);
            } else {
                return response()->json([
                    'status' => true,
                    'data' => 'belum ada method'
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'getFile' => $th->getFile(),

            ], 500);
        }
    }

    public function store(Request $request)
    {

        $categoryLingkunganKerja = [11, 27, 53];
        $category = explode('-', $request->kategori_3)[0];
        DB::beginTransaction();

        try {
            // Lingkungan Kerja

            $orderDetail = OrderDetail::where('id', $request->id)->where('is_active', true)->where('kategori_3', 'LIKE', "%{$category}%")->where('cfr', $request->no_lhp)->first();
            $orderDetailParameter = json_decode($orderDetail->parameter);
            $parameterNames = array_map(function ($param) {
                $parts = explode(';', $param);
                return $parts[1] ?? null;
            }, $orderDetailParameter);


            $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('no_order', $request->no_order)->where('id_kategori_3', $category)->where('is_active', true)->first();
            if ($header == null) {
                $header = new LhpsLingHeader;
                $header->created_by = $this->karyawan;
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
            } else {
                $history = $header->replicate();
                $history->setTable((new LhpsLingHeaderHistory())->getTable());
                $history->created_by = $this->karyawan;
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->updated_by = null;
                $history->updated_at = null;
                $history->save();
                $header->updated_by = $this->karyawan;
                $header->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            }
            $parameter = \explode(', ', $request->parameter);
            $keterangan = [];
            foreach ($request->keterangan as $key => $value) {
                if ($value != '')
                    array_push($keterangan, $value);
            }
            // dd($request->no_sampel);

            $header->no_order = ($request->no_order != '') ? $request->no_order : NULL;
            $header->no_lhp = ($request->no_lhp != '') ? $request->no_lhp : NULL;
            $header->no_sampel = ($request->noSampel != '') ? $request->noSampel : NULL;
            $header->id_kategori_2 = ($request->kategori_2 != '') ? explode('-', $request->kategori_2)[0] : NULL;
            $header->id_kategori_3 = ($category != '') ? $category : NULL;
            $header->no_qt = ($request->no_penawaran != '') ? $request->no_penawaran : NULL;
            $header->parameter_uji = json_encode($parameter);
            $header->nama_pelanggan = ($request->nama_perusahaan != '') ? $request->nama_perusahaan : NULL;
            $header->alamat_sampling = ($request->alamat_sampling != '') ? $request->alamat_sampling : NULL;
            $header->sub_kategori = ($request->jenis_sampel != '') ? $request->jenis_sampel : NULL;
            $header->deskripsi_titik = ($request->keterangan_1 != '') ? $request->keterangan_1 : NULL;
            $header->methode_sampling = ($request->metode_sampling != '') ? $request->metode_sampling : NULL;
            $header->tanggal_sampling = ($request->tanggal_tugas != '') ? $request->tanggal_tugas : NULL;
            $header->periode_analisa = ($request->periode_analisa != '') ? $request->periode_analisa : NULL;
            $header->nama_karyawan = 'Abidah Walfathiyyah';
            $header->jabatan_karyawan = 'Technical Control Supervisor';
            // $header->nama_karyawan = 'Kharina Waty';
            // $header->jabatan_karyawan = 'Technical Control Manager';
            $header->keterangan = ($keterangan != null) ? json_encode($keterangan) : NULL;
            $header->regulasi = ($request->regulasi != null) ? json_encode($request->regulasi) : NULL;
            $header->suhu = ($request->suhu_udara != '') ? $request->suhu_udara : NULL;
            $header->cuaca = ($request->cuaca != '') ? $request->cuaca : NULL;
            $header->arah_angin = ($request->arah_angin != '') ? $request->arah_angin : NULL;
            $header->kelembapan = ($request->kelembapan_udara != '') ? $request->kelembapan_udara : NULL;
            $header->kec_angin = ($request->kecepatan_angin != '') ? $request->kecepatan_angin : NULL;
            $header->titik_koordinat = ($request->titik_koordinat != '') ? $request->titik_koordinat : NULL;
            // $header->header_table        = ($request->header_table != '') ? $request->header_table : NULL;
            // $header->file_qr             = ($request->file_qr != '') ? $request->file_qr : NULL;
            $header->tanggal_lhp = ($request->tanggal_lhp != '') ? $request->tanggal_lhp : NULL;
            $header->save();

            $detail = LhpsLingDetail::where('id_header', $header->id)->first();
            if ($detail != null) {
                $history = $detail->replicate();
                $history->setTable((new LhpsLingDetailHistory())->getTable());
                $history->created_by = $this->karyawan;
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->save();
            }
            $detail = LhpsLingDetail::where('id_header', $header->id)->delete();
            foreach ((array)explode(',', $request->parameter) as $key => $val) {
                $hasil = '';
                $akr = '';
                $satuan = '';
                $attr = '';
                $methode = '';
                $bakumutu = '';
                $val = trim($val, " '\"");
                if ($request->hasil_uji) {
                    $cleaned_key_hasil_uji = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil_uji ?? []));
                    $cleaned_hasil_uji = array_combine($cleaned_key_hasil_uji, array_values($request->hasil_uji ?? []));
                }
                if ($request->C) {
                    $cleaned_key_C = array_map(fn($k) => trim($k, " '\""), array_keys($request->C));
                    $cleaned_C = array_combine($cleaned_key_C, array_values($request->C));
                    $cleaned_key_C1 = array_map(fn($k) => trim($k, " '\""), array_keys($request->C1 ?? []));
                    $cleaned_C1 = array_combine($cleaned_key_C1, array_values($request->C1 ?? []));
                    $cleaned_key_C2 = array_map(fn($k) => trim($k, " '\""), array_keys($request->C2 ?? []));
                    $cleaned_C2 = array_combine($cleaned_key_C2, array_values($request->C2 ?? []));
                }
                $cleaned_key_akr = array_map(fn($k) => trim($k, " '\""), array_keys($request->akr ?? []));
                $cleaned_akr = array_combine($cleaned_key_akr, array_values($request->akr ?? []));
                $cleaned_key_satuan = array_map(fn($k) => trim($k, " '\""), array_keys($request->satuan ?? []));
                $cleaned_satuan = array_combine($cleaned_key_satuan, array_values($request->satuan ?? []));

                $cleaned_key_methode = array_map(fn($k) => trim($k, " '\""), array_keys($request->methode ?? []));
                $cleaned_methode = array_combine($cleaned_key_methode, array_values($request->methode ?? []));
                $cleaned_key_bakumutu = array_map(fn($k) => trim($k, " '\""), array_keys($request->baku_mutu ?? []));
                $cleaned_bakumutu = array_combine($cleaned_key_bakumutu, array_values($request->baku_mutu ?? []));
                $cleaned_key_durasi = array_map(fn($k) => trim($k, " '\""), array_keys($request->durasi ?? []));
                $cleaned_durasi = array_combine($cleaned_key_durasi, array_values($request->durasi ?? []));
                if (!empty($cleaned_hasil_uji[$val])) {
                    $hasil = $cleaned_hasil_uji[$val];
                } else {
                    if (!empty($cleaned_C[$val])) {
                        $hasil = $cleaned_C[$val];
                    } else if (!empty($cleaned_C1[$val])) {
                        $hasil = $cleaned_C1[$val];
                    } else if (!empty($cleaned_C2[$val])) {
                        $hasil = $cleaned_C2[$val];
                    }
                }
                if (array_key_exists($val, $cleaned_C)) {
                    $parame = Parameter::where('id_kategori', 4)->where('nama_lab', $val)->where('is_active', true)->first();

                    $detail = new LhpsLingDetail;
                    $detail->id_header = $header->id;
                    $detail->parameter_lab = $val;
                    $detail->parameter = $parame->nama_regulasi;
                    $detail->durasi = (isset($cleaned_durasi[$val]) ? $cleaned_durasi[$val] : '');
                    $detail->akr = $cleaned_akr[$val];
                    $detail->hasil_uji = $hasil;
                    $detail->satuan = (isset($cleaned_satuan[$val]) ? $cleaned_satuan[$val] : '');
                    $detail->methode = (isset($cleaned_methode[$val]) ? $cleaned_methode[$val] : '');
                    $detail->baku_mutu = (isset($cleaned_bakumutu[$val]) ? json_encode(array($cleaned_bakumutu[$val])) : '');
                    $detail->save();
                }
            }

            $details = LhpsLingDetail::where('id_header', $header->id)->get();
            if ($header != null) {
                $file_qr = new GenerateQrDocumentLhp();
                $file_qr = $file_qr->insert('LHP_LINGKUNGAN', $header, $this->karyawan);
                if ($file_qr) {
                    $header->file_qr = $file_qr;
                    $header->save();
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

                $job = new RenderLhp($header, $details, 'downloadWSDraft', $groupedByPage);
                $this->dispatch($job);

                $job = new RenderLhp($header, $details, 'downloadLHP', $groupedByPage);
                $this->dispatch($job);

                $fileName = 'LHP-' . str_replace("/", "-", $header->no_lhp) . '.pdf';
                $header->file_lhp = $fileName;
                $header->save();
            }
            // dd($header);
            DB::commit();
            return response()->json([
                'message' => 'Data draft LHP Lingkungan no sampel ' . $request->no_lhp . ' berhasil disimpan',
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

    //Amang
    public function handleDatadetail(Request $request)
    {
        try {

            $data = array();
            $data1 = array();
            $hasil = [];

            $data2 = Subkontrak::with('ws_value_linkungan', 'master_parameter')
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approve', 1)
                ->where('is_active', true)
                ->get();

            $data = LingkunganHeader::with('ws_value_linkungan', 'master_parameter')
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approved', 1)
                ->where('is_active', true)
                ->where('lhps', 1)
                ->get();

            if ($data2->isNotEmpty()) {
                $data = $data->merge($data2);
            }

            $i = 0;
            $method_regulasi = [];
            if ($data->isNotEmpty()) {
                foreach ($data as $key => $val) {

                    $data1[$i]['id'] = $val->id;
                    $data1[$i]['name'] = $val->parameter;
                    $data1[$i]['keterangan'] = $val->master_parameter->nama_regulasi ?? null;
                    $data1[$i]['satuan'] = $val->master_parameter->satuan ?? null;
                    $data1[$i]['durasi'] = $val->ws_value_linkungan->durasi ?? null;
                    $data1[$i]['hasil'] = $val->ws_value_linkungan->C ?? null;
                    $data1[$i]['methode'] = $val->master_parameter->method ?? null;
                    $data1[$i]['baku_mutu'] = is_object($val->master_parameter) && $val->master_parameter->nilai_minimum ? \explode('#', $val->master_parameter->nilai_minimum) : null;
                    $data1[$i]['status'] = $val->master_parameter->status ?? null;
                    // dd($val);
                    // return response()->json($val->ws_value_linkungan);
                    $data_lapangan[$i]['suhu'] = $val->ws_value_linkungan->detailLingkunganHidup->suhu;
                    $data_lapangan[$i]['kelembapan'] = $val->ws_value_linkungan->detailLingkunganHidup->kelembapan;
                    $data_lapangan[$i]['keterangan'] = $val->ws_value_linkungan->detailLingkunganHidup->keterangan;
                    $data_lapangan[$i]['cuaca'] = $val->ws_value_linkungan->detailLingkunganHidup->cuaca;
                    $data_lapangan[$i]['kecepatan_angin'] = $val->ws_value_linkungan->detailLingkunganHidup->kecepatan_angin;
                    $data_lapangan[$i]['arah_angin'] = $val->ws_value_linkungan->detailLingkunganHidup->arah_angin;
                    $data_lapangan[$i]['titik_koordinat'] = $val->ws_value_linkungan->detailLingkunganHidup->titik_koordinat;


                    $bakumutu = MasterBakumutu::where('id_regulasi', $request->regulasi)
                        ->where('parameter', $val->parameter)
                        ->first();

                    if ($bakumutu != null && $bakumutu->method != '') {
                        $data1[$i]['satuan'] = $bakumutu->satuan;
                        $data1[$i]['methode'] = $bakumutu->method;
                        $data1[$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                        array_push($method_regulasi, $bakumutu->method);
                    }
                    $i++;
                }
                $hasil[] = $data1;
            }


            $data_all = array();

            $a = 0;
            foreach ($hasil as $key => $value) {
                foreach ($value as $row => $col) {
                    $data_all[$a] = $col;
                    $a++;
                }
            }
            // dd($data_all);

            $method_regulasi = array_values(array_unique($method_regulasi));
            $method = Parameter::where('is_active', true)->where('id_kategori', 1)->whereNotNull('method')->select('method')->groupBy('method')->get()->toArray();
            $result_method = array_unique(array_values(array_merge($method_regulasi, array_column($method, 'method'))));


            $keterangan = [
                '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                'ẍ Parameter belum terakreditasi.'
            ];

            if (count($data_lapangan) > 0) {
                $data_lapangan_send = (object) $data_lapangan[0];
            } else {
                $data_lapangan_send = (object) $data_lapangan;
            }
            // dd('masuk');
            return response()->json([
                'status' => true,
                'data' => $data_all,
                'data_lapangan' => $data_lapangan_send,
                'spesifikasi_method' => $result_method,
                'keterangan' => $keterangan
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $e->getMessage()
            ]);
        }
    }

    public function handleDetailEdit(Request $request)
    {


        $category = explode('-', $request->kategori_3)[0];
        $parameters = json_decode(html_entity_decode($request->parameter), true);

        try {

            $data = LhpsLingHeader::where('no_sampel', $request->no_sampel)
                ->where('id_kategori_3', $category)
                ->where('is_active', true)
                ->first();
            $details = LhpsLingDetail::where('id_header', $data->id)->get();
            $spesifikasiMethode = LhpsLingHeader::with('ws_udara',)
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approved', 1)
                ->where('is_active', true)
                // ->where('lhps', 1)
                ->get();
            $i = 0;
            $method_regulasi = [];
            if ($spesifikasiMethode->isNotEmpty()) {
                foreach ($spesifikasiMethode as $key => $val) {
                    $bakumutu = MasterBakumutu::where('id_regulasi', $request->regulasi)
                        ->where('parameter', $val->parameter)
                        ->first();

                    if ($bakumutu != null && $bakumutu->method != '') {
                        $data1[$i]['satuan'] = $bakumutu->satuan;
                        $data1[$i]['methode'] = $bakumutu->method;
                        $data1[$i]['baku_mutu'][0] = $bakumutu->baku_mutu;
                        array_push($method_regulasi, $bakumutu->method);
                    }
                }
            }
            $method_regulasi = array_values(array_unique($method_regulasi));
            $method = Parameter::where('is_active', true)->where('id_kategori', 1)->whereNotNull('method')->select('method')->groupBy('method')->get()->toArray();
            $result_method = array_unique(array_values(array_merge($method_regulasi, array_column($method, 'method'))));
            return response()->json([
                'data' => $data,
                'details' => $details,
                'spesifikasi_method' => $result_method,
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
                'getFile' => $th->getFile(),
                'status' => false
            ], 500);
        }
    }
    public function handleApprove(Request $request)
    {
        $categoryKebisingan = [23, 24, 25];
        $categoryGetaran = [13, 14, 15, 16, 17, 18, 19, 20];
        $categoryLingkunganKerja = [11, 27, 53];
        $categoryPencahayaan = [28];
        $category = explode('-', $request->kategori_3)[0];
        $sub_category = explode('-', $request->kategori_3)[1];
        $data_order = OrderDetail::where('no_sampel', $request->no_lhp)
            ->where('id', $request->id)
            ->where('is_active', true)
            ->firstOrFail();

        if (in_array($category, $categoryKebisingan)) {
            try {
                $data = LhpsKebisinganHeader::where('no_lhp', $request->no_lhp)
                    ->where('id_kategori_3', $category)
                    ->where('is_active', true)
                    ->first();
                // dd($data);
                $qr = QrDocument::where('id_document', $data->id)
                    ->where('type_document', 'LHP_KEBISINGAN')
                    ->where('is_active', 1)
                    ->where('file', $data->file_qr)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($data != null) {
                    $data_order->is_approve = 1;
                    $data_order->status = 3;
                    $data_order->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data_order->approved_by = $this->karyawan;
                    $data_order->save();

                    $data->is_approve = 1;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->approved_by = $this->karyawan;
                    $data->nama_karyawan = $this->karyawan;
                    $data->jabatan_karyawan = $request->attributes->get('user')->karyawan->jabatan;
                    $data->save();
                    HistoryAppReject::insert([
                        'no_lhp' => $data_order->cfr,
                        'no_sampel' => $data_order->no_sampel,
                        'kategori_2' => $data_order->kategori_2,
                        'kategori_3' => $data_order->kategori_3,
                        'menu' => 'Draft Udara',
                        'status' => 'approve',
                        'approved_at' => Carbon::now(),
                        'approved_by' => $this->karyawan
                    ]);
                    if ($qr != null) {
                        $dataQr = json_decode($qr->data);
                        $dataQr->Tanggal_Pengesahan = Carbon::now()->format('Y-m-d H:i:s');
                        $dataQr->Disahkan_Oleh = $this->karyawan;
                        $dataQr->Jabatan = $request->attributes->get('user')->karyawan->jabatan;
                        $qr->data = json_encode($dataQr);
                        $qr->save();
                    }
                }

                DB::commit();
                return response()->json([
                    'data' => $data,
                    'status' => true,
                    'message' => 'Data draft LHP air no sampel ' . $request->no_lhp . ' berhasil diapprove'
                ], 201);
            } catch (\Exception $th) {
                DB::rollBack();
                dd($th);
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'status' => false
                ], 500);
            }
        } else if (in_array($category, $categoryGetaran)) {
            try {
                $data = LhpsGetaranHeader::where('no_lhp', $request->no_lhp)
                    ->where('id_kategori_3', $category)
                    ->where('is_active', true)
                    ->first();
                // dd($data);
                $qr = QrDocument::where('id_document', $data->id)
                    ->where('type_document', 'LHP_KEBISINGAN')
                    ->where('is_active', 1)
                    ->where('file', $data->file_qr)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($data != null) {
                    $data_order->is_approve = 1;
                    $data_order->status = 3;
                    $data_order->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data_order->approved_by = $this->karyawan;
                    $data_order->save();

                    $data->is_approve = 1;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->approved_by = $this->karyawan;
                    $data->nama_karyawan = $this->karyawan;
                    $data->jabatan_karyawan = $request->attributes->get('user')->karyawan->jabatan;
                    $data->save();
                    HistoryAppReject::insert([
                        'no_lhp' => $data_order->cfr,
                        'no_sampel' => $data_order->no_sampel,
                        'kategori_2' => $data_order->kategori_2,
                        'kategori_3' => $data_order->kategori_3,
                        'menu' => 'Draft Udara',
                        'status' => 'approve',
                        'approved_at' => Carbon::now(),
                        'approved_by' => $this->karyawan
                    ]);
                    if ($qr != null) {
                        $dataQr = json_decode($qr->data);
                        $dataQr->Tanggal_Pengesahan = Carbon::now()->format('Y-m-d H:i:s');
                        $dataQr->Disahkan_Oleh = $this->karyawan;
                        $dataQr->Jabatan = $request->attributes->get('user')->karyawan->jabatan;
                        $qr->data = json_encode($dataQr);
                        $qr->save();
                    }
                }

                DB::commit();
                return response()->json([
                    'data' => $data,
                    'status' => true,
                    'message' => 'Data draft LHP air no sampel ' . $request->no_lhp . ' berhasil diapprove'
                ], 201);
            } catch (\Exception $th) {
                DB::rollBack();
                dd($th);
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'status' => false
                ], 500);
            }
        } else if (in_array($category, $categoryPencahayaan)) {
            try {
                // Pencahayaan  dd($data);
                $data = LhpsPencahayaanHeader::where('no_lhp', $request->no_lhp)
                    ->where('id_kategori_3', $category)
                    ->where('is_active', true)
                    ->first();
                // dd($request->all());
                $details = LhpsPencahayaanDetail::where('id_header', $data->id)->get();
                // dd($data);
                $qr = QrDocument::where('id_document', $data->id)
                    ->where('type_document', 'LHP_PENCAHAYAAN')
                    ->where('is_active', 1)
                    ->where('file', $data->file_qr)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($data != null) {

                    $data_order->is_approve = 1;
                    $data_order->status = 3;
                    $data_order->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data_order->approved_by = $this->karyawan;
                    $data_order->save();

                    $data->is_approve = 1;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->approved_by = $this->karyawan;
                    $data->nama_karyawan = $this->karyawan;
                    $data->jabatan_karyawan = $request->attributes->get('user')->karyawan->jabatan;
                    $data->save();

                    HistoryAppReject::insert([
                        'no_lhp' => $data_order->cfr,
                        'no_sampel' => $data_order->no_sampel,
                        'kategori_2' => $data_order->kategori_2,
                        'kategori_3' => $data_order->kategori_3,
                        'menu' => 'Draft Udara',
                        'status' => 'approve',
                        'approved_at' => Carbon::now(),
                        'approved_by' => $this->karyawan
                    ]);
                    if ($qr != null) {
                        $dataQr = json_decode($qr->data);
                        $dataQr->Tanggal_Pengesahan = Carbon::now()->format('Y-m-d H:i:s');
                        $dataQr->Disahkan_Oleh = $this->karyawan;
                        $dataQr->Jabatan = $request->attributes->get('user')->karyawan->jabatan;
                        $qr->data = json_encode($dataQr);
                        $qr->save();
                    }
                }
                return response()->json([
                    'data' => $data,
                    'status' => true,
                    'message' => 'Data draft LHP air no sampel ' . $request->no_lhp . ' berhasil diapprove'
                ], 201);
            } catch (\Exception $th) {
                DB::rollBack();
                dd($th);
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'line' => $th->getLine(),
                    'getFile' => $th->getFile(),

                    'status' => false
                ], 500);
            }
        } else if (in_array($category, $categoryLingkunganKerja)) {
            try {
                // Lingkungan Kerja

                $data = LhpsLingHeader::where('no_lhp', $request->no_lhp)
                    ->where('id_kategori_3', $category)
                    ->where('is_active', true)
                    ->first();
                // dd($data);
                $details = LhpsLingDetail::where('id_header', $data->id)->get();
                $qr = QrDocument::where('id_document', $data->id)
                    ->where('type_document', 'LHP_LINGKUNGAN')
                    ->where('is_active', 1)
                    ->where('file', $data->file_qr)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($data != null) {
                    $data_order->is_approve = 1;
                    $data_order->status = 3;
                    $data_order->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data_order->approved_by = $this->karyawan;
                    $data_order->save();

                    $data->is_approve = 1;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->approved_by = $this->karyawan;
                    $data->nama_karyawan = $this->karyawan;
                    $data->jabatan_karyawan = $request->attributes->get('user')->karyawan->jabatan;
                    $data->save();

                    HistoryAppReject::insert([
                        'no_lhp' => $data_order->cfr,
                        'no_sampel' => $data_order->no_sampel,
                        'kategori_2' => $data_order->kategori_2,
                        'kategori_3' => $data_order->kategori_3,
                        'menu' => 'Draft Udara',
                        'status' => 'approve',
                        'approved_at' => Carbon::now(),
                        'approved_by' => $this->karyawan
                    ]);
                    if ($qr != null) {
                        $dataQr = json_decode($qr->data);
                        $dataQr->Tanggal_Pengesahan = Carbon::now()->format('Y-m-d H:i:s');
                        $dataQr->Disahkan_Oleh = $this->karyawan;
                        $dataQr->Jabatan = $request->attributes->get('user')->karyawan->jabatan;
                        $qr->data = json_encode($dataQr);
                        $qr->save();
                    }
                }
                return response()->json([
                    'data' => $data,
                    'status' => true,
                    'message' => 'Data draft LHP air no sampel ' . $request->no_lhp . ' berhasil diapprove'
                ], 200);
            } catch (\Exception $th) {
                return response()->json([
                    'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                    'line' => $th->getLine(),
                    'getFile' => $th->getFile(),

                    'status' => false
                ], 500);
            }
        }
    }

    // Amang
    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            // Kode Lama
            // $data = OrderDetail::where('id', $request->id)->first();
            // if ($data) {
            //     $data->status = 1;
            //     $data->save();
            //     DB::commit();
            //     return response()->json([
            //         'status' => 'success',
            //         'message' => 'Data draft no sample ' . $data->no_sampel . ' berhasil direject'
            //     ]);
            // }

            $data = OrderDetail::where('id', $request->id)->first();

            $kategori3 = $data->kategori_3;
            $category = (int) explode('-', $kategori3)[0];

            $categoryKebisingan = [23, 24, 25];
            $categoryGetaran = [13, 14, 15, 16, 17, 18, 19, 20];
            $categoryLingkunganKerja = [11, 27, 53];
            $categoryPencahayaan = [28];

            // Kebisingan
            if (in_array($category, $categoryKebisingan)) {
                $lhps = LhpsKebisinganHeader::where('no_lhp', $data->cfr)
                    ->where('no_order', $data->no_order)
                    ->where('id_kategori_3', $category)
                    ->where('is_active', true)
                    ->first();

                if ($lhps) {
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
            }
            // Getaran
            else if (in_array($category, $categoryGetaran)) {
                $lhps = LhpsGetaranHeader::where('no_lhp', $data->cfr)
                    ->where('no_order', $data->no_order)
                    ->where('id_kategori_3', $category)
                    ->where('is_active', true)
                    ->first();

                if ($lhps) {
                    // History Header Getaran
                    $lhpsHistory = $lhps->replicate();
                    $lhpsHistory->setTable((new LhpsGetaranHeaderHistory())->getTable());
                    $lhpsHistory->created_at = $lhps->created_at;
                    $lhpsHistory->updated_at = $lhps->updated_at;
                    $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                    $lhpsHistory->deleted_by = $this->karyawan;
                    $lhpsHistory->save();

                    // History Detail Getaran
                    $oldDetails = LhpsGetaranDetail::where('id_header', $lhps->id)->get();
                    foreach ($oldDetails as $detail) {
                        $detailHistory = $detail->replicate();
                        $detailHistory->setTable((new LhpsGetaranDetailHistory())->getTable());
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
            // Pencahayaan
            else if (in_array($category, $categoryPencahayaan)) {
                $lhps = LhpsPencahayaanHeader::where('no_lhp', $data->cfr)
                    ->where('no_order', $data->no_order)
                    ->where('id_kategori_3', $category)
                    ->where('is_active', true)
                    ->first();

                if ($lhps) {
                    // History Header Pencahayaan
                    $lhpsHistory = $lhps->replicate();
                    $lhpsHistory->setTable((new LhpsPencahayaanHeaderHistory())->getTable());
                    $lhpsHistory->created_at = $lhps->created_at;
                    $lhpsHistory->updated_at = $lhps->updated_at;
                    $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                    $lhpsHistory->deleted_by = $this->karyawan;
                    $lhpsHistory->save();

                    // History Detail Pencahayaan
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
            }
            // Lingkungan Kerja
            else if (in_array($category, $categoryLingkunganKerja)) {
                $orderDetail = OrderDetail::where('id', $request->id)
                    ->where('is_active', true)
                    ->where('kategori_3', 'LIKE', "%{$category}%")
                    ->first();

                if ($orderDetail) {
                    $orderDetailParameter = json_decode($orderDetail->parameter); // array of strings

                    $parsedParam = [];

                    foreach ($orderDetailParameter as $item) {
                        // Pecah berdasarkan tanda ';'
                        $parts = explode(';', $item);
                        // Ambil bagian ke-1 (index 1) jika ada
                        if (isset($parts[1])) {
                            $parsedParam[] = trim($parts[1]); // "Medan Magnit Statis"
                        }
                    }


                    if (in_array("Medan Listrik", $parsedParam) || in_array("Medan Magnit Statis", $parsedParam) || in_array("Power Density", $parsedParam)) {
                        $id_kategori = explode('-', $data->kategori_3);
                        $lhps = LhpsMedanLMHeader::where('no_lhp', $data->cfr)
                            ->where('no_order', $data->no_order)
                            ->where('id_kategori_3', $id_kategori[0])
                            ->where('is_active', true)
                            ->first();

                        if ($lhps) {
                            // History Header Medan Listrik, Medan Magnit Statis, Power Density
                            $lhpsHistory = $lhps->replicate();
                            $lhpsHistory->setTable((new LhpsMedanLMHeaderHistory())->getTable());
                            $lhpsHistory->created_at = $lhps->created_at;
                            $lhpsHistory->updated_at = $lhps->updated_at;
                            $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                            $lhpsHistory->deleted_by = $this->karyawan;
                            $lhpsHistory->save();

                            // History Detail Medan Listrik, Medan Magnit Statis, Power Density
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
                    }
                    // Sinar UV
                    else if (in_array("Sinar UV", $orderDetailParameter)) {
                        $lhps = LhpSinaruvHeader::where('no_lhp', $data->cfr)
                            ->where('no_order', $data->no_order)
                            ->where('kategori_3', $data->kategori_3)
                            ->where('is_active', true)
                            ->first();

                        if ($lhps) {
                            // History Header Sinar UV
                            $lhpsHistory = $lhps->replicate();
                            $lhpsHistory->setTable((new LhpSinarUVHeaderHistory())->getTable());
                            $lhpsHistory->created_at = $lhps->created_at;
                            $lhpsHistory->updated_at = $lhps->updated_at;
                            $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                            $lhpsHistory->deleted_by = $this->karyawan;
                            $lhpsHistory->save();

                            // History Detail Sinar UV
                            $oldDetails = LhpSinaruvDetail::where('id_header', $lhps->id)->get();
                            foreach ($oldDetails as $detail) {
                                $detailHistory = $detail->replicate();
                                $detailHistory->setTable((new LhpSinarUVDetailHistory())->getTable());
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
                    // Lingkungan Kerja
                    else {
                        $lhps = LhpsLingHeader::where('no_sampel', $data->no_sampel)
                            ->where('no_order', $data->no_order)
                            ->where('id_kategori_3', $category)
                            ->where('is_active', true)
                            ->first();

                        if ($lhps) {
                            // History Header Lingkungan Kerja
                            $lhpsHistory = $lhps->replicate();
                            $lhpsHistory->setTable((new LhpsLingHeaderHistory())->getTable());
                            $lhpsHistory->created_at = $lhps->created_at;
                            $lhpsHistory->updated_at = $lhps->updated_at;
                            $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                            $lhpsHistory->deleted_by = $this->karyawan;
                            $lhpsHistory->save();

                            // History Detail Lingkungan Kerja
                            $oldDetails = LhpsLingDetail::where('id_header', $lhps->id)->get();
                            foreach ($oldDetails as $detail) {
                                $detailHistory = $detail->replicate();
                                $detailHistory->setTable((new LhpsLingDetailHistory())->getTable());
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
                }
            }

            $data->status = 1;
            $data->save();
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Data draft no sample ' . $data->no_sampel . ' berhasil direject'
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
        $categoryKebisingan = [23, 24, 25];
        $categoryGetaran = [13, 14, 15, 16, 17, 18, 19, 20];
        $categoryLingkunganKerja = [11, 27, 53];
        $categoryPencahayaan = [28];
        $category = explode('-', $request->kategori_3)[0];
        $sub_category = explode('-', $request->kategori_3)[1];
        // dd('masuk');
        DB::beginTransaction();
        try {
            if (in_array($category, $categoryKebisingan)) {
                // dd('Kebisingan');
                $header = LhpsKebisinganHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                $quotation_status = "draft_lhp_kebisingan";
            } else if (in_array($category, $categoryGetaran)) {
                // dd('Getaran');
                $header = LhpsGetaranHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                $quotation_status = "draft_lhp_getaran";
            } else if (in_array($category, $categoryPencahayaan)) {
                // dd('Pencahayaan');
                $header = LhpsPencahayaanHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                $quotation_status = "draft_lhp_pencahayaan";
            } else if (in_array($category, $categoryLingkunganKerja)) {
                // dd('Lingkungan Kerja');
                if (is_string($request->parameter)) {
                    $decodedString = html_entity_decode($request->parameter);
                    $parameter = json_decode($decodedString, true);
                    $cleanedParameter = array_map(function ($item) {
                        return preg_replace('/^\d+;/', '', $item);
                    }, $parameter);
                }
                if (in_array("Medan Listrik", $cleanedParameter) || in_array("Medan Magnit Statis", $cleanedParameter) || in_array("Power Density", $cleanedParameter)) {
                    $header = LhpsMedanLMHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                    $quotation_status = "draft_lhp_medanlm";
                } else if (in_array("Sinar UV", $cleanedParameter)) {
                    $header = LhpsSinarUVHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                    $quotation_status = "draft_lhp_sinaruv";
                } else {
                    $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                    $quotation_status = "draft_lhp_lingkungan";
                }
            }
            if ($header != null) {
                $key = $header->no_sampel . str_replace('.', '', microtime(true));
                $gen = MD5($key);
                $gen_tahun = self::encrypt(DATE('Y-m-d'));
                $token = self::encrypt($gen . '|' . $gen_tahun);

                $insertData = [
                    'token' => $token,
                    'key' => $gen,
                    'id_quotation' => $header->id,
                    'quotation_status' => $quotation_status,
                    'type' => 'draft',
                    'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                    'fileName_pdf' => $header->file_lhp,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s')
                ];

                $insert = GenerateLink::insertGetId($insertData);

                $header->id_token = $insert;
                $header->is_generated = true;
                $header->generated_by = $this->karyawan;
                $header->generated_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->expired = Carbon::now()->addYear()->format('Y-m-d');
                // dd('masuk');
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
                'getFile' => $th->getFile(),

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
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'draft_lhp_air', 'type' => 'draft_air'])->first();

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
            $categoryKebisingan = [23, 24, 25];
            $categoryGetaran = [13, 14, 15, 16, 17, 18, 19, 20];
            $categoryLingkunganKerja = [11, 27, 53];
            $categoryPencahayaan = [28];
            $category = explode('-', $request->kategori)[0];
            $sub_category = explode('-', $request->kategori)[1];
            if ($request->id != '' || isset($request->id)) {
                if (in_array($category, $categoryKebisingan)) {
                    $data = LhpsKebisinganHeader::where('id', $request->id)->update([
                        'is_emailed' => true,
                        'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'emailed_by' => $this->karyawan
                    ]);
                } else if (in_array($category, $categoryGetaran)) {
                    $data = LhpsGetaranHeader::where('id', $request->id)->update([
                        'is_emailed' => true,
                        'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'emailed_by' => $this->karyawan
                    ]);
                } else if (in_array($category, $categoryPencahayaan)) {
                    $data = LhpsPencahayaanHeader::where('id', $request->id)->update([
                        'is_emailed' => true,
                        'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'emailed_by' => $this->karyawan
                    ]);
                } else if (in_array($category, $categoryLingkunganKerja)) {
                    if (is_string($request->parameter)) {
                        $decodedString = html_entity_decode($request->parameter);
                        $parameter = json_decode($decodedString, true);
                        $cleanedParameter = array_map(function ($item) {
                            return preg_replace('/^\d+;/', '', $item);
                        }, $parameter);
                    }
                    if (in_array("Medan Listrik", $cleanedParameter) || in_array("Medan Magnit Statis", $cleanedParameter) || in_array("Power Density", $cleanedParameter)) {
                        $data = LhpsMedanLMHeader::where('id', $request->id)->update([
                            'is_emailed' => true,
                            'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                            'emailed_by' => $this->karyawan
                        ]);
                    } else if (in_array("Sinar UV", $cleanedParameter)) {
                        $data = LhpsSinarUVHeader::where('id', $request->id)->update([
                            'is_emailed' => true,
                            'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                            'emailed_by' => $this->karyawan
                        ]);
                    } else {
                        $data = LhpsLingHeader::where('id', $request->id)->update([
                            'is_emailed' => true,
                            'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                            'emailed_by' => $this->karyawan
                        ]);
                    }
                }
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
                'line' => $th->getLine(),
                'getFile' => $th->getFile(),

            ], 500);
        }
    }

    // public function setSignature(Request $request)
    // {
    //     $categoryKebisingan = [23, 24, 25];
    //     $categoryGetaran = [13, 14, 15, 16, 17, 18, 19, 20];
    //     $categoryLingkunganKerja = [11, 27, 53];
    //     $categoryPencahayaan = [28];

    //     try {
    //         if (in_array($request->category, $categoryKebisingan)) {
    //             $header = LhpsKebisinganHeader::where('id', $request->id)->first();
    //             $detail = LhpsKebisinganDetail::where('id_header', $header->id)->get();
    //         } else if (in_array($request->category, $categoryPencahayaan)) {
    //             $header = LhpsPencahayaanHeader::where('id', $request->id)->first();
    //             $detail = LhpsPencahayaanDetail::where('id_header', $header->id)->get();
    //         } else if (in_array($request->category, $categoryLingkunganKerja)) {
    //             if ($request->mode == "medanlm") {
    //                 $header = LhpsMedanLMHeader::where('id', $request->id)->first();
    //                 $detail = LhpsMedanLMDetail::where('id_header', $header->id)->get();
    //             } else if ($request->mode == "sinaruv") {
    //                 $header = LhpsSinarUVHeader::where('id', $request->id)->first();
    //                 $detail = LhpsSinarUVDetail::where('id_header', $header->id)->get();
    //             } else {
    //                 $header = LhpsLingHeader::where('id', $request->id)->first();
    //                 $detail = LhpsLingDetail::where('id_header', $header->id)->get();
    //             }
    //         }

    //         if ($header != null) {
    //             $header->nama_karyawan = $this->karyawan;
    //             $header->jabatan_karyawan = $request->attributes->get('user')->karyawan->jabatan;
    //             $header->save();

    //             $file_qr = new GenerateQrDocumentLhp();
    //             $file_qr = $file_qr->insert('LHP_AIR', $header, $this->karyawan);
    //             if ($file_qr) {
    //                 $header->file_qr = $file_qr;
    //                 $header->save();
    //             }

    //             $groupedByPage = [];
    //             if (!empty($custom)) {
    //                 foreach ($custom as $item) {
    //                     $page = $item['page'];
    //                     if (!isset($groupedByPage[$page])) {
    //                         $groupedByPage[$page] = [];
    //                     }
    //                     $groupedByPage[$page][] = $item;
    //                 }
    //             }

    //             $job = new RenderLhp($header, $detail, 'downloadWSDraft', $groupedByPage);
    //             $this->dispatch($job);

    //             $job = new RenderLhp($header, $detail, 'downloadLHP', $groupedByPage);
    //             $this->dispatch($job);

    //             return response()->json([
    //                 'message' => 'Signature berhasil diubah'
    //             ], 200);
    //         }
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => $e->getMessage(),
    //             'line' => $e->getLine(),
    //             'file' => $e->getFile()
    //         ]);
    //     }
    // }

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
