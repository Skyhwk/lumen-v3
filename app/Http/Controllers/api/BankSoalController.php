<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\QuestionOption;
use App\Models\ScaleType;
use App\Services\BankSoalImageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Schema;

class BankSoalController extends Controller
{
    public function index(Request $request)
    {
        $questions = Question::with(['options', 'scaleType', 'categoryMaster'])
            ->when($request->filled('question_category_id'), fn ($query) => $query->where('question_category_id', $request->question_category_id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', 'like', '%' . $request->status . '%'))->orderBy('id','desc');

        return DataTables::of($questions)->make(true);
    }

    public function categories(Request $request)
    {
        $query = QuestionCategory::withCount(['questions as current_question_count' => fn ($q) => $q->where('status', '!=', 'retired')])
            ->where('is_active', true);

        return response()->json([
            'success' => true,
            'data'    => $query->orderBy('name')->get(),
        ]);
    }

    public function updateCategoriesConfig(Request $request)
    {
        $categories = $request->input('categories', []);
        $hasIsShow = Schema::hasColumn('question_categories', 'is_show');
        $hasDuration = Schema::hasColumn('question_categories', 'duration_minutes');

        if (is_array($categories)) {
            foreach ($categories as $item) {
                if (isset($item['id'])) {
                    $updateData = [
                        'question_count' => isset($item['question_count']) ? (int) $item['question_count'] : 0,
                        'updated_at'     => Carbon::now(),
                    ];

                    if ($hasDuration && isset($item['duration_minutes'])) {
                        $updateData['duration_minutes'] = (int) $item['duration_minutes'];
                    }

                    if ($hasIsShow) {
                        $updateData['is_show'] = isset($item['is_show']) ? (bool) $item['is_show'] : (isset($item['is_active']) ? (bool) $item['is_active'] : false);
                    } else {
                        $updateData['is_active'] = isset($item['is_show']) ? (bool) $item['is_show'] : (isset($item['is_active']) ? (bool) $item['is_active'] : false);
                    }

                    QuestionCategory::where('id', $item['id'])->update($updateData);
                }
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Question configuration updated successfully.',
        ], 200);
    }

    public function storeCategory(Request $request)
    {
        $data['is_active'] = true;
        $data['created_by'] = $this->karyawan ?: 'System';
        $data['name'] = $request->name;
        $data['question_count'] = $request->question_count;

        return response()->json(['success' => true, 'message' => 'Kategori berhasil disimpan.', 'data' => QuestionCategory::create($data)], 201);
    }

    public function updateCategory(Request $request)
    {
        $category = QuestionCategory::findOrFail($request->input('id'));
        $data = $request->all();
        $category->update($data);

        return response()->json(['success' => true, 'message' => 'Kategori berhasil diperbarui.', 'data' => $category->fresh()]);
    }

    public function deleteCategory(Request $request)
    {
        $category = QuestionCategory::findOrFail($request->input('id'));
        if ($category->questions()->exists()) {
            return response()->json(['message' => 'Kategori yang sudah memiliki soal tidak dapat dinonaktifkan.'], 422);
        }
        $category->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => 'Kategori berhasil dinonaktifkan.']);
    }

    public function scaleTypes(Request $request)
    {
        $query = ScaleType::where('is_active', true)->orderBy('name');
        if ($request->filled('id')) $query->where('id', $request->id);
        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function indexScaleTypes()
    {
        return response()->json(['success' => true, 'data' => ScaleType::withCount('questions')->orderBy('name')->get()]);
    }

    public function storeScaleType(Request $request)
    {
        $data = $this->validatedScaleType($request);
        $data['is_active'] = 1;
        $data['created_by'] = $this->karyawan ?: 'System';
        return response()->json(['success' => true, 'message' => 'Scale Type berhasil disimpan.', 'data' => ScaleType::create($data)], 201);
    }

    public function updateScaleType(Request $request)
    {
        $scaleType = ScaleType::findOrFail($request->input('id'));
        $scaleType->update($this->validatedScaleType($request));
        return response()->json(['success' => true, 'message' => 'Scale Type berhasil diperbarui.', 'data' => $scaleType->fresh()]);
    }

    public function deleteScaleType(Request $request)
    {
        $scaleType = ScaleType::findOrFail($request->input('id'));
        $scaleType->update(['is_active' => 0]);
        return response()->json(['success' => true, 'message' => 'Scale Type berhasil dinonaktifkan.']);
    }

    public function store(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $this->validatedQuestion($request);
            $category = QuestionCategory::where('id', $data['question_category_id'])->where('is_active', true)->firstOrFail();
            $question = Question::create(array_merge($data, [
                'category' => $category->name,
                'question_image' => [],
                'is_active' => 1,
                'created_by' => $request->input('created_by', $this->karyawan ?: 'System'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]));
            $this->syncQuestionImages($question, $request->input('question_image', []));
            $this->replaceOptions($question, $data['options']);
            return response()->json(['message' => 'Bank soal berhasil disimpan.', 'data' => $question->fresh()->load(['options', 'scaleType', 'categoryMaster'])], 201);
        });
    }

