<?php

// ============================================
// 1. MODEL USER (app/Models/User.php)
// ============================================

namespace App\Models\ExecAppModels;

class User
{
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'role',
        'status',
        'permissions',
        'created_at',
        'updated_at'
    ];

    protected $hidden = [
        'password'
    ];

    public function __construct($attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function toArray()
    {
        $array = [];
        foreach ($this->fillable as $field) {
            if (isset($this->{$field}) && !in_array($field, $this->hidden)) {
                $array[$field] = $this->{$field};
            }
        }
        return $array;
    }

    public function fill($attributes)
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $this->{$key} = $value;
            }
        }
        return $this;
    }
}
