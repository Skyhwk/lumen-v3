<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DraftIcp;
use App\Models\DraftAir;
use App\Models\InstrumentIcp;
use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\DataLapanganLingkunganHidup;
use App\Models\DetailLingkunganHidup;
use App\Models\DataLapanganLingkunganKerja;
use App\Models\DetailLingkunganKerja;
use App\Models\DataLapanganEmisiCerobong;
use App\Models\EmisiCerobongHeader;
use App\Models\Colorimetri;
use App\Models\WsValueAir;
use App\Models\WsValueEmisiCerobong;
use App\Models\WsValueLingkungan;
use App\Models\WsValueUdara;
use App\Models\LingkunganHeader;
use App\Models\TemplateStp;
use App\Services\FunctionValue;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;
use App\Services\AnalystFormula;
use App\Models\AnalystFormula as Formula;
use Carbon\Carbon;
use DB;

class DraftIcpController extends Controller
{
    public function index(Request $request)
    {
        $kategori = $request->kategori;
        
        if($kategori !== 'air'){
            $draft = InstrumentIcp::selectRaw('instrument_icp.*, draft_icp.ks as ks, draft_icp.kb as kb, draft_icp.fp as fp, draft_icp.id as draft_id')
                ->leftJoin('draft_icp', function($join) {
                    $join->on('instrument_icp.no_sampel', '=', 'draft_icp.no_sampel')
                        ->on('instrument_icp.parameter', '=', 'draft_icp.parameter');
                })
                ->whereNotNull('draft_icp.id'); // Memastikan hanya data dengan draft yang ditampilkan
            if($kategori == 'udara') {
                $draft->where('kategori', 'Udara');
            }else if($kategori == 'emisi') {
                $draft->where('kategori', 'Emisi');
            }
        }else{
            $draft = DraftAir::all();
        }
        
        return DataTables::of($draft)->make(true);
    }


