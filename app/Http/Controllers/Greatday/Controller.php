<?php

namespace App\Http\Controllers\Greatday;

use App\Services\Hr\HrTableMode;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

/**
 * Base controller greatday — auth user = MasterKaryawan (Bearer via GreatdayCheckToken).
 */
class Controller extends BaseController
{
    use Concerns\RestrictsToAtasanWorkflow;

    /** @var \App\Models\MasterKaryawan|null */
    protected $karyawan;

    protected $user_id;

    protected $nama_lengkap;

    protected $privilageCabang;

    protected $idcabang;

    protected $grade;

    protected $db;

    public function __construct(Request $request)
    {
        $employee = $request->attributes->get('greatday_karyawan');
        $this->karyawan = $employee;

        if ($employee && isset($employee->nama_lengkap)) {
            $this->user_id = $employee->id;
            $this->nama_lengkap = $employee->nama_lengkap;
            $this->idcabang = $employee->id_cabang;
            $this->grade = $employee->grade ?? null;
            $this->privilageCabang = json_decode($employee->privilage_cabang ?? '[]', true) ?: [];
        } else {
            $this->user_id = null;
            $this->nama_lengkap = $request->bearerToken();
            $this->privilageCabang = [];
        }

        $this->db = date('Y');
    }

    protected function usesHrTables(): bool
    {
        return !HrTableMode::usesLegacyHrTables();
    }
}
