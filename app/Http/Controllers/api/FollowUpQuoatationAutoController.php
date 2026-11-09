<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\QuotationAutoLinkOpens;
use App\Services\GetBawahan;
use App\Services\SalesTeamHierarchyService;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class FollowUpQuoatationAutoController extends Controller
{
    public function index(Request $request)
    {
        $isSalesOrCRO = $this->isSalesOrCRO();
        $data = QuotationAutoLinkOpens::query()
            ->select('quotation_auto_link_opens.*')
            ->with(['quotation.sales']);

        $processed = (int) $request->input('processed', 0);
        $data->where('quotation_auto_link_opens.is_processed', $processed);

        if ($isSalesOrCRO) {
            $this->limitToViewerSales($data);
        }

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
            ->addColumn('nama_sales', function ($row) {
                return $row->quotation->sales->nama_lengkap ?? null;
            })
            ->filterColumn('nama_sales', function ($query, $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }

                $query->whereHas('quotation.sales', function ($sales) use ($keyword) {
                    $sales->where('nama_lengkap', 'like', '%' . $keyword . '%');
                });
            })
            ->orderColumn('nama_sales', function ($query, $order) {
                $direction = strtolower((string) $order) === 'desc' ? 'desc' : 'asc';
                $query->orderByRaw(
                    '(select master_karyawan.nama_lengkap from request_quotation inner join master_karyawan on master_karyawan.id = request_quotation.sales_id where request_quotation.id = quotation_auto_link_opens.quotation_id limit 1) ' . $direction
                );
            })
            ->make(true);
    }

    public function update(Request $request, $id)
    {
        $data = QuotationAutoLinkOpens::query()->whereKey($id);
        if ($this->isSalesOrCRO()) {
            $this->limitToViewerSales($data);
        }
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
        if (in_array($grade, ['SUPERVISOR', 'MANAGER', 'SENIOR MANAGER', 'SPV'], true)) {
            return true;
        }

        $userId = (int) $this->user_id;
        if ($userId <= 0) {
            return false;
        }

        $jabatanId = (int) MasterKaryawan::query()->whereKey($userId)->value('id_jabatan');

        return in_array($jabatanId, [15, 21, 154, 157], true);
    }

    private function isSalesOrCRO(): bool
    {
        $userId = (int) $this->user_id;
        if ($userId <= 0) {
            return false;
        }

        $employee = MasterKaryawan::query()->find($userId);

        $jabatanId = (int) optional($employee)->id_jabatan;

        if (!$employee) {
            return false;
        }

        return app(SalesTeamHierarchyService::class)->isSalesDivisionMember($employee) || $jabatanId === 148 || $jabatanId === 157;
    }
}
