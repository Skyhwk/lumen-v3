<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komentar / debat pada task.
 *
 * php artisan migrate --path=database/migrations/2026_09_23_160000_create_pm_task_comments_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_task_comments')) {
            return;
        }

        Schema::create('pm_task_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('task_id', 'pm_cmt_task_fk')
                ->references('id')->on('pm_tasks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_task_comments')) {
            Schema::drop('pm_task_comments');
        }
    }
};
