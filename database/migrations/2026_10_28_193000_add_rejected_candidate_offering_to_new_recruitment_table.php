<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRejectedCandidateOfferingToNewRecruitmentTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('new_recruitment')) {
            return;
        }

        Schema::table('new_recruitment', function (Blueprint $table) {
            if (!Schema::hasColumn('new_recruitment', 'rejected_candidate_offering')) {
                $table->boolean('rejected_candidate_offering')->default(false)->after('rejected_salary_reason');
            }
            if (!Schema::hasColumn('new_recruitment', 'rejected_candidate_offering_reason')) {
                $table->text('rejected_candidate_offering_reason')->nullable()->after('rejected_candidate_offering');
            }
        });

        // Backfill data lampau: Kandidat yang statusnya pernah candidate_offering_rejected
        // dan belum ada resubmit/sent setelahnya
        DB::table('new_recruitment')
            ->select(['id', 'meta_history', 'rejected_salary', 'rejected_salary_reason'])
            ->where('meta_history', 'like', '%candidate_offering_rejected%')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $history = json_decode($row->meta_history ?: '[]', true);
                    if (!is_array($history)) {
                        continue;
                    }

                    $rejectedIndex = null;
                    $rejectReason = null;
                    for ($i = count($history) - 1; $i >= 0; $i--) {
                        if (($history[$i]['status'] ?? '') === 'candidate_offering_rejected') {
                            $rejectedIndex = $i;
                            $rejectReason = $history[$i]['reject_reason'] ?? null;
                            break;
                        }
                    }

                    if ($rejectedIndex === null) {
                        continue;
                    }

                    $hasResent = false;
                    for ($i = $rejectedIndex + 1; $i < count($history); $i++) {
                        if (in_array((string) ($history[$i]['status'] ?? ''), [
                            'candidate_offering_sent',
                            'waiting_approve_finance',
                            'finance_approved',
                            'candidate_offering_approved',
                            'hired',
                        ], true)) {
                            $hasResent = true;
                            break;
                        }
                    }

                    if (!$hasResent) {
                        $update = [
                            'rejected_candidate_offering' => 1,
                            'rejected_candidate_offering_reason' => $rejectReason ?: ($row->rejected_salary_reason ?? null),
                        ];

                        // Jika rejected_salary saat ini aktif tapi sebenarnya dari kandidat (bukan penolakan/negosiasi direktur),
                        // bersihkan rejected_salary agar tidak bentrok
                        $hasDirectorReject = false;
                        for ($i = count($history) - 1; $i >= 0; $i--) {
                            if (preg_match('/^internal_sallary_offer_(rejected|negotiated)$/', (string) ($history[$i]['status'] ?? ''))) {
                                $hasDirectorReject = true;
                                break;
                            }
                        }

                        if (!$hasDirectorReject && (bool) ($row->rejected_salary ?? false)) {
                            $update['rejected_salary'] = 0;
                            $update['rejected_salary_reason'] = null;
                        }

                        DB::table('new_recruitment')->where('id', $row->id)->update($update);
                    }
                }
            });
    }

    public function down()
    {
        if (!Schema::hasTable('new_recruitment')) {
            return;
        }

        Schema::table('new_recruitment', function (Blueprint $table) {
            foreach (['rejected_candidate_offering', 'rejected_candidate_offering_reason'] as $column) {
                if (Schema::hasColumn('new_recruitment', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}

