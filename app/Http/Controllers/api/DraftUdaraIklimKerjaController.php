<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;
use App\Models\LhpsKebisinganHeader;
use App\Models\LhpsLingHeader;
use App\Models\LhpsLingDetail;
use App\Models\LhpsPencahayaanHeader;
use App\Models\LhpsGetaranHeader;
use App\Models\LhpsPencahayaanDetail;
use App\Models\LhpsMedanLMHeader;

use App\Models\LhpsIklimHeader;
use App\Models\LhpsIklimHeaderHistory;
use App\Models\LhpsIklimDetail;
use App\Models\LhpsIklimDetailHistory;

use App\Models\MasterSubKategori;
use App\Models\OrderDetail;
use App\Models\MetodeSampling;
use App\Models\MasterKaryawan;
use App\Models\QrDocument;
use App\Models\IklimHeader;

use App\Models\Parameter;
use App\Models\GenerateLink;
use App\Services\SendEmail;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
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
            $header->keterangan = ($request->keterangan != '') ? $request->keterangan : NULL;
            $header->sub_kategori = $request->jenis_sampel ? $request->jenis_sampel : NULL;
            $header->metode_sampling = $request->metode_sampling ? $request->metode_sampling : NULL;
            $header->tanggal_sampling = $request->tanggal_tugas ? $request->tanggal_tugas : NULL;
            $header->periode_analisa = $request->periode_analisa ? $request->periode_analisa : NULL;
            $header->nama_karyawan = 'Abidah Walfathiyyah';
            $header->jabatan_karyawan = 'Technical Control Supervisor';
    
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
                    $cleaned_key_parameter = array_map(fn($k) => trim($k, " '\""), array_keys($request->param));
                    $cleaned_parameter = array_combine($cleaned_key_parameter, array_values($request->param));
                    $cleaned_key_lokasi = array_map(fn($k) => trim($k, " '\""), array_keys($request->keterangan_lokasi));
                    $cleaned_lokasi = array_combine($cleaned_key_lokasi, array_values($request->keterangan_lokasi));
                if (in_array("ISBB", $parameter) || in_array("ISBB (8 Jam)", $parameter)) {
                    $cleaned_key_hasil = array_map(fn($k) => trim($k, " '\""), array_keys($request->hasil));
                    $cleaned_hasil = array_combine($cleaned_key_hasil, array_values($request->hasil));
                    $cleaned_key_pekerjaan = array_map(fn($k) => trim($k, " '\""), array_keys($request->aktivitas_pekerjaan));
                    $cleaned_aktivitas_pekerjaan = array_combine($cleaned_key_pekerjaan, array_values($request->aktivitas_pekerjaan));
                    $cleaned_key_paparan = array_map(fn($k) => trim($k, " '\""), array_keys($request->durasi_paparan));
                    $cleaned_durasi_paparan = array_combine($cleaned_key_paparan, array_values($request->durasi_paparan));
                    if (array_key_exists($val, $cleaned_no_sampel)) {
                        $detail = new LhpsIklimDetail;
                        $detail->id_header = $header->id;
                        $detail->no_sampel = $val;
                        $detail->param = $cleaned_parameter[$val];
                        $detail->keterangan = $cleaned_lokasi[$val];
                        $detail->hasil = $cleaned_hasil[$val];
                        $detail->aktivitas_pekerjaan = $cleaned_aktivitas_pekerjaan[$val];
                        $detail->durasi_paparan = $cleaned_durasi_paparan[$val];
                    }
                } else {
                    $cleaned_key_kecepatan_angin = array_map(fn($k) => trim($k, " '\""), array_keys($request->kecepatan_angin));
                    $cleaned_kecepatan_angin = array_combine($cleaned_key_kecepatan_angin, array_values($request->kecepatan_angin));
                    $cleaned_key_suhu_temperatur = array_map(fn($k) => trim($k, " '\""), array_keys($request->suhu_temperatur));
                    $cleaned_suhu_temperatur = array_combine($cleaned_key_suhu_temperatur, array_values($request->suhu_temperatur));
                    $cleaned_key_indeks_suhu = array_map(fn($k) => trim($k, " '\""), array_keys($request->indeks_suhu_basah));
                    $cleaned_indeks_suhu = array_combine($cleaned_key_indeks_suhu, array_values($request->indeks_suhu_basah));
                    $cleaned_key_kondisi = array_map(fn($k) => trim($k, " '\""), array_keys($request->kondisi));
                    $cleaned_kondisi = array_combine($cleaned_key_kondisi, array_values($request->kondisi));
                    if (array_key_exists($val, $cleaned_no_sampel)) {
                        $detail = new LhpsIklimDetail;
                        $detail->id_header = $header->id;
                        $detail->no_sampel = $val;
                        $detail->param = $cleaned_parameter[$val];
                        $detail->keterangan = $cleaned_lokasi[$val];
                        $detail->indeks_suhu_basah = $cleaned_indeks_suhu[$val];

                        $detail->kecepatan_angin = $cleaned_kecepatan_angin[$val];
                        $detail->suhu_temperatur = $cleaned_suhu_temperatur[$val];
                        $detail->kondisi = $cleaned_kondisi[$val];
                    }
                }

                $detail->save();
                $i++;
            }

            $details = LhpsIklimDetail::where('id_header', $header->id)->get();
            if ($header) {
                $file_qr = new GenerateQrDocumentLhp();
                $file_qr = $file_qr->insert('LHP_IKLIM', $header, $this->karyawan);
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

        
                if($parameter[0] == 'ISBB' || $parameter[0] == 'ISBB (8 Jam)'){
                       $fileName = LhpTemplate::setDataDetail($details)
                                    ->setDataHeader($header)
                                    ->whereView('DraftIklimPanas')
                                    ->render();
                } else {
                     $fileName = LhpTemplate::setDataDetail($details)
                                    ->setDataHeader($header)
                                    ->whereView('DraftIklimDingin')
                                    ->render();
                }
                
                // $fileName = 'LHP-IKLIM_KERJA-' . str_replace("/", "-", $header->no_lhp) . '.pdf';
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


     public function handleDatadetail(Request $request)
    {
         $id_category = explode('-', $request->kategori_3)[0];
        try {
             $cekLhp = LhpsIklimHeader::where('no_lhp', $request->cfr)
                ->where('id_kategori_3', $id_category)
                ->where('is_active', true)
                ->first();

            if($cekLhp) {
              $detail = LhpsIklimDetail::where('id_header', $cekLhp->id)->get();

            $existingSamples = $detail->pluck('no_sampel')->toArray();
            $data = [];
            $data1 = [];
            $hasil = [];

            $orders = OrderDetail::where('cfr', $request->cfr)
                ->where('is_approve', 0)
                ->where('is_active', true)
                ->where('kategori_2', '4-Udara')
                ->where('kategori_3', $request->kategori_3)
                ->where('status', 2)
                ->pluck('no_sampel');

            $data = IklimHeader::with('ws_udara', 'iklim_panas', 'iklim_dingin')
                ->whereIn('no_sampel', $orders)
                ->where('is_approve', 1)
                ->where('is_active', true)
                ->where('lhps', 1)
                ->get();

            $i = 0;
            if ($data->isNotEmpty()) {
                foreach ($data as $key => $val) {
                    $data1[$i]['id'] = $val->id;
                    $data1[$i]['no_sampel'] = $val->no_sampel;
                    $data1[$i]['hasil'] = round($val->ws_udara->hasil1, 1);
                    $data1[$i]['standar_min'] = $val->ws_udara->nab;
                    $data1[$i]['indeks_suhu_basah'] = $val->ws_udara->hasil1;
                    $data1[$i]['kecepatan_angin'] = $val->rata_kecepatan_angin;
                    $data1[$i]['suhu_temperatur'] = $val->rata_suhu;

                    if ($val->parameter == "IKD (CS)") {
                        $data1[$i]['keterangan'] = $val->iklim_dingin->keterangan;
                        $data1[$i]['aktivitas_pekerjaan'] = $val->iklim_dingin->aktifitas_kerja;
                        $data1[$i]['kondisi'] = $val->ws_udara->interpretasi;
                    } else if ($val->parameter == "ISBB" || $val->parameter == "ISBB (8 jam)") {
                        $data1[$i]['keterangan'] = $val->iklim_panas->keterangan;
                        $data1[$i]['indeks_suhu_basah'] = $val->iklim_panas->keterangan;
                        $data1[$i]['aktivitas_pekerjaan'] = $val->iklim_panas->aktifitas;
                        $data1[$i]['durasi_paparan'] = $val->iklim_panas->akumulasi_waktu_paparan;
                    }

                    $data1[$i]['parameter'] = $val->parameter;

                    $i++;
                }
                $hasil[] = $data1;
            }

            $data_all = [];
            $a = 0;
            foreach ($hasil as $key => $value) {
                foreach ($value as $row => $col) {
                    if (!in_array($col['no_sampel'], $existingSamples)) {
                        $col['status'] = 'belom_diadjust';
                        $data_all[$a] = $col;
                        $a++;
                    }
                }
            }

            // gabungkan dengan detail
            foreach ($data_all as $key => $value) {
                $detail[] = $value;
            }

                return response()->json([
                    'data' => $cekLhp,
                    'detail' => $detail,
                    'success' => true,
                    'status' => 200,
                    'message' => 'Data berhasil diambil'
                ], 201);  
            } else {
                $data = array();
                $data1 = array();
                $hasil = [];
                $orders = OrderDetail::where('cfr', $request->cfr)
                    ->where('is_approve', 0)
                    ->where('is_active', true)
                    ->where('kategori_2', '4-Udara')
                    ->where('kategori_3', $request->kategori_3)
                    ->where('status', 2)
                    ->pluck('no_sampel');
                $data = IklimHeader::with('ws_udara', 'iklim_panas', 'iklim_dingin')
                    ->whereIn('no_sampel', $orders)
                    ->where('is_approve', 1)
                    ->where('is_active', true)
                    ->where('lhps', 1)
                    ->get();

                $i = 0;
                if ($data->isNotEmpty()) {
                   foreach ($data as $key => $val) {
                    $data1[$i]['id'] = $val->id;
                    $data1[$i]['no_sampel'] = $val->no_sampel;
                    $data1[$i]['hasil'] = round($val->ws_udara->hasil1, 1);
                    $data1[$i]['standar_min'] = $val->ws_udara->nab;
                    $data1[$i]['indeks_suhu_basah'] = $val->ws_udara->hasil1;
                    $data1[$i]['kecepatan_angin'] = $val->rata_kecepatan_angin;
                    $data1[$i]['suhu_temperatur'] = $val->rata_suhu;

                    if ($val->parameter == "IKD (CS)") {
                        $data1[$i]['keterangan'] = $val->iklim_dingin->keterangan;
                        $data1[$i]['aktivitas_pekerjaan'] = $val->iklim_dingin->aktifitas_kerja;
                        $data1[$i]['kondisi'] = $val->ws_udara->interpretasi;
                    } else if ($val->parameter == "ISBB" || $val->parameter == "ISBB (8 jam)") {
                        $data1[$i]['keterangan'] = $val->iklim_panas->keterangan;
                        $data1[$i]['indeks_suhu_basah'] = $val->iklim_panas->keterangan;
                        $data1[$i]['aktivitas_pekerjaan'] = $val->iklim_panas->aktifitas;
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

                return response()->json([
                    'data' => [],
                    'detail' => $data_all,
                    'status' => 200,
                    'success' => true,
                    'message' => 'Data berhasil diambil'
                ], 201);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $e->getMessage(),
                'getLine' => $e->getLine(),
                'getFile' => $e->getFile()
            ]);
        }
    }
      public function handleApprove(Request $request)
        {
            try {
                $data = LhpsIklimHeader::where('id', $request->id)
                    ->where('is_active', true)
                    ->first();
                $noSampel = array_map('trim', explode(',', $request->no_sampel));
                $no_lhp = $data->no_lhp;
            
                $qr = QrDocument::where('id_document', $data->id)
                    ->where('type_document', 'LHP_IKLIM')
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
                    $data->nama_karyawan = $this->karyawan;
                    $data->jabatan_karyawan = $request->attributes->get('user')->karyawan->jabatan;
                    $data->save();
                    HistoryAppReject::insert([
                        'no_lhp' => $data->no_lhp,
                        'no_sampel' => $request->no_sampel,
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
                    'message' => 'Data draft LHP Iklim dengan no LHP ' . $no_lhp . ' berhasil diapprove'
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

            
            $lhps = LhpsIklimHeader::where('id', $request->id)
                ->where('is_active', true)
                ->first();
            $no_lhp = $lhps->no_lhp;

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
                $lhpsHistory = $lhps->replicate();
                $lhpsHistory->setTable((new LhpsIklimHeaderHistory())->getTable());
                $lhpsHistory->created_at = $lhps->created_at;
                $lhpsHistory->updated_at = $lhps->updated_at;
                $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $lhpsHistory->deleted_by = $this->karyawan;
                $lhpsHistory->save();

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
            $noSampel = array_map('trim', explode(",", $request->no_sampel));
            OrderDetail::where('cfr', $lhps->no_lhp)
                    ->whereIn('no_sampel', $noSampel)
                    ->update([
                        'status' => 1
                    ]);


            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Data draft dengan no LHP ' . $no_lhp . ' berhasil direject'
            ]);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage()
            ]);
        }
    }

  public function generate(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsIklimHeader::where('no_lhp', $request->no_lhp)
                ->where('is_active', true)
                ->where('id', $request->id)
                ->first();
            if ($header != null) {
                $key = $header->no_lhp . str_replace('.', '', microtime(true));
                $gen = MD5($key);
                $gen_tahun = self::encrypt(DATE('Y-m-d'));
                $token = self::encrypt($gen . '|' . $gen_tahun);

                $insertData = [
                    'token' => $token,
                    'key' => $gen,
                    'id_quotation' => $header->id,
                    'quotation_status' => 'draft_lhp_iklim',
                    'type' => 'draft_iklim',
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
            $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => 'draft_lhp_iklim', 'type' => 'draft_iklim'])->first();
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
            if ($request->id != '' || isset($request->id)) {
                 LhpsIklimHeader::where('id', $request->id)->update([
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
