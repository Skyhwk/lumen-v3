<?php

namespace App\Repositories\controlAccess;

class ResetTokenRepository
{
    protected $path = 'app/data/controlAccess/reset-tokens.json';

    protected function load(): array
    {
        $file = storage_path($this->path);
        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        return json_decode($content, true) ?: [];
    }

    protected function save(array $tokens): void
    {
        file_put_contents(
            storage_path($this->path),
            json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public function pruneExpired(): array
    {
        $tokens = array_values(array_filter($this->load(), function ($entry) {
            return ($entry['expires'] ?? 0) > (time() * 1000);
        }));
        $this->save($tokens);
        return $tokens;
    }

    public function create(string $userId, string $email): string
    {
        $token = bin2hex(random_bytes(16));
        $tokens = $this->pruneExpired();
        $tokens[] = [
            'token' => $token,
            'userId' => $userId,
            'email' => $email,
            'expires' => (time() + 3600) * 1000,
        ];
        $this->save($tokens);
        return $token;
    }

    public function findValid(string $token): ?array
    {
        $tokens = $this->pruneExpired();
        return collect($tokens)->firstWhere('token', $token);
    }

    public function delete(string $token): void
    {
        $tokens = array_values(array_filter($this->pruneExpired(), function ($entry) use ($token) {
            return ($entry['token'] ?? '') !== $token;
        }));
        $this->save($tokens);
    }
}
