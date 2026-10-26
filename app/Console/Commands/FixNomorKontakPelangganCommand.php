<?php

namespace App\Console\Commands;

use App\Models\KontakPelanggan;
use App\Models\PicPelanggan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FixNomorKontakPelangganCommand extends Command
{
    protected $signature = 'pelanggan:fix-nomor-kontak
        {--dry-run : Simulasi tanpa mengubah database (default)}
        {--execute : Eksekusi update ke database}
        {--rollback : Kembalikan nomor dari snapshot execute}
        {--snapshot= : Path snapshot JSON untuk rollback (default: terbaru)}
        {--force : Lewati konfirmasi}
        {--all : Sertakan data is_active=0}
        {--limit= : Batasi jumlah perubahan}
        {--output= : Folder Excel (opsional). Default: public/rekap-nomor-kontak-pelanggan}';

    protected $description = 'Perbaiki nomor kontak pelanggan dan PIC. Dry-run, --execute, atau --rollback dari snapshot. Rekap Excel + log sebelum/sesudah.';

    public function handle()
    {
        if ($this->option('rollback')) {
            return $this->handleRollback();
        }

        $execute = (bool) $this->option('execute');
        $mode = $execute ? 'EXECUTE' : 'DRY-RUN';
        $stamp = Carbon::now()->format('Ymd_His');

        $this->info("Mode: {$mode}");
        $this->info('Excel: ' . $this->excelDir());
        $this->info('Log & rollback: ' . $this->storageDir());
        $this->line('Mengumpulkan nomor yang perlu dinormalisasi...');

        $changes = $this->collectChanges();
        $limit = $this->option('limit');
        if ($limit !== null && $limit !== '' && (int) $limit > 0) {
            $changes = array_slice($changes, 0, (int) $limit);
        }

        if (!$changes) {
            $this->info('Tidak ada nomor yang perlu diubah.');
            return 0;
        }

        $this->info('Ditemukan ' . count($changes) . ' nomor.');
        $this->table(
            ['No.', 'ID Pelanggan', 'Nama Pelanggan', 'No Kontak Lama', 'No Kontak Baru', 'Sales Penanggung Jawab'],
            array_map(function ($row, $index) {
                return [
                    $index + 1,
                    $row['id_pelanggan'],
                    $row['nama_pelanggan'],
                    $row['lama'],
                    $row['baru'],
                    $row['sales'],
                ];
            }, array_slice($changes, 0, 20), array_keys(array_slice($changes, 0, 20)))
        );
        if (count($changes) > 20) {
            $this->line('... ' . (count($changes) - 20) . ' baris berikutnya ada di Excel/log.');
        }

        $excelPath = $this->writeExcel($changes, $mode, $stamp);
        $logPath = $this->writeLogFile($changes, $mode, $stamp, false);
        $this->info("Rekap Excel: {$excelPath}");
        $this->info("Log: {$logPath}");

        if (!$execute) {
            $this->warn('Ini dry-run. Database belum diubah.');
            $this->line('Jalankan eksekusi: php artisan pelanggan:fix-nomor-kontak --execute');
            return 0;
        }

        if (!$this->option('force') && !$this->confirm('Ubah ' . count($changes) . ' nomor di database sekarang?', false)) {
            $this->warn('Dibatalkan. Database tidak diubah.');
            return 0;
        }

        $updated = $this->applyChanges($changes, false);
        $snapshotPath = $this->writeSnapshot($changes, $stamp);
        $this->writeLogFile($changes, $mode, $stamp, true);
        $this->info("Selesai. {$updated} nomor diupdate.");
        $this->info("Rekap Excel: {$excelPath}");
        $this->info("Log: {$logPath}");
        $this->info("Snapshot rollback: {$snapshotPath}");
        $this->line('Jika salah, kembalikan dengan: php artisan pelanggan:fix-nomor-kontak --rollback');

        return 0;
    }

    private function handleRollback()
    {
        $stamp = Carbon::now()->format('Ymd_His');
        $snapshotPath = $this->option('snapshot') ?: $this->latestSnapshotPath();
        if (!$snapshotPath || !is_file($snapshotPath)) {
            $this->error('Snapshot tidak ditemukan. Jalankan --execute dulu, atau isi --snapshot=path.json');
            return 1;
        }

        $payload = json_decode((string) file_get_contents($snapshotPath), true);
        $changes = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];
        if (!$changes) {
            $this->error('Snapshot kosong atau rusak: ' . $snapshotPath);
            return 1;
        }

        $this->warn('Mode: ROLLBACK');
        $this->info('Excel: ' . $this->excelDir());
        $this->info('Log & rollback: ' . $this->storageDir());
        $this->line('Snapshot: ' . $snapshotPath);
        $this->info('Akan mengembalikan ' . count($changes) . ' nomor ke nilai lama.');
        $this->table(
            ['No.', 'ID Pelanggan', 'Nama Pelanggan', 'No Sekarang (baru)', 'Dikembalikan ke (lama)', 'Sales Penanggung Jawab'],
            array_map(function ($row, $index) {
                return [
                    $index + 1,
                    $row['id_pelanggan'],
                    $row['nama_pelanggan'],
                    $row['baru'],
                    $row['lama'],
                    $row['sales'],
                ];
            }, array_slice($changes, 0, 20), array_keys(array_slice($changes, 0, 20)))
        );

        $excelPath = $this->writeExcel($this->swapChanges($changes), 'ROLLBACK', $stamp);
        $this->info("Rekap Excel rollback: {$excelPath}");

        if (!$this->option('force') && !$this->confirm('Kembalikan ' . count($changes) . ' nomor ke nilai lama sekarang?', false)) {
            $this->warn('Dibatalkan. Database tidak diubah.');
            return 0;
        }

        $updated = $this->applyChanges($changes, true);
        $this->writeLogFile($this->swapChanges($changes), 'ROLLBACK', $stamp, true);
        $this->info("Rollback selesai. {$updated} nomor dikembalikan.");
        $this->info("Rekap Excel: {$excelPath}");

        return 0;
    }

    private function swapChanges(array $changes)
    {
        return array_map(function ($row) {
            $lama = $row['lama'];
            $row['lama'] = $row['baru'];
            $row['baru'] = $lama;
            return $row;
        }, $changes);
    }

    private function collectChanges()
    {
        $changes = [];
        $activeOnly = !$this->option('all');

        $kontakQuery = KontakPelanggan::query()->with('pelanggan');
        if ($activeOnly) {
            $kontakQuery->where('is_active', true);
        }
        $kontakQuery->orderBy('id')->chunkById(200, function ($rows) use (&$changes) {
            foreach ($rows as $row) {
                $this->pushChange($changes, $row, 'no_tlp_perusahaan', 'kontak_pelanggan', 'Kontak Perusahaan');
            }
        });

        $picQuery = PicPelanggan::query()->with('pelanggan');
        if ($activeOnly) {
            $picQuery->where('is_active', true);
        }
        $picQuery->orderBy('id')->chunkById(200, function ($rows) use (&$changes) {
            foreach ($rows as $row) {
                $this->pushChange($changes, $row, 'no_tlp_pic', 'pic_pelanggan', 'PIC Telepon');
                $this->pushChange($changes, $row, 'wa_pic', 'pic_pelanggan', 'PIC WA');
            }
        });

        return $changes;
    }

    private function pushChange(array &$changes, $row, $column, $table, $sumber)
    {
        $lama = trim((string) ($row->{$column} ?? ''));
        $baru = $this->normalizePhone($lama);
        if ($baru === null || $baru === $lama) {
            return;
        }

        $pelanggan = $row->pelanggan;
        $changes[] = [
            'table' => $table,
            'column' => $column,
            'record_id' => $row->id,
            'sumber' => $sumber,
            'id_pelanggan' => $pelanggan->id_pelanggan ?? '',
            'nama_pelanggan' => $pelanggan->nama_pelanggan ?? '',
            'sales' => $pelanggan->sales_penanggung_jawab ?? '',
            'lama' => $lama,
            'baru' => $baru,
        ];
    }

    private function normalizePhone($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $compact = preg_replace('/[\s\-\.\(\)]+/', '', $raw);
        if ($compact === '' || !preg_match('/^\+?\d+$/', $compact)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $compact);
        if (strlen($digits) < 4) {
            return null;
        }

        if (strpos($digits, '0') === 0) {
            return $digits;
        }

        if (strpos($digits, '62') === 0) {
            $rest = substr($digits, 2);
            if ($rest === '') {
                return null;
            }
            return strpos($rest, '0') === 0 ? $rest : '0' . $rest;
        }

        if (preg_match('/^[2-9]/', $digits)) {
            return '0' . $digits;
        }

        return null;
    }

    private function applyChanges(array $changes, $rollback = false)
    {
        $updated = 0;
        $skipped = 0;
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $actor = $rollback ? 'system:rollback-nomor-kontak' : 'system:fix-nomor-kontak';

        DB::transaction(function () use ($changes, $now, $rollback, $actor, &$updated, &$skipped) {
            foreach ($changes as $change) {
                $from = $rollback ? $change['baru'] : $change['lama'];
                $to = $rollback ? $change['lama'] : $change['baru'];
                $current = DB::table($change['table'])->where('id', $change['record_id'])->value($change['column']);

                if (trim((string) $current) !== trim((string) $from) && !$this->option('force')) {
                    $skipped++;
                    Log::warning('pelanggan:fix-nomor-kontak.skip', [
                        'alasan' => 'nilai di database sudah berubah, dilewati agar tidak menimpa',
                        'table' => $change['table'],
                        'record_id' => $change['record_id'],
                        'current' => $current,
                        'expected' => $from,
                        'target' => $to,
                    ]);
                    continue;
                }

                $payload = [$change['column'] => $to];
                if (Schema::hasColumn($change['table'], 'updated_at')) {
                    $payload['updated_at'] = $now;
                }
                if (Schema::hasColumn($change['table'], 'updated_by')) {
                    $payload['updated_by'] = $actor;
                }
                DB::table($change['table'])->where('id', $change['record_id'])->update($payload);
                $updated++;

                Log::info('pelanggan:fix-nomor-kontak', [
                    'action' => $rollback ? 'rollback' : 'execute',
                    'table' => $change['table'],
                    'column' => $change['column'],
                    'record_id' => $change['record_id'],
                    'id_pelanggan' => $change['id_pelanggan'],
                    'nama_pelanggan' => $change['nama_pelanggan'],
                    'lama' => $from,
                    'baru' => $to,
                    'sales' => $change['sales'],
                ]);
            }
        });

        if ($skipped) {
            $this->warn("{$skipped} baris dilewati karena nomor di database sudah berubah setelah execute. Pakai --force untuk tetap menimpa.");
        }

        return $updated;
    }

    private function ensureDir($dir)
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Gagal membuat folder: ' . $dir);
        }

        return $dir;
    }

    private function excelDir()
    {
        $custom = trim((string) $this->option('output'));
        if ($custom !== '') {
            return $this->ensureDir($custom);
        }

        return $this->ensureDir(public_path('rekap-nomor-kontak-pelanggan'));
    }

    private function storageDir()
    {
        return $this->ensureDir(storage_path('logs/rekap-nomor-kontak-pelanggan'));
    }

    private function snapshotDir()
    {
        return $this->ensureDir($this->storageDir() . DIRECTORY_SEPARATOR . 'snapshot');
    }

    private function writeSnapshot(array $changes, $stamp)
    {
        $dir = $this->snapshotDir();
        $fullPath = $dir . DIRECTORY_SEPARATOR . "snapshot_EXECUTE_{$stamp}.json";
        $payload = [
            'created_at' => Carbon::now()->toDateTimeString(),
            'count' => count($changes),
            'changes' => $changes,
        ];
        file_put_contents($fullPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'latest.json', json_encode([
            'path' => $fullPath,
            'created_at' => $payload['created_at'],
            'count' => $payload['count'],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $fullPath;
    }

    private function latestSnapshotPath()
    {
        $pointer = $this->snapshotDir() . DIRECTORY_SEPARATOR . 'latest.json';
        if (is_file($pointer)) {
            $meta = json_decode((string) file_get_contents($pointer), true);
            if (!empty($meta['path']) && is_file($meta['path'])) {
                return $meta['path'];
            }
        }

        $files = glob($this->snapshotDir() . DIRECTORY_SEPARATOR . 'snapshot_EXECUTE_*.json') ?: [];
        if (!$files) {
            return null;
        }
        rsort($files);

        return $files[0];
    }

    private function writeExcel(array $changes, $mode, $stamp)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Nomor Kontak');

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'Rekap Perbaikan Nomor Kontak Pelanggan');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headers = ['No.', 'ID Pelanggan', 'Nama Pelanggan', 'No Kontak Lama', 'No Kontak Baru', 'Sales Penanggung Jawab'];
        $sheet->fromArray($headers, null, 'A3');
        $sheet->getStyle('A3:F3')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A3:F3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF163B65');

        $rowIdx = 4;
        $no = 1;
        foreach ($changes as $change) {
            $sheet->setCellValue("A{$rowIdx}", $no++);
            $sheet->setCellValueExplicit("B{$rowIdx}", (string) $change['id_pelanggan'], DataType::TYPE_STRING);
            $sheet->setCellValue("C{$rowIdx}", $change['nama_pelanggan']);
            $sheet->setCellValueExplicit("D{$rowIdx}", (string) $change['lama'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("E{$rowIdx}", (string) $change['baru'], DataType::TYPE_STRING);
            $sheet->setCellValue("F{$rowIdx}", $change['sales']);
            $rowIdx++;
        }

        $lastRow = max($rowIdx - 1, 3);
        $sheet->getStyle("A3:F{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $dir = $this->excelDir();
        $fileName = "rekap_nomor_kontak_{$mode}_{$stamp}.xlsx";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $fileName;
        (new Xlsx($spreadsheet))->save($fullPath);

        return $fullPath;
    }

    private function writeLogFile(array $changes, $mode, $stamp, $applied)
    {
        $dir = $this->storageDir();
        $fileName = "fix-nomor-kontak-pelanggan-{$mode}-{$stamp}.log";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $fileName;
        $lines = [
            'mode=' . $mode,
            'applied=' . ($applied ? 'yes' : 'no'),
            'count=' . count($changes),
            'at=' . Carbon::now()->toDateTimeString(),
            '',
        ];
        foreach ($changes as $index => $change) {
            $lines[] = sprintf(
                '#%d [%s] table=%s id=%s pelanggan=%s | %s -> %s | sales=%s | %s',
                $index + 1,
                $change['sumber'],
                $change['table'],
                $change['record_id'],
                $change['id_pelanggan'],
                $change['lama'],
                $change['baru'],
                $change['sales'],
                $change['nama_pelanggan']
            );
            Log::info('pelanggan:fix-nomor-kontak.preview', [
                'mode' => $mode,
                'applied' => $applied,
                'sumber' => $change['sumber'],
                'table' => $change['table'],
                'record_id' => $change['record_id'],
                'id_pelanggan' => $change['id_pelanggan'],
                'nama_pelanggan' => $change['nama_pelanggan'],
                'lama' => $change['lama'],
                'baru' => $change['baru'],
                'sales' => $change['sales'],
            ]);
        }

        file_put_contents($fullPath, implode(PHP_EOL, $lines) . PHP_EOL);

        return $fullPath;
    }
}
