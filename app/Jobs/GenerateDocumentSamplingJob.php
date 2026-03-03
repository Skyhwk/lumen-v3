<?php

namespace App\Jobs;

use App\Services\GenerateDocumentJadwal;
use App\Services\GenerateDocumentSampling;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDocumentSamplingJob extends Job
{

    protected string $type;
    protected int $quotationId;
    protected ?string $periode;
    protected ?string $karyawan;

    public $timeout = 300; // PDF berat
    public $tries = 3;

    public function __construct(string $type, int $quotationId, ?string $periode = null, ?string $karyawan = null)
    {
        $this->type        = $type;
        $this->quotationId = $quotationId;
        $this->periode     = $periode;
        $this->karyawan = $karyawan;
    }

    public function handle()
    {
        if ($this->type === 'QT') {
            GenerateDocumentSampling::onNonKontrak($this->quotationId)->save();
            GenerateDocumentJadwal::onNonKontrak($this->quotationId)
            ->setKaryawan($this->karyawan)
            ->save();
        } else {
            GenerateDocumentSampling::onKontrak($this->quotationId)->onPeriode($this->periode)->saveKontrak();
            GenerateDocumentJadwal::onKontrak($this->quotationId)
            ->setKaryawan($this->karyawan)
            ->saveKontrak();           
        }
    }
}
