<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_documents')) {
            return;
        }

        if (Schema::hasColumn('employee_documents', 'document_number')) {
            DB::statement(
                'ALTER TABLE employee_documents MODIFY document_number VARCHAR(100) NULL'
            );
        }

        if (Schema::hasColumn('employee_documents', 'sequence_year')) {
            DB::statement(
                'ALTER TABLE employee_documents MODIFY sequence_year SMALLINT UNSIGNED NULL'
            );
        }

        if (Schema::hasColumn('employee_documents', 'sequence_number')) {
            DB::statement(
                'ALTER TABLE employee_documents MODIFY sequence_number INT UNSIGNED NULL'
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_documents')) {
            return;
        }

        DB::table('employee_documents')
            ->whereNull('document_number')
            ->orWhereNull('sequence_year')
            ->orWhereNull('sequence_number')
            ->delete();

        if (Schema::hasColumn('employee_documents', 'document_number')) {
            DB::statement(
                'ALTER TABLE employee_documents MODIFY document_number VARCHAR(100) NOT NULL'
            );
        }

        if (Schema::hasColumn('employee_documents', 'sequence_year')) {
            DB::statement(
                'ALTER TABLE employee_documents MODIFY sequence_year SMALLINT UNSIGNED NOT NULL'
            );
        }

        if (Schema::hasColumn('employee_documents', 'sequence_number')) {
            DB::statement(
                'ALTER TABLE employee_documents MODIFY sequence_number INT UNSIGNED NOT NULL'
            );
        }
    }
};