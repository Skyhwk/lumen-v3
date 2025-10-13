<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class DustFall {
    public function index($data, $id_parameter, $mdl){
		$Ta = null;
		$Qs = null;
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
		$ks = null;
		$kb = null;
		$a = null;
		$t = null;
		$flow = null;
		$waktu = null;
		$tekanan_udara = null;
		$suhu = null;

		$w2 = $data->w2;
		$w1 = $data->w1;
		$vl = $data->vl;
		$a = $data->a;
		$t = $data->t;
		$C5 = number_format(((($w2-$w1) * 30 * $vl) / ($a * $t * 0.250)), 4);

        $satuan = "Ton/Km²/Bulan";

		$data = [
            'tanggal_terima' => $data->tgl_terima,
            'flow' => $data->flow,
            'durasi' => $data->waktu,
            'tekanan_u' => $data->tekanan_udara,
            'suhu' => $data->suhu,
            'k_sample' => $ks,
            'k_blanko' => $kb,
            'Qs' => $Qs,
            'w1' => $w1,
            'w2' => $w2,
            'b1' => $b1,
            'b2' => $b2,
            'C' => isset($C) ? $C : null,
            'C1' => isset($C1) ? $C1 : null,
            'C2' => isset($C2) ? $C2 : null,
            'C3' => isset($C3) ? $C3 : null,
            'C4' => isset($C4) ? $C4 : null,
            'C5' => isset($C5) ? $C5 : null,
            'C6' => isset($C6) ? $C6 : null,
            'C7' => isset($C7) ? $C7 : null,
            'C8' => isset($C8) ? $C8 : null,
            'C9' => isset($C9) ? $C9 : null,
            'C10' => isset($C10) ? $C10 : null,
            'C11' => isset($C11) ? $C11 : null,
            'satuan' => $satuan,
            'vl' => $vl,
            'st' => $st,
            'Vstd' => $Vstd,
            'V' => $V,
            'Vu' => $Vu,
            'Vs' => $Vs,
            'Ta' => $Ta,
            't' => $t,
            'a' => $a,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];

        return $data;
    }
}
