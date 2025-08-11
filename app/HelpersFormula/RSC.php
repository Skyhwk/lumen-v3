<?php

namespace App\HelpersFormula;

class RSC
{
    public function index($data, $id_parameter, $mdl)
    {
        $data_hco3 = (object) [
            'vtm' => $data->hco3_vtm,
            'vtp' => $data->hco3_vtp,
            'kt' => $data->hco3_kt,
            'vs' => $data->hco3_vs,
            'ph' => $data->hco3_ph,
        ];

        $data_co3 = (object) [
            'vtm' => $data->co3_vtm,
            'vtp' => $data->co3_vtp,
            'kt' => $data->co3_kt,
            'vs' => $data->co3_vs,
            'ph' => $data->co3_ph,
        ];

        $data_ca = (object) [
            'vts' => $data->ca_vts,
            'vs' => $data->ca_vs,
            'kt' => $data->ca_kt,
            'fp' => $data->ca_fp,
        ];

        $data_mg = (object) [
            'vs' => $data->mg_vs,
            'vts' => $data->mg_vts,
            'vtb' => $data->mg_vtb,
            'kt' => $data->mg_kt,
            'fp' => $data->mg_fp,
        ];

        $hasil_HCO3 = $this->HCO3($data_hco3, 1);
        $hasil_CO3 = $this->CO3($data_co3, 1);
        $hasil_CA = $this->CA($data_ca, 0.006);
        $hasil_Mg = $this->Mg($data_mg, 1.56);

        if (strpos($hasil_HCO3, '<') !== false) {
            $hasil_HCO3 = 0;
        }
        if (strpos($hasil_CO3, '<') !== false) {
            $hasil_CO3 = 0;
        }
        if (strpos($hasil_CA, '<') !== false) {
            $hasil_CA = 0;
        }
        if (strpos($hasil_Mg, '<') !== false) {
            $hasil_Mg = 0;
        }

        // (HCO₃⁻ + CO₃²⁻) - (Ca + Mg)
        $rumus = ($hasil_HCO3 + $hasil_CO3) - ($hasil_CA + $hasil_Mg);

        $rumus = str_replace(',', '', $rumus);
        // dd($rumus, $hasil_CA, $hasil_CO3, $hasil_HCO3, $hasil_Mg);
        return [
            'hasil' => $rumus,
            'hasil_2' => '',
            'rpd' => '',
            'recovery' => '',
        ];
    }

    private function HCO3($data, $mdl)
    {
        $t = $data->vtm;
        $p = $data->vtp;
        $kt = $data->kt;
        $vs = $data->vs;
        $ph = $data->ph;

        $half = $t * 50 / 100;

        if ($p == 0) {
            // dd('rumus 1');
            $data->vth = 0;
            $co3 = 0;
            $t_convert = $t;

            $rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
        } else if ($p < $half) {
            // dd('rumus 2');
            $data->vth = 0;
            $co3 = 2 * $p;
            $t_convert = (($t - (2 * $p)) + (pow(10, ($ph - 14))));
            // dd($t_convert);
            $rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
        } else if ($p == $half) {
            // dd('rumus 3');
            $data->vth = 0;
            $co3 = 2 * $p;
            $t_convert = 0;

            $rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
        } else if ($p > $half) {
            // dd('rumus 4');
            $data->vth = (2 * $p) - $t;
            $co3 = 2 * ($t - $p);
            $t_convert = 0;

            $rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
        } else if ($p == $data->vth) {
            // dd('rumus 5');
            $data->vth = $t;
            $co3 = 0;
            $t_convert = 0;

            $rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
        }

        if (!is_null($mdl) && $rumus < $mdl) {
            $rumus = '<' . $mdl;
        }

        $rumus = str_replace(',', '', $rumus);

        return $rumus;
    }

    private function CO3($data, $mdl)
    {
        $t = $data->vtm;
        $p = $data->vtp;
        $kt = $data->kt;
        $vs = $data->vs;
        $ph = $data->ph;

        $half = $t * 50 / 100;

        if ($p == 0) {
            // dd('rumus 1');
            $t_convert = 0;

            $rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
        } else if ($p < $half) {
            // dd('rumus 2');
            $t_convert = ((2 * $p) - (2 * pow(10, ($ph - 14))));
            // dd($t_convert);
            $rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
        } else if ($p == $half) {
            // dd('rumus 3');
            $t_convert = ((2 * $p) - (2 * pow(10, ($ph - 14))));

            $rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
        } else if ($p > $half) {
            // dd('rumus 4');
            $t_convert = 2 * ($t - $p);

            $rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
        } else if ($p == $t) {
            // dd('rumus 5');
            $t_convert = 0;

            $rumus = number_format(($t_convert * $kt * 50000) / $vs, 2);
        }

        if (!is_null($mdl) && $rumus < $mdl) {
            $rumus = '<' . $mdl;
        }

        $rumus = str_replace(',', '', $rumus);

        return $rumus;
    }

    private function CA($data, $mdl)
    {
        $rumus = number_format(((1000 / $data->vs) * $data->vts * $data->kt * 40) * $data->fp, 4);

        if (!is_null($mdl) && $rumus < $mdl)
            $rumus = '<' . $mdl;

        $rumus = str_replace(',', '', $rumus);
        return $rumus;
    }

    private function Mg($data, $mdl)
    {
        $rumus = (1000 / $data->vs) * ABS($data->vts - $data->vtb) * $data->kt * 24.3;

        if (!is_null($mdl) && $rumus < $mdl)
            $rumus = '<' . $mdl;

        $rumus = str_replace(',', '', $rumus);
        return $rumus;
    }
}