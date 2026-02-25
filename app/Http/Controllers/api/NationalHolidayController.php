<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\NationalHoliday;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class NationalHolidayController extends Controller
{
    public function run(Request $request)
    {
        $year = (int) ($request->year ?? Carbon::now()->year);

        // optional: sync 2 tahun sekaligus biar aman buat recurring yang nyebrang tahun
        $includeNextYear = (int) ($request->include_next_year ?? 1); // default 1

        $yearsToSync = [$year];
        if ($includeNextYear === 1) {
            $yearsToSync[] = $year + 1;
        }

        $inserted = 0;
        $updated  = 0;
        $failedYears = [];

        DB::beginTransaction();
        try {
            foreach ($yearsToSync as $y) {
                $url = "https://date.nager.at/api/v3/PublicHolidays/{$y}/ID";

                $res = Http::timeout(15)->get($url);

                if (!$res->ok()) {
                    $failedYears[] = $y;
                    continue;
                }

                $rows = collect($res->json())
                    ->map(function ($h) {
                        // Nager biasanya punya: date, localName, name, countryCode, fixed, global, counties, launchYear, types
                        return [
                            'date'        => $h['date'], // YYYY-MM-DD
                            'name'        => $h['localName'] ?? ($h['name'] ?? 'Holiday'),
                            'type'        => "NATIONAL",
                            'created_at'  => Carbon::now(),
                        ];
                    })
                    ->values()
                    ->toArray();

                // Upsert by unique(date)
                // Laravel 8+ support upsert
                // Return count detail gak selalu akurat per row, jadi kita hitung manual via exists check dulu (optional).
                // Di sini: kita hitung kasar dengan cek existing dates.
                $dates = array_column($rows, 'date');
                $existingDates = NationalHoliday::whereIn('date', $dates)->pluck('date')->toArray();

                $existingMap = array_flip($existingDates);
                foreach ($rows as $r) {
                    if (isset($existingMap[$r['date']])) $updated++;
                    else $inserted++;
                }

                NationalHoliday::upsert(
                    $rows,
                    ['date'],                 // unique key
                    ['name', 'type', 'created_at', 'updated_at'] // columns to update
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Holiday sync completed',
                'data' => [
                    'years' => $yearsToSync,
                    'inserted' => $inserted,
                    'updated' => $updated,
                    'failed_years' => $failedYears,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Holiday sync failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}