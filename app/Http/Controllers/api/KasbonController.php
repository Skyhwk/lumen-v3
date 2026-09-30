<?php

namespace App\Http\Controllers\api;

use App\Models\Kasbon;
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




class KasbonController extends Controller
{
    public function index()
    {
        $data = PayrollRecordSyncService::scopeActiveKaryawanById(
            Kasbon::query()->where('kasbon.is_active', true),
            'kasbon.id_karyawan'
        );

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingIds = Kasbon::where('is_active', true)
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
        $kasbon = Kasbon::findOrFail($request->id);
        $kasbon->is_active = false;
        $kasbon->deleted_at = DATE('Y-m-d H:i:s');
        $kasbon->deleted_by = $this->karyawan;
        $kasbon->save();

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

            $existingKaryawan = Kasbon::where('is_active', true)
                ->whereNotNull('id_karyawan')
                ->pluck('id_karyawan')
                ->toArray();

            $kasbon = new Kasbon();
            $kasbon->total_kasbon = str_replace(['Rp', '.', ','], '', $request->total_kasbon);
            $kasbon->nominal_potongan = str_replace(['Rp', '.', ','], '', $request->nominal_potongan);
            $kasbon->tenor = $request->tenor;
            $kasbon->bulan_mulai_pemotongan = preg_match('/^(\d{4}-\d{2})/', (string) $request->bulan_mulai_pemotongan, $m)
                ? $m[1]
                : $request->bulan_mulai_pemotongan;
            $kasbon->tanggal_permintaan = $request->tanggal_permintaan;
            $kasbon->tanggal_pencairan = $request->tanggal_pencairan;
            $kasbon->keterangan = $request->keterangan;
            $kasbon->sisa_tenor = $request->tenor;
            $kasbon->sisa_kasbon = $kasbon->total_kasbon;
            $kasbon->status = $request->status;
            $kasbon->created_by = $this->karyawan;
            $kasbon->created_at = DATE('Y-m-d H:i:s');

            if ($request->id && in_array((int) $request->id_karyawan, array_map('intval', $existingKaryawan), true)) {
                $oldKasbon = Kasbon::findorFail($request->id);
                $oldKasbon->updated_at = DATE('Y-m-d H:i:s');
                $oldKasbon->updated_by = $this->karyawan;
                $oldKasbon->is_active = false;
                $oldKasbon->save();

                $kasbon->previous_id = $request->id;
                $kasbon->kode_kasbon = $oldKasbon->kode_kasbon;
                $kasbon->karyawan = $oldKasbon->karyawan;
                $kasbon->nik_karyawan = $oldKasbon->nik_karyawan;
                $kasbon->id_karyawan = $oldKasbon->id_karyawan;

                $message = 'Kasbon data updated successfully';
            } else {
                $karyawan = MasterKaryawan::findOrFail($request->id_karyawan);
                $kasbon->id_karyawan = $karyawan->id;
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
        $query = Kasbon::query();

        if (!empty($request->id_karyawan)) {
            $query->where('id_karyawan', $request->id_karyawan);
        } elseif (!empty($request->nik_karyawan)) {
            $query->where('nik_karyawan', $request->nik_karyawan);
        }

        $data = $query->orderBy('created_at', 'asc');

        return Datatables::of($data)->make(true);
    }
}
