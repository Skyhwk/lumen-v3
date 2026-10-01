<?php

namespace App\Http\Controllers\api;

use App\Models\DendaKaryawan;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Services\PayrollRecordSyncService;
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
        $data = PayrollRecordSyncService::scopeActiveKaryawanById(
            DendaKaryawan::query()->where('denda_karyawan.is_active', true),
            'denda_karyawan.id_karyawan'
        );

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingIds = DendaKaryawan::where('is_active', true)
            ->whereNotNull('id_karyawan')
            ->pluck('id_karyawan')
            ->all();

        $karyawan = MasterKaryawan::where('is_active', true)
            ->when(!empty($existingIds), function ($query) use ($existingIds) {
                $query->whereNotIn('id', $existingIds);
            })
            ->select('id', 'nama_lengkap')
            ->orderBy('nama_lengkap')
            ->get()
            ->map(function ($row) {
                return [
                    'id_karyawan' => $row->id,
                    'nama_lengkap' => $row->nama_lengkap,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $karyawan,
            'message' => 'Available karyawan data retrieved successfully',
        ], 201);
    }

    public function delete(Request $request){
        try {
        $dendaKaryawan = DendaKaryawan::findOrFail($request->id);
        $dendaKaryawan->is_active = false;
        $dendaKaryawan->deleted_at = DATE('Y-m-d H:i:s');
        $dendaKaryawan->deleted_by = $this->karyawan;
        $dendaKaryawan->save();

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

            $existingKaryawan = DendaKaryawan::where('is_active', true)
                ->whereNotNull('id_karyawan')
                ->pluck('id_karyawan')
                ->toArray();

            if ($request->id && in_array((int) $request->id_karyawan, array_map('intval', $existingKaryawan), true)) {
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

                $karyawan = MasterKaryawan::findOrFail($request->id_karyawan);
                $inputData->id_karyawan = $karyawan->id;
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
        $query = DendaKaryawan::query();

        if (!empty($request->id_karyawan)) {
            $query->where('id_karyawan', $request->id_karyawan);
        } elseif (!empty($request->nik_karyawan)) {
            $query->where('nik_karyawan', $request->nik_karyawan);
        }

        $data = $query->orderBy('created_at', 'asc');

        return Datatables::of($data)->make(true);
    }
}
