<?php
namespace App\HelpersFormula;

class TH 
{
    public function index($data, $id_parameter, $mdl)
    {
        $oksalat 	= 0.0100;
        $KMnO4 		= 31.6;
        $vts = $data->vts;
        $vtb = $data->vtb;
        $kt = $data->kt;
        $vs = $data->vs;
        $fp = $data->fp;
        $rumus = (($vts * $kt * 1000 ) / $vs ) * $fp;
        if(!is_null($mdl) && $rumus< $mdl){
            $rumus= '<' . $mdl;
        } else {
            $rumus = str_replace(",", "", $rumus);
        }

        $processed = [
            'hasil' => $rumus,
            'hasil_2' => '',
            'rpd' => '',
            'recovery' => '',
        ];
        return $processed;
    }
}