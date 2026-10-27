<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeeDocumentTables extends Migration
{
    public function up()
    {
        if (
            Schema::hasTable('document_number_sequences')
            && !Schema::hasTable('employee_document_sequences')
        ) {
            Schema::rename('document_number_sequences', 'employee_document_sequences');
        }

        if (
            Schema::hasTable('employee_generated_documents')
            && !Schema::hasTable('employee_documents')
        ) {
            Schema::rename('employee_generated_documents', 'employee_documents');
        }

        if (!Schema::hasTable('employee_document_sequences')) {
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

        if (!Schema::hasTable('employee_documents')) {
            Schema::create('employee_documents', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('karyawan_id');
                $table->unsignedBigInteger('new_recruitment_id')->nullable();
                $table->string('document_type', 50);
                $table->string('document_number', 100)->unique();
                $table->unsignedSmallInteger('sequence_year');
                $table->unsignedInteger('sequence_number');
                $table->string('file_name', 255);
                $table->text('file_path');
                $table->string('mime_type', 100)->default('application/pdf');
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('status', 30)->default('generating');
                $table->boolean('is_active')->default(true);
                $table->string('generated_by', 100)->nullable();
                $table->dateTime('generated_at')->nullable();
                $table->timestamps();

                $table->index(['karyawan_id', 'document_type', 'is_active'], 'employee_doc_employee_type_active');
                $table->index('new_recruitment_id');
                $table->unique(
                    ['document_type', 'sequence_year', 'sequence_number'],
                    'employee_doc_type_year_sequence_unique'
                );
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_document_sequences');
    }
}
