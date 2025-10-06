<?php

namespace App\Http\Controllers\api;



use App\Http\Controllers\Controller; 
use App\Models\HistoryAppReject;
use App\Models\LhpUdaraPsikologiDetail;
use App\Models\LhpUdaraPsikologiDetailHistory;
use App\Models\LhpUdaraPsikologiHeader;
use App\Models\LhpUdaraPsikologiHeaderHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;


use App\Models\OrderDetail;


class LhpUdaraPsikologiController extends Controller
{


	    public function index(Request $request)
    {
        DB::statement("SET SESSION sql_mode = ''");
        $data = OrderDetail::with([
			'dataPsikologi',
            'lhp_psikologi',
        	])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
            ->where('is_active', true)
			->whereJsonContains('parameter', [
				"318;Psikologi"
			])
            ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
			->whereNotNull('tanggal_terima')
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', ["118-Psikologi", "27-Udara Lingkungan Kerja"])
            ->groupBy('cfr')
			->where('status', 3)
            ->get();

        return Datatables::of($data)->make(true);
    }



	  public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $lhps = LhpUdaraPsikologiHeader::where('id', $request->id)
                ->where('is_active', true)
                ->first();

            if ($lhps) {
                HistoryAppReject::insert([
                    'no_lhp' => $lhps->no_lhp,
                    'no_sampel' => $request->noSampel,
                    'kategori_2' => $lhps->id_kategori_2,
                    'kategori_3' => $lhps->id_kategori_3,
                    'menu' => 'LHP Udara',
                    'status' => 'rejected',
                    'rejected_at' => Carbon::now(),
                    'rejected_by' => $this->karyawan
                ]);
                // History Header Kebisingan
                $lhpsHistory = $lhps->replicate();
                $lhpsHistory->setTable((new LhpUdaraPsikologiHeaderHistory())->getTable());
                $lhpsHistory->created_at = $lhps->created_at;
                $lhpsHistory->updated_at = $lhps->updated_at;
                $lhpsHistory->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $lhpsHistory->deleted_by = $this->karyawan;
                $lhpsHistory->save();

                // History Detail Kebisingan
                $oldDetails = LhpUdaraPsikologiDetail::where('id_header', $lhps->id)->get();
                foreach ($oldDetails as $detail) {
                    $detailHistory = $detail->replicate();
                    $detailHistory->setTable((new LhpUdaraPsikologiDetailHistory())->getTable());
                    $detailHistory->created_by = $this->karyawan;
                    $detailHistory->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $detailHistory->save();
                }
            }

				OrderDetail::where('cfr', $request->no_lhp)
                    ->where('status', 3)
                    ->update([
                        'status' => 2
                    ]);
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data LHP Psikologi no LHP ' . $request->no_lhp . ' berhasil direject'
            ], 201);

        } catch (\Exception $th) {
            DB::rollBack();
            // dd($th);
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage() . ' On line ' . $th->getLine() . ' On File ' . $th->getFile()
            ], 401);
        }
    }

}