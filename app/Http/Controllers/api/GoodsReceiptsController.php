<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use App\Models\PurchaseRequest;
use App\Services\Notification;
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GoodsReceiptsController extends Controller
{
    private const MAX_ATTACHMENT_SIZE = 2097152; // 2MB
    private const ATTACHMENT_DIR = 'goods-receipt/vendor';

    private const ROMAN_MONTHS = [
        '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IV',
        '05' => 'V', '06' => 'VI', '07' => 'VII', '08' => 'VIII',
        '09' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII',
    ];

    public function index(Request $request)
    {
        $scope = $request->input('scope', 'pending');

        $purchaseRequests = PurchaseRequest::with(['items', 'employee'])
            ->where('is_active', true)
            ->whereIn('status', ['Approved', 'Partially Approved'])
            ->latest();

        if ($scope === 'pending') {
            $purchaseRequests = $purchaseRequests->where('finance_status', 'Waiting Vendor Receipt');
        } else {
            $purchaseRequests = $purchaseRequests->where('finance_status', 'Waiting User Receipt');
        }

        return DataTables::of($purchaseRequests)
            ->addColumn('item_name', fn($row) => optional($row->items->first())->item_name)
            ->addColumn('quantity', fn($row) => optional($row->items->first())->quantity)
            ->addColumn('unit', fn($row) => optional($row->items->first())->unit)
            ->addColumn('finance_display_status', fn($row) => $this->resolveDisplayStatus($row))
            ->make(true);
    }

    public function getUserReceipt(Request $request)
    {
        $purchaseRequest = PurchaseRequest::with(['items', 'employee'])->findOrFail($request->id);

        if ($purchaseRequest->finance_status !== 'Waiting User Receipt') {
            return response()->json(['message' => 'Permintaan tidak dalam status menunggu serah terima ke user'], 422);
        }

        $recipient = $this->findKaryawanByName($purchaseRequest->created_by);

        $item = $purchaseRequest->items->first();

        return response()->json([
            'data' => [
                'id' => $purchaseRequest->id,
                'po_number' => $purchaseRequest->po_number,
                'request_number' => $purchaseRequest->request_number,
                'purpose' => $purchaseRequest->purpose,
                'recipient' => [
                    'nama_lengkap' => $purchaseRequest->created_by,
                    'jabatan' => $this->resolveKaryawanJabatan($recipient),
                    'divisi' => $this->resolveKaryawanDivisi($recipient),
                ],
                'item' => [
                    'item_code' => $item->item_code ?? '',
                    'item_name' => $item->item_name ?? '',
                    'quantity' => $purchaseRequest->vendor_receipt_qty ?? ($item->quantity ?? ''),
                    'unit' => $item->unit ?? '',
                    'note' => $item->note ?? '',
                ],
            ],
            'message' => 'Data serah terima user berhasil dimuat',
        ], 200);
    }

    public function getVendorReceipt(Request $request)
    {
        $purchaseRequest = PurchaseRequest::with('items')->findOrFail($request->id);

        if (!in_array($purchaseRequest->finance_status, ['Waiting Vendor Receipt', 'Waiting User Receipt'])) {
            return response()->json(['message' => 'Data tanda terima vendor tidak dapat diakses'], 422);
        }

        return response()->json([
            'data' => [
                'id' => $purchaseRequest->id,
                'po_number' => $purchaseRequest->po_number,
                'item_name' => optional($purchaseRequest->items->first())->item_name,
                'quantity' => optional($purchaseRequest->items->first())->quantity,
                'vendor_delivery_note' => $purchaseRequest->vendor_delivery_note,
                'vendor_receipt_qty' => $purchaseRequest->vendor_receipt_qty,
                'vendor_receipt_note' => $purchaseRequest->vendor_receipt_note,
                'vendor_receipt_attachments' => $this->parseAttachments($purchaseRequest->vendor_receipt_attachments),
                'finance_status' => $purchaseRequest->finance_status,
            ],
            'message' => 'Data tanda terima vendor berhasil dimuat',
        ], 200);
    }

    public function saveVendorReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'vendor_delivery_note' => 'nullable|string|max:255',
            'vendor_receipt_qty' => 'required|numeric|min:0.01',
            'vendor_receipt_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::findOrFail($request->id);

        if ($purchaseRequest->finance_status !== 'Waiting Vendor Receipt') {
            return response()->json(['message' => 'Permintaan tidak dalam status menunggu tanda terima vendor'], 422);
        }

        $attachments = $this->handleAttachments($request, $purchaseRequest->vendor_receipt_attachments);
        if ($attachments === false) {
            return response()->json(['message' => 'Lampiran harus berupa gambar dengan ukuran maksimal 2MB per file'], 422);
        }

        $employee = $request->attributes->get('user')->karyawan;
        $now = date('Y-m-d H:i:s');

        $purchaseRequest->finance_status = 'Waiting User Receipt';
        $purchaseRequest->vendor_receipt_at = $now;
        $purchaseRequest->vendor_receipt_by = $this->karyawan;
        $purchaseRequest->vendor_delivery_note = $request->vendor_delivery_note;
        $purchaseRequest->vendor_receipt_qty = $request->vendor_receipt_qty;
        $purchaseRequest->vendor_receipt_note = $request->vendor_receipt_note;
        $purchaseRequest->vendor_receipt_attachments = $this->encodeAttachments($attachments);
        $purchaseRequest->save();

        $processorName = ($employee && $employee->nama_lengkap) ? $employee->nama_lengkap : $this->karyawan;

        Notification::where('nama_lengkap', $purchaseRequest->created_by)
            ->title('Barang dari Vendor Diterima!')
            ->message("Barang untuk permintaan {$purchaseRequest->request_number} (PO {$purchaseRequest->po_number}) telah diterima dari vendor oleh {$processorName} pada " . date('d-m-Y'))
            ->url('/request/purchase-requests')
            ->send();

        return response()->json(['message' => 'Tanda terima barang dari vendor berhasil disimpan'], 200);
    }

    public function updateVendorReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'vendor_delivery_note' => 'nullable|string|max:255',
            'vendor_receipt_qty' => 'required|numeric|min:0.01',
            'vendor_receipt_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::findOrFail($request->id);

        if ($purchaseRequest->finance_status !== 'Waiting User Receipt') {
            return response()->json(['message' => 'Tanda terima vendor hanya dapat diubah sebelum serah terima ke user'], 422);
        }

        $attachments = $this->handleAttachments($request, $purchaseRequest->vendor_receipt_attachments);
        if ($attachments === false) {
            return response()->json(['message' => 'Lampiran harus berupa gambar dengan ukuran maksimal 2MB per file'], 422);
        }

        $purchaseRequest->vendor_delivery_note = $request->vendor_delivery_note;
        $purchaseRequest->vendor_receipt_qty = $request->vendor_receipt_qty;
        $purchaseRequest->vendor_receipt_note = $request->vendor_receipt_note;
        $purchaseRequest->vendor_receipt_attachments = $this->encodeAttachments($attachments);
        $purchaseRequest->save();

        return response()->json(['message' => 'Tanda terima vendor berhasil diperbarui'], 200);
    }

    public function saveUserReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'user_receipt_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::with(['items'])->findOrFail($request->id);

        if ($purchaseRequest->finance_status !== 'Waiting User Receipt') {
            return response()->json(['message' => 'Permintaan tidak dalam status menunggu serah terima ke user'], 422);
        }

        $employee = $request->attributes->get('user')->karyawan;
        $now = date('Y-m-d H:i:s');

        $purchaseRequest->handover_number = $this->generateHandoverNumber();
        $purchaseRequest->finance_status = 'Distributing';
        $purchaseRequest->user_receipt_at = $now;
        $purchaseRequest->user_receipt_by = $this->karyawan;
        $purchaseRequest->user_receipt_note = $request->user_receipt_note;
        $purchaseRequest->save();

        $pdfContent = $this->buildHandoverPdf($purchaseRequest, $employee);
        $processorName = ($employee && $employee->nama_lengkap) ? $employee->nama_lengkap : $this->karyawan;

        Notification::where('nama_lengkap', $purchaseRequest->created_by)
            ->title('Purchase Request Ready!')
            ->message("Purchase request {$purchaseRequest->request_number} sudah ready dan sedang di distribusikan ke anda oleh {$processorName} ({$purchaseRequest->handover_number}). Silakan konfirmasi penerimaan barang.")
            ->url('/request/purchase-requests')
            ->send();

        return response()->json([
            'message' => 'Dokumen serah terima berhasil dibuat',
            'data' => [
                'handover_number' => $purchaseRequest->handover_number,
                'pdf' => base64_encode($pdfContent),
            ],
        ], 200);
    }

    public function exportHandoverPdf(Request $request)
    {
        $purchaseRequest = PurchaseRequest::with(['items'])->findOrFail($request->id);

        if (empty($purchaseRequest->handover_number)) {
            return response()->json(['message' => 'Dokumen serah terima belum tersedia'], 422);
        }

        $handedBy = MasterKaryawan::with('jabatan')
            ->where('nama_lengkap', $purchaseRequest->user_receipt_by)
            ->where('is_active', true)
            ->first();

        $pdfContent = $this->buildHandoverPdf($purchaseRequest, $handedBy);

        return response()->json([
            'message' => 'PDF serah terima berhasil digenerate',
            'data' => base64_encode($pdfContent),
        ], 200);
    }

    private function generateHandoverNumber(): string
    {
        $year = date('y');
        $month = self::ROMAN_MONTHS[date('m')];
        $prefix = "ISL/PB/{$year}-{$month}/";

        $latest = PurchaseRequest::where('handover_number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(handover_number, "/", -1) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = 1;
        if ($latest && $latest->handover_number) {
            $lastPart = substr($latest->handover_number, strrpos($latest->handover_number, '/') + 1);
            $nextNumber = (int) $lastPart + 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function buildHandoverPdf(PurchaseRequest $purchaseRequest, $handedByEmployee): string
    {
        $item = $purchaseRequest->items->first();
        $recipient = $this->findKaryawanByName($purchaseRequest->created_by);

        $handoverDate = $purchaseRequest->user_receipt_at ?: date('Y-m-d H:i:s');
        $handoverDateFormatted = Carbon::parse($handoverDate)->locale('id')->isoFormat('D MMMM YYYY H:mm');

        $keteranganParts = array_filter([
            $purchaseRequest->request_number,
            $purchaseRequest->po_number ? 'PO: ' . $purchaseRequest->po_number : null,
            $purchaseRequest->purpose,
            $item->note ?? null,
            $purchaseRequest->vendor_receipt_note ? 'Catatan Vendor: ' . $purchaseRequest->vendor_receipt_note : null,
            $purchaseRequest->user_receipt_note ? 'Catatan Serah Terima: ' . $purchaseRequest->user_receipt_note : null,
        ]);

        $handoverNumber = $purchaseRequest->handover_number;
        $itemCode = $item->item_code ?? '';
        $itemName = $item->item_name ?? '';
        $quantity = $purchaseRequest->vendor_receipt_qty ?? ($item->quantity ?? '');
        $unit = $item->unit ?? '';
        $itemNote = $item->note ?? '';
        $keterangan = implode("\n", $keteranganParts);

        $handedByName = ($handedByEmployee && $handedByEmployee->nama_lengkap) ? $handedByEmployee->nama_lengkap : ($purchaseRequest->user_receipt_by ?: '-');
        $handedByRecord = $this->findKaryawanByName($handedByName);
        $handedByPosition = $this->resolveKaryawanJabatan($handedByRecord);
        if ($handedByPosition === '-') {
            $handedByPosition = 'Purchasing';
        }
        $handedByDivision = $this->resolveKaryawanDivisi($handedByRecord);
        if ($handedByDivision === '-') {
            $handedByDivision = 'Purchasing';
        }

        $receivedByName = $purchaseRequest->created_by;
        $receivedByPosition = $this->resolveKaryawanJabatan($recipient);
        $receivedByDivision = $this->resolveKaryawanDivisi($recipient);
        $receivedByDate = Carbon::parse($item->completed_at)->locale('id')->isoFormat('D MMMM YYYY H:mm');

        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A5',
            'orientation' => 'P',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 10,
            'margin_bottom' => 12,
        ]);

        $html = view('pdf.serah-terima-barang', compact(
            'handoverNumber',
            'handoverDateFormatted',
            'itemCode',
            'itemName',
            'quantity',
            'unit',
            'itemNote',
            'keterangan',
            'handedByName',
            'handedByPosition',
            'handedByDivision',
            'receivedByName',
            'receivedByPosition',
            'receivedByDivision',
            'receivedByDate',
        ))->render();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function handleAttachments(Request $request, $existingAttachmentField)
    {
        $attachments = $this->parseAttachments($existingAttachmentField);

        $removed = $request->input('removed_attachments', []);
        if (!is_array($removed)) {
            $removed = [$removed];
        }
        $removed = array_filter($removed);

        if (!empty($removed)) {
            $attachments = array_values(array_filter($attachments, fn($file) => !in_array($file, $removed)));
            foreach ($removed as $file) {
                $path = public_path(self::ATTACHMENT_DIR . '/' . $file);
                if (file_exists($path) && is_file($path)) {
                    unlink($path);
                }
            }
        }

        $index = 0;
        while ($request->hasFile("attachments.$index") || $request->hasFile("attachments[$index]")) {
            $file = $request->file("attachments.$index") ?: $request->file("attachments[$index]");

            if ($file->getSize() > self::MAX_ATTACHMENT_SIZE) {
                return false;
            }

            if (strpos($file->getMimeType(), 'image/') !== 0) {
                return false;
            }

            $destinationPath = public_path(self::ATTACHMENT_DIR);
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $file->move($destinationPath, $fileName);
            $attachments[] = $fileName;

            $index++;
        }

        return $attachments;
    }

    private function parseAttachments($attachmentField): array
    {
        if (empty($attachmentField)) {
            return [];
        }

        $decoded = json_decode($attachmentField, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter($decoded));
        }

        return [$attachmentField];
    }

    private function encodeAttachments(array $files): ?string
    {
        $files = array_values(array_filter($files));

        if (empty($files)) {
            return null;
        }

        return json_encode($files);
    }

    private function findKaryawanByName(?string $namaLengkap): ?MasterKaryawan
    {
        if (empty($namaLengkap)) {
            return null;
        }

        return MasterKaryawan::with(['jabatan', 'divisi'])
            ->where('nama_lengkap', $namaLengkap)
            ->where('is_active', true)
            ->first();
    }

    private function resolveKaryawanJabatan(?MasterKaryawan $employee): string
    {
        if (!$employee) {
            return '-';
        }

        if ($employee->relationLoaded('jabatan')) {
            $jabatanRelation = $employee->getRelation('jabatan');
            if ($jabatanRelation && !empty($jabatanRelation->nama_jabatan)) {
                return $jabatanRelation->nama_jabatan;
            }
        }

        if (!empty($employee->id_jabatan)) {
            $jabatan = MasterJabatan::where('id', $employee->id_jabatan)->where('is_active', true)->first();
            if ($jabatan && !empty($jabatan->nama_jabatan)) {
                return $jabatan->nama_jabatan;
            }
        }

        $jabatanAttr = $employee->getAttributes()['jabatan'] ?? null;
        if (!empty($jabatanAttr) && is_string($jabatanAttr)) {
            return $jabatanAttr;
        }

        return '-';
    }

    private function resolveKaryawanDivisi(?MasterKaryawan $employee): string
    {
        if (!$employee) {
            return '-';
        }

        if ($employee->relationLoaded('divisi')) {
            $divisiRelation = $employee->getRelation('divisi');
            if ($divisiRelation && !empty($divisiRelation->nama_divisi)) {
                return $divisiRelation->nama_divisi;
            }
        }

        if (!empty($employee->id_department)) {
            $divisi = MasterDivisi::where('id', $employee->id_department)->where('is_active', true)->first();
            if ($divisi && !empty($divisi->nama_divisi)) {
                return $divisi->nama_divisi;
            }
        }

        $departmentAttr = $employee->getAttributes()['department'] ?? null;
        if (!empty($departmentAttr) && is_string($departmentAttr)) {
            return $departmentAttr;
        }

        return '-';
    }

    private function resolveDisplayStatus($row): string
    {
        if ($row->finance_status === 'Waiting Vendor Receipt') {
            return 'Waiting Vendor Receipt';
        }

        if ($row->finance_status === 'Waiting User Receipt') {
            return 'Waiting User Receipt';
        }

        return $row->finance_status ?: '-';
    }
}
