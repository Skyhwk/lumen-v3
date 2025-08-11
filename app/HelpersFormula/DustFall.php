<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class DustFall {
    public function index($data, $id_parameter, $mdl){
		$Ta = null;
		$Qs = null;
		$c = null;
		$c1 = null;
		$c2 = null;
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
		$rumus = number_format(((($w2-$w1) * 30 * $vl) / ($a * $t * 0.250)), 4);
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
            'C' => $rumus,
            'C1' => $c1,
            'C2' => $c2,
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