<?php

namespace App\Http\Controllers\api;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\OrderHeader;
use App\Models\MasterKaryawan;
use App\Models\GenerateLink;
use App\Models\Invoice;
use App\Models\RecordPembayaranInvoice;
use App\Services\GenerateToken;
use App\Http\Controllers\Controller;
use App\Models\Withdraw;
use App\Services\RenderInvoice;
use App\Services\SendEmail;
use App\Services\GetAtasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;


class InvoiceController extends Controller
{
    public static function generatePDF($noInvoice)
    {
        $render = new RenderInvoice();
        $render->renderInvoice($noInvoice);
        return true;
    }

    // public function index(Request $request)
    // {
    //     try {
    //         $withdrawSub = DB::table('withdraw')
    //             ->select('no_invoice', DB::raw('SUM(nilai_pembayaran) as total_pembayaran'))
    //             ->groupBy('no_invoice');

    //         $data = Invoice::select(
    //             DB::raw('MAX(invoice.id) AS id'),
    //             'invoice.no_invoice',
    //             DB::raw('MAX(invoice.created_by) AS created_by'),
    //             DB::raw('MAX(faktur_pajak) AS faktur_pajak'),
    //             DB::raw('floor(SUM(invoice.nilai_tagihan)) AS total_tagihan'),
    //             DB::raw('MAX(rekening) AS rekening'),
    //             DB::raw('MAX(keterangan) AS keterangan'),
    //             DB::raw('MAX(nama_pj) AS nama_pj'),
    //             DB::raw('MAX(jabatan_pj) AS jabatan_pj'),
    //             DB::raw('MAX(tgl_invoice) AS tgl_invoice'),
    //             DB::raw('MAX(no_faktur) AS no_faktur'),
    //             DB::raw('MAX(alamat_penagihan) AS alamat_penagihan'),
    //             DB::raw('MAX(nama_pic) AS nama_pic'),
    //             DB::raw('MAX(no_pic) AS no_pic'),
    //             DB::raw('MAX(email_pic) AS email_pic'),
    //             DB::raw('MAX(is_custom) AS is_custom'),
    //             DB::raw('MAX(jabatan_pic) AS jabatan_pic'),
    //             DB::raw('MAX(invoice.no_po) AS no_po'),
    //             DB::raw('MAX(no_spk) AS no_spk'),
    //             DB::raw('MAX(tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
    //             DB::raw('MAX(filename) AS filename'),
    //             DB::raw('MAX(order_header.konsultan) AS consultant'),
    //             DB::raw('MAX(order_header.no_document) AS document'),
    //             DB::raw('MAX(invoice.created_at) AS created_at'),
    //             DB::raw('MAX(invoice.emailed_at) AS emailed_at'),
    //             DB::raw('MAX(invoice.emailed_by) AS emailed_by'),
    //             DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
    //             DB::raw('(MAX(invoice.nilai_pelunasan) + COALESCE(MAX(w.total_pembayaran), 0)) AS nilai_pelunasan'),
    //             DB::raw('MAX(is_generate) AS is_generate'),
    //             DB::raw('MAX(generated_by) AS generated_by'),
    //             DB::raw('MAX(generated_at) AS generated_at'),
    //             DB::raw('MAX(expired) AS expired'),
    //             DB::raw('MAX(invoice.pelanggan_id) AS pelanggan_id'),
    //             DB::raw('MAX(invoice.detail_pendukung) AS detail_pendukung'),
    //             DB::raw('COALESCE(MAX(order_header.nama_perusahaan), MAX(order_header.konsultan)) AS nama_customer'),
    //             // DB::raw('COALESCE(MAX(order_header.konsultan), MAX(order_header.nama_perusahaan)) AS nama_customer'),
    //             DB::raw('SUM(invoice.nilai_tagihan) AS nilai_tagihan'),
    //             DB::raw('MAX(order_header.is_revisi) AS is_revisi'),
    //             DB::raw('GROUP_CONCAT(invoice.no_order) AS no_orders')
    //         )
    //             ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
    //             ->leftJoinSub($withdrawSub, 'w', function ($join) {
    //                 $join->on('invoice.no_invoice', '=', 'w.no_invoice');
    //             })
    //             ->groupBy('invoice.no_invoice')
    //             ->where('is_emailed', true)
    //             ->where('invoice.is_active', true)
    //             ->where('is_whitelist', false)
    //             ->where('order_header.is_active', true)
    //             ->orderBy('invoice.no_invoice', 'DESC');

