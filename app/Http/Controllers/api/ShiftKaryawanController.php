<?php

namespace App\Http\Controllers\api;

use App\Models\MesinAbsen;
use App\Models\Absensi;
use App\Models\Rfid;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use App\Models\MasterShiftKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;



class ShiftKaryawanController extends Controller{
    // Need test from front-end
  public function setShift(Request $request) {
    $data = [];

    if($request->shift != null && count($request->shift) != 0) {
        foreach($request->shift as $key => $val) {
            if(!is_string($val) || trim($val) === '') {
                continue;
            }

            $nilai = preg_split('/\s+/', trim($val));
            $tanggal = $nilai[1] ?? null;
            $shift = $nilai[2] ?? null;

            if(!$tanggal) {
                continue;
            }

            $existing = DB::table('shift_karyawan')
                ->whereDate('tanggal', $tanggal)
                ->where('karyawan_id', $request->id_karyawan);

            if($shift === null || $shift === '' || strtoupper((string) $shift) === 'NULL') {
                $existing->delete();
                continue;
            }

            $payload = [
                'shift' => $shift,
                'time_in' => (isset($nilai[3]) && strtoupper((string) $nilai[3]) !== 'NULL') ? $nilai[3] : null,
                'time_out' => (isset($nilai[4]) && strtoupper((string) $nilai[4]) !== 'NULL') ? $nilai[4] : null,
            ];

            if((clone $existing)->exists()) {
                $existing->update($payload);
            } else {
                DB::table('shift_karyawan')->insert(array_merge($payload, [
                    'karyawan_id' => $request->id_karyawan,
                    'tanggal' => $tanggal,
                ]));
            }
        }

        return response()->json([
            'message' => 'Data has been saved'
        ], 200);

    } else {
        $insert = DB::table('shift_karyawan')
                    ->whereMonth('tanggal', $request->bulan)
                    ->where('karyawan_id', $request->id_karyawan)
                    ->delete();

        return response()->json([
            'message' => 'Data has been saved'
        ], 200);
    }
}


    public function getShift(Request $request){
        $nilai = explode("-", DATE('Y-m', \strtotime($request->bulan)));
        $month = $nilai[1];
        $year = $nilai[0];

        $data = DB::table('shift_karyawan')
        ->whereYear('tanggal', $year)
        ->whereMonth('tanggal', $month)
        ->where('karyawan_id', $request->id_karyawan)
        ->get();

        return response()->json([
            'data' => $data
        ], 200);
    }

    public function getMasterShift(Request $request)
    {
        return response()->json([
            'data' => MasterShiftKaryawan::dropdownOptions(),
        ], 200);
    }
    public function SelectUserbyDivisi(Request $request)
    {
        $data = MasterKaryawan::with('jabatan', 'department', 'rekap')
            ->whereIn('id_cabang', $this->privilageCabang)
            ->where('is_active', true);

        if ($request->id_jabatan) {
            $data->where('id_jabatan', $request->id_jabatan);
        } elseif ($request->departement && $request->departement !== 'all') {
            $data->where('id_department', $request->departement);
        }

        $data = $data->get();

        return datatables()->of($data)->make(true);
    }
    public function getPosition(Request $request){
        $data = MasterJabatan::where('is_active', true)->select('id','nama_jabatan')->get();
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 201);
    }
    public function getDepartment(Request $request){
        $data = MasterDivisi::where('is_active', true)->select('id','nama_divisi')->get();
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 201);
    }
}