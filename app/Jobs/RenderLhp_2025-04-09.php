<?php

namespace App\Jobs;

use App\Services\TemplateLhps;

class RenderLhp extends Job
{
    protected $data;
    protected $data_detail;
    protected $mode_download;
    protected $data_custom;
    protected $custom2;

    public function __construct($data, $data_detail, $mode_download, $data_custom, $custom2 = null)
    {
        $this->data = $data;
        $this->data_detail = $data_detail;
        $this->mode_download = $mode_download;
        $this->data_custom = $data_custom;
        $this->custom2 = $custom2; 
    }

    public function handle()
    {
        $totData = count($this->data_detail);
        
        if ($totData <= 20) {
            $render = TemplateLhps::lhpAir20Kolom($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            // $renderTemplateLhps = TemplateLhps::lhpAirLebih20Kolom($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            return true;
        } else if($totData > 20) {
            $render = TemplateLhps::lhpAirLebih20Kolom($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            return true;
        }
    }
}
