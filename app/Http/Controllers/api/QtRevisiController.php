<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\OrderHeader;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class QtRevisiController extends Controller
{
    public function index(Request $request)
    {
        $tahun = request()->tahun;

        $data = OrderHeader::with(['quotationNonKontrak', 'quotationKontrakH', 'sales'])
            ->where('is_revisi', true)
            ->whereYear('tanggal_penawaran', $tahun)
            ->where('is_active', 1)
            ->orderBy('tanggal_penawaran', 'desc');

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
        switch ($jabatan) {
            case 24: // Sales Staff
                $data->where('sales_id', $this->user_id);
                break;
            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                    ->pluck('id')
                    ->toArray();
                array_push($bawahan, $this->user_id);
                $data->whereIn('sales_id', $bawahan);
                break;
        }

        return DataTables::of($data)
            ->addColumn('status_quotation', function ($row) {
                return $this->resolveStatusQuotation($row);
            })
            ->filterColumn('status_quotation', function ($query, $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }

                $like = '%' . $keyword . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('order_header.status_quotation', 'like', $like)
                        ->orWhereHas('quotationKontrakH', function ($q) use ($like) {
                            $q->where('status_quotation', 'like', $like);
                        })
                        ->orWhereHas('quotationNonKontrak', function ($q) use ($like) {
                            $q->where('status_quotation', 'like', $like);
                        })
                        ->orWhere('order_header.no_document', 'like', $like);
                });
            })
            ->make(true);
    }

    private function resolveStatusQuotation($row): ?string
    {
        if (!empty($row->status_quotation)) {
            return $row->status_quotation;
        }

        return null;
    }
}
