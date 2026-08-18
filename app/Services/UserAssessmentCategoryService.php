<?php

namespace App\Services;

use App\Models\QuestionCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class UserAssessmentCategoryService
{
    public function findOwnerCategory(string $ownerKaryawan): ?QuestionCategory
    {
        if ($ownerKaryawan === '') {
            return null;
        }

        return QuestionCategory::query()
            ->where('category_scope', 'manager')
            ->where('owner_karyawan', $ownerKaryawan)
            ->whereNull('assigned_manager')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    public function findOrCreateOwnerCategory(string $ownerKaryawan, ?string $createdBy = null): QuestionCategory
    {
        $existing = $this->findOwnerCategory($ownerKaryawan);
        if ($existing) {
            return $existing;
        }

        $payload = [
            'name' => 'Assessment User',
            'category_scope' => 'manager',
            'owner_karyawan' => $ownerKaryawan,
            'assigned_manager' => null,
            'question_count' => 10,
            'is_active' => true,
            'created_by' => $createdBy ?: $ownerKaryawan,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        if (Schema::hasColumn('question_categories', 'has_time_limit')) {
            $payload['has_time_limit'] = false;
        }

        if (Schema::hasColumn('question_categories', 'duration_minutes')) {
            $payload['duration_minutes'] = 0;
        }

        return QuestionCategory::create($payload);
    }

    public function syncConfig(
        string $ownerKaryawan,
        int $questionCount,
        bool $hasTimeLimit,
        ?int $durationMinutes,
        ?string $createdBy = null
    ): QuestionCategory {
        $category = $this->findOrCreateOwnerCategory($ownerKaryawan, $createdBy);

        $updateData = [
            'question_count' => $questionCount,
            'updated_at' => Carbon::now(),
        ];

        if (Schema::hasColumn('question_categories', 'has_time_limit')) {
            $updateData['has_time_limit'] = $hasTimeLimit;
        }

        if (Schema::hasColumn('question_categories', 'duration_minutes')) {
            $updateData['duration_minutes'] = $hasTimeLimit ? (int) ($durationMinutes ?? 0) : 0;
        }

        $category->update($updateData);

        return $category->fresh();
    }

    public function toConfigObject(?QuestionCategory $category): ?object
    {
        if (!$category) {
            return null;
        }

        $hasTimeLimit = $this->categoryHasTimeLimit($category);
        $durationMinutes = $hasTimeLimit ? (int) ($category->duration_minutes ?? 0) : 0;

        return (object) [
            'owner_karyawan' => $category->owner_karyawan,
            'question_count' => (int) ($category->question_count ?? 0),
            'duration_minutes' => $durationMinutes,
            'has_time_limit' => $hasTimeLimit,
        ];
    }

    public function categoryHasTimeLimit($category): bool
    {
        if ($category && isset($category->has_time_limit)) {
            return (bool) $category->has_time_limit;
        }

        return (int) ($category->duration_minutes ?? 0) > 0;
    }

    public function appendLegacyConfigFields(object $personnelRequest): object
    {
        $category = $this->findOwnerCategory((string) ($personnelRequest->created_by ?? ''));
        $config = $this->toConfigObject($category);

        if ($config) {
            $personnelRequest->user_assessment_question_count = $config->question_count;
            $personnelRequest->user_assessment_has_time_limit = $config->has_time_limit ? 1 : 0;
            $personnelRequest->user_assessment_duration_minutes = $config->has_time_limit
                ? $config->duration_minutes
                : null;

            return $personnelRequest;
        }

        if (!isset($personnelRequest->user_assessment_question_count)) {
            $personnelRequest->user_assessment_question_count = null;
            $personnelRequest->user_assessment_has_time_limit = 0;
            $personnelRequest->user_assessment_duration_minutes = null;
        }

        return $personnelRequest;
    }
}
