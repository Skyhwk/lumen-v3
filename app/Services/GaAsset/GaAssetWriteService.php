<?php

namespace App\Services\GaAsset;

use App\Models\GaAsset;
use App\Models\GaAssetRoom;
use App\Models\MasterKategoriAset;
use App\Models\MasterSubKategoriAset;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GaAssetWriteService
{
    public function create(array $payload, string $actor): GaAsset
    {
        $request = $this->requestFromPayload($payload);
        $actor = trim($actor) !== '' ? trim($actor) : 'GA Asset System';
        $lockName = null;

        try {
            $asset = DB::transaction(function () use ($request, $actor, &$lockName) {
                $now = Carbon::now();
                $category = $this->resolveCategory($request, $actor, $now);
                $subCategory = $this->resolveSubCategory($request, $category->id, $actor, $now);
                $allocation = $this->resolveAllocation($request, null);
                $lockName = $this->sequences()->holdNameLock($subCategory->nama_sub_kategori);

                $nextNumber = $this->sequences()->claimNext($subCategory->id, $subCategory->nama_sub_kategori, $now);

                $asset = GaAsset::create(array_merge($this->assetWritablePayload($request, $category, $subCategory, $allocation), [
                    'public_id' => bin2hex(random_bytes(13)),
                    'asset_code' => 'GA-TMP-' . bin2hex(random_bytes(8)),
                    'record_kind' => 'unit',
                    'cs_code' => $this->sequences()->formatCsCode($subCategory->nama_sub_kategori, $nextNumber),
                    'cs_sequence' => $nextNumber,
                    'lifecycle_state' => 'active',
                    'publication_state' => 'draft',
                    'verification_state' => 'needs_review',
                    'version' => 1,
                    'is_active' => 1,
                    'created_by' => $actor,
                    'updated_by' => $actor,
                ]));
                $asset->update([
                    'asset_code' => 'GA-' . $now->format('Y') . '-' . str_pad((string) $asset->id, 6, '0', STR_PAD_LEFT),
                ]);

                return $asset->fresh(['kategori', 'subKategori', 'room']);
            });
        } finally {
            if ($lockName) {
                $this->sequences()->releaseNameLock($lockName);
            }
        }

        return $asset;
    }

    public function update(Request $request, string $actor): GaAsset
    {
        $actor = trim($actor) !== '' ? trim($actor) : 'GA Asset System';

        return DB::transaction(function () use ($request, $actor) {
            $now = Carbon::now();
            $asset = GaAsset::where('id', $request->id)->where('is_active', 1)->lockForUpdate()->first();
            if (!$asset) {
                throw new \InvalidArgumentException('Aset tidak ditemukan.');
            }

            $category = $this->resolveCategory($request, $actor, $now);
            $subCategory = $this->resolveSubCategory($request, $category->id, $actor, $now);
            $allocation = $this->resolveAllocation($request, $asset);

            $asset->update(array_merge($this->assetWritablePayload($request, $category, $subCategory, $allocation), [
                'version' => ((int) $asset->version) + 1,
                'updated_by' => $actor,
                'updated_at' => $now,
            ]));

            return $asset->fresh(['kategori', 'subKategori', 'room']);
        });
    }

    public function resolveAllocation(Request $request, ?GaAsset $current)
    {
        if ($request->boolean('is_unallocated')) {
            return [
                'room_id' => null,
                'pic_employee_id' => null,
                'usage_state' => 'unknown',
            ];
        }

        if (!$request->filled('room_id')) {
            throw new \InvalidArgumentException('Ruangan wajib dipilih jika aset dialokasikan.');
        }

        $roomQuery = GaAssetRoom::where('id', $request->room_id);
        if (!$current || (int) $current->room_id !== (int) $request->room_id) {
            $roomQuery->where('is_active', 1);
        }
        $roomId = $roomQuery->value('id');
        if (!$roomId) {
            throw new \InvalidArgumentException('Ruangan tidak aktif atau tidak ditemukan.');
        }

        $picEmployeeId = null;
        if ($request->filled('pic_employee_id')) {
            $picQuery = DB::table('master_karyawan')->where('id', $request->pic_employee_id);
            if (!$current || (int) $current->pic_employee_id !== (int) $request->pic_employee_id) {
                $picQuery->where('is_active', 1);
            }
            $picEmployeeId = $picQuery->value('id');
            if (!$picEmployeeId) {
                throw new \InvalidArgumentException('Penanggung jawab / pengguna aset tidak aktif atau tidak ditemukan.');
            }
        }

        return [
            'room_id' => $roomId,
            'pic_employee_id' => $picEmployeeId,
            'usage_state' => $request->usage_state ?: 'unknown',
        ];
    }

    public function resolveCategory(Request $request, string $actor, Carbon $now)
    {
        if ($request->filled('kategori_aset_id')) {
            $category = MasterKategoriAset::where('id', $request->kategori_aset_id)->where('is_active', 1)->first();
            if (!$category) {
                throw new \InvalidArgumentException('Kategori aset tidak aktif atau tidak ditemukan.');
            }
            return $category;
        }

        $name = trim((string) $request->kategori_baru);
        $category = MasterKategoriAset::whereRaw('LOWER(TRIM(nama_kategori)) = ?', [strtolower($name)])->first();
        if ($category) {
            if (!(int) $category->is_active) {
                $category->update(['is_active' => 1, 'updated_by' => $actor, 'updated_at' => $now]);
            }
            return $category;
        }

        return MasterKategoriAset::create([
            'nama_kategori' => $name,
            'is_active' => 1,
            'created_by' => $actor,
            'created_at' => $now,
        ]);
    }

    public function resolveSubCategory(Request $request, int $categoryId, string $actor, Carbon $now)
    {
        if ($request->filled('sub_kategori_aset_id')) {
            $subCategory = MasterSubKategoriAset::where('id', $request->sub_kategori_aset_id)
                ->where('id_kategori', $categoryId)
                ->where('is_active', 1)
                ->first();
            if (!$subCategory) {
                throw new \InvalidArgumentException('Sub kategori tidak aktif atau tidak sesuai dengan kategori.');
            }
            return $subCategory;
        }

        $name = trim((string) $request->sub_kategori_baru);
        $subCategory = MasterSubKategoriAset::where('id_kategori', $categoryId)
            ->whereRaw('LOWER(TRIM(nama_sub_kategori)) = ?', [strtolower($name)])
            ->first();
        if ($subCategory) {
            if (!(int) $subCategory->is_active) {
                $subCategory->update(['is_active' => 1, 'updated_by' => $actor, 'updated_at' => $now]);
            }
            return $subCategory;
        }

        $created = MasterSubKategoriAset::create([
            'id_kategori' => $categoryId,
            'nama_sub_kategori' => $name,
            'is_active' => 1,
            'created_by' => $actor,
            'created_at' => $now,
        ]);
        $this->sequences()->peekNext($created->id, $created->nama_sub_kategori);

        return $created;
    }

    public function assetWritablePayload(Request $request, $category, $subCategory, array $allocation): array
    {
        return [
            'kategori_aset_id' => $category->id,
            'sub_kategori_aset_id' => $subCategory->id,
            'name' => trim($request->name),
            'brand' => $this->nullableTrimmed($request->brand),
            'model_spec' => $this->nullableTrimmed($request->model_spec),
            'room_id' => $allocation['room_id'],
            'pic_employee_id' => $allocation['pic_employee_id'],
            'acquisition_origin' => $request->acquisition_origin,
            'purchase_date' => $request->purchase_date ?: null,
            'condition' => $request->condition,
            'condition_note' => $this->nullableTrimmed($request->condition_note),
            'usage_state' => $allocation['usage_state'],
        ];
    }

    private function requestFromPayload(array $payload): Request
    {
        return Request::create('/', 'POST', $payload);
    }

    private function sequences(): GaAssetSequenceService
    {
        return app(GaAssetSequenceService::class);
    }

    private function nullableTrimmed($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
