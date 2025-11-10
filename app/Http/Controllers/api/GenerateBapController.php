<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\DokumenBap;
use App\Models\Ftc;
use App\Models\KelengkapanKonfirmasiQs;
use App\Models\LinkLhp;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

use App\Services\GetAtasan;
use App\Services\RenderDokumenBap;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class GenerateBapController extends Controller
{
    public function index()
    {
        $dokumenBap = DokumenBap::with('order');

        return Datatables::of($dokumenBap)->make(true);
    }

    public function getNoQt(Request $request)
    {
        $linkLhps = LinkLhp::where('is_completed', 1)
            ->where('no_quotation', 'LIKE', "%{$request->term}%")
            ->select('no_quotation', 'periode', 'no_order', 'nama_perusahaan')
            ->orderBy('periode') // pastikan periodenya terurut
            ->get();

        $grouped = [];

        foreach ($linkLhps as $row) {
            $noQuotation = $row->no_quotation;
            if (!isset($grouped[$noQuotation])) {
                $grouped[$noQuotation] = [
                    'no_quotation'     => $noQuotation,
                    'no_order'         => $row->no_order,
                    'nama_perusahaan'  => $row->nama_perusahaan,
                    'periodes'         => [],
                ];
            }

            if (!is_null($row->periode)) {
                $grouped[$noQuotation]['periodes'][] = $row->periode;
            }
        }

        foreach ($grouped as &$g) {
            sort($g['periodes']);
        }

        $data = array_values($grouped);


        return response()->json([
            'data' => $data,
            'status' => 200
        ], 200);
    }

    public function getDetail(Request $request)
    {
        if ($request->no_quotation == null) {
            return response()->json([
                'message' => 'No. Quotation tidak valid'
            ]);
        }
        $type = \explode('/', $request->no_quotation)[1];
        if ($type == 'QTC') {
            $detail = QuotationKontrakH::with('order')
                ->where('no_document', $request->no_quotation)
                ->first();

            if ($detail->flag_status != 'ordered') {
                return response()->json([
                    'message' => 'Quotation sedang proses revisi / belum di order (QS Ulang)',
                    'status' => 400
                ], 400);
            }
        } else if ($type == 'QT') {
            $detail = QuotationNonKontrak::where('no_document', $request->no_quotation)->first();
            if ($detail->flag_status != 'ordered') {
                return response()->json([
                    'message' => 'Quotation sedang proses revisi / belum di order (QS Ulang)',
                    'status' => 400
                ], 400);
            }
        } else {
            return response()->json([
                'message' => 'No. Quotation tidak valid',
                'status' => 400
            ], 400);
        }

        $sales = $detail->sales_id;
        $nama_penanggung_jawab = $detail->nama_pic_order;
        $jabatan_penanggung_jawab = $detail->jabatan_pic_order;

        $dataDetail = $detail->order->orderDetail;
        $no_sampel = $dataDetail->where('periode', $request->periode)->where('is_active', 1)->pluck('no_sampel')->toArray();
        $tanggal_sampling = $dataDetail->where('periode', $request->periode)->where('is_active', 1)->pluck('tanggal_sampling')->toArray();
        $tanggal_terima = $dataDetail->where('periode', $request->periode)->where('is_active', 1)->pluck('tanggal_terima')->toArray();

        $cekTracking = Ftc::whereIn('no_sample', $no_sampel)
            ->selectRaw('CAST(ftc_laboratory AS DATE) as ftc_laboratory')
            ->pluck('ftc_laboratory')
            ->toArray();

        $cekTracking = array_filter($cekTracking); // Hilangkan nilai null/false
        sort($cekTracking);

        $tanggal_analisa_awal = null;
        $tanggal_analisa_akhir = null;

        if (!empty($cekTracking)) {
            $tanggal_analisa_awal = $cekTracking[0];
            $tanggal_analisa_akhir = end($cekTracking);

            if ($tanggal_analisa_awal == $tanggal_analisa_akhir) {
                $tanggal_analisa_akhir = null;
            }
        }

        $tanggal_sampling = array_filter($tanggal_sampling);
        sort($tanggal_sampling);
        if (!empty($tanggal_sampling)) {
            $tanggal_sampling_awal = $tanggal_sampling[0];
            $tanggal_sampling_akhir = $tanggal_sampling[count($tanggal_sampling) - 1];
            if ($tanggal_sampling_awal == $tanggal_sampling_akhir) {
                $tanggal_sampling_akhir = null;
            }
        } else {
            $tanggal_sampling_awal = null;
            $tanggal_sampling_akhir = null;
        }

        $tanggal_terima = array_filter($tanggal_terima); // Hilangkan nilai null/false
        sort($tanggal_terima);
        if (!empty($tanggal_terima)) {
            $tanggal_terima_awal = $tanggal_terima[0];
            $tanggal_terima_akhir = $tanggal_terima[count($tanggal_terima) - 1];
            if ($tanggal_terima_awal == $tanggal_terima_akhir) {
                $tanggal_terima_akhir = null;
            }
        } else {
            $tanggal_terima_awal = null;
            $tanggal_terima_akhir = null;
        }

        $getKaryawan = GetAtasan::where('id', $sales)->get();

        $atasan = $getKaryawan->where('grade', 'SUPERVISOR')->first();
        if ($atasan) {
            $nama_penanggung_jawab_sales = $atasan->nama_lengkap;
            $jabatan_penanggung_jawab_sales = $atasan->jabatan;
        } else {
            $atasan = $getKaryawan->where('grade', 'MANAGER')->first();
            $nama_penanggung_jawab_sales = $atasan->nama_lengkap;
            $jabatan_penanggung_jawab_sales = $atasan->jabatan;
        }

        $purchaseOrder = KelengkapanKonfirmasiQs::where('no_quotation', $request->no_quotation)->first();

        $data = [
            'sales' => $sales,
            'nama_penanggung_jawab' => $nama_penanggung_jawab,
            'jabatan_penanggung_jawab' => $jabatan_penanggung_jawab,
            'tanggal_sampling_awal' => $tanggal_sampling_awal,
            'tanggal_sampling_akhir' => $tanggal_sampling_akhir,
            'tanggal_terima_awal' => $tanggal_terima_awal,
            'tanggal_terima_akhir' => $tanggal_terima_akhir,
            'tanggal_analisa_awal' => $tanggal_analisa_awal,
            'tanggal_analisa_akhir' => $tanggal_analisa_akhir,
            'nama_penanggung_jawab_sales' => $nama_penanggung_jawab_sales,
            'jabatan_penanggung_jawab_sales' => $jabatan_penanggung_jawab_sales,
            'nama_penanggung_jawab_teknis' => 'Abidah Walfathiyyah',
            'jabatan_penanggung_jawab_teknis' => 'Supervisor Technical Control',
            'alamat_perusahaan' => $detail->alamat_kantor,
            'no_po' => $purchaseOrder ? $purchaseOrder->no_purchaseorder : null,
        ];

        return response()->json([
            'data' => $data,
            'status' => 200
        ], 200);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $periode = $request->periode ?? '';
            $bulan = $periode ? (int) substr($periode, 5, 2) : null;

            $orderHeader = OrderHeader::where('no_order', $request->no_order)->first();

            $bap = new DokumenBap();
            $bap->id_order = $orderHeader->id;
            $bap->periode = $request->periode;
            $bap->no_document = 'ISL/BAP/' . $orderHeader->no_order . ($bulan ? '-' . $bulan : '');
            $bap->tanggal_rilis = $request->tanggal_rilis;
            $bap->nama_perusahaan = $request->nama_perusahaan;
            $bap->alamat_perusahaan = $request->alamat_perusahaan;
            $bap->nama_penanggung_jawab = $request->nama_penanggung_jawab;
            $bap->jabatan_penanggung_jawab = $request->jabatan_penanggung_jawab;
            $bap->no_po = $request->no_po ?: null;
            $bap->tanggal_sampling_awal = $request->tanggal_sampling_awal ?: null;
            $bap->tanggal_sampling_akhir = $request->tanggal_sampling_akhir ?: null;
            $bap->tanggal_sampel_diterima_awal = $request->tanggal_sampling_awal ?: null;
            $bap->tanggal_sampel_diterima_akhir = $request->tanggal_terima_akhir ?: null;
            $bap->tanggal_penyelesaian_analisa_awal = $request->tanggal_analisa_awal ?: null;
            $bap->tanggal_penyelesaian_analisa_akhir = $request->tanggal_analisa_akhir ?: null;
            $bap->nama_tim_sales = $request->nama_penanggung_jawab_sales;
            $bap->jabatan_tim_sales = $request->jabatan_penanggung_jawab_sales;
            $bap->nama_tim_teknis = $request->nama_penanggung_jawab_teknis;
            $bap->jabatan_tim_teknis = $request->jabatan_penanggung_jawab_teknis;
            $bap->generate_at = Carbon::now()->format('Y-m-d H:i:s');
            $bap->generate_by = $this->karyawan;
            $bap->save();

            $render = new RenderDokumenBap();

            $fileName = $render->execute($bap);

            $bap->filename = $fileName;
            $bap->save();

            DB::commit();
            return response()->json([
                'message' => 'success generate BAP',
                'data' => $fileName,
                'status' => 200
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    } 
    
    public function handlePrintBap(Request $request) 
    {
        DB::beginTransaction();
        try {
            $bap = DokumenBap::where('id', $request->id)->first();
            $bap->count_print = $bap->count_print + 1;
            $bap->is_printed = true;
            $bap->printed_by = $this->karyawan;
            $bap->printed_at = Carbon::now()->format('Y-m-d H:i:s');
            $bap->save();

            DB::commit();
            return response()->json([
                'message' => 'Berhasil Print BAP' . $bap->no_document,
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error download file '.$th->getMessage(),
            ], 401);
        }
        
    }
}
