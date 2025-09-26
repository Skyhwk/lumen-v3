<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\MasterPelanggan;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        $email = $request->attributes->get('user')['email'];
        $companies = MasterPelanggan::whereHas('kontak_pelanggan', fn($q) => $q->where('email_perusahaan', $email))->orderBy('nama_pelanggan')->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $request->attributes->get('user'),
                'companies' => $companies
            ],
        ], 200);
    }
}
