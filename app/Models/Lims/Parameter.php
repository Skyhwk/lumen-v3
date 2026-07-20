<?php
namespace App\Models\Lims;

use App\Models\Sector;
use Carbon\Carbon;

class Parameter extends Sector
{
    protected $connection = 'lims';


    protected $table = 'parameter';

    protected $fillable = [
        'nama_lab',
        'nama_regulasi',
        'nama_lhp',
        'status',
        'id_kategori',
        'nama_kategori',
        'satuan',
        'method',
        'nilai_minimum',
        'nilai_ketidak_pastian',
        'is_blocked',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public $timestamps = false;

    /**
     * Relasi ke hargaParameter:
     * Mengambil record HargaParameter yang is_active dan tanggal_berlaku <= hari ini,
     * urut dari yang paling baru.
     */
    public function hargaParameter()
    {
        // return relasi, bukan langsung ambil data
        return $this->hasOne(HargaParameter::class, 'id_parameter', 'id')
            ->where('is_active', true)
            ->where('tanggal_berlaku', '<=', Carbon::today()->toDateString())
            ->orderByDesc('tanggal_berlaku');
    }
}
