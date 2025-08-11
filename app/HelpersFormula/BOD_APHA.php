<?php

namespace App\HelpersFormula;

class BOD_APHA
{
    public function index($data, $id_parameter, $mdl)
    {
        if(isset($data->type) && $data->type == 'permanganat'){
            $A      = $data->vts;
            $B      = $data->vtb;
            $N      = $data->kt;
            $FP     = $data->fp;

            $oksalat = 0.0100;
            $KMnO4  = 31.6;
            
            // (((((10-A)B-(10*N))*1*31.6*1000)/100)*FP)/1.423456789
            $rumus = (((ABS((( 10 - $A ) * $N) - ( 10 * $oksalat )) * 1 * $KMnO4 * 1000) / 100) * $FP) / 1.423456789;
            
            $rumus = number_format($rumus, 2);
            if($rumus < 0.3){
                $rumus = '<0.3';
            }
            $rumus = str_replace(',', '', $rumus);
            
            $processed = [
                'hasil' => $rumus,
                'hasil_2' => '',
                'rpd' => '',
                'recovery' => '',
            ];
        }else{
            if(isset($data->oksigen_sebelum)){
                $d1 = $data->oksigen_sebelum;
                $d2 = $data->oksigen_setelah;
            }else{
                $d1 = ($data->kt / 0.025) * $data->vts_sebelum;
                $d2 = ($data->kt / 0.025) * $data->vts_setelah;
            }
            
            $seed = $data->seed;
            $vs = $data->vs;
            $fp = $data->fp;

            $rumus = number_format((($d1 - $d2) - ($seed * $vs)) / $fp, 4);

            $selisih_d = number_format($d1 - $d2,4);

            // dump($rumus);

            if($selisih_d >= 2.0 && $d2 >= 1){ # Ketentuan 1
                $rumus = $rumus;
            } else if ($selisih_d < 2.0 && $fp == 1){ # Ketentuan 2
                $rumus = '<'.$selisih_d;
            } else if ($d2 < 1 && $fp > 1){ # Ketentuan 3
                $rumus = '>'.$rumus;
            } else if ($selisih_d < 1 && $fp > 1){ # Ketentuan 4
                $rumus = '<'.$rumus;
            }

            $rumus = str_replace(',', '', $rumus);
            // dd($rumus);
            $processed = [
                'hasil' => $rumus,
                'hasil_2' => '',
                'rpd' => '',
                'recovery' => '',
            ];
        }
		return $processed;
    }
}