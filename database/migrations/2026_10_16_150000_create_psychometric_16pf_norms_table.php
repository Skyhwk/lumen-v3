<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePsychometric16pfNormsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('psychometric_16pf_norms')) {
            return;
        }

        Schema::create('psychometric_16pf_norms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('norm_group', 32);
            $table->string('factor', 8);
            $table->unsignedTinyInteger('raw_score');
            $table->unsignedTinyInteger('sten');
            $table->timestamps();

            $table->unique(['norm_group', 'factor', 'raw_score'], 'uq_16pf_norm_group_factor_raw');
            $table->index(['norm_group', 'factor'], 'idx_16pf_norm_group_factor');
        });
    }

    public function down()
    {
        Schema::dropIfExists('psychometric_16pf_norms');
    }
}
