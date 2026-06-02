<?php

namespace App\Http\Controllers\api;

use App\Models\ReleaseSystem;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Symfony\Component\Process\Process;

class ReleaseAppsSarController extends Controller
{
    public function index(Request $request)
    {
        $releaseSystem = ReleaseSystem::where('system', 'Apps SAR')->get();
        return response()->json(['success' => true, 'data' => $releaseSystem], 200);
    }

    public function appsSar(Request $request)
    {
        DB::beginTransaction();
        $outputLog = [];

        $startTime = Carbon::now();

        try {
            $projectDir = '/var/www/javascript/apps-sar';
            $buildDir   = "$projectDir/build";
            $deployDir  = '/var/www/html/apps-sar';
            $backupDir  = '/mnt/backup/file/apps-sar/backup-' . date('dmyHi');

            $commands = [
                "cd $projectDir && git pull --ff-only origin main",
                "cd $projectDir && npm i && npm run build",
                "mkdir -p $backupDir && cp -r $deployDir/* $backupDir/ || true",
                "rsync -a --delete $buildDir/ $deployDir/"
            ];

            foreach ($commands as $cmd) {
                $process = Process::fromShellCommandline($cmd);
                $process->setTimeout(1200);
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

            $endTime = Carbon::now();
            $duration = $endTime->diffInSeconds($startTime);

            ReleaseSystem::create([
                'system'        => 'Apps SAR',
                'proses_by'     => $this->karyawan,
                'proses_at'     => $startTime,
                'done_at'       => $endTime,
                'duration_sec'  => $duration
            ]);

            DB::commit();

            Log::channel('release_apps_sar')->info('Apps SAR berhasil dirilis!', $outputLog);
            return response()->json([
                'success'  => true,
                'message'  => 'Apps SAR berhasil dirilis!',
                'duration' => $duration . ' detik',
                'logs'     => $outputLog
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('release_apps_sar')->error('Apps SAR gagal dirilis!', $outputLog);
            return response()->json([
                'success' => false,
                'message' => 'Apps SAR gagal dirilis!',
                'logs'    => $outputLog,
                'error'   => $th->getMessage()
            ], 500);
        }
    }
}
