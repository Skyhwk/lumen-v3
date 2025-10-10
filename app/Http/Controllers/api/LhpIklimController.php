<?php

namespace App\Http\Controllers\api;



use App\Models\{
    LhpsIklimCustom, 
    LhpsIklimDetail,
    LhpsIklimHeader, 
    OrderDetail
};

use App\Services\{
    GenerateQrDocumentLhp,
    LhpTemplate,
    PrintLhp
};


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

use Yajra\Datatables\Datatables;

class LhpIklimController extends Controller
{
  public function index(Request $request)
    {
        DB::statement("SET SESSION sql_mode = ''");

        $data = OrderDetail::with([
            'lhps_iklim',
            'orderHeader' => function ($query) {
                $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
            }
        ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel, GROUP_CONCAT(regulasi SEPARATOR "||") as regulasi_all')
            ->where('is_approve', 1)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->where('kategori_3', "21-Iklim Kerja")
            ->where('status', 3)
            ->groupBy('cfr')
            ->get();

        // Bersihin regulasi duplikat berdasarkan ID
        foreach ($data as $item) {
            $regsRaw = explode("||", $item->regulasi_all ?? '');
            $allRegs = [];

            foreach ($regsRaw as $reg) {
                if (empty($reg)) continue;

                // Decode JSON array misal: ["127-Peraturan...", "213-Peraturan..."]
                $decoded = json_decode($reg, true);

                if (is_array($decoded)) {
                    foreach ($decoded as $r) {
                        $allRegs[] = $r;
                    }
                }
            }

            // Hilangin duplikat berdasarkan ID
            $unique = [];
            foreach ($allRegs as $r) {
                [$id, $text] = explode("-", $r, 2);
                $unique[$id] = $r;
            }

            $item->regulasi_all = array_values($unique); // hasil array unik, rapi
        }


        return Datatables::of($data)->make(true);
    }

    public function handleReject(Request $request) {
        DB::beginTransaction();
        try {
            $header = LhpsIklimHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();
         

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
            $header = LhpsIklimHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();
            $parameter = explode(';', json_decode($request->parameter)[0])[1];
            if($header != null && $header->file_lhp == null) {
                $detail = LhpsIklimDetail::where('id_header', $header->id)->get();
                 $custom = collect(LhpsIklimCustom::where('id_header', $header->id)->get())
                ->groupBy('page')
                ->toArray();

                $fileName = '';
                if($header->file_qr == null) {
                    $header->file_qr = 'LHP-'.str_ireplace("/", "_",$header->no_lhp);
                    $header->save();
                    GenerateQrDocumentLhp::insert('LHP', $header, $this->karyawan);
                }

               

               if($parameter == 'ISBB' || $parameter == 'ISBB (8 Jam)'){
                    $fileName = LhpTemplate::setDataDetail($detail)
                        ->setDataHeader($header)
                        ->useLampiran(true)
                        ->setDataCustom($custom)
                        ->whereView('DraftIklimPanas')
                        ->render();
                } else {
                    $fileName = LhpTemplate::setDataDetail($detail)
                        ->setDataHeader($header)
                        ->useLampiran(true)
                        ->setDataCustom($custom)
                        ->whereView('DraftIklimDingin')
                        ->render();
                }

                $data = LhpsIklimHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();
                $data->file_lhp = $fileName;
                $data->save();

            } else if($header != null && $header->file_lhp != null) {
                $fileName = $header->file_lhp;
            }

            return response()->json([
                'file_name' =>  env('APP_URL') . '/public/dokumen/LHP/' . $fileName,
                // 'file_name' =>  'http://localhost/v3' . '/public/dokumen/LHP/' . $fileName,
                'message' => 'Download file '.$request->no_lhp.' berhasil!'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error download file '.$th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 401);
        }
        
    }

    public function rePrint(Request $request) 
    {
        DB::beginTransaction();
        try {
            $header = LhpsIklimHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();
                $header->count_print = $header->count_print + 1; 
                $header->save();
                $parameter = explode(';', json_decode($request->parameter)[0])[1];
                $detail = LhpsIklimDetail::where('id_header', $header->id)->get();
                $custom = collect(LhpsIklimCustom::where('id_header', $header->id)->get())
                ->groupBy('page')
                ->toArray();
            foreach ($custom as $idx => $cstm) {
                $custom[$idx] = collect($cstm)->sortBy([
                    ['tanggal_sampling', 'asc'],
                    ['no_sampel', 'asc']
                ])->values()->toArray();
            }
                if($parameter == 'ISBB' || $parameter == 'ISBB (8 Jam)'){
                  LhpTemplate::setDataDetail($detail)
                        ->setDataHeader($header)
                        ->useLampiran(true)
                        ->setDataCustom($custom)
                        ->whereView('DraftIklimPanas')
                        ->render();
                } else {
                    LhpTemplate::setDataDetail($detail)
                        ->setDataHeader($header)
                        ->useLampiran(true)
                        ->setDataCustom($custom)
                        ->whereView('DraftIklimDingin')
                        ->render();
                }
                $servicePrint = new PrintLhp();
                 $servicePrint->printByFilename($header->file_lhp, $detail);
                
                if (!$servicePrint) {
                    DB::rollBack();
                    return response()->json(['message' => 'Gagal Melakukan Reprint Data', 'status' => '401'], 401);
                }
                
                DB::commit();

                return response()->json([
                    'message' => 'Berhasil Melakukan Reprint Data ' . $request->lhp . ' berhasil!'
                ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ]);
        }
    }
}