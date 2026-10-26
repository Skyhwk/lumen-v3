<?php
namespace App\Models\Greatday;

class OvertimeRequest extends GreatdayAppModel
{
    protected $table = 'overtime_requests';

    protected $guarded = ['id'];

    public function members()
    {
        return $this->hasMany(OvertimeRequestMember::class)->where('is_active', true);
    }
}
