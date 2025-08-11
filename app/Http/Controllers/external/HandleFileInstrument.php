<?php

namespace App\Http\Controllers\external;

use Laravel\Lumen\Routing\Controller as BaseController;
use App\Models\InstrumentIcp;
use App\Models\Colorimetri;
use App\Models\Parameter;
use App\Models\TemplateStp;
use App\Models\OrderDetail;
use App\Models\WsValueAir;
use App\Models\DraftIcp;
use App\Models\DraftAir;
use App\Services\FunctionValue;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\AnalystFormula;
use App\Models\AnalystFormula as Formula;
use DB;

class HandleFileInstrument extends BaseController
{
    public function handleImport(Request $request){
        switch ($request->type) {
            case 'icp':
                return $this->importIcp($request);
                break;
            
            default:
                return response()->json(['message' => 'Invalid type'], 400);
                break;
        }
    }
    public function importIcp(Request $request){
        if (!$request->hasFile('file')) {
            return response()->json([
                'message' => 'File not uploaded',
            ], 400);
        }
        
        $file = $request->file('file');
        $filePath = $file->getRealPath();

        // Read CSV file directly into memory
        $data = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ';')) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }
    
        // Process data
        $params = $data[2];

        // Find the blanko row - look for 'B' in the second column
        $blankoRowIndex = null;
        $blanko = null;
        $counter = 0;
        foreach ($data as $index => $row) {
            if (isset($row[1]) && trim($row[1]) === 'B') {
                $counter++;
                if ($counter === 2) {
                    $blankoRowIndex = $index;
                    $blanko = $row;
                    break;
                }
            }
        }
        
        if ($blankoRowIndex === null) {
            return response()->json([
                'message' => 'Blanko data not found in the file',
            ], 400);
        }
        
        if($counter == 1){
            return response()->json([
                'message' => 'Jumlah Blanko Data Tidak Sesuai',
            ],404);
        }
        
        // Find the standartData - start from the first row with 'STD1' in the second column
        $stdStartIndex = null;
        $stdEndIndex = null;
        
        for ($i = 0; $i < count($data); $i++) {
            if (isset($data[$i][1]) && trim($data[$i][1]) === 'STD1' || preg_match('/^STD\s*\d+$/i', trim($data[$i][1]))) {
                $stdStartIndex = $i;
                break;
            }
        }
        
        if ($stdStartIndex === null) {
            return response()->json([
                'message' => 'Standard data (STD1) not found in the file',
            ], 400);
        }
        
        // Find the end of standard data (looking for last STDn)
        for ($i = $stdStartIndex; $i < count($data); $i++) {
            if (isset($data[$i][1]) && preg_match('/^STD\d+$/i', trim($data[$i][1])) || preg_match('/^STD\s*\d+$/i', trim($data[$i][1]))) {
                $stdEndIndex = $i;
            } else if ($stdEndIndex !== null) {
                // We've found the last STD and now we've hit a non-STD row
                break;
            }
        }

        // $blanko = $data[$stdEndIndex + 1] ?? null;

        // if ($blanko === null) {
        //     return response()->json([
        //         'message' => 'Blanko data not found in the file',
        //     ], 400);
        // }
        
        if ($stdEndIndex === null) {
            $stdEndIndex = $stdStartIndex; // At least include STD1
        }
        
        // Extract the standard data
        $standartData = array_slice($data, $stdStartIndex, ($stdEndIndex - $stdStartIndex) + 1);

        // Group data starting from the row after blanko
        $groupedData = [];
        $currentGroup = null;
        $sampleNumbers = [];
        
        // Process rows from after blanko row
        foreach (array_slice($data, $stdEndIndex + 2) as $row) {
            $rowData = $row;
            // Extract sample number in one pass
            if (isset($rowData[1])) {
                $sampleInfo = explode(".", $rowData[1]);
                if (isset($sampleInfo[0]) && !empty($sampleInfo[0])) {
                    if (preg_match('/^FP\d+$/', $sampleInfo[0])) {
                        if (isset($sampleInfo[1])) {
                            $sampleNumbers[] = $sampleInfo[1];
                        }
                    }else{
                        $sampleNumbers[] = $sampleInfo[0];
                    }
                }
            }
            
            // Group data
            if (isset($rowData[1]) && preg_match('/^\d+\s*PPM$/', trim($rowData[1]))) {
                $currentGroup = count($groupedData);
                $groupedData[$currentGroup][] = $rowData;
            } elseif ($currentGroup !== null) {
                $groupedData[$currentGroup][] = $rowData;
            } else {
                // Tidak ditemukan 5 PPM, tambahkan baris 5 ppm pada awal rowData
                $rowData = array_fill(0, count($rowData), 1);
                $rowData[1] = "5 PPM";
                $rowData[count($rowData) - 1] = "";
                $rowData[count($rowData) - 2] = "|";
                $groupedData[] = [$rowData];
                $currentGroup = 0;
            }
        }

        // Unique sample numbers for bulk queries
        $sampleNumbers = array_unique($sampleNumbers);
        // dd($sampleNumbers);
        
        // Fetch all required data in bulk with eager loading where helpful
        $templateStpMap = [];
        $templates = TemplateStp::with('sample')
            ->where('name', "ICP")
            ->whereIn('category_id', [1, 4, 5])
            ->where('is_active', true)
            ->get();
        
        // Create a more structured template map
        foreach ($templates as $template) {
            // Decode the JSON-encoded parameter array
            $params_from_template = json_decode($template->param, true);
            
            // Handle both string and array cases
            if (is_array($params_from_template)) {
                // If it's an array of parameters, add each as a key
                foreach ($params_from_template as $param) {
                    $templateStpMap[$param] = $template;
                }
            } else if (is_string($params_from_template)) {
                // If it's a single parameter string
                $templateStpMap[$params_from_template] = $template;
            } else {
                // Fallback to original param value if JSON decode fails
                $templateStpMap[$template->param] = $template;
            }
        }
        
        // Fetch parameters in bulk
        $parameterMap = [];
        $parameters = Parameter::where('is_active', true)->get();
        foreach ($parameters as $param) {
            $parameterMap[$param->nama_lab] = $param;
        }
        
        // Bulk fetch order details
        $orderDetailsMap = [];
        $orderDetails = OrderDetail::whereIn('no_sampel', $sampleNumbers)
            ->where('is_active', true)
            ->get();
        
        foreach ($orderDetails as $orderDetail) {
            $orderDetailsMap[$orderDetail->no_sampel] = $orderDetail;
        }
        
        // Bulk fetch existing colorimetri records
        $existingColorimetriMap = [];
        $existingRecords = Colorimetri::select('no_sampel', 'parameter', 'status')
            ->whereIn('no_sampel', $sampleNumbers)
            ->where('is_active', true)
            ->where('status', 0)
            ->get();
        
        foreach ($existingRecords as $record) {
            $key = $record->no_sampel . '-' . $record->parameter;
            $existingColorimetriMap[$key] = $record;
        }
    
        // Prepare data for batch processing
        $now = Carbon::now()->toDateTimeString();
        $icpInserts = [];
        $colorimetriInserts = [];
        $wsValueAirData = [];
        $draftIcpInserts = [];
        $draftAirIcpInserts = [];
        $functionValue = new FunctionValue; // Create once outside the loop
        
        DB::beginTransaction();
        try {
            foreach ($groupedData as $value) {
                foreach ($value as $vKey => $vValue) {
                    if (empty($vValue) || !isset($vValue[0])) {
                        continue;
                    }
                    
                    foreach ($params as $pKey => $param) {
                        if (!isset($vValue) || $param === "" || $param === "|" || $vValue[1] === "5 PPM") {
                            continue;
                        }
                        
                        // Extract and process parameter data
                        $parameter = explode(' ', $param)[0];
                        $errors = [];
                        
                        // Check for scientific notation in relevant data fields
                        $isCorrupt = false;
                        $corruptField = "";
                        
                        // Check pengecer for corruption
                        if (isset($value[0][$pKey]) && preg_match('/^-?\d+,\d+E[\+\-]\d+$/', $value[0][$pKey])) {
                            $isCorrupt = true;
                            $corruptField = "pengecer";
                            $corruptValue = $value[0][$pKey];
                        }
                        
                        // Check avg_param for corruption
                        if (!$isCorrupt && isset($vValue[$pKey]) && preg_match('/^-?\d+,\d+E[\+\-]\d+$/', $vValue[$pKey])) {
                            $isCorrupt = true;
                            $corruptField = "avg_parameter";
                            $corruptValue = $vValue[$pKey];
                        }
                        
                        // Check blanko for corruption
                        if (!$isCorrupt && isset($blanko[$pKey]) && preg_match('/^-?\d+,\d+E[\+\-]\d+$/', $blanko[$pKey])) {
                            $isCorrupt = true;
                            $corruptField = "blanko";
                            $corruptValue = $blanko[$pKey];
                        }
                        
                        // Process standarts data once per parameter
                        $standarts = array_map(function($row) use ($pKey, &$isCorrupt, &$corruptField, &$corruptValue) {
                            // Check for corruption in standart values
                            if (isset($row[$pKey]) && preg_match('/^-?\d+,\d+E[\+\-]\d+$/', $row[$pKey])) {
                                $isCorrupt = true;
                                $corruptField = "standart";
                                $corruptValue = $row[$pKey];
                            }
                            
                            return (object) [
                                'standart' => $row[1],
                                'standart_value' => isset($row[$pKey]) && !preg_match('/^-?\d+,\d+E[\+\-]\d+$/', $row[$pKey]) ? 
                                    round(floatval($row[$pKey]), 4) : 0
                            ];
                        }, $standartData);

                        //Set Maks Curva
                        $maxCurva = floatval($standartData[count($standartData) - 1][$pKey]);
                        
                        $standarts = json_encode($standarts);
                        
                        // Get values with validation for corrupt data
                        $pengecer = 1;
                        if (preg_match('/^FP(\d+)/', $vValue[1], $match)) {
                            // $pengecer = floatval($match[1]) <= $maxCurva ? intval($match[1]) : 1;
                            $pengecer = intval($match[1]);
                            $no_sampel = explode(".", $vValue[1])[1];
                            $status_param = explode(".", $vValue[1])[2] ?? '';
                        } else {
                            $pengecer = 0;
                            $no_sampel = explode(".", $vValue[1])[0];
                            $status_param = explode(".", $vValue[1])[1] ?? '';
                        }

                        $paramKey = "$parameter" . ($status_param === "TR" ? " Terlarut" : ($status_param === "TL" ? " Total" : ""));
                        $par = $parameterMap[$paramKey] ?? null; 
                        $parId = $par ? $par->id : null;

                        $not_decimal = [13, 60, 140, 174, 175];
                        $is_5_decimal = [3, 4, 22, 23, 24, 36, 37, 42, 43, 49, 50, 77, 78, 100, 101, 112, 113, 156, 157, 53, 54, 190, 189, 4, 3];
                        $is_4_decimal = [65, 66, 149, 150, 16, 17, 187, 105, 122, 121, 7, 6, 21, 20, 19, 171, 170, 147, 146, 109];
                        $is_3_decimal = [31, 33, 34, 40, 96, 97, 520, 537, 546, 547];
                        $is_2_decimal = [39, 102, 545];

                        $decimal = 0;
                        $useDecimal = false;

                        if(in_array($parId, $is_5_decimal)){
                            $useDecimal = true;
                            $decimal = 5;
                        }else if(in_array($parId, $is_4_decimal)){
                            $useDecimal = true;
                            $decimal = 4;
                        }else if(in_array($parId, $is_3_decimal)){
                            $useDecimal = true;
                            $decimal = 3;
                        }else if(in_array($parId, $is_2_decimal)){
                            $useDecimal = true;
                            $decimal = 2;
                        }

                        $avg_param = isset($vValue[$pKey]) && !preg_match('/^-?\d+,\d+E[\+\-]\d+$/', $vValue[$pKey]) ? 
                            $useDecimal == false ? round(floatval($vValue[$pKey]), 4) : round(floatval($vValue[$pKey]), $decimal) : 0;
                        $blankoParam = isset($blanko[$pKey]) && !preg_match('/^-?\d+,\d+E[\+\-]\d+$/', $blanko[$pKey]) ? 
                            round(floatval($blanko[$pKey]), 4) : 0;
                        
                        // Prepare ICP data
                        $icpData = [
                            'parameter' => $parameter . ($status_param === "TR" ? " Terlarut" : ($status_param === "TL" ? " Total" : "")),
                            'nilai_larutan' => $pengecer,
                            'no_sampel' => $no_sampel,
                            'parameter_status' => $status_param,
                            'avg_parameter' => $avg_param,
                            'blanko' => $blankoParam,
                            'kategori' => null,
                            'standarts' => $standarts,
                            'created_by' => "SYSTEM",
                            'created_at' => $now,
                            'status' => 'unprocessed', // Add a default status
                            'error_message' => null // Add a default error_message
                        ];
                        
                        // Handle corrupt data by setting an error status
                        if ($isCorrupt) {
                            $icpData['status'] = 'error';
                            $errors[] = "Data $corruptField untuk parameter $parameter" . ($status_param === "TR" ? " Terlarut" : ($status_param === "TL" ? " Total" : "")). " berformat tidak valid: $corruptValue";
                            $icpData['error_message'] = json_encode($errors);
                            $icpInserts[] = $icpData;
                            continue;
                        }
                        
                        $stp = null;
                        
                        // Try to find the template using the parameter key
                        if (isset($templateStpMap[$paramKey])) {
                            $stp = $templateStpMap[$paramKey];
                        }

                        $order_detail = $orderDetailsMap[$no_sampel] ?? null;

                        $order_parameter = null;
                        $listParam = [];
                        $isParamExist = false;
                        
                        // CEK PARAMETER IF EXIST
                        if($order_detail){
                            $order_parameter = json_decode($orderDetailsMap[$no_sampel]->parameter) ?? null;
                            $listParam = array_map(function ($item) {
                                return explode(';', $item)[1];
                            },$order_parameter);
                            $isParamExist = in_array($paramKey, $listParam);
                        }

                        // CEK ORDER DETAIL
                        if (is_null($order_detail) || $isParamExist == false) {
                            $icpData['status'] = 'error';
                            $errors[] = "Parameter $paramKey pada no sampel $no_sampel tidak ditemukan";
                            $icpData['error_message'] = json_encode($errors);
                            $icpInserts[] = $icpData;
                            continue;
                        }
                        
                        if (!is_null($stp)) {
                            // Kategori Air - Process with Colorimetri
                            if ($order_detail->kategori_2 == "1-Air") {
                                $icpData['kategori'] = "Air";
                                $cekKey = $no_sampel . '-' . $paramKey;
                                $cek = $existingColorimetriMap[$cekKey] ?? null;
                                if((int) $avg_param > $maxCurva){
                                    $icpData['status'] = 'error';
                                    $errors[] = "Nilai hasil pengujian melebihi Maks Curva";
                                    $icpData['error_message'] = json_encode($errors);
                                    $icpInserts[] = $icpData;
                                    $draftAirIcpInserts[] = [
                                        "parameter" => $parameter . ($status_param === "TR" ? " Terlarut" : ($status_param === "TL" ? " Total" : "")),
                                        "fp" => $pengecer,
                                        "no_sampel" => $no_sampel,
                                        "hp" => $avg_param,
                                        'created_by' => "SYSTEM",
                                        'created_at' => $now
                                    ];
                                    continue;
                                }
                                
                                $colorimetriData = [
                                    'no_sampel' => $no_sampel,
                                    'parameter' => $paramKey,
                                    'template_stp' => $stp->id,
                                    'tanggal_terima' => $order_detail->tanggal_terima,
                                    'jenis_pengujian' => 'sample',
                                    'fp' => $pengecer,
                                    'hp' => $avg_param,
                                    'created_by' => "SYSTEM",
                                    'created_at' => $now
                                ];
                                
                                // CEK IF DATA EXISTING
                                if (!is_null($cek)) {
                                    $icpData['status'] = 'processed';
                                    $errors[] = "Data dengan no sampel $no_sampel dengan parameter $paramKey sudah diinput";
                                    $icpData['error_message'] = json_encode($errors);
                                    continue;
                                }
                                
                                $icpData['status'] = 'processed';
                                
                                
                                // Prepare data for WsValueAir
                                if (!is_null($par)) {
                                    // Pre-compute values without database insertion
                                    // $val = $functionValue->Perkalian(null, $no_sampel, $avg_param, $pengecer, $par->id);
                                    $function = Formula::where('id_parameter', $par->id)->where('is_active', true)->first();
                                    
                                    if(!$function){
                                        $icpData['status'] = 'error';
                                        $errors[] = "Parameter $paramKey pada no sampel $no_sampel tidak terdapat pada rumus ICP";
                                        $icpData['error_message'] = json_encode($errors);
                                        $icpInserts[] = $icpData;
                                        continue;
                                    }
                                    
                                    $functionName = $function->function;
                                    
                                    $data_parsing = [
                                        'hp' => $avg_param,
                                        'fp' => $pengecer
                                    ];
                                    $data_parsing = (object)$data_parsing;
                                    $data_kalkulasi = AnalystFormula::where('function', 'Perkalian')
                                        ->where('data', $data_parsing)
                                        ->where('id_parameter', $par->id)
                                        ->process();
                                    $data_kalkulasi['no_sampel'] = $no_sampel;

                                    $colorimetriInserts[] = $colorimetriData;
                                    $wsValueAirData[] = $data_kalkulasi;
                                }
                            } else if(in_array($order_detail->kategori_2, ["4-Udara", "5-Emisi"])){ // Kategori Udara dan Emisi - Process with DraftIcp
                                $icpData['status'] = 'unprocessed';
                                $icpData['kategori'] = $order_detail->kategori_2 == "4-Udara" ? "Udara" : "Emisi";
                                // dd($icpData);
                                $draftIcpData = [
                                    'no_sampel' => $no_sampel,
                                    'ks' => $avg_param,
                                    'kb' => $blankoParam,
                                    'created_by' => "SYSTEM",
                                    'created_at' => $now
                                ];
                                // Cek jika parameter tersedia
                                if (!is_null($par)) {
                                    $draftIcpData['parameter'] = $par->nama_lab;
                                } else {
                                    $icpData['status'] = 'error';
                                    $errors[] = "Parameter $paramKey tidak ditemukan pada No Sampel $no_sampel";
                                    $icpData['error_message'] = json_encode($errors);
                                    $icpInserts[] = $icpData;
                                    continue;
                                }
                                
                                $draftIcpInserts[] = $draftIcpData;
                            }
                        } else {
                            $icpData['status'] = 'error';
                            $errors[] = "Template STP untuk parameter $paramKey tidak ditemukan";
                            $icpData['error_message'] = json_encode($errors);
                        }
                        
                        $icpData['error_message'] = count($errors) > 0 ? json_encode($errors) : null;
                        $icpInserts[] = $icpData;
                        // if($no_sampel == "NIIA012501/002" && $parameter == "Cd")dd($icpData);
                    }
                }
            }
            
            // Batch insert all data efficiently
            if (!empty($icpInserts)) {
                // Use chunk to avoid memory issues with large datasets
                foreach (array_chunk($icpInserts, 500) as $chunk) {
                    // InstrumentIcp::insert($chunk);
                    try {
                        InstrumentIcp::insert($chunk);
                    } catch (\Exception $e) {                        
                        // Jika batch insert gagal, coba insert satu per satu
                        foreach ($chunk as $singleRow) {
                            try {
                                InstrumentIcp::insert($singleRow);
                            } catch (\Exception $singleError) {
                                \Log::error("Error inserting single ICP row: " . $singleError->getMessage() . " Data: " . json_encode($singleRow));
                            }
                        }
                    }
                }
            }

            // Batch insert all data efficiently
            if (!empty($draftAirIcpInserts)) {
                // Use chunk to avoid memory issues with large datasets
                foreach (array_chunk($draftAirIcpInserts, 500) as $chunk) {
                    DraftAir::insert($chunk);
                }
            }
            
            if (!empty($draftIcpInserts)) {
                foreach (array_chunk($draftIcpInserts, 500) as $chunk) {
                    DraftIcp::insert($chunk);
                }
            }
            
            // dd($colorimetriInserts);
            // Process Colorimetri data efficiently
            if (!empty($colorimetriInserts)) {
                $colorimetriRecords = [];
                
                foreach ($colorimetriInserts as $index => $colorimetriData) {
                    $colorimetri = new Colorimetri($colorimetriData);
                    $colorimetri->save();
                    
                    // Create WsValueAir entry if we have data for this index
                    if (isset($wsValueAirData[$index])) {
                        $val = $wsValueAirData[$index];
                        $val['id_colorimetri'] = $colorimetri->id;  // Note: using id_colorimetri as in the second code
                        WsValueAir::create($val);
                    }
                }
            }
            
            DB::commit();
            return response()->json([
                'message' => 'Success import document',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }
}