<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalystWorksheetDetail extends Model
{
    protected $table = 'analyst_worksheet_details';
    
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'id_header',
        'no_sampel',
        'catatan',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
        'is_active'
    ];

    public function header()
    {
        return $this->belongsTo(AnalystWorksheetHeader::class, 'id_header', 'id');
    }

    public function getAnyHeaderUdara()
    {
        $result = collect();
        if ($this->pencahayaanHeader()->exists()) {
            $result->push($this->pencahayaanHeader);
        }
        if ($this->getaranHeader()->exists()) {
            $result->push($this->getaranHeader);
        }
        if ($this->udaraSubKontrak()->exists()) {
            $result->push($this->udaraSubKontrak);
        }
        if ($this->kebisinganHeader()->exists()) {
            $result->push($this->kebisinganHeader);
        }
        if ($this->swabTesHeader()->exists()) {
            $result->push($this->swabTesHeader);
        }
        if ($this->swabOnMicrobio()->exists()) {
            $result->push($this->swabOnMicrobio);
        }
        if ($this->dataLapanganPartikulatMeter()->exists()) {
            $result->push($this->dataLapanganPartikulatMeter);
        }
        if($this->lingkunganHeader()->exists()){
            $result->push($this->lingkunganHeader);
        }
        return $result->isEmpty() ? null : $result;
    }

    public function pencahayaanHeader()
    {
        return $this->belongsTo(PencahayaanHeader::class, 'no_sampel', 'no_sampel');
    }

    public function getaranHeader()
    {
        return $this->belongsTo(GetaranHeader::class, 'no_sampel', 'no_sampel');
    }

    public function udaraSubKontrak()
    {
        return $this->hasMany(Subkontrak::class, 'no_sampel', 'no_sampel')->with('ws_value_linkungan', 'ws_udara')->where('is_approve', true)->where('is_active', true);
    }

    public function kebisinganHeader()
    {
        return $this->belongsTo(KebisinganHeader::class, 'no_sampel', 'no_sampel');
    }

    public function swabTesHeader()
    {
        return $this->belongsTo(SwabTestHeader::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function swabOnMicrobio()
    {
        return $this->belongsTo(MicrobioHeader::class, 'no_sampel', 'no_sampel')
            ->where('microbio_header.parameter', 'like', "%Swab%")
            ->where('is_active', true);
    }

    public function dataLapanganPartikulatMeter()
    {
        return $this->belongsTo(DataLapanganPartikulatMeter::class, 'no_sampel', 'no_sampel');
    }

    public function lingkunganHeader()
    {
        return $this->belongsTo(LingkunganHeader::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function gravimetri()
    {
        return $this->hasMany(Gravimetri::class, 'no_sampel', 'no_sampel')->where('is_active', true)->where('is_approved', true);
    }

    public function titrimetri()
    {
        return $this->hasMany(Titrimetri::class, 'no_sampel', 'no_sampel')->where('is_active', true)->where('is_approved', true);
    }

    public function colorimetri()
    {
        return $this->hasMany(Colorimetri::class, 'no_sampel', 'no_sampel')->where('is_active', true)->where('is_approved', true);
    }

    public function subkontrak()
    {
        return $this->hasMany(Subkontrak::class, 'no_sampel', 'no_sampel')->where('is_active', true)->where('is_approve', true);
    }

    public function wsValueEmisiCerobong()
    {
        return $this->hasMany(WsValueEmisiCerobong::class, 'no_sampel', 'no_sampel')->with(['emisi_cerobong_header', 'emisi_isokinetik', 'subkontrak'])->where('is_active', true);
    }
}

