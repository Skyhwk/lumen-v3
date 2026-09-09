<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\IntilabInternal\PermissionRequest;
use App\Models\MasterKaryawan;
use App\Services\GetAtasan;
use App\Services\GetBawahan;
use App\Services\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class PermohonanIzinController extends Controller
{
    private function getEmployeeUserId(): ?int
    {
        if (!$this->user_id) {
            return null;
        }

        return MasterKaryawan::where('id', $this->user_id)->value('user_id');
    }

    private function getOwnerNames(): array
    {
        $names = GetBawahan::where('id', $this->user_id)
            ->get()
            ->pluck('nama_lengkap')
            ->toArray();

        if ($this->karyawan) {
            $names[] = $this->karyawan;
        }

        return array_values(array_unique(array_filter($names)));
    }

    private function baseQuery(int $periode)
    {
        return PermissionRequest::on('intilab_apps')
            ->from('intilab_apps.permission_requests as pr')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'pr.employee_id', '=', 'u.user_id')
            ->leftJoin('intilab_produksi.master_divisi as d', 'u.id_department', '=', 'd.id')
            ->where('pr.is_active', 1)
            ->whereIn('pr.created_by', $this->getOwnerNames())
            ->whereYear('pr.created_at', $periode ?: date('Y'))
            ->select(
                'pr.id',
                'pr.no_document',
                'pr.type',
                'pr.start_date',
                'pr.end_date',
                'pr.start_time',
                'pr.end_time',
                'pr.description',
                'pr.attachment',
                'pr.status',
                'pr.created_by as nama_pengaju',
                'pr.created_at as diajukan_pada',
                'pr.approved_atasan_by',
                'pr.approved_atasan_at',
                'pr.rejected_atasan_by',
                'pr.rejected_atasan_at',
                'pr.reject_atasan_reason',
                'pr.approved_hrd_by',
                'pr.approved_hrd_at',
                'pr.rejected_hrd_by',
                'pr.rejected_hrd_at',
                'pr.reject_hrd_reason',
                'd.nama_divisi'
            );
    }

    private function mapStatusLabel(string $status): string
    {
        $map = [
            'Pending' => 'Menunggu Atasan',
            'Approved Atasan' => 'Disetujui Atasan',
            'Rejected Atasan' => 'Ditolak Atasan',
            'Approved HRD' => 'Disetujui HRD',
            'Rejected HRD' => 'Ditolak HRD',
        ];

        return $map[$status] ?? $status;
    }

    private function mapTypeLabel(string $type): string
    {
        $map = [
            'Event Leave' => 'Izin Kegiatan',
            'Sick Leave' => 'Izin Sakit',
            'Late Arrival' => 'Datang Terlambat',
        ];

        return $map[$type] ?? $type;
    }

    public function indexUnprocessed(Request $request)
    {
        try {
            $query = $this->baseQuery((int) $request->periode)
                ->where(function ($query) {
                    $query->whereNull('pr.approved_hrd_by')
                        ->whereNull('pr.rejected_hrd_by')
                        ->whereNull('pr.rejected_atasan_by');
                });

            // Jika user adalah STAFF, hanya tampilkan data permohonannya sendiri.
            // Jika MANAGER, sudah otomatis dari baseQuery: getOwnerNames() sudah include bawahan.
            if ($this->grade === 'STAFF') {
                $query->where('pr.created_by', $this->karyawan);
            }

            $data = $query->get()
                ->map(function ($item) {
                    $item->status_label = $this->mapStatusLabel($item->status);
                    $item->type_label = $this->mapTypeLabel($item->type);
                    return $item;
                });

            return Datatables::of($data)
                ->addColumn('can_approve', fn () => $this->grade === 'MANAGER')
                ->make(true);
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
            $query = $this->baseQuery((int) $request->periode)
                ->where(function ($query) {
                    $query->whereNotNull('pr.approved_hrd_by')
                        ->orWhereNotNull('pr.rejected_hrd_by')
                        ->orWhereNotNull('pr.rejected_atasan_by');
                });

            // Jika user adalah STAFF, hanya tampilkan data permohonannya sendiri.
            // Jika MANAGER, sudah otomatis dari baseQuery: getOwnerNames() sudah include bawahan.
            if ($this->grade === 'STAFF') {
                $query->where('pr.created_by', $this->karyawan);
            }

            $data = $query->get()
                ->map(function ($item) {
                    $item->status_label = $this->mapStatusLabel($item->status);
                    $item->type_label = $this->mapTypeLabel($item->type);
                    return $item;
                });

            return Datatables::of($data)
                ->addColumn('can_approve', fn () => false)
                ->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function createPermohonanIzin(Request $request)
    {
        DB::beginTransaction();

        try {
            $employeeUserId = $this->getEmployeeUserId();
            if (!$employeeUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan',
                ], 404);
            }

            $validation = $this->validatePayload($request);
            if ($validation !== true) {
                return $validation;
            }

            $attachment = $this->handleAttachmentUpload($request);
            if ($attachment instanceof \Illuminate\Http\JsonResponse) {
                return $attachment;
            }

            $noDocument = str_replace('.', '/', (string) microtime(true));
            $isManager = $this->grade === 'MANAGER';

            $record = PermissionRequest::on('intilab_apps')->create([
                'employee_id' => $employeeUserId,
                'no_document' => $noDocument,
                'type' => $request->type,
                'start_date' => $request->start_date,
                'end_date' => $request->type === 'Late Arrival' ? null : $request->end_date,
                'start_time' => $request->type === 'Late Arrival' ? $request->start_time : null,
                'end_time' => $request->type === 'Late Arrival' ? $request->end_time : null,
                'description' => $request->description,
                'attachment' => $attachment,
                'status' => $isManager ? 'Approved Atasan' : 'Pending',
                'approved_atasan_by' => $isManager ? $this->karyawan : null,
                'approved_atasan_at' => $isManager ? Carbon::now()->format('Y-m-d H:i:s') : null,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'is_active' => 1,
            ]);

            if (!$isManager) {
                $atasanIds = GetAtasan::where('id', $this->user_id)->get()->pluck('id')->toArray();
                if (!empty($atasanIds)) {
                    Notification::whereIn('id', $atasanIds)
                        ->title('Permohonan Izin Baru')
                        ->message('Permohonan izin dari ' . $this->karyawan . ' menunggu persetujuan Anda')
                        ->url('/request/formulir/permohonan-izin')
                        ->send();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permohonan izin berhasil diajukan',
                'data' => ['no_document' => $record->no_document],
            ], 200);
        } catch (\Exception $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => $ex->getMessage(),
            ], 500);
        }
    }

    public function updatePermohonanIzin(Request $request)
    {
        DB::beginTransaction();

        try {
            $record = PermissionRequest::on('intilab_apps')->find($request->id);
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan izin tidak ditemukan',
                ], 404);
            }

            if ($record->created_by !== $this->karyawan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk mengubah data ini',
                ], 403);
            }

            if ($record->status !== 'Pending' || $record->approved_atasan_by) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan izin sudah diproses dan tidak dapat diubah',
                ], 400);
            }

            $validation = $this->validatePayload($request);
            if ($validation !== true) {
                return $validation;
            }

            $attachment = $this->handleAttachmentUpload($request, $record->attachment);
            if ($attachment instanceof \Illuminate\Http\JsonResponse) {
                return $attachment;
            }

            $record->update([
                'type' => $request->type,
                'start_date' => $request->start_date,
                'end_date' => $request->type === 'Late Arrival' ? null : $request->end_date,
                'start_time' => $request->type === 'Late Arrival' ? $request->start_time : null,
                'end_time' => $request->type === 'Late Arrival' ? $request->end_time : null,
                'description' => $request->description,
                'attachment' => $attachment,
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permohonan izin berhasil diperbarui',
            ], 200);
        } catch (\Exception $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => $ex->getMessage(),
            ], 500);
        }
    }

    public function approveAtasanPermohonanIzin(Request $request)
    {
        DB::beginTransaction();

        try {
            $record = PermissionRequest::on('intilab_apps')->find($request->id);
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan izin tidak ditemukan',
                ], 404);
            }

            $record->update([
                'status' => 'Approved Atasan',
                'approved_atasan_by' => $this->karyawan,
                'approved_atasan_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $userIds = GetAtasan::where('nama_lengkap', $record->created_by)->get()->pluck('id')->toArray();
            if (!empty($userIds)) {
                Notification::whereIn('id', $userIds)
                    ->title('Permohonan Izin')
                    ->message('Permohonan izin Anda telah disetujui atasan oleh ' . $this->karyawan)
                    ->url('/request/formulir/permohonan-izin')
                    ->send();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permohonan izin berhasil disetujui',
            ], 200);
        } catch (\Exception $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => $ex->getMessage(),
            ], 500);
        }
    }

    public function rejectAtasanPermohonanIzin(Request $request)
    {
        DB::beginTransaction();

        try {
            $record = PermissionRequest::on('intilab_apps')->find($request->id);
            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan izin tidak ditemukan',
                ], 404);
            }

            $record->update([
                'status' => 'Rejected Atasan',
                'rejected_atasan_by' => $this->karyawan,
                'rejected_atasan_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'reject_atasan_reason' => $request->keterangan,
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $userIds = GetAtasan::where('nama_lengkap', $record->created_by)->get()->pluck('id')->toArray();
            if (!empty($userIds)) {
                Notification::whereIn('id', $userIds)
                    ->title('Permohonan Izin Ditolak')
                    ->message('Permohonan izin Anda ditolak atasan. Alasan: ' . $request->keterangan)
                    ->url('/request/formulir/permohonan-izin')
                    ->send();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permohonan izin berhasil ditolak',
            ], 200);
        } catch (\Exception $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => $ex->getMessage(),
            ], 500);
        }
    }

    private function validatePayload(Request $request)
    {
        $allowedTypes = ['Event Leave', 'Sick Leave', 'Late Arrival'];

        if (!$request->type || !in_array($request->type, $allowedTypes, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis izin tidak valid',
            ], 422);
        }

        if (!$request->start_date) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal mulai wajib diisi',
            ], 422);
        }

        if ($request->type !== 'Late Arrival' && !$request->end_date) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal selesai wajib diisi',
            ], 422);
        }

        if ($request->type === 'Late Arrival' && (!$request->start_time || !$request->end_time)) {
            return response()->json([
                'success' => false,
                'message' => 'Jam mulai dan jam selesai wajib diisi',
            ], 422);
        }

        if (!$request->description) {
            return response()->json([
                'success' => false,
                'message' => 'Keterangan wajib diisi',
            ], 422);
        }

        return true;
    }

    private function handleAttachmentUpload(Request $request, string $existing = '')
    {
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            $extension = strtolower($file->getClientOriginalExtension());

            if (!in_array($extension, $allowed, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format lampiran harus PDF, JPG, JPEG, atau PNG',
                ], 422);
            }

            $uploadPath = public_path('permission_requests');
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $filename = str_replace('.', '', (string) microtime(true)) . '.' . $extension;
            $file->move($uploadPath, $filename);

            return $filename;
        }

        if ($existing) {
            return $existing;
        }

        if (!$request->id) {
            return response()->json([
                'success' => false,
                'message' => 'Lampiran wajib diunggah',
            ], 422);
        }

        return $existing ?: '';
    }
}
