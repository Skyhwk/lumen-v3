<?php

namespace App\Models;

class KonfigurasiLabelParameter extends Sector
{
    protected $table = 'konfigurasi_label_parameter';

    public $timestamps = false;

    protected $fillable = [
        'id_parameter',
        'nama_parameter',
        'volume',
        'regen',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function parameter()
    {
        return $this->belongsTo(Parameter::class, 'id_parameter', 'id');
    }

    public static function queryActive()
    {
        return static::query()->where('is_active', true);
    }

    public static function forParameterIds($ids)
    {
        if (empty($ids)) {
            return collect();
        }

        return static::queryActive()
            ->whereIn('id_parameter', $ids)
            ->get(['volume', 'regen', 'id_parameter', 'nama_parameter']);
    }

    public static function forNamaParameters($names)
    {
        if (empty($names)) {
            return collect();
        }

        return static::queryActive()
            ->whereIn('nama_parameter', $names)
            ->get(['volume', 'regen', 'id_parameter', 'nama_parameter']);
    }

    public static function volumeOf($idParameter)
    {
        return static::queryActive()
            ->where('id_parameter', $idParameter)
            ->value('volume') ?? 0;
    }

    public static function volumeOfNama($namaParameter)
    {
        return static::queryActive()
            ->where('nama_parameter', $namaParameter)
            ->value('volume') ?? 0;
    }

    public static function sumVolumeByNama($names)
    {
        if (empty($names)) {
            return 0;
        }

        return (float) static::queryActive()
            ->whereIn('nama_parameter', $names)
            ->sum('volume');
    }
}
