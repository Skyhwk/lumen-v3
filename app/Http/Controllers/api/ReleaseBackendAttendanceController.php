<?php

namespace App\Http\Controllers\api;

use App\Models\ReleaseSystem;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ReleaseBackendAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $releaseSystem = ReleaseSystem::where('system', 'Backend Attendance')->orderBy('id', 'desc')->get();
        return response()->json(['success' => true, 'data' => $releaseSystem], 200);
    }

    public function backend(Request $request)
    {
        DB::beginTransaction();
        $outputLog = [];

        $startTime = Carbon::now();

        try {
            $projectDir = '/var/www/html/intilab-internal';

            $commands = [
                "cd $projectDir && git pull --ff-only origin main"
            ];

            foreach ($commands as $cmd) {
                $process = Process::fromShellCommandline($cmd);
                $process->setTimeout(1200); // 20 menit
                $process->run();

                $outputLog[] = [
                    'command' => $cmd,
                    'output'  => $process->getOutput(),
                    'error'   => $process->getErrorOutput(),
                    'success' => $process->isSuccessful()
                ];

                if (!$process->isSuccessful()) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Command gagal: $cmd",
                        'logs' => $outputLog
                    ], 500);
                }
            }

            // Catat waktu selesai
            $endTime = Carbon::now();
            $duration = $endTime->diffInSeconds($startTime); // hasil dalam detik

            ReleaseSystem::create([
                'system'        => 'Backend Attendance',
                'proses_by'     => $this->karyawan,
                'proses_at'     => $startTime,
                'done_at'       => $endTime,
                'duration_sec'  => $duration // pastikan kolom ini ada di tabel
            ]);

            DB::commit();

            Log::channel('release_backend')->info('Backend berhasil dirilis!', $outputLog);
            return response()->json([
                'success'  => true,
                'message'  => 'Backend berhasil dirilis!',
                'duration' => $duration . ' detik',
                'logs'     => $outputLog
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('release_backend')->error('Backend gagal dirilis!', $outputLog);
            return response()->json([
                'success' => false,
                'message' => 'Backend gagal dirilis!',
                'logs'    => $outputLog,
                'error'   => $th->getMessage()
            ], 500);
        }
    }
}
