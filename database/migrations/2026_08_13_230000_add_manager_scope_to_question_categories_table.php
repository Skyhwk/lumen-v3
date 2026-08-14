<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddManagerScopeToQuestionCategoriesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('question_categories')) {
            return;
        }

        Schema::table('question_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('question_categories', 'category_scope')) {
                $table->string('category_scope', 20)->default('hr')->after('name');
            }
            if (!Schema::hasColumn('question_categories', 'owner_karyawan')) {
                $table->string('owner_karyawan', 150)->nullable()->after('category_scope');
            }
            if (!Schema::hasColumn('question_categories', 'assigned_manager')) {
                $table->string('assigned_manager', 150)->nullable()->after('owner_karyawan');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('question_categories')) {
            return;
        }

        Schema::table('question_categories', function (Blueprint $table) {
            foreach (['assigned_manager', 'owner_karyawan', 'category_scope'] as $column) {
                if (Schema::hasColumn('question_categories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
