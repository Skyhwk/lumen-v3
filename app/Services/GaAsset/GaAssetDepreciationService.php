<?php

namespace App\Services\GaAsset;

use App\Models\GaAsset;
use App\Models\GaAssetUsefulLifeRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GaAssetDepreciationService
{
    /** @var array{exact: array<string, int>, category: array<string, int>}|null */
    private $ruleMap = null;

    public function project($cost, int $lifeYears, int $purchaseYear, int $fiscalYear): ?array
    {
        if ($lifeYears < 1 || $purchaseYear < 1900 || $fiscalYear < $purchaseYear) {
            return null;
        }
        if (!is_numeric($cost) || (float) $cost < 0) {
            return null;
        }

        $cost = $this->money($cost);
        $elapsed = $fiscalYear - $purchaseYear + 1;
        $annual = $this->div($cost, $lifeYears);

        if ($elapsed > $lifeYears) {
            $accumulated = $cost;
            $charge = '0.00';
        } else {
            $accumulated = $this->mul($annual, $elapsed);
            if ($elapsed === $lifeYears || $this->cmp($accumulated, $cost) === 1) {
                $accumulated = $cost;
            }
            $previous = $elapsed === 1 ? '0.00' : $this->mul($annual, $elapsed - 1);
            if ($this->cmp($previous, $cost) === 1) {
                $previous = $cost;
            }
            $charge = $this->sub($accumulated, $previous);
        }

        return [
            'annual_depreciation' => $charge,
            'accumulated_depreciation' => $accumulated,
            'book_value' => $this->sub($cost, $accumulated),
        ];
    }

    public function syncAsset(GaAsset $asset, ?int $throughYear = null): void
    {
        $throughYear = $throughYear ?: (int) date('Y');
        $asset->loadMissing(['kategori:id,nama_kategori', 'subKategori:id,nama_sub_kategori']);

        $rows = $this->rowsForAsset($asset, $throughYear);
        $now = Carbon::now()->toDateTimeString();

        DB::transaction(function () use ($asset, $rows, $now) {
            DB::table('ga_asset_depreciation_yearly')->where('asset_id', $asset->id)->delete();
            if ($rows === []) {
                return;
            }

            foreach ($rows as &$row) {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
            }
            unset($row);

            DB::table('ga_asset_depreciation_yearly')->insert($rows);
        });
    }

    public function rebuild(?int $throughYear = null): array
    {
        $throughYear = $throughYear ?: (int) date('Y');
        $this->ruleMap = null;

        $stats = [
            'through_year' => $throughYear,
            'assets_seen' => 0,
            'assets_snapshotted' => 0,
            'assets_skipped' => 0,
            'rows_written' => 0,
        ];

        DB::table('ga_asset_depreciation_yearly')->delete();

        $now = Carbon::now()->toDateTimeString();
        $buffer = [];
        $flushEvery = 2000;

        $flushBuffer = function () use (&$buffer) {
            if ($buffer === []) {
                return;
            }
            foreach (array_chunk($buffer, 500) as $chunk) {
                DB::table('ga_asset_depreciation_yearly')->insert($chunk);
            }
            $buffer = [];
        };

        GaAsset::query()
            ->where('is_active', 1)
            ->whereNotNull('purchase_date')
            ->whereNotNull('acquisition_cost')
            ->with(['kategori:id,nama_kategori', 'subKategori:id,nama_sub_kategori'])
            ->orderBy('id')
            ->chunkById(500, function ($assets) use ($throughYear, &$stats, &$buffer, $flushEvery, $flushBuffer, $now) {
                foreach ($assets as $asset) {
                    $stats['assets_seen']++;
                    $rows = $this->rowsForAsset($asset, $throughYear);
                    if ($rows === []) {
                        $stats['assets_skipped']++;
                        continue;
                    }
                    $stats['assets_snapshotted']++;
                    foreach ($rows as $row) {
                        $row['created_at'] = $now;
                        $row['updated_at'] = $now;
                        $buffer[] = $row;
                        $stats['rows_written']++;
                    }
                    if (count($buffer) >= $flushEvery) {
                        $flushBuffer();
                    }
                }
            });

        $flushBuffer();

        return $stats;
    }

    public function report(int $year): array
    {
        $assets = DB::table('ga_assets as a')
            ->leftJoin('ga_asset_depreciation_yearly as d', function ($join) use ($year) {
                $join->on('d.asset_id', '=', 'a.id')->where('d.fiscal_year', '=', $year);
            })
            ->leftJoin('master_kategori_aset as k', 'k.id', '=', 'a.kategori_aset_id')
            ->leftJoin('master_sub_kategori_aset as s', 's.id', '=', 'a.sub_kategori_aset_id')
            ->where('a.is_active', 1)
            ->get([
                'k.nama_kategori as category',
                's.nama_sub_kategori as sub_category',
                'a.acquisition_cost',
                'a.purchase_date',
                'a.condition',
                'd.annual_depreciation',
                'd.accumulated_depreciation',
                'd.book_value',
            ]);

        $groups = [];
        foreach ($assets as $asset) {
            $key = ($asset->category ?: 'TANPA KATEGORI') . "\n" . ($asset->sub_category ?: '-');
            if (!isset($groups[$key])) {
                $groups[$key] = $this->emptyTotalRow($asset->category ?: 'TANPA KATEGORI', $asset->sub_category ?: '-');
            }
            $this->accumulateTotalRow($groups[$key], $asset, $year);
        }

        ksort($groups);
        $rows = array_values($groups);
        $totals = $this->emptyTotalRow('Total', '');
        foreach ($rows as $row) {
            $this->addTotalRow($totals, $row);
        }
        unset($totals['category'], $totals['sub_category']);

        return [
            'year' => $year,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    public function quantityMatrix(): array
    {
        $rows = DB::table('ga_assets')
            ->where('is_active', 1)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->selectRaw('name, `condition`, acquisition_origin, COUNT(*) as total')
            ->groupBy('name', 'condition', 'acquisition_origin')
            ->orderBy('name')
            ->get();

        $matrix = [];
        foreach ($rows as $row) {
            $name = $row->name;
            if (!isset($matrix[$name])) {
                $matrix[$name] = [
                    'name' => $name,
                    'total' => 0,
                    'normal' => 0,
                    'kendala' => 0,
                    'rusak' => 0,
                    'hilang' => 0,
                    'buang' => 0,
                    'transfer' => 0,
                    'unknown' => 0,
                    'new' => 0,
                    'second' => 0,
                    'take_over' => 0,
                ];
            }
            $count = (int) $row->total;
            $matrix[$name]['total'] += $count;
            $bucket = $this->conditionBucket($row->condition);
            $matrix[$name][$bucket] += $count;
            if ($row->acquisition_origin === 'new') {
                $matrix[$name]['new'] += $count;
            } elseif ($row->acquisition_origin === 'second') {
                $matrix[$name]['second'] += $count;
            } elseif ($row->acquisition_origin === 'take_over') {
                $matrix[$name]['take_over'] += $count;
            }
        }

        $list = array_values($matrix);
        $totals = [
            'name' => 'Total',
            'total' => 0,
            'normal' => 0,
            'kendala' => 0,
            'rusak' => 0,
            'hilang' => 0,
            'buang' => 0,
            'transfer' => 0,
            'unknown' => 0,
            'new' => 0,
            'second' => 0,
            'take_over' => 0,
        ];
        foreach ($list as $row) {
            foreach ($totals as $key => $value) {
                if ($key === 'name') {
                    continue;
                }
                $totals[$key] += $row[$key];
            }
        }

        return ['rows' => $list, 'totals' => $totals];
    }

    private function emptyTotalRow(string $category, string $subCategory): array
    {
        return [
            'category' => $category,
            'sub_category' => $subCategory,
            'asset_count' => 0,
            'acquisition_cost' => '0.00',
            'nilai_awal' => '0.00',
            'pembelian' => '0.00',
            'penjualan' => '0.00',
            'rusak_tak_terpakai' => '0.00',
            'hilang' => '0.00',
            'transfer' => '0.00',
            'nilai_perolehan' => '0.00',
            'annual_depreciation' => '0.00',
            'accumulated_depreciation' => '0.00',
            'book_value' => '0.00',
        ];
    }

    private function accumulateTotalRow(array &$row, $asset, int $year): void
    {
        $cost = $this->money($asset->acquisition_cost ?? 0);
        $purchaseYear = $asset->purchase_date ? (int) substr((string) $asset->purchase_date, 0, 4) : 0;
        $bucket = self::writeOffBucket($asset->condition);
        $row['asset_count']++;
        $row['acquisition_cost'] = $this->add($row['acquisition_cost'], $cost);

        if ($purchaseYear > 0 && $purchaseYear < $year) {
            $row['nilai_awal'] = $this->add($row['nilai_awal'], $cost);
        } elseif ($purchaseYear === $year) {
            $row['pembelian'] = $this->add($row['pembelian'], $cost);
        }

        if ($bucket === 'hilang') {
            $row['hilang'] = $this->add($row['hilang'], $cost);
        } elseif ($bucket === 'transfer') {
            $row['transfer'] = $this->add($row['transfer'], $cost);
        } elseif ($bucket === 'rusak') {
            $row['rusak_tak_terpakai'] = $this->add($row['rusak_tak_terpakai'], $cost);
        } else {
            $row['annual_depreciation'] = $this->add($row['annual_depreciation'], $asset->annual_depreciation ?? 0);
            $row['accumulated_depreciation'] = $this->add($row['accumulated_depreciation'], $asset->accumulated_depreciation ?? 0);
            $row['book_value'] = $this->add($row['book_value'], $asset->book_value ?? 0);
        }

        $row['nilai_perolehan'] = $this->sub(
            $this->sub(
                $this->sub(
                    $this->add($row['nilai_awal'], $row['pembelian']),
                    $row['penjualan']
                ),
                $row['rusak_tak_terpakai']
            ),
            $this->add($row['hilang'], $row['transfer'])
        );
    }

    private function addTotalRow(array &$totals, array $row): void
    {
        $totals['asset_count'] += $row['asset_count'];
        foreach (['acquisition_cost', 'nilai_awal', 'pembelian', 'penjualan', 'rusak_tak_terpakai', 'hilang', 'transfer', 'nilai_perolehan', 'annual_depreciation', 'accumulated_depreciation', 'book_value'] as $key) {
            $totals[$key] = $this->add($totals[$key], $row[$key]);
        }
    }

    private function conditionBucket(?string $condition): string
    {
        if (in_array($condition, ['normal'], true)) {
            return 'normal';
        }
        if (in_array($condition, ['kendala', 'issue'], true)) {
            return 'kendala';
        }
        if (in_array($condition, ['rusak', 'damaged'], true)) {
            return 'rusak';
        }
        if (in_array($condition, ['hilang', 'missing'], true)) {
            return 'hilang';
        }
        if (in_array($condition, ['rusak_berat', 'heavy_damage'], true)) {
            return 'buang';
        }
        if ($condition === 'transfer') {
            return 'transfer';
        }

        return 'unknown';
    }

    public function resolveUsefulLife(GaAsset $asset): ?array
    {
        $own = (int) $asset->useful_life_years;
        if ($own > 0) {
            return ['years' => $own, 'source' => 'asset'];
        }

        $rules = $this->rules();
        $category = $this->normalize((string) optional($asset->kategori)->nama_kategori);
        $sub = $this->normalize((string) optional($asset->subKategori)->nama_sub_kategori);
        if ($category === '') {
            return null;
        }

        if ($sub !== '' && isset($rules['exact'][$category . '|' . $sub])) {
            return ['years' => $rules['exact'][$category . '|' . $sub], 'source' => 'rule'];
        }
        if (isset($rules['category'][$category])) {
            return ['years' => $rules['category'][$category], 'source' => 'rule'];
        }

        return null;
    }

    private function rowsForAsset(GaAsset $asset, int $throughYear): array
    {
        if ($asset->acquisition_cost === null || $asset->purchase_date === null) {
            return [];
        }

        $life = $this->resolveUsefulLife($asset);
        if (!$life) {
            return [];
        }

        $purchaseYear = (int) substr((string) $asset->purchase_date, 0, 4);
        if ($purchaseYear < 1900) {
            return [];
        }

        $rows = [];
        for ($year = $purchaseYear; $year <= $throughYear; $year++) {
            $projected = $this->project($asset->acquisition_cost, $life['years'], $purchaseYear, $year);
            if (!$projected) {
                continue;
            }
            $rows[] = array_merge($projected, [
                'asset_id' => (int) $asset->id,
                'fiscal_year' => $year,
                'acquisition_cost' => $this->money($asset->acquisition_cost),
                'useful_life_years' => $life['years'],
                'useful_life_source' => $life['source'],
            ]);
        }

        if ($rows !== [] && self::isWrittenOff($asset->condition)) {
            $last = count($rows) - 1;
            $cost = $this->money($asset->acquisition_cost);
            $previous = $last === 0 ? '0.00' : $rows[$last - 1]['accumulated_depreciation'];
            $rows[$last]['accumulated_depreciation'] = $cost;
            $rows[$last]['book_value'] = '0.00';
            $rows[$last]['annual_depreciation'] = $this->sub($cost, $previous);
        }

        return $rows;
    }

    public static function isWrittenOff(?string $condition): bool
    {
        return in_array($condition, ['hilang', 'missing', 'transfer', 'rusak_berat', 'heavy_damage'], true);
    }

    public static function writeOffBucket(?string $condition): ?string
    {
        if (in_array($condition, ['hilang', 'missing'], true)) {
            return 'hilang';
        }
        if ($condition === 'transfer') {
            return 'transfer';
        }
        if (in_array($condition, ['rusak_berat', 'heavy_damage'], true)) {
            return 'rusak';
        }

        return null;
    }

    private function replaceRows(int $assetId, array $rows): void
    {
        $now = Carbon::now()->toDateTimeString();
        DB::transaction(function () use ($assetId, $rows, $now) {
            DB::table('ga_asset_depreciation_yearly')->where('asset_id', $assetId)->delete();
            if ($rows === []) {
                return;
            }
            foreach ($rows as &$row) {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
            }
            unset($row);
            DB::table('ga_asset_depreciation_yearly')->insert($rows);
        });
    }

    private function rules(): array
    {
        if ($this->ruleMap !== null) {
            return $this->ruleMap;
        }

        $map = ['exact' => [], 'category' => []];
        $rules = GaAssetUsefulLifeRule::query()->where('source', 'category')->get();
        foreach ($rules as $rule) {
            $category = $this->normalize($rule->category_name);
            $sub = $this->normalize($rule->sub_category_name);
            if ($category === '') {
                continue;
            }
            if ($sub === '') {
                $map['category'][$category] = (int) $rule->useful_life_years;
            } else {
                $map['exact'][$category . '|' . $sub] = (int) $rule->useful_life_years;
            }
        }

        return $this->ruleMap = $map;
    }

    private function normalize(string $value): string
    {
        $value = strtoupper(trim(preg_replace('/\s+/', ' ', $value)));

        return $value;
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function div(string $amount, int $divisor): string
    {
        if (function_exists('bcdiv')) {
            return bcdiv($amount, (string) $divisor, 2);
        }

        return number_format(((float) $amount) / $divisor, 2, '.', '');
    }

    private function mul(string $amount, int $factor): string
    {
        if (function_exists('bcmul')) {
            return bcmul($amount, (string) $factor, 2);
        }

        return number_format(((float) $amount) * $factor, 2, '.', '');
    }

    private function add(string $left, $right): string
    {
        $right = $this->money($right);
        if (function_exists('bcadd')) {
            return bcadd($left, $right, 2);
        }

        return number_format(((float) $left) + ((float) $right), 2, '.', '');
    }

    private function sub(string $left, string $right): string
    {
        if (function_exists('bcsub')) {
            return bcsub($left, $right, 2);
        }

        return number_format(((float) $left) - ((float) $right), 2, '.', '');
    }

    private function cmp(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, 2);
        }

        $diff = ((float) $left) - ((float) $right);
        if (abs($diff) < 0.001) {
            return 0;
        }

        return $diff > 0 ? 1 : -1;
    }
}
