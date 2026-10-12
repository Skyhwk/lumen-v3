<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcement')) {
            return;
        }

        if (!Schema::hasColumn('announcement', 'title')) {
            Schema::table('announcement', function (Blueprint $table) {
                $table->string('title', 255)->nullable();
            });
        }

        if (!Schema::hasColumn('announcement', 'receiver_category')) {
            Schema::table('announcement', function (Blueprint $table) {
                $table->enum('receiver_category', ['Divisi', 'Grade', 'Status', 'Karyawan', 'All'])
                    ->default('All');
            });
        }

        if (
            Schema::hasColumn('announcement', 'delivery')
            && !Schema::hasColumn('announcement', 'receiver_ids')
        ) {
            $this->renameColumn('delivery', 'receiver_ids');
        }

        if (!Schema::hasColumn('announcement', 'attachments')) {
            Schema::table('announcement', function (Blueprint $table) {
                $table->json('attachments')->nullable();
            });
        }

        if (!Schema::hasColumn('announcement', 'deleted_at')) {
            Schema::table('announcement', function (Blueprint $table) {
                $table->timestamp('deleted_at')->nullable();
            });
        }

        $this->reorderColumns();
    }

    public function down(): void
    {
        if (!Schema::hasTable('announcement')) {
            return;
        }

        Schema::table('announcement', function (Blueprint $table) {
            if (Schema::hasColumn('announcement', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
            if (Schema::hasColumn('announcement', 'attachments')) {
                $table->dropColumn('attachments');
            }
        });

        if (
            Schema::hasColumn('announcement', 'receiver_ids')
            && !Schema::hasColumn('announcement', 'delivery')
        ) {
            $this->renameColumn('receiver_ids', 'delivery');
        }

        Schema::table('announcement', function (Blueprint $table) {
            if (Schema::hasColumn('announcement', 'receiver_category')) {
                $table->dropColumn('receiver_category');
            }
            if (Schema::hasColumn('announcement', 'title')) {
                $table->dropColumn('title');
            }
        });
    }

    private function reorderColumns(): void
    {
        $sequence = [
            'title',
            'description',
            'filename',
            'attachments',
            'receiver_category',
            'receiver_ids',
            'is_read',
            'is_deleted',
            'created_by',
            'created_at',
            'updated_by',
            'updated_at',
            'deleted_at',
        ];

        $after = 'id';
        foreach ($sequence as $column) {
            if (!Schema::hasColumn('announcement', $column)) {
                continue;
            }

            $this->moveColumnAfter($column, $after);
            $after = $column;
        }
    }

    private function renameColumn(string $from, string $to): void
    {
        $definition = $this->columnDefinition($from);
        DB::statement(sprintf(
            'ALTER TABLE `announcement` CHANGE `%s` `%s` %s',
            $from,
            $to,
            $definition
        ));
    }

    private function moveColumnAfter(string $column, string $after): void
    {
        $definition = $this->columnDefinition($column);
        DB::statement(sprintf(
            'ALTER TABLE `announcement` MODIFY `%s` %s AFTER `%s`',
            $column,
            $definition,
            $after
        ));
    }

    private function columnDefinition(string $column): string
    {
        $meta = DB::selectOne("SHOW COLUMNS FROM `announcement` WHERE Field = '{$column}'");
        $sql = $meta->Type;
        $sql .= strtoupper((string) $meta->Null) === 'YES' ? ' NULL' : ' NOT NULL';

        if ($meta->Default !== null) {
            $default = (string) $meta->Default;
            $sql .= strtoupper($default) === 'CURRENT_TIMESTAMP'
                ? ' DEFAULT CURRENT_TIMESTAMP'
                : ' DEFAULT ' . DB::getPdo()->quote($default);
        }

        if (!empty($meta->Extra)) {
            $sql .= ' ' . $meta->Extra;
        }

        return $sql;
    }
};
