<?php

namespace App\Helpers;

use App\Jobs\ReorderNotifierJob;

use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller;

class WorkerOperation extends Controller
{
    public function index($orderHeader, $log, $bcc, $userid)
    {
        try {
            dispatch(new ReorderNotifierJob($orderHeader, $log, $bcc, $userid));

            Log::channel('perubahan_no_sampel')->info('Perubahan No Sampel : ', $log);
        } catch (\Throwable $th) {
            dd($th);
            Log::error('Error Worker Operation', $th->getMessage());
        }
    }
}
