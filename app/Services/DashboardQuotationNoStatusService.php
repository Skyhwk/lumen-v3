<?php

namespace App\Services;

use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardQuotationNoStatusService
{
    public function resolveStatus($quote): string
    {
        $flag = strtolower(trim((string) $quote->flag_status));
        $status = strtolower(trim((string) $quote->status_quotation));

        if ($flag === 'ordered') {
            return 'ordered';
        }

        if ($flag === 'void') {
            return 'void';
        }

        if (in_array($status, ['cold', 'warm', 'hot'], true)) {
            return $status;
        }

        return 'no_status';
    }

    /**
     * @param  int[]|null  $salesIds  null = semua sales (filter SMS mode all)
     */
    public function list(?array $salesIds, Carbon $startDate, Carbon $endDate): array
    {
        return collect([QuotationNonKontrak::class, QuotationKontrakH::class])
            ->flatMap(function ($model) use ($salesIds, $startDate, $endDate) {
                return $model::query()
                    ->with([
                        'pelanggan:id_pelanggan,nama_pelanggan',
                        'sales:id,nama_lengkap',
                    ])
                    ->where('is_active', 1)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->when($salesIds !== null, fn ($query) => $query->whereIn('sales_id', $salesIds))
                    ->get(['no_document', 'pelanggan_ID', 'flag_status', 'status_quotation', 'sales_id']);
            })
            ->filter(fn ($quote) => $this->resolveStatus($quote) === 'no_status')
            ->unique('no_document')
            ->sortBy('no_document')
            ->values()
            ->map(fn ($quote) => [
                'no_document' => $quote->no_document,
                'nama_perusahaan' => optional($quote->pelanggan)->nama_pelanggan ?: '-',
                'sales_penanggung_jawab' => optional($quote->sales)->nama_lengkap ?: '-',
            ])
            ->all();
    }

    public function datatable(Request $request, ?array $salesIds, Carbon $startDate, Carbon $endDate): array
    {
        $rows = collect($this->list($salesIds, $startDate, $endDate));
        $recordsTotal = $rows->count();

        $globalSearch = strtolower(trim((string) $request->input('search.value', '')));
        if ($globalSearch !== '') {
            $rows = $rows->filter(function (array $row) use ($globalSearch) {
                return $this->rowMatchesNeedle($row, $globalSearch);
            });
        }

        $columnFields = ['no_document', 'nama_perusahaan', 'sales_penanggung_jawab'];
        $columns = $request->input('columns', []);
        if (is_array($columns)) {
            foreach ($columns as $index => $column) {
                if (!is_array($column)) {
                    continue;
                }
                $columnSearch = trim((string) ($column['search']['value'] ?? ''));
                if ($columnSearch === '') {
                    continue;
                }
                $dataKey = $column['data'] ?? ($columnFields[$index] ?? null);
                if (!in_array($dataKey, $columnFields, true)) {
                    continue;
                }
                $needle = strtolower($columnSearch);
                $rows = $rows->filter(
                    fn (array $row) => str_contains(strtolower((string) ($row[$dataKey] ?? '')), $needle)
                );
            }
        }

        $recordsFiltered = $rows->count();

        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortField = $columnFields[$orderColumnIndex] ?? 'no_document';
        if (is_array($columns) && isset($columns[$orderColumnIndex]['data'])) {
            $orderDataKey = $columns[$orderColumnIndex]['data'];
            if (in_array($orderDataKey, $columnFields, true)) {
                $sortField = $orderDataKey;
            }
        }

        $rows = $orderDir === 'desc'
            ? $rows->sortByDesc($sortField, SORT_NATURAL | SORT_FLAG_CASE)
            : $rows->sortBy($sortField, SORT_NATURAL | SORT_FLAG_CASE);

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);

        if ($length > 0) {
            $rows = $rows->slice($start, $length)->values();
        } else {
            $rows = $rows->values();
        }

        return [
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->all(),
        ];
    }

    private function rowMatchesNeedle(array $row, string $needle): bool
    {
        foreach (['no_document', 'nama_perusahaan', 'sales_penanggung_jawab'] as $field) {
            if (str_contains(strtolower((string) ($row[$field] ?? '')), $needle)) {
                return true;
            }
        }

        return false;
    }
}
