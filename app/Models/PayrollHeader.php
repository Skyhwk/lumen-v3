<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PayrollHeader extends Sector{
    protected $table = 'payroll_header';
    protected $guard = [];

    
    protected $fillable = [
        'no_document',
        'status_karyawan',
        'periode_payroll',
        'status',
        'tgl_transfer',
        'keterangan',
        'transfer_by',
        'transfer_at',
        'created_at',
        'created_by',
        'approved_at',
        'approved_by',
        'deleted_at',
        'deleted_by',
        'previous_id',
        'is_active',
        'is_approve',
        'is_download',
    ];

    public $timestamps = false;

    public function payrolls()
    {
        return $this->hasMany(Payroll::class, 'payroll_header_id', 'id')
                    ->where('is_active', 1);
    }
}