<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LhpManual;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use setasign\Fpdi\Tcpdf\Fpdi;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Yajra\DataTables\Facades\DataTables;

class LhpManualController extends Controller
{
    private const DRAW_ORDER = ['watermark', 'cover', 'logo', 'qr'];
    private const MAX_PDF_COUNT = 20;
    private const ALLOWED_DOC_TYPES = ['LHP_HIGIENE_SANITASI'];

    public function index(Request $request)
    {
        try {
            $periode = (int) ($request->periode ?: date('Y'));

            $query = LhpManual::query()
                ->active()
                ->withCount(['orderDetails as total_no_sampel'])
                ->when($periode >= 2000, function ($builder) use ($periode) {
                    $builder->where(function ($sub) use ($periode) {
                        $sub->whereYear('tanggal_lhp', $periode)
                            ->orWhere(function ($fallback) use ($periode) {
                                $fallback->whereNull('tanggal_lhp')
                                    ->whereYear('created_at', $periode);
                            });
                    });
                })
                ->orderByDesc('id');

            return DataTables::of($query)
                ->filterColumn('no_order', function ($builder, $keyword) {
                    $builder->where('no_order', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('no_lhp', function ($builder, $keyword) {
                    $builder->where('no_lhp', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('no_quotation', function ($builder, $keyword) {
                    $builder->where('no_quotation', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('nama_perusahaan', function ($builder, $keyword) {
                    $builder->where('nama_perusahaan', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('status_sampling', function ($builder, $keyword) {
                    $builder->where('status_sampling', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('kategori_2', function ($builder, $keyword) {
                    $builder->where('kategori_2', 'like', '%' . $keyword . '%');
                })
                ->filterColumn('kategori_3', function ($builder, $keyword) {
                    $builder->where('kategori_3', 'like', '%' . $keyword . '%');
                })
                ->editColumn('parameter_uji', function ($row) {
                    if (is_array($row->parameter_uji)) {
                        return $row->parameter_uji;
                    }

                    if (is_string($row->parameter_uji) && $row->parameter_uji !== '') {
                        $decoded = json_decode($row->parameter_uji, true);
                        return is_array($decoded) ? $decoded : $row->parameter_uji;
                    }

                    return [];
                })
                ->editColumn('tanggal_sampling', function ($row) {
                    return $this->formatDate($row->tanggal_sampling);
                })
                ->editColumn('tanggal_terima', function ($row) {
                    return $this->formatDate($row->tanggal_terima);
                })
                ->editColumn('tanggal_lhp', function ($row) {
                    return $this->formatDate($row->tanggal_lhp);
                })
                ->make(true);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function searchNoLhp(Request $request)
    {
        try {
            $term = trim((string) ($request->term ?? $request->q ?? ''));

            if (strlen($term) < 3) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ], 200);
            }

            $existing = LhpManual::query()
                ->active()
                ->pluck('no_lhp')
                ->filter()
                ->values()
                ->all();

            $rows = OrderDetail::query()
                ->select(
                    'cfr as no_lhp',
                    DB::raw('MIN(no_order) as no_order'),
                    DB::raw('MIN(nama_perusahaan) as nama_perusahaan'),
                    DB::raw('MIN(no_quotation) as no_quotation'),
                    DB::raw('MIN(kategori_1) as status_sampling'),
                    DB::raw('MIN(kategori_2) as kategori_2'),
                    DB::raw('MIN(kategori_3) as kategori_3')
                )
                ->where('is_active', true)
                ->whereNotNull('cfr')
                ->where('cfr', '!=', '')
                ->where('cfr', 'like', '%' . $term . '%')
                ->when(!empty($existing), function ($query) use ($existing) {
                    $query->whereNotIn('cfr', $existing);
                })
                ->groupBy('cfr')
                ->orderBy('cfr')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $rows,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function previewByCfr(Request $request)
    {
        try {
            $resolved = $this->resolveCfrContext($request);
            if ($resolved instanceof \Illuminate\Http\JsonResponse) {
                return $resolved;
            }

            [$orderHeader, $orderDetails, $reference] = $resolved;

            return response()->json([
                'success' => true,
                'data' => array_merge($reference, [
                    'no_order' => $orderHeader->no_order,
                    'no_lhp' => trim((string) $request->no_lhp),
                    'no_quotation' => $reference['no_quotation'] ?: ($orderHeader->no_document ?? null),
                    'nama_perusahaan' => $reference['nama_perusahaan'] ?: ($orderHeader->nama_perusahaan ?? null),
                    'total_no_sampel' => count($reference['no_sampel_list']),
                    'samples' => $reference['sample_details'],
                ]),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function create(Request $request)
    {
        DB::beginTransaction();

        try {
            $resolved = $this->resolveCfrContext($request);
            if ($resolved instanceof \Illuminate\Http\JsonResponse) {
                DB::rollBack();
                return $resolved;
            }

            [$orderHeader, $orderDetails, $reference] = $resolved;
            $noOrder = trim((string) ($request->no_order ?: $orderHeader->no_order));
            $noLhp = trim((string) $request->no_lhp);

            $tanggalLhp = $this->parseDate($request->tanggal_release_lhp ?? $request->tanggal_lhp);
            if (!$tanggalLhp) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tanggal release LHP wajib diisi',
                ], 422);
            }

            $duplicate = LhpManual::query()
                ->where('no_lhp', $noLhp)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->exists();

            if ($duplicate) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "LHP manual untuk {$noLhp} sudah ada",
                ], 422);
            }

            $parameterUji = $this->normalizeParameterUji(
                $request->parameter_uji,
                $reference['parameter_uji'] ?? null
            );

            $fileLhp = $this->resolveUploadedFile(
                $request,
                'file_lhp',
                'dokumen/LHP_MANUAL',
                $noLhp
            );

            $fileQr = trim((string) ($request->file_qr ?? ''));
            if ($fileQr === '' && $request->hasFile('qr')) {
                $fileQr = $this->resolveUploadedFile(
                    $request,
                    'qr',
                    'qr_documents',
                    'LHP_' . str_replace('/', '_', $noLhp),
                    'svg'
                );
            }

            $now = Carbon::now();

            $record = LhpManual::create([
                'no_order' => $noOrder,
                'no_lhp' => $noLhp,
                'no_quotation' => trim((string) ($reference['no_quotation'] ?: $orderHeader->no_document ?: '')),
                'nama_perusahaan' => trim((string) ($reference['nama_perusahaan'] ?: $orderHeader->nama_perusahaan ?: '')),
                'status_sampling' => trim((string) ($reference['status_sampling'] ?: '')),
                'parameter_uji' => $parameterUji,
                'kategori_2' => trim((string) ($reference['kategori_2'] ?: '')),
                'kategori_3' => trim((string) ($reference['kategori_3'] ?: '')),
                'tanggal_sampling' => $reference['tanggal_sampling'] ?? null,
                'tanggal_terima' => $reference['tanggal_terima'] ?? null,
                'file_qr' => $fileQr ?: null,
                'file_lhp' => $fileLhp ?: trim((string) ($request->file_lhp ?? '')) ?: null,
                'tanggal_lhp' => $tanggalLhp,
                'is_active' => true,
                'created_by' => $this->karyawan,
                'created_at' => $now,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
            ]);

            DB::commit();

            $record->loadCount(['orderDetails as total_no_sampel']);

            return response()->json([
                'success' => true,
                'message' => 'LHP manual berhasil disimpan',
                'data' => array_merge($record->toArray(), [
                    'no_sampel_list' => $reference['no_sampel_list'],
                    'total_no_sampel' => count($reference['no_sampel_list']),
                ]),
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan LHP manual: ' . $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function generateQr(Request $request)
    {
        DB::beginTransaction();

        try {
            $noLhp = trim((string) ($request->no_lhp ?? ''));
            $tipeDokumen = trim((string) ($request->tipe_dokumen ?? ''));
            $tanggalLhp = trim((string) ($request->tanggal_lhp ?? ''));

            if ($noLhp === '' || $tanggalLhp === '') {
                DB::rollBack();
                return response()->json(['message' => 'No LHP dan tanggal LHP wajib diisi'], 422);
            }

            if (!in_array($tipeDokumen, self::ALLOWED_DOC_TYPES, true)) {
                DB::rollBack();
                return response()->json(['message' => 'Tipe dokumen belum didukung'], 422);
            }

            $orderDetail = OrderDetail::where('cfr', $noLhp)->where('is_active', 1)->first();
            if (!$orderDetail) {
                DB::rollBack();
                return response()->json([
                    'message' => "Data order dengan CFR {$noLhp} tidak ditemukan",
                ], 404);
            }

            $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $tanggalLhp)
                ->orderByDesc('berlaku_mulai')
                ->first();

            $filename = 'LHP_' . str_replace('/', '_', $orderDetail->cfr);
            $directory = $this->ensurePublicDirectory('qr_documents');

            $path = $directory . '/' . $filename . '.svg';
            $message = "QR Document {$filename} berhasil digenerate";

            if (!file_exists($path)) {
                $link = 'https://www.intilab.com/validation/';
                $unique = 'isldc' . (int) floor(microtime(true) * 1000);

                QrCode::size(200)->generate($link . $unique, $path);
                QrDocument::create([
                    'type_document' => $tipeDokumen,
                    'kode_qr' => $unique,
                    'file' => $filename,
                    'data' => json_encode([
                        'Nomor_LHP' => $orderDetail->cfr,
                        'Nama_Pelanggan' => $orderDetail->nama_perusahaan,
                        'Pelanggan_ID' => substr($orderDetail->no_order, 0, 6),
                        'Tanggal_Pengesahan' => Carbon::parse($tanggalLhp)->locale('id')->isoFormat('DD MMMM YYYY'),
                        'Disahkan_Oleh' => $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah',
                        'Jabatan' => $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor',
                    ]),
                    'created_at' => Carbon::now(),
                    'created_by' => 'System Manual',
                ]);
            } else {
                $message = "QR Document {$filename} sudah ada, digunakan kembali";
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'file' => $filename,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function savePdf(Request $request)
    {
        @set_time_limit(180);

        DB::beginTransaction();

        try {
            $noLhp = trim((string) ($request->no_lhp ?? ''));
            $tipeDokumen = trim((string) ($request->tipe_dokumen ?? 'LHP_HIGIENE_SANITASI'));
            $qrFileName = basename((string) $request->input('qr_file', ''));

            if ($noLhp === '') {
                DB::rollBack();
                return response()->json(['message' => 'No LHP wajib diisi'], 422);
            }

            if (!in_array($tipeDokumen, self::ALLOWED_DOC_TYPES, true)) {
                DB::rollBack();
                return response()->json(['message' => 'Tipe dokumen belum didukung'], 422);
            }

            $pdfFiles = $this->collectPdfFiles($request);
            $qrPath = $this->resolveGeneratedQrPath($qrFileName);

            if (!$pdfFiles || !$qrPath) {
                DB::rollBack();
                return response()->json(['message' => 'File PDF dan QR wajib tersedia'], 400);
            }

            if (count($pdfFiles) > self::MAX_PDF_COUNT) {
                DB::rollBack();
                return response()->json(['message' => 'Maksimal ' . self::MAX_PDF_COUNT . ' file PDF'], 400);
            }

            $overlays = json_decode($request->input('overlays', '[]'), true);
            $overlaySets = $this->syncSharedWatermark(
                $this->overlaySetsForDocuments($overlays, count($pdfFiles))
            );

            $logoPath = public_path('isl_logo.png');
            $watermarkPath = public_path('logo-watermark.png');

            $pdf = $this->makeStampPdf();

            foreach ($pdfFiles as $index => $pdfFile) {
                $this->stampDocument(
                    $pdf,
                    $pdfFile->getRealPath(),
                    $this->sortOverlays($overlaySets[$index] ?? []),
                    $logoPath,
                    $watermarkPath,
                    $qrPath
                );
            }

            $fileName = 'LHP-' . str_replace('/', '-', $noLhp) . '.pdf';
            $folder = $this->ensurePublicDirectory('dokumen/LHP_DOWNLOAD');

            $targetPath = $folder . '/' . $fileName;
            $pdf->Output($targetPath, 'F');

            // Simpan file_qr & file_lhp ke lhp_manual hanya di langkah akhir.
            $this->persistLhpManualFiles($noLhp, $qrFileName, $fileName, $request);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'File LHP berhasil disimpan',
                'file_lhp' => $fileName,
                'file_qr' => $qrFileName,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function resolveCfrContext(Request $request)
    {
        $noLhp = trim((string) ($request->no_lhp ?? ''));
        $noOrder = trim((string) ($request->no_order ?? ''));

        if ($noLhp === '') {
            return response()->json([
                'success' => false,
                'message' => 'no_lhp wajib diisi',
            ], 422);
        }

        $orderDetailsQuery = OrderDetail::query()
            ->where('cfr', $noLhp)
            ->where('is_active', true);

        if ($noOrder !== '') {
            $orderDetailsQuery->where('no_order', $noOrder);
        }

        $orderDetails = $orderDetailsQuery->orderBy('no_sampel')->get();

        if ($orderDetails->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "No LHP {$noLhp} tidak ditemukan",
            ], 404);
        }

        $noOrder = $noOrder ?: (string) $orderDetails->first()->no_order;

        $orderHeader = OrderHeader::where('no_order', $noOrder)
            ->where('is_active', true)
            ->first();

        if (!$orderHeader) {
            return response()->json([
                'success' => false,
                'message' => "Order {$noOrder} tidak ditemukan",
            ], 404);
        }

        return [$orderHeader, $orderDetails, $this->buildReferenceFromOrderDetails($orderDetails)];
    }

    /**
     * Satu no LHP (CFR) bisa punya banyak order_detail.
     * Create cukup sekali per no_lhp; metadata diambil dari gabungan semua sampel.
     */
    private function buildReferenceFromOrderDetails($orderDetails): array
    {
        $first = $orderDetails->first();
        $parameters = [];
        $kategori2 = [];
        $kategori3 = [];
        $sampleDetails = [];

        foreach ($orderDetails as $detail) {
            $decoded = $this->normalizeParameterUji(null, $detail->parameter ?? null) ?? [];
            foreach ($decoded as $param) {
                $parameters[] = $param;
            }

            if (!empty($detail->kategori_2)) {
                $kategori2[] = $detail->kategori_2;
            }
            if (!empty($detail->kategori_3)) {
                $kategori3[] = $detail->kategori_3;
            }

            $sampleDetails[] = [
                'no_sampel' => $detail->no_sampel,
                'kategori_2' => $detail->kategori_2,
                'kategori_3' => $detail->kategori_3,
                'tanggal_sampling' => $this->formatDate($detail->tanggal_sampling),
                'tanggal_terima' => $this->formatDate($detail->tanggal_terima),
                'parameter_uji' => $decoded,
            ];
        }

        $uniqueKategori2 = array_values(array_unique($kategori2));
        $uniqueKategori3 = array_values(array_unique($kategori3));
        $samplingDates = $orderDetails->pluck('tanggal_sampling')->filter()->sort()->values();
        $terimaDates = $orderDetails->pluck('tanggal_terima')->filter()->sort()->values();

        return [
            'no_quotation' => $first->no_quotation,
            'nama_perusahaan' => $first->nama_perusahaan,
            'status_sampling' => $first->kategori_1,
            'kategori_2' => count($uniqueKategori2) === 1 ? $uniqueKategori2[0] : implode(', ', $uniqueKategori2),
            'kategori_3' => count($uniqueKategori3) === 1 ? $uniqueKategori3[0] : implode(', ', $uniqueKategori3),
            'parameter_uji' => array_values(array_unique($parameters)),
            'tanggal_sampling' => $this->formatDate($samplingDates->first()),
            'tanggal_terima' => $this->formatDate($terimaDates->first()),
            'no_sampel_list' => $orderDetails->pluck('no_sampel')->filter()->values()->all(),
            'sample_details' => $sampleDetails,
        ];
    }

    private function normalizeParameterUji($input, $fallback = null): ?array
    {
        if ($input !== null && $input !== '') {
            if (is_array($input)) {
                return array_values($input);
            }

            if (is_string($input)) {
                $decoded = json_decode($input, true);
                if (json_last_error() === JSON_ERROR_NONE && is_parray($decoded)) {
                    return array_values($decoded);
                }

                return [trim($input)];
            }
        }

        if ($fallback === null || $fallback === '') {
            return null;
        }

        if (is_array($fallback)) {
            return array_values($fallback);
        }

        $decoded = json_decode((string) $fallback, true);
        return is_array($decoded) ? array_values($decoded) : null;
    }

    private function resolveUploadedFile(
        Request $request,
        string $field,
        string $directory,
        string $basename,
        string $defaultExtension = 'pdf'
    ): ?string {
        if (!$request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        if (!$file || !$file->isValid()) {
            return null;
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $defaultExtension);
        $safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $basename);
        $filename = $safeBase . '.' . $extension;
        $targetDir = $this->ensurePublicDirectory(trim($directory, '/'));

        $file->move($targetDir, $filename);

        return $filename;
    }

    private function parseDate($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $th) {
            return null;
        }
    }

    private function formatDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $th) {
            return (string) $value;
        }
    }

    private function ensurePublicDirectory(string $relativePath): string
    {
        $targetDir = public_path(trim($relativePath, '/'));

        if (is_dir($targetDir)) {
            if (!is_writable($targetDir)) {
                throw new \RuntimeException("Folder {$relativePath} tidak bisa ditulis. Periksa permission server.");
            }

            return $targetDir;
        }

        if (!@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new \RuntimeException(
                "Gagal membuat folder {$relativePath}. Buat manual: public/{$relativePath} dengan permission writable untuk web server."
            );
        }

        @chmod($targetDir, 0777);

        return $targetDir;
    }

    private function persistLhpManualFiles(string $noLhp, ?string $fileQr, ?string $fileLhp, Request $request): void
    {
        $manual = LhpManual::query()
            ->where('no_lhp', $noLhp)
            ->where('is_active', true)
            ->first();

        if (!$manual) {
            throw new \RuntimeException("Record LHP manual untuk {$noLhp} tidak ditemukan");
        }

        if ($fileQr) {
            $manual->file_qr = $fileQr;
        }
        if ($fileLhp) {
            $manual->file_lhp = $fileLhp;
        }

        $tanggalLhp = $this->parseDate($request->tanggal_lhp ?? null);
        if ($tanggalLhp) {
            $manual->tanggal_lhp = $tanggalLhp;
        }

        $manual->updated_by = $this->karyawan;
        $manual->updated_at = Carbon::now();
        $manual->save();

        if ($fileQr) {
            QrDocument::query()
                ->where('file', $fileQr)
                ->where('type_document', 'LHP_HIGIENE_SANITASI')
                ->update(['id_document' => $manual->id]);
        }
    }

    private function collectPdfFiles(Request $request)
    {
        $allFiles = $request->allFiles();
        $files = $allFiles['pdfs'] ?? $request->file('pdfs');

        if (!$files) {
            $single = $allFiles['pdf'] ?? $request->file('pdf');
            $files = $single ? [$single] : [];
        }

        if (!is_array($files)) {
            $files = [$files];
        }

        return array_values(array_filter($files));
    }

    private function resolveGeneratedQrPath($qrFileName)
    {
        if (!$qrFileName || !preg_match('/^[A-Za-z0-9_\-]+$/', $qrFileName)) {
            return null;
        }

        $qrPath = public_path('qr_documents/' . $qrFileName . '.svg');

        return is_file($qrPath) ? $qrPath : null;
    }

    private function overlaySetsForDocuments($overlays, $documentCount)
    {
        $empty = array_fill(0, max(0, (int) $documentCount), []);
        if (!is_array($overlays) || $documentCount < 1) {
            return $empty;
        }

        $first = reset($overlays);
        $isPerDocument = is_array($first) && !array_key_exists('type', $first);

        if (!$isPerDocument) {
            $shared = array_values($overlays);
            return array_fill(0, $documentCount, $shared);
        }

        $sets = [];
        for ($index = 0; $index < $documentCount; $index++) {
            $set = $overlays[$index] ?? [];
            $sets[] = is_array($set) ? array_values($set) : [];
        }

        return $sets;
    }

    private function syncSharedWatermark(array $overlaySets)
    {
        $watermark = null;
        foreach ($overlaySets as $set) {
            foreach ($set as $overlay) {
                if (($overlay['type'] ?? '') === 'watermark') {
                    $watermark = $overlay;
                    break 2;
                }
            }
        }

        if (!$watermark) {
            return $overlaySets;
        }

        $watermark['pages'] = 'all';
        $watermark['visible'] = $watermark['visible'] ?? true;

        return array_map(function ($set) use ($watermark) {
            $without = array_values(array_filter($set, function ($overlay) {
                return ($overlay['type'] ?? '') !== 'watermark';
            }));
            $without[] = $watermark;
            return $without;
        }, $overlaySets);
    }

    private function makeStampPdf()
    {
        $pdf = new class('P', 'mm') extends Fpdi {
            public function Header() {}
            public function Footer() {}
        };
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);
        $pdf->SetAutoPageBreak(false, 0);

        return $pdf;
    }

    private function stampDocument(Fpdi $pdf, $sourcePath, array $sorted, $logoPath, $watermarkPath, $qrPath)
    {
        $normalizedPath = $this->normalizePdfForFpdi($sourcePath);

        try {
            $pageCount = $pdf->setSourceFile($normalizedPath);

            for ($page = 1; $page <= $pageCount; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $orientation = $size['orientation'] ?? ($size['width'] > $size['height'] ? 'L' : 'P');
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($template, 0, 0, $size['width'], $size['height']);

                foreach ($sorted as $overlay) {
                    if (!$this->isVisibleOnPage($overlay, $page, $pageCount)) {
                        continue;
                    }
                    $this->drawOverlay($pdf, $overlay, $size, $logoPath, $watermarkPath, $qrPath);
                }
            }
        } finally {
            if ($normalizedPath && is_file($normalizedPath)) {
                @unlink($normalizedPath);
            }
        }
    }

    private function normalizePdfForFpdi($sourcePath)
    {
        $outputPath = sys_get_temp_dir() . '/lhp_fpdi_' . uniqid('', true) . '.pdf';
        $gs = is_executable('/usr/bin/gs') ? '/usr/bin/gs' : 'gs';
        $command = sprintf(
            '%s -q -dNOPAUSE -dBATCH -dSAFER -dAutoRotatePages=/None -dUseCropBox -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=%s %s 2>&1',
            escapeshellcmd($gs),
            escapeshellarg($outputPath),
            escapeshellarg($sourcePath)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);
            throw new \RuntimeException(
                'PDF terenkripsi atau tidak bisa diproses. ' . trim(implode(' ', $output))
            );
        }

        return $outputPath;
    }

    private function sortOverlays(array $overlays)
    {
        usort($overlays, function ($a, $b) {
            $orderA = array_search($a['type'] ?? '', self::DRAW_ORDER, true);
            $orderB = array_search($b['type'] ?? '', self::DRAW_ORDER, true);
            $orderA = $orderA === false ? 99 : $orderA;
            $orderB = $orderB === false ? 99 : $orderB;

            return $orderA <=> $orderB;
        });

        return $overlays;
    }

    private function isVisibleOnPage(array $overlay, $page, $pageCount)
    {
        if (isset($overlay['visible']) && !$overlay['visible']) {
            return false;
        }

        $pages = $overlay['pages'] ?? 'all';
        if ($pages === 'first') {
            return (int) $page === 1;
        }
        if ($pages === 'last') {
            return (int) $page === (int) $pageCount;
        }
        if ($pages === 'page') {
            return (int) $page === (int) ($overlay['page'] ?? 0);
        }

        return true;
    }

    private function drawOverlay(Fpdi $pdf, array $overlay, array $size, $logoPath, $watermarkPath, $qrPath)
    {
        $x = $size['width'] * ((float) ($overlay['xPercent'] ?? 0) / 100);
        $y = $size['height'] * ((float) ($overlay['yPercent'] ?? 0) / 100);
        $w = $size['width'] * ((float) ($overlay['wPercent'] ?? 0) / 100);
        $h = $size['height'] * ((float) ($overlay['hPercent'] ?? 0) / 100);
        $opacity = isset($overlay['opacity']) ? (float) $overlay['opacity'] : 1;
        $type = $overlay['type'] ?? '';

        if ($w <= 0 || $h <= 0) {
            return;
        }

        if ($type === 'cover') {
            $pdf->SetAlpha(1);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($x, $y, $w, $h, 'F');
            return;
        }

        $path = null;
        $imageType = '';
        if ($type === 'logo' && is_file($logoPath)) {
            $path = $logoPath;
            $imageType = 'PNG';
        } elseif ($type === 'watermark' && is_file($watermarkPath)) {
            $path = $watermarkPath;
            $imageType = 'PNG';
        } elseif ($type === 'qr' && is_file($qrPath)) {
            $pdf->SetAlpha(max(0.05, min(1, $opacity)));
            if (strtolower(pathinfo($qrPath, PATHINFO_EXTENSION)) === 'svg') {
                $pdf->ImageSVG($qrPath, $x, $y, $w, $h);
            } else {
                $pdf->Image($qrPath, $x, $y, $w, $h, 'PNG', '', '', false, 300, '', false, false, 0, 'CM');
            }
            $pdf->SetAlpha(1);
            return;
        }

        if (!$path) {
            return;
        }

        $pdf->SetAlpha(max(0.05, min(1, $opacity)));
        $pdf->Image($path, $x, $y, $w, $h, $imageType, '', '', false, 300, '', false, false, 0, 'CM');
        $pdf->SetAlpha(1);
    }
}
