<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupPM_TSP
{
    public function index($data, $id_parameter, $mdl) {
        $ks = null;
        // dd(count($data->ks));
        $kb = null;

        $Ta = floatval($data->suhu) + 273;
        $Qs = null;
        $C = null;
        $C1 = null;
        $C2 = null;
        $C3 = null;
        $C4 = null;
        $C5 = null;
        $C6 = null;
        $C7 = null;
        $C8 = null;
        $C9 = null;
        $C10 = null;
        $C11 = null;
        $C12 = null;
        $C13 = null;
        $C14 = null;
        $C15 = null;
        $C16 = null;
        $w1 = null;
        $w2 = null;
        $b1 = null;
        $b2 = null;
        $Vstd = null;
        $V = null;
        $Vu = null;
        $Vs = null;
        $vl = null;
        $st = null;
        $satuan = null;

        $use_vstd = ['TSP','TSP (24 Jam)'];
        $use_V = ['TSP (6 Jam)','TSP (8 Jam)'];
        // dd(in_array($data->tipe_data, $use_vstd));
        if (in_array($data->parameter, $use_vstd)) {
            $Vstd = \str_replace(",", "",number_format($data->nilQs * $data->durasi, 4));
            if((int)$Vstd <= 0) {
                $C = 0;
                $Qs = 0;
                $C1 = 0;
            }else {
                $C = \str_replace(",", "", number_format((($data->w2 - $data->w1) * 10 ** 6) / $Vstd, 4));
                $Qs = $data->nilQs;
                $C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 6));
            }

            if ($id_parameter == 342) {
                if (floatval($C) < 1.5151)
                    $C = '<1.5151';
                if (floatval($C1) < 0.0015)
                    $C1 = '<0.0015';
            } else if ($id_parameter == 343) {
                // if (floatval($C) < 0.0631)
                // 	$C = '<0.0631';
                // if (floatval($C1) < 0.000063)
                // 	$C1 = '<0.000063';
                if (floatval($C) < 1)
                    $C = '<1';
                if (floatval($C1) < 0.001)
                    $C1 = '<0.001';
            }
            $w1 = $data->w1;
            $w2 = $data->w2;

        } else if (in_array($data->parameter, $use_V)) {
            // dd($rerataFlow, $dur);
            $V = \str_replace(",", "",($data->average_flow * $data->durasi));
            // dd($dur, $rerataFlow, $V);
            $C16 = \str_replace(",", "", number_format(((($data->w2 - $data->w1) - ($data->b2 - $data->b1)) / $V) * 1000, 6));
            $C15 = \str_replace(",", "", number_format(floatval($C16) / 1000 , 6));
            // if ($id_param == 345) {
            // 	if (floatval($C) < 0.0021)
            // 		$C = '<0.0021';
            // 	if (floatval($C1) < 2.1000)
            // 		$C1 = '<2.1000';
            // }else if($id_param == 342) {
            // 	if (floatval($C) < 16.7)
            // 		$C = '<16.7';
            // 	if (floatval($C1) < 0.0167)
            // 		$C1 = '<0.0167';
            // }
            if ($id_parameter == 345) {
                if (floatval($C) < 0.001)
                    $C = '<0.001';
                if (floatval($C1) < 0.001)
                    $C1 = '<0.001';
            }else if($id_parameter == 342) {
                if (floatval($C) < 0.001)
                    $C = '<0.001';
                if (floatval($C1) < 0.001)
                    $C1 = '<0.001';
            }
            $w1 = $data->w1;
            $w2 = $data->w2;
            $b1 = $data->b1;
            $b2 = $data->b2;
            // dd($C);
        }
        $data = [
            'tanggal_terima' => $data->tanggal_terima,
            'flow' => $data->average_flow,
            'durasi' => $data->durasi,
            // 'durasi' => $waktu,
            'tekanan_u' => $data->tekanan,
            'suhu' => $data->suhu,
            'k_sample' => $ks,
            'k_blanko' => $kb,
            'Qs' => $Qs,
            'w1' => $w1,
            'w2' => $w2,
            'b1' => $b1,
            'b2' => $b2,
            'satuan' => $satuan,
            'C' => $C,
            'C1' => $C1,
            'C2' => $C2,
            'C15' => $C15,
            'C16' => $C16,
            'vl' => $vl,
            'st' => $st,
            'Vstd' => $Vstd,
            'V' => $V,
            'Vu' => $Vu,
            'Vs' => $Vs,
            'Ta' => $Ta,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        return $data;
    }

}
