<?php

namespace App\Http\Controllers\api;

use App\Models\Kasbon;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class KasbonController extends Controller
{
    public function index()
    {
        $data = Kasbon::where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingKaryawan = Kasbon::where('is_active', true)->pluck('nik_karyawan')->toArray();

        $karyawan = MasterKaryawan::where('is_active', true)
            ->whereNotIn('nik_karyawan', $existingKaryawan)
            ->select('nik_karyawan', 'nama_lengkap')
            ->get();
        
            return response()->json([
                'success' => true,
                'data' => $karyawan,
                'message' => 'Available karyawan data retrieved successfully',
            ], 201);
    }

    public function delete(Request $request){
        try {
        $bonusKaryawan = Kasbon::findOrFail($request->id);
        $bonusKaryawan->is_active = false;
        $bonusKaryawan->deleted_at = DATE('Y-m-d H:i:s');
        $bonusKaryawan->deleted_by = $this->karyawan;
        $bonusKaryawan->save();

        return response()->json([
            'success' => true,
            'message' => 'data kasbon deleted successfully'
        ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try{
            if (strlen(str_replace(['Rp', '.', ','], '', $request->total_kasbon)) > 10 || strlen(str_replace(['Rp', '.', ','], '', $request->nominal_potongan)) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal Terlalu Besar',
                ], 401);
            }
            $existingKaryawan = Kasbon::where('is_active', true)->pluck('nik_karyawan')->toArray();

            $kasbon = new Kasbon();
            $kasbon->total_kasbon = str_replace(['Rp', '.', ','], '', $request->total_kasbon);
            $kasbon->nominal_potongan = str_replace(['Rp', '.', ','], '', $request->nominal_potongan);
            $kasbon->tenor = $request->tenor;
            $kasbon->bulan_mulai_pemotongan = $request->bulan_mulai_pemotongan;
            $kasbon->tanggal_permintaan = $request->tanggal_permintaan;
            $kasbon->tanggal_pencairan = $request->tanggal_pencairan;
            $kasbon->keterangan = $request->keterangan;
            $kasbon->sisa_tenor = $request->tenor;
            $kasbon->sisa_kasbon = $kasbon->total_kasbon;
            $kasbon->status = $request->status;
            $kasbon->created_by = $this->karyawan;
            $kasbon->created_at = DATE('Y-m-d H:i:s');

            if($request->id && in_array($request->nik_karyawan, $existingKaryawan)) {
                $oldKasbon = Kasbon::findorFail($request->id);
                $oldKasbon->updated_at = DATE('Y-m-d H:i:s');
                $oldKasbon->updated_by = $this->karyawan;
                $oldKasbon->is_active = false;
                $oldKasbon->save();

                $kasbon->previous_id = $request->id;
                $kasbon->kode_kasbon = $oldKasbon->kode_kasbon;
                $kasbon->karyawan = $oldKasbon->karyawan; 
                $kasbon->nik_karyawan = $oldKasbon->nik_karyawan;

                $message = 'Kasbon data updated successfully';

            } else {
                $karyawan = MasterKaryawan::where('nik_karyawan', $request->nik_karyawan)->first();
                $kasbon->nik_karyawan = $karyawan->nik_karyawan;
                $kasbon->karyawan = $karyawan->nama_lengkap; 

                $kasbon->kode_kasbon = $this->generateNoDoc();

                $message = 'Kasbon data inserted successfully';
                
            }

            $kasbon->save();

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 201);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
    private function generateNoDoc()
    {
        $latestDocument = Kasbon::orderBy('kode_kasbon', 'desc')->first();

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
            "ISL/CAS/%s-%s/%s",
            $currentYear,
            $romanMonth[$currentMonth],
            $formattedNumber
        );

        return $no_document;
    }

    public function getHistory(Request $request)
    {
        
        $data = Kasbon::where('nik_karyawan', $request->nik_karyawan)
            ->orderBy('created_at', 'asc')
            ->get();

        return Datatables::of($data)->make(true);

    }
}