<?php

namespace App\Services;

use App\Models\RejectedFdl;
use Carbon\Carbon;

class RejectFdlService
{
    /**
     * Record a rejected FDL entry.
     *
     * @param mixed $data
     * @param string $karyawan
     * @param string|null $noteReject
     * @param string $kategoriFdl
     * @return RejectedFdl
     */
    public function recordReject($data, $karyawan, $noteReject, $kategoriFdl)
    {
        $namaTitik = null;
        if (isset($data->keterangan)) {
            $namaTitik = $data->keterangan;
        } elseif (isset($data->keterangan_1)) {
            $namaTitik = $data->keterangan_1;
        } elseif (isset($data->keterangan_2)) {
            $namaTitik = $data->keterangan_2;
        }

        return RejectedFdl::create([
            'no_sampel' => $data->no_sampel ?? null,
            'nama_titik' => $namaTitik,
            'tanggal_sampling' => $data->created_at ?? Carbon::now(),
            'nama_sampler' => $data->created_by ?? null,
            'kategori_fdl' => $kategoriFdl,
            'note_reject' => $noteReject,
            'rejected_at' => Carbon::now(),
            'rejected_by' => $karyawan,
        ]);
    }
}
