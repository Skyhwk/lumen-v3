<?php

namespace App\Http\Controllers\api;

use App\Models\PayrollHeader;
use App\Models\Payroll;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Services\GetBawahan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class MonthlySalaryController extends Controller
{
    /**
     * Mode development: set true untuk melewati filter akses & bawahan
     * Set false jika sudah siap production
     */
    private $devMode = false;

    /**
     * Daftar jabatan yang diizinkan mengakses Monthly Salary
     */
    private $allowedJabatanIds = [1,2,3,10,15,26,30,40,45,46,47,48,91,97,99,102,108,111,118,127,128,134,136,139,140,142,147,152,154,157];

    /**
     * Cek apakah user yang login memiliki akses berdasarkan id_jabatan
     */
    private function hasAccess()
    {
        if ($this->devMode) {
            return true;
        }

        if (!$this->user_id) {
            return false;
        }

        $karyawan = MasterKaryawan::where('id', $this->user_id)
            ->where('is_active', true)
            ->first();

        if (!$karyawan) {
            return false;
        }

        return in_array($karyawan->id_jabatan, $this->allowedJabatanIds);
    }

    /**
     * Ambil ID bawahan user yang login (menggunakan atasan_langsung)
     * Jika devMode aktif, return null (tidak filter)
     */
    private function getBawahanIds()
    {
        if ($this->devMode) {
            return null; // null = tidak filter, tampilkan semua
        }

        if (!$this->user_id) {
            return [];
        }

        $bawahan = GetBawahan::where('id', $this->user_id)->get();

        return $bawahan->pluck('id')->toArray();
    }

    /**
     * Index — Menampilkan daftar payroll header (rekap per periode)
     */
    public function index(Request $request)
    {
        try {
            // Cek akses berdasarkan jabatan
            // if (!$this->hasAccess()) {
            //     return response()->json([
            //         'data' => [],
            //         'recordsTotal' => 0,
            //         'recordsFiltered' => 0,
            //         'message' => 'Anda tidak memiliki akses ke halaman ini',
            //     ], 200);
            // }

            // Ambil ID bawahan (null jika devMode)
            $bawahanIds = $this->getBawahanIds();
            

            $query = PayrollHeader::select(
                'payroll_header.*',
                DB::raw('SUM(payroll.take_home_pay) as total_take_home_pay'),
                DB::raw('COUNT(DISTINCT payroll.id) as total_payroll')
            )
            ->leftJoin('payroll', function($join) use ($bawahanIds) {
                $join->on('payroll.payroll_header_id', '=', 'payroll_header.id')
                     ->where('payroll.is_active', true);
                // Filter bawahan hanya jika bukan devMode
                if ($bawahanIds !== null) {
                    $join->whereIn('payroll.id_karyawan', $bawahanIds);
                }
            })
            ->where('payroll_header.is_active', true)
            ->where('payroll_header.is_approve', true)
            ->where('payroll_header.deleted_by', null)
            ->where('payroll_header.status', '=', 'TRANSFER')
            ->where('payroll_header.periode_payroll', 'like', $request->search . '%')
            ->groupBy(
                'payroll_header.id',
                'payroll_header.no_document',
                'payroll_header.status_karyawan',
                'payroll_header.periode_payroll',
                'payroll_header.status',
                'payroll_header.tgl_transfer',
                'payroll_header.keterangan',
                'payroll_header.is_active',
                'payroll_header.is_approve',
                'payroll_header.is_download',
                'payroll_header.created_at',
                'payroll_header.created_by',
                'payroll_header.deleted_at',
                'payroll_header.deleted_by'
            )
            ->orderBy('payroll_header.status', 'desc')
            ->orderBy('payroll_header.id', 'desc');

            // Jika bukan devMode, hanya tampilkan header yang punya data bawahan
            if ($bawahanIds !== null) {
                $query->havingRaw('COUNT(DISTINCT payroll.id) > 0');
            }

            $data = $query->get();

            return Datatables::of($data)->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 201);
        }
    }

    /**
     * showDataPayroll — Menampilkan detail payroll per karyawan
     */
    public function showDataPayroll(Request $request)
    {
        try {
            // Cek akses berdasarkan jabatan
            // if (!$this->hasAccess()) {
            //     return response()->json([
            //         'data' => [],
            //         'message' => 'Anda tidak memiliki akses',
            //     ], 200);
            // }

            // Ambil ID bawahan (null jika devMode)
            $bawahanIds = $this->getBawahanIds();
            
            $id_header = $request->id_header;

            // Jika id_header tidak dikirim, cari header berdasarkan periode (default bulan ini)
            $headerIds = [];
            if (empty($id_header)) {
                $periode = $request->periode_payroll;
                
                $headers = PayrollHeader::where('periode_payroll', $periode)
                    ->where('is_active', true)
                    ->where('status', 'TRANSFER')
                    ->pluck('id')
                    ->toArray();

                if (empty($headers)) {
                    return response()->json([
                        'data' => [],
                        'recordsTotal' => 0,
                        'recordsFiltered' => 0,
                        'message' => 'Data payroll untuk periode ini belum tersedia',
                        'totals' => []
                    ]);
                }
                $headerIds = $headers;
            } else {
                $headerIds = [$id_header];
            }
            
            $data = Payroll::whereIn('payroll_header_id', $headerIds)
                ->where('is_active', true)
                ->when($bawahanIds !== null, function ($query) use ($bawahanIds) {
                    $query->whereIn('id_karyawan', $bawahanIds);
                })
                ->with(['karyawan' => function ($query) {
                    $query->select('master_karyawan.id', 'nama_lengkap', 'nik_karyawan');
                }, 'department' => function ($query) {
                    $query->select('master_divisi.id', 'nama_divisi');
                }])
                ->orderBy('nik_karyawan', 'asc');

            // Calculate totals
            $totals = Payroll::whereIn('payroll_header_id', $headerIds)
                ->where('is_active', true)
                ->when($bawahanIds !== null, function ($query) use ($bawahanIds) {
                    $query->whereIn('id_karyawan', $bawahanIds);
                })
                ->selectRaw('
                    sum(gaji_pokok) as total_gaji_pokok,
                    sum(tunjangan) as total_tunjangan,
                    sum(bonus) as total_bonus,
                    sum(pencadangan_upah) as total_pencadangan_upah,
                    sum(jamsostek) as total_jamsostek,
                    sum(bpjs_kesehatan) as total_bpjs_kesehatan,
                    sum(loan) as total_loan,
                    sum(sanksi) as total_sanksi,
                    sum(potongan_absen) as total_potongan_absen,
                    sum(pajak_pph) as total_pajak_pph,
                    sum(take_home_pay) as total_take_home_pay
                ')
                ->first();

            // Prepare DataTable response
            $response = Datatables::of($data)
                ->make(true)
                ->getData();

            // Add totals to the response
            $response->totals = $totals;

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 201);
        }
    }
}
