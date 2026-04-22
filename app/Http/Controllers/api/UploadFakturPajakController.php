<?php

namespace App\Http\Controllers\api;

use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\OrderHeader;
use App\Models\MasterKaryawan;
use App\Models\GenerateLink;
use App\Models\Invoice;
use App\Services\GenerateToken;
use App\Http\Controllers\Controller;
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


class UploadFakturPajakController extends Controller
{
    //2025-08-02
    public function index(Request $request)
    {
        try {
            // if (isset($request->tgl_akhir) && $request->tgl_akhir != null) {
            //     $db = $request->tgl_akhir;
            // } else {
            //     return response()->json(['data' => [], 'message' => 'Tanggal Transaksi Tidak Ada.!'], 201);
            // }

            $data = Invoice::select(
                'invoice.no_invoice',
                DB::raw('MAX(invoice.created_by) AS created_by'),
                DB::raw('MAX(invoice.emailed_by) AS emailed_by'),
                DB::raw('MAX(invoice.emailed_at) AS emailed_at'),
                DB::raw('MAX(faktur_pajak) AS faktur_pajak'),
                DB::raw('SUM(total_tagihan) AS total_tagihan'),
                DB::raw('MAX(rekening) AS rekening'),
                DB::raw('MAX(periode) AS periode_kontrak'), //05/02/2025
                DB::raw('MAX(keterangan) AS keterangan'),
                DB::raw('MAX(nama_pj) AS nama_pj'),
                DB::raw('MAX(jabatan_pj) AS jabatan_pj'),
                DB::raw('MAX(tgl_invoice) AS tgl_invoice'),
                DB::raw('MAX(no_faktur) AS no_faktur'),
                DB::raw('MAX(alamat_penagihan) AS alamat_penagihan'),
                DB::raw('MAX(nama_pic) AS nama_pic'),
                DB::raw('MAX(no_pic) AS no_pic'),
                DB::raw('MAX(email_pic) AS email_pic'),
                DB::raw('MAX(is_custom) AS is_custom'),
                DB::raw('MAX(invoice.keterangan_tambahan) AS keterangan_tambahan'),
                DB::raw('MAX(jabatan_pic) AS jabatan_pic'),
                DB::raw('MAX(invoice.no_po) AS no_po'),
                DB::raw('MAX(no_spk) AS no_spk'),
                DB::raw('MAX(tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
                DB::raw('MAX(filename) AS filename'),
                DB::raw('MAX(file_pph) AS file_pph'),
                DB::raw('MAX(file_faktur) AS file_faktur'),
                DB::raw('MAX(upload_file) AS upload_file'),
                DB::raw('MAX(order_header.konsultan) AS consultant'),
                DB::raw('MAX(order_header.no_document) AS document'),
                DB::raw('MAX(invoice.created_at) AS created_at'),
                DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
                DB::raw('MAX(nilai_pelunasan) AS nilai_pelunasan'),
                DB::raw('MAX(is_generate) AS is_generate'),
                DB::raw('MAX(generated_by) AS generated_by'),
                DB::raw('MAX(generated_at) AS generated_at'),
                DB::raw('MAX(expired) AS expired'),
                DB::raw('MAX(invoice.pelanggan_id) AS pelanggan_id'),
                DB::raw('MAX(invoice.detail_pendukung) AS detail_pendukung'),
                DB::raw('MAX(invoice.nama_perusahaan) AS nama_customer'),
                // DB::raw('COALESCE(MAX(order_header.konsultan), MAX(nama_perusahaan)) AS nama_customer'),
                DB::raw('SUM(invoice.nilai_tagihan) AS nilai_tagihan'),
                DB::raw('MAX(order_header.is_revisi) AS is_revisi'),
                DB::raw('GROUP_CONCAT(invoice.no_order) AS no_orders'),
                DB::raw('GROUP_CONCAT(invoice.no_quotation) AS no_quots'),
                DB::raw('GROUP_CONCAT(CONCAT(order_header.no_document, "_", invoice.no_order)) AS document_order')
            )
                ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
                ->groupBy('invoice.no_invoice')
                ->where('is_emailed', false)
                ->where('no_invoice', 'LIKE', '%INV/%')
                ->where('invoice.created_at', '>', '2025-04-27 00:00:00')
                ->whereNull('file_faktur')
                ->where('invoice.is_active', true)
                ->where('order_header.is_active', true)
                ->orderBy('invoice.no_invoice', 'DESC');

            return Datatables::of($data)
                ->filterColumn('nama_customer', function ($query, $keyword) {
                    $query->whereRaw('LOWER(invoice.nama_perusahaan) LIKE ?', ['%' . strtolower($keyword) . '%']);
                })
                ->filterColumn('document', function ($query, $keyword) {
                    $query->whereExists(function ($q) use ($keyword) {
                        $q->select(DB::raw(1))
                            ->from('order_header as oh2')
                            ->whereColumn('oh2.no_order', 'invoice.no_order')
                            ->whereRaw('LOWER(oh2.no_document) LIKE ?', ['%' . strtolower($keyword) . '%']);
                    });
                })
                ->filterColumn('emailed_at', function ($data, $keyword) {
                    if ($keyword == '-') {
                        $data->whereNull('emailed_at');
                    } else {
                        $data->whereRaw("DATE_FORMAT(emailed_at, '%Y-%m-%d') like ?", ["%$keyword%"]);
                    }
                })
                ->filterColumn('emailed_by', function ($data, $keyword) {
                    if ($keyword == '-') {
                        $data->whereNull('emailed_by');
                    } else {
                        $data->whereRaw("emailed_by like ?", ["%$keyword%"]);
                    }
                })
                ->make(true);
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function upload(Request $request)
    {
        DB::beginTransaction();
        try {
            $file = $request->file('file_input');

            // Validasi file
            if (!$file || $file->getClientOriginalExtension() !== 'pdf') {
                return response()->json(['error' => 'File tidak valid. Harus .pdf'], 400);
            }

            $inv = Invoice::where('no_invoice', $request->no_invoice)->first();
            // Pastikan folder invoice ada
            $folder = public_path('invoice-faktur');
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            // Generate nama file unik
            $fileName = 'FAKTUR' . '_' . preg_replace('/\\//', '_', $inv->no_invoice) . '.pdf';

            // Simpan file
            $file->move($folder, $fileName);
            $inv->file_faktur = $fileName;
            $inv->faktur_pajak = $request->no_faktur;
            $inv->save();
            
            DB::commit();

            $this->generatePDF($inv->no_invoice);
            
            return response()->json([
                'success'  => 'Sukses menyimpan file upload',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Terjadi kesalahan server',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private static function generatePDF($noInvoice)
    {
        $render = new RenderInvoice();
        $render->renderInvoice($noInvoice);
        return true;
    }
}
