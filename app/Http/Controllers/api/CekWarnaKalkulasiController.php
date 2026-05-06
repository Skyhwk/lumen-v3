<?php

namespace App\Http\Controllers\api;
use App\Models\DasarTargetPenjadwalan as DasarTarget;
use App\Models\MasterTargetPenjadwalan as MasterTarget;
use App\Models\KalkulasiTargetPenjadwalan as KalkulasiTarget;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class CekWarnaKalkulasiController extends Controller
{
    public function index(Request $request)
    {
        $bulanMap = [
            1 => 'januari',
            2 => 'februari',
            3 => 'maret',
            4 => 'april',
            5 => 'mei',
            6 => 'juni',
            7 => 'juli',
            8 => 'agustus',
            9 => 'september',
            10 => 'oktober',
            11 => 'november',
            12 => 'desember',
        ];

        $tahunSekarang = Carbon::now()->year;
        $bulanSekarang = Carbon::now()->month;
        $namaBulan = $bulanMap[$bulanSekarang];
        $active = $request->is_active == '' ? true : $request->is_active;

        $masterTarget = MasterTarget::where('tahun', $tahunSekarang)->where('is_active', 1)->first();
        $kalkulasiTarget = KalkulasiTarget::where('tahun', $tahunSekarang)->first();

        $nilaiMasterTarget = $masterTarget->$namaBulan ?? 0;
        $nilaiKalkulasi = $kalkulasiTarget->$namaBulan ?? 0;

        // 🔥 hitung persen
        $nilaiPersentase = ($nilaiMasterTarget > 0)
            ? ($nilaiKalkulasi / $nilaiMasterTarget) * 100
            : 0;

        // 🔥 ambil semua range
        $dasarTargets = DasarTarget::where('is_active', 1)->get();

        // Fungsi untuk menentukan teks putih atau hitam
        function getContrastColor($hexColor) {
            // Hapus tanda # jika ada
            $hex = str_replace('#', '', $hexColor);

            // Konversi ke RGB
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));

            // Rumus YIQ (Luma contrast)
            $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

            return ($yiq >= 128) ? '#000000' : '#ffffff';
        }

        // 🔥 Cari range yang cocok
        $matched = $dasarTargets->first(function ($item) use ($nilaiPersentase) {
            return $nilaiPersentase > (float) $item->persentase_awal &&
                $nilaiPersentase <= (float) $item->persentase_akhir;
        });

        return response()->json([
            'color'      => $matched ? $matched->color : null,
            'text_color' => $matched ? getContrastColor($matched->color) : '#000000',
            'keterangan' => $matched ? $matched->keterangan : null,
        ]);
    }
}
