<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\IntilabInternal\AttendanceCorrections;
use App\Services\GetAtasan;
use App\Services\GetBawahan;
use App\Services\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class KoreksiKehadiranController extends Controller
{
    private function getOwnerIds()
    {
        $ids = GetBawahan::where('id', $this->user_id)
            ->get()
            ->pluck('id')
            ->toArray();

        if ($this->user_id) {
            $ids[] = $this->user_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function menuUrl()
    {
        return '/request/permohonan/koreksi-kehadiran';
    }

    private function baseQuery($periode)
    {
        $query = AttendanceCorrections::on('intilab_apps')
            ->from('intilab_apps.attendance_corrections as ac')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'ac.employee_id', '=', 'u.id')
            ->leftJoin('intilab_produksi.master_divisi as d', 'u.id_department', '=', 'd.id')
            ->where('ac.is_active', 1)
            ->whereIn('ac.employee_id', $this->getOwnerIds())
            ->select(
                'ac.id',
                'ac.employee_id',
                'ac.type',
                'ac.date',
                'ac.time',
                'ac.description',
                'ac.attachment',
                'ac.status',
                'ac.created_by as nama_pengaju',
                'ac.created_at as diajukan_pada',
                'ac.approved_atasan_by',
                'ac.approved_atasan_at',
                'ac.rejected_atasan_by',
                'ac.rejected_atasan_at',
                'ac.reject_atasan_reason',
                'ac.approved_hrd_by',
                'ac.approved_hrd_at',
                'ac.rejected_hrd_by',
                'ac.rejected_hrd_at',
                'ac.reject_hrd_reason',
                'u.nama_lengkap as karyawan',
                'd.nama_divisi'
            );

        $periode = (int) $periode;
        if ($periode >= 2000) {
            $query->whereYear('ac.date', $periode);
        }

        return $query;
    }

    private function applyStaffFilter($query)
    {
        if ($this->grade === 'STAFF') {
            $query->where('ac.employee_id', $this->user_id);
        }

        return $query;
    }

    public function tabCounts(Request $request)
    {
        $periode = (int) ($request->periode ?: date('Y'));
        $onProgress = $this->applyStaffFilter(
            $this->baseQuery($periode)->whereIn('ac.status', ['Pending', 'Approved Atasan'])
        );
        $processed = $this->applyStaffFilter(
            $this->baseQuery($periode)->whereIn('ac.status', ['Approved HRD', 'Rejected Atasan', 'Rejected HRD'])
        );

        return response()->json([
            'success' => true,
            'data' => [
                'on_progress' => $onProgress->count(),
                'processed' => $processed->count(),
            ],
        ]);
    }

    public function indexUnprocessed(Request $request)
    {
        try {
            $query = $this->applyStaffFilter(
                $this->baseQuery((int) $request->periode)
                    ->whereIn('ac.status', ['Pending', 'Approved Atasan'])
            );

            $data = $query->get()->map(function ($item) {
                return $this->decorate($item);
            });

            return Datatables::of($data)->make(true);
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
            $query = $this->applyStaffFilter(
                $this->baseQuery((int) $request->periode)
                    ->whereIn('ac.status', ['Approved HRD', 'Rejected Atasan', 'Rejected HRD'])
            );

            $data = $query->get()->map(function ($item) {
                return $this->decorate($item);
            });

            return Datatables::of($data)->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function create(Request $request)
    {
        DB::connection('intilab_apps')->beginTransaction();

        try {
            if (!$this->user_id) {
                DB::connection('intilab_apps')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan',
                ], 404);
            }

            $validation = $this->validatePayload($request);
            if ($validation !== true) {
                DB::connection('intilab_apps')->rollBack();
                return $validation;
            }

            $attachment = $this->handleAttachmentUpload($request);
            if ($attachment instanceof \Illuminate\Http\JsonResponse) {
                DB::connection('intilab_apps')->rollBack();
                return $attachment;
            }

            $isManager = $this->grade === 'MANAGER';
            $now = Carbon::now()->format('Y-m-d H:i:s');
            $waktu = $this->normalizeTime($request->input('time'));

            AttendanceCorrections::on('intilab_apps')->create([
                'employee_id' => $this->user_id,
                'type' => $request->input('type'),
                'date' => $request->input('date'),
                'time' => $waktu,
                'description' => $request->input('description') ?: $request->input('keterangan'),
                'attachment' => $attachment !== '' ? $attachment : '',
                'status' => $isManager ? 'Approved Atasan' : 'Pending',
                'approved_atasan_by' => $isManager ? $this->karyawan : null,
                'approved_atasan_at' => $isManager ? $now : null,
                'created_by' => $this->karyawan,
                'created_at' => $now,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
                'is_active' => 1,
            ]);

            if (!$isManager) {
                try {
                    $atasanIds = GetAtasan::where('id', $this->user_id)->get()->pluck('id')->toArray();
                    if (!empty($atasanIds)) {
                        Notification::whereIn('id', $atasanIds)
                            ->title('Koreksi Kehadiran Baru')
                            ->message('Permintaan koreksi kehadiran dari ' . $this->karyawan . ' menunggu persetujuan Anda')
                            ->url($this->menuUrl())
                            ->send();
                    }
                } catch (\Exception $notifyEx) {
                    // Pengajuan tetap disimpan meski notifikasi gagal.
                }
            }

            DB::connection('intilab_apps')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Permintaan koreksi kehadiran berhasil diajukan',
            ], 200);
        } catch (\Exception $ex) {
            DB::connection('intilab_apps')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => $ex->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request)
    {
        DB::connection('intilab_apps')->beginTransaction();

        try {
            $record = AttendanceCorrections::on('intilab_apps')->find($request->id);
            if (!$record || (int) $record->is_active !== 1) {
                DB::connection('intilab_apps')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data koreksi kehadiran tidak ditemukan',
                ], 404);
            }

            if ((int) $record->employee_id !== (int) $this->user_id) {
                DB::connection('intilab_apps')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk mengubah data ini',
                ], 403);
            }

            if ($record->status !== 'Pending' || $record->approved_atasan_by) {
                DB::connection('intilab_apps')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Koreksi kehadiran sudah diproses dan tidak dapat diubah',
                ], 400);
            }

            $validation = $this->validatePayload($request);
            if ($validation !== true) {
                DB::connection('intilab_apps')->rollBack();
                return $validation;
            }

            $attachment = $this->handleAttachmentUpload($request, $record->attachment);
            if ($attachment instanceof \Illuminate\Http\JsonResponse) {
                DB::connection('intilab_apps')->rollBack();
                return $attachment;
            }

            $record->update([
                'type' => $request->input('type'),
                'date' => $request->input('date'),
                'time' => $this->normalizeTime($request->input('time')),
                'description' => $request->input('description') ?: $request->input('keterangan'),
                'attachment' => $attachment !== '' && $attachment !== null ? $attachment : ($record->attachment ?: ''),
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::connection('intilab_apps')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Permintaan koreksi kehadiran berhasil diperbarui',
            ], 200);
        } catch (\Exception $ex) {
            DB::connection('intilab_apps')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => $ex->getMessage(),
            ], 500);
        }
    }

    public function approveAtasan(Request $request)
    {
        DB::beginTransaction();

        try {
            if (!$this->isManagerGrade()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya Manager yang dapat menyetujui koreksi kehadiran',
                ], 403);
            }

            $record = AttendanceCorrections::on('intilab_apps')->find($request->id);
            if (!$record || (int) $record->is_active !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data koreksi kehadiran tidak ditemukan',
                ], 404);
            }

            if ($record->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Koreksi kehadiran sudah diproses sebelumnya',
                ], 400);
            }

            if ((int) $record->employee_id === (int) $this->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak dapat menyetujui pengajuan milik sendiri',
                ], 400);
            }

            $now = Carbon::now()->format('Y-m-d H:i:s');
            $record->update([
                'status' => 'Approved Atasan',
                'approved_atasan_by' => $this->karyawan,
                'approved_atasan_at' => $now,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
            ]);

            Notification::where('id', $record->employee_id)
                ->title('Koreksi Kehadiran')
                ->message('Permintaan koreksi kehadiran Anda telah disetujui atasan oleh ' . $this->karyawan)
                ->url($this->menuUrl())
                ->send();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Koreksi kehadiran berhasil disetujui',
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

    public function rejectAtasan(Request $request)
    {
        DB::beginTransaction();

        try {
            if (!$this->isManagerGrade()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya Manager yang dapat menolak koreksi kehadiran',
                ], 403);
            }

            $reason = trim((string) ($request->keterangan ?: $request->reason ?: $request->reject_atasan_reason));
            if ($reason === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Alasan penolakan wajib diisi',
                ], 422);
            }

            $record = AttendanceCorrections::on('intilab_apps')->find($request->id);
            if (!$record || (int) $record->is_active !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data koreksi kehadiran tidak ditemukan',
                ], 404);
            }

            if ($record->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Koreksi kehadiran sudah diproses sebelumnya',
                ], 400);
            }

            $now = Carbon::now()->format('Y-m-d H:i:s');
            $record->update([
                'status' => 'Rejected Atasan',
                'rejected_atasan_by' => $this->karyawan,
                'rejected_atasan_at' => $now,
                'reject_atasan_reason' => $reason,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
            ]);

            Notification::where('id', $record->employee_id)
                ->title('Koreksi Kehadiran Ditolak')
                ->message('Permintaan koreksi kehadiran Anda ditolak atasan. Alasan: ' . $reason)
                ->url($this->menuUrl())
                ->send();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Koreksi kehadiran berhasil ditolak',
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

    private function decorate($item)
    {
        $item->karyawan = $item->karyawan ?: $item->nama_pengaju ?: '-';
        $item->nama_divisi = $item->nama_divisi ?: '-';
        $item->status_label = $this->mapStatusLabel($item->status);
        $item->type_label = $this->mapTypeLabel($item->type);
        $item->can_approve = $this->isManagerGrade()
            && $item->status === 'Pending'
            && (int) $item->employee_id !== (int) $this->user_id;
        $item->can_edit = $item->status === 'Pending'
            && (int) $item->employee_id === (int) $this->user_id;

        return $item;
    }

    private function isManagerGrade()
    {
        return strtoupper(trim((string) $this->grade)) === 'MANAGER';
    }

    private function mapStatusLabel($status)
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

    private function mapTypeLabel($type)
    {
        $map = [
            'Check In' => 'Masuk',
            'Check Out' => 'Keluar',
        ];

        return $map[$type] ?? ($type ?: '-');
    }

    private function normalizeType($type)
    {
        $value = strtolower(trim((string) $type));
        if (in_array($value, ['masuk', 'check in', 'checkin'], true)) {
            return 'Check In';
        }
        if (in_array($value, ['keluar', 'check out', 'checkout'], true)) {
            return 'Check Out';
        }

        return trim((string) $type);
    }

    private function normalizeTime($time)
    {
        $value = trim((string) $time);
        if ($value === '') {
            return $value;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value . ':00';
        }
        return $value;
    }

    private function validatePayload(Request $request)
    {
        $request->merge(['type' => $this->normalizeType($request->type)]);
        $allowedTypes = ['Check In', 'Check Out'];

        if (!$request->type || !in_array($request->type, $allowedTypes, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Tipe absen harus Check In atau Check Out',
            ], 422);
        }

        if (!$request->date) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal wajib diisi',
            ], 422);
        }

        if (!$request->time) {
            return response()->json([
                'success' => false,
                'message' => 'Jam wajib diisi',
            ], 422);
        }

        if (!$request->description) {
            $request->merge(['description' => $request->keterangan]);
        }

        if (!$request->description) {
            return response()->json([
                'success' => false,
                'message' => 'Alasan / deskripsi wajib diisi',
            ], 422);
        }

        return true;
    }

    private function handleAttachmentUpload(Request $request, $existing = '')
    {
        $file = $request->file('attachment');
        if (!$file instanceof \Illuminate\Http\UploadedFile && $request->attachment instanceof \Illuminate\Http\UploadedFile) {
            $file = $request->attachment;
        }

        if (!$file instanceof \Illuminate\Http\UploadedFile) {
            return $existing ?: '';
        }

        if (!$file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Lampiran gagal diunggah. Periksa ukuran file lalu coba lagi.',
            ], 422);
        }

        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Format lampiran harus PDF, JPG, JPEG, atau PNG',
            ], 422);
        }

        $uploadPath = public_path('attendance_corrections');
        if (!is_dir($uploadPath) && !@mkdir($uploadPath, 0777, true) && !is_dir($uploadPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan lampiran. Folder upload tidak dapat dibuat.',
            ], 500);
        }

        if (!is_writable($uploadPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan lampiran. Folder upload tidak dapat ditulis.',
            ], 500);
        }

        $filename = str_replace('.', '', (string) microtime(true)) . '.' . $extension;

        try {
            $file->move($uploadPath, $filename);
        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan lampiran',
                'error' => $ex->getMessage(),
            ], 500);
        }

        return $filename;
    }
}
