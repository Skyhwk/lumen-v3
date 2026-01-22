<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WsValueUdara extends Sector
{
    protected $table = "ws_value_udara";
    public $timestamps = false;

    protected $guarded = [];

    public function lingkungan()
    {
        return $this->belongsTo(LingkunganHeader::class, 'id_lingkungan_header', 'id');
    }

    public function order_detail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel');
    }
    
    public function getExistingParametersAttribute(){
        return $this->lingkungan()->where('is_approved', 1)->pluck('parameter')->toArray();
    }

    public function microbiologi()
    {
        return $this->belongsTo(MicrobioHeader::class, 'id_microbiologi_header', 'id');
    }

    public function medanLm()
    {
        return $this->belongsTo(MedanLmHeader::class, 'id_medan_lm_header', 'id');
    }

    public function sinaruv()
    {
        return $this->belongsTo(SinarUvHeader::class, 'id_sinaruv_header', 'id');
    }

    public function iklim()
    {
        return $this->belongsTo(IklimHeader::class, 'id_iklim_header', 'id');
    }

    public function getaran()
    {
        return $this->belongsTo(GetaranHeader::class, 'id_getaran_header', 'id');
    }

    public function kebisingan()
    {
        return $this->belongsTo(KebisinganHeader::class, 'id_kebisingan_header', 'id');
    }

    public function direct_lain()
    {
        return $this->belongsTo(DirectLainHeader::class, 'id_direct_lain_header', 'id');
    }

    public function partikulat()
    {
        return $this->belongsTo(PartikulatHeader::class, 'id_partikulat_header', 'id');
    }

    public function pencahayaan()
    {
        return $this->belongsTo(PencahayaanHeader::class, 'id_pencahayaan_header', 'id');
    }

    public function swab()
    {
        return $this->belongsTo(SwabTestHeader::class, 'id_swab_header', 'id');
    }

    public function subkontrak()
    {
        return $this->belongsTo(Subkontrak::class, 'id_subkontrak', 'id');
    }

    public function dustfall()
    {
        return $this->belongsTo(DustFallHeader::class, 'id_dustfall_header', 'id');
    }

    public function debuPersonal()
    {
        return $this->belongsTo(DebuPersonalHeader::class, 'id_debu_personal_header', 'id');
    }

    public function getDataAnalyst()
    {
        $relations = [
            'lingkungan',
            'microbiologi',
            'medanLm',
            'sinaruv',
            'iklim',
            'getaran',
            'kebisingan',
            'direct_lain',
            'partikulat',
            'pencahayaan',
            'swab',
            'subkontrak',
            'dustfall',
            'debuPersonal'
        ];

        foreach ($relations as $relation) {
            if ($this->$relation()->exists()) {
                return $this->$relation;
            }
        }

        return null;
    }

    public function detailLingkunganKerja() {
        return $this->belongsTo('App\Models\DetailLingkunganKerja', 'no_sampel', 'no_sampel');
    }
    
}