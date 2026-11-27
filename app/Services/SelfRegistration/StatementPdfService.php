<?php

namespace App\Services\SelfRegistration;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

class StatementPdfService
{
    const RULE_COLOR = '#bfbfbf';

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

        $this->generatePdf($payload, $pdfAbs);
        $dokHash = strtoupper(substr(hash('sha256', (string) file_get_contents($pdfAbs)), 0, 12));

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
        $companies = isset($payload['companies']) && is_array($payload['companies']) ? $payload['companies'] : [];
        foreach ($companies as $company) {
            $rows .= '<tr>'
                . '<td style="border:1px solid #000;padding:5px 8px;text-align:center;background:transparent;">' . $no . '</td>'
                . '<td style="border:1px solid #000;padding:5px 8px;background:transparent;">' . $this->e($company['id_pelanggan'] ?? '') . '</td>'
                . '<td style="border:1px solid #000;padding:5px 8px;background:transparent;">' . $this->e($company['nama_pelanggan'] ?? '') . '</td>'
                . '</tr>';
            $no++;
        }

        $emailEsc = $this->e($payload['email'] ?? '');
        $namaPic = trim((string) ($payload['nama_pic'] ?? ''));
        $jabatanPic = trim((string) ($payload['jabatan_pic'] ?? ''));
        $noKontak = trim((string) ($payload['no_kontak'] ?? ''));
        $jabatanSign = $jabatanPic !== ''
            ? $jabatanPic
            : ('PIC ' . trim((string) ($payload['nama_pelanggan_utama'] ?? '')));

        if (!empty($payload['signature_src'])) {
            $signature = '<img src="' . $payload['signature_src'] . '" alt="TTD" style="height:20mm;max-width:55mm;display:block;margin:6px 0 6px;">';
        } else {
            $signature = '<div style="height:22mm;"></div>';
        }

        $points = [
            'Saya secara sah dan berwenang bertindak untuk dan atas nama perusahaan / instansi tersebut, serta telah memperoleh persetujuan atau kuasa dari pihak yang berwenang untuk melakukan pendaftaran dan bertindak sebagai Penanggung Jawab / <i>Person In Charge</i> (PIC) Pengelolaan Portal Pelanggan Intilab.',
            'Dengan diselesaikannya pendaftaran ini, saya secara otomatis ditetapkan sebagai PIC dan berwenang untuk mengakses, menggunakan, dan mengelola Portal Pelanggan Intilab; menerima, mengakses, dan mengelola seluruh data serta dokumen layanan; melakukan persetujuan dan/atau tindakan hukum melalui sistem elektronik; serta bertindak untuk dan atas nama perusahaan / instansi dalam seluruh aktivitas pada Portal Pelanggan Intilab.',
            'Seluruh data dan informasi yang saya sampaikan pada saat pendaftaran adalah benar, lengkap, dan sah secara hukum.',
            '<i>Email</i> yang saya gunakan saat pendaftaran ' . $emailEsc . ' ditetapkan sebagai <i>email</i> utama (<i>main email</i>), yaitu identitas resmi perusahaan / instansi dalam Portal Pelanggan Intilab, sarana penerimaan notifikasi sistem termasuk <i>password</i>, dan sarana persetujuan transaksi serta dokumen elektronik. Saya memahami dan menyetujui bahwa <i>email</i> tersebut merupakan bentuk autentikasi utama dalam sistem, sehingga setiap aktivitas yang dilakukan melaluinya dianggap sah dan mewakili perusahaan / instansi tanpa memerlukan verifikasi tambahan.',
            'Saya akan menjalankan kewenangan sesuai dengan penunjukan sebagai PIC dan tidak melampaui batas kewenangan yang diberikan.',
            'Saya akan menjaga kerahasiaan seluruh data, informasi, dan kredensial akses, termasuk <i>email</i>, <i>username</i>, dan <i>password</i>, serta tidak akan membagikannya kepada pihak yang tidak berwenang.',
            'Saya tidak akan menyalahgunakan akses dalam bentuk apa pun.',
            'Saya bertanggung jawab atas penggunaan akun yang berada dalam penguasaan saya, termasuk atas tindakan pihak lain yang memperoleh akses melalui saya.',
            'Setiap persetujuan, transaksi, atau tindakan yang saya lakukan melalui Portal Pelanggan Intilab diakui sah, mengikat, dan memiliki kekuatan hukum penuh bagi perusahaan / instansi.',
            'Dalam hal terjadi perubahan PIC dan/atau <i>email</i> utama, saya atau perusahaan / instansi wajib melakukan pemberitahuan dan pembaruan melalui sistem atau formulir yang ditetapkan oleh PT Inti Surya Laboratorium.',
            'Saya bersedia mematuhi seluruh ketentuan, kebijakan, dan prosedur yang ditetapkan oleh PT Inti Surya Laboratorium.',
            'Saya bertanggung jawab secara hukum atas segala akibat yang timbul dari penggunaan maupun penyalahgunaan akses yang berada dalam penguasaan saya.',
        ];

