<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use App\Models\MasterKategori;
use App\Models\TemplateStp;
use App\Models\OrderDetail;
use App\Models\Titrimetri;
use App\Models\Colorimetri;
use App\Models\Gravimetri;
use App\Models\LingkunganHeader;
use App\Models\EmisiCerobongHeader;
use Illuminate\Http\Request;

class AnalystInputParameterController extends Controller
{
    public function getKategori(Request $request) {
        $data = MasterKategori::where('is_active', true)->get()->makeHidden(['created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_at', 'deleted_by']);

        return response()->json($data);
    }

    public function getTemplate(Request $request) {
        $id = explode('-', $request->category_id)[0];
        $data = TemplateStp::where('category_id', $id)->where('is_active', true)->get()->makeHidden(['created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_at', 'deleted_by','param']);

        return response()->json($data);
    }

    public function getParameter(Request $request)
    {
        $data = TemplateStp::where('id', $request->template_id)
            ->first();
        
        if($data){
            return response()->json(json_decode($data->param, true));
        } else {
            return response()->json(['message' => 'Data not found'], 500);
        }
    }

    public function getData(Request $request) {
        try {
            if(isset($request->tanggal) && $request->tanggal!=null && isset($request->kategori) && $request->kategori!=null && $request->template !=null){
                $parame = array();
                
                $join = OrderDetail::where('tanggal_terima', $request->tanggal)->where('kategori_2',$request->kategori)->where('is_active', true)->get();
                // $par = TemplateStp::where('id', $request->template)->first();
                
                if($join->isEmpty()){
                    return response()->json([
                        'status'=>1
                    ], 200);
                }

                $select = array($request->parameter);
                
                $jumlah = count($join);
    
                foreach($join as $kyes=>$val){
                    $param = array_map(function($item) {
                        return explode(';', $item)[1];
                    }, json_decode($val->parameter));
    
                    $lis = array_diff($select, $param);
    
                    $beda = array_diff($select, $param);
    
                    foreach($beda as $num=>$kk){
                        $dat[$kk]='-';
    
                        $hola[$kk]='-';
                    }
    
                    $sama = array_diff($select , $lis);
                    foreach($sama as $mun=>$ll){
                        $dat[$ll]=$val->no_sampel;
                        $hola[$ll]='-';
                    }
    
                    ksort($dat);
                    $data[$kyes]=$dat;
    
                    ksort($hola);
                    $inter[$kyes]=$hola;
    
                }
                $kl = array("-");
                foreach($select as $key=>$tab){
                    $re= array_column($data, $tab);
                    $result = array_diff($re, $kl);
                    sort($result);
                    $tes[$key] = $result;
    
                }
                foreach($select as $key0=>$tab0){
                    $re0= array_column($inter, $tab0);
                    $result0 = array_diff($re0, $kl);
                    sort($result0);
                    $tes0[$key0] = $result0;
                }
                $tes1 = $tes0;
                // dd($tes0);
                $approve = $tes0;
    
                $stp = TemplateStp::with('sample')->where('id', $request->template)->select('name','category_id')->first();
                // dd($stp);
                if($stp->name == 'TITRIMETRI' && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {
    
                    foreach($select as $key => $val){
                        $hasil_1 = Titrimetri::where('tanggal_terima', $request->tanggal)
                        ->where('parameter', $val)
                        ->where('is_active',true)
                        ->get();
    
                        if($hasil_1!=null){
                            $nilai = array();
                            foreach($hasil_1 as $key_1 => $value_1){
                                array_push($nilai, (object)[
                                        'no_sample' => $value_1->no_sampel,
                                        'note' => $value_1->note
                                    ]);
                            }
                            $tes1[$key] = $nilai;
                        } else {
                            $tes1[$key] = [];
                        }
    
                        $hasil_2 = Titrimetri::where('tanggal_terima', $request->tanggal)
                        ->where('parameter', $val)
                        ->where('is_approved',1)
                        ->where('is_active',true)
                        ->get();
                        if($hasil_2!=null){
                            $coba = array();
                            foreach($hasil_2 as $key_3 => $value_3){
                                $coba[$key_3] = $value_3->no_sampel;
                            }
    
                            $beda_app = array_diff($tes[$key], $coba);
                            $hasil_app = array();
                            foreach($beda_app as $key_2 => $value_2){
                                $hasil_app[$key_2] = '-';
                            }
                            $final_app = \array_replace($tes[$key], $hasil_app);
    
                            $approve[$key] = $final_app;
                        } else {
                            $approve[$key] = [];
                        }
                    }
    
                }else if($stp->name == 'GRAVIMETRI' && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {
    
                    foreach($select as $key => $val){
                        $hasil_1 = Gravimetri::where('tanggal_terima', $request->tanggal)
                        ->where('parameter', $val)
                        ->where('is_active',true)
                        ->get();
    
                        if($hasil_1!=null){
                            $nilai = array();
                            foreach($hasil_1 as $key_1 => $value_1){
                                array_push($nilai, (object)[
                                        'no_sample' => $value_1->no_sampel,
                                        'note' => $value_1->note
                                    ]);
                            }
                            $tes1[$key] = $nilai;
                        } else {
                            $tes1[$key] = [];
                        }
                        $hasil_2 = Gravimetri::where('tanggal_terima', $request->tanggal)
                        ->where('parameter', $val)
                        ->where('is_approved',1)
                        ->where('is_active',true)
                        ->get();
                        if($hasil_2!=null){
                            $coba = array();
                            foreach($hasil_2 as $key_3 => $value_3){
                                $coba[$key_3] = $value_3->no_sampel;
                            }
    
                            $beda_app = array_diff($tes[$key], $coba);
                            $hasil_app = array();
                            foreach($beda_app as $key_2 => $value_2){
                                $hasil_app[$key_2] = '-';
                            }
                            $final_app = \array_replace($tes[$key], $hasil_app);
    
                            $approve[$key] = $final_app;
                        } else {
                            $approve[$key] = [];
                        }
                    }
    
                }else if(
                    ($stp->name == 'MIKROBIOLOGI' || $stp->name == 'ICP' || $stp->name == 'DIRECT READING' || $stp->name == 'COLORIMETRI' || $stp->name == 'SPEKTROFOTOMETER UV-VIS' || $stp->name == 'COLORIMETER' || $stp->name == 'MERCURY ANALYZER') 
                    && 
                    ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')
                ) {
                    // dd($select);
                    foreach($select as $key => $val){
                        $hasil_1 = Colorimetri::where('tanggal_terima', $request->tanggal)
                        ->where('template_stp',$request->template)
                        ->where('parameter', $val)
                        ->where('is_active',true)
                        ->get();
                        // dd($hasil_1);
                        if($hasil_1!=null){
                            $nilai = array();
                            foreach($hasil_1 as $key_1 => $value_1){
                                array_push($nilai, (object)[
                                        'no_sample' => $value_1->no_sampel,
                                        'note' => $value_1->note
                                    ]);
                            }
                            $tes1[$key] = $nilai;
    
    
                        } else {
                            $tes1[$key] = [];
                        }
    
                        $hasil_2 = Colorimetri::where('tanggal_terima', $request->tanggal)
                        ->where('template_stp',$request->template)
                        ->where('parameter', $val)
                        ->where('is_approved',1)
                        ->where('is_active',true)
                        ->get();
                        // dd($hasil_2);
                        if($hasil_2!=null){
                            $coba = array();
                            foreach($hasil_2 as $key_3 => $value_3){
                                $coba[$key_3] = $value_3->no_sampel;
                            }
    
                            $beda_app = array_diff($tes[$key], $coba);
                            $hasil_app = array();
                            foreach($beda_app as $key_2 => $value_2){
                                $hasil_app[$key_2] = '-';
                            }
                            $final_app = \array_replace($tes[$key], $hasil_app);
    
                            $approve[$key] = $final_app;
                        } else {
                            $approve[$key] = [];
                        }
                    }
                }else if(($stp->name == 'SPEKTRO UV-VIS' || $stp->name == 'ICP' || $stp->name == 'GRAVIMETRI') && $stp->sample->nama_kategori == 'Udara'){
                    foreach($select as $key => $val){
                        $hasil_1 = LingkunganHeader::where('tanggal_terima', $request->tanggal)
                        ->where('template_stp',$request->template)
                        ->where('parameter', $val)
                        ->where('is_active',true)
                        ->get();
    
                        if($hasil_1!=null){
                            $nilai = array();
                            foreach($hasil_1 as $key_1 => $value_1){
                                array_push($nilai, (object)[
                                        'no_sample' => $value_1->no_sampel,
                                        'note' => $value_1->note
                                    ]);
                            }
                            $tes1[$key] = $nilai;
    
                        } else {
                            $tes1[$key] = [];
                        }
    
                        $hasil_2 = LingkunganHeader::where('tanggal_terima', $request->tanggal)
                        ->where('template_stp',$request->template)
                        ->where('parameter', $val)
                        ->where('is_approved',1)
                        ->where('is_active',true)
                        ->get();
    
                        if($hasil_2!=null){
                            $coba = array();
                            foreach($hasil_2 as $key_3 => $value_3){
                                $coba[$key_3] = $value_3->no_sampel;
                            }
    
                            $beda_app = array_diff($tes[$key], $coba);
                            $hasil_app = array();
                            foreach($beda_app as $key_2 => $value_2){
                                $hasil_app[$key_2] = '-';
                            }
                            $final_app = \array_replace($tes[$key], $hasil_app);
    
                            $approve[$key] = $final_app;
                        } else {
                            $approve[$key] = [];
                        }
                    }
                }else if(($stp->name == 'SPEKTRO UV-VIS' || $stp->name == 'ICP' || $stp->name == 'GRAVIMETRI') && $stp->sample->nama_kategori == 'Emisi'){
                    foreach($select as $key => $val){
                        $hasil_1 = EmisiCerobongHeader::where('tanggal_terima', $request->tanggal)
                        ->where('template_stp',$request->template)
                        ->where('parameter', $val)
                        ->where('is_active',true)
                        ->get();
    
                        if($hasil_1!=null){
                            $nilai = array();
                            foreach($hasil_1 as $key_1 => $value_1){
                                array_push($nilai, (object)[
                                        'no_sample' => $value_1->no_sampel,
                                        'note' => $value_1->note
                                    ]);
                            }
                            $tes1[$key] = $nilai;
    
                        } else {
                            $tes1[$key] = [];
                        }
    
                        $hasil_2 = EmisiCerobongHeader::where('tanggal_terima', $request->tanggal)
                        ->where('template_stp',$request->template)
                        ->where('parameter', $val)
                        ->where('is_approved',1)
                        ->where('is_active',true)
                        ->get();
    
                        if($hasil_2!=null){
                            $coba = array();
                            foreach($hasil_2 as $key_3 => $value_3){
                                $coba[$key_3] = $value_3->no_sampel;
                            }
    
                            $beda_app = array_diff($tes[$key], $coba);
                            $hasil_app = array();
                            foreach($beda_app as $key_2 => $value_2){
                                $hasil_app[$key_2] = '-';
                            }
                            $final_app = \array_replace($tes[$key], $hasil_app);
    
                            $approve[$key] = $final_app;
                        } else {
                            $approve[$key] = [];
                        }
                    }
                }
                // dd($tes1);
                return response()->json([
                    'status'=>0,
                    'columns'=>$select,
                    'data' => $tes,
                    'nilai' => $tes1,
                    'approve' => $approve,
    
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Data Not Found.!',
                ], 401);
            }

            
        } catch (\Exception $th) {
            return response()->json([
                'message' => 'Failed To Get Data : ' . $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 401);
        }
    }
}