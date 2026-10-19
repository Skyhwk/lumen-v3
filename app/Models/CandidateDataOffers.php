<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CandidateDataOffers extends Model
{
    protected $table = 'candidate_data_offers';
    protected $guarded = [];

    protected $casts = [
        'tanggal_mulai_kerja_history' => 'array',
    ];

    public function newRecruitment()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }

    public static function hasTanggalMulaiKerjaHistoryColumn(): bool
    {
        return Schema::hasTable((new static)->getTable())
            && Schema::hasColumn((new static)->getTable(), 'tanggal_mulai_kerja_history');
    }

    public static function normalizeDateOnly($value): ?string
    {
        return RecruitmentInterview::normalizeTanggalJoin($value);
    }

    public static function appendTanggalMulaiKerjaHistory(
        int $recruitmentId,
        $oldDate,
        $newDate,
        string $source,
        ?string $by = null
    ): void {
        if (!self::hasTanggalMulaiKerjaHistoryColumn()) {
            return;
        }

        $lama = self::normalizeDateOnly($oldDate);
        $baru = self::normalizeDateOnly($newDate);
        if ($baru === null || $lama === $baru) {
            return;
        }

        $cdo = self::where('new_recruitment_id', $recruitmentId)->first();
        $history = [];
        if ($cdo && !empty($cdo->tanggal_mulai_kerja_history)) {
            $history = is_array($cdo->tanggal_mulai_kerja_history)
                ? $cdo->tanggal_mulai_kerja_history
                : (json_decode($cdo->tanggal_mulai_kerja_history, true) ?: []);
        }
        if (!is_array($history)) {
            $history = [];
        }

        $history[] = [
            'source' => $source,
            'changed_at' => Carbon::now()->toDateString(),
            'changed_by' => $by,
            'tanggal_mulai_kerja_baru' => $baru,
            'tanggal_mulai_kerja_lama' => $lama,
        ];

        if (!$cdo) {
            self::create([
                'new_recruitment_id' => $recruitmentId,
                'tanggal_mulai_kerja_history' => $history,
                'created_by' => $by,
                'updated_by' => $by,
            ]);
            return;
        }

        $cdo->update([
            'tanggal_mulai_kerja_history' => $history,
            'updated_by' => $by,
        ]);
    }
}
