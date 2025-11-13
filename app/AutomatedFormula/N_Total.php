<?php

namespace App\AutomatedFormula;

use App\Models\Colorimetri;
use App\Models\Titrimetri;
use App\Models\WsValueAir;
use Carbon\Carbon;

class N_Total
{
    public function index($required_parameter, $parameter, $no_sampel, $tanggal_terima) {
        // Check apakah sudah ada data sebelumnya
        $check = Colorimetri::where('parameter', $parameter)
            ->where('no_sampel', $no_sampel)
            ->where('template_stp', 76)
            ->where('is_active', true)
            ->where('is_total', false)
            ->first();
            
        if(isset($check->id)){
            return null; // Return null jika sudah ada
        }
        
        $all_value = [];
        $n_organik = ['N-Organik', 'N-Organik (NA)'];
        
        foreach($required_parameter as $key => $value){
            // Cari data berdasarkan jenis parameter
            if(in_array($value, $n_organik)){
                $data = Titrimetri::where('parameter', $value)
                    ->where('no_sampel', $no_sampel)
                    ->where('is_active', true)
                    ->where('is_total', true)
                    ->first();
            } else {
                $data = Colorimetri::where('parameter', $value)
                    ->where('no_sampel', $no_sampel)
                    ->where('is_active', true)
                    ->where('is_total', true)
                    ->first();
            }

            if($data){
                // Ambil hasil dari WsValueAir
                $result = WsValueAir::where(
                    in_array($value, $n_organik) ? 'id_titrimetri' : 'id_colorimetri', 
                    $data->id
                )->first();
                
                if($result && isset($result->hasil)){
                    // Konversi hasil ke numeric
                    $nilai = $this->parseHasil($result->hasil);
                    $all_value[] = $nilai;
                    
                    /** 
                     * Berhenti ketika item kedua karena 2 parameter pertama 
                     * itu sudah mewakili satu parameter wajib yang harus ada 
                     * ketika akan menghitung parameter ini
                     */
                    if (count($all_value) == 0 && $key == 1) {
                        break;
                    } else if (count($all_value) == 4) {
                        break;
                    }
                }
            }
        }

        // Hanya proses jika sudah terkumpul 4 nilai
        if(count($all_value) == 4){
            $total = array_sum($all_value);
            
            // Format hasil
            if($total < 0.0009){
                $hasil = '<0,0009';
            } else {
                $hasil = number_format($total, 4, ',', '');
            }
            
            // Jangan insert TKN di sini
            if($parameter !== 'TKN') {
                $insert = new Colorimetri();
                $insert->no_sampel = $no_sampel;
                $insert->parameter = $parameter;
                $insert->tanggal_terima = $tanggal_terima;
                $insert->jenis_pengujian = 'sample';
                $insert->template_stp = 76;
                $insert->created_by = 'SYSTEM';
                $insert->created_at = Carbon::now();
                $insert->is_total = false;
                $insert->save();

                $ws_hasil = new WsValueAir();
                $ws_hasil->id_colorimetri = $insert->id;
                $ws_hasil->no_sampel = $no_sampel;
                $ws_hasil->hasil = $hasil;
                $ws_hasil->save();
            }
    
            return $total; // Return numeric value
        }
        
        return null; // Tidak cukup data
    }
    
    /**
     * Parse string hasil menjadi numeric value
     * 
     * @param string $hasil
     * @return float
     */
    private function parseHasil($hasil) {
        // Jika ada tanda '<', anggap 0
        if(strpos($hasil, '<') !== false){
            return 0;
        }
        
        // Replace koma dengan titik untuk desimal
        // Hapus semua karakter non-numeric kecuali titik dan koma
        $cleaned = preg_replace('/[^\d,.]/', '', $hasil);
        
        // Jika ada koma, replace dengan titik
        $cleaned = str_replace(',', '.', $cleaned);
        
        return (float) $cleaned;
    }
}