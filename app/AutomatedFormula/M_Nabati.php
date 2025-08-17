<?php

namespace App\AutomatedFormula;

use App\Models\Gravimetri;
use App\Models\Colorimetri;
use App\Models\WsValueAir;
use Carbon\Carbon;

class M_Nabati
{
    public function index($required_parameter, $parameter, $no_sampel, $tanggal_terima) {
        $check = Colorimetri::where('parameter', $parameter)->where('no_sampel', $no_sampel)->where('template_stp', 76)->where('is_active', true)->where('is_total', false)->first();
        if(isset($check->id)){
            return ;
        }
        $all_value = [];
        $hasil = 0;
        foreach($required_parameter as $key => $value){
            $data = Gravimetri::where('parameter', $value)->where('no_sampel', $no_sampel)->where('template_stp', 3)->where('is_active', true)->where('is_total', true)->first();
            if($data){
                $result = WsValueAir::where('id_gravimetri', $data->id)->first();
                if(strpos($result->hasil, '<') !== false){
                    $all_value[] = 0;
                } else {
                    if(strpos($result->hasil, ',') !== false){
                        $all_value[] = str_replace(',', '', $$result->hasil);
                    }else{
                        $all_value[] = $result->hasil;
                    }
                }
            }
        }
        if(count($all_value) == 2){

            $hasil = number_format($all_value[0] - $all_value[1], 4);

            if($hasil < 0.86){
                $hasil = '<0.86';
            }

            $insert                     = new Colorimetri();
            $insert->no_sampel          = $no_sampel;
            $insert->parameter          = $parameter;
            $insert->tanggal_terima     = $tanggal_terima;
            $insert->jenis_pengujian    = 'sample';
            $insert->template_stp       = 76;
            $insert->created_by         = 'SYSTEM';
            $insert->created_at         = Carbon::now();
            $insert->is_total           = false;
            $insert->save();

            $ws_hasil                   = new WsValueAir();
            $ws_hasil->id_colorimetri   = $insert->id;
            $ws_hasil->no_sampel        = $no_sampel;
            $ws_hasil->hasil            = $hasil;
            $ws_hasil->save();
            // dd('masuk');
            return $hasil;
        }

    }
}