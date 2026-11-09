<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\QuotationAutoLinkOpens;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class FollowUpQuoatationAutoController extends Controller
{
    public function index(Request $request)
    {
        $data = QuotationAutoLinkOpens::query()
            ->select('quotation_auto_link_opens.*');

        $processed = (int) $request->input('processed', 0);
        $data->where('quotation_auto_link_opens.is_processed', $processed);

        return DataTables::of($data)->make(true);
    }

    public function update(Request $request, $id)
    {
        $data = QuotationAutoLinkOpens::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data not found',
            ], 404);
        }

        $data->is_processed = (int) $request->input('processed', 1) === 1;
        $data->save();
        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully',
        ], 200);
    }
}
