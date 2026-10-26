<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrWorkflowTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('hr_special_leave_type')) {
            Schema::create('hr_special_leave_type', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->integer('duration');
                $table->text('description')->nullable();
                $table->string('created_by')->default('System');
                $table->dateTime('created_at');
                $table->string('updated_by')->default('System');
                $table->dateTime('updated_at');
                $table->boolean('is_active')->default(true);
            });
        }

        if (!Schema::hasTable('hr_request')) {
            Schema::create('hr_request', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('uuid', 36)->unique();
                $table->string('request_type', 32);
                $table->string('no_document', 64)->unique();
                $table->unsignedInteger('karyawan_id');
                $table->unsignedInteger('id_cabang')->nullable();
                $table->unsignedInteger('id_department')->nullable();
                $table->string('status', 64);
                $table->string('workflow_code', 64)->default('default_2_step');
                $table->text('description')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->unsignedInteger('created_by_karyawan_id')->nullable();
                $table->string('created_by_name')->nullable();
                $table->dateTime('created_at');
                $table->string('updated_by_name')->nullable();
                $table->dateTime('updated_at');
                $table->boolean('is_active')->default(true);

                $table->index(['request_type', 'status', 'karyawan_id'], 'hr_request_type_status_karyawan');
                $table->index(['request_type', 'status', 'id_department'], 'hr_request_type_status_dept');
                $table->index('karyawan_id');
            });
        }

        if (!Schema::hasTable('hr_approval_step')) {
            Schema::create('hr_approval_step', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('request_id');
                $table->string('step', 32);
                $table->string('state', 32)->default('pending');
                $table->unsignedInteger('actor_karyawan_id')->nullable();
                $table->string('actor_name')->nullable();
                $table->dateTime('acted_at')->nullable();
                $table->text('reason')->nullable();

                $table->index(['request_id', 'step']);
                $table->foreign('request_id')->references('id')->on('hr_request')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('hr_leave_detail')) {
            Schema::create('hr_leave_detail', function (Blueprint $table) {
                $table->unsignedBigInteger('request_id')->primary();
                $table->string('leave_kind', 32);
                $table->unsignedInteger('special_leave_type_id')->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->string('attachment_path', 512)->nullable();

                $table->foreign('request_id')->references('id')->on('hr_request')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('hr_permission_detail')) {
            Schema::create('hr_permission_detail', function (Blueprint $table) {
                $table->unsignedBigInteger('request_id')->primary();
                $table->string('permission_kind', 32);
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->string('attachment_path', 512)->nullable();

                $table->foreign('request_id')->references('id')->on('hr_request')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('hr_overtime_detail')) {
            Schema::create('hr_overtime_detail', function (Blueprint $table) {
                $table->unsignedBigInteger('request_id')->primary();
                $table->date('start_date');
                $table->date('end_date');
                $table->time('start_time');
                $table->time('end_time');

                $table->foreign('request_id')->references('id')->on('hr_request')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('hr_overtime_participant')) {
            Schema::create('hr_overtime_participant', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('request_id');
                $table->unsignedInteger('karyawan_id');
                $table->boolean('is_active')->default(true);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();

                $table->unique(['request_id', 'karyawan_id'], 'hr_ot_participant_unique');
                $table->foreign('request_id')->references('id')->on('hr_request')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('hr_migration_map')) {
            Schema::create('hr_migration_map', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('old_connection', 64);
                $table->string('old_table', 64);
                $table->unsignedBigInteger('old_id');
                $table->string('new_table', 64);
                $table->unsignedBigInteger('new_id');
                $table->dateTime('migrated_at');

                $table->unique(['old_connection', 'old_table', 'old_id'], 'hr_migration_map_legacy_unique');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('hr_migration_map');
        Schema::dropIfExists('hr_overtime_participant');
        Schema::dropIfExists('hr_overtime_detail');
        Schema::dropIfExists('hr_permission_detail');
        Schema::dropIfExists('hr_leave_detail');
        Schema::dropIfExists('hr_approval_step');
        Schema::dropIfExists('hr_request');
        Schema::dropIfExists('hr_special_leave_type');
    }
}