        $pointsHtml = '';
        foreach ($points as $idx => $text) {
            $pointsHtml .= '<tr>'
                . '<td style="width:7mm;vertical-align:top;padding:0 2mm 0 0;text-align:left;line-height:1.56;">' . ($idx + 1) . '.</td>'
                . '<td style="vertical-align:top;padding:0;text-align:justify;line-height:1.56;">' . $text . '</td>'
                . '</tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head>
<body style="font-family: calibri, sans-serif; font-size: 11pt; color:#000000; line-height:1.42;">

<div style="margin:0;">Perihal : <b><u><i>Person In Charge</i> (PIC) Portal Pelanggan Intilab</u></b></div>
<div style="height:4mm;line-height:4mm;font-size:1px;">&nbsp;</div>

<div style="margin:0 0 2px;">Yang bertanda tangan di bawah ini :</div>
' . $this->fieldTable([
            ['Nama Lengkap', $this->e($namaPic !== '' ? $namaPic : '-')],
            ['Jabatan', $this->e($jabatanPic !== '' ? $jabatanPic : '-')],
            ['No. HP', $this->e($noKontak !== '' ? $noKontak : '-')],
            ['<i>Email</i>', $emailEsc],
        ], '0') . '
<div style="height:4.5mm;line-height:4.5mm;font-size:1px;">&nbsp;</div>

<div style="margin:0 0 4px;">Selaku pihak yang melakukan pendaftaran pada Portal Pelanggan Intilab untuk dan atas nama :</div>
<table width="100%" style="border-collapse:collapse;margin:0;background:transparent;">
<thead>
<tr>
<th style="border:1px solid #000;padding:5px 8px;background:#f2f2f2;color:#000;text-align:center;font-weight:bold;width:8%;">No.</th>
<th style="border:1px solid #000;padding:5px 8px;background:#f2f2f2;color:#000;text-align:center;font-weight:bold;width:35%;">ID Pelanggan</th>
<th style="border:1px solid #000;padding:5px 8px;background:#f2f2f2;color:#000;text-align:center;font-weight:bold;width:57%;">Nama Perusahaan</th>
</tr>
</thead>
<tbody>
' . $rows . '
</tbody>
</table>
<div style="height:5.5mm;line-height:5.5mm;font-size:1px;">&nbsp;</div>

<div style="margin:0 0 4px;">Dengan ini menyatakan dan menjamin dengan sebenar-benarnya bahwa :</div>
<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0;background:transparent;">
<tbody>
' . $pointsHtml . '
</tbody>
</table>
<div style="height:5mm;line-height:5mm;font-size:1px;">&nbsp;</div>

<div style="margin:0;text-align:justify;line-height:1.6;">Demikian surat pernyataan ini dibuat dengan sebenar-benarnya, dalam keadaan sadar dan tanpa paksaan dari pihak mana pun, dapat dipertanggungjawabkan secara hukum, serta dapat dipergunakan sebagaimana mestinya.</div>
<div style="height:7mm;line-height:7mm;font-size:1px;">&nbsp;</div>

<div style="page-break-inside:avoid;">
<div>' . $this->e($payload['tanggal_lengkap'] ?? '') . '</div>
<div>Yang menyatakan (PIC),</div>
' . $signature . '
<div><b><u>' . $this->e($namaPic) . '</u></b></div>
<div style="font-size:10pt;"><i>' . $this->e(trim($jabatanSign)) . '</i></div>
</div>

