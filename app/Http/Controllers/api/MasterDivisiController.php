<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use Validator;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;

class MasterDivisiController extends Controller
{
    public function index(Request $request)
    {
        $divisi = MasterDivisi::query()
            ->active()
            ->withCount(['jabatan as jumlah_jabatan' => function ($query) {
                $query->where('is_active', 1);
            }])
            ->get();

        return Datatables::of($divisi)->make(true);
    }

    public function getJabatan(Request $request)
    {
        $divisiId = $request->id ? (int) $request->id : null;

        $data = MasterJabatan::active()
            ->select('id', 'kode_jabatan', 'nama_jabatan', 'id_divisi')
            ->orderBy('nama_jabatan')
            ->get()
            ->map(function ($item) use ($divisiId) {
                return [
                    'id' => $item->id,
                    'text' => trim($item->kode_jabatan . ' - ' . $item->nama_jabatan, ' -'),
                    'selected' => $divisiId && (int) $item->id_divisi === $divisiId,
                ];
            });

        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 200);
    }

    public function store(Request $request)
    {
        if ($request->id != '') {
            $data = MasterDivisi::active()->where('id', $request->id)->first();
            if ($data) {
                $existingCabang = MasterDivisi::active()
                    ->where('kode_divisi', $request->kode_divisi)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($existingCabang) {
                    return response()->json(['message' => 'Kode Divisi already exists'], 401);
                }

                $data->kode_divisi = $request->kode_divisi;
                $data->nama_divisi = $request->nama_divisi;
                $data->updated_at = Date('Y-m-d H:i:s');
                $data->updated_by = $this->karyawan;
                $data->save();

                $this->syncJabatanToDivisi($data->id, $request->jabatan_ids ?? []);

                return response()->json(['message' => 'Divisi updated successfully'], 200);
            }
        } else {
            $existingCabang = MasterDivisi::active()
                ->where('kode_divisi', $request->kode_divisi)
                ->first();
            if ($existingCabang) {
                return response()->json(['message' => 'Kode Divisi already exists'], 401);
            }
            $data = MasterDivisi::create([
                'kode_divisi' => $request->kode_divisi,
                'nama_divisi' => $request->nama_divisi,
                'is_active' => 1,
                'created_at' => Date('Y-m-d H:i:s'),
                'created_by' => $this->karyawan
            ]);

            $this->syncJabatanToDivisi($data->id, $request->jabatan_ids ?? []);

            return response()->json(['message' => 'Divisi created successfully'], 201);
        }
    }

    public function delete(Request $request){
        if($request->id !=''){
            $data = MasterDivisi::active()->where('id', $request->id)->first();
            if($data){
                $data->deleted_at = Date('Y:m:d H:i:s');
                $data->deleted_by = $this->karyawan;
                $data->is_active = false;
                $data->save();

                return response()->json(['message' => 'Divisi Delete successfully'], 201);
            }

            return response()->json(['message' => 'Data Not Found.!'], 401);
        } else {
            return response()->json(['message' => 'Data Not Found.!'], 401);
        }
    }

    private function syncJabatanToDivisi($divisiId, $jabatanIds)
    {
        if (!MasterDivisi::active()->where('id', $divisiId)->exists()) {
            return;
        }

        $jabatanIds = collect(is_array($jabatanIds) ? $jabatanIds : [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $timestamp = Date('Y-m-d H:i:s');

        $unassignQuery = MasterJabatan::active()
            ->where('id_divisi', $divisiId);

        if (!empty($jabatanIds)) {
            $unassignQuery->whereNotIn('id', $jabatanIds);
        }

        $unassignQuery->update([
            'id_divisi' => null,
            'updated_at' => $timestamp,
            'updated_by' => $this->karyawan,
        ]);

        if (!empty($jabatanIds)) {
            MasterJabatan::active()
                ->whereIn('id', $jabatanIds)
                ->update([
                    'id_divisi' => $divisiId,
                    'updated_at' => $timestamp,
                    'updated_by' => $this->karyawan,
                ]);
        }
    }
}
