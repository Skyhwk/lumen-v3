<?php

namespace App\Console\Commands;

use App\Models\HistoryPerubahanSales;
use App\Models\LogWebphone;
use App\Models\LogWebphoneBackup;
use App\Models\MasterKaryawan;
use App\Models\MasterPelanggan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignBankDataOrderedCommand extends Command
{
    protected $signature = 'pelanggan:assign-bank-data-ordered
        {--sales-id=1068 : ID sales tujuan (default Asti Susilawati)}
        {--dry-run : Simulasi tanpa mengubah database}
        {--force : Lewati konfirmasi}
        {--skip-webphone : Jangan arsipkan log webphone 2 bulan terakhir}';

    protected $description = 'Assign pelanggan bank data yang sudah pernah order ke sales (default Asti Susilawati 1068).';

    public function handle()
    {
        $salesId = (int) $this->option('sales-id');
        $dryRun = (bool) $this->option('dry-run');
        $skipWebphone = (bool) $this->option('skip-webphone');

        $sales = MasterKaryawan::select('id', 'nama_lengkap', 'is_active')
            ->where('id', $salesId)
            ->first();

        if (!$sales) {
            $this->error("Sales id {$salesId} tidak ditemukan.");

            return 1;
        }

        if (!$sales->is_active) {
            $this->error("Sales {$sales->nama_lengkap} (id {$salesId}) tidak aktif.");

            return 1;
        }

        $query = $this->eligibleQuery();
        $eligible = (clone $query)->count();
        $stats = $this->collectStats();

        $this->info("Sales tujuan: {$sales->nama_lengkap} ({$sales->id})");
        $this->info('Mode: ' . ($dryRun ? 'DRY-RUN' : 'EXECUTE'));
        $this->info("Total bank data (aktif, sales_id & sales_penanggung_jawab NULL): {$stats['bank_data']}");
        $this->info("Bank data sales_id NULL saja: {$stats['sales_id_null']}");
        $this->info("Bank data sales_id NULL + sales_pj kosong/null: {$stats['sales_id_null_pj_empty']}");
        $this->info("Punya order_header aktif: {$stats['active_order']}");
        $this->info("Punya order_header apa pun (sudah pernah order): {$eligible}");
        $this->info("Punya order_header aktif (join master_pelanggan.id): {$stats['active_order_on_id']}");

        if ($eligible === 0) {
            $this->info('Tidak ada pelanggan bank data yang sudah order.');

            return 0;
        }

        $preview = (clone $query)
            ->orderBy('id')
            ->limit(50)
            ->get([
                'id',
                'id_pelanggan',
                'nama_pelanggan',
            ]);

        $orderTotals = DB::table('order_header')
            ->select('id_pelanggan', DB::raw('COUNT(*) as total_order'), DB::raw('SUM(is_active = 1) as active_order'))
            ->whereIn('id_pelanggan', $preview->pluck('id_pelanggan'))
            ->groupBy('id_pelanggan')
            ->get()
            ->keyBy('id_pelanggan');

        $this->table(
            ['ID', 'ID Pelanggan', 'Nama', 'Order aktif', 'Total order'],
            $preview->map(function ($p) use ($orderTotals) {
                $row = $orderTotals->get($p->id_pelanggan);

                return [
                    $p->id,
                    $p->id_pelanggan,
                    $p->nama_pelanggan,
                    $row->active_order ?? 0,
                    $row->total_order ?? 0,
                ];
            })->toArray()
        );

        if ($eligible > 20) {
            $this->line('... dan ' . ($eligible - 20) . ' lainnya.');
        }

        if ($dryRun) {
            $this->warn('Dry-run: tidak ada data yang diubah.');

            return 0;
        }

        if (!$this->option('force') && !$this->confirm("Assign {$eligible} pelanggan ke {$sales->nama_lengkap}?")) {
            $this->warn('Dibatalkan.');

            return 0;
        }

        $assigned = 0;
        $webphoneMoved = 0;
        $now = Carbon::now();

        $query->orderBy('id')->chunkById(200, function ($chunk) use ($sales, $now, $skipWebphone, &$assigned, &$webphoneMoved) {
            DB::transaction(function () use ($chunk, $sales, $now, $skipWebphone, &$assigned, &$webphoneMoved) {
                $ids = $chunk->pluck('id')->all();

                MasterPelanggan::whereIn('id', $ids)->update([
                    'sales_id' => $sales->id,
                    'sales_penanggung_jawab' => $sales->nama_lengkap,
                    'updated_by' => 'System',
                    'updated_at' => $now,
                ]);

                HistoryPerubahanSales::insert(
                    $chunk->map(fn ($pelanggan) => [
                        'id_pelanggan' => $pelanggan->id_pelanggan,
                        'id_sales_lama' => null,
                        'id_sales_baru' => $sales->id,
                        'tanggal_rotasi' => $now,
                    ])->all()
                );

                if (!$skipWebphone) {
                    $webphoneMoved += $this->archiveWebphoneLogs($ids);
                }

                $assigned += count($ids);
            });

            $this->line("Progress: {$assigned}");
        });

        $this->info("Selesai. {$assigned} pelanggan di-assign ke {$sales->nama_lengkap}.");
        if (!$skipWebphone) {
            $this->info("Log webphone diarsipkan: {$webphoneMoved}");
        }

        return 0;
    }

    private function eligibleQuery()
    {
        return MasterPelanggan::query()
            ->select(['id', 'id_pelanggan', 'nama_pelanggan', 'sales_id', 'sales_penanggung_jawab'])
            ->where('is_active', true)
            ->whereNull('sales_id')
            ->whereNull('sales_penanggung_jawab')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('order_header')
                    ->whereColumn('order_header.id_pelanggan', 'master_pelanggan.id_pelanggan');
            });
    }

    private function collectStats(): array
    {
        $bankData = MasterPelanggan::where('is_active', true)
            ->whereNull('sales_id')
            ->whereNull('sales_penanggung_jawab');

        $salesIdNull = MasterPelanggan::where('is_active', true)->whereNull('sales_id');

        return [
            'bank_data' => (clone $bankData)->count(),
            'sales_id_null' => (clone $salesIdNull)->count(),
            'sales_id_null_pj_empty' => (clone $salesIdNull)
                ->where(function ($q) {
                    $q->whereNull('sales_penanggung_jawab')
                        ->orWhere('sales_penanggung_jawab', '');
                })
                ->count(),
            'active_order' => (clone $bankData)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('order_header')
                        ->whereColumn('order_header.id_pelanggan', 'master_pelanggan.id_pelanggan')
                        ->where('order_header.is_active', 1);
                })
                ->count(),
            'any_order' => (clone $bankData)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('order_header')
                        ->whereColumn('order_header.id_pelanggan', 'master_pelanggan.id_pelanggan');
                })
                ->count(),
            'active_order_on_id' => (clone $bankData)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('order_header')
                        ->whereColumn('order_header.id_pelanggan', 'master_pelanggan.id')
                        ->where('order_header.is_active', 1);
                })
                ->count(),
        ];
    }

    private function archiveWebphoneLogs(array $customerIds): int
    {
        $numbers = MasterPelanggan::with('kontak_pelanggan')
            ->whereIn('id', $customerIds)
            ->get()
            ->flatMap(function ($pelanggan) {
                if (!$pelanggan->kontak_pelanggan) {
                    return [];
                }

                return $pelanggan->kontak_pelanggan->pluck('no_tlp_perusahaan');
            })
            ->filter()
            ->unique()
            ->values();

        if ($numbers->isEmpty()) {
            return 0;
        }

        $moved = 0;
        $logIds = LogWebphone::whereIn('number', $numbers->all())
            ->where('created_at', '>=', Carbon::now()->subMonths(2))
            ->pluck('id');

        $logIds->chunk(1000)->each(function ($chunk) use (&$moved) {
            $ids = $chunk->all();
            $logs = LogWebphone::whereIn('id', $ids)->get();

            if ($logs->isEmpty()) {
                return;
            }

            LogWebphoneBackup::insert(
                $logs->map(function ($log) {
                    $data = $log->toArray();
                    unset($data['id']);

                    if (!empty($data['created_at'])) {
                        $data['created_at'] = Carbon::parse($data['created_at'])->format('Y-m-d H:i:s');
                    }

                    return $data;
                })->all()
            );

            LogWebphone::whereIn('id', $ids)->delete();
            $moved += $logs->count();
        });

        return $moved;
    }
}
