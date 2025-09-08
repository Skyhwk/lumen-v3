<?php

namespace App\Http\Controllers\api;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use App\Models\MasterKaryawan;
use App\Models\AksesServer;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AksesServerController extends Controller
{
    public function getActiveKaryawan(Request $request)
    {
        $karyawan = MasterKaryawan::where('is_active', true)->get();
        return response()->json([
            'message' => 'get data karyawan success', 
            'data' => $karyawan
        ], 200);
    }

    public function index(Request $request)
    {
        $data = AksesServer::with('karyawan')->where('is_active', 1);

        return Datatables::of($data)->make(true);
    }

    public function create(Request $request)
    {
        \DB::beginTransaction();
        try {
            $username = escapeshellarg($request->input('username'));
            $password = escapeshellarg($request->input('password'));

            // 1. Tambah user dengan home dir + shell bash
            $process = new Process(['sudo', '/usr/sbin/useradd', '-m', '-s', '/bin/bash', $username]);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // 2. Set password
            $process = new Process(['sudo', 'chpasswd']);
            $process->setInput($request->input('username') . ":$rawPassword");
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // 3. Masukkan ke group www-data
            $process = new Process(['sudo', '/usr/sbin/usermod', '-aG', 'www-data', $username]);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            AksesServer::create([
                'karyawan_id' => $request->input('karyawan_id'),
                'username' => $request->input('username'),
                'password' => $request->input('password'),
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now()
            ]);

            \DB::commit();

            return response()->json([
                'message' => 'User berhasil dibuat.'
            ], 200);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat user: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request){
        // parameter request => id, column, value (column cuma dua yaitu username & password)
    }
}