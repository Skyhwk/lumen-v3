<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\Sector;
use Carbon\Carbon;

class DataLapanganEmisiKendaraan extends Sector
{
    protected $table = "data_lapangan_emisi_kendaraan";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function emisiOrder(){
        return $this->belongsTo(DataLapanganEmisiOrder::class, 'no_sampel', 'no_sampel')->with('qr','regulasi.bakumutu','kendaraan')->where('is_active', true);
    }

    public function scopeWithAppsEmissions($query, $isActive, $karyawan)
    {
        return $query->join('data_lapangan_emisi_order', 'data_lapangan_emisi_kendaraan.no_sampel', '=', 'data_lapangan_emisi_order.no_sampel')
            ->join('master_kendaraan', 'data_lapangan_emisi_order.id_kendaraan', '=', 'master_kendaraan.id')
            ->join('master_qr', 'data_lapangan_emisi_order.id_kendaraan', '=', 'master_qr.id_kendaraan')
            ->join('order_detail', 'data_lapangan_emisi_order.no_sampel', '=', 'order_detail.no_sampel')
            ->where('data_lapangan_emisi_kendaraan.is_active', $isActive)
            ->where('data_lapangan_emisi_kendaraan.created_by', 'like', '%'.$karyawan.'%')
            ->whereDate('data_lapangan_emisi_kendaraan.created_at', '>=', Carbon::now()->subDays(3))
            ->select(
                'data_lapangan_emisi_kendaraan.id',
                DB::raw('DATE(data_lapangan_emisi_kendaraan.created_at) as tgl'),
                'master_qr.id as id_qr',
                'master_qr.kode',
                'order_detail.nama_perusahaan as nama',
                'order_detail.no_sampel as no_sample',
                'master_kendaraan.bobot_kendaraan'
            )
            ->orderBy('data_lapangan_emisi_kendaraan.id', 'DESC');
    }

}