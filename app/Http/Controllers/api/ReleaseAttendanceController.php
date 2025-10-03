<?php

namespace App\Http\Controllers\api;

use App\Models\ReleaseSystem;
use App\Models\Menu;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ReleaseAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $releaseSystem = ReleaseSystem::where('system', 'Attendance')->get();
        return response()->json(['success' => true, 'data' => $releaseSystem], 200);
    }

    public function switchPatch(Request $request){
        if(isset($request->data['patch']) && $request->data['patch'] != null){
            $patch = $request->data['patch'];
        } else {
            return response()->json(['success' => false, 'message' => 'Patch not found'], 400);
        }
        
        $rollback = ReleaseSystem::where('system', 'Attendance')->where('id', $request->data['id'])->first();
        $main = ReleaseSystem::where('system', 'Attendance')->where('is_main', true)->first();

        $folderRollback = str_replace('.', '-', $rollback->patch);
        $folderMain = str_replace('.', '-', $main->patch);

        //=====================cek folder=========================================
        $dirRollback = '/mnt/backup/file/attendance/' . $folderRollback;
        $dirMain = '/var/www/javascript/frontend/attendance/build/';
        $dirBackup = '/mnt/backup/file/attendance/' . $folderMain;


        //cek folder patch sekarang sudah ada di folder backup atau belum, jika belum maka copy folder patch sekarang ke folder backup
        try{
            $commands = [
                // Langkah 1: Backup build sekarang ke dirBackup jika belum ada, jika sudah ada skip
                "[[ -d $dirBackup && \"\$(ls -A $dirBackup)\" ]] || (mkdir -p $dirBackup && cp -r $dirMain/* $dirBackup/)",
                // Langkah 2: Hapus semua file di folder build
                "rm -rf $dirMain/*",
                // Langkah 3: Copy/rsync dari rollback ke build
                "cp -r $dirRollback/* $dirMain/"
            ];

            foreach ($commands as $key => $cmd) {
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
                    return response()->json([
                        'success' => false,
                        'message' => "Command gagal: $cmd",
                        'logs' => $outputLog
                    ], 500);
                }
            }

            $rollback->is_main = true;
            $rollback->save();

            $main->is_main = false;
            $main->save();

            return response()->json(['success' => true, 'message' => 'Patch berhasil di switch ke ' . $rollback->patch], 200);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'message' => 'Patch gagal di switch'], 400);
        }
    }   

    public function attendance(Request $request)
    {
        DB::beginTransaction();
        $outputLog = [];

        $startTime = Carbon::now();

        $numberPatch = '2.1';
        
        try {
            $lastData = ReleaseSystem::where('system', 'Attendance')->where('is_main', true)->orderBy('id', 'desc')->first();

            $projectDir = '/var/www/javascript/greatday';
            $buildDir   = "$projectDir/dist";
            $deployDir  = '/var/www/javascript/frontend/attendance';
            $backupDir  = '/mnt/backup/file/attendance/' . ($lastData && $lastData->patch ? str_replace('.', '-', $lastData->patch) : str_replace('.', '-', $numberPatch));

            $commands = [
                "cd $projectDir && git pull --ff-only origin main",
                "cd $projectDir && npm i && npm run build",
                "mkdir -p $backupDir && cp -r $deployDir/* $backupDir/ || true",
                "rsync -a --delete $buildDir/ $deployDir/"
            ];

            if ($lastData) {
                $parts = explode('.', $lastData->patch);
                $major = $parts[0] ?? 2;
                $minor = isset($parts[1]) ? (int)$parts[1] + 1 : 1;
                $numberPatch = $major . '.' . $minor;
            }

            foreach ($commands as $key => $cmd) {                
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
                'system'        => 'Attendance',
                'proses_by'     => $this->karyawan,
                'proses_at'     => $startTime,
                'done_at'       => $endTime,
                'duration_sec'  => $duration,
                'patch'         => $numberPatch,
                'is_main'       => true
            ]);

            if($lastData) {
                $lastData->is_main = false;
                $lastData->save();
            }

            DB::commit();

            Log::channel('release_attendance')->info('Attendance berhasil dirilis!', $outputLog);
            return response()->json([
                'success'  => true,
                'message'  => 'Attendance berhasil dirilis!',
                'duration' => $duration . ' detik',
                'logs'     => $outputLog
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('release_attendance')->error('Attendance gagal dirilis!', $outputLog);
            return response()->json([
                'success' => false,
                'message' => 'Attendance gagal dirilis!',
                'logs'    => $outputLog,
                'error'   => $th->getMessage()
            ], 500);
        }
    }
}
