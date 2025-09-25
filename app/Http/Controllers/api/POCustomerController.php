<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class POCustomerController extends Controller
{
    public function index(){
        $data = PurchaseOrder::where('is_active', true)->get();
        $data->map(function ($item) {
            $item->invoice = json_decode($item->invoice);
            return $item;
        });
        return Datatables::of($data)->make(true);
    }

}