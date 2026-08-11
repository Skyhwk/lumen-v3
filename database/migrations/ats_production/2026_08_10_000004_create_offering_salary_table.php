<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOfferingSalaryTable extends Migration
{
    /**
     * Run the migrations for offering_salary table.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('offering_salary')) {
            Schema::create('offering_salary', function (Blueprint $table) {
                $table->id();
                $table->integer('id_recruitment')->nullable();
                $table->string('gaji_pokok', 20)->nullable();
                $table->string('tunjangan', 20)->nullable();
                $table->boolean('is_active')->default(true)->nullable();
                $table->tinyInteger('flag')->default(0)->nullable();
                $table->string('created_by', 70)->nullable();
                $table->string('updated_by', 70)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('offering_salary');
    }
}
