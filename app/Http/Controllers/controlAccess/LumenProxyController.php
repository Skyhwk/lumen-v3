<?php

namespace App\Http\Controllers\controlAccess;

use Laravel\Lumen\Routing\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;

class LumenProxyController extends Controller
{
    protected $baseController;

    public function __construct(BaseController $baseController)
    {
        $this->baseController = $baseController;
    }

    public function route(Request $request)
    {
        return $this->baseController->handle($request);
    }

    public function datatable(Request $request)
    {
        return $this->baseController->handle($request);
    }
}
