<?php
namespace App\Services;

class RosaFormatter
{
    /**
     * Format utama: terima data mentah dan kembalikan array terstruktur siap simpan.
     *
     * @param array $data
     * @return array
     */
    public static function formatRosaData(array $data): array
    {
        // ambil flag tambahan utk monitor/lebar dsb
        $monitorFlags = [
            'leher_putar'   => (int) ($data['tambah_monitor_leher_putar'] ?? 0),
            'pantulan'      => (int) ($data['tambah_monitor_pantulan'] ?? 0),
            'no_holder'     => (int) ($data['tambah_monitor_no_holder'] ?? 0),
            'terlalu_jauh'  => (int) ($data['tambah_monitor_terlalu_jauh'] ?? 0),
        ];

        $lebarFlags = [
            'kursi_sempit'  => (int) ($data['tambah_kursi_sempit'] ?? 0),
            'tidak_bisa_atur' => (int) ($data['tambah_kursi_tidak_bisa_atur'] ?? 0),
        ];

        $skor_mouse =self::mapMouse($data['skor_mouse'] ?? null);
        $skor_monitor =self::mapMonitor($data['skor_monitor'] ?? null, []);
        $skor_telepon =self::mapTelepon($data['skor_telepon'] ?? null, []);
        $skor_keyboard =self::mapKeyboard($data['skor_keyboard'] ?? null, []);
        $score_sandaran_lengan = self::mapSandaranLengan($data['skor_sandaran_lengan'] ?? null);
        $score_sandaran_punggung = self::mapSandaranPunggung($data['skor_sandaran_punggung'] ?? null);
        $score_tinggi_kursi = self::mapTinggiKursi($data['skor_tinggi_kursi'] ?? null);
        $score_lebar_dudukan = self::mapLebarDudukan($data['skor_lebar_dudukan'] ?? null, []);
        $score_durasi_kerja_kursi = self::mapDurasiKerjaBagianKursi($data['skor_durasi_kerja_kursi'] ?? null);
        $score_durasi_kerja_monitor = self::mapDurasiKerjaMonitor($data['skor_durasi_kerja_monitor'] ?? null);
        $score_durasi_kerja_telepon = self::mapDurasiKerjaTelepon($data['skor_durasi_kerja_telepon'] ?? null);
        $score_durasi_kerja_mouse = self::mapDurasiKerjaMouse($data['skor_durasi_kerja_mouse'] ?? null);
        $score_durasi_kerja_keyboard = self::mapDurasiKerjaKeyboard($data['skor_durasi_kerja_keyboard'] ?? null);

        // susun section A
        $sectionA = [
            'tinggi_kursi' => [
                'skor' => $score_tinggi_kursi,
            ],
            'lebar_dudukan' => [
                'skor' => $score_lebar_dudukan,
            ],
            'sandaran_lengan' => [
                'skor' => $score_sandaran_lengan
            ],
            'sandaran_punggung' => [
                'skor' => $score_sandaran_punggung
            ],
            'skor_durasi_kerja_kursi' => $score_durasi_kerja_kursi,
        ];

        // section B
        $sectionB = [
            'monitor' => [
                'skor' =>$skor_monitor,
            ],
            'telepon' => [
                'skor' =>$skor_telepon
            ],
            'durasi_kerja_monitor' => $score_durasi_kerja_monitor,
            'durasi_kerja_telepon' => $score_durasi_kerja_telepon,
        ];

        // section C
        $sectionC = [
            'mouse' => [
                'skor' => $skor_mouse
            ],
            'keyboard' => [
                'skor' => $skor_keyboard
            ],
            'durasi_kerja_mouse' => $score_durasi_kerja_mouse,
            'durasi_kerja_keyboard' => $score_durasi_kerja_keyboard
        ];
        $penyesuaian = [
            "mouse" => [
                "beda_permukaan" => (int)($data['tambah_mouse_beda_permukaan'] ?? 0),
                "menekuk" => (int)($data['tambah_mouse_menekuk'] ?? 0),
                "ada_palmrest" => (int)($data['tambah_mouse_ada_palmrest'] ?? 0),
            ],
            "monitor" => [
                "leher_putar"   => (int)($data['tambah_monitor_leher_putar'] ?? 0),
                "pantulan"      => (int)($data['tambah_monitor_pantulan'] ?? 0),
                "no_holder"     => (int)($data['tambah_monitor_no_holder'] ?? 0),
                "terlalu_jauh"  => (int)($data['tambah_monitor_terlalu_jauh'] ?? 0),
            ],
            "telepon" => [
                "penopang_leher" => (int)($data['tambah_telepon_penopang_leher'] ?? 0),
                "tangan_tidak_bebas" => (int)($data['tambah_telepon_tangan_tidak_bebas'] ?? 0),
            ],
            "keyboard" => [
                "deviasi"         => (int)($data['tambah_keyboard_deviasi'] ?? 0),
                "terlalu_tinggi"  => (int)($data['tambah_keyboard_terlalu_tinggi'] ?? 0),
                "diatas_kepala"   => (int)($data['tambah_keyboard_diatas_kepala'] ?? 0),
                "tidak_bisa_atur" => (int)($data['tambah_keyboard_tidak_bisa_atur'] ?? 0),
            ],
            "kursi" => [
                "sempit" => (int)($data['tambah_kursi_sempit'] ?? 0),
                "tidak_bisa_atur" => (int)($data['tambah_kursi_tidak_bisa_atur'] ?? 0)
            ],
            "dudukan" => [
                "tidak_bisa_atur" => (int)($data['tambah_dudukan_tidak_bisa_atur'] ?? 0)
            ],
            "sandaran_lengan" =>[
                "keras" => (int)($data['tambah_lengan_keras'] ?? 0),
                "lebar" => (int)($data['tambah_lengan_lebar'] ?? 0),
                "tidak_bisa_atur" => (int)($data['tambah_lengan_tidak_bisa_atur'] ?? 0)
            ],
            "sandaran_punggung" => [
                "meja_tinggi" => (int)($data['tambah_punggung_meja_tinggi'] ?? 0),
                "tidak_bisa_atur" => (int)($data['tambah_punggung_tidak_bisa_atur'] ?? 0)
            ]
        ];

        // bagian ringkasan / nilai numerik yang mungkin juga ingin disimpan
        
        $summary = [
            'skor_mouse' =>$skor_mouse['score'] + $penyesuaian['mouse']['beda_permukaan'] + $penyesuaian['mouse']['menekuk'] + $penyesuaian['mouse']['ada_palmrest'],
            'skor_monitor' => $skor_monitor['score'] + $penyesuaian['monitor']['leher_putar'] + $penyesuaian['monitor']['pantulan'] + $penyesuaian['monitor']['no_holder'] + $penyesuaian['monitor']['terlalu_jauh'],
            'skor_telepon' => $skor_telepon['score'] + $penyesuaian['telepon']['penopang_leher'] + $penyesuaian['telepon']['tangan_tidak_bebas'],
            'skor_keyboard' => $skor_keyboard['score'] + $penyesuaian['keyboard']['deviasi'] + $penyesuaian['keyboard']['terlalu_tinggi'] + $penyesuaian['keyboard']['diatas_kepala'] + $penyesuaian['keyboard']['tidak_bisa_atur'],
            'skor_tinggi_kursi' =>($score_tinggi_kursi['score'] + $penyesuaian['kursi']['sempit'] + $penyesuaian['kursi']['tidak_bisa_atur']),
            'skor_lebar_kursi' => ($score_lebar_dudukan['score'] + $penyesuaian['dudukan']['tidak_bisa_atur']),
            'skor_sandaran_lengan' => ($score_sandaran_lengan['score'] + $penyesuaian['sandaran_lengan']['keras'] + $penyesuaian['sandaran_lengan']['lebar'] +$penyesuaian['sandaran_lengan']['tidak_bisa_atur'] ),
            'skor_sandaran_punggung' => ($score_sandaran_punggung['score'] + $penyesuaian['sandaran_punggung']['meja_tinggi'] + $penyesuaian['sandaran_punggung']['tidak_bisa_atur'] ),
            'total_skor_monitor' => $skor_monitor['score'] + $penyesuaian['monitor']['leher_putar'] + $penyesuaian['monitor']['pantulan'] + $penyesuaian['monitor']['no_holder'] + $penyesuaian['monitor']['terlalu_jauh'],
            'total_skor_telepon' => $skor_telepon['score'] + $penyesuaian['telepon']['penopang_leher'] + $penyesuaian['telepon']['tangan_tidak_bebas'],
            'total_skor_keyboard' => $skor_keyboard['score'] + $penyesuaian['keyboard']['deviasi'] + $penyesuaian['keyboard']['terlalu_tinggi'] + $penyesuaian['keyboard']['diatas_kepala'] + $penyesuaian['keyboard']['tidak_bisa_atur'],
            'total_skor_mouse' => $skor_mouse['score'] + $penyesuaian['mouse']['beda_permukaan'] + $penyesuaian['mouse']['menekuk'] + $penyesuaian['mouse']['ada_palmrest'],
            'final_skor_rosa' => isset($data['final_skor_rosa']) ? (int)$data['final_skor_rosa'] : null,
            'kategori' => $data['kategori'] ?? null,
            'tindakan' => $data['tindakan'] ?? null,
            'kesimpulan' => $data['kesimpulan'] ?? null,
            'total_section_a' => isset($data['total_section_a']) ? (int)$data['total_section_a'] : null,
            'total_section_b' => isset($data['total_section_b']) ? (int)$data['total_section_b'] : null,
            'total_section_c' => isset($data['total_section_c']) ? (int)$data['total_section_c'] : null,
            'total_section_d' => isset($data['total_section_d']) ? (int)$data['total_section_d'] : null,
            'nilai_table_a' => isset($data['nilai_table_a']) ? (int)$data['nilai_table_a'] : null,
            'skor_total_sandaran_lengan_dan_punggung' => ($score_sandaran_lengan['score'] + $penyesuaian['sandaran_lengan']['keras'] + $penyesuaian['sandaran_lengan']['lebar'] +$penyesuaian['sandaran_lengan']['tidak_bisa_atur'] ) + ($score_sandaran_punggung['score'] + $penyesuaian['sandaran_punggung']['meja_tinggi'] + $penyesuaian['sandaran_punggung']['tidak_bisa_atur'] ),
            'skor_total_tinggi_kursi_dan_lebar_dudukan' => ($score_tinggi_kursi['score'] + $penyesuaian['kursi']['sempit'] + $penyesuaian['kursi']['tidak_bisa_atur']) + ($score_lebar_dudukan['score'] + $penyesuaian['dudukan']['tidak_bisa_atur']),
            'skor_durasi_kerja_bagian_kursi' => $score_durasi_kerja_kursi['score'],
            'skor_durasi_kerja_monitor' => $score_durasi_kerja_monitor['score'],
            'skor_durasi_kerja_telepon' => $score_durasi_kerja_telepon['score'],
            'skor_durasi_kerja_mouse' => $score_durasi_kerja_mouse['score'],
            'skor_durasi_kerja_keyboard' => $score_durasi_kerja_keyboard['score']
        ];
        // $summary = [
        //     'skor_mouse' => $skor_mouse['score'] ?? null,
        //     'skor_monitor' => $skor_monitor['score'] ?? null,
        //     'skor_telepon' => $skor_telepon['score'] ?? null,
        //     'skor_keyboard' => $skor_keyboard['score'] ?? null,
        //     'skor_tinggi_kursi' =>$score_tinggi_kursi['score'] ?? null,
        //     'skor_lebar_kursi' => $score_lebar_dudukan['score'] ?? null,
        //     'skor_sandaran_lengan' => $score_sandaran_lengan['score'] ?? null,
        //     'skor_sandaran_punggung' => $score_sandaran_punggung['score'] ?? null,
        //     'skor_monitor' => $skor_monitor['score'] ?? null,
        //     'skor_telepon' => $skor_telepon['score'] ?? null,
        //     'skor_keyboard' => $skor_keyboard['score'] ?? null,
        //     'total_skor_monitor' => $skor_monitor['score'] + $penyesuaian['monitor']['leher_putar'] + $penyesuaian['monitor']['pantulan'] + $penyesuaian['monitor']['no_holder'] + $penyesuaian['monitor']['terlalu_jauh'],
        //     'total_skor_telepon' => $skor_telepon['score'] + $penyesuaian['telepon']['penopang_leher'] + $penyesuaian['telepon']['tangan_tidak_bebas'],
        //     'total_skor_keyboard' => $skor_keyboard['score'] + $penyesuaian['keyboard']['deviasi'] + $penyesuaian['keyboard']['terlalu_tinggi'] + $penyesuaian['keyboard']['diatas_kepala'] + $penyesuaian['keyboard']['tidak_bisa_atur'],
        //     'total_skor_mouse' => $skor_mouse['score'] + $penyesuaian['mouse']['beda_permukaan'] + $penyesuaian['mouse']['menekuk'] + $penyesuaian['mouse']['ada_palmrest'],
        //     'final_skor_rosa' => isset($data['final_skor_rosa']) ? (int)$data['final_skor_rosa'] : null,
        //     'kategori' => $data['kategori'] ?? null,
        //     'tindakan' => $data['tindakan'] ?? null,
        //     'kesimpulan' => $data['kesimpulan'] ?? null,
        //     'total_section_a' => isset($data['total_section_a']) ? (int)$data['total_section_a'] : null,
        //     'total_section_b' => isset($data['total_section_b']) ? (int)$data['total_section_b'] : null,
        //     'total_section_c' => isset($data['total_section_c']) ? (int)$data['total_section_c'] : null,
        //     'total_section_d' => isset($data['total_section_d']) ? (int)$data['total_section_d'] : null,
        //     'nilai_table_a' => isset($data['nilai_table_a']) ? (int)$data['nilai_table_a'] : null,
        //     'skor_total_sandaran_lengan_dan_punggung' => ($score_sandaran_lengan['score'] + $penyesuaian['sandaran_lengan']['keras'] + $penyesuaian['sandaran_lengan']['lebar'] +$penyesuaian['sandaran_lengan']['tidak_bisa_atur'] ) + ($score_sandaran_punggung['score'] + $penyesuaian['sandaran_punggung']['meja_tinggi'] + $penyesuaian['sandaran_punggung']['tidak_bisa_atur'] ),
        //     'skor_total_tinggi_kursi_dan_lebar_dudukan' => ($score_tinggi_kursi['score'] + $penyesuaian['kursi']['sempit'] + $penyesuaian['kursi']['tidak_bisa_atur']) + ($score_lebar_dudukan['score'] + $penyesuaian['dudukan']['tidak_bisa_atur']),
        //     'skor_durasi_kerja_bagian_kursi' => $score_durasi_kerja_kursi['score'],
        //     'skor_durasi_kerja_monitor' => $score_durasi_kerja_monitor['score'],
        //     'skor_durasi_kerja_telepon' => $score_durasi_kerja_telepon['score'],
        //     'skor_durasi_kerja_mouse' => $score_durasi_kerja_mouse['score'],
        //     'skor_durasi_kerja_keyboard' => $score_durasi_kerja_keyboard['score']
        // ];
        // gabungkan
        return array_merge(
            [
                'section_A' => $sectionA,
                'section_B' => $sectionB,
                'section_C' => $sectionC,
                'penyesuaian' => $penyesuaian
            ],
            $summary
        );
    }

