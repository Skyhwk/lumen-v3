<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HrdAssessmentReadinessService
{
    public function check(): array
    {
        if (!Schema::hasTable('question_categories')) {
            return $this->notReady('Konfigurasi assessment HRD belum tersedia.');
        }

        $hasIsShow = Schema::hasColumn('question_categories', 'is_show');
        $categories = DB::table('question_categories')
            ->where('is_active', 1)
            ->where(function ($query) {
                $query->where('category_scope', 'hr')->orWhereNull('category_scope');
            })
            ->whereRaw('UPPER(name) != ?', ['INFORMASI PENDUKUNG'])
            ->orderBy('name')
            ->get();

        $activeCategories = $categories->filter(function ($category) use ($hasIsShow) {
            return $this->isCategoryActive($category, $hasIsShow);
        });

        if ($activeCategories->isEmpty()) {
            return $this->notReady(
                'Modul assessment HRD belum diaktifkan. Silakan atur konfigurasi psikotes terlebih dahulu.'
            );
        }

        $issues = [];
        foreach ($activeCategories as $category) {
            $issue = $this->validateCategory($category);
            if ($issue !== null) {
                $issues[] = $issue;
            }
        }

        if (!empty($issues)) {
            return $this->notReady(
                'Publish tidak dapat dilakukan karena Bank Soal HRD belum lengkap.',
                $issues
            );
        }

        return [
            'ready' => true,
            'message' => 'Bank Soal HRD siap digunakan.',
            'issues' => [],
        ];
    }

    private function validateCategory($category): ?string
    {
        $name = strtoupper(trim((string) ($category->name ?? '')));

        if ($name === 'DISC') {
            $available = DB::table('soal_psikotes')->where('kategori_soal', 'DISC')->count();
            if ($available < 1) {
                return 'Modul DISC belum memiliki soal.';
            }

            return null;
        }

        if (in_array($name, ['KOSTICK PAPI', 'PAPI KOSTICK'], true)) {
            $available = DB::table('soal_psikotes')
                ->whereIn('kategori_soal', ['KOSTICK PAPI', 'PAPI KOSTICK'])
                ->count();
            if ($available < 1) {
                return 'Modul PAPI Kostick belum memiliki soal.';
            }

            return null;
        }

        $requiredCount = (int) ($category->question_count ?? 0);
        $available = DB::table('questions')
            ->where('question_category_id', $category->id)
            ->where('question_scope', 'hr')
            ->where('status', '!=', 'retired')
            ->where('is_active', 1)
            ->count();

        if ($requiredCount < 1) {
            return "Modul {$category->name} belum dikonfigurasi (jumlah soal = 0).";
        }

        if ($available < 1) {
            return "Modul {$category->name} belum memiliki soal aktif di Bank Soal HRD.";
        }

        if ($available < $requiredCount) {
            return "Modul {$category->name} hanya memiliki {$available} soal, sedangkan target konfigurasi {$requiredCount} soal.";
        }

        return null;
    }

    private function isCategoryActive($category, bool $hasIsShow): bool
    {
        $name = strtoupper(trim((string) ($category->name ?? '')));
        if (in_array($name, ['DISC', 'KOSTICK PAPI', 'PAPI KOSTICK'], true)) {
            return true;
        }

        if ($hasIsShow) {
            return (int) ($category->is_show ?? 0) === 1;
        }

        return (int) ($category->is_active ?? 0) === 1;
    }

    private function notReady(string $message, array $issues = []): array
    {
        return [
            'ready' => false,
            'message' => $message,
            'issues' => $issues,
        ];
    }
}
