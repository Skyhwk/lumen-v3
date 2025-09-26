<?php

namespace App\Http\Controllers\api;

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
use App\Models\ValueEmisiC;
use App\Models\WsValueAir;
use App\Models\WsValueMicrobio;
use App\Models\WsValueSwab;
use App\Models\DataLapanganDebuPersonal;
use App\Models\DataLapanganLingkunganHidup;
use App\Models\DataLapanganLingkunganKerja;
use App\Models\DataLapanganEmisiCerobong;
use App\Models\DataLapanganIsokinetikHasil;
use App\Models\DataLapanganSwab;
use App\Models\DetailLingkunganHidup;
use App\Models\DetailLingkunganKerja;
use App\Models\DetailMicrobiologi;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use App\Services\FunctionValue;
use Exception;
use DB;

class AppAnalystController extends Controller
{
    public function getparam(Request $request){
        try {
            if($request->mode == 'getParam'){
                if(isset($request->tgl) && $request->tgl!=null && isset($request->category) && $request->category!=null && $request->param !=null){
                    $parame = array();
                    
                    $join = OrderDetail::where('tanggal_terima', $request->tgl)->where('kategori_2',$request->category)->where('is_active', true)->get();
                    // $par = TemplateStp::where('id', $request->param)->first();
					
                    if($join->isEmpty()){
                        return response()->json([
                            'status'=>1
                        ], 200);
                    }
                    // $select = json_decode($par->param);
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
        
                    $stp = TemplateStp::with('sample')->where('id', $request->param)->select('name','category_id')->first();
                    // dd($stp);
                    if($stp->name == 'TITRIMETRI' && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {
        
                        foreach($select as $key => $val){
                            $hasil_1 = Titrimetri::where('tanggal_terima', $request->tgl)
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
        
                            $hasil_2 = Titrimetri::where('tanggal_terima', $request->tgl)
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
                            $hasil_1 = Gravimetri::where('tanggal_terima', $request->tgl)
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
                            $hasil_2 = Gravimetri::where('tanggal_terima', $request->tgl)
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
                            $hasil_1 = Colorimetri::where('tanggal_terima', $request->tgl)
                            ->where('template_stp',$request->param)
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
        
                            $hasil_2 = Colorimetri::where('tanggal_terima', $request->tgl)
                            ->where('template_stp',$request->param)
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
                            $hasil_1 = LingkunganHeader::where('tanggal_terima', $request->tgl)
                            ->where('template_stp',$request->param)
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
        
                            $hasil_2 = LingkunganHeader::where('tanggal_terima', $request->tgl)
                            ->where('template_stp',$request->param)
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
                            $hasil_1 = EmisiCerobongHeader::where('tanggal_terima', $request->tgl)
                            ->where('template_stp',$request->param)
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
        
                            $hasil_2 = EmisiCerobongHeader::where('tanggal_terima', $request->tgl)
                            ->where('template_stp',$request->param)
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
            }else{
                $options = "<option value='' selected >Pilih Parameter</option>";
                if (isset($request->category) && $request->category != null && $request->param != null) {
                    $par = TemplateStp::where('id', $request->param)->first();
                    if ($par) {
                        foreach (json_decode($par->param) as $q) {
                            $options .= "<option value='$q'> $q </option>";
                        }
                    }
                }
                // $options .= "</select>";
                return response()->json(['html' => $options], 200);
            }
            
        } catch (\Exception $th) {
            return response()->json([
                'message' => 'Failed To Get Data : ' . $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 401);
        }
    }

    public function addValueParamApi(Request $request){
        try {
            $stp = TemplateStp::with('sample')->where('id', $request->par)->select('name','category_id')->first();
            // dd($stp);
            if($stp->name == 'TITRIMETRI' && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {
                // dd('masuk titri');
        		if(isset($request->jenis_pengujian) && $request->jenis_pengujian=='sample'){
					$vts = $request->vts;
					$vtb = $request->vtb;
					$kt = $request->kt;
					$vs = $request->vs;
					$fp = $request->fp;

					$cek = Titrimetri::where('no_sampel',$request->no_sample)
                        ->where('parameter', $request->parameter)
                        ->where('is_active', true)
                        ->where('status',0)
                        ->first();
					// dd($request->all(),$cek);
					if(isset($cek->id)){
						return response()->json([
			                'message'=> 'No Sample Sudah ada.!!'
			            ], 403);
					}else{
						$parame = $request->parameter;
                        $par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

						$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();
						$id_po = '';
						$tgl_terima = '';

						if(isset($check->id)){
							$id_po = $check->id;
							$tgl_terima = $check->tanggal_terima;
						}
						else{
							return response()->json([
				                'message'=> 'No Sample tidak ada.!!'
				            ], 404);
						}
                        $datas = new FunctionValue();
                        $checkParam = $datas->Titrimetri($par->id, $request);
                        if($checkParam == 'gagal') {
                            return response()->json([
                                'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
                            ], 404);
                        }else {
                            $data = new Titrimetri;
                            $data->no_sampel 			= $request->no_sample;
                            $data->parameter 			= $request->parameter;
                            $data->template_stp 		= $request->par;
                            $data->jenis_pengujian 		= $request->jenis_pengujian;
                            $data->konsentrasi_titan 	= $request->kt;   //konsentrasi titran
							if ($request->has('volume_titrasi_baru')) {
								$data->vts = $request->volume_titrasi_baru;
							} elseif ($request->has('do_sampel_5_hari_baru')) {
								$data->do_sampel5 = $request->do_sampel_5_hari_baru;
								$data->do_sampel0 = $request->do_sampel_0_hari_baru;
								$data->do_blanko5 = $request->do_blanko_5_hari_baru;
								$data->do_blanko0 = $request->do_blanko_0_hari_baru;
								$data->vmb 	= $request->volume_mikroba_blanko_baru;
								$data->vms 	= $request->volume_mikroba_sampel_baru;
								$data->fp 	= $request->faktor_pengenceran_baru;
							}

                            // Tambahkan penanganan untuk parameter lainnya di sini
                            // Misalnya:
							$data->vts                  = $request->vts; // volume titrasi
							$data->fp                   = $request->fp; // faktor pengenceran
                            $data->vtb 					= $request->vtb;  //vokume titrasi blanko
                            $data->vs 					= $request->vs;  //volume sample
                            $data->note 				= $request->note;
                            $data->tanggal_terima 		= $tgl_terima;
                            $data->created_by 			= $this->karyawan;
                            $data->created_at 			= DATE('Y-m-d H:i:s');
							
                            $data->save();
							
                            $datas = new FunctionValue();
                            $result = $datas->Titrimetri($par->id, $request);
							// dd($result,$data);
                            WsValueAir::create($result);

                            return response()->json([
                                'message'=> 'Value Parameter berhasil disimpan.!',
                                'par' => $request->parameter
                            ], 200);
                        }

					}
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='duplo') {
					return response()->json([
		                'message'=> 'This action not suitable'
		            ], 401);
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='spike') {
					return response()->json([
		                'message'=> 'This action not suitable'
		            ], 401);
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='retest') {
					$vts = $request->vts;
					$vtb = $request->vtb;
					$kt = $request->kt;
					$vs = $request->vs;
					$fp = $request->fp;

					$parame = $request->parameter;
                    $par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
					$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();
					$id_po = '';
					$tgl_terima = '';

					if(isset($check->id)){
						$id_po = $check->id;
						$tgl_terima = $check->tanggal_terima;
					}
					else{
						return response()->json([
			                'message'=> 'No Sample tidak ada.!!'
			            ], 401);
					}
					$cari = Titrimetri::where('no_sampel', $request->no_sample)
							->where('parameter', $request->parameter)
							->where('is_active',true)
							->get();
                    $n = count($cari);
                    $datas = new FunctionValue();
                    $checkParam = $datas->Titrimetri($par->id, $request, $n);
                    if($checkParam == 'gagal') {
                        return response()->json([
                            'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
                        ], 401);
                    }else {
                        $data = new Titrimetri;
                        $data->no_sampel 			= $request->no_sample;
                        $data->parameter 			= $request->parameter;
                        $data->template_stp 		= $request->par;
                        $data->jenis_pengujian 		= $request->jenis_pengujian;
                        $data->konsentrasi_titan 	= $request->kt;   //konsentrasi titran
						if ($request->has('volume_titrasi_baru')) {
							$data->vts = $request->volume_titrasi_baru;
						} elseif ($request->has('do_sampel_5_hari_baru')) {
							$data->do_sampel5 = $request->do_sampel_5_hari_baru;
							$data->do_sampel0 = $request->do_sampel_0_hari_baru;
							$data->do_blanko5 = $request->do_blanko_5_hari_baru;
							$data->do_blanko0 = $request->do_blanko_0_hari_baru;
							$data->vmb 	= $request->volume_mikroba_blanko_baru;
							$data->vms 	= $request->volume_mikroba_sampel_baru;
							$data->fp 	= $request->faktor_pengenceran_baru;
						}
								// Tambahkan penanganan untuk parameter lainnya di sini
								// Misalnya:
						$data->vts = $request->vts; // volume titrasi
						$data->fp = $request->fp; // faktor pengenceran
                        $data->vtb 					= $request->vtb;  //vokume titrasi blanko
                        $data->vs 					= $request->vs;  //volume sample
                        $data->note 				= $request->note;
                        $data->tanggal_terima 		= $tgl_terima;
                        $data->created_by 			= $this->karyawan;
                        $data->created_at 			= DATE('Y-m-d H:i:s');
                        $data->status 				= $n;
						// dd('stop');
                        $data->save();
						
                        $datas = new FunctionValue();
                        $result = $datas->Titrimetri($par->id, $request, $n);
						
						// dd($result);
                        WsValueAir::create($result);

                        return response()->json([
                            'message'=> 'Value Parameter berhasil disimpan.!',
                            'par' => $request->parameter
                        ], 200);
                    }

				}
        	}else if($stp->name == 'GRAVIMETRI' && ($stp->sample->nama_kategori == 'Air' || $stp->sample->nama_kategori == 'Padatan')) {
                // dd('masuk');
        		if(isset($request->jenis_pengujian) && $request->jenis_pengujian=='sample'){
					$vts = $request->vts;
					$vtb = $request->vtb;
					$kt = $request->kt;
					$vs = $request->vs;
					$fp = $request->fp;

					$cek = Gravimetri::where('no_sampel',$request->no_sample)
                        ->where('parameter', $request->parameter)
                        ->where('is_active',true)
                        ->where('status',0)
                        ->first();
					// dd($cek);
					if(isset($cek->id)){
						return response()->json([
			                'message'=> 'No Sample Sudah ada.!!'
			            ], 401);
					}else{
						$parame = $request->parameter;
                        $par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
                        
						$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();
						$id_po = '';
						$tgl_terima = '';

						if(isset($check->id)){
							$id_po = $check->id;
							$tgl_terima = $check->tanggal_terima;
						}
						else{
							return response()->json([
				                'message'=> 'No Sample tidak ada.!!'
				            ], 401);
						}

                        $datas = new FunctionValue();
                        $checkParam = $datas->Gravimetri($par->id, $request);
						// dd($checkParam);
                        if($checkParam == 'gagal') {
                            return response()->json([
                                'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
                            ], 401);
                        }else {
                            $data = new Gravimetri;
                            $data->no_sampel 			= $request->no_sample;
                            $data->parameter 			= $request->parameter;
                            $data->template_stp 		= $request->par;
                            $data->jenis_pengujian 		= $request->jenis_pengujian;
							$data->bk_1 				= $request->bk_1;
							$data->bk_2 				= $request->bk_2;
							$data->bki_1 				= $request->bki1;
							$data->bki_2 				= $request->bki2;
							$data->vs 					= $request->vs;
							if($request->has('fp')) {
								$data->fp 					= $request->fp;
							}
                            $data->note 				= $request->note;
                            $data->tanggal_terima 		= $tgl_terima;
                            $data->created_by 			= $this->karyawan;
                            $data->created_at 			= DATE('Y-m-d H:i:s');
							// dd($data,'sample');
                            $data->save();
                            $datas = new FunctionValue();
                            $result = $datas->Gravimetri($par->id, $request, '');
                            WsValueAir::create($result);
							// if($request->parameter == "TDS"){
							// 	// $data->metode				= "baru";
							// 	$data->bk_1 				= $request->bk_1_baru;
							// 	$data->bk_2 				= $request->bk_2_baru;
							// 	$data->bki_1 				= $request->bi1_baru;
							// 	$data->bki_2 				= $request->bi2_baru;
							// 	$data->vs 					= $request->vs_baru;
							// }else{
							// 	// $data->metode				= "lama";
							// 	$data->bk_1 				= $request->bk_1;
							// 	$data->bk_2 				= $request->bk_2;
							// 	$data->bki_1 				= $request->bki1;
							// 	$data->bki_2 				= $request->bki2;
							// 	$data->vs 					= $request->vs;
							// 	$data->fp 					= $request->fp;
							// }

    						//================================Kalkulasi Mineral Nabati Otomatis===================================================================================

                            $og =  Gravimetri::where('no_sampel', $request->no_sample)->where('parameter', 'OG')->where('is_active', true)->first();
                            $mm =  Gravimetri::where('no_sampel', $request->no_sample)->where('parameter', 'M.Mineral')->where('is_active', true)->first();

                            if($og!=null && $mm!=null){
                                $kalkulasi1 = WsValueAir::where('id_gravimetri', $og->id)->first();
                                $kalkulasi2 = WsValueAir::where('id_gravimetri', $mm->id)->first();

                                $data1 = new Gravimetri;
                                $data1->no_sampel 			= $request->no_sample;
                                $data1->param 				= 'M.Nabati';
                                $data1->template_stp 		= $request->par;
                                $data1->jenis_pengujian 	= $request->jenis_pengujian;
                                $data1->tanggal_terima 		= $tgl_terima;
                                $data1->created_at 			= $this->karyawan;
                                $data1->created_by 			= DATE('Y-m-d H:i:s');
								// dd($data,'sample');
                                $data1->save();

                                $kalkulasi = $kalkulasi1->hasil_2 - $kalkulasi2->hasil_2;
                                $result1 = [
                                    'id_gravimetri' => $data1->id,
                                    'no_sampel' => $request->no_sample,
                                    'hasil' => $kalkulasi
                                ];
                                WsValueAir::create($result1);
                            }

    						//================================End Kalkulasi Mineral Nabati Otomatis===================================================================================

                            return response()->json([
                                'message'=> 'Value Parameter berhasil disimpan.!',
								'par' => $request->parameter
                            ], 200);
                        }
					}
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='duplo') {
					return response()->json([
		                'message'=> 'This action not suitable'
		            ], 401);
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='spike') {
					return response()->json([
		                'message'=> 'This action not suitable'
		            ], 401);
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='retest') {
                    $parame = $request->parameter;
                    $par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

					$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',1)->first();
					$id_po = '';
					$tgl_terima = '';

					if(isset($check->id)){
						$id_po = $check->id;
						$tgl_terima = $check->tanggal_terima;
					}
					else{
						return response()->json([
			                'message'=> 'No Sample tidak ada.!!'
			            ], 401);
					}
					$cari = Gravimetri::where('no_sampel', $request->no_sample)
							->where('parameter', $request->parameter)
							->where('is_active',true)
							->get();
					// dd($cari);
                    $n = count($cari);

                    $datas = new FunctionValue();
					// dd($request->all());
                    $checkParam = $datas->Gravimetri($par->id, $request, $n);
                    if($checkParam == 'gagal') {
                        return response()->json([
                            'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
                        ], 401);
                    }else {
                        $data = new Gravimetri;
						$data->no_sampel 			= $request->no_sample;
						$data->parameter 			= $request->parameter;
						$data->template_stp 		= $request->par;
						$data->jenis_pengujian 		= $request->jenis_pengujian;
						$data->bk_1 				= $request->bk_1;
						$data->bk_2 				= $request->bk_2;
						$data->bki_1 				= $request->bki1;
						$data->bki_2 				= $request->bki2;
						$data->vs 					= $request->vs;
						if($request->has('fp')) {
							$data->fp 					= $request->fp;
						}
						$data->note 				= $request->note;
						$data->tanggal_terima 		= $tgl_terima;
						$data->created_by 			= $this->karyawan;
						$data->created_at 			= DATE('Y-m-d H:i:s');
						$data->status 				= $n;
						// dd($data,'retest');
						$data->save();

                        $datas = new FunctionValue();
                        $result = $datas->Gravimetri($par->id, $request, $n);
						// dd($result);
                        WsValueAir::create($result);

    					//================================Kalkulasi Mineral Nabati Otomatis===================================================================================
                        if($request->parameter == 'OG'){
                            $cek1 =  Gravimetri::where('no_sampel', $request->no_sample)->where('parameter', $request->parameter)->where('is_active', true)->where('status', $n)->first();
                            $cek2 =  Gravimetri::where('no_sampel', $request->no_sample)->where('parameter', 'M.Mineral')->where('is_active', true)->first();
                            if($cek1!=null && $cek2!=null){
                                $kalkulasi1 = WsValueAir::where('id_gravimetri', $cek1->id)->first();
                                $kalkulasi2 = WsValueAir::where('id_gravimetri', $cek2->id)->first();

                                $data1 = new Gravimetri;
                                $data1->no_sampel 			= $request->no_sample;
                                $data1->parameter 			= $request->parameter;
                                $data1->template_stp 	    = $request->par;
                                $data1->jenis_pengujian 	= $request->jenis_pengujian;
                                $data1->tanggal_terima 		= $tgl_terima;
                                $data1->created_by 			= $this->karyawan;
                                $data1->created_at 			= DATE('Y-m-d H:i:s');
                                $data->status 				= $n;
                                $data1->save();

                                if($data1){
                                    $kalkulasi = $kalkulasi1->hasil_2 - $kalkulasi2->hasil_2;
                                    $result1 = [
                                        'id_gravimetri' => $data1->id,
                                        'no_sampel' => $request->no_sample,
                                        'hasil' => $kalkulasi
                                    ];

                                    WsValueAir::create($result1);
                                }
                            }
                        } else if($request->parameter == 'M.Mineral'){
                            $cek1 =  Gravimetri::where('no_sampel', $request->no_sample)->where('parameter', 'OG')->where('is_active', true)->first();
                            $cek2 =  Gravimetri::where('no_sampel', $request->no_sample)->where('parameter', $request->parameter)->where('is_active', true)->where('status', $n)->first();
                            if($cek1!=null && $cek2!=null){
                                $kalkulasi1 = WsValueAir::where('id_gravimetri', $cek1->id)->first();
                                $kalkulasi2 = WsValueAir::where('id_gravimetri', $cek2->id)->first();

                                $data1 = new Gravimetri;
                                $data1->no_sampel 			= $request->no_sample;
                                $data1->parameter 			= $request->parameter;
                                $data1->template_stp 		= $request->par;
                                $data1->jenis_pengujian 	= $request->jenis_pengujian;
                                $data1->tanggal_terima 		= $tgl_terima;
                                $data1->created_by 			= $this->karyawan;
                                $data1->created_at 			= DATE('Y-m-d H:i:s');
                                $data1->status 				= $n;
                                $data1->save();

                                $kalkulasi = $kalkulasi1->hasil_2 - $kalkulasi2->hasil_2;
                                $result1 = [
                                    'id_gravimetri' => $data1->id,
                                    'no_sampel' => $request->no_sample,
                                    'hasil' => $kalkulasi
                                ];

                                WsValueAir::create($result1);

                            }
                        }
   	 					//================================End Kalkulasi Mineral Nabati Otomatis===================================================================================

                        return response()->json([
                            'message'=> 'Value Parameter berhasil disimpan.!',
                            'par' => $request->parameter
                        ], 200);
                    }

				}
        	}else if(
                ($stp->name == 'MIKROBIOLOGI' || $stp->name == 'ICP' || $stp->name == 'DIRECT READING' || $stp->name == 'COLORIMETRI' || $stp->name == 'SPEKTROFOTOMETER UV-VIS' ) 
                && 
                $stp->sample->nama_kategori == 'Air'
            ) {
				// dd($request->all());
        		if(isset($request->jenis_pengujian) && $request->jenis_pengujian=='sample'){
	        		if(isset($request->no_sample) && $request->no_sample!=null){
						$hp = $request->hp;  
						$fp = $request->fp; 

						$cek = Colorimetri::where('no_sampel',$request->no_sample)
                            ->where('parameter', $request->parameter)
                            ->where('is_active',true)
                            ->first();

						if(isset($cek->id)){
							return response()->json([
				                'message'=> 'No Sample Sudah ada.!!'
				            ], 401);
						}else{
							$parame = $request->parameter;
							$par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
							$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();
							$id_po = '';
							$tgl_terima = '';

							if(isset($check->id)){
								$id_po = $check->id;
								$tgl_terima = $check->tanggal_terima;
							}
							else{
								return response()->json([
					                'message'=> 'No Sample tidak ada.!!'
					            ], 401);
							}

                            $datas = new FunctionValue();
							// dd($request->nilaiBauTerkecil);
                            $checkParam = $datas->Colorimetri($par->id, $request, '', '');
                            if($checkParam == 'gagal') {
								return response()->json([
									'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
					            ], 401);
                            }else {
								if($par->id == 585 || $par->id == 555) {
									$hp = self::tabelMpn($request->tb1, $request->tb2, $request->tb3);
								}else {
									$hp = $request->hp;
								}
                                $data = new Colorimetri;
                                $data->no_sampel 			= $request->no_sample;
                                $data->parameter 			= $request->parameter;
                                $data->template_stp 		= $request->par;
                                $data->jenis_pengujian 		= $request->jenis_pengujian;
                                $data->hp 					= $request->hp; 
                                if($request->parameter=='Persistent Foam'){
									$data->fp = $request->waktu;
								}else{
									$data->fp = $request->fp; //faktor pengenceran
								}  
                                $data->note 					= $request->note;
                                $data->tanggal_terima 			= $tgl_terima;
                                $data->created_by 				= $this->karyawan;
                                $data->created_at 				= DATE('Y-m-d H:i:s');
								// dd($data);
                                $data->save();
										  
                                $datas = new FunctionValue();
                                $result = $datas->Colorimetri($par->id, $request, '', $hp);
                                WsValueAir::create($result);

                                return response()->json([
                                    'message'=> 'Value Parameter berhasil disimpan.!',
                                    'par' => $request->parameter
                                ], 200);
                            }
						}
					} else {
						return response()->json([
			                'message'=> 'No Sample tidak ditemukan'
			            ], 401);
					}
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='duplo') {
					return response()->json([
		                'message'=> 'This action not suitable'
		            ], 401);
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='spike') {
					return response()->json([
		                'message'=> 'This action not suitable'
		            ], 401);
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='retest') {
					if(isset($request->no_sample) && $request->no_sample!=null){
						$hp = $request->hp;  
						$fp = $request->fp; 

						// $cek = Colorimetri::where('no_sampel',$request->no_sample)
						// ->where('parameter', $request->parameter)
						// ->where('is_active',true)
						// ->first();

						// if(isset($cek->id)){
						// 	return response()->json([
				        //         'message'=> 'No Sample Sudah ada.!!'
				        //     ], 401);
						// }else{
						$parame = $request->parameter;
						$par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
						$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();
						$id_po = '';
						$tgl_terima = '';

						if(isset($check->id)){
							$id_po = $check->id;
							$tgl_terima = $check->tanggal_terima;
						}
						else{
							return response()->json([
								'message'=> 'No Sample tidak ada.!!'
							], 401);
						}

						$datas = new FunctionValue();
						// dd($request->nilaiBauTerkecil);
						$checkParam = $datas->Colorimetri($par->id, $request, '', '');
						if($checkParam == 'gagal') {
							return response()->json([
								'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
							], 401);
						}else {
							if($par->id == 585 || $par->id == 555) {
								$hp = self::tabelMpn($request->tb1, $request->tb2, $request->tb3);
							}else {
								$hp = $request->hp;
							}
							$data = new Colorimetri;
							$data->no_sampel 			= $request->no_sample;
							$data->parameter 			= $request->parameter;
							$data->template_stp 		= $request->par;
							$data->jenis_pengujian 		= $request->jenis_pengujian;
							$data->hp 					= $hp;
							if($request->parameter=='Persistent Foam'){
								$data->fp = $request->waktu;
							}else{
								$data->fp = $request->fp; //faktor pengenceran
							}  
							$data->note 				= $request->note;
							$data->tanggal_terima 		= $tgl_terima;
							$data->created_by 			= $this->karyawan;
							$data->created_at 			= DATE('Y-m-d H:i:s');
							$data->save();
							
							$datas = new FunctionValue();
							$result = $datas->Colorimetri($par->id, $request, '', $hp);
							// dd($result);
							WsValueAir::create($result);

							return response()->json([
								'message'=> 'Value Parameter berhasil disimpan.!',
								'par' => $request->parameter
							], 200);
						}
						// }
					} else {
						return response()->json([
			                'message'=> 'No Sample tidak ditemukan'
			            ], 401);
					}
				} else {
					return response()->json([
		                'message'=> 'Pilih jenis pengujian'
		            ], 401);
				}
        	}else if($stp->name == 'KIMIA PANGAN A' && $stp->sample->nama_kategori == 'Pangan'){
				if(isset($request->jenis_pengujian) && $request->jenis_pengujian=='sample'){
	        		if(isset($request->no_sample) && $request->no_sample!=null){
						$hp = $request->hp;
						$fp = $request->fp;

						$cek = Colorimetri::where('no_sampel',$request->no_sample)
						->where('parameter', $request->parameter)
						->where('is_active',true)
						->first();

						if(isset($cek->id)){
							return response()->json([
				                'message'=> 'No Sample Sudah ada.!!'
				            ], 401);
						}else{
							$parame = $request->parameter;
							$par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
							$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();
							$id_po = '';
							$tgl_terima = '';

							if(isset($check->id)){
								$id_po = $check->id;
								$tgl_terima = $check->tanggal_terima;
							}
							else{
								return response()->json([
					                'message'=> 'No Sample tidak ada.!!'
					            ], 401);
							}

                            $datas = new FunctionValue();
							// dd($request->nilaiBauTerkecil);
                            $checkParam = $datas->Colorimetri($par->id, $request, '', '');
                            if($checkParam == 'gagal') {
								return response()->json([
									'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
					            ], 401);
                            }else {
                                $data = new Colorimetri;
                                $data->no_sampel 			= $request->no_sample;
                                $data->parameter 			= $request->parameter;
                                $data->template_stp 		= $request->par;
                                $data->jenis_pengujian 		= $request->jenis_pengujian;
								if($request->has('nilaiBauTerkecil')){
									if($request->nilaiBauTerkecil == 'Tidak Berbau'){
										$data->hp 					= 'Tidak Berbau';
									}else{
										$data->hp					= $request->nilaiBauTerkecil;
									}
								}else if($request->has('nilaiTerkecil')){
									if($request->nilaiTerkecil == 'Tidak Berasa'){
										$data->hp 					= 'Tidak Berasa';
									}else{
										$data->hp 					= $request->nilaiTerkecil;
									}
								}
                                $data->note 				= $request->note;
                                $data->tanggal_terima 			= $tgl_terima;
                                $data->created_by 				= $this->karyawan;
                                $data->created_at 				= DATE('Y-m-d H:i:s');
                                $data->save();
										  
                                $datas = new FunctionValue();
                                $result = $datas->Colorimetri($par->id, $request, '', $hp);
								// dd($result,$data);
                                WsValueAir::create($result);

                                return response()->json([
                                    'message'=> 'Value Parameter berhasil disimpan.!',
                                    'par' => $request->parameter
                                ], 200);
                            }
						}
					} else {
						return response()->json([
			                'message'=> 'No Sample tidak ditemukan'
			            ], 401);
					}
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='duplo') {
					return response()->json([
		                'message'=> 'This action not suitable'
		            ], 401);
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='spike') {
					return response()->json([
		                'message'=> 'This action not suitable'
		            ], 401);
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='retest') {
					if(isset($request->no_sample) && $request->no_sample!=null){
						$hp = $request->hp;
						$fp = $request->fp;

							$parame = $request->parameter;

							$par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
							$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();
							$id_po = '';
							$tgl_terima = '';

							if(isset($check->id)){
								$id_po = $check->id;
								$tgl_terima = $check->tanggal_terima;
							}
							else{
								return response()->json([
					                'message'=> 'No Sample tidak ada.!!'
					            ], 401);
							}
							$cari = Colorimetri::where('no_sampel', $request->no_sample)
							->where('parameter', $request->parameter)
							->where('is_active',true)
							->get();
							$n = count($cari);
                            $datas = new FunctionValue();
                            $checkParam = $datas->Colorimetri($par->id, $request, $n, '');
                            if($checkParam == 'gagal') {
                                return response()->json([
					                'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
					            ], 401);
                            }else {
                                $data = new Colorimetri;
                                $data->no_sampel 			= $request->no_sample;
                                $data->parameter 			= $request->parameter;
                                $data->template_stp 		= $request->par;
                                $data->jenis_pengujian 		= $request->jenis_pengujian;
                                if($request->has('nilaiBauTerkecil')){
									if($request->nilaiBauTerkecil == 'Tidak Berbau'){
										$data->hp 					= 'Tidak Berbau';
									}else{
										$data->hp					= $request->nilaiBauTerkecil;
									}
								}else if($request->has('nilaiTerkecil')){
									if($request->nilaiTerkecil == 'Tidak Berasa'){
										$data->hp 					= 'Tidak Berasa';
									}else{
										$data->hp 					= $request->nilaiTerkecil;
									}
								}
                                $data->note 				= $request->note;
                                $data->tanggal_terima 		= $tgl_terima;
                                $data->created_by 			= $this->karyawan;
                                $data->created_at 			= DATE('Y-m-d H:i:s');
                                $data->status 				= $n;
                                $data->save();

                                $datas = new FunctionValue();
                                $result = $datas->Colorimetri($par->id, $request, $n, '');

                                WsValueAir::create($result);

                                return response()->json([
                                    'message'=> 'Value Parameter berhasil disimpan.!',
                                    'par' => $request->parameter
                                ], 200);
                            }

					} else {
						return response()->json([
			                'message'=> 'No Sample tidak ditemukan'
			            ], 401);
					}
				} else {
					return response()->json([
		                'message'=> 'Pilih jenis pengujian'
		            ], 401);
				}
            }else if(
                ($stp->name == 'ICP' || $stp->name == 'COLORIMETRI' || $stp->name == 'SPEKTROFOTOMETER UV-VIS' || $stp->name == 'MERCURY ANALYZER') 
                && 
                $stp->sample->nama_kategori == 'Padatan'
            ) {
        		if(isset($request->jenis_pengujian) && $request->jenis_pengujian=='sample'){
	        		if(isset($request->no_sample) && $request->no_sample!=null){
						$hp = $request->hp;
						$fp = $request->fp;

						$cek = Colorimetri::where('no_sampel',$request->no_sample)
						->where('parameter', $request->parameter)
						->where('is_active',true)
						->first();

						if(isset($cek->id)){
							return response()->json([
				                'message'=> 'No Sample Sudah ada.!!'
				            ], 401);
						}else{
							$parame = $request->parameter;

							$par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
							$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();
							$id_po = '';
							$tgl_terima = '';

							if(isset($check->id)){
								$id_po = $check->id;
								$tgl_terima = $check->tanggal_terima;
							}
							else{
								return response()->json([
					                'message'=> 'No Sample tidak ada.!!'
					            ], 401);
							}
                            $datas = new FunctionValue();
                            $checkParam = $datas->Colorimetri($par->id, $request, '', '');
                            if($checkParam == 'gagal') {
                                return response()->json([
					                'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
					            ], 401);
                            }else {
                                $data = new Colorimetri;
                                $data->no_sampel 			= $request->no_sample;
                                $data->parameter 			= $request->parameter;
                                $data->template_stp 		= $request->par;
                                $data->jenis_pengujian 		= $request->jenis_pengujian;
                                $data->hp 					= $request->hp;  //volume sample
                                if($request->parameter=='Persistent Foam'){$data->fp = $request->waktu;}else{$data->fp = $request->fp;}  //faktor pengenceran
                                $data->note 				= $request->note;
                                $data->tanggal_terima 		= $tgl_terima;
                                $data->created_by 			= $this->karyawan;
                                $data->created_at 			= DATE('Y-m-d H:i:s');
                                $data->save();

                                $datas = new FunctionValue();
                                $result = $datas->Colorimetri($par->id, $request, '', '');
                                
                                WsValueAir::create($result);

                                return response()->json([
                                    'message'=> 'Value Parameter berhasil disimpan.!',
                                    'par' => $request->parameter
                                ], 200);
                            }
						}
					} else {
						return response()->json([
			                'message'=> 'No Sample tidak ditemukan'
			            ], 401);
					}
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='duplo') {
					return response()->json([
		                'message'=> 'This action not suitable'
		            ], 401);
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='spike') {
					return response()->json([
		                'message'=> 'This action not suitable'
		            ], 401);
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='retest') {
					if(isset($request->no_sample) && $request->no_sample!=null){
						$hp = $request->hp;
						$fp = $request->fp;

							$parame = $request->parameter;

							$par = Parameter::where('nama_lab', $parame)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
							$check = OrderDetail::where('no_sampel',$request->no_sample)->where('is_active',true)->first();
							$id_po = '';
							$tgl_terima = '';

							if(isset($check->id)){
								$id_po = $check->id;
								$tgl_terima = $check->tanggal_terima;
							}
							else{
								return response()->json([
					                'message'=> 'No Sample tidak ada.!!'
					            ], 401);
							}
							$cari = Colorimetri::where('no_sampel', $request->no_sample)
							->where('parameter', $request->parameter)
							->where('is_active',true)
							->get();
							$n = count($cari);

                            $datas = new FunctionValue();
                            $checkParam = $datas->Colorimetri($par->id, $request, $n, '');
                            if($checkParam == 'gagal') {
                                return response()->json([
					                'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
					            ], 401);
                            }else {
                                $data = new Colorimetri;
                                $data->no_sampel 			= $request->no_sample;
                                $data->param 				= $request->parameter;
                                $data->par 					= $request->par;
                                $data->jenis_pengujian 		= $request->jenis_pengujian;
                                $data->hp 					= $request->hp;  //volume sample
                                if($request->parameter=='Persistent Foam'){$data->fp = $request->waktu;}else{$data->fp = $request->fp;}  //faktor pengenceran
                                $data->note 				= $request->note;
                                $data->tanggal_terima 		= $tgl_terima;
                                $data->created_by 			= $this->karyawan;
                                $data->created_at 			= DATE('Y-m-d H:i:s');
                                $data->status 				= $n;
                                $data->save();

                                $datas = new FunctionValue();
                                $result = $datas->Colorimetri($par->id, $request, $n, '');
                                
                                WsValueAir::create($result);

                                return response()->json([
                                    'message'=> 'Value Parameter berhasil disimpan.!',
                                    'par' => $request->parameter
                                ], 200);
                            }
					} else {
						return response()->json([
			                'message'=> 'No Sample tidak ditemukan'
			            ], 401);
					}
				} else {
					return response()->json([
		                'message'=> 'Pilih jenis pengujian'
		            ], 401);
				}
            }else if(($stp->name == 'SPEKTRO UV-VIS' || $stp->name == 'ICP' || $stp->name == 'GRAVIMETRI') && $stp->sample->nama_kategori == 'Udara'){
				// dd($request->all(), 'alif');
               		$po = OrderDetail::where('no_sampel', $request->no_sample)
						->where('is_active', true)
						->first();

                    $par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
					if($par->id == 633|| $par->id == 634|| $par->id == 635|| $par->id == 222){
						$datalapangan = DataLapanganDebuPersonal::where('no_sampel', $request->no_sample)->get();
						$param = [633, 634, 222, 635]; //[PM 10 (Personil),PM 2.5 (Personil),DEBU (P8J), Karbon Hitam (8 jam)]
						// dd($datalapangan);
						if (!in_array($par->id, $param)) {
							return response()->json([
								'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
							], 401);
						} else {
							$wsling = DebuPersonalHeader::where('no_sampel', $request->no_sample)
								->where('parameter', $request->parameter)
								->where('is_active', true)->first();
							// dd($wsling);
							if ($wsling) {
								return response()->json([
									'message' => 'Parameter sudah diinput..!!'
								], 401);
							}else{
								if (!$datalapangan->isEmpty()){
									try {
										$total_data = count($datalapangan);
										$avgFlow = []; // Total FLOW diambil dari rata-rata Shift L1-L8
										$avgWaktu = []; // Total waktu pengambilan sampel
										$avgTekananUdara = []; // Total tekanan udara
										$avgSuhu = []; // Total suhu
										if ($total_data > 0 || $total_data != '') {
											foreach ($datalapangan as $key => $value) {
												$dataflow = $value->flow;
												$datawaktu = $value->total_waktu;
												$datatekananudara = $value->tekanan_udara;
												$datasuhu = $value->suhu;
												
												// Tambahkan nilai flow, total_waktu, tekanan_udara, dan suhu ke dalam array
												$avgFlow[] = $dataflow;
												$avgWaktu[] = $datawaktu;
												$avgTekananUdara[] = $datatekananudara;
												$avgSuhu[] = $datasuhu;
											}
			
											// dd($avgFlow, $avgWaktu, $avgTekananUdara, $avgSuhu);
											// Menghitung total flow, total waktu, total tekanan udara, dan total suhu
											$total_flow = array_sum($avgFlow); // Jumlahkan semua nilai flow
											$total_waktu = array_sum($avgWaktu); // Jumlahkan semua nilai total_waktu
											$total_tekanan_udara = array_sum($avgTekananUdara); // Jumlahkan semua nilai tekanan_udara
											$total_suhu = array_sum($avgSuhu); // Jumlahkan semua nilai suhu
			
											// Jika ingin mendapatkan rata-rata, Anda bisa menghitungnya seperti ini
											$average_flow = $total_data > 0 ? number_format($total_flow / $total_data, 1) : 0; // Rata-rata FLOW diambil dari rata-rata Shift L1-L8
											$average_waktu = $total_data > 0 ? number_format($total_waktu, 1) : 0; //Total waktu pengambilan sampel
											$average_tekanan_udara = $total_data > 0 ? number_format($total_tekanan_udara / $total_data, 1) : 0; // Rata-rata tekanan udara
											$average_suhu = $total_data > 0 ? number_format($total_suhu / $total_data, 1) : 0; // Rata-rata suhu
										}
									} catch (\Exception $e) {
										return response()->json([
											'message' => 'Error : ' . $e->getMessage(),
										], 500);
									}
								}else{
									$average_flow = 0;
									$average_waktu = 0;
									$average_tekanan_udara = 0;
									$average_suhu = 0;
								}

								// dd($average_flow, $average_waktu, $average_tekanan_udara, $average_suhu);
			
								try {
									DB::beginTransaction();
									$header = new DebuPersonalHeader;
									$header->no_sampel = $request->no_sample;
									$header->parameter = $request->parameter;
									$header->template_stp = $request->par;
									$header->id_parameter = $par->id;
									$header->note = $request->note;
									$header->tanggal_terima = $po->tanggal_terima;
									$header->created_by = $this->karyawan;
									$header->created_at = DATE('Y-m-d H:i:s');
									$header->save();
									
									// dd($header);
									$rumus = new FunctionValue();
									$result = $rumus->DebuPersonal($average_flow, $average_waktu, $po->tanggal_terima, $request, $this->karyawan, $par->id, $average_tekanan_udara, $average_suhu);
									
                                    WsValueLingkungan::create($result);
			
									DB::commit();

									return response()->json([
										'message' => 'Value Parameter berhasil disimpan.!',
										'par' => $request->parameter
									], 200);

									DB::commit();
									return response()->json([
										'message' => 'Transaksi berhasil diselesaikan.',
									], 200);
								} catch (\Exception $e) {
									DB::rollback();
									return response()->json([
										'message' => 'Error : ' . $e->getMessage(),
									], 500);
								}
							}
						}
					}else{
                        // dd('masuk');
						$datlapanganh = DataLapanganLingkunganHidup::where('no_sampel', $request->no_sample)->first();
						$datlapangank = DataLapanganLingkunganKerja::where('no_sampel', $request->no_sample)->first();
                        // dd($datlapanganh,$datlapangank);
						$param = [293, 294, 295, 296, 326, 327, 328, 329, 299, 300, 289, 290, 291, 246, 247, 248, 249, 342, 343, 344, 345, 261, 256, 211, 310, 311, 312, 313, 314, 315, 568, 211, 564, 305, 306, 307, 308, 234, 569, 287, 292, 219];
						if (!in_array($par->id, $param)) {
							return response()->json([
								'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
							], 404);
						} else {
								$wsling = LingkunganHeader::where('no_sampel', $request->no_sample)->where('parameter', $request->parameter)->where('is_active', true)->first();
								
								if ($wsling) {
									return response()->json([
										'message' => 'Parameter sudah diinput..!!'
									], 403);
								} else {
									if ($datlapanganh != null || $datlapangank != null) {
											$lingHidup = DetailLingkunganHidup::where('no_sampel', $request->no_sample)->where('parameter', $request->parameter)->get();
											$lingKerja = DetailLingkunganKerja::where('no_sampel', $request->no_sample)->where('parameter', $request->parameter)->get();
											
										if (!$lingHidup->isEmpty() || !$lingKerja->isEmpty()) {
											
											try {
												$datapangan = '';
												if (count($lingHidup) > 0) {
													$datapangan = $lingHidup;
												} 
												if (count($lingKerja) > 0) {
													$datapangan = $lingKerja;
												}
												// dd($datapangan);
												if($datapangan != '') {
													$datot = count($datapangan);
												}else {
													$datot = '';
												}
												$rerata = [];
												$durasi = [];
												$tekanan_u = [];
												$suhu = [];
												$Qs = [];
												$nilQs = '';
												if ($datot > 0 || $datot != '') {
													
													foreach ($datapangan as $keye => $vale) {
														$dat = json_decode($vale->pengukuran);
														$durasii = [];
														$flow = [];
														foreach ($dat as $key => $val) {
															if ($key == 'Durasi' || $key == 'Durasi 2') {
																$formt = (int) str_replace(" menit", "", $val);
																array_push($durasii, $formt);
															} else {
																array_push($flow, $val);
															}
														}
														$rera = array_sum($flow) / count($flow);
														// $Q0 = \str_replace(",", "", number_format($rera * ((298 * $vale->tekanan_u) / (($vale->suhu + 273) * 760) ** 1 / 2), 4));
														// $Q0 = \str_replace(",", "", number_format($rera * ((298 * $vale->tekanan_u) / (($vale->suhu + 273) * 760) ** 0.5), 4));
														
														// Menghitung Q0 sesuai rumus yang benar
														$Q0 = $rera * pow((298 * $vale->tekanan_udara) / (($vale->suhu + 273) * 760), 0.5);

														// Format hasil Q0 agar 4 desimal dan hilangkan koma pemisah ribuan
														$Q0 = str_replace(",", "", number_format($Q0, 4));

														$dur = array_sum($durasii);
														array_push($rerata, $rera);
														array_push($Qs, (float) $Q0);
														array_push($durasi, $dur);
														array_push($tekanan_u, $vale->tekanan_udara);
														array_push($suhu, $vale->suhu);
													}
													if (!empty ($Qs)) {
														$nilQs = array_sum($Qs) / $datot;
													}
													// dd($nilQs);
													$rerataFlow = \str_replace(",", "", number_format(array_sum($rerata) / $datot, 1));
													if (count($durasi) == 1) {
														$durasiFin = $durasi[0];
													} else {
														$durasiFin = array_sum($durasi) / $datot;
													}
													if( $request->parameter == 'Pb (24 Jam)' || $request->parameter == 'PM 2.5 (24 Jam)' || $request->parameter == 'PM 10 (24 Jam)' || $request->parameter == 'TSP (24 Jam)' || $par->id ==  306) {
														$l25 = '';
														if (count($lingHidup) > 0) {
															// $l25 = array_filter($lingHidup->toArray(), function ($var) {
															// 	return ($var['shift2'] == 'L25');
															// });
															$l25 = DetailLingkunganHidup::where('no_sampel', $request->no_sample)->where('parameter', $request->parameter)->where('shift2', 'L25')->first();
															if($l25) {
																$waktu = explode(",",$l25->durasi_pengambilan);
																$jam = preg_replace('/\s+/', '', ($waktu[0] != '') ? str_replace("Jam", "", $waktu[0]) : 0);
																$menit = preg_replace('/\s+/', '', ($waktu[1] != '') ? str_replace("Menit", "", $waktu[1]) : 0);
																$durasiFin = ((int)$jam * 60) + (int)$menit;
															}else {
																$durasiFin = 24 * 60;
															}
														} 
														if (count($lingKerja) > 0) {
															// dd($lingKerja);
															// $l25 = array_filter($lingKerja->toArray(), function ($var) {
															// 	return ($var['shift2'] == 'L25');
															// });
															$l25 = DetailLingkunganKerja::where('no_sampel', $request->no_sample)->where('parameter', $request->parameter)->where('shift2', 'L25')->first();
															// dd($l25);
															if($l25) {
																$waktu = explode(",",$l25->durasi_pengambilan);
																$jam = preg_replace('/\s+/', '', ($waktu[0] != '') ? str_replace("Jam", "", $waktu[0]) : 0);
																$menit = preg_replace('/\s+/', '', ($waktu[1] != '') ? str_replace("Menit", "", $waktu[1]) : 0);
																$durasiFin = ((int)$jam * 60) + (int)$menit;
															}else {
																$durasiFin = 24 * 60;
															}
															// dd('masukkk');
														}
													}
													$tekananFin = \str_replace(",", "", number_format(array_sum($tekanan_u) / $datot, 1));
													$suhuFin = \str_replace(",", "", number_format(array_sum($suhu) / $datot, 1));
			
												} else {
													return response()->json([
														'message' => 'No sample tidak ada di lingkungan hidup atau lingkungan kerja.',
													], 404);
												}
											} catch (\Exception $e) {
												// dd($e);
												return response()->json([
													'message' => 'Error : ' . $e->getMessage(),
												], 500);
											}
										} else {
												$tekananFin = 0;
												$suhuFin = 0;
												$nilQs = 0;
												$datot = 0;
												$rerataFlow = 0;
												$durasiFin = 0;
										}
									} else {
										$tekananFin = 0;
										$suhuFin = 0;
										$nilQs = 0;
										$datot = 0;
										$rerataFlow = 0;
										$durasiFin = 0;
									}

									// dd($durasiFin, $tekananFin, $suhuFin, $nilQs, $datot, $rerataFlow);
		
									try {
										DB::beginTransaction();
										$data = new LingkunganHeader;
										$data->no_sampel = $request->no_sample;
										$data->parameter = $request->parameter;
										$data->template_stp = $request->par;
										$data->id_parameter = $par->id;
										$data->note = $request->note;
										$data->tanggal_terima = $po->tanggal_terima;
										$data->created_by = $this->karyawan;
										$data->created_at = DATE('Y-m-d H:i:s');
										$data->save();
										
										$datas = new FunctionValue();
										$result = $datas->valLingHidup($nilQs, $datot, $rerataFlow, $durasiFin, $po->tanggal_terima, $tekananFin, $suhuFin, $request, $this->karyawan, $par->id);
										// dd($nilQs, $datot, $rerataFlow, $durasiFin, $po->id, $po->tgl_terima, $tekananFin, $suhuFin, $request, $this->userid, $par->id, $result);
										// dd($result);
										WsValueLingkungan::create($result);
				
										DB::commit();
		
										return response()->json([
											'message' => 'Value Parameter berhasil disimpan.',
											'par' => $request->parameter
										], 200);
		
									} catch (\Exception $e) {
										DB::rollback();
										return response()->json([
											'message' => 'Error : ' . $e->getMessage(),
										], 500);
									}
								}
						}
					}
            }else if(($stp->name == 'SPEKTRO UV-VIS' || $stp->name == 'ICP' || $stp->name == 'GRAVIMETRI') && $stp->sample->nama_kategori == 'Emisi'){
				$datlapangan = DataLapanganEmisiCerobong::where('no_sampel', $request->no_sample)->first();
				if(!$datlapangan) {
					return response()->json([
						'message' => 'No Sample tidak ada di data lapangan emisi cerobong.'
					],404);
				}
				$po = OrderDetail::where('no_sampel', $request->no_sample)->where('is_active',true)->first();
				$wsemisi = EmisiCerobongHeader::where('no_sampel', $request->no_sample)->where('parameter', $request->parameter)->where('is_active',true)->first();
				$par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();

				if($wsemisi) {
					return response()->json([
						'message'=> 'Parameter sudah diinput..!!'
					], 403);
				}else {
						$param = [365, 368, 364, 360, 377, 354, 358, 378, 385];
						if($par->id == '355'){
							
							$data_ci2_json = json_decode($datlapangan->CI2);
							$data_ci_toArray = explode(";", $data_ci2_json[0]);
							$nilaiDgm = null;
							$tekanan_meteran = null;
							foreach ($data_ci_toArray as $item) {
								if (strpos($item, "Volume") !== false) {
									// Menghilangkan spasi di sekitar string
									$item = str_replace(' ', '', $item);
									// Memecah string berdasarkan delimiter ":"
									$volumeData = explode(":", $item);
									$nilaiDgm = $volumeData[1];
								}else if(strpos($item, "Tekanan") !== false){
									$item = str_replace(' ', '', $item);
									$tekananData = explode(":", $item);
									$tekanan_meteran = $tekananData[1];
								}
							}
							// dd($datlapangan);
							$datL_suhu = $datlapangan->suhu;
							$tekanan_udara = $datlapangan->tekanan_udara;
							$kons_klorin = $request->konsentrasi_klorin;
							$volumeSample = $request->volume_sample;
							$kons_blanko = $request->konsentrasi_blanko;
							$note = $request->note;

							$tekananAir = number_format(self::KonversiTekananUapAir($datL_suhu),4); //udah mmHg

							try {
								DB::beginTransaction();
								$dataHeader = new EmisiCerobongHeader;
								$dataHeader->no_sampel = $request->no_sample;
								$dataHeader->parameter = $request->parameter;
								$dataHeader->template_stp = $request->par;
								$dataHeader->id_parameter = $par->id;
								$dataHeader->note = $request->note;
								$dataHeader->tanggal_terima = $po->tanggal_terima;
								$dataHeader->created_by = $this->karyawan;
								$dataHeader->created_at = DATE('Y-m-d H:i:s');
								$dataHeader->save();

								$datas = new FunctionValue();
								$result = $datas->Cl2($nilaiDgm, $datL_suhu ,$tekanan_udara, $tekanan_meteran,$tekananAir,$kons_blanko,$kons_klorin,$volumeSample , $po->tanggal_terima, $request, $this->karyawan, $par->id, $dataHeader->id);
								
								WsValueAir::create($result);
						
								DB::commit();
								return response()->json([
									'message' => 'Value Parameter berhasil disimpan.!',
									'par' => $request->parameter
								], 200);
							} catch (\Exception $th) {
								DB::rollback();
								return response()->json([
									'line' => $th->getLine(),
									'message' => 'Error : ' . $th->getMessage(),
								]);
							}
						}else if (!in_array($par->id, $param)) {
							return response()->json([
								'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
								], 401);
						}
						else {
							if ($datlapangan) {
								$tekanan = (float) $datlapangan->tekanan_udara;
								$t_flue = (float) $datlapangan->T_Flue;
								$suhu = (float) $datlapangan->suhu;
								$nil_pv = self::penentuanPv($suhu);
								$status_par = $request->parameter;
								if ($request->parameter == 'HF') {
									$dat = json_decode($datlapangan->HF);
								} else if ($request->parameter == 'NH3') {
									$dat = json_decode($datlapangan->NH3);
								} else if ($request->parameter == 'HCl') {
									$dat = json_decode($datlapangan->HCI);
								}else if($request->parameter == 'Debu' || $request->parameter == 'Partikulat' || $request->parameter == 'Cd' || $request->parameter == 'Cr' || $request->parameter == 'Pb' || $request->parameter == 'Zn') {
									$dat = json_decode($datlapangan->partikulat);
									$status_par = 'Partikulat';
								}
							
							if ($datlapangan->tipe == '1') {
								
								if($dat != null) {
									$nil_dry = explode("; ", $dat[0]);
									$nil_dry = explode(":", $nil_dry[4]);
									$nil_dry = str_replace(" ", "", $nil_dry[1]);
									// dd($nil_dry);
									$tekanan_dry = (float) $nil_dry;
	
									$nil_vol = explode("; ", $dat[0]);
									$nil_vol = explode(":", $nil_vol[3]);
									$nil_vol = str_replace(" ", "", $nil_vol[1]);
									$volume_dry = (float) $nil_vol;

									$dura = explode("; ", $dat[0]);
									$dura = explode(":", $dura[2]);
									$dura = str_replace(" ", "", $dura[1]);
									$durasi_dry = (float) $dura;

									$awal = explode("; ", $dat[0]);
									$awal = explode(":", $awal[0]);
									$awal = str_replace(" ", "", $awal[1]);
									$awal_dry = (float) $awal;

									$akhir = explode("; ", $dat[0]);
									$akhir = explode(":", $akhir[1]);
									$akhir = str_replace(" ", "", $akhir[1]);
									$akhir_dry = (float) $akhir;
									$flow = $akhir_dry + $awal_dry / 2;
								}else {
									return response()->json([
										'message' => 'Tidak ditemukan pada data lapangan parameter : ' . $status_par . '',
									], 401);
								}
							} else if ($datlapangan->tipe == '2') {
								$tekanan_dry = 0;
								$volume_dry = 0;
								$durasi_dry = 0;
								$awal_dry = 0;
								$akhir_dry = 0;
								$flow = 0;
							}
							
						} else {
							$tekanan_dry = 0;
							$volume_dry = 0;
							$durasi_dry = 0;
							$awal_dry = 0;
							$akhir_dry = 0;
							$flow = 0;
							$tekanan = 0;
							$t_flue = 0;
							$suhu = 0;
							$nil_pv = 0;
						}
						// dd('Test');
							$data = new EmisiCerobongHeader;
							$data->no_sampel = $request->no_sample;
							$data->parameter = $request->parameter;
							$data->template_stp = $request->par;
							$data->id_parameter = $par->id;
							$data->note = $request->note;
							$data->tanggal_terima = $po->tanggal_terima;
							$data->created_by = $this->karyawan;
							$data->created_at = DATE('Y-m-d H:i:s');
							$data->save();

							$datas = new FunctionValue();
							$result = $datas->valemisic($tekanan, $t_flue, $suhu, $nil_pv, $tekanan_dry, $volume_dry, $durasi_dry, $flow, $po->tanggal_terima, $request, $this->karyawan, $par->id);
							// dd($result);
							// dd($tekanan, $t_flue, $suhu, $nil_pv, $tekanan_dry, $volume_dry, $durasi_dry, $flow, $po->id, $po->tgl_terima, $request, $result);
							ValueEmisiC::create($result);

							return response()->json([
								'message' => 'Value Parameter berhasil disimpan.!',
								'par' => $request->parameter
							], 200);
						}
				}
            }else if($stp->name == 'DIRECT READING' && $stp->sample->nama_kategori == 'Udara'){
				if(isset($request->jenis_pengujian) && $request->jenis_pengujian=='sample'){
	        		if(isset($request->no_sample) && $request->no_sample!=null){
						$po = OrderDetail::where('no_sampel', $request->no_sample)
							->where('is_active', true)
							->first();
						
                        $par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
						
						$header = DustFallHeader::where('no_sampel', $request->no_sample)
							->where('parameter', $request->parameter)
							->where('is_active', true)->first();

						if($par->id == '223') {
							if($header) {
								return response()->json([
									'message' => 'Parameter sudah diinput..!!'
								], 401);
							}else{
								$id_po = '';
								$tgl_terima = '';

								if(isset($po->id)){
									$id_po = $po->id;
									$tgl_terima = $po->tgl_terima;
								}
								else{
									return response()->json([
										'message'=> 'No Sample tidak ada.!!'
									], 401);
								}
								try {
									DB::beginTransaction();
									
									$header = new DustFallHeader();
									$header->no_sampel = $request->no_sample;
									$header->parameter = $request->parameter;
									$header->template_stp = $request->par;
									$header->id_parameter = $par->id;
									$header->note = $request->note;
									$header->tanggal_terima = $tgl_terima;
									$header->active = 0;
									$header->add_by = $this->userid;
									$header->add_at = DATE('Y-m-d H:i:s');
									$header->save();

									$rumus = new FunctionValue();
									$result = $rumus->valDustfall($request, $par->id, $po->tanggal_terima, $this->karyawan);
									DB::table('ws_value_lingkungan')->insert($result);

									DB::commit();
									$this->resultx = 'Value Parameter berhasil disimpan.!';
									Helpers::saveToLogRequest($this->pathinfo, $this->globaldate, $this->param, $this->useragen, $this->resultx, $this->ip);
									return response()->json([
										'message'=> $this->resultx,
										'par' => $request->parameter
									], 200);
								} catch (\Exception $e) {
									DB::rollback();
									return response()->json([
										'message' => 'Error: ' . $e->getMessage()
									], 401);
								}
							}
						} else {
							return response()->json([
								'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
							], 401);
						}
					}
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='duplo') {
					return response()->json([
		                'message'=> 'This action not suitable'
		            ], 401);
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='spike') {
					return response()->json([
		                'message'=> 'This action not suitable'
		            ], 401);
				} else if (isset($request->jenis_pengujian) && $request->jenis_pengujian=='retest') {
					if(isset($request->no_sample) && $request->no_sample!=null){
						$po = OrderDetail::where('no_sampel', $request->no_sample)
							->where('is_active', true)
							->first();
						
						$par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
						
						$header = DustfallHeader::where('no_sampel', $request->no_sample)
							->where('parameter', $request->parameter)
							->where('is_active', true)->first();

						if($par->id == '223') {
							if($header) {
								return response()->json([
									'message' => 'Parameter sudah diinput..!!'
								], 401);
							}else{
								$id_po = '';
								$tgl_terima = '';

								if(isset($po->id)){
									$id_po = $po->id;
									$tgl_terima = $po->tgl_terima;
								}
								else{
									return response()->json([
										'message'=> 'No Sample tidak ada.!!'
									], 401);
								}
								try {
									DB::beginTransaction();
									
									$header = new DustfallHeader();
									$header->no_sampel = $request->no_sample;
									$header->parameter = $request->parameter;
									$header->par = $request->par;
									$header->id_parameter = $par->id;
									$header->note = $request->note;
									$header->tgl_terima = $po->tgl_terima;
									$header->active = 0;
									$header->add_by = $this->userid;
									$header->add_at = DATE('Y-m-d H:i:s');
									$header->save();

									$rumus = new FunctionValue();
									$result = $rumus->valDustfall($id_po, $request, $par->id, $po->tgl_terima, $this->userid);

									WsValueLingkungan::create($result);

									DB::commit();

									return response()->json([
										'message'=> 'Value Parameter berhasil disimpan.!',
										'par' => $request->parameter
									], 200);
								} catch (\Exception $e) {
									DB::rollback();
									return response()->json([
										'message' => 'Error: ' . $e->getMessage()
									], 401);
								}
							}
						}
					}else {
						return response()->json([
			                'message'=> 'No Sample tidak ditemukan'
			            ], 401);
					}
				} else {
					return response()->json([
		                'message'=> 'Pilih jenis pengujian'
		            ], 401);
				}
			}else if($stp->name == 'MIKROBIOLOGI' || $stp->sample->nama_kategori == 'Udara'){
				if (isset($request->jenis_pengujian)) {
					// Jenis Pengujian: sample
					if ($request->jenis_pengujian == 'sample') {
						if (isset($request->no_sample) && $request->no_sample != null) {
							$po = OrderDetail::where('no_sampel', $request->no_sample)
								->where('is_active', true)
								->first();
				
                            $par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
				
							$id_param = [587, 586, 266, 235, 619, 620];
				
							if (in_array($par->id, $id_param)) {
								$fdl = DetailMicrobiologi::where('no_sampel', $request->no_sample)
									->where('is_active', true)
									->where('parameter', $request->parameter)
									->first();
				
								$header = MicrobioHeader::where('no_sampel', $request->no_sample)
									->where('parameter', $request->parameter)
									->where('is_active', true)
									->first();
				
								if ($header) {
									return response()->json([
										'message' => 'Parameter sudah diinput..!!'
									], 401);
								}
				
								if ($fdl) { // Periksa apakah $fdl tidak null
									try {
										// Ambil data suhu, tekanan, dan kelembaban
										$suhu = $fdl->suhu;
										$tekanan = $fdl->tekanan_udara;
										$kelembaban = $fdl->kelem;
				
										// Decode JSON di dalam pengukuran
										$pengukuran = json_decode($fdl->pengukuran);
				
										// Ambil nilai Flow Rate dan Durasi
										$flowRate = (float) ($pengukuran->{"Flow Rate"} ?? null);
										$durasi = (float) preg_replace('/\D/', '', $pengukuran->Durasi) ?? null;
				
										$volume = ($flowRate * $durasi) / 1000;
				
									} catch (\Exception $e) {
										return response()->json([
											'message' => 'Error: ' . $e->getMessage()
										], 500);
									}
				
									try {
										// Mulai transaksi
										DB::beginTransaction();
				
										// Simpan data ke tabel Microbioheader
										$header = new MicrobioHeader();
										$header->no_sampel = $request->no_sample;
										$header->param = $request->parameter;
										$header->par = $request->par;
										$header->id_parameter = $par->id;
										$header->note = $request->note;
										$header->tgl_terima = $po->tgl_terima;
										$header->add_by = $this->userid;
										$header->add_at = now();
										$header->save();
				
										// Hitung hasil menggunakan fungsi Microbio
										$rumus = new FunctionValue();
										$result = $rumus->Microbio(
											$volume,
											$flowRate,
											$durasi,
											$suhu,
											$tekanan,
											$kelembaban,
											$po->tanggal_terima,
											$request,
											$this->karyawan,
											$par->id,
											$header->id
										);
				
										// Simpan hasil ke tabel ws_value_microbio
										WsValueMicrobio::create($result);
				
										// Commit transaksi jika semua berhasil
										DB::commit();
				
										return response()->json([
											'message' => 'Value Parameter berhasil disimpan.!',
											'par' => $request->parameter,
										], 200);
				
									} catch (\Exception $e) {
										// Rollback transaksi jika terjadi kesalahan
										DB::rollBack();
				
										return response()->json([
											'message' => 'Error: ' . $e->getMessage(),
										], 500);
									}
								} else {
									return response()->json([
										'message' => 'Data tidak ditemukan untuk sample yang diberikan.'
									], 404);
								}
							}
						}
					} 
				
					// Jenis Pengujian: duplo, spike, atau lainnya
					elseif (in_array($request->jenis_pengujian, ['duplo', 'spike'])) {
						return response()->json([
							'message' => 'This action not suitable'
						], 401);
					}
				
					// Jenis Pengujian: retest
					elseif ($request->jenis_pengujian == 'retest') {
						if (isset($request->no_sample) && $request->no_sample != null) {
							$po = OrderDetail::where('no_sampel', $request->no_sample)
								->where('is_active', true)
								->first();
				
                            $par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
				
							$id_param = [587, 586, 266, 235, 619, 620];
				
							if (in_array($par->id, $id_param)) {
								$fdl = DetailMicrobiologi::where('no_sampel', $request->no_sample)
									->where('is_active', true)
									->where('parameter', $request->parameter)
									->first();
				
								$header = MicrobioHeader::where('no_sampel', $request->no_sample)
									->where('parameter', $request->parameter)
									->where('is_active', true)
									->first();
				
								if ($header) {
									return response()->json([
										'message' => 'Parameter sudah diinput..!!'
									], 401);
								}
				
								if ($fdl) { // Periksa apakah $fdl tidak null
									try {
										// Ambil data suhu, tekanan, dan kelembaban
										$suhu = $fdl->suhu;
										$tekanan = $fdl->tekanan_udara;
										$kelembaban = $fdl->kelem;
				
										// Decode JSON di dalam pengukuran
										$pengukuran = json_decode($fdl->pengukuran);
				
										// Ambil nilai Flow Rate dan Durasi
										$flowRate = (float) ($pengukuran->{"Flow Rate"} ?? null);
										$durasi = (float) preg_replace('/\D/', '', $pengukuran->Durasi) ?? null;
				
										$volume = ($flowRate * $durasi) / 1000;
				
									} catch (\Exception $e) {
										return response()->json([
											'message' => 'Error: ' . $e->getMessage()
										], 500);
									}
				
									try {
										// Mulai transaksi
										DB::beginTransaction();
				
										// Simpan data ke tabel Microbioheader
										$header = new MicrobioHeader();
										$header->no_sampel = $request->no_sample;
										$header->parameter = $request->parameter;
										$header->template_stp = $request->par;
										$header->id_parameter = $par->id;
										$header->note = $request->note;
										$header->tanggal_terima = $po->tanggal_terima;
										$header->created_by = $this->karyawan;
										$header->created_at = now();
										$header->save();
				
										// Hitung hasil menggunakan fungsi Microbio
										$rumus = new FunctionValue();
										$result = $rumus->Microbio(
											$volume,
											$flowRate,
											$durasi,
											$suhu,
											$tekanan,
											$kelembaban,
											$po->tanggal_terima,
											$request,
											$this->karyawan,
											$par->id,
											$header->id
										);
				
										// Simpan hasil ke tabel ws_value_microbio
										WsValueMicrobio::create($result);
				
										// Commit transaksi jika semua berhasil
										DB::commit();
				
										return response()->json([
											'message' => 'Value Parameter berhasil disimpan.!',
											'par' => $request->parameter,
										], 200);
				
									} catch (\Exception $e) {
										// Rollback transaksi jika terjadi kesalahan
										DB::rollBack();
				
										return response()->json([
											'message' => 'Error: ' . $e->getMessage(),
										], 500);
									}
								} else {
									return response()->json([
										'message' => 'Data tidak ditemukan untuk sample yang diberikan.'
									], 404);
								}
							}
						}
					} 
					// Jika jenis pengujian tidak dikenali
					else {
						return response()->json([
							'message' => 'Pilih jenis pengujian'
						], 401);
					}
				} else {
					return response()->json([
						'message' => 'Jenis pengujian tidak ada.'
					], 401);
				}				
			}else if($stp->name == 'SWAB TEST' && $stp->sample->nama_kategori == 'Udara'){
				if (isset($request->jenis_pengujian)) {
					// Jenis Pengujian: sample
					if ($request->jenis_pengujian == 'sample') {
						if (isset($request->no_sample) && $request->no_sample != null) {
							$po = OrderDetail::where('no_sampel', $request->no_sample)
								->where('is_active', true)
								->first();
				
                            $par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
				
							$id_param = [337, 227, 340];
				
							if (in_array($par->id, $id_param)) {
								$fdl = DataLapanganSwab::where('no_sampel', $request->no_sample)->first();
				
								$header = SwabTestHeader::where('no_sampel', $request->no_sample)
									->where('parameter', $request->parameter)
									->where('is_active', true)
									->first();
				
								if ($header) {
									return response()->json([
										'message' => 'Parameter sudah diinput..!!'
									], 401);
								}
				
								if ($fdl) { // Periksa apakah $fdl tidak null
									try {
										// Ambil data suhu, tekanan, dan kelembaban
										$luas = $fdl->luas;
				
									} catch (\Exception $e) {
										return response()->json([
											'message' => 'Error: ' . $e->getMessage()
										], 500);
									}
				
									try {
										// Mulai transaksi
										DB::beginTransaction();
				
										// Simpan data ke tabel SwabTestHeader
										$header = new SwabTestHeader();
										$header->no_sampel = $request->no_sample;
										$header->parameter = $request->parameter;
										$header->template_stp = $request->par;
										$header->id_parameter = $par->id;
										$header->note = $request->note;
										$header->tanggal_terima = $po->tanggal_terima;
										$header->created_by = $this->karyawan;
										$header->created_at = DATE('Y-m-d H:i:s');
										$header->save();
				
										// Hitung hasil menggunakan fungsi SwabTest
										$rumus = new FunctionValue();
										$result = $rumus->SwabTest(
											$luas,
											$po->tanggal_terima,
											$request,
											$this->karyawan,
											$par->id,
											$header->id
										);
				
										// Simpan hasil ke tabel ws_value_swabtest
										WsValueSwab::create($result);
				
										// Commit transaksi jika semua berhasil
										DB::commit();
				
										return response()->json([
											'message' => 'Value Parameter berhasil disimpan.!',
											'par' => $request->parameter,
										], 200);
				
									} catch (\Exception $e) {
										// Rollback transaksi jika terjadi kesalahan
										DB::rollBack();
				
										return response()->json([
											'message' => 'Error: ' . $e->getMessage(),
										], 500);
									}
								} else {
									return response()->json([
										'message' => 'Data tidak ditemukan untuk sample yang diberikan.'
									], 404);
								}
							}
						}
					} 
				
					// Jenis Pengujian: duplo, spike, atau lainnya
					elseif (in_array($request->jenis_pengujian, ['duplo', 'spike'])) {
						return response()->json([
							'message' => 'This action not suitable'
						], 401);
					}
				
					// Jenis Pengujian: retest
					elseif ($request->jenis_pengujian == 'retest') {
						if (isset($request->no_sample) && $request->no_sample != null) {
							$po = OrderDetail::where('no_sampel', $request->no_sample)
								->where('is_active', true)
								->first();
				
                            $par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
				
							$id_param = [337, 227, 340];
				
							if (in_array($par->id, $id_param)) {
								$fdl = DataLapanganSwab::where('no_sampel', $request->no_sample)->first();
				
								$header = SwabTestHeader::where('no_sampel', $request->no_sample)
									->where('parameter', $request->parameter)
									->where('is_active', true)
									->first();
				
								if ($header) {
									return response()->json([
										'message' => 'Parameter sudah diinput..!!'
									], 401);
								}
				
								if ($fdl) { // Periksa apakah $fdl tidak null
									try {
										// Ambil data suhu, tekanan, dan kelembaban
										$luas = $fdl->luas;
				
									} catch (\Exception $e) {
										return response()->json([
											'message' => 'Error: ' . $e->getMessage()
										], 500);
									}
				
									try {
										// Mulai transaksi
										DB::beginTransaction();
				
										// Simpan data ke tabel SwabTestHeader
										$header = new SwabTestHeader();
										$header->no_sampel = $request->no_sample;
										$header->parameter = $request->parameter;
										$header->template_stp = $request->par;
										$header->id_parameter = $par->id;
										$header->note = $request->note;
										$header->tanggal_terima = $po->tanggal_terima;
										$header->add_by = $this->userid;
										$header->add_at = DATE('Y-m-d H:i:s');
										$header->save();
				
										// Hitung hasil menggunakan fungsi SwabTest
										$rumus = new FunctionValue();
										$result = $rumus->SwabTest(
											$luas,
											$po->tanggal_terima,
											$request,
											$this->karyawan,
											$par->id,
											$header->id
										);
				
										// Simpan hasil ke tabel ws_value_swabtest
										WsValueSwab::create($result);
				
										// Commit transaksi jika semua berhasil
										DB::commit();
				
										return response()->json([
											'message' => 'Value Parameter berhasil disimpan.!',
											'par' => $request->parameter,
										], 200);
				
									} catch (\Exception $e) {
										// Rollback transaksi jika terjadi kesalahan
										DB::rollBack();
				
										return response()->json([
											'message' => 'Error: ' . $e->getMessage(),
										], 500);
									}
								} else {
									return response()->json([
										'message' => 'Data tidak ditemukan untuk sample yang diberikan.'
									], 404);
								}
							}
						}
					} 
					// Jika jenis pengujian tidak dikenali
					else {
						return response()->json([
							'message' => 'Pilih jenis pengujian'
						], 401);
					}
				} else {
					return response()->json([
						'message' => 'Jenis pengujian tidak ada.'
					], 401);
				}				
			}else if($stp->name == 'ISOKINETIK' && $stp->sample->nama_kategori == 'Emisi'){
				// if (isset($request->jenis_pengujian)) {
				// 	// Jenis Pengujian: sample
				// 	if ($request->jenis_pengujian == 'sample') {
				// 		if (isset($request->no_sample) && $request->no_sample != null) {
				// 			$po = OrderDetail::where('no_sampel', $request->no_sample)
				// 				->where('is_active', true)
				// 				->first();
				
                //             $par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori',$stp->category_id)->where('is_active',true)->first();
							
				// 			$fdl = DataLapanganIsokinetikHasil::where('no_sampel', $request->no_sample)
				// 			->where('is_active', true)
				// 			->first();
							
				// 			$header = IsokinetikHeader::where('no_sampel', $request->no_sample)
				// 			->where('parameter', $request->parameter)
				// 			->where('is_active', 0)
				// 			->first();
				// 			// if ($par->id == 366) {
				// 			if ($header) {
				// 				return response()->json([
				// 					'message' => 'Parameter sudah diinput..!!'
				// 				], 401);
				// 			}else{
				// 				if ($fdl) { // Periksa apakah $fdl tidak null
				// 					try {
				// 						// Ambil data vm(volume gas)
				// 						$vm = $fdl->v_gas;
				
				// 					} catch (\Exception $e) {
				// 						return response()->json([
				// 							'message' => 'Error: ' . $e->getMessage()
				// 						], 500);
				// 					}
				// 				} else {
				// 					return response()->json([
				// 						'message' => 'Data tidak ditemukan untuk sample yang diberikan.'
				// 					], 404);
				// 				}
								
				// 				try {
				// 					// Mulai transaksi
				// 					DB::beginTransaction();
			
				// 					// Simpan data ke tabel SwabTestHeader
				// 					$header = new IsokinetikHeader();
				// 					$header->no_sampel = $request->no_sample;
				// 					$header->parameter = $request->parameter;
				// 					$header->template_stp = $request->param;
				// 					$header->id_parameter = $par->id;
				// 					$header->note = $request->note;
				// 					$header->tanggal_terima = $po->tanggal_terima;
				// 					$header->add_by = $this->userid;
				// 					$header->add_at = DATE('Y-m-d H:i:s');
				// 					$header->save();
			
				// 					// Hitung hasil menggunakan fungsi SwabTest
				// 					$rumus = new FunctionValue();
				// 					$result = $rumus->Isokinetik(
				// 						$vm,
				// 						$po->id,
				// 						$po->tanggal_terima,
				// 						$request,
				// 						$this->karyawan,
				// 						$par->id,
				// 						$header->id
				// 					);

				// 					// Simpan hasil ke tabel ws_value_swabtest
				// 					DB::table('ws_value_isokinetik')->insert($result);
			
				// 					// Commit transaksi jika semua berhasil
				// 					DB::commit();
			
				// 					return response()->json([
				// 						'message' => 'Value Parameter berhasil disimpan.!',
				// 						'par' => $request->parameter,
				// 					], 200);
			
				// 				} catch (\Exception $e) {
				// 					// Rollback transaksi jika terjadi kesalahan
				// 					DB::rollBack();
			
				// 					return response()->json([
				// 						'message' => 'Error: ' . $e->getMessage(),
				// 					], 500);
				// 				}
				// 			}
				// 		}
				// 	} 
				
				// 	// Jenis Pengujian: duplo, spike, atau lainnya
				// 	elseif (in_array($request->jenis_pengujian, ['duplo', 'spike'])) {
				// 		return response()->json([
				// 			'message' => 'This action not suitable'
				// 		], 401);
				// 	}
				
				// 	// Jenis Pengujian: retest
				// 	elseif ($request->jenis_pengujian == 'retest') {
				// 		if (isset($request->no_sample) && $request->no_sample != null) {
				// 			$po = Po::where('no_sample', $request->no_sample)
				// 			->where('active', 0)
				// 			->first();
							
				// 			$par = Parameter::where('name', $request->parameter)
				// 			->where('category_sample', 5)
				// 			->where('active', 0)
				// 			->first();
							
				// 			$fdl = ValueHasilIsokinetik::where('no_sample', $request->no_sample)
				// 			->where('active', 0)
				// 			->first();
							
				// 			$header = IsokinetikHeader::where('no_sample', $request->no_sample)
				// 			->where('parameter', $request->parameter)
				// 			->where('active', 0)
				// 			->first();
				// 			// if ($par->id == 366) {
				// 			if ($header) {
				// 				return response()->json([
				// 					'message' => 'Parameter sudah diinput..!!'
				// 				], 401);
				// 			}else{
				// 				if ($fdl) { // Periksa apakah $fdl tidak null
				// 					try {
				// 						// Ambil data vm(volume gas)
				// 						$vm = $fdl->v_gas;
				
				// 					} catch (\Exception $e) {
				// 						return response()->json([
				// 							'message' => 'Error: ' . $e->getMessage()
				// 						], 500);
				// 					}
				// 				} else {
				// 					return response()->json([
				// 						'message' => 'Data tidak ditemukan untuk sample yang diberikan.'
				// 					], 404);
				// 				}
								
				// 				try {
				// 					// Mulai transaksi
				// 					DB::beginTransaction();
			
				// 					// Simpan data ke tabel SwabTestHeader
				// 					$header = new IsokinetikHeader();
				// 					$header->id_po = $po->id;
				// 					$header->no_sample = $request->no_sample;
				// 					$header->parameter = $request->parameter;
				// 					$header->par = $request->par;
				// 					$header->id_parameter = $par->id;
				// 					$header->note = $request->note;
				// 					$header->tgl_terima = $po->tgl_terima;
				// 					$header->add_by = $this->userid;
				// 					$header->add_at = DATE('Y-m-d H:i:s');
				// 					$header->save();
			
				// 					// Hitung hasil menggunakan fungsi SwabTest
				// 					$rumus = new FunctionValue();
				// 					$result = $rumus->Isokinetik(
				// 						$vm,
				// 						$po->id,
				// 						$po->tgl_terima,
				// 						$request,
				// 						$this->userid,
				// 						$par->id,
				// 						$header->id
				// 					);

				// 					// Simpan hasil ke tabel ws_value_swabtest
				// 					DB::table('ws_value_isokinetik')->insert($result);
			
				// 					// Commit transaksi jika semua berhasil
				// 					DB::commit();
			
				// 					return response()->json([
				// 						'message' => 'Value Parameter berhasil disimpan.!',
				// 						'par' => $request->parameter,
				// 					], 200);
			
				// 				} catch (\Exception $e) {
				// 					// Rollback transaksi jika terjadi kesalahan
				// 					DB::rollBack();
			
				// 					return response()->json([
				// 						'message' => 'Error: ' . $e->getMessage(),
				// 					], 500);
				// 				}
				// 			}
				// 		}
				// 	} 
				// 	// Jika jenis pengujian tidak dikenali
				// 	else {
				// 		return response()->json([
				// 			'message' => 'Pilih jenis pengujian'
				// 		], 401);
				// 	}
				// } else {
				// 	return response()->json([
				// 		'message' => 'Jenis pengujian tidak ada.'
				// 	], 401);
				// }
			}
        } catch (\Exception $th) {
            return response()->json([
                'message' => 'Gagal input parameter: '.$th->getMessage(),
				'file' => $th->getFile(),
                'line' => $th->getLine(),
            ],500);
        }
    }

    public function showDataApi(Request $request){
        if($request->tipe == 1) {

            // $subQuery = function ($query) use ($request) {
            //     $query->selectRaw('COUNT(*) as sudah_analisa')
            //         ->fromSub(function ($subQuery) use ($request) {
            //             $subQuery->select('a.no_sampel')
            //                 ->from('ws_value_air as a')
            //                 ->leftJoin('colorimetri as b', function ($join) use ($request) {
            //                     $join->on('a.id_colorimetri', '=', 'b.id')
            //                         ->where('b.parameter', '=', $request->parameter)
            //                         ->where('b.tanggal_terima', '>=', $request->tgl_mulai)
            //                         ->where('b.tanggal_terima', '<=', $request->tgl_akhir)
            //                         ->where('b.is_active', '=', true)
            //                         ->where('b.jenis_pengujian', '=', 'sample');
            //                 })
            //                 ->leftJoin('gravimetri as c', function ($join) use ($request) {
            //                     $join->on('a.id_gravimetri', '=', 'c.id')
            //                         ->where('c.parameter', '=', $request->parameter)
            //                         ->where('c.tanggal_terima', '>=', $request->tgl_mulai)
            //                         ->where('c.tanggal_terima', '<=', $request->tgl_akhir)
            //                         ->where('c.is_active', '=', true)
            //                         ->where('c.jenis_pengujian', '=', 'sample');
            //                 })
            //                 ->leftJoin('titrimetri as d', function ($join) use ($request) {
            //                     $join->on('a.id_titrimetri', '=', 'd.id')
            //                         ->where('d.parameter', '=', $request->parameter)
            //                         ->where('d.tanggal_terima', '>=', $request->tgl_mulai)
            //                         ->where('d.tanggal_terima', '<=', $request->tgl_akhir)
            //                         ->where('d.is_active', '=', true)
            //                         ->where('d.jenis_pengujian', '=', 'sample');
            //                 })
            //                 ->where('a.is_active', '=', true);
            //         }, 'subQuery')
            //         ->whereNotIn('id', 'subQuery.no_sampel');
            // };

            // if ($request->kategori == 1 || $request->kategori == 6) {
            //     $data = DB::table('order_detail')
            //         ->selectRaw("'$request->parameter' as param, COUNT(*) as total_analisa, ($subQuery) as belum_analisa, (SELECT COUNT(*) as sudah_analisa FROM order_detail WHERE parameter LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true AND id IN ($subQuery)) as sudah_analisa")
            //         ->where('parameter', 'LIKE', "%$request->parameter%")
            //         ->where('tanggal_terima', '>=', $request->tgl_mulai)
            //         ->where('tanggal_terima', '<=', $request->tgl_akhir)
            //         ->where('kategori_2', '=', $request->kategori)
            //         ->where('is_active', '=', true)
            //         ->groupBy('param')
            //         ->get();
            // } else if ($request->kategori == 4) {
            //     $data = DB::table('order_detail')
            //         ->selectRaw("'$request->parameter' as param, COUNT(*) as total_analisa, (SELECT COUNT(*) as belum_analisa FROM order_detail WHERE parameter LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true AND id NOT IN (SELECT a.no_sampel FROM ws_value_linghidup AS a LEFT JOIN linghidup_header AS b ON a.no_sampel = b.no_sampel AND b.is_active = true WHERE b.parameter = '$request->parameter' AND a.is_active = true)) AS belum_analisa, (SELECT COUNT(*) as sudah_analisa FROM order_detail WHERE parameter LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true AND id IN (SELECT a.no_sampel FROM ws_value_linghidup AS a LEFT JOIN linghidup_header AS b ON a.no_sampel = b.no_sampel AND b.is_active = true WHERE b.parameter = '$request->parameter' AND a.is_active = true)) as sudah_analisa")
            //         ->where('parameter', 'LIKE', "%$request->parameter%")
            //         ->where('tanggal_terima', '>=', $request->tgl_mulai)
            //         ->where('tanggal_terima', '<=', $request->tgl_akhir)
            //         ->where('kategori_2', '=', $request->kategori)
            //         ->where('is_active', '=', true)
            //         ->groupBy('param')
            //         ->get();
            // } else if ($request->kategori == 5) {
            //     $data = DB::table('order_detail')
            //         ->selectRaw("'$request->parameter' as param, COUNT(*) as total_analisa, (SELECT COUNT(*) as belum_analisa FROM order_detail WHERE parameter LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true AND id NOT IN (SELECT a.no_sampel FROM ws_value_emisic AS a LEFT JOIN emisic_header AS b ON a.no_sampel = b.no_sampel AND b.is_active = true WHERE b.parameter = '$request->parameter' AND a.is_active = true)) AS belum_analisa, (SELECT COUNT(*) as sudah_analisa FROM order_detail WHERE parameter LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true AND id IN (SELECT a.no_sampel FROM ws_value_emisic AS a LEFT JOIN emisic_header AS b ON a.no_sampel = b.no_sampel AND b.is_active = true WHERE b.parameter = '$request->parameter' AND a.is_active = true)) as sudah_analisa")
            //         ->where('parameter', 'LIKE', "%$request->parameter%")
            //         ->where('tanggal_terima', '>=', $request->tgl_mulai)
            //         ->where('tanggal_terima', '<=', $request->tgl_akhir)
            //         ->where('kategori_2', '=', $request->kategori)
            //         ->where('is_active', '=', true)
            //         ->groupBy('param')
            //         ->get();
            // }

            if($request->kategori == 1 || $request->kategori == 6) {
                $data = DB::select("SELECT '$request->parameter' as param, COUNT(*) as total_analisa, (SELECT COUNT(*) as belum_analisa FROM order_detail WHERE parameter LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true AND id NOT IN (SELECT a.no_sampel FROM ws_value_air AS a LEFT JOIN colorimetri AS b ON a.id_colorimetri = b.id AND a.no_sampel = b.no_sampel AND b.tanggal_terima >= '$request->tgl_mulai' AND b.tanggal_terima <= '$request->tgl_akhir' AND b.is_active = true AND b.jenis_pengujian = 'sample' LEFT JOIN gravimetri AS c ON a.id_gravimetri = c.id AND a.no_sampel = c.no_sampel AND c.tanggal_terima >= '$request->tgl_mulai' AND c.tanggal_terima <= '$request->tgl_akhir' AND c.is_active = true AND c.jenis_pengujian = 'sample' LEFT JOIN titrimetri AS d ON a.id_titrimetri = d.id AND a.no_sampel = d.no_sampel AND d.tanggal_terima >= '$request->tgl_mulai' AND d.tanggal_terima <= '$request->tgl_akhir' AND d.is_active = true AND d.jenis_pengujian = 'sample' WHERE b.parameter = '$request->parameter' OR c.parameter = '$request->parameter' OR d.parameter = '$request->parameter' AND a.is_active = true)) AS belum_analisa, (SELECT COUNT(*) as sudah_analisa FROM order_detail WHERE param LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true AND id IN (SELECT a.no_sampel FROM ws_value_air AS a LEFT JOIN colorimetri AS b ON a.id_colorimetri = b.id AND a.no_sampel = b.no_sampel AND b.tanggal_terima >= '$request->tgl_mulai' AND b.tanggal_terima <= '$request->tgl_akhir' AND b.is_active = true AND b.jenis_pengujian = 'sample' LEFT JOIN gravimetri AS c ON a.id_gravimetri = c.id AND a.no_sampel = c.no_sampel AND c.tanggal_terima >= '$request->tgl_mulai' AND c.tanggal_terima <= '$request->tgl_akhir' AND c.is_active = true AND c.jenis_pengujian = 'sample' LEFT JOIN titrimetri AS d ON a.id_titrimetri = d.id AND a.no_sampel = d.no_sampel AND d.tanggal_terima >= '$request->tgl_mulai' AND d.tanggal_terima <= '$request->tgl_akhir' AND d.is_active = true AND d.jenis_pengujian = 'sample' WHERE b.parameter = '$request->parameter' OR c.parameter = '$request->parameter' OR d.parameter = '$request->parameter' AND a.is_active = true)) as sudah_analisa FROM order_detail where parameter LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true ");
            }else if($request->kategori == 4) {
                $data = DB::select("SELECT '$request->parameter' as param, COUNT(*) as total_analisa, (SELECT COUNT(*) as belum_analisa FROM order_detail WHERE parameter LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true AND id NOT IN (SELECT a.no_sampel FROM ws_value_lingkungan AS a LEFT JOIN lingkungan_header AS b ON a.no_sampel = b.no_sampel AND b.is_active = true WHERE b.parameter = '$request->parameter' AND a.is_active = true)) AS belum_analisa, (SELECT COUNT(*) as sudah_analisa FROM order_detail WHERE parameter LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true AND id IN (SELECT a.no_sampel FROM ws_value_lingkungan AS a LEFT JOIN lingkungan_header AS b ON a.no_sampel = b.no_sampel AND b.is_active = true WHERE b.parameter = '$request->parameter' AND a.is_active = true)) as sudah_analisa FROM order_detail where parameter LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true");
            }else if($request->kategori == 5) {
                $data = DB::select("SELECT '$request->parameter' as param, COUNT(*) as total_analisa, (SELECT COUNT(*) as belum_analisa FROM order_detail WHERE parameter LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true AND id NOT IN (SELECT a.no_sampel FROM ws_value_emisi_cerobong AS a LEFT JOIN emisi_cerobong_header AS b ON a.no_sampel = b.no_sampel AND b.is_active = true WHERE b.parameter = '$request->parameter' AND a.is_active = true)) AS belum_analisa, (SELECT COUNT(*) as sudah_analisa FROM order_detail WHERE parameter LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true AND id IN (SELECT a.no_sampel FROM ws_value_emisi_cerobong AS a LEFT JOIN emisi_cerobong_header AS b ON a.no_sampel = b.no_sampel AND b.is_active = true WHERE b.parameter = '$request->parameter' AND a.is_active = true)) as sudah_analisa FROM order_detail where parameter LIKE '%$request->parameter%' AND tanggal_terima >= '$request->tgl_mulai' AND tanggal_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND is_active = true");
            }
            return response()->json([
                'data' => $data,
            ], 200);
        }else if($request->tipe == 2) {
            $date = date('Y-m-d');
            $user = $this->karyawan;
            $data = DB::select("SELECT COUNT(*) as tot, param FROM titrimetri WHERE created_by = '$user' AND DATE(created_at) = '$date' GROUP BY param UNION SELECT COUNT(*) as tot, param FROM gravimetri WHERE created_by = '$user' AND DATE(created_at) = '$date' GROUP BY param UNION SELECT COUNT(*) as tot, param FROM colorimetri WHERE created_by = '$user' AND DATE(created_at) = '$date' GROUP BY param");
            return response()->json([
                'data' => $data,
            ], 200);
        }
    }

    public function cmbCategory(Request $request){
        echo "<option value=''>--Pilih Kategori--</option>";

        $data = MasterKategori::where('is_active',true)->get();
        
        
        foreach ($data as $q){
            
            $id = $q->id;
            $nm = $q->nama_kategori;
            if($id == $request->value){
                echo "<option value='$id-$nm' selected> $nm </option>";
            } else {
                echo "<option value='$id-$nm'> $nm </option>";
            }
        }
        // echo "</select>";
        // dd($data);
    }

    public function cmbTemplate(Request $request){                
        echo "<option value=''>Pilih Template</option>";

        $data = TemplateStp::where('is_active',true)->where('category_id', $request->id)->get();
        
        foreach ($data as $q){
            $id = $q->id;
            $nm = $q->name;
            if($id == $request->value){
                echo "<option value='$id' selected> $nm </option>";
            } else {
                echo "<option value='$id'> $nm </option>";
            }
        }
        // echo "</select>";
    }

    public function showDetailApi(Request $request){
		
        $categori = MasterKategori::where('id', $request->kategori)->first();
        $stp = TemplateStp::where('is_active',true)->where('id', $request->par)->first();

        $data1 = [
            'id'=> $categori->id,
            'name'=> $categori->name,
        ];
        $data2 = [
            'id'=> $stp->id,
            'name'=> $stp->name,
        ];

        $cek = [];
            // if($request->tipe == 1) {
            //     $cek = DB::select("SELECT order_detail.tgl_terima, t_po.no_sample FROM t_po WHERE param LIKE '%$request->parameter%' AND tgl_terima >= '$request->tgl_mulai' AND tgl_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND active = 0 AND id NOT IN (SELECT a.id_po FROM ws_value AS a
            //     LEFT JOIN colorimetri AS b ON a.id_colori = b.id AND a.id_po = b.id_po AND b.tgl_terima >= '$request->tgl_mulai' AND b.tgl_terima <= '$request->tgl_akhir' AND b.active = 0 AND b.jenis_pengujian = 'sample'
            //     LEFT JOIN gravimetri AS c ON a.id_gravi = c.id AND a.id_po = c.id_po AND c.tgl_terima >= '$request->tgl_mulai' AND c.tgl_terima <= '$request->tgl_akhir' AND c.active = 0 AND c.jenis_pengujian = 'sample'
            //     LEFT JOIN titrimetri AS d ON a.id_titri = d.id AND a.id_po = d.id_po AND d.tgl_terima >= '$request->tgl_mulai' AND d.tgl_terima <= '$request->tgl_akhir' AND d.active = 0 AND d.jenis_pengujian = 'sample'
            //     WHERE b.param = '$request->parameter' OR c.param = '$request->parameter' OR d.param = '$request->parameter' AND a.active = 0) ");
            // }else if($request->tipe == 2) {
            //     $cek = DB::select("SELECT t_po.tgl_terima, t_po.no_sample FROM t_po WHERE param LIKE '%$request->parameter%' AND tgl_terima >= '$request->tgl_mulai' AND tgl_terima <= '$request->tgl_akhir' AND kategori_2 = '$request->kategori' AND active = 0 AND id IN (SELECT a.id_po FROM ws_value AS a
            //     LEFT JOIN colorimetri AS b ON a.id_colori = b.id AND a.id_po = b.id_po AND b.tgl_terima >= '$request->tgl_mulai' AND b.tgl_terima <= '$request->tgl_akhir' AND b.active = 0 AND b.jenis_pengujian = 'sample'
            //     LEFT JOIN gravimetri AS c ON a.id_gravi = c.id AND a.id_po = c.id_po AND c.tgl_terima >= '$request->tgl_mulai' AND c.tgl_terima <= '$request->tgl_akhir' AND c.active = 0 AND c.jenis_pengujian = 'sample'
            //     LEFT JOIN titrimetri AS d ON a.id_titri = d.id AND a.id_po = d.id_po AND d.tgl_terima >= '$request->tgl_mulai' AND d.tgl_terima <= '$request->tgl_akhir' AND d.active = 0 AND d.jenis_pengujian = 'sample'
            //     WHERE b.param = '$request->parameter' OR c.param = '$request->parameter' OR d.param = '$request->parameter' AND a.active = 0) ");
            // }
        if($request->tipe == 1) {
            if($request->kategori == 1 || $request->kategori == 6) {
                $cek = DB::select("SELECT order_detail.tanggal_terima, order_detail.no_sampel FROM order_detail WHERE parameter LIKE ? AND tanggal_terima >= ? AND tanggal_terima <= ? AND kategori_2 = ? AND is_active = true AND id NOT IN (SELECT a.no_sampel FROM ws_value_air AS a
                LEFT JOIN colorimetri AS b ON a.id_colorimetri = b.id AND a.no_sampel = b.no_sampel AND b.tanggal_terima >= ? AND b.tanggal_terima <= ? AND b.is_active = true AND b.jenis_pengujian = 'sample'
                LEFT JOIN gravimetri AS c ON a.id_gravimetri = c.id AND a.no_sampel = c.no_sampel AND c.tanggal_terima >= ? AND c.tanggal_terima <= ? AND c.is_active = true AND c.jenis_pengujian = 'sample'
                LEFT JOIN titrimetri AS d ON a.id_titrimetri = d.id AND a.no_sampel = d.no_sampel AND d.tanggal_terima >= ? AND d.tanggal_terima <= ? AND d.is_active = true AND d.jenis_pengujian = 'sample'
                WHERE b.parameter = ? OR c.parameter = ? OR d.parameter = ? AND a.is_active = true) ", [
                    '%'.$request->parameter.'%',
                    $request->tgl_mulai,
                    $request->tgl_akhir,
                    $request->kategori,
                    $request->tgl_mulai,
                    $request->tgl_akhir,
                    $request->tgl_mulai,
                    $request->tgl_akhir,
                    $request->tgl_mulai,
                    $request->tgl_akhir,
                    $request->parameter,
                    $request->parameter,
                    $request->parameter,
                ]);
            }else if($request->kategori == 4) {
                $cek = DB::select("SELECT order_detail.tanggal_terima, order_detail.no_sampel FROM order_detail WHERE parameter LIKE ? AND tanggal_terima >= ? AND tanggal_terima <= ? AND kategori_2 = ? AND is_active = true AND id NOT IN (SELECT a.no_sampel FROM ws_value_lingkungan AS a
                LEFT JOIN lingkungan_header AS b ON a.no_sampel = b.no_sampel AND b.is_active = true)", [
                    '%'.$request->parameter.'%',
                    $request->tgl_mulai,
                    $request->tgl_akhir,
                    $request->kategori,
                ]);
            }else if($request->kategori == 5) {
                $cek = DB::select("SELECT order_detail.tanggal_terima, order_detail.no_sampel FROM order_detail WHERE parameter LIKE ? AND tanggal_terima >= ? AND tanggal_terima <= ? AND kategori_2 = ? AND is_active = true AND id NOT IN (SELECT a.no_sampel FROM ws_value_emisi_cerobong AS a
                LEFT JOIN emisi_cerobong_header AS b ON a.no_sampel = b.no_sampel AND b.is_active = true)", [
                    '%'.$request->parameter.'%',
                    $request->tgl_mulai,
                    $request->tgl_akhir,
                    $request->kategori,
                ]);
            }
        }else if($request->tipe == 2) {
            if($request->kategori == 1 || $request->kategori == 6) {
                $cek = DB::select("SELECT order_detail.tanggal_terima, order_detail.no_sampel FROM order_detail WHERE parameter LIKE ? AND tanggal_terima >= ? AND tanggal_terima <= ? AND kategori_2 = ? AND is_active = true AND id IN (SELECT a.no_sampel FROM ws_value_air AS a
                LEFT JOIN colorimetri AS b ON a.id_colorimetri = b.id AND a.no_sampel = b.no_sampel AND b.tanggal_terima >= ? AND b.tanggal_terima <= ? AND b.is_active = true AND b.jenis_pengujian = 'sample'
                LEFT JOIN gravimetri AS c ON a.id_gravimetri = c.id AND a.no_sampel = c.no_sampel AND c.tanggal_terima >= ? AND c.tanggal_terima <= ? AND c.is_active = true AND c.jenis_pengujian = 'sample'
                LEFT JOIN titrimetri AS d ON a.id_titrimetri = d.id AND a.no_sampel = d.no_sampel AND d.tanggal_terima >= ? AND d.tanggal_terima <= ? AND d.is_active = true AND d.jenis_pengujian = 'sample'
                WHERE b.parameter = ? OR c.parameter = ? OR d.parameter = ? AND a.is_active = true)", [
                    '%'.$request->parameter.'%',
                    $request->tgl_mulai,
                    $request->tgl_akhir,
                    $request->kategori,
                    $request->tgl_mulai,
                    $request->tgl_akhir,
                    $request->tgl_mulai,
                    $request->tgl_akhir,
                    $request->tgl_mulai,
                    $request->tgl_akhir,
                    $request->parameter,
                    $request->parameter,
                    $request->parameter,
                ]);
            }else if($request->kategori == 4) {
                $cek = DB::select("SELECT order_detail.tanggal_terima, order_detail.no_sampel FROM order_detail WHERE parameter LIKE ? AND tanggal_terima >= ? AND tanggal_terima <= ? AND kategori_2 = ? AND is_active = true AND id IN (SELECT a.no_sampel FROM ws_value_lingkungan AS a
                LEFT JOIN lingkungan_header AS b ON a.no_sampel = b.no_sampel AND b.is_active = true)", [
                    '%'.$request->parameter.'%',
                    $request->tgl_mulai,
                    $request->tgl_akhir,
                    $request->kategori,
                ]);
            }else if($request->kategori == 5) {
                $cek = DB::select("SELECT order_detail.tanggal_terima, order_detail.no_sampel FROM order_detail WHERE parameter LIKE ? AND tanggal_terima >= ? AND tanggal_terima <= ? AND kategori_2 = ? AND is_active = true AND id IN (SELECT a.no_sampel FROM ws_value_emisi_cerobong AS a
                LEFT JOIN emisi_cerobong_header AS b ON a.no_sampel = b.no_sampel AND b.is_active = true)", [
                    '%'.$request->parameter.'%',
                    $request->tgl_mulai,
                    $request->tgl_akhir,
                    $request->kategori,
                ]);
            }

        }else if($request->tipe == 3) {
            $cek = DB::select("SELECT tanggal_terima, no_sampel FROM order_detail WHERE parameter LIKE ? AND tanggal_terima >= ? AND tanggal_terima <= ? AND kategori_2 = ? AND is_active = true ORDER BY no_sampel", [
                '%'.$request->parameter.'%',
                $request->tgl_mulai,
                $request->tgl_akhir,
                $request->kategori,
            ]);
        }

        return response()->json([
            'data'=> $cek,
            'kategori' => $data1,
            'stp' => $data2,
        ], 201);
    }

	public function logout(Request $request) {
		try {
			$Usertoken = Usertoken::where('token', $request->token)->first();
			$Usertoken->is_expired = true;
			$Usertoken->expired = date('Y-m-d H:i:s');
			$Usertoken->save();

			return response()->json([
					'message' => 'Logout Success', 
					'status' => '200'
				], 200);
		} catch (\Exception $th) {
			return response()->json([
				'message' => $th->getMessage(), 
				'status' => '400'
			],400);
		}
	}
}