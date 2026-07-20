<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LimsDocument;
use App\Models\LimsDocumentApproval;
use App\Services\LimsDocumentWorkflowService;
use App\Services\RenderLimsPsDocumentPdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Repository;
use Yajra\DataTables\DataTables;

Carbon::setLocale('id');

class LimsPsDocumentController extends Controller
{
    private const REPOSITORY_DIR = 'akreditasi';
    private const STATUS_IN_REVIEW = 'in_review';
    private const STATUS_LEGALIZED = 'legalized';

    private LimsDocumentWorkflowService $workflow;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->workflow = app(LimsDocumentWorkflowService::class);
    }

    public function initialize(Request $request)
    {
        $karyawan = $request->attributes->get('user')->karyawan ?? null;

        return response()->json([
            'data' => $this->workflow->getInitializeData($karyawan, $this->karyawan),
        ], 200);
    }

    public function getPengesahanDefaults(Request $request)
    {
        return response()->json([
            'data' => $this->workflow->getPengesahanDefaults(),
        ], 200);
    }

    public function getApprovalDefaults(Request $request)
    {
        $karyawan = $request->attributes->get('user')->karyawan ?? null;

        return response()->json([
            'data' => $this->workflow->getApprovalDefaults($karyawan, $this->karyawan),
        ], 200);
    }

    public function getVerificationDefaults(Request $request)
    {
        $karyawan = $request->attributes->get('user')->karyawan ?? null;
        $document = null;

        if ($request->filled('id') && $request->filled('menu_slug')) {
            $document = $this->findActiveDocument($request->id, $request->menu_slug);
        }

        return response()->json([
            'data' => $this->workflow->getVerificationDefaults($karyawan, $this->karyawan, $document),
        ], 200);
    }

    public function index(Request $request)
    {
        $menuSlug = $request->menu_slug;
        $scope = $request->input('scope', 'active');

        if (!$menuSlug) {
            return response()->json(['message' => 'menu_slug wajib diisi'], 422);
        }

        $query = LimsDocument::with('approvals')
            ->where('menu_slug', $menuSlug)
            ->where('is_active', true);

        if ($scope === 'archive') {
            $query->where('status', self::STATUS_LEGALIZED);
        } else {
            $query->where('status', '!=', self::STATUS_LEGALIZED);
        }

        $data = $query->orderBy('created_at', 'desc')->get();

        return DataTables::of($data)
            ->addColumn('judul_dokumen', fn($row) => $row->nama_dokumen)
            ->addColumn('sub_header', fn($row) => $row->sub_header_dokumen)
            ->addColumn('revisi', fn($row) => $row->revisian)
            ->addColumn('status_label', fn($row) => $this->resolveStatusLabel($row))
            ->addColumn('can_update', fn($row) => $this->workflow->canUserUpdate($row))
            ->addColumn('can_verify', fn($row) => $this->workflow->canUserVerify($row))
            ->addColumn('can_approve', function ($row) use ($request) {
                $karyawan = $request->attributes->get('user')->karyawan ?? null;

                return $this->workflow->canUserApprove($row, $karyawan, $this->karyawan);
            })
            ->addColumn('can_legalize', fn($row) => $this->workflow->canUserLegalize($row))
            ->addColumn('can_delete', fn($row) => $this->workflow->canUserDelete($row))
            ->addColumn('disahkan_oleh', fn($row) => $row->pengesahan)
            ->make(true);
    }

    public function getDocument(Request $request)
    {
        $document = $this->findActiveDocument($request->id, $request->menu_slug);

        if (!$document) {
            return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
        }

        $document->load('approvals');
        $document->content = $this->normalizeDocumentContent(
            $this->getContent($document->content_file)
        );
        $document->judul_dokumen = $document->nama_dokumen;
        $document->revisi = $document->revisian;

        return response()->json($document, 200);
    }

    public function saveDocument(Request $request)
    {
        $required = ['menu_slug', 'no_dokumen', 'header_dokumen', 'sub_header_dokumen', 'tanggal_cetak', 'disusun_oleh', 'jabatan_penyusun'];

        foreach ($required as $field) {
            if (!$request->filled($field)) {
                return response()->json(['message' => ucfirst(str_replace('_', ' ', $field)) . ' wajib diisi'], 422);
            }
        }

        DB::beginTransaction();

        try {
            if ($request->id) {
                $document = LimsDocument::where('id', $request->id)
                    ->where('menu_slug', $request->menu_slug)
                    ->where('is_active', true)
                    ->first();

                if (!$document) {
                    return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
                }

                $document->load('approvals');

                $oldContentFile = $document->content_file;

                if ($request->filled('content')) {
                    $contentFile = $this->generateContentKey($request->menu_slug);
                    $normalizedContent = $this->normalizeDocumentContent($request->content);
                    Repository::dir(self::REPOSITORY_DIR)->key($contentFile)->save($normalizedContent);
                    $document->content_file = $contentFile;
                    $this->deleteContentFile($oldContentFile);
                }

                $this->fillDocumentFields($document, $request);
                $document->updated_by = $this->karyawan;
                $document->updated_at = Carbon::now();
                $document->save();

                DB::commit();

                return response()->json(['message' => 'Dokumen berhasil diperbarui'], 200);
            }

            if (!$request->filled('content')) {
                return response()->json(['message' => 'Isi dokumen wajib diisi'], 422);
            }

            $contentFile = $this->generateContentKey($request->menu_slug);
            $normalizedContent = $this->normalizeDocumentContent($request->content);
            Repository::dir(self::REPOSITORY_DIR)->key($contentFile)->save($normalizedContent);

            $document = new LimsDocument();
            $this->fillDocumentFields($document, $request);
            $document->menu_slug = $request->menu_slug;
            $document->content_file = $contentFile;
            $document->status = self::STATUS_IN_REVIEW;
            $document->is_active = true;
            $document->created_by = $this->karyawan;
            $document->created_at = Carbon::now();
            $document->save();

            DB::commit();

            return response()->json(['message' => 'Dokumen berhasil disimpan'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function previewPdf(Request $request)
    {
        $document = $this->findActiveDocument($request->id, $request->menu_slug);

        if (!$document) {
            return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
        }

        $document->load('approvals');
        $content = $this->normalizeDocumentContent($this->getContent($document->content_file)) ?? '';

        $pdfString = app(RenderLimsPsDocumentPdf::class)->render($document, $content);

        return response()->json([
            'data' => base64_encode($pdfString),
            'message' => 'PDF berhasil dibuat',
        ], 200);
    }

    public function processApproval(Request $request)
    {
        $action = $request->input('action');
        $document = $this->findActiveDocument($request->id, $request->menu_slug);

        if (!$document) {
            return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
        }

        if ($document->status === self::STATUS_LEGALIZED) {
            return response()->json(['message' => 'Dokumen sudah disahkan'], 422);
        }

        $karyawan = $request->attributes->get('user')->karyawan ?? null;

        if ($action === 'verify') {
            if ($this->workflow->hasVerification($document)) {
                return response()->json(['message' => 'Dokumen sudah diverifikasi'], 422);
            }

            foreach (['nama_pengesahan', 'jabatan_pengesah', 'tanggal_pengesahan'] as $field) {
                if (!$request->filled($field)) {
                    return response()->json(['message' => ucfirst(str_replace('_', ' ', $field)) . ' wajib diisi'], 422);
                }
            }

            if ($this->workflow->isSameAsComposer($document, $request->nama_pengesahan)) {
                return response()->json(['message' => 'Verifikator tidak boleh sama dengan penyusun dokumen'], 422);
            }

            DB::beginTransaction();

            try {
                $tanggalVerifikasi = Carbon::parse($request->tanggal_pengesahan);

                LimsDocumentApproval::create([
                    'lims_document_id' => $document->id,
                    'action' => 'verify',
                    'nama' => $request->nama_pengesahan,
                    'jabatan' => $request->jabatan_pengesah,
                    'approved_at' => $tanggalVerifikasi,
                    'approved_by' => $this->karyawan,
                    'step' => 0,
                    'is_active' => true,
                ]);

                DB::commit();

                return response()->json(['message' => 'Dokumen berhasil diverifikasi'], 200);
            } catch (\Exception $e) {
                DB::rollBack();

                return response()->json(['message' => $e->getMessage()], 500);
            }
        }

        if ($action === 'approve') {
            if (!$this->workflow->isManager($karyawan)) {
                return response()->json(['message' => 'Hanya manager yang dapat menyetujui dokumen'], 403);
            }

            if (
                $this->workflow->isSelfApproval($request->nama_pengesahan, $request->jabatan_pengesah, $karyawan, $this->karyawan)
                && $this->workflow->hasUserSelfApproved($document, $karyawan, $this->karyawan)
            ) {
                return response()->json(['message' => 'Anda sudah menyetujui atas nama dan jabatan Anda sendiri'], 422);
            }

            foreach (['nama_pengesahan', 'jabatan_pengesah', 'tanggal_pengesahan'] as $field) {
                if (!$request->filled($field)) {
                    return response()->json(['message' => ucfirst(str_replace('_', ' ', $field)) . ' wajib diisi'], 422);
                }
            }

            DB::beginTransaction();

            try {
                $document->load('approvals');
                $step = $document->approvals->where('action', 'approve')->count();
                $tanggalPersetujuan = Carbon::parse($request->tanggal_pengesahan);

                LimsDocumentApproval::create([
                    'lims_document_id' => $document->id,
                    'action' => 'approve',
                    'nama' => $request->nama_pengesahan,
                    'jabatan' => $request->jabatan_pengesah,
                    'approved_at' => $tanggalPersetujuan,
                    'approved_by' => $this->karyawan,
                    'step' => $step,
                    'is_active' => true,
                ]);

                DB::commit();

                return response()->json(['message' => 'Dokumen berhasil disetujui'], 200);
            } catch (\Exception $e) {
                DB::rollBack();

                return response()->json(['message' => $e->getMessage()], 500);
            }
        }

        if ($action === 'legalize') {
            foreach (['nama_pengesahan', 'jabatan_pengesah', 'tanggal_pengesahan'] as $field) {
                if (!$request->filled($field)) {
                    return response()->json(['message' => ucfirst(str_replace('_', ' ', $field)) . ' wajib diisi'], 422);
                }
            }

            DB::beginTransaction();

            try {
                $tanggalPengesahan = Carbon::parse($request->tanggal_pengesahan);

                LimsDocumentApproval::create([
                    'lims_document_id' => $document->id,
                    'action' => 'legalize',
                    'nama' => $request->nama_pengesahan,
                    'jabatan' => $request->jabatan_pengesah,
                    'approved_at' => $tanggalPengesahan,
                    'approved_by' => $this->karyawan,
                    'step' => 0,
                    'is_active' => true,
                ]);

                $document->status = self::STATUS_LEGALIZED;
                $document->pengesahan = $request->nama_pengesahan;
                $document->disahkan_pada = $tanggalPengesahan->format('Y-m-d');
                $document->tanggal_pengesahan = $tanggalPengesahan->format('Y-m-d');
                $document->updated_by = $this->karyawan;
                $document->updated_at = Carbon::now();
                $document->save();

                DB::commit();

                return response()->json(['message' => 'Dokumen berhasil disahkan'], 200);
            } catch (\Exception $e) {
                DB::rollBack();

                return response()->json(['message' => $e->getMessage()], 500);
            }
        }

        return response()->json(['message' => 'Aksi tidak valid'], 422);
    }

    public function rejectDocument(Request $request)
    {
        $document = $this->findActiveDocument($request->id, $request->menu_slug);

        if (!$document) {
            return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
        }

        if ($document->status !== self::STATUS_LEGALIZED) {
            return response()->json(['message' => 'Hanya dokumen arsip yang dapat dikembalikan ke dokumen aktif'], 422);
        }

        DB::beginTransaction();

        try {
            LimsDocumentApproval::where('lims_document_id', $document->id)
                ->where('action', 'legalize')
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $document->status = self::STATUS_IN_REVIEW;
            $document->pengesahan = null;
            $document->disahkan_pada = null;
            $document->tanggal_pengesahan = null;
            $document->updated_by = $this->karyawan;
            $document->updated_at = Carbon::now();
            $document->save();

            DB::commit();

            return response()->json(['message' => 'Dokumen berhasil dikembalikan ke dokumen aktif'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function deleteDocument(Request $request)
    {
        $document = $this->findActiveDocument($request->id, $request->menu_slug);

        if (!$document) {
            return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
        }

        $document->load('approvals');

        DB::beginTransaction();

        try {
            $contentFile = $document->content_file;

            $document->is_active = false;
            $document->deleted_by = $this->karyawan;
            $document->deleted_at = Carbon::now();
            $document->save();

            LimsDocumentApproval::where('lims_document_id', $document->id)->update(['is_active' => false]);
            $this->deleteContentFile($contentFile);

            DB::commit();

            return response()->json(['message' => 'Dokumen berhasil dihapus'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    private function fillDocumentFields(LimsDocument $document, Request $request): void
    {
        $document->no_dokumen = $request->no_dokumen;
        $document->nama_dokumen = $request->judul_dokumen ?: $request->nama_dokumen ?: 'PANDUAN SISTEM';
        $document->header_dokumen = $request->header_dokumen;
        $document->sub_header_dokumen = $request->sub_header_dokumen;
        $document->tanggal_cetak = $request->tanggal_cetak;
        $document->terbitan = $request->filled('terbitan') ? $request->terbitan : null;
        $document->revisian = $request->filled('revisi') ? $request->revisi : null;
        $document->cetakan = $request->filled('cetakan') ? $request->cetakan : null;
        $document->disusun_oleh = $request->disusun_oleh;
        $document->jabatan_penyusun = $request->jabatan_penyusun;
        $document->tanggal_disusun = $request->filled('tanggal_disusun')
            ? $request->tanggal_disusun
            : Carbon::today()->format('Y-m-d');
    }

    private function resolveStatusLabel(LimsDocument $document): string
    {
        if ($document->status === self::STATUS_LEGALIZED) {
            return 'Disahkan';
        }

        $approvalCount = $document->approvals->where('action', 'approve')->count();

        if ($approvalCount > 0) {
            return "Disetujui ({$approvalCount})";
        }

        if ($this->workflow->hasVerification($document)) {
            return 'Terverifikasi';
        }

        return 'Menunggu Persetujuan';
    }

    private function findActiveDocument($id, $menuSlug)
    {
        if (!$id || !$menuSlug) {
            return null;
        }

        return LimsDocument::where('id', $id)
            ->where('menu_slug', $menuSlug)
            ->where('is_active', true)
            ->first();
    }

    private function generateContentKey(string $menuSlug): string
    {
        $year = Carbon::now()->format('y');
        $monthRoman = $this->romawi((int) Carbon::now()->format('n'));
        $uniqueText = uniqid('DOC');

        return "{$menuSlug}__LIMS-{$year}-{$monthRoman}-{$uniqueText}";
    }

    private function getContentFilePath(?string $contentFile): ?string
    {
        if (!$contentFile) {
            return null;
        }

        return storage_path('repository/' . self::REPOSITORY_DIR . '/' . str_replace('.', '_', $contentFile) . '.txt');
    }

    private function getContent(?string $contentFile): ?string
    {
        if (!$contentFile) {
            return null;
        }

        return Repository::dir(self::REPOSITORY_DIR)->key($contentFile)->get();
    }

    private function deleteContentFile(?string $contentFile): void
    {
        $path = $this->getContentFilePath($contentFile);

        if ($path && File::exists($path)) {
            File::delete($path);
        }
    }

    private function romawi(int $bulan): string
    {
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

        return $romawi[max(0, min(11, $bulan - 1))];
    }

    private function normalizeDocumentContent(?string $content): ?string
    {
        if (!$content) {
            return $content;
        }

        $content = preg_replace(
            '/(<li\b[^>]*\bstyle="[^"]*?)white-space\s*:\s*pre\b([^"]*")/i',
            '$1white-space: normal$2',
            $content
        );

        $content = preg_replace_callback(
            '/<li([^>]*)>\s*<p([^>]*)>/i',
            function (array $matches) {
                $liAttrs = $matches[1];
                $pAttrs = $matches[2];

                if (preg_match('/style="([^"]*)"/i', $pAttrs, $styleMatch)) {
                    $style = preg_replace('/margin-[^;"]+;?/i', '', $styleMatch[1]);
                    $style = trim($style, '; ') . '; display:inline; margin:0; padding:0;';
                    $pAttrs = preg_replace('/style="[^"]*"/i', 'style="' . $style . '"', $pAttrs);
                } else {
                    $pAttrs .= ' style="display:inline; margin:0; padding:0;"';
                }

                return '<li' . $liAttrs . '><p' . $pAttrs . '>';
            },
            $content
        );

        return $content;
    }
}
