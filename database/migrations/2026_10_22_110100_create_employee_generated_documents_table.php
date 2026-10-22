<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeeGeneratedDocumentsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('employee_documents')) {
            return;
        }

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

    public function down()
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_generated_documents');
    }
}
