<?php

namespace App\Http\Controllers\api;

use App\Models\LhpsLingHeader;
use App\Models\LhpsLingDetail;
use App\Models\LhpsLingCustom;
use App\Models\Lims\OrderDetail;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use App\Services\PrintLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class LimsLhpUdaraAmbientController extends Controller
{
    public function index(Request $request){
        $data = OrderDetail::with('lhps_ling','orderHeader','dataLapanganLingkunganHidup')->where('is_approve', true)->where('is_active', true)->where('kategori_2', '4-Udara')->where('kategori_3', '11-Udara Ambient')->where('status', 3)->orderBy('tanggal_terima', 'desc');

        if ($request->has('month_year') && !empty($request->month_year)) {
            $parts = explode('-', $request->month_year);
            if (count($parts) === 2) {
                $year = $parts[0];
                $month = $parts[1];
                $matchingIds = \App\Models\LhpsLingHeader::whereYear('tanggal_lhp', $year)
                    ->whereMonth('tanggal_lhp', $month)
                    ->where('is_active', true)
                    ->pluck('no_lhp');
                $data->whereIn('cfr', $matchingIds);
            }
        }

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
            $noLhp = $request->no_lhp ?? $request->cfr;
            if ($noLhp) {
                $header = LhpsLingHeader::where('no_lhp', $noLhp)->where('is_active', true)->first();
            } else {
                $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            }

            if ($header && $header->file_lhp) {
                $filePath = public_path('dokumen/LHP_DOWNLOAD/' . $header->file_lhp);
                if (file_exists($filePath)) {
                    $pdfContent = file_get_contents($filePath);
                    return response()->json([
                        'data' => base64_encode($pdfContent),
                        'is_base64' => true,
                        'file_name' => $header->file_lhp,
                        'message' => 'Download file berhasil!'
                    ], 200);
                }
            }

            return $this->previewLhp($request);
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
                $file_qr_path = $file_qr->insert('LHP_LINGKUNGAN_HIDUP', $header, $this->karyawan);
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
        $servicePrint->printByFilename($header->file_lhp, $detail);
        
        if (!$servicePrint) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal Melakukan Reprint Data', 'status' => '401'], 401);
        }
        
        DB::commit();

        return response()->json([
            'message' => 'Berhasil Melakukan Reprint Data ' . $request->no_sampel . ' berhasil!'
        ], 200);
    }

     public function previewLhp(Request $request)
    {
        try {
            $noLhp = $request->no_lhp ?? $request->cfr;
            if ($noLhp) {
                $header = LhpsLingHeader::where('no_lhp', $noLhp)->where('is_active', true)->first();
            } else {
                $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            }

            if (!$header) {
                return response()->json(['message' => 'Header LHP tidak ditemukan'], 404);
            }

            if ($header->file_qr == null) {
                $file_qr = new \App\Services\GenerateQrDocumentLhp();
                $file_qr_path = $file_qr->insert('LHP_AMBIENT', $header, $this->karyawan ?? 'System');
                if ($file_qr_path) {
                    $header->file_qr = $file_qr_path;
                    $header->save();
                }
            }

            $detail = LhpsLingDetail::where('id_header', $header->id)->get();
            $detail = collect($detail)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc']
            ])->values()->toArray();

            $groupedByPage = collect(LhpsLingCustom::where('id_header', $header->id)->get())
                ->groupBy('page')
                ->toArray();

            foreach ($groupedByPage as $idx => $cstm) {
                $groupedByPage[$idx] = collect($cstm)->sortBy([
                    ['tanggal_sampling', 'asc'],
                    ['no_sampel', 'asc']
                ])->values()->toArray();
            }

            $pdfContent = LhpTemplate::setDataDetail($detail)
                ->setDataHeader($header)
                ->useLampiran(true)
                ->setDataCustom($groupedByPage)
                ->whereView('DraftUdaraAmbient')
                ->render('downloadLHPFinal', 'S');

            return response()->json([
                'data' => base64_encode($pdfContent),
                'is_base64' => true,
                'file_name' => $header->file_lhp ?? (str_replace("/", "_", $noLhp ?? $request->no_sampel) . '.pdf'),
                'message' => 'LHP berhasil dirender'
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Gagal merender LHP: ' . $th->getMessage(),
                'line' => $th->getLine()
            ], 500);
        }
    }
}