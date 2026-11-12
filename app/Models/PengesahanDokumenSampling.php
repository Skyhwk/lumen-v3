<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PengesahanDokumenSampling extends Sector{
    protected $table = 'pengesahan_dokumen_sampling';
    
    protected $guarded = [];

    public $timestamps = false;

    public const TYPE_SAMPLING = 'sampling';
    public const TYPE_K3 = 'k3';

    public static function latestForType(string $type = self::TYPE_SAMPLING, $onDate = null)
    {
        $query = static::query()->where('type', $type);
        if ($onDate) {
            $query->where('berlaku_mulai', '<=', $onDate);
        }

        return $query->orderByDesc('berlaku_mulai')->first();
    }
}