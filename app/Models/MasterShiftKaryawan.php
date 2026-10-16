<?php

namespace App\Models;

class MasterShiftKaryawan extends Sector
{
    protected $table = 'master_shift_karyawan';

    protected $fillable = [
        'kode',
        'label',
        'time_in',
        'time_out',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    public function scopeActiveOrdered($query)
    {
        return $query->where('is_active', 1)->orderBy('sort_order');
    }

    /**
     * @return array<int, array{value: string, text: string, kode: string}>
     */
    public static function dropdownOptions(): array
    {
        return static::query()
            ->activeOrdered()
            ->get(['kode', 'label', 'time_in', 'time_out'])
            ->map(function ($row) {
                $timeIn = $row->time_in ? substr((string) $row->time_in, 0, 5) : '';
                $timeOut = $row->time_out ? substr((string) $row->time_out, 0, 5) : '';

                return [
                    'value' => trim("S {$row->kode} {$timeIn} {$timeOut}"),
                    'text' => $row->label ?: $row->kode,
                    'kode' => $row->kode,
                ];
            })
            ->values()
            ->all();
    }
}
