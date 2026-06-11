<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use DataTables;

use Mpdf;
use App\Services\{KaryawanProfileService, Notification, GetBawahan, PurchaseReceiptService, PurchaseRequestApprovalService};

use App\Models\{PurchaseReceiptBatch, PurchaseRequest, PurchaseRequestItem, MasterKaryawan};

class PurchaseRequestsController extends Controller
{
    private const VENDOR_ATTACHMENT_DIR = 'goods-receipt/vendor';

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
            ->where(function ($query) {
                $query->where('is_goods_voided', false)
                    ->orWhereNull('is_goods_voided');
            })
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
            ->addColumn('receipt_target_qty', fn($row) => PurchaseReceiptService::resolveTargetQty($row))
            ->addColumn('receipt_progress', fn($row) => $this->formatReceiptProgress($row))
            ->addColumn('handover_count', fn($row) => PurchaseReceiptService::countHandoverBatches($row))
            ->addColumn('unconfirmed_handover_count', fn($row) => $this->countUnconfirmedHandovers($row))
            ->addColumn('can_approve', fn($row) => PurchaseRequestApprovalService::canUserApprove($employee, $row))
            ->addColumn('approval_progress', fn($row) => PurchaseRequestApprovalService::formatProgress($row))
            ->addColumn('can_void', fn($row) => $this->canUserVoid($employee, $row))
            ->addColumn('can_receive_goods', fn($row) => $this->canUserReceiveGoods($employee, $row))
            ->addColumn('display_status', fn($row) => $this->resolveDisplayStatus($row))
            ->make(true);
    }

    public function show(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        $purchaseRequest = PurchaseRequest::with(['items', 'employee.jabatan', 'employee.divisi'])
            ->where('is_active', true)
            ->findOrFail($request->id);

        if ($employee->grade === 'STAFF' && $purchaseRequest->created_by !== $employee->nama_lengkap) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke permintaan ini'], 403);
        }

        if ($employee->grade === 'SUPERVISOR' || $employee->grade === 'MANAGER') {
            $creator = GetBawahan::where('id', $employee->id)->get()->pluck('nama_lengkap')->toArray();
            $creator[] = $employee->nama_lengkap;

            if (!in_array($purchaseRequest->created_by, $creator, true)) {
                return response()->json(['message' => 'Anda tidak memiliki akses ke permintaan ini'], 403);
            }
        }

        $targetQty = PurchaseReceiptService::resolveTargetQty($purchaseRequest);
        $receiptBatches = PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequest->id)
            ->orderBy('batch_no')
            ->get()
            ->map(fn($batch) => PurchaseReceiptService::formatBatch($batch, self::VENDOR_ATTACHMENT_DIR));

        $pendingConfirmBatches = $receiptBatches
            ->filter(fn($batch) => !empty($batch['handover_number']) && empty($batch['completed_at']))
            ->values();

        $handoverHistory = $receiptBatches
            ->filter(fn($batch) => !empty($batch['handover_number']))
            ->values();

        if ($handoverHistory->isEmpty() && $purchaseRequest->handover_number) {
            $handoverHistory = collect([[
                'id' => null,
                'batch_no' => 1,
                'vendor_receipt_qty' => $purchaseRequest->vendor_receipt_qty,
                'vendor_delivery_note' => $purchaseRequest->vendor_delivery_note,
                'vendor_receipt_note' => $purchaseRequest->vendor_receipt_note,
                'vendor_receipt_by' => $purchaseRequest->vendor_receipt_by,
                'vendor_receipt_at' => $purchaseRequest->vendor_receipt_at,
                'attachments' => array_map(function ($filename) {
                    return [
                        'filename' => $filename,
                        'url' => self::VENDOR_ATTACHMENT_DIR . '/' . $filename,
                    ];
                }, PurchaseReceiptService::parseAttachments($purchaseRequest->vendor_receipt_attachments)),
                'handover_number' => $purchaseRequest->handover_number,
                'user_handover_qty' => $purchaseRequest->vendor_receipt_qty,
                'user_receipt_note' => $purchaseRequest->user_receipt_note,
                'user_receipt_by' => $purchaseRequest->user_receipt_by,
                'user_receipt_at' => $purchaseRequest->user_receipt_at,
                'completed_by' => $purchaseRequest->completed_by,
                'completed_at' => $purchaseRequest->completed_at,
                'is_partial' => false,
            ]]);
        }

        if ($receiptBatches->isEmpty() && (float) ($purchaseRequest->vendor_received_total ?? $purchaseRequest->vendor_receipt_qty ?? 0) > 0) {
            $receiptBatches = $handoverHistory;
        }

        $approvalChain = PurchaseRequestApprovalService::parseChain($purchaseRequest->approval_chain);
        $approvalLog = PurchaseRequestApprovalService::parseLog($purchaseRequest->approval_log);

        return response()->json([
            'data' => [
                'purchase_request' => $purchaseRequest,
                'approval_chain' => $approvalChain,
                'approval_log' => $approvalLog,
                'approval_progress' => PurchaseRequestApprovalService::formatProgress($purchaseRequest),
                'requester_jabatan' => KaryawanProfileService::resolveJabatan($purchaseRequest->employee),
                'requester_divisi' => KaryawanProfileService::resolveDivisi($purchaseRequest->employee),
                'receipt_summary' => [
                    'target_qty' => $targetQty,
                    'vendor_received_total' => $purchaseRequest->vendor_received_total ?? 0,
                    'user_handed_total' => $purchaseRequest->user_handed_total ?? 0,
                    'user_confirmed_total' => $purchaseRequest->user_confirmed_total ?? 0,
                    'remaining_confirm_qty' => max(round($targetQty - (float) ($purchaseRequest->user_confirmed_total ?? 0), 2), 0),
                    'remaining_vendor_qty' => PurchaseReceiptService::getRemainingVendorQty($purchaseRequest),
                ],
                'receipt_batches' => $receiptBatches,
                'handover_history' => $handoverHistory,
                'pending_confirm_batches' => $pendingConfirmBatches,
                'can_receive_goods' => $this->canUserReceiveGoods($employee, $purchaseRequest),
            ],
            'message' => 'Detail permintaan berhasil diambil',
        ], 200);
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
        $purchaseRequest->tanggal_kedatangan = $request->filled('tanggal_kedatangan')
            ? $request->tanggal_kedatangan
            : null;
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
        $purchaseRequest->approval_step = 0;
        $purchaseRequest->approval_log = null;
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

        if ($creator) {
            $plan = PurchaseRequestApprovalService::buildApprovalPlan($creator);
            PurchaseRequestApprovalService::initializeApprovalState($purchaseRequest, $plan);
            $purchaseRequest->save();
            $this->notifyNextApprover($purchaseRequest, $employee->nama_lengkap, true);
        }

        return response()->json(['message' => 'Reopened successfully'], 201);
    }

    public function confirmReceiveGoods(Request $request)
    {
        $confirmNote = trim((string) $request->input('user_confirm_note', ''));
        if ($confirmNote === '') {
            return response()->json(['message' => 'Catatan penerimaan wajib diisi'], 422);
        }

        $purchaseRequest = PurchaseRequest::findOrFail($request->id);
        $employee = $request->attributes->get('user')->karyawan;

        if (!$this->canUserReceiveGoods($employee, $purchaseRequest)) {
            return response()->json(['message' => 'Permintaan tidak dapat dikonfirmasi penerimaan barang'], 422);
        }

        $batchQuery = \App\Models\PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequest->id)
            ->whereNotNull('handover_number')
            ->whereNull('completed_at')
            ->orderBy('batch_no');

        $batch = $request->batch_id
            ? $batchQuery->where('id', $request->batch_id)->first()
            : $batchQuery->first();

        if (!$batch) {
            return response()->json(['message' => 'Tidak ada serah terima yang menunggu konfirmasi'], 422);
        }

        $now = date('Y-m-d H:i:s');

        $batch->user_confirm_note = $confirmNote;
        $batch->completed_by = $this->karyawan;
        $batch->completed_at = $now;
        $batch->save();

        $purchaseRequest = \App\Services\PurchaseReceiptService::refreshTotals($purchaseRequest);

        $targetQty = \App\Services\PurchaseReceiptService::resolveTargetQty($purchaseRequest);
        $isComplete = (float) $purchaseRequest->user_confirmed_total >= $targetQty
            && (float) $purchaseRequest->vendor_received_total >= $targetQty;

        if ($purchaseRequest->user_receipt_by) {
            Notification::where('nama_lengkap', $purchaseRequest->user_receipt_by)
                ->title($isComplete ? 'Barang Telah Diterima User!' : 'Barang Parsial Diterima User!')
                ->message("Barang sebanyak {$batch->user_handover_qty} untuk permintaan {$purchaseRequest->request_number} ({$batch->handover_number}) telah diterima oleh {$employee->nama_lengkap} pada " . date('d-m-Y') . ". Catatan: {$confirmNote}")
                ->url('/finance/purchasing/purchase-report')
                ->send();
        }

        return response()->json([
            'message' => $isComplete
                ? 'Seluruh barang berhasil dikonfirmasi diterima'
                : 'Penerimaan parsial berhasil dikonfirmasi. Menunggu sisa barang.',
        ], 200);
    }

    public function rejectGoods(Request $request)
    {
        $voidNote = trim((string) $request->input('goods_void_note', ''));
        if ($voidNote === '') {
            return response()->json(['message' => 'Keterangan penolakan barang wajib diisi'], 422);
        }

        $purchaseRequest = PurchaseRequest::findOrFail($request->id);
        $employee = $request->attributes->get('user')->karyawan;

        if (!$this->canUserReceiveGoods($employee, $purchaseRequest)) {
            return response()->json(['message' => 'Permintaan tidak dapat ditolak pada tahap ini'], 422);
        }

        $now = date('Y-m-d H:i:s');

        $purchaseRequest->is_goods_voided = true;
        $purchaseRequest->goods_voided_by = $this->karyawan;
        $purchaseRequest->goods_voided_at = $now;
        $purchaseRequest->goods_void_note = $voidNote;
        $purchaseRequest->finance_status = 'Void';
        $purchaseRequest->status = 'Void';
        $purchaseRequest->updated_by = $this->karyawan;
        $purchaseRequest->updated_at = $now;
        $purchaseRequest->save();

        Notification::whereIn('id_jabatan', [45, 48])
            ->title('Purchase Request Di-void (Tolak Barang)!')
            ->message("Permintaan {$purchaseRequest->request_number} divoid karena user menolak barang oleh {$employee->nama_lengkap} pada " . date('d-m-Y') . ". Alasan: {$voidNote}")
            ->url('/finance/purchasing/purchase-request-void')
            ->send();

        if ($purchaseRequest->user_receipt_by) {
            Notification::where('nama_lengkap', $purchaseRequest->user_receipt_by)
                ->title('Purchase Request Di-void (Tolak Barang)!')
                ->message("Permintaan {$purchaseRequest->request_number} divoid karena user menolak barang. Alasan: {$voidNote}")
                ->url('/finance/purchasing/purchase-request-void')
                ->send();
        }

        return response()->json([
            'message' => 'Permintaan pembelian berhasil divoid karena barang ditolak',
        ], 200);
    }

    public function process(Request $request)
    {
        $parent = PurchaseRequest::with('items')->findOrFail($request->data['parent_id']);
        $employee = $request->attributes->get('user')->karyawan;

        if (!PurchaseRequestApprovalService::canUserApprove($employee, $parent)) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk memproses permintaan ini'], 403);
        }

        $item = $parent->items->first();

        if ($request->action === 'approve') {
            $now = date('Y-m-d H:i:s');
            $chain = PurchaseRequestApprovalService::parseChain($parent->approval_chain);
            $step = (int) ($parent->approval_step ?? 0);
            $log = PurchaseRequestApprovalService::parseLog($parent->approval_log);

            $log[] = [
                'step' => $step,
                'by' => $this->karyawan,
                'at' => $now,
            ];
            $parent->approval_log = PurchaseRequestApprovalService::encodeLog($log);
            $parent->rejection_note = null;
            $parent->rejected_by = null;
            $parent->rejected_at = null;

            $isFinalApproval = empty($chain) || ($step + 1) >= count($chain);

            if ($isFinalApproval) {
                $parent->status = 'Approved';
                $parent->approved_by = $this->karyawan;
                $parent->approved_at = $now;
                $parent->finance_status = 'Waiting to Delegate';
                $parent->approval_step = count($chain);

                if ($item) {
                    $item->approved_by = $this->karyawan;
                    $item->approved_at = $now;
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
            } else {
                $parent->status = 'Partially Approved';
                $parent->approval_step = $step + 1;
                $parent->finance_status = null;

                $nextApprover = PurchaseRequestApprovalService::getCurrentApprover($parent);
                $layerLabel = '';

                Notification::where('nama_lengkap', $parent->created_by)
                    ->title('Permintaan Pembelian Barang — Persetujuan Bertahap')
                    ->message("Permintaan anda telah disetujui oleh {$employee->nama_lengkap} pada " . date('d-m-Y') . ". Menunggu persetujuan atasan berikutnya{$layerLabel}.")
                    ->url('/request/purchase-requests')
                    ->send();

                if ($nextApprover) {
                    Notification::where('id', $nextApprover['id'])
                        ->title('Permintaan Pembelian Barang!')
                        ->message("Terdapat Permintaan Pembelian Barang yang menunggu persetujuan Anda (Lapis " . ($parent->approval_step + 1) . '/' . count($chain) . ") dari {$parent->created_by} pada " . date('d-m-Y'))
                        ->url('/request/purchase-requests')
                        ->send();
                }
            }
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
        if ($row->is_goods_voided || $row->finance_status === 'Void') {
            return 'Void - Tolak Barang';
        }

        if ($row->finance_status === 'Rejected') {
            return 'Ditolak Purchasing';
        }

        if ($row->status === 'Rejected') {
            return 'Ditolak Atasan';
        }

        if (in_array($row->status, ['Pending', 'Reopened', 'Partially Approved'])) {
            return PurchaseRequestApprovalService::formatDisplayStatus($row)
                ?? 'Menunggu Persetujuan Atasan';
        }

        if ($row->status === 'Done' || $row->finance_status === 'Distributed') {
            $target = PurchaseReceiptService::resolveTargetQty($row);
            $confirmed = (float) ($row->user_confirmed_total ?? 0);

            if ($target > 0 && $confirmed > 0) {
                return "Barang Diterima ({$confirmed}/{$target})";
            }

            return 'Barang Diterima';
        }

        if ($row->finance_status === 'Distributing') {
            $target = PurchaseReceiptService::resolveTargetQty($row);
            $confirmed = (float) ($row->user_confirmed_total ?? 0);

            if ($target > 0 && $confirmed > 0 && $confirmed < $target) {
                return "Barang Parsial — Konfirmasi ({$confirmed}/{$target})";
            }

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

    private function applyInitialApproval(PurchaseRequest $purchaseRequest, $employee): void
    {
        $plan = PurchaseRequestApprovalService::buildApprovalPlan($employee);

        if ($plan['mode'] === 'auto') {
            $purchaseRequest->status = 'Approved';
            $purchaseRequest->approved_by = $employee->nama_lengkap;
            $purchaseRequest->approved_at = date('Y-m-d H:i:s');
            $purchaseRequest->finance_status = 'Waiting to Delegate';
            $purchaseRequest->approval_step = 0;
            $purchaseRequest->approval_chain = null;
            $purchaseRequest->approval_log = null;
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

        PurchaseRequestApprovalService::initializeApprovalState($purchaseRequest, $plan);
        $purchaseRequest->status = 'Pending';
        $purchaseRequest->save();

        $this->notifyNextApprover($purchaseRequest, $employee->nama_lengkap);
    }

    private function notifyNextApprover(PurchaseRequest $purchaseRequest, string $actorName, bool $isReopen = false): void
    {
        $nextApprover = PurchaseRequestApprovalService::getCurrentApprover($purchaseRequest);
        if (!$nextApprover) {
            return;
        }

        $chain = PurchaseRequestApprovalService::parseChain($purchaseRequest->approval_chain);
        $step = (int) ($purchaseRequest->approval_step ?? 0);
        $layerLabel = '';

        $actionLabel = $isReopen ? 'telah direopen' : 'baru';

        Notification::where('id', $nextApprover['id'])
            ->title('Permintaan Pembelian Barang!')
            ->message("Terdapat Permintaan Pembelian Barang yang {$actionLabel} diajukan oleh {$purchaseRequest->created_by}{$layerLabel} pada " . date('d-m-Y'))
            ->url('/request/purchase-requests')
            ->send();
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
            && $purchaseRequest->created_by === $employee->nama_lengkap
            && PurchaseReceiptService::hasUnconfirmedHandover($purchaseRequest);
    }

    private function formatReceiptProgress($row): string
    {
        $target = PurchaseReceiptService::resolveTargetQty($row);
        if ($target <= 0) {
            return '-';
        }

        $confirmed = (float) ($row->user_confirmed_total ?? 0);

        return "{$confirmed}/{$target}";
    }

    private function countUnconfirmedHandovers($row): int
    {
        return (int) PurchaseReceiptBatch::where('purchase_request_id', $row->id)
            ->whereNotNull('handover_number')
            ->whereNull('completed_at')
            ->count();
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
