<?php

namespace App\Http\Controllers\api;

use App\Models\Jadwal;
use App\Models\SamplingPlan;
use App\Models\MasterPelanggan;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\HargaParameter;
use App\Models\Ftc;
use App\Models\FtcT;
use App\Models\ParameterAnalisa;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Services\SamplingPlanServices;
use App\Services\GetBawahan;
use App\Services\{Notification, GetAtasan};
use App\Helpers\WorkerOperation;
use Picqer\Barcode\BarcodeGeneratorPNG as Barcode;
use App\Jobs\RenderSamplingPlan;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Datatables;
use Exception;

class PaymentQuotationController extends Controller
{
    public function index(Request $request)
    {
        try {
            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::with([
                    'sales',
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'orderHeader.invoices'
                ])
                    ->where('id_cabang', $request->cabang)
                    // ->where('flag_status', '!=', 'ordered')
                    // ->where('is_active', true)
                    ->where('is_approved', true)
                    ->where('is_emailed', true)
                    ->whereYear('tanggal_penawaran', $request->year)
                    ->orderBy('tanggal_penawaran', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::with([
                    'sales',
                    'detail',
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'orderHeader.invoices'
                ])
                    ->where('id_cabang', $request->cabang)
                    // ->where('flag_status', '!=', 'ordered')
                    // ->where('is_active', true)
                    ->where('is_approved', true)
                    ->where('is_emailed', true)
                    ->whereYear('tanggal_penawaran', $request->year)
                    ->orderBy('tanggal_penawaran', 'desc');
            }

            $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
            if ($jabatan == 24 || $jabatan == 86) { // sales staff || Secretary Staff
                $data->where('sales_id', $this->user_id);
            } else if ($jabatan == 21 || $jabatan == 15 || $jabatan == 154) { // sales supervisor || sales manager || senior sales manager
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('id')->toArray();
                array_push($bawahan, $this->user_id);
                $data->whereIn('sales_id', $bawahan);
            }

            return DataTables::of($data)
                ->addColumn('count_jadwal', function ($row) {
                    return $row->sampling ? $row->sampling->sum(function ($sampling) {
                        return $sampling->jadwal->count();
                    }) : 0;
                })
                ->addColumn('count_detail', function ($row) {
                    return $row->detail ? $row->detail->count() : 0;
                })
                ->addColumn('total_invoice', function ($row) {
                    if (!$row->orderHeader) return 0;
                    return $row->orderHeader->invoices->count();
                })
                ->addColumn('nilai_pelunasan', function ($row) {
                    if (!$row->orderHeader || $row->orderHeader->invoices->isEmpty()) return '-';
                    $totalPelunasan = $row->orderHeader->invoices->sum('nilai_pelunasan');

                    return $totalPelunasan;
                })
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
