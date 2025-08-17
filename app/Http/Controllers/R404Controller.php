<?php
namespace App\Http\Controllers;

use App\Models\Requestlog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EandDcriptController as Edcript;

class R404Controller extends Controller
{
	public $pathinfo;
	public $param;
    public $useragen;
    public $ip;
    public $globaldate;

	public function __construct(Request $request){
        date_default_timezone_set("Asia/Jakarta");
        $this->pathinfo = $request->getPathInfo();
        $this->param = $request->all();
        $this->useragen = $request->header('User-Agent');
        $this->ip = $request->ip();
        $this->globaldate = self::getDefaultDate();
    }

    private function getDefaultDate(){
        return date("Y-m-d H:i:s");
    }

	// return 404
    public function r404(Request $request){
        
        return response()->json(['message' => '404 not found'], 404);
    }
}