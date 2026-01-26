<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Datatables;

use App\Models\ForecastSP;
use Carbon\Carbon;

class DataForecastController extends Controller
{
    public function index(Request $request)
    {
        // Menggunakan query() agar efisien
        $data = ForecastSP::whereYear('tanggal_sampling_min', $request->year);

        // Paksa order ke kolom yang PASTI ADA, misalnya tanggal_sampling_min
        return Datatables::of($data)
            ->make(true);
    }
}