    /**
     * Format pengukuran ROSA selaras mobile / apps-fdl (skor string "poin-keterangan", tanpa penyesuaian terpisah).
     */
    public static function formatRosaLegacyData(array $data): array
    {
        $idx = static function (string $key, int $default = 0) use ($data): int {
            return isset($data[$key]) ? (int) $data[$key] : $default;
        };

        $durasiStrings = [
            0 => '-1-<30 menit atau < 1 jam',
            1 => '0-1 jam - 4 jam',
            2 => '1->4 jam',
        ];
        $durasi = static function (string $key) use ($data, $durasiStrings, $idx): string {
            $i = $idx($key, 1);
            return $durasiStrings[$i] ?? $durasiStrings[1];
        };

        $pick = static function (array $options, int $index): string {
            return $options[$index] ?? $options[0];
        };

        $tinggiKursiOpts = [
            '1-Lutut membentuk 90ᵒ',
            '2-Kursi terlalu rendah, Lutut membentuk sudut < 90ᵒ',
            '2-Kursi terlalu tinggi, Lutut membentuk sudut > 90ᵒ',
            '3-Kaki tidak menapak ke lantai',
        ];
        $lebarOpts = [
            '1-Jarak antara lutut dan ujung kursi sekitar 7,62 cm',
            '2-Dudukan kursi terlalu panjang ke depan',
            '2-Dudukan kursi terlalu sempit',
        ];
        $lenganOpts = [
            '1-Siku tersangga dengan baik, rileks, dan sejajar dengan bahu',
            '2-Siku terlalu tinggi, bahu terangkat/terlalu turun atau tidak adanya penyangga lengan',
        ];
        $punggungOpts = [
            '1-Sandaran punggung menyangga keseluruhan punggung dan tulang belakang dengan baik, sandaran punggung berkisar antara 95ᵒ dan 110ᵒ',
            '2-Tidak terdapat sandaran tulang belakang, atau sandaran hanya menyangga sebagian punggung',
            '2-Sandaran terlalu ke belakang(>110°) atau terlalu ke depan (<95°)',
            '2-Tidak ada sandaran punggung sama sekali',
        ];
        $monitorOpts = [
            '1-Jarak antara pekerja dengan monitor sepanjang lengan (40 – 75 cm), eye level',
            '2-Monitor terlalu rendah, membentuk sudut < 30ᵒ',
            '3-Monitor terlalu tinggi membentuk sudut >30ᵒ (Leher terpaksa melihat ke atas)',
        ];
        $teleponOpts = [
            '1-Menelepon dengan menggunakan headset atau dengan satu tangan',
            '2-Jarak telepon dengan pekerja terlalu jauh (> 30 cm)',
        ];
        $mouseOpts = [
            '1-Mouse sejajar bahu',
            '2-Letak mouse agak jauh',
        ];
        $keyboardOpts = [
            '1-Pergelangan lurus, bahu rileks',
            '2-Pergelangan terangkat <15ᵒ dan sudut keyboard terlalu miring',
        ];

        $sectionA = [
            'tinggi_kursi' => static::legacyWithTambahan(
                ['skor' => $pick($tinggiKursiOpts, $idx('skor_tinggi_kursi'))],
                $data,
                [
                    ['tambah_kursi_sempit', '1-Tempat duduk sempit dan tidak leluasa, sehingga memaksa kaki untuk menekuk'],
                    ['tambah_kursi_tidak_bisa_atur', '1-Kursi tidak dapat diatur untuk menyesuaikan tinggi kaki'],
                ]
            ),
            'lebar_dudukan' => static::legacyWithTambahan(
                ['skor' => $pick($lebarOpts, $idx('skor_lebar_dudukan'))],
                $data,
                [
                    ['tambah_dudukan_tidak_bisa_atur', '1-Kursi tidak dapat di-adjust (diatur) untuk menyesuaikan dudukan kursi'],
                ]
            ),
            'sandaran_lengan' => static::legacyWithTambahan(
                ['skor' => $pick($lenganOpts, $idx('skor_sandaran_lengan'))],
                $data,
                [
                    ['tambah_lengan_keras', '1-Penyangga terlalu keras atau mudah rusak'],
                    ['tambah_lengan_lebar', '1-Penyangga lengan terlalu lebar'],
                    ['tambah_lengan_tidak_bisa_atur', '1-Sandaran tangan tidak dapat di-adjust (diatur) untuk menyesuaikan tinggi kaki'],
                ]
            ),
            'sandaran_punggung' => static::legacyWithTambahan(
                ['skor' => $pick($punggungOpts, $idx('skor_sandaran_punggung'))],
                $data,
                [
                    ['tambah_punggung_meja_tinggi', '1-Permukaan meja terlalu tinggi (bahu terangkat)'],
                    ['tambah_punggung_tidak_bisa_atur', '1-Sandaran punggung tidak dapat diatur'],
                ]
            ),
            'durasi_kerja_bagian_kursi' => $durasi('skor_durasi_kerja_kursi'),
        ];

        $sectionB = [
            'monitor' => static::legacyWithTambahan(
                ['skor' => $pick($monitorOpts, $idx('skor_monitor'))],
                $data,
                [
                    ['tambah_monitor_leher_putar', '1-Leher berputar lebih dari 30ᵒ'],
                    ['tambah_monitor_no_holder', '1-Tidak memiliki dokumen holder'],
                    ['tambah_monitor_pantulan', '1-Terdapat pantulan cahaya ke layar monitor'],
                    ['tambah_monitor_terlalu_jauh', '1-Monitor terlalu Jauh'],
                ]
            ),
            'telepon' => static::legacyWithTambahan(
                ['skor' => $pick($teleponOpts, $idx('skor_telepon'))],
                $data,
                [
                    ['tambah_telepon_penopang_leher', '2-Menelepon dengan penopang leher dan bahu'],
                    ['tambah_telepon_tangan_tidak_bebas', '1-Tangan tidak bebas menggenggam telepon'],
                ]
            ),
            'durasi_kerja_monitor' => $durasi('skor_durasi_kerja_monitor'),
            'durasi_kerja_telepon' => $durasi('skor_durasi_kerja_telepon'),
        ];

        $sectionC = [
            'mouse' => static::legacyWithTambahan(
                ['skor' => $pick($mouseOpts, $idx('skor_mouse'))],
                $data,
                [
                    ['tambah_mouse_menekuk', '1-Genggaman mouse menekuk'],
                    ['tambah_mouse_ada_palmrest', '1-Terdapat palmrest (sandaran) mouse'],
                    ['tambah_mouse_beda_permukaan', '2-Letak mouse dengan keyboard tidak dalam satu permukaan'],
                ]
            ),
            'keyboard' => static::legacyWithTambahan(
                ['skor' => $pick($keyboardOpts, $idx('skor_keyboard'))],
                $data,
                [
                    ['tambah_keyboard_deviasi', '1-Tangan berdeviasi (miring)'],
                    ['tambah_keyboard_terlalu_tinggi', '1-Keyboard terlalu tinggi, bahu terangkat'],
                    ['tambah_keyboard_diatas_kepala', '1-Posisi Keyboard di atas melebihi kepala (terlalu tinggi)'],
                    ['tambah_keyboard_tidak_bisa_atur', '1-Posisi Keyboard tidak dapat diatur'],
                ]
            ),
            'durasi_kerja_mouse' => $durasi('skor_durasi_kerja_mouse'),
            'durasi_kerja_keyboard' => $durasi('skor_durasi_kerja_keyboard'),
        ];

        return static::computeLegacyRosaSummary($sectionA, $sectionB, $sectionC);
    }