    public function saveData(Request $request){
        switch ($request->type) {
            case 'single':
                // dd($request->all());
                $draft = DraftIcp::where('id', $request->id)->where('parameter', $request->parameter)->first();
                
                if($draft == null){
                    DraftIcp::where('no_sampel', $request->no_sampel)->where('parameter', $request->parameter)->delete();
                    $instrument->error_message = json_encode(["Draft $request->no_sampel dengan parameter $request->parameter Tidak Ditemukan"]);
                    $instrument->status = 'error';
                    $instrument->save();
                    return response()->json([
                        'message' => 'Data Draft Tidak Ditemukan'
                    ]);
                }

                $instrument = InstrumentIcp::where('no_sampel', $draft->no_sampel)->where('parameter', $request->parameter)->first();
                
                $par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori', $instrument->kategori == 'Udara' ? 4 : 5)->first();

                if($par == null){
                    DraftIcp::where('no_sampel', $request->no_sampel)->where('parameter', $request->parameter)->delete();
                    $instrument->error_message = json_encode(["Parameter $request->parameter Tidak Ditemukan"]);
                    $instrument->status = 'error';
                    $instrument->save();
                    return response()->json([
                        'message' => 'Data Parameter Tidak Ditemukan'
                    ],404);
                }

                $stp = TemplateStp::where('param','like', '%'.$par->nama_lab.'%')->where('category_id', $instrument->kategori == "Udara" ? 4 : 5)->first();

                $order = OrderDetail::where('no_sampel', $request->no_sampel)->first();
                
                if($order->kategori_2 == '4-Udara'){
                    $datlapanganh = DataLapanganLingkunganHidup::where('no_sampel', $request->no_sampel)->first();
                    $datlapangank = DataLapanganLingkunganKerja::where('no_sampel', $request->no_sampel)->first();
                    
                    $param = [293, 294, 295, 296, 326, 327, 328, 329, 299, 300, 289, 290, 291, 246, 247, 248, 249, 342, 343, 344, 345, 261, 256, 211, 310, 311, 312, 313, 314, 315, 568, 211, 564, 305, 306, 307, 308, 234, 569, 287, 292, 219];
                    if (!in_array($par->id, $param)) {
                        DraftIcp::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->delete();
                        $instrument->error_message = json_encode(["Rumus untuk parameter " . $par->nama_lab . " belum tersedia"]);
                        $instrument->status = 'error';
                        $instrument->save();
                        return response()->json([
                            'message' => 'Formula is Coming Soon parameter : ' . $par->nama_lab . '',
                        ], 200);
                    } else {
                        $wsling = LingkunganHeader::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->where('is_active', true)->first();
                        
                        if ($wsling) {
                            DraftIcp::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->delete();
                            $instrument->error_message = json_encode(["Parameter " . $par->nama_lab . " sudah diinput"]);
                            $instrument->status = 'processed';
                            $instrument->save();
                            return response()->json([
                                'message' => 'Parameter sudah diinput..!!'
                            ], 200);
                        } else {
                            if ($datlapanganh != null || $datlapangank != null) {
                                $lingHidup = DetailLingkunganHidup::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->get();
                                $lingKerja = DetailLingkunganKerja::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->get();
                                    
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
                                                // dd($vale);
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
                                            if( $par->nama_lab == 'Pb (24 Jam)' || $par->nama_lab == 'PM 2.5 (24 Jam)' || $par->nama_lab == 'PM 10 (24 Jam)' || $par->nama_lab == 'TSP (24 Jam)' || $par->id ==  306) {
                                                $l25 = '';
                                                if (count($lingHidup) > 0) {
                                                    // $l25 = array_filter($lingHidup->toArray(), function ($var) {
                                                    // 	return ($var['shift2'] == 'L25');
                                                    // });
                                                    $l25 = DetailLingkunganHidup::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->where('shift_pengambilan', 'L25')->first();
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
                                                    $l25 = DetailLingkunganKerja::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->where('shift_pengambilan', 'L25')->first();
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
                                            DraftIcp::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->delete();
                                            $instrument->error_message = json_encode(["No sample $request->no_sampel tidak ada di lingkungan hidup atau lingkungan kerja"]);
                                            $instrument->status = 'error';
                                            $instrument->save();
                                            return response()->json([
                                                'message' => 'No sample tidak ada di lingkungan hidup atau lingkungan kerja.',
                                            ], 200);
                                        }
                                    } catch (\Exception $e) {
                                        // dd($e);
                                        DraftIcp::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->delete();
                                        $instrument->error_message = json_encode([$e->getMessage()]);
                                        $instrument->status = 'error';
                                        $instrument->save();
                                        return response()->json([
                                            'message' => 'Error : ' . $e->getMessage(),
                                        ], 200);
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

                                // return response()->json([
                                // 	'message' => 'Gagal melakukan input : No sample tersebut tidak ditemukan pada data lapangan lingkungan hidup maupun lingkungan kerja'
                                // ], 400);
                            }

                            $check = OrderDetail::where('no_sampel',$request->no_sampel)->where('is_active',true)->first();

                            if(!isset($check->id)){
                                return (object)[
                                    'message'=> 'No Sample tidak ada.!!',
                                    'status' => 401
                                ];
                            }

                            $id_po = $check->id;
                            $tgl_terima = $check->tanggal_terima;
                            // Proses kalkulasi dengan AnalystFormula
                            
                            $functionObj = Formula::where('id_parameter', $par->id)->where('is_active', true)->first();
                            if (!$functionObj) {
                                return (object)[
                                    'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
                                    'status' => 404
                                ];
                            }
                            $function = $functionObj->function;
                            $data_parsing = $request->all();

                            $data_parsing = (object) $data_parsing;
                            $data_parsing->durasi = $durasiFin;
                            $data_parsing->tekanan = $tekananFin;
                            $data_parsing->suhu = $suhuFin;
                            $data_parsing->nilQs = $nilQs;
                            $data_parsing->data_total = $datot;
                            $data_parsing->average_flow = $rerataFlow;
                            $data_parsing->tanggal_terima = $tgl_terima;
                            $data_kalkulasi = AnalystFormula::where('function', $function)
                                ->where('data', $data_parsing)
                                ->where('id_parameter', $par->id)
                                ->process();

                            if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
                                return (object)[
                                    'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
                                    'status' => 404
                                ];
                            }

                            $saveShift = [246,247,248,249,289,290,291,293,294,295,296,299,300,326,327,328,329];
                            
                            DB::beginTransaction();
                            try {
                                $data = new LingkunganHeader;
                                $data->no_sampel = $request->no_sampel;
                                $data->parameter = $request->parameter;
                                $data->template_stp = 14;
                                $data->id_parameter = $par->id;
                                $data->note = $request->note;
                                $data->tanggal_terima = $tgl_terima;
                                $data->created_by = $this->karyawan;
                                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                                $data->data_shift = null;
                                if(in_array($par->id, $saveShift)) {
                                    // Store Shift Data
                                    $data_shift = array_map(function ($sample, $blanko) {
                                        return (object) [
                                            "sample" => $sample,
                                            "blanko" => $blanko
                                        ];
                                    }, $request->ks, $request->kb);
                                    $data->data_shift = count($data_shift) > 0 ? json_encode($data_shift) : null;
                                }
                                $data->save();				
                                
                                // dd($nilQs, $datot, $rerataFlow, $durasiFin, $po->id, $po->tgl_terima, $tekananFin, $suhuFin, $request, $this->karyawan, $par->id, $result);
                                // dd($result);
                                $data_kalkulasi['id_lingkungan_header'] = $data->id;
                                $data_kalkulasi['no_sampel'] = $request->no_sampel;

                                // dd($data_kalkulasi);
                                WsValueUdara::create($data_kalkulasi);
        
                                DB::commit();

                                DraftIcp::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->delete();
                                $instrument->status = 'processed';
                                $instrument->error_message = null;
                                $instrument->save();
                                return response()->json([
                                    'message' => 'Value Parameter berhasil disimpan.',
                                    'par' => $par->nama_lab
                                ], 200);

                            } catch (\Exception $e) {
                                DB::rollback();
                                return response()->json([
                                    'message' => 'Error : ' . $e->getMessage(),
                                ], 500);
                            }
                        }
                    }
                }else if($order->kategori_2 == '5-Emisi'){
                    $datlapangan = DataLapanganEmisiCerobong::where('no_sampel', $request->no_sampel)->first();

                    if(!$datlapangan) {
                        DraftIcp::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->delete();
                        $instrument->error_message = json_encode(["No Sample " . $request->no_sampel . " tidak ada di data lapangan emisi cerobong"]);
                        $instrument->status = 'error';
                        $instrument->save();
                        return response()->json([
                            'message' => 'No Sample tidak ada di data lapangan emisi cerobong.'
                        ],404);
                    }

                    $wsemisi = EmisiCerobongHeader::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->where('is_active',true)->first();

                    if($wsemisi) {
                        DraftIcp::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->delete();
                        $instrument->error_message = json_encode(["Parameter " . $par->nama_lab . " sudah diinput"]);
                        $instrument->status = 'processed';
                        $instrument->save();
                        return response()->json([
                            'message' => 'Parameter sudah diinput..!!'
                        ]);
                    }else {
                        // $param = [365, 368, 364, 360, 377, 354, 358, 378, 385, 383, 356, 359, 367, 380];
                        // $param = [365, 368, 364, 360, 377, 354, 358, 378, 385];
                        // if (!in_array($par->id, $param)) {
                        //     DraftIcp::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->delete();
                        //     $instrument->error_message = json_encode(["Rumus untuk parameter " . $par->nama_lab . " belum tersedia"]);
                        //     $instrument->status = 'error';
                        //     $instrument->save();
                        //     return response()->json([
                        //         'message' => 'Formula is Coming Soon parameter : ' . $par->nama_lab . '',
                        //     ], 404);
                        // } else {
                            // dd($datlapangan);
                            if ($datlapangan) {
                                $tekanan = (float) $datlapangan->tekanan_udara;
                                $t_flue = (float) $datlapangan->T_Flue;
                                $suhu = (float) $datlapangan->suhu;
                                $nil_pv = self::penentuanPv($suhu);
                                $status_par = $par->nama_lab;
                                if ($par->nama_lab == 'HF') {
                                    $dat = json_decode($datlapangan->HF);
                                } else if ($par->nama_lab == 'NH3') {
                                    $dat = json_decode($datlapangan->NH3);
                                } else if ($par->nama_lab == 'HCl') {
                                    $dat = json_decode($datlapangan->HCI);
                                }else if($par->nama_lab == 'Debu' || $par->nama_lab == 'Partikulat' || $par->nama_lab == 'Cd' || $par->nama_lab == 'Cr' || $par->nama_lab == 'Pb' || $par->nama_lab == 'Zn' ) {
                                    // dd($datlapangan);
                                    $dat = json_decode($datlapangan->partikulat);
                                    $status_par = 'Partikulat';
                                }
                            
                                // dd($request->parameter);
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
                                        $flow = ($akhir_dry + $awal_dry) / 2;
                                    }else {
                                        DraftIcp::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->delete();
                                        $instrument->error_message = json_encode(["Parameter " . $par->nama_lab . " tidak ditemukan pada data lapangan"]);
                                        $instrument->status = 'error';
                                        $instrument->save();
                                        return response()->json([
                                            'message' => 'Tidak ditemukan pada data lapangan parameter : ' . $status_par . '',
                                        ], 404);
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

                            $functionObj = Formula::where('id_parameter', $par->id)->where('is_active', true)->first();
                            if (!$functionObj) {
                                return (object)[
                                    'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
                                    'status' => 404
                                ];
                            }
                            $function = $functionObj->function;
                            $data_parsing = $request->all();
                            $data_parsing = (object)$data_parsing;

                            $data_parsing->tekanan_dry = $tekanan_dry;
                            $data_parsing->volume_dry = $volume_dry;
                            $data_parsing->durasi_dry = $durasi_dry;
                            $data_parsing->awal_dry = $awal_dry;
                            $data_parsing->akhir_dry = $akhir_dry;
                            $data_parsing->flow = $flow;
                            $data_parsing->tekanan = $tekanan;
                            $data_parsing->t_flue = $t_flue;
                            $data_parsing->suhu = $suhu;
                            $data_parsing->nil_pv = $nil_pv;
                            $data_parsing->tanggal_terima = $order->tanggal_terima;
                            
                            $data_kalkulasi = AnalystFormula::where('function', $function)
                                ->where('data', $data_parsing)
                                ->where('id_parameter', $par->id)
                                ->process();
                            
                            if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
                                return response()->json([
                                    'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
                                    'status' => 404
                                    
                                ],404);
                            }
                            // dd($stp->id);
                            $data = new EmisiCerobongHeader;
                            $data->no_sampel = $request->no_sampel;
                            $data->parameter = $request->parameter;
                            $data->template_stp = 15;
                            $data->id_parameter = $par->id;
                            // $data->note = $request->note;
                            $data->tanggal_terima = $order->tanggal_terima;
                            $data->created_by = $this->karyawan;
                            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                            $data->save();
                    
                            // dd($result);
                            $data_kalkulasi['id_emisi_cerobong_header'] = $data->id;
                            $data_kalkulasi['no_sampel'] = $request->no_sampel;
                            $data_kalkulasi['created_by'] = $this->karyawan;
                            WsValueEmisiCerobong::create($data_kalkulasi);

                            DraftIcp::where('no_sampel', $request->no_sampel)->where('parameter', $par->nama_lab)->delete();
                            $instrument->status = 'processed';
                            $instrument->error_message = null;
                            $instrument->save();

                            return response()->json([
                                'message' => 'Value Parameter berhasil disimpan.!',
                                'par' => $par->nama_lab
                            ], 200);
                        }
                    }

                // }

                break;
            case 'all':
                $errors = [];
                $success = [];

                try {
                    // dd(count($request->data));
                    foreach ($request->data as $key => $value) {
                        $value = (object) $value;
                        $draft = DraftIcp::where('id', $value->id)->where('parameter', $value->parameter)->first();
                        
                        $instrument = InstrumentIcp::where('no_sampel', $value->no_sampel)->where('parameter', $value->parameter)->first();
                        if($draft == null){
                            DraftIcp::where('no_sampel', $value->no_sampel)->where('parameter', $value->parameter)->delete();
                            $instrument->error_message = json_encode(["Data Draft $value->no_sampel dengan parameter $value->parameter tidak ditemukan"]);
                            $instrument->status = 'error';
                            $instrument->save();
                            
                            $errors[] = (object)[
                                'no_sampel' => $value->no_sampel,
                                'parameter' => $value->parameter,
                                'message' => "Data Draft $value->no_sampel dengan parameter $value->parameter tidak ditemukan"
                            ];
                            continue;
                        }
                        
                        
                        $par = Parameter::where('nama_lab', $value->parameter)->where('id_kategori', $instrument->kategori == 'Udara' ? 4 : 5)->first();
                        if($par == null){
                            DraftIcp::where('no_sampel', $value->no_sampel)->where('parameter', $value->parameter)->delete();
                            $instrument->error_message = json_encode(["Data Parameter $value->parameter tidak ditemukan"]);
                            $instrument->status = 'error';
                            $instrument->save();
                            $errors[] = (object)[
                                'no_sampel' => $value->no_sampel,
                                'parameter' => $value->parameter,
                                'message' => "Parameter $value->parameter tidak ditemukan"
                            ];
                            continue;
                        }

                        $stp = TemplateStp::where('param','like', '%'.$par->nama_lab.'%')->where('category_id', $instrument->kategori == 'Udara' ? 4 : 5)->first();

                        $order = OrderDetail::where('no_sampel', $value->no_sampel)->first();
                        
                        if($instrument->kategori == 'Udara'){
                            $result = $this->handleSubmitAllUdara($value, $par, $instrument, $order);
                        }else{
                            $result = $this->handleSubmitAllEmisi($value, $par, $instrument, $order);
                        }
                        // $response = json_decode($result->getContent(), true);
                        
                        $response = json_decode(json_encode($result), true);
                        $statuscode = json_decode($result->getStatusCode(), true);

                        // dd($response);
                        if($statuscode >= 400){
                            $errors[] = $response['original']['message'];
                            continue;
                        }else{
                            $success[] = $response['original']['message'];
                            continue;
                        }
                    }

                    return response()->json([
                        'message' => 'Data berhasil disimpan.!',
                        'errors' => implode(', ', array_column($errors, 'message')),
                        'success' => implode(', ', array_column($success, 'message'))
                    ], 200);
                } catch (\Exception $th) {
                    return response()->json([
                        'message' => $th->getMessage(),
                        'line' => $th->getLine(),
                        'file' => $th->getFile()
                    ],500);
                }

                break;
            
            default:
                # code...
                break;
        }
    }

