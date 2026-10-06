<?php

namespace App\Support\Greatday;

/**
 * Uji console: alihkan penerima notif (opsi --redirect-to), bukan lewat .env.
 */
final class AttendanceReminderDevLimit
{
    public static function resolveRedirectTo(?int $cliRedirectTo): ?int
    {
        if (app()->environment('production')) {
            return null;
        }

        $id = (int) $cliRedirectTo;

        return $id > 0 ? $id : null;
    }

    /**
     * @param  list<int>|null  $cliIds
     * @return list<int>|null
     */
    public static function resolveScanIds(?array $cliIds): ?array
    {
        return $cliIds === [] ? null : $cliIds;
    }

    /**
     * @param  list<array<string, mixed>>  $reminders
     * @return list<array<string, mixed>>
     */
    public static function filterReminders(array $reminders): array
    {
        return $reminders;
    }
}
