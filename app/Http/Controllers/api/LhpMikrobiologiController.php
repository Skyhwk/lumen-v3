<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LhpsMicrobiologiDetail;
use App\Models\LhpsMicrobiologiHeader;
use App\Models\OrderDetail;
use App\Models\ParameterFdl;
use App\Models\TabelRegulasi;
use App\Services\LhpTemplate;
use App\Services\PrintLhp;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class LhpMikrobiologiController extends Controller
{
    public function index(Request $request)
    {
        $parameterAllowed = ParameterFdl::where('nama_fdl', 'microbiologi')->first();
        $parameterAllowed = json_decode($parameterAllowed->parameters, true);

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
                'lhps_microbiologi',
                'orderHeader',
            ])
            ->where('is_active', true)
            ->whereIn('kategori_3', ["12-Udara Angka Kuman", '33-Mikrobiologi Udara', '27-Udara Lingkungan Kerja'])
            ->where('status', 3)
            ->where(function ($query) use ($parameterAllowed) {
                foreach ($parameterAllowed as $param) {
                    $query->orWhere('parameter', 'LIKE', "%;$param%");
                }
            })
            ->groupBy('cfr')
            ->get();
        return Datatables::of($data)->make(true);
    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsMicrobiologiHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
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
            $header = LhpsMicrobiologiHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();

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
            $header              = LhpsMicrobiologiHeader::where('no_lhp', $request->cfr)->where('is_active', true)->first();
            $header->count_print = $header->count_print + 1;
            $header->save();
            $detailCollection       = LhpsMicrobiologiDetail::where('id_header', $header->id)->where('page', 1)->get();
            $detailCollectionCustom = collect(LhpsMicrobiologiDetail::where('id_header', $header->id)->where('page', '!=', 1)->get())->groupBy('page')->toArray();

            $id_regulasi = [];

            $id_regulasi = [];

            foreach (json_decode($header->regulasi, true) as $reg) {
                $id_regulasi[] = explode('-', $reg)[0];
            }

            $tableRegulasi = TabelRegulasi::where(function ($q) use ($id_regulasi) {
                foreach ($id_regulasi as $item) {
                    $q->orWhereJsonContains('id_regulasi', $item);
                }
            })
                ->where('is_active', 1)
                ->get();

            $validasi   = LhpsMicrobiologiDetail::where('id_header', $header->id)->get();
            $parameters = $validasi->pluck('parameter')->filter()->unique();
            $totalParam = $parameters->count();

            $isUsingTable = ! $tableRegulasi->isEmpty();

            $singleParam = $totalParam == 1;
            $doubleParam = $totalParam == 2;

            if ($singleParam && $isUsingTable) {
                $fileName = LhpTemplate::setDataDetail($detailCollection)
                    ->setDataHeader($header)
                    ->setDataCustom($detailCollectionCustom)
                    ->useLampiran(true)
                    ->whereView('DraftMicrobio1ParamTable')
                    ->render('downloadLHPFinal');
            } else if ($doubleParam) {
                $fileName = LhpTemplate::setDataDetail($detailCollection)
                    ->setDataHeader($header)
                    ->setDataCustom($detailCollectionCustom)
                    ->useLampiran(true)
                    ->whereView('DraftMicrobio2Param')
                    ->render('downloadLHPFinal');
            } else {
                $fileName = LhpTemplate::setDataDetail($detailCollection)
                    ->setDataHeader($header)
                    ->setDataCustom($detailCollectionCustom)
                    ->useLampiran(true)
                    ->whereView('DraftMicrobio1ParamNoTable')
                    ->render('downloadLHPFinal');
            }

            $header->file_lhp = $fileName;
            $header->save();

            $servicePrint = new PrintLhp();
            $servicePrint->printByFilename($header->file_lhp, $detailCollection, 'non', $header->no_lhp);
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
