<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{

    protected $user_id;
    protected $nama_lengkap;
    protected $privilageCabang;
    protected $grade;
    protected $db;

    public function __construct(Request $request)
    {
        $userId = null;
        $name_req = null;
        $cabang = null;
        $privilageCabang = null;
        $grade = null;
        $id_department = null;
        $department = null;
        if ($request->attributes->has('user')) {
            $user = $request->attributes->get('user');
            if (isset($user->karyawan) && $user->karyawan != null) {
                $name_req = $user->karyawan->nama_lengkap;
                $userId = $user->karyawan->id;
                $cabang = $user->karyawan->id_cabang;
                $id_department = $user->karyawan->id_department;
                $grade = $user->karyawan->grade;
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
        $this->grade = $grade;
        $this->privilageCabang = $privilageCabang;
        $this->db = DATE('Y');

    }
    protected function ensureSamplerCheckedInForSample(Request $request, $field = 'no_sample')
    {
        $sample = $request->input($field) ?: $request->input('no_sampel');
        $sample = strtoupper(trim((string) $sample));

        if ($sample === '') {
            return null;
        }

        $orderDetail = \App\Models\OrderDetail::where('no_sampel', $sample)
            ->where('is_active', true)
            ->first();

        if (!$orderDetail) {
            return null;
        }

        $date = $request->input('tanggal_sampling')
            ?: $request->input('tanggal')
            ?: ($orderDetail->tanggal_sampling ?: date('Y-m-d'));

        $samplerName = $this->karyawan;
        $hasCheckin = \App\Models\SamplerTrackingSession::where('is_active', true)
            ->whereDate('tanggal_sampling', $date)
            ->where(function ($query) use ($orderDetail) {
                if ($orderDetail->no_order) {
                    $query->where('no_order', $orderDetail->no_order);
                }

                if (!empty($orderDetail->no_quotation)) {
                    $query->orWhere('no_quotation', $orderDetail->no_quotation);
                }
            })
            ->whereHas('activeMembers', function ($query) use ($samplerName) {
                $query->where('sampler_name', 'like', '%' . $samplerName . '%')
                    ->whereHas('events', function ($eventQuery) {
                        $eventQuery->where('event_type', 'checkin');
                    });
            })
            ->exists();

        if ($hasCheckin) {
            return null;
        }

        return response()->json([
            'message' => 'Kamu belum check in di lokasi sampling untuk nomor sampel ' . $sample . '. Silakan check in terlebih dahulu sebelum cek/input nomor sampel.',
        ], 401);
    }

}
