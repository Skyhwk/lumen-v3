<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\ContractExpiryReminderService;

class ContractExpiryReminderController extends Controller
{
    public function index()
    {
        $rows = app(ContractExpiryReminderService::class)
            ->forManager((int) $this->user_id, $this->grade);

        $expired = 0;
        $today = 0;
        $upcoming = 0;

        foreach ($rows as $row) {
            if (($row['days_left'] ?? 0) < 0) {
                $expired++;
            } elseif (($row['days_left'] ?? 0) === 0) {
                $today++;
            } else {
                $upcoming++;
            }
        }

        return response()->json([
            'message' => 'get contract expiry reminder success',
            'data' => $rows,
            'summary' => [
                'total' => count($rows),
                'expired' => $expired,
                'today' => $today,
                'upcoming' => $upcoming,
                'window_days' => ContractExpiryReminderService::WINDOW_DAYS,
            ],
        ]);
    }
}
