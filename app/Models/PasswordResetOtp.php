<?php

namespace App\Models;

class PasswordResetOtp extends Sector
{
    protected $table = 'password_reset_otps';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'email',
        'otp_hash',
        'attempts',
        'is_used',
        'expires_at',
        'created_at',
        'used_at',
        'ip_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
