<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class AksesMenu extends Sector
{
    use HasFactory;

    protected $table = 'akses_menu';

    protected $fillable = [
        'user_id',
        'akses'
    ];

    protected $casts = [
        'akses' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function karyawan()
    {
        return $this->hasOne(MasterKaryawan::class, 'user_id', 'user_id');
    }
}
