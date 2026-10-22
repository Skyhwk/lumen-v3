<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class RenameEmployeeDocumentTables extends Migration
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
    }

    public function down()
    {
        if (
            Schema::hasTable('employee_document_sequences')
            && !Schema::hasTable('document_number_sequences')
        ) {
            Schema::rename('employee_document_sequences', 'document_number_sequences');
        }

        if (
            Schema::hasTable('employee_documents')
            && !Schema::hasTable('employee_generated_documents')
        ) {
            Schema::rename('employee_documents', 'employee_generated_documents');
        }
    }
}
