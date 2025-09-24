<?php

namespace App\HelpersFormula;

class Asiditas {
    public static function index($data, $id_parameter, $mdl) {
        // Asiditas sebagai CaCO3 (mg/L) =((((A*B)-(C*D))*50000)/v)X Fp
        $a = $data->titrasi_naoh;
        $b = $data->normalitas_naoh;
        $c = $data->penggunaan_h2so4;
        $d = $data->normalitas_h2so4;
        $v = $data->vs;
        $fp = $data->fp;
    
        $rumus = number_format((((($a * $b) - ($c * $d)) * 50000) / $v) * $fp, 4);
        
        if(!is_null($mdl) && $rumus < $mdl) {
            $rumus = '<' . $mdl;
        }

        $rumus = str_replace(",", "", $rumus);

        $processed = [
                'hasil' => $rumus,
                'hasil_2' => '',
                'rpd' => '',
                'recovery' => '',
            ];
            
            return $processed;
    }
}