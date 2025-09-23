<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class MysqlController extends Controller
{
    public function processList()
    {
        $processes = DB::select("SHOW FULL PROCESSLIST");

        // Format hasilnya biar lebih rapi (array asosiatif)
        $data = array_map(function ($row) {
            return [
                'Id' => $row->Id,
                'User' => $row->User,
                'Host' => $row->Host,
                'db' => $row->db,
                'Command' => $row->Command,
                'Time' => $row->Time,
                'State' => $row->State,
                'Info' => $row->Info,
            ];
        }, $processes);

        return response()->json([
            'data' => $data,
            'status' => 200
        ], 200);
    }

    public function killProcess(Request $request)
    {
        $id = (int) $request->input('id');

        if (!$id) {
            return response()->json(['error' => 'Invalid ID'], 400);
        }

        try {
            DB::statement("KILL {$id}");
            return response()->json(['success' => true, 'message' => "Process $id killed."]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
