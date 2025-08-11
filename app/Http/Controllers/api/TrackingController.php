<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TrackingController extends Controller
{
    public function index(Request $request)
    {
        $data = OrderDetail::with(['TrackingSatu', 'TrackingDua'])
            ->select('id', 'no_sampel', 'tanggal_terima', 'nama_perusahaan', 'cfr', 'keterangan_1', 'keterangan_2')
            ->whereHas('TrackingSatu')
            ->whereHas('TrackingDua')
            ->where('is_active', true)
            ->orderBy('id', 'DESC');

        if ($request->searchTerm != '' && $request->mode != '') {
            switch ($request->mode) {
                case 'byOrder':
                    $data->where('no_order', 'like', "%" . $request->searchTerm . "%");
                    break;
                case 'byCompany':
                    $data->Where('nama_perusahaan', 'like', "%" . $request->searchTerm . "%");
                    break;
            }
        } else {
            return response()->json(['message' => 'Kolom dan Tipe Pencarian Tidak Boleh Kosong.!'], 403);
        }

        return response()->json([
            'data' => $data->get(),
            'status' => true
        ], 200);
    }

    public function detailTracking(Request $request)
    {
        try {
            $data = OrderDetail::with(['TrackingSatu', 'TrackingDua'])->findOrFail($request->id);

            $ftc = $data->TrackingSatu;
            $ftct = $data->TrackingDua;
            $a = '-';
            $b = '-';
            $c = '-';
            $d = '-';
            $e = '-';
            $f = '-';
            $g = '-';
            $h = '-';
            $i = '-';
            $j = '-';
            $k = '-';
            $l = '-';
            $m = '-';
            $n = '-';
            $o = '-';
            $p = '-';
            $q = '-';
            $r = '-';
            $s = '-';
            $t = '-';
            $u = '-';
            $v = '-';
            $w = '-';

            if ($ftct->ftc_lhp_distribute)
                $b = date('d F Y ( D ) H:i:s', strtotime($ftct->ftc_lhp_distribute) + 60 * 60 * 7);
            if ($ftct->ftc_lhp_finance)
                $c = date('d F Y ( D ) H:i:s', strtotime($ftct->ftc_lhp_finance) + 60 * 60 * 7);
            if ($ftct->ftc_lhp_approval)
                $d = date('d F Y ( D ) H:i:s', strtotime($ftct->ftc_lhp_approval) + 60 * 60 * 7);
            if ($ftct->ftc_lhp_verifier_a)
                $e = date('d F Y ( D ) H:i:s', strtotime($ftct->ftc_lhp_verifier_a) + 60 * 60 * 7);
            if ($ftct->ftc_lhp_print)
                $f = date('d F Y ( D ) H:i:s', strtotime($ftct->ftc_lhp_print) + 60 * 60 * 7);
            if ($ftct->ftc_lhp_verifier)
                $g = date('d F Y ( D ) H:i:s', strtotime($ftct->ftc_lhp_verifier) + 60 * 60 * 7);
            if ($ftct->ftc_lhp_request)
                $h = date('d F Y ( D ) H:i:s', strtotime($ftct->ftc_lhp_request) + 60 * 60 * 7);
            if ($ftct->ftc_lhp_release_request)
                $i = date('d F Y ( D ) H:i:s', strtotime($ftct->ftc_lhp_release_request) + 60 * 60 * 7);
            if ($ftct->ftc_draft_send_a)
                $j = date('d F Y ( D ) H:i:s', strtotime($ftct->ftc_draft_send_a) + 60 * 60 * 7);
            if ($ftct->ftc_draft_send)
                $k = date('d F Y ( D ) H:i:s', strtotime($ftct->ftc_draft_send) + 60 * 60 * 7);
            if ($ftc->ftc_draft_verifier)
                $l = date('d F Y ( D ) H:i:s', strtotime($ftc->ftc_draft_verifier) + 60 * 60 * 7);
            if ($ftc->ftc_draft_tc_result_2)
                $m = date('d F Y ( D ) H:i:s', strtotime($ftc->ftc_draft_tc_result_2) + 60 * 60 * 7);
            if ($ftc->ftc_draft_tc_result)
                $n = date('d F Y ( D ) H:i:s', strtotime($ftc->ftc_draft_tc_result) + 60 * 60 * 7);
            if ($ftc->ftc_draft_admin)
                $o = date('d F Y ( D ) H:i:s', strtotime($ftc->ftc_draft_admin) + 60 * 60 * 7);
            if ($ftc->ftc_analysis_admin)
                $p = date('d F Y ( D ) H:i:s', strtotime($ftc->ftc_analysis_admin) + 60 * 60 * 7);
            if ($ftc->ftc_analysis_result_lab)
                $q = date('d F Y ( D ) H:i:s', strtotime($ftc->ftc_analysis_result_lab) + 60 * 60 * 7);
            if ($ftc->ftc_fd_lab)
                $r = date('d F Y ( D ) H:i:s', strtotime($ftc->ftc_fd_lab) + 60 * 60 * 7);
            if ($ftc->ftc_fd_sampling)
                $s = date('d F Y ( D ) H:i:s', strtotime($ftc->ftc_fd_sampling) + 60 * 60 * 7);
            if ($ftc->ftc_fd_laboratory)
                $t = date('d F Y ( D ) H:i:s', strtotime($ftc->ftc_fd_laboratory) + 60 * 60 * 7);
            if ($ftc->ftc_verifier)
                $u = date('d F Y ( D ) H:i:s', strtotime($ftc->ftc_verifier) + 60 * 60 * 7);
            if ($ftc->ftc_sample)
                $v = date('d F Y ( D ) H:i:s', strtotime($ftc->ftc_sample) + 60 * 60 * 7);
            if ($ftc->ftc_sd)
                $w = date('d F Y ( D ) H:i:s', strtotime($ftc->ftc_sd) + 60 * 60 * 7);

            return response()->json([
                'nama_perusahaan' => $data->nama_perusahaan,
                'no_sampel' => $data->no_sampel,
                'a' => $a,
                'b' => $b,
                'c' => $c,
                'd' => $d,
                'e' => $e,
                'f' => $f,
                'g' => $g,
                'h' => $h,
                'i' => $i,
                'j' => $j,
                'k' => $k,
                'l' => $l,
                'm' => $m,
                'n' => $n,
                'o' => $o,
                'p' => $p,
                'q' => $q,
                'r' => $r,
                's' => $s,
                't' => $t,
                'u' => $u,
                'v' => $v,
                'w' => $w,
                'massage' => 'Show Tracking Purcase Order Success',
                'status' => true
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Tracking Purcase Order Data Not Found',
                'status' => false
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'status' => false
            ], 500);
        }
    }


}
