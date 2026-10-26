<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayProduksiConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use SoftDeletes;
    use UsesGreatdayProduksiConnection;

    protected $table = 'announcement';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function scopeVisibleTo($query, $karyawan)
    {
        $userId = (string) (is_object($karyawan) ? ($karyawan->id ?? '') : $karyawan);
        $departmentId = is_object($karyawan) ? ($karyawan->id_department ?? null) : null;
        $grade = is_object($karyawan) ? ($karyawan->grade ?? null) : null;
        $status = is_object($karyawan)
            ? ($karyawan->status_karyawan ?? $karyawan->status ?? null)
            : null;

        return $query->where(function ($q) use ($userId, $departmentId, $grade, $status) {
            $q->where('receiver_category', 'All')
                ->orWhereJsonContains('receiver_ids', $userId);

            if ($departmentId !== null && $departmentId !== '') {
                $q->orWhere(function ($inner) use ($departmentId) {
                    $inner->where('receiver_category', 'Divisi')
                        ->whereJsonContains('receiver_ids', (string) $departmentId);
                });
            }

            if ($grade !== null && $grade !== '') {
                $q->orWhere(function ($inner) use ($grade) {
                    $inner->where('receiver_category', 'Grade')
                        ->whereJsonContains('receiver_ids', (string) $grade);
                });
            }

            if ($status !== null && $status !== '') {
                $q->orWhere(function ($inner) use ($status) {
                    $inner->where('receiver_category', 'Status')
                        ->whereJsonContains('receiver_ids', (string) $status);
                });
            }
        });
    }
}
