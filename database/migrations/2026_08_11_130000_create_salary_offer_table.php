<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalaryOfferTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('sallary_offer')) {
            Schema::create('sallary_offer', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('new_recruitment_id');
                $table->decimal('sallary_offer_hrd', 15, 2)->nullable();
                $table->decimal('sallary_offer_direktur', 15, 2)->nullable();
                $table->decimal('final_sallary', 15, 2)->nullable();
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->timestamps();

                $table->foreign('new_recruitment_id')
                    ->references('id')
                    ->on('new_recruitment')
                    ->onDelete('cascade');
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
        Schema::dropIfExists('salary_offer');
    }
}
