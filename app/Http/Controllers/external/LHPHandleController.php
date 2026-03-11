<?php

namespace App\Http\Controllers\external;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

//model
use App\Models\HoldHp;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\Invoice;
use App\Services\GroupedCfrByLhp;
use Carbon\Carbon;

class LHPHandleController extends BaseController
{
    public function cekLHP(Request $request)
    {
        $token = str_replace(' ', '+', $request->token);
        // $token = $request->token;
        
        if($token == null || $token == '') {
            return response()->json(['message' => 'Token tidak boleh kosong'], 430);
        } else {
            $cekData = DB::table('generate_link_quotation')->where('token', $token)->first();
            if($cekData){
                $dataLhp = DB::table('link_lhp')->where('id_token', $cekData->id)->first();

                if($cekData){
                    $dataLhp = DB::table('link_lhp')->where('id_token', $cekData->id)->first();
                    $checkHold =HoldHp::where('no_order',$dataLhp->no_order)->first();
                    if ($checkHold && $checkHold->is_hold == 1) {
                        // Sudah di-hold, jangan tampilkan
                        return response()->json(['message' => 'Document On Hold'], 405);
                    }else{
                        if($dataLhp && isset($dataLhp->filename) && $dataLhp->filename != null && $dataLhp->filename != '') {
                            if(file_exists(public_path('laporan/hasil_pengujian/' . $dataLhp->filename))) {
                                return response()
                                ->json(
                                    [
                                        'data' => $dataLhp,
                                        'message' => 'data hasbenn show',
                                        'qt_status' => $cekData->quotation_status,
                                        'status' => '201',
                                        'uri' => env('APP_URL') . '/public/laporan/hasil_pengujian/' . $dataLhp->filename
                                    ], 200);
                                return response()->json(['message' => 'Document found', 'data' => env('APP_URL') . '/public/laporan/hasil_pengujian/' . $dataLhp->filename], 200);
                            } else {
                                return response()->json(['message' => 'Document found but file not exists'], 403);
                            }
                            // return response()->json(['message' => 'Document found', 'data' => $dataLhp->filename], 200);
                        } else if ($dataLhp && $dataLhp->filename == null || $dataLhp->filename == ''){
                            return response()->json(['message' => 'Document found but file not exists'], 403);
                        } else {
                            return response()->json(['message' => 'Document not found'], 404);
                        }
                    }
                } else {
                    return response()->json(['message' => 'Token not found'], 401);
                }
            } else {
                return response()->json(['message' => 'Token not found'], 401);
            }
        }
    }

    public function newCheckLhp(Request $request)
    {
        $token = str_replace(' ', '+', $request->token);
        if($token == null || $token == '') {
            return response()->json(['message' => 'Token tidak boleh kosong'], 430);
        } else {
            $cekData = DB::table('generate_link_quotation')->where('token', $token)->first();
            
            if($cekData){
                $dataLhp = DB::table('link_lhp')->where('id_token', $cekData->id)->first();
                $periode = $dataLhp->periode;
                $noOrder = $dataLhp->no_order;

                $fileName = $dataLhp->filename ?? null;
                
                $checkHold =HoldHp::where('no_order',$noOrder)->where('periode',$periode)->first();

                $dataOrder = OrderHeader::where('no_order', $noOrder)->where('is_active', true)->first();

                $cekInvoice = Invoice::with(['recordPembayaran', 'recordWithdraw'])->where('no_order', $noOrder);
                $all = false;
                foreach($cekInvoice->get() as $invoice){
                    if($invoice->periode == "all") $all = true; break;
                }
                
                if($periode != null && $periode != '' && !$all) $cekInvoice = $cekInvoice->where('periode', $periode);
                $cekInvoice = $cekInvoice->where('is_active', true)->get() ?? null;

                if($dataOrder){
                    $dataGrouped = (new GroupedCfrByLhp($dataOrder, $periode))->get()->toArray();
                    unset($dataGrouped[0]['order_details']);
                    
                    return response()->json(['message' => 'Data LHP found', 'data' => $dataGrouped, 'order' => $dataOrder, 'periode' => $periode, 'invoice' => collect($cekInvoice)->toArray(), 'fileName' => $fileName, 'hold' => $checkHold], 200);
                } else {
                    return response()->json(['message' => 'Data Order not found'], 404);
                }
            } else {
                return response()->json(['message' => 'Token not found'], 401);
            }
        }
    }
}
