<?php
namespace App\Services;

use \App\Services\MpdfService as PDF;
use Illuminate\Support\Facades\View;
use App\Models\{DataLapanganErgonomi};
use Carbon\Carbon;
use App\Helpers\Helper;
use Illuminate\Support\Str;

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
            ->where('is_approve',1)
            ->orderBy('id','desc')
            ->first();
            
            $pengukuran = json_decode($dataRula->pengukuran, true);
            $pengukuran = Helper::normalize_format_key($pengukuran,true);
            $skor = $pengukuran->skor_rula;
            $tingkatResiko = '';
            $kategoriResiko = '';
            $tindakan = '';
            $result = '';
            if ($skor >= 1 && $skor <= 2) {
                $tingkatResiko = 1;
                $kategoriResiko = 'Rendah';
                $tindakan = 'Tidak ada tindakan yang diperlukan';
            } elseif ($skor >= 3 && $skor <= 4) {
                $tingkatResiko = 2;
                $kategoriResiko = 'Sedang';
                $tindakan = 'Mungkin diperlukan tindakan';
            } elseif ($skor >= 5 && $skor <= 6) {
                $tingkatResiko = 3;
                $kategoriResiko = 'Tinggi';
                $tindakan = 'Diperlukan tindakan';
            } elseif ($skor >= 7) {
                $tingkatResiko = 4;
                $kategoriResiko = 'Sangat Tinggi';
                $tindakan = 'Diperlukan tindakan sekarang';
            } else {
                $result = 'Belum ada Penilaian';
            }
            if ($skor !== null && $skor !== '') {
                $result = "Berdasarkan hasil analisa yang telah dilakukan, didapatkan hasil skor RULA yaitu sebesar {$skor}. Hasil skor tersebut masuk dalam tingkat risiko {$tingkatResiko} dan kategori risiko {$kategoriResiko}, sehingga {$tindakan}.";
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
                "alamat_pelanggan" => isset($dataRula->detail) ? $dataRula->detail->orderHeader->alamat_sampling : null,
                "tanggal_sampling" => isset($dataRula->detail) ? Carbon::parse($dataRula->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "no_lhp" => isset($dataRula->detail) ? $dataRula->detail->cfr : null,
                "periode_analisis" => null,
                "divisi" => $dataRula->divisi,
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
                ->where('is_approve',1)
                ->orderBy('id','desc')
                ->first();
            // $pengukuran = json_decode($dataRwl->pengukuran);
            $pengukuran = json_decode($dataRwl->pengukuran, true);
            $pengukuran = Helper::normalize_format_key($pengukuran,true);
            
            $avrageFrequesi = ($pengukuran->frekuensi_jumlah_awal + $pengukuran->frekuensi_jumlah_akhir) / 2;
            $pengukuran->jarak_vertikal = $dataRwl->jarak_vertikal;
            $pengukuran->berat_beban = $dataRwl->berat_beban;
            $pengukuran->frekuensi_jumlah_angkatan = $dataRwl->frekuensi_jumlah_angkatan;
            $pengukuran->durasi_jam_kerja = $dataRwl->durasi_jam_kerja;
            $pengukuran->kopling_tangan = $dataRwl->kopling_tangan;
            
            $stringAwal = $pengukuran->durasi_jam_kerja_awal;
            $kataWaktu = ['Jam', 'jam', 'Menit', 'menit', 'Detik', 'detik'];
            $stringBersih = str_ireplace($kataWaktu, '', $stringAwal);
            $stringAkhir = trim($stringBersih);
            //kesimpulan
            $liAwal = $this->resultRwl($pengukuran->lifting_index_awal);
            $liAkhir = $this->resultRwl($pengukuran->lifting_index_akhir);
            $pengukuran->durasi_jam_kerja_awal = $stringAkhir;

            $pengukuran->result_li_awal =$liAwal;
            $pengukuran->result_li_akhir =$liAkhir;
            $personal = (object) [
                "no_sampel" => $dataRwl->no_sampel,
                "nama_pekerja" => $dataRwl->nama_pekerja,
                "usia" => $dataRwl->usia,
                "lama_kerja" => json_decode($dataRwl->lama_kerja),
                "jenis_kelamin" => $dataRwl->jenis_kelamin,
                "aktivitas_ukur" => $dataRwl->aktivitas_ukur,
                "aktivitas" => $dataRwl->aktivitas,
                "nama_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->orderHeader->alamat_sampling : null,
                "tanggal_sampling" => isset($dataRwl->detail) ? Carbon::parse($dataRwl->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "no_lhp" => isset($dataRwl->detail) ? $dataRwl->detail->cfr : null,
                "periode_analisis" => null,
                "divisi" => $dataRwl->divisi,
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
                ->where('is_approve',1)
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
                "alamat_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->orderHeader->alamat_sampling : null,
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
                ->where('is_approve',1)
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
    
            $uraianAktivitasK3 =null;
            if($dataReba->input_k3 != null){
                $aktivitasK3 =json_decode($dataReba->input_k3);
                $uraianAktivitasK3=$aktivitasK3->uraian;
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
                "aktivitas_ukur" => ($uraianAktivitasK3 != null)
                    ? ($uraianAktivitasK3[0]->Uraian.' - '.$uraianAktivitasK3[0]->jam.' jam, '.$uraianAktivitasK3[0]->menit.' menit.')
                    : '',
                "nama_pelanggan" => isset($dataReba->detail) ? $dataReba->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataReba->detail) ? $dataReba->detail->orderHeader->alamat_sampling : null,
                "tanggal_sampling" => isset($dataReba->detail) ? Carbon::parse($dataReba->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "no_lhp" => isset($dataReba->detail) ? $dataReba->detail->cfr : null,
                "jenis_sampel" => isset($dataReba->detail) ? explode('-', $dataReba->detail->kategori_3)[1] : null,
                "periode_analisis" => '-',
                "deskripsi_pekerjaan" => $dataReba->aktivitas_ukur,
                "divisi" => $dataReba->divisi,
                'aktifitas_k3' =>json_decode($dataReba->input_k3) ?? (object) ['uraian' => [], 'analisis_potensi_bahaya' => '', 'kesimpulan_survey_lanjutan' => '']
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
                ->where('is_approve',1)
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
            } else if ($skor >= 3 && $skor <= 4) {
                $kategoriResiko = 'Sedang';
                $tindakan = 'Diperlukan tindakan karena rawan terkena cedera';
            } elseif ($skor >= 5) {
                $kategoriResiko = ' Tinggi';
                $tindakan = 'Diperlukan tindakan secara ergonomis sesegera mungkin';
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
                "alamat_pelanggan" => isset($dataRosa->detail) ? $dataRosa->detail->orderHeader->alamat_sampling : null,
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
                ->where('is_approve',1)
                ->orderBy('id','desc')
                ->first();
            $dataJson = json_decode($dataRwl->input_k3);
            
            $personal = (object) [
                "no_sampel" => $dataRwl->no_sampel,
                "nama_pekerja" => $dataRwl->nama_pekerja,
                "usia" => $dataRwl->usia,
                "lama_kerja" => json_decode($dataRwl->lama_kerja),
                "jenis_kelamin" => $dataRwl->jenis_kelamin,
                "aktivitas_ukur" => $dataRwl->aktivitas_ukur,
                "nama_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->orderHeader->alamat_sampling : null,
                "tanggal_sampling" => isset($dataRwl->detail) ? Carbon::parse($dataRwl->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "no_lhp" => isset($dataRwl->detail) ? $dataRwl->detail->cfr : null,
                "periode_analisis" => (isset($dataRwl->detail) ? $dataRwl->detail->tanggal_sampling : null) . ' - ' . date('Y-m-d'),
                'jabatan' =>$dataRwl->divisi,
                'aktifitas_k3' =>json_decode($dataRwl->input_k3) ?? (object) ['uraian' => [], 'analisis_potensi_bahaya' => '', 'kesimpulan_survey_lanjutan' => '']
            ];
            
            // $pengukuran = json_decode($dataRwl->pengukuran,true);
            $pengukuran = json_decode($dataRwl->pengukuran, true);
            
            $pengukuran = Helper::normalize_format_key($pengukuran,true);

            

            // $mapPointBagianAtas =Helper::normalize_format_key($mapPointBagianAtas,true);
            // $mapPointBagianBawah =Helper::normalize_format_key($mapPointBagianBawah,true);

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
            $skorDataBawahPosturAktivitasMendorong =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_bawah)->aktivitas_mendorong);
            $skorDataBawahPosturFaktorTekananLangsungKeBagianTubuh =$this->calculateSkorSNI(optional($pengukuran->tubuh_bagian_bawah)->tekanan_langsung_tubuh);
            //getaran,lingkungan,usaha_tangan,gerakan_lengan,postur_janggal,penggunaan_keyboard,faktor_tidak_dapat_di_kontrol,tekanan_langsung_ke_bagian_tubuh
            
            $skorDataAtas = array_merge(
                (array)$skorDataAtasGetaran,
                (array)$skorDataAtasLingkungan,
                (array)$skorDataAtasUsahaTangan,
                (array)$skorDataAtasGerakanLengan,
                (array)$skorDataAtasPosturJanggal,
                (array)$skorDataAtasPosturPenggunaanKeyboard,
                (array)optional($pengukuran->tubuh_bagian_atas)->faktor_tidak_dapat_di_kontrol,
                (array)$skorDataAtasPosturFaktorTekananLangsungKeBagianTubuh
            );
           
            $skorDataBawah = array_merge(
                (array) $skorDataBawahGetaran,
                (array) $skorDataBawahLingkungan,
                (array) $skorDataBawahUsahaTangan,
                (array) $skorDataBawahGerakanLengan,
                (array) $skorDataBawahPosturJanggal,
                (array) $skorDataBawahPosturPenggunaanKeyboard,
                (array) optional($pengukuran->tubuh_bagian_bawah)->faktor_tidak_dapat_di_kontrol,
                (array) $skorDataBawahPosturFaktorTekananLangsungKeBagianTubuh,
                (array) $skorDataBawahPosturAktivitasMendorong
            );
            // clearData
            foreach($skorDataAtas as $key => $value){
                if(is_array($value) && empty($value)){
                    unset($skorDataAtas[$key]);
                    continue; // Lanjut ke item berikutnya
                }
                if($value === "Tidak"){
                    unset($skorDataAtas[$key]);
                    continue; // Lanjut ke item berikutnya
                }
                //buat key baru
                if($value == 'Ditemukan 1 faktor Kontrol'){
                    $skorDataAtas[$key] = [
                        'rawTax' => $value, // Simpan teks aslinya (opsional)
                        'skor'       => 1,
                        'keterangan'     =>"Terdapat faktor yang membuat ritme kerja tubuh bagian atas dan/atau lengan tidak dapat",
                        'index'      =>14,
                        'label'      =>"faktor"
                         // Masukkan skornya
                    ];
                }else if($value == 'Ditemukan 2 atau lebih faktor kontrol'){
                    $skorDataAtas[$key] = [
                        'rawTax' => $value,
                        'skor'       => 2,
                        'keterangan'     =>"Terdapat faktor yang membuat ritme kerja tubuh bagian atas dan/atau lengan tidak dapat",
                        'index'      =>14,
                        'label'      =>"faktor"
                    ];
                }
            }

            
            
            foreach($skorDataBawah as $key => $value){
                if(is_array($value) && empty($value)){
                    unset($skorDataBawah[$key]);
                    continue; // Lanjut ke item berikutnya
                }
                if($value === "Tidak"){
                    unset($skorDataBawah[$key]);
                    continue; // Lanjut ke item berikutnya
                }
                //buat key baru
                if($value == 'Ditemukan 1 faktor Kontrol'){
                    $skorDataBawah[$key] = [
                        'rawTax' => $value, // Simpan teks aslinya (opsional)
                        'skor'       => 1,
                        'keterangan'     =>"Terdapat faktor yang membuat ritme kerja tubuh bagian atas dan/atau lengan tidak dapat dikontrol pekerja",
                        'index'      =>32,
                        'label'      =>"faktor"       // Masukkan skornya
                    ];
                }else if($value == 'Ditemukan 2 atau lebih faktor kontrol'){
                    $skorDataBawah[$key] = [
                        'rawTax' => $value,
                        'skor'       => 2,
                        'keterangan'     =>"Terdapat faktor yang membuat ritme kerja tubuh bagian atas dan/atau lengan tidak dapat dikontrol pekerja",
                        'index'      =>32,
                        'label'      =>"faktor"
                    ];
                }
            }
            $faktorResiko =$this->calculateSkorManual(optional($pengukuran->manual_handling));
            $manualHandling = $pengukuran->manual_handling;
           
            if(optional($manualHandling)->posisi_angkat_beban != null && optional($manualHandling)->estimasi_berat_benda){
                $hasilResikoBeban = $this->hitungResikoBeban(
                    $manualHandling->posisi_angkat_beban, 
                    $manualHandling->estimasi_berat_benda
                );
            }else{
                $hasilResikoBeban =null;
            }
            
            $html = View::make('ergonompotensibahaya',compact('cssGlobal','pengukuran','skorDataAtas','skorDataBawah','faktorResiko','manualHandling','hasilResikoBeban','personal','ttd'))->render();
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
                ->where('is_approve',1)
                ->orderBy('id','desc')
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
                "alamat_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->orderHeader->alamat_sampling : null,
                "tanggal_sampling" => isset($dataRwl->detail) ? Carbon::parse($dataRwl->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "no_lhp" => isset($dataRwl->detail) ? $dataRwl->detail->cfr : null,
                "periode_analisis" => (isset($dataRwl->detail) ? $dataRwl->detail->tanggal_sampling : null) . ' - ' . date('Y-m-d'),
                'jabatan' =>$dataRwl->divisi,
                'aktifitas_k3' =>json_decode($dataRwl->input_k3) ?? (object) ['uraian' => [], 'analisis_potensi_bahaya' => '', 'kesimpulan_survey_lanjutan' => '']
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
        if($pengukuran == null ||$pengukuran == "Tidak"){
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
        
        $sourceData = isset($pengukuran->faktor_resiko) ? $pengukuran->faktor_resiko : $pengukuran;
        $dataArray = json_decode(json_encode($sourceData), true);
        
        $urutanSesuaiTabel = [
            'batang_tubuh_memuntir_saat_mengangkat' => 'Batang tubuh memuntir saat mengangkat',
            'mengangkat_dengan_satu_tangan' => 'Mengangkat dengan satu tangan',
            'mengangkat_dengan_beban_yang_tidak_terduga_tidak_diprediksi' => 'Mengangkat dengan beban tidak terduga / tidak diprediksi',
            'mengangkat_1_5_kali_per_menit' => 'Mengangkat 1 - 5 kali per menit',
            'mengangkat_lebih_dari_5_kali_per_menit' => 'Mengangkat > 5 kali per menit',
            'posisi_benda_yang_diangkat_berada_di_atas_bahu' => 'Posisi benda yang diangkat berada di atas bahu',
            'posisi_benda_yang_diangkat_berada_di_bawah_posisi_siku' => 'Posisi benda yang diangkat berada di bawah posisi siku',
            'mengangkut_membawa_benda_dengan_jarak_3_9_meter' => 'Mengangkut (membawa) benda dengan jarak 3 - 9 meter',
            'mengangkut_membawa_benda_dengan_jarak_lebih_9_meter' => 'Mengangkut (membawa) benda dengan jarak > 9 meter',
            'mengangkat_benda_saat_duduk_atau_bertumpu_pada_lutut' => 'Mengangkat benda saat duduk atau bertumpu pada lutut'
        ];
        
        $dataSudahUrut = [];
        foreach ($urutanSesuaiTabel as $kunciOriginal => $labelTeks) {
            if (isset($dataArray[$kunciOriginal])) {
                $itemUntukDiproses = $dataArray[$kunciOriginal];
                
                // PENTING: Kirim label teks sebagai parameter ke-2
                $this->parseSkorRecursive($itemUntukDiproses, $labelTeks);
                
                $dataSudahUrut[$kunciOriginal] = $itemUntukDiproses;
                unset($dataArray[$kunciOriginal]);
            }
        }
        
        return $dataSudahUrut;
    }
    private function hitungRecursive(&$items, $namaKey = null)
    {
        // 1. GUARD CLAUSE: Jika items bukan array (misal string "Tidak"), stop dan return array kosong.
        if (!is_array($items)) {
            return [];
        }
      

        $hasilKalkulasi = [];

        // 2. CEK APAKAH INI NODE TARGET? (Yang punya nilai durasi)
        // Sesuaikan key dengan JSON Anda: "Durasi Gerakan" atau "durasi_gerakan"
        $keyDurasi = isset($items['Durasi Gerakan']) ? 'Durasi Gerakan' : (isset($items['durasi_gerakan']) ? 'durasi_gerakan' : null);
        $keyOvertime = isset($items['Overtime Status']) ? 'Overtime Status' : (isset($items['overtime']) ? 'overtime' : null);

        $arrayMap =[
            "leher" =>["ket"=>"Leher: memuntir atau menekuk","index"=>1,"label"=>"Postur Janggal"],
            "bahu" =>["ket"=>"Bahu: Lengan / siku yang tak ditopang di atas tinggi perut","index"=>2,"label"=>"Postur Janggal"],
            "rotasi_lengan" =>["ket"=>"Rotasi lengan bawah secara cepat","index"=>3,"label"=>"Postur Janggal"],
            "pergelangan_tangan" =>["ket"=>"Pergelangan tangan: Menekuk ke depan atau ke samping","index"=>4,"label"=>"Postur Janggal"],
            "gerakan_lengan_sedang" =>["ket"=>"Sedang: Gerakan stabil dengan jeda teratur","index"=>5,"label"=>"Gerakan Lengan"],
            "gerakan_lengan_intensif" =>["ket"=>"Intensif: Gerakan cepat yang stabil tanpa jeda teratur","index"=>6,"label"=>"Gerakan Lengan"],
            "mengetik_berselang" =>["ket"=>"Mengetik secara berselang (diselingi aktifitas / istirahat)","index"=>7,"label"=>"Penggunaan Keyboard"],
            "mengetik_intensif" =>["ket"=>"Mengetik secara Intensif","index"=>8,"label"=>"Penggunaan Keyboard"],
            "penggenggam_kuat" =>["ket"=>"Menggenggam dalam posisi <i>power grip</i> gaya > 5 kg","index"=>9,"label"=>"Usaha Tangan (Repetitif maupun Statis)"],
            "memencet_atau_menjepit" =>["ket"=>"Memencet / Menjepit benda dengan jari gaya > 1 kg","index"=>10,"label"=>"Usaha Tangan (Repetitif maupun Statis)"],
            "kuliat_tertekan" =>["ket"=>"Kulit tertekan oleh benda yang keras atau runcing","index"=>11,"label"=>"Tekanan Langsung ke bagian tubuh"],
            "menggunakan_telapak_tangan" =>["ket"=>"Menggunakan telapak atau pergelangan tangan untuk memukul","index"=>12,"label"=>"Tekanan Langsung ke bagian tubuh"],
            "getaran_lokal" =>["ket"=>"Getaran lokal (tanpa peredam)","index"=>13,"label"=>"Getaran"],
            "faktor_tidak_dapat_di_kontrol" =>["ket"=>"Terdapat faktor yang membuat ritme kerja tubuh bagian atas dan/atau lengan tidak dapat dikontrol oleh pekerja","index"=>14,"label"=>"faktor"],
            "pencahayaan" =>["ket"=>"Pencahayaan (Pencahayaan yang kurang atau silau)","index"=>15,"label"=>"Lingkungan"],
            "temperatur" =>["ket"=>"Temperatur terlalu tinggi atau rendah","index"=>16,"label"=>"Lingkungan"],
            "tubuh_membungkuk_20_45" =>["ket"=>"Tubuh membungkuk ke depan / menekuk ke samping 20 - 45°","index"=>17,"label"=>"Postur Janggal"],
            "tubuh_membungkuk_gt_45" =>["ket"=>"Tubuh membungkuk ke depan > 45°","index"=>18,"label"=>"Postur Janggal"],
            "tubuh_menekuk_30" =>["ket"=>"Tubuh menekuk ke belakang hingga 30°","index"=>19,"label"=>"Postur Janggal"],
            "tubuh_pemuntiran_torso" =>["ket"=>"Pemuntiran torso (batang tubuh)","index"=>20,"label"=>"Postur Janggal"],
            "gerakan_paha" =>["ket"=>"Gerakan paha menjauhi tubuh ke samping secara berulang-ulang","index"=>21,"label"=>"Postur Janggal"],
            "posisi_berlutut" =>["ket"=>"Posisi berlutut atau jongkok","index"=>22,"label"=>"Postur Janggal"],
            "pergelangan_kaki" =>["ket"=>"Pergelangan kaki menekuk ke atas / ke bawah secara berulang","index"=>23,"label"=>"Postur Janggal"],
            "aktivitas_pergelangan_kaki" =>["ket"=>"Aktivitas pergelangan kaki / berdiri dengan pijakan tidak memadai","index"=>24,"label"=>"Postur Janggal"],
            "duduk_tanpa_sandaran" =>["ket"=>"Duduk dalam waktu yang lama tanpa sandaran yang memadai","index"=>25,"label"=>"Postur Janggal"],
            "duduk_tanpa_pijakan" =>["ket"=>"Bekerja berdiri dalam waktu lama / duduk tanpa pijakan memadai","index"=>26,"label"=>"Postur Janggal"],
            "tubuh_tertekan_benda" =>["ket"=>"Tubuh tertekan oleh benda yang keras / runcing","index"=>27,"label"=>"Tekanan Langsung Tubuh"],
            "lutut_untuk_memukul" =>["ket"=>"Menggunakan lutut untuk memukul / menendang","index"=>28,"label"=>"Tekanan Langsung Tubuh"],
            "getaran_seluruh_tubuh" =>["ket"=>"Getaran pada seluruh tubuh (tanpa peredam)","index"=>29,"label"=>"Getaran"],
            "beban_sedang" =>["ket"=>"Beban sedang","index"=>30,"label"=>"Aktifitas Mendorong / Menarik beban"],
            "beban_berat" =>["ket"=>"Beban berat","index"=>31,"label"=>"Aktifitas Mendorong / Menarik beban"],
            "faktor_kontrol" =>["ket"=>"Terdapat faktor yang membuat ritme kerja tubuh bagian atas dan/atau lengan tidak dapat dikontrol pekerja", "index"=>32,"label"=>"x"],
        ];

        if ($keyDurasi) {
            // --- PROSES HITUNG SKOR ---
            $mapPointBagianAtas=[
                'leher'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'bahu'=>[
                    '0-25%'=>1,
                    '25-50%'=>2,
                    '50-100%'=>3
                ],
                'rotasi_lengan'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'pergelangan_tangan'=>[
                    '0-25%'=>1,
                    '25-50%'=>2,
                    '50-100%'=>3
                ],
                'gerakan_lengan_sedang'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'gerakan_lengan_intensif'=>[
                    '0-25%'=>1,
                    '25-50%'=>2,
                    '50-100%'=>3
                ],
                'mengetik_berselang'=>[
                    '0-25%'=>0,
                    '25-50%'=>0,
                    '50-100%'=>1
                ],
                'mengetik_intensif'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>3
                ],
                'penggenggam_kuat'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>3
                ],
                'memencet_atau_menjepit'=>[
                    '0-25%'=>1,
                    '25-50%'=>2,
                    '50-100%'=>3
                ],
                'kuliat_tertekan'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'menggunakan_telapak_tangan'=>[
                    '0-25%'=>1,
                    '25-50%'=>2,
                    '50-100%'=>3
                ],
                'getaran_lokal'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'faktor_tidak_dapat_di_kontrol'=>[
                    'Ditemukan 1 faktor Kontrol'=>1,
                    'Ditemukan 2 atau lebih faktor kontrol'=>2
                ],
                'pencahayaan'=>[
                    '0-25%'=>0,
                    '25-50%'=>0,
                    '50-100%'=>1
                ],
                'temperatur'=>[
                    '0-25%'=>0,
                    '25-50%'=>0,
                    '50-100%'=>1
                ]
            ];

            $mapPointBagianBawah=[
                'tubuh_membungkuk_20_45'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'tubuh_membungkuk_gt_45'=>[
                    '0-25%'=>1,
                    '25-50%'=>2,
                    '50-100%'=>3
                ],
                'tubuh_menekuk_30'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'tubuh_pemuntiran_torso'=>[
                    '0-25%'=>1,
                    '25-50%'=>2,
                    '50-100%'=>3
                ],
                'gerakan_paha'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'posisi_berlutut'=>[
                    '0-25%'=>1,
                    '25-50%'=>2,
                    '50-100%'=>3
                ],
                'pergelangan_kaki'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'aktivitas_pergelangan_kaki'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'duduk_tanpa_sandaran'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'duduk_tanpa_pijakan'=>[
                    '0-25%'=>0,
                    '25-50%'=>0,
                    '50-100%'=>1
                ],
                'tubuh_tertekan_benda'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'lutut_untuk_memukul'=>[
                    '0-25%'=>1,
                    '25-50%'=>2,
                    '50-100%'=>3
                ],
                'getaran_seluruh_tubuh'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'beban_sedang'=>[
                    '0-25%'=>0,
                    '25-50%'=>1,
                    '50-100%'=>2
                ],
                'beban_berat'=>[
                    '0-25%'=>1,
                    '25-50%'=>2,
                    '50-100%'=>3
                ],
                'faktor_kontrol'=>[
                    'Ditemukan 1 faktor Kontrol'=>1,
                    'Ditemukan 2 atau lebih faktor kontrol'=>2,
                ]
            ];
            // 1. Dapatkan string durasi (contoh: "0-25%", "Ditemukan 1 faktor Kontrol")
            $parts = explode(';', $items[$keyDurasi]);
            $durasiKey = $parts[1] ?? ($parts[0] ?? null); // Ambil bagian kedua, jika tidak ada, ambil bagian pertama.
            
            $point = 0;
            $mapDigunakan = [];
            
            // 2. Tentukan Map yang akan digunakan berdasarkan $namaKey
            if (isset($mapPointBagianAtas[$namaKey])) {
                $mapDigunakan = $mapPointBagianAtas[$namaKey];
            } elseif (isset($mapPointBagianBawah[$namaKey])) {
                $mapDigunakan = $mapPointBagianBawah[$namaKey];
            }

            // 3. Ambil nilai point dari Map
            if (!empty($mapDigunakan) && isset($mapDigunakan[$durasiKey])) {
                $point = (int)$mapDigunakan[$durasiKey];
            }
            
            // Cek overtime (handle jika string "Tidak" dianggap 0)
            $valOvertime = $items[$keyOvertime] ?? 0;
            $overtime = (is_numeric($valOvertime)) ? (float)$valOvertime : 0;
            
            $items['skor'] = $point + $overtime;

            // --- AMBIL DATA DARI MAP ARRAY ---
            // Asumsi $this->arrayMap tersedia di class ini
            if ($namaKey && isset($arrayMap[$namaKey])) {
                $items['keterangan'] = $arrayMap[$namaKey]['ket'] ?? '-';
                $items['index']      = $arrayMap[$namaKey]['index'] ?? 0;
                $items['label']      = $arrayMap[$namaKey]['label'] ?? $namaKey;
            }

            // Kembalikan item ini sebagai array tunggal agar bisa di-merge
            return [$items];
        }

        // 3. JIKA BUKAN TARGET, CARI KE DALAM ANAKNYA (REKURSIF)
        foreach ($items as $key => &$subItem) {
            // Panggil fungsi ini untuk anak-anaknya   
            if (is_string($subItem) && $subItem != "" && $key !== 'skor' && $key !== 'keterangan') {
                $subItem = []; // Mengganti string "Tidak" menjadi [] melalui referensi
                continue; // Lanjut ke item berikutnya
            }
            // 3. Lanjut Rekursi ke anak yang array
            if (is_array($subItem)) { // Pastikan hanya array yang direkursi
                $hasilAnak = $this->hitungRecursive($subItem, $key);
                
                if (!empty($hasilAnak)) {
                    $hasilKalkulasi = array_merge($hasilKalkulasi, $hasilAnak);
                }
            }
        }
        // 4. KEMBALIKAN KUMPULAN HASIL
        return $hasilKalkulasi;
    }
    // Ubah parameter dari $parentKey menjadi $parentLabel agar lebih jelas
    private function parseSkorRecursive(&$items, $parentLabel = null)
    {
        foreach ($items as $key => &$value) {
            if (is_string($value) && strpos($value, '-') !== false) {
                $parts = explode('-', $value, 2); 
                $value = [
                    'raw_text'   => $parentLabel,  // ✅ Selalu gunakan label dari parent
                    'skor'       => (int) $parts[0], 
                    'keterangan' => trim($parts[1])     
                ];
            }
            elseif (is_array($value)) {
                // ✅ Teruskan label yang sama ke level berikutnya
                $this->parseSkorRecursive($value, $parentLabel);
            }
        }
    }
    private function hitungResikoBeban($inputJarakString, $inputBeratString)
    {
    
        // --- 1. PARSING INPUT JARAK ---
        // Ubah kalimat "Pengangkatan dengan jarak dekat" menjadi "jarak_dekat"
        $jarakKey = '';
        if (stripos($inputJarakString, 'dekat') !== false) {
            $jarakKey = 'jarak_dekat';
        } elseif (stripos($inputJarakString, 'sedang') !== false) {
            $jarakKey = 'jarak_sedang';
        } elseif (stripos($inputJarakString, 'jauh') !== false) {
            $jarakKey = 'jarak_jauh';
        } else {
            return ['zona' => 'Tidak Diketahui', 'poin' => 0, 'color' => 'grey'];
        }

        // --- 2. PARSING INPUT BERAT ---
        // Ubah "Berat benda Sekitar 7 - 23 Kg" menjadi angka.
        // Kita ambil angka TERBESAR dalam string untuk keamanan (Safety Factor)
        // Contoh: "7 - 23" -> Kita ambil 23 agar masuk range "Hati-hati"
        preg_match_all('/[\d\.]+/', $inputBeratString, $matches);
        $angkaDitemukan = $matches[0] ?? [0];
        $berat = max($angkaDitemukan); // Ambil angka terbesar (misal 23)

        // --- 3. DEFINISI RULES (Sesuai Tabel) ---
        $mapArray = [
            'jarak_dekat' => [
                'label' => 'Pengangkatan dengan jarak dekat',
                'rules' => [
                    [
                        'zona'      => 'Zona Berbahaya',
                        'color'     => 'red',
                        'operator'  => '>',    
                        'limit'     => 23,
                        'poin'      => 5 
                    ],
                    [
                        'zona'      => 'Zona Hati-Hati',
                        'color'     => 'yellow',
                        'operator'  => 'between', 
                        'min'       => 7,
                        'max'       => 23,
                        'poin'      => 3
                    ],
                    [
                        'zona'      => 'Zona Aman',
                        'color'     => 'green',
                        'operator'  => '<',    
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

        // --- 4. LOGIKA PENCOCOKAN ---

        if (!isset($mapArray[$jarakKey])) { // Perbaikan: Gunakan $mapArray bukan $map
            return "Kategori jarak tidak ditemukan.";
        }

        $rules = $mapArray[$jarakKey]['rules'];

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
                // Gunakan >= dan <= agar angka batas (misal 23) masuk ke sini
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
    private function resultRwl($skor)
    {
        $tingkatResiko = '';
        $tindakan = '';
        $result = '';
        $skorNumerik = (float) $skor;
        if ($skorNumerik < 1) {
            $tingkatResiko = 'Rendah';
            $tindakan = 'Tidak ada masalah dengan pekerjaan mengangkat, maka tidak diperlukan perbaikan terhadap pekerjaan, tetapi tetap terus mendapatkan perhatian sehingga nilai LI dapat dipertahankan < 1.';
        } else if ($skorNumerik >= 1 && $skorNumerik < 3) {
            $tingkatResiko = 'Sedang';
            $tindakan = 'Ada beberapa masalah dari beberapa parameter angkat, sehingga perlu dilakukan pengecekan dan perbaikan dan redesain segera pada parameter yang menyebabkan nilai LI sedang. Upayakan perbaikan sehingga nilai LI < 1.';
        } else if ($skorNumerik >= 3) {
            $tingkatResiko = ' Tinggi';
            $tindakan = 'Terdapat banyak permasalahan dari parameter angkat, sehingga perlu dilakukan pengecekan dan perbaikan sesegera mungkin secara menyeluruh terhadap parameter-parameter yang menyebabkan nilai LI tinggi. Upayakan perbaikan sehingga nilai LI < 1.';
        } else {
            $tingkatResiko = 'Tidak Dinilai';
            $tindakan = 'Input skor tidak valid atau tidak dapat diukur.';
        }

        return ["tingkatResiko"=>$tingkatResiko,"result"=>$tindakan];
    }
}
