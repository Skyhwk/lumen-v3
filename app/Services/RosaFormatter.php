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

        // susun section A
        $sectionA = [
            'tinggi_kursi' => [
                'skor' => self::mapTinggiKursi($data['skor_tinggi_kursi'] ?? null),
            ],
            'lebar_dudukan' => [
                'skor' => self::mapLebarDudukan($data['skor_lebar_dudukan'] ?? null, $lebarFlags),
            ],
            'sandaran_lengan' => [
                'skor' => self::mapSandaranLengan($data['skor_sandaran_lengan'] ?? null,
                    [
                        'lengan_keras' => (int) ($data['tambah_lengan_keras'] ?? 0),
                        'lengan_lebar' => (int) ($data['tambah_lengan_lebar'] ?? 0),
                        'tidak_bisa_atur' => (int) ($data['tambah_lengan_tidak_bisa_atur'] ?? 0),
                    ]
                ),
            ],
            'sandaran_punggung' => [
                'skor' => self::mapSandaranPunggung($data['skor_sandaran_punggung'] ?? null,
                    [
                        'meja_tinggi' => (int) ($data['tambah_punggung_meja_tinggi'] ?? 0),
                        'tidak_bisa_atur' => (int) ($data['tambah_punggung_tidak_bisa_atur'] ?? 0),
                    ]
                ),
            ],
            'durasi_kerja_bagian_kursi' => self::mapDurasiKerjaBagianKursi($data['durasi_kerja_kursi'] ?? null),
        ];

        // section B
        $sectionB = [
            'monitor' => [
                'skor' => self::mapMonitor($data['skor_monitor'] ?? null, $monitorFlags),
            ],
            'telepon' => [
                'skor' => self::mapTelepon($data['skor_telepon'] ?? null,
                    [
                        'penopang_leher' => (int) ($data['tambah_telepon_penopang_leher'] ?? 0),
                        'tangan_tidak_bebas' => (int) ($data['tambah_telepon_tangan_tidak_bebas'] ?? 0),
                    ]
                ),
            ],
            'durasi_kerja_monitor' => self::mapDurasiKerjaMonitor($data['skor_durasi_kerja_monitor'] ?? null),
            'durasi_kerja_telepon' => self::mapDurasiKerjaTelepon($data['skor_durasi_kerja_telepon'] ?? null),
        ];

        // section C
        $sectionC = [
            'mouse' => [
                'skor' => self::mapMouse($data['skor_mouse'] ?? null,
                    [
                        'beda_permukaan' => (int) ($data['tambah_mouse_beda_permukaan'] ?? 0),
                        'menekuk' => (int) ($data['tambah_mouse_menekuk'] ?? 0),
                        'ada_palmrest' => (int) ($data['tambah_mouse_ada_palmrest'] ?? 0),
                    ]
                ),
            ],
            'keyboard' => [
                'skor' => self::mapKeyboard($data['skor_keyboard'] ?? null,
                    [
                        'deviasi' => (int) ($data['tambah_keyboard_deviasi'] ?? 0),
                        'terlalu_tinggi' => (int) ($data['tambah_keyboard_terlalu_tinggi'] ?? 0),
                        'diatas_kepala' => (int) ($data['tambah_keyboard_diatas_kepala'] ?? 0),
                        'tidak_bisa_atur' => (int) ($data['tambah_keyboard_tidak_bisa_atur'] ?? 0),
                    ]
                ),
            ],
            'durasi_kerja_mouse' => self::mapDurasiKerjaMouse($data['skor_durasi_kerja_mouse'] ?? null),
            'durasi_kerja_keyboard' => self::mapDurasiKerjaKeyboard($data['skor_durasi_kerja_keyboard'] ?? null),
        ];

        // bagian ringkasan / nilai numerik yang mungkin juga ingin disimpan
        $summary = [
            'skor_mouse' => isset($data['skor_mouse']) ? (int)$data['skor_mouse'] : null,
            'skor_monitor' => isset($data['skor_monitor']) ? (int)$data['skor_monitor'] : null,
            'skor_telepon' => isset($data['skor_telepon']) ? (int)$data['skor_telepon'] : null,
            'skor_keyboard' => isset($data['skor_keyboard']) ? (int)$data['skor_keyboard'] : null,
            'final_skor_rosa' => isset($data['final_skor_rosa']) ? (int)$data['final_skor_rosa'] : null,
            'kategori' => $data['kategori'] ?? null,
            'tindakan' => $data['tindakan'] ?? null,
            'kesimpulan' => $data['kesimpulan'] ?? null,
            'total_section_a' => isset($data['total_section_a']) ? (int)$data['total_section_a'] : null,
            'total_section_b' => isset($data['total_section_b']) ? (int)$data['total_section_b'] : null,
            'total_section_c' => isset($data['total_section_c']) ? (int)$data['total_section_c'] : null,
            'total_section_d' => isset($data['total_section_d']) ? (int)$data['total_section_d'] : null,
        ];
        
        // gabungkan
        return array_merge(
            [
                'section_A' => $sectionA,
                'section_B' => $sectionB,
                'section_C' => $sectionC,
            ],
            $summary
        );
    }

    /**
     * Map lebar dudukan (contoh mapping sesuai data yang Anda berikan).
     *
     * @param mixed $value
     * @param array $flags
     * @return string
     */
    protected static function mapLebarDudukan($value, array $flags = []): string
    {
        $v = (int) $value;
        $desc = '';

        switch ($v) {
            case 1:
                $desc = '1-Jarak antara lutut dan ujung kursi sekitar 7,62 cm';
                break;
            case 2:
                $desc = '2-Jarak terlalu sempit atau tidak nyaman';
                break;
            case 3:
                $desc = '3-Dudukan tidak sesuai/terlalu sempit dan tidak bisa diatur';
                break;
            default:
                $desc = 'Tidak diketahui';
        }

        // tambahkan detail dari flag jika ada
        $extras = [];
        if (!empty($flags['kursi_sempit'])) {
            $extras[] = 'kursi sempit';
        }
        if (!empty($flags['tidak_bisa_atur'])) {
            $extras[] = 'dudukan tidak bisa diatur';
        }

        if (!empty($extras)) {
            $desc .= ' - Tambahan: ' . implode(', ', $extras);
        }

        return $desc;
    }

    /**
     * Map monitor (contoh mapping & gabungkan flag tambahan seperti pantulan, terlalu jauh, dll).
     *
     * @param mixed $value
     * @param array $flags
     * @return string
     */
    protected static function mapMonitor($value, array $flags = []): string
    {
        $v = (int) $value;
        $desc = '';

        switch ($v) {
            case 1:
                $desc = '1-Jarak antara pekerja dengan monitor sepanjang lengan (40 – 75 cm), eye level';
                break;
            case 2:
                $desc = '2-Monitor sedikit terlalu jauh atau posisi sedikit tidak pada eye level';
                break;
            case 3:
                $desc = '3-Monitor jauh/terlalu dekat atau posisi eye level sangat tidak sesuai';
                break;
            default:
                $desc = 'Tidak diketahui';
        }

        $extras = [];
        if (!empty($flags['leher_putar'])) {
            $extras[] = 'leher harus diputar';
        }
        if (!empty($flags['pantulan'])) {
            $extras[] = 'ada pantulan';
        }
        if (!empty($flags['no_holder'])) {
            $extras[] = 'monitor tanpa holder';
        }
        if (!empty($flags['terlalu_jauh'])) {
            $extras[] = 'monitor terlalu jauh';
        }

        if (!empty($extras)) {
            $desc .= ' - Tambahan: ' . implode(', ', $extras);
        }

        return $desc;
    }

    // ----------------------
    // Contoh mapping lain (sederhana)
    // ----------------------
    protected static function mapTinggiKursi($value): string
    {
        $v = (int) $value;
        switch ($v) {
            case 1: return '1-Lutut membentuk 90ᵒ';
            case 2: return '2-Terlalu tinggi atau rendah';
            case 3: return '3-Tidak dapat diatur';
            default: return 'Tidak diketahui';
        }
    }

    protected static function mapSandaranLengan($value, array $flags = []): string
    {
        $desc = ($value === null) ? 'Tidak diketahui' : ($value . '-Deskripsi dasar sandaran lengan');
        $extras = [];
        if (!empty($flags['lengan_keras'])) $extras[] = 'lengan keras';
        if (!empty($flags['lengan_lebar'])) $extras[] = 'lengan lebar';
        if (!empty($flags['tidak_bisa_atur'])) $extras[] = 'tidak bisa diatur';
        if ($extras) $desc .= ' - Tambahan: ' . implode(', ', $extras);
        return $desc;
    }

    protected static function mapSandaranPunggung($value, array $flags = []): string
    {
        $desc = ($value === null) ? 'Tidak diketahui' : ($value . '-Deskripsi sandaran punggung');
        $extras = [];
        if (!empty($flags['meja_tinggi'])) $extras[] = 'meja terlalu tinggi';
        if (!empty($flags['tidak_bisa_atur'])) $extras[] = 'tidak bisa diatur';
        if ($extras) $desc .= ' - Tambahan: ' . implode(', ', $extras);
        return $desc;
    }

    protected static function mapDurasiKerjaBagianKursi($value): string
    {
        // Contoh konversi: 1 -> "1->4 jam"
        if ($value === null) return 'Tidak diketahui';
        switch ((int)$value) {
            case -1: return '<30 menit atau < 1 jam';
            case 0: return '0-1 jam';
            case 1: return '1->4 jam';
            case 2: return '>4 jam';
            default: return (string)$value;
        }
    }

    protected static function mapTelepon($value, array $flags = []): string
    {
        $v = (int)$value;
        $desc = ($v === 1) ? '1-Menelepon dengan menggunakan headset atau dengan satu tangan' : 'Tidak diketahui';
        $extras = [];
        if (!empty($flags['penopang_leher'])) $extras[] = 'penopang leher';
        if (!empty($flags['tangan_tidak_bebas'])) $extras[] = 'tangan tidak bebas';
        if ($extras) $desc .= ' - Tambahan: ' . implode(', ', $extras);
        return $desc;
    }

    protected static function mapMouse($value, array $flags = []): string
    {
        $v = (int)$value;
        $desc = ($v === 1) ? '1-Mouse sejajar bahu' : 'Tidak diketahui';
        $extras = [];
        if (!empty($flags['beda_permukaan'])) $extras[] = 'beda permukaan';
        if (!empty($flags['menekuk'])) $extras[] = 'menekuk';
        if (!empty($flags['ada_palmrest'])) $extras[] = 'ada palmrest';
        if ($extras) $desc .= ' - Tambahan: ' . implode(', ', $extras);
        return $desc;
    }

    protected static function mapKeyboard($value, array $flags = []): string
    {
        $v = (int)$value;
        $desc = ($v === 1) ? '1-Pergelangan lurus, bahu rileks' : 'Tidak diketahui';
        $extras = [];
        if (!empty($flags['deviasi'])) $extras[] = 'deviasi';
        if (!empty($flags['terlalu_tinggi'])) $extras[] = 'terlalu tinggi';
        if (!empty($flags['diatas_kepala'])) $extras[] = 'di atas kepala';
        if (!empty($flags['tidak_bisa_atur'])) $extras[] = 'tidak bisa diatur';
        if ($extras) $desc .= ' - Tambahan: ' . implode(', ', $extras);
        return $desc;
    }

    protected static function mapDurasiKerjaMonitor($value)
    {
        if ($value === null) return 'Tidak diketahui';
        switch ((int)$value) {
            case -1: return '<30 menit atau < 1 jam';
            case 0: return '0-1 jam';
            case 1: return '1->4 jam';
            case 2: return '>4 jam';
            default: return (string)$value;
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
