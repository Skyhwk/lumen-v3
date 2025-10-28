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
                $query->select('master_karyawan.id', 'nama_lengkap', 'nik_karyawan');
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
            // dd($request->all());
            // Calculate working days from rekap_libur_kalender
            $workingDaysQuery = DB::table('rekap_libur_kalender')
                ->select(DB::raw('
                    JSON_LENGTH(JSON_EXTRACT(tanggal, \'$."' . $request->periode . '"\')) as working_days
                '))
                ->where(DB::raw('JSON_EXTRACT(tanggal, \'$."' . $request->periode . '"\')'), '!=', 'null')
                ->where('is_active', 1)
                ->where('tahun', explode('-', $request->periode)[0])
                ->first();

            $workingDays = $workingDaysQuery ? $workingDaysQuery->working_days : 0;

            $query = DB::table('master_karyawan')
            ->select(
                DB::raw('master_karyawan.id as karyawan_id, master_karyawan.nama_lengkap, master_karyawan.nik_karyawan, master_karyawan.status_karyawan, master_divisi.nama_divisi, CASE WHEN payroll.id IS NULL THEN rekening_karyawan.no_rekening ELSE payroll.no_rekening END as no_rekening , CASE WHEN payroll.id IS NULL THEN rekening_karyawan.nama_bank ELSE payroll.nama_bank END as nama_bank,payroll.keterangan,
                ROUND(IFNULL(CASE WHEN payroll.id IS NULL THEN pph_21.pajak_bulanan ELSE payroll.pajak_pph END, 0), 0) as pajak,
                ROUND(IFNULL(CASE WHEN payroll.id IS NULL THEN master_sallary.gaji_pokok ELSE payroll.gaji_pokok END, 0), 0) as gaji_pokok, 
                ROUND(IFNULL(CASE WHEN payroll.id IS NULL THEN master_sallary.tunjangan_kerja ELSE payroll.tunjangan END, 0), 0) as tunjangan_kerja, 
                ROUND(IFNULL(SUM(CASE WHEN payroll.id IS NULL THEN bonus_karyawan.nominal ELSE payroll.bonus END), 0), 0) as bonus,
                ROUND(IFNULL(SUM(CASE WHEN payroll.id IS NULL THEN 0 ELSE payroll.incentive END), 0), 0) as incentive,
                ROUND(IFNULL(SUM(CASE WHEN payroll.id IS NULL THEN 0 ELSE payroll.potongan_lainnya END), 0), 0) as potongan_lainnya,
                ROUND(IFNULL(SUM(CASE WHEN payroll.id IS NULL THEN CASE 
                    WHEN kasbon.sisa_tenor > 0 AND kasbon.is_active = 1 AND kasbon.bulan_mulai_pemotongan <= "' . $request->periode . '" 
                    THEN kasbon.nominal_potongan
                    WHEN kasbon.sisa_tenor IS NULL AND kasbon.is_active = 1 AND kasbon.bulan_mulai_pemotongan <= "' . $request->periode . '"
                    THEN kasbon.nominal_potongan
                    ELSE 0
                END ELSE payroll.loan END), 0), 0) AS loan,
                ROUND(IFNULL(SUM(CASE WHEN payroll.id IS NULL THEN denda_karyawan.nominal_potongan ELSE payroll.sanksi END), 0), 0) as sanksi, 
                ROUND(IFNULL(CASE WHEN payroll.id IS NULL THEN bpjs_tk.nominal_potongan_karyawan ELSE payroll.jamsostek END, 0), 0) as jamsostek, 
                ROUND(IFNULL(bpjs_tk.nominal_potongan_kantor, 0), 0) as nominal_potongan_kantor, 
                ROUND(IFNULL(CASE WHEN payroll.id IS NULL THEN bpjs_kesehatan.nominal_potongan_karyawan ELSE payroll.bpjs_kesehatan END, 0), 0) as bpjs_kesehatan, 
                ROUND(IFNULL(bpjs_kesehatan.nominal_potongan_kantor, 0), 0) as nominal_p_kantor, 
                payroll.id as payroll_id, payroll.status, payroll.payroll_header_id,
                ROUND(IFNULL(SUM(CASE WHEN payroll.id IS NULL THEN pencadangan_upah.nominal_berjalan ELSE payroll.pencadangan_upah END), 0), 0) as pencadangan_upah,
                ROUND(CASE WHEN payroll.id IS NULL THEN (IFNULL(master_sallary.gaji_pokok, 0) + IFNULL(master_sallary.tunjangan_kerja, 0) + IFNULL(SUM(bonus_karyawan.nominal), 0) + IFNULL(SUM(CASE 
                    WHEN pencadangan_upah.tenor_berjalan > 0 AND pencadangan_upah.tenor_berjalan <= pencadangan_upah.tenor
                    THEN pencadangan_upah.nominal
                    ELSE 0
                END), 0) + ROUND(IFNULL(SUM(CASE WHEN payroll.id IS NULL THEN 0 ELSE payroll.incentive END), 0), 0) - 
                (IFNULL(SUM(CASE 
                    WHEN kasbon.sisa_tenor > 0 AND kasbon.is_active = 1 AND kasbon.bulan_mulai_pemotongan <= "' . $request->periode . '" 
                    THEN kasbon.nominal_potongan
                    WHEN kasbon.sisa_tenor IS NULL AND kasbon.is_active = 1 AND kasbon.bulan_mulai_pemotongan <= "' . $request->periode . '"
                    THEN kasbon.nominal_potongan
                    ELSE 0 
                END), 0) + IFNULL(SUM(denda_karyawan.nominal_potongan), 0) + IFNULL(SUM(pph_21.pajak_bulanan), 0) +
                IFNULL(bpjs_tk.nominal_potongan_karyawan, 0) + IFNULL(bpjs_kesehatan.nominal_potongan_karyawan, 0) +
                CASE 
                    WHEN master_karyawan.status_karyawan NOT IN ("Permanent", "Contract", "Special") AND (' . $workingDays . ' - JSON_LENGTH(rekap_masuk_kerja.tanggal)) > 0
                    THEN FLOOR(GREATEST(1000, ROUND((IFNULL(master_sallary.gaji_pokok, 0) + IFNULL(master_sallary.tunjangan_kerja, 0)) / ' . $workingDays . ' * (' . $workingDays . ' - JSON_LENGTH(rekap_masuk_kerja.tanggal)), 0)) / 1000) * 1000
                    ELSE 0
                END + ROUND(IFNULL(SUM(CASE WHEN payroll.id IS NULL THEN 0 ELSE payroll.potongan_lainnya END), 0), 0) +
                IFNULL(SUM(CASE 
                    WHEN pencadangan_upah.tenor_berjalan < 0
                    THEN pencadangan_upah.nominal
                    ELSE 0
                END), 0))) ELSE payroll.take_home_pay END, 0) as take_home_pay, 
                CASE WHEN payroll.id IS NULL THEN CASE 
                    WHEN master_karyawan.status_karyawan NOT IN ("Permanent", "Contract", "Special") AND (' . $workingDays . ' - JSON_LENGTH(rekap_masuk_kerja.tanggal)) > 0
                    THEN FLOOR(GREATEST(1000, ROUND((IFNULL(master_sallary.gaji_pokok, 0) + IFNULL(master_sallary.tunjangan_kerja, 0)) / ' . $workingDays . ' * (' . $workingDays . ' - JSON_LENGTH(rekap_masuk_kerja.tanggal)), 0)) / 1000) * 1000
                    ELSE 0
                END ELSE payroll.potongan_absen END as potongan_absen,  
                JSON_LENGTH(rekap_masuk_kerja.tanggal) as masuk_kerja,
                CASE WHEN payroll.id IS NULL THEN ' . $workingDays . ' ELSE payroll.hari_kerja END as hari_kerja,
                CASE WHEN payroll.id IS NULL THEN (' . $workingDays . ' - JSON_LENGTH(rekap_masuk_kerja.tanggal)) ELSE payroll.tidak_hadir END as tidak_hadir')
            )
            ->join('rekap_masuk_kerja', function($join){
                $join->on('rekap_masuk_kerja.karyawan_id', '=', 'master_karyawan.id')->where('rekap_masuk_kerja.is_active', true);
            })
            ->leftJoin('master_sallary', function($join){
                $join->on('master_karyawan.nik_karyawan', '=', 'master_sallary.nik_karyawan')->where('master_sallary.is_active', true);
            })
            ->leftJoin('bonus_karyawan', function($join) use ($request){
                $join->on('master_karyawan.nik_karyawan', '=', 'bonus_karyawan.nik_karyawan')
                ->where('bonus_karyawan.is_active', true);
            })
            ->leftJoin('rekening_karyawan', function($join) use ($request){
                $join->on('master_karyawan.nik_karyawan', '=', 'rekening_karyawan.nik_karyawan')
                ->where('rekening_karyawan.is_active', true);
            })
            ->leftJoin('bpjs_tk', function($join) use ($request){
                $join->on('master_karyawan.nik_karyawan', '=', 'bpjs_tk.nik_karyawan')->where('bpjs_tk.is_active', true);
            })
            ->leftJoin('bpjs_kesehatan', function($join) use ($request){
                $join->on('master_karyawan.nik_karyawan', '=', 'bpjs_kesehatan.nik_karyawan')->where('bpjs_kesehatan.bulan_efektif', '<=', $request->periode)->where('bpjs_kesehatan.is_active', true);
            })
            ->leftJoin('kasbon', function($join) use ($request) {
                $join->on('master_karyawan.nik_karyawan', '=', 'kasbon.nik_karyawan')->where('kasbon.is_active', true)
                     ->where('kasbon.bulan_mulai_pemotongan', '<=', $request->periode);
            })
            ->leftJoin('denda_karyawan', function($join) use ($request) {
                $join->on('master_karyawan.nik_karyawan', '=', 'denda_karyawan.nik_karyawan')->where('denda_karyawan.is_active', true)
                     ->where('denda_karyawan.bulan_mulai_pemotongan', '<=', $request->periode);
            })
            ->leftJoin('pph_21', function($join) use ($request) {
                $join->on('master_karyawan.nik_karyawan', '=', 'pph_21.nik_karyawan')->where('pph_21.is_active', true)
                     ->where('pph_21.bulan_mulai_pemotongan', '<=', $request->periode);
            })
            ->leftJoin('payroll', function($join) use ($request){
                $join->on('master_karyawan.nik_karyawan', '=', 'payroll.nik_karyawan')->where('payroll.periode_payroll', '=', $request->periode)->where('payroll.is_active', '=', true);
            })
            ->leftJoin('pencadangan_upah', function($join) use ($request){
                $join->on('master_karyawan.nik_karyawan', '=', 'pencadangan_upah.nik_karyawan')
                     ->where('pencadangan_upah.is_active', true)
                     ->where('pencadangan_upah.bulan_efektif', '<=', $request->periode_payroll)
                     ->where('pencadangan_upah.status', 'ONGOING');
            })
            ->leftJoin('master_divisi', function($join){
                $join->on('master_karyawan.id_department', '=', 'master_divisi.id');
            })
            ->leftJoin('master_jabatan', function($join){
                $join->on('master_karyawan.id_jabatan', '=', 'master_jabatan.id');
            })
            ->groupBy(DB::raw('master_karyawan.id, master_karyawan.nama_lengkap, master_karyawan.nik_karyawan, master_karyawan.status_karyawan, master_divisi.nama_divisi, pph_21.pajak_bulanan,rekening_karyawan.no_rekening, rekening_karyawan.nama_bank,payroll.keterangan, master_sallary.gaji_pokok, master_sallary.tunjangan_kerja, bpjs_tk.nominal_potongan_karyawan, bpjs_tk.nominal_potongan_kantor, bpjs_kesehatan.nominal_potongan_karyawan, bpjs_kesehatan.nominal_potongan_kantor, payroll.id,payroll.status,payroll.payroll_header_id, rekap_masuk_kerja.tanggal'))
            ->orderBy('master_karyawan.nik_karyawan', 'ASC')
            // ->where('master_karyawan.nik_karyawan', 'ISP232')
            ->whereRaw(('CASE WHEN master_karyawan.is_active = 0 THEN CAST(NOW() as DATE) <= DATE_ADD(master_karyawan.effective_date, INTERVAL 45 DAY) ELSE master_karyawan.is_active = 1 END'))
            ->where('rekap_masuk_kerja.bulan', $request->periode_payroll);
            // ->where('rekap_masuk_kerja.is_active', true);
            if($request->status_karyawan == 'Supervisor'){
                $query->where('master_karyawan.grade', strtoupper($request->status_karyawan) );
            } else {
                $query->where('master_karyawan.status_karyawan', $request->status_karyawan);
                $query->where('master_karyawan.grade', '<>', 'SUPERVISOR');
            }

            $data = $query;
            // Create a summary query
            $summaryQuery = DB::table(DB::raw("({$query->toSql()}) as subquery"))
                ->mergeBindings($query)
                ->select([
                    DB::raw('SUM(gaji_pokok) as totalGaji'),
                    DB::raw('SUM(tunjangan_kerja) as totalTunjangan'),
                    DB::raw('SUM(bonus) as totalBonus'),
                    DB::raw('SUM(pencadangan_upah) as totalPencadangan'),
                    DB::raw('SUM(jamsostek) as totalJamsostek'),
                    DB::raw('SUM(bpjs_kesehatan) as totalBpjsKes'),
                    DB::raw('SUM(loan) as totalLoan'),
                    DB::raw('SUM(sanksi) as totalSanksi'),
                    DB::raw('SUM(potongan_absen) as totalPotonganAbsen'),
                    DB::raw('SUM(pajak) as totalPajak'),
                    DB::raw('SUM(take_home_pay) as totalThp')
                ]);

            $summary = $summaryQuery->first();

            // Prepare DataTable response
            $response = Datatables::of($data)
            ->make(true)
            ->getData(); // Get raw data from DataTables

            // Add totals to the response
            $response->totals = [
                'gaji_pokok' => $summary->totalGaji,
                'tunjangan_kerja' => $summary->totalTunjangan,
                'bonus' => $summary->totalBonus,
                'pencadangan_upah' => $summary->totalPencadangan,
                'jamsostek' => $summary->totalJamsostek,
                'bpjs_kesehatan' => $summary->totalBpjsKes,
                'loan' => $summary->totalLoan,
                'sanksi' => $summary->totalSanksi,
                'potongan_absen' => $summary->totalPotonganAbsen,
                'pajak_pph' => $summary->totalPajak,
                'take_home_pay' => $summary->totalThp,
            ];

            // Return the modified response as JSON
            return response()->json($response);
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [],
                'message' => $e->getMessage(),
            ], 201);
        }
    }

    public function writeData(Request $request)
    {
        DB::beginTransaction();
        try {        
            // dd($request->all());    
            $payroll = new Payroll();

            $payroll->nik_karyawan = $request->nik_karyawan;
            $payroll->payroll_header_id = $request->payroll_header_id;
            $payroll->karyawan = $request->nama_lengkap;
            $payroll->status_karyawan = $request->status_karyawan;
            $payroll->periode_payroll = $request->periode_payroll;
            $payroll->hari_kerja = $request->hari_kerja;
            $payroll->tidak_hadir = $request->tidak_hadir;
            $payroll->gaji_pokok = str_replace(['.', ','], '', $request->gaji_pokok);
            $payroll->tunjangan = ($request->tunjangan_kerja == '') ? null : str_replace(['.', ','], '', $request->tunjangan_kerja);
            $payroll->pencadangan_upah = ($request->pencadangan_upah == '') ? null : str_replace(['.', ','], '', $request->pencadangan_upah);
            $payroll->bonus = ($request->bonus == '') ? null : str_replace(['.', ','], '', $request->bonus);
            $payroll->incentive = ($request->incentive == '') ? 0 : str_replace(['.', ','], '', $request->incentive);
            $payroll->jamsostek = ($request->jamsostek == '') ? null : str_replace(['.', ','], '', $request->jamsostek);
            $payroll->bpjs_kesehatan = ($request->bpjs_kesehatan == '') ? null : str_replace(['.', ','], '', $request->bpjs_kesehatan);
            $payroll->loan = ($request->loan == '') ? null : str_replace(['.', ','], '', $request->loan);
            $payroll->sanksi = ($request->sanksi == '') ? null : str_replace(['.', ','], '', $request->sanksi);
            $payroll->potongan_absen = ($request->potongan_absen == '') ? null : str_replace(['.', ','], '', $request->potongan_absen);
            $payroll->potongan_lainnya = ($request->potongan_lainnya == '') ? 0 : str_replace(['.', ','], '', $request->potongan_lainnya);
            $payroll->pajak_pph = ($request->pajak == '') ? 0 : str_replace(['.', ','], '', $request->pajak);
            $payroll->no_rekening = $request->no_rekening;
            $payroll->nama_bank = $request->nama_bank;
            $payroll->take_home_pay = str_replace(['.', ','], '', $request->take_home_pay);
            $payroll->keterangan = $request->keterangan;
            $payroll->created_by = $this->karyawan;
            $payroll->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $payroll->save();
            
            // Proses pengurangan kasbon
            // dd(new Kasbon, $request->periode_payroll, $request->nik_karyawan);
            $cek_kasbon = Kasbon::where('nik_karyawan', $request->nik_karyawan)
            ->where('is_active', true)
            ->where('bulan_mulai_pemotongan', '<=', $request->periode_payroll)
            ->where('sisa_tenor', '>', 0)
            ->first();
            
            if($cek_kasbon){
                // dd($cek_kasbon);
                $sisa_tenor = $cek_kasbon->sisa_tenor - 1;
                $cek_kasbon->sisa_tenor = $sisa_tenor;
                $cek_kasbon->sisa_kasbon = $cek_kasbon->sisa_kasbon - str_replace(['.', ','], '', $request->loan);
                if($sisa_tenor == 0){
                    $cek_kasbon->status = 'END';
                }
                $cek_kasbon->updated_by = $this->karyawan;
                $cek_kasbon->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $cek_kasbon->save();
            }

            // proses pengurangan denda
            $cek_denda = DendaKaryawan::where('nik_karyawan', $request->nik_karyawan)
                ->where('is_active', true)
                ->where('bulan_mulai_pemotongan', '<=', $request->periode_payroll)
                ->where('sisa_tenor', '>', 0)
                ->first();

            if($cek_denda){
                $sisa_tenor = $cek_denda->sisa_tenor - 1;
                $cek_denda->sisa_tenor = $sisa_tenor;
                $cek_denda->sisa_denda = $cek_denda->sisa_denda - str_replace(['.', ','], '', $request->sanksi);
                if($sisa_tenor == 0){
                    $cek_denda->status = 'END';
                }
                $cek_denda->updated_by = $this->karyawan;
                $cek_denda->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $cek_denda->save();
            }

            // proses pengurangan pencadangan upah
            $cek_deposit = PencadanganUpah::where('nik_karyawan', $request->nik_karyawan)
            ->where('is_active', true)
            ->where('bulan_efektif', '<=', $request->periode_payroll)
            ->where('status', 'ONGOING')
            ->first();

            if ($cek_deposit) {
                if($cek_deposit->tenor_berjalan == $cek_deposit->tenor){
                    $cek_deposit->status = 'END';
                }
                if($cek_deposit->tenor_berjalan < 0){
                    if($cek_deposit->tenor_berjalan == '-1'){
                        $cek_deposit->tenor_berjalan = $cek_deposit->tenor_berjalan + 2;
                    } else {
                        $cek_deposit->tenor_berjalan = $cek_deposit->tenor_berjalan + 1;
                    }
                }
                if($cek_deposit->tenor_berjalan > 0 && $cek_deposit->nominal_berjalan < 0){
                    $cek_deposit->nominal_berjalan = abs($cek_deposit->nominal_berjalan);
                }
                if($cek_deposit->tenor_berjalan > 0 && $cek_deposit->tenor_berjalan < $cek_deposit->tenor){
                    $cek_deposit->tenor_berjalan = $cek_deposit->tenor_berjalan + 1;
                }
                
                $cek_deposit->updated_by = $this->karyawan;
                $cek_deposit->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $cek_deposit->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data payroll berhasil disimpan'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            // dd($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data payroll: ' . $e->getMessage() . ' Line: ' . $e->getLine()
            ], 500);
        }
    }

    public function canclePayroll(Request $request)
    {   
        DB::beginTransaction();
        try {
            $payroll = Payroll::where('id', $request->id)->first();

            $payroll->is_active = false;
            $payroll->deleted_by = $this->karyawan;
            $payroll->deleted_at = DATE('Y-m-d H:i:s');

            $payroll->save();

            // Proses pengembalian kasbon
            $cek_kasbon = Kasbon::where('nik_karyawan', $payroll->nik_karyawan)
            ->where('is_active', true)
            ->where('bulan_mulai_pemotongan', '<=', $request->periode_payroll)
            ->first();
            
            if($cek_kasbon){
                $sisa_tenor = $cek_kasbon->sisa_tenor + 1;
                $cek_kasbon->sisa_tenor = $sisa_tenor;
                $cek_kasbon->sisa_kasbon = $cek_kasbon->sisa_kasbon + $cek_kasbon->nominal_potongan;
                if($cek_kasbon->sisa_tenor > 0){
                    $cek_kasbon->status = 'ONGOING';
                }
                $cek_kasbon->updated_by = $this->karyawan;
                $cek_kasbon->updated_at = DATE('Y-m-d H:i:s');
                $cek_kasbon->save();
            }

            // proses pengembalian denda
            $cek_denda = DendaKaryawan::where('nik_karyawan', $payroll->nik_karyawan)
                ->where('is_active', true)
                ->where('bulan_mulai_pemotongan', '<=', $request->periode_payroll)
                ->first();

            if($cek_denda){
                $sisa_tenor = $cek_denda->sisa_tenor + 1;
                $cek_denda->sisa_tenor = $sisa_tenor;
                $cek_denda->sisa_denda = $cek_denda->sisa_denda + $cek_denda->nominal_potongan;
                if($cek_denda->sisa_tenor > 0){
                    $cek_denda->status = 'ONGOING';
                }
                $cek_denda->updated_by = $this->karyawan;
                $cek_denda->updated_at = DATE('Y-m-d H:i:s');
                $cek_denda->save();
            }

            // proses pengembalian pencadangan upah
            $cek_deposit = PencadanganUpah::where('nik_karyawan', $payroll->nik_karyawan)
            ->where('is_active', true)
            ->where('bulan_efektif', '<=', $request->periode_payroll)
            ->first();

            if ($cek_deposit) {
                if ($cek_deposit->tenor_berjalan == $cek_deposit->tenor) {
                    $cek_deposit->status = 'ONGOING';
                } 
                if ($cek_deposit->tenor_berjalan < 0) {
                    $cek_deposit->tenor_berjalan = $cek_deposit->tenor_berjalan - 1;
                } 
                if ($cek_deposit->tenor_berjalan > 0) {
                    $cek_deposit->tenor_berjalan = $cek_deposit->tenor_berjalan - ($cek_deposit->tenor_berjalan == '1' ? 2 : 1);
                    if ($cek_deposit->nominal_berjalan < 0 ||  $cek_deposit->tenor_berjalan < 0) {
                        $cek_deposit->nominal_berjalan = -abs($cek_deposit->nominal_berjalan);
                    }
                }
                
                $cek_deposit->updated_by = $this->karyawan;
                $cek_deposit->updated_at = DATE('Y-m-d H:i:s');
                $cek_deposit->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data payroll berhasil dicancel'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencancel data payroll: ' . $e->getMessage()
            ], 500);
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