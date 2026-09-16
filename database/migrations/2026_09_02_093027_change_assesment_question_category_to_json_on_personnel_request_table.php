<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'personnel_requests';
    private const COLUMN = 'assesment_question_category';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (!Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        if ($this->columnTypeIs(self::TABLE, self::COLUMN, 'json')) {
            return;
        }

        DB::statement(
            'ALTER TABLE `' . self::TABLE . '` MODIFY `' . self::COLUMN . '` JSON NULL'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (!Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        if ($this->columnTypeIs(self::TABLE, self::COLUMN, 'varchar')) {
            return;
        }

        DB::statement(
            'ALTER TABLE `' . self::TABLE . '` MODIFY `' . self::COLUMN . '` VARCHAR(255) NULL'
        );
    }

    private function columnTypeIs(string $table, string $column, string $expectedType): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$database, $table, $column]
        );

        if (empty($result)) {
            return false;
        }

        return strtolower((string) ($result[0]->DATA_TYPE ?? '')) === strtolower($expectedType);
    }
};
