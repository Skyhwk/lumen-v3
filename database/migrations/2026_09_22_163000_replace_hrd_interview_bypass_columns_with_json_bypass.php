<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReplaceHrdInterviewBypassColumnsWithJsonBypass extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('new_recruitment')) {
            return;
        }

        if (!Schema::hasColumn('new_recruitment', 'bypass')) {
            Schema::table('new_recruitment', function (Blueprint $table) {
                $table->json('bypass')->nullable()->after('approved_interview_hrd_at');
            });
        }

        $hasLegacyReason = Schema::hasColumn('new_recruitment', 'alasan_bypass');
        $hasLegacyBy = Schema::hasColumn('new_recruitment', 'bypass_by');
        $hasLegacyAt = Schema::hasColumn('new_recruitment', 'bypass_at');

        if ($hasLegacyReason || $hasLegacyBy || $hasLegacyAt) {
            DB::table('new_recruitment')
                ->select(array_filter([
                    'id',
                    'bypass',
                    $hasLegacyReason ? 'alasan_bypass' : null,
                    $hasLegacyBy ? 'bypass_by' : null,
                    $hasLegacyAt ? 'bypass_at' : null,
                ]))
                ->orderBy('id')
                ->chunkById(100, function ($recruitments) use ($hasLegacyReason, $hasLegacyBy, $hasLegacyAt) {
                    foreach ($recruitments as $recruitment) {
                        $description = $hasLegacyReason ? $recruitment->alasan_bypass : null;
                        $by = $hasLegacyBy ? $recruitment->bypass_by : null;
                        $at = $hasLegacyAt ? $recruitment->bypass_at : null;

                        if ($description === null && $by === null && $at === null) {
                            continue;
                        }

                        $bypass = json_decode($recruitment->bypass ?? '{}', true) ?: [];
                        $bypass['hrd_interview'] = [
                            'description' => $description,
                            'by' => $by,
                            'at' => $at,
                        ];

                        DB::table('new_recruitment')
                            ->where('id', $recruitment->id)
                            ->update(['bypass' => json_encode($bypass)]);
                    }
                });

            $legacyColumns = array_filter([
                $hasLegacyAt ? 'bypass_at' : null,
                $hasLegacyBy ? 'bypass_by' : null,
                $hasLegacyReason ? 'alasan_bypass' : null,
            ]);

            Schema::table('new_recruitment', function (Blueprint $table) use ($legacyColumns) {
                $table->dropColumn($legacyColumns);
            });
        }
    }

    public function down()
    {
        if (!Schema::hasTable('new_recruitment')) {
            return;
        }

        if (!Schema::hasColumn('new_recruitment', 'bypass')) {
            return;
        }

        Schema::table('new_recruitment', function (Blueprint $table) {
            if (!Schema::hasColumn('new_recruitment', 'alasan_bypass')) {
                $table->text('alasan_bypass')->nullable()->after('approved_interview_hrd_at');
            }
            if (!Schema::hasColumn('new_recruitment', 'bypass_by')) {
                $table->string('bypass_by', 255)->nullable()->after('alasan_bypass');
            }
            if (!Schema::hasColumn('new_recruitment', 'bypass_at')) {
                $table->dateTime('bypass_at')->nullable()->after('bypass_by');
            }
        });

        DB::table('new_recruitment')
            ->select(['id', 'bypass'])
            ->whereNotNull('bypass')
            ->orderBy('id')
            ->chunkById(100, function ($recruitments) {
                foreach ($recruitments as $recruitment) {
                    $hrdBypass = (json_decode($recruitment->bypass, true) ?: [])['hrd_interview'] ?? null;
                    if (!$hrdBypass) {
                        continue;
                    }

                    DB::table('new_recruitment')
                        ->where('id', $recruitment->id)
                        ->update([
                            'alasan_bypass' => $hrdBypass['description'] ?? null,
                            'bypass_by' => $hrdBypass['by'] ?? null,
                            'bypass_at' => $hrdBypass['at'] ?? null,
                        ]);
                }
            });

        Schema::table('new_recruitment', function (Blueprint $table) {
            $table->dropColumn('bypass');
        });
    }
}
