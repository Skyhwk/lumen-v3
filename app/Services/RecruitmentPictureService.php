<?php

namespace App\Services;

use Carbon\Carbon;

class RecruitmentPictureService
{
    public function storeBase64($value)
    {
        if (!preg_match('/^data:(image\/(?:jpeg|jpg|png|webp));base64,([A-Za-z0-9+\/=\r\n]+)$/i', (string) $value, $matches)) {
            throw new \InvalidArgumentException('Foto selfie harus berupa gambar JPG, PNG, atau WEBP yang valid.');
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || $binary === '') {
            throw new \InvalidArgumentException('Foto selfie tidak dapat diproses.');
        }
        if (strlen($binary) > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('Ukuran foto selfie maksimal 5 MB.');
        }

        $mime = strtolower($matches[1]);
        $extension = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
        $directory = public_path('recruitment');
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Folder penyimpanan foto rekrutmen tidak dapat dibuat.');
        }

        $filename = Carbon::now()->format('YmdHisv') . '.' . $extension;
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($path, $binary) === false) {
            throw new \RuntimeException('Foto selfie tidak dapat disimpan.');
        }

        return $filename;
    }

    public function toDataUri($filename)
    {
        if (!$filename) {
            return null;
        }

        if (str_starts_with($filename, 'data:image') || filter_var($filename, FILTER_VALIDATE_URL)) {
            return $filename;
        }

        $base = basename((string) $filename);
        $path = public_path('recruitment' . DIRECTORY_SEPARATOR . $base);
        if (!is_file($path) || !is_readable($path)) {
            $path = public_path('recruitment' . DIRECTORY_SEPARATOR . 'foto' . DIRECTORY_SEPARATOR . $base);
            if (!is_file($path) || !is_readable($path)) {
                return null;
            }
        }

        $mime = function_exists('mime_content_type') ? mime_content_type($path) : null;
        $mime = $mime ?: 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }

    public function delete($filename)
    {
        if (!$filename) {
            return;
        }

        $path = public_path('recruitment' . DIRECTORY_SEPARATOR . basename((string) $filename));
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
