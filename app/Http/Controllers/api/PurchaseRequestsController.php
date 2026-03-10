<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use DataTables;

use Mpdf;
use App\Services\{Notification, GetBawahan};

use App\Models\{PurchaseRequest, PurchaseRequestItem, DataAset};

class PurchaseRequestsController extends Controller
{
    public function initialize(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        $dataAset = DataAset::where('is_active', true)->get();

        $assets = $dataAset->pluck('jenis_aset')->unique()->sort()->values();
        $brands = $dataAset->pluck('merk')->unique()->sort()->values();

        $units = PurchaseRequestItem::where('is_active', true)->distinct()->orderBy('unit')->pluck('unit');

        return response()->json([
            'data' => [
                'employee' => $employee,
                'lookups' => [
                    'assets' => $assets,
                    'brands' => $brands,
                    'units' => $units,
                ],
            ],
            'message' => 'Assets retrieved successfully'
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

        return DataTables::of($purchaseRequests)->make(true);
    }

    // public function generateCode(Request $request)
    // {
    //     $dataAset = DataAset::where('jenis_aset', $request->item_name)->latest()->first();

    //     if (!$dataAset) {
    //         $itemName = strtoupper($request->item_name);
    //         $code = "CS-{$itemName}-001";
    //     } else {
    //         [$prefix, $itemName, $sequence] = explode('-', $dataAset->no_cs);
    //         $sequence = $sequence + 1;

    //         $code = "CS-{$itemName}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    //     }

    //     return response()->json([
    //         'data' => $code,
    //         'message' => 'Code generated successfully'
    //     ], 201);
    // }

    public function getCode(Request $request)
    {
        $dataAsets = DataAset::where('jenis_aset', $request->item_name)->get();
        
        $latestAset = $dataAsets->sortByDesc('created_at')->first();
        
        $brands = $dataAsets->pluck('merk')->filter()->unique()->values();

        return response()->json([
            'data' => [
                'code' => optional($latestAset)->no_cs ?: "",
                'brands' => $brands
            ],
            'message' => 'Code and brands generated successfully'
        ], 200);
    }

    public function save(Request $request)
    {
        $isUpdateMode = $request->has('id');

        $purchaseRequest = $isUpdateMode ? PurchaseRequest::find($request->id) : new PurchaseRequest;

        if ($isUpdateMode) {
            $purchaseRequest->updated_by = $this->karyawan;
            $purchaseRequest->updated_at = date('Y-m-d H:i:s');
        } else {
            $purchaseRequest->request_number = str_replace('.', '/', microtime(true));
            $purchaseRequest->created_by = $this->karyawan;
            $purchaseRequest->created_at = date('Y-m-d H:i:s');
        }

        $purchaseRequest->priority = $request->priority;
        $purchaseRequest->purpose = $request->purpose;

        $purchaseRequest->save();

        foreach ($request->items as $index => $item) {
            if ($request->hasFile("items.$index.attachment")) {
                $file = $request->file("items.$index.attachment");
                $destinationPath = public_path('purchase-requests');
                if (!file_exists($destinationPath)) mkdir($destinationPath, 0777, true);

                $fileName = uniqid() . '_' . $file->getClientOriginalName();
                $file->move($destinationPath, $fileName);
                $item['attachment'] = $fileName;
            }

            if ($isUpdateMode) {
                $purchaseRequestItem = $purchaseRequest->items()->find($item['id']);
                if ($purchaseRequestItem) {
                    $purchaseRequestItem->fill($item);
                    $purchaseRequestItem->updated_by = $this->karyawan;
                    $purchaseRequestItem->updated_at = date('Y-m-d H:i:s');
                    $purchaseRequestItem->save();
                }
            } else {
                $item['created_by'] = $this->karyawan;
                $item['created_at'] = date('Y-m-d H:i:s');
                $purchaseRequest->items()->create($item);
            }
        }

        if (!$isUpdateMode) {
            $employee = $request->attributes->get('user')->karyawan;

            Notification::whereIn('id', json_decode($employee->atasan_langsung))
                ->title('Permintaan Pembelian Barang!')
                ->message("Terdapat Permintaan Pembelian Barang baru yang diajukan oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/purchase-requests')
                ->send();
        }

        return response()->json(['message' => "Permintaan pembelian barang berhasil " . ($isUpdateMode ? 'diupdate' : 'diajukan')], 201);
    }

    public function delete(Request $request)
    {
        $purchaseRequest = PurchaseRequest::findOrFail($request->id);
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
        $purchaseRequest->save();

        $purchaseRequest->items()->update([
            'rejection_note' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ]);

        $employee = $request->attributes->get('user')->karyawan;

        Notification::whereIn('id', json_decode($employee->atasan_langsung))
            ->title('Permintaan Pembelian Barang!')
            ->message("Terdapat Permintaan Pembelian Barang yang telah direopen oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
            ->url('/purchase-requests')
            ->send();

        return response()->json(['message' => 'Reopened successfully'], 201);
    }

    public function process(Request $request)
    {
        foreach ($request->data['child_ids'] as $id) {
            $item = PurchaseRequestItem::findOrFail($id);

            if ($request->action === 'approve') {
                $item->approved_by = $this->karyawan;
                $item->approved_at = date('Y-m-d H:i:s');
            }

            if ($request->action === 'reject') {
                $item->rejection_note = $request->data['reason'];
                $item->rejected_by = $this->karyawan;
                $item->rejected_at = date('Y-m-d H:i:s');
            }

            $item->save();
        }

        // ===== DETECT STATUS PARENT =====

        $parentId = $request->data['parent_id'];

        $children = PurchaseRequestItem::where('purchase_request_id', $parentId)->get();

        $total = $children->count();

        $approvedCount = $children->whereNotNull('approved_at')->count();
        $rejectedCount = $children->whereNotNull('rejected_at')->count();

        $message = ($request->action === 'approve' ? 'Approved' : 'Rejected') . " successfully";

        if (($approvedCount + $rejectedCount) !== $total) { // masih ada yang blm diproses
            return response()->json(['message' => "$message successfully"], 201);
        }

        $parent = PurchaseRequest::findOrFail($parentId);

        $employee = $request->attributes->get('user')->karyawan;

        if ($approvedCount === $total) {
            $parent->status = 'Approved';
            $parent->approved_by = $this->karyawan;
            $parent->approved_at = date('Y-m-d H:i:s');

            Notification::where('nama_lengkap', $parent->created_by)
                ->title('Permintaan Pembelian Barang Disetujui!')
                ->message("Permintaan Pembelian Barang yang anda ajukan telah disetujui oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/purchase-requests')
                ->send();

            $parent->finance_status = 'Waiting to Delegate';

            Notification::whereIn('id_jabatan', [45, 48])
                ->title('Permintaan Pembelian Barang Diajukan!')
                ->message("Terdapat Permintaan Pembelian Barang yang diajukan oleh {$parent->approved_by} pada " . date('d-m-Y'))
                ->url('/purchases')
                ->send();
        } elseif ($rejectedCount === $total) {
            $parent->status = 'Rejected';
            $parent->rejection_note = $request->data['reason'];
            $parent->rejected_by = $this->karyawan;
            $parent->rejected_at = date('Y-m-d H:i:s');

            Notification::where('nama_lengkap', $parent->created_by)
                ->title('Permintaan Pembelian Barang Ditolak!')
                ->message("Permintaan Pembelian Barang yang anda ajukan telah ditolak oleh {$employee->nama_lengkap} pada " . date('d-m-Y') . " dengan alasan: " . $request->data['reason'])
                ->url('/purchase-requests')
                ->send();
        } else {
            $parent->status = 'Partially Approved';
            $parent->approved_by = $this->karyawan;
            $parent->approved_at = date('Y-m-d H:i:s');

            Notification::where('nama_lengkap', $parent->created_by)
                ->title('Permintaan Pembelian Barang Disetujui sebagian!')
                ->message("Permintaan Pembelian Barang yang anda ajukan telah disetujui sebagian oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/purchase-requests')
                ->send();

            $parent->finance_status = 'Waiting to Delegate';

            Notification::whereIn('id_jabatan', [45, 48])
                ->title('Permintaan Pembelian Barang Diajukan!')
                ->message("Terdapat Permintaan Pembelian Barang yang diajukan oleh {$parent->approved_by} pada " . date('d-m-Y'))
                ->url('/purchases')
                ->send();
        }

        $parent->save();

        return response()->json(['message' => "Permintaan pembelian barang berhasil di{$request->action}"], 201);
    }

    public function exportPdf(Request $request)
    {
        $purchaseRequest = PurchaseRequest::with(['items', 'employee'])->findOrFail($request->id);

        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);

        $html = view('pdf.purchase-request', compact('purchaseRequest'))->render();
        $mpdf->WriteHTML($html);

        $pdfString = $mpdf->Output('', 'S');

        return response()->json([
            'data' => base64_encode($pdfString),
            'message' => 'PDF generated successfully'
        ], 200);
    }
}
