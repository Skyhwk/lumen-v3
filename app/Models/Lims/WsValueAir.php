<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WsValueAir extends Sector
{
    
    protected $connection = 'lims';
protected $table = "ws_value_air";
    public $timestamps = false;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (WsValueAir $model) {
            $model->syncFieldsFromChild();
        });
    }

    public static function childFkMap(): array
    {
        return [
            Colorimetri::class => 'id_colorimetri',
            Titrimetri::class => 'id_titrimetri',
            Gravimetri::class => 'id_gravimetri',
            Subkontrak::class => 'id_subkontrak',
        ];
    }

    /**
     * Sinkronkan parameter & approval dari child saat ws_value_air disimpan.
     */
    public function syncFieldsFromChild(): void
    {
        $child = $this->getLinkedChildRecord();
        if (!$child) {
            return;
        }

        if ($child->parameter !== null && $child->parameter !== '') {
            $this->parameter = $child->parameter;
        }

        $approval = static::extractApprovalFromChild($child);
        if ($approval['is_approved'] !== null) {
            $this->is_approved = $approval['is_approved'];
        }
        if ($approval['approved_at'] !== null) {
            $this->approved_at = $approval['approved_at'];
        }
        if ($approval['approved_by'] !== null) {
            $this->approved_by = $approval['approved_by'];
        }
    }

    /**
     * Push field child ke semua ws_value_air yang terhubung (saat child di-update).
     */
    public static function pushChildFieldsToWsValueAir(Model $child): void
    {
        $fkMap = static::childFkMap();
        $class = get_class($child);

        if (!isset($fkMap[$class])) {
            return;
        }

        $fk = $fkMap[$class];
        $update = [];

        if ($child->parameter !== null && $child->parameter !== '') {
            $update['parameter'] = $child->parameter;
        }

        $approval = static::extractApprovalFromChild($child);
        foreach ($approval as $key => $value) {
            if ($value !== null) {
                $update[$key] = $value;
            }
        }

        if (empty($update)) {
            return;
        }

        static::where($fk, $child->id)->update($update);
    }

    public static function extractApprovalFromChild(Model $child): array
    {
        $isApproved = null;

        if (isset($child->is_approved)) {
            $isApproved = (int) $child->is_approved;
        } elseif (isset($child->is_approve)) {
            $isApproved = (int) $child->is_approve;
        }

        return [
            'is_approved' => $isApproved,
            'approved_at' => $child->approved_at ?? null,
            'approved_by' => $child->approved_by ?? null,
        ];
    }

    public function getLinkedChildRecord(): ?Model
    {
        if ($this->id_colorimetri) {
            return Colorimetri::find($this->id_colorimetri);
        }

        if ($this->id_titrimetri) {
            return Titrimetri::find($this->id_titrimetri);
        }

        if ($this->id_gravimetri) {
            return Gravimetri::find($this->id_gravimetri);
        }

        if ($this->id_subkontrak) {
            return Subkontrak::find($this->id_subkontrak);
        }

        return null;
    }

    public function resolveParameterFromChild(): ?string
    {
        $child = $this->getLinkedChildRecord();

        return $child && $child->parameter !== null && $child->parameter !== ''
            ? $child->parameter
            : null;
    }

    public function titrimetri() {
        return $this->belongsTo('App\Models\Titrimetri', 'id_titrimetri', 'id')->where('is_active', true);
    }
    public function dataLapanganAir() {
        return $this->belongsTo('App\Models\DataLapanganAir', 'no_sampel', 'no_sampel');
    }
    public function gravimetri() {
        return $this->belongsTo('App\Models\Gravimetri', 'id_gravimetri', 'id')->where('is_active', true);
    }
    public function colorimetri() {
        return $this->belongsTo('App\Models\Colorimetri', 'id_colorimetri', 'id')->where('is_active', true);
    }
    public function subkontrak() {
        return $this->belongsTo('App\Models\Subkontrak', 'id_subkontrak', 'id')->where('is_active', true);
    }

    // Tracking
    public function getDataAnalyst()
    {
        if ($this->titrimetri()->exists()) {
            return $this->titrimetri;
        }
        if ($this->gravimetri()->exists()) {
            return $this->gravimetri;
        }
        if ($this->colorimetri()->exists()) {
            return $this->colorimetri;
        }
        if ($this->subkontrak()->exists()) {
            return $this->subkontrak;
        }
        return null;
    }
}
