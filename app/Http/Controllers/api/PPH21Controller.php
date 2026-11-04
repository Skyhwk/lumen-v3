<?php

namespace App\Http\Controllers\api;

use App\Models\PPH21;
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




class PPH21Controller extends Controller
{
    public function index()
    {
        $data = PayrollRecordSyncService::scopeActiveKaryawan(
            PPH21::query()->where('pph_21.is_active', true),
            'pph_21.nik_karyawan'
        );

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingIds = PPH21::where('is_active', true)
            ->whereNotNull('id_karyawan')
            ->pluck('id_karyawan')
            ->all();
        $existingNiks = PPH21::where('is_active', true)
            ->whereNull('id_karyawan')
            ->whereNotNull('nik_karyawan')
            ->pluck('nik_karyawan')
            ->all();

        $karyawan = MasterKaryawan::where('is_active', true)
            ->when(!empty($existingIds), function ($query) use ($existingIds) {
                $query->whereNotIn('id', $existingIds);
            })
            ->when(!empty($existingNiks), function ($query) use ($existingNiks) {
                $query->whereNotIn('nik_karyawan', $existingNiks);
            })
            ->select('id', 'nik_karyawan', 'nama_lengkap')
            ->orderBy('nama_lengkap')
            ->get()
            ->map(function ($row) {
                return [
                    'id_karyawan' => $row->id,
                    'nik_karyawan' => $row->nik_karyawan,
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
        $data = PPH21::findOrFail($request->id);
        $data->is_active = false;
        $data->deleted_at = DATE('Y-m-d H:i:s');
        $data->deleted_by = $this->karyawan;
        $data->save();

        return response()->json([
            'success' => true,
            'message' => 'Data PPH deleted successfully'
        ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try{
            if (strlen(str_replace(['Rp', '.', ','], '', $request->pajak_bulanan)) > 10 || strlen(str_replace(['Rp', '.', ','], '', $request->pajak_tahunan)) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal Terlalu Besar',
                ], 401);
            }
            // dd(str_replace(['Rp', '.', ','], '', $request->total_denda));
            $existing = $request->id ? PPH21::find($request->id) : null;
            $karyawan = $this->resolveKaryawan($request, $existing);
            if (!$karyawan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Karyawan tidak ditemukan.',
                ], 422);
            }

            $sameEmployee = $existing
                && $existing->is_active
                && (
                    (int) $existing->id_karyawan === (int) $karyawan->id
                    || ($existing->id_karyawan === null && $existing->nik_karyawan === $karyawan->nik_karyawan)
                );

            if ($existing && $existing->is_active && !$sameEmployee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data PPh tidak sesuai dengan karyawan yang dipilih.',
                ], 422);
            }

            if ($sameEmployee) {
                $existing->updated_at = DATE('Y-m-d H:i:s');
                $existing->updated_by = $this->karyawan;
                $existing->pajak_bulanan = str_replace(['Rp', '.', ','], '', $request->pajak_bulanan);
                $existing->pajak_tahunan = str_replace(['Rp', '.', ','], '', $request->pajak_tahunan);
                $existing->bulan_mulai_pemotongan = $request->bulan_mulai_pemotongan;
                $existing->id_karyawan = $karyawan->id;
                $existing->nik_karyawan = $karyawan->nik_karyawan;
                $existing->karyawan = $karyawan->nama_lengkap;
                $existing->save();

                $message = 'PPH data updated successfully';
            } else {
                $inputData = new PPH21();
                $inputData->created_by = $this->karyawan;
                $inputData->created_at = DATE('Y-m-d H:i:s');
                $inputData->pajak_bulanan = str_replace(['Rp', '.', ','], '', $request->pajak_bulanan);
                $inputData->pajak_tahunan = str_replace(['Rp', '.', ','], '', $request->pajak_tahunan);
                $inputData->bulan_mulai_pemotongan = $request->bulan_mulai_pemotongan;
                $inputData->id_karyawan = $karyawan->id;
                $inputData->nik_karyawan = $karyawan->nik_karyawan;
                $inputData->karyawan = $karyawan->nama_lengkap;

                $message = 'PPH data inserted successfully';

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

    private function resolveKaryawan(Request $request, ?PPH21 $existing = null): ?MasterKaryawan
    {
        $karyawanId = trim((string) $request->id_karyawan);
        $usesPphRowId = $existing
            && $karyawanId !== ''
            && (int) $karyawanId === (int) $existing->id
            && $existing->id_karyawan === null;

        if ($karyawanId !== '' && !$usesPphRowId) {
            $karyawan = MasterKaryawan::find($karyawanId);
            if ($karyawan) {
                return $karyawan;
            }
        }

        $nik = trim((string) ($request->nik_karyawan ?: optional($existing)->nik_karyawan));
        if ($nik === '') {
            return null;
        }

        return MasterKaryawan::where('nik_karyawan', $nik)
            ->orderByDesc('is_active')
            ->first();
    }

    public function getHistory(Request $request)
    {
        
        $data = PPH21::where('nik_karyawan', $request->nik_karyawan)
            ->orderBy('created_at', 'asc')
            ->get();

        return Datatables::of($data)->make(true);

    }
}