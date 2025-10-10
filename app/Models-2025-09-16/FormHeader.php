<?php 
namespace App\Models;
use App\Models\Sector;

class FormHeader extends Sector
{
    protected $table = "form_header";
    public $timestamps = false;

    protected $fillable =[
        "no_document",
        "type_document",
        "status",
        "cuti_khusus",
        "is_active",
        "created_by",
        "created_at",
        "updated_by",
        "updated_at",
        "deleted_by",
        "deleted_at"
    ];
    public function detail() {
        return $this->hasMany(FormDetail::class,'no_document','no_document');
    }
}