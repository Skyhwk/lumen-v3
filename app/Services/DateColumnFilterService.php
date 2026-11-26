<?php

namespace App\Services;

use Closure;

class DateColumnFilterService
{
    /**
     * Apply date / month column filter to a query builder.
     *
     * Accepted keywords:
     * - YYYY-MM-DD → exact date
     * - YYYY-MM    → whole month
     * - other      → optional LIKE fallback
     */
    public static function apply($query, string $column, $keyword, array $options = []): void
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return;
        }

        $columnSql = self::quoteColumn($column);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $keyword)) {
            $query->whereDate($column, $keyword);
            return;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $keyword)) {
            $query->whereRaw("DATE_FORMAT({$columnSql}, '%Y-%m') = ?", [$keyword]);
            return;
        }

        if (($options['likeFallback'] ?? true) === false) {
            return;
        }

        $query->whereRaw('LOWER(' . $columnSql . ') LIKE ?', ['%' . strtolower($keyword) . '%']);
    }

    /**
     * Closure ready for Yajra DataTables filterColumn().
     */
    public static function datatablesFilter(string $column, array $options = []): Closure
    {
        return function ($query, $keyword) use ($column, $options) {
            self::apply($query, $column, $keyword, $options);
        };
    }

    private static function quoteColumn(string $column): string
    {
        if (!preg_match('/^[A-Za-z0-9_\.]+$/', $column)) {
            throw new \InvalidArgumentException('Invalid SQL column for date filter.');
        }

        return $column;
    }
}
