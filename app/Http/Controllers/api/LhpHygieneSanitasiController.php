<?php

namespace App\Http\Controllers\api;

use App\Models\LhpsLingHeader;
use App\Models\LhpsLingDetail;
use App\Models\LhpsLingCustom;
use App\Models\OrderDetail;
use App\Models\MetodeSampling;
use App\Models\MasterBakumutu;
use App\Models\Parameter;
use App\Models\GenerateLink;
use App\Services\TemplateLhps;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use App\Services\PrintLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\LhpsHygieneSanitasiHeader;
use Carbon\Carbon;
use Exception;
use Yajra\Datatables\Datatables;

class LhpHygieneSanitasiController extends Controller
{
    public function index(Request $request){
        // $data = OrderDetail::with('lhps_ling','orderHeader','dataLapanganLingkunganKerja')->where('is_approve', true)->where('is_active', true)->where('kategori_2', '4-Udara')->where('kategori_3', '27-Udara Lingkungan Kerja')->where('status', 3)->orderBy('tanggal_terima', 'desc');
        $parameterAllowed = [
            'K3-KB',
            'K3-KFK',
            'K3-KFS',
            'K3-KFPBP',
            'K3-KRU',
            'K3-KTRTHK',
        ];

        $data = OrderDetail::selectRaw('
                max(id) as id,
                max(id_order_header) as id_order_header,
                cfr,
                GROUP_CONCAT(no_sampel SEPARATOR ",") as no_sampel,
                MAX(nama_perusahaan) as nama_perusahaan,
                MAX(konsultan) as konsultan,
                MAX(no_quotation) as no_quotation,
                MAX(no_order) as no_order,
                MAX(parameter) as parameter,
                MAX(regulasi) as regulasi,
                GROUP_CONCAT(DISTINCT kategori_1 SEPARATOR ",") as kategori_1,
                MAX(kategori_2) as kategori_2,
                MAX(kategori_3) as kategori_3,
                GROUP_CONCAT(DISTINCT keterangan_1 SEPARATOR ",") as keterangan_1,
                GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ",") as tanggal_tugas,
                GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ",") as tanggal_terima
            ')
            ->with(['lhps_hygene','orderHeader'])
            ->where('is_active', true)
            ->where('kategori_3', '27-Udara Lingkungan Kerja')
            ->where('status', 3)
            ->where(function ($query) use ($parameterAllowed) {
                foreach ($parameterAllowed as $param) {
                    $query->where('parameter', 'LIKE', "%;$param%");
                }
            })
            ->groupBy('cfr');

        return Datatables::of($data)
            ->order(function ($query) {
                $query->orderByRaw("MAX(tanggal_terima) DESC");
            })
            ->make(true);

    }


    public function handleReject(Request $request) {
        DB::beginTransaction();
        try {
            $header = LhpsHygieneSanitasiHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();
            // $detail = LhpsLingDetail::where('id_header', $header->id)->get();
            // $custom = LhpsLingCustom::where('id_header', $header->id)->get();
            if($header != null) {

                $header->is_approved = 0;
                $header->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->rejected_by = $this->karyawan;
                
                // $header->file_qr = null;
                $header->save();

                $data_order = OrderDetail::where('cfr', $request->no_lhp)->where('is_active', true)->update([
                    'status' => 2,
                    'is_approve' => 0,
                    'rejected_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'rejected_by' => $this->karyawan
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Reject no LHP '.$request->no_lhp.' berhasil!'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan '.$e->getMessage(),
            ], 401);
        }
    }

    // public function handleDownload(Request $request) {
    //     try {
    //         $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
    //         if($header != null && $header->file_lhp == null) {
    //             $detail = LhpsLingDetail::where('id_header', $header->id)->get();
    //             $custom = LhpsLingCustom::where('id_header', $header->id)->get();

    //             if($header->file_qr == null) {
    //                 $header->file_qr = 'LHP-'.str_ireplace("/", "_",$header->no_lhp);
    //                 $header->save();
    //                 GenerateQrDocumentLhp::insert('LHP', $header, $this->karyawan);
    //             }

    //             $groupedByPage = [];
    //             if(!empty($custom)) {
    //                 foreach ($custom as $item) {
    //                     $page = $item['page'];
    //                     if (!isset($groupedByPage[$page])) {
    //                         $groupedByPage[$page] = [];
    //                     }
    //                     $groupedByPage[$page][] = $item;
    //                 }
    //             }

    //             $job = new RenderLhp($header, $detail, 'downloadLHP', $groupedByPage);
    //             $this->dispatch($job);

    //             $data = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
    //             $data->file_lhp = $fileName;
    //             $data->save();

    //         } else if($header != null && $header->file_lhp != null) {
    //             $fileName = $header->file_lhp;
    //         }

    //         return response()->json([
    //             'file_name' =>  env('APP_URL') . '/public/dokumen/LHP/' . $fileName,
    //             'message' => 'Download file '.$request->no_sampel.' berhasil!'
    //         ]);
    //     } catch (\Throwable $th) {
    //         return response()->json([
    //             'message' => 'Error download file '.$th->getMessage(),
    //         ], 401);
    //     }
        
    // }

    // public function rePrint(Request $request) 
    // {
    //     DB::beginTransaction();
    //     $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
    //     $header->count_print = $header->count_print + 1; 

    //     $detail = LhpsLingDetail::where('id_header', $header->id)->get();
    //     $custom = LhpsLingCustom::where('id_header', $header->id)->get();

    //     if ($header != null) {
    //         if ($header->file_qr == null) {
    //             $file_qr = new GenerateQrDocumentLhp();
    //             $file_qr_path = $file_qr->insert('LHP_LINGKUNGAN_KERJA', $header, $this->karyawan);
    //             if ($file_qr_path) {
    //                 $header->file_qr = $file_qr_path;
    //                 $header->save();
    //             }
    //         }

    //         $groupedByPage = [];
    //         if (!empty($custom)) {
    //             foreach ($custom->toArray() as $item) {
    //                 $page = $item['page'];
    //                 if (!isset($groupedByPage[$page])) {
    //                     $groupedByPage[$page] = [];
    //                 }
    //                 $groupedByPage[$page][] = $item;
    //             }
    //         }

    //         $fileName = LhpTemplate::setDataDetail($detail)
    //             ->setDataHeader($header)
    //             ->setDataCustom($groupedByPage)
    //             ->whereView('DraftUdaraLingkunganKerja')
    //             ->render('downloadLHP');

    //         $header->file_lhp = $fileName;
    //         $header->save();
    //     }

    //     $servicePrint = new PrintLhp();
    //     $servicePrint->printByFilename($header->file_lhp, $detail);
        
    //     if (!$servicePrint) {
    //         DB::rollBack();
    //         return response()->json(['message' => 'Gagal Melakukan Reprint Data', 'status' => '401'], 401);
    //     }
        
    //     DB::commit();

    //     return response()->json([
    //         'message' => 'Berhasil Melakukan Reprint Data ' . $request->no_sampel . ' berhasil!'
    //     ], 200);
    // }
}