<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterKaryawan extends Sector
{

    protected $table = 'master_karyawan';
    protected $guarded = [];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->where('is_active', true);
    }

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'id_cabang');
    }

    public function jabatan()
    {
        return $this->belongsTo(MasterJabatan::class, 'id_jabatan');
    }

    public function divisi()
    {
        return $this->belongsTo(MasterDivisi::class, 'id_department');
    }

    public function department()
    {
        return $this->belongsTo(MasterDivisi::class, 'id_department');
    }

    public function medical()
    {
        return $this->hasOne(MedicalCheckup::class, 'karyawan_id');
    }

    public function getSubordinates($id)
    {
        $subordinates = $this->whereJsonContains('atasan_langsung', (string) $id)->where('is_active', true)->get();
        $result = [];

        foreach ($subordinates as $subordinate) {
            $result[] = [
                'name' => $subordinate->nama_lengkap,
                'image' => $subordinate->image,
                'children' => $this->getSubordinates($subordinate->id)
            ];
        }

        return $result;
    }

    public function getHierarchy($id)
    {
        $employee = $this->find($id);

        if (!$employee) {
            return null;
        }

        return [
            'name' => $employee->nama_lengkap,
            'image' => $employee->image,
            'children' => $this->getSubordinates($employee->id)
        ];
    }

    public function getFullHierarchy($id)
    {
        $employee = $this->find($id);

        if (!$employee) {
            return null;
        }

        $superiors = json_decode($employee->atasan_langsung, true);
        $fullHierarchy = [
            'name' => $employee->nama_lengkap,
            'image' => $employee->image,
            'children' => $this->getSubordinates($employee->id)
        ];

        if (!empty($superiors)) {
            foreach ($superiors as $superiorId) {
                $superiorHierarchy = $this->getHierarchy($superiorId);
                if ($superiorHierarchy) {
                    $fullHierarchy = [
                        'name' => $superiorHierarchy['name'],
                        'image' => $superiorHierarchy['image'],
                        'children' => [$fullHierarchy]
                    ];
                }
            }
        }

        return $fullHierarchy;
    }

    public function getLeaderboard($focusId)
    {
        return $this->getFullHierarchy($focusId);
    }

    public function kontak_darurat()
    {
        return $this->hasMany(KontakDaruratKaryawan::class, 'karyawan_id');
    }
    public function pendidikan_karyawan()
    {
        return $this->hasMany(PendidikanKaryawan::class, 'karyawan_id');
    }
    public function sertifikat_karyawan()
    {
        return $this->hasMany(DataSertifikatKaryawan::class, 'karyawan_id');
    }
    public function pengalaman_kerja()
    {
        return $this->hasMany(PengalamanKerjaKaryawan::class, 'karyawan_id');
    }
    // public function rekap()
    // {
    //     return $this->hasMany(RekapMasukKerja::class, 'karyawan_id', 'id')->where('is_active', true);
    // }
    public function rekap()
    {
        return $this->hasMany(RekapMasukKerja::class, 'karyawan_id');
    }

    public function rfid()
    {
        return $this->hasOne(RfidCard::class, 'userid', 'user_id');
    }

    public function salary()
    {
        return $this->hasOne(MasterSallary::class, 'nik_karyawan', 'nik_karyawan')->where('is_active', true);
    }

    public function bpjsKesehatan()
    {
        return $this->hasOne(BpjsKesehatan::class, 'nik_karyawan', 'nik_karyawan')
            ->where('is_active', true)
            ->select('nominal_potongan_karyawan', 'nik_karyawan');
    }

    public function bpjsTk()
    {
        return $this->hasOne(BpjsTK::class, 'nik_karyawan', 'nik_karyawan')
            ->where('is_active', true)
            ->select('nominal_potongan_karyawan', 'nik_karyawan');
    }

    public function pph21()
    {
        return $this->hasOne(PPH21::class, 'nik_karyawan', 'nik_karyawan')
            ->where('is_active', true)
            ->select('pajak_bulanan', 'nik_karyawan');
    }

    public function loan()
    {
        return $this->hasMany(Kasbon::class, 'nik_karyawan', 'nik_karyawan')->where('is_active', true);
    }

    public function denda()
    {
        return $this->hasMany(DendaKaryawan::class, 'nik_karyawan', 'nik_karyawan')->where('is_active', true);
    }
}
