<?php

namespace App\Jobs;

use App\Services\RenderPermintaanDokumentasiSampling;

class RenderPdfPermintaanDokumentasiSampling extends Job
{
    protected $permintaanDokumentasiSampling;
    protected $qr;
    protected $periode;

    public function __construct($permintaanDokumentasiSampling, $qr, $periode = null)
    {
        $this->permintaanDokumentasiSampling = $permintaanDokumentasiSampling;
        $this->qr = $qr;
        $this->periode = $periode;
    }

    public function handle()
    {
        $render = new RenderPermintaanDokumentasiSampling();
        $render->renderPdf($this->permintaanDokumentasiSampling, $this->qr, $this->periode);

        return true;
    }
}
