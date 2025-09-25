<?php

namespace App\HelpersFormula;
use Carbon\Carbon;
class TotalColiMPN
{
	public function index($data, $id_parameter, $mdl)
	{
		$hp = self::tabelMpn($data->tb1, $data->tb2, $data->tb3);
		// dd($hp);
		if ($hp == '<1.8' || $hp == '>1600') {
			$nilai = $hp;
		} else {
			$nilai = $hp * (int) $data->fp;
		}

		// dd($nilai);

		$nilTambahan = [
			"Kombinasi Tabung Positif-1" => $data->tb1,
			"Kombinasi Tabung Positif-2" => $data->tb2,
			"Kombinasi Tabung Positif-3" => $data->tb3,
			"Jumlah Tabung Positif (10 mL)" => $data->nil10ml,
			"Jumlah Tabung Positif (1 mL)" => $data->nil1ml,
			"Jumlah Tabung Positif (0.1 mL)" => $data->nil01ml,
			"Jumlah Tabung Positif (0.01 mL)" => $data->nil001ml,
			"Jumlah Tabung Positif (0.001 mL)" => $data->nil0001ml
		];
		$nilTambahan = json_encode($nilTambahan);

		$data = [
			'hasil' => $nilai,
			'nilai_tambahan_analyst' => $nilTambahan,
			'hasil_2' => '',
			'rpd' => '',
			'recovery' => '',
			'hasil_mpn' => $hp
		];
		return $data;
	}

