<?php

namespace App\Models;

class CiscoPhoneDirectorySetting extends Sector
{
    protected $table = 'cisco_phone_directory_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'embed_in_cnf' => 'boolean',
        'use_directory_url_tag' => 'boolean',
    ];

    public static function current(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        $row = new static();
        $row->title = 'Phonebook Kantor';
        $row->embed_in_cnf = false;
        $row->use_directory_url_tag = false;
        $row->save();

        return $row;
    }
}