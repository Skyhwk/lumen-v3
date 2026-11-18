<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_data_access')) {
            return;
        }

        Schema::create('menu_data_access', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('menu_key', 100)->comment('Slug leaf menu (generic), contoh: rekap-bak');
            $table->json('grades')->nullable()->comment('Array grade karyawan, contoh: ["STAFF","MANAGER"]');
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 150)->nullable();
            $table->string('updated_by', 150)->nullable();
            $table->string('deleted_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            $table->unique('menu_key', 'menu_data_access_menu_key_uq');
            $table->index(['menu_key', 'is_active'], 'menu_data_access_menu_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_data_access');
    }
};
