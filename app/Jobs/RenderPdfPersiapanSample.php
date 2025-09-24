<?php

namespace App\Jobs;

use App\Services\RenderPersiapanSample;

class RenderPdfPersiapanSample extends Job
{
    protected $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function handle()
    {
        $render = new RenderPersiapanSample();
        $render->renderPdf($this->id);
        return true;
    }
}
