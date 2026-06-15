<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSetAksesDashboardTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('set_akses_dashboard', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('nama_dashboard');
            $table->jsonb('user_list');
            $table->dateTime('deleted_at')->nullable();
            $table->string('deleted_by')->nullable();
            $table->string('created_by');
            $table->string('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('set_akses_dashboard');
    }
}
