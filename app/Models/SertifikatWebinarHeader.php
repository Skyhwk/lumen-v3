<?php

namespace App\Models;

use App\Models\Sector;

class SertifikatWebinarHeader extends Sector
{
    protected $table = "sertifikat_webinar_header";
    public $timestamps = false;

    protected $casts = [
        'speakers' => 'array',
    ];

    protected $guarded = [];

    public function details()
    {
        return $this->hasMany(SertifikatWebinarDetail::class, 'header_id', 'id');
    }

    public function font()
    {
        return $this->belongsTo(JenisFont::class, 'id_font', 'id');
    }

    public function layout()
    {
        return $this->belongsTo(LayoutCertificate::class, 'id_layout', 'id');
    }

    public function template()
    {
        return $this->belongsTo(TemplateBackground::class, 'id_template', 'id');
    }

}
