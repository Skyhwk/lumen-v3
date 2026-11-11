<?php

namespace App\Console\Commands;

use App\Models\ClaimFeeExternal;
use App\Models\DailyQsd;
use App\Models\MasterFeeSales;
use App\Models\MasterKaryawan;
use App\Models\MasterTargetSales;
use App\Models\MutasiFeeSales;
use App\Models\RekapFeeSales;
use App\Models\SaldoFeeSales;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kalkulasi fee sales bulanan (dijalankan di akhir bulan).
 *
 * Alur:
 *  1) Phase 1 — bulan current: buat master + rekap dari QSD yang tanggal_kelompok di bulan itu saja.
 *  2) Phase 2 — bulan-bulan sebelumnya: rekap is_lunas=0 yang QSD-nya sudah lunas → kredit ke master rekap terkait.
 */
class CalculateFeeSales extends Command
{
    protected $signature = 'calculatefee:run
                            {period? : YYYY-MM, default bulan berjalan}
                            {--dry-run : Simulasi tanpa menulis DB, tabel per sales}
                            {--sales-id= : Batasi dry-run / kalkulasi ke satu sales}';

    protected $description = 'Kalkulasi fee sales: Phase 1 master bulan berjalan, Phase 2 pelunasan rekap historis';

    /** Jabatan yang boleh Phase 1 (master bulan berjalan) saat run bulan kalender sekarang. */
    private const JABATAN_SALES_OFFICER = 24;

    private const JABATAN_CUSTOMER_RELATION_OFFICER = 148;

    private const INDO_MONTHS = [
        '01' => 'januari',
        '02' => 'februari',
        '03' => 'maret',
        '04' => 'april',
        '05' => 'mei',
        '06' => 'juni',
        '07' => 'juli',
        '08' => 'agustus',
        '09' => 'september',
        '10' => 'oktober',
        '11' => 'november',
        '12' => 'desember',
    ];

    private Carbon $cutOff;
    private string $year;
    private string $month;
    private string $period;
    private Carbon $periodStart;
    private Carbon $periodEnd;
    private string $monthStr;
    private array $categoryStr;
    private bool $dryRun = false;
    private ?int $filterSalesId = null;

    public function handle(): int
    {
        $startTime = microtime(true);
        $periodArg = $this->argument('period');

        if ($periodArg !== null && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodArg)) {
            $this->error('Format periode tidak valid. Gunakan YYYY-MM, contoh: 2026-09');

            return 1;
        }

        $this->dryRun = (bool) $this->option('dry-run');
        $salesIdOpt = $this->option('sales-id');
        $this->filterSalesId = $salesIdOpt !== null && $salesIdOpt !== '' ? (int) $salesIdOpt : null;

        $this->bootstrapPeriod($periodArg);

        $this->output->newLine();
        $this->info('  START FEE SALES — periode ' . $this->period . ($this->dryRun ? ' [DRY-RUN]' : ''));
        $this->info('  Waktu: ' . Carbon::now()->toDateTimeString());

        if ($this->dryRun) {
            $this->runDryRunPresentation();
            $duration = microtime(true) - $startTime;
            $this->output->newLine();
            $this->comment('Dry-run selesai (' . number_format($duration, 2) . ' detik). Tanpa perubahan DB.');
            $this->line('Jalankan: php artisan calculatefee:run' . ($periodArg ? ' ' . $periodArg : ''));

            return 0;
        }

        $phase1Ok = $this->phase1CloseCurrentMonth();
        $phase2Ok = $this->phase2CreditNewlyPaidRecaps();

        $duration = microtime(true) - $startTime;

        $this->output->newLine();
        $this->info(str_repeat('=', 72));
        if ($phase1Ok && $phase2Ok) {
            $this->info('  Selesai (' . number_format($duration, 2) . ' detik)');

            return 0;
        }

        $this->warn('  Selesai dengan error di salah satu phase (' . number_format($duration, 2) . ' detik)');

