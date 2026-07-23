<?php

namespace App\Http\Controllers\api;

// Models
use App\Models\LhpsEmisiIsokinetikCustom;
use App\Models\LhpsEmisiIsokinetikDetail;
use App\Models\LhpsEmisiIsokinetikHeader;
use App\Models\Lims\OrderDetail;
use App\Models\MetodeSampling;
use App\Models\MasterBakumutu;
use App\Models\Parameter;
use App\Models\GenerateLink;
// Services
use App\Services\TemplateLhps;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use App\Services\PrintLhp;
// Illuminate
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// Controller
use App\Http\Controllers\Controller;
// Others
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class LimsLhpEmisiIsokinetikController extends Controller
{
    public function index(Request $request)
    {
        DB::connection('lims')->statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
            'lhps_emisi_isokinetik',
            'orderHeader'
                => function ($query) {
                    $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
                }
            ])
            ->selectRaw('order_detail.*')
            ->where('is_approve', true)
            ->where('is_active', true)
            ->where('kategori_2', '5-Emisi')
            ->whereIn('kategori_3', [
                '34-Emisi Sumber Tidak Bergerak',
                '119-Emisi Isokinetik',
            ])
            ->where('parameter', 'like', '%Iso-%')
            ->where('status', 3);

        if ($request->has('month_year') && !empty($request->month_year)) {
            $parts = explode('-', $request->month_year);
            if (count($parts) === 2) {
                $year = $parts[0];
                $month = $parts[1];
                $matchingIds = \App\Models\LhpsEmisiIsokinetikHeader::whereYear('tanggal_lhp', $year)
                    ->whereMonth('tanggal_lhp', $month)
                    ->where('is_active', true)
                    ->pluck('no_lhp');
                $data->whereIn('cfr', $matchingIds);
            }
        }

        $data = $data->get();

        return Datatables::of($data)
            ->editColumn('lhps_emisi_isokonetik', function ($data) {
                if (is_null($data->lhps_emisi_isokonetik)) {
                    return null;
                } else {
                    $data->lhps_emisi_isokonetik->metode_sampling = $data->lhps_emisi_isokonetik->metode_sampling != null ? json_decode($data->lhps_emisi_isokonetik->metode_sampling) : null;
                    return json_decode($data->lhps_emisi_isokonetik, true);
                }
            })
            ->make(true);
    }



    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsEmisiIsokinetikHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
            if ($header != null) {

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
                'message' => 'Reject no LHP ' . $request->cfr . ' berhasil!'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan ' . $e->getMessage(),
            ], 401);
        }
    }

    public function handleDownload(Request $request)
    {
        try {
            $header = LhpsEmisiIsokinetikHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
            $fileName = $header->file_lhp;


            return response()->json([
                'file_name' => env('APP_URL') . '/public/dokumen/LHP_DOWNLOAD/' . $fileName,
                'message' => 'Download file ' . $request->cfr . ' berhasil!'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error download file ' . $th->getMessage(),
            ], 401);
        }

    }

    public function rePrint(Request $request)
    {
        DB::beginTransaction();
        $header = LhpsEmisiIsokinetikHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
        $header->count_print = $header->count_print + 1;
        $detail = LhpsEmisiIsokinetikDetail::where('id_header', $header->id)->get();
        $custom = LhpsEmisiIsokinetikCustom::where('id_header', $header->id)
            ->get()
            ->groupBy('page')
            ->toArray();
        $view = 'DraftESTBIsokinetik';

        $fileName = LhpTemplate::setDataHeader($header)
            ->setDataDetail($detail)
            ->setDataCustom($custom)
            ->whereView($view)
            ->render();

        $header->file_lhp = $fileName;
        $header->save();

        $servicePrint = new PrintLhp();
        $servicePrint->printByFilename($fileName, $detail);

        if (!$servicePrint) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal Melakukan Reprint Data', 'status' => '401'], 401);
        }

        DB::commit();

        return response()->json([
            'message' => 'Berhasil Melakukan Reprint Data ' . $request->cfr . ' berhasil!'
        ], 200);
    }

     public function previewLhp(Request $request)
    {
        try {
            $noLhp = $request->no_lhp ?? $request->cfr;
            if ($noLhp) {
                $header = LhpsEmisiIsokinetikHeader::where('no_lhp', $noLhp)->where('is_active', true)->first();
            } else {
                $header = LhpsEmisiIsokinetikHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            }

            if (!$header) {
                return response()->json(['message' => 'Header LHP tidak ditemukan'], 404);
            }

            if ($header->file_qr == null) {
                $file_qr = new \App\Services\GenerateQrDocumentLhp();
                $file_qr_path = $file_qr->insert('LHP_EMISI_ISOKINETIK', $header, $this->karyawan ?? 'System');
                if ($file_qr_path) {
                    $header->file_qr = $file_qr_path;
                    $header->save();
                }
            }

            $detail = LhpsEmisiIsokinetikDetail::where('id_header', $header->id)->get();
            $detail = collect($detail)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc']
            ])->values();

            $groupedByPage = collect(LhpsEmisiIsokinetikCustom::where('id_header', $header->id)->get())
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
                ->whereView('DraftESTBIsokinetik')
                ->render('downloadLHPFinal', 'S');

            return response()->json([
                'data' => base64_encode($pdfContent),
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