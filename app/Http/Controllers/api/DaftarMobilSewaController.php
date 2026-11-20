<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DaftarMobilSewa;
use App\Models\TanggalDaftarMobilSewa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;

class DaftarMobilSewaController extends Controller
{
    public function index(Request $request)
    {
        $tabAktif = filter_var($request->input('tab_aktif', true), FILTER_VALIDATE_BOOLEAN);
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $query = DaftarMobilSewa::with(['tanggalSewaAktif'])
            ->where('daftar_mobil_sewa.is_active', true)
            ->whereRaw(
                $tabAktif
                    ? '(SELECT MAX(t.tanggal_sewa) FROM tanggal_daftar_mobil_sewa t WHERE t.daftar_mobil_sewa_id = daftar_mobil_sewa.id AND t.is_active = 1) >= ?'
                    : '(SELECT MAX(t.tanggal_sewa) FROM tanggal_daftar_mobil_sewa t WHERE t.daftar_mobil_sewa_id = daftar_mobil_sewa.id AND t.is_active = 1) < ?',
                [$today]
            )
            ->orderByDesc('id');

        return DataTables::of($query)
            ->filterColumn('tanggal_sewa_aktif', function ($query, $keyword) {
                $date = trim((string) $keyword);
                if ($date === '') {
                    return;
                }

                try {
                    $date = Carbon::parse($date, 'Asia/Jakarta')->toDateString();
                } catch (\Throwable $th) {
                    return;
                }

                $query->whereHas('tanggalSewaAktif', function ($q) use ($date) {
                    $q->whereDate('tanggal_sewa', $date);
                });
            })
            ->make(true);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['nullable', 'integer'],
            'plat_mobil' => ['required', 'string', 'max:30'],
            'merk_mobil' => ['required', 'string', 'max:100'],
            'tipe_mobil' => ['required', 'string', 'max:100'],
            'nomor_rangka' => ['nullable', 'string', 'max:100'],
            'nomor_mesin' => ['nullable', 'string', 'max:100'],
            'warna_mobil' => ['required', 'string', 'max:50'],
            'tahun_perakitan' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'tanggal_sewa' => ['required', 'array', 'min:1'],
            'tanggal_sewa.*' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $dates = collect($request->tanggal_sewa)
            ->filter()
            ->map(function ($date) {
                return Carbon::parse($date, 'Asia/Jakarta')->toDateString();
            })
            ->unique()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return response()->json([
                'message' => 'Tanggal sewa wajib diisi minimal satu tanggal',
            ], 422);
        }

        $platMobil = strtoupper(trim($request->plat_mobil));
        $merkMobil = trim($request->merk_mobil);
        $tipeMobil = trim($request->tipe_mobil);
        $nomorRangka = $request->nomor_rangka
            ? strtoupper(trim($request->nomor_rangka))
            : null;
        $nomorMesin = $request->nomor_mesin
            ? strtoupper(trim($request->nomor_mesin))
            : null;
        $warnaMobil = trim($request->warna_mobil);
        $tahunPerakitan = $request->tahun_perakitan ?: null;
        $now = Carbon::now('Asia/Jakarta')->toDateTimeString();

        $duplicateQuery = DaftarMobilSewa::where('is_active', true)
            ->where('plat_mobil', $platMobil)
            ->where('merk_mobil', $merkMobil)
            ->where('tipe_mobil', $tipeMobil)
            ->where('warna_mobil', $warnaMobil)
            ->when($request->id, function ($query) use ($request) {
                $query->where('id', '!=', $request->id);
            });

        if ($nomorRangka === null) {
            $duplicateQuery->where(function ($query) {
                $query->whereNull('nomor_rangka')->orWhere('nomor_rangka', '');
            });
        } else {
            $duplicateQuery->where('nomor_rangka', $nomorRangka);
        }

        if ($nomorMesin === null) {
            $duplicateQuery->where(function ($query) {
                $query->whereNull('nomor_mesin')->orWhere('nomor_mesin', '');
            });
        } else {
            $duplicateQuery->where('nomor_mesin', $nomorMesin);
        }

        if ($tahunPerakitan === null) {
            $duplicateQuery->whereNull('tahun_perakitan');
        } else {
            $duplicateQuery->where('tahun_perakitan', $tahunPerakitan);
        }

