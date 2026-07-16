<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePencahayaanHeaderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('pencahayaan_header', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 50)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->bigInteger('id_parameter')->nullable();
            $table->string('parameter', 50)->nullable();
            $table->integer('lhps')->default(0);
            $table->text('notes_reject')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_by', 70)->nullable();
            $table->integer('is_approved')->default(0);
            $table->string('approved_by', 70)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('status')->default(0);
            $table->integer('is_active')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pencahayaan_header');
    }
}
