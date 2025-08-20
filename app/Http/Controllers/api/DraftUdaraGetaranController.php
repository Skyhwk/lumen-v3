<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;

use App\Models\LhpsGetaranHeader;
use App\Models\LhpsGetaranDetail;


use App\Models\LhpsGetaranHeaderHistory;
use App\Models\LhpsGetaranDetailHistory;


use App\Models\MasterSubKategori;
use App\Models\OrderDetail;
use App\Models\MetodeSampling;
use App\Models\MasterBakumutu;
use App\Models\MasterKaryawan;
use App\Models\QrDocument;

use App\Models\GetaranHeader;

use App\Models\Parameter;
use App\Models\GenerateLink;
use App\Services\SendEmail;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DraftUdaraGetaranController extends Controller
{
    // done if status = 2
    // AmanghandleDatadetail
    public function index(Request $request)
    {
        $kategori = ["13-Getaran", "14-Getaran (Bangunan)", "15-Getaran (Kejut Bangunan)", "16-Getaran (Kejut Bangunan)", "17-Getaran (Lengan & Tangan)", "18-Getaran (Lingkungan)", "19-Getaran (Mesin)", "20-Getaran (Seluruh Tubuh)"];

        DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
            'lhps_getaran',
            'lhps_kebisingan',
            'lhps_ling',
            'lhps_medanlm',
            'lhps_pencahayaan',
            'lhps_sinaruv',
            'orderHeader'
            => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', $kategori)
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
        DB::beginTransaction();
        try {

            $header = LhpsGetaranHeader::where('no_lhp', $request->no_lhp)->where('no_order', $request->no_order)->where('id_kategori_3', $category)->where('is_active', true)->first();
            if ($header == null) {
                $header = new LhpsGetaranHeader;
                $header->created_by = $this->karyawan;
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
            } else {
                // dd('update');
                $history = $header->replicate();
                $history->setTable((new LhpsGetaranHeaderHistory())->getTable());
                $history->created_by = $this->karyawan;
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->updated_by = null;
                $history->updated_at = null;
                $history->save();
                $header->updated_by = $this->karyawan;
                $header->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            }
            $parameter = \explode(', ', $request->parameter);
            $header->no_order = ($request->no_order != '') ? $request->no_order : NULL;
            $header->no_lhp = ($request->no_lhp != '') ? $request->no_lhp : NULL;
            $header->no_sampel = ($request->noSampel != '') ? $request->noSampel : NULL;
            $header->id_kategori_2 = ($request->kategori_2 != '') ? explode('-', $request->kategori_2)[0] : NULL;
            $header->id_kategori_3 = ($category != '') ? $category : NULL;
            $header->no_qt = ($request->no_penawaran != '') ? $request->no_penawaran : NULL;
            // $header->parameter_uji = $request->parameter;

            $header->parameter_uji = json_encode($parameter);
            $header->nama_pelanggan = ($request->nama_perusahaan != '') ? $request->nama_perusahaan : NULL;
            $header->alamat_sampling = ($request->alamat_sampling != '') ? $request->alamat_sampling : NULL;
            $header->keterangan = ($request->keterangan != '') ? $request->keterangan : NULL;
            // dd('masuk');
            $header->sub_kategori = ($request->jenis_sampel != '') ? $request->jenis_sampel : NULL;
            // $header->deskripsi_titik = ($request->keterangan_1 != '') ? $request->keterangan_1 : NULL;
            $header->metode_sampling = ($request->method_sampling != '') ? $request->method_sampling : NULL;
            $header->tanggal_sampling = ($request->tanggal_tugas != '') ? $request->tanggal_tugas : NULL;
            $header->periode_analisa = ($request->periode_analisa != '') ? $request->periode_analisa : NULL;
            $header->nama_karyawan = 'Kharina Waty';
            $header->jabatan_karyawan = 'Technical Control Manager';
            $header->regulasi = ($request->regulasi != null) ? json_encode($request->regulasi) : NULL;
            $header->tanggal_lhp = ($request->tanggal_lhp != '') ? $request->tanggal_lhp : NULL;


            $header->save();

            $details = LhpsGetaranDetail::where('id_header', $header->id)->get();
            foreach ($details as $detail) {
                $history = $detail->replicate();
                $history->setTable((new LhpsGetaranDetailHistory())->getTable());
                $history->created_by = $this->karyawan;
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->save();
            }
            $detail = LhpsGetaranDetail::where('id_header', $header->id)->delete();
            foreach ($request->param as $key => $val) {
                if (in_array("Getaran (LK) ST", $parameter)) {
                    // dd($request->all());
                    $cleaned_key_no_sampel = array_map(fn($k) => trim($k, " '\""), array_keys($request->no_sampel));
                    $cleaned_no_sampel = array_combine($cleaned_key_no_sampel, array_values($request->no_sampel));
                    $cleaned_key_tipe_getaran = array_map(fn($k) => trim($k, " '\""), array_keys($request->tipe_getaran));
                    $cleaned_tipe_getaran = array_combine($cleaned_key_tipe_getaran, array_values($request->tipe_getaran));
                    $cleaned_key_param = array_map(fn($k) => trim($k, " '\""), array_keys($request->param));
                    $cleaned_param = array_combine($cleaned_key_param, array_values($request->param));
                    $cleaned_key_hasil = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil));
                    $cleaned_hasil = array_combine($cleaned_key_hasil, array_values($request->hasil));
                    $cleaned_key_keterangan_detail = array_map(fn($k) => trim($k, " '\""), array_keys($request->keterangan_detail));
                    $cleaned_keterangan_detail = array_combine($cleaned_key_keterangan_detail, array_values($request->keterangan_detail));
                    $cleaned_key_sumber_getaran = array_map(fn($k) => trim($k, " '\""), array_keys($request->sumber_getaran));
                    $cleaned_sumber_getaran = array_combine($cleaned_key_sumber_getaran, array_values($request->sumber_getaran));
                    $cleaned_key_durasi_paparan = array_map(fn($k) => trim($k, " '\""), array_keys($request->waktu));
                    $cleaned_durasi_paparan = array_combine($cleaned_key_durasi_paparan, array_values($request->waktu));
                    $cleaned_key_nab = array_map(fn($k) => trim($k, " '\""), array_keys($request->nab));
                    $cleaned_nab = array_combine($cleaned_key_nab, array_values($request->nab));
                    if (array_key_exists($val, $cleaned_keterangan_detail)) {

                        $detail = new LhpsGetaranDetail;
                        $detail->id_header = $header->id;
                        $detail->no_sampel = $cleaned_no_sampel[$val];
                        $detail->keterangan = $cleaned_keterangan_detail[$val];
                        $detail->param = $cleaned_param[$val];
                        $detail->sumber_get = $cleaned_sumber_getaran[$val];
                        $detail->w_paparan = $cleaned_durasi_paparan[$val];
                        $detail->hasil = $cleaned_hasil[$val];
                        $detail->tipe_getaran = $cleaned_tipe_getaran[$val];
                        $detail->nab = $cleaned_nab[$val];
                        $detail->save();
                    }
                } else if (in_array("Getaran (LK) TL", $parameter)) {

                    $cleaned_key_no_sampel = array_map(fn($k) => trim($k, " '\""), array_keys($request->no_sampel));
                    $cleaned_no_sampel = array_combine($cleaned_key_no_sampel, array_values($request->no_sampel));
                    $cleaned_key_param = array_map(fn($k) => trim($k, " '\""), array_keys($request->param));
                    $cleaned_param = array_combine($cleaned_key_param, array_values($request->param));
                    $cleaned_key_hasil = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil));
                    $cleaned_hasil = array_combine($cleaned_key_hasil, array_values($request->hasil));
                    $cleaned_key_tipe_getaran = array_map(fn($k) => trim($k, " '\""), array_keys($request->tipe_getaran));
                    $cleaned_tipe_getaran = array_combine($cleaned_key_tipe_getaran, array_values($request->tipe_getaran));
                    $cleaned_key_keterangan_detail = array_map(fn($k) => trim($k, " '\""), array_keys($request->keterangan_detail));
                    $cleaned_keterangan_detail = array_combine($cleaned_key_keterangan_detail, array_values($request->keterangan_detail));
                    $cleaned_key_sumber_getaran = array_map(fn($k) => trim($k, " '\""), array_keys($request->sumber_getaran));
                    $cleaned_sumber_getaran = array_combine($cleaned_key_sumber_getaran, array_values($request->sumber_getaran));
                    $cleaned_key_durasi_paparan = array_map(fn($k) => trim($k, " '\""), array_keys($request->waktu));
                    $cleaned_durasi_paparan = array_combine($cleaned_key_durasi_paparan, array_values($request->waktu));
                    $cleaned_key_nab = array_map(fn($k) => trim($k, " '\""), array_keys($request->nab));
                    $cleaned_nab = array_combine($cleaned_key_nab, array_values($request->nab));
                    if (array_key_exists($val, $cleaned_keterangan_detail)) {
                        $detail = new LhpsGetaranDetail;
                        $detail->id_header = $header->id;
                        $detail->no_sampel = $cleaned_no_sampel[$val];
                        $detail->param = $cleaned_param[$val];
                        $detail->keterangan = $cleaned_keterangan_detail[$val];
                        $detail->sumber_get = $cleaned_sumber_getaran[$val];
                        $detail->w_paparan = $cleaned_durasi_paparan[$val];
                        $detail->hasil = $cleaned_hasil[$val];
                        $detail->tipe_getaran = $cleaned_tipe_getaran[$val];
                        $detail->nab = $cleaned_nab[$val];
                        $detail->save();

                    }
                } else {
                    $cleaned_key_percepatan = array_map(fn($k) => trim($k, " '\""), array_keys($request->percepatan));
                    $cleaned_percepatan = array_combine($cleaned_key_percepatan, array_values($request->percepatan));
                    $cleaned_key_kecepatan = array_map(fn($k) => trim($k, " '\""), array_keys($request->kecepatan));
                    $cleaned_kecepatan = array_combine($cleaned_key_kecepatan, array_values($request->kecepatan));
                    $cleaned_key_tipe_getaran = array_map(fn($k) => trim($k, " '\""), array_keys($request->tipe_getaran));
                    $cleaned_tipe_getaran = array_combine($cleaned_key_tipe_getaran, array_values($request->tipe_getaran));
                    $cleaned_key_lokasi = array_map(fn($k) => trim($k, " '\""), array_keys($request->lokasi));
                    $cleaned_lokasi = array_combine($cleaned_key_lokasi, array_values($request->lokasi));
                    $cleaned_key_noSampel = array_map(fn($k) => trim($k, " '\""), array_keys($request->noSampel));
                    $cleaned_noSampel = array_combine($cleaned_key_noSampel, array_values($request->noSampel));

                    if (array_key_exists($val, $cleaned_noSampel)) {

                        $detail = new LhpsGetaranDetail;
                        $detail->id_header = $header->id;
                        $detail->no_sampel = $val;
                        $detail->keterangan = $cleaned_lokasi[$val];
                        $detail->percepatan = $cleaned_percepatan[$val];
                        $detail->kecepatan = $cleaned_percepatan[$val];
                        $detail->tipe_getaran = $cleaned_tipe_getaran[$val];
                        $detail->save();
                    }
                }
            }
            $details = LhpsGetaranDetail::where('id_header', $header->id)->get();
            if ($header != null) {
                $file_qr = new GenerateQrDocumentLhp();
                $file_qr = $file_qr->insert('LHP_GETARAN', $header, $this->karyawan);
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
            // dd($parameter,in_array("Getaran (LK) TL", $parameter), $val );
            if ($val == "Getaran (LK) ST" || $val == "Getaran (LK) TL") {
                        $fileName = LhpTemplate::setDataDetail($details)
                                    ->setDataHeader($header)
                                    ->whereView('DraftGetaranPersonal')
                                    ->render();
            } else {
                    $fileName = LhpTemplate::setDataDetail($details)
                                    ->setDataHeader($header)
                                    ->whereView('DraftGetaran')
                                    ->render();
            }
                            
                $header->file_lhp = $fileName;
                $header->save();
            }
            DB::commit();
            // dd($cleaned_aktivitas);

            return response()->json([
                'message' => 'Data draft LHP Getaran no sampel ' . $request->noSampel . ' berhasil disimpan',
                'status' => true
            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
                'status' => false,
                'file' => $th->getFile()
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
            
            $data = array();
            $data1 = array();
            $hasil = []; 
            $data = GetaranHeader::with('ws_udara', 'lapangan_getaran', 'master_parameter', 'lapangan_getaran_personal')->where('no_sampel', $request->no_sampel)->where('is_approve', 1)->where('is_active', true)->where('lhps', 1)->get();
            $i = 0;
            $method_regulasi = [];
            if ($data->isNotEmpty()) {

                foreach ($data as $key => $val) {
                    $data1[$i]['id'] = $val->id;
                    $data1[$i]['parameter'] = $val->parameter;
                    $data1[$i]['satuan'] = $val->master_parameter->satuan;
                    $data1[$i]['hasil1'] = ($val->ws_udara->hasil1 != null) ? json_decode($val->ws_udara->hasil1) : '';
                    $data1[$i]['hasil2'] = $val->ws_udara->hasil2;
                    $data1[$i]['hasil3'] = $val->ws_udara->hasil3;
                    $data1[$i]['methode'] = $val->master_parameter->method; 

                    $data1[$i]['status'] = $val->master_parameter->status;

                    if ($val->parameter == "Getaran (LK) ST" || $val->parameter == "Getaran (LK) TL") {
                        $data1[$i]['data_lapangan'] = $val->lapangan_getaran_personal;
                        $data1[$i]['data_lapangan']->durasi_paparan = json_decode($val->lapangan_getaran_personal->durasi_paparan);
                        $data1[$i]['no_sampel'] = $val->lapangan_getaran_personal->no_sampel;
                        $data1[$i]['keterangan'] = $val->lapangan_getaran_personal->keterangan;
                        $data1[$i]['nab'] = $val->ws_udara->nab;
                        $data1[$i]['tipe_getaran'] = 'getaran personal';
                    } else {
                        $data1[$i]['data_lapangan'] = $val->lapangan_getaran;
                        $data1[$i]['no_sampel'] = $val->lapangan_getaran->no_sampel;
                        $data1[$i]['keterangan'] = $val->lapangan_getaran->keterangan;
                        $data1[$i]['tipe_getaran'] = 'getaran';
                    }
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

        try {
            // Getaran
            $data = LhpsGetaranHeader::where('no_lhp', $request->no_lhp)
                ->where('id_kategori_3', $category)
                ->where('is_active', true)
                ->first();
            // dd($data);
            $details = LhpsGetaranDetail::where('id_header', $data->id)->get();
            // dd($data, $details);
            $spesifikasiMethode = LhpsGetaranHeader::where('no_sampel', $request->no_sampel)
                ->where('is_approve', 1)
                ->where('is_active', 1)
                ->get();

            return response()->json([
                'data' => $data,
                'details' => $details,
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
    public function handleApprove(Request $request)
    {
        $category = explode('-', $request->kategori_3)[0];
        $data_order = OrderDetail::where('no_sampel', $request->no_sampel)
            ->where('id', $request->id)
            ->where('is_active', true)
            ->firstOrFail();

        try {
            $data = LhpsGetaranHeader::where('no_sampel', $request->no_sampel)
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
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Data draft LHP air no sampel ' . $request->no_lhp . ' Tidak ditemukan'
                ], 401);
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
    }

    // Amang
    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = OrderDetail::where('id', $request->id)->first();

            $kategori3 = $data->kategori_3;
            $category = (int) explode('-', $kategori3)[0];

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

      
        DB::beginTransaction();
        try {
                // dd('Getaran');
                $header = LhpsGetaranHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                $quotation_status = "draft_lhp_getaran";
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
            $categoryGetaran = [13, 14, 15, 16, 17, 18, 19, 20];
         
            $category = explode('-', $request->kategori)[0];
            $sub_category = explode('-', $request->kategori)[1];
            if ($request->id != '' || isset($request->id)) {
              if (in_array($category, $categoryGetaran)) {
                    $data = LhpsGetaranHeader::where('id', $request->id)->update([
                        'is_emailed' => true,
                        'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'emailed_by' => $this->karyawan
                    ]);
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
