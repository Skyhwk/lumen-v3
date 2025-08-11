<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HistoriPrinting;
use Illuminate\Support\Facades\DB;
use DataTables;

class HistoriPrintingController extends Controller
{
    public function index(Request $request)
    {
        $data = HistoriPrinting::orderBy('created_at', 'desc');
        return DataTables::of($data)->make(true);
    }
}