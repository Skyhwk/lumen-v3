<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PencadanganUpah extends Sector{
    protected $table = 'pencadangan_upah';
    protected $guard = [];

    
    protected $fillable = [
        'karyawan',
        'nik_karyawan',
        'id_karyawan',
        'tenor',
        'tipe_pengembalian',
        'nominal',
        'tenor_berjalan',
        'nominal_berjalan',
        'bulan_efektif',
        'status',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'previous_id',
        'is_active',
        'perubahan_log',
    ];

    protected $casts = [
        'perubahan_log' => 'array',
    ];

    public $timestamps = false;

    /**
     * Catat perubahan tenor_berjalan dan nominal_berjalan pada baris yang sama.
     * Payroll cancel membandingkan id baris ini, jadi log tidak boleh pindah ke baris baru.
     */
    public function appendPerubahanLog(?string $actor, string $source, ?int $payrollId, array $before): void
    {
        $tenorSame = (int) ($before['tenor_berjalan'] ?? 0) === (int) $this->tenor_berjalan;
        $nominalSame = sprintf('%.2f', (float) ($before['nominal_berjalan'] ?? 0))
            === sprintf('%.2f', (float) $this->nominal_berjalan);
        if ($tenorSame && $nominalSame) {
            return;
        }

        $log = is_array($this->perubahan_log) ? $this->perubahan_log : [];
        $log[] = [
            'tenor_berjalan_before' => $before['tenor_berjalan'] ?? null,
            'tenor_berjalan_after' => $this->tenor_berjalan,
            'nominal_berjalan_before' => $before['nominal_berjalan'] ?? null,
            'nominal_berjalan_after' => $this->nominal_berjalan,
            'source' => $source,
            'payroll_id' => $payrollId,
            'created_by' => $actor,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->perubahan_log = $log;
    }

    public function masterKaryawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'id_karyawan', 'id');
    }
}
