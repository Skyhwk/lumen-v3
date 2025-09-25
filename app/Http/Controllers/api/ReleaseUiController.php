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

class ReleaseUiController extends Controller
{
    public function index(Request $request)
    {
        $releaseSystem = ReleaseSystem::where('system', 'Frontend')->get();
        return response()->json(['success' => true, 'data' => $releaseSystem], 200);
    }

    public function frontend(Request $request)
    {
        DB::beginTransaction();
        $outputLog = [];

        $startTime = Carbon::now();

        try {
            $projectDir = '/var/www/javascript/react-js';
            $buildDir   = "$projectDir/build";
            $deployDir  = '/var/www/javascript/frontend/build';
            $backupDir  = '/mnt/backup/file/frontend/backup-' . date('dmyHi');

            $commands = [
                "cd $projectDir && git pull --ff-only origin main",
                "cd $projectDir && npm i && npm run build",
                "mkdir -p $backupDir && cp -r $deployDir/* $backupDir/ || true",
                "rsync -a --delete $buildDir/ $deployDir/"
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
                'system'        => 'Frontend',
                'proses_by'     => $this->karyawan,
                'proses_at'     => $startTime,
                'done_at'       => $endTime,
                'duration_sec'  => $duration // pastikan kolom ini ada di tabel
            ]);

            DB::commit();

            Log::channel('release_frontend')->info('Frontend berhasil dirilis!', $outputLog);
            return response()->json([
                'success'  => true,
                'message'  => 'Frontend berhasil dirilis!',
                'duration' => $duration . ' detik',
                'logs'     => $outputLog
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('release_frontend')->error('Frontend gagal dirilis!', $outputLog);
            return response()->json([
                'success' => false,
                'message' => 'Frontend gagal dirilis!',
                'logs'    => $outputLog,
                'error'   => $th->getMessage()
            ], 500);
        }
    }
}