</body></html>';
    }

    public function generatePdf(array $payload, $absolutePath)
    {
        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $mpdfConfig = [
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_top' => 33,
            'margin_header' => 6,
            'margin_bottom' => 24,
            'margin_footer' => 5,
            'margin_left' => 17,
            'margin_right' => 17,
            'default_font' => 'dejavusans',
        ];

        $calibriDir = resource_path('fonts/Calibri');
        if (is_dir($calibriDir) && is_file($calibriDir . DIRECTORY_SEPARATOR . 'calibri.ttf')) {
            $fontDirs = (new ConfigVariables())->getDefaults()['fontDir'];
            $fontData = (new FontVariables())->getDefaults()['fontdata'];
            $mpdfConfig['fontDir'] = array_merge($fontDirs, [$calibriDir]);
            $mpdfConfig['fontdata'] = $fontData + [
                'calibri' => [
                    'R' => 'calibri.ttf',
                    'B' => 'calibrib.ttf',
                    'I' => 'calibrii.ttf',
                    'BI' => 'calibriz.ttf',
                ],
            ];
            $mpdfConfig['default_font'] = 'calibri';
        }

        $mpdf = new Mpdf($mpdfConfig);
        $mpdf->SetProtection(['print'], '', 'skyhwk12');
        $mpdf->SetTitle('Surat Pernyataan - Person In Charge (PIC) Portal Pelanggan Intilab');
        $mpdf->SetAuthor('PT Inti Surya Laboratorium');

        $header = $this->headerHtml($payload);
        $footer = $this->footerHtml($payload);
        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLHeader($header, 'E');
        $mpdf->SetHTMLFooter($footer);
        $mpdf->SetHTMLFooter($footer, 'E');

        $watermark = $this->watermarkPath();
        if ($watermark) {
            $mpdf->SetWatermarkImage($watermark, 0.14, '', [55, 85]);
            $mpdf->showWatermarkImage = true;
        }

        $mpdf->WriteHTML($this->renderHtml($payload));
        $mpdf->Output($absolutePath, \Mpdf\Output\Destination::FILE);

        return $absolutePath;
    }

    private function headerHtml(array $payload)
    {
        $logo = $this->logoTag();
        $nomor = $this->e($payload['nomor_dokumen'] ?? '-');

        return '<table width="100%" style="font-family:calibri,sans-serif;border-collapse:collapse;table-layout:fixed;">
<tr>
<td colspan="2" style="text-align:right;font-size:8pt;color:#555555;padding:0 0 1mm;">Page {PAGENO} of {nbpg}</td>
</tr>
<tr>
<td style="width:46%;vertical-align:middle;padding:0 0 2mm;">' . $logo . '</td>
<td style="width:54%;vertical-align:middle;text-align:center;padding:0 0 2mm;">
<div style="font-size:19pt;font-weight:bold;color:#333333;line-height:1.15;letter-spacing:0.3px;">SURAT PERNYATAAN</div>
<div style="font-size:10pt;font-weight:bold;color:#333333;margin-top:1mm;">No. : ' . $nomor . '</div>
</td>
</tr>
<tr>
<td colspan="2" style="border-bottom:0.8px solid ' . self::RULE_COLOR . ';padding:0;line-height:0;font-size:1px;">&nbsp;</td>
</tr>
</table>';
    }

    private function footerHtml(array $payload)
    {
        return '<div style="font-family:calibri,sans-serif;border-top:0.8px solid ' . self::RULE_COLOR . ';padding-top:5px;text-align:center;color:#555555;">
<div style="font-size:9.5pt;line-height:1.35;">
Ruko Icon Business Park blok O no. 5 &ndash; 6<br>
Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang &ndash; 15345<br>
P : 021-5089-8988/89 - <u>www.intilab.com</u> - contact@intilab.com
</div>
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
            return '<img src="' . $path . '" alt="Intilab" style="height:15mm;max-width:52mm;">';
        }

        return '<div style="font-size:14pt;font-weight:bold;color:#1a4f8b;">INTILAB</div>';
    }

    private function fieldTable(array $rows, $marginBottom)
    {
        $html = '<table style="width:100%;border-collapse:collapse;margin:0 0 ' . $marginBottom . ';line-height:1.38;"><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td style="border:none;padding:1px 4mm 1px 0;width:30mm;white-space:nowrap;vertical-align:top;">' . $row[0] . '</td>'
                . '<td style="border:none;padding:1px 2mm 1px 0;width:3mm;white-space:nowrap;vertical-align:top;">:</td>'
                . '<td style="border:none;padding:1px 0;vertical-align:top;">' . $row[1] . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
