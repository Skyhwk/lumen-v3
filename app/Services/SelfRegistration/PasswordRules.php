<?php

namespace App\Services\SelfRegistration;

class PasswordRules
{
    public static function validate($password, $email = '', $nama = '')
    {
        $password = (string) $password;
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Minimal 8 karakter';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Harus mengandung huruf besar';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Harus mengandung huruf kecil';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Harus mengandung angka';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Harus mengandung simbol';
        }

        $forbidden = self::forbiddenFragments($email, $nama);
        $lower = strtolower($password);
        foreach ($forbidden as $frag) {
            if ($frag !== '' && strpos($lower, $frag) !== false) {
                $errors[] = 'Tidak boleh mengandung bagian email atau nama';
                break;
            }
        }

        return $errors;
    }

    private static function forbiddenFragments($email, $nama)
    {
        $bits = [];
        $email = strtolower(trim((string) $email));
        $local = $email;
        $at = strpos($email, '@');
        if ($at !== false) {
            $local = substr($email, 0, $at);
        }
        foreach (preg_split('/[.\-_]+/', $local) as $part) {
            $part = trim($part);
            if (strlen($part) >= 3) {
                $bits[] = $part;
            }
        }
        if (strlen($local) >= 3) {
            $bits[] = $local;
        }

        foreach (preg_split('/\s+/', strtolower((string) $nama)) as $part) {
            $part = trim($part);
            if (strlen($part) >= 3) {
                $bits[] = $part;
            }
        }

        return array_values(array_unique($bits));
    }
}
