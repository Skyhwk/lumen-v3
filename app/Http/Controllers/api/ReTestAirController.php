<?php

namespace App\Http\Controllers\api;

use App\Models\Colorimetri;
use App\Models\Titrimetri;
use App\Models\Gravimetri;
use App\Models\Subkontrak;
use App\Models\WsValueAir;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\TemplateStp;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class ReTestAirController extends Controller
{


    //20-03-2025
  
    public function index(Request $request){
        $stp = TemplateStp::where('id', $request->stp)->where('is_active', 1)->first();
        // dd($stp);
        if (!$stp) {
            return response()->json(['error' => 'STP not found'], 404);
        }

        // Determine model based on STP name
        $model = $this->getModelByStp($stp->name);
        
        if (!$model) {
            return response()->json(['error' => 'Invalid STP name'], 400);
        }

        if ($request->is_retest == 1 && $request->is_active == 0) {
            $data = $this->getRetestDataNotInMain($model, $request);
        } else if ($request->is_retest == 0 && $request->is_active == 1) {
            $data = $this->getMainDataFromRetest($model, $request);
        } else {
            $data = collect();
        }

        return Datatables::of($data)
            ->filter(function ($query) use ($request, $stp) {
                if ($request->has('columns')) {
                    $columns = $request->get('columns');
                    foreach ($columns as $column) {
                        if (isset($column['search']) && !empty($column['search']['value'])) {
                            $columnName = $column['name'] ?: $column['data'];
                            $searchValue = $column['search']['value'];
                    
                            if (isset($column['searchable']) && $column['searchable'] === 'false') {
                                continue;
                            }
                            if ($columnName === 'tanggal_terima' && $stp->name != 'OTHER') {
                                $query->whereDate('tanggal_terima', 'like', "%{$searchValue}%");
                            } 
                            elseif ($columnName === 'created_at') {
                                $query->whereDate('created_at', 'like', "%{$searchValue}%");
                            }
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

    /**
     * Determine model class based on STP name
     */
    private function getModelByStp($stpName)
    {
        $stpName = strtoupper($stpName);
        
        // Titrimetri models
        if (in_array($stpName, ['TITRIMETRI', 'TITRI A', 'TITRI B'])) {
            return Titrimetri::class;
        }
        
        // Gravimetri models
        if (in_array($stpName, ['GRAVIMETRI', 'GRAVIMETRI A', 'GRAVIMETRI B'])) {
            return Gravimetri::class;
        }
        
        // Colorimetri models
        $colorimetriTypes = [
            'MIKROBIOLOGI', 'ICP', 'DIRECT READING', 'Direct Reading A', 'Direct Reading B', 'Direct Reading C',
            'Direct Reading D', 'COLORIMETRI', 
            'SPEKTROFOTOMETER UV-VIS', 'SPEKTRO A', 'SPEKTRO B', 'SPEKTRO C',
            'SPEKTRO D', 'SPEKTRO E', 'SPEKTRO F', 'MERCURY ANALYZER'
        ];
        
        if (in_array($stpName, $colorimetriTypes)) {
            return Colorimetri::class;
        }
        
        // Subkontrak model
        if ($stpName === 'OTHER' || $stpName === 'SUBKONTRAK') {
            return Subkontrak::class;
        }
        
        return null;
    }

    /**
     * Get main data that has corresponding retest data
     */
    private function getMainDataFromRetest($model, $request)
    {
        // Get all retest data for this model and STP
        $retest = $model::where('is_active', 0)
            ->where('is_retest', 1);

        // Only add template_stp condition if not Subkontrak
        if ($model !== Subkontrak::class) {
            $retest = $retest->where('template_stp', $request->stp);
        }

        $retest = $retest->get();

        // Get unique pairs of no_sampel and parameter from retest data
        $pairs = $retest->map(fn($item) => [
            'no_sampel' => $item->no_sampel,
            'parameter' => $item->parameter
        ])->unique()->values();

        if ($pairs->isEmpty()) {
            return collect();
        }

        // Get main data that matches these pairs
        $query = $model::with('ws_value_retest', 'order_detail')
            ->where('is_active', 1)
            ->where('is_retest', 0)
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($q) use ($pair) {
                        $q->where('no_sampel', $pair['no_sampel'])
                            ->where('parameter', $pair['parameter']);
                    });
                }
            });

        // Only add template_stp condition if not Subkontrak
        if ($model !== Subkontrak::class) {
            $query = $query->where('template_stp', $request->stp);
        }

        return $query->get();
    }

    /**
     * Get retest data that doesn't have corresponding main data
     */
    private function getRetestDataNotInMain($model, $request)
    {
        // Get all main data pairs for this model and STP
        $mainQuery = $model::where('is_active', 1)
            ->where('is_retest', 0);

        // Only add template_stp condition if not Subkontrak
        if ($model !== Subkontrak::class) {
            $mainQuery = $mainQuery->where('template_stp', $request->stp);
        }

        $mainPairs = $mainQuery->get()
            ->map(fn($item) => $item->no_sampel . '||' . $item->parameter)
            ->unique()
            ->values()
            ->toArray();

        // Get retest data
        $retestQuery = $model::with('ws_value_retest', 'order_detail')
            ->where('is_active', 0)
            ->where('is_retest', 1);

        // Only add template_stp condition if not Subkontrak
        if ($model !== Subkontrak::class) {
            $retestQuery = $retestQuery->where('template_stp', $request->stp);
        }

        return $retestQuery->get()
            ->filter(function ($item) use ($mainPairs) {
                $key = $item->no_sampel . '||' . $item->parameter;
                return !in_array($key, $mainPairs);
            })
            ->values(); // Reset keys
    }

    public function getStp() 
    {
        $data = TemplateStp::where('is_active', 1)->where('category_id', 1)->get();
        return response()->json($data);
    }
}