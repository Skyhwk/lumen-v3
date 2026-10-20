<?php

namespace App\Jobs;

use App\Services\GaAsset\GaAssetWriteService;
use Illuminate\Support\Facades\Log;

class CreateGaAssetJob extends Job
{
    public $tries = 3;
    public $timeout = 60;

    protected $payload;
    protected $actor;

    public function __construct(array $payload, $actor)
    {
        $this->payload = $payload;
        $this->actor = $actor;
    }

    public function handle()
    {
        try {
            $asset = app(GaAssetWriteService::class)->create($this->payload, (string) $this->actor);
            Log::info('CreateGaAssetJob: aset berhasil dibuat dengan NO CS ' . $asset->cs_code, [
                'asset_id' => $asset->id,
                'cs_code' => $asset->cs_code,
                'cs_sequence' => $asset->cs_sequence,
            ]);
        } catch (\Exception $exception) {
            Log::error('CreateGaAssetJob: gagal membuat aset: ' . $exception->getMessage(), [
                'name' => $this->payload['name'] ?? null,
                'sub_kategori_aset_id' => $this->payload['sub_kategori_aset_id'] ?? null,
                'sub_kategori_baru' => $this->payload['sub_kategori_baru'] ?? null,
            ]);
            throw $exception;
        }
    }
}
