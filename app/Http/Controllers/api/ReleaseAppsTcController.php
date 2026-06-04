<?php

namespace App\Http\Controllers\api;

use App\Models\ReleaseSystem;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Symfony\Component\Process\Process;

class ReleaseAppsTcController extends Controller
{
    public function index(Request $request)
    {
        $releaseSystem = ReleaseSystem::where('system', 'TC Scan')->get();
        return response()->json(['success' => true, 'data' => $releaseSystem], 200);
    }

    public function appsTc(Request $request)
    {
        DB::beginTransaction();
        $outputLog = [];

        $startTime = Carbon::now();

        try {
            $projectDir = '/var/www/javascript/tc-sample';
            $buildDir   = "$projectDir/out";
            $deployDir  = '/var/www/html/tc-sample';
            $backupDir  = '/mnt/backup/file/tc-sample/backup-' . date('dmyHi');

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
                'system'        => 'TC Scan',
                'proses_by'     => $this->karyawan,
                'proses_at'     => $startTime,
                'done_at'       => $endTime,
                'duration_sec'  => $duration
            ]);

            DB::commit();

            Log::channel('release_apps_tc')->info('Apps TC berhasil dirilis!', $outputLog);
            return response()->json([
                'success'  => true,
                'message'  => 'Apps TC berhasil dirilis!',
                'duration' => $duration . ' detik',
                'logs'     => $outputLog
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('release_apps_tc')->error('Apps TC gagal dirilis!', $outputLog);
            return response()->json([
                'success' => false,
                'message' => 'Apps TC gagal dirilis!',
                'logs'    => $outputLog,
                'error'   => $th->getMessage()
            ], 500);
        }
    }
}
