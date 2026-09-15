<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeKebijakanDokumenUniqueNoRevisian extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kebijakan_dokumen')) {
            return;
        }

        Schema::table('kebijakan_dokumen', function (Blueprint $table) {
            if ($this->hasIndex('kebijakan_dokumen', 'uq_kebijakan_dokumen_no')) {
                $table->dropUnique('uq_kebijakan_dokumen_no');
            }
        });

        Schema::table('kebijakan_dokumen', function (Blueprint $table) {
            if (!$this->hasIndex('kebijakan_dokumen', 'uq_kebijakan_dokumen_no_revisian')) {
                $table->unique(['no_dokumen', 'revisian'], 'uq_kebijakan_dokumen_no_revisian');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('kebijakan_dokumen')) {
            return;
        }

        Schema::table('kebijakan_dokumen', function (Blueprint $table) {
            if ($this->hasIndex('kebijakan_dokumen', 'uq_kebijakan_dokumen_no_revisian')) {
                $table->dropUnique('uq_kebijakan_dokumen_no_revisian');
            }
        });

        Schema::table('kebijakan_dokumen', function (Blueprint $table) {
            if (!$this->hasIndex('kebijakan_dokumen', 'uq_kebijakan_dokumen_no')) {
                $table->unique('no_dokumen', 'uq_kebijakan_dokumen_no');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT COUNT(1) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return !empty($result) && (int) ($result[0]->aggregate ?? 0) > 0;
    }
}
