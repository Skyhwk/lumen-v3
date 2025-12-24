<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use App\Models\LhpsGetaranCustom;
use App\Services\LhpTemplate;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

use Yajra\Datatables\Datatables;

use App\Services\PrintLhp;

use App\Models\OrderDetail;
use App\Models\LhpsGetaranHeader;
use App\Models\LhpsGetaranDetail;

class LhpUdaraGetaranController extends Controller
{
    public function index()
    {
        $data = OrderDetail::select('nama_perusahaan', 'no_order', 'cfr', DB::raw("GROUP_CONCAT(no_sampel SEPARATOR ', ') as no_sampel"), 'kategori_3', 'tanggal_sampling', 'tanggal_terima', 'parameter')
            ->with('lhps_getaran', 'orderHeader:no_document', 'dataLapanganGetaran')
            ->where('is_approve', true)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', [
                '13-Getaran',
                '19-Getaran (Mesin)',
                '15-Getaran (Kejut Bangunan)',
                '14-Getaran (Bangunan)',
                '18-Getaran (Lingkungan)'
            ])
            ->where('status', 3)
            ->groupBy('nama_perusahaan', 'no_order', 'cfr', 'kategori_3', 'tanggal_sampling', 'tanggal_terima', 'parameter')
            ->orderBy('tanggal_terima', 'desc');

        return Datatables::of($data)->make(true);
    }

   public function handleReject(Request $request) {
        DB::beginTransaction();
        try {
            $header = LhpsGetaranHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();
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


    public function handleDownload(Request $request)
    {
        try {
            $lhpsGetaranHeader = LhpsGetaranHeader::where('no_lhp', $request->no_lhp)
                ->where('is_active', true)
                ->first();

            if (!$lhpsGetaranHeader || !$lhpsGetaranHeader->file_lhp) {
                return response()->json(['message' => 'File ' . $request->no_lhp . ' tidak ditemukan!']);
            }

            $fileName = $lhpsGetaranHeader->file_lhp;

            return response()->json([
                'file_name' =>  env('APP_URL') . '/public/dokumen/LHP/' . $fileName,
                'message' => 'Download file ' . $request->no_lhp . ' berhasil!'
            ]);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error download file ' . $th->getMessage()], 401);
        }
    }

    public function rePrint(Request $request)
    {
        try {
        DB::beginTransaction();
        $lhpsGetaranHeader = LhpsGetaranHeader::where('no_lhp', $request->no_lhp)
            ->where('is_active', true)
            ->first();
        $renderDetail = LhpsGetaranDetail::where('id_header', $lhpsGetaranHeader->id)->get();
        $renderDetail = collect($renderDetail)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc']
            ])->values()->toArray();

        $groupedByPage = collect(LhpsGetaranCustom::where('id_header', $lhpsGetaranHeader->id)->get())
            ->groupBy('page')
            ->toArray();

        foreach ($groupedByPage as $idx => $cstm) {
            $groupedByPage[$idx] = collect($cstm)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc']
            ])->values()->toArray();
        }

    
          LhpTemplate::setDataDetail($renderDetail)
                        ->setDataHeader($lhpsGetaranHeader)
                        ->useLampiran(true)
                        ->setDataCustom($groupedByPage)
                        ->whereView('DraftGetaran')
                        ->render();

        $lhpsGetaranHeader->count_print = $lhpsGetaranHeader->count_print + 1;
        $lhpsGetaranHeader->save();

        $detail = LhpsGetaranDetail::where('id_header', $lhpsGetaranHeader->id)->get();

        $servicePrint = new PrintLhp();
        $servicePrint->printByFilename($lhpsGetaranHeader->file_lhp, $detail, 'KPGI', $lhpsGetaranHeader->no_lhp);

        if (!$servicePrint) return response()->json(['message' => 'Gagal Reprint LHP'], 401);

        DB::commit();

        return response()->json([
            'message' => 'Reprint LHP ' . $request->cfr . ' berhasil!'
        ], 200);
        } catch (\Exception $th) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Terjadi kesalahan ' . $th->getMessage(),
                    'line' => $th->getLine(),
                    'file' => $th->getFile()
                    ], 401);
        }
      
    }
}
