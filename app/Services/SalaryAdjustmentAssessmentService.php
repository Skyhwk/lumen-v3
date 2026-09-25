<?php

namespace App\Services;

use App\Helpers\FrontendPublicUrl;
use App\Models\QuestionCategory;
use App\Models\SalaryAdjustmentAssessment;
use App\Models\SalaryAdjustmentAssessmentSession;
use App\Models\SalaryAdjustmentRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalaryAdjustmentAssessmentService
{
    private const MIN_OPTIONAL_QUESTION_COUNT = 1;
    private const MAX_OPTIONAL_QUESTION_COUNT = 100;

    /** @var SalaryAdjustmentAssessmentEngine */
    private $engine;

    public function __construct(SalaryAdjustmentAssessmentEngine $engine)
    {
        $this->engine = $engine;
    }

    public function generateLink(SalaryAdjustmentRequest $request, array $payload, string $generatedBy): array
    {
        $allowedStatuses = [
            SalaryAdjustmentWorkflowService::STATUS_HRD_PROCESSING,
            SalaryAdjustmentWorkflowService::STATUS_WAITING_ASSESSMENT,
        ];

        if (!in_array($request->status, $allowedStatuses, true)) {
            throw new \RuntimeException('Permohonan tidak dapat digenerate link assessment pada status ini.');
        }

        if (!(new EmployeeAdjustmentWorkflowResolver())->requiresAssessment($request->request_type)) {
            throw new \RuntimeException('Jenis permohonan ini tidak memerlukan assessment.');
        }

        $categoryConfigs = $this->orderCategoryConfigs($this->normalizeCategoryQuestion($payload));
        if (empty($categoryConfigs)) {
            throw new \RuntimeException('Minimal pilih 1 modul assessment.');
        }

        $existing = SalaryAdjustmentAssessment::where('request_id', $request->id)->first();
        if ($existing && $existing->attempt_status === 'completed') {
            throw new \RuntimeException('Assessment sudah selesai dikerjakan.');
        }

        return DB::connection('mysql')->transaction(function () use (
            $request,
            $existing,
            $categoryConfigs,
            $generatedBy
        ) {
            $token = bin2hex(random_bytes(32));
            $frontendOrigin = trim((string) ($payload['frontend_origin'] ?? ''));
            $linkUrl = FrontendPublicUrl::buildAssessmentLink(
                $token,
                $frontendOrigin !== '' ? $frontendOrigin : null
            );

            $sessionPayloads = [];
            $totalDuration = 0;
            $hasAnyTimeLimit = false;

            foreach ($categoryConfigs as $config) {
                $category = DB::table('question_categories')
                    ->where('id', $config['id'])
                    ->where('is_active', 1)
                    ->first();

                if (!$category) {
                    throw new \RuntimeException('Kategori soal tidak ditemukan.');
                }

                $categoryForEngine = (object) array_merge((array) $category, [
                    'question_count' => $config['question_count'],
                ]);

                $questions = $this->engine->buildQuestionsForCategory($categoryForEngine);
                if (empty($questions)) {
                    throw new \RuntimeException('Bank soal untuk modul ' . $category->name . ' kosong.');
                }

                $sessionDuration = $config['has_time_limit'] ? max(1, (int) $config['duration_minutes']) : 0;
                if ($sessionDuration > 0) {
                    $hasAnyTimeLimit = true;
                    $totalDuration += $sessionDuration;
                }

                $sessionPayloads[] = [
                    'question_category_id' => (int) $category->id,
                    'category_name' => $category->name,
                    'question_count' => count($questions),
                    'duration_minutes' => $sessionDuration,
                    'questions_json' => $questions,
                ];
            }

            $firstCategoryId = (int) $sessionPayloads[0]['question_category_id'];

            if ($existing) {
                SalaryAdjustmentAssessmentSession::where('assessment_id', $existing->id)->delete();
                $assessment = $existing;
                $assessment->fill([
                    'token' => $token,
                    'question_category_id' => $firstCategoryId,
                    'duration_minutes' => $totalDuration,
                    'has_time_limit' => $hasAnyTimeLimit,
                    'link_url' => $linkUrl,
                    'is_link_active' => true,
                    'link_generated_by' => $generatedBy,
                    'link_generated_at' => Carbon::now(),
                    'link_deactivated_at' => null,
                    'attempt_status' => 'pending',
                    'started_at' => null,
                    'completed_at' => null,
                    'total_score' => null,
                    'result_json' => null,
                ]);
                $assessment->save();
            } else {
                $assessment = SalaryAdjustmentAssessment::create([
                    'request_id' => $request->id,
                    'token' => $token,
                    'question_category_id' => $firstCategoryId,
                    'duration_minutes' => $totalDuration,
                    'has_time_limit' => $hasAnyTimeLimit,
                    'link_url' => $linkUrl,
                    'is_link_active' => true,
                    'link_generated_by' => $generatedBy,
                    'link_generated_at' => Carbon::now(),
                    'attempt_status' => 'pending',
                ]);
            }

            foreach ($sessionPayloads as $index => $sessionData) {
                SalaryAdjustmentAssessmentSession::create([
                    'assessment_id' => $assessment->id,
                    'session_order' => $index + 1,
                    'question_category_id' => $sessionData['question_category_id'],
                    'category_name' => $sessionData['category_name'],
                    'question_count' => $sessionData['question_count'],
                    'duration_minutes' => $sessionData['duration_minutes'],
                    'questions_json' => $sessionData['questions_json'],
                    'answers_json' => [],
                    'status' => 'pending',
                ]);
            }

            $fromStatus = $request->status;
            $request->update([
                'status' => SalaryAdjustmentWorkflowService::STATUS_WAITING_ASSESSMENT,
                'updated_by' => $generatedBy,
            ]);

            SalaryAdjustmentLogService::log(
                $request->id,
                $fromStatus,
                SalaryAdjustmentWorkflowService::STATUS_WAITING_ASSESSMENT,
                'generate_assessment',
                null,
                $generatedBy,
                'Link assessment digenerate',
                [
                    'link_url' => $linkUrl,
                    'modules' => array_column($sessionPayloads, 'category_name'),
                ]
            );

            return [
                'token' => $token,
                'link_url' => $linkUrl,
                'assessment_id' => $assessment->id,
                'module_count' => count($sessionPayloads),
            ];
        });
    }

    /**
     * @return array{next_status: string, requires_counseling: bool}
     */
    public function skipAssessment(
        SalaryAdjustmentRequest $record,
        string $target,
        string $actorName,
        ?int $actorId
    ): array {
        if (!in_array($record->status, EmployeeAdjustmentWorkflowResolver::assessmentSkippableStatuses(), true)) {
            throw new \RuntimeException('Permohonan tidak dapat dilewati assessment pada status ini');
        }

        $resolver = new EmployeeAdjustmentWorkflowResolver();
        $nextStatus = $resolver->resolveSkipAssessmentTarget($record, $target);
        $requiresCounseling = $resolver->requiresCounseling($record->request_type);

        return DB::connection('mysql')->transaction(function () use (
            $record,
            $target,
            $nextStatus,
            $requiresCounseling,
            $actorName,
            $actorId
        ) {
            $this->deactivateActiveAssessmentForSkip($record);

            $from = $record->status;
            $record->status = $nextStatus;
            $record->updated_by = $actorName;
            $record->save();

            $notes = $target === 'final'
                ? 'HRD melewati assessment & konseling'
                : ($nextStatus === SalaryAdjustmentWorkflowService::STATUS_FINAL_EVALUATION
                    ? 'HRD melewati assessment — lanjut ke Evaluasi Final'
                    : 'HRD melewati assessment — lanjut ke penjadwalan konseling');

            SalaryAdjustmentLogService::log(
                $record->id,
                $from,
                $nextStatus,
                'skip_assessment',
                $actorId,
                $actorName,
                $notes,
                [
                    'target' => $target,
                    'requires_counseling' => $requiresCounseling,
                ]
            );

            return [
                'next_status' => $nextStatus,
                'requires_counseling' => $requiresCounseling,
            ];
        });
    }

    public function deactivateActiveAssessmentForSkip(SalaryAdjustmentRequest $record): void
    {
        $assessment = SalaryAdjustmentAssessment::where('request_id', $record->id)->first();
        if (!$assessment) {
            return;
        }

        if ($assessment->attempt_status === 'completed') {
            throw new \RuntimeException('Assessment sudah selesai dikerjakan');
        }

        if (!$assessment->is_link_active) {
            return;
        }

        $assessment->is_link_active = false;
        $assessment->link_deactivated_at = Carbon::now();
        $assessment->save();
    }

    public function findByToken(string $token): ?SalaryAdjustmentAssessment
    {
        $token = trim(urldecode($token));

        return SalaryAdjustmentAssessment::with(['sessions', 'request'])
            ->where('token', $token)
            ->first();
    }

    public function getCategoriesForSelect(): array
    {
        return QuestionCategory::withCount([
            'questions as current_question_count' => function ($query) {
                $query->where('question_scope', 'hr')
                    ->where('is_active', 1)
                    ->where('status', '!=', 'retired');
            },
        ])
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('category_scope', 'hr')->orWhereNull('category_scope');
            })
            ->orderByRaw("CASE WHEN UPPER(name) = 'DISC' THEN 1 WHEN UPPER(name) IN ('KOSTICK PAPI', 'PAPI KOSTICK') THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->get()
            ->all();
    }

    private function normalizeCategoryQuestion(array $payload): array
    {
        if (!empty($payload['category_question']) && is_array($payload['category_question'])) {
            $normalized = [];

            foreach ($payload['category_question'] as $item) {
                if (!is_array($item) || empty($item['id'])) {
                    continue;
                }

                $category = QuestionCategory::find($item['id']);
                if (!$category) {
                    continue;
                }

                $isMandatory = $this->isMandatoryCategory($category->name);
                $hasTimeLimit = filter_var($item['has_time_limit'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $normalized[] = [
                    'id' => (int) $item['id'],
                    'question_count' => $isMandatory
                        ? (int) ($item['question_count'] ?? 0)
                        : $this->clampOptionalQuestionCount($item['question_count'] ?? 1),
                    'duration_minutes' => $hasTimeLimit
                        ? max(1, (int) ($item['duration_minutes'] ?? 15))
                        : 0,
                    'has_time_limit' => $hasTimeLimit,
                ];
            }

            return $normalized;
        }

        $categoryId = (int) ($payload['question_category_id'] ?? 0);
        if (!$categoryId) {
            return [];
        }

        $hasTimeLimit = filter_var($payload['has_time_limit'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $durationMinutes = max(0, (int) ($payload['duration_minutes'] ?? 0));

        if ($hasTimeLimit && $durationMinutes <= 0) {
            throw new \RuntimeException('Durasi waktu wajib diisi jika limit waktu aktif.');
        }

        return [[
            'id' => $categoryId,
            'question_count' => $this->clampOptionalQuestionCount($payload['question_count'] ?? 30),
            'duration_minutes' => $hasTimeLimit ? $durationMinutes : 0,
            'has_time_limit' => $hasTimeLimit,
        ]];
    }

    private function isMandatoryCategory(?string $name): bool
    {
        $normalized = strtoupper(trim((string) $name));

        return in_array($normalized, ['DISC', 'KOSTICK PAPI', 'PAPI KOSTICK'], true);
    }

    private function clampOptionalQuestionCount($value): int
    {
        $count = (int) $value;

        if ($count < self::MIN_OPTIONAL_QUESTION_COUNT) {
            return self::MIN_OPTIONAL_QUESTION_COUNT;
        }

        if ($count > self::MAX_OPTIONAL_QUESTION_COUNT) {
            return self::MAX_OPTIONAL_QUESTION_COUNT;
        }

        return $count;
    }

    /**
     * Urutan modul mengikuti recruitment: DISC → PAPI → kategori opsional di-shuffle.
     */
    private function orderCategoryConfigs(array $categoryConfigs): array
    {
        if (count($categoryConfigs) <= 1) {
            return $categoryConfigs;
        }

        $mandatory = [];
        $optional = [];

        foreach ($categoryConfigs as $config) {
            $category = QuestionCategory::find($config['id']);
            $name = strtoupper(trim((string) ($category->name ?? '')));

            if ($this->isMandatoryCategory($name)) {
                $mandatory[] = $config;
                continue;
            }

            $optional[] = $config;
        }

        usort($mandatory, function ($left, $right) {
            $leftCategory = QuestionCategory::find($left['id']);
            $rightCategory = QuestionCategory::find($right['id']);
            $leftName = strtoupper(trim((string) ($leftCategory->name ?? '')));
            $rightName = strtoupper(trim((string) ($rightCategory->name ?? '')));

            if ($leftName === 'DISC') {
                return -1;
            }

            if ($rightName === 'DISC') {
                return 1;
            }

            return 0;
        });

        shuffle($optional);

        return array_merge($mandatory, $optional);
    }
}
