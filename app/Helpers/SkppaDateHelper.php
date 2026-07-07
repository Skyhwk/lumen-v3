<?php

namespace App\Helpers;

use Carbon\Carbon;

class SkppaDateHelper
{
    public static function formatTanggalRange($awal, $akhir = null)
    {
        Carbon::setLocale('id');

        if (empty($awal)) {
            return '-';
        }

        return !empty($akhir)
            ? Carbon::parse($awal)->translatedFormat('d F Y') . ' - ' . Carbon::parse($akhir)->translatedFormat('d F Y')
            : Carbon::parse($awal)->translatedFormat('d F Y');
    }

    public static function formatPeriode($periode)
    {
        Carbon::setLocale('id');

        if (empty($periode)) {
            return '-';
        }

        try {
            return Carbon::createFromFormat('Y-m', $periode)->translatedFormat('F Y');
        } catch (\Throwable $th) {
            return $periode;
        }
    }
}
