<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\DokumenBap;
use Yajra\Datatables\Datatables;

class GenerateBapController extends Controller
{
    public function index()
    {
        $dokumenBap = DokumenBap::with('order');

        return Datatables::of($dokumenBap)->make(true);
    }
}