<?php

namespace App\Http\Controllers\api;

use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Picqer\Barcode\BarcodeGeneratorPNG as Barcode;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Yajra\DataTables\DataTables;
use Exception;

class RekapQuotationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $tableName = $request->mode === 'kontrak'
                ? 'request_quotation_kontrak_H'
                : 'request_quotation';

            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::with([
                    'sales',
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'alasanVoidQt'
                ])
                    ->where($tableName . '.id_cabang', $request->cabang)
                    // ->where('flag_status', '!=', 'ordered')
                    // ->where('is_active', true)
                    ->where($tableName . '.is_approved', true)
                    ->where($tableName . '.is_emailed', true)
                    ->whereYear($tableName . '.tanggal_penawaran', $request->year)
                    ->orderBy($tableName . '.tanggal_penawaran', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::with([
                    'sales',
                    'detail',
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'alasanVoidQt'
                ])
                    ->where($tableName . '.id_cabang', $request->cabang)
                    // ->where('flag_status', '!=', 'ordered')
                    // ->where('is_active', true)
                    ->where($tableName . '.is_approved', true)
                    ->where($tableName . '.is_emailed', true)
                    ->whereYear($tableName . '.tanggal_penawaran', $request->year)
                    ->orderBy($tableName . '.tanggal_penawaran', 'desc');
            }

            $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
            switch ($jabatan) {
                case 24: // Sales Staff
                    $data->where($tableName . '.sales_id', $this->user_id);
                    break;
                case 21: // Sales Supervisor
                    $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                        ->pluck('id')
                        ->toArray();
                    array_push($bawahan, $this->user_id);
                    $data->whereIn($tableName . '.sales_id', $bawahan);
                    break;
            }

            $data->select($tableName . '.*');

            return DataTables::of($data)
                ->addColumn('count_jadwal', function ($row) {
                    return $row->sampling ? $row->sampling->sum(function ($sampling) {
                        return $sampling->jadwal->count();
                    }) : 0;
                })
                ->addColumn('count_detail', function ($row) {
                    return $row->detail ? $row->detail->count() : 0;
                })
                ->filterColumn('status_quotation', function ($query, $keyword) use ($tableName) {
                    $keyword = trim((string) $keyword);
                    if ($keyword === '') {
                        return;
                    }
                    $query->where($tableName . '.status_quotation', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('sales.nama_lengkap', function ($query, $keyword) {
                    $keyword = trim((string) $keyword);
                    if ($keyword === '') {
                        return;
                    }
                    $query->whereHas('sales', function ($sales) use ($keyword) {
                        $sales->where('nama_lengkap', 'like', '%' . $keyword . '%');
                    });
                })
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function randomstr($str)
    {
        $result = substr(str_shuffle($str), 0, 12);
        return $result;
    }
}
