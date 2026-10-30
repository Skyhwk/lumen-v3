<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AksesMenu;
use App\Models\InvoiceTaxReturn;
use App\Models\MasterKaryawan;
use App\Services\InvoiceTaxReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoiceTaxReturnController extends Controller
{
    public function index(Request $request)
    {
        if (!$this->permitted('view')) return response()->json(['message' => 'Tidak memiliki akses melihat retur faktur.'], 403);
        if (!$this->ready()) return $this->notReady();
        $query = InvoiceTaxReturn::query();
        $search = mb_substr(trim((string) $request->input('q', '')), 0, 100);
        if ($search !== '') $query->where(function ($query) use ($search) {
            $query->where('no_invoice', 'like', '%' . $search . '%')->orWhere('nama_perusahaan', 'like', '%' . $search . '%');
        });
        return response()->json(['success' => true, 'data' => $query->orderByDesc('id')->paginate(20)]);
    }

    public function invoices(Request $request)
    {
        if (!$this->permitted('create')) return response()->json(['message' => 'Tidak memiliki akses membuat retur faktur.'], 403);
        if (!$this->ready()) return $this->notReady();
        $search = trim((string) $request->input('q', ''));
        if (mb_strlen($search) < 2 || mb_strlen($search) > 100) return response()->json(['message' => 'Pencarian harus 2–100 karakter.'], 422);
        return response()->json(['success' => true, 'data' => (new InvoiceTaxReturnService())->eligibleInvoices($search)]);
    }

    public function store(Request $request)
    {
        if (!$this->permitted('create')) return response()->json(['message' => 'Tidak memiliki akses membuat retur faktur.'], 403);
        if (!$this->ready()) return $this->notReady();
        $validator = Validator::make($request->all(), [
            'no_invoice' => 'required|string|max:100', 'return_date' => 'required|date_format:Y-m-d',
            'dpp' => ['required', 'numeric', 'gt:0', 'max:999999999999.99', 'regex:/^\d+(?:\.\d{1,2})?$/D'],
            'ppn' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'regex:/^\d+(?:\.\d{1,2})?$/D'],
            'reason' => 'required|string|max:3000', 'request_key' => 'required|string|max:100',
        ]);
        if ($validator->fails()) return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        try {
            $draft = (new InvoiceTaxReturnService())->createDraft($validator->validated(), (int) $this->user_id, $this->karyawan ?: 'Pengguna');
            return response()->json(['success' => true, 'message' => 'Draft retur faktur tersimpan.', 'data' => $draft], 201);
        } catch (ValidationException $exception) {
            return response()->json(['message' => collect($exception->errors())->flatten()->first(), 'errors' => $exception->errors()], 422);
        }
    }

    private function permitted(string $action): bool
    {
        if (!$this->user_id) return false;
        $userId = MasterKaryawan::where('id', $this->user_id)->value('user_id');
        $access = AksesMenu::where('user_id', $userId)->first();
        return collect($access ? $access->akses : [])->contains(function ($item) use ($action) {
            return ($item['name'] ?? '') === 'Upload Faktur Pajak' && ($item['parent'] ?? '') === 'Finance/Tax'
                && in_array($action, $item['access'] ?? [], true);
        });
    }

    private function ready(): bool
    {
        return Schema::hasTable('invoice_tax_returns');
    }

    private function notReady()
    {
        return response()->json(['message' => 'Tabel retur faktur belum tersedia. Jalankan migration create_invoice_tax_returns_table terlebih dahulu.'], 503);
    }
}
