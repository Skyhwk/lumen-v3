<?php

namespace App\Services\SelfRegistration;

use Mpdf\Mpdf;

class StatementPdfService
{
    const NAVY = '#1a4f8b';

    public function storeFromPayload($registrationId, $signatureBinary, array $payload)
    {
        $relDir = 'self-registration/' . (int) $registrationId;
        $absDir = storage_path('app/' . $relDir);
        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new \RuntimeException('Gagal membuat folder dokumen');
        }

        $sigRel = $relDir . '/signature.png';
        $pdfRel = $relDir . '/surat-pernyataan.pdf';
        $sigAbs = storage_path('app/' . $sigRel);
        $pdfAbs = storage_path('app/' . $pdfRel);

        if (file_put_contents($sigAbs, $signatureBinary) === false) {
            throw new \RuntimeException('Gagal menyimpan tanda tangan');
        }

        $payload['signature_src'] = str_replace('\\', '/', $sigAbs);
        $payload['dokumen_hash'] = $payload['dokumen_hash'] ?? 'pending';

        $this->generatePdf($payload, $pdfAbs);
        $dokHash = strtoupper(substr(hash('sha256', (string) file_get_contents($pdfAbs)), 0, 12));
        $payload['dokumen_hash'] = $dokHash;
        $this->generatePdf($payload, $pdfAbs);

        return [
            'signature_path' => $sigRel,
            'pdf_path' => $pdfRel,
            'dokumen_hash' => $dokHash,
        ];
    }

    public function readPdf($pdfRel)
    {
        $pdfRel = str_replace(['..', '\\'], ['', '/'], (string) $pdfRel);
        if ($pdfRel === '' || strpos($pdfRel, 'self-registration/') !== 0) {
            return null;
        }
        $abs = storage_path('app/' . $pdfRel);
        if (!is_file($abs)) {
            return null;
        }

        return (string) file_get_contents($abs);
    }

    public function renderHtml(array $payload)
    {
        $rows = '';
        $no = 1;
        foreach ($payload['companies'] as $company) {
            $rows .= '<tr>'
                . '<td style="border:1px solid #c5d0dc;padding:5px 8px;text-align:center;background:transparent;">' . $no . '</td>'
                . '<td style="border:1px solid #c5d0dc;padding:5px 8px;background:transparent;">' . $this->e($company['id_pelanggan']) . '</td>'
                . '<td style="border:1px solid #c5d0dc;padding:5px 8px;background:transparent;">' . $this->e($company['nama_pelanggan']) . '</td>'
                . '</tr>';
            $no++;
        }

        $signature = '';
        if (!empty($payload['signature_src'])) {
            $signature = '<img src="' . $payload['signature_src'] . '" alt="TTD" style="height:20mm;max-width:42mm;display:block;margin:4px auto 2px;">';
        } else {
            $signature = '<div style="height:20mm;"></div>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head>
<body style="font-family: serif; font-size: 10.5pt; color:#1a1a1a; line-height:1.5;">
' . $this->fieldTable([
            ['Nomor', '<b>' . $this->e($payload['nomor_dokumen'] ?? '') . '</b>'],
            ['Perihal', '<b>Pendaftaran Akun Portal Pelanggan Intilab</b>'],
        ], '14px') . '
<p style="margin:0 0 8px;">Yang bertanda tangan di bawah ini:</p>
' . $this->fieldTable([
            ['Nama Lengkap', $this->e($payload['nama_pic'])],
            ['Nomor Kontak', $this->e($payload['no_kontak'] ?: '-')],
            ['Alamat <i>Email</i>', $this->e($payload['email'])],
        ], '12px') . '

<p style="margin:0 0 8px;">dalam kapasitas sebagai <i>Person in Charge</i> (<i>PIC</i>) yang mewakili perusahaan berikut:</p>
<table width="100%" style="border-collapse:collapse;margin:0 0 12px;background:transparent;">
<tr>
<th style="border:1px solid #c5d0dc;padding:6px 8px;background:transparent;color:#111;width:12%;">No</th>
<th style="border:1px solid #c5d0dc;padding:6px 8px;background:transparent;color:#111;width:28%;"><i>ID</i> Pelanggan</th>
<th style="border:1px solid #c5d0dc;padding:6px 8px;background:transparent;color:#111;">Nama Perusahaan</th>
</tr>
' . $rows . '
</table>

<p style="margin:0 0 6px;">dengan ini menyatakan dengan sebenar-benarnya bahwa:</p>
<ol style="padding-left:18px;margin:0 0 12px;">
<li>Saya adalah <i>PIC</i> yang sah dan berwenang mewakili perusahaan di atas dalam hubungan kerja sama dengan Intilab, dan alamat <i>email</i> yang saya gunakan adalah <i>email</i> resmi yang telah saya berikan kepada Intilab.</li>
<li>Seluruh data dan informasi yang tercantum dalam pernyataan ini adalah benar, lengkap, dan dapat dipertanggungjawabkan.</li>
<li>Saya bertanggung jawab penuh atas kerahasiaan kredensial akun (<i>email</i> dan kata sandi) serta seluruh aktivitas yang dilakukan melalui akun Portal Pelanggan atas nama saya.</li>
<li>Saya akan menggunakan Portal Pelanggan hanya untuk kepentingan perusahaan yang saya wakili dan sesuai dengan ketentuan yang berlaku di Intilab.</li>
<li>Saya tidak akan memberikan, meminjamkan, atau mengalihkan akses akun kepada pihak lain yang tidak berwenang.</li>
<li>Saya akan segera memberitahukan Intilab apabila terjadi perubahan <i>PIC</i>, perubahan data, atau dugaan penyalahgunaan akun.</li>
<li>Saya menyetujui pemrosesan data pribadi saya oleh Intilab untuk keperluan pengelolaan akun dan layanan, sesuai dengan Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi.</li>
<li>Saya memahami bahwa Intilab berhak menangguhkan atau menonaktifkan akun apabila ditemukan ketidaksesuaian data atau pelanggaran atas pernyataan ini.</li>
</ol>

