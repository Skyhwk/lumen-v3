<?php

namespace App\Http\Controllers\api;

use App\Models\LhpsLingHeader;
use App\Models\LhpsLingDetail;
use App\Models\LhpsLingCustom;
use App\Models\OrderDetail;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use App\Services\PrintLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class LhpUdaraAmbientController extends Controller
{
    public function index(Request $request){
        $data = OrderDetail::with('lhps_ling','orderHeader','dataLapanganLingkunganHidup')->where('is_approve', true)->where('is_active', true)->where('kategori_2', '4-Udara')->where('kategori_3', '11-Udara Ambient')->where('status', 3)->orderBy('tanggal_terima', 'desc');
        return Datatables::of($data)->make(true);
    }

    public function handleReject(Request $request) {
        DB::beginTransaction();
        try {
            $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            $detail = LhpsLingDetail::where('id_header', $header->id)->get();
            $custom = LhpsLingCustom::where('id_header', $header->id)->get();

            if($header != null) {

                $header->is_approved = 0;
                $header->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->rejected_by = $this->karyawan;
                
                // $header->file_qr = null;
                $header->save();

                $data_order = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', true)->update([
                    'status' => 2,
                    'is_approve' => 0,
                    'rejected_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'rejected_by' => $this->karyawan
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Reject no sampel '.$request->no_sampel.' berhasil!'
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
            $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            if($header != null && $header->file_lhp == null) {
                $detail = LhpsLingDetail::where('id_header', $header->id)->get();
                $custom = LhpsLingCustom::where('id_header', $header->id)->get();

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

                $data = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
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
        $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
        $header->count_print = $header->count_print + 1; 

        $detail = LhpsLingDetail::where('id_header', $header->id)->get();
        $custom = LhpsLingCustom::where('id_header', $header->id)->get();

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
                ->whereView('DraftUdaraAmbient')
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