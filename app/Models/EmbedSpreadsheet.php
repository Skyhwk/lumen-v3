<?php

namespace App\Models;

use App\Models\Sector;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmbedSpreadsheet extends Sector
{
    use SoftDeletes;

    protected $table = 'embed_spreadsheets';
    public $timestamps = true;

    protected $fillable = [
        'nama_formulir',
        'source',
        'url_form',
        'type',
        'created_by',
        'updated_by',
        'deleted_by',
        'uploader'
    ];

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        return $this->source ?: $this->url_form;
    }

    public function getSourceAttribute($value)
    {
        if (strtolower($this->type) === 'dokumen') {
            if (empty($value)) {
                return [];
            }
            
            $decoded = json_decode($value, true);
            $paths = is_array($decoded) ? $decoded : [$value];
            
            $result = [];
            foreach ($paths as $path) {
                $fullPath = base_path('public/' . $path);
                if (is_dir($fullPath)) {
                    $files = glob($fullPath . '/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE);
                    if (is_array($files)) {
                        natsort($files);
                        foreach ($files as $file) {
                            $result[] = $path . '/' . basename($file);
                        }
                    }
                } else {
                    $result[] = $path;
                }
            }
            return $result;
        }
        return $value;
    }

    public function setSourceAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['source'] = json_encode($value);
        } else {
            $this->attributes['source'] = $value;
        }
    }

    public function getUploaderAttribute($value)
    {
        if (empty($value)) {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setUploaderAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['uploader'] = json_encode($value);
        } else {
            $this->attributes['uploader'] = $value;
        }
    }
}
