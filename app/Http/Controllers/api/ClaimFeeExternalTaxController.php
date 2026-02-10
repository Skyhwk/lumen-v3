<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClaimFeeExternal;
use App\Models\OrderHeader;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\File;

class ClaimFeeExternalTaxController extends Controller
{
    public function outstandingIndex(Request $request)
    {
        $status = $request->status;

        $query = ClaimFeeExternal::query()
            ->where('is_active', true)->where('is_approved_manajer', true)->whereIn('status_pembayaran', ['PROCESSED', 'WAITING PROCESS']);

        return DataTables::of($query)
            ->filter(function ($query) use ($request) {
                foreach ($request->columns as $column) {
                    $value = $column['search']['value'] ?? null;
                    $field = $column['data'] ?? null;

                    if (!$value || !$field) continue;

                    switch ($field) {
                        case 'no_order':
                        case 'nama_penerima':
                        case 'nama_bank':
                        case 'nama_perusahaan':
                        case 'no_quotation':
                        case 'no_invoice':
                            $query->where($field, 'like', "%{$value}%");
                            break;

                        case 'status_pembayaran':
                            $query->where('status_pembayaran', $value);
                            break;

                        case 'due_date':
                        case 'tanggal_pembayaran':
                            $this->applyDateFilter($query, $field, $value);
                            break;
                    }
                }
            })
            ->make(true);
    }

    public function settlementIndex(Request $request)
    {
        $status = $request->status;

        $query = ClaimFeeExternal::query()
            ->where('is_active', true)->where('is_approved_manajer', true)->whereIn('status_pembayaran', ['READY TO TRANSFER', 'TRANSFER', 'REJECTED']);

        return DataTables::of($query)
            ->filter(function ($query) use ($request) {
                foreach ($request->columns as $column) {
                    $value = $column['search']['value'] ?? null;
                    $field = $column['data'] ?? null;

                    if (!$value || !$field) continue;

                    switch ($field) {
                        case 'no_order':
                        case 'nama_penerima':
                        case 'nama_bank':
                        case 'nama_perusahaan':
                        case 'no_quotation':
                        case 'no_invoice':
                            $query->where($field, 'like', "%{$value}%");
                            break;

                        case 'status_pembayaran':
                            $query->where('status_pembayaran', $value);
                            break;

                        case 'due_date':
                        case 'tanggal_pembayaran':
                            $this->applyDateFilter($query, $field, $value);
                            break;
                    }
                }
            })
            ->make(true);
    }

    public function approve(Request $request)
    {
        $claim = ClaimFeeExternal::find($request->id);
        if (!$claim) {
            return response()->json(['message' => 'Claim Fee External tidak ditemukan'], 404);
        }

        $claim->status_pembayaran = "PROCESSED";
        $claim->processed_by = $this->karyawan;
        $claim->processed_at = Carbon::now()->format('Y-m-d H:i:s');
        $claim->save();

        return response()->json(['message' => 'Claim Fee External Berhasil Disetujui']);
    }

    public function setPPH(Request $request)
    {
        $claim = ClaimFeeExternal::find($request->id);
        if (!$claim) {
            return response()->json(['message' => 'Claim Fee External tidak ditemukan'], 404);
        }

        $claim->status_pembayaran = "READY TO TRANSFER";
        // $claim->transferred_by = $this->karyawan;
        // $claim->tanggal_pembayaran = $request->transfer_date;
        $claim->potongan = $request->potongan;
        $claim->nominal_bayar = $request->nominal_bayar;
        $claim->save();

        return response()->json(['message' => 'PPH External Berhasil di Set']);
    }

    public function reject(Request $request)
    {
        $claim = ClaimFeeExternal::find($request->id);
        if (!$claim) {
            return response()->json(['message' => 'Claim Fee External tidak ditemukan'], 404);
        }

        $claim->status_pembayaran = "REJECTED";
        $claim->rejected_by = $this->karyawan;
        $claim->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
        $claim->save();

        return response()->json(['message' => 'Claim Fee External Berhasil Ditolak']);
    }

    private function applyDateFilter($query, $column, $value)
    {
        // YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $query->whereDate($column, $value);

            // YYYY-MM
        } elseif (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $query->whereYear($column, substr($value, 0, 4))
                ->whereMonth($column, substr($value, 5, 2));

            // YYYY
        } elseif (preg_match('/^\d{4}$/', $value)) {
            $query->whereYear($column, $value);
        }
    }
    public function uploadFile(Request $request)
    {
        DB::beginTransaction();
        try {
            $file = $request->file('file_input');

            // Validasi file
            if (!$file || $file->getClientOriginalExtension() !== 'pdf') {
                return response()->json(['error' => 'File tidak valid. Harus .pdf'], 400);
            }

            $claim = ClaimFeeExternal::find($request->id);
            // Pastikan folder invoice ada
            $folder = public_path('claim_fee_external');
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            // Generate nama file unik
            $fileName = $claim->no_order . '-' . $claim->periode .  '.pdf';

            // Simpan file
            $file->move($folder, $fileName);
            $claim->filename = $fileName;
            $claim->save();

            DB::commit();
            return response()->json([
                'success'  => 'Sukses menyimpan file upload',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Terjadi kesalahan server',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
