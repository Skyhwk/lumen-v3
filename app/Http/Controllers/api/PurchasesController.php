<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use DataTables;

use App\Services\Notification;

use App\Models\{PurchaseRequest, PurchaseRequestItem, MasterKaryawan};
use App\Services\GetBawahan;

class PurchasesController extends Controller
{
    public function index(Request $request)
    {
        // $employee = $request->attributes->get('user')->karyawan;

        $purchaseRequests = PurchaseRequest::with(['items' => fn($q) => $q->whereNull('rejected_at'), 'employee'])
            ->whereIn('status', ["Approved", "Partially Approved"])
            ->where('is_active', true)
            ->latest();

        // if ($employee->grade === 'STAFF') {
        //     $purchaseRequests = $purchaseRequests->where('created_by', $employee->nama_lengkap);
        // }

        // if ($employee->grade === 'SUPERVISOR' || $employee->grade === 'MANAGER') {
        //     $creator = GetBawahan::where('id', $employee->id)->get()->pluck('nama_lengkap')->toArray();
        //     $creator[] = $employee->nama_lengkap;

        //     $purchaseRequests = $purchaseRequests->whereIn('created_by', $creator);
        // }

        return DataTables::of($purchaseRequests)->make(true);
    }

    public function process(Request $request) // cmn ada reject (sabda patah)
    {
        foreach ($request->data['child_ids'] as $id) {
            $item = PurchaseRequestItem::findOrFail($id);

            $item->rejection_finance_note = $request->data['reason'];
            $item->rejected_finance_by = $this->karyawan;
            $item->rejected_finance_at = date('Y-m-d H:i:s');

            $item->save();
        }

        // ===== DETECT STATUS PARENT =====

        $parentId = $request->data['parent_id'];

        $children = PurchaseRequestItem::where('purchase_request_id', $parentId)->whereNotNull('approved_at')->get();

        $total = $children->count();

        $rejectedCount = $children->whereNotNull('rejected_finance_at')->count();

        if ($rejectedCount !== $total) { // masih ada yang blm direject
            return response()->json(['message' => "Rejected successfully"], 201);
        }

        $parent = PurchaseRequest::findOrFail($parentId);

        $employee = $request->attributes->get('user')->karyawan;

        if ($rejectedCount === $total) {
            $parent->finance_status = 'Rejected';
            $parent->rejection_finance_note = $request->data['reason'];
            $parent->rejected_finance_by = $this->karyawan;
            $parent->rejected_finance_at = date('Y-m-d H:i:s');

            Notification::where('nama_lengkap', $parent->created_by)
                ->title('Permintaan Pembelian Barang Ditolak!')
                ->message("Permintaan Pembelian Barang yang anda ajukan telah ditolak finance oleh {$employee->nama_lengkap} pada " . date('d-m-Y') . " dengan alasan: {$request->data['reason']}")
                ->url('/purchase-requests')
                ->send();
        } else {
            Notification::where('nama_lengkap', $parent->created_by)
                ->title('Permintaan Pembelian Barang Ditolak!')
                ->message("Terdapat beberapa item pada Permintaan Pembelian Barang yang anda ajukan ditolak finance oleh {$employee->nama_lengkap} pada " . date('d-m-Y') . " dengan alasan: {$request->data['reason']}")
                ->url('/purchase-requests')
                ->send();
        }

        $parent->save();

        return response()->json(['message' => "Permintaan pembelian berhasil direject"], 201);
    }

    public function getFinanceStaffs(Request $request)
    {
        $pics = MasterKaryawan::whereIn('id_jabatan', [45, 48])->where('is_active', true)->get(['id', 'nama_lengkap']);

        $financeStaffs = MasterKaryawan::where('is_active', true);

        $employee = $request->attributes->get('user')->karyawan;
        if (
            $employee->id_jabatan == 45 // Senior Manager FAT
            || $employee->id_jabatan == 48 // Accounting & Expense Manager
        ) {
            $lowers = GetBawahan::where('id', $employee->id)->get()->pluck('id')->toArray();
            $financeStaffs = $financeStaffs->whereIn('id', $lowers);
        } else {
            $financeStaffs = $financeStaffs->whereIn('id_jabatan', [
                56, // Expense Staff
                57 // Purchasing Staff
            ]);
        }

        $financeStaffs = $financeStaffs->get(['id', 'nama_lengkap']);

        $selectedPics = DB::table('pic_purchases')->pluck('employee_id')->toArray();

        return response()->json([
            'success' => true,
            'pics' => $pics,
            'finance_staffs' => $financeStaffs,
            'selected_pics' => $selectedPics
        ], 200);
    }

