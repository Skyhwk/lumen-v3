<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MenuDataAccess;
use App\Services\MenuDataAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class MenuDataAccessController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = MenuDataAccess::query()->where('is_active', true);

            $menuKey = MenuDataAccess::normalizeMenuKey($request->input('menu_key', ''));
            if ($menuKey !== '') {
                $query->where('menu_key', $menuKey);
            }

            $rows = $query
                ->orderBy('menu_key')
                ->get(['id', 'menu_key', 'grades', 'created_by', 'created_at', 'updated_by', 'updated_at']);

            $labelMap = MenuDataAccessService::menuLabelMap();

            $mapped = $rows->map(function ($row) use ($labelMap) {
                $key = MenuDataAccess::normalizeMenuKey($row->menu_key);
                $grades = MenuDataAccess::normalizeGrades($row->grades);
                $minGrade = MenuDataAccess::resolveMinGrade($grades);
                $expanded = $minGrade
                    ? MenuDataAccess::expandFromMinGrade($minGrade)
                    : $grades;

                return (object) [
                    'id' => $row->id,
                    'menu_key' => $key,
                    'menu_label' => $labelMap[$key] ?? MenuDataAccessService::resolveMenuLabel($key),
                    'min_grade' => $minGrade,
                    'grades' => $expanded,
                    'grades_label' => MenuDataAccess::accessLabelFromMinGrade($minGrade),
                    'grade_count' => count($expanded),
                    'created_by' => $row->created_by,
                    'created_at' => $row->created_at
                        ? Carbon::parse($row->created_at)->format('Y-m-d H:i:s')
                        : null,
                    'updated_by' => $row->updated_by,
                    'updated_at' => $row->updated_at
                        ? Carbon::parse($row->updated_at)->format('Y-m-d H:i:s')
                        : null,
                ];
            })->values();

            return Datatables::of($mapped)->make(true);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        return $this->saveAccess($request, false);
    }

    public function update(Request $request)
    {
        return $this->saveAccess($request, true);
    }

    public function delete(Request $request)
    {
        $menuKey = MenuDataAccess::normalizeMenuKey($request->input('menu_key', ''));
        if ($menuKey === '') {
            return response()->json([
                'success' => false,
                'message' => 'menu_key wajib diisi.',
            ], 422);
        }

        $row = MenuDataAccess::query()
            ->where('menu_key', $menuKey)
            ->where('is_active', true)
            ->first();

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Pengaturan menu tidak ditemukan.',
            ], 404);
        }

        try {
            $now = Carbon::now()->format('Y-m-d H:i:s');
            $row->is_active = false;
            $row->deleted_by = $this->karyawan;
            $row->deleted_at = $now;
            $row->updated_by = $this->karyawan;
            $row->updated_at = $now;
            $row->save();

            return response()->json([
                'success' => true,
                'message' => 'Akses data menu berhasil dihapus.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Dropdown leaf menu yang belum punya pengaturan grade.
     * Kirim include_menu_key untuk mode edit agar menu saat ini tetap muncul.
     */
    public function getMenus(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => MenuDataAccessService::selectableMenus(
                true,
                $request->input('include_menu_key')
            ),
            'message' => 'Daftar menu berhasil diambil.',
        ]);
    }

    /** Opsi grade minimum (hierarki). */
    public function getGrades()
    {
        return response()->json([
            'success' => true,
            'data' => MenuDataAccess::gradeOptionList(),
            'message' => 'Daftar grade berhasil diambil.',
        ]);
    }

    /** Daftar grade aktif untuk sebuah menu_key. */
    public function gradesByMenu(Request $request)
    {
        $menuKey = MenuDataAccess::normalizeMenuKey($request->input('menu_key', ''));
        if ($menuKey === '') {
            return response()->json([
                'success' => false,
                'message' => 'menu_key wajib diisi.',
            ], 422);
        }

        $grades = MenuDataAccessService::allowedGrades($menuKey);
        $minGrade = MenuDataAccess::resolveMinGrade($grades);

        return response()->json([
            'success' => true,
            'data' => [
                'menu_key' => $menuKey,
                'min_grade' => $minGrade,
                'grades' => $grades,
                'grades_label' => MenuDataAccess::accessLabelFromMinGrade($minGrade),
            ],
        ]);
    }

    private function saveAccess(Request $request, bool $isUpdate)
    {
        $menuKey = MenuDataAccess::normalizeMenuKey($request->input('menu_key', ''));
        $minGrade = MenuDataAccess::normalizeGrade(
            $request->input('min_grade', $request->input('grade', ''))
        );

        // Kompatibel: jika min_grade kosong, turunkan dari grades[]
        if ($minGrade === '' || !MenuDataAccess::isKnownGrade($minGrade)) {
            $fromList = MenuDataAccess::resolveMinGrade($this->resolveGradesInput($request));
            $minGrade = $fromList ? MenuDataAccess::normalizeGrade($fromList) : '';
        }

        $validator = Validator::make(
            [
                'menu_key' => $menuKey,
                'min_grade' => $minGrade,
            ],
            [
                'menu_key' => 'required|string|max:100',
                'min_grade' => 'required|string|max:100',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if (!MenuDataAccess::isValidMenuKey($menuKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Format menu_key tidak valid.',
            ], 422);
        }

        if (!MenuDataAccess::isKnownGrade($minGrade)) {
            return response()->json([
                'success' => false,
                'message' => 'Grade minimum tidak dikenali.',
            ], 422);
        }

        $exists = MenuDataAccess::query()
            ->where('menu_key', $menuKey)
            ->where('is_active', true)
            ->exists();

        if (!$isUpdate && $exists) {
            return response()->json([
                'success' => false,
                'message' => 'Menu ini sudah punya pengaturan grade. Lakukan update untuk mengganti.',
            ], 422);
        }

        if ($isUpdate && !$exists) {
            return response()->json([
                'success' => false,
                'message' => 'Pengaturan menu tidak ditemukan.',
            ], 404);
        }

        try {
            $grades = MenuDataAccess::expandFromMinGrade($minGrade);
            $row = $this->upsertMenuGrades($menuKey, $grades);
            $canonicalMin = MenuDataAccess::canonicalGrade($minGrade);

            return response()->json([
                'success' => true,
                'message' => $isUpdate
                    ? 'Akses data menu berhasil diperbarui.'
                    : 'Akses data menu berhasil disimpan.',
                'data' => [
                    'id' => $row->id,
                    'menu_key' => $menuKey,
                    'menu_label' => MenuDataAccessService::resolveMenuLabel($menuKey),
                    'min_grade' => $canonicalMin,
                    'grades' => MenuDataAccess::normalizeGrades($row->grades),
                    'grades_label' => MenuDataAccess::accessLabelFromMinGrade($canonicalMin),
                ],
            ], $isUpdate ? 200 : 201);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    private function upsertMenuGrades(string $menuKey, array $grades): MenuDataAccess
    {
        $menuKey = MenuDataAccess::normalizeMenuKey($menuKey);
        $normalizedGrades = MenuDataAccess::normalizeGrades($grades);
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $row = MenuDataAccess::query()->where('menu_key', $menuKey)->first();
        if (!$row) {
            $row = new MenuDataAccess();
            $row->menu_key = $menuKey;
            $row->created_by = $this->karyawan;
            $row->created_at = $now;
        }

        $row->grades = $normalizedGrades;
        $row->is_active = true;
        $row->deleted_at = null;
        $row->deleted_by = null;
        $row->updated_by = $this->karyawan;
        $row->updated_at = $now;
        $row->save();

        return $row;
    }

    /**
     * @return list<string>
     */
    private function resolveGradesInput(Request $request): array
    {
        $raw = $request->input('grades', $request->input('grade'));

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            } elseif (str_contains($raw, ',')) {
                $raw = explode(',', $raw);
            } else {
                $raw = [$raw];
            }
        }

        return MenuDataAccess::normalizeGrades($raw);
    }
}
