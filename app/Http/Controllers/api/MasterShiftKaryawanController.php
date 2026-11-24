<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterShiftKaryawan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class MasterShiftKaryawanController extends Controller
{
    private const RESERVED_CODES = ['OFF', '24JAM', 'NULL'];

    public function index(Request $request)
    {
        $status = strtolower(trim((string) $request->input('status', 'all')));

        $query = MasterShiftKaryawan::query()
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }

        return Datatables::of($query)->make(true);
    }

    public function store(Request $request)
    {
        $id = $request->input('id');
        $isEdit = $id !== null && $id !== '';

        $kode = strtoupper(trim((string) $request->input('kode', '')));
        $label = trim((string) $request->input('label', ''));
        $timeIn = $this->normalizeTime($request->input('time_in'));
        $timeOut = $this->normalizeTime($request->input('time_out'));

        if ($kode === '') {
            return response()->json(['message' => 'Kode shift wajib diisi'], 422);
        }

        if (strlen($kode) > 50 || !preg_match('/^[A-Z0-9_-]+$/', $kode)) {
            return response()->json([
                'message' => 'Kode shift hanya boleh berisi huruf, angka, underscore (_), atau strip (-) tanpa spasi (maks. 50 karakter)',
            ], 422);
        }

        if (in_array($kode, self::RESERVED_CODES, true)) {
            return response()->json([
                'message' => "Kode shift {$kode} merupakan kode khusus sistem dan tidak dapat digunakan",
            ], 422);
        }

        if ($label === '') {
            return response()->json(['message' => 'Label shift wajib diisi'], 422);
        }

        if (strlen($label) > 150) {
            return response()->json(['message' => 'Label shift maksimal 150 karakter'], 422);
        }

        if ($timeIn === null) {
            return response()->json(['message' => 'Jam masuk tidak valid (gunakan format HH:MM)'], 422);
        }

        if ($timeOut === null) {
            return response()->json(['message' => 'Jam pulang tidak valid (gunakan format HH:MM)'], 422);
        }

        $rawSortOrder = $request->input('sort_order');
        if ($rawSortOrder !== null && $rawSortOrder !== '') {
            if (!is_numeric($rawSortOrder) || (int) $rawSortOrder < 0) {
                return response()->json(['message' => 'Urutan harus berupa angka 0 atau lebih'], 422);
            }
            $sortOrder = (int) $rawSortOrder;
        } else {
            $sortOrder = null;
        }

        $isActiveInput = $request->input('is_active');
        $isActive = ($isActiveInput === null || $isActiveInput === '')
            ? 1
            : (in_array($isActiveInput, [1, '1', true, 'true', 'on'], true) ? 1 : 0);

        $actor = $this->karyawan ?: 'system';
        $now = Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s');

        if ($isEdit) {
            $row = MasterShiftKaryawan::query()->where('id', (int) $id)->first();
            if (!$row) {
                return response()->json(['message' => 'Data shift tidak ditemukan'], 404);
            }

            $duplicate = MasterShiftKaryawan::query()
                ->where('kode', $kode)
                ->where('id', '!=', $row->id)
                ->first();

            if ($duplicate) {
                $extra = (int) $duplicate->is_active === 0 ? ' (sedang nonaktif)' : '';
                return response()->json(['message' => "Kode shift {$kode} sudah digunakan{$extra}"], 422);
            }

            $row->kode = $kode;
            $row->label = $label;
            $row->time_in = $timeIn;
            $row->time_out = $timeOut;
            if ($sortOrder !== null) {
                $row->sort_order = $sortOrder;
            }
            $row->is_active = $isActive;
            $row->updated_by = $actor;
            $row->updated_at = $now;
            $row->save();

            return response()->json([
                'message' => 'Master shift berhasil diperbarui',
                'data' => $row,
            ], 200);
        }

        $duplicate = MasterShiftKaryawan::query()->where('kode', $kode)->first();
        if ($duplicate) {
            $extra = (int) $duplicate->is_active === 0
                ? ' (status nonaktif, silakan aktifkan kembali dari tabel)'
                : '';
            return response()->json(['message' => "Kode shift {$kode} sudah ada{$extra}"], 422);
        }

        if ($sortOrder === null) {
            $maxOrder = (int) (MasterShiftKaryawan::query()->max('sort_order') ?? 0);
            $sortOrder = $maxOrder + 10;
        }

        $row = MasterShiftKaryawan::create([
            'kode' => $kode,
            'label' => $label,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
            'created_by' => $actor,
            'updated_by' => $actor,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'message' => 'Master shift berhasil ditambahkan',
            'data' => $row,
        ], 201);
    }

    public function toggleStatus(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['message' => 'Data shift tidak ditemukan'], 404);
        }

        $row = MasterShiftKaryawan::query()->where('id', $id)->first();
        if (!$row) {
            return response()->json(['message' => 'Data shift tidak ditemukan'], 404);
        }

        $nextStatus = (int) $row->is_active === 1 ? 0 : 1;
        $row->is_active = $nextStatus;
        $row->updated_by = $this->karyawan ?: 'system';
        $row->updated_at = Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s');
        $row->save();

        return response()->json([
            'message' => $nextStatus === 1 ? 'Master shift diaktifkan' : 'Master shift dinonaktifkan',
            'data' => $row,
        ], 200);
    }

    public function delete(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['message' => 'Data shift tidak ditemukan'], 404);
        }

        $row = MasterShiftKaryawan::query()->where('id', $id)->first();
        if (!$row) {
            return response()->json(['message' => 'Data shift tidak ditemukan'], 404);
        }

        $row->is_active = 0;
        $row->updated_by = $this->karyawan ?: 'system';
        $row->updated_at = Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s');
        $row->save();

        return response()->json([
            'message' => 'Master shift berhasil dinonaktifkan',
            'data' => $row,
        ], 200);
    }

    private function normalizeTime($value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $raw, $matches)) {
            return $matches[1] . ':' . $matches[2];
        }

        return null;
    }
}
