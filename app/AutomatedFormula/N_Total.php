<?php

namespace App\AutomatedFormula;

use App\Models\Colorimetri;
use App\Models\Titrimetri;
use App\Models\WsValueAir;
use Carbon\Carbon;

class N_Total
{
    public function index($required_parameter, $parameter, $no_sampel, $tanggal_terima) {
        $check = Colorimetri::where('parameter', $parameter)->where('no_sampel', $no_sampel)->where('template_stp', 76)->where('is_active', true)->where('is_total', false)->first();
        if(isset($check->id)){
            return ;
        }
        $all_value = [];
        $hasil = 0;
        foreach($required_parameter as $key => $value){
            $n_organik = ['N-Organik', 'N-Organik (NA)'];
            if(in_array($value, $n_organik)){
                $data = Titrimetri::where('parameter', $value)->where('no_sampel', $no_sampel)->where('is_active', true)->where('is_total', true)->first();
            }else{
                $data = Colorimetri::where('parameter', $value)->where('no_sampel', $no_sampel)->where('is_active', true)->where('is_total', true)->first();
            }

            if($data){
                $result = WsValueAir::where(in_array($value, $n_organik) ? 'id_titrimetri' : 'id_colorimetri', $data->id)->first();
                if(strpos($result->hasil, '<') !== false){
                    $all_value[] = 0;
                } else {
                    if(strpos($result->hasil, ',') !== false){
                        $all_value[] = str_replace(',', '', $result->hasil);
                    }else{
                        $all_value[] = $result->hasil;
                    }
                }

                /** 
                 * Berhenti ketika item kedua karena 2 parameter pertama 
                 * itu sudah mewakili satu parameter wajib yang harus ada 
                 * ketika akan menghitung parameter ini
                 * */
                if (count($all_value) == 0 && $key == 1) {
                    break;
                } else if (count($all_value) == 4) {
                    break;
                }
            }
        }

        if(count($all_value) == 4){

            $hasil = str_replace(',','',number_format(array_sum($all_value), 4));
            if($hasil < 0.0009){
                $hasil = '<0.0009';
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
    
            return $hasil;
        }
    }
}