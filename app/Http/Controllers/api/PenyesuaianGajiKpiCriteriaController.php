<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\SalaryAdjustmentKpiCriteria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class PenyesuaianGajiKpiCriteriaController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::connection('mysql')
            ->table('salary_adjustment_kpi_criteria')
            ->orderBy('criteria_no');

        return Datatables::of($query)
            ->addColumn('is_active_label', fn ($row) => $row->is_active ? 'Aktif' : 'Nonaktif')
            ->make(true);
    }

    public function summary(Request $request)
    {
        $active = SalaryAdjustmentKpiCriteria::where('is_active', true)->get();
        $totalWeight = round($active->sum('weight_pct'), 2);

        return response()->json([
            'success' => true,
            'data' => [
                'active_count' => $active->count(),
                'total_weight_pct' => $totalWeight,
                'is_valid' => abs($totalWeight - 100) < 0.01,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $record = SalaryAdjustmentKpiCriteria::find((int) $request->id);
        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Kriteria tidak ditemukan'], 404);
        }

        $criteriaName = trim((string) ($request->criteria_name ?? ''));
        $indicatorText = trim((string) ($request->indicator_text ?? ''));
        $weightPct = (float) ($request->weight_pct ?? 0);

        if ($criteriaName === '' || $indicatorText === '') {
            return response()->json(['success' => false, 'message' => 'Nama kriteria dan indikator wajib diisi'], 422);
        }

        if ($weightPct <= 0 || $weightPct > 100) {
            return response()->json(['success' => false, 'message' => 'Bobot harus antara 0 dan 100'], 422);
        }

        $record->criteria_name = $criteriaName;
        $record->indicator_text = $indicatorText;
        $record->weight_pct = $weightPct;
        if ($request->has('is_active')) {
            $record->is_active = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
        }
        $record->save();

        return response()->json([
            'success' => true,
            'message' => 'Kriteria KPI berhasil diperbarui',
        ]);
    }

    public function toggleActive(Request $request)
    {
        $record = SalaryAdjustmentKpiCriteria::find((int) $request->id);
        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Kriteria tidak ditemukan'], 404);
        }

        $record->is_active = !$record->is_active;
        $record->save();

        return response()->json([
            'success' => true,
            'message' => 'Status kriteria berhasil diubah',
            'data' => ['is_active' => $record->is_active],
        ]);
    }
}
