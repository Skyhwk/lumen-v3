<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LogReminderSamplingCustomer extends Model
{
    protected $table = 'log_reminder_sampling_customer';

    protected $fillable = [
        'run_id',
        'mode',
        'is_dry_run',
        'target_date',
        'jadwal_tanggal',
        'no_quotation',
        'no_order',
        'batch_id',
        'nama_perusahaan',
        'subject',
        'fingerprint',
        'status',
        'email_status',
        'portal_status',
        'email_to',
        'portal_users',
        'error_message',
        'meta',
    ];

    protected $casts = [
        'is_dry_run' => 'boolean',
        'target_date' => 'date',
        'jadwal_tanggal' => 'date',
        'email_to' => 'array',
        'portal_users' => 'array',
        'meta' => 'array',
    ];

    /**
     * Hapus log yang sudah lebih dari $months bulan (default 3).
     */
    public static function purgeOlderThanMonths(int $months = 3): int
    {
        $cutoff = Carbon::now('Asia/Jakarta')->subMonths(max(1, $months))->startOfDay();

        return static::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
