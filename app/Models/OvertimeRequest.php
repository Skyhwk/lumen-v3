<?php 
namespace App\Models;
use App\Models\Sector;

class OvertimeRequest extends Sector
{
    protected $table = "overtime_requests";
    public $timestamps = false;

     protected $fillable =[
        "no_document",
        "department_id",
        "start_date",
        "end_date",
        "start_time",
        "end_time",
        "description",
        "status",
        "created_by",
        "created_at",
        "updated_by",
        "updated_at",
        "deleted_by",
        "deleted_at",
        "is_active",
        "approved_atasan_by",
        "approved_atasan_at",
        "rejected_atasan_by",
        "rejected_atasan_at",
        "reject_atasan_reason",
        "approved_hrd_by",
        "approved_hrd_at",
        "rejected_hrd_by",
        "rejected_hrd_at",
        "reject_hrd_reason",
        "approved_finance_by",
        "approved_finance_at",
        "rejected_finance_by",
        "rejected_finance_at",
        "reject_finance_reason",
    ];

    public function detail() {
        return $this->hasMany(OvertimeRequestMembers::class,'overtime_request_id','id');
    }

    public function department(){
        return $this->hasOne(MasterDivisi::class,'id','department_id');
    }
}