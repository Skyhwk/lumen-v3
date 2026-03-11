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
}
