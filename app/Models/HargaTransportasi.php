<?php
namespace App\Models;

use App\Models\Sector;
use Carbon\Carbon;

class HargaTransportasi extends Sector
{
    protected $table = 'master_harga_transportasi';

    protected $fillable = [
        'status',
        'wilayah',
        'transportasi',
        'per_orang',
        'total',
        'tiket',
        'penginapan',
        '24jam',
        'id_hist',
        'tanggal_berlaku',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
        'is_active',
    ];

    public $timestamps = false;

    public static function effectiveDateExpression($alias = 'mht')
    {
        return "COALESCE({$alias}.tanggal_berlaku, DATE({$alias}.created_at), '1970-01-01')";
    }

    public static function getEffectiveForWilayah($wilayah, $tglPenawaran = null)
    {
        $date = $tglPenawaran ?: Carbon::today()->toDateString();
        $effectiveDate = static::effectiveDateExpression('master_harga_transportasi');

        return static::query()
            ->where('wilayah', $wilayah)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereRaw("{$effectiveDate} <= ?", [$date])
            ->orderByRaw("{$effectiveDate} DESC")
            ->orderBy('id', 'desc')
            ->first();
    }

    public function scopeEffective($query, $date = null)
    {
        $date = $date ?: Carbon::today()->toDateString();
        $effectiveDate = static::effectiveDateExpression('master_harga_transportasi');
        $effectiveDateSub = static::effectiveDateExpression('mht2');

        return $query
            ->where('master_harga_transportasi.is_active', true)
            ->whereNull('master_harga_transportasi.deleted_at')
            ->whereRaw("{$effectiveDate} <= ?", [$date])
            ->whereRaw("master_harga_transportasi.id = (
                SELECT mht2.id FROM master_harga_transportasi mht2
                WHERE mht2.wilayah = master_harga_transportasi.wilayah
                AND mht2.status = master_harga_transportasi.status
                AND mht2.deleted_at IS NULL
                AND mht2.is_active = true
                AND {$effectiveDateSub} <= ?
                ORDER BY {$effectiveDateSub} DESC, mht2.id DESC
                LIMIT 1
            )", [$date]);
    }
}
