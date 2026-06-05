<?php 

namespace App\HelpersFormula;

class MgHardness
{
    public function index($data, $id_parameter, $mdl)
    {
        $rumus =
            (1000 / (float)$data->vcu) *
            (
                abs((float)$data->v_edta_a - (float)$data->v_edta_b)
                * (float)$data->m_edta
                * 24.3
            ) *
            (float)$data->fp;

        // pembulatan jika perlu
        $rumus = round($rumus, 4);

        if (!is_null($mdl) && $rumus < $mdl) {
            $rumus = '<' . $mdl;
        }

        return [
            'hasil' => $rumus,
            'hasil_2' => '',
            'rpd' => '',
            'recovery' => '',
        ];
    }
}