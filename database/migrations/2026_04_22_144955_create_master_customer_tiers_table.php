<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterCustomerTiersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_customer_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Seed, Root, dll
            $table->integer('min_point'); // inclusive
            $table->integer('max_point')->nullable(); // null = tak terbatas
            $table->integer('level')->index(); // urutan (1,2,3,...)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_customer_tiers');
    }
}
