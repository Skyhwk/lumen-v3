<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class ColoriPadatanNO3
{
    public function index($data, $id_parameter, $mdl){
        if($id_parameter == 420) { //ColoriPadatanNO3
			$rumus = ($data->hp * 4.4268) * $data->fp;
			if($rumus < $mdl) $rumus = '<' . $mdl;
		}

        $rumus = str_replace(",", "", $rumus);

        $data = [
            'hasil' => $rumus,
            'hasil_2' => '',
            'rpd' => '',
            'recovery' => '',
        ];
        return $data;
    }
}