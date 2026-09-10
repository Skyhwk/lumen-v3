<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('redirect_links')) {
            return;
        }

        Schema::create('redirect_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('batch_key', 100)->unique();
            $table->string('label', 255)->nullable();
            $table->text('target_url');
            $table->unsignedBigInteger('visit_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();
            $table->string('deleted_by', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->index(['is_active', 'batch_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirect_links');
    }
};
