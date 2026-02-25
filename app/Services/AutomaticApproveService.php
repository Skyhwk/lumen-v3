<?php
namespace App\Services;

use App\Helpers\HelperAutomatic;
use App\Models\AutomaticApprove;
use App\Models\Colorimetri;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AutomaticApproveService
{
    private const APPROVED_BY = 'SYSTEM';

    public function run(): void
    {
        Log::channel('analyst_approve')->info('[AutomaticApprove] Mulai proses auto-approve');

        $rules = Cache::remember('automatic_approve_rules', Carbon::now()->addMinutes(5), function () {
            return AutomaticApprove::all();
        });
        if ($rules->isEmpty()) {
            Log::channel('analyst_approve')->info('[AutomaticApprove] Tidak ada template aktif');
        } else {
            foreach ($rules as $rule) {
                $this->processRule($rule);
            }
        }

        Log::channel('analyst_approve')->info('[AutomaticApprove] Selesai proses auto-approve');
    }

    private function processRule(AutomaticApprove $rule): void
    {
        $models = HelperAutomatic::getModelsByKategori($rule->id_kategori);

        if (empty($models)) {
            Log::channel('analyst_approve')->warning("[AutomaticApprove] Kategori {$rule->id_kategori} tidak ditemukan");
            return;
        }

        $threshold = $rule->interval === 0
            ? Carbon::now()
            : Carbon::now()->subHours($rule->interval);

        foreach ($models as $type => $config) {
            $this->approveRecords($type, $config, $rule, $threshold);
        }
    }

    private function approveRecords(string $type, array $config, AutomaticApprove $rule, Carbon $threshold): void
    {
        $modelClass    = $config['model'];
        $approvedField = $config['approved_field'];
        $extraWhere    = $config['extra_where'] ?? [];

        try {
            $query = $modelClass::query()
                ->where('created_at', '<=', $threshold)
                ->where('is_active', true)
                ->where($approvedField, false);

            foreach ($extraWhere as $column => $value) {
                $query->where($column, $value);
            }

            if (! is_null($rule->id_template)) {
                $query->where('template_stp', $rule->id_template);
            }

            $ids = $query->pluck('id')->toArray();

            if (empty($ids)) {
                Log::channel('analyst_approve')->info("[AutomaticApprove] Template#{$rule->nama_template} | {$type}: tidak ada record");
                return;
            }

            $modelClass::whereIn('id', $ids)->update([
                $approvedField => true,
                'approved_at'  => Carbon::now(),
                'approved_by'  => self::APPROVED_BY,
            ]);

            Log::channel('analyst_approve')->info(
                "[AutomaticApprove] Template#{$rule->nama_template} | {$type}: approved IDs [" . implode(',', $ids) . "]"
            );

        } catch (\Throwable $th) {
            Log::channel('analyst_approve')->error(
                "[AutomaticApprove] Rule#{$rule->id} | {$type} Error: {$th->getMessage()} " .
                "Line:{$th->getLine()} File:{$th->getFile()}"
            );
            throw $th;
        }
    }

    // ─────────────────────────────────────────────
    // DIRECT APPROVE: berdasarkan nama parameter
    // ─────────────────────────────────────────────

    private function processDirectLain(): void
    {
        $parameters = HelperAutomatic::getParameterDirect();

        foreach ($parameters as $idKategori => $paramList) {
            $this->approveDirectLain($idKategori, $paramList);
        }
    }

    private function approveDirectLain(int $idKategori, array $paramList): void
    {
        try {
            $ids = Colorimetri::query()
                ->where('is_active', true)
                ->where('is_approved', false)
                ->where('is_total', false)
                ->whereIn('parameter', $paramList)
                ->pluck('id')
                ->toArray();

            if (empty($ids)) {
                Log::channel('analyst_approve')->info("[AutomaticApprove][Direct] Kategori#{$idKategori}: tidak ada record");
                return;
            }

            Colorimetri::whereIn('id', $ids)->update([
                'is_approved' => true,
                'approved_at' => Carbon::now(),
                'approved_by' => self::APPROVED_BY,
            ]);

            Log::channel('analyst_approve')->info(
                "[AutomaticApprove][Direct] Kategori#{$idKategori}: approved IDs [" . implode(',', $ids) . "]"
            );

        } catch (\Throwable $th) {
            Log::channel('analyst_approve')->error(
                "[AutomaticApprove][Direct] Kategori#{$idKategori} Error: {$th->getMessage()} " .
                "Line:{$th->getLine()} File:{$th->getFile()}"
            );
        }
    }
}
