<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('calendar_export_histories')) {
            return;
        }

        Schema::create('calendar_export_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('revision')->default(0);
            $table->string('exported_by', 255)->nullable();
            $table->timestamp('exported_at');
            $table->unique(['year', 'revision'], 'calendar_export_year_revision_unique');
            $table->index(['year', 'exported_at'], 'calendar_export_year_date_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('calendar_export_histories')) {
            Schema::drop('calendar_export_histories');
        }
    }
};
