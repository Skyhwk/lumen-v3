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
use App\Models\DataLapanganEmisiCerobong;
use App\Models\DataLapanganLingkunganHidup;
use App\Models\DataLapanganLingkunganKerja;
use App\Models\DataLapanganSenyawaVolatile;
use App\Models\DetailLingkunganHidup;
use App\Models\DetailLingkunganKerja;
use App\Models\DetailSenyawaVolatile;
use App\Models\EmisiCerobongHeader;
use App\Models\LingkunganHeader;
use App\Models\WsValueEmisiCerobong;
use App\Models\WsValueLingkungan;
use App\Models\WsValueUdara;
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
                    $templateStpMap["$param;$template->category_id"] = $template;
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
            $parameterMap["$param->nama_lab;$param->id_kategori"] = $param;
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
        $lingkunganHeaderInserts = [];
        $emisiCerobongHeaderInserts = [];
        $wsValueAirData = [];
        $wsValueUdaraData = [];
        $wsValueLingkunganData = [];
        $wsValueEmisiCerobongData = [];
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
                        if (!isset($vValue) || $param === "" || $param === "|" || $vValue[1] === "5 PPM" || strpos($vValue[1], "PPM") !== false) {
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

                            $idCategory = explode('-', $order_detail->kategori_2)[0];
                            $stp = $templateStpMap["$paramKey;$idCategory"] ?? null;
                            
                            $kategori = explode('-', $order_detail->kategori_2);
                            $par = $parameterMap["$paramKey;$kategori[0]"] ?? null; 
                            $parId = $par ? $par->id : null;
                            if(!$isParamExist && $order_detail->kategori_2 == '1-Air'){
                                $paramKey .= " (NA)";
                                $isParamExist = in_array($paramKey, $listParam);
                                $icpData['parameter'] = $paramKey;
                            }elseif(!$isParamExist && $order_detail->kategori_2 == '4-Udara' && $paramKey == 'Pb'){
                                $paramKey .= " (24 Jam)";
                                $isParamExist = in_array($paramKey, $listParam);
                                $icpData['parameter'] = $paramKey;
                            }

                            if(!$isParamExist && $order_detail->kategori_2 == '1-Air'){
                                $paramKey = substr($paramKey, 0, -5);
                            }elseif(!$isParamExist && $order_detail->kategori_2 == '4-Udara' && $paramKey == 'Pb (24 Jam)'){
                                $paramKey = substr($paramKey, 0, -9);
                            }
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
                            } else if(in_array($order_detail->kategori_2, ["4-Udara", "5-Emisi"])){ 
                                $icpData['status'] = 'processed'; // Ubah dari 'unprocessed' ke 'processed'
                                $icpData['kategori'] = $order_detail->kategori_2 == "4-Udara" ? "Udara" : "Emisi";
                                $stParam = $order_detail->kategori_2 == "5-Emisi" || $order_detail->kategori_3 == '11-Udara Ambient'? 4 : 1;
                                
                                $payload = (object)[
                                    'no_sampel' => $no_sampel,
                                    'parameter' => $paramKey,
                                    'ks' => [$avg_param],
                                    'kb' => [$blankoParam],
                                    'st' => $stParam,
                                    'vl' => 50,
                                    'fp' => $pengecer,
                                    'created_by' => "SYSTEM",
                                    'created_at' => $now
                                ];
                                
                                if($order_detail->kategori_2 == "5-Emisi") {
                                    if(isset($payload->vl)) unset($payload->vl);
                                    $payload->vs = 50;
                                    $payload->ks = $avg_param;
                                    $payload->kb = $blankoParam;
                                }
                                
                                // Siapkan data untuk batch insert
                                if($order_detail->kategori_2 == "4-Udara"){
                                    $datlapanganh = DataLapanganLingkunganHidup::where('no_sampel', $no_sampel)->first();
                                    $datlapangank = DataLapanganLingkunganKerja::where('no_sampel', $no_sampel)->first();
                                    $datlapanganV = DataLapanganSenyawaVolatile::where('no_sampel', $no_sampel)->first();

                                    if (!$datlapanganh && !$datlapangank && !$datlapanganV) {
                                        $icpData['status'] = 'error';
                                        $errors[] = "Data lapangan tidak ditemukan untuk no sampel $no_sampel";
                                        $icpData['error_message'] = json_encode($errors);
                                        $icpInserts[] = $icpData;
                                        continue;
                                    }

                                    $helperResult = $this->HelperLingkungan($payload, $stp, $order_detail, $datlapanganh, $datlapangank, $datlapanganV, $par);
                                    
                                    if(isset($helperResult->status) && !in_array($helperResult->status, [200, 201])){
                                        $icpData['status'] = 'error';
                                        $errors[] = $helperResult->message;
                                        $icpData['error_message'] = json_encode($errors);
                                        $icpInserts[] = $icpData;
                                        $draftIcp = (array) $payload;
                                        if(isset($draftIcp['vl'])) unset($draftIcp['vl']);
                                        $draftIcp['vs'] = 50;
                                        $draftIcp['ks'] = $avg_param;
                                        $draftIcp['kb'] = $blankoParam;
                                        $draftIcpInserts[] = $draftIcp;
                                        // if($no_sampel == "SAOE012503/003" && $paramKey == "Pb")dd($icpData);
                                        continue;
                                    }
                                    
                                    // Simpan data untuk batch insert
                                    $lingkunganHeaderInserts[] = $helperResult->header;
                                    $wsValueUdaraData[] = $helperResult->ws_value_udara;
                                    $wsValueLingkunganData[] = $helperResult->ws_value_lingkungan;
                                    
                                } else {
                                    $datlapangan = DataLapanganEmisiCerobong::where('no_sampel', $no_sampel)->first();
                                    
                                    if(!$datlapangan) {
                                        $icpData['status'] = 'error';
                                        $errors[] = "Data lapangan emisi cerobong tidak ditemukan untuk no sampel $no_sampel";
                                        $icpData['error_message'] = json_encode($errors);
                                        $icpInserts[] = $icpData;
                                        $draftIcp = (array) $payload;
                                        $draftIcpInserts[] = $draftIcp;
                                        continue;
                                    }
                                    
                                    $helperResult = $this->HelperEmisiCerobong($payload, $stp, $order_detail, $datlapangan, $par);
                                    
                                    if(isset($helperResult->status) && $helperResult->status != 200){
                                        $icpData['status'] = 'error';
                                        $errors[] = $helperResult->message;
                                        $icpData['error_message'] = json_encode($errors);
                                        $icpInserts[] = $icpData;
                                        continue;
                                    }
                                    
                                    // Simpan data untuk batch insert
                                    $emisiCerobongHeaderInserts[] = $helperResult->header;
                                    $wsValueEmisiCerobongData[] = $helperResult->ws_value_emisi;
                                }
                                
                                // Hapus DraftIcp insert karena sekarang langsung ke header
                                // $draftIcpInserts[] = $draftIcpData; // HAPUS BARIS INI
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
            // dd('berhasil horeee', $icpInserts, $draftIcpInserts, $lingkunganHeaderInserts, $emisiCerobongHeaderInserts, $wsValueUdaraData, $wsValueLingkunganData, $wsValueEmisiCerobongData);

            // Process Lingkungan Header data efficiently
            if (!empty($lingkunganHeaderInserts)) {
                $colorimetriRecords = [];
                
                foreach ($lingkunganHeaderInserts as $index => $lingkunganHeaderData) {
                    $header = new LingkunganHeader($lingkunganHeaderData);
                    $header->save();
                    
                    // Create WsValueAir entry if we have data for this index
                    if (isset($wsValueUdaraData[$index])) {
                        $val = $wsValueUdaraData[$index];
                        $val['id_lingkungan_header'] = $header->id;  // Note: using id_colorimetri as in the second code
                        WsValueUdara::create($val);
                    }
                    if (isset($wsValueLingkunganData[$index])) {
                        $val = $wsValueLingkunganData[$index];
                        $val['lingkungan_header_id'] = $header->id;  // Note: using id_colorimetri as in the second code
                        WsValueLingkungan::create($val);
                    }
                }
            }

            // Process Emisi Cerobong data efficiently
            if (!empty($emisiCerobongHeaderInserts)) {
                $colorimetriRecords = [];
                
                foreach ($emisiCerobongHeaderInserts as $index => $emisiCerobongHeaderData) {
                    $header = new EmisiCerobongHeader($emisiCerobongHeaderData);
                    $header->save();
                    
                    // Create WsValueAir entry if we have data for this index
                    if (isset($wsValueEmisiCerobongData[$index])) {
                        $val = $wsValueEmisiCerobongData[$index];
                        $val['id_emisi_cerobong_header'] = $header->id;  // Note: using id_colorimetri as in the second code
                        WsValueEmisiCerobong::create($val);
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
                'trace' => $e->getTrace()
            ], 500);
        }
    }

    private function HelperLingkungan($request, $stp, $check, $datlapanganh, $datlapangank, $datlapanganV, $par)
    {
		// dd($request->all());
		$wsling = LingkunganHeader::where('no_sampel', $request->no_sampel)->where('parameter', $request->parameter)->where('is_active', true)->first();
		if ($wsling) {
			return (object)[
				'message' => 'Parameter sudah diinput..!!',
				'status' => 403
			];
		} else {
			$parame = $request->parameter;
            $tipe_data = null;
            $isO3 = strpos($par->nama_lab, 'O3') !== false;

            // dd($datot);
            $rerata = [];
            $durasi = [];
            $tekanan_u = [];
            $suhu = [];
            $Qs = [];

            // O3 Kasus Khusus, Bagi 2 Bjir
            $rerataO3 = [];
            $durasiO3 = [];
            $tekanan_uO3 = [];
            $suhuO3 = [];
            $QsO3 = [];
            $ks_all = [];
            $kb_all = [];

			if ($datlapanganh != null || $datlapangank != null || $datlapanganV != null) {
				// dd($data_parameter);
				// if ($request->id_stp == 14) {
				// 	$parame = 'TSP';
				// }
				$lingHidup = DetailLingkunganHidup::where('no_sampel', $request->no_sampel)->where('parameter', $parame)->get();
				$lingKerja = DetailLingkunganKerja::where('no_sampel', $request->no_sampel)->where('parameter', $parame)->get();
				$lingVolatile = DetailSenyawaVolatile::where('no_sampel', $request->no_sampel)->where('parameter', $parame)->get();
				// dd($lingHidup, $lingKerja, $lingVolatile,$parame);
				if (!$lingHidup->isEmpty() || !$lingKerja->isEmpty() || !$lingVolatile->isEmpty()) {

					try {
						$datapangan = '';
						if (count($lingHidup) > 0) {
							$datapangan = $lingHidup;
                            $tipe_data = 'ambient';
						}
						if (count($lingKerja) > 0) {
                            $datapangan = $lingKerja;
                            $tipe_data = 'ulk';
						}
						if (count($lingVolatile) > 0) {
                            $datapangan = $lingVolatile;
                            $tipe_data = 'ulk';
						}
						// dd($datapangan);
						if ($datapangan != '') {
							$datot = count($datapangan);
						} else {
							$datot = '';
						}

						// dd($ks_all, $kb_all);
						$nilQs = '';
						if ($datot > 0 || $datot != '') {
							$parameterExplode = explode(' ', $par->nama_lab);
							$is8Jam = count($parameterExplode) > 1 ? strpos($parameterExplode[1], '8J') !== false : false;
							foreach ($datapangan as $keye => $vale) {
								$absorbansi = !is_null($vale->absorbansi) ? json_decode($vale->absorbansi) : null;
								$dat = json_decode($vale->pengukuran);
								// dd($absorbansi, $dat);
								// dd($vale);
								if ($isO3) {
									$durasii = [[], []];
									$flow = [[], []];
									// dump($absorbansi->{"data-4"});
									if (!is_null($absorbansi)) {
										$sample_penjerap_1 = [$absorbansi->{"data-1"}, $absorbansi->{"data-2"}, $absorbansi->{"data-3"}];
										$sample_penjerap_2 = [$absorbansi->{"data-4"}, $absorbansi->{"data-5"}, $absorbansi->{"data-6"}];
										$blanko_penjerap_1 = $absorbansi->blanko;
										$blanko_penjerap_2 = $absorbansi->blanko2;
										$ks = [array_sum($sample_penjerap_1) / count($sample_penjerap_1), array_sum($sample_penjerap_2) / count($sample_penjerap_2)];
										$kb = [$blanko_penjerap_1, $blanko_penjerap_2];
										// dd($ks, $kb);
										array_push($ks_all, $ks);
										array_push($kb_all, $kb);
									}
									$i = 0;
									foreach ($dat as $key => $val) {
										if ($key == 'Durasi' || $key == 'Durasi 2') {
											$formt = (int) str_replace(" menit", "", $val);
											if ($i == 0) {
												array_push($durasii[$i], $formt);
												$i++;
											} else {
												array_push($durasii[$i], $formt);
											}
										} else {
											array_push($flow[$i], $val);
										}
									}
									// dd($flow);
									$avg_flow = array_map(function ($item) use ($vale, &$QsO3, &$rerataO3, &$keye) {
										$avg = array_sum($item) / count($item);
										$Q0 = $avg * pow((298 * $vale->tekanan_udara) / (($vale->suhu + 273) * 760), 0.5);
										$Q0 = str_replace(",", "", number_format($Q0, 4));
										$QsO3[$keye][] =  (float) $Q0;
										$rerataO3[$keye][] =  $avg;
										return $avg;
									}, $flow);

									$tekanan_uO3[] =  $vale->tekanan_udara;
									$suhuO3[] =  $vale->suhu;

									$avg_durasi = array_map(function ($item) use ($vale, &$durasiO3, &$keye) {
										$avg = array_sum($item) / count($item);
										$durasiO3[$keye][] =  $avg;
										return $avg;
									}, $durasii);
								} else {
									$durasii = [];
									$flow = [];
									if (!is_null($absorbansi)) {
										$sample_penjerap_1 = [$absorbansi->{"data-1"}, $absorbansi->{"data-2"}, $absorbansi->{"data-3"}];
										$blanko_penjerap_1 = $absorbansi->blanko;
										$ks = array_sum($sample_penjerap_1) / count($sample_penjerap_1);
										$kb = $blanko_penjerap_1;
										array_push($ks_all, $ks);
										array_push($kb_all, $kb);
									}
									foreach ($dat as $key => $val) {
										if ($key == 'Durasi' || $key == 'Durasi 2') {
											$formt = (int) str_replace(" menit", "", $val);
											array_push($durasii, $formt);
										} else {
											array_push($flow, $val);
										}
									}
									$rera = array_sum($flow) / count($flow);
									// $Q0 = \str_replace(",", "", number_format($rera * ((298 * $vale->tekanan_u) / (($vale->suhu + 273) * 760) ** 1 / 2), 4));
									// $Q0 = \str_replace(",", "", number_format($rera * ((298 * $vale->tekanan_u) / (($vale->suhu + 273) * 760) ** 0.5), 4));

									// Menghitung Q0 sesuai rumus yang benar
									$Q0 = $rera * pow((298 * $vale->tekanan_udara) / (($vale->suhu + 273) * 760), 0.5);

									// Format hasil Q0 agar 4 desimal dan hilangkan koma pemisah ribuan
									$Q0 = str_replace(",", "", number_format($Q0, 4));

									$dur = array_sum($durasii);

									array_push($rerata, $rera);
									array_push($Qs, (float) $Q0);
									array_push($durasi, $dur);
									array_push($tekanan_u, $vale->tekanan_udara);
									array_push($suhu, $vale->suhu);
								}
							}

							if ($isO3) {
								if (!empty($QsO3)) {
									$index1Qs = array_column($QsO3, 0);
									$index2Qs = array_column($QsO3, 1);
									$nil1Qs = array_sum($index1Qs) / count($index1Qs);
									$nil2Qs = array_sum($index2Qs) / count($index2Qs);
								}
								// dd($nilQs);
								$index1Flow = array_column($rerataO3, 0);
								$index2Flow = array_column($rerataO3, 1);
								$rerata1Flow = \str_replace(",", "", number_format(array_sum($index1Flow) / count($index1Flow), 1));
								$rerata2Flow = \str_replace(",", "", number_format(array_sum($index2Flow) / count($index2Flow), 1));
								// if (count($durasiO3) == 1) {
								// 	$durasiFin = array_sum($durasiO3[0]) / count($durasiO3[0]);
								// } else {
								$index1Durasi = array_column($durasiO3, 0);
								$index2Durasi = array_column($durasiO3, 1);
								$rerata1Durasi = array_sum($index1Durasi) / count($index1Durasi);
								$rerata2Durasi = array_sum($index2Durasi) / count($index2Durasi);
								// }
								// $durasiO3 = array_push($durasiO3,$durasiO3[0]);
								$tekananFin = \str_replace(",", "", number_format(array_sum($tekanan_uO3) / $datot, 1));
								$suhuFin = \str_replace(",", "", number_format(array_sum($suhuO3) / $datot, 1));
								// dd($tekananFin, $suhuFin, $rerata1Flow, $rerata2Flow, $flow, $rerata1Durasi, $rerata2Durasi,$durasii, $nil1Qs, $nil2Qs, $QsO3);
							} else {
								if (!empty($Qs)) {
									$nilQs = array_sum($Qs) / $datot;
								}

								// dd($nilQs);
								// dd('RERATA', $rerata);
								$rerataFlow = \str_replace(",", "", number_format(array_sum($rerata) / $datot, 1));
								if (count($durasi) == 1) {
									$durasiFin = $durasi[0];
								} else {
									$durasiFin = array_sum($durasi) / $datot;
								}
								if ($request->parameter == 'Pb (24 Jam)' || $request->parameter == 'PM 2.5 (24 Jam)' || $request->parameter == 'PM 10 (24 Jam)' || $request->parameter == 'TSP (24 Jam)' || $par->id ==  306) {
									$l25 = '';
									if (count($lingHidup) > 0) {

										$l25 = DetailLingkunganHidup::where('no_sampel', $request->no_sampel)->where('parameter', $parame)->where('shift_pengambilan', 'L25')->first();
										if ($l25) {
											$waktu = explode(",", $l25->durasi_pengambilan);
											$jam = preg_replace('/\s+/', '', ($waktu[0] != '') ? str_replace("Jam", "", $waktu[0]) : 0);
											$menit = preg_replace('/\s+/', '', ($waktu[1] != '') ? str_replace("Menit", "", $waktu[1]) : 0);
											$durasiFin = ((int)$jam * 60) + (int)$menit;
										} else {
											$durasiFin = 24 * 60;
										}
									}
									if (count($lingKerja) > 0) {

										$l25 = DetailLingkunganKerja::where('no_sampel', $request->no_sampel)->where('parameter', $parame)->where('shift_pengambilan', 'L25')->first();
										// dd($l25);
										if ($l25) {
											$waktu = explode(",", $l25->durasi_pengambilan);
											$jam = preg_replace('/\s+/', '', ($waktu[0] != '') ? str_replace("Jam", "", $waktu[0]) : 0);
											$menit = preg_replace('/\s+/', '', ($waktu[1] != '') ? str_replace("Menit", "", $waktu[1]) : 0);
											$durasiFin = ((int)$jam * 60) + (int)$menit;
										} else {
											$durasiFin = 24 * 60;
										}
										// dd('masukkk');
									}
									if (count($lingVolatile) > 0) {
										$l25 = DetailSenyawaVolatile::where('no_sampel', $request->no_sampel)->where('parameter', $parame)->where('shift_pengambilan', 'L25')->first();
										if ($l25) {
											$waktu = explode(",", $l25->durasi_pengambilan);
											$jam = preg_replace('/\s+/', '', ($waktu[0] != '') ? str_replace("Jam", "", $waktu[0]) : 0);
											$menit = preg_replace('/\s+/', '', ($waktu[1] != '') ? str_replace("Menit", "", $waktu[1]) : 0);
											$durasiFin = ((int)$jam * 60) + (int)$menit;
										} else {
											$durasiFin = 24 * 60;
										}
									}
								}
								// dd($durasiFin);

								$tekananFin = \str_replace(",", "", number_format(array_sum($tekanan_u) / $datot, 1));
								$suhuFin = \str_replace(",", "", number_format(array_sum($suhu) / $datot, 1));
							}
						} else {
							return (object)[
								'message' => 'No sample tidak ada di lingkungan hidup atau lingkungan kerja.',
								'status' => 404
							];
						}
					} catch (\Exception $e) {
						// dd($e);
						return (object)[
							'message' => 'Error : ' . $e->getMessage(),
							'status' => 500,
							'line' => $e->getLine(),
							'file' => $e->getFile()
						];
					}
				} else {
					return (object)[
						'message' => 'No sample tidak ada pada data lingkungan hidup atau dan lingkungan kerja.',
						'status' => 404
					];
				}
			} else {
				return (object)[
					'message' => 'Data lapangan belum diinputkan oleh Sampler.',
					'status' => 404
				];
			}

            if (is_null($tipe_data)) {
                $tipe_data = 'ulk';
            }

			if (!isset($check->id)) {
				return (object)[
					'message' => 'No Sample tidak ada.!!',
					'status' => 401
				];
			}
			$id_po = $check->id;
			$tgl_terima = $check->tanggal_terima;
			// Proses kalkulasi dengan AnalystFormula

			// $functionObj = Formula::where('id_parameter', $par->id)->where('is_active', true)->first();
			// // dd($functionObj);
			// if (!$functionObj) {
			// 	return (object)[
			// 		'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
			// 		'status' => 404
			// 	];
			// }
			$function = 'LingkunganKerjaLogam';
            if($request->parameter == 'Pb'){
                $function = 'LingkunganHidupLogamPb';
            }else if($request->parameter == 'Pb (24 Jam)'){
                $function = 'LingkunganHidupLogam24_6j';
            }
            // dd($function);
            $ulk_ambient_parameter = [
                'Cl2' => [
                    'ambient' => 'LingkunganHidupCl2',
                    'ulk' => 'LingkunganKerjaCl2'
                ]
            ];

            if(isset($ulk_ambient_parameter[$request->parameter])) {
                $function = $ulk_ambient_parameter[$request->parameter][$tipe_data];
            }

			$data_parsing = clone $request;
			$data_parsing = (object) $data_parsing;
			$data_parsing->use_absorbansi = false;
			$data_parsing->tipe_data = $tipe_data;
			// dd($data_parsing);
			if (!$isO3) {
				$data_parsing->durasi = $durasiFin;
				$data_parsing->nilQs = $nilQs;
				$data_parsing->array_qs = $Qs;
				$data_parsing->data_total = $datot;
				$data_parsing->average_flow = $rerataFlow;
				$data_parsing->flow_array = $rerata;
				$data_parsing->durasi_array = $durasi;
			} else {
				$data_parsing->durasi = [$rerata1Durasi, $rerata2Durasi];
				$data_parsing->nilQs = [$nil1Qs, $nil2Qs];
				$data_parsing->average_flow = [$rerata1Flow, $rerata2Flow];
			}

            // dd($request->all());
			if ($isO3) {
				$data_parsing->ks = array_chunk(array_map('floatval', $request->ks), 2);
				$data_parsing->kb = array_chunk(array_map('floatval', $request->kb), 2);
			} elseif(isset($request->ks)){
				$data_parsing->ks = array_map('floatval', $request->ks);
				$data_parsing->kb = array_map('floatval', $request->kb);
			}

			// dd($data_parsing->ks);

			if (count($ks_all) > 0) {
				$data_parsing->use_absorbansi = true;
				$data_parsing->ks = $ks_all;
			}
			if (count($kb_all) > 0) {
				$data_parsing->kb = $kb_all;
			}
			$data_parsing->tekanan = $tekananFin;
			$data_parsing->suhu = $suhuFin;
            $data_parsing->suhu_array = $suhu;
            $data_parsing->tekanan_array = $tekanan_u;
			$data_parsing->tanggal_terima = $tgl_terima;
			// dd($data_parsing);
			$data_kalkulasi = AnalystFormula::where('function', $function)
				->where('data', $data_parsing)
				->where('id_parameter', $par->id)
				->process();
            
            // if($request->no_sampel == 'AGAB012501/002' && $request->parameter == 'Pb') dd($par);
			if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
                return (object)[
                    'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
					'status' => 404
				];
			}

			if(isset($data_kalkulasi['status']) == 'error'){
				return (object)[
					'message' => isset($data_kalkulasi['message']) ? $data_kalkulasi['message'] : null,
					'trace' => isset($data_kalkulasi['trace']) ? $data_kalkulasi['trace'] : null,
					'line' => isset($data_kalkulasi['line']) ? $data_kalkulasi['line'] : null,
					'status' => 500
				];
			}

			$saveShift = [246, 247, 248, 249, 289, 290, 291, 293, 294, 295, 296, 299, 300, 326, 327, 328, 329, 308];
			try {
				// Siapkan header data
                $headerData = [
                    'no_sampel' => $request->no_sampel,
                    'parameter' => $request->parameter,
                    'template_stp' => $stp->id,
                    'id_parameter' => $par->id,
                    'use_absorbansi' => $data_parsing->use_absorbansi ?? false,
                    'is_approved' => ($data_parsing->use_absorbansi ?? false) ? true : false,
                    'note' => null,
                    'tanggal_terima' => $check->tanggal_terima,
                    'created_by' => 'SYSTEM',
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'data_shift' => null
                ];
                
                // Set data_shift jika ada
                $saveShift = [246, 247, 248, 249, 289, 290, 291, 293, 294, 295, 296, 299, 300, 326, 327, 328, 329, 308];
                if (in_array($par->id, $saveShift) || $stp->id == 13) {
                    if($isO3){
                        $ks = array_chunk(array_map('floatval', $request->ks), 2);
                        $kb = array_chunk(array_map('floatval', $request->kb), 2);
                        $data_shift = array_map(function ($sample, $blanko) {
                            return (object) [
                                "sample" => number_format(array_sum($sample) / count($sample),4),
                                "blanko" => number_format(array_sum($blanko) / count($blanko),4)
                            ];
                        }, $ks, $kb);
                    } else {
                        $data_shift = array_map(function ($sample, $blanko) {
                            return (object) [
                                "sample" => number_format($sample,4),
                                "blanko" => number_format($blanko,4)
                            ];
                        }, $request->ks, $request->kb);
                    }
                    $headerData['data_shift'] = count($data_shift) > 0 ? json_encode($data_shift) : null;
                }
                
                if(isset($data_kalkulasi['data_pershift'])) {
                    $headerData['data_pershift'] = json_encode($data_kalkulasi['data_pershift']);
                }
                
                // Siapkan ws_value_udara data
                if (array_key_exists('data_pershift', $data_kalkulasi)) {
                    unset($data_kalkulasi['data_pershift']);
                }
                
                $wsValueUdaraData = [
                    'no_sampel' => $request->no_sampel,
                    'hasil1'  => isset($data_kalkulasi['C'])   ? $data_kalkulasi['C']   : null,
                    'hasil2'  => isset($data_kalkulasi['C1'])  ? $data_kalkulasi['C1']  : null,
                    'hasil3'  => isset($data_kalkulasi['C2'])  ? $data_kalkulasi['C2']  : null,
                    'hasil4'  => isset($data_kalkulasi['C3'])  ? $data_kalkulasi['C3']  : null,
                    'hasil5'  => isset($data_kalkulasi['C4'])  ? $data_kalkulasi['C4']  : null,
                    'hasil6'  => isset($data_kalkulasi['C5'])  ? $data_kalkulasi['C5']  : null,
                    'hasil7'  => isset($data_kalkulasi['C6'])  ? $data_kalkulasi['C6']  : null,
                    'hasil8'  => isset($data_kalkulasi['C7'])  ? $data_kalkulasi['C7']  : null,
                    'hasil9'  => isset($data_kalkulasi['C8'])  ? $data_kalkulasi['C8']  : null,
                    'hasil10' => isset($data_kalkulasi['C9'])  ? $data_kalkulasi['C9']  : null,
                    'hasil11' => isset($data_kalkulasi['C10']) ? $data_kalkulasi['C10'] : null,
                    'hasil12' => isset($data_kalkulasi['C11']) ? $data_kalkulasi['C11'] : null,
                    'hasil13' => isset($data_kalkulasi['C12']) ? $data_kalkulasi['C12'] : null,
                    'hasil14' => isset($data_kalkulasi['C13']) ? $data_kalkulasi['C13'] : null,
                    'hasil15' => isset($data_kalkulasi['C14']) ? $data_kalkulasi['C14'] : null,
                    'hasil16' => isset($data_kalkulasi['C15']) ? $data_kalkulasi['C15'] : null,
                    'hasil17' => isset($data_kalkulasi['C16']) ? $data_kalkulasi['C16'] : null,
                    'satuan' => $data_kalkulasi['satuan']
                ];
                
                // Siapkan ws_value_lingkungan data
                $wsValueLingkunganData = [
                    'tanggal_terima' => $check->tanggal_terima,
                    'no_sampel' => $request->no_sampel
                ];
                
                // unset($data_kalkulasi['satuan']);
                foreach ($data_kalkulasi as $key => $value) {
                    if ($key !== 'satuan') {
                        $wsValueLingkunganData[$key] = $value;
                    }
                }
                
                return (object)[
                    'message' => 'Success',
                    'status' => 200,
                    'header' => $headerData,
                    'ws_value_udara' => $wsValueUdaraData,
                    'ws_value_lingkungan' => $wsValueLingkunganData
                ];
			} catch (\Exception $e) {
				return (object)[
					'message' => 'Error : ' . $e->getMessage(),
					'line' => $e->getLine(),
					'file' => $e->getFile(),
					'status' => 500,
                    'trace' => $e->getTrace()
				];
			}
		}
	}

    private function HelperEmisiCerobong($request, $stp, $order_detail, $data_lapangan, $par)
	{
		try {
			if ($data_lapangan) {
				$tekanan = (float) $data_lapangan->tekanan_udara;
				$t_flue = (float) $data_lapangan->T_Flue;
				$suhu = (float) $data_lapangan->suhu;
				$nil_pv = self::penentuanPv($suhu);
				$status_par = $request->parameter;

				if ($request->parameter == 'HF') {
					$dat = json_decode($data_lapangan->HF);
				} else if ($request->parameter == 'NH3') {
					$dat = json_decode($data_lapangan->NH3);
				} else if ($request->parameter == 'HCl') {
					$dat = json_decode($data_lapangan->HCI);
				} else if ($request->parameter == 'H2S') {
                    $dat = json_decode($data_lapangan->H2S);
                } else if (in_array($request->parameter, [
					'Debu', 'Partikulat',
					'As', 'Cd', 'Co', 'Cr', 'Cu', 'Hg', 'Mn', 'Pb',
					'Sb', 'Se', 'Tl', 'Zn', 'Sn', 'Al', 'Ba', 'Be', 'Bi',
                    'Debu (P)'
				])) {
					$dat = json_decode($data_lapangan->partikulat);
					$status_par = 'Partikulat';
				}

				if ($data_lapangan->tipe == '1') {
					if($dat != null) {
						if (is_string($dat[0])) {
						$nil_dry = explode("; ", $dat[0]);
						$nil_dry = explode(":", $nil_dry[4]);
						$nil_dry = str_replace(" ", "", $nil_dry[1]);
						// dd($nil_dry);
						$tekanan_dry = (float) $nil_dry;

						$nil_vol = explode("; ", $dat[0]);
						$nil_vol = explode(":", $nil_vol[3]);
						$nil_vol = str_replace(" ", "", $nil_vol[1]);
						$volume_dry = (float) $nil_vol;

						$dura = explode("; ", $dat[0]);
						$dura = explode(":", $dura[2]);
						$dura = str_replace(" ", "", $dura[1]);
						$durasi_dry = (float) $dura;

						$awal = explode("; ", $dat[0]);
						$awal = explode(":", $awal[0]);
						$awal = str_replace(" ", "", $awal[1]);
						$awal_dry = (float) $awal;

						$akhir = explode("; ", $dat[0]);
						$akhir = explode(":", $akhir[1]);
						$akhir = str_replace(" ", "", $akhir[1]);
						$akhir_dry = (float) $akhir;
						$flow = ($akhir_dry + $awal_dry) / 2; //04-03-2025
						} else if (is_object($dat[0])) {
							$tekanan_dry = (float) $dat[0]->tekanan;
							$volume_dry = (float) $dat[0]->volume;
							$durasi_dry = (float) $dat[0]->durasi;
							$awal_dry = (float) $dat[0]->flow_awal;
							$akhir_dry = (float) $dat[0]->flow_akhir;
							$flow = ($akhir_dry + $awal_dry) / 2;
						}
						// $flow = $akhir_dry + $awal_dry / 2;
					}else {
						return (object)[
							'message' => 'Tidak ditemukan pada data lapangan parameter : ' . $status_par . '',
							'status' => 401
						];
					}
				} else if ($data_lapangan->tipe == '2') {
					$tekanan_dry = 0;
					$volume_dry = 0;
					$durasi_dry = 0;
					$awal_dry = 0;
					$akhir_dry = 0;
					$flow = 0;
				}
			} else {
				$tekanan_dry = 0;
				$volume_dry = 0;
				$durasi_dry = 0;
				$awal_dry = 0;
				$akhir_dry = 0;
				$flow = 0;
				$tekanan = 0;
				$t_flue = 0;
				$suhu = 0;
				$nil_pv = 0;
			}

			$parame = $request->parameter;

			$functionObj = Formula::where('id_parameter', $par->id)->where('is_active', true)->first();
			if (!$functionObj) {
				return (object)[
					'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
					'status' => 404
				];
			}
			$function = $functionObj->function;
			$data_parsing = clone $request;
			$data_parsing = (object)$data_parsing;

			$data_parsing->tekanan_dry = $tekanan_dry;
			$data_parsing->volume_dry = $volume_dry;
			$data_parsing->durasi_dry = $durasi_dry;
			$data_parsing->awal_dry = $awal_dry;
			$data_parsing->akhir_dry = $akhir_dry;
			$data_parsing->flow = $flow;
			$data_parsing->tekanan = $tekanan;
			$data_parsing->t_flue = $t_flue;
			$data_parsing->suhu = $suhu;
			$data_parsing->nil_pv = $nil_pv;
			$data_parsing->tanggal_terima = $order_detail->tanggal_terima;

			$data_kalkulasi = AnalystFormula::where('function', $function)
				->where('data', $data_parsing)
				->where('id_parameter', $par->id)
				->process();

			if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
				return (object)[
					'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
					'status' => 404
				];
			}
			// dd($stp->id);
			$data_analis = array_filter((array) $request, function ($value, $key) {
                $exlude = ['jenis_pengujian', 'note','no_sampel', 'parameter', 'id_stp'];
                return !in_array($key, $exlude);
            }, ARRAY_FILTER_USE_BOTH);

            $formatted_data_analis = [];
            foreach ($data_analis as $key => $value) {
                if ($key === 'ks') {
                    $formatted_data_analis['k_sampel'] = $value;
                } elseif ($key === 'kb') {
                    $formatted_data_analis['k_blanko'] = $value;
                } elseif ($key === 'vs') {
                    $formatted_data_analis['volume_sampel'] = $value;
                } elseif ($key === 'vtp') {
                    $formatted_data_analis['volume_total_pengeceran'] = $value;
                } else {
                    $formatted_data_analis[$key] = $value;
                }
            }
            
            // Siapkan header data
            $headerData = [
                'no_sampel' => $request->no_sampel,
                'parameter' => $request->parameter,
                'template_stp' => $stp->id,
                'id_parameter' => $par->id,
                'note' => null,
                'tanggal_terima' => $order_detail->tanggal_terima,
                'created_by' => 'SYSTEM',
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'data_analis' => json_encode((object) $formatted_data_analis)
            ];

            $wsValueEmisiData = $data_kalkulasi;
            $wsValueEmisiData['no_sampel'] = $request->no_sampel;
            $wsValueEmisiData['created_by'] = 'SYSTEM';
            
            // Siapkan ws_value_emisi data
            // $wsValueEmisiData = [
            //     'no_sampel' => $request['no_sampel'],
            //     'created_by' => 'SYSTEM'
            // ];
            
            // foreach ($data_kalkulasi as $key => $value) {
            //     $wsValueEmisiData[$key] = $value;
            // }
            
            return (object)[
                'message' => 'Success',
                'status' => 200,
                'header' => $headerData,
                'ws_value_emisi' => $wsValueEmisiData
            ];
		} catch (\Exception $e) {
			return (object)[
				'message' => 'Gagal input data: '.$e->getMessage(),
				'status' => 500,
				'line' => $e->getLine(),
				'file' => $e->getFile()
			];
		}
	}

    public function penentuanPv($suhu) {

		if($suhu > 0.0 && $suhu < 0.5 ) {
			$nil_pv = 4.6;
		}else if($suhu >= 0.5 && $suhu < 1 ) {
			$nil_pv = 4.8;
		}else if($suhu >= 1.0 && $suhu < 1.5) {
			$nil_pv = 4.9;
		}else if($suhu >= 1.5 && $suhu < 2) {
			$nil_pv = 5.1;
		}else if($suhu >= 2.0 && $suhu < 2.5) {
			$nil_pv = 5.3;
		}else if($suhu >= 2.5 && $suhu < 3) {
			$nil_pv = 5.5;
		}else if($suhu >= 3.0 && $suhu < 3.5) {
			$nil_pv = 5.7;
		}else if($suhu >= 3.5 && $suhu < 4) {
			$nil_pv = 5.9;
		}else if($suhu >= 4.0 && $suhu < 4.5) {
			$nil_pv = 6.1;
		}else if($suhu >= 4.5 && $suhu < 5) {
			$nil_pv = 6.3;
		}else if($suhu >= 5.0 && $suhu < 5.5) {
			$nil_pv = 6.5;
		}else if($suhu >= 5.5 && $suhu < 6) {
			$nil_pv = 6.8;
		}else if($suhu >= 6.0 && $suhu < 6.5) {
			$nil_pv = 7.0;
		}else if($suhu >= 6.5 && $suhu < 7) {
			$nil_pv = 7.3;
		}else if($suhu >= 7.0 && $suhu < 7.5) {
			$nil_pv = 7.5;
		}else if($suhu >= 7.5 && $suhu < 8) {
			$nil_pv = 7.8;
		}else if($suhu >= 8.0 && $suhu < 8.5) {
			$nil_pv = 8.0;
		}else if($suhu >= 8.5 && $suhu < 9) {
			$nil_pv = 8.3;
		}else if($suhu >= 9.0 && $suhu < 9.5) {
			$nil_pv = 8.6;
		}else if($suhu >= 9.5 && $suhu < 10) {
			$nil_pv = 8.9;
		}else if($suhu >= 10.0 && $suhu < 10.5) {
			$nil_pv = 9.2;
		}else if($suhu >= 10.5 && $suhu < 11) {
			$nil_pv = 9.5;
		}else if($suhu >= 11.0 && $suhu < 11.5) {
			$nil_pv = 9.8;
		}else if($suhu >= 11.5 && $suhu < 12) {
			$nil_pv = 10.2;
		}else if($suhu >= 12.0 && $suhu < 12.5) {
			$nil_pv = 10.5;
		}else if($suhu >= 12.5 && $suhu < 13) {
			$nil_pv = 10.9;
		}else if($suhu >= 13.0 && $suhu < 13.5) {
			$nil_pv = 11.2;
		}else if($suhu >= 13.5 && $suhu < 14) {
			$nil_pv = 11.6;
		}else if($suhu >= 14.0 && $suhu < 14.5) {
			$nil_pv = 12.0;
		}else if($suhu >= 14.5 && $suhu < 15) {
			$nil_pv = 12.4;
		}else if($suhu >= 15.0 && $suhu < 15.5) {
			$nil_pv = 12.8;
		}else if($suhu >= 15.5 && $suhu < 16) {
			$nil_pv = 13.2;
		}else if($suhu >= 16.0 && $suhu < 16.5) {
			$nil_pv = 13.6;
		}else if($suhu >= 16.5 && $suhu < 17) {
			$nil_pv = 14.1;
		}else if($suhu >= 17.0 && $suhu < 17.5) {
			$nil_pv = 14.5;
		}else if($suhu >= 17.5 && $suhu < 18) {
			$nil_pv = 15.0;
		}else if($suhu >= 18.0 && $suhu < 18.5) {
			$nil_pv = 15.5;
		}else if($suhu >= 18.5 && $suhu < 19) {
			$nil_pv = 16.0;
		}else if($suhu >= 19.0 && $suhu < 19.5) {
			$nil_pv = 16.5;
		}else if($suhu >= 19.5 && $suhu < 20) {
			$nil_pv = 17.0;
		}else if($suhu >= 20.0 && $suhu < 20.5) {
			$nil_pv = 17.5;
		}else if($suhu >= 20.5 && $suhu < 21) {
			$nil_pv = 18.1;
		}else if($suhu >= 21.0 && $suhu < 21.5) {
			$nil_pv = 18.7;
		}else if($suhu >= 21.5 && $suhu < 22) {
			$nil_pv = 19.2;
		}else if($suhu >= 22.0 && $suhu < 22.5) {
			$nil_pv = 19.8;
		}else if($suhu >= 22.5 && $suhu < 23) {
			$nil_pv = 20.4;
		}else if($suhu >= 23.0 && $suhu < 23.5) {
			$nil_pv = 21.1;
		}else if($suhu >= 23.5 && $suhu < 24) {
			$nil_pv = 21.7;
		}else if($suhu >= 24.0 && $suhu < 24.5) {
			$nil_pv = 22.4;
		}else if($suhu >= 24.5 && $suhu < 25) {
			$nil_pv = 23.1;
		}else if($suhu >= 25.0 && $suhu < 25.5) {
			$nil_pv = 23.8;
		}else if($suhu >= 25.5 && $suhu < 26) {
			$nil_pv = 24.5;
		}else if($suhu >= 26.0 && $suhu < 26.5) {
			$nil_pv = 25.2;
		}else if($suhu >= 26.5 && $suhu < 27) {
			$nil_pv = 26.0;
		}else if($suhu >= 27.0 && $suhu < 27.5) {
			$nil_pv = 26.7;
		}else if($suhu >= 27.5 && $suhu < 28) {
			$nil_pv = 27.5;
		}else if($suhu >= 28.0 && $suhu < 28.5) {
			$nil_pv = 28.4;
		}else if($suhu >= 28.5 && $suhu < 29) {
			$nil_pv = 29.2;
		}else if($suhu >= 29.0 && $suhu < 29.5) {
			$nil_pv = 30.1;
		}else if($suhu >= 29.5 && $suhu < 30) {
			$nil_pv = 30.9;
		}else if($suhu >= 30.0 && $suhu < 30.5) {
			$nil_pv = 31.8;
		}else if($suhu >= 30.5 && $suhu < 31) {
			$nil_pv = 32.8;
		}else if($suhu >= 31.0 && $suhu < 31.5) {
			$nil_pv = 33.7;
		}else if($suhu >= 31.5 && $suhu < 32) {
			$nil_pv = 34.7;
		}else if($suhu >= 32.0 && $suhu < 32.5) {
			$nil_pv = 35.7;
		}else if($suhu >= 32.5 && $suhu < 33) {
			$nil_pv = 36.7;
		}else if($suhu >= 33.0 && $suhu < 33.5) {
			$nil_pv = 37.7;
		}else if($suhu >= 33.5 && $suhu < 34) {
			$nil_pv = 38.8;
		}else if($suhu >= 34.0 && $suhu < 34.5) {
			$nil_pv = 39.9;
		}else if($suhu >= 34.5 && $suhu < 35) {
			$nil_pv = 41.0;
		}else if($suhu >= 35.0 && $suhu < 35.5) {
			$nil_pv = 42.2;
		}else if($suhu >= 35.5 && $suhu < 36) {
			$nil_pv = 43.4;
		}else if($suhu >= 36.0 && $suhu < 36.5) {
			$nil_pv = 44.6;
		}else if($suhu >= 36.5 && $suhu < 37) {
			$nil_pv = 45.8;
		}else if($suhu >= 37.0 && $suhu < 37.5) {
			$nil_pv = 47.1;
		}else if($suhu >= 37.5 && $suhu < 38) {
			$nil_pv = 48.4;
		}else if($suhu >= 38.0 && $suhu < 38.5) {
			$nil_pv = 49.7;
		}else if($suhu >= 38.5 && $suhu < 39) {
			$nil_pv = 51.1;
		}else if($suhu >= 39.0 && $suhu < 39.5) {
			$nil_pv = 52.5;
		}else if($suhu >= 39.5 && $suhu < 40) {
			$nil_pv = 53.9;
		}else if($suhu >= 40.0 && $suhu < 40.5) {
			$nil_pv = 55.3;
		}else if($suhu >= 40.5 && $suhu < 41) {
			$nil_pv = 56.8;
		}else if($suhu >= 41.0 && $suhu < 41.5) {
			$nil_pv = 58.4;
		}else if($suhu >= 41.5 && $suhu < 42) {
			$nil_pv = 59.9;
		}else if($suhu >= 42.0 && $suhu < 42.5) {
			$nil_pv = 61.5;
		}else if($suhu >= 42.5 && $suhu < 43) {
			$nil_pv = 63.1;
		}else if($suhu >= 43.0 && $suhu < 43.5) {
			$nil_pv = 64.8;
		}else if($suhu >= 43.5 && $suhu < 44) {
			$nil_pv = 66.5;
		}else if($suhu >= 44.0 && $suhu < 44.5) {
			$nil_pv = 68.3;
		}else if($suhu >= 44.5 && $suhu < 45) {
			$nil_pv = 70.1;
		}else if($suhu >= 45.0 && $suhu < 45.5) {
			$nil_pv = 71.9;
		}else if($suhu >= 45.5 && $suhu < 46) {
			$nil_pv = 73.7;
		}else if($suhu >= 46.0 && $suhu < 46.5) {
			$nil_pv = 75.7;
		}else if($suhu >= 46.5 && $suhu < 47) {
			$nil_pv = 77.6;
		}else if($suhu >= 47.0 && $suhu < 47.5) {
			$nil_pv = 79.6;
		}else if($suhu >= 47.5 && $suhu < 48) {
			$nil_pv = 81.6;
		}else if($suhu >= 48.0 && $suhu < 48.5) {
			$nil_pv = 83.7;
		}else if($suhu >= 48.5 && $suhu < 49) {
			$nil_pv = 85.8;
		}else if($suhu >= 49.0 && $suhu < 49.5) {
			$nil_pv = 88.0;
		}else if($suhu >= 49.5 && $suhu < 50) {
			$nil_pv = 90.2;
		}else if($suhu >= 50.0 && $suhu < 50.5) {
			$nil_pv = 92.5;
		}else if($suhu >= 50.5 && $suhu < 51) {
			$nil_pv = 94.8;
		}else if($suhu >= 51.0 && $suhu < 51.5) {
			$nil_pv = 97.2;
		}else if($suhu >= 51.5 && $suhu < 52) {
			$nil_pv = 99.6;
		}else if($suhu >= 52.0 && $suhu < 52.5) {
			$nil_pv = 102.1;
		}else if($suhu >= 52.5 && $suhu < 53) {
			$nil_pv = 104.6;
		}else if($suhu >= 53.0 && $suhu < 53.5) {
			$nil_pv = 107.2;
		}else if($suhu >= 53.5 && $suhu < 54) {
			$nil_pv = 109.8;
		}else if($suhu >= 54.0 && $suhu < 54.5) {
			$nil_pv = 112.5;
		}else if($suhu >= 54.5 && $suhu < 55) {
			$nil_pv = 115.2;
		}else if($suhu >= 55.0 && $suhu < 55.5) {
			$nil_pv = 118.0;
		}else if($suhu >= 55.5 && $suhu < 56) {
			$nil_pv = 120.9;
		}else if($suhu >= 56.0 && $suhu < 56.5) {
			$nil_pv = 123.8;
		}else if($suhu >= 56.5 && $suhu < 57) {
			$nil_pv = 126.7;
		}else if($suhu >= 57.0 && $suhu < 57.5) {
			$nil_pv = 130.8;
		}else if($suhu >= 57.5 && $suhu < 58) {
			$nil_pv = 132.9;
		}else if($suhu >= 58.0 && $suhu < 58.5) {
			$nil_pv = 136.0;
		}else if($suhu >= 58.5 && $suhu < 59) {
			$nil_pv = 139.2;
		}else if($suhu >= 59.0 && $suhu < 59.5) {
			$nil_pv = 142.5;
		}else if($suhu >= 59.5 && $suhu < 60) {
			$nil_pv = 145.9;
		}else if($suhu >= 60.0 && $suhu < 60.5) {
			$nil_pv = 149.3;
		}else if($suhu >= 60.5 && $suhu < 61) {
			$nil_pv = 152.8;
		} else {
			throw new \Exception('Error karena suhu tidak sesuai, suhu di data lapangan adalah ' . $suhu);
		}

		return $nil_pv;
	}
}