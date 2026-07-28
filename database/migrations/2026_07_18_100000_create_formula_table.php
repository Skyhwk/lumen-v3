<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormulaTable extends Migration
{
    public function up()
    {
        Schema::create('formula', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_kategori')->index();
            $table->unsignedInteger('id_parameter')->index();
            $table->string('kategori', 100);
            $table->string('parameter', 150);
            $table->text('formula');
            $table->json('formula_json')->nullable();
            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('updated_by', 255)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('deleted_by', 255)->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->index(['id_kategori', 'status']);
            $table->index(['id_parameter', 'status', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('formula');
    }
}
