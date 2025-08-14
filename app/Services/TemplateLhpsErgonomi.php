<?php
namespace App\Services;

use \Mpdf\Mpdf as PDF;
use Illuminate\Support\Facades\View;
use App\Models\DataLapanganErgonomi;
use Carbon\Carbon;

class TemplateLhpsErgonomi
{
    public function ergonomiRula($data = null)
    {
        $mpdfConfig = [
            'mode' => 'utf-8',
            'format' => 'A4-L', // A4 Landscape format
            'margin_header' => 8, // Reduced from 13
            'margin_bottom' => 12, // Reduced from 17
            'margin_footer' => 5, // Reduced from 8
            'margin_top' => 15, // Reduced from 23.5
            'margin_left' => 10,
            'margin_right' => 10,
            'orientation' => 'L',
        ];

        // olah data
        $dataRula = DataLapanganErgonomi::with(['detail'])->where('no_sampel', 'T2PE012502/007')
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
        $html = View::make('ergonomirula', compact('pengukuran', 'personal'))->render();

        // --- TAMBAHKAN NOMOR HALAMAN DI SINI ---
        // Format: Teks biasa 'Halaman {PAGENO} dari {nb}'
        // {PAGENO} = nomor halaman saat ini
        // {nb} = total jumlah halaman
        $footer = '<table width="100%" border="0">
                        <tr>
                            <td width="13%"></td>
                            <td colspan="2" style="font-family:Arial, sans-serif; font-size:x-small;"> Hasil uji ini hanya
                                berlaku untuk sampel yang diuji. Lembar ini tidak boleh diubah ataupun digandakan tanpa
                                izin tertulis dari pihak laboratorium.
                            </td>
                            <td width="13%" style="font-size:xx-small; font-weight: bold; text-align: right"><i>Page {PAGENO} of {nb}</i></td>
                        </tr>
                    </table>';
        // <td width="13%" style="font-size:xx-small; font-weight: bold"><i>Laporan Ergonomi Hal. {PAGENO} dari {nb}</i></td> --}}

        // $pdf->SetFooter('Laporan Ergonomi Hal. {PAGENO}');
        $pdf->SetFooter($footer);
        $pdf->setAutoBottomMargin = 'stretch';
        // Add mPDF watermark
        $pdf->SetWatermarkText('DRAFT');
        $pdf->showWatermarkText = true;
        $pdf->watermarkTextAlpha = 0.1;
        $pdf->WriteHTML($html);
        return $pdf->Output('laporan.pdf', 'I');
    }

    public function ergonomiRwl($data = null)
    {
        $mpdfConfig = [
            'mode' => 'utf-8',
            'format' => 'A4-L', // A4 Landscape format
            'margin_header' => 8, // Reduced from 13
            'margin_bottom' => 12, // Reduced from 17
            'margin_footer' => 5, // Reduced from 8
            'margin_top' => 15, // Reduced from 23.5
            'margin_left' => 10,
            'margin_right' => 10,
            'orientation' => 'L',
        ];
        $pdf = new PDF($mpdfConfig);
        $html = View::make('ergonomirwl')->render();
        // --- TAMBAHKAN NOMOR HALAMAN DI SINI ---
        // Format: Teks biasa 'Halaman {PAGENO} dari {nb}'
        // {PAGENO} = nomor halaman saat ini
        // {nb} = total jumlah halaman
        $footer = '<table width="100%" border="0">
                        <tr>
                            <td width="13%"></td>
                            <td colspan="2" style="font-family:Arial, sans-serif; font-size:x-small;"> Hasil uji ini hanya
                                berlaku untuk sampel yang diuji. Lembar ini tidak boleh diubah ataupun digandakan tanpa
                                izin tertulis dari pihak laboratorium.
                            </td>
                            <td width="13%" style="font-size:xx-small; font-weight: bold; text-align: right"><i>Page {PAGENO} of {nb}</i></td>
                        </tr>
                    </table>';
        // <td width="13%" style="font-size:xx-small; font-weight: bold"><i>Laporan Ergonomi Hal. {PAGENO} dari {nb}</i></td> --}}

        // $pdf->SetFooter('Laporan Ergonomi Hal. {PAGENO}');
        $pdf->SetFooter($footer);
        $pdf->setAutoBottomMargin = 'stretch';
        // Add mPDF watermark
        $pdf->SetWatermarkText('DRAFT');
        $pdf->showWatermarkText = true;
        $pdf->watermarkTextAlpha = 0.1;
        $pdf->WriteHTML($html);
        return $pdf->Output('laporan.pdf', 'I');
    }

