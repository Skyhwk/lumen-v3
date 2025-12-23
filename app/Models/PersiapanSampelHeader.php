<?php

namespace App\Models;

use App\Models\Sector;

class PersiapanSampelHeader extends Sector
{
    protected $table = 'persiapan_sampel_header';
    public $timestamps = true;
    protected $fillable = [
        'no_order',
        'no_quotation',
        'periode',
        'tanggal_sampling',
        'nama_perusahaan',
        'no_sampel',
        'tambahan',
        'plastik_benthos',
        'media_petri_dish',
        'media_tabung',
        'masker',
        'sarung_tangan_karet',
        'sarung_tangan_bintik',
        'analis_berangkat',
        'sampler_berangkat',
        'analis_pulang',
        'sampler_pulang',
        'nama_sampler_cs',
        'file_ttd_sampler_cs',
        'filename_cs',
        'nama_pic_sampling_cs',
        'file_ttd_pic_sampling_cs',
        'tanda_tangan_bas',
        'catatan',
        'detail_bas_documents',
        'detail_cs_documents',
        'informasi_teknis',
        'filename_bas',
        'filename',
    ];

    public function psDetail()
    {
        return $this->hasMany(PersiapanSampelDetail::class, 'id_persiapan_sampel_header')->where('is_active', 1);
    }

    public function orderHeader()
    {
        return $this->belongsTo(OrderHeader::class, 'no_order', 'no_order');
    }
}
