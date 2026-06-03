<?php

namespace App\Http\Controllers\api;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Invoice;
use App\Models\RecordPembayaranInvoice;
use App\Http\Controllers\Controller;
use App\Models\SummaryInvoice;
use App\Services\RenderInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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

    public function index(Request $request)
    {
        try {
            $data = SummaryInvoice::query();
            $statusLunas = trim((string) $request->input('columns.3.search.value', $request->input('keterangan', '')));

            if ($statusLunas !== '') {
                $data->where('status_lunas',$statusLunas);
            }

            return Datatables::of($data)->make(true);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [],
                'message' => $th->getMessage(),
            ], 401);
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
