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
        $skorBebanA =$this->mapBebanLabel($payload["skor_beban_A"]);
        $skorLenganAtas =$this->mapLenganAtasLabel($payload["skor_lengan_atas"]);
        $skorLenganBawah =$this->mapLenganBawahLabel($payload["skor_lengan_bawah"]);
        $skorAktivitasOtotA =$this->mapAktivitasOtotLabel($payload["skor_penggunaan_otot_A"]);
        $skorTanganMemuntir =$this->mapTanganMemuntirLabel($payload["skor_pergelangan_tangan_memuntir"]);
        $skorPergelanganTangan =$this->mapPergelanganLabel($payload["skor_pergelangan_tangan"]);

        $skorKaki =$this->mapKakiLabel($payload["skor_kaki"]);
        $skorBadan =$this->mapBadanLabel($payload["skor_badan"]);
        $skorLeher =$this->mapLeherLabel($payload["skor_leher"]);
        $skorAktivitasOtotB =$this->mapAktivitasOtotLabel($payload["skor_penggunaan_otot_B"]);
        $skorBebanB = $this->mapBebanLabel($payload["skor_beban_B"]);
        // =============== 2. Format RULA bagian A ===============
        $skorA = [
            "beban" => [
                "skor" => $skorBebanA ?? 0
            ],
            "lengan_atas" => [
                "skor" => $skorLenganAtas ?? 0
            ],
            "lengan_bawah" => [
                "skor" => $skorLenganBawah ?? 0
            ],
            "aktivitas_otot" => [
                "skor" =>$skorAktivitasOtotA ?? 0
            ],
            "tangan_memuntir" => [
                "skor" => $skorTanganMemuntir ?? 0
            ],
            "pergelangan_tangan" => [
                "skor" => $skorPergelanganTangan ?? 0
            ],
        ];

        // =============== 3. Format RULA bagian B ===============
        $skorB = [
            "kaki" => [
                "skor" => $skorKaki ?? 0
            ],
            "badan" => [
                "skor" => $skorBadan ?? 0
            ],
            "beban" => [
                "skor" => $skorBebanB ?? 0
            ],
            "leher" => [
                "skor" => $skorLeher ?? 0
            ],
            "aktivitas_otot" => [
                "skor" => $skorAktivitasOtotB ?? 0
            ],
        ];

        // =============== 4. Build final RULA format ===============
        return [
            "kaki" => (int) ($skorKaki['score'] ?? 0),
            "badan" => (int) ($skorBadan['score'] ?? 0),
            "leher" => (int) ($skorLeher['score'] ?? 0),
            "skor_A" => $skorA,
            "skor_B" => $skorB,

            "beban_A" => (int) ($skorBebanA['score'] ?? 0),
            "beban_B" => (int) ($skorBebanB['score'] ?? 0),

            "skor_rula" => (int) ($payload["final_skor_rula"] ?? 0),

            // Tambahan nilai tabel
            "total_skor_A" => (int) ($payload["total_skor_A"] ?? 0),
            "nilai_tabel_A" => (int) ($payload["nilai_tabel_A"] ?? 0),
            "total_skor_B" => (int) ($payload["total_skor_B"] ?? 0),
            "nilai_tabel_B" => (int) ($payload["nilai_tabel_B"] ?? 0),

            // Komponen individual
            "lengan_atas" => (int) ($skorLenganAtas['score'] ?? 0),
            "lengan_bawah" => (int) ($skorLenganBawah['score'] ?? 0),
            "pergelangan_tangan" => (int) ($skorPergelanganTangan['score'] ?? 0),
            "tangan_memuntir" => (int) ($skorTanganMemuntir['score'] ?? 0),
            "aktivitas_otot_A" => (int) ($skorAktivitasOtotA['score'] ?? 0),
            "aktivitas_otot_B" => (int) ($skorAktivitasOtotB['score'] ?? 0),

            // Tambahan baru
            "penyesuaian" => $penyesuaian,
        ];
    }

    // ====================== LABEL MAPPER ======================

    protected function mapBebanLabel($value)
    {
       
        if($value === null) return ['keterangan'=>'Tidak diketahui','score'=>0];
        switch ((int)$value){
            case 0 : return['keterangan' =>'Beban <2 Kg (berselang)' ,'score'=>0,'index'=>0];
            case 1 : return['keterangan' =>'Beban 2-10 Kg (berselang)' ,'score'=>1,'index'=>1];
            case 2 : return['keterangan' =>'Beban 2-10 Kg (statis/berulang) ' ,'score'=>2,'index'=>2];
            case 3 : return['keterangan' =>'Beban >10 Kg, baik berulang maupun cepat' ,'score'=>3,'index'=>3];
            default: return['keterangan' =>'Tidak diketahui' ,'score'=>0];
        }
    }

    protected function mapLenganAtasLabel($value)
    {
        if ($value === null) return ['keterangan'=>'Tidak diketahui','score'=>0];
        switch ((int)$value) {
            case 0: return ['keterangan'=>'lengan atas dalam posisi netral atau berputar sekitar sudut 0-20 deg','score'=>-1,'index'=>0];
            case 1: return ['keterangan'=>'lengan atas berputar sekitar sudut 20-45 deg ke depan dan/atau kebelakang','score'=>2,'index'=>1];
            case 2: return ['keterangan'=>'lengan atas berputar sekitar sudut 45-90 deg','score'=>3,'index'=>2];
            case 3: return ['keterangan'=>'lengan atas berputar hingga sudut >90 deg','score'=>4,'index'=>3];
            default: return ['keterangan'=>'Tidak diketahui'];
        }
    }

    protected function mapLenganBawahLabel($value)
    {
        if($value == null) return ['keterangan'=>'Tidak diketehaui','score'=>0];
        switch ((int)$value){
            case 0 : return['keterangan' =>'lengan bawah 60-100 deg' ,'score'=>1,'index'=>0];
            case 1 : return['keterangan' =>'lengan bawah menekuk dari sudut 0-60 deg dan atau diatas 100 deg' ,'score'=>2,'index'=>1];
            default: return['keterangan' =>'Tidak diketehaui' ,'score'=>0];
        }
    }

    protected function mapPergelanganLabel($value)
    {
        if($value == null) return ['keterangan'=>'Tidak diketehaui','score'=>0];
        switch ((int)$value){
            case 0 : return['keterangan' =>'pergelangan dalam kondisi Netral' ,'score'=>1,'index'=>0];
            case 1 : return['keterangan' =>'pergelangan lengan menekuk hingga sudut antara 0-15 deg, baik keatas dan kebawah' ,'score'=>2,'index'=>1];
            case 2 : return['keterangan' =>'pergelangan lengan menekuk diatas sudut 15deg, baik keatas dan kebawah' ,'score'=>3,'index'=>2];
            default: return['keterangan' =>'Tidak diketehaui' ,'score'=>0];
        }
    }

    protected function mapTanganMemuntirLabel($value)
    {
        if($value === null) return ['keterangan'=>'Tidak diketehaui','score'=>0];
        switch ((int)$value){
            case 0 : return['keterangan' =>'Jika pergelangan tangan dalam kisaran tengah pada posisi memuntir' ,'score'=>1,'index'=>0];
            case 1 : return['keterangan' =>'Jika pergelangan tangan pada atau dekat batas maksimal puntiran)' ,'score'=>2,'index'=>1];
            default: return['keterangan' =>'Tidak diketehaui' ,'score'=>0];
        }
    }

    protected function mapAktivitasOtotLabel($value)
    {
        if($value === null) return ['keterangan'=>'Tidak diketehaui','score'=>0];
        switch ((int)$value){
            case 0 : return['keterangan' =>'Jika otot yang digunakan tidak dapat terdeskripsikan' ,'score'=>0,'index'=>0];
            case 1 : return['keterangan' =>'Jika pekerjaan dilakukan statis lebih dari 10 menit atau jika pekerjaan dilakukan berulang untuk lebih dari 4 kali per menit' ,'score'=>1,'index'=>1];
            default: return['keterangan' =>'Tidak diketehaui' ,'score'=>0];
        }
    }

    protected function mapBadanLabel($value)
    {
        if($value === null) return ['keterangan'=>'Tidak diketehaui','score'=>0];
        switch ((int)$value){
            case 0 : return['keterangan' =>'badan dalam posisi netral' ,'score'=>1,'index'=>0];
            case 1 : return['keterangan' =>'badan menekuk sekitar sudut 0-20 deg' ,'score'=>2,'index'=>1];
            case 2 : return['keterangan' =>'badan menekuk sekitar sudut 20-60 deg' ,'score'=>3,'index'=>2];
            case 3 : return['keterangan' =>'badan menekuk hingga sudut >60 deg' ,'score'=>4,'index'=>3];
            default: return['keterangan' =>'Tidak diketehaui' ,'score'=>0];
        }
    }

    protected function mapLeherLabel($value)
    {
        if($value === null) return ['keterangan'=>'Tidak diketehaui','score'=>0];
        switch ((int)$value){
            case 0 : return['keterangan' =>'leher menekuk sekitar sudut 0-10 deg' ,'score'=>1,'index'=>0];
            case 1 : return['keterangan' =>'leher menekuk sekitar sudut 10-20 deg' ,'score'=>2,'index'=>1];
            case 2 : return['keterangan' =>'leher menekuk sekitar sudut > 20 deg ke depan' ,'score'=>3,'index'=>2];
            case 3 : return['keterangan' =>'leher menekuk ke belakang' ,'score'=>4,'index'=>3];
            default: return['keterangan' =>'Tidak diketehaui' ,'score'=>0];
        }
    }

    protected function mapKakiLabel($value)
    {
        if($value === null) return ['keterangan'=>'Tidak diketehaui','score'=>0];
        switch ((int)$value){
            case 0 : return['keterangan' =>'Jika kaki dan telapak kaki tertopang dengan baik pada saat duduk/berdiri' ,'score'=>1,'index'=>0];
            case 1 : return['keterangan' =>'Jika kaki dan telapak kaki tertopang dengan tidak baik pada saat duduk/berdiri' ,'score'=>2,'index'=>1];
            default: return['keterangan' =>'Tidak diketehaui' ,'score'=>0];
        }
    }

    /** Format pengukuran RULA selaras mobile / apps-fdl (skor string, tanpa penyesuaian terpisah). */
    public function formatLegacyData(array $data): array
    {
        $idx = static function (string $key, int $default = 0) use ($data): int {
            return isset($data[$key]) && $data[$key] !== '' ? (int) $data[$key] : $default;
        };
        $flag = static function (string $key) use ($data): bool {
            return (int) ($data[$key] ?? 0) === 1;
        };
        $pick = static function (array $options, int $index): string {
            return $options[$index] ?? $options[0];
        };

        $lenganAtasOpts = [
            '1-lengan atas dalam posisi netral atau berputar sekitar sudut 0-20 deg',
            '2-lengan atas berputar sekitar sudut 20-45 deg ke depan dan/atau kebelakang',
            '3-lengan atas berputar sekitar sudut 45-90 deg',
            '4-lengan atas berputar hingga sudut >90 deg',
        ];
        $lenganBawahOpts = [
            '1-lengan bawah menekuk hingga sudut antara 60-100 deg',
            '2-lengan bawah menekuk dari sudut 0-60 deg dan atau diatas 100 deg',
        ];
        $pergelanganOpts = [
            '1-pergelangan dalam kondisi Netral',
            '2-pergelangan lengan menekuk hingga sudut antara 0-15 deg, baik keatas dan kebawah',
            '3-pergelangan lengan menekuk diatas sudut 15deg, baik keatas dan kebawah',
        ];
        $tanganMemuntirOpts = [
            '1-Jika pergelangan tangan dalam kisaran tengah pada posisi memuntir',
            '2-Jika pergelangan tangan pada atau dekat batas maksimal puntiran',
        ];
        $aktivitasOpts = [
            '0-Jika otot yang digunakan tidak dapat terdeskripsikan',
            '1-Jika pekerjaan dilakukan statis lebih dari 10 menit atau jika pekerjaan dilakukan berulang untuk lebih dari 4 kali per menit',
        ];
        $bebanOpts = [
            '0-Beban <2 Kg (berselang)',
            '1-Beban 2-10 Kg (berselang)',
            '2-Beban 2-10 Kg (statis atau berulang)',
            '3-Beban >10 Kg, baik berulang maupun cepat',
        ];
        $leherOpts = [
            '1-leher menekuk sekitar sudut 0-10 deg',
            '2-leher menekuk sekitar sudut 10-20 deg',
            '3-leher menekuk sekitar sudut > 20 deg ke depan',
            '4-leher menekuk ke belakang',
        ];
        $badanOpts = [
            '1-badan dalam posisi netral',
            '2-badan menekuk sekitar sudut 0-20 deg',
            '3-badan menekuk sekitar sudut 20-60 deg',
            '4-badan menekuk hingga sudut >60 deg',
        ];
        $kakiOpts = [
            '1-Jika kaki dan telapak kaki tertopang dengan baik pada saat duduk/berdiri',
            '2-Jika kaki dan telapak kaki tertopang dengan tidak baik pada saat duduk/berdiri',
        ];

        $lenganAtas = ['skor' => $pick($lenganAtasOpts, $idx('skor_lengan_atas'))];
        if ($flag('tambah_bahu_diangkat')) {
            $lenganAtas['tambahan'] = '1-jika bahu diangkat/diputar/dirotasi';
        }
        if ($flag('tambah_lengan_menjauhi')) {
            $lenganAtas['tambahan_2'] = '1-jika lengan atas diangkat menjauhi badan';
        }
        if ((int) ($data['tambah_lengan_menopang'] ?? 0) === 1) {
            $lenganAtas['tambahan_3'] = '1-jika lengan menopang / bersandar';
        }

        $lenganBawah = ['skor' => $pick($lenganBawahOpts, $idx('skor_lengan_bawah'))];
        if ($flag('tambah_lengan_bawah_menyilang')) {
            $lenganBawah['tambahan'] = '1-jika lengan bawah bekerja diluar sisi tubuh atau menyilang';
        }

        $pergelangan = ['skor' => $pick($pergelanganOpts, $idx('skor_pergelangan_tangan'))];
        if ($flag('tambah_pergelangan_deviasi')) {
            $pergelangan['tambahan'] = '1-Pergelangan tangan pada saat bekerja mengalami deviasi baik ulnar maupun radial';
        }

        $leher = ['skor' => $pick($leherOpts, $idx('skor_leher'))];
        if ($flag('tambah_leher_terpelintir')) {
            $leher['tambahan'] = '1-Jika leher terpelintir';
        }
        if ($flag('tambah_leher_ditekuk_samping')) {
            $leher['tambahan_2'] = '1-Jika leher ditekuk ke samping';
        }

        $badan = ['skor' => $pick($badanOpts, $idx('skor_badan'))];
        if ($flag('tambah_badan_terpelintir')) {
            $badan['tambahan'] = '1-Jika batang tubuh terpelintir';
        }
        if ($flag('tambah_badan_tertekuk_samping')) {
            $badan['tambahan_2'] = '1-Jika batang tubuh tertekuk ke samping';
        }

        $skorA = [
            'lengan_atas' => $lenganAtas,
            'lengan_bawah' => $lenganBawah,
            'pergelangan_tangan' => $pergelangan,
            'tangan_memuntir' => $pick($tanganMemuntirOpts, $idx('skor_pergelangan_tangan_memuntir')),
            'aktivitas_otot' => $pick($aktivitasOpts, $idx('skor_penggunaan_otot_A')),
            'beban' => $pick($bebanOpts, $idx('skor_beban_A')),
        ];
        $skorB = [
            'leher' => $leher,
            'badan' => $badan,
            'kaki' => $pick($kakiOpts, $idx('skor_kaki')),
            'aktivitas_otot' => $pick($aktivitasOpts, $idx('skor_penggunaan_otot_B')),
            'beban' => $pick($bebanOpts, $idx('skor_beban_B')),
        ];

        return $this->computeLegacyRulaSummary($skorA, $skorB);
    }

    private function extractLegacyRulaScore($value): int
    {
        if (is_string($value) && preg_match('/^(-?\d+)-/', $value, $matches)) {
            return (int) $matches[1];
        }
        return (int) $value;
    }

    private function sumLegacyRulaSections(array $skorA, array $skorB): array
    {
        $sections = ['skor_A' => $skorA, 'skor_B' => $skorB];
        $hasil = [];
        foreach ($sections as $kategori => $bagian) {
            foreach ($bagian as $key => $item) {
                $hasil[$kategori][$key] = 0;
                if (is_array($item)) {
                    foreach ($item as $subKey => $val) {
                        if ($kategori === 'skor_A' && $key === 'lengan_atas' && $subKey === 'tambahan_3') {
                            $hasil[$kategori][$key] -= $this->extractLegacyRulaScore($val);
                        } else {
                            $hasil[$kategori][$key] += $this->extractLegacyRulaScore($val);
                        }
                    }
                } else {
                    $hasil[$kategori][$key] += $this->extractLegacyRulaScore($item);
                }
            }
        }
        return $hasil;
    }

    private function computeLegacyRulaSummary(array $skorA, array $skorB): array
    {
        $tabelA = [
            1 => [1 => [1 => [1 => 1, 2 => 2], 2 => [1 => 2, 2 => 2], 3 => [1 => 2, 2 => 3], 4 => [1 => 3, 2 => 3]], 2 => [1 => [1 => 2, 2 => 2], 2 => [1 => 2, 2 => 2], 3 => [1 => 3, 2 => 3], 4 => [1 => 3, 2 => 3]], 3 => [1 => [1 => 2, 2 => 3], 2 => [1 => 3, 2 => 3], 3 => [1 => 3, 2 => 4], 4 => [1 => 4, 2 => 4]]],
            2 => [1 => [1 => [1 => 2, 2 => 3], 2 => [1 => 3, 2 => 3], 3 => [1 => 3, 2 => 4], 4 => [1 => 4, 2 => 4]], 2 => [1 => [1 => 3, 2 => 3], 2 => [1 => 3, 2 => 3], 3 => [1 => 3, 2 => 4], 4 => [1 => 4, 2 => 4]], 3 => [1 => [1 => 3, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 5], 4 => [1 => 5, 2 => 5]]],
            3 => [1 => [1 => [1 => 3, 2 => 3], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 4], 4 => [1 => 5, 2 => 5]], 2 => [1 => [1 => 3, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 4], 4 => [1 => 5, 2 => 5]], 3 => [1 => [1 => 4, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 5], 4 => [1 => 5, 2 => 5]]],
            4 => [1 => [1 => [1 => 4, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 5], 4 => [1 => 5, 2 => 5]], 2 => [1 => [1 => 4, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 5], 4 => [1 => 5, 2 => 5]], 3 => [1 => [1 => 4, 2 => 4], 2 => [1 => 5, 2 => 5], 3 => [1 => 5, 2 => 6], 4 => [1 => 6, 2 => 6]]],
            5 => [1 => [1 => [1 => 5, 2 => 5], 2 => [1 => 5, 2 => 5], 3 => [1 => 5, 2 => 6], 4 => [1 => 6, 2 => 7]], 2 => [1 => [1 => 5, 2 => 6], 2 => [1 => 6, 2 => 6], 3 => [1 => 6, 2 => 7], 4 => [1 => 7, 2 => 7]], 3 => [1 => [1 => 6, 2 => 6], 2 => [1 => 6, 2 => 7], 3 => [1 => 7, 2 => 7], 4 => [1 => 7, 2 => 8]]],
            6 => [1 => [1 => [1 => 7, 2 => 7], 2 => [1 => 7, 2 => 7], 3 => [1 => 7, 2 => 8], 4 => [1 => 8, 2 => 9]], 2 => [1 => [1 => 8, 2 => 8], 2 => [1 => 8, 2 => 8], 3 => [1 => 8, 2 => 9], 4 => [1 => 9, 2 => 9]], 3 => [1 => [1 => 9, 2 => 9], 2 => [1 => 9, 2 => 9], 3 => [1 => 9, 2 => 9], 4 => [1 => 9, 2 => 9]]],
        ];
        $tabelB = [
            1 => [1 => [1 => 1, 2 => 2], 2 => [1 => 2, 2 => 3], 3 => [1 => 3, 2 => 3], 4 => [1 => 5, 2 => 5], 5 => [1 => 6, 2 => 6], 6 => [1 => 7, 2 => 7]],
            2 => [1 => [1 => 2, 2 => 3], 2 => [1 => 2, 2 => 3], 3 => [1 => 4, 2 => 4], 4 => [1 => 5, 2 => 5], 5 => [1 => 6, 2 => 6], 6 => [1 => 7, 2 => 7]],
            3 => [1 => [1 => 3, 2 => 3], 2 => [1 => 3, 2 => 4], 3 => [1 => 4, 2 => 5], 4 => [1 => 5, 2 => 6], 5 => [1 => 6, 2 => 7], 6 => [1 => 7, 2 => 7]],
            4 => [1 => [1 => 5, 2 => 5], 2 => [1 => 5, 2 => 6], 3 => [1 => 6, 2 => 7], 4 => [1 => 7, 2 => 7], 5 => [1 => 7, 2 => 7], 6 => [1 => 8, 2 => 8]],
            5 => [1 => [1 => 7, 2 => 7], 2 => [1 => 7, 2 => 7], 3 => [1 => 7, 2 => 8], 4 => [1 => 8, 2 => 8], 5 => [1 => 8, 2 => 8], 6 => [1 => 8, 2 => 8]],
            6 => [1 => [1 => 8, 2 => 8], 2 => [1 => 8, 2 => 8], 3 => [1 => 8, 2 => 8], 4 => [1 => 8, 2 => 9], 5 => [1 => 9, 2 => 9], 6 => [1 => 9, 2 => 9]],
        ];
        $tabelC = [
            1 => [1 => 1, 2 => 2, 3 => 3, 4 => 3, 5 => 4, 6 => 5, 7 => 5],
            2 => [1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 4, 6 => 5, 7 => 5],
            3 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 4, 6 => 5, 7 => 6],
            4 => [1 => 4, 2 => 4, 3 => 4, 4 => 5, 5 => 5, 6 => 6, 7 => 7],
            5 => [1 => 5, 2 => 5, 3 => 5, 4 => 6, 5 => 7, 6 => 7, 7 => 7],
            6 => [1 => 6, 2 => 6, 3 => 6, 4 => 7, 5 => 7, 6 => 7, 7 => 7],
            7 => [1 => 7, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 7],
            8 => [1 => 7, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 7],
        ];

        $parsed = $this->sumLegacyRulaSections($skorA, $skorB);

        $lengan_atas = min(max(1, $parsed['skor_A']['lengan_atas'] ?? 0), 6);
        $lengan_bawah = min(max(1, $parsed['skor_A']['lengan_bawah'] ?? 0), 3);
        $pergelangan_tangan = min(max(1, $parsed['skor_A']['pergelangan_tangan'] ?? 0), 4);
        $tangan_memuntir = min(max(1, $parsed['skor_A']['tangan_memuntir'] ?? 0), 2);
        $aktivitas_otot = $parsed['skor_A']['aktivitas_otot'] ?? 0;
        $bebanA = $parsed['skor_A']['beban'] ?? 0;

        $nilaiTabelA = $tabelA[$lengan_atas][$lengan_bawah][$pergelangan_tangan][$tangan_memuntir] ?? 0;
        $totalSkorA = $nilaiTabelA + $bebanA + $aktivitas_otot;

        $leher = min(max(1, $parsed['skor_B']['leher'] ?? 0), 6);
        $badan = min(max(1, $parsed['skor_B']['badan'] ?? 0), 6);
        $kakiRaw = $parsed['skor_B']['kaki'] ?? 0;
        $kaki = ($kakiRaw > 1) ? 2 : 1;
        $bebanB = $parsed['skor_B']['beban'] ?? 0;
        $aktivitas_ototB = $parsed['skor_B']['aktivitas_otot'] ?? 0;

        $nilaiTabelB = $tabelB[$leher][$badan][$kaki] ?? 0;
        $totalSkorB = $nilaiTabelB + $bebanB + $aktivitas_ototB;

        $baris = $totalSkorA > 8 ? 8 : $totalSkorA;
        $kolom = $totalSkorB > 7 ? 7 : $totalSkorB;
        $skorC = $tabelC[$baris][$kolom] ?? 0;

        return [
            'skor_A' => $skorA,
            'lengan_atas' => $lengan_atas,
            'lengan_bawah' => $lengan_bawah,
            'pergelangan_tangan' => $pergelangan_tangan,
            'tangan_memuntir' => $tangan_memuntir,
            'aktivitas_otot_A' => $aktivitas_otot,
            'beban_A' => $bebanA,
            'skor_B' => $skorB,
            'leher' => $leher,
            'badan' => $badan,
            'kaki' => $kaki,
            'aktivitas_otot_B' => $aktivitas_ototB,
            'beban_B' => $bebanB,
            'nilai_tabel_A' => $nilaiTabelA,
            'nilai_tabel_B' => $nilaiTabelB,
            'total_skor_A' => $totalSkorA,
            'total_skor_B' => $totalSkorB,
            'skor_rula' => $skorC,
        ];
    }
}
