<?php
namespace App\Http\Controllers\api;
use App\Models\{
    NeedContacted
};

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
class NeedContactedController extends Controller
{
    public function index(Request $request)
    {
        $data = NeedContacted::all();
        return dataTables()->of($data)->make(true);
    }

    public function submit(Request $request){
        DB::beginTransaction();
        try {
            $data = new NeedContacted();
            $data->no_hp = $request->phone;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();
            DB::commit();
            return response()->json([
                'data' => $data,
                'message' => 'Data Berhasil disimpan',
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 400);
        }
    }

}