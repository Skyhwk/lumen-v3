<?php

namespace App\Http\Controllers\mobile;


use App\Models\AduanLapangan;

// MASTER DATA
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKategori;
use App\Models\MasterKaryawan;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

// service
use App\Services\SaveFileServices;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;


class AduanController extends Controller{
    public function aduan(Request $request){
        // dd($request->header('token'));
        $fileName = null;
        DB::beginTransaction();
        try {
            $data = new AduanLapangan;
            $waktu = Carbon::now()->format('Y-m-d H:i:s');
            $data->type_aduan = $request->type_aduan;

            if($request->type_aduan == 'Waktu Sampling'){
                $nama_customer = 'Customer Tidak Ditemukan.!';
                $cek_order = OrderDetail::where('no_order', strtoupper($request->no_order))->first();
                if($cek_order) $nama_customer = $cek_order->nama_perusahaan;

                $data->no_order = strtoupper($request->no_order);
                $data->tambahan = $request->waktu;
                
                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "No Order : $request->no_order \n";
                $message .= "Nama Customer : $nama_customer \n";
                $message .= "Status : $request->waktu \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";

            } else if ($request->type_aduan == 'Peralatan & Perlengkapan') {
                $data->nama_alat = $request->nama_alat;
                $data->koding = $request->koding;
                if($request->foto1 != ''){
                    $fileName = Self::saveImage($request->foto1, $this->user_id, 1);
                    $data->foto_1 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }
                if($request->foto2 != ''){$data->foto_2 = Self::saveImage($request->foto2, $this->user_id, 2);}

                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "Nama Alat : $request->nama_alat \n";
                $message .= "Koding : $request->koding \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";

            } else if ($request->type_aduan == 'Kendaraan') {
                $data->koding = $request->koding;
                if($request->foto1 != ''){
                    $fileName = Self::saveImage($request->foto1, $this->user_id, 1);
                    $data->foto_1 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }
                if($request->foto2 != ''){$data->foto_2 = Self::saveImage($request->foto2, $this->user_id, 2);}
                
                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "Kode Mobil / Plat No : $request->koding \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";

            } else if ($request->type_aduan == 'K3') {
                $nama_customer = 'Customer Tidak Ditemukan.!';
                $cek_order = OrderDetail::where('no_order', strtoupper($request->no_order))->first();
                if($cek_order) $nama_customer = $cek_order->nama_perusahaan;

                $data->no_order = strtoupper($request->no_order);
                if($request->foto1 != ''){
                    $fileName = Self::saveImage($request->foto1, $this->user_id, 1);
                    $data->foto_1 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }
                if($request->foto2 != ''){$data->foto_2 = Self::saveImage($request->foto2, $this->user_id, 2);}
                
                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "No Order : $request->no_order \n";
                $message .= "Nama Customer : $nama_customer \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";

            } else if ($request->type_aduan == 'Sales') {
                $nama_customer = 'Customer Tidak Ditemukan.!';
                $cek_order = OrderDetail::where('no_order', strtoupper($request->no_order))->first();
                if($cek_order) $nama_customer = $cek_order->nama_perusahaan;

                $data->no_order = strtoupper($request->no_order);

                if($request->foto1 != ''){
                    $fileName = Self::saveImage($request->foto1, $this->user_id, 1);
                    $data->foto_1 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }
                
                if($request->foto2 != ''){$data->foto_2 = Self::saveImage($request->foto2, $this->user_id, 2);}

                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "No Order : $request->no_order \n";
                $message .= "Nama Customer : $nama_customer \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";

            } else if ($request->type_aduan == 'Aduan Umum') {
                if($request->foto1 != ''){
                    $fileName = Self::saveImage($request->foto1, $this->user_id, 1);
                    $data->foto_1 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }

                if($request->foto2 != ''){
                    $fileName = Self::saveImage($request->foto2, $this->user_id, 2);
                    $data->foto_2 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }
                
                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";

            } else if ($request->type_aduan == 'BAS/CS') {
                $nama_customer = 'Customer Tidak Ditemukan.!';
                $cek_order = OrderDetail::where('no_order', strtoupper($request->no_order))->first();
                if($cek_order) $nama_customer = $cek_order->nama_perusahaan;

                $data->no_order = strtoupper($request->no_order);
                $fileName = null;
                if($request->foto1 != ''){
                    $fileName = Self::saveImage($request->foto1, $this->user_id, 1);
                    $data->foto_1 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }
                if($request->foto2 != ''){
                    $fileName = Self::saveImage($request->foto2, $this->user_id, 2);
                    $data->foto_2 = $fileName;
                    $fileName = public_path("dokumentasi/aduanSampler/$fileName");
                }

                $message = "Type Aduan : $request->type_aduan \n";
                $message .= "Waktu : $waktu \n";
                $message .= "koordinat : $request->koordinat \n";
                $message .= "No Order : $request->no_order \n";
                $message .= "Nama Customer : $nama_customer \n";
                $message .= "Sampler : $this->karyawan \n";
                $message .= "Keterangan : $request->keterangan \n";
            }
            $data->keterangan = $request->keterangan;
            $data->kordinat = $request->koordinat;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->created_by = $this->karyawan;
            $data->save();

            DB::commit();

            if($request->type_aduan == 'Waktu Sampling'){
                // Helpers::sendToNew('-1002229600148', $message, null, $fileName);
                // Helpers::sendToNew('-1002197513895', $message, null, $fileName); //channel laporan waktu sampling
                $member = ['-1002229600148', '-1002197513895'];

                $telegram = new SendTelegram();
                $telegram = SendTelegram::text($message)
                ->to($member)->send();
            }

            // if($request->type_aduan == 'BAS/CS'){
            //     // Helpers::sendToNew('-1002199994008', $message, null, $fileName);
            //     Helpers::sendToNew('-1002183256259', $message, null, $fileName); //chanel bas / cs
            // }

            if($request->type_aduan == 'Aduan Umum'){
                // Helpers::sendTelegramAtasan($message, 13, null, $fileName); //Tele Bu Suci
                // Helpers::sendToNew('-1002245551834', $message, null, $fileName); //chanel aduan umum 
                $member = ['-1002245551834', '805208290'];

                $telegram = new SendTelegram();
                $telegram = SendTelegram::text($message)
                ->to($member)->send();
            }

            if($request->type_aduan == 'Peralatan & Perlengkapan'){
                // Helpers::sendTelegramAtasan($message, 13, null, $fileName); //Tele Bu Suci
                // Helpers::sendToNew('-1002249182981', $message, null, $fileName); //channel aduan peralatan & perlengkapan

                $member = ['-1002249182981', '805208290'];

                $telegram = new SendTelegram();
                $telegram = SendTelegram::text($message)
                ->to($member)->send();
                
            }

            if($request->type_aduan == 'Kendaraan'){
                // Helpers::sendTelegramAtasan($message, 13, null, $fileName); //Tele Bu Suci
                // Helpers::sendToNew('-1002184355599', $message, null, $fileName); //chanel aduan kendaraan
                $member = ['-1002184355599', '805208290'];

                $telegram = new SendTelegram();
                $telegram = SendTelegram::text($message)
                ->to($member)->send();
            }

            if($request->type_aduan == 'K3'){
                // Helpers::sendToNew('-1002167796966', $message, null, $fileName); //channel aduan k3
                // Helpers::sendTelegramAtasan($message, 13, null, $fileName); //Tele Bu Suci

                $member = ['-1002167796966', '805208290'];

                $telegram = new SendTelegram();
                $telegram = SendTelegram::text($message)
                ->to($member)->send();
            }

            // if($request->type_aduan == 'Sales'){
            //     Helpers::sendTelegramAtasan($message, 13, null, $fileName); //Tele Bu Suci
            // }

            // // Send Tele Pribadi
            // if($request->type_aduan == 'Sales' || $request->type_aduan == 'Waktu Sampling'){
            //     // Helpers::sendTelegramAtasan($message, 19, null, $fileName); //Tele Bu Faidhah
            //     Helpers::sendToNew('1463248619', $message, null, $fileName); //Tele Lani

            //     if($request->has('no_order') && $request->no_order != ''){
            //         $db = '20' . substr(strtoupper($request->no_order), 6, 2);
            //         $cek_order_header = OrderHeader::where('no_order', strtoupper($request->no_order))->first();
            //         if($cek_order_header != null){
            //             if(explode('/',$cek_order_header->no_document)[1] == 'QT') {
            //                 $cek_sales = QuotationNonKontrak::where('no_document', $cek_order_header->no_document)->first();
            //             } else {
            //                 $cek_sales = QuotationKontrakH::where('no_document', $cek_order_header->no_document)->first();
            //             }
                            
            //             if($cek_sales != null){
            //                 Helpers::sendTelegramAtasan($message, $cek_sales->sales_id, null, $fileName); //Tele Sales
            //             }
            //         }
            //     }
            // }

            if($request->type_aduan == 'BAS/CS'){
                // Helpers::sendToNew('787241230', $message, null, $fileName); //Tele Meisya
                // Helpers::sendToNew('6480425773', $message, null, $fileName); //Tele Noerita
                // Helpers::sendToNew('1184254359', $message, null, $fileName); //Tele Nisa Alkhaira
                // Helpers::sendToNew('1463248619', $message, null, $fileName); //Tele Lani
                
                if($request->has('no_order') && $request->no_order != ''){
                    $cek_order_header = OrderHeader::where('no_order', strtoupper($request->no_order))->first();
                    if($cek_order_header != null){
                        if(explode('/',$cek_order_header->no_document)[1] == 'QT') {
                            $cek_sales = QuotationNonKontrak::where('no_document', $cek_order_header->no_document)->first();
                        } else {
                            $cek_sales = QuotationKontrakH::where('no_document', $cek_order_header->no_document)->first();
                        }
                        
                        if($cek_sales != null){
                            // $atasan = GetAtasan::where('id', 508)->get();
                            $atasan = GetAtasan::where('id', $cek_sales->sales_id)->get();
                            $atasan = $atasan->pluck('pin_user')->toArray();
                            // dd($atasan);
                            // dd($atasan);
                            // Helpers::sendTelegramAtasan($message, $cek_sales->sales_id, null, $fileName); //Tele Sales
                            $telegram = new SendTelegram();
                            $telegram = SendTelegram::text($message)
                            ->to($atasan)->send();
                        }
                    }
                }
            }

            return response()->json([
                'message' => 'Data Berhasil Disimpan.!',
                'status' => '200'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage()
            ],401);
        }
    }


         public function saveImage($foto = '', $type = '', $user = '')
    {
        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        $safeName = DATE('YmdHis') . '_' . $type . '_' . $user . '.jpg';
        $path = 'dokumentasi/aduanSampler';
        $service = new SaveFileServices();
        $service->saveFile($path ,  $safeName, $file);
        return $safeName;
    }
}