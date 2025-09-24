<?php

namespace App\Models;

use App\Models\Sector;

class HistoryPerubahanSales extends Sector
{
    protected $table = 'history_perubahan_sales';
    public $timestamps = false;

    public function detailPelanggan()
    {
        return $this->hasOne(MasterPelanggan::class, 'id_pelanggan', 'id_pelanggan');
    }

    public function salesLama()
    {
        return $this->belongsTo(MasterKaryawan::class, 'id_sales_lama', 'id');
    }

    public function salesBaru()
    {
        return $this->belongsTo(MasterKaryawan::class, 'id_sales_baru', 'id');
    }

    public function status()
    {
        return $this->hasOne(DFUS::class, 'id_pelanggan', 'id_pelanggan');
    }

    public function getFilteredStatusAttribute(): bool
    {
        if (!$this->salesBaru) return null;
        return $this->status()->where('sales_penanggung_jawab', $this->salesBaru->nama_lengkap)->first();
    }

    public function shouldIncludeInReassignList(): bool
    {
        $dfus = $this->filtered_status;

        if (!$dfus) return true;

        $status = json_decode($dfus->action_status, true);

        return !isset($status['inCall']);
    }

    public function scopeFilterReassignList($query)
    {
        return $query->whereDoesntHave('status', function ($q) {
            $q->whereColumn('dfus.id_pelanggan', 'history_perubahan_sales.id_pelanggan')
                ->whereRaw("dfus.sales_penanggung_jawab = (
                SELECT nama_lengkap 
                FROM master_karyawan 
                WHERE master_karyawan.id = history_perubahan_sales.id_sales_baru
                LIMIT 1
            )");
        });
    }

    public function getLatestDfusAttribute()
    {
        $dfus = DFUS::where('id_pelanggan', $this->id_pelanggan);
        if ($this->salesBaru) $dfus = $dfus->where('sales_penanggung_jawab', $this->salesBaru->nama_lengkap);

        return $dfus->orderByDesc('tanggal')->first();
    }
}
