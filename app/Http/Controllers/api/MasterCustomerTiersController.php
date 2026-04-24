<?php

use App\Models\MasterCustomerTiers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
class MasterCustomerTiersController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterCustomerTiers::get();

        return Datatables::of($data)
            ->make(true);
    }

    public function store(Request $request)
    {
        if($request->id){
            $data = MasterCustomerTiers::find($request->id);
        }else{
            $data = new MasterCustomerTiers();
        }

        $data->name = $request->name;
        $data->save();

        return response()->json([
            'message' => 'Data berhasil disimpan',
            'status' => 200
        ]);
    }
}