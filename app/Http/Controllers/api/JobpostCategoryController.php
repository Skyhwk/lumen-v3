<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JobpostCategoryController extends Controller
{
    public function index()
    {
        $this->ensureTables();
        $categories = DB::table('jobpost_categories')->where('is_active', 1)->orderBy('name')->get()->map(function ($category) {
            $category->division_ids = $this->ids($category->division_ids ?? '[]');
            $category->mappings = DB::table('jobpost_category_mappings as map')
                ->join('master_divisi as division', 'division.id', '=', 'map.division_id')
                ->join('master_jabatan as position', 'position.id', '=', 'map.position_id')
                ->where('map.jobpost_category_id', $category->id)
                ->orderBy('division.nama_divisi')->orderBy('map.grade')->orderBy('position.nama_jabatan')
                ->get(['map.division_id', 'map.grade', 'map.position_id', 'division.nama_divisi as division_name', 'position.nama_jabatan as position_name']);
            return $category;
        });
        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function options()
    {
        $this->ensureTables();
        $divisions = DB::table('master_divisi')->where('is_active', 1)->orderBy('nama_divisi')->get(['id', 'nama_divisi as text']);
        $positions = collect();
        $grades = MasterKaryawan::where('is_active', 1)->whereNotNull('grade')->where('grade', '!=', '')->whereRaw('UPPER(grade) <> ?', ['DIREKSI'])
            ->distinct()->orderBy('grade')->pluck('grade')->values();
        return response()->json(['success' => true, 'data' => compact('divisions', 'positions', 'grades')]);
    }

    public function positions(Request $request)
    {
        $this->ensureTables();
        $divisionIds = array_values(array_unique(array_filter(array_map('intval', $this->ids($request->input('division_ids', []))))));
        if (empty($divisionIds)) return response()->json(['success' => true, 'data' => []]);
        $fromMaster = DB::table('master_jabatan')->where('is_active', 1)->whereIn('id_divisi', $divisionIds)
            ->get(['id', 'id_divisi as division_id', 'nama_jabatan as text']);
        $fromEmployees = DB::table('master_karyawan as employee')->join('master_jabatan as position', 'position.id', '=', 'employee.id_jabatan')
            ->where('employee.is_active', 1)->where('position.is_active', 1)->whereIn('employee.id_department', $divisionIds)
            ->groupBy('position.id', 'employee.id_department', 'position.nama_jabatan')
            ->get(['position.id', 'employee.id_department as division_id', 'position.nama_jabatan as text']);
        return response()->json(['success' => true, 'data' => $fromMaster->merge($fromEmployees)->unique(fn ($row) => $row->id . '|' . $row->division_id)->sortBy('text')->values()]);
    }

    public function save(Request $request)
    {
        $this->ensureTables();
        $id = (int) $request->input('id');
        $name = trim((string) $request->input('name'));
        $divisionIds = array_values(array_unique(array_filter(array_map('intval', $this->ids($request->input('division_ids', []))))));
        $mappings = collect($request->input('mappings', []))->map(fn ($item) => [
            'grade' => trim((string) ($item['grade'] ?? '')), 'position_id' => (int) ($item['position_id'] ?? 0), 'division_id' => (int) ($item['division_id'] ?? 0),
        ])->filter(fn ($item) => $item['grade'] !== '' && $item['position_id'] && $item['division_id'])->unique(fn ($item) => implode('|', $item))->values();
        if ($name === '' || empty($divisionIds)) return response()->json(['message' => 'Nama category dan minimal satu divisi wajib diisi.'], 422);
        if (DB::table('jobpost_categories')->where('name', $name)->where('id', '!=', $id ?: 0)->exists()) return response()->json(['message' => 'Jobpost Category sudah ada.'], 422);
        foreach ($mappings as $mapping) {
            if (!in_array($mapping['division_id'], $divisionIds, true)) return response()->json(['message' => 'Divisi jabatan tidak termasuk divisi yang dipilih.'], 422);
            $valid = DB::table('master_jabatan')->where('id', $mapping['position_id'])->where('is_active', 1)->where(function ($query) use ($mapping) {
                $query->where('id_divisi', $mapping['division_id'])->orWhereExists(function ($sub) use ($mapping) {
                    $sub->selectRaw('1')->from('master_karyawan')->whereColumn('master_karyawan.id_jabatan', 'master_jabatan.id')->where('master_karyawan.is_active', 1)->where('master_karyawan.id_department', $mapping['division_id']);
                });
            })->exists();
            if (!$valid) return response()->json(['message' => 'Jabatan tidak ditemukan pada divisi yang dipilih.'], 422);
        }
        DB::transaction(function () use (&$id, $name, $divisionIds, $mappings) {
            $now = Carbon::now();
            $data = ['name' => $name, 'division_ids' => json_encode($divisionIds), 'updated_by' => $this->karyawan, 'updated_at' => $now];
            if ($id) DB::table('jobpost_categories')->where('id', $id)->update($data);
            else { $data += ['is_active' => 1, 'created_by' => $this->karyawan, 'created_at' => $now]; $id = DB::table('jobpost_categories')->insertGetId($data); }
            DB::table('jobpost_category_mappings')->where('jobpost_category_id', $id)->delete();
            foreach ($mappings as $mapping) DB::table('jobpost_category_mappings')->insert($mapping + ['jobpost_category_id' => $id, 'created_at' => $now, 'updated_at' => $now]);
            $this->syncRecruitmentQuestionCategories();
        });
        return response()->json(['success' => true, 'message' => 'Jobpost Category berhasil disimpan.']);
    }

    public function delete(Request $request)
    {
        $this->ensureTables();
        $id = (int) $request->input('id');
        if (DB::table('personnel_requests')->where('jobpost_category_id', $id)->exists()) return response()->json(['message' => 'Category sudah dipakai personnel request dan tidak dapat dinonaktifkan.'], 422);
        DB::table('jobpost_categories')->where('id', $id)->update(['is_active' => 0, 'updated_by' => $this->karyawan, 'updated_at' => Carbon::now()]);
        $this->syncRecruitmentQuestionCategories();
        return response()->json(['success' => true, 'message' => 'Jobpost Category berhasil dinonaktifkan.']);
    }

    public function aliasOptions()
    {
        if (!Schema::hasTable('jobpost_categories')) {
            abort(response()->json(['message' => 'Tabel Jobpost Category belum tersedia.'], 503));
        }

        $rows = DB::table('jobpost_categories')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name as text']);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function publishOptions(Request $request)
    {
        return $this->aliasOptions();
    }

    private function ids($value): array { if (is_string($value)) $value = json_decode($value, true) ?: []; return is_array($value) ? $value : []; }
    private function ensureTables(): void { if (!Schema::hasTable('jobpost_categories') || !Schema::hasTable('jobpost_category_mappings')) abort(response()->json(['message' => 'Tabel Jobpost Category belum tersedia.'], 503)); }

    private function syncRecruitmentQuestionCategories(): void
    {
        if (!Schema::hasTable('recruitment_general_question_categories') || !Schema::hasColumn('recruitment_general_question_categories', 'jobpost_category_id')) return;
        $now = Carbon::now();
        DB::table('recruitment_general_question_categories')->whereNotNull('jobpost_category_id')->update(['is_active' => 0, 'updated_by' => $this->karyawan, 'updated_at' => $now]);
        $scopes = DB::table('jobpost_category_mappings as map')->join('jobpost_categories as category', 'category.id', '=', 'map.jobpost_category_id')
            ->where('category.is_active', 1)->groupBy('map.jobpost_category_id', 'map.grade')
            ->get(['map.jobpost_category_id', 'map.grade']);
        foreach ($scopes as $scope) {
            $existing = DB::table('recruitment_general_question_categories')->where('jobpost_category_id', $scope->jobpost_category_id)->where('grade', $scope->grade)->first();
            if ($existing) {
                DB::table('recruitment_general_question_categories')->where('id', $existing->id)->update(['is_active' => 1, 'updated_by' => $this->karyawan, 'updated_at' => $now]);
                continue;
            }
            DB::table('recruitment_general_question_categories')->insert(['jobpost_category_id' => $scope->jobpost_category_id, 'division_id' => 0, 'grade' => $scope->grade, 'question_count' => 1, 'is_active' => 1, 'created_by' => $this->karyawan, 'updated_by' => $this->karyawan, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}
