<?php 

namespace App\HelpersFormula;
use Carbon\Carbon;

class Bau
{

    public function index($data, $id_parameter, $mdl) {
        if(isset($data->nilai_terkecil)){ // Bau Baru
            if($data->hp == "Tidak_Berbau"){
                $rumus = "Tidak Berbau";
            }else{
                $rumus = number_format(($data->nilai_terkecil + (200 - $data->nilai_terkecil))/$data->nilai_terkecil, 1, '.', '');
                $rumus = "Berbau : " .$rumus;
            }
        }else{ // Bau lama
            $rumus = $data->hp;
        }

        $data = [
            'hasil' => $rumus,
            'hasil_2' => '',
            'rpd' => '',
            'recovery' => '',
        ];
        return $data;
    }
}