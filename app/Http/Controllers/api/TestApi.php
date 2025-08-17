<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GetAtasan;
use App\Services\Notification;
use App\Services\Printing;

class TestApi extends Controller
{
    public function index()
    {
        // $getAtasan = GetAtasan::where('id', 7)->get()->pluck('email');

        // return response()->json(['data' => $getAtasan]);

        // Notification::whereIn('id', [127,7])->title('title')->message('Pesan Baru.!')->url('/')->send();
        $tes = Printing::get();
        return response()->json(['data' => $tes]);
    }
}
