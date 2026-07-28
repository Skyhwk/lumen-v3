<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTemplateStpToFormulaTable extends Migration
{
    public function up()
    {
        Schema::table('formula', function (Blueprint $table) {
            $table->unsignedInteger('id_template_stp')->nullable()->after('id_parameter');
            $table->string('template_stp', 150)->nullable()->after('parameter');

            $table->index(['id_parameter', 'id_template_stp', 'is_active'], 'formula_param_template_active_idx');
        });
    }

    public function down()
    {
        Schema::table('formula', function (Blueprint $table) {
            $table->dropIndex('formula_param_template_active_idx');
            $table->dropColumn(['id_template_stp', 'template_stp']);
        });
    }
}
