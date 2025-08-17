<?php

namespace App\Jobs;

use App\Services\RenderSamplingPlan as RenderSamplingPlanService;

class RenderSamplingPlan extends Job
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
            $render = RenderSamplingPlanService::onKontrak($this->id);
            $render->save();
            return true;
        } else {
            $render = RenderSamplingPlanService::onNonKontrak($this->id);
            $render->save();
            return true;
        }
    }
}
