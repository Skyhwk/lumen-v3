<?php

namespace App\Http\Controllers\api;


use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;



class DashboardHRDController extends Controller
{
    // Tested - Clear 
    public function hari($tanggal)
    {
        $hari = date("D", strtotime($tanggal));

        switch ($hari) {
            case 'Sun':
                $hari_ini = "Minggu";
                break;

            case 'Mon':
                $hari_ini = "Senin";
                break;

            case 'Tue':
                $hari_ini = "Selasa";
                break;

            case 'Wed':
                $hari_ini = "Rabu";
                break;

            case 'Thu':
                $hari_ini = "Kamis";
                break;

            case 'Fri':
                $hari_ini = "Jumat";
                break;

            case 'Sat':
                $hari_ini = "Sabtu";
                break;

            default:
                $hari_ini = "Tidak di ketahui";
                break;
        }

        return $hari_ini;

    }
    // Tested - Clear
    public function totGrade(Request $request)
    {
        $data = MasterKaryawan::where('is_active', true)
            ->select('status_karyawan', 'id', 'nama_lengkap', 'nik_karyawan', 'tgl_berakhir_kontrak', 'tgl_mulai_kerja', 'department', 'tanggal_lahir', 'image')
            ->get();


        return response()->json([
            'data' => $data
        ], 200);
    }
    public function getBirthday(Request $request)
    {
        [$tahun, $bulan] = explode('-', $request->tanggal_lahir);

        $dt = strtotime("{$tahun}-{$bulan}-01");
        $nextMonth = strtotime('+1 month', $dt);
        $nextMonthPlusFive = strtotime('+5 days', $nextMonth);

        $currentMonth = date('m', $dt);
        $currentYear = date('Y', $dt);
        $nextMonth = date('m', $nextMonth);
        $nextYear = date('Y', $nextMonth);
        $nextMonthPlusFiveMonth = date('m', $nextMonthPlusFive);
        $nextMonthPlusFiveYear = date('Y', $nextMonthPlusFive);

        $data = MasterKaryawan::where(function ($q) use ($currentMonth, $currentYear, $nextMonth, $nextYear, $nextMonthPlusFiveMonth, $nextMonthPlusFiveYear) {
            $q->where(function ($q2) use ($currentMonth, $currentYear) {
                $q2->whereMonth('tanggal_lahir', $currentMonth);
            })->orWhere(function ($q3) use ($nextMonth, $nextYear) {
                $q3->whereMonth('tanggal_lahir', $nextMonth);
            })->orWhere(function ($q3) use ($nextMonthPlusFiveMonth, $nextMonthPlusFiveYear) {
                $q3->whereMonth('tanggal_lahir', $nextMonthPlusFiveMonth);
            });
        })
            ->where('status_karyawan', 'Training')
            ->where('is_active', true)
            ->get();

        return Datatables::of($data)->make(true);
    }

    public function getEnd(Request $request)
    {
        [$tahun, $bulan] = explode('-', $request->tgl_akhir);

        $dt = strtotime("{$tahun}-{$bulan}-01");

        $currentMonth = date('m', $dt);
        $currentYear = date('Y', $dt);

        $nextMonth = date('m', strtotime('+1 month', $dt));
        $nextYear = date('Y', strtotime('+1 month', $dt));

        $data = MasterKaryawan::where(function ($q) use ($currentMonth, $currentYear, $nextMonth, $nextYear) {
            $q->where(function ($q2) use ($currentMonth, $currentYear) {
                $q2->whereMonth('tgl_berakhir_kontrak', $currentMonth)
                    ->whereYear('tgl_berakhir_kontrak', $currentYear);
            })->orWhere(function ($q3) use ($nextMonth, $nextYear) {
                $q3->whereMonth('tgl_berakhir_kontrak', $nextMonth)
                    ->whereYear('tgl_berakhir_kontrak', $nextYear);
            });
        })
            ->where('status_karyawan', 'Training')
            ->where('is_active', true)
            ->get();

        return Datatables::of($data)->make(true);
    }

    // Tested - Clear
    public function rangeMonth()
    {
        $datestr = DATE('Y-m-d');
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        return array(
            "start" => date('Y-m-d', strtotime('first day of this month', $dt)),
            "end" => date('Y-m-d', strtotime('last day of this month', $dt))
        );
    }
    // Tested - Clear
    public function rangeWeek()
    {
        $datestr = DATE('Y-m-d');
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        return array(
            "start" => date('N', $dt) == 1 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('last monday', $dt)),
            "end" => date('N', $dt) == 7 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('next sunday', $dt))
        );
    }
}