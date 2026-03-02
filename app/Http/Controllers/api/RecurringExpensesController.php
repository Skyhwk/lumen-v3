<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Jobs\RenderSamplingPlan;
use App\Services\JadwalServices;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\Email;
use App\Jobs\RenderAndEmailJadwal;
use App\Models\NationalHoliday;
use App\Models\RecurringDetails;
use App\Models\RecurringExpenses;
use App\Services\RenderSamplingPlan as RenderSamplingPlanService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RecurringExpensesController extends Controller
{
    public function index(Request $request)
    {
        $data = RecurringExpenses::query()
            ->with('details')
            ->where('is_active', 1);

        return Datatables::of($data)->make(true);
    }

    /**
     * Create recurring header (recurring_expenses)
     */
    public function store(Request $request)
    {

        $batchId = str_replace('.', '/', microtime(true));
        return DB::transaction(function () use ($request, $batchId) {
            $unit = $request['recurrence_unit'];
            $value = (int)($request['recurrence_value'] ?? 1);

            $startDate = Carbon::parse($request['start_date'] ?? Carbon::now()->toDateString())->startOfDay();

            // due_day hanya untuk MONTH, selain itu null-in
            $dueDay = null;
            if ($unit === 'MONTH') {
                $dueDay = isset($request['due_day']) ? (int)$request['due_day'] : (int)$startDate->day;
                $dueDay = max(1, min(31, $dueDay));
            }

            $nextDue = $this->calculateFirstDueDate(
                $startDate,
                $unit,
                $value,
                $dueDay
            );

            $aliasNextDue = $this->adjustAliasDueDate($nextDue);

            $header = RecurringExpenses::create([
                'batch_id'         => $batchId,
                'virtual_account'  => $request['virtual_account'] ?? null,
                'vendor'           => $request['vendor'] ?? null,
                'receiver_name'    => $request['receiver_name'] ?? null,
                'bank_name'        => $request['bank_name'] ?? null,
                'keterangan'       => $request['keterangan'] ?? null,
                'amount'           => $request['amount'] ?? 0,

                'recurrence_unit'  => $unit,
                'recurrence_value' => $value,
                'due_day'          => $dueDay,

                'start_date'       => $startDate->toDateString(),
                'next_due_date'    => $nextDue->toDateString(),
                'alias_next_due_date' => $aliasNextDue->toDateString(),
                'last_payment_at'  => null,
                'is_active'        => (int)($request['is_active'] ?? 1),
            ]);

            return response()->json([
                'message' => 'Recurring expense created',
                'data'    => $header->load('details'),
            ], 201);
        });
    }

    /**
     * Update recurring header (recurring_expenses)
     */
    public function update(Request $request)
    {

        return DB::transaction(function () use ($request) {

            $header = RecurringExpenses::where('batch_id', $request['batch_id'])->update(
                [
                    'virtual_account'  => $request['virtual_account'] ?? null,
                    'vendor'           => $request['vendor'] ?? null,
                    'receiver_name'    => $request['receiver_name'] ?? null,
                    'keterangan'       => $request['keterangan'] ?? null,
                    'amount'           => $request['amount'] ?? 0,
                ]
            );

            return response()->json([
                'message' => 'Recurring expense updated',
            ], 201);
        });
    }

    /**
     * Create payment detail (recurring_details) + update header dates
     */
    public function storePayment(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $header = RecurringExpenses::lockForUpdate()->findOrFail($request['recurring_expense_id']);

            $paidAt = Carbon::parse($request['paid_at']);
            $paidDate = $paidAt->copy()->startOfDay();
            if ($request->hasFile('file')) {
                $file = $request->file('file');

                if (!$file->isValid()) {
                    return response()->json(['message' => 'File upload tidak valid'], 422);
                }

                // optional: limit size (mis. 5MB)
                $maxBytes = 5 * 1024 * 1024;
                if ($file->getSize() > $maxBytes) {
                    return response()->json(['message' => 'Ukuran file maksimal 5MB'], 422);
                }

                // optional: whitelist ext
                $ext = strtolower($file->getClientOriginalExtension() ?? '');
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                $folder = public_path('rucurring_payments');
                if (!file_exists($folder)) {
                    mkdir($folder, 0777, true);
                }
                if (!in_array($ext, $allowed, true)) {
                    return response()->json(['message' => 'Format file harus JPG/PNG/PDF'], 422);
                }

                // generate safe unique name
                $safeName = 'payment_' . $header->id . '_' . Carbon::now()->format('YmdHis') . '_' . Str::random(6) . '.' . $ext;

                // simpan ke storage/app/public/recurring_payments
                // pastiin: php artisan storage:link
                $file->move($folder, $safeName);
            }
            // insert detail (riwayat bayar)
            $detail = RecurringDetails::create([
                'recurring_expense_id' => $header->id,
                'paid_at'              => $paidAt->format('Y-m-d H:i:s'),
                'paid_amount'          => $request['paid_amount'],
                'paid_by'              => $request['paid_by'],
                'payment_reference'    => $request['payment_reference'] ?? null,
                'notes'                => $request['notes'] ?? null,
                'filename'             => $safeName ?? null,
                'created_at'           => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by'           => $this->karyawan ?? null,
            ]);

            // anchor untuk hitung next: prefer pakai next_due_date yg lagi aktif
            $unit  = $header->recurrence_unit;
            $value = (int) ($header->recurrence_value ?? 1);

            // ✅ anchor jadwal: selalu pakai next_due_date yg sedang aktif
            $anchor = $header->next_due_date
                ? Carbon::parse($header->next_due_date)->startOfDay()
                : Carbon::parse($header->start_date)->startOfDay();

            // due_day hanya untuk MONTH
            $dueDay = ($unit === 'MONTH')
                ? (int) ($header->due_day ?? $anchor->day)
                : null;

            // ✅ next_due_date = jadwal + interval (paten)
            $nextDue = $this->calculateNextDueDate(
                Carbon::parse($header->start_date ?? $anchor->toDateString())->startOfDay(),
                $unit,
                $value,
                $dueDay,
                $anchor,
                // ✅ hanya MONTH yang butuh flag ini, tapi boleh juga false untuk non-month (gak ngaruh)
                ($unit === 'MONTH')
            );

            $aliasNextDue = $this->adjustAliasDueDate($nextDue);

            $header->last_payment_at      = $paidDate->toDateString();
            $header->next_due_date        = $nextDue->toDateString();
            $header->alias_next_due_date  = $aliasNextDue->toDateString();
            $header->save();

            return response()->json([
                'message' => 'Payment created & header updated',
                'detail'  => $detail,
                'header'  => $header->fresh()->load('details'),
            ], 201);
        });
    }



    /**
     * Kalkulasi next due date
     *
     * - MONTH: pakai dueDay (1-31) dan clamp ke last day of month
     * - DAY/WEEK/YEAR: based on anchorDate (+value)
     */
    private function calculateNextDueDate(
        $startDate,
        $unit,
        $value,
        $dueDay,
        $anchorDate,
        $moveForwardOneCycle = false
    ): Carbon {
        $value = max(1, (int)$value);

        if ($unit === 'MONTH') {
            $dueDay = max(1, min(31, (int)($dueDay ?? $startDate->day)));

            // Determine target month:
            // - for create: stay in same month if due day >= anchor day, else next month
            // - for payment: always move forward one cycle (add $value months)
            $base = $anchorDate->copy();
            if ($moveForwardOneCycle) {
                $base->addMonthsNoOverflow($value);
            } else {
                // first occurrence after/at anchor
                $candidate = $this->setDayClamped($base->copy(), $dueDay);
                if ($candidate->lt($anchorDate)) {
                    $base->addMonthsNoOverflow(1);
                }
            }

            return $this->setDayClamped($base, $dueDay);
        }

        if ($unit === 'WEEK') {
            return $anchorDate->copy()->addWeeks($value);
        }

        if ($unit === 'YEAR') {
            return $anchorDate->copy()->addYears($value);
        }

        // DAY default
        return $anchorDate->copy()->addDays($value);
    }

    /**
     * Set day of month; if day overflow (e.g. 31 in Feb), clamp to last day.
     */
    private function setDayClamped($date,$day): Carbon
    {
        $day = max(1, min(31, $day));
        $lastDay = $date->copy()->endOfMonth()->day;
        return $date->copy()->day(min($day, $lastDay));
    }

    private function adjustAliasDueDate($date): Carbon
    {
        $d = $date->copy()->startOfDay();

        // safety guard biar gak infinite loop
        $guard = 0;

        while (($this->isWeekend($d) || $this->isHolidayIndonesia($d)) && $guard < 370) {
            $d->subDay(); // mundur sehari
            $guard++;
        }

        return $d;
    }

    private function isHolidayIndonesia($date): bool
    {
        return NationalHoliday::whereDate('date', $date->toDateString())
            ->where('type', 'NATIONAL') // kalau mau include cuti bersama: ->whereIn('type',['NATIONAL','CUTI_BERSAMA'])
            ->exists();
    }

    private function isWeekend($date): bool
    {
        return $date->isSaturday() || $date->isSunday();
    }

    private function calculateFirstDueDate(
        $startDate,
        $unit,
        $value,
        $dueDay
    ): Carbon {
        if ($unit === 'MONTH') {
            $day = max(1, min(31, (int)($dueDay ?? $startDate->day)));
            return $this->setDayClamped($startDate->copy(), $day);
        }

        // WEEK/DAY/YEAR: first due = start_date itu sendiri
        return $startDate->copy()->startOfDay();
    }

    public function getCountRecurringExpenses()
    {
        $today = Carbon::today();

        $targetDates = [
            $today->copy()->addDays(1)->toDateString(),
            $today->copy()->addDays(3)->toDateString(),
            $today->copy()->addDays(5)->toDateString(),
            $today->copy()->addDays(7)->toDateString(),
        ];

        $data = RecurringExpenses::where('is_active', 1)
            ->whereIn('alias_next_due_date', $targetDates)
            ->orderBy('alias_next_due_date')
            ->get();

        return response()->json([
            'data' => $data
        ]);
    }

    public function bankList()
    {
        $data = RecurringExpenses::all()->pluck('bank_name')->unique()->toArray();

        return response()->json([
            'data' => $data
        ]);
    }
}
