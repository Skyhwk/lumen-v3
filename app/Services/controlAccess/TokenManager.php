<?php

namespace App\Services\controlAccess;

class TokenManager
{
    protected $path;

    public function __construct()
    {
        $this->path = storage_path('app/data/controlAccess/tokens.json');
        if (!file_exists($this->path)) {
            file_put_contents($this->path, json_encode([]));
        }
    }

    protected function load(): array
    {
        return json_decode(file_get_contents($this->path), true) ?: [];
    }

    protected function save(array $data): void
    {
        file_put_contents($this->path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function generateToken(array $user): string
    {
        $tokens = $this->load();
        $token = bin2hex(random_bytes(32));
        $tokens[$token] = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'role' => $user['role'],
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => time() + (7 * 24 * 60 * 60),
        ];
        $this->save($tokens);
        return $token;
    }

    public function getUserByToken(?string $token): ?array
    {
        if (!$token) {
            return null;
        }

        $tokens = $this->load();
        $entry = $tokens[$token] ?? null;

        if (!$entry || ($entry['expires_at'] ?? 0) < time()) {
            if ($entry) {
                unset($tokens[$token]);
                $this->save($tokens);
            }
            return null;
        }

        return $entry;
    }

    public function invalidateToken(?string $token): void
    {
        if (!$token) {
            return;
        }

        $tokens = $this->load();
        unset($tokens[$token]);
        $this->save($tokens);
    }
}
