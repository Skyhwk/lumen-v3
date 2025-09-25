<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataKandidat extends Sector
{
    protected $table = 'recruitment';
    public $timestamps = false;
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'id_cabang', 'id');
    }

    public function jabatan()
    {
        return $this->belongsTo(MasterJabatan::class, 'bagian_di_lamar', 'id');
    }

    public function department()
    {
        return $this->belongsTo(MasterDivisi::class, 'id_department');
    }
    public function review_user()
    {
        return $this->belongsTo(ReviewUser::class, 'id_review_user');
    }
    public function review_recruitment()
    {
        return $this->belongsTo(ReviewRecruitment::class, 'id_review_recruitment');
    }
    public function offering_salary()
    {
        return $this->belongsTo(OfferingSalary::class, 'id_salary');
    }

    public function approve_hrd()
    {
        return $this->belongsTo(MasterKaryawan::class, 'approve_interview_hrd_by', 'id');
    }
    public function approve_user()
    {
        return $this->belongsTo(MasterKaryawan::class, 'approve_interview_user_by', 'id');
    }
}
