<?php
namespace App\Services;

use Auth;
use Validator;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Colorimetri;
use App\Models\Titrimetri;
use App\Models\Gravimetri;
use App\Models\LingkunganHeader;
use App\Models\DebuPersonalHeader;
use App\Models\DustFallHeader;
use App\Models\EmisiCerobongHeader;
use App\Models\WsValueAir;
use App\Models\Po;
use App\Services\LookUpRdm;
use Carbon\Carbon;


class FunctionValue
{
	public $result;

	public function Titrimetri($id, $request, $status = ''){
		if($status==''){$status = 0;}
		$cari = Titrimetri::where('no_sampel', $request->no_sample)
							->where('parameter', $request->parameter)
							->where('is_active',true)
							->where('status',$status)
							->orderBy('id', 'desc')
							->first();

		if($cari==null){$id_param = '';}else{$id_param = $cari->id;}
		$vtb = $request->vtb;
		$kt = $request->kt;
		$vs = $request->vs;
		$fp = $request->fp;

		// METODE BARU
		if($request->has('volume_titrasi_baru')){
			$vts = $request->volume_titrasi_baru;
		}else{
			$vts = $request->vts;
		}

		if($request->has('do_sampel_5_hari_baru')){
			$fp = $request->faktor_pengenceran_baru;
			$do_sampel5 = $request->do_sampel_5_hari_baru;
			$do_sampel0 = $request->do_sampel_0_hari_baru;
			$do_blanko5 = $request->do_blanko_5_hari_baru;
			$do_blanko0 = $request->do_blanko_0_hari_baru;
			$vmb = $request->volume_mikroba_blanko_baru;
			$vms = $request->volume_mikroba_sampel_baru;
		}

		if($id == 38 || $id == 414) {
			$result = self::Clorida($id_param, $request->no_sample, $vts, $vtb, $kt, $vs, $fp, $id);
			return $result;
		} else if ($id == 29){
			if($request->has('do_sampel_5_hari_baru')){
				$result = self::BODBaru($id_param, $request->no_sample, $fp, $do_sampel5, $do_sampel0, $do_blanko5, $do_blanko0, $vmb, $vms);
			}else{
				$result = self::BOD($id_param, $request->no_sample, $vts, $vtb, $kt, $vs, $fp);
			}
			return $result;
		} else if ($id == 83){
			$result = self::KMnO4($id_param, $request->no_sample, $vts, $vtb, $kt, $vs, $fp);
			return $result;
		} else if ($id == 164){
			$result = self::TH($id_param, $request->no_sample, $vts, $vtb, $kt, $vs, $fp);
			return $result;
		} else if ($id == 32 || $id == 411){
			$result = self::CaHardness($id_param, $request->no_sample, $vts, $vtb, $kt, $vs, $fp, $id);
			return $result;
		} else if ($id == 95){
			$result = self::Mg($id_param, $request->no_sample, $vts, $vtb, $kt, $vs, $fp);
			return $result;
		} else if ($id == 161){
			$result = self::Sulfite($id_param, $request->no_sample, $vts, $vtb, $kt, $vs, $fp);
			return $result;
		} else if ($id == 8){
			$result = self::Alkalinitas($id_param, $request->no_sample, $vts, $vtb, $kt, $vs, $fp);
			return $result;
		} else if ($id == 10){
			$result = self::AlkalinityM($id_param, $request->no_sample, $vts, $vtb, $kt, $vs, $fp);
			return $result;
		} else if ($id == 9){
			$result = self::AlkalinityH($id_param, $request->no_sample, $kt, $vts, $vtb, $vs, $fp);
			return $result;
		} else if ($id == 11){
			$result = self::AlkalinityM($id_param, $request->no_sample, $vts, $vtb, $kt, $vs, $fp);
			return $result;
		}else if ($id == 58){
			$result = self::DO($id_param, $request->no_sample, $vts, $vtb, $kt, $vs, $fp, $id);
			return $result;
		} else {
			return 'gagal';
		}
	}

	public function Colorimetri($id, $request, $status = '', $hp_){
		if($status==''){$status = 0;}
		$cari = Colorimetri::where('no_sampel', $request->no_sample)
							->where('parameter', $request->parameter)
							->where('is_active',true)
							->where('status',$status)
							->orderBy('id', 'desc')
							->first();
		// dd($cari);
		if($cari==null){$id_param = '';}else{$id_param = $cari->id;}
		$hp = $request->hp;
		$fp = $request->fp;

		if($request->has('nilaiBauTerkecil')){
			if($request->nilaiBauTerkecil != "Tidak Berbau"){
				$hp = floatval($request->nilaiBauTerkecil);
			}else{
				$hp = $request->nilaiBauTerkecil;
			}
		}else if($request->has('nilaiTerkecil')){
			if($request->nilaiTerkecil != "Tidak Berasa"){
				$hp = floatval($request->nilaiTerkecil);
			}else{
				$hp = $request->nilaiTerkecil;
			}
		}
		

		if($id == 57 || $id == 13 || $id == 39 || $id == 158 || $id == 40 || $id == 102 || $id == 130 || $id == 187 || $id == 109 || $id == 35 || $id == 41 || $id == 48 || $id == 52 || $id == 64 || $id == 99 || $id == 105 || $id == 111 || $id == 120 || $id == 188 || $id == 36 || $id == 37 || $id == 42 || $id == 43 || $id == 49|| $id == 50 || $id == 53 || $id == 54 || $id == 65 || $id == 66 || $id == 100 || $id == 101 || $id == 112 || $id == 113 || $id == 121 || $id == 122 || $id == 189 || $id == 190 || $id == 82 || $id == 163 || $id == 179 || $id == 60 ||  $id == 174 || $id == 175 || $id == 554 || $id == 541 || $id == 543 || $id == 584 || $id == 141 || $id == 77 || $id == 23 || $id == 20 || $id == 7 || $id == 6 || $id == 4 || $id == 3 || $id == 21 || $id == 24 || $id == 34 || $id == 33 || $id == 96 || $id == 97 || $id == 537 || $id == 166 || $id == 167 || $id == 546 || $id == 547 || $id == 131 || $id == 545 || $id == 558 || $id == 170 || $id == 171 || $id == 31 || $id == 19 || $id == 22 || $id == 146 || $id == 17 || $id == 16 || $id == 76){
			$result = self::Perkalian($id_param, $request->no_sample, $hp, $fp, $id);
			return $result;
		} else if($id == 72) {
			$result = self::Calkulasi($id_param, $request->no_sample, $hp, $fp, $id);
			return $result;
		} else if($id == 46) {
			$result = self::spektroCOD($id_param, $request->no_sample, $hp, $fp, $id);
			return $result;
		} else if($id == 51) {
			$result = self::spektroCr($id_param, $request->no_sample, $hp, $fp);
			return $result;
		} else if($id == 63) {
			$result = self::spektroF($id_param, $request->no_sample, $hp, $fp, $id);
			return $result;
		} else if($id == 68||$id == 69) {
			$result = self::spektroFenol($id_param, $request->no_sample, $hp, $fp, $id);
			return $result;
		} else if($id == 92) {
			$result = self::spektroMbas($id_param, $request->no_sample, $hp, $fp);
			return $result;
		} else if($id == 58 || $id == 128) {
			$result = self::Direct($id_param, $request->no_sample, $hp, $fp, $id);
			return $result;
		} else if($id == 25){
			if($request->has('nilaiBauTerkecil')){
				$result = self::BauBaru($id_param, $request->no_sample, $hp, $fp);
			}else{
				$result = self::Bau($id_param, $request->no_sample, $hp, $fp);
			}
			return $result;
		}else if($id == 137){
			$result = self::RasaBaru($id_param, $request->no_sample, $hp, $fp);
			return $result;
		} else if($id == 126){
			$result = self::Persistent($id_param, $request->no_sample, $hp, $request->waktu);
			return $result;
		} else if($id == 108){
			$result = self::SpektroNH3($id_param, $request->no_sample, $hp, $fp, $id);
			return $result;
		} else if($id == 114){
			$result = self::SpektroNO2($id_param, $request->no_sample, $hp, $fp, $id);
			return $result;
		} else if($id == 115){
			$result = self::ColorNO3($id_param, $request->no_sample, $hp, $fp, $id);
			return $result;
		} else if($id == 59){
			$result = self::DirectDTL($id_param, $request->no_sample, $hp, $fp, $id);
			return $result;
		}else if($id == 413 || $id == 421 || $id == 454 || $id == 410 || $id == 412 || $id == 431|| $id == 427 || $id == 431 || $id == 408|| $id == 415 || $id == 418|| $id == 419 || $id == 429 || $id == 424 || $id == 425 || $id == 417 || $id == 443 || $id == 420 || $id == 422){
			$result = self::SpektroPadatan($id_param, $request->no_sample, $hp, $fp, $id);
			return $result;
		}else if($id == 585 || $id == 555) {
			$p = 0;
			if($hp_ != null || $hp_ != '') {
				$p = $hp_;
			}
			$result = self::TotalColiMPN($id_param, $request->no_sample, $p, $fp, $id, $request->tb1, $request->tb2, $request->tb3, $request->nil10ml, $request->nil1ml, $request->nil01ml, $request->nil001ml, $request->nil0001ml);
			return $result;
		}else if($id == 67){
				$result = self::Perkalian($id_param, $request->no_sample, $hp, $fp, $id);
				// 	$p = 0;
				// 	if($hp_ != null || $hp_ != '') {
				// 		$p = $hp_;
				// 	}
				// 	$result = $this->FecalColiMPN(
				// 			$id_param, 
				// 			$id_po, 
				// 			$request->no_sample, 
				// 			$p, 
				// 			$fp, 
				// 			$id, 
				// 			$request->tb1_baru,
				// 			$request->tb2_baru,
				// 			$request->tb3_baru,
				// 			$request->nil10ml_baru,
				// 			$request->nil1ml_baru,
				// 			$request->nil01ml_baru,
				// 			$request->nil001ml_baru,
				// 			$request->nil0001ml_baru
				// 		);
				// }
				return $result; 
		}else {
			return 'gagal';
		}
	}

