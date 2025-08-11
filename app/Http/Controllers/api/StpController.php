<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\TemplateStp;
use App\Models\OrderDetail;
use App\Models\MasterKategori;
use Illuminate\Http\Request;

class StpController extends Controller
{
    public function index(Request $request)
    {
        try {
            if(isset($request->tgl) && $request->tgl!=null && isset($request->category) && $request->category!=null && $request->id_stp!=null){
                $join = OrderDetail::where('tanggal_terima', $request->tgl)->where('kategori_2',$request->category)->where('is_active', true)->get();

                $par = TemplateStp::where('id', $request->id_stp)->first();

                        
                if($join->isEmpty()){
                    return response()->json([
                        'message' => 'Data tidak ditemukan',               
                    ], 404);
                }
                // dd($par);
                $select = json_decode($par->param);
                $jumlah = count($join);
                // dd($select);
                $result = '';
                $len = '';
                
                $a=0;
                $tes = array();
                $data = array();
                
                foreach($join as $kyes=>$val){
                    
                    $param = !is_null(json_decode($val->parameter)) ? array_map(function($item) {
                        return explode(';', $item)[1];
                    }, json_decode($val->parameter, true)) : [];

                    $lis = array_diff($select, $param);
                    // dd($lis, $param);
                    $beda = array_diff($select, $param);
                    foreach($beda as $num=>$kk){
                        $dat[$num]='-';
                    }                       

                    $sama = array_diff($select , $lis);
                    // dd($val);
                    foreach($sama as $mun=>$ll){
                        $dat[$mun]=$val->no_sampel;
                    }

                    ksort($dat);
                    $data[$kyes]=$dat;
                    // dd($select, $data);
                }

                return response()->json([
                    'status'=> 0,
                    'columns'=> $select,
                    'data' => $data,
                    
                ], 200);
            } else if(isset($request->tgl) && $request->tgl!=null && isset($request->category) && $request->category!=null){
                $join = OrderDetail::where('tanggal_terima', $request->tgl)->where('kategori_2',$request->category)->where('is_active',1)->get();
                        
                if($join->isEmpty()){
                    return response()->json([
                        'message' => 'Data tidak ditemukan',                
                    ], 404);
                }
                // dd($join);

                $jumlah = count($join);
                $i=0;
                $result = '';
                $len = '';
                foreach($join as $key=>$value){
                    // dd($value->parameter);
                    $i++;
                    $result_str = preg_replace('/\d+(?=;)/', '', $value->parameter);
                    $result_str = str_replace("[", '', $result_str);
                    $result_str = str_replace(";", '', $result_str);
                    $result_str = str_replace("]", '', $result_str);

                    // $len = count(json_decode($value->param));
                    $len = $len.$value->no_sample.','.$value->no_sample;
                    if($jumlah==$i) $result = $result.$result_str;
                    else $result = $result.$result_str.',';
                }
                // dd($result , $len);
                $array = json_decode('['.$result.']');
                $gg= array_unique($array);
                foreach($gg as $jk=>$wrr){
                    $columns[] = $wrr;
                }


                $a=0;
                $tes = array();
                $data = array();
                foreach($join as $kyes=>$val){
                    $param = !is_null(json_decode($val->parameter)) ? array_map(function($item) {
                        return explode(';', $item)[1];
                    }, json_decode($val->parameter, true)) : [];

                    $lis = array_diff($columns, $param);

                    $beda = array_diff($columns, $param);
                    foreach($beda as $num=>$kk){
                        $dat[$num]='-';
                    }                       

                    $sama = array_diff($columns , $lis);
                    foreach($sama as $mun=>$ll){
                        $dat[$mun]=$val->no_sampel;
                    }
                    ksort($dat);
                    $data[$kyes]=$dat;
                }

                // dd($data, $columns);

                //==========================================================================================================

                return response()->json([
                    'status'=> 0,
                    'columns'=> $columns,
                    'data' => $data,
                ], 200);


            }else{
                return response()->json([
                    'status' => 1,
                ]);
            }
            // } else if($request->id_stp == null || $request->id_stp == '') {
            //     return response()->json([
            //         'status'=>1,
            //         'message' => 'Tanggal, Kategori, dan Template Wajib Dipilih'
            //     ], 400);
            // }
        } catch (\Exception $th) {
            return response()->json([
                'status' => 1,
                'line' => $th->getLine(),
                'message' => $th->getMessage()
            ], 200);
        }
    }

    public function getCategory()
    {
        $data = MasterKategori::where('is_active', true)->select('id','nama_kategori')->get();
        return response()->json($data);
    }

    public function getTemplate(Request $request)
    {
        try {
            $data = TemplateStp::where('is_active', true)
                ->where('category_id', $request->id_kategori)
                ->select('id','name')
                ->get();

            return response()->json($data);
        }catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: '.$e->getMessage(),
                'status' => '500'
            ],500);
        }
    }
}