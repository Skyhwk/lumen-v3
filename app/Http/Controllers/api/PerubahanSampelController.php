<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PerubahanSampel;
use App\Models\OrderDetail;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class PerubahanSampelController extends Controller
{
    public function index(Request $request)
    {
        $data = PerubahanSampel::with('orderH')->where('periode', $request->periode);

        return DataTables::of($data)
            ->filterColumn('no_order', function ($query, $keyword) {
                $query->where('no_order', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_document', function ($query, $keyword) {
                $query->whereHas('orderH', function ($q) use ($keyword) {
                    $q->where('no_document', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('periode', function ($query, $keyword) {
                $query->where('periode', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('perubahan', function ($query, $keyword) {
                $query->where('perubahan', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }
}
