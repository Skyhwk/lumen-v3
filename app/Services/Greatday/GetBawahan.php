<?php

namespace App\Services\Greatday;

use App\Models\MasterKaryawan;

class GetBawahan
{
    protected $query;

    protected $karyawan;

    protected $hirarki;

    public static function where($field, $value)
    {
        $instance = new static();
        $instance->query = MasterKaryawan::where($field, $value);

        return $instance;
    }

    public static function on($field, $value)
    {
        return self::where($field, $value);
    }

    public function get()
    {
        $this->karyawan = $this->query->first();

        if (!$this->karyawan) {
            return collect([]);
        }

        if ($this->karyawan->grade === 'MANAGER' || $this->karyawan->grade === 'SENIOR MANAGER') {
            $this->hirarki = 3;
        } elseif ($this->karyawan->grade === 'SUPERVISOR') {
            $this->hirarki = 2;
        } else {
            $this->hirarki = 1;
        }

        $dataBawahanlevel1 = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->karyawan->id)
            ->where('is_active', 1)
            ->get();

        $dataBawahanlevel2 = collect([]);
        $dataBawahanlevel3 = collect([]);

        if ($this->hirarki >= 2) {
            foreach ($dataBawahanlevel1 as $bawahan) {
                if ($bawahan->grade === 'SUPERVISOR' || $this->hirarki === 3) {
                    $bawahanLevel2 = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $bawahan->id)
                        ->where('is_active', 1)
                        ->get();

                    $dataBawahanlevel2 = $dataBawahanlevel2->merge($bawahanLevel2);

                    if ($this->hirarki === 3) {
                        foreach ($bawahanLevel2 as $staff) {
                            $bawahanLevel3 = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $staff->id)
                                ->where('is_active', 1)
                                ->get();

                            $dataBawahanlevel3 = $dataBawahanlevel3->merge($bawahanLevel3);
                        }
                    }
                }
            }
        }

        return collect([$this->karyawan])->merge($dataBawahanlevel1)->merge($dataBawahanlevel2)->merge($dataBawahanlevel3);
    }

    public function all()
    {
        $this->karyawan = $this->query->first();

        if (!$this->karyawan) {
            return collect([]);
        }

        if ($this->karyawan->grade === 'MANAGER' || $this->karyawan->grade === 'SENIOR MANAGER') {
            $this->hirarki = 3;
        } elseif ($this->karyawan->grade === 'SUPERVISOR') {
            $this->hirarki = 2;
        } else {
            $this->hirarki = 1;
        }

        $dataBawahanlevel1 = MasterKaryawan::whereNotIn('grade', ['MANAGER'])
            ->whereJsonContains('atasan_langsung', (string) $this->karyawan->id)
            ->get();

        $dataBawahanlevel2 = collect([]);
        $dataBawahanlevel3 = collect([]);

        if ($this->hirarki >= 2) {
            foreach ($dataBawahanlevel1 as $bawahan) {
                if ($bawahan->grade === 'SUPERVISOR' || $this->hirarki === 3) {
                    $bawahanLevel2 = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $bawahan->id)->get();

                    $dataBawahanlevel2 = $dataBawahanlevel2->merge($bawahanLevel2);

                    if ($this->hirarki === 3) {
                        foreach ($bawahanLevel2 as $staff) {
                            $bawahanLevel3 = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $staff->id)->get();

                            $dataBawahanlevel3 = $dataBawahanlevel3->merge($bawahanLevel3);
                        }
                    }
                }
            }
        }

        return collect([$this->karyawan])->merge($dataBawahanlevel1)->merge($dataBawahanlevel2)->merge($dataBawahanlevel3);
    }
}
