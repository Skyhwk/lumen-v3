<?php

namespace App\Services;

class RenderQrPsikologi 
{
    public function render($data, $qr_audiens, $qr_admin){
        try {
            $mpdf = new \App\Services\MpdfService as Mpdf([
                'orientation' => 'L',
                'format' => 'A4',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_header' => 0,
                'margin_footer' => 0,
            ]);
            
            // Gunakan path fisik file untuk mPDF (bukan URL)
            $qr_audiens_path = public_path('qr_psikologi/' . $qr_audiens);
            $qr_admin_path = public_path('qr_psikologi/' . $qr_admin);

            // Convert periode
            $periode = "";
            if($data->periode_kontrak != null){
                $periode = $data->periode_kontrak;
                $periode = explode('-', $periode);
                // dd($periode);
                $periode = self::convertPeriodeToMonthYear($periode[1], $periode[0]);
            }

            // CSS untuk tabel struktur baru
            $style = '
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    font-family: Arial, sans-serif;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                td {
                    width: 50%;
                    text-align: center;
                    vertical-align: middle;
                    padding: 15px;
                }
                .company-name {
                    font-size: 18pt;
                    font-weight: bold;
                    word-wrap: break-word;
                }
                .period {
                    font-size: 14pt;
                }
                .qr-image {
                    width: 48%;
                    height: auto;
                    margin: 0 auto;
                    display: block;
                }
                .qr-placeholder {
                    border: 2px solid #ccc;
                    width: 48%;
                    height: auto;
                    margin: 0 auto;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .qr-label {
                    margin-top: 10px;
                    font-weight: bold;
                    font-size: 16pt;
                }
            </style>';

            // Gunakan struktur tabel yang diminta
            $html = $style . '<table border="0" cellpadding="0" cellspacing="0">';
            $html .= '<tbody>';
            
            // Baris pertama: nama perusahaan
            $html .= '<tr>';
            $html .= '<td class="company-name">' . htmlspecialchars($data->nama_perusahaan) . '</td>';
            $html .= '<td class="company-name">' . htmlspecialchars($data->nama_perusahaan) . '</td>';
            $html .= '</tr>';
            
            // Baris kedua: periode
            if(isset($data->periode_kontrak)){
                $html .= '<tr>';
                $html .= '<td class="period">' . htmlspecialchars($periode) . '</td>';
                $html .= '<td class="period">' . htmlspecialchars($periode) . '</td>';
                $html .= '</tr>';
            }
            
            // Baris ketiga: gambar QR
            $html .= '<tr>';
            
            // QR Audiens (kiri)
            $html .= '<td>';
            try {
                // Gunakan data URI untuk gambar (konversi gambar ke base64)
                $qr_audiens_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($qr_audiens_path . '.png'));
                $html .= '<img src="' . $qr_audiens_base64 . '" class="qr-image" />';
            } catch (\Exception $e) {
                $html .= '<div class="qr-placeholder">QR Audiens tidak tersedia</div>';
            }
            $html .= '</td>';
            
            // QR Admin (kanan)
            $html .= '<td>';
            try {
                // Gunakan data URI untuk gambar (konversi gambar ke base64)
                $qr_admin_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($qr_admin_path . '.png'));
                $html .= '<img src="' . $qr_admin_base64 . '" class="qr-image" />';
            } catch (\Exception $e) {
                $html .= '<div class="qr-placeholder">QR Admin tidak tersedia</div>';
            }
            $html .= '</td>';
            
            $html .= '</tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            
            $mpdf->WriteHTML($html);
            
            $quot_type = explode('/', $data->no_document)[1] == 'QT' ? 'non_kontrak' : 'kontrak';
            $no_doc = str_replace('/', '_', $data->no_document);
            if($quot_type == 'kontrak'){
                $filename = 'QR_' . $no_doc . '_' . $data->periode_kontrak . '.pdf';
            }else{
                $filename = 'QR_' . $no_doc . '.pdf';
            }
            $destination = public_path('qr_psikologi/documents/');
            if (!file_exists($destination)) {
                mkdir($destination, 0777, true);
            }
            $mpdf->Output($destination. '/' . $filename, \Mpdf\Output\Destination::FILE);

            // Perbaiki URL yang dikembalikan
            return env('APP_URL') . '/public/qr_psikologi/documents/' . $filename;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    private function convertPeriodeToMonthYear($bulan, $tahun) {
        $bulans = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember'
        ];

        if (isset($bulans[$bulan])) {
            $bulan = $bulans[$bulan];
        }
        return $bulan . ' ' . $tahun;
    }
}