<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{

    protected $user_id;
    protected $nama_lengkap;
    protected $privilageCabang;
    protected $db;

    public function __construct(Request $request)
    {
        $userId = null;
        $name_req = null;
        $cabang = null;
        $privilageCabang = null;
        $department = null;
        if ($request->attributes->has('user')) {
            $user = $request->attributes->get('user');
            if (isset($user->karyawan) && $user->karyawan != null) {
                $name_req = $user->karyawan->nama_lengkap;
                $userId = $user->karyawan->id;
                $cabang = $user->karyawan->id_cabang;
                $id_department = $user->karyawan->id_department;
                $privilageCabang = json_decode($user->karyawan->privilage_cabang);
            } else {
                $name_req = $user->email;
                $privilageCabang = [];
                $id_department = null;
            }

        } else {
            $name_req = $request->header('token');
        }

        $this->user_id = $userId;
        $this->karyawan = $name_req;
        $this->idcabang = $cabang;
        $this->department = $id_department;
        $this->privilageCabang = $privilageCabang;
        $this->db = DATE('Y');

    }
}