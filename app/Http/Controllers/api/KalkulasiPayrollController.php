<?php

namespace App\Http\Controllers\api;

use App\Models\PayrollHeader;
use App\Models\Payroll;
use App\Models\Kasbon;
use App\Models\PencadanganUpah;
use App\Models\DendaKaryawan;
use App\Models\MasterKaryawan;
use App\Models\RekapLiburKalender;
use App\Http\Controllers\Controller;
use App\Services\PencadanganUpahPayrollService;
use App\Services\PayrollDebtService;
use App\Services\PayrollDeductionService;
use App\Services\PayrollRecordSyncService;
use App\Services\Payroll\PayrollTrainingSpecialLeaveService;
use App\Services\Payroll\PayrollWorkingDaysService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;



class KalkulasiPayrollController extends Controller
{
    public function index(Request $request)
    {
        // dd($request->search);
        $data = PayrollHeader::with('payrolls')
                ->where('periode_payroll', 'like', $request->search . '%')
                ->where('is_active', true)
                ->where('is_approve', false)
                ->get();
        
        
        return Datatables::of($data)->make(true);       
        
    }

    public function showDataPayroll(Request $request)
    {
        // dd($request->all());
        // try {
            $data = Payroll::where('payroll_header_id', $request->id_header)->where('is_active', true)->newQuery();
            $data = $data->with(['karyawan' => function ($query) {
                $query->select('master_karyawan.id', 'nama_lengkap', 'nik_karyawan', 'is_active', 'effective_date');
            }, 'department' => function ($query) {
                $query->select('master_divisi.id', 'nama_divisi');
            }])
            ->orderBy('nik_karyawan', 'asc');

            // Calculate the total sums for gaji_pokok and tunjangan_kerja
            $totals = Payroll::where('payroll_header_id', $request->id_header)
            ->where('is_active', true)
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
            ->getData(); // Get raw data from DataTables

            // Add totals to the response
            $response->totals = $totals;

            // Return the modified response as JSON
            return response()->json($response);
        // } catch (\Exception $e) {
        //     return response()->json([
        //         'data' => [],
        //         'message' => $e->getMessage(),
        //     ], 201);
        // }
    }

