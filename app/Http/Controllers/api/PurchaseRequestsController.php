<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use DataTables;

use Mpdf;
use App\Services\{Notification, GetBawahan, GetAtasan};

use App\Models\{PurchaseRequest, PurchaseRequestItem, MasterKaryawan};

class PurchaseRequestsController extends Controller
{
    private const ROMAN_MONTHS = [
        '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IV',
        '05' => 'V', '06' => 'VI', '07' => 'VII', '08' => 'VIII',
        '09' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII',
    ];

    private const MAX_ATTACHMENT_SIZE = 2097152; // 2MB

    public function initialize(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        return response()->json([
            'data' => [
                'employee' => $employee,
            ],
            'message' => 'Purchase request initialized successfully'
        ], 200);
    }

    public function index(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        $purchaseRequests = PurchaseRequest::with(['items', 'employee'])
            ->where('is_active', true)
            ->latest();

        if ($employee->grade === 'STAFF') {
            $purchaseRequests = $purchaseRequests->where('created_by', $employee->nama_lengkap);
        }

        if ($employee->grade === 'SUPERVISOR' || $employee->grade === 'MANAGER') {
            $creator = GetBawahan::where('id', $employee->id)->get()->pluck('nama_lengkap')->toArray();
            $creator[] = $employee->nama_lengkap;

            $purchaseRequests = $purchaseRequests->whereIn('created_by', $creator);
        }

        $scope = $request->input('scope', 'ongoing');

        if ($scope === 'completed') {
            $purchaseRequests = $purchaseRequests->where(function ($query) {
                $query->where('status', 'Done')
                    ->orWhere('finance_status', 'Distributed');
            });
        } else {
            $purchaseRequests = $purchaseRequests->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('status', '!=', 'Done')
                        ->where(function ($q2) {
                            $q2->whereNull('finance_status')
                                ->orWhere('finance_status', '!=', 'Distributed');
                        });
                });
            });
        }

        return DataTables::of($purchaseRequests)
            ->addColumn('item_name', fn($row) => optional($row->items->first())->item_name)
            ->addColumn('quantity', fn($row) => optional($row->items->first())->quantity)
            ->addColumn('unit', fn($row) => optional($row->items->first())->unit)
            ->addColumn('can_approve', fn($row) => $this->canUserApprove($employee, $row))
            ->addColumn('can_void', fn($row) => $this->canUserVoid($employee, $row))
            ->addColumn('can_receive_goods', fn($row) => $this->canUserReceiveGoods($employee, $row))
            ->addColumn('display_status', fn($row) => $this->resolveDisplayStatus($row))
            ->make(true);
    }

    public function save(Request $request)
    {
        $isUpdateMode = $request->filled('id');

        $purchaseRequest = $isUpdateMode ? PurchaseRequest::find($request->id) : new PurchaseRequest;

        if ($isUpdateMode && !$purchaseRequest) {
            return response()->json(['message' => 'Permintaan pembelian barang tidak ditemukan'], 404);
        }

        if ($isUpdateMode && !in_array($purchaseRequest->status, ['Pending', 'Reopened'])) {
            return response()->json(['message' => 'Permintaan hanya dapat diubah saat status Pending atau Reopened'], 422);
        }

        if ($isUpdateMode) {
            $purchaseRequest->updated_by = $this->karyawan;
            $purchaseRequest->updated_at = date('Y-m-d H:i:s');
        } else {
            $purchaseRequest->request_number = $this->generateRequestNumber();
            $purchaseRequest->created_by = $this->karyawan;
            $purchaseRequest->created_at = date('Y-m-d H:i:s');
        }

        $purchaseRequest->priority = $request->priority;
        $purchaseRequest->purpose = $request->purpose;
        $purchaseRequest->save();

        $itemData = [
            'item_name' => $request->item_name,
            'item_code' => $request->item_code ?: '',
            'brand_name' => $request->brand_name,
            'quantity' => $request->quantity,
            'unit' => $request->unit,
            'note' => $request->note,
        ];

        $attachments = $this->handleAttachments($request, $isUpdateMode ? optional($purchaseRequest->items()->first())->attachment : null);

        if ($attachments === false) {
            return response()->json(['message' => 'Lampiran harus berupa gambar dengan ukuran maksimal 2MB per file'], 422);
        }

        $itemData['attachment'] = $this->encodeAttachments($attachments);

        if ($isUpdateMode) {
            $purchaseRequestItem = $purchaseRequest->items()->first();
            if ($purchaseRequestItem) {
                $purchaseRequestItem->fill($itemData);
                $purchaseRequestItem->updated_by = $this->karyawan;
                $purchaseRequestItem->updated_at = date('Y-m-d H:i:s');
                $purchaseRequestItem->save();
            } else {
                $itemData['created_by'] = $this->karyawan;
                $itemData['created_at'] = date('Y-m-d H:i:s');
                $purchaseRequest->items()->create($itemData);
            }
        } else {
            $itemData['created_by'] = $this->karyawan;
            $itemData['created_at'] = date('Y-m-d H:i:s');
            $purchaseRequest->items()->create($itemData);

            $employee = $request->attributes->get('user')->karyawan;
            $this->applyInitialApproval($purchaseRequest, $employee);
        }

        return response()->json(['message' => "Permintaan pembelian barang berhasil " . ($isUpdateMode ? 'diupdate' : 'diajukan')], 201);
    }

    public function delete(Request $request)
    {
        $purchaseRequest = PurchaseRequest::findOrFail($request->id);
        $employee = $request->attributes->get('user')->karyawan;

        if (!$this->canUserVoid($employee, $purchaseRequest)) {
            return response()->json(['message' => 'Permintaan tidak dapat divoid pada tahap ini'], 422);
        }

        $purchaseRequest->deleted_by = $this->karyawan;
        $purchaseRequest->deleted_at = date('Y-m-d H:i:s');
        $purchaseRequest->is_active = false;
        $purchaseRequest->save();

        $purchaseRequest->items()->update([
            'deleted_by' => $this->karyawan,
            'deleted_at' => date('Y-m-d H:i:s'),
            'is_active' => false,
        ]);

        return response()->json(['message' => 'Permintaan pembelian barang berhasil divoid'], 201);
    }

    public function reopen(Request $request)
    {
        $purchaseRequest = PurchaseRequest::findOrFail($request->id);
        $purchaseRequest->status = 'Reopened';
        $purchaseRequest->rejection_note = null;
        $purchaseRequest->rejected_by = null;
        $purchaseRequest->rejected_at = null;
        $purchaseRequest->approved_by = null;
        $purchaseRequest->approved_at = null;
        $purchaseRequest->finance_status = null;
        $purchaseRequest->rejection_finance_note = null;
        $purchaseRequest->rejected_finance_by = null;
        $purchaseRequest->rejected_finance_at = null;
        $purchaseRequest->save();

        $purchaseRequest->items()->update([
            'rejection_note' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejection_finance_note' => null,
            'rejected_finance_by' => null,
            'rejected_finance_at' => null,
        ]);

        $employee = $request->attributes->get('user')->karyawan;
        $creator = MasterKaryawan::where('nama_lengkap', $purchaseRequest->created_by)->where('is_active', 1)->first();
        $flow = $creator ? $this->resolveApprovalFlow($creator) : ['approver_ids' => []];

        if ($flow['mode'] !== 'auto' && !empty($flow['approver_ids'])) {
            Notification::whereIn('id', $flow['approver_ids'])
                ->title('Permintaan Pembelian Barang!')
                ->message("Terdapat Permintaan Pembelian Barang yang telah direopen oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/request/purchase-requests')
                ->send();
        }

        return response()->json(['message' => 'Reopened successfully'], 201);
    }

    public function confirmReceiveGoods(Request $request)
    {
        $purchaseRequest = PurchaseRequest::findOrFail($request->id);
        $employee = $request->attributes->get('user')->karyawan;

        if (!$this->canUserReceiveGoods($employee, $purchaseRequest)) {
            return response()->json(['message' => 'Permintaan tidak dapat dikonfirmasi penerimaan barang'], 422);
        }

        $now = date('Y-m-d H:i:s');

        $purchaseRequest->finance_status = 'Distributed';
        $purchaseRequest->status = 'Done';
        $purchaseRequest->completed_by = $this->karyawan;
        $purchaseRequest->completed_at = $now;
        $purchaseRequest->save();

        if ($purchaseRequest->user_receipt_by) {
            Notification::where('nama_lengkap', $purchaseRequest->user_receipt_by)
                ->title('Barang Telah Diterima User!')
                ->message("Barang untuk permintaan {$purchaseRequest->request_number} ({$purchaseRequest->handover_number}) telah diterima oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/finance/purchasing/purchase-report')
                ->send();
        }

        return response()->json(['message' => 'Barang berhasil dikonfirmasi diterima'], 200);
    }

    public function process(Request $request)
    {
        $parent = PurchaseRequest::with('items')->findOrFail($request->data['parent_id']);
        $employee = $request->attributes->get('user')->karyawan;

        if (!$this->canUserApprove($employee, $parent)) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk memproses permintaan ini'], 403);
        }

        $item = $parent->items->first();

        if ($request->action === 'approve') {
            $parent->status = 'Approved';
            $parent->approved_by = $this->karyawan;
            $parent->approved_at = date('Y-m-d H:i:s');
            $parent->rejection_note = null;
            $parent->rejected_by = null;
            $parent->rejected_at = null;
            $parent->finance_status = 'Waiting to Delegate';

            if ($item) {
                $item->approved_by = $this->karyawan;
                $item->approved_at = date('Y-m-d H:i:s');
                $item->rejection_note = null;
                $item->rejected_by = null;
                $item->rejected_at = null;
                $item->save();
            }

            Notification::where('nama_lengkap', $parent->created_by)
                ->title('Permintaan Pembelian Barang Disetujui!')
                ->message("Permintaan Pembelian Barang yang anda ajukan telah disetujui oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/request/purchase-requests')
                ->send();

            Notification::whereIn('id_jabatan', [45, 48])
                ->title('Permintaan Pembelian Barang Diajukan!')
                ->message("Terdapat Permintaan Pembelian Barang yang diajukan oleh {$parent->approved_by} pada " . date('d-m-Y'))
                ->url('/finance/purchasing/purchase-request-approval')
                ->send();
        }

        if ($request->action === 'reject') {
            $parent->status = 'Rejected';
            $parent->rejection_note = $request->data['reason'];
            $parent->rejected_by = $this->karyawan;
            $parent->rejected_at = date('Y-m-d H:i:s');

            if ($item) {
                $item->rejection_note = $request->data['reason'];
                $item->rejected_by = $this->karyawan;
                $item->rejected_at = date('Y-m-d H:i:s');
                $item->save();
            }

            Notification::where('nama_lengkap', $parent->created_by)
                ->title('Permintaan Pembelian Barang Ditolak!')
                ->message("Permintaan Pembelian Barang yang anda ajukan telah ditolak oleh {$employee->nama_lengkap} pada " . date('d-m-Y') . " dengan alasan: " . $request->data['reason'])
                ->url('/request/purchase-requests')
                ->send();
        }

        $parent->save();

        return response()->json(['message' => "Permintaan pembelian barang berhasil di{$request->action}"], 201);
    }

    public function exportPdf(Request $request)
    {
        $purchaseRequest = PurchaseRequest::with(['items', 'employee'])->findOrFail($request->id);
        $signatures = $this->buildSignatureBlocks($purchaseRequest);

        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);

        $html = view('pdf.purchase-request', compact('purchaseRequest', 'signatures'))->render();
        $mpdf->WriteHTML($html);

        $pdfString = $mpdf->Output('', 'S');

        return response()->json([
            'data' => base64_encode($pdfString),
            'message' => 'PDF generated successfully'
        ], 200);
    }

    private function buildSignatureBlocks(PurchaseRequest $purchaseRequest): array
    {
        $item = $purchaseRequest->items->first();

        $submittedName = $purchaseRequest->created_by ?: optional($purchaseRequest->employee)->nama_lengkap;
        $approvedName = $purchaseRequest->approved_by ?: optional($item)->approved_by;

        $submitted = $this->resolveSigner($submittedName, optional($purchaseRequest->employee)->jabatan);
        $approved = $this->resolveSigner($approvedName);
        $acc = $this->resolveSigner($purchaseRequest->delegated_by);
        $processed = $this->resolveSigner($purchaseRequest->processed_by);

        $blocks = [
            [
                'label' => 'Diajukan Oleh',
                'name' => $submitted['name'],
                'position' => $submitted['position'],
            ],
        ];

        $sameAsSubmitter = $approved['name']
            && strcasecmp(trim($approved['name']), trim($submitted['name'])) === 0
            && strcasecmp(trim($approved['position']), trim($submitted['position'])) === 0;

        if (!$sameAsSubmitter) {
            $blocks[] = [
                'label' => 'Disetujui Oleh',
                'name' => $approved['name'],
                'position' => $approved['position'],
            ];
        }

        $blocks[] = [
            'label' => 'ACC',
            'name' => $acc['name'],
            'position' => $acc['position'],
        ];

        $blocks[] = [
            'label' => 'Diproses Oleh',
            'name' => $processed['name'],
            'position' => $processed['position'],
        ];

        return $blocks;
    }

    private function resolveDisplayStatus($row): string
    {
        if ($row->finance_status === 'Rejected') {
            return 'Ditolak Purchasing';
        }

        if ($row->status === 'Rejected') {
            return 'Ditolak Atasan';
        }

        if (in_array($row->status, ['Pending', 'Reopened'])) {
            return 'Menunggu Persetujuan Atasan';
        }

        if ($row->status === 'Done' || $row->finance_status === 'Distributed') {
            return 'Barang Diterima';
        }

        if ($row->finance_status === 'Distributing') {
            return 'Barang Sedang Didistribusikan';
        }

        if ($row->finance_status === 'On Process' || $row->finance_status === 'Pending') {
            return 'Dalam Proses';
        }

        if ($row->finance_status === 'Waiting Vendor Receipt' || $row->finance_status === 'Waiting User Receipt') {
            return 'Dalam Proses';
        }

        if (
            in_array($row->finance_status, ['Waiting Process', 'Waiting to Create PO', 'PO Created'])
        ) {
            return 'Menunggu Proses';
        }

        if (
            $row->status === 'Approved'
            && (!$row->finance_status || $row->finance_status === 'Waiting to Delegate')
        ) {
            return 'Menunggu Persetujuan Purchasing';
        }

        if ($row->status === 'Partially Approved') {
            return 'Menunggu Persetujuan Purchasing';
        }

        return 'Menunggu Persetujuan Atasan';
    }

    private function resolveSigner(?string $name, ?string $fallbackPosition = null): array
    {
        if (!$name) {
            return ['name' => '', 'position' => ''];
        }

        $employee = MasterKaryawan::where('nama_lengkap', $name)->where('is_active', 1)->first();

        return [
            'name' => $name,
            'position' => $employee->jabatan ?? $fallbackPosition ?? '',
        ];
    }

    private function generateRequestNumber(): string
    {
        $year = date('y');
        $month = self::ROMAN_MONTHS[date('m')];
        $prefix = "ISL/PR/{$year}-{$month}/";

        $latest = PurchaseRequest::where('request_number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(request_number, "/", -1) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = 1;
        if ($latest) {
            $lastPart = substr($latest->request_number, strrpos($latest->request_number, '/') + 1);
            $nextNumber = (int) $lastPart + 1;
        }

        $padLength = max(4, strlen((string) $nextNumber));

        return $prefix . str_pad($nextNumber, $padLength, '0', STR_PAD_LEFT);
    }

    private function resolveApprovalFlow($employee): array
    {
        if ($employee->grade === 'MANAGER') {
            return ['mode' => 'auto', 'approver_ids' => []];
        }

        $manager = $this->findApproverManager($employee);
        if ($manager) {
            return ['mode' => 'manager', 'approver_ids' => [(int) $manager->id]];
        }

        if ($employee->grade === 'STAFF') {
            $supervisor = $this->findDirectSupervisor($employee);
            if ($supervisor) {
                return ['mode' => 'supervisor', 'approver_ids' => [(int) $supervisor->id]];
            }
        }

        if ($employee->grade === 'SUPERVISOR') {
            return ['mode' => 'auto', 'approver_ids' => []];
        }

        $atasanIds = json_decode($employee->atasan_langsung, true) ?? [];
        $approverIds = MasterKaryawan::whereIn('id', $atasanIds)
            ->where('is_active', 1)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        return ['mode' => 'supervisor', 'approver_ids' => $approverIds];
    }

    private function findApproverManager($employee): ?MasterKaryawan
    {
        $chain = GetAtasan::where('id', $employee->id)->get();

        return $chain->first(function ($person) use ($employee) {
            return (int) $person->id !== (int) $employee->id && $person->grade === 'MANAGER';
        });
    }

    private function findDirectSupervisor($employee): ?MasterKaryawan
    {
        $atasanIds = json_decode($employee->atasan_langsung, true) ?? [];

        if (empty($atasanIds)) {
            return null;
        }

        return MasterKaryawan::whereIn('id', $atasanIds)
            ->where('is_active', 1)
            ->where('grade', 'SUPERVISOR')
            ->first();
    }

    private function applyInitialApproval(PurchaseRequest $purchaseRequest, $employee): void
    {
        $flow = $this->resolveApprovalFlow($employee);

        if ($flow['mode'] === 'auto') {
            $purchaseRequest->status = 'Approved';
            $purchaseRequest->approved_by = $employee->nama_lengkap;
            $purchaseRequest->approved_at = date('Y-m-d H:i:s');
            $purchaseRequest->finance_status = 'Waiting to Delegate';
            $purchaseRequest->save();

            $item = $purchaseRequest->items()->first();
            if ($item) {
                $item->approved_by = $employee->nama_lengkap;
                $item->approved_at = date('Y-m-d H:i:s');
                $item->save();
            }

            Notification::whereIn('id_jabatan', [45, 48])
                ->title('Permintaan Pembelian Barang Diajukan!')
                ->message("Terdapat Permintaan Pembelian Barang yang diajukan oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/finance/purchasing/purchase-request-approval')
                ->send();

            return;
        }

        $purchaseRequest->status = 'Pending';
        $purchaseRequest->save();

        if (!empty($flow['approver_ids'])) {
            Notification::whereIn('id', $flow['approver_ids'])
                ->title('Permintaan Pembelian Barang!')
                ->message("Terdapat Permintaan Pembelian Barang baru yang diajukan oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/request/purchase-requests')
                ->send();
        }
    }

    private function canUserApprove($viewer, $purchaseRequest): bool
    {
        if (!in_array($purchaseRequest->status, ['Pending', 'Reopened'])) {
            return false;
        }

        $creator = MasterKaryawan::where('nama_lengkap', $purchaseRequest->created_by)->where('is_active', 1)->first();
        if (!$creator) {
            return false;
        }

        $flow = $this->resolveApprovalFlow($creator);

        if ($flow['mode'] === 'auto') {
            return false;
        }

        return in_array((int) $viewer->id, array_map('intval', $flow['approver_ids'] ?? []), true);
    }

    private function canUserVoid($employee, $purchaseRequest): bool
    {
        if ($purchaseRequest->created_by !== $employee->nama_lengkap) {
            return false;
        }

        if ($purchaseRequest->finance_status === 'Rejected') {
            return true;
        }

        if (
            $purchaseRequest->delegated_at
            || in_array($purchaseRequest->finance_status, [
                'Waiting to Create PO',
                'PO Created',
                'Waiting Process',
                'On Process',
                'Pending',
                'Distributing',
                'Distributed',
            ])
            || $purchaseRequest->status === 'Done'
        ) {
            return false;
        }

        if ($purchaseRequest->status === 'Rejected') {
            return false;
        }

        return in_array($purchaseRequest->status, ['Pending', 'Reopened', 'Approved', 'Partially Approved']);
    }

    private function canUserReceiveGoods($employee, $purchaseRequest): bool
    {
        return $purchaseRequest->finance_status === 'Distributing'
            && $purchaseRequest->created_by === $employee->nama_lengkap;
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
                $path = public_path('purchase-requests/' . $file);
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

            $destinationPath = public_path('purchase-requests');
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
}
