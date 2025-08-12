<?php

namespace App\Services;

class SaveFileServices
{
    public function saveFile($folder, $filename, $file)
    {
        try {
            file_put_contents(public_path($folder . '/' . $filename), $file);
            return true;
        } catch (\Throwable $th) {
            dd($th);
            return false;
        }
    }
}