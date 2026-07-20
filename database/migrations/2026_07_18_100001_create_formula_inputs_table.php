<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormulaInputsTable extends Migration
{
    public function up()
    {
        Schema::create('formula_inputs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('formula_id')->index();
            $table->string('variable', 64);
            $table->string('label', 150);
            $table->enum('type', ['number', 'integer', 'decimal'])->default('number');
            $table->boolean('required')->default(true);
            $table->string('default_value', 50)->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreign('formula_id')->references('id')->on('formula')->onDelete('cascade');
            $table->unique(['formula_id', 'variable'], 'uq_formula_input_variable');
            $table->index(['formula_id', 'urutan']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('formula_inputs');
    }
}
