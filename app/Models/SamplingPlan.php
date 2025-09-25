<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class SamplingPlan extends Sector
{
    protected $table = 'sampling_plan';
    public $timestamps = false;
    protected $guarded = [];

    public function jadwal()
    {
        return $this->hasMany(Jadwal::class, 'id_sampling')->where('is_active', true);
    }

    public function jadwalSP()
    {
        return $this->hasOne(Jadwal::class, 'id_sampling', 'id')->where('is_active', true);
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
        $query->with(['quotationKontrak', 'quotation'])
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
        return $this->hasMany(Jadwal::class, 'id_sampling')
            ->selectRaw('tanggal, jam_mulai, jam_selesai, kategori, GROUP_CONCAT(sampler) as sampler', 'created_by')
            ->where('is_active', true)
            ->groupBy('tanggal', 'jam_mulai', 'jam_selesai', 'kategori', 'created_by');
    }

}
