<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAssessmentDeliveryFieldsToNewRecruitment extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('new_recruitment')) {
            return;
        }

        Schema::table('new_recruitment', function (Blueprint $table) {
            if (!Schema::hasColumn('new_recruitment', 'assessment_delivery_choice')) {
                $table->string('assessment_delivery_choice', 16)->nullable()->after('token');
            }
            if (!Schema::hasColumn('new_recruitment', 'assessment_delivery_chosen_at')) {
                $table->dateTime('assessment_delivery_chosen_at')->nullable()->after('assessment_delivery_choice');
            }
            if (!Schema::hasColumn('new_recruitment', 'assessment_invitation_sent_at')) {
                $table->dateTime('assessment_invitation_sent_at')->nullable()->after('assessment_delivery_chosen_at');
            }
            if (!Schema::hasColumn('new_recruitment', 'assessment_resume_reminder_sent_at')) {
                $table->dateTime('assessment_resume_reminder_sent_at')->nullable()->after('assessment_invitation_sent_at');
            }
        });
    }

    public function down()
    {
        // Kolom dipertahankan agar rollback tidak menghapus riwayat pengiriman kandidat.
    }
}
