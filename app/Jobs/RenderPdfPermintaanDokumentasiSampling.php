<?php

namespace App\Jobs;

use App\Services\RenderPermintaanDokumentasiSampling;

class RenderPdfPermintaanDokumentasiSampling extends Job
{
    protected $permintaanDokumentasiSampling;
    protected $qr;

    public function __construct($permintaanDokumentasiSampling, $qr)
    {
        $this->permintaanDokumentasiSampling = $permintaanDokumentasiSampling;
        $this->qr = $qr;
    }

    public function handle()
    {
        $render = new RenderPermintaanDokumentasiSampling();
        $render->renderPdf($this->permintaanDokumentasiSampling, $this->qr);

        return true;
    }
}
