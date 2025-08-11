<?php

namespace App\Http\Controllers\api;

use App\Models\OrderHeader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class SalesCustomerByCategoryController extends Controller
{

	public function index(Request $request)
	{
		$data = OrderHeader::where('is_active', $request->is_active)->whereYear('tanggal_order', $request->tahun)->where('sub_kategori', $request->mode)->get();

		return DataTables::of($data)->make(true);
	}

	public function getCategory()
	{
		$data = OrderHeader::where('is_active', 1)
			->select('sub_kategori')
			->whereNotNull('sub_kategori')
			->distinct()
			->get();

		return response()->json(['data' => $data], 200);
	}
}
