<?php

namespace App\Jobs;

use App\Services\InternalMailService;
use Illuminate\Support\Facades\Log;

class SyncInternalMailInboxJob extends Job
{
    protected $idKaryawan;
    protected $legacyKey;
    protected $folder;
    protected $forceFull;

    public function __construct(int $idKaryawan, ?string $legacyKey = null, string $folder = 'inbox', bool $forceFull = false)
    {
        $this->idKaryawan = $idKaryawan;
        $this->legacyKey = $legacyKey;
        $this->folder = $folder;
        $this->forceFull = $forceFull;
    }

    public function handle(): void
    {
        try {
            $service = new InternalMailService($this->idKaryawan, $this->legacyKey);
            $service->runBoundedSync($this->folder, $this->forceFull, 45);
        } catch (\Throwable $e) {
            Log::warning('SyncInternalMailInboxJob gagal: ' . $e->getMessage(), [
                'id_karyawan' => $this->idKaryawan,
                'folder'      => $this->folder,
            ]);
        }
    }
}