        if ($duplicateQuery->exists()) {
            return response()->json([
                'message' => 'Data mobil sewa dengan plat, merk, tipe, nomor rangka, nomor mesin, warna, dan tahun perakitan yang sama sudah ada dan masih aktif',
            ], 422);
        }

        try {
            DB::beginTransaction();

            if ($request->id) {
                $data = DaftarMobilSewa::where('id', $request->id)
                    ->where('is_active', true)
                    ->first();

                if (!$data) {
                    DB::rollBack();
                    return response()->json(['message' => 'Data Not Found.!'], 404);
                }

                $data->plat_mobil = $platMobil;
                $data->merk_mobil = $merkMobil;
                $data->tipe_mobil = $tipeMobil;
                $data->nomor_rangka = $nomorRangka;
                $data->nomor_mesin = $nomorMesin;
                $data->warna_mobil = $warnaMobil;
                $data->tahun_perakitan = $tahunPerakitan;
                $data->updated_at = $now;
                $data->updated_by = $this->karyawan;
                $data->save();

                TanggalDaftarMobilSewa::where('daftar_mobil_sewa_id', $data->id)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'deleted_at' => $now,
                        'deleted_by' => $this->karyawan,
                    ]);
            } else {
                $data = DaftarMobilSewa::create([
                    'plat_mobil' => $platMobil,
                    'merk_mobil' => $merkMobil,
                    'tipe_mobil' => $tipeMobil,
                    'nomor_rangka' => $nomorRangka,
                    'nomor_mesin' => $nomorMesin,
                    'warna_mobil' => $warnaMobil,
                    'tahun_perakitan' => $tahunPerakitan,
                    'created_at' => $now,
                    'created_by' => $this->karyawan,
                    'is_active' => true,
                ]);
            }

            foreach ($dates as $date) {
                TanggalDaftarMobilSewa::create([
                    'daftar_mobil_sewa_id' => $data->id,
                    'tanggal_sewa' => $date,
                    'created_at' => $now,
                    'created_by' => $this->karyawan,
                    'is_active' => true,
                ]);
            }

            DB::commit();

            $data->load('tanggalSewaAktif');

            return response()->json([
                'message' => $request->id
                    ? 'Mobil sewa updated successfully'
                    : 'Mobil sewa created successfully',
                'data' => $data,
            ], $request->id ? 200 : 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Mobil sewa save failed: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $daftarMobilSewa = DaftarMobilSewa::where('id', $request->id)
                ->where('is_active', true)
                ->first();

            if (!$daftarMobilSewa) {
                DB::rollBack();
                return response()->json(['message' => 'Data Not Found.!'], 404);
            }

            $now = Carbon::now('Asia/Jakarta')->toDateTimeString();

            $daftarMobilSewa->is_active = false;
            $daftarMobilSewa->deleted_by = $this->karyawan;
            $daftarMobilSewa->deleted_at = $now;
            $daftarMobilSewa->save();

            TanggalDaftarMobilSewa::where('daftar_mobil_sewa_id', $daftarMobilSewa->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'deleted_at' => $now,
                    'deleted_by' => $this->karyawan,
                ]);

            DB::commit();
            return response()->json(['message' => 'Data berhasil dihapus'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Mobil sewa delete failed: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function getOptions(Request $request)
    {
        $merk = DaftarMobilSewa::where('is_active', true)
            ->whereNotNull('merk_mobil')
            ->where('merk_mobil', '!=', '')
            ->distinct()
            ->orderBy('merk_mobil')
            ->pluck('merk_mobil')
            ->values();

        $tipe = DaftarMobilSewa::where('is_active', true)
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

    public function tabCounts(Request $request)
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $maxTanggalSql = '(SELECT MAX(t.tanggal_sewa) FROM tanggal_daftar_mobil_sewa t WHERE t.daftar_mobil_sewa_id = daftar_mobil_sewa.id AND t.is_active = 1)';

        $base = DaftarMobilSewa::where('is_active', true);

        $aktif = (clone $base)
            ->whereRaw("{$maxTanggalSql} >= ?", [$today])
            ->count();

        $selesai = (clone $base)
            ->whereRaw("{$maxTanggalSql} < ?", [$today])
            ->count();

        return response()->json([
            'message' => 'Tab counts loaded successfully',
            'data' => [
                'aktif' => $aktif,
                'selesai' => $selesai,
            ],
        ], 200);
    }
}
