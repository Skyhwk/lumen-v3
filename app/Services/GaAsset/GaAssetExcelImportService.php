<?php

namespace App\Services\GaAsset;

use App\Models\GaAsset;
use App\Models\GaAssetCabangAlias;
use App\Models\GaAssetImportBatch;
use App\Models\GaAssetImportError;
use App\Models\GaAssetLocation;
use App\Models\GaAssetRoom;
use App\Models\MasterCabang;
use App\Models\MasterKategoriAset;
use App\Models\MasterSubKategoriAset;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GaAssetExcelImportService
{
    public const DATA_SHEET = 'DATA';

    public const COL_CAT1 = 2;
    public const COL_CAT2 = 3;
    public const COL_ASSET_CODE = 4;
    public const COL_DAY = 5;
    public const COL_MONTH = 6;
    public const COL_YEAR = 7;
    public const COL_ASSET_NAME = 9;
    public const COL_BRAND = 10;
    public const COL_SPEC = 11;
    public const COL_ACQ_STATUS = 12;
    public const COL_LABEL = 13;
    public const COL_FLAG_N = 14;
    public const COL_FLAG_K = 15;
    public const COL_FLAG_R = 16;
    public const COL_FLAG_RB = 17;
    public const COL_FLAG_H = 18;
    public const COL_FLAG_T = 19;
    public const COL_ROOM = 20;
    public const COL_LOKASI = 21;
    public const COL_CONDITION_TEXT = 22;
    public const COL_DAMAGE_DAY = 23;
    public const COL_DAMAGE_MONTH = 24;
    public const COL_DAMAGE_YEAR = 25;
    public const COL_MOVE_ROOM = 26;
    public const COL_MOVE_DATE = 27;
    public const COL_COST = 28;
    public const COL_ACCURATE = 29;
    public const COL_USEFUL_LIFE = 30;

    /** @var array<string, int> */
    private $cabangAliasMap = [];

    /** @var array<string, MasterKategoriAset> */
    private $kategoriCache = [];

    /** @var array<string, MasterSubKategoriAset> */
    private $subKategoriCache = [];

    /** @var array<string, GaAssetLocation> */
    private $locationCache = [];

    /** @var array<string, GaAssetRoom> */
    private $roomCache = [];

    /** @var array<int, int> */
    private $sequenceCounters = [];

    public function importFromPath(
        string $path,
        string $originalFilename,
        bool $commit,
        ?int $userId,
        ?string $userName,
        string $mode = 'full',
        bool $skipDepreciation = false
    ): array {
        if ($mode === 'financial') {
            return $this->importFinancialFromPath(
                $path,
                $originalFilename,
                $commit,
                $userId,
                $userName,
                $skipDepreciation
            );
        }

        $this->warmCaches();
        $actorName = $userName ?: 'System';

        [$sheet, $sheetName] = $this->loadDataSheet($path);

        $rowLimit = (int) $sheet->getHighestDataRow();
        $stats = [
            'rows_total' => 0,
            'rows_imported' => 0,
            'rows_updated' => 0,
            'rows_skipped' => 0,
            'rows_failed' => 0,
            'errors' => [],
        ];

        $batch = null;
        if ($commit) {
            $batch = GaAssetImportBatch::create([
                'filename' => $originalFilename,
                'sheet_name' => $sheetName,
                'is_committed' => false,
                'created_by' => $userId,
                'created_by_name' => $actorName,
            ]);
        }

        $callback = function () use ($sheet, $rowLimit, $commit, $batch, $actorName, &$stats) {
            for ($row = 3; $row <= $rowLimit; $row++) {
                $assetCode = $this->cellString($sheet, $row, self::COL_ASSET_CODE);
                if ($assetCode === '') {
                    continue;
                }

                $stats['rows_total']++;

                try {
                    $payload = $this->mapRow($sheet, $row, $actorName);
                    if ($payload === null) {
                        $stats['rows_skipped']++;
                        continue;
                    }

                    if (!$commit) {
                        $existing = GaAsset::where('cs_code', $payload['cs_code'])
                            ->orWhere('asset_code', $payload['asset_code'])
                            ->exists();
                        if ($existing) {
                            $stats['rows_updated']++;
                        } else {
                            $stats['rows_imported']++;
                        }
                        continue;
                    }

                    $existing = GaAsset::where('cs_code', $payload['cs_code'])->first();
                    if (!$existing) {
                        $existing = GaAsset::where('asset_code', $payload['asset_code'])->first();
                    }

                    $payload['updated_by'] = $actorName;

                    $movement = $payload['_movement'] ?? null;
                    unset($payload['_movement']);

                    if ($existing) {
                        unset(
                            $payload['public_id'],
                            $payload['cs_sequence'],
                            $payload['kategori_aset_id'],
                            $payload['sub_kategori_aset_id']
                        );
                        $existing->update($payload);
                        $this->syncSequenceFromPayload($payload, $existing);
                        $this->recordImportedMovement($existing, $movement, $actorName);
                        $stats['rows_updated']++;
                    } else {
                        $payload['created_by'] = $actorName;
                        $created = GaAsset::create($payload);
                        $this->syncSequenceFromPayload($payload, $created);
                        $this->recordImportedMovement($created, $movement, $actorName);
                        $stats['rows_imported']++;
                    }
                } catch (\Throwable $e) {
                    $stats['rows_failed']++;
                    $message = $e->getMessage();
                    $stats['errors'][] = [
                        'row' => $row,
                        'asset_code' => $assetCode,
                        'message' => $message,
                    ];
                    if ($batch) {
                        GaAssetImportError::create([
                            'batch_id' => $batch->id,
                            'row_number' => $row,
                            'asset_code' => $assetCode,
                            'message' => $message,
                        ]);
                    }
                }
            }
        };

        if ($commit) {
            DB::transaction($callback);
            $batch->update([
                'is_committed' => true,
                'rows_total' => $stats['rows_total'],
                'rows_imported' => $stats['rows_imported'],
                'rows_updated' => $stats['rows_updated'],
                'rows_skipped' => $stats['rows_skipped'],
                'rows_failed' => $stats['rows_failed'],
            ]);
            try {
                $stats['depreciation'] = app(GaAssetDepreciationService::class)->rebuild();
            } catch (\Throwable $e) {
                $stats['depreciation_error'] = $e->getMessage();
            }
        } else {
            $callback();
        }

        return [
            'dry_run' => !$commit,
            'sheet_name' => $sheetName,
            'filename' => $originalFilename,
            'stats' => [
                'rows_total' => $stats['rows_total'],
                'rows_imported' => $stats['rows_imported'],
                'rows_updated' => $stats['rows_updated'],
                'rows_skipped' => $stats['rows_skipped'],
                'rows_failed' => $stats['rows_failed'],
            ],
            'errors' => array_slice($stats['errors'], 0, 200),
            'errors_truncated' => count($stats['errors']) > 200,
            'batch_id' => $batch ? $batch->id : null,
            'mode' => 'full',
        ];
    }

    /**
     * Update harga, tanggal beli, dan umur manfaat saja (cocok untuk import ulang). Jauh lebih cepat dari import penuh.
     */
    public function importFinancialFromPath(
        string $path,
        string $originalFilename,
        bool $commit,
        ?int $userId,
        ?string $userName,
        bool $skipDepreciation = false
    ): array {
        $actorName = $userName ?: 'System';
        [$sheet, $sheetName] = $this->loadDataSheet($path);
        $rowLimit = (int) $sheet->getHighestDataRow();

        $stats = [
            'rows_total' => 0,
            'rows_imported' => 0,
            'rows_updated' => 0,
            'rows_skipped' => 0,
            'rows_failed' => 0,
            'rows_not_found' => 0,
            'errors' => [],
        ];

        $assetIdsByCode = $this->assetIdIndexByCode();
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $pending = [];
        $flushSize = 300;

        $flush = function () use (&$pending, $commit, $actorName, $now, &$stats) {
            if ($pending === [] || !$commit) {
                $pending = [];
                return;
            }
            DB::transaction(function () use ($pending, $actorName, $now, &$stats) {
                foreach ($pending as $row) {
                    DB::table('ga_assets')
                        ->where('id', $row['id'])
                        ->update([
                            'purchase_date' => $row['purchase_date'],
                            'acquisition_cost' => $row['acquisition_cost'],
                            'useful_life_years' => $row['useful_life_years'],
                            'updated_by' => $actorName,
                            'updated_at' => $now,
                        ]);
                    $stats['rows_updated']++;
                }
            });
            $pending = [];
        };

        $batch = null;
        if ($commit) {
            $batch = GaAssetImportBatch::create([
                'filename' => $originalFilename,
                'sheet_name' => $sheetName,
                'is_committed' => false,
                'created_by' => $userId,
                'created_by_name' => $actorName,
            ]);
        }

        for ($row = 3; $row <= $rowLimit; $row++) {
            $assetCode = $this->cellString($sheet, $row, self::COL_ASSET_CODE);
            if ($assetCode === '') {
                continue;
            }

            $stats['rows_total']++;
            $lookup = strtoupper($assetCode);

            try {
                $assetId = $assetIdsByCode[$lookup] ?? null;
                if (!$assetId) {
                    $stats['rows_not_found']++;
                    continue;
                }

                $purchaseDate = $this->mapPurchaseDate($sheet, $row);
                $cost = $this->mapMoney($sheet, $row, self::COL_COST);
                $life = $this->mapUsefulLife($sheet, $row);

                if ($purchaseDate === null && $cost === null && $life === null) {
                    $stats['rows_skipped']++;
                    continue;
                }

                if (!$commit) {
                    $stats['rows_updated']++;
                    continue;
                }

                $pending[] = [
                    'id' => $assetId,
                    'purchase_date' => $purchaseDate,
                    'acquisition_cost' => $cost,
                    'useful_life_years' => $life,
                ];

                if (count($pending) >= $flushSize) {
                    $flush();
                }
            } catch (\Throwable $e) {
                $stats['rows_failed']++;
                $stats['errors'][] = [
                    'row' => $row,
                    'asset_code' => $assetCode,
                    'message' => $e->getMessage(),
                ];
                if ($batch) {
                    GaAssetImportError::create([
                        'batch_id' => $batch->id,
                        'row_number' => $row,
                        'asset_code' => $assetCode,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $flush();

        if ($commit && $batch) {
            $batch->update([
                'is_committed' => true,
                'rows_total' => $stats['rows_total'],
                'rows_imported' => 0,
                'rows_updated' => $stats['rows_updated'],
                'rows_skipped' => $stats['rows_skipped'],
                'rows_failed' => $stats['rows_failed'],
            ]);
            if (!$skipDepreciation) {
                try {
                    $stats['depreciation'] = app(GaAssetDepreciationService::class)->rebuild();
                } catch (\Throwable $e) {
                    $stats['depreciation_error'] = $e->getMessage();
                }
            } else {
                $stats['depreciation_skipped'] = true;
            }
        }

        return [
            'dry_run' => !$commit,
            'sheet_name' => $sheetName,
            'filename' => $originalFilename,
            'mode' => 'financial',
            'stats' => [
                'rows_total' => $stats['rows_total'],
                'rows_imported' => 0,
                'rows_updated' => $stats['rows_updated'],
                'rows_skipped' => $stats['rows_skipped'],
                'rows_failed' => $stats['rows_failed'],
                'rows_not_found' => $stats['rows_not_found'],
                'depreciation' => $stats['depreciation'] ?? null,
                'depreciation_error' => $stats['depreciation_error'] ?? null,
                'depreciation_skipped' => !empty($stats['depreciation_skipped']),
            ],
            'errors' => array_slice($stats['errors'], 0, 200),
            'errors_truncated' => count($stats['errors']) > 200,
            'batch_id' => $batch ? $batch->id : null,
        ];
    }

    /** @return array{0:\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet,1:string} */
    private function loadDataSheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly([self::DATA_SHEET]);
        }
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName(self::DATA_SHEET);
        if ($sheet === null) {
            $sheet = $spreadsheet->getActiveSheet();
        }

        return [$sheet, $sheet->getTitle()];
    }

    /** @return array<string, int> */
    private function assetIdIndexByCode(): array
    {
        $index = [];
        $rows = DB::table('ga_assets')
            ->where('is_active', 1)
            ->get(['id', 'cs_code', 'asset_code']);

        foreach ($rows as $row) {
            foreach (['cs_code', 'asset_code'] as $column) {
                $code = strtoupper(trim((string) $row->{$column}));
                if ($code !== '') {
                    $index[$code] = (int) $row->id;
                }
            }
        }

        return $index;
    }

    private function warmCaches(): void
    {
        $this->cabangAliasMap = GaAssetCabangAlias::query()
            ->pluck('id_cabang', 'alias')
            ->mapWithKeys(function ($id, $alias) {
                return [strtoupper(trim($alias)) => (int) $id];
            })
            ->all();

        foreach (MasterCabang::query()->get(['id', 'nama_cabang', 'kode_cabang']) as $cabang) {
            $this->cabangAliasMap[strtoupper(trim($cabang->nama_cabang))] = (int) $cabang->id;
            if ($cabang->kode_cabang) {
                $this->cabangAliasMap[strtoupper(trim($cabang->kode_cabang))] = (int) $cabang->id;
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapRow($sheet, int $row, string $actorName): ?array
    {
        $csCode = $this->cellString($sheet, $row, self::COL_ASSET_CODE);
        if ($csCode === '') {
            return null;
        }

        $cat1Name = $this->normalizeName($this->cellString($sheet, $row, self::COL_CAT1));
        $cat2Name = $this->normalizeName($this->cellString($sheet, $row, self::COL_CAT2));
        if ($cat1Name === '') {
            $cat1Name = 'PERLENGKAPAN';
        }
        if ($cat2Name === '') {
            $cat2Name = 'LAINNYA';
        }

        $kategori = $this->resolveKategori($cat1Name, $actorName);
        $subKategori = $this->resolveSubKategori($kategori, $cat2Name, $actorName);

        $lokasi = $this->cellString($sheet, $row, self::COL_LOKASI);
        $roomName = $this->cellString($sheet, $row, self::COL_ROOM);
        $moveRoomName = $this->cellString($sheet, $row, self::COL_MOVE_ROOM);

        $branchId = $lokasi !== '' ? $this->resolveCabangId($lokasi) : null;
        $location = null;
        $room = null;
        $fromRoom = null;
        $movement = null;
        if ($branchId) {
            $location = $this->resolveDefaultLocation($branchId, $actorName);
            if ($roomName !== '' && $location) {
                $fromRoom = $this->resolveRoom($location, $roomName, $actorName);
            }
            $targetName = $moveRoomName !== '' ? $moveRoomName : $roomName;
            if ($targetName !== '' && $location) {
                $room = $this->resolveRoom($location, $targetName, $actorName);
            }
            if ($moveRoomName !== '' && $room && (!$fromRoom || (int) $fromRoom->id !== (int) $room->id)) {
                $movement = [
                    'from_room_id' => $fromRoom ? (int) $fromRoom->id : null,
                    'to_room_id' => (int) $room->id,
                    'moved_at' => $this->mapFlexibleDate($sheet, $row, self::COL_MOVE_DATE),
                ];
            }
        }

        $assetName = $this->cellString($sheet, $row, self::COL_ASSET_NAME);
        if ($assetName === '') {
            $assetName = $csCode;
        }

        return [
            'public_id' => $this->generatePublicId(),
            'asset_code' => $csCode,
            'record_kind' => 'unit',
            'cs_code' => $csCode,
            'cs_sequence' => $this->nextCsSequence($subKategori->id),
            'kategori_aset_id' => $kategori->id,
            'sub_kategori_aset_id' => $subKategori->id,
            'name' => $assetName,
            'brand' => $this->cellString($sheet, $row, self::COL_BRAND) ?: null,
            'model_spec' => $this->cellString($sheet, $row, self::COL_SPEC) ?: null,
            'purchase_date' => $this->mapPurchaseDate($sheet, $row),
            'acquisition_cost' => $this->mapMoney($sheet, $row, self::COL_COST),
            'useful_life_years' => $this->mapUsefulLife($sheet, $row),
            'branch_id' => $branchId,
            'location_id' => $location ? $location->id : null,
            'room_id' => $room ? $room->id : null,
            'acquisition_origin' => $this->mapAcquisitionOrigin($this->cellString($sheet, $row, self::COL_ACQ_STATUS)),
            'condition' => $this->mapCondition($sheet, $row),
            'condition_text' => $this->cellString($sheet, $row, self::COL_CONDITION_TEXT) ?: null,
            'damage_received_date' => $this->mapDateParts($sheet, $row, self::COL_DAMAGE_DAY, self::COL_DAMAGE_MONTH, self::COL_DAMAGE_YEAR),
            'accurate_sync_flag' => $this->mapAccurateFlag($sheet, $row),
            '_movement' => $movement,
            'lifecycle_state' => 'active',
            'usage_state' => 'in_use',
            'publication_state' => 'published',
            'verification_state' => 'verified',
            'label_legacy' => strtoupper($this->cellString($sheet, $row, self::COL_LABEL)) === 'V' ? 'V' : null,
            'is_active' => true,
        ];
    }

    private function resolveKategori(string $name, string $actorName): MasterKategoriAset
    {
        $key = strtoupper($name);
        if (isset($this->kategoriCache[$key])) {
            return $this->kategoriCache[$key];
        }

        $model = MasterKategoriAset::firstOrCreate(
            ['nama_kategori' => $name],
            [
                'is_active' => true,
                'created_by' => $actorName,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        return $this->kategoriCache[$key] = $model;
    }

    private function resolveSubKategori(MasterKategoriAset $kategori, string $name, string $actorName): MasterSubKategoriAset
    {
        $key = $kategori->id . '|' . strtoupper($name);
        if (isset($this->subKategoriCache[$key])) {
            return $this->subKategoriCache[$key];
        }

        $model = MasterSubKategoriAset::firstOrCreate(
            ['id_kategori' => $kategori->id, 'nama_sub_kategori' => $name],
            [
                'is_active' => true,
                'created_by' => $actorName,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        return $this->subKategoriCache[$key] = $model;
    }

    private function resolveDefaultLocation(int $branchId, string $actorName): GaAssetLocation
    {
        $key = (string) $branchId;
        if (isset($this->locationCache[$key])) {
            return $this->locationCache[$key];
        }

        $model = GaAssetLocation::firstOrCreate(
            ['branch_id' => $branchId, 'code' => 'IMP-DEFAULT'],
            [
                'name' => 'Lokasi Import (Excel)',
                'is_active' => true,
                'created_by' => $actorName,
                'updated_by' => $actorName,
            ]
        );

        return $this->locationCache[$key] = $model;
    }

    private function resolveRoom(GaAssetLocation $location, string $roomName, string $actorName): GaAssetRoom
    {
        $normalizedRoom = trim(preg_replace('/\s+/', ' ', $roomName));
        $key = $location->id . '|' . strtoupper($normalizedRoom);
        if (isset($this->roomCache[$key])) {
            return $this->roomCache[$key];
        }

        $model = GaAssetRoom::firstOrCreate(
            ['location_id' => $location->id, 'name' => $normalizedRoom],
            [
                'code' => $this->slugCode($normalizedRoom),
                'is_active' => true,
                'created_by' => $actorName,
                'updated_by' => $actorName,
            ]
        );

        return $this->roomCache[$key] = $model;
    }

    private function resolveCabangId(string $lokasi): int
    {
        $key = strtoupper(trim($lokasi));
        if (isset($this->cabangAliasMap[$key])) {
            return $this->cabangAliasMap[$key];
        }

        throw new \RuntimeException("LOKASI tidak dikenali: {$lokasi}");
    }

    private function mapAcquisitionOrigin(string $raw): string
    {
        $v = strtoupper(trim($raw));
        if ($v === 'TO') {
            return 'take_over';
        }
        if (preg_match('/^2\s*ND$/', $v)) {
            return 'second';
        }

        return 'new';
    }

    private function mapCondition($sheet, int $row): string
    {
        if ($this->flagIsSet($sheet, $row, self::COL_FLAG_H)) {
            return 'missing';
        }
        if ($this->flagIsSet($sheet, $row, self::COL_FLAG_RB)) {
            return 'heavy_damage';
        }
        if ($this->flagIsSet($sheet, $row, self::COL_FLAG_T)) {
            return 'transfer';
        }
        if ($this->flagIsSet($sheet, $row, self::COL_FLAG_R)) {
            return 'damaged';
        }
        if ($this->flagIsSet($sheet, $row, self::COL_FLAG_K)) {
            return 'issue';
        }

        return 'normal';
    }

    private function flagIsSet($sheet, int $row, int $col): bool
    {
        return strtoupper($this->cellString($sheet, $row, $col)) === 'V';
    }

    private function recordImportedMovement(GaAsset $asset, $movement, string $actorName): void
    {
        if (!is_array($movement) || empty($movement['to_room_id'])) {
            return;
        }

        app(GaAssetMovementService::class)->recordImportedMove(
            $asset,
            isset($movement['from_room_id']) ? (int) $movement['from_room_id'] : null,
            (int) $movement['to_room_id'],
            $movement['moved_at'] ?? null,
            $actorName
        );
    }

    private function mapAccurateFlag($sheet, int $row): ?string
    {
        $value = strtoupper($this->cellString($sheet, $row, self::COL_ACCURATE));
        if ($value === '') {
            return null;
        }

        return substr($value, 0, 8);
    }

    private function mapDateParts($sheet, int $row, int $dayCol, int $monthCol, int $yearCol): ?string
    {
        $day = (int) round((float) $this->cellNumber($sheet, $row, $dayCol));
        $month = (int) round((float) $this->cellNumber($sheet, $row, $monthCol));
        $year = (int) round((float) $this->cellNumber($sheet, $row, $yearCol));
        if ($year < 1900 || $year > 2100 || !checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function mapFlexibleDate($sheet, int $row, int $col): ?string
    {
        $raw = $this->cellNumber($sheet, $row, $col);
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw) && (float) $raw > 20000) {
            $unix = ((float) $raw - 25569) * 86400;
            return gmdate('Y-m-d', (int) $unix);
        }

        $text = trim((string) $raw);
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/', $text, $match)) {
            $year = (int) $match[3];
            if ($year < 100) {
                $year += 2000;
            }
            $day = (int) $match[1];
            $month = (int) $match[2];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        $parsed = strtotime($text);
        if ($parsed) {
            return date('Y-m-d', $parsed);
        }

        return null;
    }

    private function mapPurchaseDate($sheet, int $row): ?string
    {
        $day = (int) round((float) $this->cellNumber($sheet, $row, self::COL_DAY));
        $month = (int) round((float) $this->cellNumber($sheet, $row, self::COL_MONTH));
        $year = (int) round((float) $this->cellNumber($sheet, $row, self::COL_YEAR));
        if ($year < 1900 || $year > 2100 || !checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function mapMoney($sheet, int $row, int $col): ?string
    {
        $raw = $this->cellNumber($sheet, $row, $col);
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!is_numeric($raw) || (float) $raw < 0) {
            return null;
        }

        return number_format((float) $raw, 2, '.', '');
    }

    private function mapUsefulLife($sheet, int $row): ?int
    {
        $raw = $this->cellNumber($sheet, $row, self::COL_USEFUL_LIFE);
        if ($raw === null || $raw === '' || !is_numeric($raw)) {
            return null;
        }

        $years = (int) round((float) $raw);
        if ($years < 1 || $years > 100) {
            return null;
        }

        return $years;
    }

    private function cellNumber($sheet, int $row, int $col)
    {
        $val = $sheet->getCellByColumnAndRow($col + 1, $row)->getCalculatedValue();
        if ($val === null || $val === '') {
            return null;
        }

        return $val;
    }

    private function syncSequenceFromPayload(array $payload, GaAsset $asset): void
    {
        $subCategoryId = (int) ($asset->sub_kategori_aset_id ?: ($payload['sub_kategori_aset_id'] ?? 0));
        $subCategory = $subCategoryId
            ? MasterSubKategoriAset::find($subCategoryId)
            : null;

        app(GaAssetSequenceService::class)->syncFromCsCode(
            (string) ($payload['cs_code'] ?? $asset->cs_code),
            $subCategoryId ?: null,
            $subCategory->nama_sub_kategori ?? null
        );
    }

    private function nextCsSequence(int $subKategoriId): int
    {
        if (!isset($this->sequenceCounters[$subKategoriId])) {
            $max = (int) GaAsset::where('sub_kategori_aset_id', $subKategoriId)->max('cs_sequence');
            $this->sequenceCounters[$subKategoriId] = $max;
        }

        return ++$this->sequenceCounters[$subKategoriId];
    }

    private function generatePublicId(): string
    {
        return bin2hex(random_bytes(13));
    }

    private function cellString($sheet, int $row, int $col): string
    {
        $val = $sheet->getCellByColumnAndRow($col + 1, $row)->getCalculatedValue();
        if ($val === null) {
            return '';
        }

        return trim((string) $val);
    }

    private function normalizeName(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function slugCode(string $name): string
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', $name));
        $code = trim($code, '_');

        return $code !== '' ? substr($code, 0, 80) : 'ROOM';
    }
}
