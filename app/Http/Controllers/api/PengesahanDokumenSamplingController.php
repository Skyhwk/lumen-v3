<?php

namespace App\Http\Controllers\api;

use App\Models\PengesahanDokumenSampling;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class PengesahanDokumenSamplingController extends Controller
{
    public function getTypes()
    {
        $types = PengesahanDokumenSampling::query()
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->values();

        $items = [[
            'key' => 'lhp',
            'label' => 'Pengesahan LHP',
            'source' => 'lhp',
            'description' => 'Penanda tangan pengesahan Laporan Hasil Pengujian.',
        ]];

        foreach ($types as $type) {
            $label = $this->formatTypeLabel($type);
            $items[] = [
                'key' => $type,
                'label' => 'Pengesahan ' . $label,
                'source' => 'dokumen_sampling',
                'description' => 'Penanda tangan pengesahan ' . $label . '.',
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $items,
        ], 200);
    }

    public function index(Request $request)
    {
        $type = $this->resolveType($request);
        $now = Carbon::now();
        $data = PengesahanDokumenSampling::where('type', $type)->get();

        $latest = $data
            ->where('berlaku_mulai', '<=', $now)
            ->sortByDesc('berlaku_mulai')
            ->first();

        return Datatables::of($data)
            ->addColumn('is_active', function ($row) use ($latest) {
                return $latest && $row->id === $latest->id ? 1 : 0;
            })
            ->make(true);
    }

    public function list(Request $request)
    {
        $type = $this->resolveType($request);
        $data = PengesahanDokumenSampling::where('type', $type)
            ->orderByDesc('berlaku_mulai')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    public function store(Request $request)
    {
        $rawType = (string) $request->type;
        if (preg_match('/\s/', $rawType)) {
            return response()->json([
                'success' => false,
                'message' => 'Tipe tidak boleh ada spasi. Kalau mau pemisah, pakai underscore. Contoh: surat_k3',
            ], 422);
        }

        $type = $this->resolveType($request);
        $namaKaryawan = trim((string) $request->nama_karyawan);
        $jabatan = trim((string) $request->jabatan_karyawan);
        $berlakuMulai = trim((string) $request->berlaku_mulai);
        if ($namaKaryawan === '' || $jabatan === '' || $berlakuMulai === '') {
            return response()->json([
                'success' => false,
                'message' => 'Karyawan pengesah, jabatan, dan tanggal mulai berlaku wajib diisi',
            ], 422);
        }

        DB::beginTransaction();
        try {
            PengesahanDokumenSampling::create([
                'type' => $type,
                'user_id' => $request->user_id,
                'nama_karyawan' => $namaKaryawan,
                'jabatan_karyawan' => $jabatan,
                'berlaku_mulai' => $berlakuMulai,
                'created_at' => Carbon::now(),
                'created_by' => $this->karyawan,
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data pengesahan berhasil disimpan',
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function getListKaryawan(Request $request)
    {
        $allKaryawan = MasterKaryawan::where('is_active', true)->get();
        if ($request->for_new_type) {
            return response()->json([
                'data' => $allKaryawan,
                'latestDate' => null,
            ]);
        }

        $type = $this->resolveType($request);
        $latestPengesahan = PengesahanDokumenSampling::latestForType($type);

        return response()->json([
            'data' => $allKaryawan,
            'latestDate' => $latestPengesahan ? $latestPengesahan->berlaku_mulai : null,
        ]);
    }

    private function resolveType(Request $request): string
    {
        $type = strtolower(trim((string) $request->type));
        $type = preg_replace('/[^a-z0-9_]+/', '_', $type);
        $type = trim((string) $type, '_');
        if ($type === '' || $type === 'lhp') {
            return PengesahanDokumenSampling::TYPE_SAMPLING;
        }
        return $type;
    }

    private function formatTypeLabel(string $type): string
    {
        $clean = trim(str_replace(['_', '-'], ' ', $type));
        if ($clean === '') {
            return $type;
        }
        if (strtoupper($clean) === $clean || strlen($clean) <= 3) {
            return strtoupper($clean);
        }
        return ucwords(strtolower($clean));
    }
}
