<?php

namespace App\Services;

use App\Models\SalaryAdjustmentKpi;
use App\Models\SalaryAdjustmentKpiItem;
use Illuminate\Support\Facades\DB;

class SalaryAdjustmentKpiService
{
    public function getActiveCriteria()
    {
        return DB::connection('mysql')
            ->table('salary_adjustment_kpi_criteria')
            ->where('is_active', true)
            ->orderBy('criteria_no')
            ->get();
    }

    public function validatePayload(array $items, ?string $summary, ?string $strengths, ?string $improvements): ?string
    {
        if (empty(trim((string) $summary))) {
            return 'Ringkasan penilaian wajib diisi';
        }
        if (empty(trim((string) $strengths))) {
            return 'Kekuatan wajib diisi';
        }
        if (empty(trim((string) $improvements))) {
            return 'Area perbaikan wajib diisi';
        }

        if (empty($items)) {
            return 'Minimal 1 kriteria KPI wajib diisi';
        }

        $totalWeight = 0;

        foreach ($items as $index => $item) {
            $rowNo = $index + 1;

            if (empty(trim((string) ($item['criteria_name'] ?? '')))) {
                return 'Kriteria KPI baris ' . $rowNo . ' wajib diisi';
            }

            if (empty(trim((string) ($item['indicator_text'] ?? '')))) {
                return 'Indikator penilaian baris ' . $rowNo . ' wajib diisi';
            }

            $weight = (float) ($item['weight_pct'] ?? 0);
            if ($weight <= 0 || $weight > 100) {
                return 'Bobot baris ' . $rowNo . ' harus antara 0 dan 100';
            }

            $totalWeight += $weight;

            $score = (int) round((float) ($item['score'] ?? 0));
            if ($score < 1 || $score > 5) {
                return 'Skor baris ' . $rowNo . ' harus bilangan bulat 1 sampai 5';
            }
        }

        if (abs(round($totalWeight, 2) - 100) >= 0.01) {
            return 'Total bobot KPI harus 100% (saat ini ' . number_format($totalWeight, 2) . '%)';
        }

        return null;
    }

    public function buildCalculation(array $items): array
    {
        $computedItems = [];
        $scores = [];

        foreach ($items as $index => $item) {
            $score = round((float) ($item['score'] ?? 0), 1);
            $weight = (float) ($item['weight_pct'] ?? 0);
            $finalScore = SalaryAdjustmentWorkflowService::calculateRowFinalScore($weight, $score);
            $scores[] = $score;

            $computedItems[] = [
                'criteria_no' => (int) ($item['criteria_no'] ?? ($index + 1)),
                'criteria_name' => trim((string) ($item['criteria_name'] ?? '')),
                'weight_pct' => $weight,
                'indicator_text' => trim((string) ($item['indicator_text'] ?? '')),
                'score' => $score,
                'final_score' => $finalScore,
            ];
        }

        $totalScoreAvg = count($scores) ? round(array_sum($scores) / count($scores), 2) : 0;
        $totalFinalScore = round(array_sum(array_column($computedItems, 'final_score')), 2);

        return [
            'items' => $computedItems,
            'total_score_avg' => $totalScoreAvg,
            'total_final_score' => $totalFinalScore,
            'interpretation' => SalaryAdjustmentWorkflowService::interpretScore($totalScoreAvg),
        ];
    }

    public function store(int $requestId, array $payload, string $createdBy): SalaryAdjustmentKpi
    {
        $calculation = $this->buildCalculation($payload['items'] ?? []);

        $kpi = SalaryAdjustmentKpi::create([
            'request_id' => $requestId,
            'total_score_avg' => $calculation['total_score_avg'],
            'total_final_score' => $calculation['total_final_score'],
            'interpretation' => $calculation['interpretation'],
            'summary' => trim((string) ($payload['summary'] ?? '')),
            'strengths' => trim((string) ($payload['strengths'] ?? '')),
            'improvements' => trim((string) ($payload['improvements'] ?? '')),
            'created_by' => $createdBy,
        ]);

        foreach ($calculation['items'] as $item) {
            SalaryAdjustmentKpiItem::create([
                'kpi_id' => $kpi->id,
                'criteria_no' => $item['criteria_no'],
                'criteria_name' => $item['criteria_name'],
                'weight_pct' => $item['weight_pct'],
                'indicator_text' => $item['indicator_text'],
                'score' => $item['score'],
                'final_score' => $item['final_score'],
            ]);
        }

        return $kpi;
    }
}
