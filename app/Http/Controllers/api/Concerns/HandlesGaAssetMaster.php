<?php

namespace App\Http\Controllers\api\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\Datatables\Datatables;

trait HandlesGaAssetMaster
{
    abstract protected function gaAssetMasterModel(): string;

    abstract protected function gaAssetMasterCodeMaxLength(): int;

    abstract protected function gaAssetMasterLabel(): string;

    protected function serializeGaAssetMasterRecord(Model $row): array
    {
        return [
            'id' => (string) $row->id,
            'code' => $row->code,
            'name' => $row->name,
            'is_active' => (bool) $row->is_active,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'created_by' => $row->created_by,
            'updated_by' => $row->updated_by,
        ];
    }

    protected function normalizeGaAssetCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    protected function validateGaAssetCode(string $code, int $maxLength): ?string
    {
        if ($code === '') {
            return 'Kode wajib diisi.';
        }

        if (strlen($code) > $maxLength) {
            return "Kode maksimal {$maxLength} karakter.";
        }

        if (!preg_match('/^[A-Z0-9_-]+$/', $code)) {
            return 'Kode hanya boleh huruf, angka, underscore, dan strip.';
        }

        return null;
    }

    protected function validateGaAssetName(string $name): ?string
    {
        if ($name === '') {
            return 'Nama wajib diisi.';
        }

        if (strlen($name) > 150) {
            return 'Nama maksimal 150 karakter.';
        }

        return null;
    }

    protected function parseGaAssetIsActiveFilter(Request $request): ?bool
    {
        if (!$request->has('is_active') || $request->input('is_active') === '') {
            return null;
        }

        $value = $request->input('is_active');

        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return null;
    }

    public function index(Request $request)
    {
        $modelClass = $this->gaAssetMasterModel();
        /** @var Model $model */
        $model = new $modelClass();
        $table = $model->getTable();
        $query = $modelClass::query()->select("{$table}.*");

        return Datatables::of($query)
            ->filterColumn('code', function ($query, $keyword) use ($table) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }
                $query->where("{$table}.code", 'like', '%' . $keyword . '%');
            })
            ->filterColumn('name', function ($query, $keyword) use ($table) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }
                $query->where("{$table}.name", 'like', '%' . $keyword . '%');
            })
            ->filterColumn('is_active', function ($query, $keyword) use ($table) {
                $keyword = trim(strtolower((string) $keyword));
                if ($keyword === '' || $keyword === 'semua') {
                    return;
                }
                if (in_array($keyword, ['1', 'aktif', 'true'], true)) {
                    $query->where("{$table}.is_active", true);
                    return;
                }
                if (in_array($keyword, ['0', 'nonaktif', 'false'], true)) {
                    $query->where("{$table}.is_active", false);
                }
            })
            ->filterColumn('created_at', function ($query, $keyword) use ($table) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }
                $query->where(function ($nested) use ($keyword, $table) {
                    $nested
                        ->where("{$table}.created_at", 'like', '%' . $keyword . '%')
                        ->orWhere("{$table}.created_by", 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('updated_at', function ($query, $keyword) use ($table) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }
                $query->where(function ($nested) use ($keyword, $table) {
                    $nested
                        ->where("{$table}.updated_at", 'like', '%' . $keyword . '%')
                        ->orWhere("{$table}.updated_by", 'like', '%' . $keyword . '%');
                });
            })
            ->make(true);
    }

    public function show(Request $request)
    {
        if (!$request->id) {
            return response()->json(['message' => 'Data Not Found.!'], 404);
        }

        $modelClass = $this->gaAssetMasterModel();
        $row = $modelClass::find($request->id);

        if (!$row) {
            return response()->json(['message' => 'Data Not Found.!'], 404);
        }

        return response()->json([
            'message' => 'Data loaded successfully',
            'data' => $this->serializeGaAssetMasterRecord($row),
        ], 200);
    }

    public function store(Request $request)
    {
        $modelClass = $this->gaAssetMasterModel();
        $maxCode = $this->gaAssetMasterCodeMaxLength();
        $label = $this->gaAssetMasterLabel();

        $code = $this->normalizeGaAssetCode($request->input('code'));
        $name = trim((string) $request->input('name'));
        $isActive = $request->has('is_active')
            ? in_array($request->input('is_active'), [true, 1, '1', 'true'], true)
            : true;

        if ($message = $this->validateGaAssetCode($code, $maxCode)) {
            return response()->json(['message' => $message, 'errors' => ['code' => [$message]]], 422);
        }

        if ($message = $this->validateGaAssetName($name)) {
            return response()->json(['message' => $message, 'errors' => ['name' => [$message]]], 422);
        }

        if ($modelClass::where('code', $code)->exists()) {
            $message = 'Kode sudah digunakan.';
            return response()->json(['message' => $message, 'errors' => ['code' => [$message]]], 409);
        }

        $now = Carbon::now();

        $row = $modelClass::create([
            'code' => $code,
            'name' => $name,
            'is_active' => $isActive,
            'created_at' => $now,
            'created_by' => $this->karyawan,
            'updated_at' => null,
            'updated_by' => null,
        ]);

        return response()->json([
            'message' => "{$label} berhasil ditambahkan",
            'data' => $this->serializeGaAssetMasterRecord($row),
            'success' => true,
        ], 200);
    }

    public function update(Request $request)
    {
        if (!$request->id) {
            return response()->json(['message' => 'Data Not Found.!'], 404);
        }

        $modelClass = $this->gaAssetMasterModel();
        $label = $this->gaAssetMasterLabel();
        $row = $modelClass::find($request->id);

        if (!$row) {
            return response()->json(['message' => 'Data Not Found.!'], 404);
        }

        $name = trim((string) $request->input('name'));

        if ($message = $this->validateGaAssetName($name)) {
            return response()->json(['message' => $message, 'errors' => ['name' => [$message]]], 422);
        }

        $now = Carbon::now();
        $row->name = $name;
        $row->updated_at = $now;
        $row->updated_by = $this->karyawan;
        $row->save();

        return response()->json([
            'message' => "{$label} berhasil diperbarui",
            'data' => $this->serializeGaAssetMasterRecord($row),
        ], 200);
    }

    public function updateStatus(Request $request)
    {
        if (!$request->id) {
            return response()->json(['message' => 'Data Not Found.!'], 404);
        }

        if (!$request->has('is_active')) {
            return response()->json(['message' => 'Status wajib diisi.'], 422);
        }

        $modelClass = $this->gaAssetMasterModel();
        $label = $this->gaAssetMasterLabel();
        $row = $modelClass::find($request->id);

        if (!$row) {
            return response()->json(['message' => 'Data Not Found.!'], 404);
        }

        $isActive = in_array($request->input('is_active'), [true, 1, '1', 'true'], true);
        $now = Carbon::now();

        $row->is_active = $isActive;
        $row->updated_at = $now;
        $row->updated_by = $this->karyawan;
        $row->save();

        return response()->json([
            'message' => $isActive
                ? "{$label} diaktifkan"
                : "{$label} dinonaktifkan",
            'data' => $this->serializeGaAssetMasterRecord($row),
        ], 200);
    }
}
