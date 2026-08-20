<?php

namespace App\Models;

use Carbon\Carbon;

class UserToken extends Sector
{
    protected $table = 'user_token';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'token',
        'create_date',
        'expired',
        'is_logged_in',
        'is_expired',
        'type',
        'platform',
        'user_agent',
        'ip_address',
        'last_active_at',
        'is_impersonate',
        'impersonator_user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function karyawan()
    {
        return $this->hasOne(MasterKaryawan::class, 'user_id', 'user_id');
    }

    public function akses()
    {
        return $this->hasOne(AksesMenu::class, 'user_id', 'user_id')->where('is_active', true);
    }

    public function webphone()
    {
        return $this->hasOneThrough(Webphone::class, MasterKaryawan::class, 'user_id', 'karyawan_id', 'user_id', 'id');
    }

    /**
     * Token masih aktif (belum expired manual maupun datetime).
     */
    public function isActive()
    {
        if ($this->is_expired) {
            return false;
        }

        if (empty($this->expired)) {
            return true;
        }

        return Carbon::parse($this->expired)->greaterThanOrEqualTo(Carbon::now());
    }

    /**
     * Scope token aktif berdasarkan flag + datetime expired.
     */
    public function scopeActive($query)
    {
        return $query
            ->where('is_expired', false)
            ->where(function ($builder) {
                $builder->whereNull('expired')
                    ->orWhere('expired', '>=', Carbon::now()->toDateTimeString());
            });
    }

    /**
     * Scope sesi login nyata (bukan token impersonate).
     */
    public function scopeLoginSessions($query)
    {
        return $query
            ->where('is_logged_in', true)
            ->where('is_impersonate', 0);
    }
}
