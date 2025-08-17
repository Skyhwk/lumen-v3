<?php

namespace App\HelpersFormula;

class DirectAOX
{
    public function index($data, $id_parameter, $mdl){
        $data_tds = (object)[
            'hp' => $data->tds_hp,
            'fp' => $data->tds_fp,
        ];
        
        $data_cl2 = (object)[
            'hp' => $data->cl2_hp,
            'fp' => $data->cl2_fp,
        ];
        $data_f = (object)[
            'hp' => $data->f_hp,
            'fp' => $data->f_fp,
        ];
        
        $data_cl = (object)[
            'vts' => $data->cl_vts,
            'vtb' => $data->cl_vtb,
            'kt' => $data->cl_kt,
            'fp' => $data->cl_fp,
        ];

        // (TDS x Cl-)+(TDS x F)+(TDS x Cl2)
        $hasil_tds = $this->Perkalian('tds',$data_tds, 1);
        $hasil_cl2 = $this->Perkalian('cl2',$data_cl2, 0.01);
        $hasil_f = $this->F($data_f, 0.0038);
        $hasil_cl = $this->Cl($data_cl, 0.4);

        if (strpos($hasil_tds, '<') !== false) {
            $hasil_tds = 0;
        }

        if (strpos($hasil_cl2, '<') !== false) {
            $hasil_cl2 = 0;
        }

        if (strpos($hasil_f, '<') !== false) {
            $hasil_f = 0;
        }

        if (strpos($hasil_cl, '<') !== false) {
            $hasil_cl = 0;
        }

        $rumus = number_format(($hasil_tds * $hasil_cl) + ($hasil_tds * $hasil_f) + ($hasil_tds * $hasil_cl2), 4);

        $rumus = str_replace(',','', $rumus);
        
        return [
            'hasil' => $rumus,
            'hasil_2' => '',
            'rpd' => '',
            'recovery' => '',
        ];
    }

    private function Perkalian($type, $data, $mdl){
        $rumus = $data->hp * $data->fp;
        // cl2 = cl2 * 10 ^ -6
        if ($type == 'cl2') $rumus = $rumus * pow(10, -6);

        // if(!is_null($mdl) && $rumus<$mdl)$rumus = '<' . $mdl;
        $rumus = str_replace(',','', $rumus);
        return $rumus;
    }

    private function Cl($data, $mdl){
        $rumus = ((($data->vts - $data->vtb) * $data->kt * 35450) / 100) * $data->fp;
        // cl- = cl- * 10 ^ -6
        $rumus = $rumus * pow(10, -6);

        // if(!is_null($mdl) && $rumus<$mdl)$rumus = '<' . $mdl;
        $rumus = str_replace(',','', $rumus);
        return $rumus;
    }

    private function F($data, $mdl){
        $rumus = (($data->hp / 50) * (50 / 50)) * $data->fp;
        // f = f * 10 ^ -6
        $rumus = $rumus * pow(10, -6);
        
        // if(!is_null($mdl) && $rumus<$mdl)$rumus = '<' . $mdl;
        $rumus = str_replace(',','', $rumus);
        return $rumus;
    }
}