    public function showData(Request $request)
    {
        try {
            $karyawanLogin = DB::table('master_karyawan')
                ->leftJoin('master_jabatan', 'master_karyawan.id_jabatan', '=', 'master_jabatan.id')
                ->where('master_karyawan.id', $this->user_id)
                ->select('master_jabatan.nama_jabatan', 'master_karyawan.grade')
                ->first();

            $isSpv = false;
            if ($karyawanLogin) {
                $namaJabatan = strtolower($karyawanLogin->nama_jabatan ?? '');
                $grade = strtoupper($karyawanLogin->grade ?? '');
                if ($grade === 'SUPERVISOR' || str_contains($namaJabatan, 'spv') || str_contains($namaJabatan, 'supervisor')) {
                    $isSpv = true;
                }
            }

            if ($isSpv && $request->status_karyawan === 'Supervisor') {
                return response()->json([
                    'data' => [],
                    'message' => 'Anda adalah Supervisor (SPV). Anda tidak diperbolehkan melihat/generate data payroll untuk status karyawan Supervisor!'
                ], 403);
            }

            $periodePayroll = $request->input('periode_payroll') ?? $request->input('periode');
            if (!$periodePayroll) {
                return response()->json([
                    'data' => [],
                    'message' => 'Periode payroll wajib diisi (periode_payroll).',
                ], 422);
            }
            $periode = $request->input('periode') ?? $periodePayroll;

            $masukKerjaJsonLengthSql = PayrollWorkingDaysService::sqlShowDataMasukKerja();
            $hariKerjaExpectedSql = PayrollWorkingDaysService::sqlShowDataHariKerja($periodePayroll);
            $tidakHadirSql = PayrollWorkingDaysService::sqlShowDataTidakHadir($periodePayroll);
            $potonganAbsenEligibleSql = '(master_karyawan.status_karyawan NOT IN ("Permanent", "Contract", "Special") OR master_karyawan.is_active = 0)';
            $potonganAbsenAmountSql = 'FLOOR(GREATEST(1000, ROUND((IFNULL(master_sallary.gaji_pokok, 0) + IFNULL(master_sallary.tunjangan_kerja, 0)) / GREATEST(' . $hariKerjaExpectedSql . ', 1) * ' . $tidakHadirSql . ', 0)) / 1000) * 1000';
            $potonganAbsenCaseSql = 'CASE WHEN ' . $potonganAbsenEligibleSql
                . ' AND rekap_masuk_kerja.tanggal IS NOT NULL AND rekap_masuk_kerja.is_active = 1'
                . ' AND ' . $hariKerjaExpectedSql . ' > 0 AND ' . $tidakHadirSql . ' > 0 THEN '
                . $potonganAbsenAmountSql . ' ELSE 0 END';

            $pcPencadanganColumn = PencadanganUpahPayrollService::sqlShowDataPencadanganColumn();
            $pcThpRefundAdd = PencadanganUpahPayrollService::sqlShowDataThpRefundAdd();
            $pcThpDeductionSub = PencadanganUpahPayrollService::sqlShowDataThpDeductionSub();

            $loanSql = PayrollDebtService::sqlPreviewAmount('kasbon', $periodePayroll);
            $fineSql = PayrollDebtService::sqlPreviewAmount('denda_karyawan', $periodePayroll);

            $query = DB::table('master_karyawan')
            ->select(
                DB::raw('master_karyawan.id as karyawan_id, master_karyawan.is_active as karyawan_is_active, master_karyawan.effective_date as karyawan_effective_date, master_karyawan.nama_lengkap, master_karyawan.nik_karyawan, master_karyawan.status_karyawan, master_divisi.nama_divisi, CASE WHEN payroll.id IS NULL THEN master_karyawan.id ELSE payroll.id_karyawan END as id_karyawan, CASE WHEN payroll.id IS NULL THEN master_karyawan.id_jabatan ELSE payroll.id_jabatan END as id_jabatan, CASE WHEN payroll.id IS NULL THEN master_jabatan.nama_jabatan ELSE payroll.nama_jabatan END as nama_jabatan, CASE WHEN payroll.id IS NULL THEN rekening_karyawan.no_rekening ELSE payroll.no_rekening END as no_rekening , CASE WHEN payroll.id IS NULL THEN rekening_karyawan.nama_bank ELSE payroll.nama_bank END as nama_bank,payroll.keterangan,
                ROUND(IFNULL(CASE WHEN payroll.id IS NULL THEN pph_21.pajak_bulanan ELSE payroll.pajak_pph END, 0), 0) as pajak,
                ROUND(IFNULL(CASE WHEN payroll.id IS NULL THEN master_sallary.gaji_pokok ELSE payroll.gaji_pokok END, 0), 0) as gaji_pokok, 
                ROUND(IFNULL(CASE WHEN payroll.id IS NULL THEN master_sallary.tunjangan_kerja ELSE payroll.tunjangan END, 0), 0) as tunjangan_kerja, 
                ROUND(IFNULL(SUM(CASE WHEN payroll.id IS NULL THEN bonus_karyawan.nominal ELSE payroll.bonus END), 0), 0) as bonus,
                ROUND(IFNULL(SUM(CASE WHEN payroll.id IS NULL THEN 0 ELSE payroll.incentive END), 0), 0) as incentive,
                ROUND(IFNULL(SUM(CASE WHEN payroll.id IS NULL THEN 0 ELSE payroll.potongan_lainnya END), 0), 0) as potongan_lainnya,
                CASE WHEN payroll.id IS NULL THEN ' . $loanSql . ' ELSE IFNULL(MAX(payroll.loan), 0) END as loan,
                CASE WHEN payroll.id IS NULL THEN ' . $fineSql . ' ELSE IFNULL(MAX(payroll.sanksi), 0) END as sanksi,
                ROUND(IFNULL(CASE WHEN payroll.id IS NULL THEN bpjs_tk.nominal_potongan_karyawan ELSE payroll.jamsostek END, 0), 0) as jamsostek, 
                ROUND(IFNULL(bpjs_tk.nominal_potongan_kantor, 0), 0) as nominal_potongan_kantor, 
                ROUND(IFNULL(CASE WHEN payroll.id IS NULL THEN bpjs_kesehatan.nominal_potongan_karyawan ELSE payroll.bpjs_kesehatan END, 0), 0) as bpjs_kesehatan, 
                ROUND(IFNULL(bpjs_kesehatan.nominal_potongan_kantor, 0), 0) as nominal_p_kantor, 
                payroll.id as payroll_id, payroll.status, payroll.payroll_header_id,
                ' . $pcPencadanganColumn . ' as pencadangan_upah,
                ROUND(CASE WHEN payroll.id IS NULL THEN (IFNULL(master_sallary.gaji_pokok, 0) + IFNULL(master_sallary.tunjangan_kerja, 0) + IFNULL(SUM(bonus_karyawan.nominal), 0) + ' . $pcThpRefundAdd . ' + ROUND(IFNULL(SUM(CASE WHEN payroll.id IS NULL THEN 0 ELSE payroll.incentive END), 0), 0) - 
                (' . $loanSql . ' + ' . $fineSql . ' + IFNULL(pph_21.pajak_bulanan, 0) +
                IFNULL(bpjs_tk.nominal_potongan_karyawan, 0) + IFNULL(bpjs_kesehatan.nominal_potongan_karyawan, 0) +
                ' . $potonganAbsenCaseSql . ' + ROUND(IFNULL(SUM(CASE WHEN payroll.id IS NULL THEN 0 ELSE payroll.potongan_lainnya END), 0), 0) +
                ' . $pcThpDeductionSub . ')) ELSE payroll.take_home_pay END, 0) as take_home_pay, 
                ' . $potonganAbsenCaseSql . ' as potongan_absen,
                ' . $masukKerjaJsonLengthSql . ' as masuk_kerja,
                ' . $hariKerjaExpectedSql . ' as hari_kerja,
                ' . $tidakHadirSql . ' as tidak_hadir')
            )
            ->leftJoin('rekap_masuk_kerja', function ($join) use ($periodePayroll) {
                $join->on('rekap_masuk_kerja.karyawan_id', '=', 'master_karyawan.id')
                    ->where('rekap_masuk_kerja.bulan', $periodePayroll)
                    ->where('rekap_masuk_kerja.is_active', 1);
            })
            ->leftJoin('master_sallary', function($join){
                $join->on('master_karyawan.id', '=', 'master_sallary.id_karyawan')
                    ->where(function ($salary) {
                        $salary->where('master_sallary.is_active', true)
                            ->orWhere(function ($fallback) {
                                // Use the latest salary history only for inactive employees without an active salary.
                                $fallback->where('master_karyawan.is_active', false)
                                    ->whereRaw('NOT EXISTS (SELECT 1 FROM master_sallary AS active_salary WHERE active_salary.id_karyawan = master_karyawan.id AND active_salary.is_active = 1)')
                                    ->whereRaw('master_sallary.id = (SELECT MAX(latest_salary.id) FROM master_sallary AS latest_salary WHERE latest_salary.id_karyawan = master_karyawan.id)');
                            });
                    });
            })
            ->leftJoin('bonus_karyawan', function ($join) {
                $join->on('master_karyawan.id', '=', 'bonus_karyawan.id_karyawan');
                PayrollRecordSyncService::applyJoinBonusFilter($join);
            })
            ->leftJoin('rekening_karyawan', function ($join) {
                $join->on('master_karyawan.id', '=', 'rekening_karyawan.id_karyawan')
                    ->whereRaw(PayrollRecordSyncService::sqlJoinLatestRow('rekening_karyawan'));
            })
            ->leftJoin('bpjs_tk', function ($join) {
                $join->on('master_karyawan.id', '=', 'bpjs_tk.id_karyawan')
                    ->whereRaw(PayrollRecordSyncService::sqlJoinLatestRow('bpjs_tk'));
            })
            ->leftJoin('bpjs_kesehatan', function ($join) use ($periode) {
                $join->on('master_karyawan.id', '=', 'bpjs_kesehatan.id_karyawan')
                    ->whereRaw(
                        'bpjs_kesehatan.id = (SELECT t.id FROM bpjs_kesehatan t WHERE t.id_karyawan = master_karyawan.id'
                        . ' AND t.bulan_efektif <= ? ORDER BY t.is_active DESC, t.id DESC LIMIT 1)',
                        [$periode]
                    );
            })
            ->leftJoin('kasbon', function ($join) use ($periode) {
                $join->on('master_karyawan.id', '=', 'kasbon.id_karyawan')
                    ->whereRaw(
                        PayrollRecordSyncService::sqlJoinLatestDeductionRow(
                            'kasbon',
                            'bulan_mulai_pemotongan',
                            'sisa_kasbon'
                        ),
                        [$periode]
                    );
            })
            ->leftJoin('denda_karyawan', function ($join) use ($periode) {
                $join->on('master_karyawan.id', '=', 'denda_karyawan.id_karyawan')
                    ->whereRaw(
                        PayrollRecordSyncService::sqlJoinLatestDeductionRow(
                            'denda_karyawan',
                            'bulan_mulai_pemotongan',
                            'sisa_denda'
                        ),
                        [$periode]
                    );
            })
            ->leftJoin('pph_21', function ($join) use ($periode) {
                $join->on('master_karyawan.id', '=', 'pph_21.id_karyawan')
                    ->whereRaw(
                        'pph_21.id = (SELECT t.id FROM pph_21 t WHERE t.id_karyawan = master_karyawan.id'
                        . ' AND t.bulan_mulai_pemotongan <= ? ORDER BY t.is_active DESC, t.id DESC LIMIT 1)',
                        [$periode]
                    );
            })
            ->leftJoin('payroll', function ($join) use ($periodePayroll) {
                $join->on('master_karyawan.id', '=', 'payroll.id_karyawan')
                    ->where('payroll.periode_payroll', '=', $periodePayroll)
                    ->where('payroll.is_active', '=', true);
            })
            ->leftJoin('pencadangan_upah', function ($join) use ($periodePayroll) {
                $join->on('master_karyawan.id', '=', 'pencadangan_upah.id_karyawan')
                    ->whereRaw(
                        'pencadangan_upah.id = (SELECT t.id FROM pencadangan_upah t WHERE t.id_karyawan = master_karyawan.id'
                        . ' AND t.bulan_efektif <= ?'
                        . ' AND (t.status = "ONGOING" OR t.status IS NULL)'
                        . ' AND ('
                        . '(master_karyawan.is_active = 1 AND t.is_active = 1)'
                        . ' OR (master_karyawan.is_active = 0 AND (t.is_active = 1 OR t.is_active = 0))'
                        . ')'
                        . ' ORDER BY t.id DESC LIMIT 1)',
                        [$periodePayroll]
                    );
            })
            ->leftJoin('master_divisi', function($join){
                $join->on('master_karyawan.id_department', '=', 'master_divisi.id');
            })
            ->leftJoin('master_jabatan', function($join){
                $join->on('master_karyawan.id_jabatan', '=', 'master_jabatan.id');
            })
            ->groupBy(DB::raw('master_karyawan.id, master_karyawan.nama_lengkap, master_karyawan.nik_karyawan, master_karyawan.status_karyawan, master_karyawan.is_active, master_karyawan.effective_date, master_divisi.nama_divisi, master_karyawan.id_jabatan, master_jabatan.nama_jabatan, payroll.id_jabatan, payroll.nama_jabatan, payroll.id_karyawan, pph_21.pajak_bulanan, rekening_karyawan.no_rekening, rekening_karyawan.nama_bank, payroll.keterangan, master_sallary.gaji_pokok, master_sallary.tunjangan_kerja, bpjs_tk.nominal_potongan_karyawan, bpjs_tk.nominal_potongan_kantor, bpjs_kesehatan.nominal_potongan_karyawan, bpjs_kesehatan.nominal_potongan_kantor, payroll.id, payroll.status, payroll.payroll_header_id, payroll.hari_kerja, payroll.gaji_pokok, payroll.tunjangan, payroll.pajak_pph, payroll.jamsostek, payroll.bpjs_kesehatan, payroll.bonus, payroll.incentive, payroll.potongan_lainnya, payroll.take_home_pay, payroll.no_rekening, payroll.nama_bank, rekap_masuk_kerja.is_active, rekap_masuk_kerja.tanggal, rekap_masuk_kerja.hari_kerja'))
            ->orderBy('master_karyawan.nik_karyawan', 'ASC');
            $this->constrainPayrollKaryawanVisibility($query, $periodePayroll);
            // ->where('master_karyawan.nik_karyawan', 'ISP232')
            if($request->status_karyawan == 'Supervisor'){
                $query->where('master_karyawan.grade', strtoupper($request->status_karyawan) );
            } else {
                $query->where('master_karyawan.status_karyawan', $request->status_karyawan);
                $query->where('master_karyawan.grade', '<>', 'SUPERVISOR');
            }

            $response = Datatables::of($query)
                ->make(true)
                ->getData();

            $trainingKaryawanIds = [];
            foreach ($response->data ?? [] as $row) {
                if (($row->status_karyawan ?? '') === 'Training' && empty($row->payroll_id)) {
                    $trainingKaryawanIds[] = (int) ($row->karyawan_id ?? $row->id_karyawan ?? 0);
                }
            }
            $specialLeaveDaysMap = PayrollTrainingSpecialLeaveService::mapApprovedSpecialLeaveDayCounts(
                $trainingKaryawanIds,
                $periodePayroll
            );
            foreach ($response->data ?? [] as $row) {
                $kid = (int) ($row->karyawan_id ?? $row->id_karyawan ?? 0);
                $excused = $specialLeaveDaysMap[$kid] ?? 0;
                PayrollTrainingSpecialLeaveService::applyToPreviewRow($row, $excused);
            }

            $totalFieldMap = [
                'gaji_pokok' => 'gaji_pokok',
                'tunjangan_kerja' => 'tunjangan_kerja',
                'bonus' => 'bonus',
                'pencadangan_upah' => 'pencadangan_upah',
                'jamsostek' => 'jamsostek',
                'bpjs_kesehatan' => 'bpjs_kesehatan',
                'loan' => 'loan',
                'sanksi' => 'sanksi',
                'potongan_absen' => 'potongan_absen',
                'pajak_pph' => 'pajak',
                'take_home_pay' => 'take_home_pay',
            ];
            $totals = array_fill_keys(array_keys($totalFieldMap), 0);
            foreach ($response->data ?? [] as $row) {
                foreach ($totalFieldMap as $outKey => $rowKey) {
                    $totals[$outKey] += (float) ($row->{$rowKey} ?? 0);
                }
            }
            $response->totals = $totals;

            // Return the modified response as JSON
            return response()->json($response);
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function writeData(Request $request)
    {
        $this->validate($request, [
            'id_karyawan' => 'required|integer|min:1',
            'payroll_header_id' => 'required|integer|min:1',
            'periode_payroll' => 'required|date_format:Y-m',
            'take_home_pay' => 'required',
        ]);
        try {
            $payroll = new Payroll();
            foreach (['nik_karyawan', 'payroll_header_id', 'id_karyawan', 'status_karyawan',
                'id_jabatan', 'nama_jabatan', 'periode_payroll', 'hari_kerja', 'tidak_hadir',
                'no_rekening', 'nama_bank', 'keterangan'] as $field) {
                $payroll->$field = $request->input($field);
            }
            $payroll->karyawan = $request->nama_lengkap;
            $moneyFields = [
                'gaji_pokok' => 'gaji_pokok', 'tunjangan' => 'tunjangan_kerja',
                'pencadangan_upah' => 'pencadangan_upah', 'bonus' => 'bonus', 'incentive' => 'incentive',
                'jamsostek' => 'jamsostek', 'bpjs_kesehatan' => 'bpjs_kesehatan',
                'loan' => 'loan', 'sanksi' => 'sanksi', 'potongan_absen' => 'potongan_absen',
                'potongan_lainnya' => 'potongan_lainnya', 'pajak_pph' => 'pajak',
                'take_home_pay' => 'take_home_pay',
            ];
            foreach ($moneyFields as $field => $input) {
                $payroll->$field = PayrollDebtService::amount($request->input($input));
            }
            (new PayrollDeductionService())->submit($payroll, $this->karyawan);
            return response()->json(['success' => true, 'message' => 'Data payroll berhasil disimpan'], 200);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data payroll: ' . $e->getMessage()], 500);
        }
    }

    public function canclePayroll(Request $request)
    {
        $this->validate($request, ['id' => 'required|integer|min:1']);
        try {
            (new PayrollDeductionService())->cancel((int) $request->id, $this->karyawan);
            return response()->json(['success' => true, 'message' => 'Data payroll berhasil dicancel'], 200);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mencancel data payroll: ' . $e->getMessage()], 500);
        }
    }

    public function showRekapDataHeader(Request $request)
    {
        try {
            $data = PayrollHeader::leftJoin('payroll', function($join){
                    $join->on('payroll_header.id', '=', 'payroll.payroll_header_id')
                        ->where('payroll.is_active', '=', true);
                })
                ->select(
                    'payroll_header.id',
                    'payroll_header.no_document',
                    'payroll_header.periode_payroll',
                    'payroll_header.status_karyawan',
                    'payroll_header.keterangan',
                    'payroll_header.status',
                    'payroll_header.transfer_at',
                    'payroll_header.transfer_by',
                    DB::raw('SUM(payroll.take_home_pay) as total_take_home_pay, COUNT(payroll.id) as payrolls')
                )
                ->where('payroll_header.is_active', true)
                ->where('payroll_header.status', '=','TRANSFER')
                ->groupBy(
                    'payroll_header.id',
                    'payroll_header.no_document',
                    'payroll_header.periode_payroll',
                    'payroll_header.status_karyawan',
                    'payroll_header.keterangan',
                    'payroll_header.status',
                    'payroll_header.transfer_at',
                    'payroll_header.transfer_by'
                );

            return Datatables::of($data)->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 201);
        }
    }

    private function showRekapData($request){
        try {
            $data = Payroll::where('payroll_header_id', $request->id_header)
                ->where('is_active', true)
                ->with(['karyawan' => function ($query) {
                    $query->select('users.id', 'nama_lengkap');
                }, 'master_divisi' => function ($query) {
                    $query->select('master_divisi.id', 'name_department');
                }]);

            // Calculate the total sums
            $totalGajiPokok = Payroll::where('payroll_header_id', $request->id_header)
                ->where('is_active', true)
                ->sum('gaji_pokok');
            $totalTunjangan = Payroll::where('payroll_header_id', $request->id_header)
                ->where('is_active', true)
                ->sum('tunjangan');
            $totalBonus = Payroll::where('payroll_header_id', $request->id_header)
                ->where('is_active', true)
                ->sum('bonus');
            $totalPencadangan = Payroll::where('payroll_header_id', $request->id_header)
                ->where('is_active', true)
                ->sum('pencadangan_upah');
            $totalJamsostek = Payroll::where('payroll_header_id', $request->id_header)
                ->where('is_active', true)
                ->sum('jamsostek');
            $totalBpjsK = Payroll::where('payroll_header_id', $request->id_header)
                ->where('is_active', true)
                ->sum('bpjs_kesehatan');
            $totalLoan = Payroll::where('payroll_header_id', $request->id_header)
                ->where('is_active', true)
                ->sum('loan');
            $totalSanksi = Payroll::where('payroll_header_id', $request->id_header)
                ->where('is_active', true)
                ->sum('sanksi');
            $totalAbsen = Payroll::where('payroll_header_id', $request->id_header)
                ->where('is_active', true)
                ->sum('potongan_absen');
            $totalPph = Payroll::where('payroll_header_id', $request->id_header)
                ->where('is_active', true)
                ->sum('pajak_pph');
            $totalThp = Payroll::where('payroll_header_id', $request->id_header)
                ->where('is_active', true)
                ->sum('take_home_pay');

            // Prepare DataTable response
            $response = Datatables::of($data)
                ->make(true)
                ->getData();

            // Add totals to the response
            $response->totals = [
                'gaji_pokok' => $totalGajiPokok,
                'tunjangan' => $totalTunjangan,
                'bonus' => $totalBonus,
                'pencadangan_upah' => $totalPencadangan,
                'jamsostek' => $totalJamsostek,
                'bpjs_kes' => $totalBpjsK,
                'loan' => $totalLoan,
                'sanksi' => $totalSanksi,
                'potongan_absen' => $totalAbsen,
                'pajak_pph' => $totalPph,
                'take_home_pay' => $totalThp,
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 201);
        }
    }

    public function generateData(Request $request)
    {      
        try {
            $karyawanLogin = DB::table('master_karyawan')
                ->leftJoin('master_jabatan', 'master_karyawan.id_jabatan', '=', 'master_jabatan.id')
                ->where('master_karyawan.id', $this->user_id)
                ->select('master_jabatan.nama_jabatan', 'master_karyawan.grade')
                ->first();

            $isSpv = false;
            if ($karyawanLogin) {
                $namaJabatan = strtolower($karyawanLogin->nama_jabatan ?? '');
                $grade = strtoupper($karyawanLogin->grade ?? '');
                if ($grade === 'SUPERVISOR' || str_contains($namaJabatan, 'spv') || str_contains($namaJabatan, 'supervisor')) {
                    $isSpv = true;
                }
            }

            if ($isSpv && $request->status_karyawan === 'Supervisor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda adalah Supervisor (SPV). Anda tidak diperbolehkan melakukan generate/kalkulasi payroll untuk status karyawan Supervisor!'
                ], 403);
            }

            $conn_latestPayroll = new PayrollHeader;
            $latestPayroll = PayrollHeader::orderBy('id', 'desc')->first();
            
            $currentYear = date('y');
            $currentMonth = date('m');
            
            $romanMonth = [
                '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IV',
                '05' => 'V', '06' => 'VI', '07' => 'VII', '08' => 'VIII',
                '09' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII'
            ];
            
            if ($latestPayroll) {
                $lastKodePayroll = $latestPayroll->no_document;
                $lastYear = substr($lastKodePayroll, 8, 2);
                $lastNumber = intval(substr($lastKodePayroll, -3));
                
                if ($lastYear == $currentYear) {
                    $newNumber = $lastNumber + 1;
                } else {
                    $newNumber = 1;
                }
            } else {
                $newNumber = 1;
            }
            
            $no_document = sprintf("ISL/PRL/%s-%s/%03d", 
            $currentYear, 
            $romanMonth[$currentMonth], 
            $newNumber
        );
        
        $payrollHeader = new PayrollHeader();
        $payrollHeader->periode_payroll = $request->periode_payroll;
        $payrollHeader->status_karyawan = $request->status_karyawan;
        $payrollHeader->keterangan = $request->keterangan;
        $payrollHeader->no_document = $no_document;
        $payrollHeader->created_by = $this->karyawan;
        $payrollHeader->created_at = DATE('Y-m-d H:i:s');
        $payrollHeader->save();

            $message = 'Generate Payroll data successfully';

            return response()->json([
                'success' => true,
                'message' => $message
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Payroll Not Found'
            ], 401);
        }
    }

