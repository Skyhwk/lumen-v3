<?php

namespace App\Http\Controllers\api;

use App\Models\LeaveRequest;
use App\Models\MasterDivisi;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Services\GetAtasan;
use App\Services\GetBawahan;
use App\Services\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class PermohonanCutiController extends Controller
{
    /**
     * Data Permohonan Cuti Aktif / Berjalan (Unprocessed)
     * Untuk Karyawan (melihat pengajuan sendiri) & Atasan Langsung (melihat bawahan & approve/reject)
     */
    public function indexByOwner(Request $request)
    {
        // Ambil ID dan nama diri sendiri beserta seluruh bawahan
        $bawahan = GetBawahan::where('id', $this->user_id)->get();
        $bawahanIds = $bawahan->pluck('id')->toArray();
        $bawahanNames = $bawahan->pluck('nama_lengkap')->toArray();

        $query = LeaveRequest::on('intilab_apps')
            ->leftJoin('intilab_produksi.master_karyawan as karyawan', 'leave_requests.employee_id', '=', 'karyawan.id')
            ->leftJoin('intilab_produksi.master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('intilab_apps.special_leave_types as slt', 'leave_requests.special_leave_id', '=', 'slt.id')
            ->select(
                'leave_requests.id',
                'leave_requests.no_document',
                'leave_requests.type',
                'leave_requests.special_leave_id',
                'slt.name as special_leave_name',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.start_date as tanggal',
                'd.nama_divisi',
                DB::raw('CASE 
                        WHEN leave_requests.status = "Approved Atasan" THEN "Approve Atasan" 
                        WHEN leave_requests.status = "Rejected Atasan" THEN "Rejected Atasan" 
                        WHEN leave_requests.status = "Approved HRD" THEN "Approved HRD" 
                        WHEN leave_requests.status = "Rejected HRD" THEN "Rejected HRD" 
                        ELSE "Pending" 
                    END as status'),

                'karyawan.id as employee_id',
                'karyawan.nama_lengkap',
                'karyawan.grade as jabatan',

                'leave_requests.approved_atasan_by',
                'leave_requests.approved_atasan_at',
                'leave_requests.rejected_atasan_by',
                'leave_requests.rejected_atasan_at',
                'leave_requests.reject_atasan_reason',
                'leave_requests.approved_hrd_by',
                'leave_requests.approved_hrd_at',
                'leave_requests.rejected_hrd_by',
                'leave_requests.rejected_hrd_at',
                'leave_requests.reject_hrd_reason',

                'leave_requests.attachment',
                'leave_requests.created_by as nama_pengaju',
                'leave_requests.created_at as diajukan_pada',
                'leave_requests.description as keterangan'
            )
            // Filter hanya permohonan yang masih berjalan (belum final approve HRD / belum ditolak)
            ->whereNull('leave_requests.approved_hrd_by')
            ->whereNull('leave_requests.rejected_atasan_by')
            ->whereNull('leave_requests.rejected_hrd_by')
            ->whereYear('leave_requests.start_date', $request->periode ?? date('Y'));

        // Filter data berdasarkan diri sendiri dan bawahan (jika user_id tersedia)
        if (!empty($bawahanIds)) {
            $query->where(function ($q) use ($bawahanIds, $bawahanNames) {
                $q->whereIn('leave_requests.employee_id', $bawahanIds)
                  ->orWhereIn('leave_requests.created_by', $bawahanNames);
            });
        }

        $dt = Datatables::of($query);
        $dt = $this->applyDatatablesFilter($dt);

        return $dt->addColumn('can_approve', function ($row) {
                $isAtasan = ($this->grade === 'MANAGER');
                $isBukanDiriSendiri = ($row->employee_id != $this->user_id);
                $belumDiApprove = is_null($row->approved_atasan_by);

                return $isAtasan && $isBukanDiriSendiri && $belumDiApprove;
            })
            ->make(true);
    }

    /**
     * Alias jika frontend memanggil action `indexUnprocessed`
     */
    public function indexUnprocessed(Request $request)
    {
        return $this->indexByOwner($request);
    }

    /**
     * Data Permohonan Cuti yang Selesai / Ditolak (Processed)
     */
    public function indexByOwnerProcessed(Request $request)
    {
        $bawahan = GetBawahan::where('id', $this->user_id)->get();
        $bawahanIds = $bawahan->pluck('id')->toArray();
        $bawahanNames = $bawahan->pluck('nama_lengkap')->toArray();

        $query = LeaveRequest::on('intilab_apps')
            ->leftJoin('intilab_produksi.master_karyawan as karyawan', 'leave_requests.employee_id', '=', 'karyawan.id')
            ->leftJoin('intilab_produksi.master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('intilab_apps.special_leave_types as slt', 'leave_requests.special_leave_id', '=', 'slt.id')
            ->select(
                'leave_requests.id',
                'leave_requests.no_document',
                'leave_requests.type',
                'leave_requests.special_leave_id',
                'slt.name as special_leave_name',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.start_date as tanggal',
                'd.nama_divisi',
                DB::raw('CASE 
                        WHEN leave_requests.status = "Approved Atasan" THEN "Approve Atasan" 
                        WHEN leave_requests.status = "Rejected Atasan" THEN "Rejected Atasan" 
                        WHEN leave_requests.status = "Approved HRD" THEN "Approved HRD" 
                        WHEN leave_requests.status = "Rejected HRD" THEN "Rejected HRD" 
                        ELSE "Pending" 
                    END as status'),

                'karyawan.id as employee_id',
                'karyawan.nama_lengkap',
                'karyawan.grade as jabatan',

                'leave_requests.approved_atasan_by',
                'leave_requests.approved_atasan_at',
                'leave_requests.rejected_atasan_by',
                'leave_requests.rejected_atasan_at',
                'leave_requests.reject_atasan_reason',
                'leave_requests.approved_hrd_by',
                'leave_requests.approved_hrd_at',
                'leave_requests.rejected_hrd_by',
                'leave_requests.rejected_hrd_at',
                'leave_requests.reject_hrd_reason',

                'leave_requests.attachment',
                'leave_requests.created_by as nama_pengaju',
                'leave_requests.created_at as diajukan_pada',
                'leave_requests.description as keterangan'
            )
            // Filter yang sudah final (approved HRD atau ditolak)
            ->where(function ($q) {
                $q->whereNotNull('leave_requests.approved_hrd_by')
                  ->orWhereNotNull('leave_requests.rejected_atasan_by')
                  ->orWhereNotNull('leave_requests.rejected_hrd_by');
            })
            ->whereYear('leave_requests.start_date', $request->periode ?? date('Y'));

        if (!empty($bawahanIds)) {
            $query->where(function ($q) use ($bawahanIds, $bawahanNames) {
                $q->whereIn('leave_requests.employee_id', $bawahanIds)
                  ->orWhereIn('leave_requests.created_by', $bawahanNames);
            });
        }

        $dt = Datatables::of($query);
        $dt = $this->applyDatatablesFilter($dt);

        return $dt->make(true);
    }

    /**
     * Apply custom filter and order columns for Yajra DataTables
     */
    private function applyDatatablesFilter($datatables)
    {
        return $datatables
            ->filterColumn('nama_lengkap', function ($query, $keyword) {
                $query->where('karyawan.nama_lengkap', 'like', "%{$keyword}%");
            })
            ->filterColumn('type', function ($query, $keyword) {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('leave_requests.type', 'like', "%{$keyword}%")
                        ->orWhere('slt.name', 'like', "%{$keyword}%");
                    if (stripos('cuti tahunan', $keyword) !== false || stripos('annual leave', $keyword) !== false) {
                        $sub->orWhere('leave_requests.type', 'Annual Leave');
                    }
                    if (stripos('cuti khusus', $keyword) !== false || stripos('special leave', $keyword) !== false) {
                        $sub->orWhere('leave_requests.type', 'Special Leave');
                    }
                });
            })
            ->filterColumn('nama_divisi', function ($query, $keyword) {
                $query->where('d.nama_divisi', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_pengaju', function ($query, $keyword) {
                $query->where('leave_requests.created_by', 'like', "%{$keyword}%");
            })
            ->filterColumn('keterangan', function ($query, $keyword) {
                $query->where('leave_requests.description', 'like', "%{$keyword}%");
            })
            ->filterColumn('status', function ($query, $keyword) {
                $query->where('leave_requests.status', 'like', "%{$keyword}%");
            })
            ->filterColumn('start_date', function ($query, $keyword) {
                $query->where('leave_requests.start_date', 'like', "%{$keyword}%");
            })
            ->filterColumn('end_date', function ($query, $keyword) {
                $query->where('leave_requests.end_date', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal', function ($query, $keyword) {
                $query->where('leave_requests.start_date', 'like', "%{$keyword}%");
            })
            ->filterColumn('special_leave_name', function ($query, $keyword) {
                $query->where('slt.name', 'like', "%{$keyword}%");
            })
            ->orderColumn('nama_lengkap', function ($query, $order) {
                $query->orderBy('karyawan.nama_lengkap', $order);
            })
            ->orderColumn('nama_divisi', function ($query, $order) {
                $query->orderBy('d.nama_divisi', $order);
            })
            ->orderColumn('nama_pengaju', function ($query, $order) {
                $query->orderBy('leave_requests.created_by', $order);
            })
            ->orderColumn('keterangan', function ($query, $order) {
                $query->orderBy('leave_requests.description', $order);
            });
    }

    /**
     * Approve Permohonan Cuti oleh Atasan Langsung
     */
    public function approveAtasan(Request $request)
    {
        if ($this->grade !== 'MANAGER') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya level Manager yang berhak menyetujui permohonan cuti'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $leave = LeaveRequest::on('intilab_apps')->where('id', $request->id)->first();

            if (!$leave) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan cuti tidak ditemukan'
                ], 404);
            }

            $leave->status = 'Approved Atasan';
            $leave->approved_atasan_by = $this->karyawan;
            $leave->approved_atasan_at = Carbon::now()->format('Y-m-d H:i:s');
            $leave->save();

            $message = 'Permohonan cuti telah di-approve atasan';
            $userId = GetAtasan::where('nama_lengkap', $leave->created_by)->get()->pluck('id')->toArray();
            if (!empty($userId)) {
                Notification::whereIn('id', $userId)
                    ->title('Permohonan Cuti')
                    ->message($message . ' oleh ' . $this->karyawan)
                    ->url('/request/formulir/permohonan-cuti')
                    ->send();
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Permohonan cuti berhasil disetujui'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject Permohonan Cuti oleh Atasan Langsung
     */
    public function rejectAtasan(Request $request)
    {
        if ($this->grade !== 'MANAGER') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya level Manager yang berhak menolak permohonan cuti'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $leave = LeaveRequest::on('intilab_apps')->where('id', $request->id)->first();

            if (!$leave) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan cuti tidak ditemukan'
                ], 404);
            }

            $leave->status = 'Rejected Atasan';
            $leave->rejected_atasan_by = $this->karyawan;
            $leave->rejected_atasan_at = Carbon::now()->format('Y-m-d H:i:s');
            $leave->reject_atasan_reason = $request->keterangan;
            $leave->save();

            $message = 'Permohonan cuti telah ditolak atasan';
            $userId = GetAtasan::where('nama_lengkap', $leave->created_by)->get()->pluck('id')->toArray();
            if (!empty($userId)) {
                Notification::whereIn('id', $userId)
                    ->title('Permohonan Cuti')
                    ->message($message . ' oleh ' . $this->karyawan . '. Alasan: ' . ($request->keterangan ?? '-'))
                    ->url('/request/formulir/permohonan-cuti')
                    ->send();
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Permohonan cuti berhasil ditolak'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ambil daftar jenis cuti khusus
     */
    public function getSpecialLeaveTypes()
    {
        try {
            $types = DB::connection('intilab_apps')->table('special_leave_types')->where('is_active', 1)->get();
            return response()->json([
                'success' => true,
                'data' => $types
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buat Permohonan Cuti Baru
     */
    public function createCuti(Request $request)
    {
        DB::beginTransaction();
        try {
            $type = $request->type ?? $request->jenis;
            $specialLeaveId = null;

            if ($type === 'Annual Leave' || $type === 'annual') {
                $type = 'Annual Leave';
                $specialLeaveId = null;
            } elseif (is_string($type) && strpos($type, 'special_') === 0) {
                $specialLeaveId = (int) substr($type, 8);
                $type = 'Special Leave';
            } elseif ($request->has('special_leave_id') && !empty($request->special_leave_id)) {
                $specialLeaveId = $request->special_leave_id;
                $type = 'Special Leave';
            } else {
                $type = 'Annual Leave';
            }

            $startDate = $request->start_date ?? $request->tanggal_mulai;
            $endDate = $request->end_date ?? $request->tanggal_akhir ?? $request->tanggal_selesai ?? $startDate;
            $description = $request->description ?? $request->keterangan ?? '';

            if (!$startDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tanggal mulai cuti wajib diisi'
                ], 400);
            }

            $attachment = '';
            $file = $request->file('lampiran') ?: ($request->file('attachment') ?: $request->file('file'));
            if ($file) {
                $fileName = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                $dest = base_path('public/uploads/documents');
                if (!file_exists($dest)) {
                    mkdir($dest, 0777, true);
                }
                $file->move($dest, $fileName);
                $attachment = $fileName;
            }

            $noDocument = str_replace('.', '/', microtime(true));
            $status = ($this->grade === 'MANAGER' || $this->grade === 'DIREKTUR') ? 'Approved Atasan' : 'Pending';

            $leave = LeaveRequest::on('intilab_apps')->create([
                'employee_id' => $this->user_id,
                'no_document' => $noDocument,
                'type' => $type,
                'special_leave_id' => $specialLeaveId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'description' => $description,
                'attachment' => $attachment,
                'status' => $status,
                'approved_atasan_by' => ($status === 'Approved Atasan') ? $this->karyawan : null,
                'approved_atasan_at' => ($status === 'Approved Atasan') ? Carbon::now()->format('Y-m-d H:i:s') : null,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'is_active' => 1,
            ]);

            // Kirim notifikasi ke atasan jika ada
            $atasan = GetAtasan::where('id', $this->user_id)->get();
            $sendNotifTo = $atasan->pluck('id')->toArray();
            if (!empty($sendNotifTo)) {
                Notification::whereIn('id', $sendNotifTo)
                    ->title('Permohonan Cuti Baru!')
                    ->message('Permohonan cuti baru diajukan oleh ' . $this->karyawan)
                    ->url('/request/formulir/permohonan-cuti')
                    ->send();
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Permohonan cuti berhasil dibuat',
                'data' => $leave
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
                'error' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Permohonan Cuti
     */
    public function updateCuti(Request $request)
    {
        DB::beginTransaction();
        try {
            $leave = LeaveRequest::on('intilab_apps')->where('id', $request->id)->first();

            if (!$leave) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan cuti tidak ditemukan'
                ], 404);
            }

            if ($leave->approved_hrd_by || $leave->rejected_atasan_by || $leave->rejected_hrd_by) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan cuti yang sudah selesai/diproses tidak dapat diubah'
                ], 400);
            }

            $type = $request->type ?? $request->jenis ?? $leave->type;
            $specialLeaveId = $leave->special_leave_id;

            if ($type === 'Annual Leave' || $type === 'annual') {
                $type = 'Annual Leave';
                $specialLeaveId = null;
            } elseif (is_string($type) && strpos($type, 'special_') === 0) {
                $specialLeaveId = (int) substr($type, 8);
                $type = 'Special Leave';
            } elseif ($request->has('special_leave_id')) {
                $specialLeaveId = $request->special_leave_id;
                $type = 'Special Leave';
            }

            $startDate = $request->start_date ?? $request->tanggal_mulai ?? $leave->start_date;
            $endDate = $request->end_date ?? $request->tanggal_akhir ?? $request->tanggal_selesai ?? $startDate;
            $description = $request->description ?? $request->keterangan ?? $leave->description;

            $attachment = $leave->attachment;
            $file = $request->file('lampiran') ?: ($request->file('attachment') ?: $request->file('file'));
            if ($file) {
                $fileName = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                $dest = base_path('public/uploads/documents');
                if (!file_exists($dest)) {
                    mkdir($dest, 0777, true);
                }
                $file->move($dest, $fileName);
                $attachment = $fileName;
            }

            $leave->update([
                'type' => $type,
                'special_leave_id' => $specialLeaveId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'description' => $description,
                'attachment' => $attachment ?? '',
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Permohonan cuti berhasil diperbarui'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
                'error' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}