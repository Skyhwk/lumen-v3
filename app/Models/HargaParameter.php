<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class HargaParameter extends Sector
{
    protected $table = 'master_harga_parameter';
    protected $fillable = [
        'id_kategori',
        'nama_kategori',
        'id_parameter',
        'nama_parameter',
        'harga',
        'regen',
        'volume',
        'id_hist',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
        'is_active'
    ];

    public $timestamps = false;
    public function scopeWithHistory($query)
    {
        return $query->leftJoin('master_harga_parameter as hist', 'master_harga_parameter.id', '=', 'hist.id_hist')
            ->select(
                'master_harga_parameter.id',
                'master_harga_parameter.id_kategori',
                'master_harga_parameter.nama_kategori',
                'master_harga_parameter.id_parameter',
                'master_harga_parameter.harga',
                'master_harga_parameter.nama_parameter',
                'master_harga_parameter.regen',
                'master_harga_parameter.volume',
                'master_harga_parameter.created_by',
                'master_harga_parameter.created_at',
                \DB::raw('GROUP_CONCAT(hist.harga ORDER BY hist.id DESC SEPARATOR ",") as hist_harga'),
                \DB::raw('GROUP_CONCAT(hist.created_at ORDER BY hist.id DESC SEPARATOR ",") as tgl')
            )
            ->groupBy(
                'master_harga_parameter.id'
            );
    }
}