    public function ergonomiNbm($data = null)
    {
        $mpdfConfig = [
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 5,
            'margin_bottom' => 15,
        ];

        // olah data:
        $dataRwl = DataLapanganErgonomi::with(['detail'])->where('no_sampel', 'TAML012401/395')
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
            "periode_analis" => null,
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
            "periode_analis" => null,
        ];

        $pdf = new PDF($mpdfConfig);
        $html = View::make('ergonominbm', compact('pengukuran', 'personal'))->render();
        // --- TAMBAHKAN NOMOR HALAMAN DI SINI ---
        // Format: Teks biasa 'Halaman {PAGENO} dari {nb}'
        // {PAGENO} = nomor halaman saat ini
        // {nb} = total jumlah halaman
        $footer = '<table width="100%" border="0">
                        <tr>
                            <td width="13%"></td>
                            <td colspan="2" style="font-family:Arial, sans-serif; font-size:x-small;"> Hasil uji ini hanya
                                berlaku untuk sampel yang diuji. Lembar ini tidak boleh diubah ataupun digandakan tanpa
                                izin tertulis dari pihak laboratorium.
                            </td>
                            <td width="13%" style="font-size:xx-small; font-weight: bold; text-align: right"><i>Page {PAGENO} of {nb}</i></td>
                        </tr>
                    </table>';
        // <td width="13%" style="font-size:xx-small; font-weight: bold"><i>Laporan Ergonomi Hal. {PAGENO} dari {nb}</i></td> --}}

