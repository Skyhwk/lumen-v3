<?php

namespace App\HelpersFormula;

use Carbon\Carbon;

class IsokinetikICP {
    public function index($data, $id_parameter, $mdl){

        try {
            // KONSTAN
            $k4 = 10**-3;
            $vm = $data->vm;

            $ca1 = $data->c_a1;
            $ca2 = $data->c_a2;
            $fa = $data->fa;
            $fd = $data->fd;
            $va = $data->va;
            $vsoln1 = $data->vsoln1;
            $m_bhb = $data->m_bhb;
            $m_fhb = $data->m_fhb;

            // Perhitungan M_fh
            $M_fh = $ca1 * $fd * $vsoln1;

            // Perhitungan M_bh
            $M_bh = $ca2 * $fa * $va;

            // Perhitungan M_t
            $M_t = ($M_fh - $m_fhb) + ($M_bh - $m_bhb);

            // Perhitungan Konsentrasi C (mg/Nm³)
            $C = $k4 * $M_t / $vm;

            $processed = [
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