<p>Demikian surat pernyataan ini saya buat dengan sadar dan tanpa paksaan dari pihak mana pun, untuk dipergunakan sebagaimana mestinya.</p>

<table width="100%" style="margin-top:10px;">
<tr>
<td style="width:58%;"></td>
<td style="width:42%;text-align:center;">
' . $this->e($payload['tanggal_lengkap']) . '<br>
Yang membuat pernyataan,<br>
' . $signature . '
<div style="border-top:0.6px solid ' . self::NAVY . ';width:78%;margin:0 auto 4px;"></div>
<b>' . $this->e($payload['nama_pic']) . '</b><br>
<span style="font-size:9pt;"><i>PIC</i> ' . $this->e($payload['nama_pelanggan_utama']) . '</span>
</td>
</tr>
</table>
</body></html>';
    }

    public function generatePdf(array $payload, $absolutePath)
    {
        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_top' => 32,
            'margin_header' => 8,
            'margin_bottom' => 30,
            'margin_footer' => 4,
            'margin_left' => 16,
            'margin_right' => 16,
            'default_font' => 'times',
        ]);
        $mpdf->SetTitle('Surat Pernyataan Portal Pelanggan');
        $mpdf->SetAuthor('PT Inti Surya Laboratorium');
        $mpdf->SetHTMLHeader($this->headerHtml($payload));
        $mpdf->SetHTMLFooter($this->footerHtml($payload));

        $watermark = $this->watermarkPath();
        if ($watermark) {
            $mpdf->SetWatermarkImage($watermark, -1, '', [65, 60]);
            $mpdf->showWatermarkImage = true;
        }

        $mpdf->WriteHTML($this->renderHtml($payload));
        $mpdf->Output($absolutePath, \Mpdf\Output\Destination::FILE);

        return $absolutePath;
    }

    private function headerHtml(array $payload)
    {
        $logo = $this->logoTag();

        return '<table width="100%" style="border-collapse:collapse;">
<tr>
<td style="width:48%;vertical-align:middle;">' . $logo . '</td>
<td style="width:52%;text-align:right;vertical-align:middle;font-size:7.5pt;line-height:1.35;color:#222;">
<b>PT INTI SURYA LABORATORIUM</b><br>
Ruko Icon Business Park blok O no 5 - 6, BSD City<br>
Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang<br>
T : 021-50898988/89 - sales@intilab.com<br>
www.intilab.com
</td>
</tr>
</table>
<div style="border-top:2.2px solid ' . self::NAVY . ';border-bottom:0.6px solid ' . self::NAVY . ';margin-top:5px;height:3px;"></div>';
    }

    private function footerHtml(array $payload)
    {
        $footerNote = 'Dokumen ini ditandatangani secara elektronik melalui Portal Pelanggan Intilab '
            . 'dan memiliki kekuatan hukum sesuai UU No. 11 Tahun 2008 tentang Informasi dan Transaksi Elektronik beserta perubahannya. '
            . 'No. Dokumen: ' . $this->e($payload['nomor_dokumen'] ?? '')
            . ' | Ditandatangani: ' . $this->e($payload['timestamp_wib'] ?? '-')
            . ' | Kode Verifikasi: ' . $this->e($payload['dokumen_hash'] ?? '-');

        $generatedAt = $payload['generated_at'] ?? $payload['timestamp_wib'] ?? '-';

        return '<div style="border-top:1.6px solid ' . self::NAVY . ';padding-top:3px;">
<table width="100%" style="border-collapse:collapse;">
<tr>
<td style="width:70%;text-align:left;vertical-align:bottom;font-size:7pt;color:#555;">
' . $this->e($generatedAt) . '
</td>
<td style="width:30%;text-align:right;vertical-align:bottom;font-size:7pt;font-style:italic;color:#606060;white-space:nowrap;">
Halaman {PAGENO} dari {nbpg}
</td>
</tr>
</table>
<div style="font-size:5.5pt;color:#777;margin-top:2px;line-height:1.3;text-align:left;">' . $this->e($footerNote) . '</div>
</div>';
    }

    private function watermarkPath()
    {
        $path = public_path('logo-watermark.png');
        return is_file($path) ? str_replace('\\', '/', $path) : null;
    }

    private function logoPath()
    {
        $candidates = [
            public_path('img/isl_logo.png'),
            public_path('isl_logo.png'),
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return str_replace('\\', '/', $path);
            }
        }

        return null;
    }

    private function logoTag()
    {
        $path = $this->logoPath();
        if ($path) {
            return '<img src="' . $path . '" alt="Intilab" style="height:15mm;">';
        }

        return '<div style="font-size:14pt;font-weight:bold;color:' . self::NAVY . ';">INTILAB</div>';
    }

    private function fieldTable(array $rows, $marginBottom)
    {
        $html = '<table style="width:1px;border-collapse:collapse;margin:0 0 ' . $marginBottom . ';line-height:1.25;"><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td style="border:none;padding:0 4mm 0 0;white-space:nowrap;vertical-align:top;">' . $row[0] . '</td>'
                . '<td style="border:none;padding:0;width:4mm;white-space:nowrap;vertical-align:top;">:&nbsp;</td>'
                . '<td style="border:none;padding:0;white-space:nowrap;vertical-align:top;">' . $row[1] . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
