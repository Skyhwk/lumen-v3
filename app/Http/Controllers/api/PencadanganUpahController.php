<?php

namespace App\Http\Controllers\api;

use App\Models\PencadanganUpah;
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




class PencadanganUpahController extends Controller
{
    public function index()
    {
        $data = PayrollRecordSyncService::scopeActiveKaryawanById(
            PencadanganUpah::query()->where('pencadangan_upah.is_active', true),
            'pencadangan_upah.id_karyawan'
        );

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingIds = PencadanganUpah::where('is_active', true)
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
            $pencadanganUpah = PencadanganUpah::findOrFail($request->id);
        $pencadanganUpah->is_active = false;
        $pencadanganUpah->deleted_at = DATE('Y-m-d H:i:s');
        $pencadanganUpah->deleted_by = $this->karyawan;
        $pencadanganUpah->save();

        return response()->json([
            'success' => true,
            'message' => 'Pencadangan Upah data deleted successfully'
        ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try{
            if (strlen(str_replace(['Rp', '.', ','], '', $request->nominal)) > 10 ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal Terlalu Besar',
                ], 401);
            }

            $existingKaryawan = PencadanganUpah::where('is_active', true)
                ->whereNotNull('id_karyawan')
                ->pluck('id_karyawan')
                ->toArray();

            $pencadanganUpah = new PencadanganUpah();
            $pencadanganUpah->created_by = $this->karyawan;
            $pencadanganUpah->created_at = DATE('Y-m-d H:i:s');
            $pencadanganUpah->tenor = $request->tenor;
            $pencadanganUpah->tenor_berjalan = '-'.$request->tenor;
            $pencadanganUpah->bulan_efektif = preg_match('/^(\d{4}-\d{2})/', (string) $request->bulan_efektif, $m)
                ? $m[1]
                : $request->bulan_efektif;
            $pencadanganUpah->nominal = str_replace(['Rp', '.', ','], '', $request->nominal);
            $pencadanganUpah->nominal_berjalan = '-'.$pencadanganUpah->nominal;

            if ($request->id && in_array((int) $request->id_karyawan, array_map('intval', $existingKaryawan), true)) {
                $oldpencadanganUpah = PencadanganUpah::findorFail($request->id);
                $oldpencadanganUpah->updated_at = DATE('Y-m-d H:i:s');
                $oldpencadanganUpah->updated_by = $this->karyawan;
                $oldpencadanganUpah->is_active = false;
                $oldpencadanganUpah->save();

                $pencadanganUpah->previous_id = $request->id;
                $pencadanganUpah->karyawan = $oldpencadanganUpah->karyawan;
                $pencadanganUpah->nik_karyawan = $oldpencadanganUpah->nik_karyawan;
                $pencadanganUpah->id_karyawan = $oldpencadanganUpah->id_karyawan;

                $message = 'Pencadangan Upah data updated successfully';
            } else {
                $karyawan = MasterKaryawan::findOrFail($request->id_karyawan);
                $pencadanganUpah->id_karyawan = $karyawan->id;
                $pencadanganUpah->nik_karyawan = $karyawan->nik_karyawan;
                $pencadanganUpah->karyawan = $karyawan->nama_lengkap;

                $message = 'Pencadangan Upah data inserted successfully';
            }

            $pencadanganUpah->save();

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
        $query = PencadanganUpah::query();

        if (!empty($request->id_karyawan)) {
            $query->where('id_karyawan', $request->id_karyawan);
        } elseif (!empty($request->nik_karyawan)) {
            $query->where('nik_karyawan', $request->nik_karyawan);
        }

        $data = $query->orderBy('created_at', 'desc');

        return Datatables::of($data)->make(true);
    }
}
