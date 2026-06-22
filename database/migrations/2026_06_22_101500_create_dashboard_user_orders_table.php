<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDashboardUserOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('dashboard_user_orders', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('user_name')->nullable();
            $table->jsonb('dashboard_order');
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->unique('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('dashboard_user_orders');
    }
}