    //         return Datatables::of($data)
    //             ->addColumn('history', function ($row) {
    //                 $record = RecordPembayaranInvoice::with('sales_in_detail')->where('no_invoice', $row->no_invoice)
    //                     ->where('is_active', true)
    //                     ->orderByDesc('id')
    //                     ->get()
    //                     ->map(function ($r) {
    //                         return [
    //                             'batch_id' => $r->sales_in_detail->header->no_dokumen ?? 'data lama',
    //                             'type' => 'record',
    //                             'nilai_pembayaran' => $r->nilai_pembayaran,
    //                             'nilai_pengurangan' => null,
    //                             'jenis_pengurangan' => null,
    //                             'tanggal_pembayaran' => $r->tgl_pembayaran,
    //                             'keterangan' => $r->keterangan,
    //                             'created_by' => $r->created_by,
    //                             'created_at' => $r->created_at,
    //                         ];
    //                     });

    //                 $withdraw = Withdraw::with('sales_in_detail')->where('no_invoice', $row->no_invoice)
    //                     ->orderByDesc('id')
    //                     ->get()
    //                     ->map(function ($w) {
    //                         return [
    //                             'batch_id' => $w->sales_in_detail->header->no_dokumen ?? 'data lama',
    //                             'type' => 'withdraw',
    //                             'nilai_pembayaran' => null,
    //                             'nilai_pengurangan' => $w->nilai_pembayaran,
    //                             'jenis_pengurangan' => $w->keterangan_pelunasan,
    //                             'tanggal_pembayaran' => $w->created_at,
    //                             'keterangan' => $w->keterangan_tambahan,
    //                             'created_by' => $w->created_by,
    //                             'created_at' => $w->created_at,
    //                         ];
    //                     });

    //                 $history = $record->merge($withdraw);

    //                 return $history;
    //             })
    //             ->filterColumn('nama_customer', function ($query, $keyword) {
    //                 $query->where(function($q) use ($keyword) {
    //                     $q->where('order_header.nama_perusahaan', 'like', "%{$keyword}%");
    //                 });
    //             })
    //             ->filterColumn('consultant', function ($query, $keyword) {
    //                 $query->where(function($q) use ($keyword) {
    //                     $q->Where('order_header.konsultan', 'like', "%{$keyword}%");
    //                 });
    //             })
    //             ->filterColumn('document', function ($query, $keyword) {
    //                 $query->where(function($q) use ($keyword) {
    //                     $q->Where('order_header.no_document', 'like', "%{$keyword}%");
    //                 });
    //             })
    //             ->make(true);
    //     } catch (\Throwable $th) {
    //         dd($th);
    //     }
    // }

