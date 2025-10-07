<?php

namespace App\Http\Controllers\api;

use App\Models\{LhpsPencahayaanHeader,LhpsPencahayaanDetail,LhpsPencahayaanCustom,OrderDetail};
use App\Services\{GenerateQrDocumentLhp,LhpTemplate,PrintLhp};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class LhpUdaraPencahayaanController extends Controller
{
    public function index(Request $request){
         DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with('lhps_pencahayaan','orderHeader','dataLapanganCahaya')
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_approve', true)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->where('kategori_3', "28-Pencahayaan")
            ->groupBy('cfr')
            ->where('status', 3)
            ->get();
        
        return Datatables::of($data)->make(true);
    }

    public function handleReject(Request $request) {
        DB::beginTransaction();
        try {
            $header = LhpsPencahayaanHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();
            if($header != null) {

                $header->is_approve = 0;
                $header->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->rejected_by = $this->karyawan;
                
                // $header->file_qr = null;
                $header->save();
             OrderDetail::where('cfr', $request->no_lhp)
                    ->whereIn('no_sampel', explode(', ',$request->no_sampel))
                    ->where('is_active', true)
                    ->update([
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

    public function handleDownload(Request $request) {
        try {
            $header = LhpsPencahayaanHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            if($header != null && $header->file_lhp == null) {
                $detail = LhpsPencahayaanDetail::where('id_header', $header->id)->get();
                $custom = LhpsPencahayaanCustom::where('id_header', $header->id)->get();

                if($header->file_qr == null) {
                    $header->file_qr = 'LHP-'.str_ireplace("/", "_",$header->no_lhp);
                    $header->save();
                    GenerateQrDocumentLhp::insert('LHP', $header, $this->karyawan);
                }

                $groupedByPage = [];
                if(!empty($custom)) {
                    foreach ($custom as $item) {
                        $page = $item['page'];
                        if (!isset($groupedByPage[$page])) {
                            $groupedByPage[$page] = [];
                        }
                        $groupedByPage[$page][] = $item;
                    }
                }

                $job = new RenderLhp($header, $detail, 'downloadLHP', $groupedByPage);
                $this->dispatch($job);

                $data = LhpsPencahayaanHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
                $data->file_lhp = $fileName;
                $data->save();

            } else if($header != null && $header->file_lhp != null) {
                $fileName = $header->file_lhp;
            }

            return response()->json([
                'file_name' =>  env('APP_URL') . '/public/dokumen/LHP/' . $fileName,
                'message' => 'Download file '.$request->no_sampel.' berhasil!'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error download file '.$th->getMessage(),
            ], 401);
        }
        
    }

    public function rePrint(Request $request) 
    {
        DB::beginTransaction();
        $header = LhpsPencahayaanHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
        $header->count_print = $header->count_print + 1; 

        $detail = LhpsPencahayaanDetail::where('id_header', $header->id)->get();
        $custom = LhpsPencahayaanCustom::where('id_header', $header->id)->get();

        if ($header != null) {
            if ($header->file_qr == null) {
                $file_qr = new GenerateQrDocumentLhp();
                $file_qr_path = $file_qr->insert('LHP_AIR', $header, $this->karyawan);
                if ($file_qr_path) {
                    $header->file_qr = $file_qr_path;
                    $header->save();
                }
            }

            $groupedByPage = [];
            if (!empty($custom)) {
                foreach ($custom->toArray() as $item) {
                    $page = $item['page'];
                    if (!isset($groupedByPage[$page])) {
                        $groupedByPage[$page] = [];
                    }
                    $groupedByPage[$page][] = $item;
                }
            }

            $fileName = LhpTemplate::setDataDetail($detail)
                ->setDataHeader($header)
                ->setDataCustom($groupedByPage)
                ->whereView('DraftAir')
                ->render('downloadLHP');

            $header->file_lhp = $fileName;
            $header->save();
        }

        $servicePrint = new PrintLhp();
        $servicePrint->print($request->no_sampel);
        
        if (!$servicePrint) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal Melakukan Reprint Data', 'status' => '401'], 401);
        }
        
        DB::commit();

        return response()->json([
            'message' => 'Berhasil Melakukan Reprint Data ' . $request->no_sampel . ' berhasil!'
        ], 200);
    }
}