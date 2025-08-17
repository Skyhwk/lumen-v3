<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\{
    CustomersAccount,
     PicPelanggan,
     MasterPelanggan,
     QuotationKontrakH,
     QuotationNonKontrak,
     OrderHeader,
     LhpsAirHeader,Invoice
    };
use App\Models\customer\Users;

class PortalCustomerSideController extends Controller
{
    public function menu (Request $request)
    {
        if($request->mode == 'menu'){
            try {
                //code...
                $result = MasterPelanggan::whereIn('id_pelanggan', json_decode($request->id_pelanggan))->where('is_active',true)->get();
                return response()->json(["data"=>$result,"status"=>'success'], 200);
            } catch (\Exception $ex) {
                return response()->json([
                    'message' => $ex->getMessage(),
                    'line' => $ex->getLine(),
                    'file' => $ex->getFile(),
                ], 500);
            }
        }else if($request->mode == 'submenuQuotation'){
            try {
                $quotationH =QuotationKontrakH::with('order.getInvoice')
                ->where('pelanggan_ID', $request->id_pelanggan)
                ->where('is_active',true)
                ->whereNotIn('flag_status', ['rejected', 'void'])
                // ->where('nama_perusahaan', $request->nama_perusahaan)
                ->get(['nama_perusahaan','flag_status','wilayah','no_document']);

                $quotation=QuotationNonKontrak::with('order.getInvoice')
                ->where('pelanggan_ID', $request->id_pelanggan)
                ->where('is_active',true)
                ->whereNotIn('flag_status', ['rejected', 'void'])
                // ->where('nama_perusahaan', $request->nama_perusahaan)
                ->get(['nama_perusahaan','flag_status','wilayah','no_document']);

                $result = [];

                // Mengambil data dari $quotationH
                if (!$quotationH->isEmpty()) {
                    foreach ($quotationH as $item) {
                        $result[] = [
                            'no_quotation' => $item->no_document,
                            'status' => 'kontrak'
                        ];
                    }
                }

                // Mengambil data dari $quotation
                if (!$quotation->isEmpty()) {
                    foreach ($quotation as $item) {
                        $result[] = [
                            'no_quotation' => $item->no_document,
                            'status' => 'non_kontrak'
                        ];
                    }
                }

                return response()->json($result,200);
            } catch (\Throwable $th) {
                //throw $th;
                return response()->json([
                    'message' => $th->getMessage(),
                    'line' => $th->getLine(),
                    'file' => $th->getFile(),
                ], 500);
            }
        }else if($request->mode == 'submenuOrder'){
            try {

                $quotationH =QuotationKontrakH::with('order.getInvoice')
                ->where('pelanggan_ID', $request->id_pelanggan)
                ->where('flag_status','ordered')
                ->where('is_active',true)
                ->get(['nama_perusahaan','flag_status','wilayah','no_document','filename']);

                $quotation=QuotationNonKontrak::with('order.getInvoice')
                ->where('pelanggan_ID', $request->id_pelanggan)
                ->where('flag_status','ordered')
                ->where('is_active',true)
                ->get(['nama_perusahaan','flag_status','wilayah','no_document','filename']);

                $result = [];
                // Mengambil data dari $quotationH
                if (!$quotationH->isEmpty()) {
                    foreach ($quotationH as $item) {

                        $result[] = [
                            'no_quotation' => $item->no_document,
                            'file' => $item->filename,
                            'status' => 'kontrak'
                        ];
                    }
                }

                // Mengambil data dari $quotation
                if (!$quotation->isEmpty()) {
                    foreach ($quotation as $item) {
                        $result[] = [
                            'no_quotation' => $item->no_document,
                            'file' => $item->filename,
                            'status' => 'non_kontrak'
                        ];
                    }
                }

                return response()->json(["data"=>$result],200);
            } catch (\Throwable $th) {
                //throw $th;
                return response()->json([
                    'message' => $th->getMessage(),
                    'line' => $th->getLine(),
                    'file' => $th->getFile(),
                ], 500);
            }
        }else if($request->mode == 'submenuInvoice'){
            try {
                // $quotationH =QuotationKontrakH::with('order.getInvoice')
                // ->where('pelanggan_ID', $request->id_pelanggan)
                // ->where('flag_status','ordered')
                // ->where('is_active',true)
                // ->get(['nama_perusahaan','flag_status','wilayah','no_document']);
                // $quotation=QuotationNonKontrak::with('order.getInvoice')
                // ->where('pelanggan_ID', $request->id_pelanggan)
                // ->where('flag_status','ordered')
                // ->where('is_active',true)
                // ->get(['nama_perusahaan','flag_status','wilayah','no_document']);
                // $result = [];

                // // Mengambil data dari $quotationH
                // if (!$quotationH->isEmpty()) {
                //     foreach ($quotationH as $item) {
                //         if ($item->order && $item->order->getInvoice) {
                //             foreach ($item->order->getInvoice as $invoice) { // Looping hasil getInvoice
                //                 $result[] = [
                //                     'no_quotation' => $invoice->no_quotation ?? 'Tidak Ada',
                //                     'periode' => $invoice->periode ?? 'Tidak Ada',
                //                     'status' => 'kontrak'
                //                 ];
                //             }
                //         }
                //     }
                // }

                // // Mengambil data dari $quotation
                // if (!$quotation->isEmpty()) {
                //     foreach ($quotation as $item) {

                //         if ($item->order && $item->order->getInvoice) {
                //             foreach ($item->order->getInvoice as $invoice) { // Looping hasil getInvoice
                //                 $result[] = [
                //                     'no_quotation' => $invoice->no_quotation,
                //                     'periode' => $invoice->periode ?? null,
                //                     'status' => 'non_kontrak'
                //                 ];
                //             }
                //         }
                //     }
                // }

                $invoice = Invoice::where('pelanggan_id', $request->id_pelanggan)
                ->where('is_active', true)
                ->get(['no_quotation', 'periode','tgl_invoice','filename']);


                return response()->json(["data"=>$invoice],200);
            } catch (\Throwable $th) {
                //throw $th;
                return response()->json([
                    'message' => $th->getMessage(),
                    'line' => $th->getLine(),
                    'file' => $th->getFile(),
                ], 500);
            }
        }else if($request->mode == 'submenuLHPS'){
            $orderH = OrderHeader::with(['getLhpsAirHeader','getLhpsEmisiHeader','getLhpsEmisiCHeader','getLhpsGeteranHeader','getLhpsKebisinganHeader','getLhpsLinkunganHeader','getLhpsMedanLMHeader','getLhpsPencahayaanHeader','getLhpsSinarUVHeader'])
                ->where('id_pelanggan', $request->id_pelanggan)
                ->where('is_active', true)
                ->get(['no_document', 'no_order']);

            if($orderH->isEmpty()){
                return response()->json(["message"=>"Data tidak ditemukan"], 404);
            }else{
                $result = [];
                foreach ($orderH as $item) {
                    if ($item->getLhpsAirHeader) {
                        foreach ($item->getLhpsAirHeader as $lhps) {
                            $result[] = [
                                'no_order' => $item->no_order,
                                'no_quotation' => $item->no_document,
                                'no_lhps' => $lhps->no_lhp,
                                'file' => $lhps->file_lhp,
                                'type' =>$lhps->sub_kategori
                            ];
                        }
                    }
                    if($item->getLhpsEmisiHeader){
                        foreach ($item->getLhpsEmisiHeader as $lhps) {
                            $result[] = [
                                'no_order' => $item->no_order,
                                'no_quotation' => $item->no_document,
                                'no_lhps' => $lhps->no_lhp,
                                'file' => $lhps->file_lhp,
                                'type' =>$lhps->sub_kategori
                            ];
                        }
                    }
                    if($item->getLhpsEmisiCHeader){
                        foreach ($item->getLhpsEmisiCHeader as $lhps) {
                            $result[] = [
                                'no_order' => $item->no_order,
                                'no_quotation' => $item->no_document,
                                'no_lhps' => $lhps->no_lhp,
                                'file' => $lhps->file_lhp,
                                'type' =>$lhps->sub_kategori
                            ];
                        }
                    }
                    if($item->getLhpsGeteranHeader){
                        foreach ($item->getLhpsGeteranHeader as $lhps) {
                            $result[] = [
                                'no_order' => $item->no_order,
                                'no_quotation' => $item->no_document,
                                'no_lhps' => $lhps->no_lhp,
                                'file' => $lhps->file_lhp,
                                'type' =>$lhps->sub_kategori
                            ];
                        }
                    }
                    if($item->getLhpsKebisinganHeader){
                        foreach ($item->getLhpsKebisinganHeader as $lhps) {
                            $result[] = [
                                'no_order' => $item->no_order,
                                'no_quotation' => $item->no_document,
                                'no_lhps' => $lhps->no_lhp,
                                'file' => $lhps->file_lhp,
                                'type' =>$lhps->sub_kategori
                            ];
                        }
                    }
                    if($item->getLhpsLinkunganHeader){
                        foreach ($item->getLhpsLinkunganHeader as $lhps) {
                            $result[] = [
                                'no_order' => $item->no_order,
                                'no_quotation' => $item->no_document,
                                'no_lhps' => $lhps->no_lhp,
                                'file' => $lhps->file_lhp,
                                'type' =>$lhps->sub_kategori
                            ];
                        }
                    }
                    if($item->getLhpsMedanLMHeader){
                        foreach ($item->getLhpsMedanLMHeader as $lhps) {
                            $result[] = [
                                'no_order' => $item->no_order,
                                'no_quotation' => $item->no_document,
                                'no_lhps' => $lhps->no_lhp,
                                'file' => $lhps->file_lhp,
                                'type' =>$lhps->sub_kategori
                            ];
                        }
                    }
                    if($item->getLhpsPencahayaanHeader){
                        foreach ($item->getLhpsPencahayaanHeader as $lhps) {
                            $result[] = [
                                'no_order' => $item->no_order,
                                'no_quotation' => $item->no_document,
                                'no_lhps' => $lhps->no_lhp,
                                'file' => $lhps->file_lhp,
                                'type' =>$lhps->sub_kategori
                            ];
                        }
                    }
                    if($item->getLhpsSinarUVHeader){
                        foreach ($item->getLhpsSinarUVHeader as $lhps) {
                            $result[] = [
                                'no_order' => $item->no_order,
                                'no_quotation' => $item->no_document,
                                'no_lhps' => $lhps->no_lhp,
                                'file' => $lhps->file_lhp,
                                'type' =>$lhps->sub_kategori
                            ];
                        }
                    }
                }
                // return response()->json(["data"=>$result], 200);
                $result2 = MasterPelanggan::where('id_pelanggan', $request->id_pelanggan)->first();
                $result2->lhps = $result;
                return response()->json(["data"=>$result2], 200);
            }
        }else if($request->mode == 'submenuHistory'){
            $request->no_document = preg_replace('/R\d+$/', '', $request->no_document);

            $type = \explode('/', $request->no_document)[1];
            if ($type == 'QTC') {
                $result = QuotationKontrakH::where('no_document', 'like', '%' . $request->no_document . '%')
                    ->orderBy('no_document', 'asc')
                    ->get(['flag_status','no_document']);
            } else {
                $result = QuotationNonKontrak::where('no_document', 'like', '%' . $request->no_document . '%')
                    ->orderBy('no_document', 'asc')
                    ->get(['flag_status', 'no_document']);
            }
            return response()->json(['data' => $result], 200);
        }
    }


    public function test (Request $request)
    {
        $user =Users::with('custoken')->get()->toArray();
        return response()->json($user);
    }
}
