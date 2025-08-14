<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GetAtasan;
use App\Services\Notification;
use App\Services\Printing;
use App\Services\TemplateLhpsErgonomi;

class TestApi extends Controller
{
     public function index(Request $request)
    {   
        try {
            //code...
            $render = new TemplateLhpsErgonomi();
            switch ($request->mode) {
                case 'nbm':
                    $template = $render->ergonomiNbm();
                    break;
                case 'rwl':
                    $template = $render->ergonomiRwl();
                    break;
                case 'rula':
                    $template = $render->ergonomiRula();
                    break;
                case 'reba':
                    $template = $render->ergonomiReba();
                    break;
                case 'rosa':
                    $template = $render->ergonomiRosa();
                    break;
                case 'brief':
                    $template = $render->ergonomiBrief();
                    break;
                case 'potensi':
                    $template = $render->ergonomiPotensiBahaya();
                case 'gontrak':
                    $template = $render->ergonomiGontrak();
            }
            return response($template, 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="laporan.pdf"',
            ]);
        } catch (\Throwable $th) {
            return response()->json(["message"=>$th->getMessage(),'line'=>$th->getLine()],200);
        }
    }
   /*  public function index()
    {
        // $getAtasan = GetAtasan::where('id', 7)->get()->pluck('email');

        // return response()->json(['data' => $getAtasan]);

        // Notification::whereIn('id', [127,7])->title('title')->message('Pesan Baru.!')->url('/')->send();
        $tes = Printing::get();
        return response()->json(['data' => $tes]);
    } */
}
