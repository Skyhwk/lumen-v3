<?php

namespace App\Http\Controllers\external;

use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class GetLhpApiPakEko extends BaseController
{
    public function getLHP(Request $request){
        $key = $request->header('key');
        if(isset($key) && $key == 'eb928269046b298bc2223eb1bacd797b'){
            if(isset($request->uniq) && $request->uniq != ''){
                $response = Http::get('http://10.88.8.9/api3/lhp/'.$request->uniq,
                    []
                );
                $response = json_decode($response->getBody());
                return response()->json($response);
            } else {
                return response()->json((object)[]);
            }
        } else {
            return response()->json([
                'message' => 'Token Not Found.!'
            ],401);
        }
    }
}