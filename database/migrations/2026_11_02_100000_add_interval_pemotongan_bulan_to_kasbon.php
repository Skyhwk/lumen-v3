<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kasbon', function (Blueprint $table) {
            $table->unsignedTinyInteger('interval_pemotongan_bulan')->default(1)->after('tenor');
        });
    }

    public function down(): void
    {
        Schema::table('kasbon', function (Blueprint $table) {
            $table->dropColumn('interval_pemotongan_bulan');
        });
    }
};
