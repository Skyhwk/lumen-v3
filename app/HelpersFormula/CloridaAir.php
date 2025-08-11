<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class CloridaAir
{
    public function index($data, $id_parameter, $mdl)
    {
        // $rumus = number_format((($data->vts - $data->vtb) * $data->kt * 35450) / $data->vs * $data->fp, 2);
        // $rumus = number_format((($data->vts - $data->vtb) * $data->kt * 35450) / 100, 4);
        $rumus = number_format(((($data->vts - $data->vtb) * $data->kt * 35450) / 100) * $data->fp, 4);
        $nilai = floatval(str_replace(",", "", $rumus));
        // $NaCl = number_format($nilai * 1.65, 2);

        if (!is_null($mdl) && $nilai < $mdl) {
            $rumus = '<' . $mdl;
        } else {
            $rumus = str_replace(",", "", $rumus);
        }

        return [
            'hasil' => $rumus,
            'hasil_2' => '',
            'rpd' => '',
            'recovery' => '',
        ];
    }
}
