<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class LingkunganHidupSO2
{
    public function index($data, $id_parameter, $mdl)
    {
        $ks = null;
        // dd(count($data->ks));
        if (is_array($data->ks)) {
            $ks = number_format(array_sum($data->ks) / count($data->ks), 4);
        } else {
            $ks = $data->ks;
        }
        $kb = null;
        if (is_array($data->kb)) {
            $kb = number_format(array_sum($data->kb) / count($data->kb), 4);
        } else {
            $kb = $data->kb;
        }

        $Ta = floatval($data->suhu) + 273;
        $Qs = null;
        $C = null;
        $C1 = null;
        $C2 = null;
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

        $C_value = $C1_value = $C2_value = $C14_value = $C15_value = $C16_value = [];

        $Vu = \str_replace(",", "", number_format($data->average_flow * $data->durasi * (floatval($data->tekanan) / $Ta) * (298 / 760), 4));
        foreach ($data->key as $key => $value) {
            if ($Vu != 0.0) {
                $C = \str_replace(",", "", number_format((floatval($value) / floatval($Vu)) * 1000, 4));
            } else {
                $C = 0;
            }
            $C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
            // C (PPM) : 24.45*(C2/64.066)
            $C2 = \str_replace(",", "", number_format(24.45 * floatval($C1) / 64.066, 5));

            $C14 = $C2;

            // Vu (Nm3) = Rerata Laju Alir*t
            $Vu_alt = round($data->average_flow * $data->durasi, 4);

            // C (ug/m3) = (a/Vu)*1000
            $C15 = round((floatval($value) / floatval($Vu_alt)) * 1000, 4);

            // C17 = C16/1000
            $C16 = round(floatval($C15) / 1000, 4);

            $C_value[] = $C;
            $C1_value[] = $C1;
            $C2_value[] = $C2;

            $C14_value[] = $C14;
            $C15_value[] = $C15;
            $C16_value[] = $C16;
        }

        $C = number_format(array_sum($C_value) / count($C_value), 4);
        $C1 = number_format(array_sum($C1_value) / count($C1_value), 5);
        $C2 = number_format(array_sum($C2_value) / count($C2_value), 5);

        $C14 = number_format(array_sum($C14_value) / count($C14_value), 4);
        $C15 = number_format(array_sum($C15_value) / count($C15_value), 4);
        $C16 = number_format(array_sum($C16_value) / count($C16_value), 4);

        if (floatval($C) < 2.1531)
            $C = '<2.1531';
        if (floatval($C1) < 0.0022)
            $C1 = '<0.0022';
        if (floatval($C2) < 0.00082)
            $C2 = '<0.00082';

        $data_pershift = null;
        if (count($C_value) > 1) {
            if (count($C_value) == 3) {
                $data_pershift = [
                    'Shift 1' => $C_value[0],
                    'Shift 2' => $C_value[1],
                    'Shift 3' => $C_value[2]
                ];
            }elseif(count($C_value) == 4){
                $data_pershift = [
                    'Shift 1' => $C_value[0],
                    'Shift 2' => $C_value[1],
                    'Shift 3' => $C_value[2],
                    'Shift 4' => $C_value[3]
                ];
            }
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
            'C' => $C,
            'C1' => $C1,
            'C2' => $C2,
            'C14' => $C14,
            'C15' => $C15,
            'C16' => $C16,
            'data_pershift' => $data_pershift,
            'satuan' => $satuan,
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
