<?php

namespace App\Http\Controllers\api;
use App\Models\HistoryAppReject;
use App\Models\LhpsKebisinganHeader;
use App\Models\LhpsLingHeader;
use App\Models\LhpsLingDetail;
use App\Models\LhpsPencahayaanHeader;
use App\Models\LhpsGetaranHeader;

use App\Models\LhpsMedanLMHeader;
use App\Models\LhpsMedanLMDetail;

use App\Models\LhpsMedanLMHeaderHistory;
use App\Models\LhpsMedanLMDetailHistory;
use App\Models\LhpsSinarUVHeader;
use App\Models\LhpsSinarUVDetail;
use App\Models\LhpsSinarUVHeaderHistory;
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

use App\Models\Subkontrak;
use App\Models\MedanLMHeader;
use App\Models\SinarUVHeader;

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

class DraftUlkSinarUvController extends Controller
{
    // done if status = 2
    // AmanghandleDatadetail
    public function index()
    {
        DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([

            'lhps_sinaruv',
            'orderHeader' => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->where('kategori_3', "27-Udara Lingkungan Kerja")
            ->where('parameter', 'like', '%Sinar UV%')
            ->groupBy('cfr')
            ->where('status', 2)
            ->get();

        return Datatables::of($data)->make(true);
    }

    // Amang
    public function getKategori()
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
            $subKategori = explode('-', $request->kategori_3);
            $data = MetodeSampling::where('kategori', '4-UDARA')
                ->where('sub_kategori', strtoupper($subKategori[1]))->get();
            if ($data->isNotEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Available data retrieved successfully',
                    'data' => $data
                ], 200);
            } else {
                return response()->json([
                    'status' => true,
                    'message' => 'Belom ada method',
                    'data' => []
                ], 200);
            }
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
        $category = explode('-', $request->kategori_3)[0];
        // dd($request->all());
        DB::beginTransaction();
        try {

            $orderDetail = OrderDetail::where('id', $request->id)->where('is_active', true)->where('kategori_3', 'LIKE', "%{$category}%")->where('cfr', $request->no_lhp)->first();
            $orderDetailParameter = json_decode($orderDetail->parameter);
            $parameterNames = array_map(function ($param) {
                $parts = explode(';', $param);
                return $parts[1] ?? null;
            }, $orderDetailParameter);


            $id_kategori3 = explode('-', $request->kategori_3)[0];
            $header = LhpsSinarUVHeader::where('no_lhp', $request->no_lhp)->where('no_order', $request->no_order)->where('id_kategori_3', $id_kategori3)->where('is_active', true)->first();


            if ($header == null) {
                $header = new LhpsSinarUVHeader;
                $header->created_by = $this->karyawan;
                $header->created_at = DATE('Y-m-d H:i:s');
                // dd('masuk');
            } else {
                $history = $header->replicate();
                $history->setTable((new LhpsSinarUVHeaderHistory())->getTable());
                $history->created_by = $this->karyawan;
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->updated_by = null;
                $history->updated_at = null;
                $history->save();
                $header->updated_by = $this->karyawan;
                $header->updated_at = DATE('Y-m-d H:i:s');
            }
            $parameter = is_array($request->parameter) ? $request->parameter : explode(', ', $request->parameter);
            $keterangan = [];
            if (is_array($request->keterangan)) {
                foreach ($request->keterangan as $key => $value) {
                    if ($value != '') {
                        array_push($keterangan, $value);
                    }
                }
            }
            $header->no_order = ($request->no_order != '') ? $request->no_order : NULL;
            $header->no_lhp = ($request->no_lhp != '') ? $request->no_lhp : NULL;
            $header->no_sampel = ($request->noSampel != '') ? $request->noSampel : NULL;
            $header->no_qt = ($request->no_penawaran != '') ? $request->no_penawaran : NULL;
            $header->jenis_sampel = ($request->kategori_3 != '') ? explode("-", $request->kategori_3)[1] : NULL;
            $header->parameter_uji = json_encode($parameter);
            $header->nama_karyawan = 'Abidah Walfathiyyah';
            $header->jabatan_karyawan = 'Technical Control Supervisor';
            // $header->nama_karyawan = 'Kharina Waty';
            // $header->jabatan_karyawan = 'Technical Control Manager';
            $header->nama_pelanggan = ($request->nama_perusahaan != '') ? $request->nama_perusahaan : NULL;
            $header->alamat_sampling = ($request->alamat_sampling != '') ? $request->alamat_sampling : NULL;
            $header->id_kategori_3 = ($id_kategori3 != '') ? $id_kategori3 : NULL;
            $header->sub_kategori = ($request->kategori_3 != '') ? explode("-", $request->kategori_3)[1] : NULL;
            $header->metode_sampling = ($request->metode_sampling != '') ? $request->metode_sampling : NULL;
            $header->keterangan = ($keterangan != null) ? json_encode($keterangan) : NULL;
            $header->tanggal_lhp = ($request->tanggal_lhp != '') ? $request->tanggal_lhp : NULL;
            $header->tanggal_sampling = ($request->tanggal_sampling != '') ? $request->tanggal_sampling : NULL;
            $header->tanggal_sampling_text = ($request->tgl_terima_hide != '') ? $request->tgl_terima_hide : NULL;
            $header->periode_analisa = ($request->periode_analisa != '') ? $request->periode_analisa : NULL;
            $header->regulasi = ($request->regulasi != null) ? json_encode($request->regulasi) : NULL;
            // dd($request->regulasi);
            if (count(array_filter($request->regulasi)) > 0) {
                $header->id_regulasi = ($request->regulasi1 != null) ? $request->regulasi1 : NULL;
            }

            $header->save();

            $detail = LhpsSinarUVDetail::where('id_header', $header->id)->first();
            if ($detail != null) {
                $history = $detail->replicate();
                $history->setTable((new LhpsSinarUVDetailHistory())->getTable());
                $history->created_by = $this->karyawan;
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->save();
            }
            $detail = LhpsSinarUVDetail::where('id_header', $header->id)->delete();
            foreach ((array) $request->parameter as $key => $val) {
                $cleaned_key_no_sampel = array_map(fn($k) => trim($k, " '\""), array_keys($request->no_sampel));
                $cleaned_no_sampel = array_combine($cleaned_key_no_sampel, array_values($request->no_sampel));
                $cleaned_key_nab = array_map(fn($k) => trim($k, " '\""), array_keys($request->nab));
                $cleaned_nab = array_combine($cleaned_key_nab, array_values($request->nab));
                $cleaned_key_waktu_pemaparan = array_map(fn($k) => trim($k, " '\""), array_keys($request->waktu_pemaparan));
                $cleaned_waktu_pemaparan = array_combine($cleaned_key_waktu_pemaparan, array_values($request->waktu_pemaparan));
                $cleaned_key_keterangan = array_map(fn($k) => trim($k, " '\""), array_keys($request->keterangan2));
                $cleaned_keterangan = array_combine($cleaned_key_keterangan, array_values($request->keterangan2));
                $cleaned_key_mata = array_map(fn($k) => trim($k, " '\""), array_keys($request->mata));
                $cleaned_mata = array_combine($cleaned_key_mata, array_values($request->mata));
                $cleaned_key_siku = array_map(fn($k) => trim($k, " '\""), array_keys($request->siku));
                $cleaned_siku = array_combine($cleaned_key_siku, array_values($request->siku));
                $cleaned_key_betis = array_map(fn($k) => trim($k, " '\""), array_keys($request->betis));
                $cleaned_betis = array_combine($cleaned_key_betis, array_values($request->betis));

                if (array_key_exists($val, $cleaned_keterangan)) {

                    $parame = Parameter::where('id_kategori', 4)->where('nama_lab', $val)->where('is_active', true)->first();
                    $detail = new LhpsSinarUVDetail;
                    $detail->id_header = $header->id;
                    $detail->no_sampel = $cleaned_no_sampel[$val];
                    $detail->parameter = $val;
                    $detail->keterangan = $cleaned_keterangan[$val];
                    $detail->nab = $cleaned_nab[$val];
                    $detail->waktu_pemaparan = $cleaned_waktu_pemaparan[$val];
                    $detail->mata = $cleaned_mata[$val];
                    $detail->betis = $cleaned_betis[$val];
                    $detail->siku = $cleaned_siku[$val];
                    $detail->save();

                }
            }
            $details = LhpsSinarUVDetail::where('id_header', $header->id)->get();
            if ($header != null) {
                $file_qr = new GenerateQrDocumentLhp();
                $file_qr = $file_qr->insert('LHP_SINAR_UV', $header, $this->karyawan);
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

                $job = new RenderLhp($header, $details, 'downloadLHPFinal', $groupedByPage);
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
            $data_lapangan = array();
            $id_category = explode('-', $request->kategori_3)[0];

            $parameters = json_decode(html_entity_decode($request->parameter), true);
            $parameterArray = is_array($parameters) ? array_map('trim', explode(';', $parameters[0])) : [];
            // dd('id_category', $id_category);
            $data = array();
            $data1 = array();
            $hasil = [];
                $data = SinarUvHeader::with(['ws_udara', 'master_parameter', 'datalapangan'])
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approved', true)
                    ->where('is_active', true)
                    ->select('*')
                    ->get();
                $i = 0;
                $method_regulasi = [];
                if ($data->isNotEmpty()) {
                    foreach ($data as $key => $val) {
                        // dd($val->datalapangan->waktu_pemaparan);

                        $hasil2 = $val->ws_udara ? json_decode($val->ws_udara->hasil1) : null;
                        $data1[$i]['id'] = $val->id;
                        $data1[$i]['no_sampel'] = $val->no_sampel;
                        $data1[$i]['parameter'] = $val->master_parameter->nama_lab ?? null;
                        if ($val->datalapangan->keterangan_2 == '-') {
                            dd($val->datalapangan->keterangan_2);
                            $data1[$i]['keterangan'] = $val->datalapangan->aktivitas_pekerja;
                        } else {
                            $keterangan = strpos($val->datalapangan->keterangan_2, ':') !== false
                                ? explode(":", $val->datalapangan->keterangan_2)
                                : [$val->datalapangan->keterangan_2];
                            $data1[$i]['keterangan'] = (isset($keterangan[1]) ? $keterangan[1] : $keterangan[0]) . ' - ' . $val->datalapangan->aktivitas_pekerja;
                        }
                        // $data1[$i]['keterangan'] = $val->master_parameter->nama_regulasi ?? null;
                        $data1[$i]['satuan'] = $val->master_parameter->satuan ?? null;
                        $data1[$i]['mata'] = $hasil2->Mata ?? null;
                        $data1[$i]['nab'] = $val->ws_udara->nab ?? null;
                        $data1[$i]['betis'] = $hasil2->Betis ?? null;
                        $data1[$i]['siku'] = $hasil2->Siku ?? null;
                        $data1[$i]['waktu_pemaparan'] = $val->datalapangan->waktu_pemaparan ?? null;

                        $data1[$i]['methode'] = $val->master_parameter->method ?? null;
                        $data1[$i]['baku_mutu'] = is_object($val->master_parameter) && $val->master_parameter->nilai_minimum ? \explode('#', $val->master_parameter->nilai_minimum) : null;
                        $data1[$i]['status'] = $val->master_parameter->status ?? null;
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
            $method_regulasi = array_values(array_unique($method_regulasi));
            $method = Parameter::where('is_active', true)->where('id_kategori', 1)->whereNotNull('method')->select('method')->groupBy('method')->get()->toArray();
            $result_method = array_unique(array_values(array_merge($method_regulasi, array_column($method, 'method'))));

            if ($id_category == 11 || $id_category == 27) {
                $keterangan = [
                    '▲ Hasil Uji melampaui nilai ambang batas yang diperbolehkan.',
                    '↘ Parameter diuji langsung oleh pihak pelanggan, bukan bagian dari parameter yang dilaporkan oleh laboratorium.',
                    'ẍ Parameter belum terakreditasi.'
                ];
            } else {
                $keterangan = [];
            }
            if (count($data_lapangan) > 0) {
                $data_lapangan_send = (object) $data_lapangan[0];
            } else {
                $data_lapangan_send = (object) $data_lapangan;
            }

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
        // dd("masuk");
        $sub_category = explode('-', $request->kategori_3)[1];
        $parameters = json_decode(html_entity_decode($request->parameter), true);
        $parameterArray = is_array($parameters) ? array_map('trim', explode(';', $parameters[0])) : [];
        try {
            if ($parameterArray[1] == 'Medan Magnit Statis' || $parameterArray[1] == 'Medan Listrik' || $parameterArray[1] == 'Power Density') {
                $data = LhpsMedanLMHeader::where('no_sampel', $request->no_sampel)
                    ->where('is_approve', 0)
                    ->where('is_active', true)->first();
                $details = LhpsMedanLMDetail::where('id_header', $data->id)->get();

                $spesifikasiMethode = MedanLMHeader::with('ws_udara')
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approve', 1)
                    ->where('is_active', true)
                    ->where('lhps', 1)->get();
                // dd($request->all());
                $i = 0;
                $method_regulasi = [];
                if ($spesifikasiMethode->isNotEmpty()) {
                    foreach ($spesifikasiMethode as $key => $val) {
                        $bakumutu = MasterBakumutu::where('id_parameter', $val->id_parameter)
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
                // dd($method_regulasi);
                // $method = Parameter::where('is_active', true)->where('id_kategori', 4)->whereNotNull('method')->select('method')->groupBy('method')->get()->toArray();
                // $result_method = array_unique(array_values(array_merge($method_regulasi, array_column($method, 'method'))));
                return response()->json([
                    'data' => $data,
                    'details' => $details,
                    'spesifikasi_method' => $method_regulasi
                ], 201);
            } else {
                $data = LhpsSinarUVHeader::where('no_sampel', $request->no_sampel)
                    ->where('id_kategori_3', $category)
                    ->where('is_active', true)
                    ->first();
                $details = LhpsSinarUVDetail::where('id_header', $data->id)->get();
                // dd($details);
                $spesifikasiMethode = LhpsSinarUVHeader::with('ws_udara', 'data_lapangan', 'master_parameter')
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approve', 1)
                    ->where('is_active', true)
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
            }



        } catch (\Exception $th) {
            dd($th);
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
                'status' => false
            ], 500);
        }
    }
    public function handleApprove(Request $request)
    {

        $categoryLingkunganKerja = [11, 27, 53];

        $category = explode('-', $request->kategori_3)[0];
        $sub_category = explode('-', $request->kategori_3)[1];
        $data_order = OrderDetail::where('no_sampel', $request->no_lhp)
            ->where('id', $request->id)
            ->where('is_active', true)
            ->firstOrFail();

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
                'status' => false
            ], 500);
        }
    }

