<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PenanggungJawabDokumenKaryawan extends Sector
{
    protected $table = 'penanggung_jawab_dokumen_karyawan';

    protected $guarded = [];

    public static function syncActive()
    {
        $currentId = static::query()
            ->whereNotNull('mulai_berlaku')
            ->whereDate('mulai_berlaku', '<=', Carbon::today()->toDateString())
            ->orderByDesc('mulai_berlaku')
            ->orderByDesc('id')
            ->value('id');

        DB::table('penanggung_jawab_dokumen_karyawan')->update(['is_active' => 0]);
        if ($currentId) {
            DB::table('penanggung_jawab_dokumen_karyawan')
                ->where('id', $currentId)
                ->update(['is_active' => 1]);
        }
    }
}
