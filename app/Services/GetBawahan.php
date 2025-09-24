<?php

namespace App\Services;

use App\Models\MasterKaryawan;

class GetBawahan
{
    protected $query;
    protected $karyawan;
    protected $hirarki;

    public static function where($field, $value)
    {
        $instance = new static();
        // $instance->query = MasterKaryawan::where($field, $value)->where('is_active', 1);
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
        // Ambil data atasan (Manager, Supervisor, atau Staff)
        $this->karyawan = $this->query->first();

        if (!$this->karyawan) {
            return collect([]);
        }

        // Tentukan jumlah hirarki berdasarkan level atasan
        if ($this->karyawan->grade === 'MANAGER') {
            $this->hirarki = 3; // Manager → Supervisor → Staff
        } elseif ($this->karyawan->grade === 'SUPERVISOR') {
            $this->hirarki = 2; // Supervisor → Staff
        } else {
            $this->hirarki = 1; // Staff (tidak punya bawahan)
        }

        // Ambil semua bawahan langsung
        $dataBawahanlevel1 = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->karyawan->id)
            ->where('is_active', 1)
            ->get();

        $dataBawahanlevel2 = collect([]);
        $dataBawahanlevel3 = collect([]);

        if ($this->hirarki >= 2) {
            foreach ($dataBawahanlevel1 as $bawahan) {
                if ($bawahan->grade === 'SUPERVISOR' || $this->hirarki === 3) {
                    // Ambil semua staff di bawah supervisor
                    $bawahanLevel2 = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $bawahan->id)
                        ->where('is_active', 1)
                        ->get();

                    $dataBawahanlevel2 = $dataBawahanlevel2->merge($bawahanLevel2);
                    // $bawahan->bawahan_level2 = $bawahanLevel2;

                    // Jika hirarki 3, ambil staff di bawah supervisor yang ada di level 2
                    if ($this->hirarki === 3) {
                        foreach ($bawahanLevel2 as $staff) {
                            $bawahanLevel3 = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $staff->id)
                            ->where('is_active', 1)
                            ->get();

                            $dataBawahanlevel3 = $dataBawahanlevel3->merge($bawahanLevel3);
                            // $staff->bawahan_level3 = $bawahanLevel3;
                        }
                    }
                }
            }
        }
        return collect([$this->karyawan])->merge($dataBawahanlevel1)->merge($dataBawahanlevel2)->merge($dataBawahanlevel3);
    }
    
    public function all()
    {
        // Ambil data atasan (Manager, Supervisor, atau Staff)
        $this->karyawan = $this->query->first();

        if (!$this->karyawan) {
            return collect([]);
        }

        // Tentukan jumlah hirarki berdasarkan level atasan
        if ($this->karyawan->grade === 'MANAGER') {
            $this->hirarki = 3; // Manager → Supervisor → Staff
        } elseif ($this->karyawan->grade === 'SUPERVISOR') {
            $this->hirarki = 2; // Supervisor → Staff
        } else {
            $this->hirarki = 1; // Staff (tidak punya bawahan)
        }

        // Ambil semua bawahan langsung
        $dataBawahanlevel1 = MasterKaryawan::whereNotIn('grade', ['MANAGER'])->whereJsonContains('atasan_langsung', (string) $this->karyawan->id)->get();

        $dataBawahanlevel2 = collect([]);
        $dataBawahanlevel3 = collect([]);

        if ($this->hirarki >= 2) {
            foreach ($dataBawahanlevel1 as $bawahan) {
                if ($bawahan->grade === 'SUPERVISOR' || $this->hirarki === 3) {
                    // Ambil semua staff di bawah supervisor
                    $bawahanLevel2 = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $bawahan->id)->get();

                    $dataBawahanlevel2 = $dataBawahanlevel2->merge($bawahanLevel2);
                    // $bawahan->bawahan_level2 = $bawahanLevel2;

                    // Jika hirarki 3, ambil staff di bawah supervisor yang ada di level 2
                    if ($this->hirarki === 3) {
                        foreach ($bawahanLevel2 as $staff) {
                            $bawahanLevel3 = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $staff->id)->get();

                            $dataBawahanlevel3 = $dataBawahanlevel3->merge($bawahanLevel3);
                            // $staff->bawahan_level3 = $bawahanLevel3;
                        }
                    }
                }
            }
        }
        return collect([$this->karyawan])->merge($dataBawahanlevel1)->merge($dataBawahanlevel2)->merge($dataBawahanlevel3);
    }
}
