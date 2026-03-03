<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterPelanggan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Yajra\Datatables\Datatables;

class SearchDataCustomerController extends Controller
{


    public function index(Request $request)
    {
        $value = $request->searchValue;
        $type  = $request->searchType;

        $data = MasterPelanggan::with(['kontak_pelanggan', 'currentSales'])
            ->whereNotIn('sales_id', ['127'])
            ->where('is_active', true);

        if ($type == 'nama_lengkap') {
            $data = $data = $data->whereHas('currentSales', function ($q) use ($value) {
                $q->where('nama_lengkap', 'like', "%$value%");
            });
        } else if ($type == 'no_tlp_perusahaan') {
            $data = $data = $data->whereHas('kontak_pelanggan', function ($q) use ($value) {
                $q->where('no_tlp_perusahaan', 'like', "%$value%");
            });
        } else if ($type == 'nama_pelanggan') {
            $data = $data = $data->where('nama_pelanggan', 'like', "%$value%");
        }

        return Datatables::of($data)
            ->order(function ($query) {
                $query->orderBy('nama_pelanggan', 'asc');
            })
            ->make(true);

    }

}
