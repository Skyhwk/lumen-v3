<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use Illuminate\Support\Collection;

class GetBawahan
{
    protected $query;
    protected $karyawan;

    /** @var array<int, array<int, array<int, MasterKaryawan>>> */
    protected static $subordinateMaps = [];

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
     * Indeks bawahan di-cache per request agar tidak N+1 query per level.
     */
    protected function collectDescendants(int $rootId, bool $activeOnly = true): Collection
    {
        $descendants = collect([]);
        $queue = [$rootId];
        $visited = [$rootId => true];
        $mapKey = $activeOnly ? 1 : 0;
        $subordinateMap = self::$subordinateMaps[$mapKey] ?? null;

        if ($subordinateMap === null) {
            $subordinateMap = self::buildSubordinateMap($activeOnly);
            self::$subordinateMaps[$mapKey] = $subordinateMap;
        }

        while (!empty($queue)) {
            $supervisorId = array_shift($queue);

            foreach ($subordinateMap[$supervisorId] ?? [] as $subordinate) {
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

    protected static function buildSubordinateMap(bool $activeOnly): array
    {
        $query = MasterKaryawan::query();
        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        $map = [];
        foreach ($query->get() as $employee) {
            foreach (self::parseAtasanIds($employee->atasan_langsung ?? '[]') as $supervisorId) {
                $map[$supervisorId][] = $employee;
            }
        }

        return $map;
    }

    protected static function parseAtasanIds($atasanLangsung): array
    {
        $decoded = is_array($atasanLangsung)
            ? $atasanLangsung
            : (json_decode($atasanLangsung ?? '[]', true) ?? []);

        return array_values(array_filter(array_map('intval', $decoded)));
    }
}
