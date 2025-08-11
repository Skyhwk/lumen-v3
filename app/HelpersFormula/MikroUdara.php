<?php

namespace App\HelpersFormula;

class MikroUdara
{
    public function index($data, $id_parameter, $mdl)
    {
        $cfu = $data->cfu;
        $debit = $data->debit;
        $waktu = $data->waktu;

        $V = ($debit * $waktu) / 1000;
        $rumus = $cfu / $V;

        return [
            'hasil' => $rumus,
        ];
    }
}
