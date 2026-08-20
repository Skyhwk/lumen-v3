<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\MasterKaryawan;

class UpdateFtcVerifierFromScanTc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ftc:update-verifier 
                            {--year= : Filter berdasarkan tahun (contoh: 2025)} 
                            {--month= : Filter berdasarkan bulan (1-12)} 
                            {--only-null : Hanya update jika ftc_verifier dan user_verifier pada t_ftc masih NULL} 
                            {--dry-run : Tampilkan simulasi jumlah data tanpa mengubah database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update ftc_verifier dan user_verifier pada tabel t_ftc berdasarkan data scan_sampel_tc';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $year = $this->option('year');
        $month = $this->option('month');
        $onlyNull = $this->option('only-null');
        $dryRun = $this->option('dry-run');

        $this->info('===== Start Command: UpdateFtcVerifierFromScanTc =====');
        if ($year) {
            $this->info("Filter Tahun: {$year}");
        }
        if ($month) {
            $this->info("Filter Bulan: {$month}");
        }

        // Optimized single query using LEFT JOIN to avoid N+1 query problem
        $query = DB::table('scan_sampel_tc as stc')
            ->leftJoin('t_ftc as f', 'f.no_sample', '=', 'stc.no_sampel')
            ->select(
                'stc.no_sampel', 
                'stc.created_at as scan_created_at', 
                'stc.created_by as scan_created_by', 
                'stc.updated_at as scan_updated_at', 
                'stc.updated_by as scan_updated_by',
                'f.id as ftc_id',
                'f.ftc_verifier',
                'f.user_verifier'
            )
            ->whereNotNull('stc.no_sampel')
            ->whereRaw("TRIM(stc.no_sampel) != ''");

        if ($year) {
            $query->whereYear('stc.created_at', $year);
        }
        if ($month) {
            $query->whereMonth('stc.created_at', $month);
        }

        if ($onlyNull) {
            $query->where(function ($q) {
                $q->whereNull('f.id')
                  ->orWhereNull('f.ftc_verifier')
                  ->orWhereNull('f.user_verifier');
            });
        }

        $scanRecords = $query->get();
        $total = count($scanRecords);

        $this->info("Total data scan_sampel_tc diproses: {$total}");

        if ($total === 0) {
            $this->info('Tidak ada data yang perlu diproses.');
            $this->info('===== Finish Command: UpdateFtcVerifierFromScanTc =====');
            return 0;
        }

        if ($dryRun) {
            $this->info('[DRY-RUN] Mode simulasi aktif. Tidak ada perubahan yang akan disimpan ke database.');
        }

        // Cache karyawan name to ID mapping
        $karyawanList = MasterKaryawan::select('id', 'nama_lengkap')->get();
        $karyawanMap = [];
        foreach ($karyawanList as $k) {
            if ($k->nama_lengkap) {
                $karyawanMap[strtolower(trim($k->nama_lengkap))] = $k->id;
            }
        }

        $updatedCount = 0;
        $insertedCount = 0;
        $skippedCount = 0;

        $nowStr = Carbon::now()->format('Y-m-d H:i:s');

        $executeProcess = function () use ($scanRecords, $dryRun, $nowStr, $karyawanMap, &$updatedCount, &$insertedCount) {
            foreach ($scanRecords as $scan) {
                $verifierTime = $scan->scan_created_at ? $scan->scan_created_at : ($scan->scan_updated_at ? $scan->scan_updated_at : $nowStr);
                
                // Resolve user_verifier ID
                $userVerifierId = null;
                $createdBy = $scan->scan_created_by ? $scan->scan_created_by : $scan->scan_updated_by;

                if ($createdBy) {
                    if (is_numeric($createdBy)) {
                        $userVerifierId = (int) $createdBy;
                    } else {
                        $cleanName = strtolower(trim($createdBy));
                        if (isset($karyawanMap[$cleanName])) {
                            $userVerifierId = $karyawanMap[$cleanName];
                        }
                    }
                }

                if (!empty($scan->ftc_id)) {
                    if (!$dryRun) {
                        DB::table('t_ftc')->where('id', $scan->ftc_id)->update([
                            'ftc_verifier' => $verifierTime,
                            'user_verifier' => $userVerifierId,
                        ]);
                    }
                    $updatedCount++;
                } else {
                    if (!$dryRun) {
                        DB::table('t_ftc')->insert([
                            'no_sample' => trim($scan->no_sampel),
                            'ftc_verifier' => $verifierTime,
                            'user_verifier' => $userVerifierId,
                            'is_active' => 1,
                        ]);
                    }
                    $insertedCount++;
                }
            }
        };

        if (!$dryRun) {
            DB::transaction($executeProcess);
        } else {
            $executeProcess();
        }

        $this->info("Ringkasan Hasil:");
        $this->info("- Diupdate                : {$updatedCount}");
        $this->info("- Dibuat baru (insert)    : {$insertedCount}");
        $this->info("- Dilewati (karena terisi): {$skippedCount}");
        $this->info('===== Finish Command: UpdateFtcVerifierFromScanTc =====');

        return 0;
    }
}
