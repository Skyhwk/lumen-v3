<?php

namespace App\Jobs;

use App\Services\RenderSD;

class RenderSampelSD extends Job
{
    protected $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function handle()
    {
        $render = new RenderSD();
        $render->renderHeader($this->id);
        return true;
    }
}
