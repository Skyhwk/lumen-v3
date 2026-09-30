<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\IntilabInternal\OvertimeReimbursement;
use App\Services\GetAtasan;
use App\Services\GetBawahan;
use App\Services\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class PenggantianBiayaLemburController extends Controller
{
    private function resolveScope(Request $request)
    {
        return strtolower(trim((string) $request->input('scope', '')));
    }

    private function statusGroups($scope)
    {
        if ($scope === 'hrd') {
            return [
                'unprocessed' => ['Approved Atasan'],
                'processed' => ['Approved HRD', 'Approved Finance', 'Rejected HRD', 'Rejected Finance'],
            ];
        }

        if ($scope === 'finance') {
            return [
                'unprocessed' => ['Approved HRD'],
                'processed' => ['Approved Finance', 'Rejected Finance'],
            ];
        }

        return [
            'unprocessed' => ['Pending', 'Approved Atasan', 'Approved HRD'],
            'processed' => ['Approved Finance', 'Rejected Atasan', 'Rejected HRD', 'Rejected Finance'],
        ];
    }

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

    private function isManagerGrade()
    {
        return strtoupper(trim((string) $this->grade)) === 'MANAGER';
    }

    private function staffMenuUrl()
    {
        return '/request/permohonan/penggantian-biaya-lembur';
    }

    private function baseQuery($periode, $scope = '')
    {
        $query = OvertimeReimbursement::on('intilab_apps')
            ->from('intilab_apps.overtime_reimbursements as obr')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'obr.employee_id', '=', 'u.id')
            ->leftJoin('intilab_produksi.master_divisi as d', 'u.id_department', '=', 'd.id')
            ->where('obr.is_active', 1)
            ->select(
                'obr.id',
                'obr.employee_id',
                'obr.date',
                'obr.amount',
                'obr.description',
                'obr.attachment',
                'obr.status',
                'obr.created_by as nama_pengaju',
                'obr.created_at as diajukan_pada',
                'obr.approved_atasan_by',
                'obr.approved_atasan_at',
                'obr.rejected_atasan_by',
                'obr.rejected_atasan_at',
                'obr.reject_atasan_reason',
                'obr.approved_hrd_by',
                'obr.approved_hrd_at',
                'obr.rejected_hrd_by',
                'obr.rejected_hrd_at',
                'obr.reject_hrd_reason',
                'obr.approved_finance_by',
                'obr.approved_finance_at',
                'obr.rejected_finance_by',
                'obr.rejected_finance_at',
                'obr.reject_finance_reason',
                'u.nama_lengkap as karyawan',
                'd.nama_divisi'
            );

        $periode = (int) $periode;
        if ($periode >= 2000) {
            $query->whereYear('obr.date', $periode);
        }

        if ($scope !== 'hrd' && $scope !== 'finance') {
            $ownerIds = $this->getOwnerIds();
            $query->whereIn('obr.employee_id', $ownerIds ?: [0]);
            if ($this->grade === 'STAFF' && $this->user_id) {
                $query->where('obr.employee_id', $this->user_id);
            }
        }

        return $query;
    }

    public function tabCounts(Request $request)
    {
        $periode = (int) ($request->periode ?: date('Y'));
        $scope = $this->resolveScope($request);
        $groups = $this->statusGroups($scope);

        return response()->json([
            'success' => true,
            'data' => [
                'on_progress' => $this->baseQuery($periode, $scope)->whereIn('obr.status', $groups['unprocessed'])->count(),
                'processed' => $this->baseQuery($periode, $scope)->whereIn('obr.status', $groups['processed'])->count(),
            ],
        ]);
    }

    public function indexUnprocessed(Request $request)
    {
        try {
            $scope = $this->resolveScope($request);
            $groups = $this->statusGroups($scope);
            $data = $this->baseQuery((int) $request->periode, $scope)
                ->whereIn('obr.status', $groups['unprocessed'])
                ->get()
                ->map(function ($item) use ($scope) {
                    return $this->decorate($item, $scope);
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
            $scope = $this->resolveScope($request);
            $groups = $this->statusGroups($scope);
            $data = $this->baseQuery((int) $request->periode, $scope)
                ->whereIn('obr.status', $groups['processed'])
                ->get()
                ->map(function ($item) use ($scope) {
                    return $this->decorate($item, $scope);
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

            $validation = $this->validatePayload($request, true);
            if ($validation !== true) {
                DB::connection('intilab_apps')->rollBack();
                return $validation;
            }

            $attachment = $this->handleAttachmentUpload($request);
            if ($attachment instanceof \Illuminate\Http\JsonResponse) {
                DB::connection('intilab_apps')->rollBack();
                return $attachment;
            }

            $isManager = $this->isManagerGrade();
            $now = Carbon::now()->format('Y-m-d H:i:s');

            OvertimeReimbursement::on('intilab_apps')->create([
                'employee_id' => $this->user_id,
                'date' => $request->input('date'),
                'amount' => $this->parseAmount($request->input('amount')),
                'description' => $request->input('description') ?: $request->input('keterangan'),
                'attachment' => $attachment,
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
                $this->notifyAtasan('Klaim Biaya Lembur Baru', 'Pengajuan penggantian biaya lembur dari ' . $this->karyawan . ' menunggu persetujuan Anda');
            }

            DB::connection('intilab_apps')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan penggantian biaya lembur berhasil diajukan',
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
            $record = OvertimeReimbursement::on('intilab_apps')->find($request->id);
            if (!$record || (int) $record->is_active !== 1) {
                DB::connection('intilab_apps')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data klaim biaya lembur tidak ditemukan',
                ], 404);
            }

            if ((int) $record->employee_id !== (int) $this->user_id) {
                DB::connection('intilab_apps')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk mengubah data ini',
                ], 403);
            }

            if ($record->status !== 'Pending') {
                DB::connection('intilab_apps')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Klaim sudah diproses dan tidak dapat diubah',
                ], 400);
            }

            $validation = $this->validatePayload($request, false);
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
                'date' => $request->input('date'),
                'amount' => $this->parseAmount($request->input('amount')),
                'description' => $request->input('description') ?: $request->input('keterangan'),
                'attachment' => $attachment ?: $record->attachment,
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::connection('intilab_apps')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan penggantian biaya lembur berhasil diperbarui',
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

    public function approve(Request $request)
    {
        $scope = $this->resolveScope($request);
        if ($scope === 'hrd') {
            return $this->approveHrd($request);
        }
        if ($scope === 'finance') {
            return $this->approveFinance($request);
        }

        return $this->approveAtasan($request);
    }

    public function reject(Request $request)
    {
        $scope = $this->resolveScope($request);
        if ($scope === 'hrd') {
            return $this->rejectHrd($request);
        }
        if ($scope === 'finance') {
            return $this->rejectFinance($request);
        }

        return $this->rejectAtasan($request);
    }

    public function approveAtasan(Request $request)
    {
        if (!$this->isManagerGrade()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Manager yang dapat menyetujui klaim biaya lembur',
            ], 403);
        }

        return $this->processDecision($request, 'Pending', [
            'status' => 'Approved Atasan',
            'approved_atasan_by' => $this->karyawan,
            'approved_atasan_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ], 'Klaim biaya lembur berhasil disetujui atasan', 'Klaim Biaya Lembur', 'Pengajuan penggantian biaya lembur Anda telah disetujui atasan oleh ' . $this->karyawan, true);
    }

    public function rejectAtasan(Request $request)
    {
        if (!$this->isManagerGrade()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Manager yang dapat menolak klaim biaya lembur',
            ], 403);
        }

        $reason = $this->rejectReason($request);
        if ($reason === '') {
            return response()->json([
                'success' => false,
                'message' => 'Alasan penolakan wajib diisi',
            ], 422);
        }

        return $this->processDecision($request, 'Pending', [
            'status' => 'Rejected Atasan',
            'rejected_atasan_by' => $this->karyawan,
            'rejected_atasan_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'reject_atasan_reason' => $reason,
        ], 'Klaim biaya lembur berhasil ditolak atasan', 'Klaim Biaya Lembur Ditolak', 'Pengajuan penggantian biaya lembur Anda ditolak atasan. Alasan: ' . $reason);
    }

    public function approveHrd(Request $request)
    {
        return $this->processDecision($request, 'Approved Atasan', [
            'status' => 'Approved HRD',
            'approved_hrd_by' => $this->karyawan,
            'approved_hrd_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ], 'Klaim biaya lembur berhasil disetujui HRD', 'Klaim Biaya Lembur', 'Pengajuan penggantian biaya lembur Anda telah disetujui HRD oleh ' . $this->karyawan);
    }

    public function rejectHrd(Request $request)
    {
        $reason = $this->rejectReason($request);
        if ($reason === '') {
            return response()->json([
                'success' => false,
                'message' => 'Alasan penolakan wajib diisi',
            ], 422);
        }

        return $this->processDecision($request, 'Approved Atasan', [
            'status' => 'Rejected HRD',
            'rejected_hrd_by' => $this->karyawan,
            'rejected_hrd_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'reject_hrd_reason' => $reason,
        ], 'Klaim biaya lembur berhasil ditolak HRD', 'Klaim Biaya Lembur Ditolak', 'Pengajuan penggantian biaya lembur Anda ditolak HRD. Alasan: ' . $reason);
    }

    public function approveFinance(Request $request)
    {
        return $this->processDecision($request, 'Approved HRD', [
            'status' => 'Approved Finance',
            'approved_finance_by' => $this->karyawan,
            'approved_finance_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ], 'Klaim biaya lembur berhasil disetujui Finance', 'Klaim Biaya Lembur', 'Pengajuan penggantian biaya lembur Anda telah disetujui Finance oleh ' . $this->karyawan);
    }

    public function rejectFinance(Request $request)
    {
        $reason = $this->rejectReason($request);
        if ($reason === '') {
            return response()->json([
                'success' => false,
                'message' => 'Alasan penolakan wajib diisi',
            ], 422);
        }

        return $this->processDecision($request, 'Approved HRD', [
            'status' => 'Rejected Finance',
            'rejected_finance_by' => $this->karyawan,
            'rejected_finance_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'reject_finance_reason' => $reason,
        ], 'Klaim biaya lembur berhasil ditolak Finance', 'Klaim Biaya Lembur Ditolak', 'Pengajuan penggantian biaya lembur Anda ditolak Finance. Alasan: ' . $reason);
    }

    private function processDecision(Request $request, $expectedStatus, array $payload, $successMessage, $notifTitle, $notifMessage, $blockSelf = false)
    {
        DB::connection('intilab_apps')->beginTransaction();

        try {
            $record = OvertimeReimbursement::on('intilab_apps')->find($request->id);
            if (!$record || (int) $record->is_active !== 1) {
                DB::connection('intilab_apps')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data klaim biaya lembur tidak ditemukan',
                ], 404);
            }

            if ($record->status !== $expectedStatus) {
                DB::connection('intilab_apps')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Klaim sudah diproses sebelumnya',
                ], 400);
            }

            if ($blockSelf && (int) $record->employee_id === (int) $this->user_id) {
                DB::connection('intilab_apps')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak dapat menyetujui pengajuan milik sendiri',
                ], 400);
            }

            $payload['updated_by'] = $this->karyawan;
            $payload['updated_at'] = Carbon::now()->format('Y-m-d H:i:s');
            $record->update($payload);

            $this->notifyEmployee($record->employee_id, $notifTitle, $notifMessage);

            DB::connection('intilab_apps')->commit();

            return response()->json([
                'success' => true,
                'message' => $successMessage,
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

    private function decorate($item, $scope = '')
    {
        $item->karyawan = $item->karyawan ?: $item->nama_pengaju ?: '-';
        $item->nama_divisi = $item->nama_divisi ?: '-';
        $item->status_label = $this->mapStatusLabel($item->status);
        $item->amount_label = $this->formatAmount($item->amount);
        $item->approved_by = $item->approved_finance_by ?: $item->approved_hrd_by ?: $item->approved_atasan_by ?: '-';
        $item->rejected_by = $item->rejected_finance_by ?: $item->rejected_hrd_by ?: $item->rejected_atasan_by ?: '-';
        $item->reject_reason = $item->reject_finance_reason ?: $item->reject_hrd_reason ?: $item->reject_atasan_reason ?: '-';
        $item->can_approve = $this->canApprove($item, $scope);
        $item->can_edit = $scope !== 'hrd' && $scope !== 'finance'
            && $item->status === 'Pending'
            && (int) $item->employee_id === (int) $this->user_id;

        return $item;
    }

    private function canApprove($item, $scope)
    {
        if ($scope === 'hrd') {
            return $item->status === 'Approved Atasan';
        }
        if ($scope === 'finance') {
            return $item->status === 'Approved HRD';
        }

        return $this->isManagerGrade()
            && $item->status === 'Pending'
            && (int) $item->employee_id !== (int) $this->user_id;
    }

    private function mapStatusLabel($status)
    {
        $map = [
            'Pending' => 'Menunggu Atasan',
            'Approved Atasan' => 'Disetujui Atasan',
            'Rejected Atasan' => 'Ditolak Atasan',
            'Approved HRD' => 'Disetujui HRD',
            'Rejected HRD' => 'Ditolak HRD',
            'Approved Finance' => 'Disetujui Finance',
            'Rejected Finance' => 'Ditolak Finance',
        ];

        return $map[$status] ?? $status;
    }

    private function parseAmount($value)
    {
        $raw = trim(str_replace(['Rp', 'rp', ' '], '', (string) $value));
        if ($raw === '') {
            return 0;
        }
        if (strpos($raw, ',') !== false && strpos($raw, '.') !== false) {
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif (strpos($raw, ',') !== false) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (substr_count($raw, '.') > 1) {
            $raw = str_replace('.', '', $raw);
        }

        return round((float) $raw, 2);
    }

    private function formatAmount($value)
    {
        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }

    private function rejectReason(Request $request)
    {
        return trim((string) ($request->keterangan ?: $request->reason ?: $request->reject_reason));
    }

    private function validatePayload(Request $request, $requireAttachment)
    {
        if (!$request->input('date')) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal wajib diisi',
            ], 422);
        }

        $amount = $this->parseAmount($request->input('amount'));
        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Nominal wajib diisi dan harus lebih dari 0',
            ], 422);
        }

        if (!$request->input('description') && !$request->input('keterangan')) {
            return response()->json([
                'success' => false,
                'message' => 'Keterangan wajib diisi',
            ], 422);
        }

        if ($requireAttachment && !$request->hasFile('attachment') && !($request->attachment instanceof \Illuminate\Http\UploadedFile)) {
            return response()->json([
                'success' => false,
                'message' => 'Lampiran bukti wajib diunggah',
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

        $uploadPath = public_path('overtime_reimbursements');
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

    private function notifyAtasan($title, $message)
    {
        try {
            $atasanIds = GetAtasan::where('id', $this->user_id)->get()->pluck('id')->toArray();
            if (!empty($atasanIds)) {
                Notification::whereIn('id', $atasanIds)
                    ->title($title)
                    ->message($message)
                    ->url($this->staffMenuUrl())
                    ->send();
            }
        } catch (\Exception $ex) {
            // Pengajuan tetap disimpan meski notifikasi gagal.
        }
    }

    private function notifyEmployee($employeeId, $title, $message)
    {
        try {
            Notification::where('id', $employeeId)
                ->title($title)
                ->message($message)
                ->url($this->staffMenuUrl())
                ->send();
        } catch (\Exception $ex) {
            // Keputusan tetap disimpan meski notifikasi gagal.
        }
    }
}
