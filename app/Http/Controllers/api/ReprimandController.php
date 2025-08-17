<?php
namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use App\Models\{Reprimand, MasterKaryawan};
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\DB;


class ReprimandController extends Controller
{
    public function index(Request $request)
    {
        $data = Reprimand::with('user', 'reqby', 'approveby')
            ->where('is_active', true)
            ->get();

        return Datatables::of($data)->make(true);
    }
    public function store(Request $request)
    {
        $timestamp = date('Y-m-d H:i:s');
        DB::beginTransaction();
        try {
            if (isset($request->id_user) && $request->id_user != null) {
                $data = new Reprimand;

                if ($request->id_user != '')
                    $data->id_user = $request->id_user;
                if ($request->keterangan != '')
                    $data->keterangan = $request->keterangan;
                if ($request->sp != '')
                    $data->sp = $request->sp;
                if ($request->expired_date != '')
                    $data->expired_date = $request->expired_date;
                $data->request_at = $timestamp;
                $data->request_by = $this->user_id;
                $data->add_at = $timestamp;
                $data->add_by = $this->user_id;
                $data->save();

                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Reprimand created successfully.'
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Cannot Add Reprimand.!'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'failed',
                'message' => 'An error occurred while trying to add Reprimand: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approve(Request $request)
    {
        $searchYear = $request->search;
        $db = isset($searchYear) ? date('Y', strtotime($searchYear)) : $this->db;
        $timestamp = date('Y-m-d H:i:s');
        DB::beginTransaction();
        try {
            if ($request->id != '' || isset($request->id)) {
                $data = Reprimand::where('id', $request->id)->first();
                if (!is_null($data)) {
                    $data->approve_by = $this->user_id;
                    $data->approve_at = $timestamp;
                    $data->save();

                    DB::commit();
                    return response()->json([
                        'message' => 'Approve Success!',
                    ], 200);
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Reprimand not found!'
                    ], 401);
                }
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Cannot Approve Reprimand!'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while trying to approve Reprimand: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delete()
    {
        $data = Reprimand::where('is_active', true)->get();
        foreach ($data as $key => $value) {
            $startDate = date_create_from_format('Y-m-d', date('Y-m-d', strtotime($value->add_at)));
            $endDate = date_create_from_format('Y-m-d', $value->expired_date);
            $check = (date('Y-m-d') >= $startDate->format('Y-m-d') && date('Y-m-d') <= $endDate->format('Y-m-d'));
            if ($check == false) {
                $del = Reprimand::where('id', $value->id)->first();
                $del->is_active = false;
                $del->save();
            }
        }
    }

    public function cmbUser(Request $request)
    {
        $searchYear = $request->search;
        $db = isset($searchYear) ? date('Y', strtotime($searchYear)) : $this->db;
        $atasan = json_decode($request->value);
        if (!is_array($atasan)) {
            $atasan = array($request->value);
        }
        $data = MasterKaryawan::select('id', 'nama_lengkap', 'nik_karyawan')
            ->where('is_active', $request->active)
            ->get();
        return response()->json([
            'data' => $data
        ], 200);
    }
}
