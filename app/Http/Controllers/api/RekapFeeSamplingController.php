<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\api\Concerns\BuildsFeeSamplingDatatable;
use App\Models\PengajuanFeeSampling;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RekapFeeSamplingController extends Controller
{
    use BuildsFeeSamplingDatatable;

    public function index(Request $request)
    {
        $data = PengajuanFeeSampling::query()
            ->where('is_approve_finance', 1)
            ->whereNotNull('transfer_date')
            ->where('is_upload_bukti_pembayaran', 1);

        return $this->buildFeeSamplingDatatable($data);
    }

    public function summary(Request $request)
    {
        $data = PengajuanFeeSampling::query()
            ->where('is_approve_finance', 1)
            ->whereNotNull('transfer_date')
            ->where('is_upload_bukti_pembayaran', 1);

        return $this->feeSamplingSummaryJson($data, $request);
    }
}