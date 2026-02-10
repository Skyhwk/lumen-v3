<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LhpsMedanLMDetail;
use App\Models\LhpsMedanLMHeader;
use App\Models\OrderDetail;
use App\Services\LhpTemplate;
use App\Services\PrintLhp;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class LhpGelombangMikroController extends Controller
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
                'lhps_medanlm',
                'orderHeader',
            ])
            ->where('is_active', true)
            ->where('kategori_3', '27-Udara Lingkungan Kerja')
            ->where('status', 3)
            ->where(function ($q) {
                $q->whereJsonContains('parameter', "563;Medan Magnet")
                    ->orWhereJsonContains('parameter', "316;Power Density")
                    ->orWhereJsonContains('parameter', "277;Medan Listrik")
                    ->orWhereJsonContains('parameter', "236;Gelombang Elektro");
            })
            ->groupBy('cfr')
            ->get();

        return Datatables::of($data)->make(true);
    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsMedanLMHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
            if ($header != null) {

                $header->is_approve  = 0;
                $header->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->rejected_by = $this->karyawan;

                // $header->file_qr = null;
                $header->save();

                OrderDetail::where('cfr', $request->cfr)->where('is_active', true)->update([
                    'status'      => 2,
                    'is_approve'  => 0,
                    'rejected_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'rejected_by' => $this->karyawan,
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Reject no sampel ' . $request->cfr . ' berhasil!',
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
            $header = LhpsMedanLMHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();

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
            $header              = LhpsMedanLMHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
            $header->count_print = $header->count_print + 1;
            $header->save();
            $detail = LhpsMedanLMDetail::where('id_header', $dataHeader->id)->where('page', 1)->get();
            $custom = collect(LhpsMedanLMDetail::where('id_header', $dataHeader->id)->where('page', '!=', 1)->get())->groupBy('page')->toArray();

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

            $fileName = LhpTemplate::setDataDetail($detail)
                ->setDataHeader($header)
                ->setDataCustom($custom)
                ->useLampiran(true)
                ->whereView('DraftGelombangMikro')
                ->render('downloadLHPFinal');

            $header->file_lhp = $fileName;
            $header->save();

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
