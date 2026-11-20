<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AsetKaryawan;
use App\Models\MasterAsetKaryawan;
use App\Models\MasterKaryawan;
use App\Models\RfidCard;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\Datatables\Datatables;

class AsetKaryawanController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('aset_karyawan')) {
            return Datatables::of(collect([]))->make(true);
        }

        $query = AsetKaryawan::query()
            ->from('aset_karyawan')
            ->leftJoin('master_karyawan', 'aset_karyawan.master_karyawan_id', '=', 'master_karyawan.id')
            ->leftJoin('master_aset_karyawan', 'aset_karyawan.master_aset_karyawan_id', '=', 'master_aset_karyawan.id')
            ->leftJoin('master_jabatan', function ($join) {
                $join->on('master_karyawan.id_jabatan', '=', 'master_jabatan.id')
                    ->where('master_jabatan.is_active', true);
            })
            ->leftJoin('master_cabang', function ($join) {
                $join->on('master_karyawan.id_cabang', '=', 'master_cabang.id')
                    ->where('master_cabang.is_active', true);
            })
            ->where('aset_karyawan.is_active', true)
            ->groupBy(
                'aset_karyawan.master_karyawan_id',
                'master_karyawan.nik_karyawan',
                'master_karyawan.nama_lengkap',
                'master_karyawan.department',
                'master_jabatan.nama_jabatan',
                'master_cabang.nama_cabang'
            )
            ->selectRaw("
                aset_karyawan.master_karyawan_id,
                master_karyawan.nik_karyawan,
                master_karyawan.nama_lengkap,
                master_karyawan.department,
                master_jabatan.nama_jabatan,
                master_cabang.nama_cabang,
                GROUP_CONCAT(
                    CONCAT(
                        COALESCE(master_aset_karyawan.nama, 'Aset'),
                        ' (',
                        aset_karyawan.qty,
                        ' ',
                        COALESCE(NULLIF(TRIM(aset_karyawan.satuan), ''), 'Pcs'),
                        ')'
                    )
                    ORDER BY master_aset_karyawan.nama ASC, aset_karyawan.id ASC
                    SEPARATOR ', '
                ) as aset
            ");

        return Datatables::of($query)
            ->filterColumn('nik_karyawan', function ($query, $keyword) {
                $query->where('master_karyawan.nik_karyawan', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_lengkap', function ($query, $keyword) {
                $query->where('master_karyawan.nama_lengkap', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_jabatan', function ($query, $keyword) {
                $query->where('master_jabatan.nama_jabatan', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_cabang', function ($query, $keyword) {
                $query->where('master_cabang.nama_cabang', 'like', "%{$keyword}%");
            })
            ->filterColumn('aset', function ($query, $keyword) {
                $query->havingRaw('aset LIKE ?', ['%' . $keyword . '%']);
            })
            ->orderColumn('nik_karyawan', 'master_karyawan.nik_karyawan $1')
            ->orderColumn('nama_lengkap', 'master_karyawan.nama_lengkap $1')
            ->orderColumn('nama_jabatan', 'master_jabatan.nama_jabatan $1')
            ->orderColumn('nama_cabang', 'master_cabang.nama_cabang $1')
            ->orderColumn('aset', 'aset $1')
            ->make(true);
    }

    public function show(Request $request)
    {
        $this->validate($request, [
            'master_karyawan_id' => 'required|integer',
        ]);

        if (!Schema::hasTable('aset_karyawan')) {
            return response()->json([
                'status' => false,
                'message' => 'Tabel aset karyawan belum tersedia.',
            ], 500);
        }

        $karyawanId = (int) $request->master_karyawan_id;
        $karyawan = MasterKaryawan::where('id', $karyawanId)->where('is_active', true)->first();
        if (!$karyawan) {
            return response()->json([
                'status' => false,
                'message' => 'Karyawan tidak ditemukan atau tidak aktif.',
            ], 404);
        }

        $rows = AsetKaryawan::with(['masterAset', 'rfidCard'])
            ->where('master_karyawan_id', $karyawanId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $assets = $rows->map(function ($row) {
            $namaMaster = strtolower(trim((string) optional($row->masterAset)->nama));
            $isIdCard = $namaMaster === 'id card' || !empty($row->rfid_card_id);

            return [
                'id' => $row->id,
                'is_id_card' => $isIdCard,
                'master_aset_karyawan_id' => $isIdCard ? null : $row->master_aset_karyawan_id,
                'rfid_card_id' => $row->rfid_card_id ? (int) $row->rfid_card_id : null,
                'rfid_kode_kartu' => optional($row->rfidCard)->kode_kartu,
                'qty' => max(1, (int) ($row->qty ?? 1)),
                'satuan' => $row->satuan,
            ];
        })->values();

        return response()->json([
            'status' => true,
            'message' => 'Detail aset karyawan loaded.',
            'data' => [
                'master_karyawan_id' => $karyawan->id,
                'nik_karyawan' => $karyawan->nik_karyawan,
                'nama_lengkap' => $karyawan->nama_lengkap,
                'assets' => $assets,
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        return $this->saveAssetsRequest($request, false);
    }

    public function update(Request $request)
    {
        return $this->saveAssetsRequest($request, true);
    }

    /**
     * Create / replace aset karyawan.
     * Update = soft-delete aset lama + release RFID lama, lalu insert ulang.
     */
    private function saveAssetsRequest(Request $request, bool $isUpdate)
    {
        $this->validate($request, [
            'master_karyawan_id' => 'required|integer',
            'assets' => 'required',
            'new_recruitment_id' => 'nullable|integer',
        ]);

        if (!Schema::hasTable('aset_karyawan') || !Schema::hasTable('master_aset_karyawan')) {
            return response()->json([
                'status' => false,
                'message' => 'Tabel aset karyawan belum tersedia.',
            ], 500);
        }

        $assets = $this->normalizeAssetsPayload($request->input('assets'));
        if ($assets === []) {
            return response()->json([
                'status' => false,
                'message' => 'Minimal satu aset harus diisi.',
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($request, $assets, $isUpdate) {
                $karyawan = MasterKaryawan::where('id', (int) $request->master_karyawan_id)
                    ->where('is_active', true)
                    ->first();

                if (!$karyawan) {
                    throw new Exception('Karyawan tidak ditemukan atau tidak aktif.');
                }

                $now = Carbon::now()->toDateTimeString();
                $newRecruitmentId = $request->filled('new_recruitment_id')
                    ? (int) $request->new_recruitment_id
                    : null;

                if ($isUpdate) {
                    $this->deactivateExistingAssets($karyawan->id, $now);
                }

                return $this->persistAssets($karyawan, $assets, $now, $newRecruitmentId);
            });

            return response()->json([
                'status' => true,
                'message' => $isUpdate
                    ? 'Aset karyawan berhasil diperbarui.'
                    : 'Aset karyawan berhasil disimpan.',
                'data' => $result,
            ], $isUpdate ? 200 : 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $isUpdate
                    ? 'Terjadi kesalahan saat memperbarui aset karyawan.'
                    : 'Terjadi kesalahan saat menyimpan aset karyawan.',
            ], 500);
        }
    }

    private function deactivateExistingAssets(int $karyawanId, string $now): void
    {
        $existing = AsetKaryawan::where('master_karyawan_id', $karyawanId)
            ->where('is_active', true)
            ->get(['id', 'rfid_card_id']);

        if ($existing->isEmpty()) {
            return;
        }

        $rfidIds = $existing->pluck('rfid_card_id')->filter()->unique()->values()->all();
        if ($rfidIds !== [] && Schema::hasTable('rfid_card')) {
            RfidCard::whereIn('id', $rfidIds)
                ->where('userid', $karyawanId)
                ->update(['userid' => null]);
        }

        AsetKaryawan::where('master_karyawan_id', $karyawanId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'deleted_at' => $now,
                'deleted_by' => $this->karyawan,
                'updated_at' => $now,
                'updated_by' => $this->karyawan,
            ]);
    }

    private function persistAssets($karyawan, array $assets, string $now, $newRecruitmentId): array
    {
        $saved = 0;
        $rfidAssigned = 0;
        $usedRfidIds = [];
        $createdIds = [];

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $isIdCard = filter_var($asset['is_id_card'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $rfidCardId = !empty($asset['rfid_card_id']) ? (int) $asset['rfid_card_id'] : null;

            if ($isIdCard) {
                if (!$rfidCardId) {
                    throw new Exception('ID Card wajib memilih RFID Card yang Available.');
                }

                if (in_array($rfidCardId, $usedRfidIds, true)) {
                    throw new Exception('RFID Card yang sama tidak boleh digunakan lebih dari sekali.');
                }

                if (!Schema::hasTable('rfid_card')) {
                    throw new Exception('Tabel RFID Card belum tersedia.');
                }

                $rfid = RfidCard::where('id', $rfidCardId)
                    ->where('status', 0)
                    ->where(function ($query) use ($karyawan) {
                        $query->whereNull('userid')
                            ->orWhere('userid', '')
                            ->orWhere('userid', $karyawan->id);
                    })
                    ->lockForUpdate()
                    ->first();

                if (!$rfid) {
                    throw new Exception('RFID Card tidak tersedia / sudah terpakai.');
                }

                $rfid->userid = $karyawan->id;
                $rfid->save();
                $usedRfidIds[] = $rfidCardId;
                $rfidAssigned++;

                $masterIdCard = $this->resolveOrCreateIdCardMaster($now);
                $aset = AsetKaryawan::create([
                    'master_karyawan_id' => $karyawan->id,
                    'new_recruitment_id' => $newRecruitmentId,
                    'master_aset_karyawan_id' => $masterIdCard->id,
                    'rfid_card_id' => $rfid->id,
                    'qty' => max(1, (int) ($asset['qty'] ?? 1)),
                    'satuan' => trim((string) ($asset['satuan'] ?? '')) ?: 'Pcs',
                    'created_by' => $this->karyawan,
                    'created_at' => $now,
                    'is_active' => true,
                ]);

                $createdIds[] = (int) $aset->id;
                $saved++;
                continue;
            }

            $masterAsetId = (int) ($asset['master_aset_karyawan_id'] ?? 0);
            if ($masterAsetId <= 0) {
                continue;
            }

            $masterAset = MasterAsetKaryawan::where('id', $masterAsetId)
                ->where('is_active', true)
                ->first();

            if (!$masterAset) {
                throw new Exception('Master aset karyawan tidak ditemukan atau tidak aktif.');
            }

            $aset = AsetKaryawan::create([
                'master_karyawan_id' => $karyawan->id,
                'new_recruitment_id' => $newRecruitmentId,
                'master_aset_karyawan_id' => $masterAset->id,
                'rfid_card_id' => null,
                'qty' => max(1, (int) ($asset['qty'] ?? 1)),
                'satuan' => trim((string) ($asset['satuan'] ?? '')) ?: null,
                'created_by' => $this->karyawan,
                'created_at' => $now,
                'is_active' => true,
            ]);

            $createdIds[] = (int) $aset->id;
            $saved++;
        }

        if ($saved < 1) {
            throw new Exception('Minimal satu aset harus diisi.');
        }

        return [
            'saved' => $saved,
            'rfid_assigned' => $rfidAssigned,
            'created_ids' => $createdIds,
        ];
    }

    /**
     * Terima assets sebagai array FormData ATAU JSON string.
     */
    private function normalizeAssetsPayload($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, function ($item) {
                return is_array($item);
            }));
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $this->normalizeAssetsPayload($decoded);
            }
        }

        return [];
    }

    /**
     * Master "ID Card" untuk baris aset_karyawan (FE option khusus, bukan dari list master biasa).
     */
    private function resolveOrCreateIdCardMaster(string $now): MasterAsetKaryawan
    {
        $existing = MasterAsetKaryawan::where('is_active', true)
            ->whereRaw('LOWER(TRIM(nama)) = ?', ['id card'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return MasterAsetKaryawan::create([
            'nama' => 'ID Card',
            'created_by' => $this->karyawan,
            'created_at' => $now,
            'is_active' => true,
        ]);
    }

    public function getFormOptions(Request $request)
    {
        $karyawan = [];
        if (Schema::hasTable('master_karyawan')) {
            $editKaryawanId = $request->filled('master_karyawan_id')
                ? (int) $request->master_karyawan_id
                : null;

            $karyawanQuery = MasterKaryawan::where('is_active', true);

            // Saat create: karyawan yang sudah punya aset aktif tidak ikut option.
            // Saat update: tetap sertakan karyawan yang sedang diedit.
            if (Schema::hasTable('aset_karyawan')) {
                $karyawanQuery->where(function ($query) use ($editKaryawanId) {
                    $query->whereNotIn('id', function ($sub) {
                        $sub->select('master_karyawan_id')
                            ->from('aset_karyawan')
                            ->where('is_active', true)
                            ->whereNotNull('master_karyawan_id');
                    });

                    if ($editKaryawanId) {
                        $query->orWhere('id', $editKaryawanId);
                    }
                });
            }

            $karyawan = $karyawanQuery
                ->orderBy('nama_lengkap')
                ->get(['id', 'nik_karyawan', 'nama_lengkap'])
                ->map(function ($item) {
                    $nik = trim((string) ($item->nik_karyawan ?? ''));
                    $nama = trim((string) ($item->nama_lengkap ?? ''));
                    return [
                        'id' => $item->id,
                        'nik_karyawan' => $nik,
                        'nama_lengkap' => $nama,
                        'text' => trim(($nik !== '' ? $nik . ' - ' : '') . $nama),
                    ];
                })
                ->values();
        }

        $masterAset = [];
        if (Schema::hasTable('master_aset_karyawan')) {
            $masterAset = MasterAsetKaryawan::where('is_active', true)
                ->whereRaw('LOWER(TRIM(nama)) <> ?', ['id card'])
                ->orderBy('nama')
                ->get(['id', 'nama'])
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'nama' => $item->nama,
                        'text' => $item->nama,
                    ];
                })
                ->values();
        }

        $rfid = [];
        if (Schema::hasTable('rfid_card')) {
            $editKaryawanId = $request->filled('master_karyawan_id')
                ? (int) $request->master_karyawan_id
                : null;

            $rfid = RfidCard::where('status', 0)
                ->where(function ($query) use ($editKaryawanId) {
                    $query->whereNull('userid')->orWhere('userid', '');
                    if ($editKaryawanId) {
                        $query->orWhere('userid', $editKaryawanId);
                    }
                })
                ->orderBy('kode_kartu')
                ->get(['id', 'kode_kartu', 'userid'])
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'kode_kartu' => $item->kode_kartu,
                        'text' => $item->kode_kartu,
                        'userid' => $item->userid,
                    ];
                })
                ->values();
        }

        return response()->json([
            'status' => true,
            'message' => 'Options loaded successfully',
            'data' => [
                'karyawan' => $karyawan,
                'master_aset' => $masterAset,
                'rfid' => $rfid,
            ],
        ], 200);
    }

    public function destroy(Request $request)
    {
        $this->validate($request, [
            'master_karyawan_id' => 'required|integer',
        ]);

        if (!Schema::hasTable('aset_karyawan')) {
            return response()->json([
                'status' => false,
                'message' => 'Tabel aset karyawan belum tersedia.',
            ], 500);
        }

        try {
            $karyawanId = (int) $request->master_karyawan_id;
            $now = Carbon::now()->toDateTimeString();

            $exists = AsetKaryawan::where('master_karyawan_id', $karyawanId)
                ->where('is_active', true)
                ->exists();

            if (!$exists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data aset karyawan tidak ditemukan.',
                ], 404);
            }

            DB::transaction(function () use ($karyawanId, $now) {
                $this->deactivateExistingAssets($karyawanId, $now);
            });

            return response()->json([
                'status' => true,
                'message' => 'Seluruh aset karyawan berhasil dihapus.',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Gagal menghapus aset karyawan.',
            ], 500);
        }
    }
}
