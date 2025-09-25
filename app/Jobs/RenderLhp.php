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
        $data = $this->data;

        // if($this->data->id_kategori_3 == 32) {
        //     $render = TemplateLhps::DirectESBSolar($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
        // }
        // dd($data);
        $dataDetail = is_array($this->data_detail) ? $this->data_detail : [];
        $totData = count($dataDetail);

        if ($data->id_kategori_3 == 32) {
            $render = new TemplateLhps;
            $render->DirectESBSolar($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            return true;
        } else if ($data->id_kategori_3 == 31) {
            $render = new TemplateLhps;
            $render->DirectESBBensin($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            return true;
        } else if ($data->id_kategori_2 == 34) {

            $render = new TemplateLhps;
            $render->emisisumbertidakbergerak($this->data, $this->data_detail, $this->mode_download, $this->data_custom);
            return true;
        } else if (in_array($data->id_kategori_3, [11, 27])) {
            $parameter = json_decode($data->parameter_uji);

            if (in_array("Sinar UV", $parameter)) {
                $render = new TemplateLhps;
                $render->lhpSinarUV($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            } else if (in_array("Medan Magnit Statis", $parameter) || in_array("Medan Listrik", $parameter) || in_array("Power Density", $parameter)) {
                $render = new TemplateLhps;
                $render->lhpMagnet($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            } else {
                $render = new TemplateLhps;
                $render->lhpLingkungan($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            }
            return true;
        } else if (in_array($data->id_kategori_3, [28])) {
            $render = new TemplateLhps;
            $render->lhpPencahayaan($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            return true;
        } else if (in_array($data->id_kategori_3, [23, 24, 25])) {
            $parameter = json_decode($data->parameter_uji);
            // dd($parameter);
          if (is_array($parameter) && in_array("Kebisingan (P8J)", $parameter)) {
                $render = new TemplateLhps;
                $render->lhpKebisinganPersonal($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            } else {
                $render = new TemplateLhps;
                $render->lhpKebisinganSesaat($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            }
            return true;
        } else if (in_array($data->id_kategori_3, [21])) {
            $parameter = json_decode($data->parameter_uji);
            if (is_array($parameter) && (in_array("ISBB", $parameter) || in_array("ISBB (8 Jam)", $parameter))) {
                $render = new TemplateLhps;
                $render->lhpIklimPanas($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            } else {
                $render = new TemplateLhps;
                $render->lhpIklimDingin($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            }
            return true;
        } else if (in_array($data->id_kategori_3, [13, 14, 15, 16, 18, 19])) {
            $render = new TemplateLhps;
            $render->lhpGetaran($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            return true;
        } else if (in_array($data->id_kategori_3, [17, 20])) {
            $render = new TemplateLhps;
            $render->lhpGetaranPersonal($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            return true;
        } else if ($totData <= 20 && stripos($data->sub_kategori, "air") !== false) {
            $render = new TemplateLhps;
            $render->lhpAir20Kolom($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            return true;
        } else if ($totData > 20 && stripos($data->sub_kategori, "air") !== false) {
            $render = new TemplateLhps;
            $render->lhpAirLebih20Kolom($this->data, $this->data_detail, $this->mode_download, $this->data_custom, $this->custom2);
            return true;
        }
    }
}
