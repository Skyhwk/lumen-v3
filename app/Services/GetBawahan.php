<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use Illuminate\Support\Collection;

class GetBawahan
{
    protected $query;
    protected $karyawan;

    public static function where($field, $value)
    {
        $instance = new static();
        $instance->query = MasterKaryawan::where($field, $value);
        return $instance;
    }

    public static function on($field, $value)
    {
        $instance = new static();
        $instance->query = MasterKaryawan::where($field, $value);
        return $instance;
    }

    public function get()
    {
        $this->karyawan = $this->query->first();

        if (!$this->karyawan) {
            return collect([]);
        }

        return collect([$this->karyawan])->merge(
            $this->collectDescendants($this->karyawan->id, true)
        );
    }

    public function all()
    {
        $this->karyawan = $this->query->first();

        if (!$this->karyawan) {
            return collect([]);
        }

        return collect([$this->karyawan])->merge(
            $this->collectDescendants($this->karyawan->id, false)
        );
    }

    /**
     * Ambil seluruh turunan secara dinamis berdasarkan atasan_langsung (BFS).
     * Tidak bergantung pada grade — mendukung struktur hirarki fleksibel.
     */
    protected function collectDescendants(int $rootId, bool $activeOnly = true): Collection
    {
        $descendants = collect([]);
        $queue = [$rootId];
        $visited = [$rootId => true];

        while (!empty($queue)) {
            $supervisorId = array_shift($queue);

            $query = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $supervisorId);

            if ($activeOnly) {
                $query->where('is_active', 1);
            }

            foreach ($query->get() as $subordinate) {
                if (isset($visited[$subordinate->id])) {
                    continue;
                }

                $visited[$subordinate->id] = true;
                $descendants->push($subordinate);
                $queue[] = $subordinate->id;
            }
        }

        return $descendants;
    }
}
