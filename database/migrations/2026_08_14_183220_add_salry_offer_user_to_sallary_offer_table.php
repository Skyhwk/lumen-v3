<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sallary_offer')) {
            return;
        }

        if (Schema::hasColumn('sallary_offer', 'sallary_offer_user')) {
            return;
        }

        Schema::table('sallary_offer', function (Blueprint $table) {
            $table->decimal('sallary_offer_user', 15, 2)->after('sallary_offer_hrd')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sallary_offer')) {
            return;
        }

        if (!Schema::hasColumn('sallary_offer', 'sallary_offer_user')) {
            return;
        }

        Schema::table('sallary_offer', function (Blueprint $table) {
            $table->dropColumn('sallary_offer_user');
        });
    }
};
