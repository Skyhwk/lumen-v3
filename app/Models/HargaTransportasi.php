<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

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
        return $query->leftJoin('master_harga_transportasi as hist', 'master_harga_transportasi.id', '=', 'hist.id_hist')
                     ->select(
                        'master_harga_transportasi.id',
                        'master_harga_transportasi.status',
                        'master_harga_transportasi.wilayah',
                        'master_harga_transportasi.transportasi',
                        'master_harga_transportasi.per_orang',
                        'master_harga_transportasi.total',
                        'master_harga_transportasi.tiket',
                        'master_harga_transportasi.penginapan',
                        'master_harga_transportasi.24jam',
                        'master_harga_transportasi.created_by',
                        'master_harga_transportasi.created_at',
                        \DB::raw("GROUP_CONCAT(hist.transportasi ORDER BY hist.id DESC SEPARATOR ',') hist_transport"),
                        \DB::raw("GROUP_CONCAT(hist.per_orang ORDER BY hist.id DESC SEPARATOR ',') hist_per_orang"),
                        \DB::raw("GROUP_CONCAT(hist.total ORDER BY hist.id DESC SEPARATOR ',') hist_total"),
                        \DB::raw("GROUP_CONCAT(hist.tiket ORDER BY hist.id DESC SEPARATOR ',') hist_tiket"),
                        \DB::raw("GROUP_CONCAT(hist.penginapan ORDER BY hist.id DESC SEPARATOR ',') hist_penginapan"),
                        \DB::raw("GROUP_CONCAT(hist.24jam ORDER BY hist.id DESC SEPARATOR ',') hist_24jam"),
                        \DB::raw("GROUP_CONCAT(hist.created_at ORDER BY hist.id DESC SEPARATOR ',') tgl")
                     )
                     ->groupBy(
                        'master_harga_transportasi.id'
                    );
    }
}
