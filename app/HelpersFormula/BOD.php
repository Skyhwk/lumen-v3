<?php

namespace App\HelpersFormula;
use Carbon\Carbon;

class BOD
{
    public function index($data, $id_parameter, $mdl)
    {
        if (!empty($data->do_sampel_5_hari_baru)) { // BOD Baru
            $rumus = 0;

            if ($data->volume_mikroba_blanko_baru != 0 && $data->faktor_pengenceran_baru != 0) {
                // (((A1 - A2) - ((B1 - B2)/VB * VS)) / (1/FP))
                // - A1 = DO Sampel 5 Hari (mg/L)
                // - A2 = DO Sampel 0 Hari (mg/L)
                // - B1 = DO Blanko 5 Hari (mg/L)
                // - B2 = DO Blanko 0 Hari (mg/L)
                // - Vb = Volume Mikroba Blanko (mL)
                // - Vs = Volume Mikroba Sampel (mL)
                // - FP = Faktor Pengenceran
                $asub = $data->do_sampel_5_hari_baru - $data->do_sampel_0_hari_baru;
                $bsub = $data->do_blanko_5_hari_baru - $data->do_blanko_0_hari_baru;
                $vb = $data->volume_mikroba_blanko_baru;
                $vs = $data->volume_mikroba_sampel_baru;
                $fp = $data->faktor_pengenceran_baru;

                $rumus = number_format((($asub - (($bsub / $vb) * $vs)) / (1 / $fp)), 4);
            }

            if (!is_null($mdl) && $rumus < $mdl) {
                $rumus = '<' . $mdl;
            }

            $processed = [
                'no_sampel' => $data->no_sample,
                'hasil' => $rumus,
                'hasil_2' => '',
                'rpd' => '',
                'recovery' => '',
            ];

            return $processed;
        } else { // BOD Lama
            $oksalat = 0.0100;
            $KMnO4 = 31.6;

            $rumus = ((ABS(((10 - $data->vts) * $data->kt) - (10 * $oksalat)) * 1 * $KMnO4 * 1000) / 100) * $data->fp;
            $NaCl = number_format($rumus / 1.423456789, 2, '.', '');
            if ($NaCl < $mdl) {
                $NaCl = '<' . $mdl;
            } else {
                $NaCl = str_replace(",", "", $NaCl);
            }

            $rumus = number_format($rumus, 4);
            $processed = [
                'hasil' => $NaCl,
                'hasil_2' => $rumus,
                'rpd' => '',
                'recovery' => '',
            ];

            return $processed;
        }
    }
}