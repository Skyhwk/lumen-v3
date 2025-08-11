<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class IsokinetikHg {
    public function index($data, $id_parameter, $mdl){

        try {
            // KONSTAN
            $k4 = 10**-3;
            $vm = $data->vm;

            $qfh = $data->qfh;
            $vf1b = $data->vf1b;
            $vf2b = $data->vf2b;
            $vf3a = $data->vf3A;
            $vf3b = $data->vf3b;
            $vf3c = $data->vf3c;
            $vsoln1 = $data->vsoln1;
            $vsoln2 = $data->vsoln2;
            $vsoln3a = $data->vsoln3a;
            $vsoln3b = $data->vsoln3b;
            $vsoln3c = $data->vsoln3c;
            $hgbhb = $data->hgbhb;
            $hgfhb = $data->hgfhb;
            $qbh2b = $data->qbh2b;
            $qbh3a = $data->qbh3a;
            $qbh3b = $data->qbh3b;
            $qbh3c = $data->qbh3c;

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

            $C = number_format($C, 4, '.', '');

            // $detail_column = (object) [
            //     'Q fh' => $qfh,
            //     'V f1b' => $vf1b,
            //     'V f2b' => $vf2b,
            //     'V f3a' => $vf3a,
            //     'V f3b' => $vf3b,
            //     'V f3c' => $vf3c,
            //     'V soln1' => $vsoln1,
            //     'V soln2' => $vsoln2,
            //     'V soln3A' => $vsoln3a,
            //     'V soln3B' => $vsoln3b,
            //     'V soln3C' => $vsoln3c,
            //     'Hg fhb' => $hgfhb,
            //     'Hg bhb' => $hgbhb,
            //     'Q bh2b' => $qbh2b,
            //     'Q bh3a' => $qbh3a,
            //     'Q bh3b' => $qbh3b,
            //     'Q bh3c' => $qbh3c,
            //     'Hg fh' => $Hg_fh,
            //     'Hg bh' => $Hg_bh,
            //     'Hb bh2' => $Hg_bh2,
            //     'Hb bh3A' => $Hg_bh3A,
            //     'Hb bh3B' => $Hg_bh3B,
            //     'Hb bh3C' => $Hg_bh3C,
            //     'Hg t' => $Hg_t,
            //     'C' => $C    
            // ];

            $processed = [
                // 'id_parameter' => $id_parameter,
                // 'tanggal_terima' => $data->tanggal_terima,
                // 'suhu' => null,
                // 'Va' => null,
                // 'Vs' => null,
                // 'Vstd' => null,
                // 'Pa' => null,
                // 'Pm' => null,
                // 'tekanan_air' => null,
                // 'Pv' => null,
                // 't' => null,
                // 'vl' => null,
                // 'st' => null,
                // 'k_sample' => null,
                // 'k_blanko' => null,
                // 'w1' => null,
                // 'w2' => null,
                // 'C' => $c2,
                // 'C1' => $c,
                // 'C2' => $c1,
                // 'detail' => json_encode($detail_column),
                // 'created_at' => Carbon::now()->format('Y-m-d H:i:s')  // Jika waktu dibuat otomatis diambil dari server
                'id_parameter' => isset($id_parameter) ? $id_parameter : null,  // Jika $id_param tidak ada, set null
                'no_sampel' => isset($data->no_sample) ? $data->no_sample : null,  // Jika $data->no_sample tidak ada, set null
                'tanggal_terima' => isset($data->tanggal_terima) ? $data->tanggal_terima : null,  // Jika $tgl_terima tidak ada, set null
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
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),  // Jika waktu dibuat otomatis diambil dari server
            ];

            return $processed;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}