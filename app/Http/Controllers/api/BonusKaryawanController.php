<?php

namespace App\Http\Controllers\api;

use App\Models\BonusKaryawan;
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




class BonusKaryawanController extends Controller
{
    public function index()
    {
        $data = PayrollRecordSyncService::scopeActiveKaryawanById(
            BonusKaryawan::query()->where('bonus_karyawan.is_active', true),
            'bonus_karyawan.id_karyawan'
        );

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingIds = BonusKaryawan::where('is_active', true)
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
        $bonusKaryawan = BonusKaryawan::findOrFail($request->id);
        $bonusKaryawan->is_active = false;
        $bonusKaryawan->deleted_at = DATE('Y-m-d H:i:s');
        $bonusKaryawan->deleted_by = $this->karyawan;
        $bonusKaryawan->save();

        return response()->json([
            'success' => true,
            'message' => 'Data bonus deleted successfully'
        ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try{
            if (strlen(str_replace(['Rp', '.', ','], '', $request->nominal)) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal Terlalu Besar',
                ], 401);
            }

            $existingKaryawan = BonusKaryawan::where('is_active', true)
                ->whereNotNull('id_karyawan')
                ->pluck('id_karyawan')
                ->toArray();

            $bonusKaryawan = new BonusKaryawan();
            $bonusKaryawan->nominal = str_replace(['Rp', '.', ','], '', $request->nominal);
            $bonusKaryawan->bulan_efektif = preg_match('/^(\d{4}-\d{2})/', (string) $request->bulan_efektif, $m)
                ? $m[1]
                : $request->bulan_efektif;
            $bonusKaryawan->created_by = $this->karyawan;
            $bonusKaryawan->created_at = DATE('Y-m-d H:i:s');

            if ($request->id && in_array((int) $request->id_karyawan, array_map('intval', $existingKaryawan), true)) {
                $oldBonusKaryawan = BonusKaryawan::findorFail($request->id);
                $oldBonusKaryawan->updated_at = DATE('Y-m-d H:i:s');
                $oldBonusKaryawan->updated_by = $this->karyawan;
                $oldBonusKaryawan->is_active = false;
                $oldBonusKaryawan->save();

                $bonusKaryawan->previous_id = $request->id;
                $bonusKaryawan->karyawan = $oldBonusKaryawan->karyawan;
                $bonusKaryawan->nik_karyawan = $oldBonusKaryawan->nik_karyawan;
                $bonusKaryawan->id_karyawan = $oldBonusKaryawan->id_karyawan;

                $message = 'Bonus Karyawan data updated successfully';
            } else {
                $karyawan = MasterKaryawan::findOrFail($request->id_karyawan);
                $bonusKaryawan->id_karyawan = $karyawan->id;
                $bonusKaryawan->nik_karyawan = $karyawan->nik_karyawan;
                $bonusKaryawan->karyawan = $karyawan->nama_lengkap;

                $message = 'Bonus Karyawan data inserted successfully';
            }

            $bonusKaryawan->save();

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
        $query = BonusKaryawan::query();

        if (!empty($request->id_karyawan)) {
            $query->where('id_karyawan', $request->id_karyawan);
        } elseif (!empty($request->nik_karyawan)) {
            $query->where('nik_karyawan', $request->nik_karyawan);
        }

        $data = $query->orderBy('created_at', 'asc');

        return Datatables::of($data)->make(true);
    }
}
