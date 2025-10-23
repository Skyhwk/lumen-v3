<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Jobs\ForwardKontrakJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use App\Services\Notification;
use App\Services\GetAtasan;
use App\Services\GetBawahan;
use App\Models\RequestQR;
use App\Models\Parameter;
use App\Models\HargaParameter;
use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\MasterPelanggan;

use App\Jobs\ForwardNonKontrakJob;

class RequestQrController extends Controller
{
    public function index(Request $request){
        try{
            $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
            $id_jabatan = [21,24]; // Can't View All
            $getBawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();

            $data = RequestQR::where('is_active', true)
                ->where('tipe', $request->mode);

            if($jabatan == 21){
                $data->whereIn('created_by', $getBawahan);
            }else if($jabatan == 24){
                $data->where('created_by', $this->karyawan);
            }else {
                $data->where(function ($query) {
                    $query->where('is_rejected', false)
                        ->where('is_processed', false);
                });
            }

            return DataTables::of($data)
                ->editColumn('data_pendukung_sampling', function ($item) {
                    return json_decode($item['data_pendukung_sampling']);
                })
                ->editColumn('email_cc', function ($item) {
                    return json_decode($item['email_cc']);
                })
                ->addColumn('can_processed', function ($item) {
                    $exist = MasterPelanggan::select(['id', 'nama_pelanggan','id_pelanggan','sales_id'])->where('sales_penanggung_jawab', $item['created_by'])
                    ->where(function($query) use ($item) {
                        if($item['konsultan'] != null){
                            $query->where('nama_pelanggan', $item['konsultan']);

                        }else{
                            $query->where('nama_pelanggan', $item['nama_pelanggan']);
                        }
                    })->first();
                    return $exist !== null ? true : false;
                    // return true;
                    // return $item['pelanggan_info'] != null ? true : false;
                })
                ->addColumn('id_pelanggan', function ($item) {
                    $exist = MasterPelanggan::select(['id', 'nama_pelanggan','id_pelanggan','sales_id'])->where('sales_penanggung_jawab', $item['created_by'])
                    ->where(function($query) use ($item) {
                        if($item['konsultan'] != null){
                            $query->where('nama_pelanggan', $item['konsultan']);

                        }else{
                            $query->where('nama_pelanggan', $item['nama_pelanggan']);
                        }
                    })->first();
                    return $exist !== null ? $exist->id_pelanggan : null;
                    // return $item['pelanggan_info'] !== null ? $item['pelanggan_info']->id_pelanggan : null;
                })
                ->addColumn('sales_id', function ($item) {
                    $exist = MasterPelanggan::select(['id', 'nama_pelanggan','id_pelanggan','sales_id'])->where('sales_penanggung_jawab', $item['created_by'])
                    ->where(function($query) use ($item) {
                        if($item['konsultan'] != null){
                            $query->where('nama_pelanggan', $item['konsultan']);

                        }else{
                            $query->where('nama_pelanggan', $item['nama_pelanggan']);
                        }
                    })->first();
                    return $exist !== null ? $exist->sales_id : null;
                    // return $item['pelanggan_info'] !== null ? $item['pelanggan_info']->sales_id : null;
                })
                ->make(true);
        }catch(\Exception $e){
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
        }
    }

    public function getInformation(Request $request)
    {
        $data = MasterPelanggan::with(['kontak_pelanggan', 'alamat_pelanggan', 'pic_pelanggan'])
            ->where('id_pelanggan', $request->id_pelanggan)->where('is_active',true)->first();
        switch ($request->mode) {
            case 'non_kontrak':
                $quotation = QuotationNonKontrak::where('pelanggan_ID', $request->id_pelanggan)
                    ->where('is_active', true)
                    ->orderBy('id', 'DESC')
                    ->get();

                $count = count($quotation);
                if ($count == 0) {
                    $message = "Pelanggan $request->id_pelanggan belum pernah melakukan penawaran.";
                    $status = 200;
                } else {
                    $data->data_pendukung_sampling = $quotation[0]->data_pendukung_sampling;
                    $message = "Pelanggan $request->id_pelanggan sudah melakukan penawaran sebanyak $count kali.";
                    $status = 200;
                }

                return response()->json([
                    'data' => $data,
                    'message' => $message,
                    'status' => $status
                ], $status);
            case 'kontrak':
                $quotation = QuotationKontrakH::where('pelanggan_ID', $request->id_pelanggan)
                    ->where('is_active', true)
                    ->orderBy('id', 'DESC')
                    ->get();

                $count = count($quotation);
                if ($count == 0) {
                    $message = "Pelanggan $request->id_pelanggan belum pernah melakukan penawaran.";
                    $status = 200;
                } else {
                    $data->data_pendukung_sampling = $quotation[0]->data_pendukung_sampling;
                    $message = "Pelanggan $request->id_pelanggan sudah melakukan penawaran sebanyak $count kali.";
                    $status = 200;
                }

                // dd($data);
                return response()->json([
                    'data' => $data,
                    'message' => $message,
                    'status' => $status
                ], $status);
            default:
                $data = [];
                $message = "Invalid mode.";
                return response()->json([
                    'data' => $data,
                    'message' => $message
                ], 400);
        }
    }

    public function submit(Request $request){
        try{
            $payload = $request->all();
            $modeQt = $payload['informasi_pelanggan']['tipe'];
            $mode = $payload['informasi_pelanggan']['mode'];
            if($mode == "create"){
                switch ($modeQt) {
                    case 'kontrak' :
                        return $this->createRequestKontrak($request);
                        break;
                    case 'non_kontrak' :
                        return $this->createRequestNon($request);
                        break;
                    default:
                        return response()->json(['message' => "Mode $modeQt tidak dikenali"], 500);
                }
            }else{
                switch ($modeQt) {
                    case 'kontrak' :
                        return $this->updateRequestKontrak($request);
                        break;
                    case 'non_kontrak' :
                        return $this->updateRequestNon($request);
                        break;
                    default:
                        return response()->json(['message' => "Mode $modeQt tidak dikenali"], 500);
                }
            }
        }catch(\Exception $e){
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
        }
    }

    public function approve(Request $request) {
        DB::beginTransaction();
        try{
            $data = RequestQr::where('id', $request->id)->first();
            $data->is_processed = 1;
            $data->processed_by = $this->karyawan;
            $data->processed_at = DATE('Y-m-d H:i:s');
            $data->save();

            $message = 'Request QR telah disetujui';

            Notification::where('nama_lengkap', $data->created_by)
                    ->title('Request QR Disetujui')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-programming')
                    ->send();
            DB::commit();
            return response()->json([
                'message' => "Request QR untuk perusahaan $data->perusahaan berhasil disetujui",
            ]);
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
        }
    }

    public function reject(Request $request) {
        // dd($request->all());
        DB::beginTransaction();
        try{
            $data = RequestQr::where('id', $request->id)->first();
            $data->is_rejected = 1;
            $data->rejected_by = $this->karyawan;
            $data->rejected_at = DATE('Y-m-d H:i:s');
            $data->keterangan_reject = $request->keterangan_reject;
            $data->save();

            $message = 'Request QR telah ditolak';

            Notification::where('nama_lengkap', $data->created_by)
                    ->title('Request QR Ditolak')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/ticket-programming')
                    ->send();
            DB::commit();
            return response()->json([
                'message' => "Request QR untuk perusahaan $data->perusahaan berhasil ditolak",
            ]);
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
        }
    }