	public function Persistent($id, $no_sample, $hp, $waktu){
		$rumus = number_format(($hp / 1000) / $waktu, 4);
		// if($id_val==126){
		// 	if($rumus<0.0020)$rumus='<0.0020';
		// }
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function Gravimetri($id, $request, $status = ''){
		if($status==''){$status = 0;}
		$cari = Gravimetri::where('no_sampel', $request->no_sample )
				->where('parameter', $request->parameter )
				->where('is_active',true)
				->where('status',$status)
				// ->where('status',0)
				->orderBy('id', 'desc')
				->first();
		// dd($cari);
		if($cari==null){
			$id_param = '';
		}else{
			$id_param = $cari->id;
		}
		$bk1  = $request->bk_1;
		$bk2  = $request->bk_2;
		$bki1 = $request->bki1;
		$bki2 = $request->bki2;
		$vs   = $request->vs;
		$fp   = $request->has('fp') ? $request->fp : null;


		if($id == 117 || $id == 89 || $id == 416 || $id == 179){		//OG & M.Mineral
			$result = self::Grafi($id_param, $request->no_sample, $bk1, $bk2, $bki1, $bki2, $vs, $fp, $id);
			return $result;
		}else if($id == 163){
			$result = self::GrafiTDS($id_param, $request->no_sample, $bk1, $bk2, $bki1, $bki2, $vs, $id);
			return $result;
		}else {
			return 'gagal';
		}
	}
//==================================================Gravimetri========================================
	public function Grafi($id, $no_sample, $bk1, $bk2, $bki1, $bki2, $vs, $fp, $id_val){
		$rerata1 = ($bk1 + $bk2) / 2;
		$rerata2 = ($bki1 + $bki2) / 2;
		$rumus = number_format(((($rerata2 - $rerata1) * 1000) / $vs) * $fp, 4);
		$NaCl = $rumus;
		if($id_val == 179){
			if($rumus<0.5357){
				$rumus='<0.5357';
			}
		}else{
			if($rumus<0.86){
				$rumus='<0.86';
			}
		}
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_gravimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function GrafiTDS($id, $no_sample, $bk1, $bk2, $bki1, $bki2, $vs, $id_val){
		$rerata1 = ($bk1 + $bk2) / 2;
		$rerata2 = ($bki1 + $bki2) / 2;
		$rumus = number_format(((($rerata2 - $rerata1) * 1000) / $vs), 4);
		$NaCl = $rumus;
		if($id_val == 163){
			if($rumus<1){
				$rumus='<1';
			}
		}
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_gravimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

//========================================Colorimetri & Spektrofometri========================================
	public function Perkalian($id, $no_sample, $hp, $fp, $id_val){
		$rumus = $hp * $fp;
		if($id_val==82 || $id_val==163 || $id_val==158 || $id_val==187 || $id_val==175 || $id_val==554 || $id_val==67 || $id_val==174 || $id_val==179 || $id_val==13){
			if($rumus<1)$rumus = '<1';
		} //$id_val==60 || $id == 175 ||
		else if($id_val==584) {
			$rumus = $rumus;
		} else if($id_val==115 || $id_val == 543){
			if($rumus<0.1)$rumus = '<0.1';
		} else if($id_val==39 || $id_val==102 || $id_val==130){
			if($rumus<0.01)$rumus = '<0.01';
		} else if($id_val==40){
			if($rumus<0.001)$rumus = '<0.001';
		} else if($id_val==109){
			if($rumus<0.0031)$rumus = '<0.0031';
		} else if($id_val==541){
			if($rumus<0.0009)$rumus = '<0.0009';
		} else if($id_val==36 || $id_val==37){
			if($rumus<0.0029)$rumus = '<0.0029';
		} else if($id_val==42 || $id_val==43){
			if($rumus<0.0054)$rumus = '<0.0054';
		} else if($id_val==49 || $id_val==50){
			if($rumus<0.0020)$rumus = '<0.0020';
		} else if($id_val==53 || $id_val==54){
			if($rumus<0.0150)$rumus = '<0.0150';
		} else if($id_val==65 || $id_val==66){
			if($rumus<0.0131)$rumus = '<0.0131';
		} else if($id_val==100 || $id_val==101){
			if($rumus<0.0055)$rumus = '<0.0055';
		} else if($id_val==105){
			if($rumus<0.0476)$rumus = '<0.0476';
		} else if($id_val==112 || $id_val==113){
			if($rumus<0.0021)$rumus = '<0.0021';
		} else if($id_val==121 || $id_val==122){
			if($rumus<0.0047)$rumus = '<0.0047';
		} else if($id_val==189 || $id_val==190){
			if($rumus<0.0040)$rumus = '<0.0040';
		}else if($id_val==114) {
			if($rumus<0.0030)$rumus = '<0.0030';
		}else if($id_val==108) {
			if($rumus<0.0038)$rumus = '<0.0038';
		} else if($id_val==57) {
			// MDL DHL
			if($rumus<1)$rumus = '<1';
		} else if($id_val==141) {
			// MDL SALINITAS
			if($rumus<0.2)$rumus = '<0.2';
		}


		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function SpektroNH3($id, $no_sample, $hp, $fp, $id_val){
		// hasil berbeda karena sebelumnya 1.2158 seharusnya 1.21589
		$rumus = number_format(($hp * 1.2158) * $fp, 4);
		if($rumus < 0.0038) $rumus = '<0.0038';
		$rumus = str_replace(",", "", $rumus);
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function SpektroPadatan($id, $no_sample, $hp, $fp, $id_val){
        if($id_val == 454) {
            $rumus = ($hp / 50) * $fp;
				if($rumus <0.0056) $rumus = '<0.0056';
			}else if($id_val == 413) {
				$rumus = (($hp / 50) * (60/50)) * $fp;
				if($rumus <0.0038) $rumus = '<0.0038';
			}else if($id_val == 421) {
				$rumus = ($hp * 3.2845) * $fp;
				if($rumus <0.0030) $rumus = '<0.0030';
			}else if($id_val == 410 || $id_val == 412 || $id_val == 427 || $id_val == 431 || $id_val == 408 || $id_val == 415 || $id_val == 418|| $id_val == 419 || $id_val == 429 || $id_val == 424 || $id_val == 425 || $id_val == 417 || $id_val == 443) {
				$rumus = $hp * $fp;
				if($id_val == 410) {
					if($rumus <0.0029) $rumus = '<0.0029';
				}else if($id_val == 412) {
					if($rumus <0.0054) $rumus = '<0.0054';
				}else if($id_val == 427) {
					if($rumus <0.0150) $rumus = '<0.0150';
				}else if($id_val == 431) {
					if($rumus <0.0020) $rumus = '<0.0020';
				}else if($id_val == 408) {
					if($rumus <0.0131) $rumus = '<0.0131';
				}else if($id_val == 415) {
					if($rumus <0.0055) $rumus = '<0.0055';
				}else if($id_val == 418) {
					if($rumus <0.0476) $rumus = '<0.0476';
				}else if($id_val == 419) {
					if($rumus <0.0021) $rumus = '<0.0021';
				}else if($id_val == 429) {
					if($rumus <0.0047) $rumus = '<0.0047';
				}else if($id_val == 424) {
					if($rumus <0.0040) $rumus = '<0.0040';
				}else if($id_val == 425) {
					if($rumus <0.001) $rumus = '<0.001';
				}else if($id_val == 417) {
					if($rumus <0.01) $rumus = '<0.01';
				}
			}else if($id_val == 420) {
				$rumus = ($hp * 4.4268) * $fp;
				if($rumus <0.4427) $rumus = '<0.4427';
			}else if($id_val == 422) {
				$rumus = number_format($hp / 1000, 4);
        }
		$rumus = str_replace(",", "", $rumus);
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}
	public function SpektroNO2($id, $no_sample, $hp, $fp, $id_val){
		// dd($hp, $fp);
		$rumus = number_format(($hp * 3.2845) * $fp, 4);
		if($rumus < 0.0030) $rumus = '<0.0030';
		$rumus = str_replace(",", "", $rumus);
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function ColorNO3($id, $no_sample, $hp, $fp, $id_val){
		$rumus = number_format(($hp * 4.4268) * $fp, 4);
		if($rumus<0.4427) $rumus = '<0.4427';

		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function Direct($id, $no_sample, $hp, $fp, $id_val){
		$rumus = $hp;
		if($id_val==57){
			if($rumus<1)$rumus = '<1';
		} else if($id_val==58){
			if($rumus<0.10)$rumus = '<0.10';
		} else if($id_val==141){
			if($rumus<0.2)$rumus = '<0.2';
		}
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function TotalColiMPN($id, $no_sample, $hp, $fp, $id_val, $tb1, $tb2, $tb3, $nil10ml, $nil1ml, $nil01ml, $nil001ml, $nil0001ml){
		if($hp === '<1.8' || $hp === '>1600') {
			$nilai = $hp;
		} else {
			$nilai = $hp * (int)$fp;
		}
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$nilTambahan = NULL;
		if($id_val == 555 || $id_val == 585) {
			$nilTambahan = [
				"Kombinasi Tabung Positif-1" => $tb1,
				"Kombinasi Tabung Positif-2" => $tb2,
				"Kombinasi Tabung Positif-3" => $tb3,
				"Jumlah Tabung Positif (10 mL)" => $nil10ml,
				"Jumlah Tabung Positif (1 mL)" => $nil1ml,
				"Jumlah Tabung Positif (0.1 mL)" => $nil01ml,
				"Jumlah Tabung Positif (0.01 mL)" => $nil001ml,
				"Jumlah Tabung Positif (0.001 mL)" => $nil0001ml
			];
			$nilTambahan = json_encode($nilTambahan);
		}
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $nilai,
			'nilai_tambahan_analyst' => $nilTambahan,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}
	public function DirectDTL($id, $no_sample, $hp, $fp, $id_val){
		$rumus = number_format(1/$hp, 2);
		if($id_val==59){
			if($rumus<0.0002)$rumus = '<0.0002';
		}
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function Bau($id, $no_sample, $hp, $fp){
		$rumus = $hp;
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function Calkulasi($id, $no_sample, $hp, $fp, $id_val){
		$rumus = $hp * 1.06 * $fp;
		if($id_val==72){
			if($rumus<0.0020)$rumus='<0.0020';
		}
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function spektroCOD($id, $no_sample, $hp, $fp, $id_val){
		// dd($hp, $fp);
		$rumus = (($hp * 1000) / 2.5) * $fp;
		if($rumus<1.31)$rumus = '<1.31';
		// Format hanya jika tidak mengandung '<'
		$rumus = (strpos($rumus, '<') === false) ? number_format($rumus, 4) : $rumus;
		$rumus = str_replace(",", "", $rumus);
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		// dd($data);
		return $data;
	}

	public function spektroCr($id, $no_sample, $hp, $fp){
		// Ganti dari 2 angka dibelakang koma menjadi 4
		$rumus = number_format(($hp / 50) * $fp, 2);
		if($rumus < 0.0056){
			$hg = '<0.0056';
		}else{$hg = $rumus;}
		$rumus = str_replace(",", "", $rumus);
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $hg,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function spektroF($id, $no_sample, $hp, $fp, $id_val){
		$rumus = number_format((($hp / 50) * (50 / 50)) * $fp,4);
		if($id_val==63){
			if($rumus<0.0038)$rumus = '<0.0038';
		}
		$rumus = str_replace(",", "", $rumus);
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function spektroFenol($id, $no_sample, $hp, $fp, $id_val){
		$rumus =number_format(($hp / 100) * 1000 * $fp, 4);
		if($id_val==68){
			if($rumus<0.0009)$rumus = '<0.0009';
		}
		$rumus = str_replace(",", "", $rumus);
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function spektroMbas($id, $no_sample, $hp, $fp){
		$rumus = number_format(($hp / 100) * $fp, 4);
		if($rumus<0.0063)$rumus = '<0.0063';
		$rumus = str_replace(",", "", $rumus);
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

//===============================================Titrimetri====================================================
	public function Clorida($id, $no_sample, $vts, $vtb, $kt, $vs, $fp, $id_val)
	{
        if($id_val == 38) {
            $rumus = number_format((($vts - $vtb) * $kt * 35450 ) / $vs * $fp, 2);
            $nilai_ = floatval(\str_replace(",", "", $rumus));
            $NaCl = number_format($nilai_ * 1.65, 2);
            if($rumus < 0.4){
                $rumus = '<0.4';
            } else {
                $rumus = str_replace(",", "", $rumus);
            }
        }else if($id_val == 414) {
            $rumus = number_format((($vts - $vtb) * $kt * 35450 ) / 100, 2);
            $NaCl = number_format($rumus * 1.65, 2);
            if($rumus < 0.4){
                $rumus = '<0.4';
            } else {
                $rumus = str_replace(",", "", $rumus);
            }
        }
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_titrimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function BOD($id, $no_sample, $vts, $vtb, $kt, $vs, $fp)
	{
		$oksalat 	= 0.0100;
		$KMnO4 		= 31.6;
		$rumus = ((ABS((( 10 - $vts ) * $kt) - ( 10 * $oksalat )) * 1 * $KMnO4 * 1000) / 100) * $fp;
		$NaCl = number_format($rumus / 1.423456789, 2);
		if($NaCl<1){
			$NaCl = '<1';
		} else {
			$NaCl = str_replace(",", "", $NaCl);
		}

		$RPD = '';
		$Recovery = '';

		$data = [
			'id_titrimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $NaCl,
			'hasil_2' => $rumus,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function KMnO4($id, $no_sample, $vts, $vtb, $kt, $vs, $fp)
	{
		$oksalat 	= 0.0100;
		$KMnO4 		= 31.6;
		$rumus = number_format(((ABS((( 10 - $vts ) * $kt) - ( 10 * $oksalat )) * 1 * $KMnO4 * 1000) / 100) * $fp, 4);
		if($rumus<0.3){
			$rumus='<0.3';
		} else {
			$rumus = str_replace(",", "", $rumus);
		}
		$NaCl = '';
		$RPD = '';
		$Recovery = '';

		$data = [
			'id_titrimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function TH($id, $no_sample, $vts, $vtb, $kt, $vs, $fp)
	{
		$oksalat 	= 0.0100;
		$KMnO4 		= 31.6;
		$rumus = (($vts * $kt * 1000 ) / $vs ) * $fp;
		if($rumus<3.39)$rumus = '<3.39';
		$NaCl = '';
		$RPD = '';
		$Recovery = '';

		$data = [
			'id_titrimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function CaHardness($id, $no_sample, $vts, $vtb, $kt, $vs, $fp, $id_val)
	{
        if($id_val == 32) {
            $rumus = (1000 / $vs) * $vts * $kt * 40;
            if($rumus<3.39)$rumus = '<3.39';
			}else if($id_val == 411) {
				$rumus = (1000 / 25) * $vts * $kt * 40;
				if($rumus<0.016)$rumus = '<0.016';
        }

		$NaCl = '';
		$RPD = '';
		$Recovery = '';

		$data = [
			'id_titrimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function Mg($id, $no_sample, $vts, $vtb, $kt, $vs, $fp)
	{
		$rumus = (1000 / $vs) * ABS($vts - $vtb) * $kt * 24.3;
		if($rumus<1.56)$rumus='<1.56';
		$NaCl = '';
		$RPD = '';
		$Recovery = '';

		$data = [
			'id_titrimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function Sulfite($id, $no_sample, $vts, $vtb, $kt, $vs, $fp)
	{
		$rumus = (($vts - $vtb) * $kt * 6 * 40000)/ $vs;
		if($rumus<0.0357)$rumus = '<0.0357';
		$NaCl = '';
		$RPD = '';
		$Recovery = '';

		$data = [
			'id_titrimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function Alkalinitas($id, $no_sample, $vts, $vtb, $kt, $vs, $fp)
	{
		$rumus = number_format(($vts* $kt * 50000)/ $vs, 2);
		if($rumus<1){
			$rumus = '<1';
		}
		$NaCl = '';
		$RPD = '';
		$Recovery = '';

		$data = [
			'id_titrimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function AlkalinityM($id, $no_sample, $vts, $vtb, $kt, $vs, $fp)
	{
		$rumus = number_format(($vts* $kt * 50000)/ $vs, 2);
		$NaCl = '';
		$RPD = '';
		$Recovery = '';

		$data = [
			'id_titrimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function AlkalinityH($id, $no_sample, $kt, $vtm, $vtp, $vs, $fp)
	{

		$half = $vtm * 50 / 100;


		if($vtp == 0){
			$vth = 0;
			$co3 = 0;
			$caco3 = $vtm;

			$rumus = number_format(($vth * $kt * 50000) / $vs, 2);
			$caco = number_format(($caco3 * $kt * 50000) / $vs, 2);
			$RPD = '';
			$Recovery = '';

		} else if ($vtp < $half){
			$vth = 0;
			$co3 = 2 * $vtp;
			$caco3 = $vtm - (2 * $vtp);

			$rumus = number_format(($vth * $kt * 50000) / $vs, 2);
			$caco = number_format(($caco3 * $kt * 50000) / $vs, 2);
			$RPD = '';
			$Recovery = '';
		} else if ($vtp == $half){
			$vth = 0;
			$co3 = 2 * $vtp;
			$caco3 = 0;

			$rumus = number_format(($vth * $kt * 50000) / $vs, 2);
			$caco = number_format(($caco3 * $kt * 50000) / $vs, 2);
			$RPD = '';
			$Recovery = '';
		} else if ($vtp > $half){
			$vth = (2 * $vtp) - $vtm;
			$co3 = 2 * ($vtm - $vtp);
			$caco3 = 0;

			$rumus = number_format(($vth * $kt * 50000) / $vs, 2);
			$caco = number_format(($caco3 * $kt * 50000) / $vs, 2);
			$RPD = '';
			$Recovery = '';
		} else if($vtp == $vth){
			$vth = $vtm;
			$co3 = 0;
			$caco3 = 0;

			$rumus = number_format(($vth * $kt * 50000) / $vs, 2);
			$caco = number_format(($caco3 * $kt * 50000) / $vs, 2);
			$RPD = '';
			$Recovery = '';
		}

		$data = [
			'id_titrimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $caco,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function valLingHidup($nilQs, $datot, $rerataFlow, $dur, $tgl_terima, $tekanan_u, $suhu, $request, $userid, $id_param)
	{
		try {
			// dd($nilQs, $datot, $rerataFlow, $dur, $tgl_terima, $tekanan_u, $suhu, $userid, $id_param);
			$cari = LingkunganHeader::where('no_sampel', $request->no_sample)
				->where('parameter', $request->parameter)
				->where('is_active', true)
				->first();
			// dd($cari);
			$ks = null;
			// dd(count($request->ks));
			if (is_array($request->ks)) {
				$ks = number_format(array_sum($request->ks) / count($request->ks), 4);
			}else {
				$ks = $request->ks;
			}
			$kb = null;
			if (is_array($request->kb)) {
				$kb = number_format(array_sum($request->kb) / count($request->kb), 4);
			}else {
				$kb = $request->kb;
			}
			// dd($kb, $ks);
			if ($cari == null) {
				$id_param_ = '';
			} else {
				$id_param_ = $cari->id;
			}

			$Ta = floatval($suhu) + 273;
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
			if ($id_param == 293 || $id_param == 294 || $id_param == 295 || $id_param == 296) {
				$Vu = \str_replace(",", "",number_format($rerataFlow * $dur * (floatval($tekanan_u) / $Ta) * (298 / 760), 4));
				// dd($Vu);
				if($Vu != 0.0) {
					$C = \str_replace(",", "", number_format(($ks / floatval($Vu)) * (10 / 25) * 1000, 4));
				}else {
					$C = 0;
				}
				$C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
				$C2 = \str_replace(",", "", number_format(24.45 * floatval($C1) / 46, 5));
				if (floatval($C) < 0.4623)
					$C = '<0.4623';
				if (floatval($C1) < 0.00046)
					$C1 = '<0.00046';
				if (floatval($C2) < 0.00025)
					$C2 = '<0.00025';
			} else if ($id_param == 326 || $id_param == 327 || $id_param == 328 || $id_param == 329) {
				$Vu = \str_replace(",", "",number_format($rerataFlow * $dur * (floatval($tekanan_u) / $Ta) * (298 / 760), 4));
				if($Vu != 0.0) {
					$C = \str_replace(",", "", number_format((floatval($ks) / floatval($Vu)) * 1000, 4));
				}else {
					$C = 0;
				}
				$C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
				$C2 = \str_replace(",", "", number_format(24.45 * floatval($C1) / 64.46, 5));
				if (floatval($C) < 2.1531)
					$C = '<2.1531';
				if (floatval($C1) < 0.0022)
					$C1 = '<0.0022';
				if (floatval($C2) < 0.00082)
					$C2 = '<0.00082';
			} 
			// else if ($id_param == 326 || $id_param == 327 || $id_param == 328 || $id_param == 329) {
			// 	$Vu = \str_replace(",", "",number_format($rerataFlow * $dur * (floatval($tekanan_u) / $Ta) * (298 / 760), 4));
			// 	$C = \str_replace(",", "", number_format(($ks / floatval($Vu)) * 1000, 4));
			// 	$C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
			// 	$C2 = \str_replace(",", "", number_format(24.45 * floatval($C1) / 64.46, 5));
			// 	if (floatval($C) < 2.1531)
			// 		$C = '<2.1531';
			// 	if (floatval($C1) < 0.0022)
			// 		$C1 = '<0.0022';
			// 	if (floatval($C2) < 0.00082)
			// 		$C2 = '<0.00082';
			// } 
			// if ($id_param == 261) {
			// 	$Vu = \str_replace(",", "",number_format($dur * $rerataFlow * (298 / (273 + floatval($suhu))) * (floatval($tekanan_u) / 760), 4));
			// 	$C = \str_replace(
			// 		",",
			// 		"",
			// 		number_format(
			// 			(((20 / 19) * ($ks - $kb) * 12.5) / floatval($Vu)) * 1000,
			// 			4
			// 		)
			// 	);
			// 	// dd(
			// 	// 	'C ' . $C,
			// 	// 	'ks ' . $ks,
			// 	// 	'kb ' . $kb,
			// 	// 	'Vu ' . $Vu
			// 	// );
			// 	$C1 = \str_replace(",", "", number_format(((20 / 19) * ($ks - $kb) * 12.5) / floatval($Vu), 5));
			// 	$C2 = \str_replace(",", "", number_format(24.45 * (floatval($C1) / 20.01), 5));
			// 	if (floatval($C) < 32.8)
			// 		$C = '<32.8';
			// 	if (floatval($C1) < 0.0382)
			// 		$C1 = '<0.0382';
			// 	if (floatval($C2) < 0.0467)
			// 		$C2 = '<0.0467';
			// } else 
			// if ($id_param == 256) {
			// 	$Vu = \str_replace(",", "",number_format($dur * $rerataFlow * (298 / (273 + floatval($suhu))) * (floatval($tekanan_u) / 760), 4));
			// 	$C = \str_replace(
			// 		",",
			// 		"",
			// 		number_format(((($ks - $kb) * 50 * (36.5 / 35.5)) / floatval($Vu)) * 1000000, 4)
			// 	);
			// 	$C1 = \str_replace(",", "", number_format(((($ks - $kb) * 50 * (36.5 / 35.5)) / floatval($Vu)) * 1000, 5));
			// 	$C2 = \str_replace(",", "", number_format(24.45 * (floatval($C1) / 36.4), 5));
			// 	if (floatval($C) < 138.4)
			// 		$C = '<138.4';
			// 	if (floatval($C1) < 0.1384)
			// 		$C1 = '<0.1384';
			// 	if (floatval($C2) < 0.0927)
			// 		$C2 = '<0.0927';
			// } else 

			else if ($id_param == 299 || $id_param == 300) {
				$Vu = \str_replace(",", "",number_format($rerataFlow * $dur * (floatval($tekanan_u) / $Ta) * (298 / 760), 4));
				if($Vu != 0.0) {
					$C = \str_replace(",", "", number_format(($ks / floatval($Vu)) * 1000, 4));
				}else {
					$C = 0;
				}
				$C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
				$C2 = \str_replace(",", "", number_format(24.45 * floatval($C1) / 48, 5));
				if (floatval($C) < 0.1419)
					$C = '<0.1419';
				if (floatval($C1) < 0.00014)
					$C1 = '<0.00014';
				if (floatval($C2) < 0.00007)
					$C2 = '<0.00007';
			} else if ($id_param == 289 || $id_param == 290 || $id_param == 291) {
				$Vu = \str_replace(",", "",number_format($rerataFlow * $dur * (floatval($tekanan_u) / $Ta) * (298 / 760), 4));
				if($Vu != 0.0) {
					$C = \str_replace(",", "", number_format(($ks / floatval($Vu)) * 1000, 4));
				}else {
					$C = 0;
				}
				$C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
				$C2 = \str_replace(",", "", number_format(24.45 * floatval($C1) / 17, 5));
				if (floatval($C) < 0.1419)
					$C = '<0.1419';
				if (floatval($C1) < 0.0005)
					$C1 = '<0.0005';
				if (floatval($C2) < 0.0007)
					$C2 = '<0.0007';
			} else if ($id_param == 246 || $id_param == 247 || $id_param == 248 || $id_param == 249) {
				$Vu = \str_replace(",", "",number_format($rerataFlow * $dur * (floatval($tekanan_u) / $Ta) * (298 / 760), 4));
				if($Vu != 0.0) {
					$C_ = \str_replace(",", "", number_format(($ks - $kb) / floatval($Vu), 4));
				}else {
					$C_ = 0;
				}
				
				$C_ = \str_replace(",", "", number_format((floatval($ks) - floatval($kb)) / (floatval($Vu) != 0.0 ? floatval($Vu) : 1), 4));
				$C = \str_replace(",", "", number_format(floatval($C_) * (34 / 24.45), 4));
				$C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
				$C2 = \str_replace(",", "", number_format(24.45 * floatval($C1) / 34, 5));
				if (floatval($C) < 1.39)
					$C = '<1.39';
				if (floatval($C1) < 0.0014)
					$C1 = '<0.0014';
				if (floatval($C2) < 0.0010)
					$C2 = '<0.0010';
			} else if ($id_param == 342 || $id_param == 343 || $id_param == 344 || $id_param == 345 || $id_param == 310 || $id_param == 313) {
				// $Ta = null;
				// $waktu = $dur;
				if ($request->kateg_tsp == '11') {
					$Vstd = \str_replace(",", "",number_format($nilQs * $dur, 4));
					if((int)$Vstd <= 0) {
						$C = 0;
						$Qs = 0;
						$C1 = 0;
					}else {
						$C = \str_replace(",", "", number_format((($request->w2 - $request->w1) * 10 ** 6) / $Vstd, 4));
						$Qs = $nilQs;
						$C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 6));
					}
					
					if ($id_param == 342) {
						if (floatval($C) < 1.5151)
							$C = '<1.5151';
						if (floatval($C1) < 0.0015)
							$C1 = '<0.0015';
					} else if ($id_param == 343) {
						// if (floatval($C) < 0.0631)
						// 	$C = '<0.0631';
						// if (floatval($C1) < 0.000063)
						// 	$C1 = '<0.000063';
						if (floatval($C) < 1)
							$C = '<1';
						if (floatval($C1) < 0.001)
							$C1 = '<0.001';
					}
					$w1 = $request->w1;
					$w2 = $request->w2;
					
				} else if ($request->kateg_tsp == '27') {
					// dd($rerataFlow, $dur);
					$V = \str_replace(",", "",($rerataFlow * $dur));
					// dd($dur, $rerataFlow, $V);
					$C = \str_replace(",", "", number_format(((($request->w2 - $request->w1) - ($request->b2 - $request->b1)) / $V) * 1000, 6));
					$C1 = \str_replace(",", "", number_format(floatval($C) * 1000 , 6));
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
					if ($id_param == 345) {
						if (floatval($C) < 0.001)
							$C = '<0.001';
						if (floatval($C1) < 0.001)
							$C1 = '<0.001';
					}else if($id_param == 342) {
						if (floatval($C) < 0.001)
							$C = '<0.001';
						if (floatval($C1) < 0.001)
							$C1 = '<0.001';
					}
					$w1 = $request->w1;
					$w2 = $request->w2;
					$b1 = $request->b1;
					$b2 = $request->b2;
					// dd($C);
				}
			} else if ($id_param == 311 || $id_param == 312 || $id_param == 314 || $id_param == 315) {
				// $Ta = null;
				
				$Vstd = \str_replace(",", "",number_format($nilQs * $dur, 4));
				// dd($Vstd,$nilQs,$dur);
				if((int)$Vstd <= 0) {
						$C = 0;
						$Qs = 0;
						$C1 = 0;
					}else {
						$C = \str_replace(",", "", number_format((($request->w2 - $request->w1) * 10 ** 6) / $Vstd, 4));
						$Qs = $nilQs;
						$C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 6));
					}
				if ($id_param == 310 || $id_param == 311 || $id_param == 312) {
					// dd($C,$C1);
					if (floatval($C) < 0.56)
						$C = '<0.56';
					if (floatval($C1) < 0.00056)
						$C1 = '<0.00056';
				} else if ($id_param == 313 || $id_param == 314 || $id_param == 315) {
					if (floatval($C) < 0.58)
						$C = '<0.58';
					if (floatval($C1) < 0.00058)
						$C1 = '<0.00058';
				}
				// dd($request);
				$w1 = $request->w1;
				$w2 = $request->w2;
				// dd($w1, $w2);
			} else if ($id_param == 261 || $id_param == 256 || $id_param == 568) {
				$Vs = \str_replace(",", "",number_format(($dur * $rerataFlow) * (298/(273 + $suhu)) * ($tekanan_u/760), 4));
				if((int)$Vs <= 0) {
						$C = 0;
						$Qs = 0;
						$C1 = 0;
					}else {
						if ($id_param == 261) {
							$C = \str_replace(",", "", number_format((((20/19)*($ks - $kb)* 12.5)/$Vs) * 1000, 4));
							$C1 = \str_replace(",", "", number_format(((20/19)*($ks - $kb)* 12.5)/$Vs, 4));
							$C2 = \str_replace(",", "", number_format(24.45*($C1/20.01), 4));
						} else if ($id_param == 256 || $id_param == 568) {
							$C = \str_replace(",", "", number_format(((($ks - $kb)* 50 * (36.5/35.5))/$Vs) * 1000000, 1));
							$C1 = \str_replace(",", "", number_format(((($ks - $kb)* 50 * (36.5/35.5))/$Vs) * 1000, 4));
							$C2 = \str_replace(",", "", number_format(24.45*($C1/36.5), 4));
						}
					}
				if ($id_param == 261) {
					if (floatval($C) < 38.2)
						$C = '<38.2';
					if (floatval($C1) < 0.0382)
						$C1 = '<0.0382';
					if (floatval($C2) < 0.0467)
						$C2 = '<0.0467';
				} else if ($id_param == 256 || $id_param == 568) {
					if (floatval($C) < 138.4)
						$C = '<138.4';
					if (floatval($C1) < 0.1384)
						$C1 = '<0.1384';
					if (floatval($C2) < 0.0927)
						$C2 = '<0.0927';
				}
			} else if ($id_param == 211 || $id_param == 564) {
			// } else if ($id_param == 355 || $id_param == 564 || $id_param == 211) {
				$Vu = \str_replace(",", "",number_format(($rerataFlow) * $dur * ($tekanan_u/$Ta) * (298/760), 4));
				if((int)$Vu <= 0) {
						$C = 0;
						$Qs = 0;
						$C1 = 0;
					}else {
						$C = \str_replace(",", "", number_format(($ks/$Vu) * 1000000, 3));
						$C1 = \str_replace(",", "", number_format(($ks/$Vu) * 0.001, 3));
						$C2 = \str_replace(",", "", number_format(($C1*0.001) * (24.45/71), 4));
					}
					if (floatval($C) < 4.000)
						$C = '<4.000';
					if (floatval($C1) < 0.004)
						$C1 = '<0.004';
					if (floatval($C2) < 0.0013)
						$C2 = '<0.0013';
			} else if ($id_param == 305 ||$id_param == 306 ||$id_param == 307 ||$id_param == 308 || $id_param == 234 ||$id_param == 569 ||$id_param == 287 ||$id_param == 292 ||$id_param == 219) {
				$Vstd = $rerataFlow * $dur;
				// dd($Vstd);
				// if()
				// $Qs = $nilQs;
				if((int)$Vstd <= 0) {
					$C = 0;
					$Qs = 0;
					$C1 = 0;
				}else {
					$C = \str_replace(",", "", number_format((($request->ks - $request->kb) * $request->vl * $request->st)/$Vstd, 4));
					$C1 = \str_replace(",", "", number_format($C/1000, 6));
					if($id_param == 234 ||$id_param == 569) { // Fe Udara
						$C2 = \str_replace(",", "", number_format($C1*24.45/55.845, 7));
					}else if($id_param == 287) { // Mn Udara
						$C2 = \str_replace(",", "", number_format($C1*24.45/55.845, 7));
					}else if($id_param == 292) {
						$C2 = \str_replace(",", "", number_format($C1*24.45/58.6934, 7));
					}else if($id_param == 219) {
						$C2 = \str_replace(",", "", number_format($C1*24.45/51.9961, 7));
					}else if($id_param == 305 ||$id_param == 306 ||$id_param == 307 ||$id_param == 308) {
						$C2 = \str_replace(",", "", number_format($C1*24.45/207.2, 7));
						if (floatval($C) < 0.0128)
							$C = '<0.0128';
						if (floatval($C1) < 0.000013)
							$C1 = '<0.000013';
						if (floatval($C2) < 0.0000014)
							$C2 = '<0.0000014';
					}
					// dd( $dur, $Vstd, $C, $C1. $C2);
					$vl = $request->vl;
					$st = $request->st;
					$ks = $request->ks;
					$kb = $request->kb;
				}
			} else {
				return 'gagal';
			}
			// dd()
			$data = [
				'lingkungan_header_id' => $id_param_,
				'no_sampel' => $request->no_sample,
				'tanggal_terima' => $tgl_terima,
				'flow' => $rerataFlow,
				'durasi' => $dur,
				// 'durasi' => $waktu,
				'tekanan_u' => $tekanan_u,
				'suhu' => $suhu,
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
				'vl' => $vl,
				'st' => $st,
				'Vstd' => $Vstd,
				'V' => $V,
				'Vu' => $Vu,
				'Vs' => $Vs,
				'Ta' => $Ta,
				'created_by' => $userid,
				'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
			];

			// dd($data);
			return $data;

		} catch (Exception $e) {
			dd($e);
		}
	}

	public function valemisic($tekanan, $t_flue, $suhu, $nil_pv, $tekanan_dry, $volume_dry, $durasi_dry, $flow, $tgl_terima, $request, $userid, $id_param)
	{
		$cari = EmisiCerobongHeader::where('no_sampel', $request->no_sample)
			->where('parameter', $request->parameter)
			->where('is_active', true)
			->first();
		if ($cari == null) {
			$id_param_ = '';
		} else {
			$id_param_ = $cari->id;
		}

		$Vs = null;
		$Vstd = null;
		$vl = null;
		$st = null;
		$ks = null;
		$kb = null;
		$w1 = null;
		$w2 = null;
		$C = null;
		$C1 = null;
		$C2 = null;


		
		if (is_array($request->ks)) {
			$ks = array_sum($request->ks) / count($request->ks);
		}else {
			$ks = floatval($request->ks);
		}
		if (is_array($request->kb)) {
			$kb = array_sum($request->kb) / count($request->kb);
		}else {
			$kb = floatval($request->kb);
		}

		if($id_param == 365 || $id_param == 368 || $id_param == 364) {
			// dd($tekanan_dry);
			$tekanan_dry = LookUpRdm::getRdm();
			
			$Vs = \str_replace(",", "", number_format($volume_dry * (298 / (273 + $suhu)) * (($tekanan + $tekanan_dry - $nil_pv) / 760), 4));
			// $Vs = \str_replace(",", "", number_format($volume_dry * (298 / (273 + $t_flue)) * (($tekanan + $tekanan_dry - $nil_pv) / 760), 4));
			if (floatval($Vs) > 0) {
				if ($id_param == 365) {
					$C1 = \str_replace(",", "", number_format(((20 / 19) * (floatval($ks) - floatval($kb)) * (250 / 20)) / floatval($Vs), 4));
					$C2 = \str_replace(",", "", number_format(24.45 * (floatval($C1) / 20.01), 5));
					if (floatval($C1) < 0.0003)
						$C1 = '<0.0003';
					if (floatval($C2) < 0.00036)
						$C2 = '<0.00036';
				} else if ($id_param == 368) {
					$C1 = \str_replace(",", "", number_format((((floatval($ks) - floatval($kb)) * 25) / floatval($Vs)) * (17 / 24.45), 4));
					$C2 = \str_replace(",", "", number_format(24.45 * (floatval($C1) / 17), 4));
					// dump($C1, $C2);
					if (floatval($C1) < 0.0257)
						$C1 = '<0.0257';
					if (floatval($C2) < 0.0369)
						$C2 = '<0.0369';
				} else if ($id_param == 364) {
					try {
						// $nilbag = \str_replace(",", "", );
						// $nilbag = number_format(36.5 / 35.5, 4);
						$C1 = \str_replace(",", "", number_format((((floatval($ks) - floatval($kb)) * 50 * (36.5 / 35.5)) / floatval($Vs)) * 1000, 4));
						// dd((($ks - $kb) * 50 * (36.5 / 35.5)) / floatval($Vs));
						$C2 = \str_replace(",", "", number_format(24.45 * (floatval($C1) / 36.5), 4));
						if (floatval($C1) < 0.0031)
							$C1 = '<0.0031';
						if (floatval($C2) < 0.0020)
							$C2 = '<0.0020';
					}catch(Exception $e) {
						dd($e);
					}
				}
			} else {
				$C1 = null;
				$C2 = null;
			}
		}else if($id_param == 360 || $id_param == 377 || $id_param == 354 || $id_param ==  358 || $id_param ==  378 || $id_param ==  385) {
			// $Vstd = \str_replace(",", "", number_format($flow * (((298*$tekanan)/(($suhu+273)*760)) ** 1 / 2) * $durasi_dry, 4));
			
			// 04-03-2025
			$Vstd = str_replace(",", "", number_format(
				$flow * pow(((298 * $tekanan) / (($suhu + 273) * 760)), 1 / 2) * $durasi_dry, 
				4
			));
			if (floatval($Vstd) > 0) {
				if ($id_param == 360 || $id_param == 377) {
					$C = \str_replace(",", "", number_format((((floatval($request->w2) - floatval($request->w1)) * 10) ** 6) / floatval($Vstd), 4));
					$C1 = \str_replace(",", "", number_format(floatval($C) / 1000, 5));
					$w1 = $request->w1;
					$w2 = $request->w2;
				} else if ($id_param == 354 || $id_param ==  358 || $id_param ==  378 || $id_param ==  385) {
					$C = \str_replace(",", "", number_format(((floatval($ks) - floatval($kb)) * floatval($request->vl) * floatval($request->st)) / floatval($Vstd), 4));
					$C1 = \str_replace(",", "", number_format((floatval($C) / 1000), 4));
					if($id_param == 354) {
						$C2 = \str_replace(",", "", number_format((floatval($C1) * 24.45 / 108.905), 4));
						if (floatval($C) < 0.0029)
							$C = '<0.0029';
						if (floatval($C1) < 0.0008)
							$C1 = '<0.0008';
						if (floatval($C2) < 0.000018)
							$C2 = '<0.000018';
					}else if($id_param ==  358) {
						$C2 = \str_replace(",", "", number_format((floatval($C1) * 24.45 / 51.9961), 4));
					}else if($id_param ==  378) {
						$C2 = \str_replace(",", "", number_format((floatval($C1) * 24.45 / 207.2), 4));
					}else if($id_param ==  385) {
						$C2 = \str_replace(",", "", number_format((floatval($C1) * 24.45 / 65.38), 4));
					}
					$vl = $request->vl;
					$st = $request->st;
				}
			} else {
				$C = null;
				$C1 = null;
			}
		}

		$data = [
			'id_emisi_cerobong_header' => $id_param_,
			'no_sampel' => $request->no_sample,
			'tanggal_terima' => $tgl_terima,
			'suhu' => $suhu,
			'Va' => $volume_dry,
			'Vs' => $Vs,
			'Vstd' => $Vstd,
			'Pa' => $tekanan,
			'Pm' => $tekanan_dry,
			'Pv' => $nil_pv,
			't' => $durasi_dry,
			'durasi' => $durasi_dry,
			'flow' => $flow,
			'vl' => $vl,
			'st' => $st,
			'k_sample' => $ks,
			'k_blanko' => $kb,
			'w1' => $w1,
			'w2' => $w2,
			'C' => $C,
			'C1' => $C1,
			'C2' => $C2,
			'created_by' => $userid,
			'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
		];
		return $data;
	}

	// METODE BARU

	public function DO($id_param, $no_sample, $volume_titrasi_baru, $vtb, $kt, $vs, $fp, $id_val)
	{
		$rumus = number_format($volume_titrasi_baru * 1, 4, '.', '');
		$RPD = '';
		$Recovery = '';
		$NaCl = '';
		if ($rumus < 0.10) {
			$rumus = '<0.10';
		}
		
		$data = [
			'id_titri' => $id_param,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function BODBaru($id_param, $no_sample, $fp, $do_sampel5, $do_sampel0, $do_blanko5, $do_blanko0, $vmb, $vms)
	{
		$rumus = number_format(((($do_sampel5 - $do_sampel0) - (($do_blanko5 - $do_blanko0) / $vmb)) * $vms) / (1 / $fp), 4, '.', '');
		if($rumus < 1) {
			$rumus = '<1';
		}
		$RPD = '';
		$Recovery = '';
		$NaCl = '';

		$data = [
			'id_titrimetri' => $id_param,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function BauBaru($id_param, $no_sample, $hp, $fp)
	{
		if($hp == "Tidak Berbau"){
			$rumus = "Tidak Berbau";
		}else{
			$rumus = number_format(($hp + (200-$hp))/$hp, 4, '.', '');
		}
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id_param,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}
	
	public function RasaBaru($id_param, $no_sample, $hp, $fp)
	{
		if($hp == "Tidak Berasa"){
			$rumus = "Tidak Berasa";
		}else{
			$rumus = number_format(((($hp + (200-$hp)))/$hp), 4, '.', '');
		}
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$data = [
			'id_colorimetri' => $id_param,
			'no_sampel' => $no_sample,
			'hasil' => $rumus,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function FecalColiMPN($id, $no_sample, $hp, $fp, $id_val, $tb1, $tb2, $tb3, $nil10ml, $nil1ml, $nil01ml, $nil001ml, $nil0001ml){
		$nilai = $hp * (int)$fp;
		$NaCl = '';
		$RPD = '';
		$Recovery = '';
		$nilTambahan = NULL;
		if($id_val == 67) {
			$nilTambahan = [
				"Kombinasi Tabung Positif-1" => $tb1,
				"Kombinasi Tabung Positif-2" => $tb2,
				"Kombinasi Tabung Positif-3" => $tb3,
				"Jumlah Tabung Positif (10 mL)" => $nil10ml,
				"Jumlah Tabung Positif (1 mL)" => $nil1ml,
				"Jumlah Tabung Positif (0.1 mL)" => $nil01ml,
				"Jumlah Tabung Positif (0.01 mL)" => $nil001ml,
				"Jumlah Tabung Positif (0.001 mL)" => $nil0001ml
			];
			$nilTambahan = json_encode($nilTambahan);
		}
		$data = [
			'id_colorimetri' => $id,
			'no_sampel' => $no_sample,
			'hasil' => $nilai,
			'nilai_tambahan_analyst' => $nilTambahan,
			'hasil_2' => $NaCl,
			'rpd' => $RPD,
			'recovery' => $Recovery,
		];
		return $data;
	}

	public function wsMicrobio($rerataFlow, $dur, $tgl_terima, $tekanan_u, $suhu, $request, $userid, $id_param){
		try {
			$cari = DB::table('microbio_header')->where('no_sampel', $request->no_sample)
				->where('parameter', $request->parameter)
				->where('is_active', true)
				->first();
			
			if ($cari == null) {
				$id_param_ = '';
			} else {
				$id_param_ = $cari->id;
			}

			if ($id_param == 266 || $id_param == 235 || $id_param == 295 || $id_param == 296) {
				$Vu = \str_replace(",", "",($rerataFlow * $dur));
				$hasil = \str_replace(",", "", number_format(($request->jumlah_col / $Vu), 4));
			}

			$data = [
				'id_microbio_header' => $id_param_,
				'no_sampel' => $request->no_sample,
				'tanggal_terima' => $tgl_terima,
				'flow' => $rerataFlow,
				'durasi' => $dur,
				'tekanan_u' => $tekanan_u,
				'suhu' => $suhu,
				'jumlah_coloni' => $request->jumlah_col,
				'volume_udara' => $Vu,
				'hasil' => $request->hasil_uji,
				'created_by' => $userid,
				'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
			];
			
			return $data;
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Error : ' . $e->getMessage(),
			], 401);
		}
	}

	public function DebuPersonal($flow, $waktu, $tgl_terima, $request, $userid, $id_param, $tekanan_udara, $suhu){
		try {
			$header = DebuPersonalHeader::where('no_sampel', $request->no_sample)
				->where('parameter', $request->parameter)
				->where('is_active', true)
				->first();
			// dd($header);
			if ($header == null) {
				$id_header = '';
			} else {
				$id_header = $header->id;
			}

			$Ta = floatval($suhu) + 273;
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

			$w1 = $request->w1;
			$w2 = $request->w2;
			$b1 = $request->b1;
			$b2 = $request->b2;

			
			// if ($id_param == 633 || $id_param == 634 || $id_param == 635){
			$vl = number_format($flow * $waktu, 1);
			// dd($w1, $w2, $b1, $b2,$vl,$flow, $waktu);
			$c1 = number_format(((($w2-$w1) - ($b2-$b1)) * 1000) / $vl, 4); //C (mg/m3)
			if($id_param == 222){
				$c1 = number_format((($w2 - $w1) - ($b2 - $b1)) * (10 ** 3) / $vl, 4); // C (mg/m3)
			}
			$c = number_format($c1 * 1000, 4); //C (ug/m3)
			// }
			
			$data = [
				'lingkungan_header_id' => $id_header,
				'no_sampel' => $request->no_sample,
				'tanggal_terima' => $tgl_terima,
				'flow' => $flow,
				'durasi' => $waktu,
				'tekanan_u' => $tekanan_udara,
				'suhu' => $suhu,
				'k_sample' => $ks,
				'k_blanko' => $kb,
				'Qs' => $Qs,
				'w1' => $w1,
				'w2' => $w2,
				'b1' => $b1,
				'b2' => $b2,
				'C' => $c,
				'C1' => $c1,
				'C2' => $c2,
				'vl' => $vl,
				'st' => $st,
				'Vstd' => $Vstd,
				'V' => $V,
				'Vu' => $Vu,
				'Vs' => $Vs,
				'Ta' => $Ta,
				'created_by' => $userid,
				'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
			];
			
			return $data;
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Error : ' . $e->getMessage(),
			], 401);
		}
	}

	public function valDustfall($request, $par_id, $tgl_terima, $userid) {
		$header = DustFallHeader::where('no_sampel', $request->no_sample)
				->where('parameter', $request->parameter)
				->where('is_active', true)
				->first();
		// dd($header);
		if ($header == null) {
			$id_header = '';
		} else {
			$id_header = $header->id;
		}

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

		$w2 = $request->w2;
		$w1 = $request->w1;
		$vl = $request->vl;
		$a = $request->a;
		$t = $request->t;
		$rumus = number_format(((($w2-$w1) * 30 * $vl) / ($a * $t * 0.250)), 4);
		$data = [
				'lingkungan_header_id' => $id_header,
				'no_sampel' => $request->no_sample,
				'tanggal_terima' => $tgl_terima,
				'flow' => $flow,
				'durasi' => $waktu,
				'tekanan_u' => $tekanan_udara,
				'suhu' => $suhu,
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
				'created_by' => $userid,
				'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
			];
			
			return $data;
	}

	public function Microbio($volume, $flow, $durasi, $Suhu, $Tekanan, $Kelembaban, $tgl_terima, $request, $userid, $id_param, $id_header){
		try {
			
			$rumus = number_format(($request->jumlah_coloni / $volume), 2);

			$data = [
				'id_microbio_header' => $id_header,
				'no_sampel' => $request->no_sample,
				'tanggal_terima' => $tgl_terima,
				'flow' => $flow,
				'durasi' => $durasi,
				'tekanan_u' => $Tekanan,
				'suhu' => $Suhu,
				'jumlah_coloni' => $request->jumlah_coloni,
				'volume_udara' => $volume,
				'hasil' => $rumus,
				'created_by' => $userid,
				'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
			];
			
			return $data;
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Error : ' . $e->getMessage(),
			], 401);
		}
	}

	public function SwabTest($luas, $tgl_terima, $request, $userid, $id_param, $id_header){
		try {
			$n = floatval($request->jumlah_mikroba);
			$f = floatval($request->jumlah_pengencer);
			$d = floatval($request->volume);
			$a = floatval($luas);
			// Lakukan perhitungan terlebih dahulu
			$rumus = (($n * $f) / $a) * $d;

			// Cek apakah id_param sesuai dan rumus kurang dari 1
			if ($id_param == 227 || $id_param == 337) {
				if ($rumus < 1) {
					$rumus = '<1';
				} else {
					// Format hasil perhitungan ke 2 desimal
					$rumus = number_format($rumus, 2);
				}
			}

			$data = [
				'id_swab_header' => $id_header,
				'no_sampel' => $request->no_sample,
				'tanggal_terima' => $tgl_terima,
				'luas' => $luas,
				'jumlah_mikroba' => $request->jumlah_mikroba,
				'cairan_pengencer' => $request->jumlah_pengencer,
				'volume' => $request->volume,
				'hasil' => $rumus,
				'created_by' => $userid,
				'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
			];
			
			return $data;
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Error : ' . $e->getMessage(),
			], 401);
		}
	}

	/**
	 * Calculate the concentration of chlorine (Cl2) based on provided parameters.
	 *
	 * This function performs several calculations to determine the volume and concentration
	 * of chlorine in a sample, using various input parameters and formulas. The results
	 * are formatted to four decimal places and returned as a data array.
	 *
	 * @param float $nilaiDgm The DGM value used in the Vs calculation.
	 * @param float $datL_suhu The ambient temperature in degrees Celsius.
	 * @param float $tekanan_udara The air pressure.
	 * @param float $tekanan_meteran The meter pressure.
	 * @param float $tekananAir The saturation vapor pressure.
	 * @param float $kons_blanko The concentration of the blank sample.
	 * @param float $kons_klorin The concentration of chlorine.
	 * @param float $volumeSample The volume of the sample.
	 * @param int $id_po The purchase order ID.
	 * @param string $tgl_terima The date of receipt.
	 * @param Request $request The request object containing sample details.
	 * @param int $userid The ID of the user performing the operation.
	 * @param int $id_param The parameter ID for chlorine.
	 * @return array The calculated data including volume and concentration values.
	 * @throws \Exception If an error occurs during the calculation process.
	 */
	public function Cl2($nilaiDgm, $datL_suhu ,$tekanan_udara, $tekanan_meteran,$tekananAir,$kons_blanko,$kons_klorin,$volumeSample , $tgl_terima, $request,$userid, $id_param, $id_header){
		try {
			$isDivisionZero = false;
			// Vs Formula
			// Nilai DGM x (298/(273+Suhu udara)) x ((Tekanan Udara + Tekanan Meteran - Tekanan uap air Jenuh)/760
			if ((273 + $datL_suhu) != 0 && 760 != 0) {
				$vs = ($nilaiDgm * (298 / (273 + $datL_suhu)) * (($tekanan_udara + $tekanan_meteran - $tekananAir) / 760));
			} else {
				$isDivisionZero = true;
				$vs = 0; // Handle division by zero
			}
			
			// C Formula
			// (((A-B) x 25 x 50 / V) / Vs) x 1000
			if ($volumeSample != 0 && $vs != 0) {
				$c = (((($kons_klorin - $kons_blanko) * 25 * 50) / $volumeSample) / $vs) * 1000;
			} else {
				$isDivisionZero = true;
				$c = 0; // Handle division by zero
			}
			
			// C1 Formula
			// ((0.316 x (A-B) x 25 x 50 / V) / Vs) x 1000
			if ($volumeSample != 0 && $vs != 0) {
				$c1 = ((0.316 * (($kons_klorin - $kons_blanko) * 25 * 50) / $volumeSample) / $vs) * 1000;
			} else {
				$isDivisionZero = true;
				$c1 = 0; // Handle division by zero
			}

			if($isDivisionZero){
				return 'gagal';
			}
			
			// C2 Formula
			// c1 x 3.16 
			$c2 = $c1 * 3.16;
			
			// Format 4 angka dibelakang koma
			$vs = number_format($vs, 4);
			$c = number_format($c, 4);
			$c1 = number_format($c1, 4);
			$c2 = number_format($c2, 4);

			$data = [
				'id_emisi_cerobong_header' => $id_header,
				'id_parameter' => $id_param,
				'no_sampel' => $request->no_sample,
				'tanggal_terima' => $tgl_terima,
				'suhu' => $datL_suhu,
				'Va' => null,
				'Vs' => $vs,
				'Vstd' => null,
				'Pa' => $tekanan_udara,
				'Pm' => $tekanan_meteran,
				'tekanan_air' => $tekananAir,
				'Pv' => null,
				't' => null,
				'vl' => $volumeSample,
				'st' => null,
				'k_sample' => $kons_klorin,
				'k_blanko' => $kons_blanko,
				'w1' => null,
				'w2' => null,
				'C' => $c2,
				'C1' => $c,
				'C2' => $c1,
				'created_by' => $userid,
				'created_at' => Carbon::now()->format('Y-m-d H:i:s'),			
			];

			return $data;
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Error : ' . $e->getMessage(),
				'line' => $e->getLine(),
				'file' => $e->getFile()
			], 401);
		}
	}

	public function Isokinetik($vm, $tgl_terima, $request, $userid, $id_param, $id_header){
		// dd($request->all(),$vm, $id_po, $tgl_terima, $userid, $id_param, $id_header);
		try {
			// KONSTAN
			$k4 = 10**-3;
			$vm = $vm;
			// Hg
			if($id_param == 366){

				$qfh = $request->qfh;
				$vf1b = $request->vf1b;
				$vf2b = $request->vf2b;
				$vf3a = $request->vf3A;
				$vf3b = $request->vf3b;
				$vf3c = $request->vf3c;
				$vsoln1 = $request->vsoln1;
				$vsoln2 = $request->vsoln2;
				$vsoln3a = $request->vsoln3a;
				$vsoln3b = $request->vsoln3b;
				$vsoln3c = $request->vsoln3c;
				$hgbhb = $request->hgbhb;
				$hgfhb = $request->hgfhb;
				$qbh2b = $request->qbh2b;
				$qbh3a = $request->qbh3a;
				$qbh3b = $request->qbh3b;
				$qbh3c = $request->qbh3c;

				// Perhitungan Hg_fh
				$Hg_fh = ($qfh / $vf1b) * $vsoln1;
				
				// Perhitungan Hg_bh2
				$Hg_bh2 = ($qbh2b / $vf2b) * $vsoln2;
				
				// Perhitungan Hg_bh3A
				$Hg_bh3A = ($qbh3a / $vf3a) * $vsoln3a;
				
				// Perhitungan Hg_bh3B
				$Hg_bh3B = ($qbh3b / $vf3b) * $vsoln3b;
				
				// Perhitungan Hg_bh3C
				$Hg_bh3C = ($qbh3c / $vf3c) * $vsoln3c;
				
				// Perhitungan Hg_bh
				$Hg_bh = $Hg_bh2 + $Hg_bh3A + $Hg_bh3B + $Hg_bh3C;
				
				// Perhitungan Hg_t
				$Hg_t = ($Hg_fh - $hgfhb) + ($Hg_bh - $hgbhb);
				
				// Perhitungan Konsentrasi C (mg/Nm³)
				$C = $k4 * $Hg_t / $vm;
				
			}else{
				$ca1 = $request->c_a1;
				$ca2 = $request->c_a2;
				$fa = $request->fa;
				$fd = $request->fd;
				$va = $request->va;
				$vsoln1 = $request->vsoln1;
				$m_bhb = $request->m_bhb;
				$m_fhb = $request->m_fhb;

				// Perhitungan M_fh
				$M_fh = $ca1 * $fd * $vsoln1;

				// Perhitungan M_bh
				$M_bh = $ca2 * $fa * $va;

				// Perhitungan M_t
				$M_t = ($M_fh - $m_fhb) + ($M_bh - $m_bhb);

				// Perhitungan Konsentrasi C (mg/Nm³)
				$C = $k4 * $M_t / $vm;

			}

			$C = number_format($C, 4, '.', '');

			$data = [
				'id_isokinetik_header' => isset($id_header) ? $id_header : null,  // Jika $id_header tidak ada, set null
				'id_parameter' => isset($id_param) ? $id_param : null,  // Jika $id_param tidak ada, set null
				'no_sampel' => isset($request->no_sample) ? $request->no_sample : null,  // Jika $request->no_sample tidak ada, set null
				'tanggal_terima' => isset($tgl_terima) ? $tgl_terima : null,  // Jika $tgl_terima tidak ada, set null
				'vstd' => isset($vm) ? $vm : null,  // Jika $vm tidak ada, set null
				'vsoln1' => isset($vsoln1) ? $vsoln1 : null,  // Jika $vsoln1 tidak ada, set null
				'vsolnbh2b' => isset($vsoln2) ? $vsoln2 : null,  // Jika $vsoln2 tidak ada, set null
				'vsolnbh3a' => isset($vsoln3a) ? $vsoln3a : null,  // Jika $vsoln3a tidak ada, set null
				'vsolnbh3b' => isset($vsoln3b) ? $vsoln3b : null,  // Jika $vsoln3b tidak ada, set null
				'vsolnbh3c' => isset($vsoln3c) ? $vsoln3c : null,  // Jika $vsoln3c tidak ada, set null
				'qfh' => isset($qfh) ? $qfh : null,  // Jika $qfh tidak ada, set null
				'vfbh1b' => isset($vf1b) ? $vf1b : null,  // Jika $vf1b tidak ada, set null
				'vfbh2b' => isset($vf2b) ? $vf2b : null,  // Jika $vf2b tidak ada, set null
				'vfbh3a' => isset($vf3a) ? $vf3a : null,  // Jika $vf3a tidak ada, set null
				'vfbh3b' => isset($vf3b) ? $vf3b : null,  // Jika $vf3b tidak ada, set null
				'vfbh3c' => isset($vf3c) ? $vf3c : null,  // Jika $vf3c tidak ada, set null
				'qbh2b' => isset($qbh2b) ? $qbh2b : null,  // Jika $qbh2b tidak ada, set null
				'qbh3a' => isset($qbh3a) ? $qbh3a : null,  // Jika $qbh3a tidak ada, set null
				'qbh3b' => isset($qbh3b) ? $qbh3b : null,  // Jika $qbh3b tidak ada, set null
				'qbh3c' => isset($qbh3c) ? $qbh3c : null,  // Jika $qbh3c tidak ada, set null
				'ca1' => isset($ca1) ? $ca1 : null,  // Jika $ca1 tidak ada, set null
				'ca2' => isset($ca2) ? $ca2 : null,  // Jika $ca2 tidak ada, set null
				'fa' => isset($fa) ? $fa : null,  // Jika $fa tidak ada, set null
				'fd' => isset($fd) ? $fd : null,  // Jika $fd tidak ada, set null
				'va' => isset($va) ? $va : null,  // Jika $va tidak ada, set null
				'k4' => isset($k4) ? $k4 : null,  // Jika $k4 tidak ada, set null
				'C' => isset($C) ? $C : null,  // Jika $C tidak ada, set null
				'C1' => null,  // Mengatur c1 menjadi null secara langsung
				'C2' => null,  // Mengatur c2 menjadi null secara langsung
				'hgfhb' => isset($hgfhb) ? $hgfhb : (isset($m_fhb) ? $m_fhb : null),  // Jika $hgfhb tidak ada, gunakan $m_fhb, jika tidak ada juga, set null
				'hgbhb' => isset($hgbhb) ? $hgbhb : (isset($m_bhb) ? $m_bhb : null),  // Jika $hgbhb tidak ada, gunakan $m_bhb, jika tidak ada juga, set null
				'created_by' => isset($userid) ? $userid : null,  // Jika $userid tidak ada, set null
				'created_at' => Carbon::now()->format('Y-m-d H:i:s'),  // Jika waktu dibuat otomatis diambil dari server
			];			

		return $data;
		
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Error : ' . $e->getMessage(),
			], 401);
		}
	}	
	
}
