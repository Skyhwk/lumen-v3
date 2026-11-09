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
            if (!Schema::hasColumn('promo_pengujian', 'masa_berlaku')) {
                $table->string('masa_berlaku', 20)
                    ->nullable()
                    ->after('status')
                    ->comment('lifetime, periode');
            }
            if (!Schema::hasColumn('promo_pengujian', 'berlaku_dari')) {
                $table->date('berlaku_dari')->nullable()->after('masa_berlaku');
            }
            if (!Schema::hasColumn('promo_pengujian', 'berlaku_sampai')) {
                $table->date('berlaku_sampai')->nullable()->after('berlaku_dari');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('promo_pengujian')) {
            return;
        }

        Schema::table('promo_pengujian', function (Blueprint $table) {
            if (Schema::hasColumn('promo_pengujian', 'berlaku_sampai')) {
                $table->dropColumn('berlaku_sampai');
            }
            if (Schema::hasColumn('promo_pengujian', 'berlaku_dari')) {
                $table->dropColumn('berlaku_dari');
            }
            if (Schema::hasColumn('promo_pengujian', 'masa_berlaku')) {
                $table->dropColumn('masa_berlaku');
            }
        });
    }
};
