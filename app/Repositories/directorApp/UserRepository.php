<?php

namespace App\Repositories\directorApp;

class UserRepository
{
    protected $path = 'app/data/directorApp/users.json';

    public function all()
    {
        return json_decode(file_get_contents(storage_path($this->path)), true);
    }

    public function find($id)
    {
        return collect($this->all())->firstWhere('id', $id);
    }

    public function findByEmail($email)
    {
        return collect($this->all())->firstWhere('email', $email);
    }

    public function findByUsername($username)
    {
        return collect($this->all())->firstWhere('username', $username);
    }

    public function update($id, array $data)
    {
        $user = $this->find($id);
        $updatedUser = array_merge($user, $data);

        $allData = array_map(function ($item) use ($id, $updatedUser) {
            if ($item['id'] == $id) return $updatedUser;

            return $item;
        }, $this->all());

        file_put_contents(storage_path($this->path), json_encode($allData, JSON_PRETTY_PRINT));

        return true;
    }

    public function updatePassword($id, $password)
    {
        $user = $this->find($id);
        $user['password'] = $password;

        $allData = array_map(function ($item) use ($id, $user) {
            if ($item['id'] == $id) return $user;

            return $item;
        }, $this->all());

        file_put_contents(storage_path($this->path), json_encode($allData, JSON_PRETTY_PRINT));

        return true;
    }
}
