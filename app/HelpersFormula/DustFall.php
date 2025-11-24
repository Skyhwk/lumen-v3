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

		$w2 = array_sum([$data->bki1, $data->bki2]) / 2;
		$w1 = array_sum([$data->bk1, $data->bk2]) / 2;
		$vl = $data->vl;
		$a = $data->luas_botol / 10000;
		$t = $data->selisih_hari;
        // C13 (Ton/Km²/Bulan) = (((W2-W1)*30*V)/(A*T*0.250))
		$rumus = number_format(((($w2-$w1) * 30 * $vl) / ($a * $t * 0.250)), 4);

        $satuan = "Ton/Km²/Bulan";

		$data = [
            'tanggal_terima' => $data->tanggal_terima,
            'flow' => null,
            'durasi' => null,
            'tekanan_u' => null,
            'suhu' => null,
            'k_sample' => $ks,
            'k_blanko' => $kb,
            'Qs' => $Qs,
            'w1' => $w1,
            'w2' => $w2,
            'b1' => $b1,
            'b2' => $b2,
            'hasil' => $rumus,
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
