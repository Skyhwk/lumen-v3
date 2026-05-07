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
                $nilai = ($data->nilai_terkecil + (200 - $data->nilai_terkecil)) / $data->nilai_terkecil;

                $rumus = "Berbau : " . number_format(round($nilai, 1), 1, '.', '');
            }
        }else{ // Bau lama
            $rumus = $data->hp;
        }

        $data = [
            'hasil' => $rumus,
            'hasil_2' => $nilai ?? null,
            'rpd' => '',
            'recovery' => '',
        ];
        return $data;
    }
}