<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom biaya disimpan sebagai JSON (rincian baris + total) untuk visualisasi ke depan.
 * spare_part tidak lagi dipakai untuk input baru; data lama tetap ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_mobil_detail')) {
            return;
        }

        $rows = DB::table('service_mobil_detail')->select('id', 'biaya')->whereNotNull('biaya')->get();

        foreach ($rows as $row) {
            $raw = $row->biaya;
            if ($raw === null || $raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                continue;
            }

            DB::table('service_mobil_detail')->where('id', $row->id)->update([
                'biaya' => json_encode([
                    'version' => 0,
                    'legacy_text' => (string) $raw,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }

        try {
            DB::statement('ALTER TABLE service_mobil_detail MODIFY biaya JSON NULL');
        } catch (\Throwable $e) {
            // MariaDB/MySQL versi lama: tetap TEXT jika JSON gagal
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('service_mobil_detail')) {
            return;
        }

        $rows = DB::table('service_mobil_detail')->select('id', 'biaya')->whereNotNull('biaya')->get();

        foreach ($rows as $row) {
            $decoded = json_decode($row->biaya, true);
            if (!is_array($decoded)) {
                continue;
            }

            if (($decoded['version'] ?? null) === 0 && isset($decoded['legacy_text'])) {
                DB::table('service_mobil_detail')->where('id', $row->id)->update([
                    'biaya' => $decoded['legacy_text'],
                ]);
            } elseif (($decoded['version'] ?? null) === 1) {
                $lines = [];
                foreach ($decoded['lines'] ?? [] as $line) {
                    $label = trim(($line['uraian'] ?? '') . ' — ' . ($line['keterangan'] ?? ''));
                    $label = trim($label, ' —');
                    $harga = (int) ($line['harga'] ?? 0);
                    $lines[] = $label ? ($label . ': Rp ' . number_format($harga, 0, ',', '.')) : '';
                }
                $text = implode("\n", array_filter($lines));
                if (!empty($decoded['total'])) {
                    $text .= ($text ? "\n" : '') . 'Total: Rp ' . number_format((int) $decoded['total'], 0, ',', '.');
                }
                DB::table('service_mobil_detail')->where('id', $row->id)->update(['biaya' => $text ?: null]);
            }
        }

        try {
            DB::statement('ALTER TABLE service_mobil_detail MODIFY biaya TEXT NULL');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
