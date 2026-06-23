<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LimsDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Repository;
use Yajra\DataTables\DataTables;

Carbon::setLocale('id');

class LimsDocumentController extends Controller
{
    private const REPOSITORY_DIR = 'akreditasi';

    public function index(Request $request)
    {
        $menuSlug = $request->menu_slug;

        if (!$menuSlug) {
            return response()->json(['message' => 'menu_slug wajib diisi'], 422);
        }

        $data = LimsDocument::where('menu_slug', $menuSlug)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return DataTables::of($data)->make(true);
    }

    public function getDocument(Request $request)
    {
        $document = $this->findActiveDocument($request->id, $request->menu_slug);

        if (!$document) {
            return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
        }

        $document->content = $this->normalizeDocumentContent(
            $this->getContent($document->content_file)
        );

        return response()->json($document, 200);
    }

    public function saveDocument(Request $request)
    {

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

                $oldContentFile = $document->content_file;

                if ($request->filled('content')) {
                    $contentFile = $this->generateContentKey($request->menu_slug);
                    $normalizedContent = $this->normalizeDocumentContent($request->content);
                    Repository::dir(self::REPOSITORY_DIR)->key($contentFile)->save($normalizedContent);
                    $document->content_file = $contentFile;
                    $this->deleteContentFile($oldContentFile);
                }

                $document->nama_dokumen = $request->nama_dokumen;
                $document->terbitan = $request->terbitan;
                $document->revisian = $request->revisian;
                $document->pengesahan = $request->pengesahan;
                $document->disahkan_pada = $request->disahkan_pada ?: null;
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

            LimsDocument::create([
                'menu_slug' => $request->menu_slug,
                'nama_dokumen' => $request->nama_dokumen,
                'terbitan' => $request->terbitan,
                'revisian' => $request->revisian,
                'pengesahan' => $request->pengesahan,
                'disahkan_pada' => $request->disahkan_pada ?: null,
                'content_file' => $contentFile,
                'is_active' => true,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now(),
            ]);

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

    public function deleteDocument(Request $request)
    {
        $document = $this->findActiveDocument($request->id, $request->menu_slug);

        if (!$document) {
            return response()->json(['message' => 'Dokumen tidak ditemukan'], 404);
        }

        DB::beginTransaction();

        try {
            $contentFile = $document->content_file;

            $document->is_active = false;
            $document->deleted_by = $this->karyawan;
            $document->deleted_at = Carbon::now();
            $document->save();

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

    /**
     * Normalisasi HTML dari paste Google Docs:
     * - li dengan white-space:pre membuat teks turun dari bullet/angka
     * - p block di dalam li dengan margin-top memisahkan teks dari marker
     */
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
