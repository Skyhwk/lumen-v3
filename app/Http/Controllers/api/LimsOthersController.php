<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Subkontrak;
use App\Models\WsValueAir;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class LimsOthersController extends Controller
{
    

    // 20-03-2025
    public function index(Request $request)
    {
        $limsDb = DB::connection('lims')->getDatabaseName();

        $data = Subkontrak::with('ws_value')
            ->join(DB::raw("{$limsDb}.order_detail as order_detail"),'order_detail.no_sampel','=','subkontrak.no_sampel')
            ->where('subkontrak.is_approve', $request->approve)
            ->where('subkontrak.is_active', true)
            ->where('subkontrak.is_total', false)
            ->select('subkontrak.*', 'order_detail.tanggal_terima')
            ->orderByRaw("
                    CASE
                        WHEN order_detail.tanggal_terima IS NULL THEN 1
                        ELSE 0
                    END,
                    order_detail.tanggal_terima DESC
            ");
        
        if ($request->filled('periode')) {
            $periode = explode('-', $request->periode);
            if (count($periode) == 2) {
                $data->whereYear('subkontrak.created_at', $periode[0])
                     ->whereMonth('subkontrak.created_at', $periode[1]);
            }
        }
        return Datatables::of($data)
            ->addColumn('tanggal_terima', function ($item) {
                return $item->order_detail->tanggal_terima ?? '-';
            })

            ->addColumn('kategori_3', function ($item) {
                return $item->order_detail->kategori_3 ?? '-';
            })

            ->addColumn('tanggal_sampling', function ($item) {
                return $item->order_detail->tanggal_sampling ?? '-';
            })

            ->filterColumn('tanggal_terima', function ($query, $keyword) {
                $query->where('order_detail.tanggal_terima', 'like', "%{$keyword}%");
            })

            ->filterColumn('kategori_3', function ($query, $keyword) {
                $query->where('order_detail.kategori_3', 'like', "%{$keyword}%");
            })

            ->filter(function ($query) use ($request) {

                if ($request->has('columns')) {
                    $columns = $request->get('columns');

                    foreach ($columns as $column) {

                        if (! empty($column['search']['value'])) {

                            $columnName  = $column['name'] ?: $column['data'];
                            $searchValue = $column['search']['value'];

                            // HANYA BOLEH FILTER KOLOM colorimetri
                            if (in_array($columnName, [
                                'parameter',
                                'jenis_pengujian',
                                'created_at',
                            ])) {
                                $query->where("subkontrak.$columnName", 'like', "%{$searchValue}%");
                            }

                        }
                    }
                }
            })
            ->make(true);
    }

    public function deleteData(Request $request)
    {
        DB::beginTransaction();
        try {
            $data                      = Subkontrak::where('id', $request->id)->first();
            $data->is_active           = false;
            $data->deleted_at          = Carbon::now()->format('Y-m-d H:i:s');
            $data->deleted_by          = $this->karyawan;
            $data->is_retest           = 1;
            $data->notes_reject_retest = $request->note;
            $data->save();

            $ws_value = WsValueAir::where('id_colorimetri', $request->id)->where('is_active', true)->first();
            if ($ws_value) {
                $ws_value->is_active = false;
                $ws_value->save();
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                "success" => true,
                'message' => 'Data colorimetri no sample ' . $data->no_sampel . ' berhasil dihapus .!',
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan! ' . $th->getMessage(),
            ], 401);
        }
    }
}
