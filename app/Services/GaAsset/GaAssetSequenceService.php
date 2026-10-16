<?php

namespace App\Services\GaAsset;

use App\Models\GaAsset;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GaAssetSequenceService
{
    public function peekNext(int $subCategoryId, string $subCategoryName): int
    {
        $existingMax = $this->existingCsMax($subCategoryName);
        if ($subCategoryId > 0) {
            $sequence = $this->sync($subCategoryId, $subCategoryName, Carbon::now(), false);
            $existingMax = max($existingMax, (int) $sequence->last_number);
        }

        return $existingMax + 1;
    }

    public function claimNext(int $subCategoryId, string $subCategoryName, ?Carbon $now = null): int
    {
        $now = $now ?: Carbon::now();
        $lockName = $this->lockName($subCategoryName);
        $this->acquireLock($lockName);

        try {
            $sequence = $this->sync($subCategoryId, $subCategoryName, $now, true);
            $nextNumber = max((int) $sequence->last_number, $this->existingCsMax($subCategoryName)) + 1;
            while (GaAsset::where('cs_code', $this->formatCsCode($subCategoryName, $nextNumber))->exists()) {
                $nextNumber++;
            }

            $this->raiseNameTo($subCategoryName, $nextNumber, $now, $subCategoryId);

            return $nextNumber;
        } finally {
            $this->releaseLock($lockName);
        }
    }

    public function syncFromCsCode(string $csCode, ?int $subCategoryId = null, ?string $subCategoryName = null, ?Carbon $now = null): void
    {
        $parsed = $this->parseCsCode($csCode);
        if (!$parsed) {
            return;
        }

        $now = $now ?: Carbon::now();
        $this->raiseNameTo($parsed['name'], $parsed['number'], $now, $subCategoryId);

        if ($subCategoryId && $subCategoryName && $this->normalizeName($subCategoryName) === $parsed['name']) {
            $this->raiseTo($subCategoryId, $parsed['number'], $now, false);
        }
    }

    public function formatCsCode(string $subCategoryName, int $number): string
    {
        return $this->csCodePrefix($subCategoryName) . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    public function existingCsMax(string $subCategoryName): int
    {
        $prefix = $this->csCodePrefix($subCategoryName);

        return (int) DB::table('ga_assets')
            ->where('cs_code', 'like', $prefix . '%')
            ->max(DB::raw("CAST(SUBSTRING_INDEX(cs_code, '-', -1) AS UNSIGNED)"));
    }

    private function sync(int $subCategoryId, string $subCategoryName, Carbon $now, bool $lockForUpdate)
    {
        $existingMax = $this->existingCsMax($subCategoryName);
        $this->raiseTo($subCategoryId, $existingMax, $now, $lockForUpdate);

        $query = DB::table('ga_asset_sequences')->where('sub_kategori_aset_id', $subCategoryId);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }
        $sequence = $query->first();
        if (!$sequence) {
            throw new \RuntimeException('Gagal menyimpan sequence NO CS.');
        }

        return $sequence;
    }

    private function raiseNameTo(string $subCategoryName, int $number, Carbon $now, ?int $preferredId = null): void
    {
        $name = $this->normalizeName($subCategoryName);
        $ids = DB::table('master_sub_kategori_aset')
            ->whereRaw('UPPER(TRIM(nama_sub_kategori)) = ?', [$name])
            ->pluck('id');

        if ($preferredId && !$ids->contains($preferredId)) {
            $ids->push($preferredId);
        }

        foreach ($ids as $id) {
            $this->raiseTo((int) $id, $number, $now, false);
        }
    }

    private function raiseTo(int $subCategoryId, int $number, Carbon $now, bool $lockForUpdate): void
    {
        DB::table('ga_asset_sequences')->insertOrIgnore([
            'sub_kategori_aset_id' => $subCategoryId,
            'last_number' => $number,
            'updated_at' => $now,
        ]);

        $query = DB::table('ga_asset_sequences')->where('sub_kategori_aset_id', $subCategoryId);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }
        $sequence = $query->first();
        if (!$sequence) {
            return;
        }

        if ((int) $sequence->last_number < $number) {
            DB::table('ga_asset_sequences')->where('id', $sequence->id)->update([
                'last_number' => $number,
                'updated_at' => $now,
            ]);
        }
    }

    private function parseCsCode(string $csCode): ?array
    {
        if (!preg_match('/^CS-(.+)-(\d+)$/i', trim($csCode), $match)) {
            return null;
        }

        return [
            'name' => $this->normalizeName($match[1]),
            'number' => (int) $match[2],
        ];
    }

    private function csCodePrefix(string $subCategoryName): string
    {
        return 'CS-' . $this->normalizeName($subCategoryName) . '-';
    }

    private function normalizeName(string $name): string
    {
        $name = strtoupper(trim(preg_replace('/\s+/', ' ', $name)));
        return $name === '' ? 'ASET' : $name;
    }

    public function holdNameLock(string $subCategoryName): string
    {
        $lockName = $this->lockName($subCategoryName);
        $this->acquireLock($lockName);

        return $lockName;
    }

    public function releaseNameLock(string $lockName): void
    {
        $this->releaseLock($lockName);
    }

    private function lockName(string $subCategoryName): string
    {
        $normalized = $this->normalizeName($subCategoryName);
        if (strlen($normalized) > 50) {
            $normalized = md5($normalized);
        }

        return 'ga-cs-' . $normalized;
    }

    private function acquireLock(string $lockName): void
    {
        $lockResult = DB::select('SELECT GET_LOCK(?, 30) AS acquired', [$lockName]);
        $lockAcquired = isset($lockResult[0]) && (int) $lockResult[0]->acquired === 1;
        if (!$lockAcquired) {
            throw new \RuntimeException('Gagal mengunci sequence NO CS: ' . $lockName);
        }
    }

    private function releaseLock(string $lockName): void
    {
        DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
    }
}
