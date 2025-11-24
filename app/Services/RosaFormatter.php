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
            'skor_mouse' => $skor_mouse['score'] ?? null,
            'skor_monitor' => $skor_monitor['score'] ?? null,
            'skor_telepon' => $skor_telepon['score'] ?? null,
            'skor_keyboard' => $skor_keyboard['score'] ?? null,
            'skor_tinggi_kursi' =>$score_tinggi_kursi['score'] ?? null,
            'skor_lebar_kursi' => $score_lebar_dudukan['score'] ?? null,
            'skor_sandaran_lengan' => $score_sandaran_lengan['score'] ?? null,
            'skor_sandaran_punggung' => $score_sandaran_punggung['score'] ?? null,
            'skor_monitor' => $skor_monitor['score'] ?? null,
            'skor_telepon' => $skor_telepon['score'] ?? null,
            'skor_keyboard' => $skor_keyboard['score'] ?? null,
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
