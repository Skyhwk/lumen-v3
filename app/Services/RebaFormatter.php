<?php
namespace App\Services;

class RebaFormatter {
    public static function formatRebaData($dataRequest) {
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