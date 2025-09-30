<?php

namespace App\Http\Controllers\api;

use App\Models\ReleaseSystem;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ReleasePortalCustomerController extends Controller
{
    public function index(Request $request)
    {
        $releaseSystem = ReleaseSystem::where('system', 'Portal Customer')->get();
        return response()->json(['success' => true, 'data' => $releaseSystem], 200);
    }

    public function portalCustomer(Request $request)
    {
        DB::beginTransaction();
        $outputLog = [];

        $startTime = Carbon::now();
        
        try {
            $response = Http::post('https://portal.intilab.com/api/release-backend', []);
            $body = $response->json();
            if($body['success']){
                $outputLog[] = [
                    'command' => $body['logs'],
                    'output'  => $body['message'],
                    'error'   => '',
                    'success' => true
                ];

                $endTime = Carbon::now();
                $duration = $endTime->diffInSeconds($startTime); // hasil dalam detik

                ReleaseSystem::create([
                    'system'        => 'Portal Customer',
                    'proses_by'     => $this->karyawan,
                    'proses_at'     => $startTime,
                    'done_at'       => $endTime,
                    'duration_sec'  => $duration // pastikan kolom ini ada di tabel
                ]);

                DB::commit();

                Log::channel('release_backend')->info('Portal Customer berhasil dirilis!', $outputLog);
                return response()->json([
                    'success'  => true,
                    'message'  => 'Portal Customer berhasil dirilis!',
                    'duration' => $duration . ' detik',
                    'logs'     => $outputLog
                ]);
            }
            else{
                $outputLog[] = [
                    'command' => $body['logs'],
                    'output'  => $body['message'],
                    'error'   => $body['error'],
                    'success' => false
                ];

                DB::rollBack();

                Log::channel('release_backend')->error('Portal Customer gagal dirilis!', $outputLog);
                return response()->json([
                    'success' => false,
                    'message' => 'Portal Customer gagal dirilis!',
                    'logs'    => $outputLog,
                    'error'   => $body['error']
                ], 500);
            }

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('release_backend')->error('Portal Customer gagal dirilis!', $outputLog);
            return response()->json([
                'success' => false,
                'message' => 'Portal Customer gagal dirilis!',
                'logs'    => $outputLog,
                'error'   => $th->getMessage()
            ], 500);
        }
    }
}
