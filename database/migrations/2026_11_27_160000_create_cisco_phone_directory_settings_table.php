<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreateCiscoPhoneDirectorySettingsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('cisco_phone_directory_settings')) {
            Schema::create('cisco_phone_directory_settings', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title', 32)->default('Phonebook Kantor');
                $table->string('base_url', 255)->nullable();
                $table->string('cnf_base_url', 255)->nullable();
                $table->string('access_key', 100)->nullable();
                $table->boolean('embed_in_cnf')->default(false);
                $table->boolean('use_directory_url_tag')->default(false);
                $table->timestamps();
            });

            DB::table('cisco_phone_directory_settings')->insert([
                'title' => 'Phonebook Kantor',
                'embed_in_cnf' => false,
                'use_directory_url_tag' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('cisco_phone_directory_settings');
    }
}