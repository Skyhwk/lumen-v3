<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBiayaOperasionalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('biaya_operasional', function (Blueprint $table) {
            $table->id();
            $table->string('bo_number', 50)->unique();
            $table->string('person_in_charge', 150);
            $table->json('destination');
            $table->date('travel_date');
            $table->enum('status', ['requested', 'request_approved', 'approved', 'prepared', 'completed', 'void'])->default('requested');
            $table->boolean('is_active')->default(true);

            $table->string('request_approved_by', 150)->nullable();
            $table->dateTime('request_approved_at')->nullable();
            $table->string('approved_by', 150)->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->string('rejected_by', 150)->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->string('prepared_by', 150)->nullable();
            $table->dateTime('prepared_at')->nullable();
            $table->string('completed_by', 150)->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->string('created_by', 150)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('updated_by', 150)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('deleted_by', 150)->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->index('bo_number');
            $table->index('status');
            $table->index('is_active');
            $table->index('travel_date');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('biaya_operasional');
    }
}


