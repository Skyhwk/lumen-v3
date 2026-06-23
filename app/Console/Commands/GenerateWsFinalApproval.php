<?php

namespace App\Console\Commands;

use App\Models\OrderDetail;
use App\Services\WsFinalApprovalService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateWsFinalApproval extends Command
{
    protected $signature = 'wsfinal:generate
        {--month= : Proses 1 bulan saja format YYYY-MM}
        {--all-months : Proses per bulan dari Januari tahun berjalan sampai bulan ini}
        {--from= : Tanggal awal format YYYY-MM-DD, default 1 Januari tahun berjalan}
        {--to= : Tanggal akhir format YYYY-MM-DD, default hari ini}
        {--chunk=50 : Jumlah header/LHP per proses}
        {--progress-every=10 : Tampilkan progress setiap jumlah header/LHP ini}
        {--approved-by=system : Nama pengisi approved_by untuk header yang complete}
        {--dry-run : Hitung data tanpa generate ke tabel ws_final_approval}';

    protected $description = 'Generate data ws_final_approval per LHP/CFR dari WS Final yang sudah approve';

    public function handle()
    {
        $chunk = max((int) $this->option('chunk'), 1);
        $progressEvery = max((int) $this->option('progress-every'), 1);
        $approvedBy = (string) $this->option('approved-by');
        $dryRun = (bool) $this->option('dry-run');

        $failed = 0;
        foreach ($this->ranges() as $range) {
            $failed += $this->processRange(
                $range['from'],
                $range['to'],
                $chunk,
                $progressEvery,
                $approvedBy,
                $dryRun
            );
        }

        return $failed > 0 ? 1 : 0;
    }

    private function processRange(Carbon $from, Carbon $to, int $chunk, int $progressEvery, string $approvedBy, bool $dryRun): int
    {
        $this->info(sprintf(
            '[WsFinalGenerate] Start from %s to %s%s',
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $dryRun ? ' (dry-run)' : ''
        ));

        $orderDetailIds = $this->approvedWsFinalOrderDetailIds($from, $to);
        $total = $orderDetailIds->count();

        $this->info(sprintf('[WsFinalGenerate] Total header/LHP: %d', $total));

        if ($dryRun || $total === 0) {
            $this->info('[WsFinalGenerate] Done');
            return 0;
        }

        $processed = 0;
        $failed = 0;

        $orderDetailIds->chunk($chunk)
            ->each(function ($idChunk) use (&$processed, &$failed, $total, $approvedBy, $progressEvery) {
                $orderDetails = OrderDetail::whereIn('id', $idChunk->values()->all())
                    ->orderBy('id')
                    ->get();

                foreach ($orderDetails as $orderDetail) {
                    try {
                        WsFinalApprovalService::finalizeLhp($orderDetail, true, $approvedBy);
                        $processed++;
                    } catch (\Throwable $th) {
                        $failed++;
                        $this->error(sprintf(
                            '[WsFinalGenerate] Failed %s: %s',
                            $this->lhpNumber($orderDetail),
                            $th->getMessage()
                        ));
                    }

                    if (($processed + $failed) % $progressEvery === 0) {
                        $this->info(sprintf(
                            '[WsFinalGenerate] Progress %d/%d, failed %d, last %s',
                            $processed + $failed,
                            $total,
                            $failed,
                            $this->lhpNumber($orderDetail)
                        ));
                    }
                }

                $this->info(sprintf(
                    '[WsFinalGenerate] Progress %d/%d, failed %d',
                    $processed,
                    $total,
                    $failed
                ));
            });

        $this->info(sprintf(
            '[WsFinalGenerate] DONE processed: %d, failed: %d',
            $processed,
            $failed
        ));

        return $failed;
    }

    private function ranges(): array
    {
        if ($this->option('month')) {
            $month = Carbon::createFromFormat('!Y-m', $this->option('month'));

            return [[
                'from' => $month->copy()->startOfMonth(),
                'to' => $month->copy()->endOfMonth(),
            ]];
        }

        if ($this->option('all-months')) {
            $current = Carbon::now()->startOfYear();
            $end = Carbon::now()->startOfMonth();
            $ranges = [];

            while ($current->lte($end)) {
                $ranges[] = [
                    'from' => $current->copy()->startOfMonth(),
                    'to' => $current->copy()->endOfMonth(),
                ];

                $current->addMonth();
            }

            return $ranges;
        }

        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::now()->startOfYear()->startOfDay();

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        return [[
            'from' => $from,
            'to' => $to,
        ]];
    }

    private function approvedWsFinalOrderDetailIds(Carbon $from, Carbon $to)
    {
        $sampleNumbers = $this->approvedWsFinalSampleNumbers($from, $to);

        return OrderDetail::query()
            ->where('is_active', true)
            ->whereIn('no_sampel', $sampleNumbers->all())
            ->selectRaw('MIN(id) as id')
            ->groupBy(DB::raw("COALESCE(NULLIF(cfr, ''), no_sampel)"))
            ->pluck('id')
            ->values();
    }

    private function approvedWsFinalSampleNumbers(Carbon $from, Carbon $to)
    {
        $samples = $this->eligibleWsFinalQuery($from, $to)
            ->whereIn('status', [1, 2])
            ->pluck('no_sampel');

        foreach (WsFinalApprovalService::parameterSourceClasses() as $modelClass) {
            if (!class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass();
            $table = $model->getTable();

            if (!Schema::hasColumn($table, 'no_sampel')) {
                continue;
            }

            $approvalColumn = $this->approvalColumn($table);
            if ($approvalColumn === null) {
                continue;
            }

            $query = $modelClass::query()
                ->join('order_detail as od', "{$table}.no_sampel", '=', 'od.no_sampel')
                ->where('od.is_active', true)
                ->whereIn('od.kategori_2', [
                    '1-Air',
                    '4-Udara',
                    '5-Emisi',
                    '6-Padatan',
                ])
                ->whereBetween('od.tanggal_sampling', [
                    $from->format('Y-m-d'),
                    $to->format('Y-m-d'),
                ])
                ->where("{$table}.{$approvalColumn}", 1);

            if (Schema::hasColumn($table, 'is_active')) {
                $query->where("{$table}.is_active", true);
            }

            $samples = $samples->merge(
                $query->distinct()->pluck("{$table}.no_sampel")
            );
        }

        return $samples
            ->filter()
            ->unique()
            ->values();
    }

    private function eligibleWsFinalQuery(Carbon $from, Carbon $to)
    {
        return OrderDetail::query()
            ->where('is_active', true)
            ->whereIn('kategori_2', [
                '1-Air',
                '4-Udara',
                '5-Emisi',
                '6-Padatan',
            ])
            ->whereBetween('tanggal_sampling', [
                $from->format('Y-m-d'),
                $to->format('Y-m-d'),
            ]);
    }

    private function approvalColumn(string $table): ?string
    {
        foreach (['lhps', 'is_approve', 'is_approved'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function lhpNumber(OrderDetail $orderDetail): string
    {
        return $orderDetail->cfr ?: $orderDetail->no_sampel;
    }
}
