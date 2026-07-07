<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DaftarMobil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class DaftarMobilController extends Controller
{
    public function index(Request $request)
    {
        $data = DaftarMobil::where('is_active', true)->orderByDesc('id');

        return Datatables::of($data)->make(true);
    }

    public function getAllActive(Request $request)
    {
        $data = DaftarMobil::where('is_active', true)
            ->select(
                'plat_mobil',
                'merk_mobil',
                'tipe_mobil',
                'nomor_rangka',
                'nomor_mesin',
                'warna_mobil',
                'tahun_perakitan'
            )
            ->orderBy('plat_mobil')
            ->get();

        return response()->json([
            'message' => 'Data loaded successfully',
            'data' => $data,
        ], 200);
    }

    public function getOptions(Request $request)
    {
        $merk = DaftarMobil::where('is_active', true)
            ->whereNotNull('merk_mobil')
            ->where('merk_mobil', '!=', '')
            ->distinct()
            ->orderBy('merk_mobil')
            ->pluck('merk_mobil')
            ->values();

        $tipe = DaftarMobil::where('is_active', true)
            ->whereNotNull('tipe_mobil')
            ->where('tipe_mobil', '!=', '')
            ->distinct()
            ->orderBy('tipe_mobil')
            ->pluck('tipe_mobil')
            ->values();

        return response()->json([
            'message' => 'Options loaded successfully',
            'data' => [
                'merk' => $merk,
                'tipe' => $tipe,
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     'id' => ['nullable', 'integer'],
        //     'plat_mobil' => ['required', 'string', 'max:30'],
        //     'merk_mobil' => ['required', 'string', 'max:100'],
        //     'tipe_mobil' => ['required', 'string', 'max:100'],
        //     'nomor_rangka' => ['required', 'string', 'max:100'],
        //     'nomor_mesin' => ['required', 'string', 'max:100'],
        //     'warna_mobil' => ['required', 'string', 'max:50'],
        //     'status_gps' => ['nullable', 'in:0,1,true,false'],
        //     'is_active' => ['nullable', 'in:0,1,true,false'],
        // ]);

        // if ($validator->fails()) {
        //     return response()->json([
        //         'message' => $validator->errors()->first(),
        //         'errors' => $validator->errors(),
        //     ], 422);
        // }

        try {
            $platMobil = strtoupper(trim($request->plat_mobil));
            $existing = DaftarMobil::where('plat_mobil', $platMobil)
                ->where('is_active', true)
                ->when($request->id, function ($query) use ($request) {
                    $query->where('id', '!=', $request->id);
                })
                ->first();

            if ($existing) {
                return response()->json(['message' => 'Plat mobil already exists'], 401);
            }

            if ($request->id) {
                $data = DaftarMobil::where('id', $request->id)->first();

                if (!$data) {
                    return response()->json(['message' => 'Data Not Found.!'], 404);
                }

                $data->plat_mobil = $platMobil;
                $data->merk_mobil = trim($request->merk_mobil);
                $data->tipe_mobil = trim($request->tipe_mobil);
                $data->nomor_rangka = strtoupper(trim($request->nomor_rangka));
                $data->nomor_mesin = strtoupper(trim($request->nomor_mesin));
                $data->warna_mobil = trim($request->warna_mobil);
                $data->tahun_perakitan = $request->tahun_perakitan;
                if ($request->has('status_gps')) {
                    $data->status_gps = $this->toBoolean($request->status_gps);
                }
                if ($request->has('is_active')) {
                    $data->is_active = $this->toBoolean($request->is_active);
                }
                $data->updated_at = date('Y-m-d H:i:s');
                $data->updated_by = $this->karyawan;
                $data->save();

                return response()->json(['message' => 'Mobil updated successfully'], 200);
            }

            DaftarMobil::create([
                'plat_mobil' => $platMobil,
                'merk_mobil' => trim($request->merk_mobil),
                'tipe_mobil' => trim($request->tipe_mobil),
                'nomor_rangka' => $request->nomor_rangka ? strtoupper(trim($request->nomor_rangka)) : null,
                'nomor_mesin' => $request->nomor_mesin ? strtoupper(trim($request->nomor_mesin)) : null,
                'warna_mobil' => trim($request->warna_mobil),
                'tahun_perakitan' => $request->tahun_perakitan ? $request->tahun_perakitan : null,
                'status_gps' => false,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $this->karyawan,
            ]);

            return response()->json(['message' => 'Mobil created successfully'], 201);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Mobil created failed ' . $th->getMessage()], 401);
        }
    }

    public function delete(Request $request)
    {
        if (!$request->id) {
            return response()->json(['message' => 'Data Not Found.!'], 401);
        }

        $data = DaftarMobil::where('id', $request->id)->first();

        if (!$data) {
            return response()->json(['message' => 'Data Not Found.!'], 404);
        }

        $data->deleted_at = date('Y-m-d H:i:s');
        $data->deleted_by = $this->karyawan;
        $data->is_active = false;
        $data->save();

        return response()->json(['message' => 'Mobil deleted successfully'], 200);
    }

    protected function toBoolean($value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
