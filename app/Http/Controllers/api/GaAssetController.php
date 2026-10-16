<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAsset;
use App\Models\GaAssetAcquisition;
use App\Models\GaAssetEvent;
use App\Models\GaAssetIdentifier;
use App\Models\GaAssetLocation;
use App\Models\GaAssetRoom;
use App\Models\GaAssetSequence;
use App\Models\MasterSubKategoriAset;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Yajra\Datatables\Datatables;

class GaAssetController extends Controller
{
    public function index(Request $request)
    {
        $data = GaAsset::query()
            ->leftJoin('master_sub_kategori_aset', 'master_sub_kategori_aset.id', '=', 'ga_assets.sub_kategori_aset_id')
            ->leftJoin('master_kategori_aset', 'master_kategori_aset.id', '=', 'ga_assets.kategori_aset_id')
            ->leftJoin('master_cabang', 'master_cabang.id', '=', 'ga_assets.branch_id')
            ->leftJoin('ga_asset_locations', 'ga_asset_locations.id', '=', 'ga_assets.location_id')
            ->leftJoin('ga_asset_rooms', 'ga_asset_rooms.id', '=', 'ga_assets.room_id')
            ->where('ga_assets.record_kind', GaAsset::RECORD_UNIT)
            ->select([
                'ga_assets.id',
                'ga_assets.asset_code',
                'ga_assets.cs_code',
                'ga_assets.name',
                'ga_assets.condition',
                'ga_assets.publication_state',
                'ga_assets.version',
                'ga_assets.branch_id',
                'ga_assets.location_id',
                'ga_assets.room_id',
                'ga_assets.sub_kategori_aset_id',
                'ga_assets.created_at',
                'master_sub_kategori_aset.nama_sub_kategori',
                'master_kategori_aset.nama_kategori',
                'master_cabang.nama_cabang',
                'ga_asset_locations.name as location_name',
                'ga_asset_rooms.name as room_name',
            ]);

        return Datatables::of($data)
            ->filterColumn('location_name', function ($query, $keyword) {
                $like = '%' . $this->searchText($keyword) . '%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('ga_asset_locations.name', 'like', $like)
                        ->orWhere('ga_asset_rooms.name', 'like', $like);
                });
            })
            ->filterColumn('ga_assets.condition', function ($query, $keyword) {
                $this->whereLabel($query, 'ga_assets.condition', $this->searchText($keyword), [
                    'normal' => 'Normal',
                    'kendala' => 'Kendala',
                    'rusak' => 'Rusak',
                    'rusak_berat' => 'Rusak Berat',
                    'hilang' => 'Hilang',
                    'transfer' => 'Transfer',
                    'unknown' => 'Belum diketahui',
                ]);
            })
            ->filterColumn('ga_assets.publication_state', function ($query, $keyword) {
                $this->whereLabel($query, 'ga_assets.publication_state', $this->searchText($keyword), [
                    'draft' => 'Draft',
                    'published' => 'Terbit',
                    'archived' => 'Arsip',
                ]);
            })
            ->make(true);
    }

    public function show(Request $request)
    {
        $asset = GaAsset::where('id', (int) $request->id)
            ->where('record_kind', GaAsset::RECORD_UNIT)
            ->first();

        if (!$asset) {
            return response()->json(['message' => 'Aset tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->present($asset),
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->payload($request);
        $validator = $this->validator($payload);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $subKategori = $this->readySubKategori($payload['sub_kategori_aset_id']);
        if (!$subKategori) {
            return response()->json(['message' => 'Jenis aset tidak ditemukan.'], 422);
        }

        $placement = $this->placementError($payload);
        if ($placement) {
            return response()->json(['message' => $placement], 422);
        }

        if ($payload['action'] === 'publish' && $payload['condition'] === 'unknown') {
            return response()->json(['message' => 'Kondisi harus diisi sebelum aset diterbitkan.'], 422);
        }

        try {
            $asset = DB::transaction(function () use ($payload, $subKategori) {
                $asset = new GaAsset();
                $asset->public_id = bin2hex(random_bytes(13));
                $asset->asset_code = 'TMP-' . $asset->public_id;
                $asset->record_kind = GaAsset::RECORD_UNIT;
                $asset->version = 1;
                $asset->lifecycle_state = GaAsset::LIFECYCLE_ACTIVE;
                $asset->usage_state = GaAsset::USAGE_UNKNOWN;
                $asset->publication_state = GaAsset::PUBLICATION_DRAFT;
                $asset->verification_state = GaAsset::VERIFICATION_NEEDS_REVIEW;
                $asset->is_active = true;
                $asset->created_by = $this->karyawan;
                $this->fillAsset($asset, $payload, $subKategori);
                $asset->save();

                $asset->asset_code = 'GA-' . date('Y') . '-' . str_pad((string) $asset->id, 6, '0', STR_PAD_LEFT);
                $asset->save();

                $this->saveAcquisition($asset, $payload);
                $this->writeEvent($asset, GaAssetEvent::CREATED, null, $this->present($asset));

                if ($payload['action'] === 'publish') {
                    $this->publishAsset($asset, $subKategori);
                }

                return $asset->fresh();
            });
        } catch (QueryException $exception) {
            return $this->queryFailed($exception);
        }

        return response()->json([
            'success' => true,
            'message' => $asset->publication_state === GaAsset::PUBLICATION_PUBLISHED
                ? 'Aset berhasil diterbitkan.'
                : 'Aset berhasil disimpan sebagai draft.',
            'data' => $this->present($asset),
        ], 201);
    }

    public function update(Request $request)
    {
        $payload = $this->payload($request);
        $validator = $this->validator($payload, true);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $subKategori = $this->readySubKategori($payload['sub_kategori_aset_id']);
        if (!$subKategori) {
            return response()->json(['message' => 'Jenis aset tidak ditemukan.'], 422);
        }

        $placement = $this->placementError($payload);
        if ($placement) {
            return response()->json(['message' => $placement], 422);
        }

        try {
            $result = DB::transaction(function () use ($request, $payload, $subKategori) {
                $asset = GaAsset::where('id', (int) $request->id)
                    ->where('record_kind', GaAsset::RECORD_UNIT)
                    ->lockForUpdate()
                    ->first();

                if (!$asset) {
                    return ['status' => 404, 'message' => 'Aset tidak ditemukan.'];
                }
                if ($asset->publication_state === GaAsset::PUBLICATION_ARCHIVED) {
                    return ['status' => 422, 'message' => 'Aset arsip tidak dapat diubah. Pulihkan dulu.'];
                }
                if ((int) $asset->version !== (int) $payload['expected_version']) {
                    return ['status' => 409, 'message' => 'Data sudah diubah orang lain. Muat ulang lalu coba lagi.'];
                }
                if ($asset->cs_code && (int) $asset->sub_kategori_aset_id !== $payload['sub_kategori_aset_id']) {
                    return ['status' => 422, 'message' => 'Jenis tidak dapat diubah setelah nomor CS terbit.'];
                }
                if ($payload['action'] === 'publish' && $payload['condition'] === 'unknown') {
                    return ['status' => 422, 'message' => 'Kondisi harus diisi sebelum aset diterbitkan.'];
                }

                $before = $this->present($asset);
                $this->fillAsset($asset, $payload, $subKategori);
                $asset->version = (int) $asset->version + 1;
                $asset->updated_by = $this->karyawan;
                $asset->save();
                $this->saveAcquisition($asset, $payload);

                if ($payload['action'] === 'publish' && $asset->publication_state !== GaAsset::PUBLICATION_PUBLISHED) {
                    $this->publishAsset($asset, $subKategori, $before);
                } else {
                    $this->writeEvent($asset, GaAssetEvent::UPDATED, $before, $this->present($asset));
                }

                return ['status' => 200, 'asset' => $asset->fresh()];
            });
        } catch (QueryException $exception) {
            return $this->queryFailed($exception);
        }

        if (empty($result['asset'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        $asset = $result['asset'];

        return response()->json([
            'success' => true,
            'message' => $asset->publication_state === GaAsset::PUBLICATION_PUBLISHED && $payload['action'] === 'publish'
                ? 'Aset berhasil diterbitkan.'
                : 'Aset berhasil diupdate.',
            'data' => $this->present($asset),
        ]);
    }

    public function publish(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        try {
            $result = DB::transaction(function () use ($request) {
                $asset = GaAsset::where('id', (int) $request->id)
                    ->where('record_kind', GaAsset::RECORD_UNIT)
                    ->lockForUpdate()
                    ->first();

                if (!$asset) {
                    return ['status' => 404, 'message' => 'Aset tidak ditemukan.'];
                }
                if ((int) $asset->version !== (int) $request->expected_version) {
                    return ['status' => 409, 'message' => 'Data sudah diubah orang lain. Muat ulang lalu coba lagi.'];
                }
                if ($asset->publication_state === GaAsset::PUBLICATION_ARCHIVED) {
                    return ['status' => 422, 'message' => 'Aset arsip tidak dapat diterbitkan. Pulihkan dulu.'];
                }
                if ($asset->publication_state === GaAsset::PUBLICATION_PUBLISHED) {
                    return ['status' => 422, 'message' => 'Aset sudah diterbitkan.'];
                }
                if ($asset->condition === 'unknown') {
                    return ['status' => 422, 'message' => 'Kondisi harus diisi sebelum aset diterbitkan.'];
                }

                $subKategori = $this->readySubKategori((int) $asset->sub_kategori_aset_id);
                if (!$subKategori) {
                    return ['status' => 422, 'message' => 'Jenis aset tidak ditemukan.'];
                }

                $asset->version = (int) $asset->version + 1;
                $asset->updated_by = $this->karyawan;
                $asset->save();
                $this->publishAsset($asset, $subKategori);

                return ['status' => 200, 'asset' => $asset->fresh()];
            });
        } catch (QueryException $exception) {
            return $this->queryFailed($exception);
        }

        if (empty($result['asset'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil diterbitkan.',
            'data' => $this->present($result['asset']),
        ]);
    }

    public function archive(Request $request)
    {
        return $this->changePublication($request, GaAsset::PUBLICATION_ARCHIVED);
    }

    public function restore(Request $request)
    {
        return $this->changePublication($request, 'restore');
    }

    private function changePublication(Request $request, string $target)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        try {
            $result = DB::transaction(function () use ($request, $target) {
                $asset = GaAsset::where('id', (int) $request->id)
                    ->where('record_kind', GaAsset::RECORD_UNIT)
                    ->lockForUpdate()
                    ->first();

                if (!$asset) {
                    return ['status' => 404, 'message' => 'Aset tidak ditemukan.'];
                }
                if ((int) $asset->version !== (int) $request->expected_version) {
                    return ['status' => 409, 'message' => 'Data sudah diubah orang lain. Muat ulang lalu coba lagi.'];
                }

                if ($target === GaAsset::PUBLICATION_ARCHIVED) {
                    if ($asset->publication_state === GaAsset::PUBLICATION_ARCHIVED) {
                        return ['status' => 422, 'message' => 'Aset sudah diarsipkan.'];
                    }
                    $hasComponent = GaAsset::where('parent_asset_id', $asset->id)
                        ->where('publication_state', '!=', GaAsset::PUBLICATION_ARCHIVED)
                        ->exists();
                    if ($hasComponent) {
                        return ['status' => 422, 'message' => 'Aset masih punya komponen aktif dan tidak dapat diarsipkan.'];
                    }

                    $before = $this->present($asset);
                    $asset->publication_state = GaAsset::PUBLICATION_ARCHIVED;
                    $asset->archived_at = date('Y-m-d H:i:s');
                    $asset->archived_by = $this->karyawan;
                    $asset->version = (int) $asset->version + 1;
                    $asset->updated_by = $this->karyawan;
                    $asset->save();
                    $this->writeEvent($asset, GaAssetEvent::ARCHIVED, $before, $this->present($asset));

                    return ['status' => 200, 'message' => 'Aset berhasil diarsipkan.', 'asset' => $asset];
                }

                if ($asset->publication_state !== GaAsset::PUBLICATION_ARCHIVED) {
                    return ['status' => 422, 'message' => 'Aset ini tidak sedang diarsipkan.'];
                }

                $before = $this->present($asset);
                $asset->publication_state = $asset->cs_code
                    ? GaAsset::PUBLICATION_PUBLISHED
                    : GaAsset::PUBLICATION_DRAFT;
                $asset->archived_at = null;
                $asset->archived_by = null;
                $asset->version = (int) $asset->version + 1;
                $asset->updated_by = $this->karyawan;
                $asset->save();
                $this->writeEvent($asset, GaAssetEvent::RESTORED, $before, $this->present($asset));

                return ['status' => 200, 'message' => 'Aset berhasil dipulihkan.', 'asset' => $asset];
            });
        } catch (QueryException $exception) {
            return $this->queryFailed($exception);
        }

        if (empty($result['asset'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $this->present($result['asset']),
        ]);
    }

    private function publishAsset(GaAsset $asset, MasterSubKategoriAset $subKategori, ?array $before = null): void
    {
        $before = $before ?? $this->present($asset);

        if (!$asset->cs_code) {
            $allocated = $this->allocateCs($subKategori);
            $asset->cs_sequence = $allocated['number'];
            $asset->cs_code = $allocated['cs_code'];

            GaAssetIdentifier::create([
                'asset_id' => $asset->id,
                'scheme' => GaAssetIdentifier::SCHEME_CS_CODE,
                'value_raw' => $allocated['cs_code'],
                'value_normalized' => $allocated['cs_code'],
                'is_primary' => true,
            ]);
        }

        $asset->publication_state = GaAsset::PUBLICATION_PUBLISHED;
        $asset->verification_state = GaAsset::VERIFICATION_VERIFIED;
        $asset->updated_by = $this->karyawan;
        $asset->save();

        $this->writeEvent($asset, GaAssetEvent::CS_ALLOCATED, $before, $this->present($asset));
    }

    private function allocateCs(MasterSubKategoriAset $subKategori): array
    {
        MasterSubKategoriAset::where('id', $subKategori->id)->lockForUpdate()->first();

        $sequence = GaAssetSequence::where('sub_kategori_aset_id', $subKategori->id)->lockForUpdate()->first();
        if (!$sequence) {
            $sequence = new GaAssetSequence();
            $sequence->sub_kategori_aset_id = $subKategori->id;
            $sequence->last_number = 0;
        }

        $next = (int) $sequence->last_number + 1;
        $sequence->last_number = $next;
        $sequence->updated_at = date('Y-m-d H:i:s');
        $sequence->save();

        return [
            'number' => $next,
            'cs_code' => GaAssetSequence::formatCode((string) $subKategori->nama_sub_kategori, $next),
        ];
    }

    private function fillAsset(GaAsset $asset, array $payload, MasterSubKategoriAset $subKategori): void
    {
        $asset->sub_kategori_aset_id = $subKategori->id;
        $asset->kategori_aset_id = (int) $subKategori->id_kategori;
        $asset->name = $payload['name'];
        $asset->brand = $payload['brand'];
        $asset->model_spec = $payload['model_spec'];
        $asset->branch_id = $payload['branch_id'];
        $asset->location_id = $payload['location_id'];
        $asset->room_id = $payload['room_id'];
        $asset->condition = $payload['condition'];
        $asset->condition_note = $payload['condition_note'];
        $asset->acquisition_origin = $payload['acquisition_origin'];
    }

    private function saveAcquisition(GaAsset $asset, array $payload): void
    {
        $acquisition = GaAssetAcquisition::where('asset_id', $asset->id)->first();
        if (!$acquisition) {
            $acquisition = new GaAssetAcquisition();
            $acquisition->asset_id = $asset->id;
        }

        $acquisition->acquisition_date = $payload['acquisition_date'];
        $acquisition->date_precision = $payload['acquisition_date'] ? 'full' : 'unknown';
        $acquisition->amount = $payload['amount'];
        $acquisition->currency = 'IDR';
        $acquisition->value_basis = $payload['amount'] !== null ? 'standalone' : 'unknown';
        $acquisition->save();
    }

    private function writeEvent(GaAsset $asset, string $type, ?array $before, array $after): void
    {
        GaAssetEvent::create([
            'asset_id' => $asset->id,
            'event_type' => $type,
            'actor_id' => $this->karyawan,
            'occurred_at' => date('Y-m-d H:i:s'),
            'before_json' => $before,
            'after_json' => $after,
        ]);
    }

    private function present(GaAsset $asset): array
    {
        $asset->unsetRelation('acquisition');
        $asset->load(['acquisition', 'subKategori', 'kategori', 'location', 'room', 'cabang']);
        $acquisition = $asset->acquisition;
        $date = $acquisition && $acquisition->acquisition_date
            ? $acquisition->acquisition_date->format('Y-m-d')
            : null;

        return [
            'id' => $asset->id,
            'public_id' => $asset->public_id,
            'asset_code' => $asset->asset_code,
            'cs_code' => $asset->cs_code,
            'cs_sequence' => $asset->cs_sequence,
            'version' => (int) $asset->version,
            'sub_kategori_aset_id' => $asset->sub_kategori_aset_id,
            'kategori_aset_id' => $asset->kategori_aset_id,
            'nama_sub_kategori' => optional($asset->subKategori)->nama_sub_kategori,
            'nama_kategori' => optional($asset->kategori)->nama_kategori,
            'name' => $asset->name,
            'brand' => $asset->brand,
            'model_spec' => $asset->model_spec,
            'branch_id' => $asset->branch_id,
            'location_id' => $asset->location_id,
            'room_id' => $asset->room_id,
            'nama_cabang' => optional($asset->cabang)->nama_cabang,
            'location_name' => optional($asset->location)->name,
            'room_name' => optional($asset->room)->name,
            'condition' => $asset->condition,
            'condition_note' => $asset->condition_note,
            'publication_state' => $asset->publication_state,
            'acquisition_origin' => $asset->acquisition_origin,
            'acquisition_date' => $date,
            'amount' => $acquisition ? $acquisition->amount : null,
        ];
    }

    private function payload(Request $request): array
    {
        $amount = trim((string) $request->input('amount', ''));
        $date = trim((string) $request->input('acquisition_date', ''));

        return [
            'sub_kategori_aset_id' => (int) $request->input('sub_kategori_aset_id'),
            'name' => trim((string) $request->input('name')),
            'brand' => $this->nullableString($request->input('brand')),
            'model_spec' => $this->nullableString($request->input('model_spec')),
            'branch_id' => (int) $request->input('branch_id'),
            'location_id' => $this->nullableInt($request->input('location_id')),
            'room_id' => $this->nullableInt($request->input('room_id')),
            'condition' => trim((string) $request->input('condition')),
            'condition_note' => $this->nullableString($request->input('condition_note')),
            'acquisition_origin' => trim((string) $request->input('acquisition_origin', 'unknown')),
            'acquisition_date' => $date !== '' ? $date : null,
            'amount' => $amount !== '' ? $amount : null,
            'action' => trim((string) $request->input('action', 'draft')),
            'expected_version' => (int) $request->input('expected_version', $request->input('version')),
        ];
    }

    private function validator(array $payload, bool $withVersion = false)
    {
        $rules = [
            'sub_kategori_aset_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:150'],
            'model_spec' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['required', 'integer', 'min:1'],
            'location_id' => ['nullable', 'integer', 'min:1'],
            'room_id' => ['nullable', 'integer', 'min:1'],
            'condition' => ['required', Rule::in(GaAsset::CONDITIONS)],
            'condition_note' => ['nullable', 'string'],
            'acquisition_origin' => ['required', Rule::in(GaAsset::ACQUISITION_ORIGINS)],
            'acquisition_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'action' => ['required', Rule::in(['draft', 'publish', 'save'])],
        ];

        if ($withVersion) {
            $rules['expected_version'] = ['required', 'integer', 'min:1'];
        }

        return Validator::make($payload, $rules);
    }

    private function readySubKategori(int $id)
    {
        $subKategori = MasterSubKategoriAset::where('id', $id)->where('is_active', true)->first();
        if (!$subKategori || !(int) $subKategori->id_kategori) {
            return null;
        }

        return $subKategori;
    }

    private function placementError(array $payload): ?string
    {
        if ($payload['location_id']) {
            $location = GaAssetLocation::where('id', $payload['location_id'])->where('is_active', true)->first();
            if (!$location || (int) $location->branch_id !== $payload['branch_id']) {
                return 'Lokasi tidak sesuai dengan cabang yang dipilih.';
            }
        }

        if ($payload['room_id']) {
            if (!$payload['location_id']) {
                return 'Pilih lokasi sebelum memilih ruang.';
            }
            $room = GaAssetRoom::where('id', $payload['room_id'])->where('is_active', true)->first();
            if (!$room || (int) $room->location_id !== $payload['location_id']) {
                return 'Ruang tidak sesuai dengan lokasi yang dipilih.';
            }
        }

        return null;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value) || (int) $value < 1) {
            return null;
        }

        return (int) $value;
    }

    private function searchText(string $keyword): string
    {
        return trim(trim($keyword), '%');
    }

    private function whereLabel($query, string $column, string $keyword, array $labels): void
    {
        $needle = strtolower($keyword);
        $matched = [];

        foreach ($labels as $code => $label) {
            $codeText = str_replace('_', ' ', strtolower((string) $code));
            $labelText = strtolower($label);
            if ($needle !== '' && (strpos($labelText, $needle) !== false || strpos($codeText, $needle) !== false)) {
                $matched[] = $code;
            }
        }

        if (!$matched) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn($column, $matched);
    }

    private function nullableString($value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function invalid($validator)
    {
        return response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422);
    }

    private function queryFailed(QueryException $exception)
    {
        $duplicate = (string) $exception->getCode() === '23000';

        return response()->json([
            'message' => $duplicate
                ? 'Nomor CS bentrok. Coba terbitkan lagi.'
                : 'Gagal menyimpan aset.',
        ], $duplicate ? 409 : 500);
    }
}
