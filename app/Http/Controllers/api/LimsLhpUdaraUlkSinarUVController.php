<?php

namespace App\Http\Controllers\api;

use App\Models\lhpsSinarUVCustom;
use App\Models\LhpsSinarUVDetail;
use App\Models\LhpsSinarUVHeader;
use App\Models\Lims\OrderDetail;

use App\Services\LhpTemplate;
use App\Services\PrintLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class LimsLhpUdaraUlkSinarUVController extends Controller
{
    public function index(Request $request)
    {
        DB::connection('lims')->statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
            'lhps_sinaruv',
            'orderHeader' => function ($query) {
                    $query->select('id', 'nama_pic_order', 'jabatan_pic_order', 'no_pic_order', 'email_pic_order', 'alamat_sampling');
                }
            ])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_approve', 1)
            ->where('is_active', true)
            ->where('kategori_2', '4-Udara')
            ->where('kategori_3', "27-Udara Lingkungan Kerja")
            ->whereJsonContains('parameter', '324;Sinar UV')
            ->groupBy('cfr')
            ->where('status', 3);

        if ($request->has('month_year') && !empty($request->month_year)) {
            $parts = explode('-', $request->month_year);
            if (count($parts) === 2) {
                $year = $parts[0];
                $month = $parts[1];
                $matchingIds = \App\Models\LhpsSinarUVHeader::whereYear('tanggal_lhp', $year)
                    ->whereMonth('tanggal_lhp', $month)
                    ->where('is_active', true)
                    ->pluck('no_lhp');
                $data->whereIn('cfr', $matchingIds);
            }
        }

        $data = $data->get();

        return Datatables::of($data)->make(true);
    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsSinarUVHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();

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
            $header = LhpsSinarUVHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();

            $fileName = $header->file_lhp;

            return response()->json([
                'file_name' => env('APP_URL') . '/public/dokumen/LHP/' . $fileName,
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
        $header = LhpsSinarUVHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
        $header->count_print = $header->count_print + 1;
        $header->save();

        $detail = LhpsSinarUVDetail::where('id_header', $header->id)->get();
        $groupedByPage = collect(lhpsSinarUVCustom::where('id_header', $header->id)->get())
                ->groupBy('page')
                ->toArray();

        LhpTemplate::setDataDetail(LhpsSinarUVDetail::where('id_header', $header->id)->get())
                ->setDataHeader($header)
                ->setDataCustom($groupedByPage)
                ->whereView('DraftUlkSinarUv')
                ->render();
        $servicePrint = new PrintLhp();
        $servicePrint->printByFilename($header->file_lhp, $detail);

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
            $header = LhpsSinarUvHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            if (!$header) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            $detail = LhpsSinarUVDetail::where('id_header', $header->id)->get();
            $custom = \App\Models\lhpsSinarUVCustom::where('id_header', $header->id)->get();

            $groupedByPage = [];
            if (!empty($custom)) {
                foreach ($custom->toArray() as $cItem) {
                    $page = $cItem['page'];
                    if (!isset($groupedByPage[$page])) {
                        $groupedByPage[$page] = [];
                    }
                    $groupedByPage[$page][] = $cItem;
                }
            }

            $fileName = \App\Services\LhpTemplate::setDataDetail($detail)
                ->setDataHeader($header)
                ->setDataCustom($groupedByPage)
                ->whereView('DraftUlkSinarUv')
                ->render('downloadLHPFinal');

            // Find file
            $filePath = base_path('public/dokumen/LHP_DOWNLOAD/' . $fileName);
            if (!file_exists($filePath)) {
                $filePath = base_path('public/dokumen/LHP/' . $fileName);
            }

            if (file_exists($filePath)) {
                $base64 = base64_encode(file_get_contents($filePath));
                return response()->json(['data' => $base64]);
            }

            return response()->json(['message' => 'Gagal merender PDF'], 404);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

}