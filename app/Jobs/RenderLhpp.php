<?php

namespace App\Jobs;

use App\Services\TemplateLhpp;

class RenderLhpp extends Job
{
    protected $data;
    protected $data_detail;
    protected $mode_download;
    protected $cfr;

    public function __construct($data, $data_detail, $mode_download, $cfr)
    {
        $this->data = $data;
        $this->data_detail = $data_detail;
        $this->mode_download = $mode_download;
        $this->cfr = $cfr;
    }

    public function handle()
    {
        $data = $this->data;
        $dataDetail = is_array($this->data_detail) ? $this->data_detail : [];

        $totData = count($dataDetail);

        try {
            $render = app(TemplateLhpp::class);
            $render->lhpp_psikologi($this->data, $this->data_detail, $this->mode_download, $this->cfr);
        } catch (\Throwable $e) {
            throw $e;
        }

        // $render = new TemplateLhpp;
        // $render->lhpp_psikologi($this->data, $this->data_detail, $this->mode_download);
        return true;
    }
}
