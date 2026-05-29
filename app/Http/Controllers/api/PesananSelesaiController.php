<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;

class PesananSelesaiController extends ClaimRewardController
{
    public function index(Request $request)
    {
        $request->merge(['status' => 'completed']);
        return parent::index($request);
    }
}
