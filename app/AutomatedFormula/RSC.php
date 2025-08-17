<?php

namespace App\AutomatedFormula;

use App\Models\Titrimetri;
use App\Models\WsValueAir;
use Carbon\Carbon;

class RSC
{
    public function index($required_parameter, $parameter, $no_sampel, $tanggal_terima) {
        $check = Titrimetri::where('parameter', $parameter)->where('no_sampel', $no_sampel)->where('is_active', true)->first();
        if(isset($check->id)){
            return ;
        }
        $all_value = [];
        $hasil = 0;
        foreach($required_parameter as $key => $value){
            $data = Titrimetri::where('parameter', $value)->where('no_sampel', $no_sampel)->where('is_active', 1)->first();
            if($data){
                $result = WsValueAir::where('id_titrimetri', $data->id)->first();
                if(strpos($result->hasil, '<') !== false){
                    $all_value[] = 0;
                } else {
                    if(strpos($result->hasil, ',') !== false){
                        $all_value[] = str_replace(',', '', $result->hasil);
                    }else{
                        $all_value[] = $result->hasil;
                    }
                }
            }
        }
        if(count($all_value) == 4){
            // ------------------------ HCO3- ----------- CO3- ----------- Ca ------------- Mg ------- //
            $hasil = number_format(($all_value[0] + $all_value[1]) - ($all_value[2] + $all_value[3]), 4);

            $insert                     = new Titrimetri();
            $insert->no_sampel          = $no_sampel;
            $insert->parameter          = $parameter;
            $insert->tanggal_terima     = $tanggal_terima;
            $insert->jenis_pengujian    = 'sample';
            $insert->template_stp       = 4;
            $insert->created_by         = 'SYSTEM';
            $insert->created_at         = Carbon::now();
            $insert->save();

            $ws_hasil                   = new WsValueAir();
            $ws_hasil->id_titrimetri    = $insert->id;
            $ws_hasil->no_sampel        = $no_sampel;
            $ws_hasil->hasil            = $hasil;
            $ws_hasil->save();
    
            return $hasil;
        }

    }
}