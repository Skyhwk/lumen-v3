<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use App\Models\Sector;

class DataLapanganErgonomi extends Sector
{
    protected $table = "data_lapangan_ergonomi";
    public $timestamps = false;

    protected $guarded = [];

    public function detail()
    {
        if (config('is_lims', false)) {
            return $this->belongsTo(\App\Models\Lims\OrderDetail::class, 'no_sampel', 'no_sampel')->where('is_active', true)->with('orderHeader');
        }
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')->where('is_active', true)->with('orderHeader');
    }

    public function scopeActive(Builder $query): Builder
    {
        if (Schema::hasColumn($this->getTable(), 'is_active')) {
            return $query->where($this->getTable() . '.is_active', 1);
        }

        return $query;
    }

    public static function findLatestActive(string $noSampel, ?int $method = null, bool $approvedOnly = false)
    {
        $noSampel = strtoupper(trim($noSampel));

        $query = static::query()
            ->active()
            ->where('no_sampel', $noSampel)
            ->orderBy('id', 'desc');

        if ($method !== null) {
            $query->where('method', $method);
        }

        if ($approvedOnly) {
            $query->where('is_approve', 1);
        }

        return $query->first();
    }

    public static function findLatestApprovedForLhp(string $noSampel, int $method)
    {
        return static::with(['detail'])
            ->active()
            ->where('no_sampel', $noSampel)
            ->where('method', $method)
            ->where('is_approve', 1)
            ->orderBy('id', 'desc')
            ->first();
    }
}