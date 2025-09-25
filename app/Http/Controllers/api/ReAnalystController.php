<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\TemplateStp;
use App\Models\MasterCabang;
use App\Models\MasterKategori;
use App\Models\CategorySample;
use App\Models\Parameter;
use App\Models\User;
use App\Models\Usertoken;
use App\Models\Requestlog;
use App\Models\OrderDetail;
use App\Models\Titrimetri;
use App\Models\Colorimetri;
use App\Models\Gravimetri;
use App\Models\LingkunganHeader;
use App\Models\DebuPersonalHeader;
use App\Models\WsValueLingkungan;
use App\Models\EmisiCerobongHeader;
use App\Models\DustFallHeader;
use App\Models\MicrobioHeader;
use App\Models\IsokinetikHeader;
use App\Models\SwabTestHeader;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class ReAnalystController extends Controller
{
    public function index(Request $request){
        try {
            $stp = TemplateStp::with('sample')->where('id', $request->id_stp)->select('name','category_id')->first();
            
            if($stp->name == 'TITRIMETRI' && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {
                $data = Titrimetri::with('ws_value', 'order_detail')
                ->where('is_active',true)
                ->where('template_stp', $request->id_stp)
                ->where('is_approve',true)
                ->whereNotNull('rejected_at')
                ->orderBy('id', 'desc')
                ->get();
    
                return response()->json([
                    'data' => $data,
                    'message'=> 'Show Worksheet Success2'
                ], 200);
            } else if($stp->name == 'GRAVIMETRI' && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {
                $data = Gravimetri::with('value', 'detail', 'addby','delby')
                ->where('active', $request->active)
                ->where('par', $request->par)
                ->where('approve',0)
                ->whereNotNull('reject_at')
                ->where('active',0)
                ->orderBy('id', 'desc')
                ->get();
    
                return response()->json([
                    'data' => $data,
                    'message'=> 'Show Worksheet Success2'
                ], 200);
            } else if(
                ($stp->name == 'MIKROBIOLOGI' || $stp->name == 'ICP' || $stp->name == 'DIRECT READING' || $stp->name == 'COLORIMETRI' || $stp->name == 'SPEKTROFOTOMETER UV-VIS' || $stp->name == 'COLORIMETER' || $stp->name == 'MERCURY ANALYZER') 
                && 
                ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')
            ) {
                $data = Colorimetri::with('value', 'detail', 'addby','delby')
                ->where('par', $request->par)
                ->where('approve', 0)
                ->whereNotNull('reject_at')
                // ->whereNotIn('no_sample', function($query) {
                // 	$query->select('no_sample')
                // 		->from('colorimetri')
                // 		->where('active', 0);
                // })
                ->orderBy('id', 'desc')
                ->get();
    
                return response()->json([
                    'data' => $data,
                    'message'=> 'Show Worksheet Success2'
                ], 200);
            }else if(($stp->name == 'SPEKTRO UV-VIS' || $stp->name == 'ICP' || $stp->name == 'GRAVIMETRI') && $stp->sample->nama_kategori == 'Udara'){
                $data = Linghidupheader::with('addby', 'value', 'datlapangank', 'datlapanganh')->where('active', $request->active)
                ->where('par', $request->par)
                ->where('approve',0)
                ->where('active',0)
                ->whereNotNull('reject_at')
                ->orderBy('id', 'desc')
                ->get();
                return response()->json([
                    'data' => $data,
                    'message'=> 'Show Worksheet Success2'
                ], 200);
            }else if(($stp->name == 'SPEKTRO UV-VIS' || $stp->name == 'ICP' || $stp->name == 'GRAVIMETRI') && $stp->sample->nama_kategori == 'Emisi'){
                $data = EmisicHeader::with('addby', 'value')->where('active', $request->active)
                ->where('par', $request->par)
                ->where('approve',0)
                ->where('active',0)
                ->where('id_parameter', $request->parameter)
                ->whereNotNull('reject_at')
                ->orderBy('id', 'desc')
                ->get();
                return response()->json([
                    'data' => $data,
                    'message'=> 'Show Worksheet Success2'
                ], 200);
            }else if($stp->name == 'DIRECT READING' && ($stp->sample->nama_kategori == 'Udara' || $stp->sample->nama_kategori == 'Emisi')){
                $data = Titrimetri::with('value', 'detail', 'addby','delby')
                ->where('active', $request->active)
                ->where('par', $request->par)
                ->where('approve',0)
                ->where('active',0)
                ->whereNotNull('reject_at')
                ->get();
                return response()->json([
                    'data' => $data,
                    'message'=> 'Show Worksheet Success2'
                ], 200);
            }
        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine()
            ], 500);
        }
    }
}