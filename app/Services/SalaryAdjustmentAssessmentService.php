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

    private const PF16_QUESTION_COUNT = 105;

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
        if ($existing && !empty($existing->token)) {
            throw new \RuntimeException(
                'Link assessment sudah ada. Take down lalu Re-open jika ingin memperbarui modul/soal.'
            );
        }

        return DB::connection('mysql')->transaction(function () use (
            $request,
            $categoryConfigs,
            $generatedBy,
            $payload
        ) {
            $token = bin2hex(random_bytes(32));
            $frontendOrigin = trim((string) ($payload['frontend_origin'] ?? ''));
            $linkUrl = FrontendPublicUrl::buildAssessmentLink(
                $token,
                $frontendOrigin !== '' ? $frontendOrigin : null
            );

            $built = $this->buildSessionPayloadsFromConfigs($categoryConfigs);
            $sessionPayloads = $built['sessions'];
            $totalDuration = $built['total_duration'];
            $hasAnyTimeLimit = $built['has_any_time_limit'];
            $firstCategoryId = (int) $sessionPayloads[0]['question_category_id'];

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

    public function takeDownLink(SalaryAdjustmentRequest $request, string $actor): void
    {
        $this->assertAssessmentLinkManageable($request);

        $assessment = SalaryAdjustmentAssessment::where('request_id', $request->id)->first();
        if (!$assessment || empty($assessment->token)) {
            throw new \RuntimeException('Link assessment belum digenerate.');
        }

        if ($assessment->attempt_status === 'completed') {
            throw new \RuntimeException('Assessment sudah selesai dikerjakan.');
        }

        if (!$assessment->is_link_active) {
            throw new \RuntimeException('Link assessment sudah nonaktif.');
        }

        $assessment->is_link_active = false;
        $assessment->link_deactivated_at = Carbon::now();
        $assessment->save();

        SalaryAdjustmentLogService::log(
            $request->id,
            $request->status,
            $request->status,
            'takedown_assessment_link',
            null,
            $actor,
            'Link assessment di-take down',
            ['link_url' => $assessment->link_url]
        );
    }

    public function getReopenPreset(SalaryAdjustmentRequest $request): array
    {
        $this->assertAssessmentLinkManageable($request);

        $assessment = SalaryAdjustmentAssessment::where('request_id', $request->id)->first();
        if (!$assessment || empty($assessment->token)) {
            throw new \RuntimeException('Link assessment belum digenerate.');
        }

        if ($assessment->attempt_status === 'completed') {
            throw new \RuntimeException('Assessment sudah selesai dikerjakan.');
        }

        $sessions = SalaryAdjustmentAssessmentSession::where('assessment_id', $assessment->id)
            ->orderBy('session_order')
            ->get();

        $categoryQuestion = $sessions->map(function ($session) {
            $duration = (int) $session->duration_minutes;

            return [
                'id' => (int) $session->question_category_id,
                'question_count' => (int) $session->question_count,
                'duration_minutes' => $duration > 0 ? $duration : 15,
                'has_time_limit' => $duration > 0,
            ];
        })->values()->all();

        $linkUrl = FrontendPublicUrl::buildAssessmentLink($assessment->token) ?: $assessment->link_url;

        return [
            'link_url' => $linkUrl,
            'category_question' => $categoryQuestion,
        ];
    }

    public function reopenLink(SalaryAdjustmentRequest $request, array $payload, string $actor): array
    {
        $this->assertAssessmentLinkManageable($request);

        $categoryConfigs = $this->orderCategoryConfigs($this->normalizeCategoryQuestion($payload));
        if (empty($categoryConfigs)) {
            throw new \RuntimeException('Minimal pilih 1 modul assessment.');
        }

        $assessment = SalaryAdjustmentAssessment::where('request_id', $request->id)->first();
        if (!$assessment || empty($assessment->token)) {
            throw new \RuntimeException('Link assessment belum digenerate.');
        }

        if ($assessment->attempt_status === 'completed') {
            throw new \RuntimeException('Assessment sudah selesai dikerjakan.');
        }

        if ($assessment->is_link_active) {
            throw new \RuntimeException('Take down link terlebih dahulu sebelum Re-open.');
        }

        return DB::connection('mysql')->transaction(function () use (
            $request,
            $assessment,
            $categoryConfigs,
            $payload,
            $actor
        ) {
            $token = $assessment->token;
            $frontendOrigin = trim((string) ($payload['frontend_origin'] ?? ''));
            $linkUrl = $frontendOrigin !== ''
                ? FrontendPublicUrl::buildAssessmentLink($token, $frontendOrigin)
                : ($assessment->link_url ?: FrontendPublicUrl::buildAssessmentLink($token));

            $built = $this->buildSessionPayloadsFromConfigs($categoryConfigs);
            $sessionPayloads = $built['sessions'];
            $totalDuration = $built['total_duration'];
            $hasAnyTimeLimit = $built['has_any_time_limit'];
            $firstCategoryId = (int) $sessionPayloads[0]['question_category_id'];

            SalaryAdjustmentAssessmentSession::where('assessment_id', $assessment->id)->delete();

            $assessment->fill([
                'question_category_id' => $firstCategoryId,
                'duration_minutes' => $totalDuration,
                'has_time_limit' => $hasAnyTimeLimit,
                'link_url' => $linkUrl,
                'is_link_active' => true,
                'link_deactivated_at' => null,
                'attempt_status' => 'pending',
                'started_at' => null,
                'completed_at' => null,
                'total_score' => null,
                'result_json' => null,
            ]);
            $assessment->save();

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
                'updated_by' => $actor,
            ]);

            SalaryAdjustmentLogService::log(
                $request->id,
                $fromStatus,
                SalaryAdjustmentWorkflowService::STATUS_WAITING_ASSESSMENT,
                'reopen_assessment_link',
                null,
                $actor,
                'Link assessment di-reopen (modul/soal diperbarui, URL sama)',
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
            ->orderByRaw("CASE WHEN UPPER(name) = 'DISC' THEN 1 WHEN UPPER(name) IN ('KOSTICK PAPI', 'PAPI KOSTICK') THEN 2 WHEN UPPER(name) = '16PF' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->get()
            ->all();
    }

    private function assertAssessmentLinkManageable(SalaryAdjustmentRequest $request): void
    {
        $allowedStatuses = [
            SalaryAdjustmentWorkflowService::STATUS_HRD_PROCESSING,
            SalaryAdjustmentWorkflowService::STATUS_WAITING_ASSESSMENT,
        ];

        if (!in_array($request->status, $allowedStatuses, true)) {
            throw new \RuntimeException('Permohonan tidak dapat mengelola link assessment pada status ini.');
        }

        if (!(new EmployeeAdjustmentWorkflowResolver())->requiresAssessment($request->request_type)) {
            throw new \RuntimeException('Jenis permohonan ini tidak memerlukan assessment.');
        }
    }

    /**
     * @return array{sessions: array<int, array<string, mixed>>, total_duration: int, has_any_time_limit: bool}
     */
    private function buildSessionPayloadsFromConfigs(array $categoryConfigs): array
    {
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

        return [
            'sessions' => $sessionPayloads,
            'total_duration' => $totalDuration,
            'has_any_time_limit' => $hasAnyTimeLimit,
        ];
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
                $isPf16 = $this->isPf16Category($category->name);
                $hasTimeLimit = filter_var($item['has_time_limit'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $normalized[] = [
                    'id' => (int) $item['id'],
                    'question_count' => $isPf16
                        ? self::PF16_QUESTION_COUNT
                        : ($isMandatory
                        ? (int) ($item['question_count'] ?? 0)
                        : $this->clampOptionalQuestionCount($item['question_count'] ?? 1, $category->name)),
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

    private function isPf16Category(?string $name): bool
    {
        return strtoupper(trim((string) $name)) === '16PF';
    }

    private function clampOptionalQuestionCount($value, ?string $categoryName = null): int
    {
        if ($this->isPf16Category($categoryName)) {
            return self::PF16_QUESTION_COUNT;
        }

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
