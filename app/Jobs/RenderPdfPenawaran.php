<?php

namespace App\Jobs;

use App\Services\RenderNonKontrak;
use App\Services\RenderKontrak;
use App\Services\RenderSar;

class RenderPdfPenawaran extends Job
{
    protected $id;
    protected $qt;

    public function __construct($id, $qt)
    {
        $this->id = $id;
        $this->qt = $qt;
    }

    public function handle()
    {
        if ($this->qt == 'kontrak') {
            $render = new RenderKontrak();
            $render->renderDataQuotation($this->id, 'id');
            $render = new RenderKontrak();
            $render->renderDataQuotation($this->id, 'en');
            return true;
        } else if ($this->qt == 'quotation-sar') {
            $render = new RenderSar();
            $render->renderHeader($this->id, 'id');
            $render = new RenderSar();
            $render->renderHeader($this->id, 'en');
            return true;
        } else {
            $render = new RenderNonKontrak();
            $render->renderHeader($this->id, 'id');
            $render = new RenderNonKontrak();
            $render->renderHeader($this->id, 'en');
            return true;
        }
    }
}
