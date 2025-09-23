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

    public function restartDatabase(Request $request)
    {
        try {
            $output = [];
            $returnVar = 0;

            // Gunakan sudo dengan path lengkap
            exec('sudo /usr/bin/systemctl restart mysql.service 2>&1', $output, $returnVar);

            if ($returnVar !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal me-restart database: ' . implode("\n", $output)
                ], 500);
            }

            return response()->json(['success' => true, 'message' => 'Database berhasil di-restart']);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => $th->getMessage()], 500);
        }
    }
}
