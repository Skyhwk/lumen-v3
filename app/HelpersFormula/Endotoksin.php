<?php

namespace App\HelpersFormula;

class Endotoksin
{
    public static function index($data, $id_parameter, $mdl)
    {
        $rumus = (object) [
            'sensitivitas_reagen' => $data->sr,
            'hasil_pengujian' => $data->hp,
            'hasil_uji_control' => $data->control,
        ];

        $data = [
            // 'hasil' => json_encode($rumus),
            'hasil' => $data->hp,
            'hasil_2' => json_encode($rumus),
            'rpd' => '',
            'recovery' => '',
        ];
        // dd($data);
        return $data;
    }
}