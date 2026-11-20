<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Services\Printing;
use App\Models\Printers;

class PrintingController extends Controller
{
    public function index(Request $request)
    {

        $printerSelect = [
            'id',
            'full_path as description',
            'full_path as destination',
            'is_default as state',
            'server',
            'share_name',
            'status',
            DB::raw("COALESCE(NULLIF(TRIM(status), ''), 'Tidak diketahui') as computer_state"),
        ];

        if ($this->department != 7) {
            $data = Printers::select($printerSelect)
                ->where('id_divisi', $this->department)
                ->get();
        } else {
            $data = Printers::select($printerSelect)->get();
        }
        return response()->json(['data' => $data], 200);
    }

    public function print(Request $request)
    {
        try {
            $cek_printer = Printers::where('id', $request->printer)->first();

            $response = Printing::where('pdf', env('APP_URL').'/public/'.$request->filename)
            ->where('printer', $cek_printer->full_path)
            ->where('karyawan', $this->karyawan)
            ->where('filename', $request->filename)
            ->where('printer_name', $request->printer_name)
            ->where('destination', $request->destination)
            ->where('pages', $request->pages)
            ->where('copies', intval($request->copies))
            ->print();

            return response()->json(['message' => 'Printing Success!'],200);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 401);
        }
    
    }
}