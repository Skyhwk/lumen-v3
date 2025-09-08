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
            // --- Sanitize username agar tidak ada kutip/spasi aneh ---
            $username = trim($request->input('username'), " \t\n\r\0\x0B'\"");
            $password = $request->input('password');

            // Cek apakah username sudah ada di DB
            if (AksesServer::where('username', $username)->exists()) {
                return response()->json([
                    'message' => "User $username sudah ada di database."
                ], 400);
            }

            // Cek apakah username sudah ada di sistem Linux
            $process = new Process(['id', $username]);
            $process->run();
            if ($process->isSuccessful()) {
                return response()->json([
                    'message' => "User $username sudah ada di sistem Linux."
                ], 400);
            }

            // Tambah user baru
            $process = new Process(['sudo', '/usr/sbin/useradd', '-m', '-s', '/bin/bash', $username]);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Set password
            $process = new Process(['sudo', '-S', 'chpasswd']);
            $process->setInput("$username:$password");
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Tambah ke group www-data
            $process = new Process(['sudo', '/usr/sbin/usermod', '-aG', 'www-data', $username]);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Simpan di database
            AksesServer::create([
                'karyawan_id' => $request->input('id_karyawan'),
                'username' => $username,
                'password' => $password,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now()
            ]);

            \DB::commit();

            return response()->json([
                'message' => 'User berhasil dibuat.'
            ], 200);
        } catch (\Exception $e) {
            \DB::rollBack();

            $username = trim($request->input('username'), " \t\n\r\0\x0B'\"");
            if (isset($username) && !empty($username)) {
                $process = new \Symfony\Component\Process\Process(['sudo', '/usr/sbin/userdel', '-r', $username]);
                $process->run();
                // Tidak perlu throw error jika gagal, karena ini hanya cleanup
            }
            \DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat user: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $value = trim($request->input('value'), " \t\n\r\0\x0B'\"");
        $column = $request->input('column');
        $id = $request->input('id');

        $akses = AksesServer::findOrFail($id);

        if ($column === 'username') {
            // Cek apakah username baru sudah ada
            if (AksesServer::where('username', $value)->where('id', '!=', $id)->exists()) {
                return response()->json([
                    'message' => "Username $value sudah digunakan."
                ], 400);
            }

            // Rename user di sistem
            $process = new Process(['sudo', '/usr/sbin/usermod', '-l', $value, $akses->username]);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $akses->username = $value;
        }

        if ($column === 'password') {
            $process = new Process(['sudo', '-S', 'chpasswd']);
            $process->setInput("$akses->username:$value");
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $akses->password = $value;
        }

        $akses->updated_by = $this->karyawan;
        $akses->updated_at = Carbon::now();
        $akses->save();

        return response()->json(['message' => 'User berhasil diperbarui.'], 200);
    }

    public function delete(Request $request)
    {
        \DB::beginTransaction();
        try {
            $id = $request->input('id');
            $akses = AksesServer::findOrFail($id);

            // Hapus user di sistem (jika ada)
            $username = $akses->username;
            $process = new Process(['id', $username]);
            $process->run();

            if ($process->isSuccessful()) {
                // User ada di sistem → hapus
                $process = new Process(['sudo', '/usr/sbin/userdel', '-r', $username]);
                $process->run();
                if (!$process->isSuccessful()) {
                    // Kalau gagal hapus user di sistem, tetap lanjut soft-delete di DB
                    \Log::warning("Gagal hapus user $username di sistem Linux: " . $process->getErrorOutput());
                }
            }

            // Soft delete di database
            $akses->is_active = 0;
            $akses->save();

            \DB::commit();

            return response()->json([
                'message' => "User $username berhasil di-nonaktifkan."
            ], 200);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus user: ' . $e->getMessage()
            ], 500);
        }
    }

}