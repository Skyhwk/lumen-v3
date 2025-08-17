<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\{CustomersAccount,
     PicPelanggan,
     MasterPelanggan,
     QuotationKontrakH,
     QuotationNonKontrak};
use App\Models\customer\Users;

class PortalCustomerSideController extends Controller
{
    public function menu (Request $request)
    {
        try {
            //code...
            return response()->json('ssss', 200);
            $idPelanggan=json_decode($request->id_pelanggan);
            $quotationH =QuotationKontrakH::with('order')
            ->selectRaw('nama_perusahaan, pelanggan_ID, flag_status ,wilayah ,GROUP_CONCAT(no_document) as documents')
            ->whereIn('pelanggan_ID', $idPelanggan)
            ->groupBy('nama_perusahaan', 'pelanggan_ID','flag_status','wilayah')
            ->get();

            $quotation=QuotationNonKontrak::with('order')
            ->selectRaw('nama_perusahaan, pelanggan_ID, flag_status ,wilayah ,GROUP_CONCAT(no_document) as documents')
            ->whereIn('pelanggan_ID', $idPelanggan)
            ->groupBy('nama_perusahaan', 'pelanggan_ID','flag_status','wilayah')
            ->get();
            $result = [];
            if(!$quotationH->isEmpty()){
                foreach ($quotationH as $item) {

                    $namaPerusahaan = $item->nama_perusahaan;

                    if (!isset($result[$namaPerusahaan])) {
                        $result[$namaPerusahaan] = [
                            'qt' => [],
                            'id_pelanggan' => [],
                            'wilayah' => [],
                        ];
                    }

                    // Mengubah hasil documents menjadi array
                    $documents = explode(',', $item->documents);
                    $subData = [];

                    foreach ($documents as $doc) {
                        $subData[$doc] = [
                            'id' => 'contoh_id',  // Kamu bisa ganti dengan value yang sesuai
                            'file' => 'contoh_file',  // Kamu bisa ganti dengan value yang sesuai
                            'flag_status' => $item->flag_status  // Kamu bisa ganti dengan value yang sesuai
                        ];
                    }

                    $result[$namaPerusahaan]['qt'] = array_merge($result[$namaPerusahaan]['qt'], $subData);
                    if (!in_array($item->pelanggan_ID, $result[$namaPerusahaan]['id_pelanggan'])) {
                        $result[$namaPerusahaan]['id_pelanggan'][] = $item->pelanggan_ID;
                    }

                    if (!in_array($item->wilayah, $result[$namaPerusahaan]['wilayah'])) {
                        $result[$namaPerusahaan]['wilayah'][] = $item->wilayah;
                    }
                }
            }
            if(!$quotation->isEmpty()){
                foreach ($quotation as $item) {
                    if($item->order != null){
                        dd('ada kita');
                    }
                    $namaPerusahaan = $item->nama_perusahaan;

                    if (!isset($result[$namaPerusahaan])) {
                        $result[$namaPerusahaan] = [
                            'qt' => [],
                            'id_pelanggan' => [],
                            'wilayah' => [],
                        ];
                    }

                    // Mengubah hasil documents menjadi array
                    $documents = explode(',', $item->documents);
                    $subData = [];

                    foreach ($documents as $doc) {
                        $subData[$doc] = [
                            'id' => 'contoh_id',  // Kamu bisa ganti dengan value yang sesuai
                            'file' => 'contoh_file',  // Kamu bisa ganti dengan value yang sesuai
                            'flag_status' => $item->flag_status  // Kamu bisa ganti dengan value yang sesuai
                        ];
                    }

                    $result[$namaPerusahaan]['qt'] = array_merge($result[$namaPerusahaan]['qt'], $subData);
                    if (!in_array($item->pelanggan_ID, $result[$namaPerusahaan]['id_pelanggan'])) {
                        $result[$namaPerusahaan]['id_pelanggan'][] = $item->pelanggan_ID;
                    }

                    if (!in_array($item->wilayah, $result[$namaPerusahaan]['wilayah'])) {
                        $result[$namaPerusahaan]['wilayah'][] = $item->wilayah;
                    }
                }
            }
            return response()->json(["data"=>$result,"status"=>'success'], 200);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'file' => $ex->getFile(),
            ], 500);
        }

        // $quotationKontrak = QuotationKontrakH::where('email_pic_order', $request->email)->where('is_active', true)->first();
        // $quotationNonKontrak = QuotationNonKontrak::where('email_pic_order', $request->email)->where('is_active', true)->first();

        // if ($masterPelanggan) {
        //     // Email ditemukan di tabel MasterPelanggan
        //     $nama_perusahaan = $masterPelanggan->nama_pelanggan;
        // } else if ($quotationKontrak) {
        //     // Email ditemukan di tabel QuotationKontrakH
        //     $nama_perusahaan = $quotationKontrak->nama_perusahaan;
        // } else if ($quotationNonKontrak) {
        //     // Email ditemukan di tabel QuotationNonKontrak
        //     $nama_perusahaan = $quotationNonKontrak->nama_perusahaan;
        // } else {
        //     // Email tidak ditemukan di semua tabel
        //     return response()->json([
        //         'message' => 'Silahkan menghubungi pihak Sales',
        //         'status' => '404',
        //     ], 200);
        // }

        // $account = CustomersAccount::where('email', $request->email)->first();
        // if ($account) {
        //     return response()->json([
        //         'message' => 'Email sudah terdaftar',
        //         'status' => '200'
        //     ], 200);
        // }
    }

    public function testRelasi (Request $request)
    {
        $data
    }
}
