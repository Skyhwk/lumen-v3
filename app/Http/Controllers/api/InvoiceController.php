<?php

namespace App\Http\Controllers\api;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Invoice;
use App\Models\RecordPembayaranInvoice;
use App\Http\Controllers\Controller;
use App\Models\SummaryInvoice;
use App\Services\DateColumnFilterService;
use App\Services\RenderInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;


class InvoiceController extends Controller
{
    private const MIN_YEAR = 2024;

    private function resolveYear($year): int
    {
        $currentYear = (int) Carbon::now()->year;
        $year = (int) ($year ?: $currentYear);

        return max(self::MIN_YEAR, min($currentYear, $year));
    }

    public static function generatePDF($noInvoice)
    {
        $render = new RenderInvoice();
        $render->renderInvoice($noInvoice);
        return true;
    }

    public function index(Request $request)
    {
        try {
            $year = $this->resolveYear($request->year);

            $data = SummaryInvoice::query()
                ->whereYear('summary_invoice.tgl_invoice', $year)
                ->leftJoin('request_quotation', 'summary_invoice.document', '=', 'request_quotation.no_document')
                ->leftJoin('request_quotation_kontrak_H', 'summary_invoice.document', '=', 'request_quotation_kontrak_H.no_document')
                ->select(
                    'summary_invoice.*',
                    'request_quotation.kode_promo as kode_promo_non_kontrak',
                    'request_quotation_kontrak_H.kode_promo as kode_promo_kontrak'
                );

            return $this->filterInvoiceColumns(Datatables::of($data))->make(true);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [],
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    private function filterInvoiceColumns($datatable)
    {
        $like = function ($column) {
            return function ($query, $keyword) use ($column) {
                $query->whereRaw('LOWER(' . $column . ') LIKE ?', ['%' . strtolower($keyword) . '%']);
            };
        };

        return $datatable
            ->filterColumn('status_lunas', function ($query, $keyword) {
                $query->where('summary_invoice.status_lunas', $keyword);
            })
            ->filterColumn('kode_promo_non_kontrak', function ($query, $keyword) {
                $likeValue = '%' . strtolower($keyword) . '%';
                $query->where(function ($inner) use ($likeValue) {
                    $inner->whereRaw('LOWER(request_quotation.kode_promo) LIKE ?', [$likeValue])
                        ->orWhereRaw('LOWER(request_quotation_kontrak_H.kode_promo) LIKE ?', [$likeValue]);
                });
            })
            ->orderColumn('kode_promo_non_kontrak', function ($query, $direction) {
                $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';
                $query->orderByRaw(
                    'COALESCE(request_quotation.kode_promo, request_quotation_kontrak_H.kode_promo) ' . $direction
                );
            })
            ->filterColumn('no_invoice', $like('summary_invoice.no_invoice'))
            ->filterColumn(
                'tgl_invoice',
                DateColumnFilterService::datatablesFilter('summary_invoice.tgl_invoice')
            )
            ->filterColumn('document', $like('summary_invoice.document'))
            ->filterColumn('sales_penanggung_jawab', $like('summary_invoice.sales_penanggung_jawab'))
            ->filterColumn('nama_customer', $like('summary_invoice.nama_customer'))
            ->filterColumn('consultant', $like('summary_invoice.consultant'))
            ->filterColumn('no_po', $like('summary_invoice.no_po'))
            ->filterColumn('no_spk', $like('summary_invoice.no_spk'))
            ->filterColumn(
                'tgl_jatuh_tempo',
                DateColumnFilterService::datatablesFilter('summary_invoice.tgl_jatuh_tempo')
            )
            ->filterColumn(
                'tgl_pelunasan',
                DateColumnFilterService::datatablesFilter('summary_invoice.tgl_pelunasan')
            )
            ->filterColumn('nilai_tagihan', $like('summary_invoice.nilai_tagihan'))
            ->filterColumn('nilai_pelunasan', $like('summary_invoice.nilai_pelunasan'))
            ->filterColumn('emailed_by', $like('summary_invoice.emailed_by'))
            ->filterColumn(
                'emailed_at',
                DateColumnFilterService::datatablesFilter('summary_invoice.emailed_at')
            )
            ->filterColumn(
                'created_at',
                DateColumnFilterService::datatablesFilter('summary_invoice.created_at')
            )
            ->filterColumn('created_by', $like('summary_invoice.created_by'))
        ;
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
