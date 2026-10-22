<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentNumberSequencesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('employee_document_sequences')) {
            return;
        }

        Schema::create('employee_document_sequences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('document_type', 50);
            $table->string('prefix', 100);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->unsignedTinyInteger('padding')->default(4);
            $table->timestamps();

            $table->unique(['document_type', 'year'], 'employee_doc_sequence_type_year_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('employee_document_sequences');
        Schema::dropIfExists('document_number_sequences');
    }
}
