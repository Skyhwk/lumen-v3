<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MasterPelanggan;
use App\Models\OrderDetail;
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

        // 1️⃣ Order detail → map no_order => tgl_sampling
        $orderSamplingMap = OrderDetail::query()
        ->join('order_header as oh', 'oh.no_order', '=', 'order_detail.no_order')
        ->where('order_detail.is_active', 1)
        ->whereYear('order_detail.tanggal_sampling', '>=', 2024)
        ->selectRaw('
            order_detail.no_order,
            MIN(order_detail.tanggal_sampling) AS tgl_sampling,
            oh.sales_id
        ')
        ->groupBy('order_detail.no_order', 'oh.sales_id')
        ->get()
        ->keyBy('no_order')
        ->map(function ($row) {
            return [
                'tgl_sampling' => $row->tgl_sampling,
                'sales_id'     => $row->sales_id,
            ];
        })
        ->toArray();

        // 2️⃣ Ambil pelanggan + invoice + relasi
        $data = MasterPelanggan::query()
        ->with([
            'invoices.recordPembayaran',
            'invoices.recordWithdraw'
        ])
        ->select('id_pelanggan', 'nama_pelanggan', 'sales_penanggung_jawab', 'sales_id')
        ->where('is_active', 1)
        // ->where('id_pelanggan', 'KSDE01')
        ->whereHas('invoices')
        ->get()
        ->map(function ($pelanggan) use ($orderSamplingMap) {

            $tagihan = $pelanggan->invoices->sum('nilai_tagihan');

            $invoices = $pelanggan->invoices
                ->groupBy('no_invoice')
                ->map(function ($group, $noInvoice) use ($orderSamplingMap) {

                    $first = $group->first();

                    $nilaiTagihan = $group->sum('nilai_tagihan');

                    $pembayaran = $first->recordPembayaran->sum('nilai_pembayaran');
                    $withdraw   = $first->recordWithdraw->sum('nilai_pembayaran');
                    $terbayar   = $pembayaran + $withdraw;

                    $pph = $first->recordWithdraw->sum(function ($item) {
                        return $item->keterangan_pelunasan === 'PPH'
                            ? $item->nilai_pembayaran
                            : 0;
                    });

                    $periode = $group->pluck('periode')->filter()->unique()->values();
                    $noOrder = $group->pluck('no_order')->unique()->values();

                    $tglSampling = $noOrder
                    ->map(fn ($no) => $orderSamplingMap[$no]['tgl_sampling'] ?? '-')
                    ->filter()
                    ->values();

                    $sales_id = $noOrder
                    ->map(fn ($no) => $orderSamplingMap[$no]['sales_id'] ?? null)
                    ->filter()
                    ->first(); // biasanya 1 invoice = 1 sales

                    return [
                        'id_pelanggan'   => $first->pelanggan_id,
                        'no_quotation'   => $group->pluck('no_quotation')->unique()->implode(','),
                        'no_order'       => $noOrder->implode(','),
                        'no_invoice'     => $noInvoice,
                        'periode'        => $periode->isEmpty() ? null : $periode->implode(','),
                        'tgl_sampling'   => $tglSampling->isEmpty() ? null : $tglSampling->implode(','),
                        'tgl_invoice'    => $first->tgl_invoice,
                        'tgl_jatuh_tempo'=> $first->tgl_jatuh_tempo,
                        'nilai_tagihan'  => $nilaiTagihan,
                        'terbayar'       => $terbayar,
                        'pph'            => $pph,
                        'is_complete'    => abs($nilaiTagihan - $terbayar) <= 10 ? 1 : 0,
                        'sales_id'       => $sales_id
                    ];
                })
                ->values();

            $totalTerbayar = $invoices->sum('terbayar');
            $totalPph      = $invoices->sum('pph');

            return [
                'id_pelanggan'            => $pelanggan->id_pelanggan,
                'nama_pelanggan'          => $pelanggan->nama_pelanggan,
                'sales_penanggung_jawab'  => $pelanggan->sales_penanggung_jawab,
                'sales_id'                => $pelanggan->sales_id,
                'jumlah_invoice'          => $invoices->count(),
                'nilai_tagihan'           => $tagihan,
                'terbayar'                => $totalTerbayar,
                'total_pph'               => $totalPph,
                'is_complete'             => abs($tagihan - $totalTerbayar) <= 10 ? 1 : 0,
                'invoices'                => $invoices->toArray(),
            ];
        })
        ->values()
        ->toArray();

        printf("\n[BillingComand] [%s] Complete Calculate Data, total data : " . count($data), date('Y-m-d H:i:s'));

        printf("\n[BillingComand] [%s] Start Insert or Update", date('Y-m-d H:i:s'));
        $this->sync($data);
        printf("\n[BillingComand] [%s] Complete Insert or Update", date('Y-m-d H:i:s'));

        printf("\n[BillingComand] [%s] Start Query Fixing", date('Y-m-d H:i:s'));

        DB::table('billing_list_detail')
        ->whereIn('periode', ['null', ''])
        ->update(['periode' => null]);

        printf("\n[BillingComand] [%s] Complete Query Fixing", date('Y-m-d H:i:s'));
    }

    private function sync(array $data)
    {
        $chunkSize = 500;

        collect($data)->chunk($chunkSize)->each(function ($chunk) {

            $now = Carbon::now()->subHours(7);
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
                        'sales_id' => $inv['sales_id'],
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
                tgl_jatuh_tempo, nilai_tagihan, terbayar, pph, is_complete, sales_id,
                created_at, updated_at)
                VALUES
            ";

            $values = [];
            $bindings = [];

            foreach ($chunk as $r) {
                $values[] = "(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
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
                $bindings[] = $r['sales_id'];
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
                    sales_id = new.sales_id,
                    updated_at = new.updated_at
            ";

            DB::statement($sql, $bindings);
        }
    }
}