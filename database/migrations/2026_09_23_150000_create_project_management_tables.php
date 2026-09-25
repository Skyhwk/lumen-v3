<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project Management — board per divisi + tasks + activity log.
 *
 * php artisan migrate --path=database/migrations/2026_09_23_150000_create_project_management_tables.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pm_projects')) {
            Schema::create('pm_projects', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('division_id')->index();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['division_id', 'name'], 'pm_projects_div_name_uq');
            });
        }

        if (!Schema::hasTable('pm_columns')) {
            Schema::create('pm_columns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->index();
                $table->string('name', 80);
                $table->string('color', 20)->default('#64748b');
                $table->unsignedInteger('position')->default(0);
                $table->boolean('is_done')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->foreign('project_id', 'pm_col_project_fk')
                    ->references('id')->on('pm_projects')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('pm_tasks')) {
            Schema::create('pm_tasks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('column_id')->index();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('assignee_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('priority', 20)->default('medium'); // low|medium|high|urgent
                $table->date('due_date')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->foreign('project_id', 'pm_task_project_fk')
                    ->references('id')->on('pm_projects')->onDelete('cascade');
                $table->foreign('column_id', 'pm_task_column_fk')
                    ->references('id')->on('pm_columns')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('pm_task_activities')) {
            Schema::create('pm_task_activities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('task_id')->index();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action', 50);
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->foreign('task_id', 'pm_act_task_fk')
                    ->references('id')->on('pm_tasks')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_task_activities')) {
            Schema::drop('pm_task_activities');
        }
        if (Schema::hasTable('pm_tasks')) {
            Schema::drop('pm_tasks');
        }
        if (Schema::hasTable('pm_columns')) {
            Schema::drop('pm_columns');
        }
        if (Schema::hasTable('pm_projects')) {
            Schema::drop('pm_projects');
        }
    }
};
