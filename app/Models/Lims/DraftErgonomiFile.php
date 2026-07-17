<?php
namespace App\Models\Lims;

use App\Models\Sector;

class DraftErgonomiFile extends Sector
{
    protected $connection = 'lims';


    protected $table = 'draft_ergonomi_file';

    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (!empty($model->no_sampel)) {
                $model->no_order = explode('/', $model->no_sampel)[0];
            }
        });
    }

    public function order_detail()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')->where('is_active',1);
    }

    public function ergonomi_lapangan()
    {
        return $this->belongsTo(DataLapanganErgonomi::class, 'no_sampel', 'no_sampel');
    }

    public function link()
    {
        return $this->belongsTo('App\Models\Lims\GenerateLink','id','id_quotation')
        ->where('quotation_status', 'draft_ergonomi');
    }

}
