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
use App\Models\RecurringDetails;
use App\Models\RecurringExpenses;
use App\Services\RenderSamplingPlan as RenderSamplingPlanService;
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

            $nextDue = $this->calculateNextDueDate(
                startDate: $startDate,
                unit: $unit,
                value: $value,
                dueDay: $dueDay,
                // untuk header baru, next_due_date = occurrence pertama setelah/di start_date
                anchorDate: $startDate
            );

            $header = RecurringExpenses::create([
                'batch_id'         => $batchId,
                'virtual_account'  => $request['virtual_account'] ?? null,
                'vendor'           => $request['vendor'] ?? null,
                'receiver_name'    => $request['receiver_name'] ?? null,
                'keterangan'       => $request['keterangan'] ?? null,
                'amount'           => $request['amount'] ?? 0,

                'recurrence_unit'  => $unit,
                'recurrence_value' => $value,
                'due_day'          => $dueDay,

                'start_date'       => $startDate->toDateString(),
                'next_due_date'    => $nextDue->toDateString(),
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
                'payment_method'       => $request['payment_method'] ?? null,
                'payment_reference'    => $request['payment_reference'] ?? null,
                'notes'                => $request['notes'] ?? null,
                'filename'             => $safeName ?? null,
                'created_at'           => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by'           => $this->karyawan ?? null,
            ]);

            // anchor untuk hitung next: prefer pakai next_due_date yg lagi aktif
            $anchor = $header->next_due_date
                ? Carbon::parse($header->next_due_date)->startOfDay()
                : $paidDate;

            $unit  = $header->recurrence_unit;
            $value = (int)($header->recurrence_value ?? 1);

            // kalau bukan MONTH, due_day harus null (permintaan lu)
            $dueDay = ($unit === 'MONTH') ? (int)($header->due_day ?? $anchor->day) : null;
            if ($unit !== 'MONTH') {
                $header->due_day = null;
            }

            // next_due_date = anchor + interval
            $nextDue = $this->calculateNextDueDate(
                startDate: Carbon::parse($header->start_date ?? $anchor->toDateString())->startOfDay(),
                unit: $unit,
                value: $value,
                dueDay: $dueDay,
                anchorDate: $anchor,
                // untuk payment: maju 1 cycle dari anchor
                moveForwardOneCycle: true
            );

            $header->last_payment_at = $paidDate->toDateString();
            $header->next_due_date   = $nextDue->toDateString();
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
        Carbon $startDate,
        string $unit,
        int $value,
        ?int $dueDay,
        Carbon $anchorDate,
        bool $moveForwardOneCycle = false
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
    private function setDayClamped(Carbon $date, int $day): Carbon
    {
        $day = max(1, min(31, $day));
        $lastDay = $date->copy()->endOfMonth()->day;
        return $date->copy()->day(min($day, $lastDay));
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
            ->whereIn('next_due_date', $targetDates)
            ->orderBy('next_due_date')
            ->get();

        return response()->json([
            'data' => $data
        ]);
    }
}
