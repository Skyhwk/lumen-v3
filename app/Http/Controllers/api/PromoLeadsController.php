<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\PromoLeads;

class PromoLeadsController extends Controller
{
    public function store(Request $request)
    {
        $exists = PromoLeads::where('nama_lengkap', $request->nama_lengkap)
            ->where('promo', $request->promo)
            ->exists();

        if ($exists) return response()->json(['message' => 'Data already exists', 'success' => false], 500);

        $promoLeads = new PromoLeads();

        $promoLeads->nama_lengkap = $request->nama_lengkap;
        $promoLeads->nama_perusahaan = $request->nama_perusahaan;
        $promoLeads->email = $request->email;
        $promoLeads->no_hp = $request->no_hp;
        $promoLeads->promo = $request->promo;

        $promoLeads->save();

        return response()->json(['message' => 'Saved Successfully', 'success' => true], 200);
    }
}
