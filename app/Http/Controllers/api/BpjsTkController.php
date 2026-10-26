<?php

namespace App\Http\Controllers\api;

use App\Models\BpjsTk;
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




class BpjsTkController extends Controller
{
    public function index()
    {
        $data = PayrollRecordSyncService::scopeActiveKaryawanById(
            BpjsTk::query()->where('bpjs_tk.is_active', true),
            'bpjs_tk.id_karyawan'
        );

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingIds = BpjsTk::where('is_active', true)
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
            $bpjsTk = BpjsTk::findOrFail($request->id);
        $bpjsTk->is_active = false;
        $bpjsTk->deleted_at = DATE('Y-m-d H:i:s');
        $bpjsTk->deleted_by = $this->karyawan;
        $bpjsTk->save();

        return response()->json([
            'success' => true,
            'message' => 'data BPJS TK deleted successfully'
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

            $existingKaryawan = BpjsTk::where('is_active', true)
                ->whereNotNull('id_karyawan')
                ->pluck('id_karyawan')
                ->toArray();

            $bpjsTk = new BpjsTk();
            $bpjsTk->created_by = $this->karyawan;
            $bpjsTk->no_bpjs_tk = $request->no_bpjs_tk;
            $bpjsTk->bulan_efektif = preg_match('/^(\d{4}-\d{2})/', (string) $request->bulan_efektif, $m)
                ? $m[1]
                : $request->bulan_efektif;
            $bpjsTk->gaji_pokok = str_replace(['Rp', '.', ','], '', $request->gaji_pokok);
            $bpjsTk->potongan_karyawan = $request->potongan_karyawan / 100;
            $bpjsTk->nominal_potongan_karyawan = $bpjsTk->potongan_karyawan * $bpjsTk->gaji_pokok;
            $bpjsTk->potongan_kantor = $request->potongan_kantor / 100;
            $bpjsTk->nominal_potongan_kantor = $bpjsTk->potongan_kantor * $bpjsTk->gaji_pokok;
            $bpjsTk->created_at = DATE('Y-m-d H:i:s');

            if ($request->id && in_array((int) $request->id_karyawan, array_map('intval', $existingKaryawan), true)) {
                $oldBpjsTk = BpjsTk::findorFail($request->id);
                $oldBpjsTk->updated_at = DATE('Y-m-d H:i:s');
                $oldBpjsTk->updated_by = $this->karyawan;
                $oldBpjsTk->is_active = false;
                $oldBpjsTk->save();

                $bpjsTk->previous_id = $request->id;
                $bpjsTk->karyawan = $oldBpjsTk->karyawan;
                $bpjsTk->nik_karyawan = $oldBpjsTk->nik_karyawan;
                $bpjsTk->id_karyawan = $oldBpjsTk->id_karyawan;

                $message = 'BPJS TK data updated successfully';
            } else {
                $karyawan = MasterKaryawan::findOrFail($request->id_karyawan);
                $bpjsTk->id_karyawan = $karyawan->id;
                $bpjsTk->nik_karyawan = $karyawan->nik_karyawan;
                $bpjsTk->karyawan = $karyawan->nama_lengkap;

                $message = 'BPJS TK data inserted successfully';
            }

            $bpjsTk->save();

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
        $query = BpjsTk::query();

        if (!empty($request->id_karyawan)) {
            $query->where('id_karyawan', $request->id_karyawan);
        } elseif (!empty($request->nik_karyawan)) {
            $query->where('nik_karyawan', $request->nik_karyawan);
        }

        $data = $query->orderBy('created_at', 'desc');

        return Datatables::of($data)->make(true);
    }
}
