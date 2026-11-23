<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WaMessageTemplateService
{
    /**
     * Render one active DB variant. Returning null tells the caller to use its
     * legacy hardcoded message while templates have not been deployed yet.
     */
    public static function render(string $code, array $variables): ?string
    {
        try {
            if (!Schema::hasTable('wa_message_templates') || !Schema::hasTable('wa_message_template_variants')) {
                return null;
            }

            $template = DB::table('wa_message_templates')
                ->where('code', $code)
                ->where('is_active', 1)
                ->first();

            if (!$template) {
                return null;
            }

            $variants = DB::table('wa_message_template_variants')
                ->where('template_id', $template->id)
                ->where('is_active', 1)
                ->get(['id', 'body', 'weight']);

            if ($variants->isEmpty()) {
                return null;
            }

            $variant = self::pickWeightedVariant($variants->all());
            if (!$variant || trim((string) $variant->body) === '') {
                return null;
            }

            $isSalaryOffering = strcasecmp($template->code, 'salary_offering_letter') === 0;
            if ($isSalaryOffering) {
                $variables = array_intersect_key($variables, array_flip(['sapaan', 'saudara', 'nama', 'posisi', 'email']));
            }

            $replace = [];
            foreach ($variables as $key => $value) {
                $replace['{{' . $key . '}}'] = (string) $value;
            }

            $message = strtr($variant->body, $replace);

            // Use the notification in code when a persisted variant still expects salary fields.
            if ($isSalaryOffering && strpos($message, '{{') !== false) {
                return null;
            }

            return $message;
        } catch (\Throwable $e) {
            Log::warning('WA database template unavailable; using legacy message.', [
                'template_code' => $code,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function pickWeightedVariant(array $variants)
    {
        $totalWeight = array_sum(array_map(function ($variant) {
            return max(1, (int) ($variant->weight ?? 1));
        }, $variants));

        $pick = random_int(1, $totalWeight);
        foreach ($variants as $variant) {
            $pick -= max(1, (int) ($variant->weight ?? 1));
            if ($pick <= 0) {
                return $variant;
            }
        }

        return $variants[0] ?? null;
    }
}
