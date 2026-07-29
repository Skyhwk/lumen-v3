<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterDivisi;
use App\Models\PurchaseOrderDocument;
use App\Models\PurchaseRequest;
use App\Services\{KaryawanProfileService, Notification, PurchaseReceiptService};
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProcessPurchasesController extends Controller
{
    private const MAX_ATTACHMENT_SIZE = 2097152;
    private const ATTACHMENT_DIR = 'purchase-process/proof';

    public function index(Request $request)
    {
        $scope = $request->input('scope', 'pending');

        $poDocuments = PurchaseOrderDocument::with([
            'purchaseRequest.items',
            'purchaseRequest.employee.jabatan',
            'purchaseRequest.employee.divisi',
        ])
            ->where(function ($query) {
                $query->where('is_voided', false)->orWhereNull('is_voided');
            })
            ->whereHas('purchaseRequest', function ($query) {
                $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->where('is_goods_voided', false)->orWhereNull('is_goods_voided');
                    });
            })
            ->latest('processed_at')
            ->latest('id');

        if ($scope === 'pending') {
            $poDocuments = $poDocuments->where('po_status', 'approved');
        } else {
            $poDocuments = $poDocuments
                ->where('po_status', 'active')
                ->whereNotNull('purchase_transacted_at');
        }

        return DataTables::of($poDocuments)
            ->addColumn('purchase_request_id', fn($row) => $row->purchase_request_id)
            ->addColumn('po_document_id', fn($row) => $row->id)
            ->addColumn('request_number', fn($row) => optional($row->purchaseRequest)->request_number)
            ->filterColumn('request_number', function ($query, $keyword) {
                $query->whereHas('purchaseRequest', function ($q) use ($keyword) {
                    $q->where('request_number', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('po_number', fn($row) => $row->po_number)
            ->filterColumn('po_number', function ($query, $keyword) {
                $query->where('purchase_order_documents.po_number', 'like', "%{$keyword}%");
            })
            ->addColumn('item_name', fn($row) => $row->item_name ?: optional(optional($row->purchaseRequest)->items->first())->item_name)
            ->filterColumn('item_name', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('purchase_order_documents.item_name', 'like', "%{$keyword}%")
                        ->orWhereHas('purchaseRequest.items', function ($subQ) use ($keyword) {
                            $subQ->where('item_name', 'like', "%{$keyword}%");
                        });
                });
            })
            ->addColumn('quantity', fn($row) => (float) $row->quantity)
            ->filterColumn('quantity', function ($query, $keyword) {
                $query->where('purchase_order_documents.quantity', 'like', "%{$keyword}%");
            })
            ->addColumn('unit', fn($row) => $row->unit ?: optional(optional($row->purchaseRequest)->items->first())->unit)
            ->filterColumn('unit', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('purchase_order_documents.unit', 'like', "%{$keyword}%")
                        ->orWhereHas('purchaseRequest.items', function ($subQ) use ($keyword) {
                            $subQ->where('unit', 'like', "%{$keyword}%");
                        });
                });
            })
            ->addColumn('purpose', fn($row) => optional($row->purchaseRequest)->purpose)
            ->filterColumn('purpose', function ($query, $keyword) {
                $query->whereHas('purchaseRequest', function ($q) use ($keyword) {
                    $q->where('purpose', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('priority', fn($row) => optional($row->purchaseRequest)->priority)
            ->filterColumn('priority', function ($query, $keyword) {
                $query->whereHas('purchaseRequest', function ($q) use ($keyword) {
                    $q->where('priority', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('supplier_name', fn($row) => $row->supplier_name)
            ->filterColumn('supplier_name', function ($query, $keyword) {
                $query->where('purchase_order_documents.supplier_name', 'like', "%{$keyword}%");
            })
            ->addColumn('grand_total', fn($row) => (float) $row->grand_total)
            ->addColumn('created_by', fn($row) => optional($row->purchaseRequest)->created_by)
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->whereHas('purchaseRequest', function ($q) use ($keyword) {
                    $q->where('created_by', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('requester_divisi', fn($row) => KaryawanProfileService::resolveDivisi(optional($row->purchaseRequest)->employee))
            ->filterColumn('requester_divisi', fn($query, $keyword) => $this->applyRequesterDivisiFilter($query, $keyword))
            ->addColumn('po_approved_at', fn($row) => $row->processed_at)
            ->filterColumn('po_approved_at', function ($query, $keyword) {
                $query->where('purchase_order_documents.processed_at', 'like', "%{$keyword}%");
            })
            ->addColumn('purchase_transacted_at', fn($row) => $row->purchase_transacted_at)
            ->filterColumn('purchase_transacted_at', function ($query, $keyword) {
                $query->where('purchase_order_documents.purchase_transacted_at', 'like', "%{$keyword}%");
            })
            ->addColumn('purchase_transaction_no', fn($row) => $row->purchase_transaction_no)
            ->filterColumn('purchase_transaction_no', function ($query, $keyword) {
                $query->where('purchase_order_documents.purchase_transaction_no', 'like', "%{$keyword}%");
            })
            ->addColumn('process_display_status', fn($row) => $scope === 'pending' ? 'Menunggu Transaksi' : 'Sudah Ditransaksikan')
            ->addColumn('can_transact', fn($row) => $row->po_status === 'approved')
            ->make(true);
    }

    public function getTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), ['id' => 'required', 'po_document_id' => 'required|integer']);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::with(['items', 'employee.jabatan', 'employee.divisi'])->findOrFail($request->id);
        $poDocument = $this->findApprovedPoDocument($purchaseRequest->id, (int) $request->po_document_id);

        if (!$poDocument) {
            return response()->json(['message' => 'PO tidak ditemukan atau belum di-approve'], 422);
        }

        $item = $purchaseRequest->items->first();

        return response()->json([
            'data' => [
                'id' => $purchaseRequest->id,
                'po_document_id' => $poDocument->id,
                'request_number' => $purchaseRequest->request_number,
                'po_number' => $poDocument->po_number,
                'supplier_name' => $poDocument->supplier_name,
                'item_name' => $poDocument->item_name ?: optional($item)->item_name,
                'quantity' => (float) $poDocument->quantity,
                'unit' => $poDocument->unit ?: optional($item)->unit,
                'grand_total' => (float) $poDocument->grand_total,
                'purpose' => $purchaseRequest->purpose,
                'priority' => $purchaseRequest->priority,
                'po_approved_at' => $poDocument->processed_at,
                'po_approved_by' => $poDocument->processed_by,
                'purchase_transaction_no' => $poDocument->purchase_transaction_no,
                'purchase_transaction_date' => $poDocument->purchase_transaction_date,
                'purchase_transaction_note' => $poDocument->purchase_transaction_note,
                'attachments' => $this->formatProofAttachments($poDocument->purchase_transaction_proof),
            ],
            'message' => 'Data proses pembelian berhasil dimuat',
        ], 200);
    }

    public function completeTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'po_document_id' => 'required|integer',
            'purchase_transaction_no' => 'required|string|max:100',
            'purchase_transaction_date' => 'required|date',
            'purchase_transaction_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::with('items')->findOrFail($request->id);
        $poDocument = $this->findApprovedPoDocument($purchaseRequest->id, (int) $request->po_document_id);

        if (!$poDocument) {
            return response()->json(['message' => 'PO tidak ditemukan atau sudah ditransaksikan'], 422);
        }

        $attachments = $this->handleAttachments($request, $poDocument->purchase_transaction_proof);
        if ($attachments === false) {
            return response()->json(['message' => 'Lampiran bukti transaksi harus berupa gambar maksimal 2MB per file'], 422);
        }

        if (empty($attachments)) {
            return response()->json(['message' => 'Minimal satu bukti transaksi/pembelian wajib dilampirkan'], 422);
        }

        $employee = $request->attributes->get('user')->karyawan;
        $now = date('Y-m-d H:i:s');

        DB::beginTransaction();

        try {
            $poDocument->purchase_transaction_no = trim($request->purchase_transaction_no);
            $poDocument->purchase_transaction_date = $request->purchase_transaction_date;
            $poDocument->purchase_transaction_note = $request->purchase_transaction_note;
            $poDocument->purchase_transaction_proof = $this->encodeAttachments($attachments);
            $poDocument->purchase_transacted_by = $this->karyawan;
            $poDocument->purchase_transacted_at = $now;
            $poDocument->po_status = 'active';
            $poDocument->save();

            app(PurchaseOrdersController::class)->syncPurchaseRequestFromPosPublic($purchaseRequest);
            $purchaseRequest->refresh();

            $processorName = ($employee && $employee->nama_lengkap) ? $employee->nama_lengkap : $this->karyawan;

            Notification::where('nama_lengkap', $purchaseRequest->created_by)
                ->title('Purchase Order Ditransaksikan!')
                ->message("PO {$poDocument->po_number} untuk permintaan {$purchaseRequest->request_number} telah ditransaksikan oleh {$processorName} dan menunggu penerimaan barang.")
                ->url('/request/purchase-requests')
                ->send();

            DB::commit();

            return response()->json([
                'message' => 'Transaksi pembelian berhasil. Data dipindahkan ke Goods Receipt.',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyelesaikan transaksi pembelian: ' . $th->getMessage(),
            ], 500);
        }
    }

    private function findApprovedPoDocument(int $purchaseRequestId, int $poDocumentId): ?PurchaseOrderDocument
    {
        return PurchaseOrderDocument::where('purchase_request_id', $purchaseRequestId)
            ->where('id', $poDocumentId)
            ->where('po_status', 'approved')
            ->where(function ($query) {
                $query->where('is_voided', false)->orWhereNull('is_voided');
            })
            ->first();
    }

    private function formatProofAttachments($attachmentField): array
    {
        return array_map(function ($filename) {
            return [
                'filename' => $filename,
                'url' => self::ATTACHMENT_DIR . '/' . $filename,
            ];
        }, $this->parseAttachments($attachmentField));
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

    private function applyRequesterDivisiFilter($query, string $keyword): void
    {
        $matchingDivisiIds = MasterDivisi::where('is_active', true)
            ->where('nama_divisi', 'like', "%{$keyword}%")
            ->pluck('id');

        $query->whereHas('purchaseRequest.employee', function ($employeeQuery) use ($keyword, $matchingDivisiIds) {
            $employeeQuery->where(function ($q) use ($keyword, $matchingDivisiIds) {
                $q->where('department', 'like', "%{$keyword}%");

                if ($matchingDivisiIds->isNotEmpty()) {
                    $q->orWhereIn('id_divisi', $matchingDivisiIds);
                }
            });
        });
    }
}
