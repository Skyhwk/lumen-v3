<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;
use App\Models\MasterKaryawan;

class RecruitmentGeneralQuestionController extends Controller
{
    public function categories()
    {
        $this->ensureTables();
        $categories = DB::table('recruitment_general_question_categories as category')
            ->leftJoin('jobpost_categories as jobpost', 'jobpost.id', '=', 'category.jobpost_category_id')
            ->leftJoin('recruitment_general_questions as question', function ($join) {
                $join->on('question.category_id', '=', 'category.id')
                    ->where('question.is_active', 1)
                    ->where('question.status', 'active');
            })
            ->where('category.is_active', 1)
            ->groupBy('category.id', 'category.jobpost_category_id', 'category.grade', 'category.question_count', 'jobpost.name')
            ->select('category.id', 'category.jobpost_category_id', 'category.grade', 'category.question_count', 'jobpost.name as jobpost_name', DB::raw('COUNT(question.id) as available_question_count'))
            ->orderBy('jobpost.name')->orderBy('category.grade')->get()->filter(fn ($category) => $this->canManageJobpost((int) $category->jobpost_category_id))
            ->map(function ($category) {
                $category->name = trim(($category->jobpost_name ?: 'Jobpost Category') . ' - ' . $category->grade);
                $category->is_question_shortage = (int) $category->available_question_count < (int) $category->question_count;
                return $category;
            })->values();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function scopes()
    {
        $this->ensureTables();
        $rows = DB::table('jobpost_category_mappings as map')->join('jobpost_categories as jobpost', 'jobpost.id', '=', 'map.jobpost_category_id')
            ->where('jobpost.is_active', 1)->groupBy('map.jobpost_category_id', 'map.grade', 'jobpost.name')
            ->select('map.jobpost_category_id', 'map.grade', 'jobpost.name')->orderBy('jobpost.name')->orderBy('map.grade')->get()
            ->filter(fn ($row) => $this->canManageJobpost((int) $row->jobpost_category_id))
            ->map(fn ($row) => ['jobpost_category_id' => (int) $row->jobpost_category_id, 'grade' => $row->grade, 'text' => $row->name . ' - ' . $row->grade])->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function positions(Request $request)
    {
        $categoryId = (int) $request->input('jobpost_category_id'); $grade = trim((string) $request->input('grade'));
        if ($categoryId < 1 || $grade === '') return response()->json(['message' => 'Jobpost Category dan grade wajib dipilih.'], 422);
        if (!$this->canManageJobpost($categoryId)) return response()->json(['message' => 'Kamu tidak memiliki akses untuk divisi ini.'], 403);

        return response()->json(['success' => true, 'data' => DB::table('jobpost_category_mappings as map')->join('master_jabatan as position', 'position.id', '=', 'map.position_id')
            ->where('map.jobpost_category_id', $categoryId)->where('map.grade', $grade)->whereIn('map.division_id', $this->allowedDivisionIds())->where('position.is_active', 1)->orderBy('position.nama_jabatan')->get(['position.id', 'position.nama_jabatan as text'])]);
    }

    public function storeCategory(Request $request)
    {
        $this->ensureTables();
        $data = $this->categoryData($request);
        $exists = DB::table('recruitment_general_question_categories')->where('jobpost_category_id', $data['jobpost_category_id'])->where('grade', $data['grade'])->where('is_active', 1)->exists();
        if ($exists) return response()->json(['message' => 'Kategori divisi dan grade tersebut sudah ada.'], 422);

        $id = DB::table('recruitment_general_question_categories')->insertGetId($data + ['created_by' => $this->karyawan, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
        return response()->json(['success' => true, 'data' => ['id' => $id], 'message' => 'Kategori berhasil disimpan.'], 201);
    }

    public function updateCategory(Request $request)
    {
        $this->ensureTables();
        $id = (int) $request->input('id');
        if (!$id || !$this->canManageCategory($id)) return response()->json(['message' => 'Kategori tidak ditemukan.'], 404);
        $data = $this->categoryData($request);
        $duplicate = DB::table('recruitment_general_question_categories')->where('jobpost_category_id', $data['jobpost_category_id'])->where('grade', $data['grade'])->where('is_active', 1)->where('id', '!=', $id)->exists();
        if ($duplicate) return response()->json(['message' => 'Kategori divisi dan grade tersebut sudah ada.'], 422);
        DB::table('recruitment_general_question_categories')->where('id', $id)->update($data + ['updated_by' => $this->karyawan, 'updated_at' => Carbon::now()]);
        return response()->json(['success' => true, 'message' => 'Kategori berhasil diperbarui.']);
    }

    public function deleteCategory(Request $request)
    {
        $this->ensureTables();
        $id = (int) $request->input('id');
        if (!$this->canManageCategory($id)) return response()->json(['message' => 'Kategori tidak ditemukan.'], 404);
        if (DB::table('recruitment_general_questions')->where('category_id', $id)->where('is_active', 1)->exists()) return response()->json(['message' => 'Kategori yang memiliki soal tidak dapat dinonaktifkan.'], 422);
        DB::table('recruitment_general_question_categories')->where('id', $id)->update(['is_active' => 0, 'updated_by' => $this->karyawan, 'updated_at' => Carbon::now()]);
        return response()->json(['success' => true, 'message' => 'Kategori berhasil dinonaktifkan.']);
    }

    public function index(Request $request)
    {
        $this->ensureTables();
        $categoryId = (int) $request->input('category_id');
        if (!$this->canManageCategory($categoryId)) return response()->json(['message' => 'Kategori tidak ditemukan.'], 404);
        $query = DB::table('recruitment_general_questions as question')->where('question.is_active', 1)->where('question.category_id', $categoryId)
            ->select('question.*')
            ->orderByDesc('question.id');
        return DataTables::of($query)->addColumn('options', function ($question) {
            return DB::table('recruitment_general_question_options as option')->leftJoin('master_jabatan as position', 'position.id', '=', 'option.position_id')
                ->where('option.question_id', $question->id)->orderBy('option.option_order')
                ->get(['option.id', 'option.option_text', 'option.position_id', 'option.option_order', 'position.nama_jabatan as position_name']);
        })->make(true);
    }

    public function store(Request $request)
    {
        return $this->saveQuestion($request);
    }

    public function update(Request $request)
    {
        return $this->saveQuestion($request, (int) $request->input('id'));
    }

    public function delete(Request $request)
    {
        $this->ensureTables();
        $question = DB::table('recruitment_general_questions')->where('id', (int) $request->input('id'))->first();
        if (!$question || !$this->canManageCategory((int) $question->category_id)) return response()->json(['message' => 'Soal tidak ditemukan.'], 404);
        DB::table('recruitment_general_questions')->where('id', $question->id)->update(['is_active' => 0, 'updated_by' => $this->karyawan, 'updated_at' => Carbon::now()]);
        return response()->json(['success' => true, 'message' => 'Soal berhasil dihapus.']);
    }

    private function saveQuestion(Request $request, int $id = 0)
    {
        $this->ensureTables();
        $category = DB::table('recruitment_general_question_categories')->where('id', (int) $request->input('category_id'))->where('is_active', 1)->first();
        if (!$category || !$this->canManageCategory((int) $category->id)) return response()->json(['message' => 'Kategori tidak valid.'], 422);
        $text = trim((string) $request->input('question_text'));
        $options = collect($request->input('options', []))->values();
        if ($text === '' || $options->count() < 2) return response()->json(['message' => 'Soal dan minimal dua opsi wajib diisi.'], 422);
        foreach ($options as $option) {
            if (trim((string) ($option['option_text'] ?? '')) === '' || (int) ($option['position_id'] ?? 0) < 1) return response()->json(['message' => 'Setiap opsi harus memiliki teks dan position.'], 422);
            $valid = DB::table('jobpost_category_mappings')->where('jobpost_category_id', $category->jobpost_category_id)->where('grade', $category->grade)->whereIn('division_id', $this->allowedDivisionIds())->where('position_id', (int) $option['position_id'])->exists();
            if (!$valid) return response()->json(['message' => 'Position opsi harus terdaftar pada Jobpost Category dan grade kategori.'], 422);
        }
        return DB::transaction(function () use ($id, $category, $text, $options) {
            $now = Carbon::now();
            if ($id) {
                $question = DB::table('recruitment_general_questions')->where('id', $id)->where('is_active', 1)->first();
                if (!$question) return response()->json(['message' => 'Soal tidak ditemukan.'], 404);
                if (!$this->canManageCategory((int) $question->category_id)) return response()->json(['message' => 'Soal tidak ditemukan.'], 404);
                DB::table('recruitment_general_questions')->where('id', $id)->update(['category_id' => $category->id, 'question_text' => $text, 'status' => 'active', 'updated_by' => $this->karyawan, 'updated_at' => $now]);
                DB::table('recruitment_general_question_options')->where('question_id', $id)->delete();
            } else {
                $id = DB::table('recruitment_general_questions')->insertGetId(['category_id' => $category->id, 'question_text' => $text, 'status' => 'active', 'is_active' => 1, 'created_by' => $this->karyawan, 'created_at' => $now, 'updated_at' => $now]);
            }
            foreach ($options as $index => $option) DB::table('recruitment_general_question_options')->insert(['question_id' => $id, 'option_text' => trim($option['option_text']), 'position_id' => (int) $option['position_id'], 'option_order' => $index + 1, 'created_at' => $now, 'updated_at' => $now]);
            return response()->json(['success' => true, 'message' => 'Soal berhasil disimpan.']);
        });
    }

    private function categoryData(Request $request): array
    {
        $categoryId = (int) $request->input('jobpost_category_id');
        $grade = trim((string) $request->input('grade'));
        $questionCount = (int) $request->input('question_count');
        if ($categoryId < 1 || $grade === '' || $questionCount < 1) abort(response()->json(['message' => 'Jobpost Category, grade, dan jumlah soal wajib valid.'], 422));
        if (!$this->canManageJobpost($categoryId) || !DB::table('jobpost_category_mappings')->where('jobpost_category_id', $categoryId)->where('grade', $grade)->exists()) abort(response()->json(['message' => 'Jobpost Category atau grade tidak valid.'], 422));
        return ['jobpost_category_id' => $categoryId, 'division_id' => 0, 'grade' => $grade, 'question_count' => $questionCount];
    }

    private function ensureTables(): void
    {
        if (!Schema::hasTable('recruitment_general_question_categories') || !Schema::hasColumn('recruitment_general_question_categories', 'jobpost_category_id') || !Schema::hasTable('recruitment_general_questions') || !Schema::hasTable('recruitment_general_question_options')) abort(response()->json(['message' => 'Struktur tabel Pertanyaan Umum Rekrutmen belum lengkap. Jalankan migration terbaru.'], 503));
    }

    private function allowedDivisionIds(): array
    {
        $employeeId = $this->effectiveEmployeeId();
        if (!$employeeId) return [];
        $employee = MasterKaryawan::where('id', $employeeId)->where('is_active', 1)->first(['id_department', 'grade']);
        if (!$employee || !$employee->id_department || !in_array(strtoupper(trim((string) $employee->grade)), ['MANAGER', 'SENIOR MANAGER'], true)) return [];
        return [(int) $employee->id_department];
    }

    private function canManageCategory(int $categoryId): bool
    {
        $query = DB::table('recruitment_general_question_categories')->where('id', $categoryId)->where('is_active', 1);
        $category = $query->first();
        return $category && $this->canManageJobpost((int) $category->jobpost_category_id);
    }

    private function canManageJobpost(int $jobpostCategoryId): bool
    {
        $divisionIds = $this->allowedDivisionIds();
        if (empty($divisionIds)) return false;
        return DB::table('jobpost_categories as category')->join('jobpost_category_mappings as map', 'map.jobpost_category_id', '=', 'category.id')
            ->where('category.id', $jobpostCategoryId)->where('category.is_active', 1)->whereIn('map.division_id', $divisionIds)->exists();
    }

    private function effectiveEmployeeId(): ?int
    {
        $id = $this->user_id;
        if (env('APP_ENV') !== 'production' && env('DEV_BYPASS_USER_ID') !== null && env('DEV_BYPASS_USER_ID') !== '') $id = env('DEV_BYPASS_USER_ID');
        return $id ? (int) $id : null;
    }
}
