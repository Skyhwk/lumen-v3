<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PengajuanFeeSampling;
use Yajra\DataTables\Facades\DataTables;

class MonitoringFeeController extends Controller
{
    public function index(Request $request)
    {
        $processed = (int) $request->input('processed', 1);
        $isManager = $this->grade === 'MANAGER' || $this->grade === 'SENIOR MANAGER';

        $data = PengajuanFeeSampling::query();
        if ($processed) {
            $data->whereNotNull('transfer_date');
        } else {
            $data->whereNull('transfer_date')->where('is_reject_finance', 0);
        }

        $data->select('pengajuan_fee_sampling.*')->selectRaw(
            '(select count(*) from pengajuan_fee_sampling_detail where pengajuan_fee_sampling_id = pengajuan_fee_sampling.id) as jumlah_pengajuan'
        );

        return DataTables::of($data)
            ->addColumn('custom_status_info', function ($row) {
                if ($row->transfer_date !== null) {
                    return 'DONE';
                }
                if ($row->is_reject_finance == 1) {
                    return 'Ditolak Finance';
                }
                if ($row->is_approve_finance == 1) {
                    return 'Disetujui Finance';
                }
                return 'Menunggu Finance';
            })
            ->addColumn('jumlah_pengajuan', function ($row) {
                return (int) ($row->jumlah_pengajuan ?? collect($row->detail_fee)->count());
            })
            ->editColumn('total_fee_approve', function ($row) use ($isManager) {
                return $isManager ? $row->total_fee_approve : '******';
            })
            ->editColumn('total_fee_request', function ($row) use ($isManager) {
                return $isManager ? $row->total_fee_request : '******';
            })
            ->filterColumn('tgl_pengajuan', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    return;
                }
                $date = strlen($keyword) >= 10 ? substr($keyword, 0, 10) : $keyword;
                $query->whereDate('tgl_pengajuan', $date);
            })
            ->filterColumn('jumlah_pengajuan', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '' || !is_numeric($keyword)) {
                    return;
                }
                $count = (int) $keyword;
                $query->whereRaw(
                    '(select count(*) from pengajuan_fee_sampling_detail where pengajuan_fee_sampling_id = pengajuan_fee_sampling.id) = ?',
                    [$count]
                );
            })
            ->make(true);
    }

    public function summary(Request $request)
    {
        $processed = (int) $request->input('processed', 1);
        $isManager = $this->grade === 'MANAGER' || $this->grade === 'SENIOR MANAGER';

        if (!$isManager) {
            return response()->json([
                'data' => '******',
            ]);
        }

        $query = PengajuanFeeSampling::query();
        if ($processed) {
            $query->whereNotNull('transfer_date');
        } else {
            $query->whereNull('transfer_date');
        }

        if ($request->filled('tgl_pengajuan')) {
            $date = substr(trim($request->tgl_pengajuan), 0, 10);
            $query->whereDate('tgl_pengajuan', $date);
        }

        if ($request->filled('jumlah_pengajuan') && is_numeric(trim($request->jumlah_pengajuan))) {
            $count = (int) trim($request->jumlah_pengajuan);
            $query->whereRaw(
                '(select count(*) from pengajuan_fee_sampling_detail where pengajuan_fee_sampling_id = pengajuan_fee_sampling.id) = ?',
                [$count]
            );
        }

        if ($request->filled('created_by')) {
            $keyword = strtolower(trim($request->created_by));
            $query->whereRaw('LOWER(created_by) LIKE ?', ["%{$keyword}%"]);
        }

        return response()->json([
            'data' => (float) $query->sum('total_fee_approve'),
        ]);
    }

    public function getDetailFee(Request $request)
    {
        $isManager = $this->grade === 'MANAGER' || $this->grade === 'SENIOR MANAGER';

        $data = PengajuanFeeSampling::with(['detail_fee' => function ($q) {
            $q->orderBy('tanggal', 'asc')->orderBy('id', 'asc');
        }])->find($request->id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        if (!$isManager) {
            $data->total_fee_request = '******';
            $data->total_fee_approve = '******';

            $data->detail_fee->transform(function ($detail) {
                $detail->total_fee = '******';
                $detail->overtime = '******';
                $detail->fee_pokok = '******';
                $detail->fee_tambahan = '******';

                if (is_array($detail->rincian_fee_pokok)) {
                    $rincianPokok = $detail->rincian_fee_pokok;
                    foreach (['base', 'tambah_tempat_kedua', 'tambah_tempat_selanjutnya'] as $key) {
                        if (array_key_exists($key, $rincianPokok)) {
                            $rincianPokok[$key] = '******';
                        }
                    }
                    $detail->rincian_fee_pokok = $rincianPokok;
                }

                if (is_array($detail->fee_tambahan_rincian)) {
                    $rincianTambahan = $detail->fee_tambahan_rincian;
                    foreach (['driver', 'luar_kota', 'sampling_24jam', 'luar_kota_24jam', 'isokinetik', 'hari_libur'] as $key) {
                        if (array_key_exists($key, $rincianTambahan)) {
                            $rincianTambahan[$key] = '******';
                        }
                    }
                    $detail->fee_tambahan_rincian = $rincianTambahan;
                }

                return $detail;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }
}
