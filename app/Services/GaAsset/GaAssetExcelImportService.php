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
        ?string $userName
    ): array {
        $this->warmCaches();
        $actorName = $userName ?: 'System';

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName(self::DATA_SHEET);
        if ($sheet === null) {
            $sheet = $spreadsheet->getActiveSheet();
        }
        $sheetName = $sheet->getTitle();

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

                    if ($existing) {
                        unset(
                            $payload['public_id'],
                            $payload['cs_sequence'],
                            $payload['kategori_aset_id'],
                            $payload['sub_kategori_aset_id']
                        );
                        $existing->update($payload);
                        $stats['rows_updated']++;
                    } else {
                        $payload['created_by'] = $actorName;
                        GaAsset::create($payload);
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
        ];
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

        $branchId = $lokasi !== '' ? $this->resolveCabangId($lokasi) : null;
        $location = null;
        $room = null;
        if ($branchId) {
            $location = $this->resolveDefaultLocation($branchId, $actorName);
            if ($roomName !== '' && $location) {
                $room = $this->resolveRoom($location, $roomName, $actorName);
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
            'branch_id' => $branchId,
            'location_id' => $location ? $location->id : null,
            'room_id' => $room ? $room->id : null,
            'acquisition_origin' => $this->mapAcquisitionOrigin($this->cellString($sheet, $row, self::COL_ACQ_STATUS)),
            'condition' => $this->mapCondition($sheet, $row),
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