    public function setPIC(Request $request)
    {
        DB::table('pic_purchases')->delete();
        DB::statement('ALTER TABLE pic_purchases AUTO_INCREMENT = 1');

        $data = [];
        foreach ($request->pic_ids as $id) {
            $data[] = ['employee_id' => $id];
        }

        DB::table('pic_purchases')->insert($data);

        return response()->json(['message' => 'PIC berhasil diupdate'], 200);
    }

    public function delegate(Request $request)
    {
        $purchaseRequest = PurchaseRequest::findOrFail($request->id);

        $purchaseRequest->finance_status = 'Waiting Process';
        $purchaseRequest->delegated_by = $this->karyawan;
        $purchaseRequest->delegated_at = date('Y-m-d H:i:s');
        $purchaseRequest->delegated_to = $request->finance_staff;

        $purchaseRequest->save();

        $employee = $request->attributes->get('user')->karyawan;

        Notification::where('nama_lengkap', $purchaseRequest->created_by)
            ->title('Permintaan Pembelian Barang Didelegasikan!')
            ->message("Permintaan Pembelian Barang yang anda ajukan telah didelegasikan oleh {$employee->nama_lengkap} pada " . date('d-m-Y') . " dan siap diproses oleh {$request->finance_staff}")
            ->url('/purchase-requests')
            ->send();

        Notification::where('nama_lengkap', $request->finance_staff)
            ->title('Permintaan Pembelian Barang Didelegasikan!')
            ->message("Terdapat Permintaan Pembelian Barang yang didelegasikan oleh {$employee->nama_lengkap} pada " . date('d-m-Y') . " dan siap diproses oleh Anda")
            ->url('/purchases')
            ->send();

        return response()->json(['message' => "Permintaan pembelian berhasil didelegasikan"], 200);
    }

    public function saveProgress(Request $request)
    {
        $purchaseRequest = PurchaseRequest::findOrFail($request->id);

        $employee = $request->attributes->get('user')->karyawan;
        $uppers = MasterKaryawan::whereIn('id', json_decode($employee->atasan_langsung))->whereIn('id_jabatan', [45, 84])->pluck('id')->toArray();

        if ($request->type === 'process') {
            $purchaseRequest->finance_status = 'On Process';
            $purchaseRequest->processed_by = $this->karyawan;
            $purchaseRequest->processed_at = date('Y-m-d H:i:s');

            Notification::where('nama_lengkap', $purchaseRequest->created_by)
                ->title('Permintaan Pembelian Barang Diproses!')
                ->message("Permintaan Pembelian Barang yang anda ajukan sedang diproses oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/purchase-requests')
                ->send();

            Notification::whereIn('id', $uppers)
                ->title('Permintaan Pembelian Barang Diproses!')
                ->message("Permintaan Pembelian Barang sedang diproses oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/purchases')
                ->send();
        }

        if ($request->type === 'pending') {
            $purchaseRequest->finance_status = 'Pending';
            $purchaseRequest->pending_by = $this->karyawan;
            $purchaseRequest->pending_at = date('Y-m-d H:i:s');

            Notification::where('nama_lengkap', $purchaseRequest->created_by)
                ->title('Permintaan Pembelian Barang Dipending!')
                ->message("Permintaan Pembelian Barang yang anda ajukan telah dipending oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/purchase-requests')
                ->send();

            Notification::whereIn('id', $uppers)
                ->title('Permintaan Pembelian Barang Dipending!')
                ->message("Permintaan Pembelian Barang telah dipending oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/purchases')
                ->send();
        }

        if ($request->type === 'done') {
            $purchaseRequest->status = 'Done';
            $purchaseRequest->finance_status = 'Distributed';
            $purchaseRequest->completed_by = $this->karyawan;
            $purchaseRequest->completed_at = date('Y-m-d H:i:s');

            Notification::where('nama_lengkap', $purchaseRequest->created_by)
                ->title('Permintaan Pembelian Barang Selesai!')
                ->message("Permintaan Pembelian Barang yang anda ajukan telah dinyatakan selesai oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/purchase-requests')
                ->send();

            Notification::whereIn('id', $uppers)
                ->title('Permintaan Pembelian Barang Selesai!')
                ->message("Permintaan Pembelian Barang telah dinyatakan selesai oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/purchases')
                ->send();
        }

        $purchaseRequest->save();

        return response()->json(['message' => "Berhasil menyimpan progress"], 200);
    }
}
