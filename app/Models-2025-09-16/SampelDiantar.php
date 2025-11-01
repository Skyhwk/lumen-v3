<?php

namespace App\Models;

use App\Models\Sector;
use Carbon\Carbon;

class SampelDiantar extends Sector
{
    protected $table = 'sampel_diantar';
    protected $guarded = ['id'];
    public $timestamps = false;

    public function order()
    {
        return $this->belongsTo(OrderHeader::class, 'no_quotation', 'no_document')->where('is_active', 1);
    }

    public function detail()
    {
        return $this->hasMany(SampelDiantarDetail::class, 'id_header', 'id')->where('is_active', 1);
    }

    public function getUniquePeriodeAttribute()
    {
        return $this->detail
            ->pluck('periode')
            ->filter()
            ->unique()
            ->values();
    }

    public function getPeriodeAttribute()
    {
        return $this->detail
            ->map(function ($item) {
                $createdAt = Carbon::parse($item->created_at)->format('Y-m-d');
                return "{$item->periode},{$createdAt}";
            })
            ->filter()
            ->unique()
            ->values();
    }
}
