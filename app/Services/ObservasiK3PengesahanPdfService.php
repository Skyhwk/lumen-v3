<?php

namespace App\Services;

use App\Models\ObservasiK3;
use Carbon\Carbon;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class ObservasiK3PengesahanPdfService
{
    public function generate(ObservasiK3 $obs, array $snapshot): string
    {
        $dir = public_path('dokumen/observasi_k3');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $safeQt = preg_replace('/[^A-Za-z0-9_\-.]/', '_', (string) ($obs->no_quotation ?? 'QT'));
        $filename = 'ObservasiK3_Pengesahan_' . $obs->id . '_' . $safeQt . '_' . date('YmdHis') . '.pdf';

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
        ]);

        $html = $this->buildHtml($obs, $snapshot);
        $mpdf->WriteHTML($html);
        $mpdf->Output($dir . DIRECTORY_SEPARATOR . $filename, Destination::FILE);

        return $filename;
    }

    private function buildHtml(ObservasiK3 $obs, array $snapshot): string
    {
        $identitas = $snapshot['identitas'] ?? [];
        $uraian = $snapshot['uraian'] ?? [];
        $pembuka = $snapshot['pembuka'] ?? '';
        $pernyataanList = $snapshot['pernyataan_list'] ?? [];
        $penutup = $snapshot['pernyataan'] ?? '';

        $lokasi = $identitas['lokasi'] ?? [];
        $lokasiText = is_array($lokasi) && count($lokasi) ? implode(', ', $lokasi) : '—';

        $tanggal = $this->formatTanggalIndo($identitas['tanggal_observasi'] ?? $obs->tanggal_observasi);
        $waktu = $identitas['waktu_observasi'] ?? $obs->waktu_observasi;
        $waktu = $waktu ? substr((string) $waktu, 0, 5) : '—';

        $ttdPetugas = $this->imgTag($obs->ttd_petugas);
        $ttdMengetahui = $this->imgTag($obs->ttd_mengetahui);

        $uraianRows = $this->buildUraianTableRows($uraian);

        $pernyataanOl = '';
        foreach ($pernyataanList as $i => $line) {
            $pernyataanOl .= '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        $namaPetugas = htmlspecialchars(
            $identitas['petugas_observasi'] ?? $obs->petugas_observasi ?? 'Petugas',
            ENT_QUOTES,
            'UTF-8'
        );
        $namaMengetahui = htmlspecialchars($obs->nama_mengetahui ?? 'Customer', ENT_QUOTES, 'UTF-8');
        $jabatan = htmlspecialchars($obs->jabatan_petugas ?? $identitas['jabatan_petugas'] ?? '—', ENT_QUOTES, 'UTF-8');
        $tglPengesahan = $this->formatTanggalIndo($obs->tanggal_pengesahan);

        return <<<HTML
<style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #0f172a; }
    .title { text-align: center; font-weight: bold; }
    .section { background: #e2e8f0; border: 1px solid #334155; padding: 6px 8px; font-weight: bold; text-transform: uppercase; font-size: 11px; }
    table { border-collapse: collapse; width: 100%; }
    td, th { border: 1px solid #334155; padding: 5px 8px; vertical-align: top; font-size: 10.5px; }
    .uraian th { background: #f8fafc; font-weight: bold; }
    ul { margin: 4px 0; padding-left: 16px; }
</style>
<div class="title" style="font-size:14px;">LEMBAR PENGESAHAN</div>
<div class="title" style="font-size:12px; margin-bottom:10px;">FORM PENDATAAN DAN OBSERVASI KESELAMATAN DAN KESEHATAN KERJA (K3)</div>
<p style="text-align:justify;">{$this->e($pembuka)}</p>

<div class="section">IDENTITAS KEGIATAN</div>
<table>
<tr><td width="38%"><b>Nama Perusahaan</b></td><td>{$this->e($identitas['nama_perusahaan'] ?? $obs->nama_perusahaan)}</td></tr>
<tr><td><b>Lokasi / Area Kerja</b></td><td>{$this->e($lokasiText)}</td></tr>
<tr><td><b>Jenis Kegiatan</b></td><td>{$this->e($identitas['jenis_kegiatan'] ?? 'Observasi dan Pendataan K3')}</td></tr>
<tr><td><b>Tanggal Pelaksanaan</b></td><td>{$this->e($tanggal)}</td></tr>
<tr><td><b>Waktu Pelaksanaan</b></td><td>{$this->e($waktu)}</td></tr>
<tr><td><b>Petugas Observasi</b></td><td>{$namaPetugas}</td></tr>
<tr><td><b>Jabatan</b></td><td>{$jabatan}</td></tr>
</table>
<br>

<div class="section">URAIAN PENDATAAN DAN OBSERVASI K3</div>
<table class="uraian">
<thead><tr><th width="20%">NAMA TITIK</th><th width="22%">KATEGORI</th><th width="58%">PENGENDALIAN</th></tr></thead>
<tbody>{$uraianRows}</tbody>
</table>
<br>

<div class="section">PERNYATAAN</div>
<table><tr><td style="border-top:none;">
<p>Dengan ini dinyatakan bahwa:</p>
<ol>{$pernyataanOl}</ol>
</td></tr></table>
<br>

<div class="section">PENGESAHAN</div>
<table><tr><td style="border-top:none;">
<p>{$this->e($penutup)}</p>
<table width="100%">
<tr>
<td width="50%" align="center" style="font-weight:bold;">Petugas Observasi dan Pendataan Penerapan K3</td>
<td width="50%" align="center" style="font-weight:bold;">Mengetahui</td>
</tr>
<tr>
<td align="center" height="90">{$ttdPetugas}</td>
<td align="center" height="90">{$ttdMengetahui}</td>
</tr>
<tr>
<td align="center" style="font-weight:bold;">({$namaPetugas})</td>
<td align="center" style="font-weight:bold;">({$namaMengetahui})</td>
</tr>
</table>
<table width="100%" style="margin-top:8px;">
<tr><td width="38%"><b>Tanggal Pengesahan</b></td><td>{$this->e($tglPengesahan)}</td></tr>
</table>
</td></tr></table>
HTML;
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
                    $html .= '<td rowspan="' . $rowspan . '"><b>' . $this->e($titik) . '</b></td>';
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

    private function imgTag(?string $filename): string
    {
        if (!$filename) {
            return '';
        }
        $path = public_path('dokumentasi/sampling/' . $filename);
        if (!is_file($path)) {
            return '';
        }

        return '<img src="' . $path . '" style="max-height:70px; max-width:200px;" />';
    }

    private function formatTanggalIndo($val): string
    {
        if (!$val) {
            return '—';
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
