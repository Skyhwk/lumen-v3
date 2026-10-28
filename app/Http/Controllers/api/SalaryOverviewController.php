<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterSallary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalaryOverviewController extends Controller
{
    public function index(Request $request)
    {
        $gptk = '(master_sallary.gaji_pokok + master_sallary.tunjangan_kerja)';

        $data = $this->activeSalaryQuery()
            ->groupBy('master_divisi.id', 'master_divisi.nama_divisi')
            ->orderBy('master_divisi.nama_divisi')
            ->get([
                'master_divisi.id as id_divisi',
                'master_divisi.nama_divisi',
                DB::raw("MIN({$gptk}) as gptk_terendah"),
                DB::raw("ROUND(AVG({$gptk}), 2) as gptk_rata_rata"),
                DB::raw("MAX({$gptk}) as gptk_tertinggi"),
            ])
            ->map(function ($row) {
                return [
                    'id_divisi' => (int) $row->id_divisi,
                    'nama_divisi' => $row->nama_divisi,
                    'gptk_terendah' => (float) $row->gptk_terendah,
                    'gptk_rata_rata' => (float) $row->gptk_rata_rata,
                    'gptk_tertinggi' => (float) $row->gptk_tertinggi,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /*
    public function detail(Request $request)
    {
        $divisionId = (int) $request->input('id_divisi');
        if ($divisionId <= 0) {
            return response()->json(['message' => 'Divisi wajib dipilih.'], 422);
        }

        $division = DB::table('master_divisi')
            ->where('id', $divisionId)
            ->where('is_active', true)
            ->first(['id', 'nama_divisi']);

        if (!$division) {
            return response()->json(['message' => 'Divisi tidak ditemukan.'], 404);
        }

        $gptk = '(master_sallary.gaji_pokok + master_sallary.tunjangan_kerja)';

        $employees = $this->activeSalaryQuery()
            ->leftJoin('master_jabatan', function ($join) {
                $join->on('master_karyawan.id_jabatan', '=', 'master_jabatan.id')
                    ->where('master_jabatan.is_active', true);
            })
            ->where('master_divisi.id', $divisionId)
            ->orderBy('master_karyawan.nama_lengkap')
            ->get([
                'master_karyawan.id as id_karyawan',
                'master_karyawan.nik_karyawan',
                'master_karyawan.nama_lengkap',
                'master_jabatan.nama_jabatan',
                'master_sallary.gaji_pokok',
                'master_sallary.tunjangan_kerja',
                DB::raw("{$gptk} as gptk"),
            ])
            ->map(function ($row) {
                return [
                    'id_karyawan' => (int) $row->id_karyawan,
                    'nik_karyawan' => $row->nik_karyawan,
                    'nama_lengkap' => $row->nama_lengkap,
                    'nama_jabatan' => $row->nama_jabatan,
                    'gaji_pokok' => (float) $row->gaji_pokok,
                    'tunjangan_kerja' => (float) $row->tunjangan_kerja,
                    'gptk' => (float) $row->gptk,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'id_divisi' => (int) $division->id,
                'nama_divisi' => $division->nama_divisi,
                'karyawan' => $employees,
            ],
        ]);
    }
    */

    private function activeSalaryQuery()
    {
        return MasterSallary::query()
            ->join('master_karyawan', function ($join) {
                $join->on('master_sallary.id_karyawan', '=', 'master_karyawan.id')
                    ->where('master_karyawan.is_active', true);
            })
            ->join('master_divisi', function ($join) {
                $join->on('master_karyawan.id_department', '=', 'master_divisi.id')
                    ->where('master_divisi.is_active', true);
            })
            ->where('master_sallary.is_active', true)
            ->whereNotNull('master_sallary.id_karyawan')
            ->where(function ($query) {
                $query->where('master_karyawan.status_karyawan', '!=', 'Magang/Intern')
                    ->orWhereNull('master_karyawan.status_karyawan');
            })
            ->whereRaw('UPPER(TRIM(master_karyawan.grade)) = ?', ['STAFF'])
            ->whereRaw(
                'NOT (ROUND(COALESCE(master_sallary.gaji_pokok, 0), 2) = 0 AND ROUND(COALESCE(master_sallary.tunjangan_kerja, 0), 2) = 0)'
            );
    }
}