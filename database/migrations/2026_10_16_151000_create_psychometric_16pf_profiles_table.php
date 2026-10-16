<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePsychometric16pfProfilesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('psychometric_16pf_profiles')) {
            return;
        }

        Schema::create('psychometric_16pf_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('factor', 8)->unique();
            $table->text('low_text');
            $table->text('high_text');
            $table->string('short_low', 500)->nullable();
            $table->string('short_high', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('psychometric_16pf_profiles');
    }
}
