<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAnalystWorksheetTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('analyst_worksheet_headers')) {
            Schema::create('analyst_worksheet_headers', function (Blueprint $table) {
                $table->id();
                $table->string('nama_workspace')->nullable();
                $table->unsignedBigInteger('id_kategori')->nullable();
                $table->string('parameter')->nullable();
                $table->string('created_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_finished')->default(false);
            });
        }

        if (!Schema::hasTable('analyst_worksheet_details')) {
            Schema::create('analyst_worksheet_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_header');
                $table->string('no_sampel');
                $table->text('catatan')->nullable();
                $table->string('created_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('deleted_by')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->foreign('id_header')
                      ->references('id')->on('analyst_worksheet_headers')
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
        Schema::dropIfExists('analyst_worksheet_details');
        Schema::dropIfExists('analyst_worksheet_headers');
    }
}
