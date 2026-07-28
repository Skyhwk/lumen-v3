<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSatuanToFormulaTable extends Migration
{
    public function up()
    {
        Schema::table('formula', function (Blueprint $table) {
            $table->string('satuan', 150)->nullable()->after('template_stp');

            $table->index(
                ['id_parameter', 'id_template_stp', 'satuan', 'is_active'],
                'formula_param_template_satuan_active_idx'
            );
        });
    }

    public function down()
    {
        Schema::table('formula', function (Blueprint $table) {
            $table->dropIndex('formula_param_template_satuan_active_idx');
            $table->dropColumn('satuan');
        });
    }
}
