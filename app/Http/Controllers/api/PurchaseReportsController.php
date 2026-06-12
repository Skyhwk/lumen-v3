<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrderDocument;
use App\Models\PurchaseOrderDocumentRevision;
use App\Models\PurchaseReceiptBatch;
use App\Models\PurchaseRequest;
use App\Services\KaryawanProfileService;
use App\Services\PurchaseReceiptService;
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;

class PurchaseReportsController extends Controller
{
    private const VENDOR_ATTACHMENT_DIR = 'goods-receipt/vendor';
    private const PR_ATTACHMENT_DIR = 'purchase-requests';

    public function indexVoid(Request $request)
    {
        $purchaseRequests = PurchaseRequest::with(['items', 'employee.jabatan', 'employee.divisi'])
            ->where('is_active', true)
            ->where('is_goods_voided', true)
            ->latest('goods_voided_at');

        return DataTables::of($purchaseRequests)
            ->addColumn('item_name', fn($row) => optional($row->items->first())->item_name)
            ->addColumn('quantity', fn($row) => optional($row->items->first())->quantity)
            ->addColumn('unit', fn($row) => optional($row->items->first())->unit)
            ->addColumn('requester_name', fn($row) => $row->created_by ?: '-')
            ->addColumn('requester_jabatan', fn($row) => KaryawanProfileService::resolveJabatan($row->employee))
            ->addColumn('requester_divisi', fn($row) => KaryawanProfileService::resolveDivisi($row->employee))
            ->filterColumn('item_name', function ($query, $keyword) {
                $query->whereHas('items', function ($sub) use ($keyword) {
                    $sub->where('item_name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('requester_name', function ($query, $keyword) {
                $query->where('created_by', 'like', "%{$keyword}%");
            })
            ->filterColumn('goods_void_note', function ($query, $keyword) {
                $query->where('goods_void_note', 'like', "%{$keyword}%");
            })
            ->filterColumn('goods_voided_by', function ($query, $keyword) {
                $query->where('goods_voided_by', 'like', "%{$keyword}%");
            })
            ->make(true);
    }

    public function index(Request $request)
    {
        $purchaseRequests = PurchaseRequest::with(['items', 'employee.jabatan', 'employee.divisi'])
            ->where('is_active', true)
            ->where('finance_status', 'Distributed')
            ->where(function ($query) {
                $query->where('is_goods_voided', false)
                    ->orWhereNull('is_goods_voided');
            })
            ->latest();

        return DataTables::of($purchaseRequests)
            ->addColumn('item_name', fn($row) => optional($row->items->first())->item_name)
            ->addColumn('quantity', fn($row) => optional($row->items->first())->quantity)
            ->addColumn('unit', fn($row) => optional($row->items->first())->unit)
            ->addColumn('vendor_receipt_qty', fn($row) => $row->vendor_received_total ?? $row->vendor_receipt_qty)
            ->addColumn('handover_number', fn($row) => $row->handover_number)
            ->addColumn('requester_name', fn($row) => $row->created_by ?: '-')
            ->addColumn('requester_jabatan', fn($row) => KaryawanProfileService::resolveJabatan($row->employee))
            ->addColumn('requester_divisi', fn($row) => KaryawanProfileService::resolveDivisi($row->employee))
            ->addColumn('finance_display_status', fn() => 'Completed')
            ->filterColumn('item_name', function ($query, $keyword) {
                $query->whereHas('items', function ($sub) use ($keyword) {
                    $sub->where('item_name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('quantity', function ($query, $keyword) {
                $query->whereHas('items', function ($sub) use ($keyword) {
                    $sub->where('quantity', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('requester_name', function ($query, $keyword) {
                $query->where('created_by', 'like', "%{$keyword}%");
            })
            ->filterColumn('requester_jabatan', function ($query, $keyword) {
                $query->whereHas('employee', function ($sub) use ($keyword) {
                    $sub->where(function ($inner) use ($keyword) {
                        $inner->where('jabatan', 'like', "%{$keyword}%")
                            ->orWhereHas('jabatan', function ($jabatan) use ($keyword) {
                                $jabatan->where('nama_jabatan', 'like', "%{$keyword}%");
                            });
                    });
                });
            })
            ->filterColumn('requester_divisi', function ($query, $keyword) {
                $query->whereHas('employee', function ($sub) use ($keyword) {
                    $sub->where(function ($inner) use ($keyword) {
                        $inner->where('department', 'like', "%{$keyword}%")
                            ->orWhereHas('divisi', function ($divisi) use ($keyword) {
                                $divisi->where('nama_divisi', 'like', "%{$keyword}%");
                            });
                    });
                });
            })
            ->filterColumn('vendor_receipt_qty', function ($query, $keyword) {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('vendor_received_total', 'like', "%{$keyword}%")
                        ->orWhere('vendor_receipt_qty', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('finance_display_status', function ($query, $keyword) {
                $normalized = strtolower(trim($keyword));
                $allowed = ['completed', 'selesai', 'distributed', 'distribusi'];
                if (!in_array($normalized, $allowed, true) && stripos('completed', $keyword) === false) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->filterColumn('completed_at', function ($query, $keyword) {
                $query->where('completed_at', 'like', "%{$keyword}%");
            })
            ->make(true);
    }

    public function show(Request $request)
    {
        $purchaseRequest = PurchaseRequest::with(['items', 'employee.jabatan', 'employee.divisi'])
            ->where('is_active', true)
            ->where('finance_status', 'Distributed')
            ->findOrFail($request->id);

        $item = $purchaseRequest->items->first();
        $poDocuments = PurchaseOrderDocument::where('purchase_request_id', $purchaseRequest->id)
            ->orderBy('id')
            ->get();
        $poRevisionHistory = PurchaseOrderDocumentRevision::where('purchase_request_id', $purchaseRequest->id)
            ->orderByDesc('revised_at')
            ->get();
        $poDocument = $poDocuments
            ->filter(fn($doc) => !$doc->is_voided)
            ->last() ?: $poDocuments->last();
        $voidHistory = $poDocuments
            ->filter(fn($doc) => (bool) $doc->is_voided)
            ->map(fn($doc) => $this->formatPoDocument($doc, $purchaseRequest))
            ->values();
        $receiptBatches = PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequest->id)
            ->orderBy('batch_no')
            ->get()
            ->map(fn($batch) => PurchaseReceiptService::formatBatch($batch, self::VENDOR_ATTACHMENT_DIR));

        return response()->json([
            'data' => [
                'summary' => [
                    'id' => $purchaseRequest->id,
                    'request_number' => $purchaseRequest->request_number,
                    'po_number' => $purchaseRequest->po_number,
                    'handover_number' => $purchaseRequest->handover_number,
                    'finance_status' => $purchaseRequest->finance_status,
                    'status' => $purchaseRequest->status,
                    'priority' => $purchaseRequest->priority,
                    'purpose' => $purchaseRequest->purpose,
                    'receipt_target_qty' => $purchaseRequest->receipt_target_qty,
                    'vendor_received_total' => $purchaseRequest->vendor_received_total,
                    'user_handed_total' => $purchaseRequest->user_handed_total,
                    'user_confirmed_total' => $purchaseRequest->user_confirmed_total,
                    'completed_at' => $purchaseRequest->completed_at,
                    'completed_by' => $purchaseRequest->completed_by,
                    'po_created_by' => $purchaseRequest->po_created_by,
                    'po_created_at' => $purchaseRequest->po_created_at,
                    'po_approved_by' => $purchaseRequest->po_approved_by,
                    'po_approved_at' => $purchaseRequest->po_approved_at,
                ],
                'requester' => $purchaseRequest->employee
                    ? [
                        'nama_lengkap' => $purchaseRequest->created_by ?: '-',
                        'jabatan' => KaryawanProfileService::resolveJabatan($purchaseRequest->employee),
                        'divisi' => KaryawanProfileService::resolveDivisi($purchaseRequest->employee),
                        'nik_karyawan' => $purchaseRequest->employee->nik_karyawan ?? '-',
                    ]
                    : KaryawanProfileService::profile($purchaseRequest->created_by),
                'item' => [
                    'item_code' => $item->item_code ?? '',
                    'item_name' => $item->item_name ?? '',
                    'brand_name' => $item->brand_name ?? '',
                    'quantity' => $item->quantity ?? '',
                    'unit' => $item->unit ?? '',
                    'note' => $item->note ?? '',
                    'attachments' => $this->buildAttachmentUrls($item->attachment ?? null, self::PR_ATTACHMENT_DIR),
                ],
                'purchase_request' => [
                    'created_by' => $purchaseRequest->created_by,
                    'created_at' => $purchaseRequest->created_at,
                    'status' => $purchaseRequest->status,
                    'finance_status' => $purchaseRequest->finance_status,
                    'approved_by' => $purchaseRequest->approved_by,
                    'approved_at' => $purchaseRequest->approved_at,
                    'delegated_by' => $purchaseRequest->delegated_by,
                    'delegated_at' => $purchaseRequest->delegated_at,
                    'processed_by' => $purchaseRequest->processed_by,
                    'processed_at' => $purchaseRequest->processed_at,
                    'rejected_finance_by' => $purchaseRequest->rejected_finance_by,
                    'rejected_finance_at' => $purchaseRequest->rejected_finance_at,
                    'rejection_finance_note' => $purchaseRequest->rejection_finance_note,
                ],
                'purchase_order' => $poDocument ? $this->formatPoDocument($poDocument, $purchaseRequest) : null,
                'po_void_history' => $voidHistory,
                'po_revision_history' => $poRevisionHistory,
                'vendor_receipt' => [
                    'vendor_receipt_at' => $purchaseRequest->vendor_receipt_at,
                    'vendor_receipt_by' => $purchaseRequest->vendor_receipt_by,
                    'vendor_delivery_note' => $purchaseRequest->vendor_delivery_note,
                    'vendor_receipt_qty' => $purchaseRequest->vendor_receipt_qty,
                    'vendor_receipt_note' => $purchaseRequest->vendor_receipt_note,
                    'attachments' => $this->buildAttachmentUrls($purchaseRequest->vendor_receipt_attachments, self::VENDOR_ATTACHMENT_DIR),
                ],
                'handover' => [
                    'handover_number' => $purchaseRequest->handover_number,
                    'user_handover_qty' => $purchaseRequest->vendor_receipt_qty,
                    'user_receipt_at' => $purchaseRequest->user_receipt_at,
                    'user_receipt_by' => $purchaseRequest->user_receipt_by,
                    'user_receipt_note' => $purchaseRequest->user_receipt_note,
                ],
                'completion' => [
                    'completed_by' => $purchaseRequest->completed_by,
                    'completed_at' => $purchaseRequest->completed_at,
                ],
                'receipt_batches' => $receiptBatches,
                'timeline' => $this->buildTimeline($purchaseRequest, $poDocument, $voidHistory, $receiptBatches),
            ],
            'message' => 'Detail laporan pembelian berhasil diambil',
        ], 200);
    }

    private function formatPoDocument(PurchaseOrderDocument $poDocument, PurchaseRequest $purchaseRequest): array
    {
        return [
            'id' => $poDocument->id,
            'po_number' => $poDocument->po_number,
            'po_date' => $poDocument->po_date,
            'supplier_name' => $poDocument->supplier_name,
            'supplier_address' => $poDocument->supplier_address,
            'item_name' => $poDocument->item_name,
            'quantity' => $poDocument->quantity,
            'unit' => $poDocument->unit,
            'unit_price' => $poDocument->unit_price,
            'line_total' => $poDocument->line_total,
            'discount' => $poDocument->discount,
            'sub_total' => $poDocument->sub_total,
            'ppn_percent' => $poDocument->ppn_percent,
            'ppn_amount' => $poDocument->ppn_amount,
            'other_cost' => $poDocument->other_cost,
            'grand_total' => $poDocument->grand_total,
            'keterangan' => $poDocument->keterangan,
            'payment_term' => $poDocument->payment_term,
            'delivery_time' => $poDocument->delivery_time,
            'delivery_type' => $poDocument->delivery_type,
            'approval_name' => $poDocument->approval_name,
            'approval_jabatan' => $poDocument->approval_jabatan,
            'approval_date' => $poDocument->approval_date,
            'created_by' => $poDocument->created_by,
            'created_at' => $poDocument->created_at,
            'po_approved_by' => $purchaseRequest->po_approved_by,
            'po_approved_at' => $purchaseRequest->po_approved_at,
            'is_voided' => (bool) $poDocument->is_voided,
            'voided_by' => $poDocument->voided_by,
            'voided_at' => $poDocument->voided_at,
            'void_reason' => $poDocument->void_reason,
            'void_from_finance_status' => $poDocument->void_from_finance_status,
        ];
    }

    private function buildTimeline(PurchaseRequest $purchaseRequest, ?PurchaseOrderDocument $poDocument, $voidHistory = null, $receiptBatches = null): array
    {
        $steps = [
            [
                'title' => 'Pengajuan PR',
                'by' => $purchaseRequest->created_by,
                'at' => $purchaseRequest->created_at,
                'note' => $purchaseRequest->purpose,
            ],
            [
                'title' => 'Persetujuan Atasan',
                'by' => $purchaseRequest->approved_by,
                'at' => $purchaseRequest->approved_at,
            ],
            [
                'title' => 'Persetujuan Purchasing',
                'by' => $purchaseRequest->delegated_by,
                'at' => $purchaseRequest->delegated_at,
            ],
            [
                'title' => 'Proses / Buat PO',
                'by' => $purchaseRequest->po_created_by ?: $purchaseRequest->processed_by,
                'at' => $purchaseRequest->po_created_at ?: $purchaseRequest->processed_at,
                'note' => $purchaseRequest->po_number ? 'PO: ' . $purchaseRequest->po_number : null,
            ],
            [
                'title' => 'PO Diproses',
                'by' => $purchaseRequest->po_approved_by,
                'at' => $purchaseRequest->po_approved_at,
            ],
        ];

        if ($receiptBatches && count($receiptBatches)) {
            foreach ($receiptBatches as $batch) {
                $steps[] = [
                    'title' => 'Tanda Terima Vendor (Batch #' . $batch['batch_no'] . ')',
                    'by' => $batch['vendor_receipt_by'] ?? null,
                    'at' => $batch['vendor_receipt_at'] ?? null,
                    'note' => trim(
                        'Qty: ' . ($batch['vendor_receipt_qty'] ?? '-')
                        . ($batch['vendor_delivery_note'] ? '. SJ: ' . $batch['vendor_delivery_note'] : '')
                        . ($batch['vendor_receipt_note'] ? '. ' . $batch['vendor_receipt_note'] : '')
                    ) ?: null,
                ];

                if (!empty($batch['handover_number'])) {
                    $steps[] = [
                        'title' => 'Serah Terima ke User (Batch #' . $batch['batch_no'] . ')',
                        'by' => $batch['user_receipt_by'] ?? null,
                        'at' => $batch['user_receipt_at'] ?? null,
                        'note' => trim(
                            ($batch['handover_number'] ?? '')
                            . ' Qty: ' . ($batch['user_handover_qty'] ?? '-')
                            . ($batch['user_receipt_note'] ? ' — ' . $batch['user_receipt_note'] : '')
                        ) ?: null,
                    ];
                }

                if (!empty($batch['completed_at'])) {
                    $steps[] = [
                        'title' => 'Barang Diterima User (Batch #' . $batch['batch_no'] . ')',
                        'by' => $batch['completed_by'] ?? null,
                        'at' => $batch['completed_at'] ?? null,
                        'note' => 'Qty: ' . ($batch['user_handover_qty'] ?? '-'),
                    ];
                }
            }
        } else {
            $steps[] = [
                'title' => 'Tanda Terima Vendor',
                'by' => $purchaseRequest->vendor_receipt_by,
                'at' => $purchaseRequest->vendor_receipt_at,
                'note' => trim(($purchaseRequest->vendor_delivery_note ? 'SJ: ' . $purchaseRequest->vendor_delivery_note . '. ' : '') . ($purchaseRequest->vendor_receipt_note ?: '')),
            ];
            $steps[] = [
                'title' => 'Serah Terima ke User',
                'by' => $purchaseRequest->user_receipt_by,
                'at' => $purchaseRequest->user_receipt_at,
                'note' => $purchaseRequest->handover_number
                    ? $purchaseRequest->handover_number . ($purchaseRequest->user_receipt_note ? ' — ' . $purchaseRequest->user_receipt_note : '')
                    : $purchaseRequest->user_receipt_note,
            ];
            $steps[] = [
                'title' => 'Barang Diterima User',
                'by' => $purchaseRequest->completed_by,
                'at' => $purchaseRequest->completed_at,
            ];
        }

        if ($voidHistory) {
            foreach ($voidHistory as $voidedPo) {
                $steps[] = [
                    'title' => 'PO Di-void',
                    'by' => $voidedPo['voided_by'] ?? null,
                    'at' => $voidedPo['voided_at'] ?? null,
                    'note' => trim(
                        ($voidedPo['po_number'] ? 'PO: ' . $voidedPo['po_number'] . '. ' : '')
                        . ($voidedPo['void_from_finance_status'] ? 'Status: ' . $voidedPo['void_from_finance_status'] . '. ' : '')
                        . ($voidedPo['void_reason'] ? 'Alasan: ' . $voidedPo['void_reason'] : '')
                    ) ?: null,
                    'is_void' => true,
                ];
            }
        }

        usort($steps, function ($a, $b) {
            $timeA = !empty($a['at']) ? strtotime($a['at']) : 0;
            $timeB = !empty($b['at']) ? strtotime($b['at']) : 0;

            return $timeA <=> $timeB;
        });

        return array_values(array_filter(array_map(function ($step) {
            if (empty($step['at']) && empty($step['by'])) {
                return null;
            }

            $step['at_formatted'] = !empty($step['at'])
                ? Carbon::parse($step['at'])->locale('id')->isoFormat('D MMM YYYY HH:mm')
                : '-';

            return $step;
        }, $steps)));
    }

    private function buildAttachmentUrls($attachmentField, string $directory): array
    {
        $files = $this->parseAttachments($attachmentField);

        return array_map(function ($filename) use ($directory) {
            return [
                'filename' => $filename,
                'url' => $directory . '/' . $filename,
            ];
        }, $files);
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
}
