<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recruitment_application_drafts')
            || !Schema::hasColumn('recruitment_application_drafts', 'no_request')
            || !Schema::hasColumn('recruitment_application_drafts', 'email')
            || !Schema::hasColumn('recruitment_application_drafts', 'no_telepon')
            || !Schema::hasColumn('recruitment_application_drafts', 'last_activity_at')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM recruitment_application_drafts'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('recruitment_application_drafts', function (Blueprint $table) use ($indexes) {
            if (!in_array('recruitment_drafts_request_email_activity_idx', $indexes, true)) {
                $table->index(['no_request', 'email', 'last_activity_at'], 'recruitment_drafts_request_email_activity_idx');
            }

            if (!in_array('recruitment_drafts_request_phone_activity_idx', $indexes, true)) {
                $table->index(['no_request', 'no_telepon', 'last_activity_at'], 'recruitment_drafts_request_phone_activity_idx');
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: the indexes are safe to keep on shared databases.
    }
};