    public function deleteHeader(Request $request){
        try {
            $data = PayrollHeader::find($request->id);
            
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll header not found'
                ], 404);
            }

            $data->is_active = false;
            $data->deleted_at = DATE('Y-m-d H:i:s');
            $data->deleted_by = $this->karyawan;
            $data->save();

            return response()->json([
                'success' => true,
                'message' => 'Payroll header data deleted successfully'
            ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function approveHeader(Request $request){
        try {
            $data = PayrollHeader::find($request->id);
            
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll header not found'
                ], 404);
            }

            $data->is_approve = true;
            $data->approved_at = DATE('Y-m-d H:i:s');
            $data->approved_by = $this->karyawan;
            $data->save();

            return response()->json([
                'success' => true,
                'message' => 'Payroll header data approved successfully'
            ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    /**
     * Aktif: wajib rekap absensi aktif periode.
     * Non-aktif: hanya bulan keluar (periode = bulan effective_date).
     * Gaji bulan keluar bisa diproses/bayar di bulan kalender berikutnya (+1 bulan),
     * tapi periode payroll-nya tetap bulan keluar — tidak ikut periode gaji bulan berikutnya.
     */
    private function constrainPayrollKaryawanVisibility($query, string $periode): void
    {
        $query->where(function ($outer) use ($periode) {
            $outer->where(function ($active) {
                $active->where('master_karyawan.is_active', 1)
                    ->whereNotNull('rekap_masuk_kerja.tanggal')
                    ->where('rekap_masuk_kerja.is_active', 1);
            })->orWhere(function ($inactive) use ($periode) {
                $inactive->where('master_karyawan.is_active', 0)
                    ->whereNotNull('master_karyawan.effective_date')
                    ->whereRaw('DATE_FORMAT(master_karyawan.effective_date, "%Y-%m") = ?', [$periode]);
            });
        });
    }

    private function generateNoDoc()
    {
        $latestDocument = PayrollHeader::orderBy('kode_kasbon', 'desc')->first();

        $currentYear = DATE('y');
        $currentMonth = DATE('m');

        $romanMonth = [
            '01' => 'I',
            '02' => 'II',
            '03' => 'III',
            '04' => 'IV',
            '05' => 'V',
            '06' => 'VI',
            '07' => 'VII',
            '08' => 'VIII',
            '09' => 'IX',
            '10' => 'X',
            '11' => 'XI',
            '12' => 'XII'
        ];

        if ($latestDocument) {
            $lastNumber = 0;
            if (preg_match('/(\d{6})$/', $latestDocument->kode_kasbon, $matches)) {
                $lastNumber = intval($matches[1]);
            }
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        $formattedNumber = str_pad($newNumber, 6, '0', STR_PAD_LEFT);

        $no_document = sprintf(
            "ISL/PRL/%s-%s/%s",
            $currentYear,
            $romanMonth[$currentMonth],
            $formattedNumber
        );

        return $no_document;
    }
}