    public function saveDataAir(Request $request){
        switch($request->type){
            case 'single':
                // dd($request->all());
                DB::beginTransaction();
                try{
                    $draft = DraftAir::where('id', $request->id)->where('parameter', $request->parameter)->first();
                    
                    $instrument = InstrumentIcp::where('no_sampel', $draft->no_sampel)->where('parameter', $request->parameter)->first();

                    if($draft == null){
                        DraftAir::where('no_sampel', $request->no_sampel)->where('parameter', $request->parameter)->delete();
                        $instrument->error_message = json_encode(["Draft $request->no_sampel dengan parameter $request->parameter Tidak Ditemukan"]);
                        $instrument->status = 'error';
                        $instrument->save();
                        return response()->json([
                            'message' => 'Data Draft Tidak Ditemukan'
                        ],404);
                    }

                    $par = Parameter::where('nama_lab', $request->parameter)->where('id_kategori', 1)->first();
                    
                    $stp = TemplateStp::where('param','like', '%'.$par->nama_lab.'%')->where('category_id', $instrument->kategori == 1)->first();
                    
                    if($par == null){
                        DraftAir::where('no_sampel', $request->no_sampel)->where('parameter', $request->parameter)->delete();
                        $instrument->error_message = json_encode(["Parameter $request->parameter Tidak Ditemukan"]);
                        $instrument->status = 'error';
                        $instrument->save();
                        return response()->json([
                            'message' => 'Data Parameter Tidak Ditemukan'
                        ],404);
                    }

                    $order = OrderDetail::where('no_sampel', $request->no_sampel)->first();
                    if($order == null){
                        return response()->json([
                            'message' => 'No Sampel Tidak Ditemukan'
                        ],404);
                    }
                    $cek = Colorimetri::where('no_sampel', $request->no_sampel)->where('parameter', $request->parameter)->first();
                    if($cek == null){
                        $data                   = new Colorimetri();
                        $data->no_sampel        = $request->no_sampel;
                        $data->parameter        = $request->parameter;
                        $data->tanggal_terima   = $order->tanggal_terima;
                        $data->jenis_pengujian  = 'sample';
                        $data->template_stp     = $stp->id;
                        $data->hp               = $request->hp;
                        $data->fp               = $request->fp;
                        $data->created_by       = $this->karyawan;
                        $data->created_at       = Carbon::now()->format('Y-m-d H:i:s');
                        $data->save();

                        $datas = new FunctionValue();
                        $result = $datas->Perkalian($data->id, $request->no_sampel, $request->hp, $request->fp, $par->id);
                        WsValueAir::create($result);

                        $instrument->status = 'processed';
                        $instrument->error = null;
                        $instrument->save();

                        $draft->delete();

                        DB::commit();
                        return response()->json([
                            'message' => 'Data berhasil disimpan'
                        ],201);
                    }else{
                        return response()->json([
                            'message' => "Data dengan no sampel $request->no_sampel dan parameter $request->parameter sudah diinput"
                        ],400);
                    }

                    return response()->json([
                        'message' => 'Terjadi kesalahan'
                    ],500);
                } catch (\Exception $th) {
                    return response()->json([
                        'message' => $th->getMessage(),
                        'line' => $th->getLine(),
                        'file' => $th->getFile()
                    ],500);
                }

                break;
            case 'all':
                $errors = [];
                $success = [];
                DB::beginTransaction();
                try {
                    // First pass - validate all data and collect errors
                    $validData = [];
                    $invalidSamples = [];
                    
                    foreach ($request->data as $key => $value) {
                        $value = (object) $value;
                        $hasError = false;
                        
                        // Validate draft exists
                        $draft = DraftAir::where('id', $value->id)->where('parameter', $value->parameter)->first();
                        if ($draft == null) {
                            $instrument = InstrumentIcp::where('no_sampel', $value->no_sampel)->where('parameter', $value->parameter)->first();
                            if ($instrument) {
                                $instrument->error_message = json_encode(["Draft $value->no_sampel dengan parameter $value->parameter Tidak Ditemukan"]);
                                $instrument->status = 'error';
                                $instrument->save();
                            }
                            
                            $errors[] = (object)[
                                'no_sampel' => $value->no_sampel,
                                'parameter' => $value->parameter,
                                'message' => "Data Draft $value->no_sampel dengan parameter $value->parameter tidak ditemukan"
                            ];
                            $invalidSamples[$value->no_sampel . '-' . $value->parameter] = true;
                            continue;
                        }
                        
                        // Validate parameter exists
                        $par = Parameter::where('nama_lab', $value->parameter)->where('id_kategori', 1)->first();

                        $stp = TemplateStp::where('param','like', '%'.$par->nama_lab.'%')->where('category_id', $instrument->kategori == 1)->first();

                        if ($par == null) {
                            $instrument = InstrumentIcp::where('no_sampel', $value->no_sampel)->where('parameter', $value->parameter)->first();
                            if ($instrument) {
                                $instrument->error_message = json_encode(["Parameter $value->parameter Tidak Ditemukan"]);
                                $instrument->status = 'error';
                                $instrument->save();
                            }
                            
                            $errors[] = (object)[
                                'no_sampel' => $value->no_sampel,
                                'parameter' => $value->parameter,
                                'message' => "Parameter $value->parameter tidak ditemukan"
                            ];
                            $invalidSamples[$value->no_sampel . '-' . $value->parameter] = true;
                            continue;
                        }
                        
                        // Validate order exists
                        $order = OrderDetail::where('no_sampel', $value->no_sampel)->first();
                        if ($order == null) {
                            $instrument = InstrumentIcp::where('no_sampel', $value->no_sampel)->where('parameter', $value->parameter)->first();
                            if ($instrument) {
                                $instrument->error_message = json_encode(["No Sampel $value->no_sampel Tidak Ditemukan"]);
                                $instrument->status = 'error';
                                $instrument->save();
                            }
                            
                            $errors[] = (object)[
                                'no_sampel' => $value->no_sampel,
                                'parameter' => $value->parameter,
                                'message' => "Parameter $value->parameter tidak ditemukan pada Order dengan No Sampel $value->no_sampel"
                            ];
                            $invalidSamples[$value->no_sampel . '-' . $value->parameter] = true;
                            continue;
                        }
                        
                        // Check if already exists in Colorimetri
                        $cek = Colorimetri::where('no_sampel', $value->no_sampel)->where('parameter', $value->parameter)->first();
                        if ($cek != null) {
                            $errors[] = (object)[
                                'no_sampel' => $value->no_sampel,
                                'parameter' => $value->parameter,
                                'message' => "Data dengan no sampel $value->no_sampel dan parameter $value->parameter sudah diinput"
                            ];
                            $invalidSamples[$value->no_sampel . '-' . $value->parameter] = true;
                            continue;
                        }
                        
                        // If all validation passes, add to valid data
                        $validData[] = [
                            'value' => $value,
                            'draft' => $draft,
                            'parameter' => $par,
                            'order' => $order
                        ];
                    }
                    
                    // Second pass - process only valid data
                    foreach ($validData as $data) {
                        $value = $data['value'];
                        $draft = $data['draft'];
                        $par = $data['parameter'];
                        $order = $data['order'];
                        
                        // Create new Colorimetri entry
                        $colorimetri                    = new Colorimetri();
                        $colorimetri->no_sampel         = $value->no_sampel;
                        $colorimetri->parameter         = $value->parameter;
                        $colorimetri->tanggal_terima    = $order->tanggal_terima;
                        $colorimetri->jenis_pengujian   = 'sample';
                        $colorimetri->template_stp      = $stp->id;
                        $colorimetri->hp                = $value->hp;
                        $colorimetri->fp                = $value->fp;
                        $colorimetri->created_by        = $this->karyawan;
                        $colorimetri->created_at        = Carbon::now()->format('Y-m-d H:i:s');
                        $colorimetri->save();
                        
                        // Calculate and save value
                        $datas = new FunctionValue();
                        $result = $datas->Perkalian($colorimetri->id, $value->no_sampel, $value->hp, $value->fp, $par->id);
                        WsValueAir::create($result);
                        
                        // Update instrument status
                        $instrument = InstrumentIcp::where('no_sampel', $value->no_sampel)->where('parameter', $value->parameter)->first();
                        if ($instrument) {
                            $instrument->status = 'processed';
                            $instrument->error = null;
                            $instrument->save();
                        }
                        
                        // Delete draft
                        $draft->delete();
                        
                        $success[] = (object)[
                            'no_sampel' => $value->no_sampel,
                            'parameter' => $value->parameter,
                            'message' => "Data dengan no sampel $value->no_sampel dan parameter $value->parameter berhasil disimpan"
                        ];
                    }
                    
                    DB::commit();
                    
                    return response()->json([
                        'message' => 'Proses Submit Berhasil!',
                        'error' => count($errors) > 0 ? implode(', ', array_column($errors, 'message')) : '',
                        'success' => count($success) > 0 ? implode(', ', array_column($success, 'message')) : ''
                    ], 200);
                } catch (\Exception $th) {
                    DB::rollBack();
                    return response()->json([
                        'message' => $th->getMessage(),
                        'line' => $th->getLine(),
                        'file' => $th->getFile()
                    ], 500);
                }
                break;
            
            default:
                return response()->json([
                    'message' => 'Tipe data tidak sesuai'
                ],404);
        }
    }

