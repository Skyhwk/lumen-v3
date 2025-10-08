<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Services\Notification;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Jobs\RenderPdfPermintaanDokumentasiSampling;

use App\Models\QrDocument;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\PermintaanDokumentasiSampling;

class PermintaanDokumentasiSamplingController extends Controller
{
    public function index()
    {
        $permintaanDokumentasiSampling = PermintaanDokumentasiSampling::latest()
            ->where('is_rejected', 0)
            ->where('is_active', 1)
            ->where('is_approved', 0)
            ->get();

        return DataTables::of($permintaanDokumentasiSampling)->make(true);
    }

    public function searchQuotations(Request $request)
    {
        $search = $request->input('q');

        $kontrak = QuotationKontrakH::with(['detail:id_request_quotation_kontrak_h,periode_kontrak', 'order:id,no_document,no_order', 'order.orderDetail:id_order_header,periode,tanggal_sampling'])
            ->select('id', 'no_document', 'nama_perusahaan', 'alamat_sampling')
            ->where('no_document', 'LIKE', "%{$search}%")
            ->whereNotIn('flag_status', ['rejected', 'void'])
            ->where('is_active', true)
            ->limit(5)
            ->get();

        $nonKontrak = QuotationNonKontrak::with(['order:id,no_document,no_order', 'order.orderDetail:id_order_header,tanggal_sampling'])
            ->select('id', 'no_document', 'nama_perusahaan', 'alamat_sampling')
            ->where('no_document', 'LIKE', "%{$search}%")
            ->whereNotIn('flag_status', ['rejected', 'void'])
            ->where('is_active', true)
            ->limit(5)
            ->get();

        $results = $kontrak->merge($nonKontrak);
        $results->makeHidden(['id']);

        return response()->json($results, 200);
    }

    private function generateQr($noDocument)
    {
        $filename = str_replace("/", "_", $noDocument);
        $dir = public_path("qr_documents");

        if (!file_exists($dir)) mkdir($dir, 0755, true);

        $path = $dir . "/$filename.svg";
        $link = 'https://www.intilab.com/validation/';
        $unique = 'isldc' . (int) floor(microtime(true) * 1000);

        QrCode::size(200)->generate($link . $unique, $path);

        return $unique;
    }

    public function save(Request $request)
    {
        DB::beginTransaction();
        try {
            $latest = PermintaanDokumentasiSampling::where('is_active', 1)->latest()->first();
            $no_document = 'ISL/DKS/' . date('y') . '-' . ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][date('m') - 1] . '/' . sprintf('%04d', $latest ? $latest->id + 1 : 1);

            $permintaanDokumentasiSampling = new PermintaanDokumentasiSampling();

            $permintaanDokumentasiSampling->no_document = $no_document;
            $permintaanDokumentasiSampling->no_quotation = $request->no_quotation;
            $permintaanDokumentasiSampling->periode = $request->periode;
            $permintaanDokumentasiSampling->no_order = $request->no_order;
            $permintaanDokumentasiSampling->nama_perusahaan = $request->nama_perusahaan;
            $permintaanDokumentasiSampling->alamat_sampling = $request->alamat_sampling;
            $permintaanDokumentasiSampling->created_by = $this->karyawan;
            $permintaanDokumentasiSampling->created_at = Carbon::now();
            $permintaanDokumentasiSampling->updated_by = $this->karyawan;
            $permintaanDokumentasiSampling->updated_at = Carbon::now();

            $permintaanDokumentasiSampling->save();

            $qr = new QrDocument();

            $qr->id_document = $permintaanDokumentasiSampling->id;
            $qr->type_document = 'permintaan_dokumentasi_sampling';
            $qr->kode_qr = $this->generateQr($no_document);
            $qr->file = str_replace("/", "_", $no_document);

            $qr->data = json_encode([
                'no_document' => $no_document,
                'type_document' => 'permintaan_dokumentasi_sampling',
                'no_quotation' => $request->no_quotation,
                'no_order' => $request->no_order,
                'periode' => Carbon::parse($request->periode)->translatedFormat('F Y'),
                'tanggal_sampling' => Carbon::parse($request->tanggal_sampling)->translatedFormat('d F Y'),
                'nama_perusahaan' => $request->nama_perusahaan
            ]);

            $qr->created_by = $this->karyawan;
            $qr->created_at = Carbon::now();

            $qr->save();

            $this->dispatch(new RenderPdfPermintaanDokumentasiSampling($permintaanDokumentasiSampling, $qr));

            DB::commit();

            Notification::whereIn('id', [13, 127, 599])
                ->title('Berhasil mengirim permintaan')
                ->message('Permintaan Dokumentasi Kegiatan Sampling telah ditambahkan oleh ' . $this->karyawan . ' pada ' . Carbon::now()->translatedFormat('d F Y H:i'))
                ->url('/permintaan-dokumentasi-sampling')
                ->send();

            return response()->json(['message' => 'Requested successfully'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to request',
                'error' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    public function approve(Request $request)
    {
        if (in_array($request->attributes->get('user')->karyawan->id, [13, 127, 599])) {
            $permintaanDokumentasiSampling = PermintaanDokumentasiSampling::find($request->id);

            $permintaanDokumentasiSampling->is_approved = 1;
            $permintaanDokumentasiSampling->approved_by = $this->karyawan;
            $permintaanDokumentasiSampling->approved_at = Carbon::now();

            $permintaanDokumentasiSampling->save();

            Notification::whereIn('id', [13, 127, 599])
                ->title('Berhasil approve permintaan')
                ->message('Permintaan Dokumentasi Kegiatan Sampling telah diapprove oleh ' . $this->karyawan . ' pada ' . Carbon::now()->translatedFormat('d F Y H:i'))
                ->url('/permintaan-dokumentasi-sampling')
                ->send();

            return response()->json(['message' => 'Berhasil approve permintaan'], 200);
        }

        return response()->json(['message' => 'Anda tidak memiliki akses untuk approve permintaan'], 401);
    }

    public function reject(Request $request)
    {
        if (in_array($request->attributes->get('user')->karyawan->id, [13, 127, 599])) {
            $permintaanDokumentasiSampling = PermintaanDokumentasiSampling::find($request->id);

            $permintaanDokumentasiSampling->is_rejected = 1;
            $permintaanDokumentasiSampling->rejected_by = $this->karyawan;
            $permintaanDokumentasiSampling->rejected_at = Carbon::now();

            $permintaanDokumentasiSampling->save();

            Notification::whereIn('id', [13, 127, 599])
                ->title('Berhasil reject permintaan')
                ->message('Permintaan Dokumentasi Kegiatan Sampling telah direject oleh ' . $this->karyawan . ' pada ' . Carbon::now()->translatedFormat('d F Y H:i'))
                ->url('/permintaan-dokumentasi-sampling')
                ->send();

            return response()->json(['message' => 'Berhasil reject permintaan'], 200);
        }

        return response()->json(['message' => 'Anda tidak memiliki akses untuk reject permintaan'], 401);
    }
}
