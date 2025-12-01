<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use App\Models\Colorimetri;
use App\Models\Titrimetri;
use App\Models\Gravimetri;
use App\Models\ParameterTotal;
use App\Models\AnalisParameter;
use App\Models\TemplateStp;
use App\Models\OrderDetail;
use Carbon\Carbon;
use DB;

class TotalAirController extends Controller
{
    public function index(Request $request){
        $data = Colorimetri::with('ws_value', 'order_detail')
            ->where('is_approved', $request->approve)
            ->where('is_active', true)
            ->where('is_total', false)
            ->where('template_stp', $request->template_stp)
            ->select('colorimetri.*', 'order_detail.tanggal_terima', 'order_detail.no_sampel','order_detail.kategori_3');
        return Datatables::of($data)
            ->orderColumn('tanggal_terima', function ($query, $order) {
                $query->orderBy('order_detail.tanggal_terima', $order);
            })
            ->orderColumn('created_at', function ($query, $order) {
                $query->orderBy('colorimetri.created_at', $order);
            })
            ->orderColumn('no_sampel', function ($query, $order) {
                $query->orderBy('order_detail.no_sampel', $order);
            })
            ->filter(function ($query) use ($request) {
                if ($request->has('columns')) {
                    $columns = $request->get('columns');
                    foreach ($columns as $column) {
                        if (isset($column['search']) && !empty($column['search']['value'])) {
                            $columnName = $column['name'] ?: $column['data'];
                            $searchValue = $column['search']['value'];
                            
                            // Skip columns that aren't searchable
                            if (isset($column['searchable']) && $column['searchable'] === 'false') {
                                continue;
                            }
                            
                            // Special handling for date fields
                            if ($columnName === 'tanggal_terima') {
                                // Assuming the search value is a date or part of a date
                                $query->whereDate('tanggal_terima', 'like', "%{$searchValue}%");
                            } 
                            // Handle created_at separately if needed
                            elseif ($columnName === 'created_at') {
                                $query->whereDate('created_at', 'like', "%{$searchValue}%");
                            }
                            // Standard text fields
                            elseif (in_array($columnName, [
                                'no_sampel', 'parameter', 'jenis_pengujian'
                            ])) {
                                $query->where($columnName, 'like', "%{$searchValue}%");
                            }
                        }
                    }
                }
            })
        ->make(true);
    }

    public function showDetail(Request $request){
        $parent = ParameterTotal::where('parameter_name', $request->parameter)->where('is_active', 1)->first();
        $children = json_decode($parent->id_child);
        $analisParameter = AnalisParameter::whereIn('parameter_id', $children)
            ->get()
            ->map(function ($item) use ($request) {
                $stp = TemplateStp::where('id', $item->id_stp)->where('is_active', 1)->first();
                $header = null;
                if($stp->name == 'GRAVIMETRI' && $stp->category_id == 1){
                    $header = Gravimetri::with('ws_value')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('parameter', $item->parameter_name)
                        ->where('template_stp', $item->id_stp)
                        ->where('is_active', 1)
                        ->where('is_total', 1)
                        ->first();
                    if($header){
                        $header->template = 'gravimetri';
                    }
                }else if (( ($stp->name == 'MIKROBIOLOGI' || $stp->name == 'ICP' || $stp->name == 'DIRECT READING' || $stp->name == 'COLORIMETRI' || $stp->name == 'SPEKTROFOTOMETER UV-VIS' || $stp->name == 'MERCURY ANALYZER')
                &&
                $stp->category_id == 1
                )) {
                    $header = Colorimetri::with('ws_value')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('parameter', $item->parameter_name)
                        ->where('template_stp', $item->id_stp)
                        ->where('is_active', 1)
                        ->where('is_total', 1)
                        ->first();
                    if($header){
                        $header->template = 'colorimetri';
                    }
                }else if($stp->name == 'TITRIMETRI' && $stp->category_id == 1){
                    $header = Titrimetri::with('ws_value')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('parameter', $item->parameter_name)
                        ->where('template_stp', $item->id_stp)
                        ->where('is_active', 1)
                        ->where('is_total', 1)
                        ->first();
                    if($header){
                        $header->template = 'titrimetri';
                    }
                }
                return $header ?? null;
            });

        return response()->json([
            'data' => json_decode($analisParameter)
        ], 200);
    }

    public function approve(Request $request){
        DB::beginTransaction();
        try {
            $Colorimetri = Colorimetri::where('id', $request->id)->first();
            $Colorimetri->is_approved = true;
            $Colorimetri->save();

            DB::commit();

            return response()->json([
                'message'=> 'Successfully Approved',
                'data' => $Colorimetri
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'data' => $th
            ], 500);
        }
    }

    public function delete(Request $request){
        DB::beginTransaction();
        try {
            $Colorimetri = Colorimetri::where('id', $request->id)->first();
            $Colorimetri->is_active = false;
            $Colorimetri->save();

            $parameter_total = ParameterTotal::where('parameter_name', $Colorimetri->parameter)->where('is_active', 1)->first();
            $children = json_decode($parameter_total->id_child);
            foreach ($children as $child) {
                $analisParameter = AnalisParameter::where('parameter_id', $child)->first();
                $stp = TemplateStp::where('id', $analisParameter->id_stp)->where('is_active', 1)->first();
                if($stp->name == 'GRAVIMETRI' && $stp->category_id == 1){
                    $graviChild = Gravimetri::where('template_stp', $analisParameter->id_stp)->where('no_sampel', $Colorimetri->no_sampel)->where('parameter', $analisParameter->parameter_name)->where('is_active', 1)->where('is_total', 1)->first();
                    if($graviChild){
                        $graviChild->is_active = false;
                        $graviChild->save();
                    }
                }else if($stp->name == 'TITRIMETRI' && $stp->category_id == 1){
                    $titriChild = Titrimetri::where('template_stp', $analisParameter->id_stp)->where('no_sampel', $Colorimetri->no_sampel)->where('parameter', $analisParameter->parameter_name)->where('is_active', 1)->where('is_total', 1)->first();
                    if($titriChild){
                        $titriChild->is_active = false;
                        $titriChild->save();
                    }
                    
                }if (( ($stp->name == 'MIKROBIOLOGI' || $stp->name == 'ICP' || $stp->name == 'DIRECT READING' || $stp->name == 'COLORIMETRI' || $stp->name == 'SPEKTROFOTOMETER UV-VIS' || $stp->name == 'MERCURY ANALYZER')
                &&
                $stp->category_id == 1
                )){
                    $coloriChild = Colorimetri::where('template_stp', $analisParameter->id_stp)->where('no_sampel', $Colorimetri->no_sampel)->where('parameter', $analisParameter->parameter_name)->where('is_active', 1)->where('is_total', 1)->first();
                    if($coloriChild){
                        $coloriChild->is_active = false;
                        $coloriChild->save();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message'=> 'Successfully Deleted',
                'data' => $Colorimetri
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'data' => $th
            ], 500);
        }
    }
}