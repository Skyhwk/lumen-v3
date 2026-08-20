<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmployeeMigratedColumnsToCandidateOnboardingVerificationTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('candidate_onboarding_verification')) {
            Schema::create('candidate_onboarding_verification', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('new_recruitment_id')->unique();
                $table->boolean('has_id_card')->default(false);
                $table->boolean('has_email')->default(false);
                $table->boolean('has_server_account')->default(false);
                $table->boolean('has_all_documents')->default(false);
                $table->string('verified_by', 255)->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('employee_migrated_at')->nullable();
                $table->string('employee_migrated_by', 255)->nullable();
                $table->timestamps();

                if (Schema::hasTable('new_recruitment')) {
                    $table->foreign('new_recruitment_id')
                        ->references('id')
                        ->on('new_recruitment')
                        ->onDelete('cascade');
                }
            });

            return;
        }

        Schema::table('candidate_onboarding_verification', function (Blueprint $table) {
            if (!Schema::hasColumn('candidate_onboarding_verification', 'employee_migrated_at')) {
                $table->timestamp('employee_migrated_at')->nullable()->after('verified_at');
            }
            if (!Schema::hasColumn('candidate_onboarding_verification', 'employee_migrated_by')) {
                $table->string('employee_migrated_by', 255)->nullable()->after('employee_migrated_at');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('candidate_onboarding_verification')) {
            return;
        }

        Schema::table('candidate_onboarding_verification', function (Blueprint $table) {
            foreach (['employee_migrated_by', 'employee_migrated_at'] as $column) {
                if (Schema::hasColumn('candidate_onboarding_verification', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
