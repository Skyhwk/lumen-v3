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
        $releaseSystem = ReleaseSystem::where('system', 'TC Scan')->orderBy('id', 'desc')->get();
        return response()->json(['success' => true, 'data' => $releaseSystem], 200);
    }

    public function currentVersion()
    {
        $release = ReleaseSystem::where('system', 'TC Scan')
            ->where('is_main', true)
            ->orderBy('id', 'desc')
            ->first(['patch', 'proses_at', 'done_at']);

        if (!$release || !$release->patch) {
            return response()->json(['success' => true, 'data' => null], 200);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'system' => 'TC Scan',
                'patch' => $release->patch,
                'released_at' => $release->done_at ?: $release->proses_at,
            ],
        ], 200);
    }

    public function switchPatch(Request $request)
    {
        if (!isset($request->data['patch']) || $request->data['patch'] == null) {
            return response()->json(['success' => false, 'message' => 'Patch not found'], 400);
        }

        $rollback = ReleaseSystem::where('system', 'TC Scan')->where('id', $request->data['id'])->first();
        $main = ReleaseSystem::where('system', 'TC Scan')->where('is_main', true)->first();

        if (!$rollback || !$main) {
            return response()->json(['success' => false, 'message' => 'Data patch tidak lengkap'], 400);
        }

        if ($this->isDryRun()) {
            $rollback->is_main = true;
            $rollback->save();
            $main->is_main = false;
            $main->save();

            return response()->json([
                'success' => true,
                'dry_run' => true,
                'message' => 'DRY-RUN: flag is_main dipindah ke patch ' . $rollback->patch . '. File deploy tidak diubah.',
            ], 200);
        }

        $paths = $this->resolveReleasePaths($main, $rollback->patch);
        $dirRollback = rtrim(env('TC_SAMPLE_BACKUP_DIR', '/mnt/backup/file/tc-sample'), '/') . '/' . str_replace('.', '-', $rollback->patch);
        $dirMain = rtrim($paths['deployDir'], '/') . '/';
        $dirBackup = rtrim(env('TC_SAMPLE_BACKUP_DIR', '/mnt/backup/file/tc-sample'), '/') . '/' . str_replace('.', '-', $main->patch);

        try {
            $commands = [
                "[[ -d $dirBackup && \"\$(ls -A $dirBackup)\" ]] || (mkdir -p $dirBackup && cp -r $dirMain/* $dirBackup/)",
                "rm -rf $dirMain/*",
                "cp -r $dirRollback/* $dirMain/"
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

    public function appsTc(Request $request)
    {
        $dryRun = $this->isDryRun();

        $lock = $this->acquireReleaseLock();
        if ($lock === false) {
            return response()->json([
                'success' => false,
                'message' => 'Rilis lain sedang berjalan.',
            ], 409);
        }

        $outputLog = [];
        $startTime = Carbon::now();
        $numberPatch = '1.0';

        try {
            $lastData = ReleaseSystem::where('system', 'TC Scan')->where('is_main', true)->orderBy('id', 'desc')->first();

            $paths = $this->resolveReleasePaths($lastData, $numberPatch);
            $projectDir = $paths['projectDir'];
            $buildDir   = $paths['buildDir'];
            $deployDir  = $paths['deployDir'];
            $backupDir  = $paths['backupDir'];

            if ($lastData && $lastData->patch) {
                $parts = explode('.', $lastData->patch);
                $major = $parts[0] ?? 1;
                $minor = isset($parts[1]) ? (int)$parts[1] + 1 : 1;
                $numberPatch = $major . '.' . $minor;
            }

            if (!preg_match('/^\d+\.\d+$/', $numberPatch)) {
                return response()->json(['success' => false, 'message' => 'Nomor patch tidak valid'], 422);
            }

            if (!$dryRun) {
                $preflightError = $this->preflight($paths);
                if ($preflightError) {
                    Log::channel('release_apps_tc')->warning('Preflight rilis TC gagal', ['message' => $preflightError]);
                    return response()->json(['success' => false, 'message' => $preflightError], 422);
                }
            }

            $safeProject = escapeshellarg($projectDir);
            $safeBuild = escapeshellarg($buildDir);
            $safeDeploy = escapeshellarg($deployDir);
            $safeBackup = escapeshellarg($backupDir);
            $safeIndex = escapeshellarg($buildDir . '/index.html');
            $safeVersion = escapeshellarg($numberPatch);

            $commands = [
                "cd {$safeProject} && git pull --ff-only origin main",
                "cd {$safeProject} && npm i && node scripts/generate-version.js {$safeVersion} && NEXT_PUBLIC_APP_VERSION={$safeVersion} npm run build",
                "test -f {$safeIndex}",
                "mkdir -p {$safeBackup} && { [ ! \"$(ls -A {$safeDeploy} 2>/dev/null)\" ] || cp -r {$safeDeploy}/* {$safeBackup}/; }",
                "rsync -a --delete {$safeBuild}/ {$safeDeploy}/"
            ];

            foreach ($commands as $key => $cmd) {
                if ($dryRun) {
                    $outputLog[] = [
                        'command' => $cmd,
                        'output'  => 'DRY-RUN: perintah tidak dieksekusi.',
                        'error'   => '',
                        'success' => true
                    ];
                    continue;
                }

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
                    $message = $key === 2
                        ? 'Hasil build kosong atau tidak ada index.html. Deploy dibatalkan supaya folder live tidak terhapus.'
                        : "Command gagal: $cmd";

                    Log::channel('release_apps_tc')->error($message, $outputLog);
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'logs' => $outputLog
                    ], 500);
                }

                if ($key === 1 && !$this->writeDeployVersionFile($buildDir, $numberPatch)) {
                    $message = 'Gagal menulis version.json ke hasil build';
                    Log::channel('release_apps_tc')->error($message, $outputLog);
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'logs' => $outputLog
                    ], 500);
                }
            }

            $endTime = Carbon::now();
            $duration = $endTime->diffInSeconds($startTime);

            DB::beginTransaction();
            try {
                ReleaseSystem::create([
                    'system'        => 'TC Scan',
                    'proses_by'     => $this->karyawan,
                    'proses_at'     => $startTime,
                    'done_at'       => $endTime,
                    'duration_sec'  => $duration,
                    'patch'         => $numberPatch,
                    'keterangan'    => $dryRun ? json_encode(['type' => 'DRY_RUN']) : null,
                    'is_main'       => true
                ]);

                if ($lastData) {
                    $lastData->is_main = false;
                    $lastData->save();
                }

                DB::commit();
            } catch (\Throwable $dbError) {
                DB::rollBack();
                throw $dbError;
            }

            $message = $dryRun
                ? 'DRY-RUN: alur rilis tercatat di database local. git/npm/rsync tidak dijalankan.'
                : 'Apps TC berhasil dirilis!';

            Log::channel('release_apps_tc')->info($message, $outputLog);
            return response()->json([
                'success'  => true,
                'dry_run'  => $dryRun,
                'message'  => $message,
                'duration' => $duration . ' detik',
                'logs'     => $outputLog
            ]);
        } catch (\Throwable $th) {
            Log::channel('release_apps_tc')->error('Apps TC gagal dirilis!', [
                'logs' => $outputLog,
                'error' => $th->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Apps TC gagal dirilis!',
                'logs'    => $outputLog,
                'error'   => $th->getMessage()
            ], 500);
        } finally {
            $this->releaseLock($lock);
        }
    }

    private function isDryRun()
    {
        if (filter_var(env('ALLOW_RELEASE_LOCAL', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (filter_var(env('RELEASE_DRY_RUN', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return env('APP_ENV') === 'local';
    }

    private function acquireReleaseLock()
    {
        $lockDir = storage_path('framework');
        if (!is_dir($lockDir) && !@mkdir($lockDir, 0755, true) && !is_dir($lockDir)) {
            return false;
        }

        $handle = fopen($lockDir . '/release_tc.lock', 'c');
        if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) {
            if ($handle) {
                fclose($handle);
            }
            return false;
        }

        return $handle;
    }

    private function releaseLock($handle)
    {
        if (!is_resource($handle)) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function preflight(array $paths)
    {
        if (!is_dir($paths['projectDir'] . '/.git')) {
            return 'Repo tidak ditemukan di server ini: ' . $paths['projectDir'] . ' (cek TC_SAMPLE_PROJECT_DIR). Rilis harus jalan di server production.';
        }

        if (!is_dir($paths['deployDir'])) {
            return 'Folder deploy tidak ada: ' . $paths['deployDir'] . ' (cek TC_SAMPLE_DEPLOY_DIR).';
        }

        foreach (['git', 'npm', 'rsync'] as $bin) {
            $check = Process::fromShellCommandline('command -v ' . escapeshellarg($bin));
            $check->run();
            if (!$check->isSuccessful()) {
                return "'" . $bin . "' tidak terpasang di server ini.";
            }
        }

        return null;
    }

    private function resolveReleasePaths($lastData, $numberPatch)
    {
        $projectDir = rtrim(env('TC_SAMPLE_PROJECT_DIR', '/var/www/javascript/tc-sample'), '/');
        $deployDir = rtrim(env('TC_SAMPLE_DEPLOY_DIR', '/var/www/html/tc-sample'), '/');
        $backupRoot = rtrim(env('TC_SAMPLE_BACKUP_DIR', '/mnt/backup/file/tc-sample'), '/');
        $backupName = $lastData && $lastData->patch
            ? str_replace('.', '-', $lastData->patch)
            : str_replace('.', '-', $numberPatch);

        return [
            'projectDir' => $projectDir,
            'buildDir' => $projectDir . '/out',
            'deployDir' => $deployDir,
            'backupDir' => $backupRoot . '/' . $backupName,
        ];
    }

    private function writeDeployVersionFile($buildDir, $numberPatch)
    {
        if (!is_dir($buildDir) && !mkdir($buildDir, 0755, true) && !is_dir($buildDir)) {
            return false;
        }

        $payload = json_encode([
            'patch' => $numberPatch,
            'generated_at' => Carbon::now()->toDateTimeString(),
        ], JSON_PRETTY_PRINT);

        return file_put_contents($buildDir . '/version.json', $payload) !== false;
    }
}
