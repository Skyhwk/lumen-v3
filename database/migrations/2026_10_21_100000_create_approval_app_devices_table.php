<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApprovalAppDevicesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('approval_app_devices')) {
            return;
        }

        Schema::create('approval_app_devices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('email', 191)->index();
            $table->string('device_id', 191);
            $table->text('fcm_token')->nullable();
            $table->string('platform', 32)->default('android');
            $table->timestamps();

            $table->unique(['email', 'device_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('approval_app_devices');
    }
}
