<?php

namespace App\Jobs;

use App\Services\GenerateDocumentJadwal;
use App\Services\GenerateDocumentSampling;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDocumentJadwalJob extends Job
{

    protected string $type;
    protected int $quotationId;
    protected string $karyawan;

    public $timeout = 300; // PDF berat
    public $tries = 3;

    public function __construct(string $type, int $quotationId, ?string $karyawan = null)
    {
        $this->type        = $type;
        $this->quotationId = $quotationId;
        $this->karyawan    = $karyawan;
    }

    public function handle()
    {
        if ($this->type === 'QT') {
            GenerateDocumentJadwal::onNonKontrak($this->quotationId)
            ->setKaryawan($this->karyawan)->save();
        } else {
            GenerateDocumentJadwal::onKontrak($this->quotationId)
            ->setKaryawan($this->karyawan)->saveKontrak();
                
        }
    }
}
