<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MasterPelanggan;
use Carbon\Carbon;
use DB;

class BillingComand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Billing Live';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        printf("\n[BillingComand] [%s] Start Running...", date('Y-m-d H:i:s'));

        $data = MasterPelanggan::with('invoices')
        ->selectRaw('id_pelanggan, nama_pelanggan, sales_penanggung_jawab, sales_id')
        ->where('is_active', 1)
        ->whereHas('invoices')
        ->get()
        ->map(function($q) {
            $tagihan = $q->invoices->sum('nilai_tagihan');
            $terbayar = $q->invoices->reduce(function($carry, $b) {
                $pembayaran = $b->recordPembayaran->sum('nilai_pembayaran') ?? 0;
                $withdraw = $b->recordWithdraw->sum('nilai_pembayaran') ?? 0;
                return $carry + $pembayaran + $withdraw;
            }, 0);

            $status = abs($tagihan - $terbayar) <= 10 ? 1 : 0;
            $invoices = $q->invoices->map(function($b) {
                $tgl_sampling = null;
                
                return [
                    "id_pelanggan" => $b->pelanggan_id,
                    "no_quotation" => $b->no_quotation,
                    "no_order" => $b->no_order,
                    "no_invoice" => $b->no_invoice,
                    "periode" => $b->periode,
                    "tgl_sampling" => $tgl_sampling,
                    "tgl_invoice" => $b->tgl_invoice,
                    "tgl_jatuh_tempo" => $b->tgl_jatuh_tempo,
                    "nilai_tagihan" => $b->nilai_tagihan,
                    "terbayar" => $b->recordPembayaran->sum('nilai_pembayaran') ?? 0 + $b->recordWithdraw->sum('nilai_pembayaran') ?? 0,
                    "pph" => $b->recordWithdraw->where('keterangan_pelunasan', 'PPH')->sum('nilai_pembayaran') ?? 0,
                    "is_complete" => abs($b->nilai_tagihan - ($b->recordPembayaran->sum('nilai_pembayaran') ?? 0 + $b->recordWithdraw->sum('nilai_pembayaran') ?? 0)) <= 10 ? 1 : 0,
                ];
            })->values()->toArray();

            $totalPph = collect($invoices)->sum(function($invoice) {
                return $invoice['pph'] ?? 0;
            });

            return [
                'id_pelanggan' => $q->id_pelanggan,
                'nama_pelanggan' => $q->nama_pelanggan,
                'sales_penanggung_jawab' => $q->sales_penanggung_jawab,
                'sales_id' => $q->sales_id,
                'jumlah_invoice' => $q->invoices->count(),
                'nilai_tagihan' => $tagihan,
                'terbayar' => $terbayar,
                'total_pph' => $totalPph,
                'is_complete' => $status,
                'invoices' => $invoices,
            ];
        })->values()->toArray();

        printf("\n[BillingComand] [%s] Complete Calculate Data", date('Y-m-d H:i:s'));

        printf("\n[BillingComand] [%s] Start Insert or Update", date('Y-m-d H:i:s'));
        $this->sync($data);
        printf("\n[BillingComand] [%s] Complete Insert or Update", date('Y-m-d H:i:s'));

        printf("\n[BillingComand] [%s] Start Query Fixing", date('Y-m-d H:i:s'));

        DB::table('billing_list_detail')
        ->whereIn('periode', ['null', ''])
        ->update(['periode' => null]);

        printf("\n[BillingComand] [%s] Complete Query Fixing", date('Y-m-d H:i:s'));

        printf("\n[BillingComand] [%s] Start Query Update ", date('Y-m-d H:i:s'));
        DB::statement("
            UPDATE billing_list_detail b
            JOIN (
                SELECT 
                    od.no_order,
                    od.periode,
                    MIN(od.tanggal_sampling) AS min_sampling
                FROM order_detail od
                WHERE od.tanggal_sampling IS NOT NULL
                AND od.is_active = 1
                AND od.periode IS NOT NULL
                GROUP BY od.no_order, od.periode
            ) x 
            ON x.no_order COLLATE utf8mb4_general_ci = b.no_order
            AND x.periode  COLLATE utf8mb4_general_ci = b.periode
            SET b.tgl_sampling = x.min_sampling
            WHERE b.tgl_sampling IS NULL
            AND b.periode IS NOT NULL AND b.periode <> 'all';
        ");

        DB::statement("
            UPDATE billing_list_detail b
            JOIN (
                SELECT 
                    od.no_order,
                    MIN(od.tanggal_sampling) AS min_sampling
                FROM order_detail od
                WHERE od.tanggal_sampling IS NOT NULL
                AND od.is_active = 1
                GROUP BY od.no_order
            ) x 
            ON x.no_order COLLATE utf8mb4_general_ci = b.no_order
            SET b.tgl_sampling = x.min_sampling
            WHERE b.tgl_sampling IS NULL
            AND b.periode IS NULL;
        ");

        DB::statement("
            UPDATE billing_list_detail b
            JOIN (
                SELECT 
                    od.no_order,
                    MIN(od.tanggal_sampling) AS min_sampling
                FROM order_detail od
                WHERE od.tanggal_sampling IS NOT NULL
                AND od.is_active = 1
                GROUP BY od.no_order
            ) x 
            ON x.no_order COLLATE utf8mb4_general_ci = b.no_order
            SET b.tgl_sampling = x.min_sampling
            WHERE b.tgl_sampling IS NULL
            AND b.periode = 'all';
        ");
        printf("\n[BillingComand] [%s] Complete Query Update ", date('Y-m-d H:i:s'));
    }

    private function sync(array $data)
    {
        $chunkSize = 500;

        collect($data)->chunk($chunkSize)->each(function ($chunk) {

            $now = Carbon::now();
            $headers = [];
            $details = [];

            foreach ($chunk as $row) {

                // =========================
                // HEADER
                // =========================
                $headers[] = [
                    'id_pelanggan' => $row['id_pelanggan'],
                    'nama_pelanggan' => $row['nama_pelanggan'],
                    'sales_penanggung_jawab' => $row['sales_penanggung_jawab'],
                    'sales_id' => $row['sales_id'],
                    'jumlah_invoice' => $row['jumlah_invoice'],
                    'nilai_tagihan' => $row['nilai_tagihan'],
                    'terbayar' => $row['terbayar'],
                    'total_pph' => $row['total_pph'],
                    'is_complete' => $row['is_complete'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // =========================
                // DETAIL
                // =========================
                foreach ($row['invoices'] as $inv) {
                    $details[] = [
                        'id_pelanggan' => $inv['id_pelanggan'],
                        'no_invoice' => $inv['no_invoice'],
                        'no_order' => $inv['no_order'],
                        'no_quotation' => $inv['no_quotation'],
                        'periode' => $inv['periode'],
                        'tgl_sampling' => $inv['tgl_sampling'],
                        'tgl_invoice' => $inv['tgl_invoice'],
                        'tgl_jatuh_tempo' => $inv['tgl_jatuh_tempo'],
                        'nilai_tagihan' => $inv['nilai_tagihan'],
                        'terbayar' => $inv['terbayar'],
                        'pph' => $inv['pph'],
                        'is_complete' => $inv['is_complete'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::transaction(function () use ($headers, $details, $now) {

                // =========================
                // UPSERT HEADER
                // =========================
                $this->bulkUpsertHeader($headers);

                // mapping id_pelanggan → header_id
                $headerMap = DB::table('billing_list_header')
                    ->whereIn('id_pelanggan', array_column($headers, 'id_pelanggan'))
                    ->pluck('id', 'id_pelanggan');

                // inject billing_header_id
                foreach ($details as &$d) {
                    $d['billing_header_id'] = $headerMap[$d['id_pelanggan']];
                }

                // =========================
                // UPSERT DETAIL
                // =========================
                $this->bulkUpsertDetail($details);
            });
        });
    }

    private function bulkUpsertHeader(array $rows, $chunkSize = 500)
    {
        foreach (array_chunk($rows, $chunkSize) as $chunk) {

            $sql = "
                INSERT INTO billing_list_header
                (id_pelanggan, nama_pelanggan, sales_penanggung_jawab, sales_id,
                jumlah_invoice, nilai_tagihan, terbayar, total_pph, is_complete,
                created_at, updated_at)
                VALUES
            ";

            $values = [];
            $bindings = [];

            foreach ($chunk as $r) {
                $values[] = "(?,?,?,?,?,?,?,?,?,?,?)";
                $bindings[] = $r['id_pelanggan'];
                $bindings[] = $r['nama_pelanggan'];
                $bindings[] = $r['sales_penanggung_jawab'];
                $bindings[] = $r['sales_id'];
                $bindings[] = $r['jumlah_invoice'];
                $bindings[] = $r['nilai_tagihan'];
                $bindings[] = $r['terbayar'];
                $bindings[] = $r['total_pph'];
                $bindings[] = $r['is_complete'];
                $bindings[] = $r['created_at'];
                $bindings[] = $r['updated_at'];
            }

            $sql .= implode(',', $values) . "
                AS new
                ON DUPLICATE KEY UPDATE
                    nama_pelanggan = new.nama_pelanggan,
                    sales_penanggung_jawab = new.sales_penanggung_jawab,
                    sales_id = new.sales_id,
                    jumlah_invoice = new.jumlah_invoice,
                    nilai_tagihan = new.nilai_tagihan,
                    terbayar = new.terbayar,
                    total_pph = new.total_pph,
                    is_complete = new.is_complete,
                    updated_at = new.updated_at
            ";

            DB::statement($sql, $bindings);
        }
    }

    private function bulkUpsertDetail(array $rows, $chunkSize = 400)
    {
        foreach (array_chunk($rows, $chunkSize) as $chunk) {

            $sql = "
                INSERT INTO billing_list_detail
                (billing_header_id, id_pelanggan, no_invoice, no_order,
                no_quotation, periode, tgl_sampling, tgl_invoice,
                tgl_jatuh_tempo, nilai_tagihan, terbayar, pph, is_complete,
                created_at, updated_at)
                VALUES
            ";

            $values = [];
            $bindings = [];

            foreach ($chunk as $r) {
                $values[] = "(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $bindings[] = $r['billing_header_id'];
                $bindings[] = $r['id_pelanggan'];
                $bindings[] = $r['no_invoice'];
                $bindings[] = $r['no_order'];
                $bindings[] = $r['no_quotation'];
                $bindings[] = $r['periode'];
                $bindings[] = $r['tgl_sampling'];
                $bindings[] = $r['tgl_invoice'];
                $bindings[] = $r['tgl_jatuh_tempo'];
                $bindings[] = $r['nilai_tagihan'];
                $bindings[] = $r['terbayar'];
                $bindings[] = $r['pph'];
                $bindings[] = $r['is_complete'];
                $bindings[] = $r['created_at'];
                $bindings[] = $r['updated_at'];
            }

            $sql .= implode(',', $values) . "
                AS new
                ON DUPLICATE KEY UPDATE
                    billing_header_id = new.billing_header_id,
                    id_pelanggan = new.id_pelanggan,
                    no_order = new.no_order,
                    no_quotation = new.no_quotation,
                    periode = new.periode,
                    tgl_sampling = new.tgl_sampling,
                    tgl_invoice = new.tgl_invoice,
                    tgl_jatuh_tempo = new.tgl_jatuh_tempo,
                    nilai_tagihan = new.nilai_tagihan,
                    terbayar = new.terbayar,
                    pph = new.pph,
                    is_complete = new.is_complete,
                    updated_at = new.updated_at
            ";

            DB::statement($sql, $bindings);
        }
    }
}