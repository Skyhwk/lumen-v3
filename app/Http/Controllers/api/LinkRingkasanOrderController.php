<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;

Carbon::setLocale('id');

class LinkRingkasanOrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = DB::table('link_ringkasan_order')
                ->select('id', 'no_order', 'no_quotation', 'nama_perusahaan', 'link', 'created_at')
                ->get();

            return DataTables::of($data)->make(true);
        } catch (\Throwable $th) {
            dd($th);
        }
    }
}