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
use App\Models\LhpsIklimHeader;
use App\Models\LhpsIklimHeaderHistory;
use App\Models\LhpsIklimDetail;
use App\Models\LhpsIklimDetailHistory;

use App\Models\MasterSubKategori;
use App\Models\OrderDetail;
use App\Models\MetodeSampling;
use App\Models\MasterBakumutu;
use App\Models\MasterKaryawan;
use App\Models\LingkunganHeader;
use App\Models\QrDocument;
use App\Models\PencahayaanHeader;
use App\Models\IklimHeader;
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

class DraftUdaraIklimKerjaController extends Controller
{
    // done if status = 2
    // AmanghandleDatadetail
    public function index(Request $request)
    {
        DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
            'lhps_iklim',
            'orderHeader'
            => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_approve', 0)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->where('kategori_3', "21-Iklim Kerja")
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
        DB::beginTransaction();
        try {
            $idKategori2 = explode('-', $request->kategori_2)[0];
            $idKategori3 = explode('-', $request->kategori_3)[0];

            $header = LhpsIklimHeader::where('no_lhp', $request->no_lhp)
                ->where('no_order', $request->no_order)
                ->where('id_kategori_3', $idKategori3)
                ->where('is_active', true)
                ->first();

            if (!$header) {
                $header = new LhpsIklimHeader;
                $header->created_by = $this->karyawan;
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
            } else {
                $history = $header->replicate();
                $history->setTable((new LhpsIklimHeaderHistory())->getTable());
                $history->created_by = $this->karyawan;
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $history->updated_by = null;
                $history->updated_at = null;

                $history->save();

                $header->updated_by = $this->karyawan;
                $header->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            }

            $parameter = explode(', ', $request->parameter);
            $header->no_order = $request->no_order ? $request->no_order : NULL;
            $header->no_lhp = $request->no_lhp ? $request->no_lhp : NULL;
            $header->no_sampel = $request->noSampel ? $request->noSampel : NULL;
            $header->id_kategori_2 = $idKategori2;
            $header->id_kategori_3 = $idKategori3;
            $header->no_qt = $request->no_penawaran ? $request->no_penawaran : NULL;
            $header->parameter_uji = json_encode($parameter);
            $header->nama_pelanggan = $request->nama_perusahaan ? $request->nama_perusahaan : NULL;
            $header->alamat_sampling = $request->alamat_sampling ? $request->alamat_sampling : NULL;
            $header->sub_kategori = $request->jenis_sampel ? $request->jenis_sampel : NULL;
            // $header->deskripsi_titik    = $request->keterangan_1    ? $request->keterangan_1                : NULL;
            $header->metode_sampling = $request->method_sampling ? $request->method_sampling : NULL;
            $header->tanggal_sampling = $request->tanggal_tugas ? $request->tanggal_tugas : NULL;
            $header->periode_analisa = $request->periode_analisa ? $request->periode_analisa : NULL;
            $header->nama_karyawan = 'Abidah Walfathiyyah';
            $header->jabatan_karyawan = 'Technical Control Supervisor';
            // $header->nama_karyawan = 'Kharina Waty';
            // $header->jabatan_karyawan = 'Technical Control Manager';
            $header->regulasi = $request->regulasi ? json_encode($request->regulasi) : NULL;
            $header->tanggal_lhp = $request->tanggal_lhp ? $request->tanggal_lhp : NULL;

            $header->save();

            $details = LhpsIklimDetail::where('id_header', $header->id)->get();
            foreach ($details as $detail) {
                $history = $detail->replicate();
                $history->setTable((new LhpsIklimDetailHistory())->getTable());
                $history->created_by = $this->karyawan;
                $history->created_at = Carbon::now()->format('Y-m-d H:i:s');

                $history->save();
            }

            $detail = LhpsIklimDetail::where('id_header', $header->id)->delete();
            $i = 0;
            foreach ($request->no_sampel as $key => $val) {
                $cleaned_key_no_sampel = array_map(fn($k) => trim($k, " '\""), array_keys($request->no_sampel));
                $cleaned_no_sampel = array_combine($cleaned_key_no_sampel, array_values($request->no_sampel));
                $cleaned_key_lokasi = array_map(fn($k) => trim($k, " '\""), array_keys($request->lokasi));
                $cleaned_lokasi = array_combine($cleaned_key_lokasi, array_values($request->lokasi));

                $param = $cleaned_key_no_sampel[$i];

                $detail = new LhpsIklimDetail;
                $detail->id_header = $header->id;
                $detail->no_sampel = $val;
                $detail->param = $param;
                $detail->keterangan = $cleaned_lokasi[$param];

                if ($param == 'ISBB' || $param == 'ISBB (8 Jam)') {
                    $cleaned_key_hasil = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil));
                    $cleaned_hasil = array_combine($cleaned_key_hasil, array_values($request->hasil));
                    $cleaned_key_indeks_suhu = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil));
                    $cleaned_indeks_suhu = array_combine($cleaned_key_indeks_suhu, array_values($request->hasil));
                    $cleaned_key_pekerjaan = array_map(fn($k) => trim($k, " '\""), array_keys($request->aktivitas));
                    $cleaned_aktivitas_pekerjaan = array_combine($cleaned_key_pekerjaan, array_values($request->aktivitas));
                    $cleaned_key_paparan = array_map(fn($k) => trim($k, " '\""), array_keys($request->durasi_paparan));
                    $cleaned_durasi_paparan = array_combine($cleaned_key_paparan, array_values($request->durasi_paparan));

