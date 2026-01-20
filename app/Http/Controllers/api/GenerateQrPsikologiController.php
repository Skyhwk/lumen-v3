<?php

namespace App\Http\Controllers\api;

use App\Models\User;
use App\Models\Usertoken;
use App\Models\Requestlog;
use App\Models\DataLapanganPsikologi;
use App\Models\Po;
use App\Models\Ftc;
use App\Models\HistoriPrinting;
use App\Models\Ftcp;
use App\Models\QrPsikologi;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\PsikologiHeader;
use App\Models\Parameter;
use App\Models\CategoryValue;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Services\GenerateQrPsikologi;
use App\Services\RenderQrPsikologi;
use Illuminate\Http\Request;
use Auth;
use Validator;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use App\Http\Controllers\defaultApi\HelpersController as Helpers;
use App\Http\Controllers\EandDcriptController as Edcript;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Mpdf\Mpdf;
use GuzzleHttp\Client;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\LabelOptions;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Color\Color;
use App\Services\SendEmail;

class GenerateQrPsikologiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = DB::table('order_detail as od')
                ->selectRaw('
                    MAX(CASE WHEN qp.type = "audiens" THEN qp.id END) as id_audiens,
                    MAX(od.no_quotation) as no_document,
                    MAX(od.nama_perusahaan) as nama_perusahaan,
                    MAX(od.periode) as periode,
                    MAX(j.kategori) as kategori,
                    MAX(j.sampler) as sampler,
                    MAX(j.tanggal) as jadwal,
                    MAX(qp.id_quotation) as id_quotation,
                    COUNT(qp.id) > 0 as is_exist,
                    MAX(CASE WHEN qp.type = "audiens" THEN qp.file END) as audiens_file,
                    MAX(CASE WHEN qp.type = "administrator" THEN qp.file END) as admin_file,
                    MAX(CASE WHEN qp.type = "audiens" THEN qp.expired END) as audiens_expired,
                    MAX(qp.created_at) as created_at')
                ->leftJoin('jadwal as j', function ($join) {
                    $join->on('j.no_quotation', '=', 'od.no_quotation')
                        ->where('j.is_active', 1)
                        ->where(function ($query) {
                            $query->whereColumn('od.periode', '=', 'j.periode') // Ketika keduanya non-NULL dan sama
                                ->orWhere(function ($q) {
                                    $q->whereNull('od.periode')
                                        ->whereNull('j.periode'); // Ketika keduanya NULL
                                });
                        });
                })
                ->leftJoin('qr_psikologi as qp', function ($join) {
                    // Menggunakan JSON_EXTRACT untuk membandingkan nilai spesifik di dalam JSON
                    $join->whereRaw('JSON_EXTRACT(qp.data, "$.no_document") = od.no_quotation')
                        ->where('qp.is_active', 1)
                        // Asumsi 'periode' juga ada di dalam JSON 'data' di tabel 'qr_psikologi'
                        ->where(function ($query) {
                            $query->whereColumn('od.periode', '=', 'qp.periode') // Ketika keduanya non-NULL dan sama
                                ->orWhere(function ($q) {
                                    $q->whereNull('od.periode')
                                        ->whereNull('qp.periode'); // Ketika keduanya NULL
                                });
                        });
                })
                ->join('order_header as oh', function ($join) {
                    $join->on('oh.id', '=', 'od.id_order_header')
                        ->where('oh.is_active', 1)
                        ->where('oh.is_revisi', 0);
                })
                ->where('od.kategori_3', '118-Psikologi')
                ->where('od.is_active', 1)
                // ->where('oh.is_active', 1)
                ->groupBy('od.periode', 'od.no_quotation', 'od.kategori_3')
                ->orderBy('created_at', 'desc');
            // PERBAIKAN 7: Gunakan variabel yang benar untuk Datatables
            // return Datatables::of($groupedData)->make(true);
            return Datatables::of($data)
                ->addColumn('print', function ($data) {
                // Now get the print count in PHP after the main query is done
                    $filename = 'QR_' . str_replace('/', '_', $data->no_document) . ($data->periode ? '_' . $data->periode : '') . '.pdf';
                    $print = DB::table('histori_printing')
                        ->where('filename', $filename)
                        ->where('status', 'done')
                        ->count();

                    return $print;
                })
                ->addColumn('filename', function ($data) {
                    $filename = 'QR_' . str_replace('/', '_', $data->no_document) . ($data->periode ? '_' . $data->periode : '') . '.pdf';

                    return $filename;
                })
                ->addColumn('email_pic_sampling', function ($data) {
                    $email_pic_order = OrderHeader::where('id', $data->id_quotation)->value('email_pic_order');

                    return $email_pic_order;
                })
                ->addColumn('sales_id', function ($data) {
                    if(!is_null($data->periode)){
                        $model = new QuotationKontrakH();
                    }else{
                        $model = new QuotationNonKontrak();
                    }

                    $quotation = $model->where('no_document', $data->no_document)->where('is_active', true)->first();

                    return $quotation->sales_id ?? null;
                })
                ->editColumn('kategori', function ($data) {
                    return json_decode($data->kategori);
                })
                ->editColumn('sampler', function ($data) {
                    $decode = json_decode($data->sampler);
                    if(is_array($decode)){
                        $total = count($decode);
                        return $total > 0 ? implode(',', $decode) : '-';
                    }else{
                        return $data->sampler;
                    }
                    return json_decode($data->sampler);
                })
                ->filterColumn('no_document', function ($query, $keyword) {
                    $query->where('od.no_quotation', 'like', '%' . $keyword . '%');
                })
                
                ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                    $query->where('od.nama_perusahaan', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('periode', function ($query, $keyword) {
                    $query->where('od.periode', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('jadwal', function ($query, $keyword) {
                    $query->where('j.tanggal', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('created_at', function ($query, $keyword) {
                    $query->where('qp.created_at', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('sampler', function ($query, $keyword) {
                    $query->where('j.sampler', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('kategori', function ($query, $keyword) {
                    $query->where('j.kategori', 'like', '%' . $keyword . '%');
                })
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    public function downloadQr(Request $request)
    {
        try {
            $data = OrderHeader::where('id', $request->id_quotation)->first();

            if (!$data) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            $quot_type = explode('/', $data->no_document)[1] == 'QT' ? 'non_kontrak' : 'kontrak';
            if ($quot_type == 'non_kontrak') {
                $filename = str_replace("/", "_", $data->no_document);
            } else {
                $filename = str_replace("/", "_", $data->no_document) . '_' . $request->periode;
            }
            $path = public_path() . "/qr_psikologi/documents/QR_" . $filename . '.pdf';
            // dd($path);
            if (file_exists($path)) {
                return response()->json(['link' => env('APP_URL') . '/public/qr_psikologi/documents/QR_' . $filename . '.pdf'], 200);
            } else {
                return response()->json(['message' => 'File not found'], 404);
            }
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function getAllOrder(Request $request)
    {
        $data = OrderHeader::where('is_active', true)
            ->where('no_document', 'LIKE', "%$request->no_document%")
            ->limit(10)
            ->get();

        return response()->json($data, 200);
    }

    public function getPenawaranDetail(Request $request)
    {
        $data = QuotationKontrakH::with(['detail:id_request_quotation_kontrak_h,periode_kontrak'])
            ->where('no_document', $request->penawaran)
            ->where('is_active', true)
            ->first();

        if (!$data) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        // dd($data);

        return response()->json(['message' => 'Data has been shown', 'data' => $data->detail], 200);
    }

    public function QrCodeGenerator(Request $request)
    {
        if (isset($request->no_document) && $request->no_document != null) {
            DB::beginTransaction();
            try {
                $data = OrderHeader::where('no_document', $request->no_document)
                    ->where('is_active', true)
                    ->first();
                $data->tanggal_sampling = OrderDetail::where('id_order_header', $data->id)->first()->tgl_sampling;
                if (isset($request->periode) && $request->periode != null) {
                    $data->periode_kontrak = $request->periode;
                }
                if (!$data) {
                    return response()->json([
                        'message' => 'Penawaran Tidak Ditemukan'
                    ], 404);
                }
                // dd($data);
                // Generate QR untuk tipe 'audiens'
                $dataAudiens = $data->replicate();
                $dataAudiens->id = $data->id;
                $dataAudiens->type = 'audiens';
                $genAudiens = (new GenerateQrPsikologi())->insert($dataAudiens, $this->karyawan);

                // Generate QR untuk tipe 'administrator'
                $dataAdmin = $data->replicate();
                $dataAdmin->id = $data->id;
                $dataAdmin->type = 'administrator';
                $genAdmin = (new GenerateQrPsikologi())->insert($dataAdmin, $this->karyawan);

                // Render PDF untuk kedua tipe
                RenderQrPsikologi::render($data, $genAudiens, $genAdmin);

                DB::commit();

                return response()->json([
                    'message' => 'Generate QR Psikologi untuk ' . $data->no_document . ' berhasil.'
                ], 200);
            } catch (\Exception $th) {
                DB::rollBack();
                return response()->json([
                    'message' => $th->getMessage(),
                    'file' => $th->getFile(),
                    'line' => $th->getLine()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'Pastikan Sudah memilih Penawaran'
            ], 401);
        }
    }

    public function getDetails(Request $request)
    {
        if (isset($request->token) && $request->token != null) {
            try {
                $data = QrPsikologi::where('token', $request->token)->first();
                $orderDetail = OrderDetail::select(['no_sampel', 'keterangan_1', 'tanggal_sampling'])->where('id_order_header', $data->id_quotation)->where('periode', $data->periode)->where('is_active', 1)->whereJsonContains('parameter', '318;Psikologi')->whereNull('tanggal_terima')->get();
                $psikologi = DataLapanganPsikologi::whereIn('no_sampel', $orderDetail->pluck('no_sampel'))->selectRaw('nama_pekerja')->get();
                // Filter User List
                $orderDetail = $orderDetail->filter(function ($item) use ($psikologi) {
                    return !$psikologi->contains('nama_pekerja', explode('.', $item->keterangan_1)[0]);
                });
                $data->order_detail = $orderDetail->toArray();
                if (isset($data->expired) && $data->expired < Carbon::now()->format('Y-m-d H:i:s')) {
                    // if($data->is_finished == 1){
                    return response()->json([
                        'message' => "We're sorry, but this link is no longer available. It may have expired or been removed. Please visit our homepage or contact support for further assistance."
                    ], 401);
                } else {
                    return response()->json([
                        'message' => 'Token Ditemukan.',
                        'data' => [
                            'data' => $data->data,
                            'expired' => $data->expired,
                            'order_detail' => $data->order_detail
                        ],
                        'token' => $request->token
                    ], 200);
                }
            } catch (\Exception $th) {
                return response()->json([
                    'message' => $th->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'Token Tidak Ditemukan'
            ], 401);
        }
    }

    public function updateColumn(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = QrPsikologi::where('id', $request->id)->where('is_active', 1)->first();
            $data->{$request->column} = $request->value;
            // $data->updated_by = $this->karyawan;
            // $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // dd('berhasil');
            DB::commit();
            return response()->json([
                'message' => 'Data Berhasil Diubah'
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ], 401);
        }
    }

    public function submit(Request $request)
    {
        DB::beginTransaction();
        try {
            $pertanyaan = $request->pertanyaan;
            $jawaban = $request->jawaban;
            $dataPertanyaanJawaban = [];
            $kesimpulan = [];
            $value = [[], [], [], [], [], []];

            $tp = 0;
            $kp = 0;
            $bbkuan = 0;
            $bbkual = 0;
            $pk = 0;
            $tjo = 0;
            
            $existingData = DataLapanganPsikologi::where('no_sampel', $request->no_sampel)->first();
            
            if(isset($existingData)){
                $existingDate = Carbon::parse($existingData->created_at)->format('Y-m-d H:i:s');
                return response()->json([
                    'message' => "Data dengan nomor sample $request->no_sampel sudah diisi pada tanggal  $existingDate",
                ], 403);
            }

            for ($i = 0; $i < count($pertanyaan); $i++) {
                $dataPertanyaanJawaban[$i]['pertanyaan'] = $pertanyaan[$i];
                $dataPertanyaanJawaban[$i]['jawaban'] = $jawaban[$i];
                $nilai = explode('-', str_replace(' ', '', $jawaban[$i]));
                if ($i == 0 || $i == 6 || $i == 12 || $i == 18 || $i == 24) { // 1,7,13,19,25
                    $tp += (int) $nilai[0];
                    array_push($value[0], $nilai[0]);
                } else if ($i == 1 || $i == 7 || $i == 13 || $i == 19 || $i == 25) // 2,8,14,20,26
                {
                    $kp += (int) $nilai[0];
                    array_push($value[1], $nilai[0]);
                } else if ($i == 2 || $i == 8 || $i == 14 || $i == 20 || $i == 26) // 3,9,15,21,27
                {
                    $bbkuan += (int) $nilai[0];
                    array_push($value[2], $nilai[0]);
                } else if ($i == 3 || $i == 9 || $i == 15 || $i == 21 || $i == 27) // 4,10,16,22,28
                {
                    $bbkual += (int) $nilai[0];
                    array_push($value[3], $nilai[0]);
                } else if ($i == 4 || $i == 10 || $i == 16 || $i == 22 || $i == 28) // 5,11,17,23,29
                {
                    $pk += (int) $nilai[0];
                    array_push($value[4], $nilai[0]);
                } else if ($i == 5 || $i == 11 || $i == 17 || $i == 23 || $i == 29) // 6,12,18,24,30
                {
                    $tjo += (int) $nilai[0];
                    array_push($value[5], $nilai[0]);
                }
            }

            // SKOR tiap Kategori ditarik Kesimpulan dari Ketentuan Berikut;
            // 1. Skor Kurang atau sama dengan 9 = RINGAN
            // 2. Skor 10-24 = SEDANG
            // 3. Skor besar dari 24 = BERAT
            $kesimpulan['tp']['kesimpulan'] = $tp <= 9 ? 'RINGAN' : ($tp >= 10 && $tp <= 24 ? 'SEDANG' : 'BERAT');
            $kesimpulan['tp']['nilai'] = $tp;
            $kesimpulan['tp']['records'] = $value[0];
            $kesimpulan['kp']['kesimpulan'] = $kp <= 9 ? 'RINGAN' : ($kp >= 10 && $kp <= 24 ? 'SEDANG' : 'BERAT');
            $kesimpulan['kp']['nilai'] = $kp;
            $kesimpulan['kp']['records'] = $value[1];
            $kesimpulan['bbkuan']['kesimpulan'] = $bbkuan <= 9 ? 'RINGAN' : ($bbkuan >= 10 && $bbkuan <= 24 ? 'SEDANG' : 'BERAT');
            $kesimpulan['bbkuan']['nilai'] = $bbkuan;
            $kesimpulan['bbkuan']['records'] = $value[2];
            $kesimpulan['bbkual']['kesimpulan'] = $bbkual <= 9 ? 'RINGAN' : ($bbkual >= 10 && $bbkual <= 24 ? 'SEDANG' : 'BERAT');
            $kesimpulan['bbkual']['nilai'] = $bbkual;
            $kesimpulan['bbkual']['records'] = $value[3];
            $kesimpulan['pk']['kesimpulan'] = $pk <= 9 ? 'RINGAN' : ($pk >= 10 && $pk <= 24 ? 'SEDANG' : 'BERAT');
            $kesimpulan['pk']['nilai'] = $pk;
            $kesimpulan['pk']['records'] = $value[4];
            $kesimpulan['tjo']['kesimpulan'] = $tjo <= 9 ? 'RINGAN' : ($tjo >= 10 && $tjo <= 24 ? 'SEDANG' : 'BERAT');
            $kesimpulan['tjo']['nilai'] = $tjo;
            $kesimpulan['tjo']['records'] = $value[5];

            // $pertanyaanJawabanJson = json_encode($dataPertanyaanJawaban);

            $hasil = (object) [
                'kesimpulan' => $kesimpulan,
                'pertanyaan_jawaban' => $dataPertanyaanJawaban
            ];
            // dd($hasil);
            $data = new DataLapanganPsikologi();
            $data->no_sampel = $request->no_sampel;
            $data->no_order = isset($request->no_order) ? $request->no_order : null;
            $data->no_quotation = isset($request->no_penawaran) ? $request->no_penawaran : null;
            $data->nama_perusahaan = isset($request->nama_perusahaan) ? $request->nama_perusahaan : null;
            $data->nama_pekerja = $request->nama_karyawan;
            $data->divisi = $request->divisi;
            $data->periode = $request->periode ?? null;
            $data->usia = $request->usia;
            $data->jenis_kelamin = $request->jenis_kelamin;
            $data->lama_kerja = $request->lama_bekerja;
            $data->persetujuan = $request->persetujuan;
            $data->hasil = json_encode($hasil);
            $data->permission = 1;
            $data->is_approve = 1;
            $data->approved_by = 'SYSTEM';
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->created_by = 'SYSTEM';
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            $periode = !empty($request->periode) ? $request->periode : null;
            $orderDetail = OrderDetail::where('no_sampel', $request->no_sampel)->where('periode', $periode)->first();
            $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d');
            $orderDetail->save();

            //Update Expired QR
            $qr = QrPsikologi::where('id_quotation', $orderDetail->id_order_header)
                ->where('is_active', true)
                ->update([
                    'expired' => Carbon::now()->addDays(10)->format('Y-m-d H:i:s')
                ]);
            
            if (!$data) {
                return response()->json(['message' => 'Data Lapangan tidak ditemukan.'], 403);
            }
            $header = PsikologiHeader::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();

            if (!$header) {
                $orderParameter = $orderDetail->parameter;
                $clean = str_replace(['[', ']'], '', $orderParameter); 
                $parts = explode(';', $clean);

                $header = new PsikologiHeader;
                $header->no_sampel = $data->no_sampel;
                $header->id_parameter = isset($parts[0]) ? intval(trim($parts[0], "\" ")) : null;
                $header->parameter = rtrim(trim($parts[1]), "\"") ?? null;
                $header->tanggal_terima = $orderDetail->tanggal_terima;
                $header->is_approve = true;
                $header->approved_by = 'SYTEM';
                $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->created_by = 'SYTEM';
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->save();

            }else{
                $header->tanggal_terima = $orderDetail->tanggal_terima;
                $header->is_reject = false;
                $header->is_approve = true;
                $header->approved_by = 'SYTEM';
                $header->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->created_by = 'SYTEM';
                $header->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Your Psychology Test Record Has Been Successfully Saved.',
                'status' => 200
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 403);
        }
    }

    public function cmbvalqr(Request $request)
    {
        if ($this->rjsn != 1) {
            Helpers::saveToLogRequest($this->pathinfo, $this->globaldate, $this->param, $this->useragen, $this->resultx, $this->ip);
            return response()->json($this->rjsn, 401);
        } else {
            echo "<select><option value=''>--Pilih QR--</option>";

            $data = Qr::where('active', 0)->where('status', 0)->get();


            foreach ($data as $q) {

                $id = $q->id;
                $nm = $q->kode;
                if ($id == $request->value) {
                    echo "<option value='$id' selected> $nm </option>";
                } else {
                    echo "<option value='$id'> $nm </option>";
                }
            }
            echo "</select>";
        }
    }

    public function print(Request $request)
    {

        $data = MasterQr::whereIn('file', explode(',', $request->qr))->get();
        foreach ($data as $key => $val) {
            $num = ($val->print + 1);
            $val->print = $num;
            $val->save();
        }

        return response()->json([
            'message' => 'Update success'
        ], 201);
    }
    public function printQrPDF(Request $request)
    {
        try {
            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => 'A3',
                'margin_left' => 15,
                'margin_right' => 5,
                'margin_top' => 15,
                'margin_header' => 0,
                'margin_bottom' => 0,
                'margin_footer' => 0,
            );

            $barcodeImgNames = $request->input('imgData');
            if (!is_array($barcodeImgNames)) {
                throw new Exception('Invalid input data');
            }
            $mpdf = new Mpdf($mpdfConfig);

            $mpdf->WriteHTML('<!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            .box {
                                padding: 2px;
                                border: 1px solid black;
                                text-align: center;
                                position: relative;
                            }
                            .text-header {
                                font-size: 8px;
                            }
                            .sticker {
                                width: 12%;
                                height: auto;
                            }
                            .number {
                                position: absolute;
                                z-index: 99;
                                bottom: 11%;
                                right: 6%;
                                border: 1px solid;
                                border-radius: 150px;
                                padding: 3px;
                                font-size: 7px;
                            }
                        </style>
                    </head>
                    <body>
                        <table border="0" cellspacing="15" cellpadding="0" width="0">
                            <tr>');

            $count = 0;
            $angka = 0;
            foreach ($barcodeImgNames as $imgName) {
                $count++;
                $angka++;
                $barcodeImgPath = public_path('barcode/emisi/' . $imgName);
                // dd($barcodeImgPath);
                $mpdf->WriteHTML('<td class="box">
                            <div>
                                <span class="text-header">PT INTI SURYA LABORATORIUM</span>
                                <br>
                                <span class="text-header">Uji Emisi Kendaraan</span>
                                <br>
                                <img class="sticker" src="' . $barcodeImgPath . '">
                                <span class="number" style="border-radius: 30px;">' . $angka . '</span>
                            </div>
                        </td>');

                MasterQr::where('file', $imgName)->increment('print');

                if ($count % 6 == 0) {
                    $mpdf->WriteHTML('</tr><tr>');
                }
            }

            $mpdf->WriteHTML('</tr></table>
                </body></html>');
            $dir = public_path('dokumen/qr_emisi/');
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $filename = str_replace('.', '_', microtime(true)) . '.pdf';

            $mpdf->Output($dir . '/' . $filename, \Mpdf\Output\Destination::FILE);

            return response()->json([
                'message' => 'Success',
                'data' => env('APP_URL') . '/public/dokumen/qr_emisi/' . $filename
            ]);
        } catch (Exception $e) {
            dd($e);
        }
    }

    public function getQuotation(Request $request)
    {
        try {
            $model = str_contains($request->no_document, 'QTC') ? QuotationKontrakH::class : QuotationNonKontrak::class;

            $quotation = $model::with('qr_psikologi')->where([
                'no_document' => $request->no_document,
                'is_active' => true,
            ])
                ->whereNotIn('flag_status', ['rejected', 'void'])
                // ->latest()
                ->first();
            $sales = DB::table('master_karyawan')->where('id', $quotation->sales_id)->first();
            $getKaryawan = $request->attributes->get('user');

            $getToken = DB::table('qr_psikologi')->whereRaw("JSON_EXTRACT(data, '$.no_document') = ?", [$request->no_document]);
            if($request->periode){
                $getToken = $getToken->where('periode', $request->periode);
            }
            $getToken = $getToken->get();
            
            return response()->json([
                'quotation' =>$quotation,
                'sales' => $sales->nama_lengkap,
                'email_sales' => $sales->email,
                'no_sales' => $sales->no_telpon,
                'nama_apling' => $this->karyawan,
                'token' => $getToken,
                'jabatan_apling' => $getKaryawan->karyawan->jabatan
            ], 200);
        } catch (\Exception $th) {
            dd($th);
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function sendMail(Request $request)
    {
        try {
            if (is_array($request->bcc)) {
                $bcc = $request->bcc;
            } else {
                if(empty($request->bcc)){
                    $bcc = $request->bcc;
                } else if (str_contains($request->bcc, ',')) {
                    $bcc = explode(',', $request->bcc);
                } else {
                    $bcc = [$request->bcc];
                }
            }

            if (is_array($request->cc)) {
                $cc = $request->cc;
            }else{
                if(empty($request->cc)){
                    $cc = $request->cc;
                } else if (str_contains($request->cc, ',')) {
                    $cc = explode(',', $request->cc);
                } else {
                    $cc = [$request->cc];
                }
            }

            $validAttachments = [];
            foreach ($request->attachments as $item) {
                $filePath = base_path('public/qr_psikologi/' . $item['name']);
                if (file_exists($filePath)) {
                    $validAttachments[] = $filePath;
                } else {
                    error_log("Attachment file not found: " . $item['name']);
                }
            }

            $email = SendEmail::where('to', $request->to)
                ->where('subject', $request->subject)
                ->where('body', $request->content)
                ->where('cc', $cc)
                ->where('bcc', $bcc)
                ->where('attachment', $validAttachments)
                ->where('karyawan', $this->karyawan)
                ->fromAdmsales()
                ->send();

            if ($email)
                return response()->json(['message' => 'Email berhasil dikirim'], 200);

            return response()->json(['message' => 'Email gagal dikirim'], 400);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage(), 'line' => $th->getLine()], 500);
        }
    }
}