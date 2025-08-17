<?php

namespace App\Services\directorApp;

class TokenManager
{
    protected $path;

    public function __construct()
    {
        $this->path = storage_path('app/data/directorApp/tokens.json');
        if (!file_exists($this->path)) {
            file_put_contents($this->path, json_encode([]));
        }
    }

    protected function load()
    {
        return json_decode(file_get_contents($this->path), true);
    }

    protected function save($data)
    {
        file_put_contents($this->path, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function generateToken($user)
    {
        $tokens = $this->load();
        $token = bin2hex(random_bytes(32));
        $tokens[$token] = [
            'email' => $user['email'],
            'name' => $user['name'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        $this->save($tokens);
        return $token;
    }

    public function getUserByToken($token)
    {
        $tokens = $this->load();
        return $tokens[$token] ?? null;
    }

    public function invalidateToken($token)
    {
        $tokens = $this->load();
        unset($tokens[$token]);
        $this->save($tokens);
    }
}
