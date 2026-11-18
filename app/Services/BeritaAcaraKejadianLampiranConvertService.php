<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Konversi lampiran PDF BAK ke JPG saat upload (Imagick + Ghostscript).
 */
class BeritaAcaraKejadianLampiranConvertService
{
    private const RESOLUTION_DPI = 150;

    private const JPEG_QUALITY = 85;

    /**
     * Simpan tiap halaman PDF sebagai JPG di $outputDirectory (nama: {base}_p{n}.jpg).
     *
     * @return array<int, string> path absolut file JPG
     */
    public function convertPdfToJpegFiles(string $pdfPath, string $outputDirectory, string $nameBase): array
    {
        $this->ensureImagickAvailable();
        $this->configureGhostscriptForImagick();

        if (!is_file($pdfPath)) {
            throw new RuntimeException('File PDF lampiran tidak ditemukan.');
        }

        if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0755, true) && !is_dir($outputDirectory)) {
            throw new RuntimeException('Folder penyimpanan lampiran gagal dibuat.');
        }

        $nameBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nameBase) ?: 'dok';

        $imagick = new \Imagick();
        try {
            $imagick->setResolution(self::RESOLUTION_DPI, self::RESOLUTION_DPI);
            $imagick->readImage($pdfPath);

            $paths = [];
            $pageCount = $imagick->getNumberImages();
            if ($pageCount < 1) {
                throw new RuntimeException('PDF lampiran tidak memiliki halaman.');
            }

            for ($i = 0; $i < $pageCount; $i++) {
                $imagick->setIteratorIndex($i);
                $page = $imagick->getImage();
                $page->setImageBackgroundColor('white');
                if (defined('Imagick::ALPHACHANNEL_REMOVE')) {
                    $page->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                }
                try {
                    $page = $page->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                } catch (\Throwable $ex) {
                    // halaman tanpa layer
                }
                $page->setImageFormat('jpeg');
                $page->setImageCompressionQuality(self::JPEG_QUALITY);

                $jpegName = $nameBase . '_p' . ($i + 1) . '.jpg';
                $jpegPath = $outputDirectory . DIRECTORY_SEPARATOR . $jpegName;
                $page->writeImage($jpegPath);
                $page->clear();

                if (!is_file($jpegPath) || filesize($jpegPath) <= 0) {
                    throw new RuntimeException('Gagal menyimpan halaman JPG lampiran (halaman ' . ($i + 1) . ').');
                }

                $paths[] = $jpegPath;
            }

            return $paths;
        } catch (\Throwable $ex) {
            Log::error('Konversi PDF lampiran BAK (Imagick) gagal', [
                'pdf' => $pdfPath,
                'message' => $ex->getMessage(),
            ]);

            throw new RuntimeException(
                'Gagal mengonversi lampiran PDF ke JPG. Pastikan Imagick dan Ghostscript terpasang di server. '
                . $ex->getMessage()
            );
        } finally {
            $imagick->clear();
        }
    }

    private function ensureImagickAvailable(): void
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            throw new RuntimeException(
                'Extension PHP Imagick wajib untuk lampiran PDF. Hubungi admin server.'
            );
        }
    }

    private function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    private function resolveCommandInPath(string $command): ?string
    {
        if ($this->isWindows()) {
            $output = shell_exec('where ' . $command . ' 2>nul');
        } else {
            $output = shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null');
        }

        $line = trim(explode("\n", str_replace("\r", '', trim((string) $output)))[0] ?? '');
        if ($line === '' || !is_file($line)) {
            return null;
        }

        return $line;
    }

    private function prependPathDirectory(string $directory): void
    {
        if ($directory === '' || !is_dir($directory)) {
            return;
        }

        $path = getenv('PATH') ?: '';
        if (strpos($path, $directory) === false) {
            putenv('PATH=' . $directory . PATH_SEPARATOR . $path);
        }
    }

    private function findGhostscriptBinary(): ?string
    {
        $candidates = [];
        $envGs = getenv('GHOSTSCRIPT_BINARY') ?: getenv('GS_PROG');
        if (!empty($envGs)) {
            $candidates[] = $envGs;
        }

        if ($this->isWindows()) {
            $laragonGs = 'C:\\laragon\\bin\\ghostscript';
            if (is_dir($laragonGs)) {
                foreach (glob($laragonGs . '\\gs*\\bin\\gswin64c.exe') ?: [] as $gsPath) {
                    $candidates[] = $gsPath;
                }
                foreach ([$laragonGs . '\\bin\\gswin64c.exe', 'C:\\laragon\\bin\\ghostscript\\bin\\gswin64c.exe'] as $flatBin) {
                    if (is_file($flatBin)) {
                        $candidates[] = $flatBin;
                    }
                }
            }

            $candidates = array_merge($candidates, [
                base_path('tools/ghostscript/install/gs/bin/gswin64c.exe'),
                base_path('tools/ghostscript/bin/gswin64c.exe'),
            ]);
            foreach (['C:\\Program Files\\gs', 'C:\\Program Files (x86)\\gs'] as $base) {
                if (!is_dir($base)) {
                    continue;
                }
                foreach (glob($base . '\\gs*', GLOB_ONLYDIR) ?: [] as $dir) {
                    $candidates[] = $dir . '\\bin\\gswin64c.exe';
                    $candidates[] = $dir . '\\bin\\gswin32c.exe';
                }
            }
            foreach (['gswin64c', 'gswin32c', 'gs'] as $command) {
                $resolved = $this->resolveCommandInPath($command);
                if ($resolved) {
                    $candidates[] = $resolved;
                }
            }
        } else {
            $candidates[] = '/usr/bin/gs';
            $candidates[] = '/usr/local/bin/gs';
            $resolved = $this->resolveCommandInPath('gs');
            if ($resolved) {
                $candidates[] = $resolved;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function configureGhostscriptForImagick(): void
    {
        $gsBinary = $this->findGhostscriptBinary();
        if (!$gsBinary) {
            throw new RuntimeException(
                'Ghostscript (gs) diperlukan agar Imagick dapat membaca PDF. Install Ghostscript di server.'
            );
        }

        $gsBasename = strtolower(basename($gsBinary));
        if (!$this->isWindows()) {
            $this->prependPathDirectory(dirname($gsBinary));

            return;
        }

        if ($gsBasename === 'gs.exe' || $gsBasename === 'gs') {
            $this->prependPathDirectory(dirname($gsBinary));

            return;
        }

        $gsAliasDir = storage_path('app/ghostscript');
        if (!is_dir($gsAliasDir)) {
            mkdir($gsAliasDir, 0777, true);
        }

        $gsAlias = $gsAliasDir . DIRECTORY_SEPARATOR . 'gs.exe';
        if (!is_file($gsAlias) || filemtime($gsBinary) > filemtime($gsAlias)) {
            if (!@copy($gsBinary, $gsAlias)) {
                throw new RuntimeException(
                    'Gagal menyiapkan Ghostscript untuk Imagick. Periksa izin folder storage/app/ghostscript.'
                );
            }
        }

        $this->prependPathDirectory($gsAliasDir);
    }
}