<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DaftarMobil;
use App\Models\ServiceMobil;
use App\Models\ServiceMobilLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class JadwalServiceMobilController extends Controller
{
    /**
     * Index DataTables — tab Jadwal (status: dijadwalkan).
     */
    public function index(Request $request)
    {
        $data = ServiceMobil::where('is_active', true)
            ->where('status', ServiceMobil::STATUS_DIJADWALKAN)
            ->orderByDesc('id');

        return Datatables::of($data)->make(true);
    }

    public function indexDalamPerbaikan(Request $request)
    {
        $today = date('Y-m-d');

        $data = ServiceMobil::where('is_active', true)
            ->where('status', ServiceMobil::STATUS_DALAM_PERBAIKAN)
            ->orderByDesc('id');

        return Datatables::of($data)
            ->addColumn('lewat_estimasi', function ($row) use ($today) {
                return $row->tanggal_selesai_estimasi && $row->tanggal_selesai_estimasi < $today;
            })
            ->make(true);
    }

    public function indexHistory(Request $request)
    {
        $data = ServiceMobil::with([
            'details' => function ($q) {
                $q->where('is_active', true)->orderBy('urutan');
            },
        ])
            ->where('is_active', true)
            ->whereIn('status', [
                ServiceMobil::STATUS_SELESAI,
                ServiceMobil::STATUS_DIBATALKAN,
            ])
            ->orderByDesc('id');

        return Datatables::of($data)
            ->addColumn('service_details', function ($row) {
                if ($row->status !== ServiceMobil::STATUS_SELESAI) {
                    return [];
                }

                return $row->details
                    ->map(function ($detail) {
                        return [
                            'deskripsi_pekerjaan' => $detail->deskripsi_pekerjaan,
                            'biaya' => $detail->biaya,
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->make(true);
    }

    /**
     * Detail service untuk modal view (header + detail biaya + lampiran).
     */
    public function show(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $service = ServiceMobil::with([
            'details' => function ($q) {
                $q->where('is_active', true)->orderBy('urutan');
            },
            'attachments' => function ($q) {
                $q->where('is_active', true)->orderBy('id');
            },
        ])
            ->where('is_active', true)
            ->find($request->id);

        if (!$service) {
            return response()->json(['message' => 'Data Not Found.!'], 404);
        }

        return response()->json([
            'message' => 'Data loaded successfully',
            'data' => $service,
        ], 200);
    }

    public function getOptions(Request $request)
    {
        $editingServiceId = $request->filled('service_id') ? (int) $request->service_id : null;
        $editingMobilId = null;

        if ($editingServiceId) {
            $editingMobilId = ServiceMobil::where('id', $editingServiceId)
                ->where('is_active', true)
                ->value('daftar_mobil_id');
        }

        // Mobil yang masih di jadwal / perbaikan tidak ditawarkan (sampai selesai atau dibatalkan).
        $busyMobilIds = ServiceMobil::where('is_active', true)
            ->whereIn('status', [
                ServiceMobil::STATUS_DIJADWALKAN,
                ServiceMobil::STATUS_DALAM_PERBAIKAN,
            ])
            ->when($editingServiceId, function ($q) use ($editingServiceId) {
                $q->where('id', '!=', $editingServiceId);
            })
            ->pluck('daftar_mobil_id')
            ->unique()
            ->values()
            ->all();

        $mobil = DaftarMobil::where('is_active', true)
            ->where(function ($q) use ($busyMobilIds, $editingMobilId) {
                $q->where(function ($available) use ($busyMobilIds) {
                    if (empty($busyMobilIds)) {
                        $available->whereRaw('1 = 1');
                    } else {
                        $available->whereNotIn('id', $busyMobilIds);
                    }
                });
                if ($editingMobilId) {
                    $q->orWhere('id', $editingMobilId);
                }
            })
            ->orderBy('plat_mobil')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'text' => trim($item->plat_mobil . ' - ' . ($item->merk_mobil ?: '') . ' ' . ($item->tipe_mobil ?: '')),
                    'plat_mobil' => $item->plat_mobil,
                    'merk_mobil' => $item->merk_mobil,
                    'tipe_mobil' => $item->tipe_mobil,
                ];
            });

        return response()->json([
            'message' => 'Options loaded successfully',
            'data' => [
                'mobil' => $mobil,
            ],
        ], 200);
    }

    /**
     * Buat / edit jadwal service (status dijadwalkan).
     * Field mengikuti roadmap §5.1.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['nullable', 'integer'],
            'daftar_mobil_id' => ['required', 'integer'],
            'tanggal_mulai_rencana' => ['required', 'date'],
            'tanggal_selesai_rencana_awal' => ['required', 'date', 'after_or_equal:tanggal_mulai_rencana'],
            'keluhan' => ['required', 'string'],
            'nama_bengkel' => ['nullable', 'string', 'max:150'],
            'catatan' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $mobil = DaftarMobil::where('id', $request->daftar_mobil_id)
            ->where('is_active', true)
            ->first();

        if (!$mobil) {
            return response()->json(['message' => 'Mobil tidak ditemukan atau tidak aktif'], 404);
        }

        $overlap = $this->findServiceOverlap(
            (int) $request->daftar_mobil_id,
            $request->tanggal_mulai_rencana,
            $request->tanggal_selesai_rencana_awal,
            $request->id ? (int) $request->id : null
        );

        if ($overlap) {
            return response()->json([
                'message' => 'Mobil sudah punya jadwal service yang bertumpuk pada periode ini (#' . $overlap->id . ', ' . $overlap->status . ')',
            ], 422);
        }

        try {
            DB::beginTransaction();

            if ($request->id) {
                $service = ServiceMobil::where('id', $request->id)
                    ->where('is_active', true)
                    ->first();

                if (!$service) {
                    DB::rollBack();
                    return response()->json(['message' => 'Data Not Found.!'], 404);
                }

                if ($service->status !== ServiceMobil::STATUS_DIJADWALKAN) {
                    DB::rollBack();
                    return response()->json(['message' => 'Hanya jadwal berstatus Dijadwalkan yang dapat diubah'], 422);
                }

                $before = $service->only([
                    'daftar_mobil_id',
                    'plat_mobil',
                    'merk_mobil',
                    'tipe_mobil',
                    'tanggal_mulai_rencana',
                    'tanggal_selesai_rencana_awal',
                    'tanggal_selesai_estimasi',
                    'keluhan',
                    'nama_bengkel',
                    'catatan',
                ]);

                $service->daftar_mobil_id = $mobil->id;
                $service->plat_mobil = $mobil->plat_mobil;
                $service->merk_mobil = $mobil->merk_mobil;
                $service->tipe_mobil = $mobil->tipe_mobil;
                $service->tanggal_mulai_rencana = $request->tanggal_mulai_rencana;
                $service->tanggal_selesai_rencana_awal = $request->tanggal_selesai_rencana_awal;
                // Estimasi terbaru ikut di-set ulang ke rencana awal saat masih dijadwalkan
                $service->tanggal_selesai_estimasi = $request->tanggal_selesai_rencana_awal;
                $service->keluhan = trim($request->keluhan);
                $service->nama_bengkel = $request->nama_bengkel ? trim($request->nama_bengkel) : null;
                $service->catatan = $request->catatan ? trim($request->catatan) : null;
                $service->updated_at = date('Y-m-d H:i:s');
                $service->updated_by = $this->karyawan;
                $service->save();

                $this->writeLog($service->id, 'jadwal_diubah', $before, $service->only(array_keys($before)));

                DB::commit();
                return response()->json(['message' => 'Jadwal service berhasil diubah'], 200);
            }

            $service = ServiceMobil::create([
                'daftar_mobil_id' => $mobil->id,
                'plat_mobil' => $mobil->plat_mobil,
                'merk_mobil' => $mobil->merk_mobil,
                'tipe_mobil' => $mobil->tipe_mobil,
                'tanggal_mulai_rencana' => $request->tanggal_mulai_rencana,
                'tanggal_selesai_rencana_awal' => $request->tanggal_selesai_rencana_awal,
                'tanggal_selesai_estimasi' => $request->tanggal_selesai_rencana_awal,
                'status' => ServiceMobil::STATUS_DIJADWALKAN,
                'keluhan' => trim($request->keluhan),
                'nama_bengkel' => $request->nama_bengkel ? trim($request->nama_bengkel) : null,
                'catatan' => $request->catatan ? trim($request->catatan) : null,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $this->karyawan,
                'is_active' => true,
            ]);

            $this->writeLog($service->id, 'dibuat', null, [
                'daftar_mobil_id' => $service->daftar_mobil_id,
                'plat_mobil' => $service->plat_mobil,
                'tanggal_mulai_rencana' => $service->tanggal_mulai_rencana,
                'tanggal_selesai_rencana_awal' => $service->tanggal_selesai_rencana_awal,
                'keluhan' => $service->keluhan,
            ]);

            DB::commit();
            return response()->json(['message' => 'Jadwal service berhasil dibuat'], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    /**
     * Batalkan jadwal (hanya sebelum mulai service).
     */
    public function cancel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'alasan_pembatalan' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $service = ServiceMobil::where('id', $request->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$service) {
                DB::rollBack();
                return response()->json(['message' => 'Data Not Found.!'], 404);
            }

            if ($service->status !== ServiceMobil::STATUS_DIJADWALKAN) {
                DB::rollBack();
                return response()->json(['message' => 'Pembatalan hanya untuk status Dijadwalkan'], 422);
            }

            $before = ['status' => $service->status];
            $service->status = ServiceMobil::STATUS_DIBATALKAN;
            $service->alasan_pembatalan = trim($request->alasan_pembatalan);
            $service->canceled_at = date('Y-m-d H:i:s');
            $service->canceled_by = $this->karyawan;
            $service->updated_at = date('Y-m-d H:i:s');
            $service->updated_by = $this->karyawan;
            $service->save();

            $this->writeLog(
                $service->id,
                'dibatalkan',
                $before,
                ['status' => $service->status, 'alasan_pembatalan' => $service->alasan_pembatalan],
                $service->alasan_pembatalan
            );

            DB::commit();
            return response()->json(['message' => 'Jadwal service berhasil dibatalkan'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    /**
     * Mulai service — status dijadwalkan → dalam_perbaikan (roadmap §5.2).
     */
    public function start(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'tanggal_mulai_aktual' => ['required', 'date'],
            'nama_bengkel' => ['required', 'string', 'max:150'],
            'tanggal_selesai_estimasi' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $service = ServiceMobil::where('id', $request->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$service) {
                DB::rollBack();
                return response()->json(['message' => 'Data Not Found.!'], 404);
            }

            if ($service->status !== ServiceMobil::STATUS_DIJADWALKAN) {
                DB::rollBack();
                return response()->json(['message' => 'Hanya jadwal berstatus Dijadwalkan yang dapat dimulai'], 422);
            }

            $today = date('Y-m-d');
            $rencana = $service->tanggal_mulai_rencana
                ? date('Y-m-d', strtotime($service->tanggal_mulai_rencana))
                : null;
            if ($rencana !== $today) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Mulai Service hanya bisa dilakukan jika tanggal mulai rencana adalah hari ini. Edit jadwal terlebih dahulu.',
                ], 422);
            }

            $mulaiAktual = $request->tanggal_mulai_aktual;
            $estimasi = $request->tanggal_selesai_estimasi ?: $service->tanggal_selesai_estimasi;

            if ($mulaiAktual > $estimasi) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Tanggal mulai aktual melewati estimasi selesai. Sesuaikan estimasi selesai terlebih dahulu.',
                ], 422);
            }

            $before = [
                'status' => $service->status,
                'nama_bengkel' => $service->nama_bengkel,
                'tanggal_mulai_aktual' => $service->tanggal_mulai_aktual,
                'tanggal_selesai_estimasi' => $service->tanggal_selesai_estimasi,
            ];

            $service->status = ServiceMobil::STATUS_DALAM_PERBAIKAN;
            $service->tanggal_mulai_aktual = $mulaiAktual;
            $service->nama_bengkel = trim($request->nama_bengkel);
            $service->tanggal_selesai_estimasi = $estimasi;
            $service->started_at = date('Y-m-d H:i:s');
            $service->started_by = $this->karyawan;
            $service->updated_at = date('Y-m-d H:i:s');
            $service->updated_by = $this->karyawan;
            $service->save();

            $this->writeLog($service->id, 'dimulai', $before, [
                'status' => $service->status,
                'nama_bengkel' => $service->nama_bengkel,
                'tanggal_mulai_aktual' => $service->tanggal_mulai_aktual,
                'tanggal_selesai_estimasi' => $service->tanggal_selesai_estimasi,
            ]);

            DB::commit();
            return response()->json(['message' => 'Service berhasil dimulai'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    /**
     * Ubah estimasi — boleh diperpanjang atau dipendekkan (roadmap §5.3).
     * Syarat: tidak lebih kecil dari tanggal mulai aktual/rencana.
     */
    public function extend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'tanggal_selesai_estimasi' => ['required', 'date'],
            'alasan' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $service = ServiceMobil::where('id', $request->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$service) {
                DB::rollBack();
                return response()->json(['message' => 'Data Not Found.!'], 404);
            }

            if ($service->status !== ServiceMobil::STATUS_DALAM_PERBAIKAN) {
                DB::rollBack();
                return response()->json(['message' => 'Perpanjangan hanya untuk status Dalam Perbaikan'], 422);
            }

            $minDate = $service->tanggal_mulai_aktual
                ?: $service->tanggal_mulai_rencana;
            $minDate = $minDate ? date('Y-m-d', strtotime($minDate)) : null;
            $newEstimasi = date('Y-m-d', strtotime($request->tanggal_selesai_estimasi));

            if ($minDate && $newEstimasi < $minDate) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Estimasi selesai tidak boleh sebelum tanggal mulai (' . $minDate . ').',
                ], 422);
            }

            if ($newEstimasi === date('Y-m-d', strtotime($service->tanggal_selesai_estimasi))) {
                DB::rollBack();
                return response()->json(['message' => 'Estimasi selesai tidak berubah. Pilih tanggal yang berbeda.'], 422);
            }

            $before = ['tanggal_selesai_estimasi' => $service->tanggal_selesai_estimasi];
            $service->tanggal_selesai_estimasi = $newEstimasi;
            $service->updated_at = date('Y-m-d H:i:s');
            $service->updated_by = $this->karyawan;
            $service->save();

            $jenisLog = $newEstimasi > date('Y-m-d', strtotime($before['tanggal_selesai_estimasi']))
                ? 'diperpanjang'
                : 'estimasi_diubah';

            $this->writeLog(
                $service->id,
                $jenisLog,
                $before,
                ['tanggal_selesai_estimasi' => $service->tanggal_selesai_estimasi],
                trim($request->alasan)
            );

            DB::commit();
            return response()->json(['message' => 'Estimasi service berhasil diubah'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    /**
     * Selesaikan service — status dalam_perbaikan → selesai (roadmap §5.4, rincian minimal 1).
     */
    public function complete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'tanggal_selesai_aktual' => ['required', 'date'],
            'catatan_hasil' => ['nullable', 'string'],
            'kilometer' => ['nullable', 'integer', 'min:0'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.deskripsi_pekerjaan' => ['required', 'string'],
            'details.*.spare_part' => ['nullable', 'string'],
            'details.*.biaya' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,gif', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $service = ServiceMobil::where('id', $request->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$service) {
                DB::rollBack();
                return response()->json(['message' => 'Data Not Found.!'], 404);
            }

            if ($service->status !== ServiceMobil::STATUS_DALAM_PERBAIKAN) {
                DB::rollBack();
                return response()->json(['message' => 'Penyelesaian hanya untuk status Dalam Perbaikan'], 422);
            }

            $selesaiAktual = $request->tanggal_selesai_aktual;
            $today = Carbon::now('Asia/Jakarta')->toDateString();
            $estimasi = $service->tanggal_selesai_estimasi
                ? Carbon::parse($service->tanggal_selesai_estimasi)->toDateString()
                : null;
            $maxSelesai = ($estimasi && $estimasi > $today) ? $estimasi : $today;

            if ($service->tanggal_mulai_aktual && $selesaiAktual < $service->tanggal_mulai_aktual) {
                DB::rollBack();
                return response()->json(['message' => 'Tanggal selesai aktual tidak boleh sebelum tanggal mulai aktual'], 422);
            }

            if ($selesaiAktual > $maxSelesai) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Tanggal selesai aktual tidak boleh setelah ' . $maxSelesai,
                ], 422);
            }

            $before = ['status' => $service->status];
            $service->status = ServiceMobil::STATUS_SELESAI;
            $service->tanggal_selesai_aktual = $selesaiAktual;
            $service->catatan_hasil = $request->catatan_hasil ? trim($request->catatan_hasil) : null;
            $service->kilometer = $request->kilometer !== null && $request->kilometer !== ''
                ? (int) $request->kilometer
                : null;
            $service->completed_at = date('Y-m-d H:i:s');
            $service->completed_by = $this->karyawan;
            $service->updated_at = date('Y-m-d H:i:s');
            $service->updated_by = $this->karyawan;
            $service->save();

            foreach ($request->details as $index => $detail) {
                $biayaPayload = $this->normalizeDetailBiaya($detail['biaya'] ?? null);

                \App\Models\ServiceMobilDetail::create([
                    'service_mobil_id' => $service->id,
                    'deskripsi_pekerjaan' => trim($detail['deskripsi_pekerjaan']),
                    'spare_part' => null,
                    'biaya' => $biayaPayload,
                    'urutan' => $index + 1,
                    'created_by' => $this->karyawan,
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_active' => true,
                ]);
            }

            $savedFiles = 0;
            if ($request->hasFile('attachments')) {
                $baseDir = public_path('servicemobile');
                if (!is_dir($baseDir)) {
                    if (!@mkdir($baseDir, 0777, true) && !is_dir($baseDir)) {
                        throw new \RuntimeException('Gagal membuat folder upload servicemobile. Cek permission public/servicemobile.');
                    }
                    @chmod($baseDir, 0777);
                }

                $dir = $baseDir . DIRECTORY_SEPARATOR . $service->id;
                if (!is_dir($dir)) {
                    if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                        throw new \RuntimeException('Gagal membuat folder upload service #' . $service->id . '. Cek permission public/servicemobile.');
                    }
                    @chmod($dir, 0777);
                }

                foreach ($request->file('attachments') as $file) {
                    if (!$file || !$file->isValid()) {
                        continue;
                    }

                    $original = $file->getClientOriginalName();
                    $mime = $file->getClientMimeType() ?: $file->getMimeType();
                    $size = $file->getSize();
                    $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
                    $file->move($dir, $safeName);
                    @chmod($dir . DIRECTORY_SEPARATOR . $safeName, 0666);

                    $relativePath = 'servicemobile/' . $service->id . '/' . $safeName;

                    \App\Models\ServiceMobilAttachment::create([
                        'service_mobil_id' => $service->id,
                        'jenis_lampiran' => 'penyelesaian',
                        'nama_file' => $original,
                        'path_file' => $relativePath,
                        'mime_type' => $mime,
                        'ukuran_byte' => $size ?: null,
                        'created_by' => $this->karyawan,
                        'created_at' => date('Y-m-d H:i:s'),
                        'is_active' => true,
                    ]);
                    $savedFiles++;
                }
            }

            $this->writeLog($service->id, 'selesai', $before, [
                'status' => $service->status,
                'tanggal_selesai_aktual' => $service->tanggal_selesai_aktual,
                'jumlah_detail' => count($request->details),
                'jumlah_lampiran' => $savedFiles,
            ]);

            DB::commit();
            return response()->json(['message' => 'Service berhasil diselesaikan'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    private function findServiceOverlap(int $mobilId, string $start, string $end, ?int $excludeId = null)
    {
        return ServiceMobil::where('is_active', true)
            ->where('daftar_mobil_id', $mobilId)
            ->whereIn('status', [
                ServiceMobil::STATUS_DIJADWALKAN,
                ServiceMobil::STATUS_DALAM_PERBAIKAN,
            ])
            ->when($excludeId, function ($q) use ($excludeId) {
                $q->where('id', '!=', $excludeId);
            })
            ->where(function ($q) use ($start, $end) {
                // Overlap: start_existing <= end_new AND end_existing >= start_new
                $q->whereRaw('tanggal_mulai_rencana <= ?', [$end])
                    ->whereRaw('tanggal_selesai_estimasi >= ?', [$start]);
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array|null Struktur biaya v1: lines[{uraian,qty,keterangan,harga}], total, currency
     */
    private function normalizeDetailBiaya($raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_array($raw)) {
            return $this->finalizeBiayaPayload($raw);
        }

        if (!is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $this->finalizeBiayaPayload($decoded);
        }

        return [
            'version' => 0,
            'legacy_text' => $trimmed,
        ];
    }

    private function finalizeBiayaPayload(array $data): ?array
    {
        if (($data['version'] ?? null) === 0) {
            return [
                'version' => 0,
                'legacy_text' => trim((string) ($data['legacy_text'] ?? '')),
            ];
        }

        $lines = [];
        foreach ($data['lines'] ?? [] as $line) {
            if (!is_array($line)) {
                continue;
            }
            $uraian = trim((string) ($line['uraian'] ?? ''));
            $keterangan = trim((string) ($line['keterangan'] ?? ''));
            $harga = (int) preg_replace('/[^\d]/', '', (string) ($line['harga'] ?? '0'));
            $qty = null;
            if (array_key_exists('qty', $line) && $line['qty'] !== null && $line['qty'] !== '') {
                $qtyParsed = (int) preg_replace('/[^\d]/', '', (string) $line['qty']);
                if ($qtyParsed > 0) {
                    $qty = $qtyParsed;
                }
            }
            if ($uraian === '' && $keterangan === '' && $harga <= 0 && $qty === null) {
                continue;
            }
            $lines[] = [
                'uraian' => $uraian,
                'qty' => $qty,
                'keterangan' => $keterangan,
                'harga' => $harga,
            ];
        }

        if ($lines === []) {
            return null;
        }

        $total = (int) ($data['total'] ?? 0);
        if ($total <= 0) {
            $total = array_sum(array_column($lines, 'harga'));
        }

        return [
            'version' => 1,
            'currency' => 'IDR',
            'lines' => $lines,
            'total' => $total,
        ];
    }

    private function writeLog($serviceId, string $jenis, $before = null, $after = null, ?string $alasan = null)
    {
        ServiceMobilLog::create([
            'service_mobil_id' => $serviceId,
            'jenis_tindakan' => $jenis,
            'nilai_sebelum' => $before,
            'nilai_sesudah' => $after,
            'alasan' => $alasan,
            'created_by' => $this->karyawan,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
