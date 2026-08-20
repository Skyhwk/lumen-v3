<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalystWorksheetHeader extends Model
{
    protected $table = 'analyst_worksheet_headers';
    
    // We only use created_at, no updated_at by default in schema
    const UPDATED_AT = null;

    protected $fillable = [
        'nama_workspace',
        'id_kategori',
        'parameter',
        'created_by',
        'is_active',
        'is_finished'
    ];

    public function details()
    {
        return $this->hasMany(AnalystWorksheetDetail::class, 'id_header', 'id');
    }

    public function kategori()
    {
        return $this->belongsTo(MasterKategori::class, 'id_kategori', 'id');
    }
}
