<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRejectedKandidatTrackingColumnsToNewRecruitmentTable extends Migration
{
    public function up()
    {
        Schema::table('new_recruitment', function (Blueprint $table) {
            if (!Schema::hasColumn('new_recruitment', 'is_rejected_kandidat_by')) {
                $table->string('is_rejected_kandidat_by', 255)->nullable()->after('is_rejected_kandidat');
            }

            if (!Schema::hasColumn('new_recruitment', 'is_rejected_kandidat_at')) {
                $table->timestamp('is_rejected_kandidat_at')->nullable()->after('is_rejected_kandidat_by');
            }

            if (!Schema::hasColumn('new_recruitment', 'is_rejected_kandidat_reason')) {
                $table->text('is_rejected_kandidat_reason')->nullable()->after('is_rejected_kandidat_at');
            }
        });

        if (!Schema::hasColumn('new_recruitment', 'is_rejected_kandidat_by')) {
            return;
        }

        DB::table('new_recruitment')
            ->where('is_rejected_kandidat', true)
            ->where(function ($query) {
                $query->whereNull('is_rejected_kandidat_by')
                    ->orWhere('is_rejected_kandidat_by', '=', '');
            })
            ->whereNotNull('rejected_by')
            ->where('rejected_by', '!=', '')
            ->update([
                'is_rejected_kandidat_by' => DB::raw('rejected_by'),
                'is_rejected_kandidat_at' => DB::raw('COALESCE(rejected_at, updated_at, created_at)'),
                'is_rejected_kandidat_reason' => DB::raw('alasan_reject'),
            ]);

        if (Schema::hasColumn('new_recruitment', 'reject_interview_user_by')) {
            DB::table('new_recruitment')
                ->where('is_rejected_kandidat', true)
                ->where(function ($query) {
                    $query->whereNull('is_rejected_kandidat_by')
                        ->orWhere('is_rejected_kandidat_by', '=', '');
                })
                ->whereNotNull('reject_interview_user_by')
                ->where('reject_interview_user_by', '!=', '')
                ->update([
                    'is_rejected_kandidat_by' => DB::raw('reject_interview_user_by'),
                    'is_rejected_kandidat_at' => DB::raw('COALESCE(reject_interview_user_at, updated_at, created_at)'),
                    'is_rejected_kandidat_reason' => DB::raw("COALESCE(alasan_reject, 'Tidak lulus interview user')"),
                ]);
        }

        DB::table('new_recruitment')
            ->select(['id', 'meta_history'])
            ->where('is_rejected_kandidat', true)
            ->where(function ($query) {
                $query->whereNull('is_rejected_kandidat_by')
                    ->orWhere('is_rejected_kandidat_by', '=', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $history = json_decode($row->meta_history ?: '[]', true);
                    if (!is_array($history)) {
                        continue;
                    }

                    for ($i = count($history) - 1; $i >= 0; $i--) {
                        if (($history[$i]['status'] ?? '') !== 'hrd_final_decision_rejected') {
                            continue;
                        }

                        $entry = $history[$i];
                        $reason = trim((string) ($entry['reject_reason'] ?? $entry['alasan_reject'] ?? $entry['reason'] ?? ''));

                        DB::table('new_recruitment')
                            ->where('id', $row->id)
                            ->update([
                                'is_rejected_kandidat_by' => $entry['by'] ?? null,
                                'is_rejected_kandidat_at' => $entry['at'] ?? Carbon::now(),
                                'is_rejected_kandidat_reason' => $reason !== '' ? $reason : null,
                            ]);

                        break;
                    }
                }
            });
    }

    public function down()
    {
        Schema::table('new_recruitment', function (Blueprint $table) {
            $columns = [];

            foreach (['is_rejected_kandidat_reason', 'is_rejected_kandidat_at', 'is_rejected_kandidat_by'] as $column) {
                if (Schema::hasColumn('new_recruitment', $column)) {
                    $columns[] = $column;
                }
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
}
