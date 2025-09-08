<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ImportDataCustomer;

class ImportDataCustomerController extends Controller
{
    public function index(Request $request)
    {
        $importDataCustomer = ImportDataCustomer::select('filename', 'created_by', 'created_at', 'is_generated')
        ->groupBy('filename', 'created_by', 'created_at', 'is_generated')
        ->get();

        return response()->json(['data' => $importDataCustomer], 200);
    }

    public function showDetail(Request $request){
        $data = ImportDataCustomer::where('filename', $request->filename)
        ->get();

        return response()->json(['data' => $data], 200);
    }

    public function create(Request $request){
        dd($request->all());
    }
}