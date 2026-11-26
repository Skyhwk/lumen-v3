<?php

namespace App\Models;

class TemplateKategoriSkK3 extends Sector
{
    protected $table = 'template_kategori_sk_k3';

    protected $guarded = [];

    public $timestamps = false;

    public function subKategori()
    {
        return $this->belongsTo(MasterSubKategori::class, 'id_sub_kategori', 'id');
    }
}