    public function exportRequest(Request $request) {
        try{
            $payload = $request->all();
            $modeQt = $payload['informasi_pelanggan']['modeQt'];

            switch ($modeQt) {
                case 'kontrak' :
                    return $this->createKontrak($request);
                    break;
                case 'non_kontrak' :
                    return $this->createNonKontrak($request);
                    break;
                default:
                    return response()->json(['message' => "Mode $modeQt tidak dikenali"], 500);
            }
        }catch(\Exception $e){
            dd($e);
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
        }
    }

    public function createNonKontrak(Request $request)
    {
        // Ambil payload dari request
        $payload = $request->all();

        if (!isset($payload['informasi_pelanggan']['tgl_penawaran']) || $payload['informasi_pelanggan']['tgl_penawaran'] == null) {
            return response()->json([
                'message' => 'Mohon isi tanggal penawaran terlebih dahulu.'
            ], 400);
        }

        $db = DATE('Y', strtotime($payload['informasi_pelanggan']['tgl_penawaran']));
        $sales_id = $payload['informasi_pelanggan']['sales_id'];
        if ($sales_id == null) {
            return response()->json([
                'message' => 'Mohon isi sales penanggung jawab terlebih dahulu.'
            ], 400);
        }

        try {
            // Jalankan job secara sinkron agar tidak terjadi error serialisasi closure
            $job = new ForwardNonKontrakJob((object)$payload, $this->idcabang, $this->karyawan, $sales_id);
            $this->dispatch($job);

            return response()->json([
                'message' => "Penawaran berhasil dibuat",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function createKontrak(Request $request)
    {
        $payload = $request->all();
        if (isset($payload['informasi_pelanggan']['tgl_penawaran']) && $payload['informasi_pelanggan']['tgl_penawaran'] != null) {
            $db = DATE('Y', \strtotime($payload['informasi_pelanggan']['tgl_penawaran']));
        } else {
            return response()->json([
                'message' => 'Please field date quotation first.!'
            ], 401);
        }

        $sales_id = $payload['informasi_pelanggan']['sales_id'];
        if ($sales_id == null) {
            return response()->json([
                'message' => 'Mohon isi sales penanggung jawab terlebih dahulu.'
            ], 400);
        }

        try {
            $job = new ForwardKontrakJob((object)$payload, $this->idcabang, $this->karyawan, $this->user_id);
            $this->dispatch($job);

            sleep(3);
            return response()->json([
                'message' => "Penawaran berhasil dibuat",
            ],200);
        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }

        // DB::beginTransaction();
        // try {
        //     $tahun_chek = date('y', strtotime($payload->informasi_pelanggan['tgl_penawaran']));  // 2 digit tahun (misal: 25)
        //     $bulan_chek = date('m', strtotime($payload->informasi_pelanggan['tgl_penawaran']));  // 2 digit bulan (misal: 01)
        //     $bulan_chek = self::romawi($bulan_chek);

        //     $cek = QuotationKontrakH::where('id_cabang', $this->idcabang)
        //         ->where('no_document', 'not like', '%R%')
        //         ->where('no_document', 'like', '%/' . $tahun_chek . '-%')
        //         ->orderBy('id', 'DESC')
        //         ->first();

        //     $no_ = 1;  // Set default nomor urut menjadi 1

        //     if ($cek != null) {
        //         // Pisahkan komponen no_document untuk mengambil tahun dan nomor urut terakhir
        //         $parts = explode('/', $cek->no_document);

        //         if (count($parts) > 3) {  // Pastikan formatnya sesuai
        //             $tahun_cek_full = $parts[2];  // Tahun dan bulan dokumen terakhir
        //             list($tahun_cek_docLast, $bulan_cek_docLast) = explode('-', $tahun_cek_full);

        //             if ((int) $tahun_chek == (int) $tahun_cek_docLast) {
        //                 // Ambil nomor urut terakhir dan tambah 1
        //                 $no_ = (int) explode('/', $cek->no_document)[3] + 1;
        //             }
        //         }
        //     }

        //     // Format nomor dokumen menjadi 8 digit
        //     $no_quotation = sprintf('%06d', $no_);
        //     $no_document = 'ISL/QTC/' . $tahun_chek . '-' . $bulan_chek . '/' . $no_quotation;

        //     // Implementasi untuk create kontrak
        //     // Insert Data Quotation Kontrak Header
        //     $dataH = new QuotationKontrakH;
        //     $dataH->no_quotation = $no_quotation;  //penentian nomor Quotation
        //     $dataH->no_document = $no_document;
        //     $dataH->pelanggan_ID = $payload->informasi_pelanggan['pelanggan_ID'];
        //     $dataH->id_cabang = $this->idcabang;

        //     //dataH customer order     -------------------------------------------------------> save ke master customer parrent
        //     $dataH->nama_perusahaan = strtoupper(htmlspecialchars_decode($payload->informasi_pelanggan['nama_perusahaan']));
        //     $dataH->tanggal_penawaran = strtoupper($payload->informasi_pelanggan['tgl_penawaran']);
        //     if ($payload->informasi_pelanggan['konsultan'] != '')
        //         $dataH->konsultan = strtoupper(trim(htmlspecialchars_decode($payload->informasi_pelanggan['konsultan'])));
        //     if ($payload->informasi_pelanggan['alamat_kantor'] != '')
        //         $dataH->alamat_kantor = $payload->informasi_pelanggan['alamat_kantor'];
        //     $dataH->no_tlp_perusahaan = \str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan['no_tlp_perusahaan']);
        //     $dataH->nama_pic_order = ucwords($payload->informasi_pelanggan['nama_pic_order']);
        //     $dataH->jabatan_pic_order = $payload->informasi_pelanggan['jabatan_pic_order'];
        //     $dataH->no_pic_order = \str_replace(["-", "_"], "", $payload->informasi_pelanggan['no_pic_order']);
        //     $dataH->email_pic_order = $payload->informasi_pelanggan['email_pic_order'];
        //     $dataH->email_cc = isset($payload->informasi_pelanggan['email_cc']) ? json_encode($payload->informasi_pelanggan['email_cc']) : null;
        //     $dataH->status_sampling = $payload->informasi_pelanggan['status_sampling'];
        //     $dataH->alamat_sampling = $payload->informasi_pelanggan['alamat_sampling'];
        //     // $dataH->no_tlp_sampling = \str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan['no_tlp_pic_sampling']);
        //     $dataH->nama_pic_sampling = ucwords($payload->informasi_pelanggan['nama_pic_sampling']);
        //     $dataH->jabatan_pic_sampling = $payload->informasi_pelanggan['jabatan_pic_sampling'];
        //     $dataH->no_tlp_pic_sampling = \str_replace(["-", "_"], "", $payload->informasi_pelanggan['no_tlp_pic_sampling']);
        //     $dataH->email_pic_sampling = $payload->informasi_pelanggan['email_pic_sampling'];
        //     //end lokasi sampling customer
        //     // $dataH->status_wilayah = $payload->status_wilayah;
        //     // $dataH->wilayah = $payload->wilayah;
        //     $dataH->periode_kontrak_awal = $payload->data_pendukung[0]['periodeAwal'];
        //     $dataH->periode_kontrak_akhir = $payload->data_pendukung[0]['periodeAkhir'];
        //     $dataH->sales_id = $payload->informasi_pelanggan['sales_id'];
        //     $dataH->created_by = $this->karyawan;
        //     $dataH->created_at = DATE('Y-m-d H:i:s');
        //     $data_pendukung_h = [];
        //     $data_s = [];
        //     $period = [];

        //     $globalTitikCounter = 1; // <======= BUAT NOMOR DI PENAMAAN TITIK
        //     foreach ($payload->data_pendukung as $key => $data_pendukungH) {
        //         $param = [];
        //         $regulasi = '';
        //         $periode = '';

        //         if ($data_pendukungH['parameter'] != null)
        //             $param = $data_pendukungH['parameter'];
        //         if (isset($data_pendukungH['regulasi']))
        //             $regulasi = $data_pendukungH['regulasi'];
        //         if ($data_pendukungH['periode'] != null)
        //             $periode = $data_pendukungH['periode'];

        //         $exp = explode("-", $data_pendukungH['kategori_1']);
        //         $kategori = $exp[0];
        //         $vol = 0;

        //         // GET PARAMETER NAME FOR CEK HARGA KONTRAK
        //         $parameter = [];
        //         foreach ($data_pendukungH['parameter'] as $va) {
        //             $cek_par = DB::table('parameter')->where('id', explode(';', $va)[0])->first();
        //             array_push($parameter, $cek_par->nama_lab);
        //         }

        //         $harga_pertitik = HargaParameter::select(DB::raw("SUM(harga) as total_harga, SUM(volume) as volume"))
        //             ->where('is_active', true)
        //             ->whereIn('nama_parameter', $parameter)
        //             ->where('id_kategori', $kategori)
        //             ->first();

        //         if ($harga_pertitik->volume != null)
        //             $vol += floatval($harga_pertitik->volume);
        //         if ($data_pendukungH['jumlah_titik'] == '') {
        //             $reqtitik = 0;
        //         } else {
        //             $reqtitik = $data_pendukungH['jumlah_titik'];
        //         }

        //         $temp_prearasi = [];
        //         if ($data_pendukungH['biaya_preparasi'] != null || $data_pendukungH['biaya_preparasi'] != "") {
        //             foreach ($data_pendukungH['biaya_preparasi'] as $pre) {
        //                 if ($pre['desc_preparasi'] != null && $pre['biaya_preparasi_padatan'] != null)
        //                     $temp_prearasi[] = ['Deskripsi' => $pre['desc_preparasi'], 'Harga' => floatval(\str_replace(['Rp. ', ','], '', $pre['biaya_preparasi_padatan']))];
        //             }
        //         }
        //         $biaya_preparasi = $temp_prearasi;

        //         array_push($data_pendukung_h, (object) [
        //             'kategori_1' => $data_pendukungH['kategori_1'],
        //             'kategori_2' => $data_pendukungH['kategori_2'],
        //             'regulasi' => $regulasi,
        //             'parameter' => $param,
        //             'jumlah_titik' => $data_pendukungH['jumlah_titik'],
        //             'penamaan_titik' => isset($data_pendukungH['penamaan_titik']) ? $data_pendukungH['penamaan_titik'] : "",
        //             'total_parameter' => count($param),
        //             'harga_satuan' => $harga_pertitik->total_harga,
        //             'harga_total' => floatval($harga_pertitik->total_harga) * (int) $reqtitik,
        //             'volume' => $vol,
        //             'periode' => $periode,
        //             'biaya_preparasi' => $biaya_preparasi
        //         ]);

        //         foreach ($data_pendukungH['periode'] as $key => $v) {
        //             array_push($period, $v);
        //         }
        //     }

        //     $dataH->data_pendukung_sampling = json_encode(array_values($data_pendukung_h));

        //     $dataH->save();

        //     $period = array_values(array_unique($period));

        //     foreach ($period as $key => $per) {
        //         // Insert Data Quotation Kontrak Detail
        //         $dataD = new QuotationKontrakD;
        //         $dataD->id_request_quotation_kontrak_h = $dataH->id;

        //         $data_sampling = [];
        //         $datas = [];
        //         $harga_total = 0;
        //         $harga_air = 0;
        //         $harga_udara = 0;
        //         $harga_emisi = 0;
        //         $harga_padatan = 0;
        //         $harga_swab_test = 0;
        //         $harga_tanah = 0;
        //         $grand_total = 0;
        //         $total_diskon = 0;
        //         $j = $key + 1;
        //         $n = 0;

        //         $desc_preparasi = [];
        //         $harga_preparasi = 0;
        //         foreach ($payload->data_pendukung as $m => $data_pendukungD) {
        //             if (in_array($per, $data_pendukungD['periode'])) {
        //                 $param = [];
        //                 $regulasi = '';
        //                 if ($data_pendukungD['parameter'] != null)
        //                     $param = $data_pendukungD['parameter'];
        //                 if (isset($data_pendukungD['regulasi']))
        //                     $regulasi = $data_pendukungD['regulasi'];

        //                 $exp = explode("-", $data_pendukungD['kategori_1']);
        //                 $kategori = $exp[0];
        //                 $vol = 0;

        //                 // GET PARAMETER NAME FOR CEK HARGA KONTRAK
        //                 $parameter = [];
        //                 foreach ($data_pendukungD['parameter'] as $va) {
        //                     $cek_par = DB::table('parameter')->where('id', explode(';', $va)[0])->first();
        //                     array_push($parameter, $cek_par->nama_lab);
        //                 }

        //                 $harga_pertitik = HargaParameter::select(DB::raw("SUM(harga) as total_harga, SUM(volume) as volume"))
        //                     ->where('is_active', true)
        //                     ->whereIn('nama_parameter', $parameter)
        //                     ->where('id_kategori', $kategori)
        //                     ->first();

        //                 if ($harga_pertitik->volume != null)
        //                     $vol += floatval($harga_pertitik->volume);
        //                 if ($data_pendukungD['jumlah_titik'] == '') {
        //                     $reqtitik = 0;
        //                 } else {
        //                     $reqtitik = $data_pendukungD['jumlah_titik'];
        //                 }

        //                 //============= BIAYA PREPARASI ==================
        //                 $temp_prearasi = [];
        //                 if ($data_pendukungD['biaya_preparasi'] != null || $data_pendukungD['biaya_preparasi'] != "") {
        //                     foreach ($data_pendukungD['biaya_preparasi'] as $pre) {
        //                         if ($pre['desc_preparasi'] != null && $pre['biaya_preparasi_padatan'] != null)
        //                             $temp_prearasi[] = ['Deskripsi' => $pre['desc_preparasi'], 'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre['biaya_preparasi_padatan']))];
        //                         if ($pre['biaya_preparasi_padatan'] != null || $pre['biaya_preparasi_padatan'] != "")
        //                             $harga_preparasi += floatval(\str_replace(['Rp. ', ',', '.'], '', $pre['biaya_preparasi_padatan']));
        //                     }
        //                 }
        //                 $biaya_preparasi = $temp_prearasi;

        //                 // dd($biaya_preparasi);

        //                 // PENENTUAN NOMOR PENAMAAN TITIK
        //                 $penamaan_titik_fixed = [];
        //                 if ($data_pendukungD['penamaan_titik'] != null) {
        //                     foreach ($data_pendukungD['penamaan_titik'] as $pt) {
        //                         $penamaan_titik_fixed[] = [sprintf('%03d', $globalTitikCounter) => trim($pt)];
        //                         $globalTitikCounter++;
        //                     }
        //                 }

        //                 $data_sampling[$n++] = [
        //                     'kategori_1' => $data_pendukungD['kategori_1'],
        //                     'kategori_2' => $data_pendukungD['kategori_2'],
        //                     'regulasi' => $regulasi,
        //                     'parameter' => $param,
        //                     'jumlah_titik' => $data_pendukungD['jumlah_titik'],
        //                     'penamaan_titik' => $penamaan_titik_fixed,
        //                     'total_parameter' => count($param),
        //                     'harga_satuan' => $harga_pertitik->total_harga,
        //                     'harga_total' => floatval($harga_pertitik->total_harga) * (int) $reqtitik,
        //                     'volume' => $vol,
        //                     'biaya_preparasi' => $biaya_preparasi
        //                 ];

        //                 // kalkulasi harga parameter sesuai titik
        //                 if ($kategori == 1) { // air
        //                     // dd('masuk');
        //                     $harga_air += floatval($harga_pertitik->total_harga) * (int) $reqtitik;

        //                 } else if ($kategori == 4) { //  udara
        //                     $harga_udara += floatval($harga_pertitik->total_harga) * (int) $reqtitik;
        //                 } else if ($kategori == 5) { // emisi

        //                     $harga_emisi += floatval($harga_pertitik->total_harga) * (int) $reqtitik;
        //                 } else if ($kategori == 6) { // padatan

        //                     $harga_padatan += floatval($harga_pertitik->total_harga) * (int) $reqtitik;
        //                 } else if ($kategori == 7) { // swab test

        //                     $harga_swab_test += floatval($harga_pertitik->total_harga) * (int) $reqtitik;
        //                 } else if ($kategori == 8) { // tanah

        //                     $harga_tanah += floatval($harga_pertitik->total_harga) * (int) $reqtitik;
        //                 }
        //                 // end kalkulasi harga parameter sesuai titik
        //             }
        //         }

        //         $datas[$j] = [
        //             'periode_kontrak' => $per,
        //             'data_sampling' => json_encode(array_values($data_sampling))
        //         ];

        //         $dataD->periode_kontrak = $per;
        //         $grand_total += $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah;
        //         $dataD->data_pendukung_sampling = json_encode($datas);
        //         // end data sampling
        //         $dataD->harga_air = $harga_air;
        //         $dataD->harga_udara = $harga_udara;
        //         $dataD->harga_emisi = $harga_emisi;
        //         $dataD->harga_padatan = $harga_padatan;
        //         $dataD->harga_swab_test = $harga_swab_test;
        //         $dataD->harga_tanah = $harga_tanah;

        //         //============= BIAYA PREPARASI
        //         $dataD->biaya_preparasi = json_encode($desc_preparasi);
        //         $dataD->total_biaya_preparasi = $harga_preparasi;
        //         // dd($dataD);
        //         $dataD->save();
        //     }

        //     $data_request = RequestQr::where('id', $payload->informasi_pelanggan['id'])->first();
        //     // dd($data_request);
        //     $data_request->is_active = 0;
        //     $data_request->save();

        //     if($this->karyawan == $data_request->created_by){ // JIka yang membuat request qr itu sendiri maka kirim ke atasan juga
        //         $message = 'Request QR telah diexport ke request quotation';

        //         $getAtasan = GetAtasan::where('id', $this->user_id)->get()->pluck('id')->toArray();

        //         Notification::whereIn('id', $getAtasan)
        //             ->title('Ticket Programming Update')
        //             ->message($message . ' Oleh ' . $this->karyawan)
        //             ->url('/ticket-programming')
        //             ->send();
        //     }else { // JIka yang membuat quotation itu bukan yang membuat request qr maka kirim ke yang membuat request qr
        //         $message = 'Request QR telah diexport ke request quotation';
        //         Notification::where('nama_lengkap', $dataH->created_by)
        //             ->title('Request QR telah diexport ke request quotation')
        //             ->message($message . ' Oleh ' . $this->karyawan)
        //             ->url('/ticket-programming')
        //             ->send();
        //     }

        //     DB::commit();

        //     return response()->json([
        //         'message' => "Request Quotation number $no_document success created",
        //         'status' => 200
        //     ], 200);
        // } catch (\Exception $e) {
        //     DB::rollback();
        //     throw $e;
        // }
    }

    /**
     * Create Request Non Kontrak
     */
    private function createRequestNon($payload){
        DB::beginTransaction();
        try{
            $data = new RequestQR;

            $data->nama_pelanggan = $this->normalizeText($payload->informasi_pelanggan['nama_perusahaan']);
            $data->tipe = isset($payload->informasi_pelanggan['tipe']) && $payload->informasi_pelanggan['tipe'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['tipe'])) : null;
            $data->konsultan = isset($payload->informasi_pelanggan['konsultan']) && $payload->informasi_pelanggan['konsultan'] !== '' ? $this->normalizeText($payload->informasi_pelanggan['konsultan']) : null;
            $data->alamat_kantor = isset($payload->informasi_pelanggan['alamat_kantor']) && $payload->informasi_pelanggan['alamat_kantor'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['alamat_kantor'])) : null;
            $data->no_tlp_perusahaan = isset($payload->informasi_pelanggan['no_tlp_perusahaan']) && $payload->informasi_pelanggan['no_tlp_perusahaan'] !== '' ? str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan['no_tlp_perusahaan']) : null;
            $data->nama_pic_order = isset($payload->informasi_pelanggan['nama_pic_order']) && $payload->informasi_pelanggan['nama_pic_order'] !== '' ? ucwords($payload->informasi_pelanggan['nama_pic_order']) : null;
            $data->jabatan_pic_order = isset($payload->informasi_pelanggan['jabatan_pic_order']) && $payload->informasi_pelanggan['jabatan_pic_order'] !== '' ? $payload->informasi_pelanggan['jabatan_pic_order'] : null;
            $data->no_pic_order = isset($payload->informasi_pelanggan['no_pic_order']) && $payload->informasi_pelanggan['no_pic_order'] !== '' ? str_replace(["-", "_"], "", $payload->informasi_pelanggan['no_pic_order']) : null;
            $data->email_pic_order = isset($payload->informasi_pelanggan['email_pic_order']) && $payload->informasi_pelanggan['email_pic_order'] !== '' ? $payload->informasi_pelanggan['email_pic_order'] : null;
            $data->email_cc = isset($payload->informasi_pelanggan['email_cc']) && $payload->informasi_pelanggan['email_cc'] !== '' ? json_encode($payload->informasi_pelanggan['email_cc']) : null;
            $data->status_sampling = isset($payload->informasi_pelanggan['status_sampling']) && $payload->informasi_pelanggan['status_sampling'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['status_sampling'])) : null;
            $data->alamat_sampling = isset($payload->informasi_pelanggan['alamat_sampling']) && $payload->informasi_pelanggan['alamat_sampling'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['alamat_sampling'])) : null;
            $data->no_tlp_sampling = isset($payload->informasi_pelanggan['no_tlp_pic_sampling']) && $payload->informasi_pelanggan['no_tlp_pic_sampling'] !== '' ? str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan['no_tlp_pic_sampling']) : null;
            $data->nama_pic_sampling = isset($payload->informasi_pelanggan['nama_pic_sampling']) && $payload->informasi_pelanggan['nama_pic_sampling'] !== '' ? ucwords($payload->informasi_pelanggan['nama_pic_sampling']) : null;
            $data->jabatan_pic_sampling = isset($payload->informasi_pelanggan['jabatan_pic_sampling']) && $payload->informasi_pelanggan['jabatan_pic_sampling'] !== '' ? $payload->informasi_pelanggan['jabatan_pic_sampling'] : null;
            $data->no_tlp_pic_sampling = isset($payload->informasi_pelanggan['no_tlp_pic_sampling']) && $payload->informasi_pelanggan['no_tlp_pic_sampling'] !== '' ? str_replace(["-", "_"], "", $payload->informasi_pelanggan['no_tlp_pic_sampling']) : null;
            $data->email_pic_sampling = isset($payload->informasi_pelanggan['email_pic_sampling']) && $payload->informasi_pelanggan['email_pic_sampling'] !== '' ? $payload->informasi_pelanggan['email_pic_sampling'] : null;

