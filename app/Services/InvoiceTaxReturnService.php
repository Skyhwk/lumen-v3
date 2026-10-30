<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceTaxReturn;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceTaxReturnService
{
    /** The existing invoice screens use MAX(pelunasan), not SUM of repeated order rows. */
    public function eligibleInvoices(string $search)
    {
        $query = Invoice::where('is_active', true)->where(function ($query) use ($search) {
            $query->where('no_invoice', 'like', '%' . $search . '%')->orWhere('nama_perusahaan', 'like', '%' . $search . '%');
        })->select('no_invoice')->groupBy('no_invoice')
            ->havingRaw('MAX(nilai_pelunasan) <> 0')
            ->havingRaw("MAX(CASE WHEN TRIM(COALESCE(faktur_pajak, '')) <> '' THEN 1 ELSE 0 END) = 1")
            ->orderByDesc('no_invoice')->limit(20)->get();
        return $query->map(function ($row) {
            try {
                return $this->snapshot(Invoice::where('no_invoice', $row->no_invoice)->where('is_active', true)->orderBy('id')->get());
            } catch (ValidationException $exception) {
                return null;
            }
        })->filter()->values();
    }

    public function createDraft(array $input, int $actorId, string $actorName): InvoiceTaxReturn
    {
        if ($actorId < 1) throw ValidationException::withMessages(['user' => ['Pengguna tidak valid.']]);
        return DB::transaction(function () use ($input, $actorId, $actorName) {
            $dpp = $this->cents($input['dpp']);
            $ppn = $this->cents($input['ppn']);
            if ($dpp <= 0 || $ppn < 0) throw ValidationException::withMessages(['amount' => ['DPP harus lebih dari nol dan PPN tidak boleh negatif.']]);
            $amount = $dpp + $ppn;
            // Serialize drafts for all order rows belonging to the same invoice.
            $rows = Invoice::where('no_invoice', $input['no_invoice'])->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            $existing = InvoiceTaxReturn::where('request_key', $input['request_key'])->first();
            if ($existing) {
                if ((int) $existing->created_by !== $actorId || $existing->no_invoice !== $input['no_invoice']
                    || $this->cents($existing->dpp) !== $dpp || $this->cents($existing->ppn) !== $ppn
                    || (string) $existing->return_date !== $input['return_date'] || $existing->reason !== trim($input['reason'])) {
                    throw ValidationException::withMessages(['request_key' => ['Referensi permintaan sudah digunakan.']]);
                }
                return $existing;
            }
            $snapshot = $this->snapshot($rows);
            if ($amount <= 0 || $amount > $this->cents($snapshot['remaining_amount'])) {
                throw ValidationException::withMessages(['amount' => ['Nilai retur harus lebih dari nol dan tidak melebihi sisa nilai invoice yang dapat diretur.']]);
            }
            $date = Carbon::createFromFormat('!Y-m-d', $input['return_date'], 'Asia/Jakarta');
            if (!$date || $date->toDateString() !== $input['return_date'] || $date->gt(Carbon::now('Asia/Jakarta')->startOfDay())) {
                throw ValidationException::withMessages(['return_date' => ['Tanggal retur tidak valid atau melebihi hari ini.']]);
            }
            $reason = trim($input['reason']);
            if ($reason === '') throw ValidationException::withMessages(['reason' => ['Alasan retur wajib diisi.']]);
            return InvoiceTaxReturn::create([
                'no_invoice' => $snapshot['no_invoice'], 'faktur_pajak' => $snapshot['faktur_pajak'],
                'nama_perusahaan' => $snapshot['nama_perusahaan'], 'return_date' => $input['return_date'],
                'dpp' => number_format($dpp / 100, 2, '.', ''), 'ppn' => number_format($ppn / 100, 2, '.', ''),
                'amount' => number_format($amount / 100, 2, '.', ''), 'amount_basis' => 'dpp_plus_ppn',
                'reason' => $reason, 'status' => 'draft', 'request_key' => $input['request_key'],
                'created_by' => $actorId, 'created_by_name' => $actorName, 'invoice_snapshot' => $snapshot,
                'meta_history' => [['type' => 'draft_created', 'recorded_at' => Carbon::now('Asia/Jakarta')->toDateTimeString(),
                    'actor_id' => $actorId, 'actor' => $actorName, 'amount' => number_format($amount / 100, 2, '.', ''),
                    'dpp' => number_format($dpp / 100, 2, '.', ''), 'ppn' => number_format($ppn / 100, 2, '.', ''),
                    'return_date' => $input['return_date'], 'reason' => $reason, 'status' => 'draft']],
            ]);
        });
    }

    private function snapshot($rows): array
    {
        $fakturs = $rows->pluck('faktur_pajak')->map(function ($value) { return trim((string) $value); })->filter()->unique()->values();
        $paid = $rows->max('nilai_pelunasan');
        if ($rows->isEmpty() || $fakturs->count() !== 1 || (float) $paid == 0) {
            throw ValidationException::withMessages(['no_invoice' => ['Invoice harus aktif, memiliki satu faktur_pajak yang konsisten, dan nilai_pelunasan tidak sama dengan nol.']]);
        }
        $invoice = $rows->first()->no_invoice;
        $total = $rows->sum(function ($row) { return $this->cents($row->nilai_tagihan); });
        $reserved = InvoiceTaxReturn::where('no_invoice', $invoice)->whereIn('status', ['draft', 'submitted', 'approved'])
            ->get(['amount'])->sum(function ($row) { return $this->cents($row->amount); });
        return ['no_invoice' => $invoice, 'faktur_pajak' => $fakturs->first(),
            'nama_perusahaan' => $rows->first()->nama_perusahaan, 'nilai_pelunasan' => $paid,
            'nilai_tagihan' => number_format($total / 100, 2, '.', ''),
            'reserved_amount' => number_format($reserved / 100, 2, '.', ''),
            'remaining_amount' => number_format(max(0, $total - $reserved) / 100, 2, '.', ''),
            'orders' => $rows->pluck('no_order')->filter()->unique()->values()->all(),
            'invoice_ids' => $rows->pluck('id')->all()];
    }

    private function cents($amount): int
    {
        $value = (string) ($amount ?? '0');
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $value)) {
            throw ValidationException::withMessages(['amount' => ['Nominal harus berupa angka dengan maksimal dua desimal.']]);
        }
        return (int) round((float) $value * 100);
    }
}
