<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\QuestionOption;
use App\Models\ScaleType;
use App\Services\BankSoalImageService;
use App\Services\GetBawahan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BankSoalController extends Controller
{
    public function index(Request $request)
    {
        $scope = $this->scope($request);

        $questions = Question::with(['options', 'scaleType', 'categoryMaster'])
            ->where('question_scope', $scope)
            ->when($scope === 'manager', function ($query) use ($request) {
                if ($request->filled('question_category_id')) {
                    $this->resolveManagerCategory((int) $request->question_category_id, 'view');
                } else {
                    $query->whereIn('question_category_id', $this->accessibleManagerCategoryIds());
                }
            })
            ->when($request->filled('question_category_id'), fn ($query) => $query->where('question_category_id', $request->question_category_id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', 'like', '%' . $request->status . '%'))->orderBy('id','desc');

        return DataTables::of($questions)->make(true);
    }

    public function categories(Request $request)
    {
        $query = QuestionCategory::withCount(['questions as current_question_count' => fn ($q) => $q->where('question_scope', 'hr')->where('status', '!=', 'retired')])
            ->where('is_active', true)
            ->where(function ($builder) {
                $builder->where('category_scope', 'hr')->orWhereNull('category_scope');
            });

        return response()->json([
            'success' => true,
            'data'    => $query
                ->orderByRaw("CASE WHEN UPPER(name) = 'DISC' THEN 1 WHEN UPPER(name) IN ('KOSTICK PAPI', 'PAPI KOSTICK') THEN 2 ELSE 3 END")
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function managerCategories(Request $request)
    {
        $managerHierarchyNames = $this->managerHierarchyNames();
        $categories = QuestionCategory::withCount(['questions as current_question_count' => fn ($q) => $q->where('question_scope', 'manager')->where('status', '!=', 'retired')])
            ->where('category_scope', 'manager')
            ->where('is_active', true)
            ->where(function ($query) use ($managerHierarchyNames) {
                $query->whereIn('owner_karyawan', $managerHierarchyNames)
                    ->orWhere('assigned_manager', $this->getEffectiveKaryawanName());
            })
            ->orderBy('name')
            ->get()
            ->map(function ($category) use ($managerHierarchyNames) {
                $category->can_manage = $this->canManageManagerCategory($category, $managerHierarchyNames);

                return $category;
            });

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function managerAvailableQuestionCount()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total' => $this->countManagerAvailableQuestions(),
            ],
        ]);
    }

    public function managerCategoryManagers()
    {
        $managers = GetBawahan::where('id', $this->user_id)->get()
            ->filter(function ($employee) {
                return strtoupper((string) ($employee->grade ?? '')) === 'MANAGER'
                    && (string) $employee->nama_lengkap !== (string) $this->karyawan;
            })
            ->map(function ($employee) {
                return [
                    'id' => $employee->nama_lengkap,
                    'text' => $employee->nama_lengkap . ($employee->nik_karyawan ? ' (' . $employee->nik_karyawan . ')' : ''),
                ];
            })
            ->values();

        return response()->json(['success' => true, 'data' => $managers]);
    }

    public function updateCategoriesConfig(Request $request)
    {
        $categories = $request->input('categories', []);
        $hasIsShow = Schema::hasColumn('question_categories', 'is_show');
        $hasDuration = Schema::hasColumn('question_categories', 'duration_minutes');
        $hasTimeLimit = Schema::hasColumn('question_categories', 'has_time_limit');

        if (is_array($categories)) {
            foreach ($categories as $item) {
                if (isset($item['id'])) {
                    $category = QuestionCategory::find($item['id']);
                    $questionCount = isset($item['question_count']) ? (int) $item['question_count'] : 0;

                    if ($category && !$this->isMandatoryAssessmentCategory($category->name)) {
                        $questionCount = max(30, $questionCount);
                    }

                    $updateData = [
                        'question_count' => $questionCount,
                        'updated_at'     => Carbon::now(),
                    ];

                    if ($hasTimeLimit) {
                        $updateData['has_time_limit'] = $this->parseFormBoolean(
                            $item['has_time_limit'] ?? null,
                            true
                        );
                    }

                    if ($hasDuration && isset($item['duration_minutes'])) {
                        $updateData['duration_minutes'] = (int) $item['duration_minutes'];
                    }

                    $isVisible = $this->parseFormBoolean(
                        $item['is_show'] ?? ($item['is_active'] ?? null),
                        false
                    );

                    if ($hasIsShow) {
                        $updateData['is_show'] = $isVisible;
                    } else {
                        $updateData['is_active'] = $isVisible;
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
        $categoryScope = strtolower(trim((string) $request->input('category_scope', 'hr')));
        $columns = Schema::getColumnListing('question_categories');
        $data = array_intersect_key($request->all(), array_flip($columns));
        $data['is_active'] = isset($data['is_active']) ? (bool) $data['is_active'] : true;
        $data['created_by'] = $this->karyawan ?: 'System';
        $data['name'] = trim((string) $request->name);
        $data['question_count'] = $request->question_count;

        if ($categoryScope === 'manager') {
            $assignedManager = trim((string) $request->input('assigned_manager', ''));
            $hasSubordinateManagers = $this->hasSubordinateManagers();
            if ($hasSubordinateManagers && $assignedManager === '') {
                $this->fail('Manager terkait wajib dipilih.');
            }
            if (!$hasSubordinateManagers) {
                $assignedManager = '';
            }
            if ($data['name'] === '') {
                $this->fail('Nama kategori wajib diisi.');
            }
            if ($assignedManager !== '' && QuestionCategory::where('category_scope', 'manager')
                ->where('owner_karyawan', $this->karyawan)
                ->where('assigned_manager', $assignedManager)
                ->where('is_active', true)
                ->exists()) {
                $this->fail('Kategori untuk manager ini sudah ada.');
            }

            $data['category_scope'] = 'manager';
            $data['owner_karyawan'] = $this->karyawan;
            $data['assigned_manager'] = $assignedManager !== '' ? $assignedManager : null;
        } else {
            $data['category_scope'] = 'hr';
        }

        return response()->json(['success' => true, 'message' => 'Kategori berhasil disimpan.', 'data' => QuestionCategory::create($data)], 201);
    }

    public function updateCategory(Request $request)
    {
        $category = QuestionCategory::findOrFail($request->input('id'));
        if ($this->isManagerCategory($category)) {
            $this->resolveManagerCategory((int) $category->id, 'write');
        }

        $columns = Schema::getColumnListing('question_categories');
        $allowedCols = array_diff($columns, ['id', 'created_at', 'category_scope', 'owner_karyawan']);
        $data = array_intersect_key($request->all(), array_flip($allowedCols));
        if ($this->isManagerCategory($category) && $request->filled('assigned_manager')) {
            if (!$this->hasSubordinateManagers()) {
                unset($data['assigned_manager']);
            } else {
                $assignedManager = trim((string) $request->input('assigned_manager'));
                if ($assignedManager === '') {
                    $this->fail('Manager terkait wajib dipilih.');
                }
                if (QuestionCategory::where('category_scope', 'manager')
                    ->where('owner_karyawan', $category->owner_karyawan)
                    ->where('assigned_manager', $assignedManager)
                    ->where('is_active', true)
                    ->where('id', '!=', $category->id)
                    ->exists()) {
                    $this->fail('Kategori untuk manager ini sudah ada.');
                }
                $data['assigned_manager'] = $assignedManager;
            }
        }

        $category->update($data);

        return response()->json(['success' => true, 'message' => 'Kategori berhasil diperbarui.', 'data' => $category->fresh()]);
    }

    public function deleteCategory(Request $request)
    {
        $category = QuestionCategory::findOrFail($request->input('id'));
        if ($this->isManagerCategory($category)) {
            $this->resolveManagerCategory((int) $category->id, 'write');
        }
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
        $data['code'] = $this->generateScaleTypeCode($data['name']);
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

    public function userAssessmentConfig()
    {
        return response()->json([
            'success' => true,
            'data' => DB::table('user_assessment_configs')
                ->where('owner_karyawan', $this->managerOwnerName())
                ->first(),
        ]);
    }

    public function saveUserAssessmentConfig(Request $request)
    {
        $questionCount = (int) $request->input('question_count');
        $durationMinutes = (int) $request->input('duration_minutes');
        if ($questionCount < 1 || $durationMinutes < 1) {
            $this->fail('Jumlah soal dan durasi wajib lebih dari 0.');
        }

        $owner = $this->managerOwnerName();
        $existing = DB::table('user_assessment_configs')->where('owner_karyawan', $owner)->first();
        $data = [
            'question_count' => $questionCount,
            'duration_minutes' => $durationMinutes,
            'is_active' => 1,
            'updated_by' => $owner,
            'updated_at' => Carbon::now(),
        ];

        if ($existing) {
            DB::table('user_assessment_configs')->where('id', $existing->id)->update($data);
        } else {
            $data += [
                'owner_karyawan' => $owner,
                'created_by' => $owner,
                'created_at' => Carbon::now(),
            ];
            DB::table('user_assessment_configs')->insert($data);
        }

        return response()->json(['success' => true, 'message' => 'Konfigurasi assessment berhasil disimpan.']);
    }

    public function downloadImportTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Bank Soal');
        $headers = ['Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Option E', 'Option F', 'Correct Option', 'Difficulty', 'Status', 'Explanation'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            'Contoh: Manakah jawaban yang benar?',
            'Pilihan A',
            'Pilihan B',
            'Pilihan C',
            'Pilihan D',
            '',
            '',
            'A',
            'easy',
            'active',
            'Contoh penjelasan atau konteks tambahan untuk soal ini',
        ], null, 'A2');
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
        ]);
        $sheet->freezePane('A2');
        $sheet->getColumnDimension('A')->setWidth(45);
        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $column) $sheet->getColumnDimension($column)->setWidth(28);
        $sheet->getColumnDimension('H')->setWidth(16);
        $sheet->getColumnDimension('I')->setWidth(14);
        $sheet->getColumnDimension('J')->setWidth(14);
        $sheet->getColumnDimension('K')->setWidth(45);

        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Petunjuk');
        $guide->fromArray([
            ['Petunjuk Upload Bank Soal'],
            ['PENTING: Hapus baris contoh pada sheet Bank Soal sebelum upload. Jika tidak dihapus, contoh tersebut akan ikut dibuat menjadi soal.'],
            ['1. Isi satu soal per baris pada sheet Bank Soal'],
            ['2. Minimal isi Question, Option A, Option B, Correct Option, Difficulty, dan Status'],
            ['3. Kolom opsi dapat ditambahkan berurutan (Option A, Option B, dan seterusnya) sebelum Correct Option'],
            ['4. Correct Option harus sesuai huruf pilihan yang terisi pada baris tersebut'],
            ['5. Difficulty: easy, medium, atau hard'],
            ['6. Status: draft atau active. Jika kolom atau nilainya kosong, status otomatis active'],
            ['7. Explanation bersifat opsional dan boleh dikosongkan. Kolom ini untuk penjelasan atau konteks soal, bukan penjelasan jawaban tertentu'],
            ['8. Jangan mengubah nama dan urutan header selain menambahkan kolom Option secara berurutan'],
        ], null, 'A1');
        $guide->getStyle('A1')->getFont()->setBold(true);
        $guide->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '9C0006']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC7CE']],
        ]);
        $guide->getColumnDimension('A')->setWidth(110);
        $spreadsheet->setActiveSheetIndex(0);

        $directory = public_path('bank-soal');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $fileName = 'template-bank-soal.xlsx';
        (new Xlsx($spreadsheet))->save($directory . DIRECTORY_SEPARATOR . $fileName);

        return response()->json([
            'success' => true,
            'data' => $fileName,
        ]);
    }

    public function importQuestions(Request $request)
    {
        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            $this->fail('File template wajib diunggah.');
        }

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            $this->fail('Format file harus .xlsx atau .csv.');
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            $this->fail('Ukuran file maksimal 5 MB.');
        }

        try {
            if ($extension === 'csv') {
                $reader = IOFactory::createReader('Csv');
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                $reader->setInputEncoding('UTF-8');
                $spreadsheet = $reader->load($file->getRealPath());
            } else {
                $spreadsheet = IOFactory::load($file->getRealPath());
            }
            $rows = $spreadsheet->getActiveSheet()->toArray('', false, true, false);
        } catch (\Throwable $exception) {
            $this->fail('Template tidak dapat dibaca. Gunakan file .xlsx atau .csv dari template yang disediakan.');
        }

        if (count($rows) < 2) {
            $this->fail('Template belum memiliki data soal.');
        }

        $headers = array_map(fn ($value) => strtolower(trim(ltrim((string) $value, "\xEF\xBB\xBF"))), array_shift($rows));
        while ($headers && end($headers) === '') {
            array_pop($headers);
        }
        $correctOptionIndex = array_search('correct option', $headers, true);
        $optionHeaders = $correctOptionIndex === false ? [] : array_slice($headers, 1, $correctOptionIndex - 1);
        $expectedOptionHeaders = [];
        for ($optionIndex = 0; $optionIndex < count($optionHeaders) && $optionIndex < 26; $optionIndex++) {
            $expectedOptionHeaders[] = 'option ' . chr(ord('a') + $optionIndex);
        }
        $fixedHeaders = $correctOptionIndex === false ? [] : array_slice($headers, $correctOptionIndex);
        $validFixedHeaders = [
            ['correct option', 'difficulty', 'status', 'explanation'],
            ['correct option', 'difficulty', 'explanation'],
        ];
        if (
            ($headers[0] ?? null) !== 'question'
            || count($optionHeaders) < 2
            || count($optionHeaders) > 26
            || $optionHeaders !== $expectedOptionHeaders
            || !in_array($fixedHeaders, $validFixedHeaders, true)
        ) {
            $this->fail('Format kolom template tidak sesuai. Unduh template terbaru lalu isi tanpa mengubah header.');
        }

        $scope = $this->scope($request);
        $category = $scope === 'manager'
            ? $this->resolveManagerCategory((int) $request->input('question_category_id'), 'write')
            : QuestionCategory::where('id', $request->input('question_category_id'))->where('is_active', true)->first();
        if ($scope === 'hr' && !$category) {
            $this->fail('Pilih kategori aktif sebelum mengunggah soal.');
        }
        if ($scope === 'manager' && !$category) {
            $this->fail('Pilih kategori aktif sebelum mengunggah soal.');
        }

        $questions = [];
        $errors = [];
        $existingQuestionKeys = Question::query()
            ->with(['options' => function ($query) {
                $query->orderBy('option_order')->orderBy('id');
            }])
            ->where('question_scope', $scope)
            ->where('question_category_id', $category->id)
            ->where('is_active', 1)
            ->orderBy('id')
            ->get(['id', 'question_text'])
            ->mapWithKeys(fn ($question) => [
                $this->importQuestionKey($question->question_text, $question->options->toArray()) => $question->id,
            ])
            ->all();
        foreach ($rows as $rowIndex => $row) {
            if (!array_filter($row, fn ($value) => trim((string) $value) !== '')) continue;

            $row = array_pad($row, count($headers), '');
            $data = array_combine($headers, array_slice($row, 0, count($headers)));
            $questionText = trim((string) $data['question']);
            $correctOption = strtoupper(trim((string) $data['correct option']));
            $difficulty = strtolower(trim((string) $data['difficulty'] ?: 'easy'));
            $status = strtolower(trim((string) ($data['status'] ?? ''))) ?: 'active';
            $options = [];

            foreach ($optionHeaders as $optionHeader) {
                $letter = strtoupper(substr($optionHeader, strlen('option ')));
                $optionText = trim((string) $data[$optionHeader]);
                if ($optionText !== '') {
                    $options[] = [
                        'option_text' => $optionText,
                        'is_correct' => $correctOption === $letter,
                        'option_order' => count($options) + 1,
                    ];
                }
            }

            $line = $rowIndex + 2;
            if ($questionText === '') $errors[] = "Baris {$line}: Question wajib diisi.";
            if (count($options) < 2) $errors[] = "Baris {$line}: minimal isi Option A dan Option B.";
            if (!collect($options)->contains('is_correct', true)) $errors[] = "Baris {$line}: Correct Option harus sesuai huruf pilihan yang terisi.";
            if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) $errors[] = "Baris {$line}: Difficulty harus easy, medium, atau hard.";
            if (!in_array($status, ['draft', 'active'], true)) $errors[] = "Baris {$line}: Status harus draft atau active.";

            $questionKey = $questionText !== ''
                ? $this->importQuestionKey($questionText, $options)
                : '__row_' . $line;
            $questions[$questionKey] = [
                'existing_question_id' => $questionText !== '' ? ($existingQuestionKeys[$questionKey] ?? null) : null,
                'question_text' => $questionText,
                'explanation' => trim((string) $data['explanation']) ?: null,
                'difficulty' => $difficulty,
                'status' => $status,
                'options' => $options,
            ];
        }

        if (!$questions) $this->fail('Template belum memiliki baris soal yang dapat diimpor.');
        if (count($questions) > 500) $this->fail('Maksimal 500 soal untuk satu kali unggah.');
        if ($errors) return response()->json(['message' => 'Ada data template yang perlu diperbaiki.', 'errors' => $errors], 422);

        $result = ['created' => 0, 'updated' => 0];
        DB::transaction(function () use ($questions, $scope, $category, &$result) {
            foreach ($questions as $data) {
                if ($data['existing_question_id']) {
                    $question = Question::where('id', $data['existing_question_id'])
                        ->where('is_active', 1)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $this->replaceOptions($question, $data['options']);
                    $question->update(['updated_at' => Carbon::now()]);
                    $result['updated']++;
                    continue;
                }

                $question = Question::create([
                    'question_scope' => $scope,
                    'owner_karyawan' => $scope === 'manager' ? $category->owner_karyawan : null,
                    'question_category_id' => $category->id ?? null,
                    'category' => $category->name ?? null,
                    'question_type' => 'single_choice',
                    'scale_type_id' => null,
                    'scoring_type' => 'correct_answer',
                    'question_text' => $data['question_text'],
                    'question_image' => [],
                    'explanation' => $data['explanation'],
                    'difficulty' => $data['difficulty'],
                    'status' => $data['status'],
                    'is_active' => 1,
                    'created_by' => $this->karyawan ?: 'System',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
                $this->replaceOptions($question, $data['options']);
                $result['created']++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => $result['created'] . ' soal dibuat dan ' . $result['updated'] . ' soal diperbarui.',
        ]);
    }

    public function store(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $scope = $this->scope($request);
            $data = $this->validatedQuestion($request);
            $category = $scope === 'manager'
                ? $this->resolveManagerCategory((int) $data['question_category_id'], 'write')
                : QuestionCategory::where('id', $data['question_category_id'])->where('is_active', true)->firstOrFail();
            $managerOwner = $scope === 'manager' ? $category->owner_karyawan : null;
            $question = Question::create(array_merge($data, [
                'question_scope' => $scope,
                'owner_karyawan' => $managerOwner,
                'question_category_id' => $category->id,
                'category' => $category->name,
                'question_image' => [],
                'is_active' => 1,
                'created_by' => $request->input('created_by', $this->karyawan ?: 'System'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]));
            $this->syncQuestionImages($question, $request->input('question_image', []));
            if ($data['question_type'] === 'scale') {
                $this->replaceOptions($question, []);
            } else {
                $this->replaceOptions($question, $data['options']);
            }
            return response()->json(['message' => 'Bank soal berhasil disimpan.', 'data' => $question->fresh()->load(['options', 'scaleType', 'categoryMaster'])], 201);
        });
    }

    public function update(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $question = Question::with('options')->findOrFail($request->input('id'));
            $scope = $this->scope($request);
            $this->ensureQuestionScope($question, $scope, $request, 'write');
            $data = $this->validatedQuestion($request);
            $category = $scope === 'manager'
                ? $this->resolveManagerCategory((int) $data['question_category_id'], 'write')
                : QuestionCategory::where('id', $data['question_category_id'])->where('is_active', true)->firstOrFail();
            $question->update(array_merge($data, [
                'question_scope' => $scope,
                'owner_karyawan' => $scope === 'manager' ? $category->owner_karyawan : null,
                'question_category_id' => $category->id,
                'category' => $category->name,
                'updated_at' => Carbon::now(),
            ]));
            $this->syncQuestionImages($question, $request->input('question_image', []));
            if ($data['question_type'] === 'scale') {
                $this->replaceOptions($question, []);
            } else {
                $this->replaceOptions($question, $data['options']);
            }
            return response()->json(['success' => true, 'message' => 'Bank soal berhasil diperbarui.', 'data' => $question->fresh()->load(['options', 'scaleType', 'categoryMaster'])]);
        });
    }

    public function updateStatus(Request $request)
    {
        $question = Question::findOrFail($request->input('id'));
        $this->ensureQuestionScope($question, $this->scope($request), $request, 'write');
        $question->update(['status' => $request->status]);
        return response()->json(['success' => true, 'message' => 'Status bank soal berhasil diperbarui.']);
    }

    public function bulkAction(Request $request)
    {
        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        $action = strtolower(trim((string) $request->input('action')));

        if ($ids->isEmpty()) $this->fail('Pilih minimal satu soal.');
        if ($ids->count() > 100) $this->fail('Maksimal 100 soal untuk satu bulk action.');
        if (!in_array($action, ['active', 'draft', 'retired', 'delete'], true)) {
            $this->fail('Bulk action tidak valid.');
        }

        $questions = Question::with('options')->whereIn('id', $ids)->get();
        if ($questions->count() !== $ids->count()) $this->fail('Sebagian soal tidak ditemukan.');

        foreach ($questions as $question) {
            $this->ensureQuestionScope($question, $this->scope($request), $request, 'write');
        }

        DB::transaction(function () use ($questions, $action) {
            if ($action !== 'delete') {
                Question::whereIn('id', $questions->pluck('id'))->update([
                    'status' => $action,
                    'updated_at' => Carbon::now(),
                ]);
                return;
            }

            $imageService = new BankSoalImageService();
            foreach ($questions as $question) {
                foreach ($question->question_image as $image) $imageService->deleteImg($image);
                foreach ($question->options as $option) $imageService->deleteImg($option->option_image);
                $question->options()->delete();
                $question->delete();
            }
        });

        $label = $action === 'delete' ? 'dihapus' : 'diubah menjadi ' . $action;
        return response()->json([
            'success' => true,
            'message' => $questions->count() . " soal berhasil {$label}.",
        ]);
    }

    public function delete(Request $request)
    {
        $question = Question::with('options')->findOrFail($request->input('id'));
        $this->ensureQuestionScope($question, $this->scope($request), $request, 'write');
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

        if ($data['name'] === '') $this->fail('Nama Scale Type wajib diisi.');

        $options = is_string($request->input('options')) ? json_decode($request->input('options'), true) : $request->input('options', []);
        if (!is_array($options) || count($options) < 2) $this->fail('Scale Type minimal memiliki dua pilihan.');

        $data['options'] = collect($options)->map(function ($option) {
            $value = trim((string) ($option['value'] ?? ''));
            $label = trim((string) ($option['label'] ?? ''));
            if ($value === '' || $label === '') {
                $this->fail('Nilai dan label setiap pilihan wajib diisi.');
            }
            if (!is_numeric($value)) {
                $this->fail('Nilai pilihan Scale Type harus berupa angka.');
            }

            return [
                'value' => strpos($value, '.') !== false ? (float) $value : (int) $value,
                'label' => $label,
            ];
        })->sortByDesc('value')->values()->all();

        if (collect($data['options'])->pluck('value')->unique()->count() !== count($data['options'])) {
            $this->fail('Nilai pilihan Scale Type tidak boleh duplikat.');
        }

        return $data;
    }

    private function generateScaleTypeCode(string $name): string
    {
        $base = strtoupper(Str::slug($name, '_'));
        if ($base === '') {
            $base = 'SCALE';
        }
        $base = substr($base, 0, 45);
        $code = $base;
        $counter = 1;
        while (ScaleType::where('code', $code)->exists()) {
            $code = substr($base, 0, 40) . '_' . $counter;
            $counter++;
        }

        return $code;
    }

    private function validatedQuestion(Request $request)
    {
        $data = $request->all();
        $data['question_text'] = trim($data['question_text']);
        $data['difficulty'] = $data['difficulty'] ?? 'easy';
        $data['status'] = $data['status'] ?? 'active';
        $data['question_type'] = in_array($request->input('question_type'), ['single_choice', 'multiple_choice', 'scale'], true)
            ? $request->input('question_type')
            : 'single_choice';
        $data['options'] = $data['options'] ?? [];
        if ($this->scope($request) === 'manager') {
            if (empty($data['question_category_id'])) {
                $this->fail('Pilih kategori soal terlebih dahulu.');
            }
            $this->resolveManagerCategory((int) $data['question_category_id'], 'write');
            $data['question_type'] = 'single_choice';
            $data['scoring_type'] = 'correct_answer';
            $data['scale_type_id'] = null;
        }

        if ($data['question_type'] === 'scale') {
            $scaleTypeId = (int) $request->input('scale_type_id');
            if (!$scaleTypeId) {
                $this->fail('Scale Type wajib dipilih untuk soal bertipe scale.');
            }
            ScaleType::where('id', $scaleTypeId)->where('is_active', true)->firstOrFail();
            $data['scale_type_id'] = $scaleTypeId;
            $data['scoring_type'] = 'scale_average';
            $data['options'] = [];
            return $data;
        }

        $data['scale_type_id'] = null;
        $data['scoring_type'] = 'correct_answer';

        if (in_array($data['question_type'], ['single_choice', 'multiple_choice']) && count($data['options']) === 0) $this->fail('Answer option wajib diisi.');
        $correctOptionCount = collect($data['options'])->filter(fn ($option) => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))->count();
        if ($correctOptionCount === 0) $this->fail('Pilih minimal satu Answer Option sebagai jawaban Correct.');
        if ($data['question_type'] === 'single_choice' && $correctOptionCount > 1) $this->fail('Single Choice hanya boleh memiliki satu jawaban benar.');
        return $data;
    }

    private function scope(Request $request)
    {
        $scope = strtolower(trim((string) $request->input('question_scope', 'hr')));
        if (!in_array($scope, ['hr', 'manager'], true)) {
            $this->fail('Scope bank soal tidak valid.');
        }

        return $scope;
    }

    private function hasSubordinateManagers(): bool
    {
        return GetBawahan::where('id', $this->user_id)->get()
            ->contains(function ($employee) {
                return strtoupper((string) ($employee->grade ?? '')) === 'MANAGER'
                    && (string) $employee->nama_lengkap !== (string) $this->karyawan;
            });
    }

    private function managerOwnerName()
    {
        if (!$this->karyawan) {
            $this->fail('User tidak terautentikasi.');
        }

        return $this->karyawan;
    }

    private function isManagerCategory($category): bool
    {
        return strtolower((string) ($category->category_scope ?? 'hr')) === 'manager';
    }

    private function getEffectiveKaryawanName()
    {
        $isDevMode = env('APP_ENV') !== 'production' && env('DEV_BYPASS_USER_ID') !== null;
        $devUserId = env('DEV_BYPASS_USER_ID');
        
        if ($isDevMode && $devUserId) {
            $devKaryawan = \App\Models\MasterKaryawan::where('id', $devUserId)->first();
            if ($devKaryawan) {
                return $devKaryawan->nama_lengkap;
            }
        }
        return $this->karyawan;
    }

    private function countManagerAvailableQuestions(): int
    {
        return Question::query()
            ->whereIn('owner_karyawan', $this->managerHierarchyNames())
            ->where('is_active', 1)
            ->where('question_type', 'single_choice')
            ->count();
    }

    private function accessibleManagerCategoryIds(): array
    {
        $managerHierarchyNames = $this->managerHierarchyNames();

        return QuestionCategory::query()
            ->where('category_scope', 'manager')
            ->where('is_active', true)
            ->where(function ($query) use ($managerHierarchyNames) {
                $query->whereIn('owner_karyawan', $managerHierarchyNames)
                    ->orWhere('assigned_manager', $this->getEffectiveKaryawanName());
            })
            ->pluck('id')
            ->all();
    }

    private function canManageManagerCategory($category, ?array $managerHierarchyNames = null): bool
    {
        $managerHierarchyNames ??= $this->managerHierarchyNames();
        $canManageOwner = in_array((string) $category->owner_karyawan, $managerHierarchyNames, true);
        $isAssignedManager = (string) $category->assigned_manager === (string) $this->getEffectiveKaryawanName();

        return $canManageOwner || $isAssignedManager;
    }

    private function resolveManagerCategory(int $categoryId, string $accessLevel = 'view'): QuestionCategory
    {
        $category = QuestionCategory::where('id', $categoryId)
            ->where('category_scope', 'manager')
            ->where('is_active', true)
            ->firstOrFail();

        if (!$this->canManageManagerCategory($category)) {
            $this->fail($accessLevel === 'write'
                ? 'Hanya pemilik kategori, atasannya, atau manager terkait yang dapat mengubah data.'
                : 'Kategori tidak dapat diakses.');
        }

        return $category;
    }

    private function getEffectiveUserId()
    {
        $isDevMode = env('APP_ENV') !== 'production' && env('DEV_BYPASS_USER_ID') !== null;
        if ($isDevMode) {
            return env('DEV_BYPASS_USER_ID');
        }
        return $this->user_id;
    }

    private function managerHierarchyNames(): array
    {
        return GetBawahan::where('id', $this->getEffectiveUserId())->get()
            ->pluck('nama_lengkap')
            ->push($this->getEffectiveKaryawanName())
            ->filter()
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->values()
            ->all();
    }

    private function ensureQuestionScope(Question $question, $scope, Request $request, string $accessLevel = 'view')
    {
        if ($question->question_scope !== $scope) {
            $this->fail('Soal tidak dapat diakses dari bank soal ini.');
        }

        if ($scope === 'manager') {
            $this->resolveManagerCategory((int) $question->question_category_id, $accessLevel);
        }
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

    private function isMandatoryAssessmentCategory(?string $name): bool
    {
        $normalized = strtoupper(trim((string) $name));

        return in_array($normalized, ['DISC', 'KOSTICK PAPI', 'PAPI KOSTICK'], true);
    }

    private function parseFormBoolean($value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return filter_var($normalized, FILTER_VALIDATE_BOOLEAN);
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

    private function normalizedQuestionText($value): string
    {
        $text = trim(strip_tags((string) $value));
        $text = preg_replace('/\s+/u', ' ', $text) ?: '';

        return Str::lower($text);
    }

    private function importQuestionKey($questionText, array $options): string
    {
        $normalizedOptions = collect($options)
            ->sortBy(function ($option, $index) {
                return (int) ($option['option_order'] ?? ($index + 1));
            })
            ->values()
            ->map(function ($option, $index) {
                return [
                    'order' => (int) ($option['option_order'] ?? ($index + 1)),
                    'text' => $this->normalizedQuestionText($option['option_text'] ?? ''),
                    'is_correct' => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            })
            ->all();

        return hash('sha256', json_encode([
            'question' => $this->normalizedQuestionText($questionText),
            'options' => $normalizedOptions,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function fail(string $message)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 401);
    }
}
