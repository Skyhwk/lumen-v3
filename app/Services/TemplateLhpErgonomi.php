<?php
namespace App\Services;

use \Mpdf\Mpdf as PDF;
use Illuminate\Support\Facades\View;
use App\Models\{DataLapanganErgonomi};
use Carbon\Carbon;
use App\Helpers\Helper;

class TemplateLhpErgonomi
{
    public function ergonomiRula($data = null,$cssGlobal='',$spesifik='',$ttd= null)
    {
        try {
            $mpdfConfig = [
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 5,
            ];
            $dataRula = DataLapanganErgonomi::with(['detail'])->where('no_sampel', $data->no_sampel)
            ->where('method', 3)
            ->orderBy('id','desc')
            ->first();
            
            $pengukuran = json_decode($dataRula->pengukuran, true);
            $pengukuran = Helper::normalize_format_key($pengukuran,true);
            $skor = 7;
            $tingkatResiko = '';
            $kategoriResiko = '';
            $tindakan = '';
            $result = '';
            if ($skor >= 1 && $skor <= 2) {
                $tingkatResiko = 0;
                $kategoriResiko = 'Rendah';
                $tindakan = 'Tidak ada tindakan yang diperlukan';
            } elseif ($skor >= 3 && $skor <= 4) {
                $tingkatResiko = 1;
                $kategoriResiko = 'Sedang';
                $tindakan = 'Mungkin diperlukan tindakan';
            } elseif ($skor >= 5 && $skor <= 6) {
                $tingkatResiko = 2;
                $kategoriResiko = 'Tinggi';
                $tindakan = 'Diperlukan tindakan';
            } elseif ($skor >= 7) {
                $tingkatResiko = 3;
                $kategoriResiko = 'Sangat Tinggi';
                $tindakan = 'Diperlukan tindakan sekarang';
            } else {
                $result = 'Belum ada Penilaian';
            }
            if ($skor !== null && $skor !== '') {
                $result = "Berdasarkan hasil analisa yang telah dilakukan, didapatkan hasil skor RULA yaitu sebesar {$skor},Hasil skor tersebut masuk dalam tingkat risiko {$tingkatResiko} dan kategori resiko{$kategoriResiko}, sehingga kemungkinan {$tindakan} untuk mencegah terjadinya kecelakaan kerja dan penyakit akibat kerja.";
            }

            $pengukuran->result = $result;
            
            $personal = (object) [
                "no_sampel" => $dataRula->no_sampel,
                "nama_pekerja" => $dataRula->nama_pekerja,
                "usia" => $dataRula->usia,
                "lama_kerja" => json_decode($dataRula->lama_kerja),
                "jenis_kelamin" => $dataRula->jenis_kelamin,
                "aktivitas_ukur" => $dataRula->aktivitas_ukur,
                "aktivitas" => $dataRula->aktivita,
                "nama_pelanggan" => isset($dataRula->detail) ? $dataRula->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRula->detail) ? $dataRula->detail->alamat_perusahaan : null,
                "tanggal_sampling" => isset($dataRula->detail) ? Carbon::parse($dataRula->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "no_lhp" => isset($dataRula->detail) ? $dataRula->detail->cfr : null,
                "periode_analisis" => null,
            ];
            
           
            $pdf = new PDF($mpdfConfig);
            $html = View::make('ergonomirula',compact('pengukuran', 'personal','ttd'))->render();
            
            return $html;
            return $pdf->Output('laporan.pdf', 'I');
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    public function ergonomiRwl($data = null,$cssGlobal='',$spesifik='',$ttd= null)
    {   
        try {
            //code...
            $mpdfConfig = [
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 15,
            ];
            $dataRwl = DataLapanganErgonomi::with(['detail'])->where('no_sampel', $data->no_sampel)
                ->where('method', 5)
                ->orderBy('id','desc')
                ->first();
            // $pengukuran = json_decode($dataRwl->pengukuran);
            $pengukuran = json_decode($dataRwl->pengukuran, true);
            $pengukuran = Helper::normalize_format_key($pengukuran,true);
            
            $avrageFrequesi = ($pengukuran->frekuensi_jumlah_awal + $pengukuran->frekuensi_jumlah_akhir) / 2;
            $pengukuran->frekuensi = $avrageFrequesi;
            $pengukuran->durasi_jam_kerja = $pengukuran->durasi_jam_kerja_akhir;
            $pengukuran->jarak_vertikal = $dataRwl->jarak_vertikal;
            $pengukuran->kopling_tangan = $dataRwl->kopling_tangan;
            $pengukuran->durasi_jam_kerja = $dataRwl->durasi_jam_kerja;
            $pengukuran->berat_beban = $dataRwl->berat_beban;
            $personal = (object) [
                "no_sampel" => $dataRwl->no_sampel,
                "nama_pekerja" => $dataRwl->nama_pekerja,
                "usia" => $dataRwl->usia,
                "lama_kerja" => json_decode($dataRwl->lama_kerja),
                "jenis_kelamin" => $dataRwl->jenis_kelamin,
                "aktivitas_ukur" => $dataRwl->aktivitas_uku,
                "aktivitas" => $dataRwl->aktivita,
                "nama_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->alamat_perusahaan : null,
                "tanggal_sampling" => isset($dataRwl->detail) ? Carbon::parse($dataRwl->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "no_lhp" => isset($dataRwl->detail) ? $dataRwl->detail->cfr : null,
                "periode_analisis" => null,
            ];
            
        //    dd($pengukuran);
            $pdf = new PDF($mpdfConfig);
            $html = View::make('ergonomirwl',compact('pengukuran','personal','ttd'))->render();
            return $html;
        } catch (\Exception $th) {
            throw $th;
        }
    }

    public function ergonomiNbm($data = null,$cssGlobal='',$spesifik='',$ttd= null)
    { 
        try {
            
            $mpdfConfig = [
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 15,
            ];
            
            // olah data:
            $dataRwl = DataLapanganErgonomi::with(['detail'])->where('no_sampel', $data->no_sampel)
                ->where('method', 1)
                ->orderBy('id','desc')
                ->first();

            // $ws_ergo = DataLapanganErgonomi::where('id_data_lapangan', $data->id)->first();
            // if($ws_ergo){
            //     $dataRwl->pengukuran = $ws_ergo->pengukuran;
            //     $dataRwl->sebelum_kerja = $ws_ergo->sebelum_kerja;
            //     $dataRwl->setelah_kerja = $ws_ergo->setelah_kerja;
            // }
    
            // $pengukuran = json_decode($dataRwl->pengukuran);
            $pengukuran = json_decode($dataRwl->pengukuran, true);
            $pengukuran = Helper::normalize_format_key($pengukuran,true);
            
            $sebelumKerja = json_decode($dataRwl->sebelum_kerja);
            $setelahKerja = json_decode($dataRwl->setelah_kerja);
            
            //total sebelum kiri/kanan
            $kiriSebelumOnly = array_sum(array_filter((array) $pengukuran->sebelum, function ($value, $key) {
                return stripos($key, 'kiri') !== false && $key !== 'skor_kiri';
            }, ARRAY_FILTER_USE_BOTH));
            $kananSebelumOnly = array_sum(array_filter((array) $pengukuran->sebelum, function ($value, $key) {
                return stripos($key, 'kanan') !== false && $key !== 'skor_kanan';
            }, ARRAY_FILTER_USE_BOTH));
            //total setelah kiri/kanan
            $kiriSetelahOnly = array_sum(array_filter((array) $pengukuran->setelah, function ($value, $key) {
                return stripos($key, 'kiri') !== false && $key !== 'skor_kiri';
            }, ARRAY_FILTER_USE_BOTH));
            $kananSetelahOnly = array_sum(array_filter((array) $pengukuran->setelah, function ($value, $key) {
                return stripos($key, 'kanan') !== false && $key !== 'skor_kanan';
            }, ARRAY_FILTER_USE_BOTH));
    
            // formatData
            $pengukuran->sebelum->skor_kiri_sebelum = $kiriSebelumOnly;
            $pengukuran->sebelum->skor_kanan_sebelum = $kananSebelumOnly;
            $pengukuran->setelah->skor_kiri_setelah = $kiriSetelahOnly;
            $pengukuran->setelah->skor_kanan_setelah = $kananSetelahOnly;
            
            // dd($pengukuran); //kategori_risiko, tindakan_perbaikan
            foreach (['sebelum', 'setelah'] as $waktu) {
                // 1. Cek dulu apakah datanya ada agar tidak error
                if (isset($pengukuran->$waktu)) {
                    
                    // 2. Ambil data ke variabel sementara (Temp)
                    $dataTemp = $pengukuran->$waktu;

                    // 3. Hitung resiko
                    $skor = $dataTemp->total_skor ?? 0; // Pakai null coalescing biar aman
                    [$tingkatResiko, $kategoriResiko, $tindakan] = $this->hitungResiko($skor, 'nbm');

                    // 4. Modifikasi data di variabel sementara
                    $dataTemp->tingkat_resiko = $tingkatResiko;
                    $dataTemp->kategori_resiko = $kategoriResiko;
                    $dataTemp->tindakan = $tindakan;

                    // 5. PENTING: Masukkan kembali (Overwrite) ke objek utama
                    $pengukuran->$waktu = $dataTemp;
                }
            }
            $uraianAktivitasK3 =null;
            if($dataRwl->input_k3 != null){
                $aktivitasK3 =json_decode($dataRwl->input_k3);
                $uraianAktivitasK3=$aktivitasK3->uraian;
            }
            $personal = (object) [
                "no_sampel" => $dataRwl->no_sampel,
                "nama_pekerja" => $dataRwl->nama_pekerja,
                "usia" => $dataRwl->usia,
                "lama_kerja" => json_decode($dataRwl->lama_kerja),
                "jenis_kelamin" => $dataRwl->jenis_kelamin,
                "aktivitas_ukur" => ($uraianAktivitasK3 != null) ? $uraianAktivitasK3 : $dataRwl->aktivitas_ukur,
                "divisi" => $dataRwl->divisi,
                "aktivitas" => $dataRwl->aktivita,
                "nama_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->alamat_perusahaan : null,
                "tanggal_sampling" => isset($dataRwl->detail) ? Carbon::parse($dataRwl->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "no_lhp" => isset($dataRwl->detail) ? $dataRwl->detail->cfr : null,
                "periode_analisis" => null,
            ];
            
            
            $pdf = new PDF($mpdfConfig);
            $html = View::make('ergonominbm', compact('pengukuran', 'personal','spesifik','ttd'))->render();
            return $html;  
        }catch (ViewException $e) {
            return "<p style='color:red'>View <b>ergonomgontrak</b> tidak ditemukan!</p>";
        } catch (\Exception $th) {
            throw $th;
        }
    }

    public function ergonomiReba($data = null,$cssGlobal ='',$spesifik ='',$ttd= null)
    {   
        try {
            $mpdfConfig = [
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_top' => 5,
                'margin_bottom' => 15,
                'margin_left' => 10,
                'margin_right' => 10,
                // 'orientation' => 'L',
                // 'margin_header' => 8,
                // 'margin_footer' => 5,
            ];
    
            $dataReba = DataLapanganErgonomi::with(['detail'])
                ->where('no_sampel', $data->no_sampel)
                ->where('method', 2)
                ->orderBy('id','desc')
                ->first();
    
            //$pengukuran = json_decode($dataReba->pengukuran);
            $pengukuran = json_decode($dataReba->pengukuran, true);
            $pengukuran = Helper::normalize_format_key($pengukuran,true);
            $skor = $pengukuran->final_skor_reba;
            $tingkatResiko = '';
            $kategoriResiko = '';
            $tindakan = '';
            $result = '';
            if ($skor == 1) {
                $tingkatResiko = 0;
                $kategoriResiko = 'Sangat Rendah';
                $tindakan = 'Tidak ada tindakan yang diperlukan';
            } elseif ($skor >= 2 && $skor <= 3) {
                $tingkatResiko = 1;
                $kategoriResiko = 'Rendah';
                $tindakan = 'Mungkin diperlukan tindakan';
            } elseif ($skor >= 4 && $skor <= 7) {
                $tingkatResiko = 2;
                $kategoriResiko = 'Sedang';
                $tindakan = 'Diperlukan tindakan';
            } elseif ($skor >= 8 && $skor <= 10) {
                $tingkatResiko = 3;
                $kategoriResiko = ' Tinggi';
                $tindakan = 'Diperlukan tindakan segera';
            } elseif ($skor >= 11 && $skor <= 15) {
                $tingkatResiko = 4;
                $kategoriResiko = 'Sangat Tinggi';
                $tindakan = 'Diperlukan tindakan sesegera mungkin';
            } else {
                $result = 'Belum ada Penilaian';
            }
            if ($skor !== null && $skor !== '') {
                $result = "Berdasarkan hasil analisa yang telah dilakukan, didapatkan hasil skor REBA yaitu sebesar {$skor}. Hasil skor tersebut masuk dalam tingkat risiko {$tingkatResiko} dan kategori resiko {$kategoriResiko}, sehingga {$tindakan} untuk mencegah terjadinya kecelakaan kerja dan penyakit akibat kerja.";
                // $result = null;
            }
    
            $pengukuran->tingkat_resiko = $tingkatResiko;
            $pengukuran->kategori_resiko = $kategoriResiko;
            $pengukuran->tindakan = $tindakan;
            $pengukuran->result = $result;
            $personal = (object) [
                "no_sampel" => $dataReba->no_sampel,
                "nama_pekerja" => $dataReba->nama_pekerja,
                "usia" => $dataReba->usia,
                "lama_kerja" => json_decode($dataReba->lama_kerja),
                "jenis_kelamin" => $dataReba->jenis_kelamin,
                "aktivitas_ukur" => $dataReba->aktivitas_ukur,
                "nama_pelanggan" => isset($dataReba->detail) ? $dataReba->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataReba->detail) ? $dataReba->detail->alamat_perusahaan : null,
                "tanggal_sampling" => isset($dataReba->detail) ? Carbon::parse($dataReba->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "no_lhp" => isset($dataReba->detail) ? $dataReba->detail->cfr : null,
                "jenis_sampel" => isset($dataReba->detail) ? explode('-', $dataReba->detail->kategori_3)[1] : null,
                "periode_analisis" => '-',
                "deskripsi_pekerjaan" => $dataReba->aktivitas_ukur,
            ];

            
            $pdf = new PDF($mpdfConfig);
            $html = View::make('ergonomireba', compact('pengukuran', 'personal','cssGlobal','spesifik','ttd'))->render();
            return $html;
        } catch (ViewException $e) {
            return "<p style='color:red'>View <b>ergonomgontrak</b> tidak ditemukan!</p>";
        } catch (\Throwable $th) {
            throw $th;
        }
        
    }

    public function ergonomiRosa($data = null,$cssGlobal='',$spesifik='',$ttd= null)
    {   
        try {
            $mpdfConfig = [
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_top' => 5,
                'margin_bottom' => 15,
                'margin_left' => 10,
                'margin_right' => 10,
                'orientation' => 'L',
                // 'margin_header' => 8,
                // 'margin_footer' => 5,
            ];
    
            $dataRosa = DataLapanganErgonomi::with(['detail'])
                ->where('no_sampel', $data->no_sampel)
                ->where('method', 4)
                ->orderBy('id','desc')
                ->first();
    
            // $pengukuran = json_decode($dataRosa->pengukuran);
            $pengukuran = json_decode($dataRosa->pengukuran, true);
            $pengukuran = Helper::normalize_format_key($pengukuran,true);
            $skor = $pengukuran->final_skor_rosa;
            $tingkatResiko = '';
            $kategoriResiko = '';
            $tindakan = '';
            $result = '';
            if ($skor >= 1 && $skor <= 2) {
                $kategoriResiko = 'Rendah';
                $tindakan = 'Mungkin perlu dilakukan tindakan';
            } else if ($skor >= 3 && $skor <= 5) {
                $kategoriResiko = 'Sedang';
                $tindakan = 'Diperlukan tindakan karena rawan terkena cedera';
            } elseif ($skor >= 5) {
                $kategoriResiko = ' Tinggi';
                $tindakan = 'Diperlukan tindakan segera';
            } else {
                $result = 'Belum ada Penilaian';
            }
            if ($skor !== null && $skor !== '') {
                $result = "Berdasarkan hasil analisa yang telah dilakukan, didapatkan hasil skor ROSA yaitu sebesar {$skor}. Hasil skor tersebut masuk dalam kategori resiko {$kategoriResiko}, sehingga {$tindakan}.";
                // $result = null;
            }
            // $pengukuran->tingkat_resiko = $tingkatResiko;
            $uraianAktivitasK3 =null;
            if($dataRosa->input_k3 != null){
                $aktivitasK3 =json_decode($dataRosa->input_k3);
                $uraianAktivitasK3=$aktivitasK3->uraian;
            }
            $pengukuran->kategori_resiko = $kategoriResiko;
            $pengukuran->tindakan = $tindakan;
            $pengukuran->result = $result;
            $personal = (object) [
                "no_lhp" => isset($dataRosa->detail) ? $dataRosa->detail->cfr : null,
                "no_sampel" => $dataRosa->no_sampel,
                "jenis_sampel" => isset($dataRosa->detail) ? explode('-', $dataRosa->detail->kategori_3)[1] : null,
                "nama_pelanggan" => isset($dataRosa->detail) ? $dataRosa->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRosa->detail) ? $dataRosa->detail->alamat_perusahaan : null,
                "tanggal_sampling" => isset($dataRosa->detail) ? Carbon::parse($dataRosa->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "periode_analisis" => '-',
                "nama_pekerja" => $dataRosa->nama_pekerja,
                "aktivitas_ukur" => ($uraianAktivitasK3 != null)
                    ? ($uraianAktivitasK3[0]->Uraian.' - '.$uraianAktivitasK3[0]->jam.' jam, '.$uraianAktivitasK3[0]->menit.' menit.')
                    : $dataRosa->aktivitas_ukur,
                "usia" => $dataRosa->usia,
                "lama_kerja" => json_decode($dataRosa->lama_kerja),
                "divisi" => $dataRosa->divisi,
            ];
            
            $pdf = new PDF($mpdfConfig);
            
            $html = View::make('ergonomirosa', compact('pengukuran', 'personal','ttd'))->render();
            return $html;
        }catch (ViewException $e) {
            return "<p style='color:red'>View <b>ergonomgontrak</b> tidak ditemukan!</p>";
        } catch (\Throwable $th) {
            throw $th;
        }
    }
    
    public function ergonomiBrief($data = null,$cssGlobal='',$spesifik='',$ttd= null)
    {   
        try {
            $mpdfConfig = [
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 15,
            ];
            $pdf = new PDF($mpdfConfig);
            $html = View::make('ergonomibrief')->render();
            return $html;
        } catch (ViewException $e) {
            return "<p style='color:red'>View <b>ergonomgontrak</b> tidak ditemukan!</p>";
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function ergonomiPotensiBahaya ($data = null,$cssGlobal ='',$spesifik ='',$ttd= null)
    {
       
        try {
            $mpdfConfig = [
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 15,
            ];
    
            $pdf = new PDF($mpdfConfig);
            $dataRwl = DataLapanganErgonomi::with(['detail'])->where('no_sampel',$data->no_sampel)
                ->where('method', 8)
                ->orderBy('id','desc')
                ->first();
            $personal = (object) [
                "no_sampel" => $dataRwl->no_sampel,
                "nama_pekerja" => $dataRwl->nama_pekerja,
                "usia" => $dataRwl->usia,
                "lama_kerja" => json_decode($dataRwl->lama_kerja),
                "jenis_kelamin" => $dataRwl->jenis_kelamin,
                "aktivitas_ukur" => $dataRwl->aktivitas_ukur,
                "nama_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->alamat_perusahaan : null,
                "tanggal_sampling" => isset($dataRwl->detail) ? Carbon::parse($dataRwl->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "no_lhp" => isset($dataRwl->detail) ? $dataRwl->detail->cfr : null,
                "periode_analisis" => (isset($dataRwl->detail) ? $dataRwl->detail->tanggal_sampling : null) . ' - ' . date('Y-m-d'),
                'jabatan' =>$dataRwl->divisi,
                'aktifitas_k3' =>json_decode($dataRwl->input_k3)
            ];


             
    
            // $pengukuran = json_decode($dataRwl->pengukuran,true);
            $pengukuran = json_decode($dataRwl->pengukuran, true);
            $pengukuran = Helper::normalize_format_key($pengukuran,true);

            $mapPointBagianAtas=[
                'Leher'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Bahu'=>[
                    '0%-25%'=>1,
                    '25%-50%'=>2,
                    '50%-100%'=>3
                ],
                'Rotasi Lengan'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Pergelangan Tangan'=>[
                    '0%-25%'=>1,
                    '25%-50%'=>2,
                    '50%-100%'=>3
                ],
                'Gerakan Lengan Sedang'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Gerakan Lengan Intensif'=>[
                    '0%-25%'=>1,
                    '25%-50%'=>2,
                    '50%-100%'=>3
                ],
                'Mengetik Berselang'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>0,
                    '50%-100%'=>1
                ],
                'Mengetik Intensif'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>3
                ],
                'Penggenggam Kuat'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>3
                ],
                'Memencet atau Menjepit'=>[
                    '0%-25%'=>1,
                    '25%-50%'=>2,
                    '50%-100%'=>3
                ],
                'Kuliat Tertekan'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Menggunakan Telapak Tangan'=>[
                    '0%-25%'=>1,
                    '25%-50%'=>2,
                    '50%-100%'=>3
                ],
                'Getaran Lokal'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Faktor Tidak Dapat Di Kontrol'=>[
                    'Ditemukan 1 faktor Kontrol'=>1,
                    'Ditemukan 2 atau lebih faktor kontrol'=>2
                ],
                'Pencahayaan'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>0,
                    '50%-100%'=>1
                ],
                'Temperatur'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>0,
                    '50%-100%'=>1
                ]
            ];

            $mapPointBagianBawah=[
                'Tubuh Membungkuk 20°-45°'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Tubuh Membungkuk >45°'=>[
                    '0%-25%'=>1,
                    '25%-50%'=>2,
                    '50%-100%'=>3
                ],
                'Tubuh Menekuk 30°'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Tubuh Pemuntiran Torso'=>[
                    '0%-25%'=>1,
                    '25%-50%'=>2,
                    '50%-100%'=>3
                ],
                'Gerakan Paha'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Posisi Berlutut'=>[
                    '0%-25%'=>1,
                    '25%-50%'=>2,
                    '50%-100%'=>3
                ],
                'Pergelangan Kaki'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Aktivitas Pergelangan Kaki'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Duduk Tanpa Sandaran'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Duduk Tanpa Pijakan'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>0,
                    '50%-100%'=>1
                ],
                'Tubuh Tertekan Benda'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Lutut Untuk Memukul'=>[
                    '0%-25%'=>1,
                    '25%-50%'=>2,
                    '50%-100%'=>3
                ],
                'Getaran Seluruh Tubuh'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Beban Sedang'=>[
                    '0%-25%'=>0,
                    '25%-50%'=>1,
                    '50%-100%'=>2
                ],
                'Beban Berat'=>[
                    '0%-25%'=>1,
                    '25%-50%'=>2,
                    '50%-100%'=>3
                ],
                'Faktor Kontrol'=>[
                    'Ditemukan 1 faktor Kontrol'=>1,
                    'Ditemukan 2 atau lebih faktor kontrol'=>2,
                ]
            ];

            $mapPointBagianAtas =Helper::normalize_format_key($mapPointBagianAtas,true);
            $mapPointBagianBawah =Helper::normalize_format_key($mapPointBagianBawah,true);
            
            
            

            $skorDataAtasGetaran =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_atas)->getaran);
            
            $skorDataAtasLingkungan =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_atas)->lingkungan);
            $skorDataAtasUsahaTangan =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_atas)->usaha_tangan);
            $skorDataAtasGerakanLengan =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_atas)->gerakan_lengan);
            $skorDataAtasPosturJanggal =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_atas)->postur_janggal);
            $skorDataAtasPosturPenggunaanKeyboard =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_atas)->penggunaan_keyboard);
            $skorDataAtasPosturFaktorTidakDapatDiKontrol =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_atas)->faktor_tidak_dapat_di_kontrol);
            $skorDataAtasPosturFaktorTekananLangsungKeBagianTubuh =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_atas)->tekanan_langsung_ke_bagian_tubuh);
            
            $skorDataBawahGetaran =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_bawah)->getaran);
            $skorDataBawahLingkungan =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_bawah)->lingkungan);
            $skorDataBawahUsahaTangan =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_bawah)->usaha_tangan);
            $skorDataBawahGerakanLengan =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_bawah)->gerakan_lengan);
            $skorDataBawahPosturJanggal =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_bawah)->postur_janggal);
            $skorDataBawahPosturPenggunaanKeyboard =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_bawah)->penggunaan_keyboard);
            $skorDataBawahPosturFaktorTidakDapatDiKontrol =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_bawah)->faktor_tidak_dapat_di_kontrol);
            $skorDataBawahPosturFaktorTekananLangsungKeBagianTubuh =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_bawah)->tekanan_langsung_ke_bagian_tubuh);
            //getaran,lingkungan,usaha_tangan,gerakan_lengan,postur_janggal,penggunaan_keyboard,faktor_tidak_dapat_di_kontrol,tekanan_langsung_ke_bagian_tubuh
            
            $skorDataAtas = array_merge(
                $skorDataAtasGetaran,
                $skorDataAtasLingkungan,
                $skorDataAtasUsahaTangan,
                $skorDataAtasGerakanLengan,
                $skorDataAtasPosturJanggal,
                $skorDataAtasPosturPenggunaanKeyboard,
                $skorDataAtasPosturFaktorTidakDapatDiKontrol,
                $skorDataAtasPosturFaktorTekananLangsungKeBagianTubuh
            );
           
            $skorDataBawah = array_merge(
                (array) $skorDataBawahGetaran,
                (array) $skorDataBawahLingkungan,
                (array) $skorDataBawahUsahaTangan,
                (array) $skorDataBawahGerakanLengan,
                (array) $skorDataBawahPosturJanggal,
                (array) $skorDataBawahPosturPenggunaanKeyboard,
                (array) $skorDataBawahPosturFaktorTidakDapatDiKontrol,
                (array) $skorDataBawahPosturFaktorTekananLangsungKeBagianTubuh
            );
            // clearData
            foreach($skorDataAtas as $key => $value){
                if($value === "Tidak"){
                    unset($skorDataAtas[$key]);
                }
                //buat key baru
                if($value == 'Ditemukan 1 faktor Kontrol'){
                    $skorDataAtas[$key] = [
                        'keterangan' => $value, // Simpan teks aslinya (opsional)
                        'skor'       => 1       // Masukkan skornya
                    ];
                }else if($value == 'Ditemukan 2 atau lebih faktor kontrol'){
                    $skorDataAtas[$key] = [
                        'keterangan' => $value,
                        'skor'       => 2
                    ];
                }
            }
            
            foreach($skorDataBawah as $key => $value){
                if($value === "Tidak"){
                    unset($skorDataBawah[$key]);
                }
                //buat key baru
                if($value == 'Ditemukan 1 faktor Kontrol'){
                    $skorDataBawah[$key] = [
                        'keterangan' => $value, // Simpan teks aslinya (opsional)
                        'skor'       => 1       // Masukkan skornya
                    ];
                }else if($value == 'Ditemukan 2 atau lebih faktor kontrol'){
                    $skorDataBawah[$key] = [
                        'keterangan' => $value,
                        'skor'       => 2
                    ];
                }
            }
            $faktorResiko =$this->calculateSkorManual(optional($pengukuran->manual_handling));
            $manualHandling = $pengukuran->manual_handling;
            $strukturTabel = [
            [
                'no' => 1, 
                'kategori' => 'Postur Janggal', 
                'label' => 'Leher: memuntir atau menekuk', 
                'key' => 'leher' // <--- Ini kunci penghubung ke data skor
            ],
            [
                'no' => 2, 
                'kategori' => 'Postur Janggal', 
                'label' => 'Bahu: Lengan / siku yang tak ditopang...', 
                'key' => 'bahu'
            ],
            [
                'no' => 3, 
                'kategori' => 'Postur Janggal', 
                'label' => 'Rotasi lengan bawah secara cepat', 
                'key' => 'rotasi_lengan'
            ],
            [
                'no' => 4, 
                'kategori' => 'Postur Janggal', 
                'label' => 'Pergelangan tangan: Menekuk ke depan...', 
                'key' => 'pergelangan_tangan'
            ],
            [
                'no' => 5, 
                'kategori' => 'Gerakan Lengan', 
                'label' => 'Sedang: Gerakan stabil dengan jeda teratur', 
                'key' => 'gerakan_lengan_sedang' // Pastikan key ini ada di data
            ],
            [
                'no' => 6, 
                'kategori' => 'Gerakan Lengan', 
                'label' => 'Intensif: Gerakan cepat yang stabil...', 
                'key' => 'gerakan_lengan_intensif'
            ],
            [
                'no' => 7, 
                'kategori' => 'Penggunaan Keyboard', 
                'label' => 'Mengetik secara berselang...', 
                'key' => 'mengetik_berselang'
            ],
            [
                'no' => 8, 
                'kategori' => 'Penggunaan Keyboard', 
                'label' => 'Mengetik secara Intensif', 
                'key' => 'mengetik_intensif'
            ],
            [
                'no' => 9, 
                'kategori' => 'Usaha Tangan', 
                'label' => 'Menggenggam dalam posisi "power grip"...', 
                'key' => 'penggenggam_kuat'
            ],
            [
                'no' => 10, 
                'kategori' => 'Usaha Tangan', 
                'label' => 'Memencet / Menjepit benda dengan jari...', 
                'key' => 'memencet_atau_menjepit'
            ],
            [
                'no' => 11, 
                'kategori' => 'Tekanan Langsung', 
                'label' => 'Kulit tertekan oleh benda yang keras...', 
                'key' => 'kuliat_tertekan'
            ],
            [
                'no' => 12, 
                'kategori' => 'Tekanan Langsung', 
                'label' => 'Menggunakan telapak... untuk memukul', 
                'key' => 'menggunakan_telapak_tangan'
            ],
            [
                'no' => 13, 
                'kategori' => 'Getaran', 
                'label' => 'Getaran lokal (tanpa peredam)', 
                'key' => 'getaran_lokal'
            ],
            [
                'no' => 14, 
                'kategori' => 'Faktor Kontrol', 
                'label' => 'Terdapat faktor yang membuat ritme kerja...', 
                'key' => 'faktor_tidak_dapat_di_kontrol' // Atau 'faktor_kontrol' sesuaikan data
            ],
            [
                'no' => 15, 
                'kategori' => 'Lingkungan', 
                'label' => 'Pencahayaan (kurang atau silau)', 
                'key' => 'pencahayaan'
            ],
            [
                'no' => 16, 
                'kategori' => 'Lingkungan', 
                'label' => 'Temperatur terlalu tinggi atau rendah', 
                'key' => 'temperatur'
            ],
        ];
            $html = View::make('ergonompotensibahaya',compact('cssGlobal','pengukuran','skorDataAtas','skorDataBawah','faktorResiko','manualHandling','personal','ttd','strukturTabel'))->render();
            return $html;
        } catch (ViewException $e) {
            return "<p style='color:red'>View <b>ergonomgontrak</b> tidak ditemukan!</p>";
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function ergonomiGontrak ($data = null,$cssGlobal ='',$spesifik ='',$ttd= null)
    {   
        
        try {
            //code...
            $mpdfConfig = [
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 5,
                'margin_bottom' => 15,
            ];
    
            $pdf = new PDF($mpdfConfig);
            $dataRwl = DataLapanganErgonomi::with(['detail'])->where('no_sampel', $data->no_sampel)
                ->where('method', 7)
                ->first();
    
            // $pengukuran = json_decode($dataRwl->pengukuran);
            $pengukuran = json_decode($dataRwl->pengukuran, true);
            $pengukuran = Helper::normalize_format_key($pengukuran,true);
            
            $sebelumKerja = json_decode($dataRwl->sebelum_kerja);
            $setelahKerja = json_decode($dataRwl->setelah_kerja);
            $personal = (object) [
                "no_sampel" => $dataRwl->no_sampel,
                "nama_pekerja" => $dataRwl->nama_pekerja,
                "usia" => $dataRwl->usia,
                "lama_kerja" => json_decode($dataRwl->lama_kerja),
                "jenis_kelamin" => $dataRwl->jenis_kelamin,
                "aktivitas_ukur" => $dataRwl->aktivitas_ukur,
                "nama_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->alamat_perusahaan : null,
                "tanggal_sampling" => isset($dataRwl->detail) ? Carbon::parse($dataRwl->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "no_lhp" => isset($dataRwl->detail) ? $dataRwl->detail->cfr : null,
                "periode_analisis" => (isset($dataRwl->detail) ? $dataRwl->detail->tanggal_sampling : null) . ' - ' . date('Y-m-d'),
                'jabatan' =>$dataRwl->divisi,
                'aktifitas_k3' =>json_decode($dataRwl->input_k3)
            ];

            
            
            $masa_kerja = $pengukuran->identitas_umum->masa_kerja;
            $fisik = $pengukuran->identitas_umum->lelah_fisik;
            $mental = $pengukuran->identitas_umum->lelah_mental;
            $masa_kerja_map = [
                '0' => 'Kurang dari 3 Bulan',
                '1' => '3 Bulan - 1 Tahun',
                '2' => '1 - 5 Tahun',
                '3' => '5 - 10 Tahun',
                '4' => 'Lebih dari 10 Tahun',
            ];
            $fisikMentalMap =[
                "0" => "Tidak pernah",
                "1" => "Kadang - kadang",
                "2" => "Sering",
                "3" => "Selalu",
                "4" => "Unknown"
            ];
    
            $pengukuran->identitas_umum->masa_kerja =$masa_kerja_map[$masa_kerja] ?? 'Unknow';
            $pengukuran->identitas_umum->lelah_mental =$fisikMentalMap[$mental] ?? 'Unknow';
            $pengukuran->identitas_umum->lelah_fisik =$fisikMentalMap[$fisik] ?? 'Unknow';
            
            $html = View::make('ergonomgontrak',compact('pengukuran','personal','cssGlobal','spesifik','ttd'))->render();
            return $html;
        }catch (ViewException $e) {
            return "<p style='color:red'>View <b>ergonomgontrak</b> tidak ditemukan!</p>";
        } catch (\Throwable $th) {
            throw $th;
        }
    }
    
    private function hitungResiko($skor, $case = null)
    {
        if ($case == 'nbm') {
            if ($skor >= 0 && $skor <= 20) {
                return [0, 'Rendah', 'Belum diperlukan adanya tindakan perbaikan'];
            } elseif ($skor >= 21 && $skor <= 41) {
                return [1, 'Sedang', 'Mungkin diperlukan tindakan dikemudian hari'];
            } elseif ($skor >= 42 && $skor <= 62) {
                return [2, 'Tinggi', 'Diperlukan tindakan segera'];
            } elseif ($skor >= 63 && $skor <= 84) {
                return [3, 'Sangat Tinggi', 'Diperlukan tindakan menyeluruh sesegera mungkin'];
            } else {
                return [null, 'Tidak Diketahui', 'Skor tidak valid'];
            }
        }
    }
    
    private function flattenPengukuran($sectionName, $data)
    { 
        $result = [];
        if($data != null){
            foreach ($data as $kategori => $subdata) {
                
                if (is_iterable($subdata)) {
                    foreach ($subdata as $potensi => $value) {
                        
                        // kalau langsung string
                        if (is_string($value)) {
                            $result[] = [
                                'section'  => $sectionName,
                                'kategori' => $kategori,
                                'potensi'  => $potensi,
                                'skor'     => $value,
                            ];
                        }
    
                        // kalau array/object
                        elseif (is_iterable($value)) {
                            foreach ($value as $subpotensi => $subval) {
                                
                                if (is_string($subval)) {
                                    $result[] = [
                                        'section'  => $sectionName,
                                        'kategori' => $kategori,
                                        'potensi'  => $potensi . ' - ' . $subpotensi,
                                        'skor'     => $subval,
                                    ];
                                }
    
                                elseif (is_iterable($subval)) {
                                    foreach ($subval as $detailKey => $detailVal) {
                                        $result[] = [
                                            'section'  => $sectionName,
                                            'kategori' => $kategori,
                                            'potensi'  => $potensi . ' - ' . $subpotensi . ' - ' . $detailKey,
                                            'skor'     => $detailVal,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                } 
                // kalau $subdata langsung string (kayak "Faktor Tidak Dapat Di Kontrol")
                elseif (is_string($subdata)) {
                    $result[] = [
                        'section'  => $sectionName,
                        'kategori' => $kategori,
                        'potensi'  => $kategori, // bisa juga kosong
                        'skor'     => $subdata,
                    ];
                }
                
            }
        }
      


        return $result;
    }

    private function calculateSkorSNI($pengukuran)
    {
        // 1. Ubah SEMUA jadi Array murni biar tidak pusing Object vs Array
        if($pengukuran == null){
            return [];
        }
        $data = json_decode(json_encode($pengukuran), true);
        
        // 2. Panggil fungsi pembantu untuk menyelam dan menghitung
        $this->hitungRecursive($data);

        // 3. Lihat hasilnya
        return $data;
    }
    private function calculateSkorManual($pengukuran)
    {
        
        if (empty($pengukuran)) {
            return [];
        }

        // 2. Ambil bagian faktor_resiko (sesuaikan dengan struktur object Anda)
        // Jika $pengukuran itu sendiri sudah isinya faktor_resiko, hapus property aksesnya.
        $sourceData = isset($pengukuran->faktor_resiko) ? $pengukuran->faktor_resiko : $pengukuran;

        // 3. JURUS ANDALAN: Ubah Object nested menjadi Array Murni
        // Ini mengubah struktur {#...} menjadi [...] agar mudah di-looping
        $dataArray = json_decode(json_encode($sourceData), true);
        
        // 4. Panggil fungsi pengolah data (Pass by Reference)
        $this->parseSkorRecursive($dataArray);

        // 5. Cek Hasilnya
        return $dataArray;
    }
    private function hitungRecursive(&$items, $namaKey = null)
    {
        // Cek apakah level ini punya 'durasi_gerakan'?
        // Jika YA, langsung hitung skornya.

        $arrayMap =[
            "leher" =>"Leher: memuntir atau menekuk",
            "bahu" =>"Bahu: Lengan / siku yang tak ditopang di atas tinggi perut",
            "rotasi_lengan" =>"Rotasi lengan bawah secara cepat",
            "pergelangan_tangan" =>"Pergelangan tangan: Menekuk ke depan atau ke samping",
            "gerakan_lengan_sedang" =>"Sedang: Gerakan stabil dengan jeda teratur",
            "gerakan_lengan_intensif" =>"Intensif: Gerakan cepat yang stabil tanpa jeda teratur",
            "mengetik_berselang" =>"Mengetik secara berselang (diselingi aktifitas / istirahat)",
            "mengetik_intensif" =>"Mengetik secara Intensif",
            "penggenggam_kuat" =>"Menggenggam dalam posisi <i>power grip</i> gaya > 5 kg",
            "memencet_atau_menjepit" =>"Memencet / Menjepit benda dengan jari gaya > 1 kg",
            "kuliat_tertekan" =>"Kulit tertekan oleh benda yang keras atau runcing",
            "menggunakan_telapak_tangan" =>"Menggunakan telapak atau pergelangan tangan untuk memukul",
            "getaran_lokal" =>"Getaran lokal (tanpa peredam)",
            "faktor_tidak_dapat_di_kontrol" =>"Terdapat faktor yang membuat ritme kerja tubuh bagian atas dan/atau lengan tidak dapat",
            "pencahayaan" =>"Pencahayaan (Pencahayaan yang kurang atau silau)",
            "temperatur" =>"Temperatur terlalu tinggi atau rendah",
            "tubuh_membungkuk_20_45" => "Tubuh membungkuk ke depan / menekuk ke samping 20 - 45°",
            "tubuh_membungkuk_gt_45" => "Tubuh membungkuk ke depan > 45°",
            "tubuh_menekuk_30" => "Tubuh menekuk ke belakang hingga 30°",
            "tubuh_pemuntiran_torso" => "Pemuntira torso (batang tubuh)",
            "gerakan_paha" => "Gerakan paha menjauhi tubuh ke samping secara berulang-ulang",
            "posisi_berlutut" => "Posisi berlutut atau jongkok",
            "pergelangan_kaki" => "Pergelangan kaki menekuk ke atas / ke bawah secara berulang",
            "aktivitas_pergelangan_kaki" => "Aktivitas pergelangan kaki / berdiri dengan pijakan tidak memadai",
            "duduk_tanpa_sandaran" => "Duduk dalam waktu yang lama tanpa sandaran yang memadai",
            "duduk_tanpa_pijakan" => "Bekerja berdiri dalam waktu lama / duduk tanpa pijakan memadai",
            "tubuh_tertekan_benda" => "Tubuh tertekan oleh benda yang keras / runcing",
            "lutut_untuk_memukul" => "Menggunakan lutut untuk memukul / menendang",
            "getaran_seluruh_tubuh" => "Getaran pada seluruh tubuh (tanpa peredam)",
            "beban_sedang" => "Beban sedang",
            "beban_berat" => "Beban berat",
            "faktor_kontrol" => "Terdapat faktor yang membuat ritme kerja tubuh bagian atas dan/atau lengan tidak dapat dikontrol pekerja",
        ];
        if (isset($items['durasi_gerakan'])) {
            
            $parts = explode(';', $items['durasi_gerakan']);
            $point = isset($parts[0]) ? (int)$parts[0] : 0;
            $overtime = isset($items['overtime']) ? (float)$items['overtime'] : 0;
            
            $items['skor'] = $point + $overtime;
            if ($namaKey && isset($arrayMap[$namaKey])) {
                $items['keterangan'] = $arrayMap[$namaKey]; // Masukkan ke array
            }
            // Sudah ketemu, tidak perlu menyelam lebih dalam di cabang ini
            return;
        }

        // Jika TIDAK ketemu di kulit luar, cek apakah dia punya anak (array)?
        // Kalau punya anak, kita selami anaknya satu per satu.
        if (is_array($items)) {
            foreach ($items as $key => &$subItem) {
                // Panggil diri sendiri untuk mengecek si anak
                if (is_array($subItem)) {
                    $this->hitungRecursive($subItem, $key);
                }
            }
        }
    }
    private function parseSkorRecursive(&$items)
    {
        foreach ($items as $key => &$value) {
            
            // KASUS 1: Apakah ini String target? (Contoh: "2-Pengangkatan sering...")
            // Cirinya: Berupa String DAN punya tanda strip "-"
            if (is_string($value) && strpos($value, '-') !== false) {
                
                // Pecah berdasarkan strip pertama saja
                // "2-Pengangkatan" -> Jadi ["2", "Pengangkatan"]
                $parts = explode('-', $value, 2); 
                
                $skor = isset($parts[0]) ? (int) $parts[0] : 0;
                $ket  = isset($parts[1]) ? $parts[1] : '';

                // UBAH format string tadi menjadi Array yang punya skor
                $value = [
                    'raw_text'   => $value, // Simpan teks asli
                    'skor'       => $skor,  // Ini angka 2 nya
                    'keterangan' => $ket    // Ini keterangannya
                ];
            }

            // KASUS 2: Masih berupa Array/Container? (Menyelam lagi)
            // Ini akan menangani key "0", "1", dst.
            elseif (is_array($value)) {
                $this->parseSkorRecursive($value);
            }
        }
    }

    private function hitungResikoBeban($inputJarak, $inputBerat) {
        $mapArray = [
            'jarak_dekat' => [
                'label' => 'Pengangkatan dengan jarak dekat',
                'rules' => [
                    [
                        'zona'      => 'Zona Berbahaya',
                        'color'     => 'red',
                        'operator'  => '>',    // Berat benda lebih dari
                        'limit'     => 23,
                        'poin'      => 5       // Catatan: di gambar tertulis 5*
                    ],
                    [
                        'zona'      => 'Zona Hati-Hati',
                        'color'     => 'yellow',
                        'operator'  => 'between', // Antara X hingga Y
                        'min'       => 7,
                        'max'       => 23,
                        'poin'      => 3
                    ],
                    [
                        'zona'      => 'Zona Aman',
                        'color'     => 'green',
                        'operator'  => '<',    // Kurang dari
                        'limit'     => 7,
                        'poin'      => 0
                    ]
                ]
            ],
            'jarak_sedang' => [
                'label' => 'Pengangkatan dengan jarak sedang',
                'rules' => [
                    [
                        'zona'      => 'Zona Berbahaya',
                        'color'     => 'red',
                        'operator'  => '>',
                        'limit'     => 16,
                        'poin'      => 6
                    ],
                    [
                        'zona'      => 'Zona Hati-Hati',
                        'color'     => 'yellow',
                        'operator'  => 'between',
                        'min'       => 5,
                        'max'       => 16,
                        'poin'      => 3
                    ],
                    [
                        'zona'      => 'Zona Aman',
                        'color'     => 'green',
                        'operator'  => '<',
                        'limit'     => 5,
                        'poin'      => 0
                    ]
                ]
            ],
            'jarak_jauh' => [
                'label' => 'Pengangkatan dengan jarak jauh',
                'rules' => [
                    [
                        'zona'      => 'Zona Berbahaya',
                        'color'     => 'red',
                        'operator'  => '>',
                        'limit'     => 13,
                        'poin'      => 6
                    ],
                    [
                        'zona'      => 'Zona Hati-Hati',
                        'color'     => 'yellow',
                        'operator'  => 'between',
                        'min'       => 4.5,
                        'max'       => 13,
                        'poin'      => 3
                    ],
                    [
                        'zona'      => 'Zona Aman',
                        'color'     => 'green',
                        'operator'  => '<',
                        'limit'     => 4.5,
                        'poin'      => 0
                    ]
                ]
            ]
        ];

        if (!isset($map[$jarakKey])) {
            return "Kategori jarak tidak ditemukan.";
        }

        $rules = $map[$jarakKey]['rules'];

        foreach ($rules as $rule) {
            if ($rule['operator'] === '>') {
                if ($berat > $rule['limit']) {
                    return $rule;
                }
            } elseif ($rule['operator'] === '<') {
                if ($berat < $rule['limit']) {
                    return $rule;
                }
            } elseif ($rule['operator'] === 'between') {
                // Menggunakan >= dan <= agar "hingga 23" masuk ke sini sesuai logika tabel umum
                if ($berat >= $rule['min'] && $berat <= $rule['max']) {
                    return $rule;
                }
            }
        }
        
        return null;
    }
    
    private function groupByKategori($data)
    {
       
        $grouped = [];
        foreach ($data as $row) {
            $kategori = $row['kategori'];
            if (!isset($grouped[$kategori])) {
                $grouped[$kategori] = [];
            }
            $grouped[$kategori][] = $row;
        }
        return $grouped;
    }
}
