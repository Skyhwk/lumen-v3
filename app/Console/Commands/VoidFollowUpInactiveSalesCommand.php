<?php

namespace App\Console\Commands;

use App\Models\AlasanVoidQt;
use App\Models\Invoice;
use App\Models\Jadwal;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\SamplingPlan;
use App\Services\QuotationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VoidFollowUpInactiveSalesCommand extends Command
{
    protected $signature = 'quotation:void-follow-up-inactive-sales
        {--year=2026 : Tahun tanggal_penawaran. Pakai all untuk semua tahun}
        {--cabang= : Filter id_cabang (kosong = semua)}
        {--mode=all : all|kontrak|non_kontrak}
        {--alasan=SALES NON AKTIF : Alasan void}
        {--dry-run : Simulasi tanpa mengubah database}
        {--force : Lewati konfirmasi}
        {--limit= : Batasi jumlah QT yang diproses}';

    protected $description = 'Void QT Follow Up milik sales nonaktif yang belum pernah order (cek data_lama.no_order). Invoice unpaid, jadwal, dan sampling plan ikut di-nonaktifkan.';

    public function handle(QuotationService $quotationService)
    {
        $dryRun = (bool) $this->option('dry-run');
        $yearOption = (string) $this->option('year');
        $year = strtolower($yearOption) === 'all' ? null : (int) $yearOption;
        $cabang = $this->option('cabang');
        $cabang = ($cabang === null || $cabang === '') ? null : (int) $cabang;
        $mode = strtolower((string) $this->option('mode'));
        $alasan = trim((string) $this->option('alasan')) ?: 'SALES NON AKTIF';
        $limit = $this->option('limit');
        $limit = ($limit === null || $limit === '') ? null : max(1, (int) $limit);

        if (!in_array($mode, ['all', 'kontrak', 'non_kontrak'], true)) {
            $this->error('Mode harus all, kontrak, atau non_kontrak.');

            return 1;
        }

        $this->info('Mode: ' . ($dryRun ? 'DRY-RUN' : 'EXECUTE'));
        $this->info('Tahun: ' . ($year ?: 'all'));
        $this->info('Cabang: ' . ($cabang ?: 'all'));
        $this->info('Tipe: ' . $mode);
        $this->info("Alasan void: {$alasan}");

        $sources = $this->sources($mode);
        $followUpInactive = 0;
        $hasOldOrder = 0;
        $hasOrderHeader = 0;

        foreach ($sources as $source) {
            $this->line("Hitung {$source['tipe']}...");
            $base = $this->followUpInactiveQuery($source['model'], $source['tipe'], $year, $cabang);
            $followUpInactive += (clone $base)->count();
            $hasOldOrder += (clone $base)->where($this->hasOldOrderConstraint($source['table']))->count();
            $hasOrderHeader += (clone $base)->whereExists($this->orderHeaderExists($source['table']))->count();
        }

        $this->line('Mengumpulkan kandidat eligible...');
        $rows = $this->collectEligible($sources, $year, $cabang);
        $this->line('Kandidat sebelum cek invoice: ' . $rows->count());
        $this->line('Cek invoice lunas...');

        $paidNos = $this->paidInvoiceNumbers($rows->pluck('no_document'));
        $eligible = collect();
        $paidInvoice = collect();

        foreach ($rows as $row) {
            if (isset($paidNos[$row['no_document']])) {
                $paidInvoice->push($row);
                continue;
            }

            $eligible->push($row);
        }

        $this->attachRelatedCounts($eligible);

        $this->info("Follow Up + sales nonaktif: {$followUpInactive}");
        $this->info("Skip QS Ulang / data_lama.no_order ada: {$hasOldOrder}");
        $this->info("Skip sudah ada order_header: {$hasOrderHeader}");
        $this->info('Skip invoice sudah ada pembayaran: ' . $paidInvoice->count());
        $this->info('Eligible (belum pernah order, invoice belum lunas): ' . $eligible->count());
        $this->info('  Non kontrak: ' . $eligible->where('tipe', 'non_kontrak')->count());
        $this->info('  Kontrak: ' . $eligible->where('tipe', 'kontrak')->count());

        $bySales = $eligible->groupBy('sales_name')->map->count()->sortDesc();
        $this->table(
            ['Sales nonaktif', 'Qty QT'],
            $bySales->map(fn ($count, $name) => [$name, $count])->values()->all()
        );

        if ($limit !== null) {
            $eligible = $eligible->take($limit)->values();
            $this->info("Limit: {$limit} → diproses {$eligible->count()}");
        }

        if ($eligible->isEmpty()) {
            $this->info('Tidak ada QT yang perlu di-void.');

            return 0;
        }

        $this->table(
            ['Tipe', 'No QT', 'Sales', 'ID Pelanggan', 'Perusahaan', 'Flag', 'SP', 'Jadwal', 'Invoice aktif'],
            $eligible->take(30)->map(function ($row) {
                return [
                    $row['tipe'],
                    $row['no_document'],
                    $row['sales_name'],
                    $row['pelanggan_id'],
                    $row['nama_perusahaan'],
                    $row['flag_status'],
                    $row['sampling_plan_count'],
                    $row['jadwal_count'],
                    $row['invoice_count'],
                ];
            })->toArray()
        );

        if ($eligible->count() > 30) {
            $this->line('... dan ' . ($eligible->count() - 30) . ' lainnya.');
        }

        if ($dryRun) {
            $this->warn('Dry-run: tidak ada data yang diubah.');

            return 0;
        }

        if (!$this->option('force') && !$this->confirm("Void {$eligible->count()} quotation Follow Up?")) {
            $this->warn('Dibatalkan.');

            return 0;
        }

        $now = Carbon::now();
        $voided = 0;
        $invoiceVoided = 0;
        $spVoided = 0;
        $jadwalVoided = 0;
        $skipped = 0;

        foreach ($eligible->chunk(50) as $chunk) {
            foreach ($chunk as $row) {
                $result = $this->voidOne($row, $quotationService, $alasan, $now);
                if (!$result['ok']) {
                    $this->warn($result['message']);
                    $skipped++;
                    continue;
                }

                $voided++;
                $invoiceVoided += $result['invoice'];
                $spVoided += $result['sampling_plan'];
                $jadwalVoided += $result['jadwal'];
            }

            $this->line("Progress void QT: {$voided}");
        }

        $this->info("Selesai. QT void: {$voided}. Invoice nonaktif: {$invoiceVoided}. SP: {$spVoided}. Jadwal: {$jadwalVoided}. Skip: {$skipped}.");

        return 0;
    }

    private function sources($mode)
    {
        $all = [
            ['tipe' => 'non_kontrak', 'model' => QuotationNonKontrak::class, 'table' => 'request_quotation'],
            ['tipe' => 'kontrak', 'model' => QuotationKontrakH::class, 'table' => 'request_quotation_kontrak_H'],
        ];

        if ($mode === 'non_kontrak') {
            return [$all[0]];
        }

        if ($mode === 'kontrak') {
            return [$all[1]];
        }

        return $all;
    }

    private function collectEligible(array $sources, $year, $cabang)
    {
        $rows = collect();

        foreach ($sources as $source) {
            $table = $source['table'];
            $query = $this->followUpInactiveQuery($source['model'], $source['tipe'], $year, $cabang)
                ->where($this->neverOrderedConstraint($table))
                ->whereNotExists($this->orderHeaderExists($table))
                ->orderBy("{$table}.id")
                ->select([
                    "{$table}.id",
                    "{$table}.no_document",
                    "{$table}.pelanggan_ID as pelanggan_id",
                    "{$table}.nama_perusahaan",
                    "{$table}.flag_status",
                    "{$table}.sales_id",
                    'master_karyawan.nama_lengkap as sales_name',
                ]);

            foreach ($query->get() as $qt) {
                $rows->push([
                    'tipe' => $source['tipe'],
                    'id' => $qt->id,
                    'no_document' => $qt->no_document,
                    'pelanggan_id' => $qt->pelanggan_id,
                    'nama_perusahaan' => $qt->nama_perusahaan,
                    'flag_status' => $qt->flag_status,
                    'sales_id' => $qt->sales_id,
                    'sales_name' => $qt->sales_name ?: '-',
                    'sampling_plan_count' => 0,
                    'jadwal_count' => 0,
                    'invoice_count' => 0,
                ]);
            }
        }

        return $rows->values();
    }

    private function followUpInactiveQuery($model, $tipe, $year, $cabang)
    {
        $table = (new $model)->getTable();

        $query = $model::query()
            ->from($table)
            ->join('master_karyawan', 'master_karyawan.id', '=', "{$table}.sales_id")
            ->whereIn("{$table}.flag_status", ['emailed', 'sp'])
            ->where("{$table}.is_generate_data_lab", 1)
            ->where("{$table}.is_active", true)
            ->where("{$table}.is_approved", true)
            ->where("{$table}.is_emailed", true)
            ->where(function ($q) {
                $q->where('master_karyawan.is_active', false)
                    ->orWhere('master_karyawan.is_active', 0);
            });

        if ($tipe === 'non_kontrak') {
            $query->where("{$table}.status_sampling", '!=', 'SD');
        }

        if ($year) {
            $query->whereYear("{$table}.tanggal_penawaran", $year);
        }

        if ($cabang) {
            $query->where("{$table}.id_cabang", $cabang);
        }

        return $query;
    }

    private function neverOrderedConstraint($table)
    {
        return function ($q) use ($table) {
            $q->whereNull("{$table}.data_lama")
                ->orWhere("{$table}.data_lama", '')
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.data_lama, '$.no_order')) IS NULL")
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.data_lama, '$.no_order')) IN ('', 'null')");
        };
    }

    private function hasOldOrderConstraint($table)
    {
        return function ($q) use ($table) {
            $q->whereNotNull("{$table}.data_lama")
                ->where("{$table}.data_lama", '!=', '')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.data_lama, '$.no_order')) IS NOT NULL")
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$table}.data_lama, '$.no_order')) NOT IN ('', 'null')");
        };
    }

    private function orderHeaderExists($table)
    {
        return function ($q) use ($table) {
            $q->select(DB::raw(1))
                ->from('order_header')
                ->whereColumn('order_header.no_document', "{$table}.no_document");
        };
    }

    private function paidInvoiceNumbers($noDocuments)
    {
        $paid = collect();

        foreach ($noDocuments->filter()->unique()->values()->chunk(500) as $chunk) {
            $paid = $paid->merge(
                Invoice::where('nilai_pelunasan', '>', 0)
                    ->whereIn('no_quotation', $chunk->all())
                    ->pluck('no_quotation')
            );
        }

        return $paid->unique()->flip();
    }

    private function attachRelatedCounts($rows)
    {
        $noDocuments = $rows->pluck('no_document')->filter()->unique()->values();
        if ($noDocuments->isEmpty()) {
            return;
        }

        $invoiceCounts = collect();
        $spCounts = collect();
        $jadwalCounts = collect();

        foreach ($noDocuments->chunk(500) as $chunk) {
            $ids = $chunk->all();

            $invoiceCounts = $invoiceCounts->union(
                Invoice::whereIn('no_quotation', $ids)
                    ->where('is_active', true)
                    ->selectRaw('no_quotation, COUNT(*) as total')
                    ->groupBy('no_quotation')
                    ->pluck('total', 'no_quotation')
            );

            $spCounts = $spCounts->union(
                SamplingPlan::whereIn('no_quotation', $ids)
                    ->where('is_active', true)
                    ->selectRaw('no_quotation, COUNT(*) as total')
                    ->groupBy('no_quotation')
                    ->pluck('total', 'no_quotation')
            );

            $jadwalCounts = $jadwalCounts->union(
                Jadwal::whereIn('no_quotation', $ids)
                    ->where('is_active', true)
                    ->selectRaw('no_quotation, COUNT(*) as total')
                    ->groupBy('no_quotation')
                    ->pluck('total', 'no_quotation')
            );
        }

        foreach ($rows as $i => $row) {
            $row['invoice_count'] = (int) ($invoiceCounts[$row['no_document']] ?? 0);
            $row['sampling_plan_count'] = (int) ($spCounts[$row['no_document']] ?? 0);
            $row['jadwal_count'] = (int) ($jadwalCounts[$row['no_document']] ?? 0);
            $rows[$i] = $row;
        }
    }

    private function voidOne(array $row, QuotationService $quotationService, $alasan, Carbon $now)
    {
        return DB::transaction(function () use ($row, $quotationService, $alasan, $now) {
            $model = $row['tipe'] === 'kontrak' ? QuotationKontrakH::class : QuotationNonKontrak::class;
            $qt = $model::where('id', $row['id'])->where('is_active', true)->lockForUpdate()->first();

            if (!$qt) {
                return ['ok' => false, 'message' => "Skip {$row['no_document']}: sudah tidak aktif."];
            }

            if (!in_array($qt->flag_status, ['emailed', 'sp'], true)) {
                return ['ok' => false, 'message' => "Skip {$qt->no_document}: flag_status={$qt->flag_status}."];
            }

            if ($this->extractNoOrder($qt->data_lama)) {
                return ['ok' => false, 'message' => "Skip {$qt->no_document}: data_lama.no_order ada."];
            }

            if (OrderHeader::where('no_document', $qt->no_document)->exists()) {
                return ['ok' => false, 'message' => "Skip {$qt->no_document}: sudah ada order_header."];
            }

            $check = $quotationService->validateVoidQuotation($qt->no_document);
            if (empty($check['status'])) {
                return ['ok' => false, 'message' => $check['message']];
            }

            $baseQuotation = preg_replace('/R\d+$/', '', $qt->no_document);
            $invoiceCount = Invoice::where('no_quotation', 'like', '%' . $baseQuotation . '%')
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'keterangan_delete' => $alasan,
                    'deleted_by' => 'System',
                    'deleted_at' => $now,
                ]);

            $spCount = SamplingPlan::where('no_quotation', $qt->no_document)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $jadwalCount = Jadwal::where('no_quotation', $qt->no_document)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $qt->flag_status = 'void';
            $qt->is_active = false;
            $qt->document_status = 'Non Aktif';
            $qt->deleted_by = 'System';
            $qt->deleted_at = $now->format('Y-m-d H:i:s');
            $qt->save();

            $alasanVoid = new AlasanVoidQt();
            $alasanVoid->no_quotation = $qt->no_document;
            $alasanVoid->alasan = $alasan;
            $alasanVoid->keterangan = json_encode([
                ['keterangan' => 'Bulk void Follow Up QT sales nonaktif, belum pernah order (data_lama.no_order kosong).'],
            ]);
            $alasanVoid->voided_by = 'System';
            $alasanVoid->voided_at = $now->format('Y-m-d H:i:s');
            $alasanVoid->save();

            return [
                'ok' => true,
                'invoice' => $invoiceCount,
                'sampling_plan' => $spCount,
                'jadwal' => $jadwalCount,
            ];
        });
    }

    private function extractNoOrder($dataLama)
    {
        if ($dataLama === null || $dataLama === '') {
            return null;
        }

        $parsed = is_array($dataLama) || is_object($dataLama) ? (array) $dataLama : json_decode($dataLama, true);
        if (!is_array($parsed) || !array_key_exists('no_order', $parsed)) {
            return null;
        }

        $noOrder = $parsed['no_order'];
        if ($noOrder === null || $noOrder === '' || $noOrder === 'null') {
            return null;
        }

        return (string) $noOrder;
    }
}
