<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('promo_pengujian')) {
            return;
        }

        Schema::table('promo_pengujian', function (Blueprint $table) {
            if (!Schema::hasColumn('promo_pengujian', 'ketentuan_promo')) {
                $table->longText('ketentuan_promo')
                    ->nullable()
                    ->after('nama_diskon')
                    ->comment('HTML dari TinyMCE');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('promo_pengujian')) {
            return;
        }

        Schema::table('promo_pengujian', function (Blueprint $table) {
            if (Schema::hasColumn('promo_pengujian', 'ketentuan_promo')) {
                $table->dropColumn('ketentuan_promo');
            }
        });
    }
};
