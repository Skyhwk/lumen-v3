<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScaleTypesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('scale_types')) {
        Schema::create('scale_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 255)->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'idx_scale_types_active_order');
        });
        }
    }

    public function down()
    {
        Schema::dropIfExists('scale_types');
    }
}