<?php

namespace App\Services;

class RlwFormatter
{
    /**
     * Format data NIOSH agar sesuai struktur yang disimpan di database.
     *
     * @param array $data      // data hasil hitung dari frontend
     * @param array $extraData // id_datalapangan, no_sampel, method
     * @return array
     */
    public static function format(array $data, array $extraData = []): array
    {
        return [
            "lokasi_tangan" => [
                "Vertikal Awal"     => $data["lokasi_tangan"]["Vertikal Awal"] ?? null,
                "Vertikal Akhir"    => $data["lokasi_tangan"]["Vertikal Akhir"] ?? null,
                "Horizontal Awal"   => $data["lokasi_tangan"]["Horizontal Awal"] ?? null,
                "Horizontal Akhir"  => $data["lokasi_tangan"]["Horizontal Akhir"] ?? null,
            ],

            "sudut_asimetris" => [
                "Awal"  => $data["sudut_asimetris"]["Awal"] ?? null,
                "Akhir" => $data["sudut_asimetris"]["Akhir"] ?? null,
            ],

            // LI & multipliers awal & akhir
            "lifting_index_awal"     => $data["lifting_index_awal"] ?? null,
            "pengali_jarak_awal"     => $data["pengali_jarak_awal"] ?? null,
            "lifting_index_akhir"    => $data["lifting_index_akhir"] ?? null,
            "pengali_jarak_akhir"    => $data["pengali_jarak_akhir"] ?? null,

            // Data beban & RWL awal & akhir
            "konstanta_beban_awal"   => $data["konstanta_beban_awal"] ?? null,
            "nilai_beban_rwl_awal"   => $data["nilai_beban_rwl_awal"] ?? null,
            "pengali_kopling_awal"   => $data["pengali_kopling_awal"] ?? null,
            "durasi_jam_kerja_awal"  => $data["durasi_jam_kerja_awal"] ?? null,
            "frekuensi_jumlah_awal"  => $data["frekuensi_jumlah_awal"] ?? null,

            "konstanta_beban_akhir"  => $data["konstanta_beban_akhir"] ?? null,
            "nilai_beban_rwl_akhir"  => $data["nilai_beban_rwl_akhir"] ?? null,
            "pengali_kopling_akhir"  => $data["pengali_kopling_akhir"] ?? null,
            "durasi_jam_kerja_akhir" => $data["durasi_jam_kerja_akhir"] ?? null,
            "frekuensi_jumlah_akhir" => $data["frekuensi_jumlah_akhir"] ?? null,

            // Multiplier faktor awal & akhir
            "pengali_vertikal_awal"   => $data["pengali_vertikal_awal"] ?? null,
            "pengali_asimetris_awal"  => $data["pengali_asimetris_awal"] ?? null,
            "pengali_frekuensi_awal"  => $data["pengali_frekuensi_awal"] ?? null,
            "pengali_horizontal_awal" => $data["pengali_horizontal_awal"] ?? null,

            "kesimpulan_nilai_li_awal" => $data["kesimpulan_nilai_li_awal"] ?? null,

            "pengali_vertikal_akhir"   => $data["pengali_vertikal_akhir"] ?? null,
            "pengali_asimetris_akhir"  => $data["pengali_asimetris_akhir"] ?? null,
            "pengali_frekuensi_akhir"  => $data["pengali_frekuensi_akhir"] ?? null,
            "pengali_horizontal_akhir" => $data["pengali_horizontal_akhir"] ?? null,

            "kesimpulan_nilai_li_akhir" => $data["kesimpulan_nilai_li_akhir"] ?? null,

            // Extra (id_datalapangan, no_sampel, method)
            "id_datalapangan" => $extraData["id_datalapangan"] ?? null,
            "no_sampel"       => $extraData["no_sampel"] ?? null,
            "method"          => $extraData["method"] ?? 5, // default method = 5 (NIOSH)
        ];
    }
}