        return 1;
    }

    private function bootstrapPeriod(?string $periodArg): void
    {
        Carbon::setLocale('id');

        $this->cutOff = Carbon::create(2026, 1, 1);
        $this->categoryStr = config('kategori.id');

        if ($periodArg) {
            [$this->year, $this->month] = explode('-', $periodArg);
        } else {
            $now = Carbon::now();
            $this->year = (string) $now->year;
            $this->month = $now->format('m');
        }

        $this->period = $this->year . '-' . $this->month;
        $this->periodStart = Carbon::create((int) $this->year, (int) $this->month)->startOfMonth();
        $this->periodEnd = Carbon::create((int) $this->year, (int) $this->month)->endOfMonth();
        $this->monthStr = self::INDO_MONTHS[$this->month];
    }

    private function logCli(string $message, string $type = 'INFO'): void
    {
        $map = [
            'ERROR' => 'error',
            'SUCCESS' => 'info',
            'WARNING' => 'warn',
        ];
        $method = $map[$type] ?? 'line';
        $this->{$method}('[' . $type . '] ' . $message);
    }

    // -------------------------------------------------------------------------
    // Phase 1: master + rekap untuk QSD bulan current saja (bukan kumulatif).
    // -------------------------------------------------------------------------

    private function phase1CloseCurrentMonth(): bool
    {
        $this->output->newLine();
        $this->info('--- PHASE 1: tutup buku bulan ' . $this->period . ' ---');
        if ($this->isRunningCurrentCalendarMonth()) {
            $this->comment(
                'Bulan kalender sekarang: hanya Sales Officer (SO) yang dibuatkan master; '
                . 'CRO / jabatan lain hanya Phase 2 (pelunasan bulan sebelumnya).'
            );
        }

        $targets = MasterTargetSales::with('sales:id,nama_lengkap,id_jabatan')
            ->where(['tahun' => $this->year, 'is_active' => true])
            ->whereNotNull($this->monthStr)
            ->get();

        $this->logCli('Target aktif: ' . $targets->count() . ' sales');

        $allOk = true;

        foreach ($targets as $index => $targetSales) {
            $salesId = (int) $targetSales->karyawan_id;
            if ($this->filterSalesId !== null && $salesId !== $this->filterSalesId) {
                continue;
            }
            $salesName = $targetSales->sales->nama_lengkap ?? (string) $salesId;

            $this->line(sprintf('[%d/%d] %s', $index + 1, $targets->count(), $salesName));

            DB::beginTransaction();
            try {
                $plan = $this->buildPhase1Plan($targetSales, $salesId);
                if ($plan['action'] === 'create') {
                    $this->persistPhase1Master($targetSales, $salesId, $plan);
                    $this->logCli('Master + rekap tersimpan', 'SUCCESS');
                } else {
                    $this->logCli($plan['status'] . ', lewati.', 'WARNING');
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $allOk = false;
                $this->logCli($e->getMessage() . ' (baris ' . $e->getLine() . ')', 'ERROR');
            }
        }

        return $allOk;
    }

    /**
     * @return array{
     *   action: string,
     *   status: string,
     *   quotations?: Collection,
     *   target_category?: Collection,
     *   achieved_category?: array,
     *   percentage_category?: float,
     *   target_amount?: float,
     *   achieved_amount?: float,
     *   rate?: float,
     *   percentage_amount?: float,
     *   basis?: float,
     *   paid_achieved_amount?: float,
     *   estimated_fee?: float,
     *   claimed_fee?: float,
     *   rekap_count?: int,
     *   rekap_lunas_count?: int,
     * }
     */
    private function buildPhase1Plan(MasterTargetSales $targetSales, int $salesId): array
    {
        $role = $this->describeSalesRole($salesId);

        if ($this->isRunningCurrentCalendarMonth() && !$this->isSalesOfficerForPhase1($salesId)) {
            return [
                'action' => 'skip',
                'status' => $role['label'] . ' — bukan SO, Phase 1 dilewati (hanya pelunasan historis)',
                'jabatan_code' => $role['code'],
                'jabatan_label' => $role['label'],
            ];
        }

        if (MasterFeeSales::where(['sales_id' => $salesId, 'period' => $this->period, 'is_active' => true])->exists()) {
            return [
                'action' => 'skip',
                'status' => 'Master sudah ada',
                'jabatan_code' => $role['code'],
                'jabatan_label' => $role['label'],
            ];
        }

        $quotations = $this->loadPhase1Quotations($salesId);

        if ($quotations->isEmpty()) {
            return [
                'action' => 'skip',
                'status' => 'Tidak ada QSD baru di bulan ini',
                'jabatan_code' => $role['code'],
                'jabatan_label' => $role['label'],
            ];
        }

        $figures = $this->computePhase1Figures($targetSales, $quotations);

        return array_merge([
            'action' => 'create',
            'status' => 'Buat master + rekap',
            'quotations' => $quotations,
            'jabatan_code' => $role['code'],
            'jabatan_label' => $role['label'],
        ], $figures);
    }

    private function isRunningCurrentCalendarMonth(): bool
    {
        return $this->period === Carbon::now()->format('Y-m');
    }

    private function isSalesOfficerForPhase1(int $salesId): bool
    {
        $jabatanId = MasterKaryawan::where('id', $salesId)->where('is_active', true)->value('id_jabatan');

        return (int) $jabatanId === self::JABATAN_SALES_OFFICER;
    }

    /**
     * @return array{code: string, label: string, id_jabatan: int|null}
     */
    private function describeSalesRole(int $salesId): array
    {
        $jabatanId = MasterKaryawan::where('id', $salesId)->where('is_active', true)->value('id_jabatan');
        $jabatanId = $jabatanId !== null ? (int) $jabatanId : null;

        if ($jabatanId === self::JABATAN_SALES_OFFICER) {
            return ['code' => 'SO', 'label' => 'Sales Officer (SO)', 'id_jabatan' => $jabatanId];
        }
        if ($jabatanId === self::JABATAN_CUSTOMER_RELATION_OFFICER) {
            return ['code' => 'CRO', 'label' => 'Customer Relation Officer (CRO)', 'id_jabatan' => $jabatanId];
        }

        $nama = $jabatanId
            ? DB::table('master_jabatan')->where('id', $jabatanId)->value('nama_jabatan')
            : null;

        return [
            'code' => 'LAIN',
            'label' => $nama ? $nama . ' (id=' . $jabatanId . ')' : 'Jabatan tidak diketahui',
            'id_jabatan' => $jabatanId,
        ];
    }

    private function loadPhase1Quotations(int $salesId): Collection
    {
        $existingRecaps = RekapFeeSales::whereHas(
            'masterFeeSales',
            fn ($q) => $q->where(['sales_id' => $salesId, 'is_active' => true])
        )->get();

        return DailyQsd::with('orderHeader.orderDetail')
            ->where('sales_id', $salesId)
            ->whereDate('tanggal_kelompok', '>=', $this->periodStart)
            ->whereDate('tanggal_kelompok', '<=', $this->periodEnd)
            ->get()
            ->map(fn ($qsd) => $this->mapQsdForFee($qsd, $existingRecaps))
            ->filter()
            ->values();
    }

    private function computePhase1Figures(MasterTargetSales $targetSales, Collection $quotations): array
    {
        $targetCategory = collect($targetSales->{$this->monthStr});
        $achievedCategoryDetails = $targetCategory->map(function ($_, $category) use ($targetCategory, $quotations) {
            $target = $targetCategory[$category];
            $achieved = $quotations
                ->flatMap(fn ($q) => optional($q->orderHeader)->orderDetail)
                ->filter(fn ($od) => collect($this->categoryStr[$category] ?? [])->contains($od->kategori_3))
                ->count();

            $calculated = $target && $achieved ? (int) floor($achieved / $target) : 0;

            return [
                'target' => $target,
                'achieved' => $achieved,
                'point' => $calculated > 3 ? 3 : $calculated,
            ];
        });

        $totalAchievedPoint = $achievedCategoryDetails->sum('point');
        $totalAchievedPoint = $totalAchievedPoint === 0 ? 1 : $totalAchievedPoint;
        $totalTargetPoint = $targetCategory->filter(fn ($v) => $v > 0)->count() ?: 1;

        $achievedCategory = [
            'total_target' => $achievedCategoryDetails->sum('target'),
            'total_achieved' => $achievedCategoryDetails->sum('achieved'),
            'total_point' => $totalAchievedPoint . '/' . $totalTargetPoint,
            'achieved_category_details' => $achievedCategoryDetails->toArray(),
        ];
        $percentageCategory = $totalAchievedPoint / $totalTargetPoint;

        $targetAmount = (float) (json_decode($targetSales->target, true)[$this->period] ?? 0);
        $achievedAmount = (float) $quotations->sum('total_revenue');
        $rate = ($achievedAmount >= $targetAmount ? 5 : 1) / 100;
        $percentageAmount = $targetAmount > 0 ? $achievedAmount / $targetAmount : 0.0;
        $basis = $rate * $percentageCategory;
        $paidAchievedAmount = (float) $quotations->sum('total_revenue_yg_lunas');
        $estimatedFee = $achievedAmount * $basis;
        $claimedFee = $paidAchievedAmount * $basis;

        return [
            'target_category' => $targetCategory,
            'achieved_category' => $achievedCategory,
            'percentage_category' => $percentageCategory,
            'target_amount' => $targetAmount,
            'achieved_amount' => $achievedAmount,
            'rate' => $rate,
            'percentage_amount' => $percentageAmount,
            'basis' => $basis,
            'paid_achieved_amount' => $paidAchievedAmount,
            'estimated_fee' => $estimatedFee,
            'claimed_fee' => $claimedFee,
            'rekap_count' => $quotations->count(),
            'rekap_lunas_count' => $quotations->filter(fn ($q) => $q->is_lunas)->count(),
        ];
    }

    private function persistPhase1Master(MasterTargetSales $targetSales, int $salesId, array $plan): void
    {
        /** @var Collection $quotations */
        $quotations = $plan['quotations'];

        $master = new MasterFeeSales();
        $master->sales_id = $salesId;
        $master->period = $this->period;
        $master->target_category = json_encode($plan['target_category']);
        $master->achieved_category = json_encode($plan['achieved_category']);
        $master->percentage_category = $plan['percentage_category'];
        $master->target_amount = $plan['target_amount'];
        $master->achieved_amount = $plan['achieved_amount'];
        $master->rate = $plan['rate'];
        $master->percentage_amount = $plan['percentage_amount'];
        $master->basis = $plan['basis'];
        $master->paid_achieved_amount = $plan['paid_achieved_amount'];
        $master->estimated_fee = $plan['estimated_fee'];
        $master->claimed_fee = $plan['claimed_fee'];
        $master->created_by = 'System';
        $master->created_at = $this->periodEnd;
        $master->save();

        $claimedFee = (float) $plan['claimed_fee'];

        RekapFeeSales::insert($quotations->map(fn ($q) => [
            'fee_sales_id' => $master->id,
            'no_document' => $q->no_quotation,
            'no_order' => $q->no_order,
            'nama_perusahaan' => $q->nama_perusahaan,
            'periode' => $q->periode,
            'kategori_3' => json_encode(
                optional($q->orderHeader)->orderDetail
                    ? $q->orderHeader->orderDetail->pluck('kategori_3')->toArray()
                    : []
            ),
            'no_invoice' => preg_replace('/\s*\(Lunas\)\s*/i', '', (string) $q->no_invoice),
            'is_lunas' => $q->is_lunas ? 1 : 0,
            'total_revenue' => $q->total_revenue,
        ])->values()->all());

        if ($claimedFee > 0) {
            $this->insertMutasiKredit($salesId, $this->period, $claimedFee, $this->periodEnd);
            $this->addSaldo($salesId, $claimedFee, $this->periodEnd);
        }
    }

    // -------------------------------------------------------------------------
    // Phase 2: rekap belum lunas + QSD sudah lunas → kredit per master rekap.
    // -------------------------------------------------------------------------

    private function phase2CreditNewlyPaidRecaps(): bool
    {
        $this->output->newLine();
        $this->info('--- PHASE 2: pelunasan rekap (semua bulan ≤ ' . $this->period . ') ---');

        $targetSalesIds = MasterTargetSales::query()
            ->where(['tahun' => $this->year, 'is_active' => true])
            ->whereNotNull($this->monthStr)
            ->pluck('karyawan_id');

        $openSalesIds = DB::table('rekap_fee_sales as r')
            ->join('master_fee_sales as m', 'm.id', '=', 'r.fee_sales_id')
            ->where('m.is_active', true)
            ->where('r.is_lunas', 0)
            ->distinct()
            ->pluck('m.sales_id');

        $salesIds = $targetSalesIds->merge($openSalesIds)->unique()->values();

        $this->logCli(sprintf(
            'Sales diproses: %d (%d target, %d masih ada rekap belum lunas)',
            $salesIds->count(),
            $targetSalesIds->count(),
            $openSalesIds->unique()->count()
        ));

        $allOk = true;

        foreach ($salesIds as $salesId) {
            $salesId = (int) $salesId;
            if ($this->filterSalesId !== null && $salesId !== $this->filterSalesId) {
                continue;
            }
            $salesName = MasterKaryawan::where('id', $salesId)->value('nama_lengkap') ?? (string) $salesId;

            $batches = $this->collectLunasCreditBatches($salesId);

            if ($batches->isEmpty()) {
                continue;
            }

            $this->line('>>> ' . $salesName . ': ' . $batches->sum(fn ($b) => count($b['recap_ids'])) . ' rekap lunas baru');

            DB::beginTransaction();
            try {
                $totalSaldoCredit = 0.0;

                foreach ($batches as $masterId => $batch) {
                    $master = MasterFeeSales::where('id', $masterId)->where('is_active', true)->lockForUpdate()->first();
                    if (!$master) {
                        continue;
                    }

                    $claimedFee = $batch['claimed_fee'];
                    if ($claimedFee <= 0) {
                        continue;
                    }

                    $master->paid_achieved_amount += $batch['paid_achieved'];
                    $master->claimed_fee += $claimedFee;
                    $master->updated_by = 'System';
                    $master->updated_at = $this->periodEnd;
                    $master->save();

                    RekapFeeSales::whereIn('id', $batch['recap_ids'])->update(['is_lunas' => 1]);

                    $this->insertMutasiKredit($salesId, $master->period, $claimedFee, $this->periodEnd);

                    $totalSaldoCredit += $claimedFee;
                }

                if ($totalSaldoCredit > 0) {
                    $this->addSaldo($salesId, $totalSaldoCredit, $this->periodEnd);
                }

                DB::commit();
                $this->logCli('Kredit pelunasan OK: ' . $salesName, 'SUCCESS');
            } catch (\Throwable $e) {
                DB::rollBack();
                $allOk = false;
                $this->logCli($salesName . ': ' . $e->getMessage(), 'ERROR');
            }
        }

        return $allOk;
    }

    /**
     * @return Collection<int, array{claimed_fee: float, paid_achieved: float, recap_ids: int[]}>
     */
    private function collectLunasCreditBatches(int $salesId): Collection
    {
        $openRecaps = RekapFeeSales::query()
            ->from('rekap_fee_sales as r')
            ->join('master_fee_sales as m', 'm.id', '=', 'r.fee_sales_id')
            ->where('m.is_active', true)
            ->where('m.sales_id', $salesId)
            ->where('r.is_lunas', 0)
            ->select([
                'r.id as rekap_id',
                'r.fee_sales_id',
                'r.no_order',
                'r.periode',
                'r.total_revenue',
                'm.basis',
                'm.period as master_period',
            ])
            ->get();

        $batches = [];

        foreach ($openRecaps as $row) {
            $qsd = DailyQsd::query()
                ->where('sales_id', $salesId)
                ->where('no_order', $row->no_order)
                ->when($row->periode, fn ($q) => $q->where('periode', $row->periode))
                ->whereDate('tanggal_kelompok', '>=', $this->cutOff)
                ->whereDate('tanggal_kelompok', '<=', $this->periodEnd)
                ->first();

            if (!$qsd || !$qsd->is_lunas) {
                continue;
            }

            $revenue = (float) $row->total_revenue;
            $fee = $revenue * (float) $row->basis;
            $masterId = (int) $row->fee_sales_id;

            if (!isset($batches[$masterId])) {
                $batches[$masterId] = [
                    'claimed_fee' => 0.0,
                    'paid_achieved' => 0.0,
                    'recap_ids' => [],
                ];
            }

            $batches[$masterId]['claimed_fee'] += $fee;
            $batches[$masterId]['paid_achieved'] += $revenue;
            $batches[$masterId]['recap_ids'][] = (int) $row->rekap_id;
        }

        return collect($batches);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function recapAlreadyExists(Collection $existingRecaps, DailyQsd $qsd): bool
    {
        return $existingRecaps->contains(function ($recap) use ($qsd) {
            if ($recap->no_order !== $qsd->no_order) {
                return false;
            }
            if (!$qsd->periode) {
                return true;
            }

            return $recap->periode === $qsd->periode;
        });
    }

    private function mapQsdForFee(DailyQsd $qsd, Collection $existingRecaps): ?DailyQsd
    {
        if ($this->recapAlreadyExists($existingRecaps, $qsd)) {
            return null;
        }

        $totalFeeExternal = ClaimFeeExternal::where(['no_order' => $qsd->no_order, 'is_active' => true])
            ->when($qsd->periode, fn ($q) => $q->where('periode', $qsd->periode))
            ->sum('nominal');

        $totalRevenue = $qsd->total_revenue - ($totalFeeExternal + $qsd->nilai_pengurangan);
        $qsd->total_revenue = $totalRevenue;
        $qsd->total_revenue_yg_lunas = $qsd->is_lunas ? $totalRevenue : 0;

        if ($qsd->periode) {
            $orderDetail = optional($qsd->orderHeader)->orderDetail
                ? $qsd->orderHeader->orderDetail->filter(fn ($od) => $od->periode === $qsd->periode)->values()
                : collect();
            if ($orderDetail->isNotEmpty()) {
                $qsd->orderHeader->setRelation('orderDetail', $orderDetail);
            }
        }

        return $qsd;
    }

    private function insertMutasiKredit(
        int $salesId,
        string $masterPeriod,
        float $amount,
        Carbon $createdAt,
        ?string $descriptionSuffix = null
    ): void {
        $label = Carbon::createFromFormat('Y-m', $masterPeriod)->translatedFormat('F Y');
        $description = 'Fee Sales ' . $label;
        if ($descriptionSuffix) {
            $description .= ' — ' . $descriptionSuffix;
        }

        $mutasi = new MutasiFeeSales();
        $mutasi->sales_id = $salesId;
        $mutasi->batch_number = str_replace('.', '/', (string) microtime(true));
        $mutasi->period = $masterPeriod;
        $mutasi->mutation_type = 'Kredit';
        $mutasi->amount = $amount;
        $mutasi->description = $description;
        $mutasi->status = 'Done';
        $mutasi->created_by = 'System';
        $mutasi->created_at = $createdAt;
        $mutasi->save();
    }

    private function addSaldo(int $salesId, float $amount, Carbon $at): void
    {
        $saldo = SaldoFeeSales::where(['sales_id' => $salesId, 'is_active' => true])->latest()->first();

        if ($saldo) {
            $saldo->active_balance += $amount;
            $saldo->updated_by = 'System';
            $saldo->updated_at = $at;
        } else {
            $saldo = new SaldoFeeSales();
            $saldo->sales_id = $salesId;
            $saldo->active_balance = $amount;
            $saldo->created_by = 'System';
            $saldo->created_at = $at;
        }

        $saldo->save();
    }

    // -------------------------------------------------------------------------
    // Dry-run: tabel per sales
    // -------------------------------------------------------------------------

    private function runDryRunPresentation(): void
    {
        $this->warn('Mode dry-run — tidak ada INSERT/UPDATE ke database.');

        $phase1BySales = $this->collectDryRunPhase1Plans();
        $phase2BySales = $this->collectDryRunPhase2Plans();

        $allSalesIds = $phase1BySales->keys()
            ->merge($phase2BySales->keys())
            ->unique()
            ->sort()
            ->values();

        if ($allSalesIds->isEmpty()) {
            $this->warn('Tidak ada sales yang memenuhi filter / target untuk periode ini.');

            return;
        }

        $summaryRows = [];

        foreach ($allSalesIds as $salesId) {
            $salesId = (int) $salesId;
            $name = MasterKaryawan::where('id', $salesId)->value('nama_lengkap') ?? (string) $salesId;
            $saldoNow = (float) (SaldoFeeSales::where(['sales_id' => $salesId, 'is_active' => true])->latest()->value('active_balance') ?? 0);

            $p1 = $phase1BySales->get($salesId) ?? [
                'action' => 'skip',
                'status' => 'Bukan target bulan ini',
            ];
            $p2 = $phase2BySales->get($salesId, ['lines' => [], 'total_credit' => 0.0]);

            $kreditP1 = $p1['action'] === 'create' ? (float) $p1['claimed_fee'] : 0.0;
            $kreditP2 = (float) $p2['total_credit'];
            $kreditTotal = $kreditP1 + $kreditP2;

            $this->output->newLine();
            $role = $this->describeSalesRole($salesId);
            $this->info("Sales: {$name} (id={$salesId}) — {$role['label']}");
            $this->line('Saldo aktif sekarang: ' . $this->formatMoney($saldoNow));
            $this->line('Simulasi saldo setelah run: ' . $this->formatMoney($saldoNow + $kreditTotal)
                . ' (+' . $this->formatMoney($kreditTotal) . ')');

            $phase1Table = [[
                '1 — Master ' . $this->period,
                $p1['jabatan_code'] ?? $role['code'],
                $p1['status'] ?? '—',
                (string) ($p1['rekap_count'] ?? '—'),
                (string) ($p1['rekap_lunas_count'] ?? '—'),
                $p1['action'] === 'create' ? $this->formatMoney($p1['achieved_amount']) : '—',
                $p1['action'] === 'create' ? number_format((float) $p1['basis'] * 100, 4) . '%' : '—',
                $p1['action'] === 'create' ? $this->formatMoney($p1['estimated_fee']) : '—',
                $p1['action'] === 'create' ? $this->formatMoney($p1['claimed_fee']) : '—',
            ]];

            $this->table(
                ['Phase', 'Jabatan', 'Status', 'Rekap', 'Lunas', 'Omzet', 'Basis', 'Est. fee', 'Kredit'],
                $phase1Table
            );

            if (!empty($p2['lines'])) {
                $phase2Rows = array_map(fn ($line) => [
                    '2 — Pelunasan',
                    $line['master_period'],
                    (string) $line['rekap_count'],
                    $this->formatMoney($line['paid_achieved']),
                    $this->formatMoney($line['claimed_fee']),
                ], $p2['lines']);

                $this->table(
                    ['Phase', 'Master', 'Rekap lunas baru', 'Omzet lunas', 'Kredit'],
                    $phase2Rows
                );
            } else {
                $this->line('Phase 2: tidak ada rekap yang baru lunas.');
            }

            $summaryRows[] = [
                $salesId,
                $name,
                $role['code'],
                $p1['action'] === 'create' ? 'Ya' : 'Tidak',
                (string) count($p2['lines']),
                $this->formatMoney($kreditP1),
                $this->formatMoney($kreditP2),
                $this->formatMoney($kreditTotal),
            ];
        }

        $this->output->newLine();
        $this->info('Ringkasan semua sales');
        $this->table(
            ['ID', 'Nama', 'Jabatan', 'Master baru', 'Batch P2', 'Kredit P1', 'Kredit P2', 'Total kredit'],
            $summaryRows
        );
    }

  /** @return Collection<int, array> */
    private function collectDryRunPhase1Plans(): Collection
    {
        $targets = MasterTargetSales::with('sales:id,nama_lengkap,id_jabatan')
            ->where(['tahun' => $this->year, 'is_active' => true])
            ->whereNotNull($this->monthStr)
            ->get();

        $plans = collect();

        foreach ($targets as $targetSales) {
            $salesId = (int) $targetSales->karyawan_id;
            if ($this->filterSalesId !== null && $salesId !== $this->filterSalesId) {
                continue;
            }

            $plan = $this->buildPhase1Plan($targetSales, $salesId);
            $plans->put($salesId, $plan);
        }

        return $plans;
    }

  /** @return Collection<int, array{lines: array<int, array>, total_credit: float}> */
    private function collectDryRunPhase2Plans(): Collection
    {
        $targetSalesIds = MasterTargetSales::query()
            ->where(['tahun' => $this->year, 'is_active' => true])
            ->whereNotNull($this->monthStr)
            ->pluck('karyawan_id');

        $openSalesIds = DB::table('rekap_fee_sales as r')
            ->join('master_fee_sales as m', 'm.id', '=', 'r.fee_sales_id')
            ->where('m.is_active', true)
            ->where('r.is_lunas', 0)
            ->distinct()
            ->pluck('m.sales_id');

        $salesIds = $targetSalesIds->merge($openSalesIds)->unique()->values();

        $out = collect();

        foreach ($salesIds as $salesId) {
            $salesId = (int) $salesId;
            if ($this->filterSalesId !== null && $salesId !== $this->filterSalesId) {
                continue;
            }

            $batches = $this->collectLunasCreditBatches($salesId);
            $lines = [];
            $totalCredit = 0.0;

            foreach ($batches as $masterId => $batch) {
                $period = MasterFeeSales::where('id', $masterId)->value('period') ?? (string) $masterId;
                $lines[] = [
                    'master_period' => $period,
                    'rekap_count' => count($batch['recap_ids']),
                    'paid_achieved' => $batch['paid_achieved'],
                    'claimed_fee' => $batch['claimed_fee'],
                ];
                $totalCredit += $batch['claimed_fee'];
            }

            if ($lines !== [] || $targetSalesIds->contains($salesId)) {
                $out->put($salesId, ['lines' => $lines, 'total_credit' => $totalCredit]);
            }
        }

        return $out;
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }
}
