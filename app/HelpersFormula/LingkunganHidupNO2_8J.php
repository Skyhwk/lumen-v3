<?php

namespace App\HelpersFormula;

use Carbon\Carbon;
class LingkunganHidupNO2_8J
{
    public function index($data, $id_parameter, $mdl) {
        
        $ks = null;
        // dd(count($data->ks));
        if (is_array($data->ks)) {
            $ks = number_format(array_sum($data->ks) / count($data->ks), 4);
        }else {
            $ks = $data->ks;
        }
        $kb = null;
        if (is_array($data->kb)) {
            $kb = number_format(array_sum($data->kb) / count($data->kb), 4);
        }else {
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

        $hasil = [];

        $Vu = \str_replace(",", "",number_format($data->average_flow * $data->durasi * (floatval($data->tekanan) / $Ta) * (298 / 760), 4));
        // dd($Vu);
        foreach ($data->ks as $key => $value_ks) {
            if($Vu != 0.0) {
                $result = \str_replace(",", "", number_format(($value_ks / floatval($Vu)) * (10 / 25) * 1000, 4));
            }else {
                $result = 0;
            }
            array_push($hasil, $result);
        }

        $hasil1 = $hasil[0];
        $hasil2 = $hasil[1];
        $hasil3 = $hasil[2];
        $avg_hasil = number_format(array_sum($hasil) / count($hasil), 4);

        if(!is_null($mdl) && $avg_hasil < $mdl){
            $avg_hasil = '<'.$mdl;
        }

        $satuan = 'µg/Nm³';
        
        $processed = [
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
            'hasil1' => $avg_hasil,
            'hasil2' => $hasil1,
            'hasil3' => $hasil2,
            'hasil4' => $hasil3,
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

        return $processed;
    }

}