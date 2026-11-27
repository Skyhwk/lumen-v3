<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCiscoPhoneDirectoryEntriesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('cisco_phone_directory_entries')) {
            Schema::create('cisco_phone_directory_entries', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('display_name', 120);
                $table->string('telephone', 40);
                $table->string('department', 120)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'sort_order', 'display_name'], 'idx_cisco_dir_active_sort');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('cisco_phone_directory_entries');
    }
}