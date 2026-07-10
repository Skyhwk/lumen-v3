<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Services\Notification;
use Yajra\Datatables\Datatables;
use Mpdf;

class ChangeRequestController extends Controller
{
    /**
     * Get data for Datatables.
     */
    public function index(Request $request)
    {
        $query = ChangeRequest::query()->where('is_active', true);

        // Apply filters if present
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('jenis_permintaan')) {
            $query->where('jenis_permintaan', $request->input('jenis_permintaan'));
        }
        if ($request->filled('prioritas')) {
            $query->where('prioritas', $request->input('prioritas'));
        }

        $query->orderBy('id', 'desc');

        return Datatables::of($query)->make(true);
    }

    /**
     * Store new Change Request.
     */
    public function store(Request $request)
    {
        // 1. Enforce access limit (Supervisor / Manager / Direktur / IT Dept)
        $grade = strtoupper($this->grade ?? '');
        $deptId = (int) ($this->department ?? 0);
        $allowedGrades = ['SUPERVISOR', 'MANAGER', 'DIREKTUR', 'ADMINISTRATOR'];

        if (!in_array($grade, $allowedGrades) && $deptId !== 7) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya level Supervisor, Manager, Direktur, atau Departemen IT yang diperbolehkan mengajukan Change Request.',
                'status' => 403
            ], 403);
        }

        // Validate input
        $this->validate($request, [
            'judul' => 'required|string|max:255',
            'jenis_permintaan' => 'required|string|max:50',
            'aplikasi' => 'required|string|max:100',
            'prioritas' => 'required|string|max:20',
        ]);

        try {
            DB::beginTransaction();

            // Fetch user's division
            $karyawan = MasterKaryawan::with('divisi')->find($this->user_id);
            $divisi = $karyawan && $karyawan->divisi ? $karyawan->divisi->nama_divisi : 'Lainnya';

            // Generate automatic document number CR-YYYY-XXX
            $year = date('Y');
            $latest = ChangeRequest::whereYear('tanggal_permintaan', $year)
                ->orderBy('nomor_dokumen', 'desc')
                ->first();

            $count = 1;
            if ($latest) {
                $parts = explode('-', $latest->nomor_dokumen);
                if (count($parts) === 3) {
                    $count = (int) $parts[2] + 1;
                }
            }
            $nomor_dokumen = 'CR-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

            // Handle file upload
            $lampiranFilename = null;
            if ($request->hasFile('lampiran')) {
                $file = $request->file('lampiran');
                $ext = strtolower($file->getClientOriginalExtension());
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'xlsx', 'xls', 'pdf', 'docx', 'doc'];

                if (!in_array($ext, $allowedExts)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Format file lampiran tidak diizinkan. Hanya gambar, excel, pdf, atau doc.',
                        'status' => 422
                    ], 422);
                }

                if ($file->getSize() > 5242880) { // 5MB
                    return response()->json([
                        'success' => false,
                        'message' => 'Ukuran file lampiran maksimal 5MB.',
                        'status' => 422
                    ], 422);
                }

                $destDir = public_path('change_requests');
                if (!File::isDirectory($destDir)) {
                    File::makeDirectory($destDir, 0777, true, true);
                }

                $lampiranFilename = $nomor_dokumen . '_' . time() . '.' . $ext;
                $file->move($destDir, $lampiranFilename);
            }

            // Save Change Request
            $cr = ChangeRequest::create([
                'nomor_dokumen' => $nomor_dokumen,
                'tanggal_permintaan' => date('Y-m-d'),
                'pemohon' => $this->karyawan ?? 'System User',
                'divisi' => $divisi,
                'aplikasi' => $request->input('aplikasi'),
                'jenis_permintaan' => $request->input('jenis_permintaan'),
                'judul' => $request->input('judul'),
                'latar_belakang' => $request->input('latar_belakang'),
                'kondisi_saat_ini' => $request->input('kondisi_saat_ini'),
                'kondisi_yang_diinginkan' => $request->input('kondisi_yang_diinginkan'),
                'dampak' => $request->input('dampak') ? json_decode($request->input('dampak'), true) : [],
                'prioritas' => $request->input('prioritas'),
                'lampiran' => $lampiranFilename,
                'status' => 'OPEN',
                'created_by' => $this->karyawan,
                'updated_by' => $this->karyawan,
            ]);

            // Kirim notifikasi ke pemohon
            try {
                Notification::where('id', $this->user_id)
                    ->title('Change Request Diajukan')
                    ->message('Change Request Anda dengan nomor ' . $nomor_dokumen . ' berhasil dibuat.')
                    ->url('/request/change-request')
                    ->send();
            } catch (\Exception $e) {
                \Log::error('Gagal mengirim notifikasi CR ke pemohon: ' . $e->getMessage());
            }

            // Kirim notifikasi ke divisi IT (department_id = 7)
            try {
                Notification::where('id_department', 7)
                    ->title('Change Request Baru')
                    ->message('Terdapat pengajuan Change Request baru (' . $nomor_dokumen . ') dari divisi ' . $divisi . '.')
                    ->url('/request/change-request')
                    ->send();
            } catch (\Exception $e) {
                \Log::error('Gagal mengirim notifikasi CR ke divisi IT: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Change Request dengan nomor ' . $nomor_dokumen . ' berhasil dibuat.',
                'data' => $cr,
                'status' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat Change Request: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    /**
     * Submit IT Technical Analysis.
     */
    public function updateITAnalysis(Request $request)
    {
        $deptId = (int) ($this->department ?? 0);
        if ($deptId !== 7) { // 7 is IT Department
            return response()->json([
                'success' => false,
                'message' => 'Hanya anggota Departemen IT yang diperbolehkan mengisi Analisa IT.',
                'status' => 403
            ], 403);
        }

        $this->validate($request, [
            'id' => 'required|integer',
            'analisa_it' => 'required|string',
            'tingkat_kesulitan' => 'required|string',
            'estimasi_pengerjaan' => 'required|string',
            'risiko' => 'required|string',
            'developer_pic' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $cr = ChangeRequest::find($request->input('id'));
            if (!$cr) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Change Request tidak ditemukan.',
                    'status' => 404
                ], 404);
            }

            $cr->update([
                'analisa_it' => $request->input('analisa_it'),
                'tingkat_kesulitan' => $request->input('tingkat_kesulitan'),
                'estimasi_pengerjaan' => $request->input('estimasi_pengerjaan'),
                'risiko' => $request->input('risiko'),
                'developer_pic' => $request->input('developer_pic'),
                'disetujui_it_by' => $this->karyawan,
                'disetujui_it_at' => Carbon::now(),
                'tanggal_development' => date('Y-m-d'),
                'status' => 'DEVELOPMENT',
                'updated_by' => $this->karyawan,
            ]);

            // Kirim notifikasi ke Developer PIC yang ditugaskan
            $developerPicName = $request->input('developer_pic');
            $developerId = DB::table('master_karyawan')->where('nama_lengkap', $developerPicName)->value('id');
            if ($developerId) {
                try {
                    Notification::where('id', $developerId)
                        ->title('Penugasan Developer PIC')
                        ->message('Anda ditugaskan sebagai Developer PIC untuk Change Request nomor ' . $cr->nomor_dokumen . '.')
                        ->url('/request/change-request')
                        ->send();
                } catch (\Exception $e) {
                    \Log::error('Gagal mengirim notifikasi tugas ke developer: ' . $e->getMessage());
                }
            }

            // Kirim notifikasi ke pemohon
            $pemohonId = DB::table('master_karyawan')->where('nama_lengkap', $cr->pemohon)->value('id');
            if ($pemohonId) {
                try {
                    Notification::where('id', $pemohonId)
                        ->title('Change Request Mulai Dikerjakan')
                        ->message('Change Request Anda (' . $cr->nomor_dokumen . ') telah disetujui IT dan sedang dikerjakan oleh ' . $developerPicName . '.')
                        ->url('/request/change-request')
                        ->send();
                } catch (\Exception $e) {
                    \Log::error('Gagal mengirim notifikasi development ke pemohon: ' . $e->getMessage());
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Analisa IT berhasil disimpan. Status berubah menjadi DEVELOPMENT.',
                'data' => $cr,
                'status' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate Analisa IT: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    /**
     * Transition status (Testing / UAT Approval / Reject).
     */
    public function updateStatus(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|integer',
            'action' => 'required|string', // TESTING, APPROVE_UAT, REJECT
        ]);

        try {
            DB::beginTransaction();

            $cr = ChangeRequest::find($request->input('id'));
            if (!$cr) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Change Request tidak ditemukan.',
                    'status' => 404
                ], 404);
            }

            $action = strtoupper($request->input('action'));
            $deptId = (int) ($this->department ?? 0);

            if ($action === 'TESTING') {
                if ($deptId !== 7) {
                    return response()->json(['success' => false, 'message' => 'Hanya Departemen IT yang bisa memindahkan status ke TESTING.', 'status' => 403], 403);
                }
                $cr->update([
                    'status' => 'TESTING',
                    'tanggal_testing' => date('Y-m-d'),
                    'updated_by' => $this->karyawan,
                ]);

                // Kirim notifikasi ke pemohon
                $pemohonId = DB::table('master_karyawan')->where('nama_lengkap', $cr->pemohon)->value('id');
                if ($pemohonId) {
                    try {
                        Notification::where('id', $pemohonId)
                            ->title('UAT Change Request Ready')
                            ->message('Change Request Anda (' . $cr->nomor_dokumen . ') telah selesai dikembangkan dan siap untuk ditest (UAT).')
                            ->url('/request/change-request')
                            ->send();
                    } catch (\Exception $e) {
                        \Log::error('Gagal mengirim notifikasi testing ke pemohon: ' . $e->getMessage());
                    }
                }

                $message = 'Status berhasil diubah ke TESTING.';

            } elseif ($action === 'APPROVE_UAT') {
                // Only the applicant (pemohon) can approve UAT
                if ($this->karyawan !== $cr->pemohon) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Hanya pemohon (' . $cr->pemohon . ') yang berhak menyetujui UAT / rilis.',
                        'status' => 403
                    ], 403);
                }
                $cr->update([
                    'status' => 'DONE',
                    'disetujui_user_by' => $this->karyawan,
                    'disetujui_user_at' => Carbon::now(),
                    'tanggal_release' => date('Y-m-d'),
                    'pic_release' => $this->karyawan,
                    'updated_by' => $this->karyawan,
                ]);

                // Kirim notifikasi ke divisi IT (department_id = 7)
                try {
                    Notification::where('id_department', 7)
                        ->title('UAT Change Request Disetujui')
                        ->message('UAT untuk Change Request ' . $cr->nomor_dokumen . ' telah disetujui oleh pemohon. Status: DONE.')
                        ->url('/request/change-request')
                        ->send();
                } catch (\Exception $e) {
                    \Log::error('Gagal mengirim notifikasi UAT selesai ke IT: ' . $e->getMessage());
                }

                $message = 'User Acceptance Test (UAT) disetujui. Status berubah menjadi DONE (RELEASED).';

            } elseif ($action === 'REJECT') {
                $cr->update([
                    'status' => 'REJECT',
                    'updated_by' => $this->karyawan,
                ]);

                // Kirim notifikasi ke pemohon bahwa CR ditolak
                $pemohonId = DB::table('master_karyawan')->where('nama_lengkap', $cr->pemohon)->value('id');
                if ($pemohonId) {
                    try {
                        Notification::where('id', $pemohonId)
                            ->title('Change Request Ditolak')
                            ->message('Change Request Anda dengan nomor ' . $cr->nomor_dokumen . ' ditolak.')
                            ->url('/request/change-request')
                            ->send();
                    } catch (\Exception $e) {
                        \Log::error('Gagal mengirim notifikasi reject ke pemohon: ' . $e->getMessage());
                    }
                }

                $message = 'Change Request berhasil ditolak (REJECT).';

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Aksi status tidak valid.',
                    'status' => 400
                ], 400);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $cr,
                'status' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal merubah status: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get list of IT employees.
     */
    public function getItEmployees()
    {
        $employees = DB::table('master_karyawan')
            ->where('id_department', 7) // IT Department
            ->where('is_active', true)
            ->whereRaw('LOWER(grade) != ?', ['manager'])
            ->select('id', 'nama_lengkap')
            ->orderBy('nama_lengkap', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $employees,
            'status' => 200
        ]);
    }

    /**
     * Export Change Request to PDF.
     */
    public function exportPdf(Request $request)
    {
        $id = $request->input('id');
        $cr = ChangeRequest::find($id);
        if (!$cr) {
            return response()->json([
                'success' => false,
                'message' => 'Data Change Request tidak ditemukan.',
                'status' => 404
            ], 404);
        }

        try {
            // Generate PDF using Mpdf
            $mpdf = new Mpdf([
                'format' => 'A4',
                'orientation' => 'P',
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_left' => 15,
                'margin_right' => 15,
            ]);

            $mpdf->setDisplayMode('fullpage');
            $mpdf->SetTitle('Change Request - ' . $cr->nomor_dokumen);
            $mpdf->SetAuthor('PT Inti Surya Laboratorium');

            $html = view('change_request_pdf', ['data' => $cr])->render();

            $mpdf->WriteHTML($html);

            $fileName = 'Change_Request_' . str_replace('/', '_', $cr->nomor_dokumen) . '.pdf';
            
            // Output PDF as inline stream to browser
            return response($mpdf->Output($fileName, 'S'))
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $fileName . '"');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat PDF: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }
}
