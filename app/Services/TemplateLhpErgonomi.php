<?php
namespace App\Services;

use \Mpdf\Mpdf as PDF;
use Illuminate\Support\Facades\View;
use App\Models\DataLapanganErgonomi;
use Carbon\Carbon;

class TemplateLhpErgonomi
{
    public function ergonomiRula($data = null,$cssGlobal='',$spesifik='',$mode='')
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
            ->first();
            $pengukuran = json_decode($dataRula->pengukuran);
            
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
                "lama_kerja" => $dataRula->lama_kerja,
                "jenis_kelamin" => $dataRula->jenis_kelamin,
                "aktivitas_ukur" => $dataRula->aktivitas_ukur,
                "nama_pelanggan" => isset($dataRula->detail) ? $dataRula->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRula->detail) ? $dataRula->detail->alamat_perusahaan : null,
                "tanggal_sampling" => isset($dataRula->detail) ? $dataRula->detail->tanggal_sampling : null,
                "no_lhp" => isset($dataRula->detail) ? $dataRula->detail->cfr : null,
                "periode_analis" => null,
            ];
           
            $pdf = new PDF($mpdfConfig);
            $html = View::make('ergonomirula',compact('pengukuran', 'personal'))->render();
            
            return $html;
            $pdf->SetFooter('Laporan Ergonomi Hal. {PAGENO}');
            $pdf->setAutoBottomMargin = 'stretch';
            // Add mPDF watermark
            $pdf->SetWatermarkText('DRAFT');
            $pdf->showWatermarkText = true;
            $pdf->watermarkTextAlpha = 0.1;
            $pdf->WriteHTML($html);
            return $pdf->Output('laporan.pdf', 'I');
        } catch (\Exception $ex) {
            throw $ex;
        }
    }
    
    public function ergonomiRwl($data = null,$cssGlobal='',$spesifik='',$mode='')
    {
        $mpdfConfig = [
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 5,
            'margin_bottom' => 15,
        ];
        $dataRula = DataLapanganErgonomi::with(['detail'])->where('no_sampel', $data->no_sampel)
            ->where('method', 5)
            ->first();
        $pengukuran = json_decode($dataRula->pengukuran);
        $avrageFrequesi = ($pengukuran->frekuensi_jumlah_awal + $pengukuran->frekuensi_jumlah_akhir) / 2;
        $pengukuran->frekuensi = $avrageFrequesi;
        $pengukuran->durasi_jam_kerja = $pengukuran->durasi_jam_kerja_akhir;
        $pengukuran->jarak_vertikal = $dataRula->jarak_vertikal;
        $pengukuran->kopling_tangan = $dataRula->kopling_tangan;
        $pengukuran->durasi_jam_kerja = $dataRula->durasi_jam_kerja;
        $pengukuran->berat_beban = $dataRula->berat_beban;
        $personal = (object) [
                "no_sampel" => $dataRula->no_sampel,
                "nama_pekerja" => $dataRula->nama_pekerja,
                "usia" => $dataRula->usia,
                "lama_kerja" => $dataRula->lama_kerja,
                "divisi" => $dataRula->divisi,
                "jenis_kelamin" => $dataRula->jenis_kelamin,
                "aktivitas_ukur" => $dataRula->aktivitas_ukur,
                "nama_pelanggan" => isset($dataRula->detail) ? $dataRula->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRula->detail) ? $dataRula->detail->alamat_perusahaan : null,
                "tanggal_sampling" => isset($dataRula->detail) ? $dataRula->detail->tanggal_sampling : null,
                "no_lhp" => isset($dataRula->detail) ? $dataRula->detail->cfr : null,
                "periode_analis" => null,
            ];
       
        $pdf = new PDF($mpdfConfig);
        $html = View::make('ergonomirwl',compact('pengukuran','personal'))->render();
        return $html;
    }

    public function ergonomiNbm($data = null,$cssGlobal='',$spesifik='',$mode='')
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
                ->first();
    
            $pengukuran = json_decode($dataRwl->pengukuran);
            $sebelumKerja = json_decode($dataRwl->sebelum_kerja);
            $setelahKerja = json_decode($dataRwl->setelah_kerja);
            $personal = (object) [
                "no_sampel" => $dataRwl->no_sampel,
                "nama_pekerja" => $dataRwl->nama_pekerja,
                "usia" => $dataRwl->usia,
                "lama_kerja" => $dataRwl->lama_kerja,
                "jenis_kelamin" => $dataRwl->jenis_kelamin,
                "aktivitas_ukur" => $dataRwl->aktivitas_ukur,
                "nama_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->alamat_perusahaan : null,
                "tanggal_sampling" => isset($dataRwl->detail) ? $dataRwl->detail->tanggal_sampling : null,
                "no_lhp" => isset($dataRwl->detail) ? $dataRwl->detail->cfr : null,
                "periode_analisis" => null,
            ];
            // dd($pengukuran,$sebelumKerja,$setelahKerja,$personal);
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
                # code...
                $skor = $pengukuran->$waktu->total_skor;
                [$tingkatResiko, $kategoriResiko, $tindakan] = $this->hitungResiko($skor, 'nbm');
                $pengukuran->$waktu->tingkat_resiko = $tingkatResiko;
                $pengukuran->$waktu->kategori_resiko = $kategoriResiko;
                $pengukuran->$waktu->tindakan = $tindakan;
            }
    
            $personal = (object) [
                "no_sampel" => $dataRwl->no_sampel,
                "nama_pekerja" => $dataRwl->nama_pekerja,
                "usia" => $dataRwl->usia,
                "lama_kerja" => $dataRwl->lama_kerja,
                "jenis_kelamin" => $dataRwl->jenis_kelamin,
                "aktivitas_ukur" => $dataRwl->aktivitas_ukur,
                "nama_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->alamat_perusahaan : null,
                "tanggal_sampling" => isset($dataRwl->detail) ? $dataRwl->detail->tanggal_sampling : null,
                "no_lhp" => isset($dataRwl->detail) ? $dataRwl->detail->cfr : null,
                "periode_analisis" => null,
            ];
            
            $pdf = new PDF($mpdfConfig);
            $html = View::make('ergonominbm', compact('pengukuran', 'personal','spesifik'))->render();
            return $html;  
        }catch (ViewException $e) {
            return "<p style='color:red'>View <b>ergonomgontrak</b> tidak ditemukan!</p>";
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function ergonomiReba($data = null,$cssGlobal ='',$spesifik ='',$mode='')
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
                ->first();
    
            $pengukuran = json_decode($dataReba->pengukuran);
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
                "nama_pelanggan" => isset($dataReba->detail) ? $dataReba->detail->nama_perusahaan : '-',
                "alamat_pelanggan" => isset($dataReba->detail) ? $dataReba->detail->alamat_perusahaan : '-',
                "tanggal_sampling" => isset($dataReba->detail) ? Carbon::parse($dataReba->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "no_lhp" => isset($dataReba->detail) ? $dataReba->detail->cfr : '-',
                "jenis_sampel" => isset($dataReba->detail) ? explode('-', $dataReba->detail->kategori_3)[1] : '-',
                "periode_analisis" => '-',
                "deskripsi_pekerjaan" => $dataReba->aktivitas_ukur
            ];
            $pdf = new PDF($mpdfConfig);
            $html = View::make('ergonomireba', compact('pengukuran', 'personal','cssGlobal','spesifik'))->render();
            return $html;
        } catch (ViewException $e) {
            return "<p style='color:red'>View <b>ergonomgontrak</b> tidak ditemukan!</p>";
        } catch (\Throwable $th) {
            throw $th;
        }
        
    }

    public function ergonomiRosa($data = null,$cssGlobal='',$spesifik='',$mode='')
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
                ->first();
    
            $pengukuran = json_decode($dataRosa->pengukuran);
            $skor = $pengukuran->final_skor_rosa;
            $skor = 5;
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
            $pengukuran->kategori_resiko = $kategoriResiko;
            $pengukuran->tindakan = $tindakan;
            $pengukuran->result = $result;
            $personal = (object) [
                "no_lhp" => isset($dataRosa->detail) ? $dataRosa->detail->cfr : '-',
                "no_sampel" => $dataRosa->no_sampel,
                "jenis_sampel" => isset($dataRosa->detail) ? explode('-', $dataRosa->detail->kategori_3)[1] : '-',
                "nama_pelanggan" => isset($dataRosa->detail) ? $dataRosa->detail->nama_perusahaan : '-',
                "alamat_pelanggan" => isset($dataRosa->detail) ? $dataRosa->detail->alamat_perusahaan : '-',
                "tanggal_sampling" => isset($dataRosa->detail) ? Carbon::parse($dataRosa->detail->tanggal_sampling)->locale('id')->isoFormat('DD MMMM YYYY') : null,
                "periode_analisis" => '-',
                "nama_pekerja" => $dataRosa->nama_pekerja,
                "aktivitas_ukur" => $dataRosa->aktivitas_ukur,
                "usia" => $dataRosa->usia,
                "lama_kerja" => json_decode($dataRosa->lama_kerja),
            ];
            
            $pdf = new PDF($mpdfConfig);
            $html = View::make('ergonomirosa', compact('pengukuran', 'personal'))->render();
            return $html;
        }catch (ViewException $e) {
            return "<p style='color:red'>View <b>ergonomgontrak</b> tidak ditemukan!</p>";
        } catch (\Throwable $th) {
            throw $th;
        }
    }
    
    public function ergonomiBrief($data = null,$cssGlobal='',$spesifik='',$mode='')
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

    public function ergonomiPotensiBahaya ($data = null,$cssGlobal ='',$spesifik ='',$mode='')
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
                ->first();
            $personal = (object) [
                "no_sampel" => $dataRwl->no_sampel,
                "nama_pekerja" => $dataRwl->nama_pekerja,
                "usia" => $dataRwl->usia,
                "lama_kerja" => $dataRwl->lama_kerja,
                "jenis_kelamin" => $dataRwl->jenis_kelamin,
                "aktivitas_ukur" => $dataRwl->aktivitas_ukur,
                "nama_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->alamat_perusahaan : null,
                "tanggal_sampling" => isset($dataRwl->detail) ? $dataRwl->detail->tanggal_sampling : null,
                "no_lhp" => isset($dataRwl->detail) ? $dataRwl->detail->cfr : null,
                "periode_analis" => (isset($dataRwl->detail) ? $dataRwl->detail->tanggal_sampling : null) . ' - ' . date('Y-m-d'),
                'jabatan' =>$dataRwl->divisi,
                'aktifitas_k3' =>json_decode($dataRwl->input_k3)
            ];
    
            $pengukuran = json_decode($dataRwl->pengukuran,true);
            $dataAtas  = $this->flattenPengukuran("Tubuh Bagian Atas", $pengukuran['Tubuh_Bagian_Atas']);
            $dataBawah = $this->flattenPengukuran("Tubuh Bagian Bawah", $pengukuran['Tubuh_Bagian_Bawah']);
            $groupedAtas  = $this->groupByKategori($dataAtas);
            $groupedBawah  = $this->groupByKategori($dataBawah);
            // dd($personal);
            $html = View::make('ergonompotensibahaya',compact('cssGlobal','pengukuran','dataAtas','groupedAtas','groupedBawah','personal'))->render();
            return $html;
        } catch (ViewException $e) {
            return "<p style='color:red'>View <b>ergonomgontrak</b> tidak ditemukan!</p>";
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function ergonomiGontrak ($data = null,$cssGlobal ='',$spesifik ='',$mode='')
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
    
            $pengukuran = json_decode($dataRwl->pengukuran);
            $sebelumKerja = json_decode($dataRwl->sebelum_kerja);
            $setelahKerja = json_decode($dataRwl->setelah_kerja);
            $personal = (object) [
                "no_sampel" => $dataRwl->no_sampel,
                "nama_pekerja" => $dataRwl->nama_pekerja,
                "usia" => $dataRwl->usia,
                "lama_kerja" => $dataRwl->lama_kerja,
                "jenis_kelamin" => $dataRwl->jenis_kelamin,
                "aktivitas_ukur" => $dataRwl->aktivitas_ukur,
                "nama_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->nama_perusahaan : null,
                "alamat_pelanggan" => isset($dataRwl->detail) ? $dataRwl->detail->alamat_perusahaan : null,
                "tanggal_sampling" => isset($dataRwl->detail) ? $dataRwl->detail->tanggal_sampling : null,
                "no_lhp" => isset($dataRwl->detail) ? $dataRwl->detail->cfr : null,
                "periode_analis" => (isset($dataRwl->detail) ? $dataRwl->detail->tanggal_sampling : null) . ' - ' . date('Y-m-d'),
                'jabatan' =>$dataRwl->divisi,
                'aktifitas_k3' =>json_decode($dataRwl->input_k3)
            ];
            
            $masa_kerja = $pengukuran->Identitas_Umum->{'Masa Kerja'};
            $fisik = $pengukuran->Identitas_Umum->{'Lelah Fisik'};
            $mental = $pengukuran->Identitas_Umum->{'Lelah Mental'};
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
    
            $pengukuran->Identitas_Umum->{'Masa Kerja'} =$masa_kerja_map[$masa_kerja] ?? 'Unknow';
            $pengukuran->Identitas_Umum->{'Lelah Mental'} =$fisikMentalMap[$mental] ?? 'Unknow';
            $pengukuran->Identitas_Umum->{'Lelah Fisik'} =$fisikMentalMap[$fisik] ?? 'Unknow';
            
            $html = View::make('ergonomgontrak',compact('pengukuran','personal','cssGlobal','spesifik'))->render();
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

        foreach ($data as $kategori => $subdata) {
            if (is_iterable($subdata)) {
                foreach ($subdata as $potensi => $value) {
                    
                    // kalau value langsung string
                    if (is_string($value)) {
                        $result[] = [
                            'section'  => $sectionName,
                            'kategori' => $kategori,
                            'potensi'  => $potensi,
                            'skor'     => $value,
                        ];
                    }

                    // kalau value array/object
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

                            // kalau masih nested lagi
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
        }

        return $result;
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