    public function index(Request $request)
    {
        try {
            $withdrawSub = DB::table('withdraw')
                ->select(
                    'no_invoice',
                    DB::raw('SUM(nilai_pembayaran) as total_pembayaran')
                )
                ->groupBy('no_invoice');

            $data = Invoice::query()
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

                    DB::raw('MAX(order_header.konsultan) AS consultant'),
                    DB::raw('MAX(order_header.no_document) AS document'),

                    DB::raw('MAX(invoice.created_at) AS created_at'),
                    DB::raw('MAX(invoice.emailed_at) AS emailed_at'),
                    DB::raw('MAX(invoice.emailed_by) AS emailed_by'),

                    DB::raw('MAX(invoice.tgl_pelunasan) AS tgl_pelunasan'),
                    DB::raw('(SUM(invoice.nilai_pelunasan) + COALESCE(MAX(w.total_pembayaran), 0)) AS nilai_pelunasan'),
                    // DB::raw('MAX(invoice.nilai_pelunasan) AS nilai_pelunasan' + 
                    //     ' + COALESCE(MAX(w.total_pembayaran), 0) AS nilai_pelunasan'),

                    DB::raw('MAX(invoice.is_generate) AS is_generate'),
                    DB::raw('MAX(invoice.generated_by) AS generated_by'),
                    DB::raw('MAX(invoice.generated_at) AS generated_at'),
                    DB::raw('MAX(invoice.expired) AS expired'),

                    DB::raw('MAX(invoice.pelanggan_id) AS pelanggan_id'),
                    DB::raw('MAX(invoice.detail_pendukung) AS detail_pendukung'),

                    DB::raw('COALESCE(
                        MAX(order_header.nama_perusahaan),
                        MAX(order_header.konsultan)
                    ) AS nama_customer'),

                    DB::raw('MAX(order_header.is_revisi) AS is_revisi'),

                    DB::raw('GROUP_CONCAT(DISTINCT invoice.no_order) AS no_orders'),
                    DB::raw("
                        CASE
                            WHEN SUM(invoice.nilai_tagihan) = 0 THEN 'Belum Ada Pembayaran'
                            WHEN (SUM(invoice.nilai_tagihan)
                                - (COALESCE(SUM(invoice.nilai_pelunasan),0)
                                    + COALESCE(MAX(w.total_pembayaran),0))
                                ) < 0 THEN 'Kelebihan Pembayaran'
                            WHEN (SUM(invoice.nilai_tagihan)
                                - (COALESCE(SUM(invoice.nilai_pelunasan),0)
                                    + COALESCE(MAX(w.total_pembayaran),0))
                                ) > 0 THEN 'Belum Lunas'
                            ELSE 'Lunas'
                        END AS status_lunas
                    ")
                ])
                ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
                ->leftJoinSub($withdrawSub, 'w', function ($join) {
                    $join->on('invoice.no_invoice', '=', 'w.no_invoice');
                })
                ->where([
                    ['invoice.is_active', true],
                    ['invoice.is_emailed', true],
                    ['invoice.is_whitelist', false],
                    ['order_header.is_active', true],
                ])
                ->groupBy('invoice.no_invoice');

            // ===== FILTER MANUAL KAREBA SEARCHABLE NYA FALSE =====
            if ($request->has('keterangan') && $request->keterangan != '') {
                $status = $request->keterangan;
                
                $data->havingRaw("
                    CASE
                        WHEN SUM(invoice.nilai_pelunasan) = 0 THEN 'Belum Ada Pembayaran'
                        WHEN (SUM(invoice.nilai_tagihan)
                            - (COALESCE(MAX(invoice.nilai_pelunasan),0)
                                + COALESCE(MAX(w.total_pembayaran),0))
                            ) < 0 THEN 'Kelebihan Pembayaran'
                        WHEN (SUM(invoice.nilai_tagihan)
                            - (COALESCE(MAX(invoice.nilai_pelunasan),0)
                                + COALESCE(MAX(w.total_pembayaran),0))
                            ) > 0 THEN 'Belum Lunas'
                        ELSE 'Lunas'
                    END = ?
                ", [$status]);
            }
            if ($request->filled('emailed_by')) {
                $data->havingRaw(
                    'MAX(invoice.emailed_by) LIKE ?',
                    ['%' . $request->emailed_by . '%']
                );
            }

            if ($request->filled('nama_customer')) {
                $data->havingRaw(
                    'COALESCE(
                        MAX(order_header.nama_perusahaan),
                        MAX(order_header.konsultan)
                    ) LIKE ?',
                    ['%' . $request->nama_customer . '%']
                );
            }

            if ($request->filled('tanggal_pembayaran')) {
                $data->havingRaw(
                    'DATE(MAX(invoice.tgl_pelunasan)) LIKE ?',
                    ['%' . $request->tanggal_pembayaran . '%']
                );
            }
            
            if ($request->filled('tanggal_invoice')) {
                $data->havingRaw(
                    'DATE(MAX(invoice.tgl_invoice)) LIKE ?',
                    ['%' . $request->tanggal_invoice . '%']
                );
            }
            
            if ($request->filled('tanggal_jatuh_tempo')) {
                $data->havingRaw(
                    'DATE(MAX(invoice.tgl_jatuh_tempo)) LIKE ?',
                    ['%' . $request->tanggal_jatuh_tempo . '%']
                );
            }

            if ($request->filled('emailed_at')) {
                $data->havingRaw(
                    'DATE(MAX(invoice.emailed_at)) LIKE ?',
                    ['%' . $request->emailed_at . '%']
                );
            }

            if ($request->filled('no_invoice')) {
                $data->where('invoice.no_invoice', 'like', '%' . $request->no_invoice . '%');
            }
            if ($request->filled('document')) {
                $data->where('order_header.no_document', 'like', '%' . $request->document . '%');
            }

            if ($request->filled('consultant')) {
                $data->where('order_header.konsultan', 'like', '%' . $request->consultant . '%');
            }

            $data->orderByDesc('invoice.no_invoice');


            return DataTables::of($data)
                /* ================= HISTORY ================= */
                ->addColumn('history', function ($row) {
                    $record = RecordPembayaranInvoice::with('sales_in_detail.header')
                        ->where('no_invoice', $row->no_invoice)
                        ->where('is_active', true)
                        ->orderByDesc('id')
                        ->get()
                        ->map(fn ($r) => [
                            'batch_id' => $r->sales_in_detail->header->no_dokumen ?? 'data lama',
                            'type' => 'record',
                            'nilai_pembayaran' => $r->nilai_pembayaran,
                            'nilai_pengurangan' => null,
                            'jenis_pengurangan' => null,
                            'tanggal_pembayaran' => $r->tgl_pembayaran,
                            'keterangan' => $r->keterangan,
                            'created_by' => $r->created_by,
                            'created_at' => $r->created_at,
                        ]);

                    $withdraw = Withdraw::with('sales_in_detail.header')
                        ->where('no_invoice', $row->no_invoice)
                        ->orderByDesc('id')
                        ->get()
                        ->map(fn ($w) => [
                            'batch_id' => $w->sales_in_detail->header->no_dokumen ?? 'data lama',
                            'type' => 'withdraw',
                            'nilai_pembayaran' => null,
                            'nilai_pengurangan' => $w->nilai_pembayaran,
                            'jenis_pengurangan' => $w->keterangan_pelunasan,
                            'tanggal_pembayaran' => $w->created_at,
                            'keterangan' => $w->keterangan_tambahan,
                            'created_by' => $w->created_by,
                            'created_at' => $w->created_at,
                        ]);

                    return $record->merge($withdraw);
                })
                ->make(true);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }


