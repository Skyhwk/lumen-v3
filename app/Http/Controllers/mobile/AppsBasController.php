<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AppsBasService;

class AppsBasController extends Controller
{
    protected $service;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->service = new AppsBasService($this->karyawan, $this->user_id);
    }

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function detailData(Request $request)
    {
        return $this->service->detailData($request);
    }

    public function updateData(Request $request)
    {
        return $this->service->updateData($request);
    }

    public function sendEmail(Request $request)
    {
        return $this->service->sendEmail($request);
    }

    public function preview(Request $request)
    {
        return $this->service->preview($request);
    }

    public function storeSampelTidakSelesai(Request $request)
    {
        return $this->service->storeSampelTidakSelesai($request);
    }
}