    // public function handleSubmitAllAir($value, $par, $instrument, $order, $draft){
    //     DB::beginTransaction();
    //     try{
    //         $cek = Colorimetri::where('no_sampel', $value->no_sampel)->where('parameter', $value->parameter)->first();
    //         if($cek == null){
    //             $data                   = new Colorimetri();
    //             $data->no_sampel        = $value->no_sampel;
    //             $data->parameter        = $value->parameter;
    //             $data->tanggal_terima   = $order->tanggal_terima;
    //             $data->hp               = $value->hp;
    //             $data->fp               = $value->fp;
    //             $data->created_by       = $this->karyawan;
    //             $data->created_at       = Carbon::now()->format('Y-m-d H:i:s');
    //             $data->save();

    //             $datas = new FunctionValue();
    //             $result = $datas->Perkalian(null, $value->no_sampel, $value->hp, $value->fp, $par->id);
    //             WsValueAir::create($result);
                
    //             $instrument->status = 'processed';
    //             $instrument->save();

    //             $draft->delete();

    //             DB::commit();
    //             return response()->json([
    //                 'message' => 'Data berhasil disimpan'
    //             ],201);
    //         }else{
    //             DB::rollBack();
    //             return response()->json([
    //                 'message' => "Data dengan no sampel $value->no_sampel dan parameter $value->parameter sudah diinput"
    //             ],400);
    //         }
    //     }catch (\Exception $th) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => $th->getMessage(),
    //             'line' => $th->getLine(),
    //             'file' => $th->getFile()
    //         ], 500);
    //     }
    // }