    public function pelunasan(Request $request)
    {
        DB::beginTransaction();
        try {
            $getNilai = RecordPembayaranInvoice::select(DB::raw('SUM(nilai_pembayaran) AS nilai_pembayaran'))
                ->where('no_invoice', $request->no_invoice)
                ->where('is_active', true)
                ->groupBy('no_invoice')
                ->first();

            if ($getNilai != null) {
                $getSisa = RecordPembayaranInvoice::select(DB::raw('sisa_pembayaran'))
                    ->where('no_invoice', $request->no_invoice)
                    ->where('is_active', true)
                    ->orderBy('id', 'DESC')
                    ->first();

                if ($getSisa->sisa_pembayaran != 0) {
                    $sisaPembayaran = $getSisa->sisa_pembayaran - preg_replace('/[Rp., ]/', '', $request->nilai_pelunasan) - preg_replace('/[Rp., ]/', '', $request->nilai_pengurangan);
                } else {
                    $sisaPembayaran = $getSisa->sisa_pembayaran;
                }

                $nilaiPembayaran = $getNilai->nilai_pembayaran + preg_replace('/[Rp., ]/', '', $request->nilai_pelunasan);
            } else {

                $nilaiPembayaran = preg_replace('/[Rp., ]/', '', $request->nilai_pelunasan);
                $sisaPembayaran = preg_replace('/[Rp., ]/', '', $request->nilai_tagihan) - preg_replace('/[Rp., ]/', '', $request->nilai_pelunasan) - preg_replace('/[Rp., ]/', '', $request->nilai_pengurangan);
            }

            //insert ke tabel record pembayaran invoice
            $insert = [
                'no_invoice' => $request->no_invoice,
                'tgl_pembayaran' => $request->tgl_pelunasan,
                'nilai_pembayaran' => preg_replace('/[Rp., ]/', '', $request->nilai_pelunasan),
                'nilai_pengurangan' => preg_replace('/[Rp., ]/', '', $request->nilai_pengurangan),
                'lebih_bayar' => preg_replace('/[Rp., ]/', '', $request->lebih_bayar),
                'sisa_pembayaran' => $sisaPembayaran,
                'keterangan' => $request->keterangan,
                'status' => 0,
                'created_at' => Carbon::now(),
                'created_by' => $this->karyawan,
            ];

            RecordPembayaranInvoice::insert($insert);

            //update ke tabel invoice
            $update = [
                'tgl_pelunasan' => $request->tgl_pelunasan,
                'nilai_pelunasan' => $nilaiPembayaran + preg_replace('/[Rp., ]/', '', $request->nilai_pengurangan),
                'keterangan_pelunasan' => $request->keterangan_pelunasan,
            ];

            Invoice::where('no_invoice', $request->no_invoice)
                ->update($update);

            DB::commit();
            return response()->json(['message' => 'Successfully Create Pelunasan', 'status' => 200], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function reject(Request $request)
    {
        $updatedCount = Invoice::where('no_invoice', $request->no_invoice)
            ->update(
                [
                    'is_generate' => false,
                    'is_emailed' => false,
                    'rejected_by' => $this->karyawan,
                    'rejected_at' => Carbon::now(),
                    'keterangan_reject' => $request->reason,
                ]
            );

        return response()->json([
            'message' => "Invoice " . $request->no_invoice . " Has Been Rejected."
        ]);
    }

    public function UpdateJatuhTempo(Request $request)
    {
        $updatedCount = Invoice::where('no_invoice', $request->no_invoice)
            ->update(
                [
                    'tgl_jatuh_tempo' => $request->tgl_jatuh_tempo,
                ]
            );

        return response()->json([
            'message' => "Invoice " . $request->no_invoice . " Has Been Updated."
        ]);
    }
}
