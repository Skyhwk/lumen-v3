<?php
namespace App\Services;

use App\Models\MasterKaryawan;

class GetAtasan
{
    protected $query;
    protected $atasan = [];
    protected $karyawan;

    public static function where($field, $value)
    {
        $instance = new static();
        // $instance->query = MasterKaryawan::where($field, $value)->where('is_active', 1);
        $instance->query = MasterKaryawan::where($field, $value);
        return $instance;
    }

    public function get()
    {
        $this->karyawan = $this->query->first();
        if (!$this->karyawan) {
            return collect([]);
        }

        $result = collect([$this->karyawan]);

        $atasan_langsung = json_decode($this->karyawan->atasan_langsung, true);
        if (!empty($atasan_langsung)) {
            $atasan = MasterKaryawan::whereIn('id', $atasan_langsung)
                ->where('is_active', 1)
                ->where('id', '!=', 1)
                ->get();
            foreach ($atasan as $item) {
                if ($item->nama_lengkap == 'Siti Nur Faidhah' || $item->nama_lengkap == 'Reiko Nishio Yana Gita Sinaga')
                    continue;
                $this->atasan[] = $item;
            }
            $all_supervisor_ids = [];
            foreach ($atasan as $item) {
                $atasan_supervisor = json_decode($item->atasan_langsung, true);
                if (!empty($atasan_supervisor)) {
                    $all_supervisor_ids = array_merge($all_supervisor_ids, $atasan_supervisor);
                }
            }
            if (!empty($all_supervisor_ids)) {
                $upper_atasan = MasterKaryawan::whereIn('id', $all_supervisor_ids)
                    ->where('is_active', 1)
                    ->where('id', '!=', 1)
                    ->get();

                foreach ($upper_atasan as $upper) {
                    if ($upper->nama_lengkap == 'Siti Nur Faidhah' || $upper->nama_lengkap == 'Reiko Nishio Yana Gita Sinaga')
                        continue;
                    $this->atasan[] = $upper;
                }
            }
        }

        if (!empty($this->atasan)) {
            return $result->merge(collect($this->atasan)->unique('id')->values());
        } else {
            return $result->unique('id')->values();
        }

    }
}