    public function update(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $question = Question::with('options')->findOrFail($request->input('id'));
            $data = $this->validatedQuestion($request);
            $category = QuestionCategory::where('id', $data['question_category_id'])->where('is_active', true)->firstOrFail();
            $question->update(array_merge($data, ['category' => $category->name, 'updated_at' => Carbon::now()]));
            $this->syncQuestionImages($question, $request->input('question_image', []));
            $this->replaceOptions($question, $data['options']);
            return response()->json(['success' => true, 'message' => 'Bank soal berhasil diperbarui.', 'data' => $question->fresh()->load(['options', 'scaleType', 'categoryMaster'])]);
        });
    }

    public function updateStatus(Request $request)
    {
        $question = Question::findOrFail($request->input('id'));
        $question->update(['status' => $request->status]);
        return response()->json(['success' => true, 'message' => 'Status bank soal berhasil diperbarui.']);
    }

    public function delete(Request $request)
    {
        $question = Question::with('options')->findOrFail($request->input('id'));
        $imageService = new BankSoalImageService();
        foreach ($question->question_image as $image) $imageService->deleteImg($image);
        foreach ($question->options as $option) $imageService->deleteImg($option->option_image);
        $question->options()->delete();
        $question->delete();
        return response()->json(['success' => true, 'message' => 'Bank soal berhasil dihapus.']);
    }

    private function validatedScaleType(Request $request)
    {
        $data = $request->all();
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['description'] = trim((string) ($data['description'] ?? '')) ?: null;
        unset($data['code']);
        unset($data['sort_order']);

        if ($data['name'] === '') abort(422, 'Nama Scale Type wajib diisi.');

        $options = is_string($request->input('options')) ? json_decode($request->input('options'), true) : $request->input('options', []);
        if (!is_array($options) || count($options) < 2) abort(422, 'Scale Type minimal memiliki dua pilihan.');

        $data['options'] = collect($options)->map(function ($option) {
            return [
                'value' => trim((string) ($option['value'] ?? '')),
                'label' => trim((string) ($option['label'] ?? '')),
            ];
        })->values()->all();

        if (collect($data['options'])->contains(fn ($option) => $option['value'] === '' || $option['label'] === '')) abort(422, 'Nilai dan label setiap pilihan wajib diisi.');
        if (collect($data['options'])->pluck('value')->unique()->count() !== count($data['options'])) abort(422, 'Nilai pilihan Scale Type tidak boleh duplikat.');

        return $data;
    }

    private function validatedQuestion(Request $request)
    {
        $data = $request->all();
        $data['question_text'] = trim($data['question_text']);
        $data['difficulty'] = $data['difficulty'] ?? 'easy';
        $data['status'] = $data['status'] ?? 'draft';
        $data['scoring_type'] = $data['scoring_type'] ?? ($data['question_type'] === 'text' ? 'manual_review' : 'correct_answer');
        $data['options'] = $data['options'] ?? [];

        if ($data['question_type'] === 'scale' && !ScaleType::where('id', $data['scale_type_id'])->where('is_active', true)->exists()) abort(422, 'Scale Type wajib dipilih dan harus aktif.');
        if ($data['question_type'] !== 'scale') $data['scale_type_id'] = null;
        if (in_array($data['question_type'], ['single_choice', 'multiple_choice']) && count($data['options']) === 0) abort(422, 'Answer option wajib diisi.');
        if ($data['question_type'] === 'single_choice' && collect($data['options'])->filter(fn ($option) => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))->count() > 1) abort(422, 'Single Choice hanya boleh memiliki satu jawaban benar.');
        return $data;
    }

    private function syncQuestionImages(Question $question, $images)
    {
        $images = is_array($images) ? $images : ($images ? [$images] : []);
        $service = new BankSoalImageService();
        $previous = $question->question_image ?: [];
        $stored = collect($images)->map(function ($image, $index) use ($service, $question) {
            if (is_string($image) && strpos($image, 'data:image/') === 0) return $service->convertImg($image, $question->id, 'question-' . ($index + 1));
            return basename(parse_url((string) $image, PHP_URL_PATH) ?: (string) $image);
        })->filter()->values()->all();
        foreach ($previous as $image) {
            if (!in_array(basename(parse_url($image, PHP_URL_PATH) ?: $image), $stored, true)) $service->deleteImg($image);
        }
        $question->question_image = $stored;
        $question->save();
    }

    private function replaceOptions(Question $question, array $options)
    {
        $service = new BankSoalImageService();
        $keptImages = collect($options)->pluck('option_image')->filter()->map(function ($image) {
            return basename(parse_url((string) $image, PHP_URL_PATH) ?: (string) $image);
        })->all();
        foreach ($question->options as $option) {
            $fileName = basename(parse_url((string) $option->option_image, PHP_URL_PATH) ?: (string) $option->option_image);
            if ($fileName && !in_array($fileName, $keptImages, true)) $service->deleteImg($option->option_image);
        }
        $question->options()->delete();
        foreach ($options as $index => $option) {
            $model = QuestionOption::create(['question_id' => $question->id, 'option_text' => $option['option_text'] ?? null, 'option_image' => null, 'is_correct' => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN), 'option_order' => $option['option_order'] ?? ($index + 1), 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
            if (!empty($option['option_image'])) {
                $image = $option['option_image'];
                $model->option_image = strpos((string) $image, 'data:image/') === 0 ? $service->convertImg($image, $question->id, $model->id) : basename(parse_url((string) $image, PHP_URL_PATH) ?: (string) $image);
                $model->save();
            }
        }
    }
}