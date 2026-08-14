<?php

namespace App\Services;

class BankSoalImageService
{
    public function convertImg($foto = '', $questionId = '', $optionId = 'question')
    {
        if (!$foto || !is_string($foto)) {
            return null;
        }

        if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $foto, $matches)) {
            return null;
        }

        $base64 = preg_replace('/^data:image\/(jpeg|jpg|png|webp);base64,/', '', $foto);
        $file = base64_decode($base64, true);
        if ($file === false) {
            return null;
        }

        $destinationPath = public_path('bank-soal');
        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0775, true);
        }
        @chmod($destinationPath, 0775);

        $timestamp = date('YmdHis');
        $safeQuestionId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $questionId);
        $safeOptionId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $optionId);
        $safeName = $timestamp . '_' . $safeQuestionId . '_' . $safeOptionId . '.jpeg';

        if (file_put_contents($destinationPath . DIRECTORY_SEPARATOR . $safeName, $file) === false) {
            throw new \RuntimeException('Gagal menyimpan image bank soal.');
        }

        return $safeName;
    }

    public function deleteImg($image)
    {
        $fileName = basename(parse_url((string) $image, PHP_URL_PATH) ?: (string) $image);
        $path = public_path('bank-soal' . DIRECTORY_SEPARATOR . $fileName);
        if ($fileName && is_file($path)) {
            @unlink($path);
        }
    }

    public function url($image)
    {
        if (!$image) return null;
        if (filter_var($image, FILTER_VALIDATE_URL)) return $image;
        $appUrl = rtrim((string) env('APP_URL', ''), '/');
        return ($appUrl ? $appUrl : '') . '/bank-soal/' . basename($image);
    }
}
