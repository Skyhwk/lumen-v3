<?php

namespace App\Jobs;

use App\Services\MobilisasiOperasionalService;
use Illuminate\Support\Facades\Log;

class SyncMobilisasiOperasionalAfterJadwalJob extends Job
{
    protected $oldIds;
    protected $newIds;
    protected $actor;
    protected $alasan;

    public function __construct(array $oldIds, array $newIds, $actor, $alasan)
    {
        $this->oldIds = $oldIds;
        $this->newIds = $newIds;
        $this->actor = $actor;
        $this->alasan = $alasan;
    }

    public function handle()
    {
        try {
            app(MobilisasiOperasionalService::class)->reconcileAfterJadwalChange(
                $this->oldIds,
                $this->newIds,
                $this->actor,
                $this->alasan
            );
        } catch (\Throwable $e) {
            Log::channel('sampling')->warning('Gagal sync MO setelah update jadwal: ' . $e->getMessage(), [
                'old_ids' => $this->oldIds,
                'new_ids' => $this->newIds,
            ]);
        }
    }
}