    public function handleSubmitAllUdara($value, $par, $instrument, $order){
        
        $datlapanganh = DataLapanganLingkunganHidup::where('no_sampel', $value->no_sampel)->first();
        $datlapangank = DataLapanganLingkunganKerja::where('no_sampel', $value->no_sampel)->first();
        
        $param = [293, 294, 295, 296, 326, 327, 328, 329, 299, 300, 289, 290, 291, 246, 247, 248, 249, 342, 343, 344, 345, 261, 256, 211, 310, 311, 312, 313, 314, 315, 568, 211, 564, 305, 306, 307, 308, 234, 569, 287, 292, 219];
        if (!in_array($par->id, $param)) {
            DraftIcp::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->delete();
            $instrument->error_message = json_encode(["Rumus untuk parameter " . $par->nama_lab . " belum tersedia"]);
            $instrument->status = 'error';
            $instrument->save();
            return response()->json([
                'message' => 'Formula is Coming Soon parameter : ' . $par->nama_lab . '',
            ], 200);
        } else {
            $wsling = LingkunganHeader::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->where('is_active', true)->first();
            
            if ($wsling) {
                DraftIcp::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->delete();
                $instrument->error_message = json_encode(["Parameter " . $par->nama_lab . " sudah diinput"]);
                $instrument->status = 'processed';
                $instrument->save();
                return response()->json([
                    'message' => 'Parameter sudah diinput..!!'
                ], 200);
            } else {
                if ($datlapanganh != null || $datlapangank != null) {
                    $lingHidup = DetailLingkunganHidup::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->get();
                    $lingKerja = DetailLingkunganKerja::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->get();
                        
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
                                    // dd($vale);
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
                                if( $par->nama_lab == 'Pb (24 Jam)' || $par->nama_lab == 'PM 2.5 (24 Jam)' || $par->nama_lab == 'PM 10 (24 Jam)' || $par->nama_lab == 'TSP (24 Jam)' || $par->id ==  306) {
                                    $l25 = '';
                                    if (count($lingHidup) > 0) {
                                        // $l25 = array_filter($lingHidup->toArray(), function ($var) {
                                        // 	return ($var['shift2'] == 'L25');
                                        // });
                                        $l25 = DetailLingkunganHidup::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->where('shift_pengambilan', 'L25')->first();
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
                                        $l25 = DetailLingkunganKerja::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->where('shift_pengambilan', 'L25')->first();
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
                                DraftIcp::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->delete();
                                $instrument->error_message = json_encode(["No sample $value->no_sampel tidak ada di lingkungan hidup atau lingkungan kerja"]);
                                $instrument->status = 'error';
                                $instrument->save();
                                return response()->json([
                                    'message' => 'No sample tidak ada di lingkungan hidup atau lingkungan kerja.',
                                ], 200);
                            }
                        } catch (\Exception $e) {
                            // dd($e);
                            DraftIcp::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->delete();
                            $instrument->error_message = json_encode([$e->getMessage()]);
                            $instrument->status = 'error';
                            $instrument->save();
                            return response()->json([
                                'message' => 'Error : ' . $e->getMessage(),
                            ], 200);
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

                    // return response()->json([
                    // 	'message' => 'Gagal melakukan input : No sample tersebut tidak ditemukan pada data lapangan lingkungan hidup maupun lingkungan kerja'
                    // ], 400);
                }

                $check = OrderDetail::where('no_sampel',$value->no_sampel)->where('is_active',true)->first();

                if(!isset($check->id)){
                    return (object)[
                        'message'=> 'No Sample tidak ada.!!',
                        'status' => 401
                    ];
                }

                $id_po = $check->id;
                $tgl_terima = $check->tanggal_terima;
                // Proses kalkulasi dengan AnalystFormula
                
                $functionObj = Formula::where('id_parameter', $par->id)->where('is_active', true)->first();
                if (!$functionObj) {
                    return (object)[
                        'message'=> 'Formula is Coming Soon parameter : '.$value->parameter.'',
                        'status' => 404
                    ];
                }
                $function = $functionObj->function;
                $data_parsing = $value;

                $data_parsing = (object) $data_parsing;
                $data_parsing->durasi = $durasiFin;
                $data_parsing->tekanan = $tekananFin;
                $data_parsing->suhu = $suhuFin;
                $data_parsing->nilQs = $nilQs;
                $data_parsing->data_total = $datot;
                $data_parsing->average_flow = $rerataFlow;
                $data_parsing->tanggal_terima = $tgl_terima;
                $data_kalkulasi = AnalystFormula::where('function', $function)
                    ->where('data', $data_parsing)
                    ->where('id_parameter', $par->id)
                    ->process();

                if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
                    return (object)[
                        'message'=> 'Formula is Coming Soon parameter : '.$value->parameter.'',
                        'status' => 404
                    ];
                }

                $saveShift = [246,247,248,249,289,290,291,293,294,295,296,299,300,326,327,328,329];
                
                DB::beginTransaction();
                try {
                    $data = new LingkunganHeader;
                    $data->no_sampel = $value->no_sampel;
                    $data->parameter = $value->parameter;
                    $data->template_stp = 14;
                    $data->id_parameter = $par->id;
                    // $data->note = $value->note;
                    $data->tanggal_terima = $tgl_terima;
                    $data->created_by = $this->karyawan;
                    $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->data_shift = null;
                    if(in_array($par->id, $saveShift)) {
                        // Store Shift Data
                        $data_shift = array_map(function ($sample, $blanko) {
                            return (object) [
                                "sample" => $sample,
                                "blanko" => $blanko
                            ];
                        }, $value->ks, $value->kb);
                        $data->data_shift = count($data_shift) > 0 ? json_encode($data_shift) : null;
                    }
                    $data->save();				
                    
                    // dd($nilQs, $datot, $rerataFlow, $durasiFin, $po->id, $po->tgl_terima, $tekananFin, $suhuFin, $request, $this->karyawan, $par->id, $result);
                    // dd($result);
                    // $data_kalkulasi['id_lingkungan_header'] = $data->id;
                    $data_kalkulasi['lingkungan_header_id'] = $data->id;
                    $data_kalkulasi['no_sampel'] = $value->no_sampel;

                    WsValueLingkungan::create($data_kalkulasi);

                    DB::commit();

                    DraftIcp::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->delete();
                    $instrument->status = 'processed';
                    $instrument->error_message = null;
                    $instrument->save();
                    return response()->json([
                        'message' => 'Value Parameter berhasil disimpan.',
                        'par' => $par->nama_lab
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

    public function handleSubmitAllEmisi($value, $par, $instrument, $order){
        $datlapangan = DataLapanganEmisiCerobong::where('no_sampel', $value->no_sampel)->first();

        if(!$datlapangan) {
            DraftIcp::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->delete();
            $instrument->error_message = json_encode(["No Sample $value->no_sampel tidak ada di data lapangan emisi cerobong"]);
            $instrument->status = 'error';
            $instrument->save();
            return response()->json([
                "no_sampel" => $value->no_sampel,
                "parameter" => $par->nama_lab,
                "message" => "No Sample $value->no_sampel tidak ada di data lapangan emisi cerobong"
            ],404);
        }

        $wsemisi = EmisiCerobongHeader::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->where('is_active',true)->first();

        if($wsemisi) {
            DraftIcp::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->delete();
            $instrument->error_message = json_encode(["Parameter $par->nama_lab sudah diinput"]);
            $instrument->status = 'processed';
            $instrument->save();

            return response()->json([
                "no_sampel" => $value->no_sampel,
                "parameter" => $par->nama_lab,
                "message" => "Parameter $par->nama_lab sudah diinput"
            ],200);
        }else {
            // $param = [365, 368, 364, 360, 377, 354, 358, 378, 385, 383, 356, 359, 367, 380];
            $param = [365, 368, 364, 360, 377, 354, 358, 378, 385];
            if (!in_array($par->id, $param)) {
                DraftIcp::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->delete();
                $instrument->error_message = json_encode(["Rumus untuk parameter " . $par->nama_lab . " belum tersedia"]);
                $instrument->status = 'error';
                $instrument->save();
                return response()->json([
                    "no_sampel" => $value->no_sampel,
                    "parameter" => $par->nama_lab,
                    'message' => 'Formula is Coming Soon parameter : ' . $par->nama_lab . ''
                ],404);
            } else {
                // dd($datlapangan);
                if ($datlapangan) {
                    $tekanan = (float) $datlapangan->tekanan_udara;
                    $t_flue = (float) $datlapangan->T_Flue;
                    $suhu = (float) $datlapangan->suhu;
                    $nil_pv = self::penentuanPv($suhu);
                    $status_par = $par->nama_lab;
                    if ($par->nama_lab == 'HF') {
                        $dat = json_decode($datlapangan->HF);
                    } else if ($par->nama_lab == 'NH3') {
                        $dat = json_decode($datlapangan->NH3);
                    } else if ($par->nama_lab == 'HCl') {
                        $dat = json_decode($datlapangan->HCI);
                    }else if($par->nama_lab == 'Debu' || $par->nama_lab == 'Partikulat' || $par->nama_lab == 'Cd' || $par->nama_lab == 'Cr' || $par->nama_lab == 'Pb' || $par->nama_lab == 'Zn' ) {
                        // dd($datlapangan);
                        $dat = json_decode($datlapangan->partikulat);
                        $status_par = 'Partikulat';
                    }
                
                    // dd($value->parameter);
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
                            $flow = ($akhir_dry + $awal_dry) / 2;
                        }else {
                            DraftIcp::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->delete();
                            $instrument->error_message = json_encode(["Parameter $status_par dengan no sampel $value->no_sampel tidak ada di data lapangan emisi cerobong"]);
                            $instrument->status = 'error';
                            $instrument->save();
                            return response()->json([
                                "no_sampel" => $value->no_sampel,
                                "parameter" => $status_par,
                                "message" => "Parameter $status_par dengan no sampel $value->no_sampel tidak ada di data lapangan emisi cerobong"
                            ],404);
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
                $functionObj = Formula::where('id_parameter', $par->id)->where('is_active', true)->first();
                if (!$functionObj) {
                    return response()->json([
                        'message'=> 'Formula is Coming Soon parameter : '.$value->parameter.'',
                        'status' => 404
                    ], 404);
                }
                $function = $functionObj->function;
                // $data_parsing = $value->all();
                $data_parsing = $value;
                $data_parsing = (object)$data_parsing;

                $data_parsing->tekanan_dry = $tekanan_dry;
                $data_parsing->volume_dry = $volume_dry;
                $data_parsing->durasi_dry = $durasi_dry;
                $data_parsing->awal_dry = $awal_dry;
                $data_parsing->akhir_dry = $akhir_dry;
                $data_parsing->flow = $flow;
                $data_parsing->tekanan = $tekanan;
                $data_parsing->t_flue = $t_flue;
                $data_parsing->suhu = $suhu;
                $data_parsing->nil_pv = $nil_pv;
                $data_parsing->tanggal_terima = $order->tanggal_terima;
                
                $data_kalkulasi = AnalystFormula::where('function', $function)
                    ->where('data', $data_parsing)
                    ->where('id_parameter', $par->id)
                    ->process();
                
                if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
                    return response()->json([
                        'message'=> 'Formula is Coming Soon parameter : '.$value->parameter.'',
                        'status' => 404
                    ],404);
                }
                // dd($stp->id);
                DB::beginTransaction();
                try{
                    $data = new EmisiCerobongHeader;
                    $data->no_sampel = $value->no_sample;
                    $data->parameter = $value->parameter;
                    $data->template_stp = 15;
                    $data->id_parameter = $par->id;
                    $data->note = $value->note;
                    $data->tanggal_terima = $order->tanggal_terima;
                    $data->created_by = $this->karyawan;
                    $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
            
                    // dd($result);
                    $data_kalkulasi['id_emisi_cerobong_header'] = $data->id;
                    $data_kalkulasi['no_sampel'] = $value->no_sample;
                    $data_kalkulasi['created_by'] = $this->karyawan;
                    WsValueEmisiCerobong::create($data_kalkulasi);

                    DraftIcp::where('no_sampel', $value->no_sampel)->where('parameter', $par->nama_lab)->delete();
                    $instrument->error_message = json_encode(["Parameter " . $par->nama_lab . " sudah diinput"]);
                    $instrument->status = 'processed';
                    $instrument->error_message = null;
                    $instrument->save();

                    DB::commit();

                    return response()->json([
                        'message' => 'Value Parameter berhasil disimpan.',
                        'par' => $par->nama_lab
                    ], 200);
                } catch (\Throwable $th) {
                    DB::rollBack();
                    return response()->json([
                        'message' => $th
                    ], 500);
                }
            }
        }
    }

    public function KonversiTekananUapAir($suhu) {
		if (!is_float($suhu) && !is_double($suhu)) {
			// Jika tipe $suhu bukan float atau double, maka ubah menjadi format desimal
			$suhuTodecimal = number_format($suhu, 1, '.', '');
			$suhuToArray = explode('.', $suhuTodecimal);
		} else {
			// Jika $suhu sudah bertipe float atau double, tidak perlu diubah
			$suhuTodecimal = $suhu;
			$suhuToArray = explode('.', (string)$suhuTodecimal);
		}		

		$axisY = $suhuToArray[0];
		$axisX = $suhuToArray[1];
		$tekananUapAirJenuh = [
			0 => [0.6105, 0.6195, 0.6195, 0.6241, 0.6286, 0.6333, 0.6379, 0.6426, 0.6473, 0.6519],
			1 => [0.6567, 0.6615, 0.6663, 0.6711, 0.6759, 0.6809, 0.6858, 0.6907, 0.6958, 0.7007],
			2 => [0.7058, 0.7109, 0.7159, 0.7210, 0.7262, 0.7314, 0.7366, 0.7419, 0.7473, 0.7526],
			3 => [0.7579, 0.7633, 0.7687, 0.7742, 0.7797, 0.7851, 0.7907, 0.7963, 0.8019, 0.8077],
			4 => [0.8134, 0.8191, 0.8249, 0.8306, 0.8365, 0.8423, 0.8483, 0.8543, 0.8603, 0.8663],
			5 => [0.8723, 0.8785, 0.8846, 0.8907, 0.8970, 0.9033, 0.9095, 0.9158, 0.9222, 0.9286],
			6 => [0.9350, 0.9415, 0.9481, 0.9546, 0.9611, 0.9678, 0.9745, 0.9813, 0.9881, 0.9949],
			7 => [1.002, 1.009, 1.016, 1.022, 1.030, 1.037, 1.044, 1.051, 1.058, 1.065],
			8 => [1.073, 1.080, 1.087, 1.095, 1.102, 1.110, 1.117, 1.125, 1.132, 1.140],
			9 => [1.148, 1.156, 1.164, 1.171, 1.179, 1.187, 1.195, 1.203, 1.211, 1.219],
			10 => [1.228, 1.236, 1.244, 1.253, 1.261, 1.269, 1.278, 1.286, 1.295, 1.304],
			11 => [1.312, 1.321, 1.330, 1.3388, 1.3478, 1.3567, 1.3658, 1.3748, 1.3839, 1.3998],
			12 => [1.4023, 1.4116, 1.4209, 1.4303, 1.4397, 1.4492, 1.4587, 1.4683, 1.4779, 1.4876],
			13 => [1.4973, 1.5072, 1.5171, 1.5269, 1.5369, 1.5471, 1.5571, 1.5673, 1.5776, 1.5879],
			14 => [1.5981, 1.6085, 1.6191, 1.6296, 1.6401, 1.6508, 1.6615, 1.6723, 1.6831, 1.6940],
			15 => [1.7049, 1.7159, 1.7269, 1.7381, 1.7493, 1.7605, 1.7719, 1.7832, 1.7947, 1.8061],
			16 => [1.8177, 1.8293, 1.8410, 1.8529, 1.8648, 1.8766, 1.8886, 1.9006, 1.9128, 1.9249],
			17 => [1.9372, 1.9494, 1.9618, 1.9744, 1.9869, 1.9994, 2.0121, 2.0249, 2.0377, 2.0505],
			18 => [2.0634, 2.0765, 2.0896, 2.1028, 2.1160, 2.1293, 2.1426, 2.1560, 2.1694, 2.1830],
			19 => [2.1968, 2.2106, 2.2245, 2.2383, 2.2523, 2.2663, 2.2805, 2.2947, 2.3090, 2.3234],
			20 => [2.3378, 2.3523, 2.3669, 2.3815, 2.3963, 2.4111, 2.4261, 2.4410, 2.4561, 2.4713],
			21 => [2.4865, 2.5018, 2.5171, 2.5326, 2.5482, 2.5639, 2.5797, 2.5955, 2.6114, 2.6274],
			22 => [2.6434, 2.6595, 2.6758, 2.6922, 2.7086, 2.7251, 2.7418, 2.7584, 2.7751, 2.7919],
			23 => [2.8088, 2.8259, 2.8430, 2.8602, 2.8775, 2.8950, 2.9124, 2.9300, 2.9478, 2.9655],
			24 => [2.9834, 3.0014, 3.0195, 3.0378, 3.0560, 3.0744, 3.0928, 3.1113, 3.1299, 3.1485],
			25 => [3.1672, 3.1860, 3.2049, 3.2240, 3.2432, 3.2625, 3.2820, 3.3016, 3.3213, 3.3411],
			26 => [3.3609, 3.3809, 3.4009, 3.4211, 3.4413, 3.4616, 3.4820, 3.5025, 3.5232, 3.5440],
			27 => [3.5649, 3.5860, 3.6070, 3.6282, 3.6496, 3.6710, 3.6925, 3.7141, 3.7358, 3.7577],
			28 => [3.7796, 3.8016, 3.8237, 3.8460, 3.8683, 3.8909, 3.9135, 3.9363, 3.9693, 3.9823],
			29 => [4.0054, 4.0286, 4.0519, 4.0754, 4.0990, 4.1227, 4.1466, 4.1705, 4.1945, 4.2186],
			30 => [4.2429, 4.2672, 4.2918, 4.3164, 4.3411, 4.1659, 4.3908, 4.4159, 4.4412, 4.4667],
			31 => [4.4923, 4.5180, 4.5439, 4.5698, 4.5958, 4.6219, 4.6482, 4.6745, 4.7011, 4.7279],
			32 => [4.7547, 4.7816, 4.887, 4.8359, 4.8632, 4.8907, 4.9184, 4.9341, 4.9740, 5.0020],
			33 => [5.0301, 5.0286, 5.0869, 5.1154, 5.1441, 5.1730, 5.3030, 5.2312, 5.2605, 5.2898],
			34 => [5.3193, 5.3490, 5.3788, 5.4088, 5.4390, 5.4693, 5.4997, 5.5302, 5.5609, 5.5918],
			35 => [5.6229, 5.6541, 5.6854, 5.7169, 5.7485, 5.7802, 5.8122, 5.8443, 5.8766, 5.9088],
			36 => [5.9412, 5.9739, 6.0067, 6.0396, 6.0727, 6.1060, 6.1395, 6.1731, 6.2070, 6.2410],
			37 => [6.2751, 6.3093, 6.3437, 6.3783, 6.4131, 6.4480, 6.4831, 6.5183, 6.5537, 6.5893],
			38 => [6.6251, 6.6609, 6.6969, 6.7330, 6.7693, 6.8058, 6.8425, 6.8794, 6.9166, 6.9541],
			39 => [6.9917, 7.0294, 7.0673, 7.1053, 7.1434, 7.1817, 7.2202, 7.2589, 7.2977, 7.3367],
			40 => [7.3759, 7.414, 7.454, 7.494, 7.534, 7.574, 7.614, 7.654, 7.695, 7.737],
			41 => [7.778, 7.819, 7.861, 7.902, 7.943, 7.986, 8.029, 8.071, 8.114, 8.157],
			42 => [8.199, 8.242, 8.285, 8.329, 8.373, 8.417, 8.461, 8.505, 8.549, 8.594],
			43 => [8.639, 8.685, 8.730, 8.775, 8.821, 8.867, 8.914, 8.961, 9.007, 9.054],
			44 => [9.101, 9.147, 9.195, 9.243, 9.291, 9.339, 9.387, 9.435, 9.485, 9.534],
			45 => [9.583, 9.633, 9.682, 9.731, 9.781, 9.831, 9.882, 9.933, 9.983, 10.03],
			46 => [10.09, 10.14, 10.19, 10.24, 10.29, 10.35, 10.40, 10.40, 10.45, 10.56],
			47 => [10.61, 10.67, 10.72, 10.78, 10.83, 10.88, 10.94, 10.99, 11.05, 11.10],
			48 => [11.16, 11.22, 11.27, 11.33, 11.39, 11.45, 11.50, 11.56, 11.62, 11.68],
			49 => [11.74, 11.79, 11.85, 11.91, 11.97, 12.03, 12.09, 12.15, 12.21, 12.27],
			50 => [12.33, 12.39, 12.46, 12.52, 12.58, 12.64, 12.70, 12.77, 12.83, 12.89],
			51 => [12.96, 13.02, 13.09, 13.15, 13.22, 13.28, 13.347, 13.412, 13.479, 13.544],
			52 => [13.611, 13.678, 13.746, 13.812, 13.880, 13.948, 14.016, 14.084, 14.154, 14.223],
			53 => [14.292, 14.361, 14.431, 14.500, 14.571, 14.641, 14.712, 14.784, 14.856, 14.928],
			54 => [15.000, 15.072, 15.144, 15.217, 15.291, 15.364, 15.439, 15.513, 15.588, 15.663],
			55 => [15.737, 15.812, 15.887, 15.963, 16.040, 16.117, 16.195, 16.272, 16.349, 16.427],
			56 => [16.505, 16.585, 16.664, 16.743, 16.823, 16.903, 16.983, 17.064, 17.145, 17.227],
			57 => [17.308, 17.391, 17.473, 17.556, 17.639, 17.721, 17.805, 17.889, 17.973, 18.059],
			58 => [18.143, 18.228, 18.313, 18.400, 18.486, 18.573, 18.660, 18.748, 18.836, 18.924],
			59 => [19.012, 19.101, 19.190, 19.280, 19.369, 19.460, 19.550, 19.641, 19.732, 19.824],
			60 => [19.916, 20.008, 20.101, 20.194, 20.288, 20.381, 20.476, 20.570, 20.665, 20.760],
			61 => [20.856, 20.952, 21.048, 21.144, 21.241, 21.340, 21.438, 21.542, 21.636, 21.734],
			62 => [21.834, 21.934, 22.034, 22.134, 22.236, 22.337, 22.438, 22.541, 22.643, 22.746],
			63 => [22.849, 22.953, 23.057, 23.162, 23.267, 23.373, 23.478, 23.585, 23.691, 23.798],
			64 => [23.906, 24.013, 24.121, 24.230, 24.339, 24.449, 24.558, 24.669, 24.779, 24.891],
			65 => [25.003, 25.115, 25.227, 25.339, 25.453, 25.567, 25.682, 25.797, 25.911, 26.054],
			66 => [26.143, 26.259, 26.376, 26.494, 26.611, 26.728, 26.847, 26.966, 27.086, 27.206],
			67 => [27.326, 27.447, 27.568, 27.690, 27.812, 27.935, 28.058, 28.180, 28.304, 28.428],
			68 => [28.554, 28.679, 28.806, 28.932, 29.059, 29.186, 29.314, 29.442, 29.570, 29.699],
			69 => [29.828, 29.959, 30.090, 30.220, 30.352, 30.484, 30.617, 30.751, 30.884, 31.017],
			70 => [31.16, 31.29, 31.42, 31.56, 31.70, 31.84, 31.97, 32.12, 32.25, 32.38],
			71 => [32.52, 32.66, 32.80, 32.94, 33.08, 33.22, 33.37, 33.52, 33.65, 33.80],
			72 => [33.94, 34.09, 34.24, 34.38, 34.53, 34.68, 34.82, 34.97, 35.13, 35.28],
			73 => [35.42, 35.57, 35.73, 35.88, 36.04, 36.18, 36.34, 36.49, 36.65, 36.80],
			74 => [36.96, 37.12, 37.26, 37.42, 37.58, 37.74, 37.90, 38.06, 38.22, 38.38],
			75 => [38.54, 38.70, 38.86, 39.04, 39.20, 39.36, 39.53, 39.69, 39.85, 40.02],
			76 => [40.18, 40.36, 40.52, 40.69, 40.86, 41.02, 41.20, 41.37, 41.54, 41.72],
			77 => [41.88, 42.05, 42.22, 42.40, 42.57, 42.76, 42.93, 43.10, 43.29, 43.46],
			78 => [43.64, 43.82, 44.00, 44.18, 44.36, 44.54, 44.73, 44.90, 45.09, 45.28],
			79 => [45.46, 45.65, 45.84, 46.02, 46.21, 46.40, 46.58, 46.77, 46.96, 47.16],
			80 => [47.34, 47.53, 47.73, 47.92, 48.12, 48.32, 48.50, 48.70, 48.90, 49.10],
			81 => [49.29, 49.49, 49.69, 49.89, 50.22, 50.30, 50.64, 50.70, 50.90, 51.10],
			82 => [51.32, 51.52, 51.73, 51.93, 52.14, 52.34, 52.56, 52.77, 52.98, 53.20],
			83 => [53.41, 53.62, 53.84, 54.05, 54.26, 54.48, 54.70, 54.92, 55.13, 55.36],
			84 => [55.57, 55.78, 56.01, 56.22, 56.45, 56.68, 56.90, 57.13, 57.36, 57.58],
			85 => [57.81, 58.04, 58.26, 58.49, 58.73, 58.96, 59.18, 59.42, 59.65, 59.89],
			86 => [60.12, 60.34, 60.58, 60.82, 61.06, 61.29, 61.53, 61.77, 62.01, 62.25],
			87 => [62.49, 62.73, 62.97, 63.21, 63.46, 63.70, 63.95, 64.19, 64.45, 64.69],
			88 => [64.94, 65.19, 65.45, 65.69, 65.94, 66.19, 66.45, 66.70, 66.97, 67.22],
			89 => [67.47, 67.73, 67.99, 68.25, 68.51, 68.78, 69.03, 69.30, 69.57, 69.70],
			90 => [70.096, 70.362, 70.630, 70.898, 71.167, 71.437, 71.709, 71.981, 72.254, 72.527],
			91 => [72.801, 73.075, 73.351, 73.629, 73.907, 74.185, 74.465, 74.746, 75.027, 75.310],
			92 => [75.592, 75.876, 76.162, 76.447, 76.734, 77.022, 77.310, 77.599, 77.890, 78.182],
			93 => [78.474, 78.767, 79.060, 79.355, 79.651, 79.948, 80.245, 80.544, 80.844, 81.145],
			94 => [81.447, 81.749, 82.052, 82.356, 82.661, 82.968, 83.274, 83.582, 83.892, 84.202],
			95 => [84.513, 84.825, 85.138, 85.452, 85.766, 86.082, 86.400, 86.717, 87.036, 87.355],
			96 => [87.675, 87.997, 88.319, 88.643, 88.967, 89.293, 89.619, 89.947, 90.275, 90.605],
			97 => [90.935, 91.266, 91.598, 91.931, 92.266, 92.602, 92.939, 93.276, 93.615, 93.954],
			98 => [94.295, 94.636, 94.979, 95.323, 95.667, 96.012, 96.359, 96.707, 97.056, 97.407],
			99 => [97.757, 98.109, 98.463, 98.816, 99.172, 99.528, 99.885, 100.24, 100.60, 100.96],
			100 => [101.32, 101.69, 102.05, 102.42, 102.78, 103.15, 103.52, 103.89, 104.26, 104.63],
			101 => [105.00, 105.37, 105.75, 106.12, 106.50, 106.88, 107.26, 107.64, 108.02, 108.40]
		];
		
		$tekananUapAir = $tekananUapAirJenuh[$axisY][$axisX];
		// Konversi KPa ke mmHg
		$tekananuapAirmmHg = $tekananUapAir * 7.50062;
		// dd($tekananuapAirmmHg);
		return $tekananuapAirmmHg;
	}

    public function penentuanPv($suhu) {
	
		if($suhu > 0.0 && $suhu < 0.5 ) {
			$nil_pv = 4.6;
		}else if($suhu >= 0.5 && $suhu < 1 ) {
			$nil_pv = 4.8;
		}else if($suhu >= 1.0 && $suhu < 1.5) {
			$nil_pv = 4.9;
		}else if($suhu >= 1.5 && $suhu < 2) {
			$nil_pv = 5.1;
		}else if($suhu >= 2.0 && $suhu < 2.5) {
			$nil_pv = 5.3;
		}else if($suhu >= 2.5 && $suhu < 3) {
			$nil_pv = 5.5;
		}else if($suhu >= 3.0 && $suhu < 3.5) {
			$nil_pv = 5.7;
		}else if($suhu >= 3.5 && $suhu < 4) {
			$nil_pv = 5.9;
		}else if($suhu >= 4.0 && $suhu < 4.5) {
			$nil_pv = 6.1;
		}else if($suhu >= 4.5 && $suhu < 5) {
			$nil_pv = 6.3;
		}else if($suhu >= 5.0 && $suhu < 5.5) {
			$nil_pv = 6.5;
		}else if($suhu >= 5.5 && $suhu < 6) {
			$nil_pv = 6.8;
		}else if($suhu >= 6.0 && $suhu < 6.5) {
			$nil_pv = 7.0;
		}else if($suhu >= 6.5 && $suhu < 7) {
			$nil_pv = 7.3;
		}else if($suhu >= 7.0 && $suhu < 7.5) {
			$nil_pv = 7.5;
		}else if($suhu >= 7.5 && $suhu < 8) {
			$nil_pv = 7.8;
		}else if($suhu >= 8.0 && $suhu < 8.5) {
			$nil_pv = 8.0;
		}else if($suhu >= 8.5 && $suhu < 9) {
			$nil_pv = 8.3;
		}else if($suhu >= 9.0 && $suhu < 9.5) {
			$nil_pv = 8.6;
		}else if($suhu >= 9.5 && $suhu < 10) {
			$nil_pv = 8.9;
		}else if($suhu >= 10.0 && $suhu < 10.5) {
			$nil_pv = 9.2;
		}else if($suhu >= 10.5 && $suhu < 11) {
			$nil_pv = 9.5;
		}else if($suhu >= 11.0 && $suhu < 11.5) {
			$nil_pv = 9.8;
		}else if($suhu >= 11.5 && $suhu < 12) {
			$nil_pv = 10.2;
		}else if($suhu >= 12.0 && $suhu < 12.5) {
			$nil_pv = 10.5;
		}else if($suhu >= 12.5 && $suhu < 13) {
			$nil_pv = 10.9;
		}else if($suhu >= 13.0 && $suhu < 13.5) {
			$nil_pv = 11.2;
		}else if($suhu >= 13.5 && $suhu < 14) {
			$nil_pv = 11.6;
		}else if($suhu >= 14.0 && $suhu < 14.5) {
			$nil_pv = 12.0;
		}else if($suhu >= 14.5 && $suhu < 15) {
			$nil_pv = 12.4;
		}else if($suhu >= 15.0 && $suhu < 15.5) {
			$nil_pv = 12.8;
		}else if($suhu >= 15.5 && $suhu < 16) {
			$nil_pv = 13.2;
		}else if($suhu >= 16.0 && $suhu < 16.5) {
			$nil_pv = 13.6;
		}else if($suhu >= 16.5 && $suhu < 17) {
			$nil_pv = 14.1;
		}else if($suhu >= 17.0 && $suhu < 17.5) {
			$nil_pv = 14.5;
		}else if($suhu >= 17.5 && $suhu < 18) {
			$nil_pv = 15.0;
		}else if($suhu >= 18.0 && $suhu < 18.5) {
			$nil_pv = 15.5;
		}else if($suhu >= 18.5 && $suhu < 19) {
			$nil_pv = 16.0;
		}else if($suhu >= 19.0 && $suhu < 19.5) {
			$nil_pv = 16.5;
		}else if($suhu >= 19.5 && $suhu < 20) {
			$nil_pv = 17.0;
		}else if($suhu >= 20.0 && $suhu < 20.5) {
			$nil_pv = 17.5;
		}else if($suhu >= 20.5 && $suhu < 21) {
			$nil_pv = 18.1;
		}else if($suhu >= 21.0 && $suhu < 21.5) {
			$nil_pv = 18.7;
		}else if($suhu >= 21.5 && $suhu < 22) {
			$nil_pv = 19.2;
		}else if($suhu >= 22.0 && $suhu < 22.5) {
			$nil_pv = 19.8;
		}else if($suhu >= 22.5 && $suhu < 23) {
			$nil_pv = 20.4;
		}else if($suhu >= 23.0 && $suhu < 23.5) {
			$nil_pv = 21.1;
		}else if($suhu >= 23.5 && $suhu < 24) {
			$nil_pv = 21.7;
		}else if($suhu >= 24.0 && $suhu < 24.5) {
			$nil_pv = 22.4;
		}else if($suhu >= 24.5 && $suhu < 25) {
			$nil_pv = 23.1;
		}else if($suhu >= 25.0 && $suhu < 25.5) {
			$nil_pv = 23.8;
		}else if($suhu >= 25.5 && $suhu < 26) {
			$nil_pv = 24.5;
		}else if($suhu >= 26.0 && $suhu < 26.5) {
			$nil_pv = 25.2;
		}else if($suhu >= 26.5 && $suhu < 27) {
			$nil_pv = 26.0;
		}else if($suhu >= 27.0 && $suhu < 27.5) {
			$nil_pv = 26.7;
		}else if($suhu >= 27.5 && $suhu < 28) {
			$nil_pv = 27.5;
		}else if($suhu >= 28.0 && $suhu < 28.5) {
			$nil_pv = 28.4;
		}else if($suhu >= 28.5 && $suhu < 29) {
			$nil_pv = 29.2;
		}else if($suhu >= 29.0 && $suhu < 29.5) {
			$nil_pv = 30.1;
		}else if($suhu >= 29.5 && $suhu < 30) {
			$nil_pv = 30.9;
		}else if($suhu >= 30.0 && $suhu < 30.5) {
			$nil_pv = 31.8;
		}else if($suhu >= 30.5 && $suhu < 31) {
			$nil_pv = 32.8;
		}else if($suhu >= 31.0 && $suhu < 31.5) {
			$nil_pv = 33.7;
		}else if($suhu >= 31.5 && $suhu < 32) {
			$nil_pv = 34.7;
		}else if($suhu >= 32.0 && $suhu < 32.5) {
			$nil_pv = 35.7;
		}else if($suhu >= 32.5 && $suhu < 33) {
			$nil_pv = 36.7;
		}else if($suhu >= 33.0 && $suhu < 33.5) {
			$nil_pv = 37.7;
		}else if($suhu >= 33.5 && $suhu < 34) {
			$nil_pv = 38.8;
		}else if($suhu >= 34.0 && $suhu < 34.5) {
			$nil_pv = 39.9;
		}else if($suhu >= 34.5 && $suhu < 35) {
			$nil_pv = 41.0;
		}else if($suhu >= 35.0 && $suhu < 35.5) {
			$nil_pv = 42.2;
		}else if($suhu >= 35.5 && $suhu < 36) {
			$nil_pv = 43.4;
		}else if($suhu >= 36.0 && $suhu < 36.5) {
			$nil_pv = 44.6;
		}else if($suhu >= 36.5 && $suhu < 37) {
			$nil_pv = 45.8;
		}else if($suhu >= 37.0 && $suhu < 37.5) {
			$nil_pv = 47.1;
		}else if($suhu >= 37.5 && $suhu < 38) {
			$nil_pv = 48.4;
		}else if($suhu >= 38.0 && $suhu < 38.5) {
			$nil_pv = 49.7;
		}else if($suhu >= 38.5 && $suhu < 39) {
			$nil_pv = 51.1;
		}else if($suhu >= 39.0 && $suhu < 39.5) {
			$nil_pv = 52.5;
		}else if($suhu >= 39.5 && $suhu < 40) {
			$nil_pv = 53.9;
		}else if($suhu >= 40.0 && $suhu < 40.5) {
			$nil_pv = 55.3;
		}else if($suhu >= 40.5 && $suhu < 41) {
			$nil_pv = 56.8;
		}else if($suhu >= 41.0 && $suhu < 41.5) {
			$nil_pv = 58.4;
		}else if($suhu >= 41.5 && $suhu < 42) {
			$nil_pv = 59.9;
		}else if($suhu >= 42.0 && $suhu < 42.5) {
			$nil_pv = 61.5;
		}else if($suhu >= 42.5 && $suhu < 43) {
			$nil_pv = 63.1;
		}else if($suhu >= 43.0 && $suhu < 43.5) {
			$nil_pv = 64.8;
		}else if($suhu >= 43.5 && $suhu < 44) {
			$nil_pv = 66.5;
		}else if($suhu >= 44.0 && $suhu < 44.5) {
			$nil_pv = 68.3;
		}else if($suhu >= 44.5 && $suhu < 45) {
			$nil_pv = 70.1;
		}else if($suhu >= 45.0 && $suhu < 45.5) {
			$nil_pv = 71.9;
		}else if($suhu >= 45.5 && $suhu < 46) {
			$nil_pv = 73.7;
		}else if($suhu >= 46.0 && $suhu < 46.5) {
			$nil_pv = 75.7;
		}else if($suhu >= 46.5 && $suhu < 47) {
			$nil_pv = 77.6;
		}else if($suhu >= 47.0 && $suhu < 47.5) {
			$nil_pv = 79.6;
		}else if($suhu >= 47.5 && $suhu < 48) {
			$nil_pv = 81.6;
		}else if($suhu >= 48.0 && $suhu < 48.5) {
			$nil_pv = 83.7;
		}else if($suhu >= 48.5 && $suhu < 49) {
			$nil_pv = 85.8;
		}else if($suhu >= 49.0 && $suhu < 49.5) {
			$nil_pv = 88.0;
		}else if($suhu >= 49.5 && $suhu < 50) {
			$nil_pv = 90.2;
		}else if($suhu >= 50.0 && $suhu < 50.5) {
			$nil_pv = 92.5;
		}else if($suhu >= 50.5 && $suhu < 51) {
			$nil_pv = 94.8;
		}else if($suhu >= 51.0 && $suhu < 51.5) {
			$nil_pv = 97.2;
		}else if($suhu >= 51.5 && $suhu < 52) {
			$nil_pv = 99.6;
		}else if($suhu >= 52.0 && $suhu < 52.5) {
			$nil_pv = 102.1;
		}else if($suhu >= 52.5 && $suhu < 53) {
			$nil_pv = 104.6;
		}else if($suhu >= 53.0 && $suhu < 53.5) {
			$nil_pv = 107.2;
		}else if($suhu >= 53.5 && $suhu < 54) {
			$nil_pv = 109.8;
		}else if($suhu >= 54.0 && $suhu < 54.5) {
			$nil_pv = 112.5;
		}else if($suhu >= 54.5 && $suhu < 55) {
			$nil_pv = 115.2;
		}else if($suhu >= 55.0 && $suhu < 55.5) {
			$nil_pv = 118.0;
		}else if($suhu >= 55.5 && $suhu < 56) {
			$nil_pv = 120.9;
		}else if($suhu >= 56.0 && $suhu < 56.5) {
			$nil_pv = 123.8;
		}else if($suhu >= 56.5 && $suhu < 57) {
			$nil_pv = 126.7;
		}else if($suhu >= 57.0 && $suhu < 57.5) {
			$nil_pv = 130.8;
		}else if($suhu >= 57.5 && $suhu < 58) {
			$nil_pv = 132.9;
		}else if($suhu >= 58.0 && $suhu < 58.5) {
			$nil_pv = 136.0;
		}else if($suhu >= 58.5 && $suhu < 59) {
			$nil_pv = 139.2;
		}else if($suhu >= 59.0 && $suhu < 59.5) {
			$nil_pv = 142.5;
		}else if($suhu >= 59.5 && $suhu < 60) {
			$nil_pv = 145.9;
		}else if($suhu >= 60.0 && $suhu < 60.5) {
			$nil_pv = 149.3;
		}else if($suhu >= 60.5 && $suhu < 61) {
			$nil_pv = 152.8;
		} else {
			throw new Exception('Error karena suhu tidak sesuai, suhu di data lapangan adalah ' . $suhu);
		}

		return $nil_pv;
	}
}