<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\Printing;
use App\Models\Printers;

class PrintingController extends Controller
{
    public function index(Request $request)
    {
        if($this->department != 7){
            $data = Printers::select(
                'id',
                'full_path as description',
                'full_path as destination',
                'is_default as state',
                DB::raw("'Online' as computer_state")
            )->where('id_divisi', $this->department)->get();
        }else{
            $data = Printers::select(
                'id',
                'full_path as description',
                'full_path as destination',
                'is_default as state',
                DB::raw("'Online' as computer_state")
            )->get();
        }
        return response()->json(['data' => $data], 200);
    }

    public function print(Request $request)
    {
        $cek_printer = Printers::where('id', $request->printer)->first();

        $print = Printing::where('pdf', env('APP_URL').'/public/'.$request->filename)
        ->where('printer', $cek_printer->full_path)
        ->where('karyawan', $this->karyawan)
        ->where('filename', $request->filename)
        ->where('printer_name', $request->printer_name)
        ->where('destination', $request->destination)
        ->where('pages', $request->pages)
        ->print();

        return response()->json(['message' => 'Printing success'], 200);
    }
}