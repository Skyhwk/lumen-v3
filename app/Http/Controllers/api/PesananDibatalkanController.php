<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;

class PesananDibatalkanController extends ClaimRewardController
{
    public function index(Request $request)
    {
        $request->merge(['status' => 'cancelled_rejected']);
        return parent::index($request);
    }
}
