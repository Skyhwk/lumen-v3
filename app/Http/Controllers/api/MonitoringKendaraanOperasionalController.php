<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DaftarMobil;
use App\Models\MonitoringKendaraanOperasional;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class MonitoringKendaraanOperasionalController extends Controller
{
    private function tabStatusFilter(string $tab): ?string
    {
        if ($tab === MonitoringKendaraanOperasional::TAB_SEDANG_DIGUNAKAN) {
            return MonitoringKendaraanOperasional::STATUS_SEDANG_DI_GUNAKAN;
        }

        return MonitoringKendaraanOperasional::STATUS_TERSEDIA;
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab', MonitoringKendaraanOperasional::TAB_TERSEDIA);
        $statusFilter = $this->tabStatusFilter($tab === MonitoringKendaraanOperasional::TAB_SEDANG_DIGUNAKAN
            ? MonitoringKendaraanOperasional::TAB_SEDANG_DIGUNAKAN
            : MonitoringKendaraanOperasional::TAB_TERSEDIA);

        $data = MonitoringKendaraanOperasional::with('daftarMobil')
            ->where('is_active', true)
            ->where('status_kendaraan', $statusFilter)
            ->orderByDesc('id');

        return Datatables::of($data)
            ->filterColumn('nama_mobil', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('nama_mobil', 'like', "%{$keyword}%")
                        ->orWhere('plat_mobil', 'like', "%{$keyword}%");
                });
            })
            ->make(true);
    }

    public function tabCounts(Request $request)
    {
        $base = MonitoringKendaraanOperasional::where('is_active', true);

        return response()->json([
            'message' => 'Tab counts loaded',
            'data' => [
                MonitoringKendaraanOperasional::TAB_TERSEDIA => (clone $base)
                    ->where('status_kendaraan', MonitoringKendaraanOperasional::STATUS_TERSEDIA)
                    ->count(),
                MonitoringKendaraanOperasional::TAB_SEDANG_DIGUNAKAN => (clone $base)
                    ->where('status_kendaraan', MonitoringKendaraanOperasional::STATUS_SEDANG_DI_GUNAKAN)
                    ->count(),
            ],
        ], 200);
    }

    public function getOptions(Request $request)
    {
        $mobil = DaftarMobil::where('is_active', true)
            ->orderBy('plat_mobil')
            ->get(['id', 'plat_mobil', 'merk_mobil', 'tipe_mobil', 'warna_mobil'])
            ->map(function ($item) {
                $nama = trim(implode(' ', array_filter([
                    $item->merk_mobil,
                    $item->warna_mobil,
                ])));

                return [
                    'id' => $item->id,
                    'plat_mobil' => $item->plat_mobil,
                    'nama_mobil' => $nama !== '' ? strtoupper($nama) : ($item->merk_mobil ?: '-'),
                    'text' => trim($item->plat_mobil . ' — ' . ($nama ?: $item->merk_mobil ?: '-')),
                ];
            });

        return response()->json([
            'message' => 'Options loaded successfully',
            'data' => [
                'mobil' => $mobil,
                'jenis_dimensi' => ['MVP', 'VAN', 'BOX', 'MOTOR', 'SEDAN'],
                'tipe_transmisi' => ['Manual', 'Otomatis'],
                'kepemilikan' => ['Internal', 'Eksternal'],
                'status_kendaraan' => [
                    [
                        'value' => MonitoringKendaraanOperasional::STATUS_TERSEDIA,
                        'label' => 'TERSEDIA',
                        'description' => 'Alat dalam kondisi layak pakai dan dapat digunakan untuk kegiatan sampling.',
                    ],
                    [
                        'value' => MonitoringKendaraanOperasional::STATUS_RUSAK,
                        'label' => 'RUSAK',
                        'description' => 'Alat mengalami kerusakan sehingga tidak dapat digunakan sebelum dilakukan penanganan.',
                    ],
                    [
                        'value' => MonitoringKendaraanOperasional::STATUS_SEDANG_DI_MAINTENANCE,
                        'label' => 'SEDANG DI MAINTENANCE',
                        'description' => 'Alat sedang menjalani proses pemeliharaan atau perbaikan.',
                    ],
                    [
                        'value' => MonitoringKendaraanOperasional::STATUS_SEDANG_DIBOOKING,
                        'label' => 'SEDANG DIBOOKING',
                        'description' => 'Kendaraan sudah terjadwalkan dengan penjelasan pada keterangan.',
                    ],
                    [
                        'value' => MonitoringKendaraanOperasional::STATUS_SEDANG_DI_GUNAKAN,
                        'label' => 'SEDANG DI GUNAKAN',
                        'description' => 'Alat masih digunakan dalam kegiatan sampling.',
                    ],
                ],
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'daftar_mobil_id' => ['required', 'integer'],
            'jenis_dimensi_kendaraan' => ['required', 'in:MVP,VAN,BOX,MOTOR,SEDAN'],
            'tipe_transmisi' => ['required', 'in:Manual,Otomatis'],
            'kepemilikan' => ['required', 'in:Internal,Eksternal'],
            'durasi_ketersediaan' => ['nullable', 'integer', 'min:1'],
            'status_kendaraan' => ['required', 'in:' . implode(',', [
                MonitoringKendaraanOperasional::STATUS_TERSEDIA,
                MonitoringKendaraanOperasional::STATUS_RUSAK,
                MonitoringKendaraanOperasional::STATUS_SEDANG_DI_MAINTENANCE,
                MonitoringKendaraanOperasional::STATUS_SEDANG_DIBOOKING,
                MonitoringKendaraanOperasional::STATUS_SEDANG_DI_GUNAKAN,
            ])],
            'keterangan' => ['nullable', 'string'],
            'code_aset' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if ($request->kepemilikan === 'Eksternal' && !$request->filled('durasi_ketersediaan')) {
            return response()->json([
                'message' => 'Durasi ketersediaan kendaraan wajib diisi untuk kepemilikan Eksternal.',
            ], 422);
        }

        $mobil = DaftarMobil::where('id', $request->daftar_mobil_id)
            ->where('is_active', true)
            ->first();

        if (!$mobil) {
            return response()->json([
                'message' => 'Data mobil tidak ditemukan.',
            ], 404);
        }

        $namaMobil = trim(implode(' ', array_filter([$mobil->merk_mobil, $mobil->warna_mobil])));
        if ($namaMobil === '') {
            $namaMobil = $mobil->merk_mobil;
        }
        $namaMobil = strtoupper((string) $namaMobil);

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $keterangan = trim((string) $request->input('keterangan', ''));
        if ($keterangan !== '') {
            $keterangan .= "\n\n[Dicatat: {$now} — {$this->karyawan}]";
        }

        MonitoringKendaraanOperasional::create([
            'daftar_mobil_id' => $mobil->id,
            'nama_mobil' => $namaMobil,
            'plat_mobil' => $mobil->plat_mobil,
            'code_aset' => $request->input('code_aset'),
            'jenis_dimensi_kendaraan' => $request->jenis_dimensi_kendaraan,
            'tipe_transmisi' => $request->tipe_transmisi,
            'kepemilikan' => $request->kepemilikan,
            'durasi_ketersediaan' => $request->kepemilikan === 'Eksternal'
                ? (int) $request->durasi_ketersediaan
                : null,
            'status_kendaraan' => $request->status_kendaraan,
            'keterangan' => $keterangan !== '' ? $keterangan : null,
            'created_by' => $this->karyawan,
            'created_at' => $now,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Data monitoring kendaraan berhasil ditambahkan.',
        ], 200);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $row = MonitoringKendaraanOperasional::where('id', $request->id)
            ->where('is_active', true)
            ->first();

        if (!$row) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $row->is_active = false;
        $row->updated_by = $this->karyawan;
        $row->updated_at = $now;
        $row->save();

        return response()->json([
            'message' => 'Data monitoring kendaraan berhasil dihapus.',
        ], 200);
    }
}
