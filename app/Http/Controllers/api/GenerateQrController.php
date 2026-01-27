<?php

namespace App\Http\Controllers\api;

use App\Models\User;
use App\Models\Usertoken;
use App\Models\Requestlog;
use App\Models\Kendaraan;
use App\Models\Po;
use App\Models\Ftc;
use App\Models\Ftcp;
use App\Models\MasterQr;
use App\Models\CategorySample;
use App\Models\Parameter;
use App\Models\CategoryValue;
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
use Mpdf;
use GuzzleHttp\Client;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\LabelOptions;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Color\Color;
class GenerateQrController extends Controller
{
    public function indexQrApi(Request $request)
    {
        $data = MasterQr::select('master_qr.id as id', 'kode', 'master_qr.created_at', 'master_qr.status', 'master_qr.is_active', 'file', 'master_kendaraan.merk_kendaraan', 'master_kendaraan.jenis_bbm', 'master_kendaraan.tahun_pembuatan', 'order_detail.nama_perusahaan', 'master_qr.print')
            ->leftJoin('data_lapangan_emisi_order', function ($join) {
                $join->on("master_qr.id", "=", "data_lapangan_emisi_order.id_qr")
                    ->on("data_lapangan_emisi_order.is_active", "=", DB::raw("0"));
            })
            ->leftJoin('master_kendaraan', 'data_lapangan_emisi_order.id_kendaraan', '=', 'master_kendaraan.id')
            ->leftJoin('order_detail', 'data_lapangan_emisi_order.no_sampel', '=', 'order_detail.no_sampel')
            ->where('master_qr.is_active', $request->active)
            ->orderBy('master_qr.created_at', 'desc');

        return Datatables::of($data)
            ->filterColumn('kode', function ($query, $keyword) {
                $query->where('kode', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('master_qr.created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('status', function ($query, $keyword) {
                $keyword = strtolower($keyword);
                if (strpos($keyword, 'un') !== false) {
                    $query->where('master_qr.status', '!=', 0);
                } elseif (strpos($keyword, 'av') !== false) {
                    $query->where('master_qr.status', 0);
                }
            })
            ->filterColumn('print', function ($query, $keyword) {
                $query->where('master_qr.print', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function QrCodeGenerator(Request $request)
    {
        if (isset($request->num) && $request->num != null && $request->num < 100) {
            $value = array();
            $data = array();
            $img = array();
            $gen = (rand(1, 100000));
            $now = sprintf("%06d", $gen);
            $prefix = 'https://www.intilab.com/validation/';
            $tempdir = public_path() . '/barcode/emisi/';
            for ($i = 0; $i < $request->num; $i++) {
                $cek = MasterQr::latest('id')->first();
                if (is_null($cek)) {
                    $qrCode = sprintf("%02d", $i + 1);
                } else {
                    $number = substr($cek->kode, -2);
                    $m = substr($cek->kode, 3, 6);
                    if ($m != $now) {
                        $qrCode = sprintf("%02d", 1);
                    } else {
                        $qrCode = sprintf("%02d", $number + 1);
                    }
                }
                $kode = 'ISL' . $now . $qrCode;
                $data[] = $kode;
                $value = [
                    'kode' => $kode,
                    'file' => $kode . '.png',
                    'created_by' => $this->karyawan,
                    'created_at' => Carbon::now(),
                ];
                MasterQr::query()->insert($value);
                $isi_teks = $kode;
                $namafile = $isi_teks . ".png";
                $img[$i] = $namafile;
                // Buat QR Code
                $qrCode = QrCode::create($prefix . $isi_teks)
                    ->setEncoding(new Encoding('UTF-8'))
                    ->setSize(250)
                    ->setMargin(15)
                    ->setForegroundColor(new Color(0, 0, 0)) // Warna hitam
                    ->setBackgroundColor(new Color(255, 255, 255)); // Warna putih

                // Buat label dengan font size yang diatur langsung
                $label = Label::create($isi_teks);

                // Tambahkan logo (jika ada)
                $logoPath = $tempdir . 'logo-final.png';
                if (file_exists($logoPath)) {
                    $logo = Logo::create($logoPath)->setResizeToWidth(50);
                } else {
                    $logo = null;
                }

                // Simpan sebagai file PNG
                $writer = new PngWriter();
                $result = $writer->write($qrCode, $logo, $label);
                $result->saveToFile($tempdir . $namafile);
            }
            return response()->json([
                'data' => $data,
                'img' => json_encode($img),
                'message' => 'Generate Qr Success.!'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Pastikan untuk pilih jumlah'
            ], 401);
        }
    }

    public function getDetailKendaraanApi(Request $request)
    {
        if ($this->rjsn != 1) {
            Helpers::saveToLogRequest($this->pathinfo, $this->globaldate, $this->param, $this->useragen, $this->resultx, $this->ip);
            return response()->json($this->rjsn, 401);
        } else {
            if (isset($request->id) && $request->id != null) {
                $data = Qr::join('kendaraan', 'qr.id_kendaraan', '=', 'kendaraan.id')
                    ->where('qr.id', $request->id)
                    ->first();
                return response()->json([
                    'merk' => $data->merk_kendaraan,
                    'bbm' => $data->jenis_bbm,
                    'plat' => $data->nomor_plat,
                    'no_mesin' => $data->no_mesin,
                    'tahun' => $data->tahun_pembuatan,
                    'transmisi' => $data->transmisi,
                    'cc' => $data->cc,
                    'message' => 'Data has ben show'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Data not found.!'
                ], 401);
            }
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

}