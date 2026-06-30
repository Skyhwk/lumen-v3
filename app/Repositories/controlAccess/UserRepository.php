<?php

namespace App\Repositories\controlAccess;

class UserRepository
{
    protected $path = 'app/data/controlAccess/users.json';

    protected function load(): array
    {
        $file = storage_path($this->path);
        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $data = json_decode($content, true);
        if (!$data) {
            return [];
        }

        return is_array($data) && isset($data[0]) ? $data : ($data['users'] ?? []);
    }

    protected function save(array $users): void
    {
        $file = storage_path($this->path);
        $content = file_exists($file) ? file_get_contents($file) : '';
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $existing = $content !== '' ? json_decode($content, true) : null;

        if (is_array($existing) && !isset($existing[0])) {
            $payload = array_merge($existing, ['users' => $users]);
        } else {
            $payload = ['users' => $users, 'version' => '1.0.0', 'private' => true];
        }

        file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function all(): array
    {
        return $this->load();
    }

    public function find(string $id): ?array
    {
        return collect($this->load())->firstWhere('id', $id);
    }

    public function findByEmail(string $email): ?array
    {
        return collect($this->load())->firstWhere('email', $email);
    }

    public function findByUsername(string $username): ?array
    {
        return collect($this->load())->firstWhere('username', $username);
    }

    public function findByCredential(string $credential): ?array
    {
        return collect($this->load())->first(function ($user) use ($credential) {
            return ($user['username'] ?? '') === $credential || ($user['email'] ?? '') === $credential;
        });
    }

    public function updatePassword(string $id, string $passwordHash): bool
    {
        $users = $this->load();
        $found = false;

        $users = array_map(function ($user) use ($id, $passwordHash, &$found) {
            if (($user['id'] ?? '') === $id) {
                $found = true;
                $user['passwordHash'] = $passwordHash;
            }
            return $user;
        }, $users);

        if (!$found) {
            return false;
        }

        $this->save($users);
        return true;
    }
}
