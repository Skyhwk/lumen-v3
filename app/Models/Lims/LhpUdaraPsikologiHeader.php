<?php

namespace App\Models\Lims;

use App\Models\LhpUdaraPsikologiDetail;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpUdaraPsikologiHeader extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhp_udara_psikologi_header";
    public $timestamps = false;
    protected $guarded = [];

    public function lhpUdaraPsikologiDetail()
    {
        return $this->hasMany(LhpUdaraPsikologiDetail::class, 'id_header', 'id');
    }
    public function link()
    {
        return $this->belongsTo(GenerateLink::class, 'id', 'id_quotation')
            ->where('quotation_status', 'lhp_psikologi');
    }
}
