<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('follow_up_customer_account')) {
            return;
        }

        Schema::table('follow_up_customer_account', function (Blueprint $table) {
            if (!Schema::hasColumn('follow_up_customer_account', 'preview_by_id')) {
                $table->unsignedBigInteger('preview_by_id')->nullable()->after('assigned_at');
            }
            if (!Schema::hasColumn('follow_up_customer_account', 'preview_until')) {
                $table->dateTime('preview_until')->nullable()->after('preview_by_id');
            }
        });

        Schema::table('follow_up_customer_account', function (Blueprint $table) {
            if (Schema::hasColumn('follow_up_customer_account', 'preview_until') && !$this->hasIndex('follow_up_customer_account', 'idx_follow_up_customer_preview_until')) {
                $table->index(['preview_until', 'followed_at'], 'idx_follow_up_customer_preview_until');
            }
            if (Schema::hasColumn('follow_up_customer_account', 'preview_by_id') && !$this->hasIndex('follow_up_customer_account', 'idx_follow_up_customer_preview_by')) {
                $table->index(['preview_by_id'], 'idx_follow_up_customer_preview_by');
            }
        });

        DB::table('follow_up_customer_account')
            ->whereNull('followed_at')
            ->whereNotNull('assigned_to')
            ->update([
                'assigned_to' => null,
                'slot' => null,
                'assigned_at' => null,
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('follow_up_customer_account')) {
            return;
        }

        Schema::table('follow_up_customer_account', function (Blueprint $table) {
            if ($this->hasIndex('follow_up_customer_account', 'idx_follow_up_customer_preview_until')) {
                $table->dropIndex('idx_follow_up_customer_preview_until');
            }
            if ($this->hasIndex('follow_up_customer_account', 'idx_follow_up_customer_preview_by')) {
                $table->dropIndex('idx_follow_up_customer_preview_by');
            }
            if (Schema::hasColumn('follow_up_customer_account', 'preview_until')) {
                $table->dropColumn('preview_until');
            }
            if (Schema::hasColumn('follow_up_customer_account', 'preview_by_id')) {
                $table->dropColumn('preview_by_id');
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
};