	public function tabelMpn($tb1, $tb2, $tb3)
	{
		$hasil = '';
		if ($tb1 == 0 && $tb2 == 0 && $tb3 == 0) {
			$hasil = '<1.8';
		} else if ($tb1 == 0 && $tb2 == 0 && $tb3 == 1) {
			$hasil = 1.8;
		} else if ($tb1 == 0 && $tb2 == 1 && $tb3 == 0) {
			$hasil = 1.8;
		} else if ($tb1 == 0 && $tb2 == 1 && $tb3 == 1) {
			$hasil = 3.6;
		} else if ($tb1 == 0 && $tb2 == 2 && $tb3 == 0) {
			$hasil = 3.7;
		} else if ($tb1 == 0 && $tb2 == 2 && $tb3 == 1) {
			$hasil = 5.5;
		} else if ($tb1 == 0 && $tb2 == 3 && $tb3 == 0) {
			$hasil = 5.6;
		} else if ($tb1 == 1 && $tb2 == 0 && $tb3 == 0) {
			$hasil = 2;
		} else if ($tb1 == 1 && $tb2 == 0 && $tb3 == 1) {
			$hasil = 4;
		} else if ($tb1 == 1 && $tb2 == 0 && $tb3 == 2) {
			$hasil = 6;
		} else if ($tb1 == 1 && $tb2 == 1 && $tb3 == 0) {
			$hasil = 4;
		} else if ($tb1 == 1 && $tb2 == 1 && $tb3 == 1) {
			$hasil = 6.1;
		} else if ($tb1 == 1 && $tb2 == 1 && $tb3 == 2) {
			$hasil = 8.1;
		} else if ($tb1 == 1 && $tb2 == 2 && $tb3 == 0) {
			$hasil = 6.1;
		} else if ($tb1 == 1 && $tb2 == 2 && $tb3 == 1) {
			$hasil = 8.2;
		} else if ($tb1 == 1 && $tb2 == 3 && $tb3 == 0) {
			$hasil = 8.3;
		} else if ($tb1 == 1 && $tb2 == 3 && $tb3 == 1) {
			$hasil = 10;
		} else if ($tb1 == 1 && $tb2 == 4 && $tb3 == 0) {
			$hasil = 11;
		} else if ($tb1 == 2 && $tb2 == 0 && $tb3 == 0) {
			$hasil = 4.5;
		} else if ($tb1 == 2 && $tb2 == 0 && $tb3 == 1) {
			$hasil = 6.8;
		} else if ($tb1 == 2 && $tb2 == 0 && $tb3 == 2) {
			$hasil = 9.1;
		} else if ($tb1 == 2 && $tb2 == 1 && $tb3 == 0) {
			$hasil = 6.8;
		} else if ($tb1 == 2 && $tb2 == 1 && $tb3 == 1) {
			$hasil = 9.2;
		} else if ($tb1 == 2 && $tb2 == 1 && $tb3 == 2) {
			$hasil = 12;
		} else if ($tb1 == 2 && $tb2 == 2 && $tb3 == 0) {
			$hasil = 9.3;
		} else if ($tb1 == 2 && $tb2 == 2 && $tb3 == 1) {
			$hasil = 12;
		} else if ($tb1 == 2 && $tb2 == 2 && $tb3 == 2) {
			$hasil = 14;
		} else if ($tb1 == 2 && $tb2 == 3 && $tb3 == 0) {
			$hasil = 12;
		} else if ($tb1 == 2 && $tb2 == 3 && $tb3 == 1) {
			$hasil = 14;
		} else if ($tb1 == 2 && $tb2 == 4 && $tb3 == 0) {
			$hasil = 15;
		} else if ($tb1 == 3 && $tb2 == 0 && $tb3 == 0) {
			$hasil = 7.8;
		} else if ($tb1 == 3 && $tb2 == 0 && $tb3 == 1) {
			$hasil = 11;
		} else if ($tb1 == 3 && $tb2 == 0 && $tb3 == 2) {
			$hasil = 13;
		} else if ($tb1 == 3 && $tb2 == 1 && $tb3 == 0) {
			$hasil = 11;
		} else if ($tb1 == 3 && $tb2 == 1 && $tb3 == 1) {
			$hasil = 14;
		} else if ($tb1 == 3 && $tb2 == 1 && $tb3 == 2) {
			$hasil = 17;
		} else if ($tb1 == 3 && $tb2 == 2 && $tb3 == 0) {
			$hasil = 14;
		} else if ($tb1 == 3 && $tb2 == 2 && $tb3 == 1) {
			$hasil = 17;
		} else if ($tb1 == 3 && $tb2 == 2 && $tb3 == 2) {
			$hasil = 20;
		} else if ($tb1 == 3 && $tb2 == 3 && $tb3 == 0) {
			$hasil = 17;
		} else if ($tb1 == 3 && $tb2 == 3 && $tb3 == 1) {
			$hasil = 21;
		} else if ($tb1 == 3 && $tb2 == 3 && $tb3 == 2) {
			$hasil = 24;
		} else if ($tb1 == 3 && $tb2 == 4 && $tb3 == 0) {
			$hasil = 21;
		} else if ($tb1 == 3 && $tb2 == 4 && $tb3 == 1) {
			$hasil = 24;
		} else if ($tb1 == 3 && $tb2 == 5 && $tb3 == 0) {
			$hasil = 25;
		} else if ($tb1 == 4 && $tb2 == 0 && $tb3 == 0) {
			$hasil = 13;
		} else if ($tb1 == 4 && $tb2 == 0 && $tb3 == 1) {
			$hasil = 17;
		} else if ($tb1 == 4 && $tb2 == 0 && $tb3 == 2) {
			$hasil = 21;
		} else if ($tb1 == 4 && $tb2 == 0 && $tb3 == 3) {
			$hasil = 25;
		} else if ($tb1 == 4 && $tb2 == 1 && $tb3 == 0) {
			$hasil = 17;
		} else if ($tb1 == 4 && $tb2 == 1 && $tb3 == 1) {
			$hasil = 21;
		} else if ($tb1 == 4 && $tb2 == 1 && $tb3 == 2) {
			$hasil = 26;
		} else if ($tb1 == 4 && $tb2 == 1 && $tb3 == 3) {
			$hasil = 31;
		} else if ($tb1 == 4 && $tb2 == 2 && $tb3 == 0) {
			$hasil = 22;
		} else if ($tb1 == 4 && $tb2 == 2 && $tb3 == 1) {
			$hasil = 26;
		} else if ($tb1 == 4 && $tb2 == 2 && $tb3 == 2) {
			$hasil = 32;
		} else if ($tb1 == 4 && $tb2 == 2 && $tb3 == 3) {
			$hasil = 38;
		} else if ($tb1 == 4 && $tb2 == 3 && $tb3 == 0) {
			$hasil = 27;
		} else if ($tb1 == 4 && $tb2 == 3 && $tb3 == 1) {
			$hasil = 33;
		} else if ($tb1 == 4 && $tb2 == 3 && $tb3 == 2) {
			$hasil = 39;
		} else if ($tb1 == 4 && $tb2 == 4 && $tb3 == 0) {
			$hasil = 34;
		} else if ($tb1 == 4 && $tb2 == 4 && $tb3 == 1) {
			$hasil = 40;
		} else if ($tb1 == 4 && $tb2 == 4 && $tb3 == 2) {
			$hasil = 47;
		} else if ($tb1 == 4 && $tb2 == 5 && $tb3 == 0) {
			$hasil = 41;
		} else if ($tb1 == 4 && $tb2 == 5 && $tb3 == 1) {
			$hasil = 48;
		} else if ($tb1 == 5 && $tb2 == 0 && $tb3 == 0) {
			$hasil = 23;
		} else if ($tb1 == 5 && $tb2 == 0 && $tb3 == 1) {
			$hasil = 31;
		} else if ($tb1 == 5 && $tb2 == 0 && $tb3 == 2) {
			$hasil = 43;
		} else if ($tb1 == 5 && $tb2 == 0 && $tb3 == 3) {
			$hasil = 58;
		} else if ($tb1 == 5 && $tb2 == 1 && $tb3 == 0) {
			$hasil = 33;
		} else if ($tb1 == 5 && $tb2 == 1 && $tb3 == 1) {
			$hasil = 46;
		} else if ($tb1 == 5 && $tb2 == 1 && $tb3 == 2) {
			$hasil = 63;
		} else if ($tb1 == 5 && $tb2 == 1 && $tb3 == 3) {
			$hasil = 84;
		} else if ($tb1 == 5 && $tb2 == 2 && $tb3 == 0) {
			$hasil = 49;
		} else if ($tb1 == 5 && $tb2 == 2 && $tb3 == 1) {
			$hasil = 70;
		} else if ($tb1 == 5 && $tb2 == 2 && $tb3 == 2) {
			$hasil = 94;
		} else if ($tb1 == 5 && $tb2 == 2 && $tb3 == 3) {
			$hasil = 120;
		} else if ($tb1 == 5 && $tb2 == 2 && $tb3 == 4) {
			$hasil = 150;
		} else if ($tb1 == 5 && $tb2 == 3 && $tb3 == 0) {
			$hasil = 79;
		} else if ($tb1 == 5 && $tb2 == 3 && $tb3 == 1) {
			$hasil = 110;
		} else if ($tb1 == 5 && $tb2 == 3 && $tb3 == 2) {
			$hasil = 140;
		} else if ($tb1 == 5 && $tb2 == 3 && $tb3 == 3) {
			$hasil = 170;
		} else if ($tb1 == 5 && $tb2 == 3 && $tb3 == 4) {
			$hasil = 210;
		} else if ($tb1 == 5 && $tb2 == 4 && $tb3 == 0) {
			$hasil = 130;
		} else if ($tb1 == 5 && $tb2 == 4 && $tb3 == 1) {
			$hasil = 170;
		} else if ($tb1 == 5 && $tb2 == 4 && $tb3 == 2) {
			$hasil = 220;
		} else if ($tb1 == 5 && $tb2 == 4 && $tb3 == 3) {
			$hasil = 280;
		} else if ($tb1 == 5 && $tb2 == 4 && $tb3 == 4) {
			$hasil = 350;
		} else if ($tb1 == 5 && $tb2 == 4 && $tb3 == 5) {
			$hasil = 430;
		} else if ($tb1 == 5 && $tb2 == 5 && $tb3 == 0) {
			$hasil = 240;
		} else if ($tb1 == 5 && $tb2 == 5 && $tb3 == 1) {
			$hasil = 350;
		} else if ($tb1 == 5 && $tb2 == 5 && $tb3 == 2) {
			$hasil = 540;
		} else if ($tb1 == 5 && $tb2 == 5 && $tb3 == 3) {
			$hasil = 920;
		} else if ($tb1 == 5 && $tb2 == 5 && $tb3 == 4) {
			$hasil = 1600;
		} else if ($tb1 == 5 && $tb2 == 5 && $tb3 == 5) {
			$hasil = '>1600';
		}
		return $hasil;
	}
}