<?php

namespace App\Services;

class RulaFormatter
{
    /**
     * Mapping penyesuaian ke bagian tubuh
     */
    protected $adjustmentMap = [
        // LEHER
        "tambah_leher_terpelintir"      => "leher",
        "tambah_leher_ditekuk_samping"  => "leher",

        // BADAN
        "tambah_badan_terpelintir"      => "badan",
        "tambah_badan_tertekuk_samping" => "badan",

        // LENGAN ATAS
        "tambah_bahu_diangkat"          => "lengan_atas",
        "tambah_lengan_menjauhi"        => "lengan_atas",
        "tambah_lengan_menopang"        => "lengan_atas",

        // LENGAN BAWAH
        "tambah_lengan_bawah_menyilang" => "lengan_bawah",

        // PERGELANGAN TANGAN
        "tambah_pergelangan_deviasi"    => "pergelangan_tangan",
        "skor_pergelangan_tangan_memuntir" => "pergelangan_tangan",
    ];

    /**
     * Format RULA payload menjadi format database
     */
    public  function format(array $payload)
    {
        
        // =============== 1. Penyesuaian (checkbox / tambahan) ===============
       
        $penyesuaian = [];

        foreach ($this->adjustmentMap as $key => $bagian) {
            if (isset($payload[$key])) {
                $penyesuaian[$bagian][$key] = (int) $payload[$key];
            }
        }

        // =============== 2. Format RULA bagian A ===============
        $skorA = [
            "beban" => $this->mapBebanLabel($payload["skor_beban_A"] ?? 0),
            "lengan_atas" => [
                "skor" => $this->mapLenganAtasLabel($payload["skor_lengan_atas"] ?? 0)
            ],
            "lengan_bawah" => [
                "skor" => $this->mapLenganBawahLabel($payload["skor_lengan_bawah"] ?? 0)
            ],
            "aktivitas_otot" => $this->mapAktivitasOtotLabel($payload["skor_penggunaan_otot_A"] ?? 0),
            "tangan_memuntir" => $this->mapTanganMemuntirLabel($payload["skor_pergelangan_tangan_memuntir"] ?? 0),
            "pergelangan_tangan" => [
                "skor" => $this->mapPergelanganLabel($payload["skor_pergelangan_tangan"] ?? 0)
            ],
        ];

        // =============== 3. Format RULA bagian B ===============
        $skorB = [
            "kaki" => $this->mapKakiLabel($payload["skor_kaki"] ?? 0),
            "badan" => [
                "skor" => $this->mapBadanLabel($payload["skor_badan"] ?? 0)
            ],
            "beban" => $this->mapBebanLabel($payload["skor_beban_B"] ?? 0),
            "leher" => [
                "skor" => $this->mapLeherLabel($payload["skor_leher"] ?? 0)
            ],
            "aktivitas_otot" => $this->mapAktivitasOtotLabel($payload["skor_penggunaan_otot_B"] ?? 0),
        ];

        // =============== 4. Build final RULA format ===============
        return [
            "kaki" => (int) ($payload["skor_kaki"] ?? 0),
            "badan" => (int) ($payload["skor_badan"] ?? 0),
            "leher" => (int) ($payload["skor_leher"] ?? 0),

            "skor_A" => $skorA,
            "skor_B" => $skorB,

            "beban_A" => (int) ($payload["skor_beban_A"] ?? 0),
            "beban_B" => (int) ($payload["skor_beban_B"] ?? 0),

            "skor_rula" => (int) ($payload["final_skor_rula"] ?? 0),

            // Tambahan nilai tabel
            "total_skor_A" => (int) ($payload["total_skor_A"] ?? 0),
            "nilai_tabel_A" => (int) ($payload["nilai_tabel_A"] ?? 0),
            "total_skor_B" => (int) ($payload["total_skor_B"] ?? 0),
            "nilai_tabel_B" => (int) ($payload["nilai_tabel_B"] ?? 0),

            // Komponen individual
            "lengan_atas" => (int) ($payload["skor_lengan_atas"] ?? 0),
            "lengan_bawah" => (int) ($payload["skor_lengan_bawah"] ?? 0),
            "pergelangan_tangan" => (int) ($payload["skor_pergelangan_tangan"] ?? 0),
            "tangan_memuntir" => (int) ($payload["skor_pergelangan_tangan_memuntir"] ?? 0),
            "aktivitas_otot_A" => (int) ($payload["skor_penggunaan_otot_A"] ?? 0),
            "aktivitas_otot_B" => (int) ($payload["skor_penggunaan_otot_B"] ?? 0),

            // Tambahan baru
            "penyesuaian" => $penyesuaian,
        ];
    }

    // ====================== LABEL MAPPER ======================

    protected function mapBebanLabel($value)
    {
        return [
            0 => "0-Beban <2 Kg (berselang)",
            1 => "1-Beban 2-10 Kg",
            2 => "2-Beban >10 Kg"
        ][$value] ?? "0-Beban <2 Kg (berselang)";
    }

    protected function mapLenganAtasLabel($value)
    {
        return [
            1 => "1-lengan atas netral",
            2 => "2-lengan atas 20-45 deg",
            3 => "3-lengan atas 45-90 deg",
            4 => "4-lengan atas berputar hingga sudut >90 deg"
        ][$value] ?? "1-lengan atas netral";
    }

    protected function mapLenganBawahLabel($value)
    {
        return [
            1 => "1-lengan bawah 60-100 deg",
            2 => "2-lengan bawah menekuk dari sudut 0-60 deg dan atau diatas 100 deg"
        ][$value] ?? "1-lengan bawah 60-100 deg";
    }

    protected function mapPergelanganLabel($value)
    {
        return [
            1 => "1-pergelangan netral",
            2 => "2-pergelangan lengan menekuk hingga sudut antara 0-15 deg",
            3 => "3-pergelangan sudut > 15 deg"
        ][$value] ?? "1-pergelangan netral";
    }

    protected function mapTanganMemuntirLabel($value)
    {
        return [
            0 => "0-Tidak memuntir",
            1 => "1-Jika pergelangan tangan dalam kisaran tengah pada posisi memuntir",
            2 => "2-Memuntir ekstrem"
        ][$value] ?? "0-Tidak memuntir";
    }

    protected function mapAktivitasOtotLabel($value)
    {
        return [
            0 => "0-Jika otot yang digunakan tidak dapat terdeskripsikan",
            1 => "1-Aktivitas statis >10 detik",
            2 => "2-Gerakan berulang-ulang"
        ][$value] ?? "0-Jika otot tidak terdeskripsikan";
    }

    protected function mapBadanLabel($value)
    {
        return [
            1 => "1-Badan tegak",
            2 => "2-badan menekuk sekitar sudut 0-20 deg",
            3 => "3-badan menekuk > 20 deg"
        ][$value] ?? "1-Badan tegak";
    }

    protected function mapLeherLabel($value)
    {
        return [
            1 => "1-leher netral",
            2 => "2-leher menekuk sekitar sudut 10-20 deg",
            3 => "3-leher >20 deg / ekstensi"
        ][$value] ?? "1-leher netral";
    }

    protected function mapKakiLabel($value)
    {
        return [
            1 => "1-Jika kaki dan telapak kaki tertopang dengan baik",
            2 => "2-Kaki tidak stabil"
        ][$value] ?? "1-Jika kaki tertopang";
    }
}
