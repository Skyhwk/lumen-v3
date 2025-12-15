<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LhpsSwabTesDetail;
use App\Models\LhpsSwabTesHeader;
use App\Models\OrderDetail;
use App\Services\LhpTemplate;
use App\Services\PrintLhp;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class LhpSwabTesController extends Controller
{
    public function index(Request $request)
    {
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
            ->with([
                'lhps_swab_udara',
                'orderHeader',
            ])
            ->where('is_active', true)
            ->where('kategori_3', '46-Udara Swab Test')
            ->where('status', 3)
            ->groupBy('cfr')
            ->get();

        return Datatables::of($data)->make(true);
    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsSwabTesHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
            if ($header) {
                $header->is_approve  = 0;
                $header->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->rejected_by = $this->karyawan;
                $header->save();

                OrderDetail::where('cfr', $request->cfr)->where('is_active', true)->update([
                    'status'      => 2,
                    'is_approve'  => 0,
                    'rejected_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'rejected_by' => $this->karyawan,
                ]);
            } else {
                return response()->json([
                    'message' => 'Data no lhp ' . $request->cfr . ' tidak ditemukan!',
                ], 401);
            }

            DB::commit();
            return response()->json([
                'message' => 'Reject no lhp ' . $request->cfr . ' berhasil!',
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
            $header = LhpsSwabTesHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();

            $fileName = $header->file_lhp;

            return response()->json([
                'file_name' => env('APP_URL') . '/public/dokumen/LHP/' . $fileName,
                'message'   => 'Download file ' . $request->cfr . ' berhasil!',
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
        try {
            $header              = LhpsSwabTesHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
            $header->count_print = $header->count_print + 1;
            $detail              = LhpsSwabTesDetail::where('id_header', $header->id)->get();

            $detail = collect($detail)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc'],
            ])->values()->toArray();
            // $custom = collect(LhpsKebisinganCustom::where('id_header', $header->id)->get())
            //     ->groupBy('page')
            //     ->toArray();

            // foreach ($custom as $idx => $cstm) {
            //     $custom[$idx] = collect($cstm)->sortBy([
            //         ['tanggal_sampling', 'asc'],
            //         ['no_sampel', 'asc']
            //     ])->values()->toArray();
            // }

            $detailCollection       = LhpsSwabTesDetail::where('id_header', $header->id)->where('page', 1)->get();
            $detailCollectionCustom = collect(LhpsSwabTesDetail::where('id_header', $header->id)->where('page', '!=', 1)->get())->groupBy('page')->toArray();

            $validasi        = LhpsSwabTesDetail::where('id_header', $header->id)->get();
            $groupedBySampel = $validasi->groupBy('no_sampel');
            $totalSampel     = $groupedBySampel->count();
            $parameters      = $validasi->pluck('parameter')->filter()->unique();
            $totalParam      = $parameters->count();

            $isSingleSampelMultiParam = $totalSampel === 1 && $totalParam > 2;
            $isMultiSampelOneParam    = $totalSampel >= 1 && $totalParam === 1;

            if ($isSingleSampelMultiParam) {
                $fileName = LhpTemplate::setDataDetail($detailCollection)
                    ->setDataHeader($header)
                    ->setDataCustom($detailCollectionCustom)
                    ->useLampiran(true)
                    ->whereView('DraftSwab3Param')
                    ->render('downloadLHPFinal');
                $header->file_lhp = $fileName;
                $header->save();
            } else if ($isMultiSampelOneParam) {
                $fileName = LhpTemplate::setDataDetail($detailCollection)
                    ->setDataHeader($header)
                    ->setDataCustom($detailCollectionCustom)
                    ->useLampiran(true)
                    ->whereView('DraftSwab1Param')
                    ->render('downloadLHPFinal');
                $header->file_lhp = $fileName;
                $header->save();
            } else {
                $fileName = LhpTemplate::setDataDetail($detailCollection)
                    ->setDataHeader($header)
                    ->setDataCustom($detailCollectionCustom)
                    ->useLampiran(true)
                    ->whereView('DraftSwab2Param')
                    ->render('downloadLHPFinal');
                $header->file_lhp = $fileName;
                $header->save();
            }
            
            $servicePrint = new PrintLhp();
            $servicePrint->printByFilename($header->file_lhp, $detail, '', $header->no_lhp);
            if (! $servicePrint) {
                DB::rollBack();
                return response()->json(['message' => 'Gagal Melakukan Reprint Data', 'status' => '401'], 401);
            }

            DB::commit();

            return response()->json([
                'message' => 'Berhasil Melakukan Reprint Data ' . $request->cfr . ' berhasil!',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error Reprint Data ' . $th->getMessage(),
            ], 401);
        }
    }
}
