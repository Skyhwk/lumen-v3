<?php

namespace App\Http\Controllers\api;

use App\Models\BpjsKesehatan;
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




class BpjsKesehatanController extends Controller
{
    public function index()
    {
        $data = PayrollRecordSyncService::scopeActiveKaryawanById(
            BpjsKesehatan::query()->where('bpjs_kesehatan.is_active', true),
            'bpjs_kesehatan.id_karyawan'
        )
            ->leftJoin('master_karyawan', function ($join) {
                $join->on('bpjs_kesehatan.id_karyawan', '=', 'master_karyawan.id')
                    ->where('master_karyawan.is_active', true);
            })
            ->select('bpjs_kesehatan.*', 'master_karyawan.jabatan');

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingIds = BpjsKesehatan::where('is_active', true)
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
        $BpjsKesehatan = BpjsKesehatan::findOrFail($request->id);
        $BpjsKesehatan->is_active = false;
        $BpjsKesehatan->deleted_at = DATE('Y-m-d H:i:s');
        $BpjsKesehatan->deleted_by = $this->karyawan;
        $BpjsKesehatan->save();

        return response()->json([
            'success' => true,
            'message' => 'BPJS Kesehatan data deleted successfully'
        ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try{
            if (strlen(str_replace(['Rp', '.', ','], '', $request->gaji_pokok)) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal Terlalu Besar',
                ], 401);
            }

            $existingKaryawan = BpjsKesehatan::where('is_active', true)
                ->whereNotNull('id_karyawan')
                ->pluck('id_karyawan')
                ->toArray();

            $BpjsKesehatan = new BpjsKesehatan();
            $BpjsKesehatan->created_by = $this->karyawan;
            $BpjsKesehatan->no_bpjs = $request->no_bpjs;
            $BpjsKesehatan->bulan_efektif = preg_match('/^(\d{4}-\d{2})/', (string) $request->bulan_efektif, $m)
                ? $m[1]
                : $request->bulan_efektif;
            $BpjsKesehatan->gaji_pokok = str_replace(['Rp', '.', ','], '', $request->gaji_pokok);
            $BpjsKesehatan->potongan_karyawan = $request->potongan_karyawan / 100;
            $BpjsKesehatan->nominal_potongan_karyawan = $BpjsKesehatan->potongan_karyawan * $BpjsKesehatan->gaji_pokok;
            $BpjsKesehatan->potongan_kantor = $request->potongan_kantor / 100;
            $BpjsKesehatan->nominal_potongan_kantor = $BpjsKesehatan->potongan_kantor * $BpjsKesehatan->gaji_pokok;
            $BpjsKesehatan->created_at = DATE('Y-m-d H:i:s');

            if ($request->id && in_array((int) $request->id_karyawan, array_map('intval', $existingKaryawan), true)) {
                $oldBpjsKesehatan = BpjsKesehatan::findorFail($request->id);
                $oldBpjsKesehatan->updated_at = DATE('Y-m-d H:i:s');
                $oldBpjsKesehatan->updated_by = $this->karyawan;
                $oldBpjsKesehatan->is_active = false;
                $oldBpjsKesehatan->save();

                $BpjsKesehatan->previous_id = $request->id;
                $BpjsKesehatan->karyawan = $oldBpjsKesehatan->karyawan;
                $BpjsKesehatan->nik_karyawan = $oldBpjsKesehatan->nik_karyawan;
                $BpjsKesehatan->id_karyawan = $oldBpjsKesehatan->id_karyawan;

                $message = 'BPJS Kesehatan data updated successfully';
            } else {
                $karyawan = MasterKaryawan::findOrFail($request->id_karyawan);
                $BpjsKesehatan->id_karyawan = $karyawan->id;
                $BpjsKesehatan->nik_karyawan = $karyawan->nik_karyawan;
                $BpjsKesehatan->karyawan = $karyawan->nama_lengkap;

                $message = 'BPJS Kesehatan data inserted successfully';
            }

            $BpjsKesehatan->save();

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
        $query = BpjsKesehatan::query();

        if (!empty($request->id_karyawan)) {
            $query->where('id_karyawan', $request->id_karyawan);
        } elseif (!empty($request->nik_karyawan)) {
            $query->where('nik_karyawan', $request->nik_karyawan);
        }

        $data = $query->orderBy('created_at', 'desc');

        return Datatables::of($data)->make(true);
    }
}
