<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDashboardComponentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dashboard_component', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('nama_komponen');
            $table->string('nama_dashboard');
            $table->string('owner');
            $table->string('owner_id');
            $table->integer('is_active');
            $table->string('updated_by');
            $table->string('created_by');
            $table->dateTime('deleted_at')->nullable();
            $table->string('deleted_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dashboard_component');
    }
}
