<?php

namespace App\Services;

use App\Models\BpjsKesehatan;
use App\Models\BpjsTk;
use App\Models\MasterKaryawan;
use App\Models\MasterSallary;
use App\Models\PPH21;

class EmployeeCompensationSnapshotService
{
    public function resolveForEmployee(MasterKaryawan $employee): array
    {
        $salary = MasterSallary::query()
            ->where('is_active', true)
            ->where(function ($query) use ($employee) {
                $query->where('nik_karyawan', $employee->nik_karyawan)
                    ->orWhere('karyawan', $employee->nama_lengkap);
            })
            ->orderByDesc('id')
            ->first();

        $gajiPokok = (float) ($salary->gaji_pokok ?? 0);
        $tunjangan = (float) ($salary->tunjangan_kerja ?? 0);

        $bpjsTk = BpjsTk::query()
            ->where('is_active', true)
            ->where('nik_karyawan', $employee->nik_karyawan)
            ->orderByDesc('id')
            ->first();

        $bpjsKesehatan = BpjsKesehatan::query()
            ->where('is_active', true)
            ->where('nik_karyawan', $employee->nik_karyawan)
            ->orderByDesc('id')
            ->first();

        $pph21 = PPH21::query()
            ->where('is_active', true)
            ->where('nik_karyawan', $employee->nik_karyawan)
            ->orderByDesc('id')
            ->first();

        $bpjsTkRate = (float) ($bpjsTk->potongan_karyawan ?? 0);
        $bpjsKesRate = (float) ($bpjsKesehatan->potongan_karyawan ?? 0);
        $bpjsTkAmount = (float) ($bpjsTk->nominal_potongan_karyawan ?? 0);
        $bpjsKesAmount = (float) ($bpjsKesehatan->nominal_potongan_karyawan ?? 0);
        $pph21Amount = (float) ($pph21->pajak_bulanan ?? 0);

        return [
            'gaji_pokok' => $gajiPokok,
            'tunjangan' => $tunjangan,
            'fee' => null,
            'bpjs_tk' => $bpjsTkAmount,
            'bpjs_kesehatan' => $bpjsKesAmount,
            'pph21' => $pph21Amount,
            'bpjs_tk_rate' => $bpjsTkRate,
            'bpjs_kesehatan_rate' => $bpjsKesRate,
            'take_home_pay' => $gajiPokok + $tunjangan,
        ];
    }

    public function project(float $gajiPokok, float $tunjangan, array $baseSnapshot): array
    {
        $bpjsTkRate = (float) ($baseSnapshot['bpjs_tk_rate'] ?? 0);
        $bpjsKesRate = (float) ($baseSnapshot['bpjs_kesehatan_rate'] ?? 0);
        $baseGaji = (float) ($baseSnapshot['gaji_pokok'] ?? 0);
        $basePph21 = (float) ($baseSnapshot['pph21'] ?? 0);
        $gajiUnchanged = round($gajiPokok) === round($baseGaji);

        $bpjsTk = $gajiUnchanged
            ? (float) ($baseSnapshot['bpjs_tk'] ?? 0)
            : ($bpjsTkRate > 0
                ? round($gajiPokok * $bpjsTkRate, 0)
                : (float) ($baseSnapshot['bpjs_tk'] ?? 0));

        $bpjsKesehatan = $gajiUnchanged
            ? (float) ($baseSnapshot['bpjs_kesehatan'] ?? 0)
            : ($bpjsKesRate > 0
                ? round($gajiPokok * $bpjsKesRate, 0)
                : (float) ($baseSnapshot['bpjs_kesehatan'] ?? 0));

        $pph21 = $gajiUnchanged
            ? $basePph21
            : ($baseGaji > 0 && $basePph21 > 0
                ? round($basePph21 * ($gajiPokok / $baseGaji), 0)
                : $basePph21);

        return [
            'gaji_pokok' => $gajiPokok,
            'tunjangan' => $tunjangan,
            'fee' => null,
            'bpjs_tk' => $bpjsTk,
            'bpjs_kesehatan' => $bpjsKesehatan,
            'pph21' => $pph21,
            'take_home_pay' => $gajiPokok + $tunjangan,
        ];
    }
}
