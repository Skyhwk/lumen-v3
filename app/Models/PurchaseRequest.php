<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PurchaseRequest extends Sector{
    protected $table = 'purchase_request';
    protected $guard = [];
    
    
    protected $fillable = [
        'uniq_id',
        'no_ref',
        'nama_barang',
        'merk',
        'no_katalog',
        'keperluan',
        'quantity',
        'satuan',
        'request_status',
        'due_date',
        'status',
        'request_time',
        'request_by',
        'process_time',
        'process_by',
        'pending_time',
        'pending_by',
        'pending_notes',
        'reject_by',
        'reject_times',
        'reject_notes',
        'done_by',
        'done_time',
        'done_notes',
        'void_by',
        'void_time',
        'void_notes',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'is_urgent',
        'is_active',
    ];
    
    public $timestamps = false;
}