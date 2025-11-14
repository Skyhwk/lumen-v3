<?php
namespace App\Services;

class RebaFormatter {
    public static function formatRebaData($dataRequest) {
        // mapping dasar (seperti sebelumnya)
        $tablePointLeher = [
            "1" => ["keterangan" => "1-leher menekuk sekitar sudut 0-20 deg"],
            "2" => ["keterangan" => "2-leher menekuk sekitar sudut > 20 deg ke depan"],
            "3" => ["keterangan" => "2-leher menekuk ke belakang"]
        ];
        $tablePointBadan = [
            "1" => ["keterangan" => "1-badan dalam posisi netral"],
            "2" => ["keterangan" => "2-badan menekuk sekitar sudut 0-20 deg ke depan dan kebelakang"],
            "3" => ["keterangan" => "3-badan menekuk sekitar sudut 20-60 deg"],
            "4" => ["keterangan" => "4-badan menekuk hingga sudut >60 deg"]
        ];
        $tablePointKaki = [
            "1" => ["keterangan" => "1-Kaki dalam posisi netral"],
            "2" => ["keterangan" => "2-salah satu kaki menekuk"],
            "3" => ["keterangan" => "1-kaki menekuk hingga sudut 30-60 deg"],
            "4" => ["keterangan" => "2-kaki menekuk hingga sudut >60 deg"]
        ];
        $tablePointLenganAtas = [
            "1" => ["keterangan" => "1-lengan atas dalam posisi netral atau berputar sekitar sudut 0-20 deg"],
            "2" => ["keterangan" => "2-lengan atas berputar sekitar sudut 20-45 deg ke depan dan/atau kebelakang"],
            "3" => ["keterangan" => "3-lengan atas berputar sekitar sudut 45-90 deg"],
            "4" => ["keterangan" => "4-lengan atas berputar hingga sudut >90 deg"]
        ];
        $tablePointLenganBawah = [
            "1" => ["keterangan" => "1-Lengan bawah menekuk hingga sudut antara 60-100 deg"],
            "2" => ["keterangan" => "2-Lengan bawah menekuk dari sudut 0-60 deg dan atau diatas 100 deg"]
        ];
        $tablePointPergelanganTangan = [
            "1" => ["keterangan" => "1-Pergelangan lengan menekuk hingga sudut antara 0-15 deg, baik keatas dan kebawah"],
            "2" => ["keterangan" => "2-Pergelangan lengan menekuk >15deg, baik keatas dan kebawah"]
        ];
        $tablePointAktivitasOtot = [
            "1" => ["keterangan" => "1-Satu atau lebih bagian tubuh dalam keadaan statis, Misal ditopang lebih dari 1 min (+1)"],
            "2" => ["keterangan" => "1-Gerakan berulang-ulang, Misal lebih dari 4 min, Tidak termasuk berjalan (+1)"],
            "3" => ["keterangan" => "1-Postur tubuh tidak stabil selama kerja (+1)"]
        ];

        // template awal hasil akhir
        $result = [
            "skor_A" => [
                "kaki" => [
                    "skor" => $tablePointKaki[$dataRequest['skor_kaki']]['keterangan'] ?? null
                ],
                "badan" => [
                    "skor" => $tablePointBadan[$dataRequest['skor_badan']]['keterangan'] ?? null
                ],
                "beban" => [
                    "skor" => "{$dataRequest['skor_beban']}-Beban"
                ],
                "leher" => [
                    "skor" => $tablePointLeher[$dataRequest['skor_leher']]['keterangan'] ?? null
                ]
            ],
            "skor_B" => [
                "pegangan" => "{$dataRequest['skor_pegangan']}-Pegangan",
                "lengan_atas" => [
                    "skor" => $tablePointLenganAtas[$dataRequest['skor_lengan_atas']]['keterangan'] ?? null
                ],
                "lengan_bawah" => $tablePointLenganBawah[$dataRequest['skor_lengan_bawah']]['keterangan'] ?? null,
                "pergelangan_tangan" => [
                    "skor" => $tablePointPergelanganTangan[$dataRequest['skor_pergelangan_tangan']]['keterangan'] ?? null
                ]
            ],
            "skor_C" => [
                "aktivitas_otot" => $tablePointAktivitasOtot[$dataRequest['skor_aktivitas_otot']]['keterangan'] ?? null
            ],
            "penyesuaian" => [
                "leher" => "",
                "kaki" => "",
                "badan" => "",
                "beban" => "",
                "lengan_atas" => "",
                "pergelangan_tangan" => ""
            ],
            "skor_kaki" => (int)$dataRequest['skor_kaki'],
            "skor_badan" => (int)$dataRequest['skor_badan'],
            "skor_beban" => (int)$dataRequest['skor_beban'],
            "skor_leher" => (int)$dataRequest['skor_leher'],
            "skor_lengan_atas" => (int)$dataRequest['skor_lengan_atas'],
            "skor_lengan_bawah" => (int)$dataRequest['skor_lengan_bawah'],
            "skor_pergelangan_tangan" => (int)$dataRequest['skor_pergelangan_tangan'],
            "skor_pegangan" => (int)$dataRequest['skor_pegangan'],
            "skor_aktivitas_otot" => (int)$dataRequest['skor_aktivitas_otot'],
            "nilai_tabel_a" => (int)$dataRequest['nilai_tabel_a'],
            "total_skor_a" => (int)$dataRequest['total_skor_a'],
            "nilai_tabel_b" => (int)$dataRequest['nilai_tabel_b'],
            "total_skor_b" => (int)$dataRequest['total_skor_b'],
            "nilai_tabel_c" => (int)$dataRequest['nilai_tabel_c'],
            "final_skor_reba" => (int)$dataRequest['final_skor_reba']
        ];

        // --- loop untuk key yang mengandung 'tambah_' ---
        foreach ($dataRequest as $key => $value) {
            if (strpos($key, 'tambah_') === 0) {
                $bagian = null;
                if (strpos($key, 'leher') !== false) {
                    $bagian = 'leher';
                } elseif (strpos($key, 'kaki') !== false) {
                    $bagian = 'kaki';
                } elseif (strpos($key, 'badan') !== false) {
                    $bagian = 'badan';
                } elseif (strpos($key, 'beban') !== false) {
                    $bagian = 'beban';
                } elseif (strpos($key, 'lengan') !== false) {
                    $bagian = 'lengan_atas';
                } elseif (strpos($key, 'pergelangan') !== false) {
                    $bagian = 'pergelangan_tangan';
                }

                if ($bagian && array_key_exists($bagian, $result['penyesuaian'])) {
                    if (!is_array($result['penyesuaian'][$bagian])) {
                        $result['penyesuaian'][$bagian] = [];
                    }
                    $result['penyesuaian'][$bagian][$key] = (int)$value;
                }
            }
        }

        // ubah string kosong jadi 0 agar konsisten
        foreach ($result['penyesuaian'] as $k => $v) {
            if ($v === "") {
                $result['penyesuaian'][$k] = 0;
            }
        }

        return $result;
    }

}