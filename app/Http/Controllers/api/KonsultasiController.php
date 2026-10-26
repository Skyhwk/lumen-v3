<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\IntilabInternal\ConsultationRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class KonsultasiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = $this->baseQuery((int) $request->periode, $request->scope)
                ->whereIn('cr.status', ['Pending', 'Approved']);

            return Datatables::of($this->decorate($query->get()))->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 402);
        }
    }

    public function tabCounts(Request $request)
    {
        $periode = (int) ($request->periode ?: date('Y'));

        return response()->json([
            'success' => true,
            'data' => [
                'on_progress' => $this->baseQuery($periode, $request->scope)->where('cr.status', 'Pending')->count(),
                'processed' => $this->baseQuery($periode, $request->scope)->whereIn('cr.status', ['Approved', 'Rejected'])->count(),
            ],
        ]);
    }

    public function indexUnprocessed(Request $request)
    {
        try {
            $query = $this->baseQuery((int) $request->periode, $request->scope)
                ->where('cr.status', 'Pending');

            return Datatables::of($this->decorate($query->get()))->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function indexProcessed(Request $request)
    {
        try {
            $query = $this->baseQuery((int) $request->periode, $request->scope)
                ->whereIn('cr.status', ['Approved', 'Rejected']);

            return Datatables::of($this->decorate($query->get()))->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function create(Request $request)
    {
        try {
            if (!$this->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan',
                ], 404);
            }

            $type = $this->normalizeType($request->type);
            $tanggal = trim((string) $request->tanggal);
            $waktu = trim((string) $request->waktu);
            $deskripsi = trim((string) ($request->deskripsi ?: $request->description ?: $request->keterangan));

            if ($type === '' || $tanggal === '' || $waktu === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipe, tanggal, dan waktu wajib diisi',
                ], 422);
            }

            $now = Carbon::now()->format('Y-m-d H:i:s');
            ConsultationRequest::on('intilab_apps')->create([
                'employee_id' => $this->user_id,
                'date' => $tanggal,
                'time' => $waktu,
                'type' => $type,
                'description' => $deskripsi,
                'status' => 'Pending',
                'is_active' => 1,
                'created_by' => $this->karyawan,
                'created_at' => $now,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permintaan konsultasi berhasil diajukan',
            ], 200);
        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengajukan konsultasi: ' . $ex->getMessage(),
            ], 500);
        }
    }

    public function approve(Request $request)
    {
        try {
            $data = ConsultationRequest::on('intilab_apps')
                ->where('id', $this->resolveId($request))
                ->where('is_active', 1)
                ->first();

            if ($data === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data konsultasi tidak ditemukan',
                ], 404);
            }

            if ($data->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Konsultasi sudah diproses sebelumnya',
                ], 400);
            }

            $now = Carbon::now()->format('Y-m-d H:i:s');
            $data->update([
                'status' => 'Approved',
                'approved_by' => $this->karyawan,
                'approved_at' => $now,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
            ]);

            return response()->json(['message' => 'Permintaan konsultasi disetujui'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function void(Request $request)
    {
        try {
            $data = ConsultationRequest::on('intilab_apps')
                ->where('id', $this->resolveId($request))
                ->where('is_active', 1)
                ->first();

            if ($data === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data konsultasi tidak ditemukan',
                ], 404);
            }

            $reason = $request->paramBody ?? $request->keterangan ?? $request->reject_reason;
            $now = Carbon::now()->format('Y-m-d H:i:s');
            $data->update([
                'status' => 'Rejected',
                'rejected_by' => $this->karyawan,
                'rejected_at' => $now,
                'reject_reason' => $reason,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil direject',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal reject data: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function resolveId(Request $request)
    {
        return $request->id ?: $request->consulId;
    }

    private function baseQuery($periode, $scope = null)
    {
        $query = ConsultationRequest::on('intilab_apps')
            ->from('intilab_apps.consultation_requests as cr')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'cr.employee_id', '=', 'u.id')
            ->leftJoin('intilab_produksi.master_divisi as d', 'u.id_department', '=', 'd.id')
            ->where('cr.is_active', 1)
            ->select(
                'cr.id',
                'cr.employee_id',
                'cr.date as tanggal',
                'cr.time as waktu',
                'cr.type',
                'cr.description as deskripsi',
                'cr.status',
                'cr.approved_by',
                'cr.approved_at',
                'cr.rejected_by',
                'cr.rejected_at',
                'cr.reject_reason',
                'cr.created_by',
                'cr.created_at',
                'u.nama_lengkap as karyawan',
                'd.nama_divisi as department'
            );

        $periode = (int) $periode;
        if ($periode >= 2000) {
            $query->whereYear('cr.date', $periode);
        }

        if ($scope !== 'hrd' && $this->grade === 'STAFF' && $this->user_id) {
            $query->where('cr.employee_id', $this->user_id);
        }

        return $query;
    }

    private function decorate($items)
    {
        return $items->map(function ($item) {
            $item->nama_pengaju = $item->karyawan ?: $item->created_by ?: '-';
            $item->karyawan = $item->nama_pengaju;
            $item->nama_divisi = $item->department ?: '-';
            $item->department = $item->nama_divisi;
            $item->status_label = $this->statusLabel($item->status);
            $item->type_label = $this->typeLabel($item->type);
            $item->deskripsi = $item->deskripsi ?: '-';
            $item->approved_by = $item->status === 'Approved' ? ($item->approved_by ?: '-') : '-';
            $item->rejected_by = $item->status === 'Rejected' ? ($item->rejected_by ?: '-') : '-';
            $item->reject_reason = $item->status === 'Rejected' ? ($item->reject_reason ?: '-') : '-';
            $item->can_approve = $item->status === 'Pending';

            return $item;
        });
    }

    private function normalizeType($type)
    {
        $value = strtolower(trim((string) $type));
        if ($value === 'online') {
            return 'Online';
        }
        if ($value === 'offline') {
            return 'Offline';
        }

        return trim((string) $type);
    }

    private function statusLabel($status)
    {
        $map = [
            'Pending' => 'Menunggu Persetujuan',
            'Approved' => 'Disetujui',
            'Rejected' => 'Ditolak',
        ];

        return $map[$status] ?? ($status ?: '-');
    }

    private function typeLabel($type)
    {
        return $this->normalizeType($type) ?: '-';
    }
}