    /** @param array<int, array{0: string, 1: string}> $tambahanDefs [flagKey, legacy string] */
    private static function legacyWithTambahan(array $node, array $data, array $tambahanDefs): array
    {
        $n = 0;
        foreach ($tambahanDefs as [$flagKey, $legacyStr]) {
            if ((int) ($data[$flagKey] ?? 0) !== 1) {
                continue;
            }
            $n++;
            $node[$n === 1 ? 'tambahan' : 'tambahan_' . $n] = $legacyStr;
        }
        return $node;
    }

    private static function extractLegacyScore($value): int
    {
        if (is_string($value) && preg_match('/^(-?\d+)-/', $value, $matches)) {
            return (int) $matches[1];
        }
        return (int) $value;
    }

    private static function sumLegacySectionScores(array $sections): array
    {
        $hasil = [];
        foreach ($sections as $sectionKey => $bagian) {
            foreach ($bagian as $key => $item) {
                $hasil[$sectionKey][$key] = 0;
                if (is_array($item)) {
                    foreach ($item as $subKey => $val) {
                        $hasil[$sectionKey][$key] += static::extractLegacyScore($val);
                    }
                } else {
                    $hasil[$sectionKey][$key] += static::extractLegacyScore($item);
                }
            }
        }
        return $hasil;
    }

