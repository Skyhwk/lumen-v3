<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterPelanggan;
use App\Services\SelfRegistration\StatementPdfService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SelfRegistrasiCustomerController extends Controller
{
    public function index()
    {
        $portalDb = $this->portalDb();

        $rows = DB::table('self_registration_customer as r')
            ->leftJoin($portalDb . '.users as u', 'u.id', '=', 'r.user_id')
            ->orderByDesc('r.id')
            ->get([
                'r.id',
                'r.email',
                'r.user_id',
                'r.id_pelanggan',
                'r.nomor_dokumen',
                'r.dokumen_hash',
                'r.pdf_path',
                'r.status',
                'r.signed_at',
                'r.used_at',
                'r.created_at',
                'u.nama_lengkap',
                'u.is_active',
                'u.id_pelanggan as user_id_pelanggan',
            ]);

        $allIds = $rows->flatMap(function ($row) {
            return $this->decodeIds($row->id_pelanggan ?: $row->user_id_pelanggan);
        })->unique()->values();

        $pelanggan = MasterPelanggan::whereIn('id_pelanggan', $allIds)
            ->get()
            ->keyBy('id_pelanggan');

        $data = $rows->map(function ($row) use ($pelanggan) {
            $ids = $this->decodeIds($row->id_pelanggan ?: $row->user_id_pelanggan);
            $row->id_pelanggan = json_encode($ids);
            $row->nama_lengkap = $row->nama_lengkap ?: '';
            $row->has_pdf = !empty($row->pdf_path);
            $row->pelanggan = collect($ids)
                ->map(fn ($id) => $pelanggan[$id] ?? null)
                ->filter()
                ->values();
            unset($row->user_id_pelanggan, $row->pdf_path);
            return $row;
        });

        return response()->json([
            'message' => 'Data self registrasi customer berhasil ditampilkan',
            'data' => $data,
            'status' => 200,
            'success' => true,
        ], 200);
    }

    public function getCustomer(Request $request)
    {
        try {
            $search = trim((string) ($request->search ?? ''));
            if ($search === '') {
                return response()->json([
                    'message' => 'Customer data displayed successfully',
                    'data' => [],
                    'status' => 200,
                    'success' => true,
                ], 200);
            }

            $regId = (int) $request->input('user_id');
            $occupied = array_keys($this->occupiedCustomerCodes($regId));

            $data = MasterPelanggan::query()
                ->where('is_active', true)
                ->when($occupied, function ($q) use ($occupied) {
                    $q->whereNotIn('id_pelanggan', $occupied);
                })
                ->where(function ($q) use ($search) {
                    $q->where('nama_pelanggan', 'like', '%' . $search . '%')
                        ->orWhere('id_pelanggan', 'like', '%' . $search . '%');
                })
                ->select('id_pelanggan', 'nama_pelanggan')
                ->orderBy('nama_pelanggan', 'asc')
                ->limit(50)
                ->get();

            return response()->json([
                'message' => 'Customer data displayed successfully',
                'data' => $data,
                'status' => 200,
                'success' => true,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'data' => [],
                'status' => 500,
                'success' => false,
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $id = (int) $request->input('id');
        $email = strtolower(trim((string) $request->input('email')));
        $nama = trim((string) $request->input('nama_lengkap'));
        $ids = $request->input('id_pelanggan', []);
        if (!is_array($ids)) {
            $ids = $this->decodeIds($ids);
        } else {
            $ids = array_values(array_filter(array_map('strval', $ids)));
        }

        if ($id < 1 || $email === '' || $ids === []) {
            return response()->json([
                'message' => 'Email dan pelanggan wajib diisi',
                'status' => 422,
                'success' => false,
            ], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'message' => 'Format email tidak valid',
                'status' => 422,
                'success' => false,
            ], 422);
        }

        $portalDb = $this->portalDb();
        $row = DB::table('self_registration_customer')->where('id', $id)->first();
        if (!$row) {
            return response()->json([
                'message' => 'Data tidak ditemukan',
                'status' => 404,
                'success' => false,
            ], 404);
        }

        $dupUser = DB::table($portalDb . '.users')
            ->where('email', $email)
            ->when($row->user_id, function ($q) use ($row) {
                $q->where('id', '!=', $row->user_id);
            })
            ->first();
        if ($dupUser) {
            return response()->json([
                'message' => 'Email sudah terdaftar',
                'status' => 400,
                'success' => false,
            ], 400);
        }

        $dupReg = DB::table('self_registration_customer')
            ->where('email', $email)
            ->where('id', '!=', $id)
            ->whereIn('status', ['pending', 'signed'])
            ->first();
        if ($dupReg) {
            return response()->json([
                'message' => 'Email sudah terdaftar',
                'status' => 400,
                'success' => false,
            ], 400);
        }

        $occupied = $this->occupiedCustomerCodes($id);
        foreach ($ids as $code) {
            if (isset($occupied[$code])) {
                return response()->json([
                    'message' => 'Pelanggan ' . $code . ' sudah terikat akun lain',
                    'status' => 400,
                    'success' => false,
                ], 400);
            }
        }

        if ($row->user_id && $nama === '') {
            return response()->json([
                'message' => 'Nama PIC wajib diisi',
                'status' => 422,
                'success' => false,
            ], 422);
        }

        $jsonIds = json_encode($ids);
        $now = Carbon::now();

        DB::beginTransaction();
        try {
            DB::table('self_registration_customer')->where('id', $id)->update([
                'email' => $email,
                'id_pelanggan' => $jsonIds,
                'updated_at' => $now,
            ]);

            if ($row->user_id) {
                DB::table($portalDb . '.users')->where('id', $row->user_id)->update([
                    'nama_lengkap' => $nama,
                    'email' => $email,
                    'id_pelanggan' => $jsonIds,
                    'updated_by' => $this->karyawan,
                    'updated_at' => $now,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Data customer berhasil disimpan',
                'status' => 200,
                'success' => true,
            ], 200);
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'status' => 500,
                'success' => false,
            ], 500);
        }
    }

    public function viewPdf(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id < 1) {
            return response()->json(['message' => 'ID tidak valid', 'success' => false, 'status' => 422], 422);
        }

        $row = DB::table('self_registration_customer')->where('id', $id)->first();
        if (!$row || empty($row->pdf_path)) {
            return response()->json(['message' => 'Dokumen tidak ditemukan', 'success' => false, 'status' => 404], 404);
        }

        $binary = (new StatementPdfService())->readPdf($row->pdf_path);
        if ($binary === null || $binary === '') {
            return response()->json(['message' => 'File dokumen tidak ada di penyimpanan', 'success' => false, 'status' => 404], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'filename' => 'surat-pernyataan-portal-intilab.pdf',
            'mime' => 'application/pdf',
            'content_base64' => base64_encode($binary),
        ]);
    }

    private function portalDb()
    {
        return env('DB_PORTAL_PELANGGAN', 'portal_pelanggan_intilab');
    }

    private function decodeIds($raw)
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }

        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
    }

    private function occupiedCustomerCodes($exceptRegId = 0)
    {
        $occupied = [];
        $portalDb = $this->portalDb();
        $reg = $exceptRegId
            ? DB::table('self_registration_customer')->where('id', $exceptRegId)->first()
            : null;
        $exceptUserId = $reg && $reg->user_id ? (int) $reg->user_id : 0;

        $users = DB::table($portalDb . '.users')
            ->where('is_active', 1)
            ->when($exceptUserId, function ($q) use ($exceptUserId) {
                $q->where('id', '!=', $exceptUserId);
            })
            ->get(['id_pelanggan']);

        foreach ($users as $user) {
            foreach ($this->decodeIds($user->id_pelanggan) as $code) {
                $occupied[$code] = true;
            }
        }

        $busy = DB::table('self_registration_customer')
            ->where('status', 'signed')
            ->when($exceptRegId, function ($q) use ($exceptRegId) {
                $q->where('id', '!=', $exceptRegId);
            })
            ->get(['id_pelanggan']);

        foreach ($busy as $row) {
            foreach ($this->decodeIds($row->id_pelanggan) as $code) {
                $occupied[$code] = true;
            }
        }

        return $occupied;
    }
}
