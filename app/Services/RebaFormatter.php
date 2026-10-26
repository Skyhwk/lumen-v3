<?php
namespace App\Services;

class RebaFormatter {
    public  function formatRebaData($dataRequest) {
        // mapping dasar (seperti sebelumnya)
        

        //variabel
        $skorKaki = $this->tablePointKaki($dataRequest['skor_kaki'] ?? null);
        $skorBadan =$this->tablePointBadan($dataRequest['skor_badan'] ?? null);
        $skorBeban = $this->tableBeban($dataRequest['skor_beban'] ?? null);
        $skorLeher = $this->tablePointLeher($dataRequest['skor_leher'] ?? null);
        $skorPegangan = $this->tablePointPegangan($dataRequest['skor_pegangan'] ?? null);
        $skorLenganAtas = $this->tablePointLenganAtas($dataRequest['skor_lengan_atas'] ?? null);
        $skorLenganBawah = $this->tablePointLenganBawah($dataRequest['skor_lengan_bawah'] ?? null);
        $skorPergelangan = $this->tablePointPergelanganTangan($dataRequest['skor_pergelangan_tangan'] ?? null);
        $skorAktivitasOtot = $this->tablePointAktivitasOtot($dataRequest['skor_aktivitas_otot'] ?? null);


        // template awal hasil akhir
        $result = [
            "skor_A" => [
                "kaki" => [
                    "skor" => $skorKaki
                ],
                "badan" => [
                    "skor" => $skorBadan
                ],
                "beban" => [
                    "skor" => $skorBeban
                ],
                "leher" => [
                    "skor" => $skorLeher
                ]
            ],
            "skor_B" => [
                "pegangan" => [
                    "skor" =>$skorPegangan
                ],
                "lengan_atas" => [
                    "skor" => $skorLenganAtas
                ],
                "lengan_bawah" => [
                    "skor" =>$skorLenganBawah
                ],
                "pergelangan_tangan" => [
                    "skor" => $skorPergelangan
                ]
            ],
            "skor_C" => [
                "aktivitas_otot" => [
                    "skor" =>$skorAktivitasOtot
                ]
            ],
            "penyesuaian" => [
                "leher" => "",
                "kaki" => "",
                "badan" => "",
                "beban" => "",
                "lengan_atas" => "",
                "pergelangan_tangan" => ""
            ],
            "skor_kaki" => $skorKaki['score'],
            "skor_badan" => $skorBadan['score'],
            "skor_beban" => $skorBeban['score'],
            "skor_leher" => $skorLeher['score'],
            "skor_lengan_atas" => $skorLenganAtas['score'],
            "skor_lengan_bawah" => $skorLenganBawah['score'],
            "skor_pergelangan_tangan" => $skorPergelangan['score'],
            "skor_pegangan" => $skorPegangan['score'],
            "skor_aktivitas_otot" => $skorAktivitasOtot['score'],
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

    /**
     * Format pengukuran REBA selaras mobile / apps-fdl (skor string, tanpa objek index/score/keterangan).
     */
    public function formatRebaLegacyData(array $data): array
    {
        $idx = static function (string $key, int $default = 0) use ($data): int {
            if (!isset($data[$key]) || $data[$key] === '') {
                return $default;
            }
            return (int) $data[$key];
        };
        $checked = static function (string $key) use ($data): bool {
            return (int) ($data[$key] ?? 0) === 1;
        };
        $pick = static function (array $options, int $index): string {
            return $options[$index] ?? $options[0];
        };

        $leherOpts = [
            '1-leher menekuk sekitar sudut 0-20 deg',
            '2-leher menekuk sekitar sudut > 20 deg ke depan',
            '2-leher menekuk ke belakang',
        ];
        $badanOpts = [
            '1-badan dalam posisi netral',
            '2-badan menekuk sekitar sudut 0-20 deg ke depan dan kebelakang',
            '3-badan menekuk sekitar sudut 20-60 deg',
            '4-badan menekuk hingga sudut >60 deg',
        ];
        $kakiOpts = [
            '1-Kaki dalam posisi netral',
            '2-salah satu kaki menekuk',
        ];
        $bebanOpts = [
            '0-Beban <5 Kg ',
            '1-Beban 5-10 Kg',
            '2-Beban >10 Kg',
        ];
        $lenganAtasOpts = [
            '1-lengan atas dalam posisi netral atau berputar sekitar sudut 0-20 deg',
            '2-lengan atas berputar sekitar sudut 20-45 deg ke depan dan/atau kebelakang',
            '3-lengan atas berputar sekitar sudut 45-90 deg',
            '4-lengan atas berputar hingga sudut >90 deg',
        ];
        $lenganBawahOpts = [
            '1-Lengan bawah menekuk hingga sudut antara 60-100 deg',
            '2-Lengan bawah menekuk dari sudut 0-60 deg dan atau diatas 100 deg',
        ];
        $pergelanganOpts = [
            '1-Pergelangan lengan menekuk hingga sudut antara 0-15 deg, baik keatas dan kebawah',
            '2-Pergelangan lengan menekuk >15deg, baik keatas dan kebawah',
        ];
        $peganganOpts = [
            '0-Pegangan Bagus(Pegangan kontainer baik dan kuat)',
            '1-Pegangan Sedang(Pegangan dapat diterima, tetapi tidak ideal)',
            '2-Pegangan Kurang Baik(Pegangan dapat digunakan, tetapi tidak dapat diterima)',
            '3-Pegangan Jelek(Terlalu dipaksakan dan tidak dapat diterima)',
        ];
        $aktivitasOpts = [
            -1 => '0-Tidak ada aktivitas statis atau berulang',
            0 => '1-Satu atau lebih bagian tubuh dalam keadaan statis, Misal ditopang lebih dari 1 min',
            1 => '1-Gerakan berulang-ulang, Misal lebih dari 4 min, Tidak termasuk berjalan',
            2 => '1-Postur tubuh tidak stabil selama kerja',
        ];

        $leher = ['skor' => $pick($leherOpts, $idx('skor_leher'))];
        if ($checked('tambah_leher_memuntir')) {
            $leher['tambahan'] = '1-posisi leher membungkuk dan atau memuntir';
        }

        $badan = ['skor' => $pick($badanOpts, $idx('skor_badan'))];
        if ($checked('tambah_badan_memuntir')) {
            $badan['tambahan'] = '1-posisi leher membungkuk dan atau memuntir';
        }

        $kaki = ['skor' => $pick($kakiOpts, $idx('skor_kaki'))];
        $tambahKaki = $idx('tambah_kaki_menekuk', 0);
        if ($tambahKaki === 1) {
            $kaki['tambahan'] = '1-kaki menekuk hingga sudut 30-60 deg';
        } elseif ($tambahKaki === 2) {
            $kaki['tambahan_2'] = '2-kaki menekuk hingga sudut >60 deg';
        }

        $beban = ['skor' => $pick($bebanOpts, $idx('skor_beban'))];
        if ($checked('tambah_beban_tiba_tiba')) {
            $beban['tambahan'] = '1-Pembebanan secara tiba-tiba atau mendadak';
        }

        $lenganAtas = ['skor' => $pick($lenganAtasOpts, $idx('skor_lengan_atas'))];
        if ($checked('tambah_lengan_bahu_diangkat')) {
            $lenganAtas['tambahan'] = '1-jika bahu diangkat/diputar/dirotasi';
        }
        if ($checked('tambah_lengan_bahu_menjauhi')) {
            $lenganAtas['tambahan_2'] = '1-jika bahu diangkat menjauhi badan';
        }
        if ((int) ($data['tambah_lengan_menopang'] ?? 0) === -1) {
            $lenganAtas['tambahan_3'] = '1-jika lengan menopang / bersandar';
        }

        $pergelangan = ['skor' => $pick($pergelanganOpts, $idx('skor_pergelangan_tangan'))];
        if ($checked('tambah_pergelangan_memuntir')) {
            $pergelangan['tambahan'] = '1-Jika pergelangan tangan memuntir';
        }

        $aktivitasIndex = $idx('skor_aktivitas_otot', -1);
        $aktivitasStr = $aktivitasOpts[$aktivitasIndex] ?? $aktivitasOpts[-1];

        $skorA = [
            'leher' => $leher,
            'badan' => $badan,
            'kaki' => $kaki,
            'beban' => $beban,
        ];
        $skorB = [
            'lengan_atas' => $lenganAtas,
            'lengan_bawah' => $pick($lenganBawahOpts, $idx('skor_lengan_bawah')),
            'pergelangan_tangan' => $pergelangan,
            'pegangan' => $pick($peganganOpts, $idx('skor_pegangan')),
        ];
        $skorC = [
            'aktivitas_otot' => $aktivitasStr,
        ];

        return $this->computeLegacyRebaSummary($skorA, $skorB, $skorC);
    }

    private function extractLegacyRebaScore($value): int
    {
        if (is_string($value) && preg_match('/^(-?\d+)-/', $value, $matches)) {
            return (int) $matches[1];
        }
        return (int) $value;
    }

    private function sumLegacyRebaSections(array $skorA, array $skorB, array $skorC): array
    {
        $sections = ['skor_A' => $skorA, 'skor_B' => $skorB, 'skor_C' => $skorC];
        $hasil = [];
        foreach ($sections as $kategori => $bagian) {
            foreach ($bagian as $key => $item) {
                $hasil[$kategori][$key] = 0;
                if (is_array($item)) {
                    foreach ($item as $subKey => $val) {
                        if ($kategori === 'skor_B' && $key === 'lengan_atas' && $subKey === 'tambahan_3') {
                            $hasil[$kategori][$key] -= $this->extractLegacyRebaScore($val);
                        } else {
                            $hasil[$kategori][$key] += $this->extractLegacyRebaScore($val);
                        }
                    }
                } else {
                    $hasil[$kategori][$key] += $this->extractLegacyRebaScore($item);
                }
            }
        }
        return $hasil;
    }

    private function computeLegacyRebaSummary(array $skorA, array $skorB, array $skorC): array
    {
        $tableA = [
            1 => [1 => [1 => 1, 2 => 2, 3 => 3, 4 => 4], 2 => [1 => 1, 2 => 2, 3 => 3, 4 => 4], 3 => [1 => 3, 2 => 3, 3 => 5, 4 => 6]],
            2 => [1 => [1 => 2, 2 => 3, 3 => 4, 4 => 5], 2 => [1 => 3, 2 => 4, 3 => 5, 4 => 6], 3 => [1 => 4, 2 => 5, 3 => 6, 4 => 7]],
            3 => [1 => [1 => 2, 2 => 4, 3 => 5, 4 => 6], 2 => [1 => 4, 2 => 5, 3 => 6, 4 => 7], 3 => [1 => 5, 2 => 6, 3 => 7, 4 => 8]],
            4 => [1 => [1 => 3, 2 => 5, 3 => 6, 4 => 7], 2 => [1 => 5, 2 => 6, 3 => 7, 4 => 8], 3 => [1 => 6, 2 => 7, 3 => 8, 4 => 9]],
            5 => [1 => [1 => 4, 2 => 6, 3 => 7, 4 => 8], 2 => [1 => 6, 2 => 7, 3 => 8, 4 => 9], 3 => [1 => 7, 2 => 8, 3 => 9, 4 => 9]],
        ];
        $tableB = [
            1 => [1 => [1 => 1, 2 => 2, 3 => 2], 2 => [1 => 1, 2 => 2, 3 => 3]],
            2 => [1 => [1 => 1, 2 => 2, 3 => 3], 2 => [1 => 2, 2 => 3, 3 => 4]],
            3 => [1 => [1 => 3, 2 => 4, 3 => 5], 2 => [1 => 4, 2 => 5, 3 => 5]],
            4 => [1 => [1 => 4, 2 => 5, 3 => 5], 2 => [1 => 5, 2 => 6, 3 => 7]],
            5 => [1 => [1 => 6, 2 => 7, 3 => 8], 2 => [1 => 7, 2 => 8, 3 => 8]],
            6 => [1 => [1 => 7, 2 => 8, 3 => 8], 2 => [1 => 8, 2 => 9, 3 => 9]],
        ];
        $tableC = [
            1 => [1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 3, 6 => 3, 7 => 4, 8 => 5, 9 => 6, 10 => 7, 11 => 7, 12 => 7],
            2 => [1 => 1, 2 => 2, 3 => 2, 4 => 3, 5 => 4, 6 => 4, 7 => 5, 8 => 6, 9 => 6, 10 => 7, 11 => 7, 12 => 8],
            3 => [1 => 2, 2 => 3, 3 => 3, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 7, 10 => 8, 11 => 8, 12 => 8],
            4 => [1 => 3, 2 => 4, 3 => 4, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 8, 10 => 9, 11 => 9, 12 => 9],
            5 => [1 => 4, 2 => 4, 3 => 4, 4 => 5, 5 => 6, 6 => 7, 7 => 8, 8 => 8, 9 => 9, 10 => 9, 11 => 9, 12 => 9],
            6 => [1 => 6, 2 => 6, 3 => 6, 4 => 7, 5 => 8, 6 => 8, 7 => 9, 8 => 9, 9 => 10, 10 => 10, 11 => 10, 12 => 10],
            7 => [1 => 7, 2 => 7, 3 => 7, 4 => 8, 5 => 9, 6 => 9, 7 => 9, 8 => 10, 9 => 10, 10 => 11, 11 => 11, 12 => 11],
            8 => [1 => 8, 2 => 8, 3 => 8, 4 => 9, 5 => 10, 6 => 10, 7 => 10, 8 => 10, 9 => 10, 10 => 11, 11 => 11, 12 => 11],
            9 => [1 => 9, 2 => 9, 3 => 9, 4 => 10, 5 => 10, 6 => 10, 7 => 11, 8 => 11, 9 => 11, 10 => 12, 11 => 12, 12 => 12],
            10 => [1 => 10, 2 => 10, 3 => 10, 4 => 11, 5 => 11, 6 => 11, 7 => 11, 8 => 12, 9 => 12, 10 => 12, 11 => 12, 12 => 12],
            11 => [1 => 11, 2 => 11, 3 => 11, 4 => 11, 5 => 12, 6 => 12, 7 => 12, 8 => 12, 9 => 12, 10 => 12, 11 => 12, 12 => 12],
            12 => [1 => 12, 2 => 12, 3 => 12, 4 => 12, 5 => 12, 6 => 12, 7 => 12, 8 => 12, 9 => 12, 10 => 12, 11 => 12, 12 => 12],
        ];

        $parsed = $this->sumLegacyRebaSections($skorA, $skorB, $skorC);

        $leher = $parsed['skor_A']['leher'] ?? 0;
        $badan = $parsed['skor_A']['badan'] ?? 0;
        $kaki = $parsed['skor_A']['kaki'] ?? 0;
        $beban = $parsed['skor_A']['beban'] ?? 0;
        $skorA_dari_tabel = $tableA[$badan][$leher][$kaki] ?? 0;
        $totalSkorA = $skorA_dari_tabel + $beban;

        $lengan_atas = $parsed['skor_B']['lengan_atas'] ?? 0;
        $lengan_bawah = $parsed['skor_B']['lengan_bawah'] ?? 0;
        $pergelangan = $parsed['skor_B']['pergelangan_tangan'] ?? 0;
        $pegangan = $parsed['skor_B']['pegangan'] ?? 0;
        $skorB_dari_tabel = $tableB[$lengan_atas][$lengan_bawah][$pergelangan] ?? 0;
        $totalSkorB = $skorB_dari_tabel + $pegangan;

        $aktivitasi_otot = $parsed['skor_C']['aktivitas_otot'] ?? 0;
        $skorC_dari_tabel = $tableC[$totalSkorA][$totalSkorB] ?? 0;
        $totalSkorC = $aktivitasi_otot + $skorC_dari_tabel;

        return [
            'skor_A' => $skorA,
            'skor_leher' => $leher,
            'skor_badan' => $badan,
            'skor_kaki' => $kaki,
            'skor_beban' => $beban,
            'nilai_tabel_a' => $skorA_dari_tabel,
            'total_skor_a' => $totalSkorA,
            'skor_B' => $skorB,
            'skor_lengan_atas' => $lengan_atas,
            'skor_lengan_bawah' => $lengan_bawah,
            'skor_pergelangan_tangan' => $pergelangan,
            'skor_pegangan' => $pegangan,
            'nilai_tabel_b' => $skorB_dari_tabel,
            'total_skor_b' => $totalSkorB,
            'skor_C' => $skorC,
            'skor_aktivitas_otot' => $aktivitasi_otot,
            'nilai_tabel_c' => $skorC_dari_tabel,
            'final_skor_reba' => $totalSkorC,
        ];
    }

    protected function tablePointLeher($value) {
        if($value === null) return ["keterangan" =>"Tidak diketahu","score"=>0];
        switch((int)$value){
            case 0 :return ["keterangan" => "1-leher menekuk sekitar sudut 0-20 deg","score"=>1,"index"=>0];
            case 1 :return ["keterangan" => "2-leher menekuk sekitar sudut > 20 deg ke depan","score"=>2,"index"=>1];
            case 2 :return ["keterangan" => "2-leher menekuk ke belakang","score"=>2,"index"=>2];
            default:return ["keterangan" =>"Tidak diketahu","score"=>0];
        }
    }
    protected function tablePointBadan ($value) {
        if($value === null) return ["keterangan" =>"Tidak diketahu","score"=>0];
        switch((int)$value){
            case 0 :return ["keterangan" => "badan dalam posisi netral","score"=>1,"index"=>0];
            case 1 :return ["keterangan" => "badan menekuk sekitar sudut 0-20 deg ke depan dan kebelakang","score"=>2,"index"=>1];
            case 2 :return ["keterangan" => "badan menekuk sekitar sudut 20-60 deg","score"=>3,"index"=>2];
            case 3 :return ["keterangan" => "badan menekuk hingga sudut >60 deg","score"=>4,"index"=>3];
            default:return ["keterangan" =>"Tidak diketahu","score"=>0];
        }
    }
    protected function tablePointKaki ($value) {
        if($value === null) return ["keterangan" =>"Tidak diketahu","score"=>0];
        switch((int)$value){
            case 0 :return ["keterangan" => "Kaki dalam posisi netral","score"=>1,"index"=>0];
            case 1 :return ["keterangan" => "salah satu kaki menekuk","score"=>2,"index"=>1];
            case 2 :return ["keterangan" => "kaki menekuk hingga sudut 30-60 deg","score"=>1,"index"=>2];
            case 3 :return ["keterangan" => "kaki menekuk hingga sudut >60 deg","score"=>2,"index"=>3];
            default:return ["keterangan" =>"Tidak diketahu","score"=>0];
        }
    }
    protected function tablePointLenganAtas ($value) {
        if($value === null) return ["keterangan" =>"Tidak diketahu","score"=>0];
        switch((int)$value){
            case 0 :return ["keterangan" => "lengan atas dalam posisi netral atau berputar sekitar sudut 0-20 deg","score"=>1,"index"=>0];
            case 1 :return ["keterangan" => "lengan atas berputar sekitar sudut 20-45 deg ke depan dan/atau kebelakang","score"=>2,"index"=>1];
            case 2 :return ["keterangan" => "lengan atas berputar sekitar sudut 45-90 deg","score"=>3,"index"=>2];
            case 3 :return ["keterangan" => "lengan atas berputar hingga sudut >90 deg","score"=>4,"index"=>3];
            default:return ["keterangan" =>"Tidak diketahu","score"=>0];
        }
    }
    protected function tablePointLenganBawah ($value) {
        if($value === null) return ["keterangan" =>"Tidak diketahu","score"=>0];
        switch((int)$value){
            case 0 :return ["keterangan" => "lengan bawah menekuk hingga sudut antara 60-100 deg","score"=>1,"index"=>0];
            case 1 :return ["keterangan" => "lengan bawah menekuk dari sudut 0-60 deg dan atau diatas 100 deg","score"=>2,"index"=>1];
            default:return ["keterangan" =>"Tidak diketahu","score"=>0];
        }
    }
    protected function tablePointPergelanganTangan ($value) {
        if($value === null) return ["keterangan" =>"Tidak diketahu","score"=>0];
        switch((int)$value){
            case 0 :return ["keterangan" => "pergelangan lengan menekuk hingga sudut antara 0-15 deg, baik keatas dan kebawah","score"=>1,"index"=>0];
            case 1 :return ["keterangan" => "pergelangan lengan menekuk >15deg, baik keatas dan kebawah","score"=>2,"index"=>1];
            default:return ["keterangan" =>"Tidak diketahu","score"=>0];
        }
    }
    protected function tablePointAktivitasOtot ($value) {
        if($value === null) return ["keterangan" =>"Tidak diketahu","score"=>0];
        switch((int)$value){
            case 0 :return ["keterangan" => "satu atau lebih bagian tubuh dalam keadaan statis, Misal ditopang lebih dari 1 min","score"=>1,"index"=>0];
            case 1 :return ["keterangan" => "gerakan berulang-ulang, Misal lebih dari 4 min, Tidak termasuk berjalan","score"=>1,"index"=>1];
            case 2 :return ["keterangan" => "postur tubuh tidak stabil selama kerja","score"=>1,"index"=>2];
            default:return ["keterangan" =>"Tidak diketahu","score"=>0];
        }
    }
    protected function tableBeban ($value){
        if($value === null) return ["keterangan" =>"Tidak diketahu","score"=>0];
        switch((int)$value){
            case 0 :return ["keterangan" => "Beban < 5 Kg","score"=>0,"index"=>0];
            case 1 :return ["keterangan" => "Beban 5-10 Kg","score"=>1,"index"=>1];
            case 2 :return ["keterangan" => "Beban > 10 Kg","score"=>2,"index"=>2];
            default:return ["keterangan" =>"Tidak diketahu","score"=>0];
        }
    }

    protected function tablePointPegangan($value){
        if($value === null)  return["keterangan" =>"Tidak diketahu","score"=>0];
        switch((int)$value){
            case 0 :return ["keterangan" => "Pegangan Bagus","score"=>0,"index"=>0];
            case 1 :return ["keterangan" => "Pegangan Sedang","score"=>1,"index"=>1];
            case 2 :return ["keterangan" => "Pegangan Kurang Baik","score"=>2,"index"=>2];
            case 3 :return ["keterangan" => "Pegangan Jelek","score"=>3,"index"=>3];
            default:return ["keterangan" =>"Tidak diketahu","score"=>0];
        }
    }

}