<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIsRejectFinanceToNewRecruitmentTable extends Migration
{
    public function up()
    {
        Schema::table('new_recruitment', function (Blueprint $table) {
            if (!Schema::hasColumn('new_recruitment', 'is_reject_finance')) {
                $table->boolean('is_reject_finance')->default(false);
            }

            if (!Schema::hasColumn('new_recruitment', 'is_reject_finance_by')) {
                $table->string('is_reject_finance_by', 255)->nullable();
            }

            if (!Schema::hasColumn('new_recruitment', 'is_reject_finance_at')) {
                $table->timestamp('is_reject_finance_at')->nullable();
            }

            if (!Schema::hasColumn('new_recruitment', 'is_reject_finance_reason')) {
                $table->text('is_reject_finance_reason')->nullable();
            }
        });

        if (!Schema::hasColumn('new_recruitment', 'is_reject_finance')) {
            return;
        }

        DB::table('new_recruitment')
            ->select(['id', 'meta_history'])
            ->where('meta_history', 'like', '%finance_rejected%')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $history = json_decode($row->meta_history ?: '[]', true);
                    if (!is_array($history)) {
                        continue;
                    }

                    for ($i = count($history) - 1; $i >= 0; $i--) {
                        if (($history[$i]['status'] ?? '') !== 'finance_rejected') {
                            continue;
                        }

                        $entry = $history[$i];
                        $reason = trim((string) ($entry['reject_reason'] ?? $entry['alasan_reject'] ?? $entry['reason'] ?? ''));

                        DB::table('new_recruitment')
                            ->where('id', $row->id)
                            ->update([
                                'is_reject_finance' => true,
                                'is_reject_finance_by' => $entry['by'] ?? null,
                                'is_reject_finance_at' => $entry['at'] ?? Carbon::now(),
                                'is_reject_finance_reason' => $reason !== '' ? $reason : null,
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

            foreach ([
                'is_reject_finance_reason',
                'is_reject_finance_at',
                'is_reject_finance_by',
                'is_reject_finance',
            ] as $column) {
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
