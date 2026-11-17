<?php

namespace App\Services;

use App\Models\ObservasiK3;
use Carbon\Carbon;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Layout mengikuti template "FDL Observasi K3 - Lembar Pengesahan".
 * Hanya blok URAIAN memakai tabel 3 kolom; sisanya garis label–isi (titik jika kosong).
 */
class ObservasiK3PengesahanPdfService
{
    private const FIELD_DOTS = '................................................';

    public function generate(ObservasiK3 $obs, array $snapshot): string
    {
        $dir = public_path('dokumen/observasi_k3');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $this->resolveFilename($obs);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 18,
            'margin_right' => 18,
            'margin_top' => 16,
            'margin_bottom' => 16,
        ]);

        $html = $this->buildHtml($obs, $snapshot);
        $mpdf->WriteHTML($html);
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        $mpdf->Output($path, Destination::FILE);

        $old = $obs->file_pengesahan_pdf;
        if ($old && $old !== $filename) {
            $oldPath = $dir . DIRECTORY_SEPARATOR . $old;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        return $filename;
    }

    /** Nama file = nomor QT. QT yang sama + observasi lain (sampel beda) dapat suffix _id. */
    private function resolveFilename(ObservasiK3 $obs): string
    {
        $safeQt = preg_replace('/[^A-Za-z0-9_\-.]/', '_', (string) ($obs->no_quotation ?: 'QT'));
        $preferred = $safeQt . '.pdf';

        $taken = ObservasiK3::where('id', '!=', $obs->id)
            ->where('file_pengesahan_pdf', $preferred)
            ->exists();

        if ($taken) {
            return $safeQt . '_' . $obs->id . '.pdf';
        }

        return $preferred;
    }

    private function buildHtml(ObservasiK3 $obs, array $snapshot): string
    {
        $identitas = $snapshot['identitas'] ?? [];
        $uraian = $snapshot['uraian'] ?? [];
        $pembuka = $snapshot['pembuka'] ?? '';
        $pernyataanList = $snapshot['pernyataan_list'] ?? [];
        $penutup = $snapshot['pernyataan'] ?? '';

        $lokasi = $identitas['lokasi'] ?? [];
        $lokasiText = is_array($lokasi) && count($lokasi) ? implode(', ', $lokasi) : '';

        $tanggal = $this->formatTanggalIndo($identitas['tanggal_observasi'] ?? $obs->tanggal_observasi);
        $waktu = $identitas['waktu_observasi'] ?? $obs->waktu_observasi;
        $waktu = $waktu ? substr((string) $waktu, 0, 5) : '';

        $ttdPetugas = $this->signatureImg($obs->ttd_petugas);
        $ttdMengetahui = $this->signatureImg($obs->ttd_mengetahui);

        $uraianRows = $this->buildUraianTableRows($uraian);

        $pernyataanOl = '';
        foreach ($pernyataanList as $line) {
            $pernyataanOl .= '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        $namaPetugas = (string) ($identitas['petugas_observasi'] ?? $obs->petugas_observasi ?? 'Petugas');
        $namaMengetahui = (string) ($obs->nama_mengetahui ?? 'Customer');
        $tglPengesahan = $this->formatTanggalIndo($obs->tanggal_pengesahan);

        $identitasBlock = $this->buildFieldBlock([
            'Nama Perusahaan' => $identitas['nama_perusahaan'] ?? $obs->nama_perusahaan,
            'Lokasi / Area Kerja' => $lokasiText,
            'Jenis Kegiatan' => $identitas['jenis_kegiatan'] ?? 'Observasi dan Pendataan K3',
            'Tanggal Pelaksanaan' => $tanggal,
            'Waktu Pelaksanaan' => $waktu,
            'Petugas Observasi' => $namaPetugas,
            'Jabatan' => $obs->jabatan_petugas ?? $identitas['jabatan_petugas'] ?? '',
        ]);

        $ttdPetugasBlock = $ttdPetugas !== '' ? $ttdPetugas : '&nbsp;';
        $ttdMengetahuiBlock = $ttdMengetahui !== '' ? $ttdMengetahui : '&nbsp;';

        return <<<HTML
<style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #000; line-height: 1.45; }
    .title-main { text-align: center; font-weight: bold; font-size: 13pt; margin: 0; }
    .title-sub { text-align: center; font-weight: bold; font-size: 11pt; margin: 2px 0 12px 0; }
    .section { font-weight: bold; text-transform: uppercase; font-size: 10.5pt; margin: 14px 0 6px 0; }
    .section-content { padding: 0 0 4px 0; font-size: 11pt; }
    table.fields { width: 100%; border-collapse: collapse; }
    table.fields td { border: none; padding: 3px 0; vertical-align: top; font-size: 11pt; }
    table.fields td.lbl { width: 38%; }
    table.fields td.val { width: 62%; }
    table.uraian { border-collapse: collapse; width: 100%; margin-top: 8px; margin-bottom: 12px; font-size: 10pt; }
    table.uraian td, table.uraian th { border: 1px solid #000; padding: 5px 6px; vertical-align: top; }
    table.uraian th { font-weight: bold; text-align: left; }
    ol { margin: 6px 0 0 0; padding-left: 20px; }
    ol li { margin-bottom: 4px; }
    .spacer-pengesahan { height: 22px; line-height: 22px; font-size: 1px; }
    table.ttd { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.ttd td { border: none; width: 50%; text-align: center; padding: 4px 10px; }
    table.ttd td.hdr { font-weight: bold; height: 36px; vertical-align: bottom; }
    table.ttd td.sign { height: 88px; vertical-align: middle; }
    table.ttd td.nm { font-weight: bold; padding-top: 6px; vertical-align: top; }
    table.ttd img { display: block; margin: 0 auto; }
    table.keep { width: 100%; border-collapse: collapse; page-break-inside: avoid; }
    table.keep td { border: none; padding: 0; }
</style>
<p class="title-main">LEMBAR PENGESAHAN</p>
<p class="title-sub">FORM PENDATAAN DAN OBSERVASI KESELAMATAN DAN KESEHATAN KERJA (K3)</p>
<p style="text-align:justify; margin: 0 0 4px 0;">{$this->e($pembuka)}</p>

<div class="section">IDENTITAS KEGIATAN</div>
<div class="section-content">{$identitasBlock}</div>

<div class="section">URAIAN PENDATAAN DAN OBSERVASI K3</div>
<table class="uraian">
<thead><tr><th width="20%">NAMA TITIK</th><th width="22%">KATEGORI</th><th width="58%">PENGENDALIAN</th></tr></thead>
<tbody>{$uraianRows}</tbody>
</table>

<table class="keep">
<tr><td>
<div class="section">PERNYATAAN</div>
<div class="section-content">
<p style="margin:0;">Dengan ini dinyatakan bahwa:</p>
<ol>{$pernyataanOl}</ol>
</div>
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td class="spacer-pengesahan" height="22">&nbsp;</td></tr>
</table>
<div class="section">PENGESAHAN</div>
<div class="section-content">
<p style="text-align:justify; margin:0 0 8px 0;">{$this->e($penutup)}</p>
<table class="ttd" width="100%">
<tr>
    <td class="hdr" width="50%" align="center" valign="bottom">Petugas Observasi dan Pendataan Penerapan K3</td>
    <td class="hdr" width="50%" align="center" valign="bottom">Mengetahui</td>
</tr>
<tr>
    <td class="sign" width="50%" align="center" valign="middle" height="88">{$ttdPetugasBlock}</td>
    <td class="sign" width="50%" align="center" valign="middle" height="88">{$ttdMengetahuiBlock}</td>
</tr>
<tr>
    <td class="nm" width="50%" align="center">({$this->e($namaPetugas)})</td>
    <td class="nm" width="50%" align="center">({$this->e($namaMengetahui)})</td>
</tr>
</table>
<table class="fields" style="margin-top:14px;">
<tr>
    <td class="lbl">Tanggal Pengesahan</td>
    <td class="val">{$this->fieldValue($tglPengesahan)}</td>
</tr>
</table>
</div>
</td></tr>
</table>
HTML;
    }

    private function buildFieldBlock(array $fields): string
    {
        $html = '<table class="fields">';
        foreach ($fields as $label => $value) {
            $html .= '<tr><td class="lbl">' . $this->e($label) . '</td><td class="val">' . $this->fieldValue($value) . '</td></tr>';
        }

        return $html . '</table>';
    }

    private function fieldValue($value): string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '' || $v === '—') {
            return htmlspecialchars(self::FIELD_DOTS, ENT_QUOTES, 'UTF-8');
        }

        return $this->e($v);
    }

    private function buildUraianTableRows(array $uraian): string
    {
        if (count($uraian) === 0) {
            return '<tr><td colspan="3" align="center">Tidak ada data pengendalian.</td></tr>';
        }

        $groups = [];
        foreach ($uraian as $row) {
            $titik = $row['nama_titik'] ?? '—';
            if (!isset($groups[$titik])) {
                $groups[$titik] = [];
            }
            $groups[$titik][] = $row;
        }

        $html = '';
        foreach ($groups as $titik => $rows) {
            $rowspan = count($rows);
            foreach ($rows as $ri => $row) {
                $html .= '<tr>';
                if ($ri === 0) {
                    $html .= '<td rowspan="' . $rowspan . '">' . $this->e($titik) . '</td>';
                }
                $html .= '<td><b>' . $this->e(mb_strtoupper((string) ($row['kategori'] ?? ''), 'UTF-8')) . '</b></td><td>';
                foreach ($row['detail'] ?? [] as $poin) {
                    $html .= '<div><b>' . $this->e($poin['poin'] ?? '') . '</b></div>';
                    foreach ($poin['pengendalian'] ?? [] as $line) {
                        $html .= '<div>- ' . $this->e($line) . '</div>';
                    }
                }
                $html .= '</td></tr>';
            }
        }

        return $html;
    }

    private function signatureImg(?string $filename): string
    {
        if (!$filename) {
            return '';
        }
        $path = public_path('dokumentasi/sampling/' . $filename);
        if (!is_file($path)) {
            return '';
        }

        $src = $this->cropSignatureWhitespace($path) ?: $path;

        return '<img src="' . $src . '" height="52" />';
    }

    /** Potong whitespace canvas TTD supaya coretan ada di tengah kotak gambar. */
    private function cropSignatureWhitespace(string $path): ?string
    {
        $info = @getimagesize($path);
        if (!$info) {
            return null;
        }

        $im = $info[2] === IMAGETYPE_PNG
            ? @imagecreatefrompng($path)
            : @imagecreatefromjpeg($path);
        if (!$im) {
            return null;
        }

        $w = imagesx($im);
        $h = imagesy($im);
        $minX = $w;
        $minY = $h;
        $maxX = 0;
        $maxY = 0;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($im, $x, $y);
                $a = ($rgb >> 24) & 0x7F;
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $isInk = $a < 110 && ($r < 240 || $g < 240 || $b < 240);
                if ($isInk) {
                    if ($x < $minX) {
                        $minX = $x;
                    }
                    if ($y < $minY) {
                        $minY = $y;
                    }
                    if ($x > $maxX) {
                        $maxX = $x;
                    }
                    if ($y > $maxY) {
                        $maxY = $y;
                    }
                }
            }
        }

        if ($maxX <= $minX || $maxY <= $minY) {
            imagedestroy($im);

            return null;
        }

        $pad = 10;
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $maxX = min($w - 1, $maxX + $pad);
        $maxY = min($h - 1, $maxY + $pad);
        $cw = $maxX - $minX + 1;
        $ch = $maxY - $minY + 1;

        $out = imagecreatetruecolor($cw, $ch);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $transparent = imagecolorallocatealpha($out, 255, 255, 255, 127);
        imagefilledrectangle($out, 0, 0, $cw, $ch, $transparent);
        imagecopy($out, $im, 0, 0, $minX, $minY, $cw, $ch);

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ttd_crop_' . md5($path) . '.png';
        imagepng($out, $tmp);
        imagedestroy($im);
        imagedestroy($out);

        return $tmp;
    }

    private function formatTanggalIndo($val): string
    {
        if (!$val) {
            return '';
        }
        try {
            return Carbon::parse($val)->locale('id')->translatedFormat('d F Y');
        } catch (\Throwable $e) {
            return (string) $val;
        }
    }

    private function e($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}