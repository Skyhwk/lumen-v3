<?php

namespace App\Models;

use App\Models\Sector;

class SamplingPlan extends Sector
{
    protected $connection = 'mysql';
    public $timestamps = false;
    protected $guarded = [];

    public function getTable()
    {
        $mainDb = \DB::connection('mysql')->getDatabaseName();
        return $mainDb . '.sampling_plan';
    }

    public function jadwal()
    {
        $mainDb = \DB::connection('mysql')->getDatabaseName();
        return $this->hasMany(Jadwal::class, 'id_sampling')
            ->from($mainDb . '.jadwal')
            ->where('is_active', true);
    }

    public function jadwalSP()
    {
        $mainDb = \DB::connection('mysql')->getDatabaseName();
        return $this->hasOne(Jadwal::class, 'id_sampling', 'id')
            ->from($mainDb . '.jadwal')
            ->where('is_active', true);
    }

    public function countJadwal()
    {
        return $this->jadwal->count();
    }

    public function quotationKontrak()
    {
        // return $this->belongsTo(QuotationKontrakH::class, 'quotation_id');
        return $this->belongsTo(QuotationKontrakH::class, 'no_quotation', 'no_document');
    }

    public function quotation()
    {
        // return $this->belongsTo(QuotationNonKontrak::class, 'quotation_id');
        return $this->belongsTo(QuotationNonKontrak::class, 'no_quotation', 'no_document');
    }

    public function scopeWithTypeModelSub($query)
    {
        $query->with([
            'quotationKontrak.detail',
            'quotation'
        ])
        ->where(function ($query) {
            $query->where('status_quotation', 'kontrak')
                ->orWhere('status_quotation', '!=', 'kontrak')
                ->orWhereNull('status_quotation');
        });
    }

    public function praNoSample()
    {
        return $this->hasOne(PraNoSample::class, 'no_quotation', 'no_quotation')
            ->when($this->status_quotation == 'kontrak', function ($query) {
                $query->where('periode', $this->periode_kontrak);
            });
    }

    public function groupJadwal()
    {
        $mainDb = \DB::connection('mysql')->getDatabaseName();
        return $this->hasMany(Jadwal::class, 'id_sampling')
            ->from($mainDb . '.jadwal')
            ->selectRaw('tanggal, jam_mulai, jam_selesai, kategori, GROUP_CONCAT(sampler) as sampler', 'created_by')
            ->where('is_active', true)
            ->groupBy('tanggal', 'jam_mulai', 'jam_selesai', 'kategori', 'created_by');
    }

}
