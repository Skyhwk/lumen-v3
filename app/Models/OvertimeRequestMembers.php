<?php 
namespace App\Models;
use App\Models\Sector;

class OvertimeRequestMembers extends Sector
{
    protected $table = "overtime_request_members";
    public $timestamps = false;

     protected $fillable =[
        "overtime_request_id",
        "no_document",
        "employee_id",
        "created_by",
        "created_at",
        "updated_by",
        "updated_at",
        "is_active"
    ];
    
    public function header(){
        return $this->belongsTo(OvertimeRequest::class,'overtime_request_id','id');
    }   

    public function employee(){
        return $this->belongsTo(MasterKaryawan::class,'employee_id','id');
    }
}