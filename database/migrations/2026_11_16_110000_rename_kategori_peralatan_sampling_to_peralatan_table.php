<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kategori_peralatan_sampling')) {
            return;
        }

        if (Schema::hasTable('peralatan')) {
            return;
        }

        Schema::rename('kategori_peralatan_sampling', 'peralatan');
    }

    public function down(): void
    {
        if (!Schema::hasTable('peralatan')) {
            return;
        }

        if (Schema::hasTable('kategori_peralatan_sampling')) {
            return;
        }

        Schema::rename('peralatan', 'kategori_peralatan_sampling');
    }
};
