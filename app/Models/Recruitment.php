<?php
namespace App\Models;

use App\Models\Sector;
use Illuminate\Support\Facades\Log;
class Recruitment extends Sector
{

    protected $table = 'recruitment';

    public $timestamps = false;
    protected $fillable = [];
    
    public function setFillableFields(array $fields, ?string $traceId = null)
    {
        $columns = \Schema::getColumnListing($this->getTable());
        $invalid = array_diff($fields, $columns);
        if (!empty($invalid)) {
             Log::warning('Ada kolom tidak valid di fillable', [
                 'trace_id' => $traceId, // tracing ID dimasukkan di sini
                'invalid_fields' => implode(', ', $invalid),
                'table' => $this->getTable(),
            ]);
        }
        $this->fillable = array_intersect($fields, $columns);
        return $this;
    }

    public function examps()
    {
        return $this->hasOne(RecruitmantExamp::class, 'kode_uniq', 'kode_uniq');
    }

    public function jabatan()
    {
        return $this->belongsTo(MasterJabatan::class, 'bagian_di_lamar', 'id');
    }

}
