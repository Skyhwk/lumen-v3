<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\{
    MasterCabang,
    MasterSupplier,
    PurchaseOrderDocument,
    PurchaseRequest,
};
use App\Services\{GenerateQrDocumentPo, KaryawanProfileService, Notification, PurchaseReceiptService};
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseOrdersController extends Controller
{
    private const ROMAN_MONTHS = [
        '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IV',
        '05' => 'V', '06' => 'VI', '07' => 'VII', '08' => 'VIII',
        '09' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII',
    ];

    public function index(Request $request)
    {
        $scope = $request->input('scope', 'pending');

        $purchaseRequests = PurchaseRequest::with(['items', 'employee.jabatan', 'employee.divisi'])
            ->where('is_active', true)
            ->whereIn('status', ['Approved', 'Partially Approved'])
            ->where('finance_status', '!=', 'Rejected')
            ->latest();

        if ($scope === 'pending') {
            $purchaseRequests = $purchaseRequests->where('finance_status', 'Waiting to Create PO');
        } else {
            $purchaseRequests = $purchaseRequests->whereIn('finance_status', [
                'On Process',
                'Waiting Vendor Receipt',
                'Waiting User Receipt',
                'Distributing',
            ])->whereNotNull('po_number');
        }

        return DataTables::of($purchaseRequests)
            ->addColumn('item_name', fn($row) => optional($row->items->first())->item_name)
            ->addColumn('quantity', fn($row) => optional($row->items->first())->quantity)
            ->addColumn('unit', fn($row) => optional($row->items->first())->unit)
            ->addColumn('requester_divisi', fn($row) => KaryawanProfileService::resolveDivisi($row->employee))
            ->addColumn('finance_display_status', fn($row) => $this->resolveFinanceDisplayStatus($row))
            ->addColumn('has_po', fn($row) => !empty($row->po_number))
            ->make(true);
    }

    public function initialize(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $headOffice = MasterCabang::where('id', 1)->where('is_active', true)->first();

        $shippingAddress = $headOffice
            ? trim("PT INTI SURYA LABORATORIUM, {$headOffice->alamat_cabang}")
            : 'PT INTI SURYA LABORATORIUM';

        $defaultPic = trim(($employee->nama_lengkap ?? '') . ' / Purchasing', ' /');

        return response()->json([
            'data' => [
                'employee' => $employee,
                'default_pic' => $defaultPic,
                'approver' => [
                    'nama_lengkap' => $employee->nama_lengkap ?? '',
                    'jabatan' => $employee->jabatan ?? '',
                ],
                'shipping_address' => $shippingAddress,
                'suppliers' => MasterSupplier::where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'address', 'phone']),
            ],
            'message' => 'Purchase order initialized successfully',
        ], 200);
    }

    public function getSuppliers()
    {
        return response()->json(
            MasterSupplier::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'address', 'phone']),
            200
        );
    }

    public function getPo(Request $request)
    {
        $validator = Validator::make($request->all(), ['id' => 'required']);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::with(['items', 'employee'])->findOrFail($request->id);
        $poDocument = $this->getActivePoDocument($purchaseRequest->id);

        if (!$poDocument) {
            return response()->json(['message' => 'Dokumen PO aktif tidak ditemukan'], 404);
        }

        return response()->json([
            'data' => [
                'purchase_request' => $purchaseRequest,
                'po_document' => $poDocument,
            ],
            'message' => 'Detail PO berhasil diambil',
        ], 200);
    }

    public function createPo(Request $request)
    {
        $validator = Validator::make($request->all(), $this->poFormRules());

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::with('items')->findOrFail($request->id);

        if ($purchaseRequest->finance_status !== 'Waiting to Create PO') {
            return response()->json(['message' => 'Permintaan tidak dalam status siap dibuat PO'], 422);
        }

        return $this->storePoDocument($request, $purchaseRequest, true);
    }

    public function updatePo(Request $request)
    {
        $validator = Validator::make($request->all(), $this->poFormRules());

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::with('items')->findOrFail($request->id);

        if ($purchaseRequest->finance_status !== 'On Process') {
            return response()->json(['message' => 'PO hanya dapat diubah saat status On Process'], 422);
        }

        $poDocument = $this->getActivePoDocument($purchaseRequest->id);

        if (!$poDocument) {
            return response()->json(['message' => 'Dokumen PO aktif tidak ditemukan'], 404);
        }

        return $this->storePoDocument($request, $purchaseRequest, false, $poDocument);
    }

    public function voidPo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::findOrFail($request->id);

        if (!in_array($purchaseRequest->finance_status, ['On Process', 'Waiting Vendor Receipt'], true)) {
            return response()->json([
                'message' => 'PO hanya dapat di-void sebelum barang diterima dari vendor',
            ], 422);
        }

        if (!$purchaseRequest->po_number) {
            return response()->json(['message' => 'Nomor PO tidak ditemukan'], 422);
        }

        $poDocument = $this->getActivePoDocument($purchaseRequest->id);

        if (!$poDocument) {
            return response()->json(['message' => 'Dokumen PO aktif tidak ditemukan'], 404);
        }

        $employee = $request->attributes->get('user')->karyawan;
        $now = date('Y-m-d H:i:s');
        $voidFromStatus = $purchaseRequest->finance_status;
        $voidedPoNumber = $purchaseRequest->po_number;

        DB::beginTransaction();

        try {
            $poDocument->is_voided = true;
            $poDocument->voided_by = $this->karyawan;
            $poDocument->voided_at = $now;
            $poDocument->void_reason = trim($request->reason);
            $poDocument->void_from_finance_status = $voidFromStatus;
            $poDocument->save();

            $purchaseRequest->finance_status = 'Waiting to Create PO';
            $purchaseRequest->po_number = null;
            $purchaseRequest->po_created_by = null;
            $purchaseRequest->po_created_at = null;
            $purchaseRequest->po_approved_by = null;
            $purchaseRequest->po_approved_at = null;
            $purchaseRequest->processed_by = null;
            $purchaseRequest->processed_at = null;
            $purchaseRequest->save();

            $voidedByName = ($employee && $employee->nama_lengkap) ? $employee->nama_lengkap : $this->karyawan;

            Notification::where('nama_lengkap', $purchaseRequest->created_by)
                ->title('Purchase Order Di-void')
                ->message("PO {$voidedPoNumber} untuk permintaan {$purchaseRequest->request_number} telah di-void oleh {$voidedByName} pada " . date('d-m-Y') . ". Alasan: {$poDocument->void_reason}")
                ->url('/request/purchase-requests')
                ->send();

            Notification::whereIn('id_jabatan', [56, 57])
                ->title('PO Di-void — Siap Dibuat Ulang')
                ->message("PO {$voidedPoNumber} ({$purchaseRequest->request_number}) di-void oleh {$voidedByName}. Permintaan kembali ke antrian pembuatan PO.")
                ->url('/finance/purchasing/purchase-order')
                ->send();

            DB::commit();

            return response()->json([
                'message' => 'Purchase Order berhasil di-void. Permintaan kembali ke antrian pembuatan PO.',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal void Purchase Order: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function processPo(Request $request)
    {
        $validator = Validator::make($request->all(), ['id' => 'required']);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::with('items')->findOrFail($request->id);

        if ($purchaseRequest->finance_status !== 'On Process') {
            return response()->json(['message' => 'PO hanya dapat diproses saat status On Process'], 422);
        }

        if (!$purchaseRequest->po_number) {
            return response()->json(['message' => 'Nomor PO tidak ditemukan'], 422);
        }

        $employee = $request->attributes->get('user')->karyawan;
        $now = date('Y-m-d H:i:s');

        $purchaseRequest->finance_status = 'Waiting Vendor Receipt';
        $purchaseRequest->po_approved_by = $this->karyawan;
        $purchaseRequest->po_approved_at = $now;
        $purchaseRequest->receipt_target_qty = PurchaseReceiptService::resolveTargetQty($purchaseRequest);
        $purchaseRequest->vendor_received_total = 0;
        $purchaseRequest->user_handed_total = 0;
        $purchaseRequest->user_confirmed_total = 0;
        $purchaseRequest->save();

        $processorName = ($employee && $employee->nama_lengkap) ? $employee->nama_lengkap : $this->karyawan;

        Notification::where('nama_lengkap', $purchaseRequest->created_by)
            ->title('Purchase Order Diproses!')
            ->message("PO {$purchaseRequest->po_number} untuk permintaan {$purchaseRequest->request_number} telah diproses oleh {$processorName} dan menunggu penerimaan barang.")
            ->url('/request/purchase-requests')
            ->send();

        return response()->json([
            'message' => 'Purchase Order berhasil diproses. Data dipindahkan ke Goods Receipt.',
        ], 200);
    }

    private function poFormRules(): array
    {
        return [
            'id' => 'required',
            'supplier_name' => 'required|string|max:255',
            'supplier_address' => 'nullable|string',
            'supplier_id' => 'nullable|integer',
            'item_name' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'ppn_percent' => 'nullable|numeric|min:0|max:100',
            'other_cost' => 'nullable|numeric|min:0',
            'keterangan' => 'nullable|string',
            'phone_fax' => 'nullable|string|max:100',
            'pic' => 'nullable|string|max:255',
            'payment_term' => 'nullable|string|max:255',
            'item_status' => 'nullable|string|max:100',
            'delivery_time' => 'required|date',
            'delivery_type' => 'nullable|string|max:100',
            'offer_ref' => 'nullable|string|max:255',
            'shipping_address' => 'nullable|string',
            'approval_date' => 'required|date',
            'approval_name' => 'required|string|max:255',
            'approval_jabatan' => 'nullable|string|max:255',
        ];
    }

    private function storePoDocument(Request $request, PurchaseRequest $purchaseRequest, bool $isCreate, ?PurchaseOrderDocument $existingPo = null)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $item = $purchaseRequest->items->first();

        $unitPrice = (float) $request->unit_price;
        $quantity = (float) $request->quantity;
        $discount = (float) ($request->discount ?? 0);
        $ppnPercent = (float) ($request->ppn_percent ?? 11);
        $otherCost = (float) ($request->other_cost ?? 0);

        $lineTotal = round($quantity * $unitPrice, 2);
        $subTotal = max(round($lineTotal - $discount, 2), 0);
        $ppnAmount = round($lineTotal * ($ppnPercent / 100), 2);
        $grandTotal = round($subTotal + $ppnAmount + $otherCost, 2);

        DB::beginTransaction();

        try {
            $supplierId = $this->resolveSupplier($request);
            $now = date('Y-m-d H:i:s');

            $poData = [
                'supplier_id' => $supplierId,
                'supplier_name' => trim($request->supplier_name),
                'supplier_address' => $request->supplier_address,
                'item_name' => trim($request->item_name),
                'quantity' => $quantity,
                'unit' => optional($item)->unit,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'discount' => $discount,
                'sub_total' => $subTotal,
                'ppn_percent' => $ppnPercent,
                'ppn_amount' => $ppnAmount,
                'other_cost' => $otherCost,
                'grand_total' => $grandTotal,
                'keterangan' => trim($request->keterangan ?? '') ?: trim(optional($item)->note ?? ''),
                'phone_fax' => $request->phone_fax ?: '021-5089-8988/89',
                'pic' => $request->pic,
                'payment_term' => $request->payment_term ?: 'Setelah Invoice diterima',
                'item_status' => $request->item_status ?: 'Ready Stok',
                'delivery_time' => $request->delivery_time,
                'delivery_type' => $request->delivery_type ?: 'Barang diambil',
                'offer_ref' => $request->offer_ref,
                'shipping_address' => $request->shipping_address,
                'approval_date' => $request->approval_date,
                'approval_name' => trim($request->approval_name),
                'approval_jabatan' => trim($request->approval_jabatan ?? ''),
            ];

            if ($isCreate) {
                $poNumber = $this->generatePoNumber();
                $poDocument = PurchaseOrderDocument::create(array_merge($poData, [
                    'purchase_request_id' => $purchaseRequest->id,
                    'po_date' => date('Y-m-d'),
                    'po_number' => $poNumber,
                    'invoice_number' => $poNumber,
                    'created_by' => $this->karyawan,
                    'created_at' => $now,
                ]));

                $qrService = new GenerateQrDocumentPo();
                $qrFile = $qrService->insert('PURCHASE_ORDER', $poDocument, $this->karyawan);
                $poDocument->qr_file = $qrFile;
                $poDocument->save();

                $purchaseRequest->finance_status = 'On Process';
                $purchaseRequest->po_number = $poNumber;
                $purchaseRequest->po_created_by = $this->karyawan;
                $purchaseRequest->po_created_at = $now;
                $purchaseRequest->processed_by = $this->karyawan;
                $purchaseRequest->processed_at = $now;
                $purchaseRequest->save();

                $processorName = ($employee && $employee->nama_lengkap) ? $employee->nama_lengkap : $this->karyawan;

                Notification::where('nama_lengkap', $purchaseRequest->created_by)
                    ->title('Permintaan Pembelian Barang Diproses!')
                    ->message("Permintaan pembelian barang {$purchaseRequest->request_number} sedang diproses. PO {$poNumber} telah dibuat oleh {$processorName} pada " . date('d-m-Y'))
                    ->url('/request/purchase-requests')
                    ->send();

                DB::commit();

                return response()->json([
                    'message' => 'Purchase Order berhasil dibuat. Permintaan pembelian sedang diproses.',
                    'data' => ['po_number' => $poNumber],
                ], 201);
            }

            $existingPo->fill($poData);
            $existingPo->save();

            DB::commit();

            return response()->json([
                'message' => 'Purchase Order berhasil diperbarui',
                'data' => ['po_number' => $existingPo->po_number],
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyimpan Purchase Order: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function exportPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::findOrFail($request->id);
        $poDocumentId = $request->input('po_document_id');
        $poDocument = $poDocumentId
            ? PurchaseOrderDocument::where('purchase_request_id', $purchaseRequest->id)->findOrFail($poDocumentId)
            : $this->getActivePoDocument($purchaseRequest->id);

        if (!$poDocument) {
            return response()->json(['message' => 'Dokumen PO tidak ditemukan'], 404);
        }

        $pdfString = $this->buildPdf($poDocument);

        return response()->json([
            'data' => base64_encode($pdfString),
            'message' => 'PDF generated successfully',
        ], 200);
    }

    private function resolveSupplier(Request $request): ?int
    {
        if ($request->supplier_id) {
            $supplier = MasterSupplier::where('id', $request->supplier_id)
                ->where('is_active', true)
                ->first();

            if ($supplier) {
                $supplier->address = $request->supplier_address ?: $supplier->address;
                $supplier->updated_at = date('Y-m-d H:i:s');
                $supplier->save();

                return (int) $supplier->id;
            }
        }

        $existing = MasterSupplier::where('name', trim($request->supplier_name))
            ->where('is_active', true)
            ->first();

        if ($existing) {
            $existing->address = $request->supplier_address ?: $existing->address;
            $existing->updated_at = date('Y-m-d H:i:s');
            $existing->save();

            return (int) $existing->id;
        }

        $created = MasterSupplier::create([
            'name' => trim($request->supplier_name),
            'address' => $request->supplier_address,
            'phone' => $request->phone_fax,
            'is_active' => true,
            'created_by' => $this->karyawan,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $created->id;
    }

    private function generatePoNumber(): string
    {
        $year = date('y');
        $month = self::ROMAN_MONTHS[date('m')];
        $prefix = "ISL/PO/{$year}-{$month}/";

        $latest = PurchaseOrderDocument::where('po_number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(po_number, "/", -1) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = 1;
        if ($latest) {
            $lastPart = substr($latest->po_number, strrpos($latest->po_number, '/') + 1);
            $nextNumber = (int) $lastPart + 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function buildPdf(PurchaseOrderDocument $poDocument): string
    {
        $purchaseRequest = PurchaseRequest::with('items')->find($poDocument->purchase_request_id);
        $itemNote = trim(optional(optional($purchaseRequest)->items->first())->note ?? '');
        $keterangan = trim($poDocument->keterangan ?? '') ?: $itemNote;

        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 10,
            'margin_bottom' => 15,
        ]);

        $poDateFormatted = Carbon::parse($poDocument->po_date)->locale('id')->isoFormat('DD MMMM YYYY');
        $approvalDateFormatted = Carbon::parse($poDocument->approval_date)->locale('id')->isoFormat('DD MMMM YYYY');
        $deliveryTimeFormatted = Carbon::parse($poDocument->delivery_time)->locale('id')->isoFormat('DD MMMM YYYY');
        $qrPath = $poDocument->qr_file
            ? public_path('qr_documents/' . $poDocument->qr_file . '.svg')
            : null;

        $html = view('pdf.purchase-order', compact(
            'poDocument',
            'poDateFormatted',
            'approvalDateFormatted',
            'deliveryTimeFormatted',
            'qrPath',
            'keterangan'
        ))->render();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function getActivePoDocument(int $purchaseRequestId): ?PurchaseOrderDocument
    {
        return PurchaseOrderDocument::where('purchase_request_id', $purchaseRequestId)
            ->where(function ($query) {
                $query->where('is_voided', false)->orWhereNull('is_voided');
            })
            ->latest('id')
            ->first();
    }

    private function resolveFinanceDisplayStatus($row): string
    {
        if ($row->finance_status === 'Waiting to Create PO') {
            return 'Waiting to Create PO';
        }

        if ($row->finance_status === 'PO Created') {
            return 'PO Created';
        }

        if ($row->finance_status === 'On Process') {
            return 'On Process';
        }

        if ($row->finance_status === 'Waiting Vendor Receipt') {
            return 'Waiting Vendor Receipt';
        }

        if ($row->finance_status === 'Waiting User Receipt') {
            return 'Waiting User Receipt';
        }

        if ($row->finance_status === 'Distributing') {
            return 'Distributing';
        }

        if (in_array($row->finance_status, ['Waiting Process', 'Pending'])) {
            return $row->finance_status;
        }

        if ($row->finance_status === 'Distributed' || $row->status === 'Done') {
            return 'Distributed';
        }

        return $row->finance_status ?: '-';
    }
}
