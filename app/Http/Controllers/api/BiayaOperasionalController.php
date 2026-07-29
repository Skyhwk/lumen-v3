<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\{BiayaOperasional, BiayaOperasionalItem, BiayaOperasionalReceipt, MasterKaryawan};
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mpdf;

class BiayaOperasionalController extends Controller
{
    private const ROMAN_MONTHS = [
        '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IV',
        '05' => 'V', '06' => 'VI', '07' => 'VII', '08' => 'VIII',
        '09' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII',
    ];

    private const RECEIPT_DIR = 'biaya-operasional';

    public function initialize(Request $request)
    {
        $employees = MasterKaryawan::where('is_active', true)
            ->orderBy('nama_lengkap')
            ->limit(300)
            ->pluck('nama_lengkap')
            ->values();

        $destinations = BiayaOperasional::whereNotNull('destination')
            ->where('destination', '!=', '')
            ->pluck('destination')
            ->flatMap(fn($destination) => $this->parseDestinations($destination))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return response()->json([
            'data' => [
                'employee' => $request->attributes->get('user')->karyawan,
                'employees' => $employees,
                'destinations' => $destinations,
            ],
            'message' => 'BO initialized successfully',
        ], 200);
    }

    public function index(Request $request)
    {
        $scope = $request->input('scope', 'request');

        $query = BiayaOperasional::with(['items', 'receipts'])
            ->latest('created_at');

        switch ($scope) {
            case 'create':
            case 'request':
                $query->where('is_active', true)->where('status', 'requested');
                break;
            case 'ongoing':
            case 'transit':
                $query->where('is_active', true)->where('status', 'prepared');
                break;
            case 'completed':
                $query->where('is_active', true)->where('status', 'completed');
                break;
            case 'rekap':
                $query->where(function ($sub) {
                    $sub->where(function ($active) {
                        $active->where('is_active', true)->where('status', 'completed');
                    })->orWhere(function ($void) {
                        $void->where('is_active', false)->where('status', 'void');
                    });
                });
                break;
            case 'void':
                $query->where('is_active', false)->where('status', 'void');
                break;
            case 'approval':
                $query->where('is_active', true)->where('status', 'requested');
                break;
            case 'process':
                $query->where('is_active', true)->whereIn('status', ['approved', 'prepared']);
                break;
        }
        return DataTables::of($query)
            ->addColumn('needs_summary', fn($row) => $row->items->pluck('need_name')->join(', '))
            ->addColumn('display_status', fn($row) => $this->displayStatus($row->status))
            ->addColumn('total_prepared', fn($row) => $row->items->sum('prepared_amount'))
            ->addColumn('total_used', fn($row) => $row->items->sum('used_amount'))
            ->addColumn('balance', fn($row) => $row->items->sum('prepared_amount') - $row->items->sum('used_amount'))
            ->filterColumn('needs_summary', function ($query, $keyword) {
                $query->whereHas('items', fn($sub) => $sub->where('need_name', 'like', "%{$keyword}%"));
            })
            ->filterColumn('bo_number', fn($query, $keyword) => $query->where('bo_number', 'like', "%{$keyword}%"))
            ->filterColumn('person_in_charge', fn($query, $keyword) => $query->where('person_in_charge', 'like', "%{$keyword}%"))
            ->filterColumn('destination', fn($query, $keyword) => $query->where('destination', 'like', "%{$keyword}%"))
            ->filterColumn('created_by', fn($query, $keyword) => $query->where('created_by', 'like', "%{$keyword}%"))
            ->make(true);
    }

