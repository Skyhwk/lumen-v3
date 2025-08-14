<?php

namespace App\Services;

class SaveFileServices
{
public function saveFile($folder, $filename, $file)
{
    try {
        $fullPath = public_path($folder . '/' . $filename);

        // Pastikan foldernya ada
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $file);

        return true;
    } catch (\Throwable $th) {
        dd($th);
        return false;
    }
}
}