<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGdTablesAndHrFormExtensions extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('gd_user')) {
            Schema::create('gd_user', function (Blueprint $table) {
                $table->unsignedInteger('id')->primary();
                $table->unsignedInteger('karyawan_id')->index();
                $table->string('username')->nullable();
                $table->string('email')->nullable();
                $table->string('password');
                $table->boolean('is_active')->default(true);
                $table->string('created_by')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('updated_by')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->string('deleted_by')->nullable();
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (!Schema::hasTable('gd_user_token')) {
            Schema::create('gd_user_token', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('karyawan_id')->index();
                $table->string('token', 512)->unique();
                $table->dateTime('create_date')->nullable();
                $table->dateTime('expired')->nullable();
                $table->boolean('is_logged_in')->default(false);
                $table->boolean('is_expired')->default(false);
                $table->string('type', 32)->nullable();
            });
        }

        if (!Schema::hasTable('gd_migration_map')) {
            Schema::create('gd_migration_map', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('old_connection', 64);
                $table->string('old_table', 64);
                $table->unsignedBigInteger('old_id');
                $table->string('new_table', 64);
                $table->unsignedBigInteger('new_id');
                $table->dateTime('migrated_at');

                $table->unique(['old_connection', 'old_table', 'old_id'], 'gd_migration_map_legacy_unique');
            });
        }

        if (!Schema::hasTable('hr_attendance_correction_detail')) {
            Schema::create('hr_attendance_correction_detail', function (Blueprint $table) {
                $table->unsignedBigInteger('request_id')->primary();
                $table->string('correction_type', 32);
                $table->date('correction_date');
                $table->time('correction_time');
                $table->string('attachment_path', 512)->nullable();

                $table->foreign('request_id')->references('id')->on('hr_request')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('hr_consultation_detail')) {
            Schema::create('hr_consultation_detail', function (Blueprint $table) {
                $table->unsignedBigInteger('request_id')->primary();
                $table->string('consultation_type', 32);
                $table->date('consultation_date');
                $table->time('consultation_time');

                $table->foreign('request_id')->references('id')->on('hr_request')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('hr_event_report_detail')) {
            Schema::create('hr_event_report_detail', function (Blueprint $table) {
                $table->unsignedBigInteger('request_id')->primary();
                $table->string('subject', 255);
                $table->date('event_date');
                $table->time('event_time');
                $table->string('attachment_path', 512)->nullable();

                $table->foreign('request_id')->references('id')->on('hr_request')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('hr_event_report_detail');
        Schema::dropIfExists('hr_consultation_detail');
        Schema::dropIfExists('hr_attendance_correction_detail');
        Schema::dropIfExists('gd_migration_map');
        Schema::dropIfExists('gd_user_token');
        Schema::dropIfExists('gd_user');
    }
}
