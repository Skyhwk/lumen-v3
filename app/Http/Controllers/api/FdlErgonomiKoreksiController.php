<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DataLapanganErgonomi;
use App\Models\ErgonomiHeader;
use App\Services\RebaFormatter;
use App\Services\RlwFormatter;
use App\Services\RosaFormatter;
use App\Services\RulaFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FdlErgonomiKoreksiController extends Controller
{
    /**
     * Hanya untuk menampilkan tombol perbaikan data di portal (whitelist env).
     */
    public function canEdit(Request $request)
    {
        return response()->json([
            'allowed' => $this->isKoreksiPersonilAllowed(),
        ], 200);
    }

    public function prefill(Request $request)
    {
        if (!$this->isKoreksiPersonilAllowed()) {
            return response()->json(['message' => 'Anda tidak memiliki akses koreksi ergonomi.'], 403);
        }

        $id = $request->input('id') ?: $request->input('id_lapangan_sumber');
        if ($id === null || $id === '') {
            return response()->json(['message' => 'id wajib diisi.'], 422);
        }

        $row = DataLapanganErgonomi::with('detail')->find($id);
        if (!$row) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        $decodeJson = function ($value) {
            if ($value === null || $value === '') {
                return null;
            }
            if (is_array($value)) {
                return $value;
            }
            $decoded = json_decode(html_entity_decode((string) $value), true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        };

        return response()->json([
            'message' => 'Successful.',
            'data' => $row,
            'pengukuran' => $decodeJson($row->pengukuran),
            'sebelum_kerja' => $decodeJson($row->sebelum_kerja),
            'setelah_kerja' => $decodeJson($row->setelah_kerja),
            'method' => (int) $row->method,
        ], 200);
    }

    /**
     * Simpan perbaikan data dari form portal (row baru, tidak auto-approve).
     */
    public function storePortal(Request $request)
    {
        if (!$this->isKoreksiPersonilAllowed()) {
            return response()->json(['message' => 'Anda tidak memiliki akses koreksi ergonomi.', 'success' => false], 403);
        }

        $idSumber = $request->input('id_lapangan_sumber')
            ?: $request->input('id_datalapangan')
            ?: $request->input('id');

        if ($idSumber === null || $idSumber === '') {
            return response()->json(['message' => 'id_lapangan_sumber wajib diisi.', 'success' => false], 422);
        }

        $old = DataLapanganErgonomi::find($idSumber);
        if (!$old) {
            return response()->json(['message' => 'Data lapangan sumber tidak ditemukan.', 'success' => false], 404);
        }

        $method = (int) $old->method;
        $portalMethods = [1, 2, 3, 4, 5, 7, 8];
        if (!in_array($method, $portalMethods, true)) {
            return response()->json([
                'message' => 'Perbaikan data portal belum didukung untuk method ' . $method . '.',
                'success' => false,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $fields = $this->buildPortalKoreksiFields($method, $request);

            $new = $old->replicate();
            $new->is_approve = 0;
            $new->approved_by = null;
            $new->approved_at = null;
            $new->created_by = $this->karyawan;
            $new->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $new->updated_by = null;
            $new->updated_at = null;

            foreach ($fields as $column => $value) {
                if ($value !== null) {
                    $new->{$column} = $value;
                }
            }

            $new->save();
            $this->finalizeNewKoreksiRecord($new, $old);
            $this->supersedeOldRecord($old, $new);

            DB::commit();

            return response()->json([
                'message' => 'Koreksi berhasil disimpan. Menunggu pengecekan dan approve.',
                'success' => true,
                'status' => 200,
                'data' => [
                    'id' => $new->id,
                    'id_lapangan_sumber' => $old->id,
                    'no_sampel' => $new->no_sampel,
                    'method' => $new->method,
                    'is_approve' => $new->is_approve,
                ],
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'success' => false,
                'status' => 500,
            ], 500);
        }
    }

    private function buildPortalKoreksiFields(int $method, Request $request): array
    {
        $meta = $this->lapanganMetaFromRequest($request);

        switch ($method) {
            case 1:
                return array_merge([
                    'pengukuran' => $this->normalizeJsonColumn($request->input('pengukuran')),
                    'sebelum_kerja' => $this->normalizeJsonColumn($request->input('sebelum_kerja')),
                    'setelah_kerja' => $this->normalizeJsonColumn($request->input('setelah_kerja')),
                ], $meta);
            case 2:
                $formatted = (new RebaFormatter())->formatRebaData($request->all());
                return array_merge(['pengukuran' => json_encode($formatted, JSON_UNESCAPED_SLASHES)], $meta);
            case 3:
                $formatted = (new RulaFormatter())->format($request->all());
                return array_merge(['pengukuran' => json_encode($formatted, JSON_UNESCAPED_SLASHES)], $meta);
            case 4:
                $formatted = RosaFormatter::formatRosaData($request->all());
                return array_merge(['pengukuran' => json_encode($formatted, JSON_UNESCAPED_SLASHES)], $meta);
            case 5:
                $formatted = RlwFormatter::format($request->all(), [
                    'id_datalapangan' => $request->input('id_datalapangan'),
                    'no_sampel' => $request->input('no_sampel'),
                    'method' => $request->input('method'),
                ]);
                return array_merge(['pengukuran' => json_encode($formatted, JSON_UNESCAPED_SLASHES)], $meta);
            case 7:
                $payload = $request->except([
                    'id_lapangan_sumber',
                    'id_datalapangan',
                    'id',
                    'method',
                    'no_sampel',
                    'no_sample',
                    'pekerja',
                    'divisi',
                    'usia',
                    'year',
                    'month',
                    'kelamin',
                    'waktu_bekerja',
                    'aktivitas',
                    'aktivitas_ukur',
                ]);
                return array_merge([
                    'pengukuran' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                ], $meta);
            case 8:
                $payload = $request->except([
                    'id_lapangan_sumber',
                    'id_datalapangan',
                    'id',
                    'method',
                    'no_sampel',
                    'no_sample',
                    'pekerja',
                    'divisi',
                    'usia',
                    'year',
                    'month',
                    'kelamin',
                    'waktu_bekerja',
                    'aktivitas',
                    'aktivitas_ukur',
                ]);
                return array_merge(['pengukuran' => json_encode($payload, JSON_UNESCAPED_SLASHES)], $meta);
            default:
                return [];
        }
    }

    private function lapanganMetaFromRequest(Request $request): array
    {
        $meta = [];

        if ($request->filled('pekerja')) {
            $meta['nama_pekerja'] = $request->input('pekerja');
        }
        if ($request->filled('divisi')) {
            $meta['divisi'] = $request->input('divisi');
        }
        if ($request->filled('usia')) {
            $meta['usia'] = $request->input('usia');
        }
        if ($request->filled('kelamin')) {
            $meta['jenis_kelamin'] = $request->input('kelamin');
        }
        if ($request->filled('waktu_bekerja')) {
            $meta['waktu_bekerja'] = $request->input('waktu_bekerja');
        }
        if ($request->filled('aktivitas')) {
            $meta['aktivitas'] = $request->input('aktivitas');
        }
        if ($request->filled('aktivitas_ukur')) {
            $meta['aktivitas_ukur'] = $request->input('aktivitas_ukur');
        }
        if ($request->filled('year') && $request->filled('month')) {
            $meta['lama_kerja'] = json_encode($request->input('year') . ' Tahun' . ', ' . $request->input('month') . ' Bulan');
        }

        return $meta;
    }

    private function normalizeJsonColumn($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        return json_encode($value, JSON_UNESCAPED_SLASHES);
    }

    private function isKoreksiPersonilAllowed(): bool
    {
        $raw = (string) config('app.fdl_ergonomi_koreksi_personil', '');
        if (trim($raw) === '') {
            return false;
        }

        $allowed = array_filter(array_map(function ($item) {
            return mb_strtolower(trim($item));
        }, explode(',', $raw)));

        if ($allowed === []) {
            return false;
        }

        $candidates = array_filter([
            mb_strtolower(trim((string) $this->karyawan)),
            $this->user_id !== null ? (string) $this->user_id : null,
        ]);

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    private function finalizeNewKoreksiRecord(DataLapanganErgonomi $new, DataLapanganErgonomi $old): void
    {
        $new->is_approve = 0;
        $new->approved_by = null;
        $new->approved_at = null;

        if (Schema::hasColumn('data_lapangan_ergonomi', 'koreksi_dari_id')) {
            $new->koreksi_dari_id = $old->id;
        }

        if (Schema::hasColumn('data_lapangan_ergonomi', 'is_active')) {
            $new->is_active = 1;
        }

        $new->updated_by = $this->karyawan;
        $new->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $new->save();
    }

    private function supersedeOldRecord(DataLapanganErgonomi $old, DataLapanganErgonomi $new): void
    {
        if (Schema::hasColumn('data_lapangan_ergonomi', 'is_active')) {
            $old->is_active = 0;
        }
        if (Schema::hasColumn('data_lapangan_ergonomi', 'replaced_by_id')) {
            $old->replaced_by_id = $new->id;
        }

        $old->is_approve = 0;
        $old->approved_by = null;
        $old->approved_at = null;
        $old->updated_by = $this->karyawan;
        $old->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $old->save();

        $headerPayload = [];
        if (Schema::hasColumn('ergonomi_header', 'is_active')) {
            $headerPayload['is_active'] = 0;
        }
        if (Schema::hasColumn('ergonomi_header', 'is_approve')) {
            $headerPayload['is_approve'] = 0;
        }
        if ($headerPayload !== []) {
            ErgonomiHeader::where('id_lapangan', $old->id)->update($headerPayload);
        }
    }
}
