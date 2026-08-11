<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStatusLhpTerlambatTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('status_lhp_terlambat')) {
            Schema::create('status_lhp_terlambat', function (Blueprint $table) {
                $table->id();
                $table->string('no_order');
                $table->string('no_lhp')->nullable();
                $table->string('status')->nullable();
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();

                $table->index('no_order');
                $table->index('no_lhp');
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
        Schema::dropIfExists('status_lhp_terlambat');
    }
}
