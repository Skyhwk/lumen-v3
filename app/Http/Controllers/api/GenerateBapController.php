<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\DokumenBap;
use App\Models\Ftc;
use App\Models\LinkLhp;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

use App\Services\GetAtasan;

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
        if($request->no_quotation == null){
            return response()->json([
                'message' => 'No. Quotation tidak valid'
            ]);
        }
        $type = \explode('/', $request->no_quotation)[1];
        if($type == 'QTC'){
            $detail = QuotationKontrakH::with('order')
            ->where('no_document', $request->no_quotation)
            ->first();

            if($detail->flag_status != 'ordered')
            {
                return response()->json([
                    'message' => 'Quotation sedang proses revisi / belum di order (QS Ulang)',
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
            if($atasan){
                $nama_penanggung_jawab_sales = $atasan->nama_lengkap;
                $jabatan_penanggung_jawab_sales = $atasan->jabatan;
            } else {
                $atasan = $getKaryawan->where('grade', 'MANAGER')->first();
                $nama_penanggung_jawab_sales = $atasan->nama_lengkap;
                $jabatan_penanggung_jawab_sales = $atasan->jabatan;
            }



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
            ];
            
        } else if($type == 'QT'){
            $detail = QuotationNonKontrak::where('no_document', $request->no_quotation)->first();
            $order_detail = OrderDetail::where('no_order', $detail->no_order)->where('is_active', 1)->get()->pluck('no_sampel');
        } else {
            return response()->json([
                'message' => 'No. Quotation tidak valid',
                'status' => 400
            ], 400);
        }

        return response()->json([
            'data' => $data,
            'status' => 200
        ], 200);
    }
}