        // $pdf->SetFooter('Laporan Ergonomi Hal. {PAGENO}');
        $pdf->SetFooter($footer);
        $pdf->setAutoBottomMargin = 'stretch';
        // Add mPDF watermark
        $pdf->SetWatermarkText('DRAFT');
        $pdf->showWatermarkText = true;
        $pdf->watermarkTextAlpha = 0.1;
        $pdf->WriteHTML($html);
        return $pdf->Output('laporan.pdf', 'I');
    }

    public function ergonomiReba($data = null)
    {
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
            ->where('no_sampel', 'TAML012401/395')
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
        // dd($dataReba->aktivitas_ukur);
        // dd(explode('-',$dataReba->detail->kategori_3)[1]);

        $pdf = new PDF($mpdfConfig);
        $html = View::make('ergonomireba', compact('pengukuran', 'personal'))->render();

        // --- TAMBAHKAN NOMOR HALAMAN DI SINI ---
        // Format: Teks biasa 'Halaman {PAGENO} dari {nb}'
        // {PAGENO} = nomor halaman saat ini
        // {nb} = total jumlah halaman
        $footer = '<table width="100%" border="0">
                        <tr>
                            <td width="13%"></td>
                            <td colspan="2" style="font-family:Arial, sans-serif; font-size:x-small;"> Hasil uji ini hanya
                                berlaku untuk sampel yang diuji. Lembar ini tidak boleh diubah ataupun digandakan tanpa
                                izin tertulis dari pihak laboratorium.
                            </td>
                            <td width="13%" style="font-size:xx-small; font-weight: bold; text-align: right"><i>Page {PAGENO} of {nb}</i></td>
                        </tr>
                    </table>';
        // <td width="13%" style="font-size:xx-small; font-weight: bold"><i>Laporan Ergonomi Hal. {PAGENO} dari {nb}</i></td> --}}

        // $pdf->SetFooter('Laporan Ergonomi Hal. {PAGENO}');
        $pdf->SetFooter($footer);
        $pdf->setAutoBottomMargin = 'stretch';
        $pdf->SetWatermarkText('DRAFT');
        $pdf->showWatermarkText = true;
        $pdf->watermarkTextAlpha = 0.1;
        $pdf->WriteHTML($html);
        return $pdf->Output('laporan.pdf', 'I');

    }

    public function ergonomiRosa($data = null)
    {
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
            ->where('no_sampel', 'TAML012401/395')
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
        ];
        // dd($pengukuran);
        $pdf = new PDF($mpdfConfig);
        $html = View::make('ergonomirosa', compact('pengukuran', 'personal'))->render();

        // --- TAMBAHKAN NOMOR HALAMAN DI SINI ---
        // Format: Teks biasa 'Halaman {PAGENO} dari {nb}'
        // {PAGENO} = nomor halaman saat ini
        // {nb} = total jumlah halaman
        $footer = '<table width="100%" border="0">
                        <tr>
                            <td width="13%"></td>
                            <td colspan="2" style="font-family:Arial, sans-serif; font-size:x-small;"> Hasil uji ini hanya
                                berlaku untuk sampel yang diuji. Lembar ini tidak boleh diubah ataupun digandakan tanpa
                                izin tertulis dari pihak laboratorium.
                            </td>
                            <td width="13%" style="font-size:xx-small; font-weight: bold; text-align: right"><i>Page {PAGENO} of {nb}</i></td>
                        </tr>
                    </table>';
        // <td width="13%" style="font-size:xx-small; font-weight: bold"><i>Laporan Ergonomi Hal. {PAGENO} dari {nb}</i></td> --}}

        // $pdf->SetFooter('Laporan Ergonomi Hal. {PAGENO}');
        $pdf->SetFooter($footer);
        $pdf->setAutoBottomMargin = 'stretch';
        $pdf->SetWatermarkText('DRAFT');
        $pdf->showWatermarkText = true;
        $pdf->watermarkTextAlpha = 0.1;
        $pdf->WriteHTML($html);
        return $pdf->Output('laporan.pdf', 'I');
    }

    public function ergonomiBrief($data = null)
    {
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
        $pdf = new PDF($mpdfConfig);
        $html = View::make('ergonomibrief')->render();

        // --- TAMBAHKAN NOMOR HALAMAN DI SINI ---
        // Format: Teks biasa 'Halaman {PAGENO} dari {nb}'
        // {PAGENO} = nomor halaman saat ini
        // {nb} = total jumlah halaman
        $footer = '<table width="100%" border="0">
                        <tr>
                            <td width="13%"></td>
                            <td colspan="2" style="font-family:Arial, sans-serif; font-size:x-small;"> Hasil uji ini hanya
                                berlaku untuk sampel yang diuji. Lembar ini tidak boleh diubah ataupun digandakan tanpa
                                izin tertulis dari pihak laboratorium.
                            </td>
                            <td width="13%" style="font-size:xx-small; font-weight: bold; text-align: right"><i>Page {PAGENO} of {nb}</i></td>
                        </tr>
                    </table>';
        // <td width="13%" style="font-size:xx-small; font-weight: bold"><i>Laporan Ergonomi Hal. {PAGENO} dari {nb}</i></td> --}}

        // $pdf->SetFooter('Laporan Ergonomi Hal. {PAGENO}');
        $pdf->SetFooter($footer);
        $pdf->setAutoBottomMargin = 'stretch';
        $pdf->SetWatermarkText('DRAFT');
        $pdf->showWatermarkText = true;
        $pdf->watermarkTextAlpha = 0.1;
        $pdf->WriteHTML($html);
        return $pdf->Output('laporan.pdf', 'I');
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
}
