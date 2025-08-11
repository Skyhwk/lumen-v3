<?php

namespace App\Http\Controllers\api;

use App\Models\DendaKaryawan;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class DendaKaryawanController extends Controller
{
    public function index()
    {
        $data = DendaKaryawan::where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingKaryawan = DendaKaryawan::where('is_active', true)->pluck('nik_karyawan')->toArray();

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
        $bonusKaryawan = DendaKaryawan::findOrFail($request->id);
        $bonusKaryawan->is_active = false;
        $bonusKaryawan->deleted_at = DATE('Y-m-d H:i:s');
        $bonusKaryawan->deleted_by = $this->karyawan;
        $bonusKaryawan->save();

        return response()->json([
            'success' => true,
            'message' => 'Data denda karyawan deleted successfully'
        ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    private function generateNoDoc()
    {
        $latestDocument = DendaKaryawan::orderBy('kode_denda', 'desc')->first();

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
            if (preg_match('/(\d{6})$/', $latestDocument->kode_denda, $matches)) {
                $lastNumber = intval($matches[1]);
            }
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        $formattedNumber = str_pad($newNumber, 6, '0', STR_PAD_LEFT);

        $no_document = sprintf(
            "ISL/DND/%s-%s/%s",
            $currentYear,
            $romanMonth[$currentMonth],
            $formattedNumber
        );

        return $no_document;
    }

    public function store(Request $request)
    {
        try{
            if (strlen(str_replace(['Rp', '.', ','], '', $request->nominal_potongan)) > 10 || strlen(str_replace(['Rp', '.', ','], '', $request->nominal_potongan)) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal Terlalu Besar',
                ], 401);
            }
            // dd(str_replace(['Rp', '.', ','], '', $request->total_denda));
            $existingKaryawan = DendaKaryawan::where('is_active', true)->pluck('nik_karyawan')->toArray();

            if($request->id && in_array($request->nik_karyawan, $existingKaryawan)) {
                $updatedData = DendaKaryawan::findorFail($request->id);
                $updatedData->updated_at = DATE('Y-m-d H:i:s');
                $updatedData->updated_by = $this->karyawan;
                $updatedData->total_denda = str_replace(['Rp', '.', ','], '', $request->total_denda);
                $updatedData->tenor = $request->tenor;
                $updatedData->bulan_mulai_pemotongan = $request->bulan_mulai_pemotongan;
                $updatedData->nominal_potongan = str_replace(['Rp', '.', ','], '', $request->nominal_potongan);
                $updatedData->keterangan = $request->keterangan;
                $updatedData->sisa_tenor = $request->tenor;
                $updatedData->kode_denda = $this->generateNoDoc();
                $updatedData->sisa_denda = $updatedData->total_denda;
                $updatedData->save();

                $message = 'Denda Karyawan data updated successfully';

            } else {
                $inputData = new DendaKaryawan();
                $inputData->created_by = $this->karyawan;
                $inputData->created_at = DATE('Y-m-d H:i:s');
                $inputData->total_denda = str_replace(['Rp', '.', ','], '', $request->total_denda);
                $inputData->tenor = $request->tenor;
                $inputData->bulan_mulai_pemotongan = $request->bulan_mulai_pemotongan;
                $inputData->nominal_potongan = str_replace(['Rp', '.', ','], '', $request->nominal_potongan);
                $inputData->keterangan = $request->keterangan;
                $inputData->sisa_tenor = $request->tenor;
                $inputData->kode_denda = $this->generateNoDoc();
                $inputData->sisa_denda = $inputData->total_denda;
                $karyawan = MasterKaryawan::where('nik_karyawan', $request->nik_karyawan)->first();
                $inputData->nik_karyawan = $karyawan->nik_karyawan;
                $inputData->karyawan = $karyawan->nama_lengkap; 

                $message = 'Denda Karyawan data inserted successfully';
                
                $inputData->save();
            }


            return response()->json([
                'success' => true,
                'message' => $message,
            ], 201);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function getHistory(Request $request)
    {
        
        $data = DendaKaryawan::where('nik_karyawan', $request->nik_karyawan)
            ->orderBy('created_at', 'asc')
            ->get();

        return Datatables::of($data)->make(true);

    }
}