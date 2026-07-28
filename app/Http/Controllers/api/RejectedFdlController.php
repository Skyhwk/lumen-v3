<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\RejectedFdl;
use Illuminate\Http\Request;
use Yajra\DataTables\Datatables;    

class RejectedFdlController extends Controller
{
    public function index(Request $request)
    {
        $data = RejectedFdl::with('order_detail')->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }
}