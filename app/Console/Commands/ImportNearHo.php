<?php

namespace App\Console\Commands;

use App\Models\KontakPelanggan;
use App\Models\MasterPelanggan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class ImportNearHo extends Command
{
    protected $signature = 'customer:import-near-ho
        {file : Path file Excel}
        {--execute : Simpan ke database. Tanpa opsi ini hanya pratinjau}';

    protected $description = 'Import customer radius 15 km dari HO ke master_pelanggan dan kontak_pelanggan';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $execute = (bool) $this->option('execute');

        if (!is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");
            return 1;
        }

        $rows = $this->readRows($file);
        if (!$rows) {
            $this->error('Tidak ada baris customer di file Excel.');
            return 1;
        }

        $existingNames = MasterPelanggan::query()
            ->where('is_active', 1)
            ->pluck('nama_pelanggan')
            ->map(function ($name) {
                return $this->nameKey((string) $name);
            })
            ->flip();

        $existingPhones = KontakPelanggan::query()
            ->whereNotNull('no_tlp_perusahaan')
            ->pluck('no_tlp_perusahaan')
            ->map(function ($phone) {
                return $this->normalizePhone((string) $phone);
            })
            ->filter()
            ->flip();

        $lastUrut = (int) MasterPelanggan::query()
            ->where('id_cabang', 1)
            ->max(DB::raw('CAST(no_urut AS UNSIGNED)'));

        $planned = [];
        $skipped = [];
        $seenNames = [];
        $seenPhones = [];

        foreach ($rows as $row) {
            $nameKey = $this->nameKey($row['nama']);
            $phone = $row['phone'];

            if ($nameKey === '') {
                $skipped[] = $this->skip($row, 'Nama customer kosong');
                continue;
            }
            if ($phone === null) {
                $skipped[] = $this->skip($row, 'Nomor telepon tidak valid: ' . $row['phone_raw']);
                continue;
            }
            if (isset($seenNames[$nameKey]) || isset($existingNames[$nameKey])) {
                $skipped[] = $this->skip($row, 'Nama customer sudah ada');
                continue;
            }
            if (isset($seenPhones[$phone]) || isset($existingPhones[$phone])) {
                $skipped[] = $this->skip($row, 'Nomor telepon sudah dipakai');
                continue;
            }

            $lastUrut++;
            $planned[] = [
                'row' => $row['row'],
                'nama_pelanggan' => $row['nama'],
                'no_tlp_perusahaan' => $phone,
                'sub_kategori' => $row['bidang'],
                'no_urut' => str_pad((string) $lastUrut, 5, '0', STR_PAD_LEFT),
            ];
            $seenNames[$nameKey] = true;
            $seenPhones[$phone] = true;
        }

        if (!$execute) {
            foreach ($planned as &$item) {
                $item['id_pelanggan'] = $this->makeIdPelanggan($item['nama_pelanggan']);
            }
            unset($item);
        }

        $this->info($execute ? 'Mode: EXECUTE' : 'Mode: DRY-RUN');
        $this->info('Siap disimpan: ' . count($planned) . ' | Dilewati: ' . count($skipped));

        if ($planned && !$execute) {
            $this->table(
                ['Baris', 'ID Pelanggan', 'No Urut', 'Nama', 'Telepon', 'Bidang'],
                array_map(function ($item) {
                    return [
                        $item['row'],
                        $item['id_pelanggan'],
                        $item['no_urut'],
                        $item['nama_pelanggan'],
                        $item['no_tlp_perusahaan'],
                        $item['sub_kategori'],
                    ];
                }, array_slice($planned, 0, 15))
            );
            if (count($planned) > 15) {
                $this->line('... ' . (count($planned) - 15) . ' baris berikutnya tidak ditampilkan.');
            }
        }

        if ($skipped) {
            $this->warn('Baris yang dilewati:');
            $this->table(
                ['Baris', 'Nama', 'Telepon', 'Alasan'],
                array_map(function ($item) {
                    return [$item['row'], $item['nama'], $item['phone'], $item['reason']];
                }, $skipped)
            );
        }

        if (!$execute) {
            $this->comment('Belum ada data yang ditulis. Tambahkan --execute untuk menyimpan.');
            return 0;
        }

        if (!$planned) {
            $this->info('Tidak ada data baru untuk disimpan.');
            return 0;
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $actor = 'import-near-ho';

        DB::transaction(function () use (&$planned, $now, $actor) {
            foreach ($planned as &$item) {
                $item['id_pelanggan'] = $this->makeIdPelanggan($item['nama_pelanggan']);
                $pelanggan = MasterPelanggan::create([
                    'id_cabang' => 1,
                    'no_urut' => $item['no_urut'],
                    'id_pelanggan' => $item['id_pelanggan'],
                    'nama_pelanggan' => $item['nama_pelanggan'],
                    'wilayah' => null,
                    'kategori_pelanggan' => 'Domestik',
                    'sub_kategori' => $item['sub_kategori'] !== '' ? $item['sub_kategori'] : null,
                    'npwp' => null,
                    'bahan_pelanggan' => null,
                    'merk_pelanggan' => null,
                    'sales_penanggung_jawab' => null,
                    'sales_id' => null,
                    'created_by' => $actor,
                    'created_at' => $now,
                    'is_active' => 1,
                ]);

                KontakPelanggan::create([
                    'pelanggan_id' => $pelanggan->id,
                    'no_tlp_perusahaan' => $item['no_tlp_perusahaan'],
                    'email_perusahaan' => null,
                    'created_by' => $actor,
                    'created_at' => $now,
                    'is_active' => 1,
                ]);
            }
            unset($item);
        });

        $this->table(
            ['Baris', 'ID Pelanggan', 'No Urut', 'Nama', 'Telepon', 'Bidang'],
            array_map(function ($item) {
                return [
                    $item['row'],
                    $item['id_pelanggan'],
                    $item['no_urut'],
                    $item['nama_pelanggan'],
                    $item['no_tlp_perusahaan'],
                    $item['sub_kategori'],
                ];
            }, array_slice($planned, 0, 15))
        );
        if (count($planned) > 15) {
            $this->line('... ' . (count($planned) - 15) . ' baris berikutnya tidak ditampilkan.');
        }

        $this->info(count($planned) . ' customer berhasil disimpan.');
        return 0;
    }

    private function readRows(string $file): array
    {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];

        for ($row = 3; $row <= $sheet->getHighestRow(); $row++) {
            $nama = $this->readCell($sheet->getCell('B' . $row));
            $phoneRaw = $this->readCell($sheet->getCell('C' . $row));
            $bidang = $this->readCell($sheet->getCell('D' . $row));

            if ($nama === '' && $phoneRaw === '' && $bidang === '') {
                continue;
            }

            $rows[] = [
                'row' => $row,
                'nama' => $nama,
                'phone_raw' => $phoneRaw,
                'phone' => $this->normalizePhone($phoneRaw),
                'bidang' => mb_substr($bidang, 0, 70),
            ];
        }

        return $rows;
    }

    private function readCell(Cell $cell): string
    {
        $value = $cell->getValue();

        // File Google Sheets menyimpan teks asli di argumen terakhir IFERROR.
        if (is_string($value) && stripos($value, 'IFERROR') !== false) {
            if (preg_match('/,\s*"([^"]*)"\s*\)\s*$/', $value, $matches)) {
                return trim($matches[1]);
            }
        }

        $cached = $cell->getOldCalculatedValue();
        if ($cached !== null && $cached !== '') {
            return trim((string) $cached);
        }

        return trim((string) $value);
    }

    private function normalizePhone(string $raw): ?string
    {
        $raw = preg_replace("/'.*$/", '', $raw) ?? $raw;
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (strpos($digits, '62') === 0 && strlen($digits) > 9) {
            $digits = '0' . substr($digits, 2);
        }

        if ($digits[0] !== '0') {
            $digits = '0' . $digits;
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    private function nameKey(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', trim($name));

        return mb_strtoupper($name ?? '');
    }

    private function makeIdPelanggan(string $nama): string
    {
        $namaPelangganUpper = strtoupper(str_replace([' ', "\t", ','], '', $nama));

        for ($i = 1; $i <= 10; $i++) {
            $generatedId = $this->randomstr($namaPelangganUpper, $i);
            if (!MasterPelanggan::where('id_pelanggan', $generatedId)->exists()) {
                return $generatedId;
            }
        }

        throw new RuntimeException('Gagal menghasilkan id_pelanggan yang unik');
    }

    private function randomstr(string $str, int $no): string
    {
        $str = preg_replace('/[^A-Z]/', '', $str);

        return substr(str_shuffle($str), 0, 4) . sprintf('%02d', $no);
    }

    private function skip(array $row, string $reason): array
    {
        return [
            'row' => $row['row'],
            'nama' => $row['nama'],
            'phone' => $row['phone'] ?: $row['phone_raw'],
            'reason' => $reason,
        ];
    }
}
