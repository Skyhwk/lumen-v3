<?php

namespace App\Console\Commands;

use App\Models\AlamatPelanggan;
use App\Models\AlamatPelangganBackup;
use App\Models\KontakPelanggan;
use App\Models\KontakPelangganBackup;
use App\Models\MasterPelanggan;
use App\Models\MasterPelangganBackup;
use App\Models\PicPelanggan;
use App\Models\PicPelangganBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackupMasterPelangganDediWibowoCommand extends Command
{
    protected $signature = 'pelanggan:backup-dedi-wibowo
        {--dry-run : Simulasi tanpa memindahkan data}
        {--force : Lewati konfirmasi}
        {--sales= : Filter sales penanggung jawab (default: Dedi Wibowo)}';

    protected $description = 'Pindahkan data master pelanggan (beserta kontak, alamat, PIC) ke tabel backup lalu hapus dari master.';

    public function handle()
    {
        $salesName = trim((string) ($this->option('sales') ?: 'Dedi Wibowo'));
        $dryRun = (bool) $this->option('dry-run');

        $requiredTables = [
            'master_pelanggan_backup',
            'kontak_pelanggan_backup',
            'alamat_pelanggan_backup',
            'pic_pelanggan_backup',
        ];

        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("Tabel {$table} belum ada. Jalankan migration terlebih dahulu.");

                return 1;
            }
        }

        $query = MasterPelanggan::where('is_active', true)
            ->where('sales_penanggung_jawab', $salesName);

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info("Tidak ada pelanggan aktif dengan sales penanggung jawab \"{$salesName}\".");

            return 0;
        }

        $this->info("Sales: {$salesName}");
        $this->info('Mode: ' . ($dryRun ? 'DRY-RUN' : 'EXECUTE'));
        $this->info("Total pelanggan yang akan dipindahkan: {$total}");

        if (!$dryRun && !$this->option('force')) {
            if (!$this->confirm('Lanjutkan memindahkan data ke backup dan menghapus dari master?')) {
                $this->warn('Dibatalkan.');

                return 0;
            }
        }

        $moved = 0;

        $query->orderBy('id')->chunkById(100, function ($chunk) use ($dryRun, &$moved) {
            foreach ($chunk as $master) {
                if ($dryRun) {
                    $this->line("[DRY-RUN] {$master->id_pelanggan} - {$master->nama_pelanggan}");
                    $moved++;
                    continue;
                }

                DB::beginTransaction();
                try {
                    $this->movePelangganToBackup($master);
                    DB::commit();
                    $this->line("OK: {$master->id_pelanggan} - {$master->nama_pelanggan}");
                    $moved++;
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $this->error("GAGAL {$master->id_pelanggan}: {$e->getMessage()}");
                }
            }
        });

        $this->info(($dryRun ? 'Simulasi selesai' : 'Selesai') . ". Total: {$moved} pelanggan.");

        return 0;
    }

    private function movePelangganToBackup(MasterPelanggan $master): void
    {
        if (MasterPelangganBackup::where('id', $master->id)->exists()) {
            throw new \RuntimeException("Backup dengan ID internal {$master->id} sudah ada.");
        }

        $backupMaster = $master->replicate();
        $backupMaster->setTable((new MasterPelangganBackup())->getTable());
        $backupMaster->id = $master->id;
        $backupMaster->save();

        foreach (KontakPelanggan::where('pelanggan_id', $master->id)->get() as $row) {
            $backup = $row->replicate();
            $backup->setTable((new KontakPelangganBackup())->getTable());
            $backup->id = $row->id;
            $backup->save();
            $row->delete();
        }

        foreach (PicPelanggan::where('pelanggan_id', $master->id)->get() as $row) {
            $backup = $row->replicate();
            $backup->setTable((new PicPelangganBackup())->getTable());
            $backup->id = $row->id;
            $backup->save();
            $row->delete();
        }

        foreach (AlamatPelanggan::where('pelanggan_id', $master->id)->get() as $row) {
            $backup = $row->replicate();
            $backup->setTable((new AlamatPelangganBackup())->getTable());
            $backup->id = $row->id;
            $backup->save();
            $row->delete();
        }

        $master->delete();
    }
}
