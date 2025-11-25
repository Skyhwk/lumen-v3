<?php

namespace App\Http\Controllers\api;

use App\Models\LhpsKebisinganPersonalHeader;
use App\Models\LhpsKebisinganPersonalDetail;
use App\Models\LhpsKebisinganPersonalCustom;
use App\Models\OrderDetail;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use App\Services\PrintLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MasterRegulasi;
use Carbon\Carbon;
use Exception;
use Yajra\Datatables\Datatables;

class LhpKebisinganPersonalController extends Controller
{
    public function index(Request $request){
        DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
            'lhps_kebisingan',
            'orderHeader' => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel, GROUP_CONCAT(regulasi SEPARATOR "||") as regulasi_all')
            ->where('is_approve', 1)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', ["23-Kebisingan", '24-Kebisingan (24 Jam)', '25-Kebisingan (Indoor)', '26-Kualitas Udara Dalam Ruang'])
            ->whereJsonContains('parameter', '271;Kebisingan (P8J)')
            ->where('status', 3)
            ->groupBy('cfr')
            ->get();

        return Datatables::of($data)->make(true);
    }

    public function handleReject(Request $request) {
        DB::beginTransaction();
        try {
            $header = LhpsKebisinganPersonalHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
            if($header != null) {

                $header->is_approve = 0;
                $header->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->rejected_by = $this->karyawan;
                
                // $header->file_qr = null;
                $header->save();

                OrderDetail::where('cfr', $request->cfr)->where('is_active', true)->update([
                    'status' => 2,
                    'is_approve' => 0,
                    'rejected_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'rejected_by' => $this->karyawan
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Reject no sampel '.$request->cfr.' berhasil!'
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
            $header = LhpsKebisinganPersonalHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
            
                $fileName = $header->file_lhp;

            return response()->json([
                'file_name' =>  env('APP_URL') . '/public/dokumen/LHP/' . $fileName,
                'message' => 'Download file '.$request->cfr.' berhasil!'
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
        try {
            $header = LhpsKebisinganPersonalHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
            $header->count_print = $header->count_print + 1; 
            $header->save();
            $detail = LhpsKebisinganPersonalDetail::where('id_header', $header->id)->get();

            $detail = collect($detail)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc']
            ])->values()->toArray();
            $custom = collect(LhpsKebisinganPersonalCustom::where('id_header', $header->id)->get())
                ->groupBy('page')
                ->toArray();

            foreach ($custom as $idx => $cstm) {
                $custom[$idx] = collect($cstm)->sortBy([
                    ['tanggal_sampling', 'asc'],
                    ['no_sampel', 'asc']
                ])->values()->toArray();
            }

            $id_regulasii = explode('-', (json_decode($header->regulasi)[0]))[0];
            if (in_array($id_regulasii, [54, 151, 167, 168, 382])) {

                $master_regulasi = MasterRegulasi::find($id_regulasii);
                if ($master_regulasi->deskripsi == 'Kebisingan Lingkungan' || $master_regulasi->deskripsi == 'Kebisingan LH') {
                    $fileName = LhpTemplate::setDataDetail($detail)
                        ->setDataHeader($header)
                        ->setDataCustom($custom)
                        ->useLampiran(true)
                        ->whereView('DraftKebisinganLh')
                        ->render();
                } else if ($master_regulasi->deskripsi == 'Kebisingan LH - 24 Jam') {
                    $fileName = LhpTemplate::setDataDetail($detail)
                        ->setDataHeader($header)
                        ->setDataCustom($custom)
                        ->useLampiran(true)
                        ->whereView('DraftKebisinganLh24Jam')
                        ->render();
                }
            } else {
                $fileName = LhpTemplate::setDataDetail($detail)
                    ->setDataHeader($header)
                    ->setDataCustom($custom)
                    ->useLampiran(true)
                    ->whereView('DraftKebisingan')
                    ->render();
            }


            $header->file_lhp = $fileName;
            $header->save();

            $servicePrint = new PrintLhp();
            $servicePrint->printByFilename($header->file_lhp, $detail, 'KPGI', $header->no_lhp);
            if (!$servicePrint) {
                DB::rollBack();
                return response()->json(['message' => 'Gagal Melakukan Reprint Data', 'status' => '401'], 401);
            }
            
            DB::commit();

            return response()->json([
                'message' => 'Berhasil Melakukan Reprint Data ' . $request->cfr . ' berhasil!'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error Reprint Data ' . $th->getMessage(),
            ], 401);
        }
    }
}