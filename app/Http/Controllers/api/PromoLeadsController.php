<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\PromoLeads;

class PromoLeadsController extends Controller
{
    public function store(Request $request)
    {
        $promoLeads = new PromoLeads();

        $promoLeads->nama_lengkap = $request->nama_lengkap;
        $promoLeads->nama_perusahaan = $request->nama_perusahaan;
        $promoLeads->email = $request->email;
        $promoLeads->no_hp = $request->no_hp;

        $promoLeads->save();

        return response()->json(['message' => 'Saved Successfully', 'success' => true], 200);
    }
}
