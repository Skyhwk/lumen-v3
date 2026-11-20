<?php

namespace App\Services\HrAset;

use App\Models\AsetKaryawan;
use App\Models\GaAsset;
use App\Models\MasterAsetKaryawan;
use App\Models\MasterSubKategoriAset;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HrGaAssetLinkService
{
    public const MASTER_STATUS_UNMAPPED = 'unmapped';
    public const MASTER_STATUS_MAPPED = 'mapped_sub_kategori';

    public const ROW_STATUS_UNLINKED = 'unlinked';
    public const ROW_STATUS_LINKED = 'linked';
    public const ROW_STATUS_NOT_APPLICABLE = 'not_applicable';

    public function linkMasterToSubKategori(int $masterAsetKaryawanId, ?int $subKategoriAsetId, string $actor): MasterAsetKaryawan
    {
        if (!Schema::hasTable('master_aset_karyawan')) {
            throw new Exception('Tabel master aset karyawan belum tersedia.');
        }

        $master = MasterAsetKaryawan::where('id', $masterAsetKaryawanId)
            ->where('is_active', true)
            ->first();

        if (!$master) {
            throw new Exception('Master aset karyawan tidak ditemukan atau tidak aktif.');
        }

        $now = Carbon::now()->toDateTimeString();

        if ($subKategoriAsetId === null || $subKategoriAsetId <= 0) {
            $master->sub_kategori_aset_id = null;
            $master->ga_link_status = self::MASTER_STATUS_UNMAPPED;
            $master->ga_linked_at = null;
            $master->ga_linked_by = null;
            $master->updated_at = $now;
            $master->updated_by = $actor;
            $master->save();

            return $master;
        }

        if (!Schema::hasTable('master_sub_kategori_aset')) {
            throw new Exception('Tabel master sub kategori aset belum tersedia.');
        }

        $sub = MasterSubKategoriAset::where('id', $subKategoriAsetId)
            ->where('is_active', true)
            ->first();

        if (!$sub) {
            throw new Exception('Sub kategori aset GA tidak ditemukan atau tidak aktif.');
        }

        $master->sub_kategori_aset_id = $sub->id;
        $master->ga_link_status = self::MASTER_STATUS_MAPPED;
        $master->ga_linked_at = $now;
        $master->ga_linked_by = $actor;
        $master->updated_at = $now;
        $master->updated_by = $actor;
        $master->save();

        return $master;
    }

    /**
     * Unit GA yang boleh di-assign ke baris aset_karyawan (fase link manual).
     */
    public function searchAssignableGaAssets(
        int $subKategoriAsetId,
        ?int $masterKaryawanId = null,
        ?string $keyword = null,
        int $limit = 30
    ): array {
        if (!Schema::hasTable('ga_assets')) {
            return [];
        }

        $limit = max(1, min(100, $limit));

        $query = GaAsset::query()
            ->where('is_active', true)
            ->where('record_kind', GaAsset::RECORD_UNIT)
            ->where('sub_kategori_aset_id', $subKategoriAsetId)
            ->where(function ($inner) use ($masterKaryawanId) {
                $inner->whereNull('pic_employee_id');
                if ($masterKaryawanId) {
                    $inner->orWhere('pic_employee_id', $masterKaryawanId);
                }
            });

        if ($keyword !== null && trim($keyword) !== '') {
            $like = '%' . trim($keyword) . '%';
            $query->where(function ($inner) use ($like) {
                $inner->where('asset_code', 'like', $like)
                    ->orWhere('cs_code', 'like', $like)
                    ->orWhere('name', 'like', $like);
            });
        }

        return $query
            ->orderBy('asset_code')
            ->limit($limit)
            ->get(['id', 'asset_code', 'cs_code', 'name', 'pic_employee_id', 'sub_kategori_aset_id'])
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'asset_code' => $row->asset_code,
                    'cs_code' => $row->cs_code,
                    'name' => $row->name,
                    'text' => trim(($row->cs_code ?: $row->asset_code) . ' — ' . $row->name),
                    'pic_employee_id' => $row->pic_employee_id,
                ];
            })
            ->values()
            ->all();
    }

    public function linkAsetKaryawanRowToGaUnit(int $asetKaryawanId, int $gaAssetId, string $actor): AsetKaryawan
    {
        if (!Schema::hasTable('aset_karyawan') || !Schema::hasTable('ga_assets')) {
            throw new Exception('Tabel aset karyawan atau GA belum tersedia.');
        }

        return DB::transaction(function () use ($asetKaryawanId, $gaAssetId, $actor) {
            $row = AsetKaryawan::with('masterAset')
                ->where('id', $asetKaryawanId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                throw new Exception('Baris aset karyawan tidak ditemukan.');
            }

            if (!empty($row->rfid_card_id)) {
                throw new Exception('ID Card tidak di-link ke unit GA (gunakan status not_applicable).');
            }

            $master = $row->masterAset;
            $subKategoriId = $master ? (int) ($master->sub_kategori_aset_id ?? 0) : 0;
            if ($subKategoriId <= 0) {
                throw new Exception('Master aset karyawan belum di-mapping ke sub kategori GA.');
            }

            if ((int) ($row->qty ?? 1) > 1) {
                throw new Exception('Link unit GA saat ini hanya untuk qty = 1. Pecah baris atau gunakan fase pivot (lihat roadmap).');
            }

            $alreadyLinked = AsetKaryawan::where('ga_asset_id', $gaAssetId)
                ->where('is_active', true)
                ->where('id', '<>', $row->id)
                ->exists();

            if ($alreadyLinked) {
                throw new Exception('Unit GA ini sudah di-link ke baris aset karyawan lain.');
            }

            $ga = GaAsset::where('id', $gaAssetId)
                ->where('is_active', true)
                ->where('record_kind', GaAsset::RECORD_UNIT)
                ->lockForUpdate()
                ->first();

            if (!$ga) {
                throw new Exception('Unit GA tidak ditemukan.');
            }

            if ((int) $ga->sub_kategori_aset_id !== $subKategoriId) {
                throw new Exception('Sub kategori unit GA tidak sesuai mapping master HR.');
            }

            $karyawanId = (int) $row->master_karyawan_id;

            if ($ga->pic_employee_id !== null && (int) $ga->pic_employee_id !== $karyawanId) {
                throw new Exception('Unit GA sudah memiliki PIC karyawan lain.');
            }

            $now = Carbon::now()->toDateTimeString();

            $ga->pic_employee_id = $karyawanId;
            $ga->updated_by = $actor;
            $ga->updated_at = $now;
            $ga->save();

            $row->ga_asset_id = $ga->id;
            $row->ga_link_status = self::ROW_STATUS_LINKED;
            $row->ga_linked_at = $now;
            $row->ga_linked_by = $actor;
            $row->updated_at = $now;
            $row->updated_by = $actor;
            $row->save();

            return $row;
        });
    }

    public function unlinkAsetKaryawanRowFromGaUnit(int $asetKaryawanId, string $actor, bool $clearPic = false): AsetKaryawan
    {
        if (!Schema::hasTable('aset_karyawan')) {
            throw new Exception('Tabel aset karyawan belum tersedia.');
        }

        return DB::transaction(function () use ($asetKaryawanId, $actor, $clearPic) {
            $row = AsetKaryawan::where('id', $asetKaryawanId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                throw new Exception('Baris aset karyawan tidak ditemukan.');
            }

            $gaId = (int) ($row->ga_asset_id ?? 0);
            $now = Carbon::now()->toDateTimeString();

            if ($gaId > 0 && Schema::hasTable('ga_assets')) {
                $ga = GaAsset::where('id', $gaId)->lockForUpdate()->first();
                if ($ga && $clearPic && (int) $ga->pic_employee_id === (int) $row->master_karyawan_id) {
                    $ga->pic_employee_id = null;
                    $ga->updated_by = $actor;
                    $ga->updated_at = $now;
                    $ga->save();
                }
            }

            $row->ga_asset_id = null;
            $row->ga_link_status = self::ROW_STATUS_UNLINKED;
            $row->ga_linked_at = null;
            $row->ga_linked_by = null;
            $row->updated_at = $now;
            $row->updated_by = $actor;
            $row->save();

            return $row;
        });
    }
}