    private static function computeLegacyRosaSummary(array $sectionA, array $sectionB, array $sectionC): array
    {
        $tableSectionA = [
            2 => [2 => 2, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8],
            3 => [2 => 2, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8],
            4 => [2 => 3, 3 => 3, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8],
            5 => [2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8],
            6 => [2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
            7 => [2 => 6, 3 => 6, 4 => 6, 5 => 7, 6 => 8, 7 => 8, 8 => 8, 9 => 9],
            8 => [2 => 7, 3 => 7, 4 => 7, 5 => 8, 6 => 8, 7 => 9, 8 => 9, 9 => 9],
        ];
        $tableSectionB = [
            0 => [0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6],
            1 => [0 => 1, 1 => 1, 2 => 2, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6],
            2 => [0 => 1, 1 => 2, 2 => 2, 3 => 3, 4 => 3, 5 => 4, 6 => 6, 7 => 7],
            3 => [0 => 2, 1 => 2, 2 => 3, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 8],
            4 => [0 => 3, 1 => 3, 2 => 4, 3 => 4, 4 => 5, 5 => 6, 6 => 7, 7 => 8],
            5 => [0 => 4, 1 => 4, 2 => 5, 3 => 5, 4 => 6, 5 => 7, 6 => 8, 7 => 9],
            6 => [0 => 5, 1 => 5, 2 => 6, 3 => 7, 4 => 8, 5 => 8, 6 => 9, 7 => 9],
        ];
        $tableSectionC = [
            0 => [0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6],
            1 => [0 => 1, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7],
            2 => [0 => 1, 1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7],
            3 => [0 => 2, 1 => 3, 2 => 3, 3 => 3, 4 => 5, 5 => 6, 6 => 7, 7 => 8],
            4 => [0 => 3, 1 => 4, 2 => 4, 3 => 5, 4 => 5, 5 => 6, 6 => 7, 7 => 8],
            5 => [0 => 4, 1 => 5, 2 => 5, 3 => 6, 4 => 6, 5 => 7, 6 => 8, 7 => 9],
            6 => [0 => 5, 1 => 6, 2 => 6, 3 => 7, 4 => 7, 5 => 8, 6 => 8, 7 => 9],
            7 => [0 => 6, 1 => 7, 2 => 7, 3 => 8, 4 => 8, 5 => 9, 6 => 9, 7 => 9],
        ];
        $tableSectionD = [
            1 => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
            2 => [1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
            3 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
            4 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
            5 => [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
            6 => [1 => 6, 2 => 6, 3 => 6, 4 => 6, 5 => 6, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
            7 => [1 => 7, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 7, 8 => 8, 9 => 9],
            8 => [1 => 8, 2 => 8, 3 => 8, 4 => 8, 5 => 8, 6 => 8, 7 => 8, 8 => 8, 9 => 9],
            9 => [1 => 9, 2 => 9, 3 => 9, 4 => 9, 5 => 9, 6 => 9, 7 => 9, 8 => 9, 9 => 9],
        ];
        $skorRosa = [
            1 => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
            2 => [1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
            3 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
            4 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
            5 => [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
            6 => [1 => 6, 2 => 6, 3 => 6, 4 => 6, 5 => 6, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
            7 => [1 => 7, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
            8 => [1 => 8, 2 => 8, 3 => 8, 4 => 8, 5 => 8, 6 => 8, 7 => 8, 8 => 8, 9 => 9, 10 => 10],
            9 => [1 => 9, 2 => 9, 3 => 9, 4 => 9, 5 => 9, 6 => 9, 7 => 9, 8 => 9, 9 => 9, 10 => 10],
            10 => [1 => 10, 2 => 10, 3 => 10, 4 => 10, 5 => 10, 6 => 10, 7 => 10, 8 => 10, 9 => 10, 10 => 10],
        ];

        $parsed = static::sumLegacySectionScores([
            'section_A' => $sectionA,
            'section_B' => $sectionB,
            'section_C' => $sectionC,
        ]);

        $tinggi_kursi = $parsed['section_A']['tinggi_kursi'] ?? 0;
        $lebar_dudukan = $parsed['section_A']['lebar_dudukan'] ?? 0;
        $durasi_kursi = $parsed['section_A']['durasi_kerja_bagian_kursi'] ?? 0;
        $sandaran_lengan = $parsed['section_A']['sandaran_lengan'] ?? 0;
        $sandaran_punggung = $parsed['section_A']['sandaran_punggung'] ?? 0;

        $armRestAndBackSupport = $sandaran_lengan + $sandaran_punggung;
        $seatPanHeightOrdDepth = $tinggi_kursi + $lebar_dudukan;
        $nilai_section_A = $tableSectionA[$seatPanHeightOrdDepth][$armRestAndBackSupport] ?? 0;
        $totalSkorA = $nilai_section_A + $durasi_kursi;

        $monitor = $parsed['section_B']['monitor'] ?? 0;
        $durasi_monitor = $parsed['section_B']['durasi_kerja_monitor'] ?? 0;
        $telepon = $parsed['section_B']['telepon'] ?? 0;
        $durasi_telepon = $parsed['section_B']['durasi_kerja_telepon'] ?? 0;
        $totalMonitor = $monitor + $durasi_monitor;
        $totalTelepon = $telepon + $durasi_telepon;
        $totalSkorB = $tableSectionB[$totalTelepon][$totalMonitor] ?? 0;

        $keyboard = $parsed['section_C']['keyboard'] ?? 0;
        $mouse = $parsed['section_C']['mouse'] ?? 0;
        $durasi_keyboard = $parsed['section_C']['durasi_kerja_keyboard'] ?? 0;
        $durasi_mouse = $parsed['section_C']['durasi_kerja_mouse'] ?? 0;
        $totalKeyboard = $keyboard + $durasi_keyboard;
        $totalMouse = $mouse + $durasi_mouse;
        $totalSkorC = $tableSectionC[$totalMouse][$totalKeyboard] ?? 0;

        $totalSkorD = $tableSectionD[$totalSkorB][$totalSkorC] ?? 0;
        $finalRosa = $skorRosa[$totalSkorA][$totalSkorD] ?? 0;

        return [
            'section_A' => $sectionA,
            'section_B' => $sectionB,
            'section_C' => $sectionC,
            'skor_mouse' => $mouse,
            'skor_monitor' => $monitor,
            'skor_telepon' => $telepon,
            'nilai_table_a' => $nilai_section_A,
            'skor_keyboard' => $keyboard,
            'final_skor_rosa' => $finalRosa,
            'total_section_a' => $totalSkorA,
            'total_section_b' => $totalSkorB,
            'total_section_c' => $totalSkorC,
            'total_section_d' => $totalSkorD,
            'skor_lebar_kursi' => $lebar_dudukan,
            'total_skor_mouse' => $totalMouse,
            'skor_tinggi_kursi' => $tinggi_kursi,
            'total_skor_monitor' => $totalMonitor,
            'total_skor_telepon' => $totalTelepon,
            'total_skor_keyboard' => $totalKeyboard,
            'skor_sandaran_lengan' => $sandaran_lengan,
            'skor_sandaran_punggung' => $sandaran_punggung,
            'skor_durasi_kerja_mouse' => $durasi_mouse,
            'skor_durasi_kerja_monitor' => $durasi_monitor,
            'skor_durasi_kerja_telepon' => $durasi_telepon,
            'skor_durasi_kerja_keyboard' => $durasi_keyboard,
            'skor_durasi_kerja_bagian_kursi' => $durasi_kursi,
            'skor_total_sandaran_lengan_dan_punggung' => $armRestAndBackSupport,
            'skor_total_tinggi_kursi_dan_lebar_dudukan' => $seatPanHeightOrdDepth,
        ];
    }

    /**
     * Map lebar dudukan (contoh mapping sesuai data yang Anda berikan).
     *
     * @param mixed $value
     * @param array $flags
     * @return array
     */
    protected static function mapLebarDudukan($value, array $flags = []): array
    {
        $v = (int) $value;
        switch ($v) {
            case 0: return ['keterangan'=>'Jarak antara lutut dan ujung kursi sekitar 7,62 cm','score'=>1,'index'=>0];
            case 1: return ['keterangan'=>'Dudukan kursi terlalu panjang ke depan','score'=>2,'index'=>1];
            case 2: return ['keterangan'=>'Dudukan kursi terlalu sempit','score'=>2,'index'=>2];
            default: return 'Tidak diketahui';
        }
    }

    /**
     * Map monitor (contoh mapping & gabungkan flag tambahan seperti pantulan, terlalu jauh, dll).
     *
     * @param mixed $value
     * @param array $flags
     * @return array
     */
    protected static function mapMonitor($value, array $flags = []): array
    {
        $v = (int) $value;
        switch ($v) {
            case 0: return ['keterangan'=>'Jarak antara pekerja dengan monitor sepanjang lengan (40 – 75 cm), eye level','score'=>1,'index'=>0];
            case 1: return ['keterangan'=>'Monitor sedikit terlalu jauh atau posisi sedikit tidak pada eye level','score'=>2,'index'=>1];
            case 2: return ['keterangan'=>'Monitor jauh/terlalu dekat atau posisi eye level sangat tidak sesuai','score'=>3,'index'=>2];
            default: return 'Tidak diketahui';
        }
    }

    // ----------------------
    // Contoh mapping lain
    // ----------------------
    protected static function mapTinggiKursi($value)
    {
        $v = (int) $value;
        switch ($v) {
            case 0: return ['keterangan'=>'Lutut membentuk 90ᵒ','score'=>1,'index'=>0];
            case 1: return ['keterangan'=>'Kursi terlalu rendah, Lutut membentuk sudut < 90ᵒ','score'=>2,'index'=>1];
            case 2: return ['keterangan'=>'Kursi terlalu tinggi, Lutut membentuk sudut > 90ᵒ','score'=>2,'index'=>2];
            case 3: return ['keterangan'=>'Kaki tidak menapak ke lantai','score'=>3,'index'=>3];
            default: return 'Tidak diketahui';
        }
    }

    protected static function mapSandaranLengan($value, array $flags = []): array
    {
        $v = (int) $value;
        switch($v){
            case 0 : return ['keterangan'=>'Siku tersangga dengan baik, rileks, dan sejajar dengan bahu','score'=>1,'index'=>0];
            case 1 : return ['keterangan'=>'Siku terlalu tinggi, bahu terangkat/terlalu turun atau tidak adanya penyangga lengan','score'=>2,'index'=>1];
            default: return 'Tidak diketahui';
        }
        // $desc = ($value === null) ? 'Tidak diketahui' : ($value . '-Deskripsi dasar sandaran lengan');
        // $extras = [];
        // if (!empty($flags['lengan_keras'])) $extras[] = 'lengan keras';
        // if (!empty($flags['lengan_lebar'])) $extras[] = 'lengan lebar';
        // if (!empty($flags['tidak_bisa_atur'])) $extras[] = 'tidak bisa diatur';
        // if ($extras) $desc .= ' - Tambahan: ' . implode(', ', $extras);
        // return $desc;
    }

    protected static function mapSandaranPunggung($value, array $flags = []): array
    {
        $v =(int) $value;
        switch($v){
            case 0 : return ['keterangan'=>'Sandaran punggung menyangga keseluruhan punggung dan tulang belakang dengan baik, sandaran punggung berkisar antara 95ᵒ dan 110ᵒ','score'=>1,'index'=>0];
            case 1 : return ['keterangan'=>'Tidak terdapat sandaran tulang belakang, atau sandaran hanya menyangga sebagian punggung','score'=>2,'index'=>1];
            case 2 : return ['keterangan'=>'Sandaran terlalu ke belakang(>110°) atau terlalu ke depan (<95°)','score'=>2,'index'=>2];
            case 3 : return ['keterangan'=>'Tidak ada sandaran punggung sama sekali','score'=>2,'index'=>3];
            default: return 'Tidak diketahui';
        }
        // $desc = ($value === null) ? 'Tidak diketahui' : ($value . '-Deskripsi sandaran punggung');
        // $extras = [];
        // if (!empty($flags['meja_tinggi'])) $extras[] = 'meja terlalu tinggi';
        // if (!empty($flags['tidak_bisa_atur'])) $extras[] = 'tidak bisa diatur';
        // if ($extras) $desc .= ' - Tambahan: ' . implode(', ', $extras);
        // return $desc;
    }

    protected static function mapDurasiKerjaBagianKursi($value): array
    {
        // Contoh konversi: 1 -> "1->4 jam"
        if ($value === null) return ['keterangan'=>'Tidak diketahui','score'=>0];
        switch ((int)$value) {
            case 0: return ['keterangan'=>'< 1 jam','score'=>-1,'index'=>0];
            case 1: return ['keterangan'=>'1 - 4 jam','score'=>0,'index'=>1];
            case 2: return ['keterangan'=>'> 4 jam','score'=>1,'index'=>2];
            default: return ['keterangan'=>'Tidak diketahui','score'=>0];
        }
    }

    protected static function mapTelepon($value, array $flags = []): array
    {
        $v = (int)$value;
        switch($v){
            case 0: return ['keterangan'=>'Menelepon dengan menggunakan headset atau dengan satu tangan','score'=>1,'index'=>0];
            case 1: return ['keterangan'=>'Jarak telepon dengan pekerja terlalu jauh (> 30 cm)','score'=>2,'index'=>1];
            default: return ['keterangan'=>'Tidak diketahui'];
        }
        // $desc = ($v === 1) ? '1-Menelepon dengan menggunakan headset atau dengan satu tangan' : 'Tidak diketahui';
        // $extras = [];
        // if (!empty($flags['penopang_leher'])) $extras[] = 'penopang leher';
        // if (!empty($flags['tangan_tidak_bebas'])) $extras[] = 'tangan tidak bebas';
        // if ($extras) $desc .= ' - Tambahan: ' . implode(', ', $extras);
        // return $desc;
    }

    protected static function mapMouse($value, array $flags = []): array
    {
        $v = (int)$value;
        switch($v){
            case 0: return ['keterangan'=>'Mouse sejajar bahu','score'=>1,'index'=>0];
            case 1: return ['keterangan'=>'Letak mouse agak jauh','score'=>2,'index'=>1];
            default: return ['keterangan'=>'Tidak diketahui'];
        }
        // $desc = ($v === 1) ? '1-Mouse sejajar bahu' : 'Tidak diketahui';
        // $extras = [];
        // if (!empty($flags['beda_permukaan'])) $extras[] = 'beda permukaan';
        // if (!empty($flags['menekuk'])) $extras[] = 'menekuk';
        // if (!empty($flags['ada_palmrest'])) $extras[] = 'ada palmrest';
        // if ($extras) $desc .= ' - Tambahan: ' . implode(', ', $extras);
        // return $desc;
    }

    protected static function mapKeyboard($value, array $flags = []): array
    {
        $v = (int)$value;
        switch($v){
            case 0: return ['keterangan'=>'Pergelangan lurus, bahu rileks','score'=>1,'index'=>0];
            case 1: return ['keterangan'=>'Pergelangan terangkat <15ᵒ dan sudut keyboard terlalu miring','score'=>2,'index'=>1];
            default: return ['keterangan'=>'Tidak diketahui'];
        }
    }

    protected static function mapDurasiKerjaMonitor($value)
    {
        if ($value === null) return ['keterangan'=>'Tidak diketahui','score'=>0];
        switch ((int)$value) {
            case 0: return ['keterangan'=>'< 1 jam','score'=>-1,'index'=>0];
            case 1: return ['keterangan'=>'1 - 4 jam','score'=>0,'index'=>1];
            case 2: return ['keterangan'=>'> 4 jam','score'=>1,'index'=>2];
            default: return ['keterangan'=>'Tidak diketahui'];
        }
    }

    protected static function mapDurasiKerjaTelepon($value)
    {
        return self::mapDurasiKerjaMonitor($value);
    }

    protected static function mapDurasiKerjaMouse($value)
    {
        return self::mapDurasiKerjaMonitor($value);
    }

    protected static function mapDurasiKerjaKeyboard($value)
    {
        return self::mapDurasiKerjaMonitor($value);
    }
}
