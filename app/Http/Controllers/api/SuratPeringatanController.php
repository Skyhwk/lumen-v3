<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\SuratPeringatanDisipliner;
use App\Services\SuratPeringatanDocumentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class SuratPeringatanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = $this->baseQuery();

            $periode = (int) $request->input('periode', date('Y'));
            if ($periode >= 2000) {
                $query->whereYear('spd.tanggal_terbit', $periode);
            }

            $jenis = $this->resolveJenisPeringatan($request);
            if ($jenis !== '') {
                $query->where('spd.jenis_peringatan', $jenis);
            }

            if ($request->filled('status')) {
                $query->where('spd.status', strtolower(trim((string) $request->status)));
            }

            $data = $query->get()->map(fn ($row) => $this->decorate($row));

            return Datatables::of($data)->make(true);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat peringatan tidak ditemukan.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->decorate($record),
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    public function employeeHistory(Request $request)
    {
        $this->validate($request, [
            'karyawan_id' => 'required|integer',
        ]);

        $karyawanId = (int) $request->karyawan_id;

        $items = $this->baseQuery()
            ->where('spd.karyawan_id', $karyawanId)
            ->orderByDesc('spd.tanggal_terbit')
            ->orderByDesc('spd.id')
            ->get()
            ->map(fn ($row) => $this->decorate($row));

        return response()->json([
            'success' => true,
            'data' => $items,
            'suggested_type' => app(SuratPeringatanDocumentService::class)->suggestJenisPeringatan($karyawanId),
        ], 200);
    }

    public function suggestType(Request $request)
    {
        $this->validate($request, [
            'karyawan_id' => 'required|integer',
        ]);

        $karyawanId = (int) $request->karyawan_id;
        $service = app(SuratPeringatanDocumentService::class);
        $suggested = $service->suggestJenisPeringatan($karyawanId);

        return response()->json([
            'success' => true,
            'data' => [
                'suggested_type' => $suggested,
                'id_surat_sebelumnya' => $service->resolveIdSuratSebelumnya($karyawanId, $suggested),
            ],
        ], 200);
    }

    public function store(Request $request, SuratPeringatanDocumentService $documentService)
    {
        DB::beginTransaction();

        try {
            $validation = $this->validatePayload($request);
            if ($validation !== true) {
                DB::rollBack();
                return $validation;
            }

            $karyawanId = (int) $request->karyawan_id;
            if (!$this->employeeExists($karyawanId)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan.',
                ], 404);
            }

            $jenisPeringatan = $this->resolveJenisPeringatan($request);
            $documentService->assertCanIssue($jenisPeringatan, $karyawanId);

            $tanggalTerbit = Carbon::parse($this->resolveTanggalTerbit($request))->format('Y-m-d');
            $sequence = $documentService->allocateDocumentNumber(
                $jenisPeringatan,
                (int) date('Y', strtotime($tanggalTerbit))
            );

            $idSuratSebelumnya = $request->filled('id_surat_sebelumnya')
                ? (int) $request->id_surat_sebelumnya
                : $documentService->resolveIdSuratSebelumnya($karyawanId, $jenisPeringatan);

            $now = Carbon::now();

            $letter = SuratPeringatanDisipliner::create([
                'karyawan_id' => $karyawanId,
                'jenis_peringatan' => $jenisPeringatan,
                'nomor_surat' => $sequence['nomor_surat'],
                'tahun_nomor' => $sequence['tahun'],
                'urutan_nomor' => $sequence['urutan'],
                'tanggal_terbit' => $tanggalTerbit,
                'tanggal_kejadian' => $request->filled('tanggal_kejadian')
                    ? Carbon::parse($request->tanggal_kejadian)->format('Y-m-d')
                    : null,
                'jenis_pelanggaran' => trim((string) ($request->jenis_pelanggaran ?? $request->violation_category ?? '')) ?: null,
                'uraian_pelanggaran' => trim((string) $this->resolveUraianPelanggaran($request)) ?: null,
                'id_surat_sebelumnya' => $idSuratSebelumnya,
                'status' => SuratPeringatanDisipliner::STATUS_DRAFT,
                'created_by' => $this->karyawan,
                'updated_by' => $this->karyawan,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $detail = $this->findActiveRecord($letter->id);
            $letter->load('suratSebelumnya');
            $documentService->generateAndStorePdf($letter, $detail);

            $letter->status = SuratPeringatanDisipliner::STATUS_ISSUED;
            $letter->published_by = $this->karyawan;
            $letter->updated_by = $this->karyawan;
            $letter->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Surat peringatan berhasil diterbitkan.',
                'data' => $this->decorate($this->findActiveRecord($letter->id)),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function destroy(Request $request)
    {
        DB::beginTransaction();

        try {
            $record = SuratPeringatanDisipliner::query()
                ->where('id', $this->resolveId($request))
                ->where('is_active', true)
                ->first();

            if ($record === null) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Surat peringatan tidak ditemukan.',
                ], 404);
            }

            $now = Carbon::now();
            $record->update([
                'is_active' => false,
                'status' => SuratPeringatanDisipliner::STATUS_CANCELLED,
                'updated_by' => $this->karyawan,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Surat peringatan berhasil dihapus.',
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    public function printPdf(Request $request, SuratPeringatanDocumentService $documentService)
    {
        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat peringatan tidak ditemukan.',
                ], 404);
            }

            $letter = SuratPeringatanDisipliner::with('suratSebelumnya')->find($record->id);

            try {
                $pdfBinary = $documentService->readPdfBinary($letter);
            } catch (\Throwable $e) {
                $pdfBinary = $documentService->renderPdf($letter, $record);
                $documentService->generateAndStorePdf($letter, $record);
            }

            return response()->json([
                'success' => true,
                'message' => 'PDF surat peringatan siap.',
                'data' => base64_encode($pdfBinary),
                'pdf_ready' => true,
                'file_name' => $letter->nama_berkas ?: ($letter->nomor_surat . '.pdf'),
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    private function baseQuery()
    {
        return SuratPeringatanDisipliner::query()
            ->from('surat_peringatan_disipliner as spd')
            ->leftJoin('master_karyawan as mk', 'spd.karyawan_id', '=', 'mk.id')
            ->leftJoin('master_divisi as md', 'mk.id_department', '=', 'md.id')
            ->where('spd.is_active', true)
            ->select(
                'spd.id',
                'spd.karyawan_id',
                'spd.jenis_peringatan',
                'spd.nomor_surat',
                'spd.tanggal_terbit',
                'spd.tanggal_kejadian',
                'spd.jenis_pelanggaran',
                'spd.uraian_pelanggaran',
                'spd.id_surat_sebelumnya',
                'spd.status',
                'spd.nama_berkas',
                'spd.file_path',
                'spd.file_size',
                'spd.created_by',
                'spd.updated_by',
                'spd.published_by',
                'spd.created_at',
                'spd.updated_at',
                'mk.nik_karyawan',
                'mk.nama_lengkap',
                'mk.department',
                'mk.jabatan',
                'md.nama_divisi'
            );
    }

    private function findActiveRecord($id)
    {
        if ((int) $id <= 0) {
            return null;
        }

        return $this->baseQuery()->where('spd.id', (int) $id)->first();
    }

    private function decorate($item)
    {
        if ($item === null) {
            return null;
        }

        $item->nama_karyawan = $item->nama_lengkap ?: '-';
        $item->divisi = $item->nama_divisi ?: ($item->department ?: '-');
        $item->status_label = $this->mapStatusLabel($item->status);
        $item->tanggal_terbit_label = $item->tanggal_terbit
            ? Carbon::parse($item->tanggal_terbit)->format('d-m-Y')
            : '-';
        $item->tanggal_kejadian_label = $item->tanggal_kejadian
            ? Carbon::parse($item->tanggal_kejadian)->format('d-m-Y')
            : '-';
        $item->has_document = !empty($item->file_path);
        $item->jenis_peringatan_label = $this->mapJenisPeringatanLabel($item->jenis_peringatan);
        $item->dibuat_oleh_label = trim((string) ($item->created_by ?? '')) ?: '-';
        $item->diubah_oleh_label = trim((string) ($item->updated_by ?? '')) ?: '-';
        $item->diterbitkan_oleh_label = trim((string) ($item->published_by ?? '')) ?: '-';
        $item->created_at_label = $item->created_at
            ? Carbon::parse($item->created_at)->format('d-m-Y H:i')
            : '-';
        $item->updated_at_label = $item->updated_at
            ? Carbon::parse($item->updated_at)->format('d-m-Y H:i')
            : '-';

        return $item;
    }

    private function mapJenisPeringatanLabel(?string $type): string
    {
        $map = [
            SuratPeringatanDisipliner::TYPE_SP1 => 'Surat Peringatan 1 (SP1)',
            SuratPeringatanDisipliner::TYPE_SP2 => 'Surat Peringatan 2 (SP2)',
            SuratPeringatanDisipliner::TYPE_SP3 => 'Surat Peringatan 3 (SP3)',
        ];

        return $map[strtoupper(trim((string) $type))] ?? (string) $type;
    }

    private function mapStatusLabel(?string $status): string
    {
        $map = [
            SuratPeringatanDisipliner::STATUS_DRAFT => 'Draf',
            SuratPeringatanDisipliner::STATUS_ISSUED => 'Terbit',
            SuratPeringatanDisipliner::STATUS_CANCELLED => 'Dibatalkan',
        ];

        return $map[$status] ?? (string) $status;
    }

    private function validatePayload(Request $request)
    {
        $this->validate($request, [
            'karyawan_id' => 'required|integer',
            'issue_date' => 'nullable|date',
            'tanggal_terbit' => 'nullable|date',
            'tanggal_kejadian' => 'nullable|date',
            'incident_date' => 'nullable|date',
            'jenis_pelanggaran' => 'nullable|string|max:150',
            'violation_category' => 'nullable|string|max:150',
            'uraian_pelanggaran' => 'nullable|string',
            'violation_description' => 'nullable|string',
        ]);

        $jenis = $this->resolveJenisPeringatan($request);
        if (!in_array($jenis, [
            SuratPeringatanDisipliner::TYPE_SP1,
            SuratPeringatanDisipliner::TYPE_SP2,
            SuratPeringatanDisipliner::TYPE_SP3,
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis peringatan wajib dipilih (SP1, SP2, atau SP3).',
            ], 422);
        }

        $plainDescription = trim(strip_tags((string) $this->resolveUraianPelanggaran($request)));
        if ($plainDescription === '') {
            return response()->json([
                'success' => false,
                'message' => 'Uraian pelanggaran wajib diisi.',
            ], 422);
        }

        if ($this->resolveTanggalTerbit($request) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal terbit wajib diisi.',
            ], 422);
        }

        return true;
    }

    private function resolveJenisPeringatan(Request $request): string
    {
        $value = $request->jenis_peringatan ?? $request->warning_type ?? '';

        return strtoupper(trim((string) $value));
    }

    private function resolveTanggalTerbit(Request $request): string
    {
        return trim((string) ($request->tanggal_terbit ?? $request->issue_date ?? ''));
    }

    private function resolveUraianPelanggaran(Request $request): string
    {
        return (string) ($request->uraian_pelanggaran ?? $request->violation_description ?? '');
    }

    private function employeeExists(int $karyawanId): bool
    {
        return MasterKaryawan::query()
            ->where('id', $karyawanId)
            ->where('is_active', true)
            ->exists();
    }

    private function resolveId(Request $request): int
    {
        return (int) ($request->input('id') ?: $request->input('letter_id') ?: 0);
    }
}
