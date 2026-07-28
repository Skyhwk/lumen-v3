<?php

namespace App\Jobs;

use App\Services\RenderCOC;

class RenderPdfCOC extends Job
{
    protected $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function handle()
    {
        $render = new RenderCOC();
        $render->renderPdf($this->id);
        return true;
    }
}