    public function save(Request $request)
    {
        $needs = $request->input('needs', []);
        if (!is_array($needs) || count(array_filter($needs)) < 1) {
            return response()->json(['message' => 'Checklist kebutuhan minimal 1'], 422);
        }

        $destinations = $this->parseDestinations($request->input('destination', []));
        if (!$request->person_in_charge || !count($destinations) || !$request->travel_date) {
            return response()->json(['message' => 'Penanggung jawab, tujuan, dan tanggal perjalanan wajib diisi'], 422);
        }

        DB::beginTransaction();
        try {
            $bo = BiayaOperasional::create([
                'bo_number' => $this->generateBoNumber(),
                'person_in_charge' => $request->person_in_charge,
                'destination' => json_encode(array_values(array_unique($destinations))),
                'travel_date' => $request->travel_date,
                'status' => 'requested',
                'is_active' => true,
                'created_by' => $this->karyawan,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            foreach (array_values(array_unique(array_filter($needs))) as $need) {
                BiayaOperasionalItem::create([
                    'bo_id' => $bo->id,
                    'need_name' => $need,
                    'created_by' => $this->karyawan,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            DB::commit();
            return response()->json(['data' => $bo, 'message' => 'Biaya operasional berhasil diajukan'], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function show(Request $request)
    {
        $bo = BiayaOperasional::with(['items', 'receipts'])->findOrFail($request->id);
        $bo->needs_summary = $bo->items->pluck('need_name')->join(', ');
        $bo->destination_text = $this->destinationText($bo->destination);
        $bo->display_status = $this->displayStatus($bo->status);
        $bo->total_prepared = $bo->items->sum('prepared_amount');
        $bo->total_used = $bo->items->sum('used_amount');
        $bo->balance = $bo->total_prepared - $bo->total_used;

        return response()->json(['data' => $bo, 'message' => 'Detail BO berhasil diambil'], 200);
    }

    public function approveBo(Request $request)
    {
        $bo = BiayaOperasional::findOrFail($request->id);
        if ($bo->status !== 'requested' || !$bo->is_active) {
            return response()->json(['message' => 'BO hanya bisa diapprove saat status requested'], 422);
        }

        $bo->status = 'approved';
        $bo->approved_by = $this->karyawan;
        $bo->approved_at = date('Y-m-d H:i:s');
        $bo->updated_by = $this->karyawan;
        $bo->updated_at = date('Y-m-d H:i:s');
        $bo->save();

        return response()->json(['data' => $bo, 'message' => 'BO berhasil diapprove'], 200);
    }

    public function rejectBo(Request $request)
    {
        $bo = BiayaOperasional::findOrFail($request->id);
        if ($bo->status !== 'requested' || !$bo->is_active) {
            return response()->json(['message' => 'BO hanya bisa direject saat status requested'], 422);
        }

        $bo->status = 'void';
        $bo->is_active = false;
        $bo->rejected_by = $this->karyawan;
        $bo->rejected_at = date('Y-m-d H:i:s');
        $bo->deleted_by = $this->karyawan;
        $bo->deleted_at = date('Y-m-d H:i:s');
        $bo->updated_by = $this->karyawan;
        $bo->updated_at = date('Y-m-d H:i:s');
        $bo->save();

        return response()->json(['data' => $bo, 'message' => 'BO berhasil direject'], 200);
    }
    public function prepareBudget(Request $request)
    {
        $bo = BiayaOperasional::with('items')->findOrFail($request->id);
        if ($bo->status !== 'approved') {
            return response()->json(['message' => 'BO hanya bisa diproses setelah approved'], 422);
        }

        $items = $request->input('items', []);
        if (!is_array($items) || count($items) < 1) {
            return response()->json(['message' => 'Item budget wajib diisi'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                BiayaOperasionalItem::where('bo_id', $bo->id)
                    ->where('id', $item['id'] ?? null)
                    ->update([
                        'prepared_amount' => $item['prepared_amount'] ?? 0,
                        'updated_by' => $this->karyawan,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }

            $bo->status = 'prepared';
            $bo->prepared_by = $this->karyawan;
            $bo->prepared_at = date('Y-m-d H:i:s');
            $bo->save();

            DB::commit();
            return response()->json(['data' => $bo, 'message' => 'Budget BO berhasil disiapkan'], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function submitActual(Request $request)
    {
        $bo = BiayaOperasional::with('items')->findOrFail($request->id);
        if ($bo->status !== 'prepared') {
            return response()->json(['message' => 'BO hanya bisa direalisasikan saat status transit'], 422);
        }

        $items = $request->input('items', []);
        if (!is_array($items) || count($items) < 1) {
            return response()->json(['message' => 'Realisasi item wajib diisi'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                BiayaOperasionalItem::where('bo_id', $bo->id)
                    ->where('id', $item['id'] ?? null)
                    ->update([
                        'used_amount' => $item['used_amount'] ?? 0,
                        'updated_by' => $this->karyawan,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }

            $this->handleReceipts($request, $bo);

            $bo->status = 'completed';
            $bo->completed_by = $this->karyawan;
            $bo->completed_at = date('Y-m-d H:i:s');
            $bo->save();

            DB::commit();
            return response()->json(['data' => $bo, 'message' => 'Realisasi BO berhasil disimpan'], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function voidBo(Request $request)
    {
        $bo = BiayaOperasional::findOrFail($request->id);
        if (!in_array($bo->status, ['requested', 'prepared'])) {
            return response()->json(['message' => 'BO hanya bisa divoid sebelum selesai'], 422);
        }

        $bo->status = 'void';
        $bo->is_active = false;
        $bo->deleted_by = $this->karyawan;
        $bo->deleted_at = date('Y-m-d H:i:s');
        $bo->updated_by = $this->karyawan;
        $bo->updated_at = date('Y-m-d H:i:s');
        $bo->save();

        return response()->json(['data' => $bo, 'message' => 'BO berhasil divoid'], 200);
    }
    public function exportReceiptPdf(Request $request)
    {
        $bo = BiayaOperasional::with('items')->findOrFail($request->id);
        $html = $this->receiptHtml($bo);

        $mpdf = new Mpdf\Mpdf([
            'format' => [80, 160],
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_top' => 4,
            'margin_bottom' => 4,
        ]);
        $mpdf->WriteHTML($html);

        return response()->json([
            'data' => base64_encode($mpdf->Output('', 'S')),
            'message' => 'Struk BO berhasil dibuat',
        ], 200);
    }

    private function handleReceipts(Request $request, BiayaOperasional $bo): void
    {
        if (!$request->hasFile('receipts')) {
            return;
        }

        $dir = public_path(self::RECEIPT_DIR);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        foreach ($request->file('receipts') as $file) {
            if (!$file->isValid() || strpos($file->getMimeType(), 'image/') !== 0) {
                continue;
            }

            $fileName = 'BO_' . preg_replace('/[^A-Za-z0-9]/', '_', $bo->bo_number) . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $fileName);

            BiayaOperasionalReceipt::create([
                'bo_id' => $bo->id,
                'file_name' => $fileName,
                'original_name' => $file->getClientOriginalName(),
                'created_by' => $this->karyawan,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function parseDestinations($destination): array
    {
        if (is_array($destination)) {
            return array_values(array_filter(array_map('trim', $destination)));
        }

        if (!$destination) {
            return [];
        }

        $decoded = json_decode($destination, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded)));
        }

        return array_values(array_filter(array_map('trim', [$destination])));
    }

    private function destinationText($destination): string
    {
        $destinations = $this->parseDestinations($destination);
        return count($destinations) ? implode(', ', $destinations) : '-';
    }
    private function generateBoNumber(): string
    {
        $year = date('y');
        $month = date('m');
        $roman = self::ROMAN_MONTHS[$month];
        $prefix = "ISL/BO/{$year}-{$roman}/";

        $last = BiayaOperasional::where('bo_number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(bo_number, "/", -1) AS UNSIGNED) DESC')
            ->first();

        $next = $last ? ((int) substr($last->bo_number, strrpos($last->bo_number, '/') + 1)) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    private function displayStatus(?string $status): string
    {
        return [
            'requested' => 'Menunggu Approval',
            'approved' => 'Approved',
            'prepared' => 'Transit',
            'completed' => 'Selesai',
            'void' => 'Void',
        ][$status] ?? '-';
    }

    private function formatIndonesianDate($date): string
    {
        if (!$date) {
            return '-';
        }

        $timestamp = strtotime($date);
        if (!$timestamp) {
            return '-';
        }

        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        return $days[(int) date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
    }
    private function receiptHtml(BiayaOperasional $bo): string
    {
        $rows = $bo->items->map(function ($item) {
            return '<tr><td style="padding:1px 0; vertical-align:top;">' . e($item->need_name) . '</td><td style="padding:1px 0; text-align:right; vertical-align:top; white-space:nowrap;">Rp ' . number_format($item->prepared_amount, 0, ',', '.') . '</td></tr>';
        })->join('');

        $total = number_format($bo->items->sum('prepared_amount'), 0, ',', '.');
        $travelDate = $this->formatIndonesianDate($bo->travel_date);
        $destinationText = $this->destinationText($bo->destination);
        $logoPath = public_path('isl_logo.png');
        $logo = file_exists($logoPath)
            ? '<img src="' . $logoPath . '" style="height:22px; margin-bottom:2px;">'
            : '<div style="font-weight:bold; font-size:11px;">INTILAB</div>';
        $dash = '<div style="border-top:0.7px dashed #333; margin:6px 0;"></div>';

        return '
            <div style="font-family: &quot;Courier New&quot;, Courier, monospace; font-size:9px; line-height:1.25; width:72mm; color:#000;">
                <div style="text-align:center;">' . $logo . '</div>
                <div style="text-align:center; font-size:9px; letter-spacing:.3px;">STRUK BIAYA OPERASIONAL</div>
                ' . $dash . '
                <table style="width:100%; font-size:9px; border-collapse:collapse; line-height:1.25;">
                    <tr><td style="padding:1px 0;">No BO</td><td style="padding:1px 0; text-align:right">' . e($bo->bo_number) . '</td></tr>
                    <tr><td style="padding:1px 0;">PJ</td><td style="padding:1px 0; text-align:right">' . e($bo->person_in_charge) . '</td></tr>
                    <tr><td style="padding:1px 0;">Tujuan</td><td style="padding:1px 0; text-align:right">' . e($destinationText) . '</td></tr>
                    <tr><td style="padding:1px 0;">Tanggal</td><td style="padding:1px 0; text-align:right">' . e($travelDate) . '</td></tr>
                </table>
                ' . $dash . '
                <table style="width:100%; font-size:9px; border-collapse:collapse; line-height:1.25;">' . $rows . '</table>
                ' . $dash . '
                <table style="width:100%; font-size:10px; font-weight:bold; border-collapse:collapse;">
                    <tr><td style="padding:2px 0;">TOTAL DISIAPKAN</td><td style="padding:2px 0; text-align:right; white-space:nowrap;">Rp ' . $total . '</td></tr>
                </table>
                <br>
                <div style="text-align:center; font-size:9px;">Diterima oleh pemohon</div>
                <br><br>
                <div style="text-align:center;">( ' . e($bo->person_in_charge) . ' )</div>
            </div>
        ';
    }
}















