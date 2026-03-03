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

class ReleaseUiController extends Controller
{
    public function index(Request $request)
    {
        $releaseSystem = ReleaseSystem::where('system', 'Frontend')->orderBy('id', 'desc')->get();
        return response()->json(['success' => true, 'data' => $releaseSystem], 200);
    }

    public function switchPatch(Request $request){
        if(isset($request->data['patch']) && $request->data['patch'] != null){
            $patch = $request->data['patch'];
        } else {
            return response()->json(['success' => false, 'message' => 'Patch not found'], 400);
        }
        
        $rollback = ReleaseSystem::where('system', 'Frontend')->where('id', $request->data['id'])->first();
        $main = ReleaseSystem::where('system', 'Frontend')->where('is_main', true)->first();

        $folderRollback = str_replace('.', '-', $rollback->patch);
        $folderMain = str_replace('.', '-', $main->patch);

        //=====================cek folder=========================================
        $dirRollback = '/mnt/backup/file/frontend/' . $folderRollback;
        $dirMain = '/var/www/javascript/frontend/build/';
        $dirBackup = '/mnt/backup/file/frontend/' . $folderMain;


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

    public function frontend(Request $request)
    {
        DB::beginTransaction();
        $outputLog = [];

        $startTime = Carbon::now();
        
        $versionFile = '/var/www/javascript/react-js/src/utils/version.json';
        $version = null;
        if (file_exists($versionFile)) {
            $versionContent = file_get_contents($versionFile);
            $version = json_decode($versionContent, true);
            $oldVersion = $version;
            $awal = \explode('.', $version['version'])[2];
            if($awal == 1){
                $awal = 518;
            } else {
                $awal = $awal + 1;
            }
        }

        $countMenu = $this->getCountMenu();

        $numberPatch = '3.'.$countMenu.'.'.$awal;
        
        try {
            $lastData = ReleaseSystem::where('system', 'Frontend')->where('is_main', true)->orderBy('id', 'desc')->first();

            $projectDir = '/var/www/javascript/react-js';
            $buildDir   = "$projectDir/build";
            $deployDir  = '/var/www/javascript/frontend/build';
            $backupDir  = '/mnt/backup/file/frontend/' . ($lastData && $lastData->patch ? str_replace('.', '-', $lastData->patch) : 'backup-' . date('dmyHi'));

            $commands = [
                "cd $projectDir && git pull --ff-only origin main",
                "cd $projectDir && npm i && npm run build",
                "mkdir -p $backupDir && cp -r $deployDir/* $backupDir/ || true",
                "rsync -a --delete $buildDir/ $deployDir/"
            ];

            foreach ($commands as $key => $cmd) {
                if($key == 1){
                    if (file_exists($versionFile)) {
                        $version['version'] = $numberPatch;
                        file_put_contents($versionFile, json_encode($version, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    }
                }
                
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
                    if ($oldVersion) {
                        file_put_contents($versionFile, json_encode($oldVersion, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    }

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
                'duration_sec'  => $duration,
                'patch'         => $numberPatch,
                'keterangan'    => json_encode($request->notes),
                'is_main'       => true
            ]);

            if($lastData) {
                $lastData->is_main = false;
                $lastData->save();
            }

            DB::commit();

            Log::channel('release_frontend')->info('Frontend berhasil dirilis!', $outputLog);
            return response()->json([
                'success'  => true,
                'message'  => 'Frontend berhasil dirilis!',
                'duration' => $duration . ' detik',
                'logs'     => $outputLog
            ]);
        } catch (\Throwable $th) {
            if ($oldVersion) {
                file_put_contents($versionFile, json_encode($oldVersion, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

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

    private function getCountMenu()
    {
        $menu = Menu::where('is_active', true)->get();

        $transformedData = $menu->map(function ($item) {
            $children = collect($item->submenu)->map(function ($submenu) {
                $submenu = (object) $submenu;
                $children = collect($submenu->sub_menu)->map(function ($subMenuItem) {
                    return [
                        'name' => $subMenuItem,
                        'path' => '/' . \Illuminate\Support\Str::slug($subMenuItem)
                    ];
                });

                return [
                    'name' => $submenu->nama_inden_menu,
                    'path' => '/' . \Illuminate\Support\Str::slug($submenu->nama_inden_menu),
                    'children' => $children
                ];
            });

            return [
                'name' => $item->menu,
                'icon' => $item->icon,
                'children' => $children,
                'path' => '/' . \Illuminate\Support\Str::slug($item->menu)
            ];
        });

        return count($this->getMenus($transformedData));
    }

    private function getMenus($menu)
    {
        $result = [];

        foreach ($menu as $item) {
            // Jika item memiliki children
            if (isset($item['children']) && count($item['children']) > 0) {

                foreach ($item['children'] as $child) {
                    if (isset($child['children']) && count($child['children']) > 0) {
                        foreach ($child['children'] as $grandChild) {
                            if (isset($grandChild['path'])) {
                                $permissions = $this->getPermissions($grandChild['name']);
                                $result[] = array_merge(
                                    $permissions,
                                    [
                                        'name' => '→→ ' . $grandChild['name']
                                    ]
                                );
                            }
                        }
                    } else if (isset($child['path'])) {
                        $permissions = $this->getPermissions($child['name']);
                        $result[] = array_merge(
                            $permissions,
                            [
                                'name' => '→ ' . $child['name']
                            ]
                        );
                    }
                }
            }
        }

        return $result;
    }

    private function getPermissions($name)
    {
        $defaultPermissions = [
            'name' => $name,
            'view' => false,
            'create' => false,
            'update' => false,
            'delete' => false,
            'export' => false,
            'import' => false,
            'approve' => false,
            'reject' => false,
            'all' => false,
        ];

        return $defaultPermissions;
    }
}
