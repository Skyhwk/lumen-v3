<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sallary_offer', function (Blueprint $table) {
            // Menambahkan kolom setelah sallary_offer_hrd
            // Saya tambahkan ->nullable() agar tidak error jika tabel sudah memiliki data lama
            $table->decimal('sallary_offer_user', 15, 2)->after('sallary_offer_hrd')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sallary_offer', function (Blueprint $table) {
            $table->dropColumn('sallary_offer_user');
        });
    }
};