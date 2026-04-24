<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\RecordPembayaranInvoice;
use App\Models\Withdraw;
use Carbon\Carbon;

class SummaryInvoice extends Command
{
    protected $signature = 'summary:invoice';
    protected $description = 'Summary invoice';

    public function handle()
    {
        printf("\n[SummaryInvoice] [%s] Start", Carbon::now());

        try {

            $withdrawSub = DB::table('withdraw')
                ->select('no_invoice', DB::raw('SUM(nilai_pembayaran) as total_pembayaran'))
                ->groupBy('no_invoice');

            $data = Invoice::query()
                ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
                ->leftJoinSub(
                    $withdrawSub,
                    'w',
                    fn($join) =>
                    $join->on('invoice.no_invoice', '=', 'w.no_invoice')
                )
                ->leftJoin('master_karyawan', 'order_header.sales_id', '=', 'master_karyawan.id')
                ->where([
                    ['invoice.is_active', true],
                    ['invoice.is_emailed', true],
                    ['invoice.is_whitelist', false],
                    ['order_header.is_active', true],
                ])
                ->select([
                    DB::raw('MAX(invoice.id) AS id'),
                    'invoice.no_invoice',

                    DB::raw('MAX(invoice.created_by) AS created_by'),
                    DB::raw('MAX(invoice.faktur_pajak) AS faktur_pajak'),

                    DB::raw('FLOOR(SUM(invoice.nilai_tagihan)) AS total_tagihan'),
                    DB::raw('SUM(invoice.nilai_tagihan) AS nilai_tagihan'),

                    DB::raw('MAX(invoice.rekening) AS rekening'),
                    DB::raw('MAX(invoice.keterangan) AS keterangan'),

                    DB::raw('MAX(invoice.nama_pj) AS nama_pj'),
                    DB::raw('MAX(invoice.jabatan_pj) AS jabatan_pj'),

                    DB::raw('MAX(invoice.tgl_invoice) AS tgl_invoice'),
                    DB::raw('MAX(invoice.no_faktur) AS no_faktur'),

                    DB::raw('MAX(invoice.alamat_penagihan) AS alamat_penagihan'),
                    DB::raw('MAX(invoice.nama_pic) AS nama_pic'),
                    DB::raw('MAX(invoice.no_pic) AS no_pic'),
                    DB::raw('MAX(invoice.email_pic) AS email_pic'),
                    DB::raw('MAX(invoice.jabatan_pic) AS jabatan_pic'),

                    DB::raw('MAX(invoice.no_po) AS no_po'),
                    DB::raw('MAX(invoice.no_spk) AS no_spk'),

                    DB::raw('MAX(invoice.tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
                    DB::raw('MAX(invoice.filename) AS filename'),
                    DB::raw('MAX(invoice.upload_file) AS upload_file'),
                    DB::raw('MAX(invoice.file_pph) AS file_pph'),

                    DB::raw('MAX(order_header.konsultan) AS consultant'),
                    DB::raw('MAX(order_header.no_document) AS document'),
                    DB::raw('MAX(order_header.sales_id) AS sales_id'),
                    DB::raw('MAX(master_karyawan.nama_lengkap) AS sales_penanggung_jawab'),

                    DB::raw('MAX(invoice.created_at) AS created_at'),
                    DB::raw('MAX(invoice.emailed_at) AS emailed_at'),
                    DB::raw('MAX(invoice.emailed_by) AS emailed_by'),

                    DB::raw('MAX(invoice.tgl_pelunasan) AS tgl_pelunasan'),
                    DB::raw('(SUM(invoice.nilai_pelunasan) + COALESCE(MAX(w.total_pembayaran), 0)) AS nilai_pelunasan'),

                    DB::raw('MAX(invoice.is_generate) AS is_generate'),
                    DB::raw('MAX(invoice.generated_by) AS generated_by'),
                    DB::raw('MAX(invoice.generated_at) AS generated_at'),
                    DB::raw('MAX(invoice.expired) AS expired'),

                    DB::raw('MAX(invoice.pelanggan_id) AS pelanggan_id'),
                    DB::raw('MAX(invoice.detail_pendukung) AS detail_pendukung'),

                    DB::raw('COALESCE(MAX(order_header.nama_perusahaan), MAX(order_header.konsultan)) AS nama_customer'),
                    DB::raw('MAX(order_header.is_revisi) AS is_revisi'),
                    DB::raw('GROUP_CONCAT(DISTINCT invoice.no_order) AS no_orders'),

                    DB::raw("
                        CASE
                            WHEN SUM(invoice.nilai_tagihan) = 0 THEN 'Belum Ada Pembayaran'
                            WHEN (
                                SUM(invoice.nilai_tagihan) -
                                (COALESCE(SUM(invoice.nilai_pelunasan), 0) + COALESCE(MAX(w.total_pembayaran), 0))
                            ) < 0 THEN 'Kelebihan Pembayaran'
                            WHEN (
                                SUM(invoice.nilai_tagihan) -
                                (COALESCE(SUM(invoice.nilai_pelunasan), 0) + COALESCE(MAX(w.total_pembayaran), 0))
                            ) > 0 THEN 'Belum Lunas'
                            ELSE 'Lunas'
                        END AS status_lunas
                    ")
                ])
                ->groupBy('invoice.no_invoice')
                ->get();

            $invoiceNumbers = $data->pluck('no_invoice');

            $records = RecordPembayaranInvoice::with('sales_in_detail.header')
                ->whereIn('no_invoice', $invoiceNumbers)
                ->where('is_active', true)
                ->orderByDesc('id')
                ->get()
                ->groupBy('no_invoice');

            $withdraws = Withdraw::with('sales_in_detail.header')
                ->whereIn('no_invoice', $invoiceNumbers)
                ->orderByDesc('id')
                ->get()
                ->groupBy('no_invoice');

            $data = $data->map(function ($row) use ($records, $withdraws) {

                $record = collect($records[$row->no_invoice] ?? [])
                    ->map(function ($item) {
                        $item = is_array($item) ? $item : (array) $item;

                        return [
                            'batch_id' => $item['sales_in_detail']['header']['no_dokumen'] ?? 'data lama',
                            'type' => 'record',
                            'nilai_pembayaran' => $item['nilai_pembayaran'] ?? null,
                            'nilai_pengurangan' => null,
                            'jenis_pengurangan' => null,
                            'tanggal_pembayaran' => $item['tgl_pembayaran'] ?? null,
                            'keterangan' => $item['keterangan'] ?? null,
                            'created_by' => $item['created_by'] ?? null,
                            'created_at' => $item['created_at'] ?? null,
                        ];
                    });

                $withdraw = collect($withdraws[$row->no_invoice] ?? [])
                    ->map(function ($item) {
                        $item = is_array($item) ? $item : (array) $item;

                        return [
                            'batch_id' => $item['sales_in_detail']['header']['no_dokumen'] ?? 'data lama',
                            'type' => 'withdraw',
                            'nilai_pembayaran' => null,
                            'nilai_pengurangan' => $item['nilai_pembayaran'] ?? null,
                            'jenis_pengurangan' => $item['keterangan_pelunasan'] ?? null,
                            'tanggal_pembayaran' => $item['created_at'] ?? null,
                            'keterangan' => $item['keterangan_tambahan'] ?? null,
                            'created_by' => $item['created_by'] ?? null,
                            'created_at' => $item['created_at'] ?? null,
                        ];
                    });

                return [
                    "no_invoice" => $row->no_invoice,
                    "created_by" => $row->created_by,
                    "faktur_pajak" => $row->faktur_pajak,
                    "total_tagihan" => $row->total_tagihan,
                    "nilai_tagihan" => $row->nilai_tagihan,
                    "rekening" => $row->rekening,
                    "keterangan" => $row->keterangan,
                    "nama_pj" => $row->nama_pj,
                    "jabatan_pj" => $row->jabatan_pj,
                    "tgl_invoice" => $row->tgl_invoice,
                    "no_faktur" => $row->no_faktur,
                    "alamat_penagihan" => $row->alamat_penagihan,
                    "nama_pic" => $row->nama_pic,
                    "no_pic" => $row->no_pic,
                    "email_pic" => $row->email_pic,
                    "jabatan_pic" => $row->jabatan_pic,
                    "no_po" => $row->no_po,
                    "no_spk" => $row->no_spk,
                    "tgl_jatuh_tempo" => $row->tgl_jatuh_tempo,
                    "filename" => $row->filename,
                    "upload_file" => $row->upload_file,
                    "file_pph" => $row->file_pph,
                    "consultant" => $row->consultant,
                    "document" => $row->document,
                    "sales_id" => $row->sales_id,
                    "sales_penanggung_jawab" => $row->sales_penanggung_jawab,
                    "created_at" => $row->created_at,
                    "emailed_at" => $row->emailed_at,
                    "emailed_by" => $row->emailed_by,
                    "tgl_pelunasan" => $row->tgl_pelunasan,
                    "nilai_pelunasan" => $row->nilai_pelunasan,
                    "is_generate" => $row->is_generate,
                    "generated_by" => $row->generated_by,
                    "generated_at" => $row->generated_at,
                    "expired" => $row->expired,
                    "pelanggan_id" => $row->pelanggan_id,
                    "detail_pendukung" => $row->detail_pendukung,
                    "nama_customer" => $row->nama_customer,
                    "is_revisi" => $row->is_revisi,
                    "no_orders" => $row->no_orders,
                    "status_lunas" => $row->status_lunas,
                    "history" => json_encode(
                        $record->merge($withdraw)->values()->toArray()
                    ),
                    "updated_at" => Carbon::now(),
                ];
            })->values()->toArray();

            printf("\n[SummaryInvoice] [%s] Complete Data: %d", Carbon::now(), count($data));

            $newInvoiceNumbers = array_column($data, 'no_invoice');

            collect($data)
                ->chunk(50)
                ->each(function ($chunk, $index) {

                    printf("\n[SummaryInvoice] [%s] Upsert chunk ke-%d size:%d", Carbon::now(), $index + 1, count($chunk));

                    DB::table('summary_invoice')->upsert(
                        $chunk->toArray(),
                        ['no_invoice'],
                        [
                            "created_by",
                            "faktur_pajak",
                            "total_tagihan",
                            "nilai_tagihan",
                            "rekening",
                            "keterangan",
                            "nama_pj",
                            "jabatan_pj",
                            "tgl_invoice",
                            "no_faktur",
                            "alamat_penagihan",
                            "nama_pic",
                            "no_pic",
                            "email_pic",
                            "jabatan_pic",
                            "no_po",
                            "no_spk",
                            "tgl_jatuh_tempo",
                            "filename",
                            "upload_file",
                            "file_pph",
                            "consultant",
                            "document",
                            "sales_id",
                            "sales_penanggung_jawab",
                            "created_at",
                            "emailed_at",
                            "emailed_by",
                            "tgl_pelunasan",
                            "nilai_pelunasan",
                            "is_generate",
                            "generated_by",
                            "generated_at",
                            "expired",
                            "pelanggan_id",
                            "detail_pendukung",
                            "nama_customer",
                            "is_revisi",
                            "no_orders",
                            "status_lunas",
                            "history",
                            "updated_at"
                        ]
                    );
                });

            printf("\n[SummaryInvoice] [%s] Upsert selesai", Carbon::now());

            // 🔥 DELETE (tetap pakai sekali, tapi aman karena data sudah masuk bertahap)
            $newInvoiceNumbers = array_column($data, 'no_invoice');

            DB::table('summary_invoice')
                ->whereNotIn('no_invoice', $newInvoiceNumbers)
                ->delete();

            DB::commit();

            printf("\n[SummaryInvoice] [%s] DONE total: %d", Carbon::now(), count($data));
        } catch (\Throwable $th) {
            DB::rollBack();
            dd($th->getMessage(), $th->getLine(), $th->getFile());
        }
    }
}