                    $detail->hasil = $cleaned_hasil[$param];
                    $detail->indeks_suhu_basah = $cleaned_indeks_suhu[$param];
                    $detail->aktivitas_pekerjaan = $cleaned_aktivitas_pekerjaan[$param];
                    $detail->durasi_paparan = $cleaned_durasi_paparan[$param];
                } else {
                    $cleaned_key_kecepatan_angin = array_map(fn($k) => trim($k, " '\""), array_keys($request->kecepatan_angin));
                    $cleaned_kecepatan_angin = array_combine($cleaned_key_kecepatan_angin, array_values($request->kecepatan_angin));
                    $cleaned_key_suhu_temperatur = array_map(fn($k) => trim($k, " '\""), array_keys($request->suhu_temperatur));
                    $cleaned_suhu_temperatur = array_combine($cleaned_key_suhu_temperatur, array_values($request->suhu_temperatur));
                    $cleaned_key_kondisi = array_map(fn($k) => trim($k, " '\""), array_keys($request->kondisi));
                    $cleaned_kondisi = array_combine($cleaned_key_kondisi, array_values($request->kondisi));

                    $detail->kecepatan_angin = $cleaned_kecepatan_angin[$param];
                    $detail->suhu_temperatur = $cleaned_suhu_temperatur[$param];
                    $detail->kondisi = $cleaned_kondisi[$param];
                }

                $detail->save();
                $i++;
            }

            $details = LhpsIklimDetail::where('id_header', $header->id)->get();
            if ($header) {
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
                        if (!isset($groupedByPage[$page]))
                            $groupedByPage[$page] = [];
                        $groupedByPage[$page][] = $item;
                    }
                }

                $job = new RenderLhp($header, $details, 'downloadWSDraft', $groupedByPage);
                $this->dispatch($job);

                $job = new RenderLhp($header, $details, 'downloadLHP', $groupedByPage);
                $this->dispatch($job);

                $job = new RenderLhp($header, $details, 'downloadLHPFinal', $groupedByPage);
                $this->dispatch($job);

                $fileName = 'LHP-IKLIM_KERJA-' . str_replace("/", "-", $header->no_lhp) . '.pdf';

                $header->file_lhp = $fileName;
                $header->save();
            }
            DB::commit();

            return response()->json([
                'message' => 'Draft LHP Iklim Kerja berhasil disimpan',
                'status' => true
            ], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
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
            $orders = OrderDetail::where('cfr', $request->cfr)
                ->where('is_approve', 0)
                ->where('is_active', true)
                ->where('kategori_2', '4-Udara')
                ->where('kategori_3', $request->kategori_3)
                // ->where('is_approve', true)
                ->where('status', 2)
                ->pluck('no_sampel');

            $data = IklimHeader::with('ws_udara', 'iklim_panas', 'iklim_dingin')->whereIn('no_sampel', $orders)->where('is_approve', 1)->where('is_active', true)->where('lhps', 1)->get();
            $i = 0;
            $method_regulasi = [];
            if ($data->isNotEmpty()) {

                foreach ($data as $key => $val) {
                    $data1[$i]['id'] = $val->id;
                    $data1[$i]['no_sampel'] = $val->no_sampel;
                    $data1[$i]['hasil_uji'] = round($val->ws_udara->hasil1, 1);
                    $data1[$i]['standar_min'] = $val->ws_udara->nab;
                    $data1[$i]['indeks_suhu_basah_dan_bola'] = $val->ws_udara->hasil1;
                    $data1[$i]['kecepatan_angin'] = $val->rata_kecepatan_angin;
                    $data1[$i]['suhu_temperatur_aktual'] = $val->rata_suhu;

                    if ($val->parameter == "IKD (CS)") {
                        $data1[$i]['lokasi'] = $val->iklim_dingin->keterangan;
                        $data1[$i]['aktifitas'] = $val->iklim_dingin->aktifitas;
                        $data1[$i]['aktifitas_kerja'] = $val->iklim_dingin->aktifitas_kerja;
                        $data1[$i]['kondisi'] = $val->ws_udara->interpretasi;
                    } else if ($val->parameter == "ISBB") {
                        $data1[$i]['lokasi'] = $val->iklim_panas->keterangan;
                        $data1[$i]['aktifitas'] = $val->iklim_panas->aktifitas;
                        $data1[$i]['durasi_paparan'] = $val->iklim_panas->akumulasi_waktu_paparan;
                    }

                    $data1[$i]['parameter'] = $val->parameter;

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
        try {
            $category = explode('-', $request->kategori_3)[0];
            $data = LhpsIklimHeader::where('no_lhp', $request->no_lhp)
                ->where('id_kategori_3', $category)
                ->where('is_active', true)
                ->first();

            $details = LhpsIklimDetail::where('id_header', $data->id)->get();

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
            $data = OrderDetail::where('id', $request->id)->first();

            $kategori3 = $data->kategori_3;
            $category = (int) explode('-', $kategori3)[0];
            $lhps = LhpsIklimHeader::where('no_lhp', $data->cfr)
                ->where('no_order', $data->no_order)
                ->where('id_kategori_3', $category)
                ->where('is_active', true)
                ->first();

            if ($lhps) {
                // History Header Iklim
                $lhpsHistory = $lhps->replicate();
                $lhpsHistory->setTable((new LhpsIklimHeaderHistory())->getTable());
                $lhpsHistory->created_at = $lhps->created_at;
                $lhpsHistory->updated_at = $lhps->updated_at;
                $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $lhpsHistory->deleted_by = $this->karyawan;
                $lhpsHistory->save();

                // History Detail Iklim
                $oldDetails = LhpsIklimDetail::where('id_header', $lhps->id)->get();
                foreach ($oldDetails as $detail) {
                    $detailHistory = $detail->replicate();
                    $detailHistory->setTable((new LhpsIklimDetailHistory())->getTable());
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
