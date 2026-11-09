<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\QuotationAutoLinkOpens;
use App\Services\GetBawahan;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class FollowUpQuoatationAutoController extends Controller
{
    public function index(Request $request)
    {
        $data = QuotationAutoLinkOpens::query()
            ->select('quotation_auto_link_opens.*')
            ->with('quotation');

        $processed = (int) $request->input('processed', 0);
        $data->where('quotation_auto_link_opens.is_processed', $processed);
        $this->limitToViewerSales($data);

        return DataTables::of($data)
            ->filterColumn('last_opened_at', function ($query, $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }

                $date = strlen($keyword) >= 10 ? substr($keyword, 0, 10) : $keyword;
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    return;
                }

                $query->whereDate('quotation_auto_link_opens.last_opened_at', $date);
            })
            ->make(true);
    }

    public function update(Request $request, $id)
    {
        $data = QuotationAutoLinkOpens::query()->whereKey($id);
        $this->limitToViewerSales($data);
        $data = $data->first();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data not found',
            ], 404);
        }

        $data->is_processed = (int) $request->input('processed', 1) === 1;
        $data->save();
        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully',
        ], 200);
    }

    private function limitToViewerSales($query): void
    {
        $salesIds = $this->viewerSalesIds();
        if ($salesIds === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('quotation', function ($quotation) use ($salesIds) {
            $quotation->whereIn('sales_id', $salesIds);
        });
    }

    private function viewerSalesIds(): array
    {
        $userId = (int) $this->user_id;
        if ($userId <= 0) {
            return [];
        }

        if (!$this->isAtasanGrade()) {
            return [$userId];
        }

        return GetBawahan::where('id', $userId)
            ->get()
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isAtasanGrade(): bool
    {
        $grade = strtoupper(trim((string) $this->grade));

        return in_array($grade, ['SUPERVISOR', 'MANAGER', 'SENIOR MANAGER', 'SPV'], true);
    }
}
