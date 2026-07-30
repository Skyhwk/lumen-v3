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

class ReleaseAppsFdlController extends Controller
{
    public function index(Request $request)
    {
        $releaseSystem = ReleaseSystem::where('system', 'Apps FDL')->get();
        return response()->json(['success' => true, 'data' => $releaseSystem], 200);
    }

    public function appsFdl(Request $request)
    {
        DB::beginTransaction();
        $outputLog = [];

        $startTime = Carbon::now();

        try {
            // ╔══════════════════════════════════════════════════════════════╗
            // ║  BLOK SERVER PRODUCTION                                     ║
            // ║  Remark seluruh blok ini jika ingin test di lokal Windows   ║
            // ╚══════════════════════════════════════════════════════════════╝
            $projectDir = '/var/www/javascript/apps-fdl';
            $buildDir   = "$projectDir/build";
            $deployDir  = '/var/www/html/apps-fdl';
            $backupDir  = '/mnt/backup/file/apps-fdl/backup-' . date('dmyHi');

            $commands = [
                "cd $projectDir && git pull --ff-only origin main",
                "cd $projectDir && npm i && node scripts/generate-version.js && npm run build",
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

            // ╔══════════════════════════════════════════════════════════════╗
            // ║  BLOK LOCAL WINDOWS (UNTUK TESTING)                         ║
            // ║  Unremark blok ini & remark blok server di atas untuk test  ║
            // ╚══════════════════════════════════════════════════════════════╝
            // $projectDir = 'D:\ReactJs\apps-fdl';
            // $buildDir   = "$projectDir\\build";
            // $deployDir  = 'D:\ReactJs\backup\apps-fdl_deploy';
            // $backupDir  = 'D:\ReactJs\backup\apps-fdl\backup-' . date('dmyHi');
            //
            // $commands = [
            //     ['cmd' => 'C:/PROGRA~1/nodejs/node.exe scripts/generate-version.js', 'cwd' => $projectDir],
            //     ['cmd' => 'C:/PROGRA~1/nodejs/npm.cmd run build', 'cwd' => $projectDir],
            //     ['cmd' => "if not exist \"$backupDir\" mkdir \"$backupDir\"", 'cwd' => null],
            //     ['cmd' => "if exist \"$deployDir\" xcopy /E /I /Y \"$deployDir\" \"$backupDir\"", 'cwd' => null],
            //     ['cmd' => "if not exist \"$deployDir\" mkdir \"$deployDir\"", 'cwd' => null],
            //     ['cmd' => "xcopy /E /I /Y \"$buildDir\" \"$deployDir\"", 'cwd' => null],
            // ];
            //
            // foreach ($commands as $item) {
            //     $process = Process::fromShellCommandline($item['cmd']);
            //     $process->setTimeout(1200);
            //     if ($item['cwd']) {
            //         $process->setWorkingDirectory($item['cwd']);
            //     }
            //     $env = array_merge($_SERVER, $_ENV, [
            //         'CI'   => 'false',
            //         'PATH' => getenv('PATH') . ';C:\\Program Files\\nodejs',
            //     ]);
            //     $process->setEnv($env);
            //     $process->run();
            //
            //     $outputLog[] = [
            //         'command' => $item['cmd'],
            //         'output'  => $process->getOutput(),
            //         'error'   => $process->getErrorOutput(),
            //         'success' => $process->isSuccessful()
            //     ];
            //
            //     if (!$process->isSuccessful()) {
            //         DB::rollBack();
            //         return response()->json([
            //             'success' => false,
            //             'message' => "Command gagal: " . $item['cmd'],
            //             'logs' => $outputLog
            //         ], 500);
            //     }
            // }

            // Catat waktu selesai
            $endTime = Carbon::now();
            $duration = $endTime->diffInSeconds($startTime); // hasil dalam detik

            ReleaseSystem::create([
                'system'        => 'Apps FDL',
                'proses_by'     => $this->karyawan,
                'proses_at'     => $startTime,
                'done_at'       => $endTime,
                'duration_sec'  => $duration // pastikan kolom ini ada di tabel
            ]);

            DB::commit();

            Log::channel('release_apps_fdl')->info('Apps FDL berhasil dirilis!', $outputLog);
            return response()->json([
                'success'  => true,
                'message'  => 'Apps FDL berhasil dirilis!',
                'duration' => $duration . ' detik',
                'logs'     => $outputLog
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('release_apps_fdl')->error('Apps FDL gagal dirilis!', $outputLog);
            return response()->json([
                'success' => false,
                'message' => 'Apps FDL gagal dirilis!',
                'logs'    => $outputLog,
                'error'   => $th->getMessage()
            ], 500);
        }
    }
}
