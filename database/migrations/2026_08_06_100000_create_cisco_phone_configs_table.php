<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCiscoPhoneConfigsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('cisco_phone_configs')) {
        Schema::create('cisco_phone_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('mac_address', 12)->unique();
            $table->string('label', 150)->nullable();
            $table->string('phone_model', 50)->default('CP-3905');
            $table->string('extension', 30)->nullable();
            $table->string('display_name', 100)->nullable();
            $table->string('sip_server', 191)->nullable();
            $table->string('auth_name', 100)->nullable();
            $table->text('auth_password')->nullable();
            $table->text('phone_password')->nullable();
            $table->json('config_json')->nullable();
            $table->string('cnf_filename', 50)->nullable();
            $table->string('cnf_file_path', 255)->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'extension'], 'idx_cisco_phone_active_ext');
        });
        }
    }

    public function down()
    {
        Schema::dropIfExists('cisco_phone_configs');
    }
}