            $data_sampling = [];
            $harga_total = 0;
            $harga_air = 0;
            $harga_udara = 0;
            $harga_emisi = 0;
            $harga_padatan = 0;
            $harga_swab_test = 0;
            $harga_tanah = 0;
            $harga_pangan = 0;
            $grand_total = 0;
            $harga_preparasi = 0;
            $desc_preparasi = [];
            // $total_diskon = 0;

            if (isset($payload->data_pendukung)) {
                foreach ($payload->data_pendukung as $i => $item) {
                    $param = $item['parameter'];
                    $exp = explode("-", $item['kategori_1']);
                    $kategori = $exp[0];
                    $vol = 0;

                    $parameter = [];
                    foreach ($param as $par) {
                        $cek_par = Parameter::where('id', explode(';', $par)[0])->first();
                        array_push($parameter, $cek_par->nama_lab);
                    }

                    $harga_pertitik = HargaParameter::select(DB::raw("SUM(harga) as total_harga, SUM(volume) as volume"))
                        ->where('is_active', true)
                        ->whereIn('nama_parameter', $parameter)
                        ->where('id_kategori', $kategori)
                        ->first();

                    if ($harga_pertitik->volume != null) {
                        $vol += floatval($harga_pertitik->volume);
                    }

                    $titik = $item['jumlah_titik'];

                    // Process biaya_preparasi if exists
                    $temp_preparasi = [];
                    if (isset($item['biaya_preparasi']) && $item['biaya_preparasi'] != null) {
                        foreach ($item['biaya_preparasi'] as $pre) {
                            if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null) {
                                $temp_preparasi[] = [
                                    'Deskripsi' => $pre->desc_preparasi,
                                    'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))
                                ];
                            }
                            if ($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "") {
                                $harga_preparasi += floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan));
                            }
                        }
                    }

                    $data_sampling[$i] = [
                        'kategori_1' => $item['kategori_1'],
                        'kategori_2' => $item['kategori_2'],
                        'regulasi' => isset($item['regulasi']) ? $item['regulasi'] : '',
                        'penamaan_titik' => isset($item['penamaan_titik']) ? $item['penamaan_titik'] : '',
                        'parameter' => $param,
                        'jumlah_titik' => $titik,
                        'total_parameter' => count($param),
                        'harga_satuan' => $harga_pertitik->total_harga,
                        'harga_total' => floatval($harga_pertitik->total_harga) * (int) $titik,
                        'volume' => $vol,
                        'biaya_preparasi' => $temp_preparasi
                    ];

                    switch ($kategori) {
                        case '1':
                            $harga_air += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '4':
                            $harga_udara += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '5':
                            $harga_emisi += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '6':
                            $harga_padatan += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '7':
                            $harga_swab_test += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '8':
                            $harga_tanah += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '9':
                            $harga_pangan += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                    }
                }
            } else {
                $data_sampling = [];
            }

            $grand_total = $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah + $harga_pangan;
            $data->data_pendukung_sampling = json_encode(array_values($data_sampling));

            // Store prices in $data
            $data->harga_air = $harga_air;
            $data->harga_udara = $harga_udara;
            $data->harga_emisi = $harga_emisi;
            $data->harga_padatan = $harga_padatan;
            $data->harga_swab_test = $harga_swab_test;
            $data->harga_tanah = $harga_tanah;
            $data->harga_pangan = $harga_pangan;
            $data->grand_total = $grand_total;

            $data->created_by = $this->karyawan;
            $data->created_at = DATE('Y-m-d H:i:s');
            $data->save();

            // Remove the period loop since all prices are now being saved directly to $data
            // Note: I'm assuming that $period variable comes from somewhere else in the code
            // The entire period foreach loop has been removed as requested

            DB::commit();
            return response()->json([
                'message' => "Request QR untuk perusahaan $data->nama_pelanggan berhasil disimpan",
            ], 201);
        } catch(\Exception $e){
            DB::rollBack();
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
        }
    }

    /**
     * Create Request Kontrak
     *
     */
    private function createRequestKontrak($payload){
        DB::beginTransaction();
        try {
            $data = new RequestQR;

            $data->nama_pelanggan = $this->normalizeText($payload->informasi_pelanggan['nama_perusahaan']);
            $data->tipe = isset($payload->informasi_pelanggan['tipe']) && $payload->informasi_pelanggan['tipe'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['tipe'])) : null;
            $data->konsultan = isset($payload->informasi_pelanggan['konsultan']) && $payload->informasi_pelanggan['konsultan'] !== '' ? $this->normalizeText($payload->informasi_pelanggan['konsultan']) : null;
            $data->alamat_kantor = isset($payload->informasi_pelanggan['alamat_kantor']) && $payload->informasi_pelanggan['alamat_kantor'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['alamat_kantor'])) : null;
            $data->no_tlp_perusahaan = isset($payload->informasi_pelanggan['no_tlp_perusahaan']) && $payload->informasi_pelanggan['no_tlp_perusahaan'] !== '' ? str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan['no_tlp_perusahaan']) : null;
            $data->nama_pic_order = isset($payload->informasi_pelanggan['nama_pic_order']) && $payload->informasi_pelanggan['nama_pic_order'] !== '' ? ucwords($payload->informasi_pelanggan['nama_pic_order']) : null;
            $data->jabatan_pic_order = isset($payload->informasi_pelanggan['jabatan_pic_order']) && $payload->informasi_pelanggan['jabatan_pic_order'] !== '' ? $payload->informasi_pelanggan['jabatan_pic_order'] : null;
            $data->no_pic_order = isset($payload->informasi_pelanggan['no_pic_order']) && $payload->informasi_pelanggan['no_pic_order'] !== '' ? str_replace(["-", "_"], "", $payload->informasi_pelanggan['no_pic_order']) : null;
            $data->email_pic_order = isset($payload->informasi_pelanggan['email_pic_order']) && $payload->informasi_pelanggan['email_pic_order'] !== '' ? $payload->informasi_pelanggan['email_pic_order'] : null;
            $data->email_cc = isset($payload->informasi_pelanggan['email_cc']) && $payload->informasi_pelanggan['email_cc'] !== '' ? json_encode($payload->informasi_pelanggan['email_cc']) : null;
            $data->status_sampling = isset($payload->informasi_pelanggan['status_sampling']) && $payload->informasi_pelanggan['status_sampling'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['status_sampling'])) : null;
            $data->alamat_sampling = isset($payload->informasi_pelanggan['alamat_sampling']) && $payload->informasi_pelanggan['alamat_sampling'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['alamat_sampling'])) : null;
            $data->no_tlp_sampling = isset($payload->informasi_pelanggan['no_tlp_pic_sampling']) && $payload->informasi_pelanggan['no_tlp_pic_sampling'] !== '' ? str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan['no_tlp_pic_sampling']) : null;
            $data->nama_pic_sampling = isset($payload->informasi_pelanggan['nama_pic_sampling']) && $payload->informasi_pelanggan['nama_pic_sampling'] !== '' ? ucwords($payload->informasi_pelanggan['nama_pic_sampling']) : null;
            $data->jabatan_pic_sampling = isset($payload->informasi_pelanggan['jabatan_pic_sampling']) && $payload->informasi_pelanggan['jabatan_pic_sampling'] !== '' ? $payload->informasi_pelanggan['jabatan_pic_sampling'] : null;
            $data->no_tlp_pic_sampling = isset($payload->informasi_pelanggan['no_tlp_pic_sampling']) && $payload->informasi_pelanggan['no_tlp_pic_sampling'] !== '' ? str_replace(["-", "_"], "", $payload->informasi_pelanggan['no_tlp_pic_sampling']) : null;
            $data->email_pic_sampling = isset($payload->informasi_pelanggan['email_pic_sampling']) && $payload->informasi_pelanggan['email_pic_sampling'] !== '' ? $payload->informasi_pelanggan['email_pic_sampling'] : null;
            $data->periode_kontrak_awal = $payload->data_pendukung[0]['periodeAwal'];
            $data->periode_kontrak_akhir = $payload->data_pendukung[0]['periodeAkhir'];
            $data->created_by = $this->karyawan;
            $data->created_at = DATE('Y-m-d H:i:s');
            $data_pendukung_h = [];
            $period = [];
            $grand_total = 0;
            $total_volume = 0;

            $globalTitikCounter = 1; // <======= BUAT NOMOR DI PENAMAAN TITIK
            foreach ($payload->data_pendukung as $key => $data_pendukungH){
                $param = [];
                $regulasi = '';
                $periode = '';

                if ($data_pendukungH['parameter'] != null)
                    $param = $data_pendukungH['parameter'];
                if (isset($data_pendukungH['regulasi']))
                    $regulasi = $data_pendukungH['regulasi'];
                if ($data_pendukungH['periode'] != null)
                    $periode = $data_pendukungH['periode'];

                $exp = explode("-", $data_pendukungH['kategori_1']);
                $kategori = $exp[0];
                $vol = 0;

                // GET PARAMETER NAME FOR CEK HARGA KONTRAK
                $parameter = [];
                foreach ($data_pendukungH['parameter'] as $va) {
                    $cek_par = DB::table('parameter')->where('id', explode(';', $va)[0])->first();
                    array_push($parameter, $cek_par->nama_lab);
                }

                $harga_pertitik = HargaParameter::select(DB::raw("SUM(harga) as total_harga, SUM(volume) as volume"))
                    ->where('is_active', true)
                    ->whereIn('nama_parameter', $parameter)
                    ->where('id_kategori', $kategori)
                    ->first();

                if ($harga_pertitik->volume != null)
                    $vol += floatval($harga_pertitik->volume);
                if ($data_pendukungH['jumlah_titik'] == '') {
                    $reqtitik = 0;
                } else {
                    $reqtitik = $data_pendukungH['jumlah_titik'];
                }

                $temp_prearasi = [];
                if ($data_pendukungH['biaya_preparasi'] != null || $data_pendukungH['biaya_preparasi'] != "") {
                    foreach ($data_pendukungH['biaya_preparasi'] as $pre) {
                        if ($pre['desc_preparasi'] != null && $pre['biaya_preparasi_padatan'] != null)
                            $temp_prearasi[] = ['Deskripsi' => $pre['desc_preparasi'], 'Harga' => floatval(\str_replace(['Rp. ', ','], '', $pre['biaya_preparasi_padatan']))];
                    }
                }
                $biaya_preparasi = $temp_prearasi;

                // Calculate total price for this item
                $harga_total = floatval($harga_pertitik->total_harga) * (int) $reqtitik;

                // Add to grand total
                $grand_total += $harga_total;
                $total_volume += $vol;

                array_push($data_pendukung_h, (object) [
                    'kategori_1' => $data_pendukungH['kategori_1'],
                    'kategori_2' => $data_pendukungH['kategori_2'],
                    'regulasi' => $regulasi,
                    'parameter' => $param,
                    'jumlah_titik' => $data_pendukungH['jumlah_titik'],
                    'penamaan_titik' => isset($data_pendukungH['penamaan_titik']) ? $data_pendukungH['penamaan_titik'] : "",
                    'total_parameter' => count($param),
                    'harga_satuan' => $harga_pertitik->total_harga,
                    'harga_total' => $harga_total,
                    'volume' => $vol,
                    'periode' => $periode,
                    'biaya_preparasi' => $biaya_preparasi
                ]);

                // Store all periods in the period array
                if (is_array($data_pendukungH['periode'])) {
                    foreach ($data_pendukungH['periode'] as $p) {
                        $period[] = $p;
                    }
                }
            }

            // Store processed data to $data object
            $data->data_pendukung_sampling = json_encode(array_values($data_pendukung_h));
            $data->grand_total = $grand_total;

            $data->save();
            DB::commit();

            return response()->json([
                'message' => "Request QR untuk perusahaan $data->nama_pelanggan berhasil dibuat",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
        }
    }
    /**
     * Update Request Non Kontrak
     */
    private function updateRequestNon($payload){
        DB::beginTransaction();
        try{
            $data = RequestQr::where('id', $payload->informasi_pelanggan['id'])->where('is_active', true)->first();
            if($data == null){
                return response()->json(['message' => 'Request QR tidak ditemukan'], 404);
            }

            $data->nama_pelanggan = $this->normalizeText($payload->informasi_pelanggan['nama_perusahaan']);
            $data->tipe = isset($payload->informasi_pelanggan['tipe']) && $payload->informasi_pelanggan['tipe'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['tipe'])) : null;
            $data->konsultan = isset($payload->informasi_pelanggan['konsultan']) && $payload->informasi_pelanggan['konsultan'] !== '' ? $this->normalizeText($payload->informasi_pelanggan['konsultan']) : null;
            $data->alamat_kantor = isset($payload->informasi_pelanggan['alamat_kantor']) && $payload->informasi_pelanggan['alamat_kantor'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['alamat_kantor'])) : null;
            $data->no_tlp_perusahaan = isset($payload->informasi_pelanggan['no_tlp_perusahaan']) && $payload->informasi_pelanggan['no_tlp_perusahaan'] !== '' ? str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan['no_tlp_perusahaan']) : null;
            $data->nama_pic_order = isset($payload->informasi_pelanggan['nama_pic_order']) && $payload->informasi_pelanggan['nama_pic_order'] !== '' ? ucwords($payload->informasi_pelanggan['nama_pic_order']) : null;
            $data->jabatan_pic_order = isset($payload->informasi_pelanggan['jabatan_pic_order']) && $payload->informasi_pelanggan['jabatan_pic_order'] !== '' ? $payload->informasi_pelanggan['jabatan_pic_order'] : null;
            $data->no_pic_order = isset($payload->informasi_pelanggan['no_pic_order']) && $payload->informasi_pelanggan['no_pic_order'] !== '' ? str_replace(["-", "_"], "", $payload->informasi_pelanggan['no_pic_order']) : null;
            $data->email_pic_order = isset($payload->informasi_pelanggan['email_pic_order']) && $payload->informasi_pelanggan['email_pic_order'] !== '' ? $payload->informasi_pelanggan['email_pic_order'] : null;
            $data->email_cc = isset($payload->informasi_pelanggan['email_cc']) && $payload->informasi_pelanggan['email_cc'] !== '' ? json_encode($payload->informasi_pelanggan['email_cc']) : null;
            $data->status_sampling = isset($payload->informasi_pelanggan['status_sampling']) && $payload->informasi_pelanggan['status_sampling'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['status_sampling'])) : null;
            $data->alamat_sampling = isset($payload->informasi_pelanggan['alamat_sampling']) && $payload->informasi_pelanggan['alamat_sampling'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['alamat_sampling'])) : null;
            $data->no_tlp_sampling = isset($payload->informasi_pelanggan['no_tlp_pic_sampling']) && $payload->informasi_pelanggan['no_tlp_pic_sampling'] !== '' ? str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan['no_tlp_pic_sampling']) : null;
            $data->nama_pic_sampling = isset($payload->informasi_pelanggan['nama_pic_sampling']) && $payload->informasi_pelanggan['nama_pic_sampling'] !== '' ? ucwords($payload->informasi_pelanggan['nama_pic_sampling']) : null;
            $data->jabatan_pic_sampling = isset($payload->informasi_pelanggan['jabatan_pic_sampling']) && $payload->informasi_pelanggan['jabatan_pic_sampling'] !== '' ? $payload->informasi_pelanggan['jabatan_pic_sampling'] : null;
            $data->no_tlp_pic_sampling = isset($payload->informasi_pelanggan['no_tlp_pic_sampling']) && $payload->informasi_pelanggan['no_tlp_pic_sampling'] !== '' ? str_replace(["-", "_"], "", $payload->informasi_pelanggan['no_tlp_pic_sampling']) : null;
            $data->email_pic_sampling = isset($payload->informasi_pelanggan['email_pic_sampling']) && $payload->informasi_pelanggan['email_pic_sampling'] !== '' ? $payload->informasi_pelanggan['email_pic_sampling'] : null;

            $data_sampling = [];
            $harga_total = 0;
            $harga_air = 0;
            $harga_udara = 0;
            $harga_emisi = 0;
            $harga_padatan = 0;
            $harga_swab_test = 0;
            $harga_tanah = 0;
            $harga_pangan = 0;
            $grand_total = 0;
            $harga_preparasi = 0;
            $desc_preparasi = [];
            // $total_diskon = 0;

            if (isset($payload->data_pendukung)) {
                foreach ($payload->data_pendukung as $i => $item) {
                    $param = $item['parameter'];
                    $exp = explode("-", $item['kategori_1']);
                    $kategori = $exp[0];
                    $vol = 0;

                    $parameter = [];
                    foreach ($param as $par) {
                        $cek_par = Parameter::where('id', explode(';', $par)[0])->first();
                        array_push($parameter, $cek_par->nama_lab);
                    }

                    $harga_pertitik = HargaParameter::select(DB::raw("SUM(harga) as total_harga, SUM(volume) as volume"))
                        ->where('is_active', true)
                        ->whereIn('nama_parameter', $parameter)
                        ->where('id_kategori', $kategori)
                        ->first();

                    if ($harga_pertitik->volume != null) {
                        $vol += floatval($harga_pertitik->volume);
                    }

                    $titik = $item['jumlah_titik'];

                    // Process biaya_preparasi if exists
                    $temp_preparasi = [];
                    if (isset($item['biaya_preparasi']) && $item['biaya_preparasi'] != null) {
                        foreach ($item['biaya_preparasi'] as $pre) {
                            if ($pre->desc_preparasi != null && $pre->biaya_preparasi_padatan != null) {
                                $temp_preparasi[] = [
                                    'Deskripsi' => $pre->desc_preparasi,
                                    'Harga' => floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan))
                                ];
                            }
                            if ($pre->biaya_preparasi_padatan != null || $pre->biaya_preparasi_padatan != "") {
                                $harga_preparasi += floatval(\str_replace(['Rp. ', ',', '.'], '', $pre->biaya_preparasi_padatan));
                            }
                        }
                    }

                    $data_sampling[$i] = [
                        'kategori_1' => $item['kategori_1'],
                        'kategori_2' => $item['kategori_2'],
                        'regulasi' => isset($item['regulasi']) ? $item['regulasi'] : '',
                        'penamaan_titik' => isset($item['penamaan_titik']) ? $item['penamaan_titik'] : '',
                        'parameter' => $param,
                        'jumlah_titik' => $titik,
                        'total_parameter' => count($param),
                        'harga_satuan' => $harga_pertitik->total_harga,
                        'harga_total' => floatval($harga_pertitik->total_harga) * (int) $titik,
                        'volume' => $vol,
                        'biaya_preparasi' => $temp_preparasi
                    ];

                    switch ($kategori) {
                        case '1':
                            $harga_air += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '4':
                            $harga_udara += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '5':
                            $harga_emisi += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '6':
                            $harga_padatan += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '7':
                            $harga_swab_test += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '8':
                            $harga_tanah += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                        case '9':
                            $harga_pangan += floatval($harga_pertitik->total_harga) * (int) $titik;
                            break;
                    }
                }
            } else {
                $data_sampling = [];
            }

            $grand_total = $harga_air + $harga_udara + $harga_emisi + $harga_padatan + $harga_swab_test + $harga_tanah + $harga_pangan;
            $data->data_pendukung_sampling = json_encode(array_values($data_sampling));

            // Store prices in $data
            $data->harga_air = $harga_air;
            $data->harga_udara = $harga_udara;
            $data->harga_emisi = $harga_emisi;
            $data->harga_padatan = $harga_padatan;
            $data->harga_swab_test = $harga_swab_test;
            $data->harga_tanah = $harga_tanah;
            $data->harga_pangan = $harga_pangan;
            $data->grand_total = $grand_total;

            $data->updated_by = $this->karyawan;
            $data->updated_at = DATE('Y-m-d H:i:s');
            $data->save();

            // Remove the period loop since all prices are now being saved directly to $data
            // Note: I'm assuming that $period variable comes from somewhere else in the code
            // The entire period foreach loop has been removed as requested

            DB::commit();
            return response()->json([
                'message' => "Request QR untuk perusahaan $data->nama_pelanggan berhasil disimpan",
            ], 201);
        } catch(\Exception $e){
            DB::rollBack();
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
        }
    }

    /**
     * Update Request Kontrak
     *
     */
    private function updateRequestKontrak($payload){
        DB::beginTransaction();
        try {
            $data = RequestQr::where('id', $payload->informasi_pelanggan['id'])->where('is_active', true)->first();

            $data->nama_pelanggan = $this->normalizeText($payload->informasi_pelanggan['nama_perusahaan']);
            $data->tipe = isset($payload->informasi_pelanggan['tipe']) && $payload->informasi_pelanggan['tipe'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['tipe'])) : null;
            $data->konsultan = isset($payload->informasi_pelanggan['konsultan']) && $payload->informasi_pelanggan['konsultan'] !== '' ? $this->normalizeText($payload->informasi_pelanggan['konsultan']) : null;
            $data->alamat_kantor = isset($payload->informasi_pelanggan['alamat_kantor']) && $payload->informasi_pelanggan['alamat_kantor'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['alamat_kantor'])) : null;
            $data->no_tlp_perusahaan = isset($payload->informasi_pelanggan['no_tlp_perusahaan']) && $payload->informasi_pelanggan['no_tlp_perusahaan'] !== '' ? str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan['no_tlp_perusahaan']) : null;
            $data->nama_pic_order = isset($payload->informasi_pelanggan['nama_pic_order']) && $payload->informasi_pelanggan['nama_pic_order'] !== '' ? ucwords($payload->informasi_pelanggan['nama_pic_order']) : null;
            $data->jabatan_pic_order = isset($payload->informasi_pelanggan['jabatan_pic_order']) && $payload->informasi_pelanggan['jabatan_pic_order'] !== '' ? $payload->informasi_pelanggan['jabatan_pic_order'] : null;
            $data->no_pic_order = isset($payload->informasi_pelanggan['no_pic_order']) && $payload->informasi_pelanggan['no_pic_order'] !== '' ? str_replace(["-", "_"], "", $payload->informasi_pelanggan['no_pic_order']) : null;
            $data->email_pic_order = isset($payload->informasi_pelanggan['email_pic_order']) && $payload->informasi_pelanggan['email_pic_order'] !== '' ? $payload->informasi_pelanggan['email_pic_order'] : null;
            $data->email_cc = isset($payload->informasi_pelanggan['email_cc']) && $payload->informasi_pelanggan['email_cc'] !== '' ? json_encode($payload->informasi_pelanggan['email_cc']) : null;
            $data->status_sampling = isset($payload->informasi_pelanggan['status_sampling']) && $payload->informasi_pelanggan['status_sampling'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['status_sampling'])) : null;
            $data->alamat_sampling = isset($payload->informasi_pelanggan['alamat_sampling']) && $payload->informasi_pelanggan['alamat_sampling'] !== '' ? strtoupper(trim($payload->informasi_pelanggan['alamat_sampling'])) : null;
            $data->no_tlp_sampling = isset($payload->informasi_pelanggan['no_tlp_pic_sampling']) && $payload->informasi_pelanggan['no_tlp_pic_sampling'] !== '' ? str_replace(["-", "(", ")", " ", "_"], "", $payload->informasi_pelanggan['no_tlp_pic_sampling']) : null;
            $data->nama_pic_sampling = isset($payload->informasi_pelanggan['nama_pic_sampling']) && $payload->informasi_pelanggan['nama_pic_sampling'] !== '' ? ucwords($payload->informasi_pelanggan['nama_pic_sampling']) : null;
            $data->jabatan_pic_sampling = isset($payload->informasi_pelanggan['jabatan_pic_sampling']) && $payload->informasi_pelanggan['jabatan_pic_sampling'] !== '' ? $payload->informasi_pelanggan['jabatan_pic_sampling'] : null;
            $data->no_tlp_pic_sampling = isset($payload->informasi_pelanggan['no_tlp_pic_sampling']) && $payload->informasi_pelanggan['no_tlp_pic_sampling'] !== '' ? str_replace(["-", "_"], "", $payload->informasi_pelanggan['no_tlp_pic_sampling']) : null;
            $data->email_pic_sampling = isset($payload->informasi_pelanggan['email_pic_sampling']) && $payload->informasi_pelanggan['email_pic_sampling'] !== '' ? $payload->informasi_pelanggan['email_pic_sampling'] : null;
            $data->periode_kontrak_awal = $payload->data_pendukung[0]['periodeAwal'];
            $data->periode_kontrak_akhir = $payload->data_pendukung[0]['periodeAkhir'];
            $data->updated_by = $this->karyawan;
            $data->updated_at = DATE('Y-m-d H:i:s');
            $data_pendukung_h = [];
            $period = [];
            $grand_total = 0;
            $total_volume = 0;

            foreach ($payload->data_pendukung as $key => $data_pendukungH){
                $param = [];
                $regulasi = '';
                $periode = '';

                if ($data_pendukungH['parameter'] != null)
                    $param = $data_pendukungH['parameter'];
                if (isset($data_pendukungH['regulasi']))
                    $regulasi = $data_pendukungH['regulasi'];
                if ($data_pendukungH['periode'] != null)
                    $periode = $data_pendukungH['periode'];

                $exp = explode("-", $data_pendukungH['kategori_1']);
                $kategori = $exp[0];
                $vol = 0;

                // GET PARAMETER NAME FOR CEK HARGA KONTRAK
                $parameter = [];
                foreach ($data_pendukungH['parameter'] as $va) {
                    $cek_par = DB::table('parameter')->where('id', explode(';', $va)[0])->first();
                    array_push($parameter, $cek_par->nama_lab);
                }

                $harga_pertitik = HargaParameter::select(DB::raw("SUM(harga) as total_harga, SUM(volume) as volume"))
                    ->where('is_active', true)
                    ->whereIn('nama_parameter', $parameter)
                    ->where('id_kategori', $kategori)
                    ->first();

                if ($harga_pertitik->volume != null)
                    $vol += floatval($harga_pertitik->volume);
                if ($data_pendukungH['jumlah_titik'] == '') {
                    $reqtitik = 0;
                } else {
                    $reqtitik = $data_pendukungH['jumlah_titik'];
                }

                $temp_prearasi = [];
                if ($data_pendukungH['biaya_preparasi'] != null || $data_pendukungH['biaya_preparasi'] != "") {
                    foreach ($data_pendukungH['biaya_preparasi'] as $pre) {
                        if ($pre['desc_preparasi'] != null && $pre['biaya_preparasi_padatan'] != null)
                            $temp_prearasi[] = ['Deskripsi' => $pre['desc_preparasi'], 'Harga' => floatval(\str_replace(['Rp. ', ','], '', $pre['biaya_preparasi_padatan']))];
                    }
                }
                $biaya_preparasi = $temp_prearasi;

                // Calculate total price for this item
                $harga_total = floatval($harga_pertitik->total_harga) * (int) $reqtitik;

                // Add to grand total
                $grand_total += $harga_total;
                $total_volume += $vol;

                array_push($data_pendukung_h, (object) [
                    'kategori_1' => $data_pendukungH['kategori_1'],
                    'kategori_2' => $data_pendukungH['kategori_2'],
                    'regulasi' => $regulasi,
                    'parameter' => $param,
                    'jumlah_titik' => $data_pendukungH['jumlah_titik'],
                    'penamaan_titik' => isset($data_pendukungH['penamaan_titik']) ? $data_pendukungH['penamaan_titik'] : "",
                    'total_parameter' => count($param),
                    'harga_satuan' => $harga_pertitik->total_harga,
                    'harga_total' => $harga_total,
                    'volume' => $vol,
                    'periode' => $periode,
                    'biaya_preparasi' => $biaya_preparasi
                ]);

                // Store all periods in the period array
                if (is_array($data_pendukungH['periode'])) {
                    foreach ($data_pendukungH['periode'] as $p) {
                        $period[] = $p;
                    }
                }
            }

            // Store processed data to $data object
            $data->data_pendukung_sampling = json_encode(array_values($data_pendukung_h));
            $data->grand_total = $grand_total;

            $data->save();
            DB::commit();

            return response()->json([
                'message' => "Request QR untuk perusahaan $data->nama_pelanggan berhasil dibuat",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
        }
    }

    public function updateColumn(Request $request){
        DB::beginTransaction();
        try{
            $data = RequestQr::find($request->id);
            $data->update([$request->column => $request->value]);

            DB::commit();
            return response()->json(['message' => 'Data berhasil diubah']);
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()], 500);
        }
    }

    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }

    public function randomstr($str, $no)
    {
        $str = str_replace(' ', '', $str);
        $str = str_replace('\t', '', $str);
        $str = str_replace(',', '', $str);
        $result = substr(str_shuffle($str), 0, 4) . sprintf("%02d", $no);
        return $result;
    }

    public function getTemplatePenawaran()
    {
        $template = DB::table('template_penawaran')->where('is_active', true)->latest()->get();
        return response()->json($template, 200);
    }

    private function normalizeText($text) {
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim(strtoupper($text));
    }
}
