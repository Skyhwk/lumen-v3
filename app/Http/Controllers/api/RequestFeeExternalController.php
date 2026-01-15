<?php
namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClaimFeeExternal;
use App\Models\OrderHeader;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class RequestFeeExternalController extends Controller
{
    public function outstandingIndex(Request $request)
    {
        $status = $request->status;

        $query = ClaimFeeExternal::query()
            ->where('is_active', true)->where('is_approved_manajer', true);

        if (is_array($status)) {
            $query->whereIn('status_pembayaran', $status);
        } else {
            $query->where('status_pembayaran', $status);
        }

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
            ->where('is_active', true)->where('is_approved_manajer', true);

        if (is_array($status)) {
            $query->whereIn('status_pembayaran', $status);
        } else {
            $query->where('status_pembayaran', $status);
        }

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

    public function transfer(Request $request)
    {
        $claim = ClaimFeeExternal::find($request->id);
        if (!$claim) {
            return response()->json(['message' => 'Claim Fee External tidak ditemukan'], 404);
        }

        $claim->status_pembayaran = "TRANSFER";
        $claim->transferred_by = $this->karyawan;
        $claim->tanggal_pembayaran = $request->transfer_date;
        $claim->save();

        return response()->json(['message' => 'Claim Fee External Berhasil Ditransfer']);
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



}