    // Amang
    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {

            $data = OrderDetail::where('id', $request->id)->first();

            $kategori3 = $data->kategori_3;
            $category = (int) explode('-', $kategori3)[0];

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
                else if (in_array("324;Sinar UV", $orderDetailParameter)) {
                    $lhps = LhpsSinarUVHeader::where('no_lhp', $data->cfr)
                        ->where('no_order', $data->no_order)
                        ->where('id_kategori_3', explode('-', $data->kategori_3)[0])
                        ->where('is_active', true)
                        ->first();

                    if ($lhps) {
                        // History Header Sinar UV
                        $lhpsHistory = $lhps->replicate();
                        $lhpsHistory->setTable((new LhpsSinarUVHeaderHistory())->getTable());
                        $lhpsHistory->created_at = $lhps->created_at;
                        $lhpsHistory->updated_at = $lhps->updated_at;
                        $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                        $lhpsHistory->deleted_by = $this->karyawan;
                        $lhpsHistory->save();
                        // dd('masuk');

                        // History Detail Sinar UV
                        $oldDetails = LhpsSinarUVDetail::where('id_header', $lhps->id)->get();
                        foreach ($oldDetails as $detail) {
                            $detailHistory = $detail->replicate();
                            $detailHistory->setTable((new LhpsSinarUVDetailHistory())->getTable());
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
            ], 400);
        }
    }

    // Amang
    public function generate(Request $request)
    {


        // dd('masuk');
        DB::beginTransaction();
        try {
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
                'line' => $th->getLine()
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
