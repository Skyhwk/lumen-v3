<?php

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class SalaryAdjustmentDocumentService
{
    private const TEMP_DIR = 'temp/salary-adjustment';

    public function generateSummaryPdf(array $bundle): array
    {
        $request = $bundle['request'] ?? [];
        $employeeName = $request['nama_lengkap'] ?? 'Karyawan';
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $employeeName) ?: 'karyawan';
        $batchId = str_replace('.', '', uniqid('adj_', true));
        $relativeDir = self::TEMP_DIR . '/' . $batchId;
        $outputDir = base_path('public/' . $relativeDir);

        if (!is_dir($outputDir) && !@mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new \RuntimeException('Gagal membuat folder lampiran PDF.');
        }

        $filename = 'Ringkasan-Penyesuaian-Gaji-' . $safeName . '.pdf';
        $fullPath = $outputDir . '/' . $filename;
        $html = $this->buildSummaryHtml($bundle);

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
        ]);
        $pdf->WriteHTML($html);
        $pdf->Output($fullPath, Destination::FILE);

        return [
            'filename' => $filename,
            'path' => $relativeDir . '/' . $filename,
            'full_path' => $fullPath,
            'batch_dir' => $outputDir,
        ];
    }

    public function cleanup(?string $batchDir): void
    {
        if (!$batchDir || !is_dir($batchDir)) {
            return;
        }

        foreach (glob($batchDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($batchDir);
    }

    private function buildSummaryHtml(array $bundle): string
    {
        $request = $bundle['request'] ?? [];
        $kpi = $bundle['kpi'] ?? null;
        $assessment = $bundle['assessment'] ?? null;
        $counseling = $bundle['counseling'] ?? null;
        $attendance = $bundle['attendance'] ?? null;

        $fmt = fn ($value) => number_format((float) ($value ?? 0), 0, ',', '.');

        $kpiRows = '';
        foreach (($kpi['items'] ?? []) as $item) {
            $kpiRows .= '<tr>'
                . '<td>' . e($item['criteria_name'] ?? '-') . '</td>'
                . '<td style="text-align:center;">' . e((string) ($item['score'] ?? '-')) . '</td>'
                . '<td style="text-align:center;">' . e((string) ($item['final_score'] ?? '-')) . '</td>'
                . '</tr>';
        }

        $attendanceRows = '';
        foreach (($attendance['monthly'] ?? []) as $month) {
            $attendanceRows .= '<tr>'
                . '<td>' . e($month['label'] ?? '-') . '</td>'
                . '<td style="text-align:center;">' . e((string) ($month['attendance_days'] ?? 0)) . '</td>'
                . '<td style="text-align:center;">' . e((string) ($month['total_records'] ?? 0)) . '</td>'
                . '</tr>';
        }

        return '
        <style>
            body { font-family: sans-serif; font-size: 11px; color: #222; }
            h1 { font-size: 16px; margin-bottom: 4px; }
            h2 { font-size: 13px; margin-top: 16px; margin-bottom: 6px; }
            table { width: 100%; border-collapse: collapse; margin-top: 6px; }
            th, td { border: 1px solid #ccc; padding: 6px; vertical-align: top; }
            th { background: #f3f4f6; }
            .muted { color: #666; }
        </style>
        <h1>Ringkasan Permohonan Penyesuaian Gaji</h1>
        <p class="muted">No. Dokumen: ' . e($request['no_document'] ?? '-') . '</p>
        <table>
            <tr><th>Karyawan</th><td>' . e($request['nama_lengkap'] ?? '-') . '</td></tr>
            <tr><th>Manager Pengaju</th><td>' . e($request['manager_nama'] ?? '-') . '</td></tr>
            <tr><th>Jabatan</th><td>' . e($request['jabatan'] ?? '-') . '</td></tr>
            <tr><th>Bulan Efektif</th><td>' . e($request['bulan_efektif'] ?? '-') . '</td></tr>
            <tr><th>Gaji Saat Ini</th><td>Rp ' . $fmt($request['current_gaji_pokok'] ?? 0) . '</td></tr>
            <tr><th>Tunjangan Saat Ini</th><td>Rp ' . $fmt($request['current_tunjangan_kerja'] ?? 0) . '</td></tr>
            <tr><th>Delta Gaji</th><td>Rp ' . $fmt($request['adjustment_gaji_pokok'] ?? 0) . '</td></tr>
            <tr><th>Delta Tunjangan</th><td>Rp ' . $fmt($request['adjustment_tunjangan'] ?? 0) . '</td></tr>
            <tr><th>Target Gaji Pokok</th><td>Rp ' . $fmt($request['requested_gaji_pokok'] ?? 0) . '</td></tr>
            <tr><th>Target Tunjangan</th><td>Rp ' . $fmt($request['requested_tunjangan_kerja'] ?? 0) . '</td></tr>
        </table>

        <h2>1. KPI Manager</h2>
        <p>Rata-rata: ' . e((string) ($kpi['total_score_avg'] ?? '-')) . ' | Interpretasi: ' . e($kpi['interpretation'] ?? '-') . '</p>
        <table>
            <thead><tr><th>Kriteria</th><th>Skor</th><th>Skor Final</th></tr></thead>
            <tbody>' . ($kpiRows ?: '<tr><td colspan="3">Tidak ada data KPI</td></tr>') . '</tbody>
        </table>
        <p><strong>Ringkasan:</strong> ' . nl2br(e($kpi['summary'] ?? '-')) . '</p>

        <h2>2. Hasil Assessment</h2>
        <p>Skor: ' . e((string) ($assessment['total_score'] ?? '-')) . '% | Status: ' . e($assessment['attempt_status'] ?? '-') . '</p>

        <h2>3. Absensi 3 Bulan</h2>
        <p>Periode: ' . e($attendance['period_label'] ?? '-') . ' | Hari hadir: ' . e((string) ($attendance['attendance_days'] ?? 0)) . '</p>
        <table>
            <thead><tr><th>Bulan</th><th>Hari Hadir</th><th>Total Scan</th></tr></thead>
            <tbody>' . ($attendanceRows ?: '<tr><td colspan="3">Tidak ada data absensi</td></tr>') . '</tbody>
        </table>

        <h2>4. Hasil Konseling</h2>
        <p>Tanggal: ' . e($counseling['scheduled_date'] ?? '-') . ' | Konselor: ' . e($counseling['counselor_name'] ?? '-') . '</p>
        <p>' . nl2br(e($counseling['result_notes'] ?? '-')) . '</p>
        ';
    }
}
