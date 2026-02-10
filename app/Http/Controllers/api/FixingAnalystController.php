<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AnalystFormula as ModelsAnalystFormula;
use App\Models\DataLapanganLingkunganHidup;
use App\Models\DataLapanganLingkunganKerja;
use App\Models\DataLapanganSenyawaVolatile;
use App\Models\DetailLingkunganHidup;
use App\Models\DetailLingkunganKerja;
use App\Models\DetailSenyawaVolatile;
use App\Models\LingkunganHeader;
use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\TemplateStp;
use App\Models\WsValueLingkungan;
use App\Models\WsValueUdara;
use App\Services\AnalystFormula;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixingAnalystController extends Controller
{
    /**
     * Recalculate sample based on parameter that comes within the request
     */
    public function recalculateUdaraSampleBasedParameter(Request $request)
    {
        $startDate = $request->start_date ?? NULL;
        $endDate = $request->end_date ?? NULL;
        $parameter = $request->parameter;

        $headerUpdates = [];
        $wsUdaraUpdates = [];
        $wsLingkunganUpdates = [];

        $lingHeaderMap = [];
        $lingDataShiftMap = [];
        $lingHeaderSamples = [];

        // OPTIMIZED: Gunakan eager loading untuk semua data terkait
        // $lingHeader = LingkunganHeader::whereDate('created_at', '>=', $startDate)
        //     ->whereDate('created_at', '<=', $endDate)
        //     ->where('parameter', $parameter)
        //     ->where('is_active', true)
        //     ->get();
        $lingHeader = LingkunganHeader::where('no_sampel',$request->no_sampel)
            ->where('parameter', $parameter)
            ->where('is_active', true)
            ->get();
        // dd(count($lingHeader));
        foreach ($lingHeader as $header) {
            $data_shift_decode = json_decode($header->data_shift, true);
            $lingDataShiftMap[$header->id] = $data_shift_decode;
            $lingHeaderMap["$header->id;$header->no_sampel;$header->template_stp"] = $header;
            $lingHeaderSamples[] = $header->no_sampel;
        }

        // dd(count($lingHeaderMap));
        // Process the remaining data
        // $lingHeaderMap = array_splice($lingHeaderMap, 506, 507);
        // dd(array_keys($lingHeaderMap));

        if ($lingHeader->isEmpty()) {
            return response()->json([
                'message' => 'No records found for the given criteria'
            ], 404);
        }

        // OPTIMIZED: Load semua data OrderDetail sekaligus
        $orderDetailMap = OrderDetail::whereIn('no_sampel', $lingHeaderSamples)
            ->get()
            ->keyBy('no_sampel');

        // OPTIMIZED: Load semua TemplateStp sekaligus
        $templateStps = TemplateStp::whereIn('id', $lingHeader->pluck('template_stp')->unique())
            ->get()
            ->keyBy('id');

        // OPTIMIZED: Load semua Parameter sekaligus
        $parameters = Parameter::whereIn('id', $lingHeader->pluck('id_parameter')->unique())
            ->get()
            ->keyBy('id');

        // OPTIMIZED: Load semua DataLapangan sekaligus
        $datlapanganhMap = DataLapanganLingkunganHidup::whereIn('no_sampel', $lingHeaderSamples)
            ->get()
            ->keyBy('no_sampel');

        $datlapangankMap = DataLapanganLingkunganKerja::whereIn('no_sampel', $lingHeaderSamples)
            ->get()
            ->keyBy('no_sampel');

        $datlapanganVMap = DataLapanganSenyawaVolatile::whereIn('no_sampel', $lingHeaderSamples)
            ->get()
            ->keyBy('no_sampel');

        // OPTIMIZED: Load semua DetailLingkungan sekaligus
        $detailLingkunganHidupMap = DetailLingkunganHidup::whereIn('no_sampel', $lingHeaderSamples)
            ->where('parameter', $parameter)
            ->get()
            ->groupBy('no_sampel');

        $detailLingkunganKerjaMap = DetailLingkunganKerja::whereIn('no_sampel', $lingHeaderSamples)
            ->where('parameter', $parameter)
            ->get()
            ->groupBy('no_sampel');

        $detailSenyawaVolatileMap = DetailSenyawaVolatile::whereIn('no_sampel', $lingHeaderSamples)
            ->where('parameter', $parameter)
            ->get()
            ->groupBy('no_sampel');

        // OPTIMIZED: Load semua AnalystFormula sekaligus
        $parameterIds = $parameters->pluck('id')->toArray();
        $analystFormulas = ModelsAnalystFormula::whereIn('id_parameter', $parameterIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id_parameter');

        $processedCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($lingHeaderMap as $key => $header) {
            try {
                [$headerId, $noSampel, $templateStpId] = explode(';', $key);
                $data_shift_decode = $lingDataShiftMap[$headerId];

                if (!$data_shift_decode || !is_array($data_shift_decode)) {
                    Log::warning("Invalid data_shift for header ID: {$header->id}");
                    $failedCount++;
                    continue;
                }

                $data_shift = array_map(function ($item) {
                    return [
                        'sample' => (float) ($item['sample'] ?? 0),
                        'blanko' => (float) ($item['blanko'] ?? 0),
                    ];
                }, $data_shift_decode);

                $ks = array_column($data_shift, 'sample');
                $kb = array_column($data_shift, 'blanko');

                $payload = (object) [
                    'no_sample' => $header->no_sampel,
                    'parameter' => $parameter,
                    'ks' => $ks,
                    'kb' => $kb
                ];

                // OPTIMIZED: Gunakan data yang sudah di-load
                $order_detail = $orderDetailMap[$header->no_sampel] ?? null;
                $stp = $templateStps[$header->template_stp] ?? null;
                $par = $parameters[$header->id_parameter] ?? null;

                if (!$order_detail || !$stp || !$par) {
                    Log::warning("Missing related data for header ID: {$headerId}");
                    $failedCount++;
                    continue;
                }

                // OPTIMIZED: Gunakan data dari map
                $datlapanganh = $datlapanganhMap[$header->no_sampel] ?? null;
                $datlapangank = $datlapangankMap[$header->no_sampel] ?? null;
                $datlapanganV = $datlapanganVMap[$header->no_sampel] ?? null;

                // OPTIMIZED: Gunakan data detail dari map
                $lingHidup = $detailLingkunganHidupMap[$header->no_sampel] ?? collect();
                $lingKerja = $detailLingkunganKerjaMap[$header->no_sampel] ?? collect();
                $lingVolatile = $detailSenyawaVolatileMap[$header->no_sampel] ?? collect();

                // OPTIMIZED: Panggil fungsi helper dengan data yang sudah di-load
                $result = $this->HelperLingkunganOptimized(
                    $payload,
                    $header,
                    $stp,
                    $order_detail,
                    $datlapanganh,
                    $datlapangank,
                    $datlapanganV,
                    $par,
                    $lingHidup,
                    $lingKerja,
                    $lingVolatile,
                    $analystFormulas[$par->id] ?? null
                );

                if ($result->status == 200) {
                    $headerUpdates[] = $result->header;
                    $wsUdaraUpdates[] = $result->ws_value_udara;
                    $wsLingkunganUpdates[] = $result->ws_value_lingkungan;
                    $processedCount++;
                } else {
                    $failedCount++;
                    $errors[] = [
                        'no_sampel' => $header->no_sampel,
                        'message' => $result->message. ", ". $result->line ?? 'Unknown error'
                    ];
                }
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = [
                    'no_sampel' => $header->no_sampel ?? 'Unknown',
                    'message' => $e->getMessage() . ", " . $e->getFile() . " line " . $e->getLine()
                ];
                Log::error("Error processing header: " . $e->getMessage());
            }
        }

        if (empty($headerUpdates)) {
            return response()->json([
                'message' => 'No records were successfully processed',
                'failed_count' => $failedCount,
                'errors' => $errors
            ], 422);
        }

        DB::beginTransaction();
        try {
            $updatedCount = 0;

            // dd(array_map(function ($item) {
            //     return $item['no_sampel'];
            // }, $wsUdaraUpdates));
            // dd('YESSS', count($headerUpdates), count($wsUdaraUpdates), count($wsLingkunganUpdates));
            foreach ($headerUpdates as $index => $headerData) {
                $id = $headerData['id'];
                unset($headerData['id']);

                // Update LingkunganHeader
                LingkunganHeader::where('id', $id)->update($headerData);

                // Update WsValueUdara
                if (isset($wsUdaraUpdates[$index])) {
                    $wsUdaraData = $wsUdaraUpdates[$index];
                    $wsUdaraId = $wsUdaraData['id_lingkungan_header'];
                    unset($wsUdaraData['id_lingkungan_header']);

                    WsValueUdara::where('id_lingkungan_header', $wsUdaraId)->update($wsUdaraData);
                }

                // Update WsValueLingkungan
                if (isset($wsLingkunganUpdates[$index])) {
                    $wsLingkunganData = $wsLingkunganUpdates[$index];
                    $lingkunganHeaderId = $wsLingkunganData['lingkungan_header_id'];
                    unset($wsLingkunganData['lingkungan_header_id']);

                    WsValueLingkungan::where('lingkungan_header_id', $lingkunganHeaderId)->update($wsLingkunganData);
                }

                $updatedCount++;
            }

            DB::commit();

            return response()->json([
                'message' => 'Recalculation completed successfully',
                'processed_records' => $processedCount,
                'updated_records' => $updatedCount,
                'failed_records' => $failedCount,
                'errors' => $errors
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Database update error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'Database update failed: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Optimized version of HelperLingkungan with pre-loaded data
     */
    private function HelperLingkunganOptimized(
        $request,
        $header,
        $stp,
        $check,
        $datlapanganh,
        $datlapangank,
        $datlapanganV,
        $par,
        $lingHidup,
        $lingKerja,
        $lingVolatile,
        $functionObj = null
    ) {
        $parame = $request->parameter;
        $tipe_data = null;
        $isO3 = strpos($par->nama_lab, 'O3') !== false;

        $rerata = [];
        $durasi = [];
        $tekanan_u = [];
        $suhu = [];
        $Qs = [];

        // O3 Kasus Khusus
        $rerataO3 = [];
        $durasiO3 = [];
        $tekanan_uO3 = [];
        $suhuO3 = [];
        $QsO3 = [];
        $ks_all = [];
        $kb_all = [];

        // OPTIMIZED: Gunakan data yang sudah di-pass sebagai parameter
        if ($datlapanganh != null || $datlapangank != null || $datlapanganV != null) {
            // OPTIMIZED: Tidak perlu query lagi, gunakan data yang sudah di-load
            $datapangan = null;
            
            if ($lingHidup->isNotEmpty()) {
                $datapangan = $lingHidup;
                $tipe_data = 'ambient';
            } elseif ($lingKerja->isNotEmpty()) {
                $datapangan = $lingKerja;
                $tipe_data = 'ulk';
            } elseif ($lingVolatile->isNotEmpty()) {
                $datapangan = $lingVolatile;
                $tipe_data = 'ulk';
            }

            if ($datapangan) {
                $datot = $datapangan->count();

                $nilQs = '';
                if ($datot > 0) {
                    $parameterExplode = explode(' ', $par->nama_lab);
                    $is8Jam = count($parameterExplode) > 1 ? strpos($parameterExplode[1], '8J') !== false : false;

                    foreach ($datapangan as $keye => $vale) {
                        $absorbansi = !is_null($vale->absorbansi) ? json_decode($vale->absorbansi) : null;
                        $dat = json_decode($vale->pengukuran);

                        if ($isO3) {
                            $durasii = [[], []];
                            $flow = [[], []];

                            if (!is_null($absorbansi)) {
                                $sample_penjerap_1 = [$absorbansi->{"data-1"}, $absorbansi->{"data-2"}, $absorbansi->{"data-3"}];
                                $sample_penjerap_2 = [$absorbansi->{"data-4"}, $absorbansi->{"data-5"}, $absorbansi->{"data-6"}];
                                $blanko_penjerap_1 = $absorbansi->blanko;
                                $blanko_penjerap_2 = $absorbansi->blanko2;
                                $ks = [array_sum($sample_penjerap_1) / count($sample_penjerap_1), array_sum($sample_penjerap_2) / count($sample_penjerap_2)];
                                $kb = [$blanko_penjerap_1, $blanko_penjerap_2];
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

                            $avg_flow = array_map(function ($item) use ($vale, &$QsO3, &$rerataO3, &$keye) {
                                $avg = array_sum($item) / count($item);
                                $Q0 = $avg * pow((298 * $vale->tekanan_udara) / (($vale->suhu + 273) * 760), 0.5);
                                $Q0 = str_replace(",", "", number_format($Q0, 4));
                                $QsO3[$keye][] = (float) $Q0;
                                $rerataO3[$keye][] = $avg;
                                return $avg;
                            }, $flow);

                            $tekanan_uO3[] = $vale->tekanan_udara;
                            $suhuO3[] = $vale->suhu;

                            $avg_durasi = array_map(function ($item) use ($vale, &$durasiO3, &$keye) {
                                $avg = array_sum($item) / count($item);
                                $durasiO3[$keye][] = $avg;
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
                            $Q0 = $rera * pow((298 * $vale->tekanan_udara) / (($vale->suhu + 273) * 760), 0.5);
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

                        $index1Flow = array_column($rerataO3, 0);
                        $index2Flow = array_column($rerataO3, 1);
                        $rerata1Flow = str_replace(",", "", number_format(array_sum($index1Flow) / count($index1Flow), 1));
                        $rerata2Flow = str_replace(",", "", number_format(array_sum($index2Flow) / count($index2Flow), 1));

                        $index1Durasi = array_column($durasiO3, 0);
                        $index2Durasi = array_column($durasiO3, 1);
                        $rerata1Durasi = array_sum($index1Durasi) / count($index1Durasi);
                        $rerata2Durasi = array_sum($index2Durasi) / count($index2Durasi);

                        $tekananFin = str_replace(",", "", number_format(array_sum($tekanan_uO3) / $datot, 1));
                        $suhuFin = str_replace(",", "", number_format(array_sum($suhuO3) / $datot, 1));
                    } else {
                        if (!empty($Qs)) {
                            $nilQs = array_sum($Qs) / $datot;
                        }

                        $rerataFlow = str_replace(",", "", number_format(array_sum($rerata) / $datot, 1));

                        if (count($durasi) == 1) {
                            $durasiFin = $durasi[0];
                        } else {
                            $durasiFin = array_sum($durasi) / $datot;
                        }

                        // OPTIMIZED: Cari L25 dari data yang sudah di-load
                        if (in_array($request->parameter, ['Pb (24 Jam)', 'PM 2.5 (24 Jam)', 'PM 10 (24 Jam)', 'TSP (24 Jam)']) || $par->id == 306) {
                            $l25 = null;

                            if ($lingHidup->isNotEmpty()) {
                                $l25 = $lingHidup->firstWhere('shift_pengambilan', 'L25');
                            } elseif ($lingKerja->isNotEmpty()) {
                                $l25 = $lingKerja->firstWhere('shift_pengambilan', 'L25');
                            } elseif ($lingVolatile->isNotEmpty()) {
                                $l25 = $lingVolatile->firstWhere('shift_pengambilan', 'L25');
                            }

                            if ($l25) {
                                $waktu = explode(",", $l25->durasi_pengambilan);
                                $jam = preg_replace('/\s+/', '', ($waktu[0] != '') ? str_replace("Jam", "", $waktu[0]) : 0);
                                $menit = preg_replace('/\s+/', '', ($waktu[1] != '') ? str_replace("Menit", "", $waktu[1]) : 0);
                                $durasiFin = ((int) $jam * 60) + (int) $menit;
                            } else {
                                $durasiFin = 24 * 60;
                            }
                        }

                        $tekananFin = str_replace(",", "", number_format(array_sum($tekanan_u) / $datot, 1));
                        $suhuFin = str_replace(",", "", number_format(array_sum($suhu) / $datot, 1));
                    }
                } else {
                    return (object) [
                        'message' => 'No sample tidak ada di lingkungan hidup atau lingkungan kerja.',
                        'status' => 404
                    ];
                }
            } else {
                return (object) [
                    'message' => 'No sample tidak ada pada data lingkungan hidup atau dan lingkungan kerja.',
                    'status' => 404
                ];
            }
        } else {
            return (object) [
                'message' => 'Data lapangan belum diinputkan oleh Sampler.',
                'status' => 404
            ];
        }

        if (is_null($tipe_data)) {
            $tipe_data = 'ulk';
        }

        if (!isset($check->id)) {
            return (object) [
                'message' => 'No Sample tidak ada.!!',
                'status' => 401
            ];
        }

        $id_po = $check->id;
        $tgl_terima = $check->tanggal_terima;

        // OPTIMIZED: Gunakan functionObj yang sudah di-pass
        if (!$functionObj) {
            return (object) [
                'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
                'status' => 404
            ];
        }

        $function = $functionObj->function;

        $ulk_ambient_parameter = [
            'Cl2' => [
                'ambient' => 'LingkunganHidupCl2',
                'ulk' => 'LingkunganKerjaCl2'
            ]
        ];

        if (isset($ulk_ambient_parameter[$request->parameter])) {
            $function = $ulk_ambient_parameter[$request->parameter][$tipe_data];
        }

        $data_parsing = clone $request;
        $data_parsing = (object) $data_parsing;
        $data_parsing->use_absorbansi = false;
        $data_parsing->tipe_data = $tipe_data;

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

        if ($isO3) {
            $data_parsing->ks = array_chunk(array_map('floatval', $request->ks), 2);
            $data_parsing->kb = array_chunk(array_map('floatval', $request->kb), 2);
        } elseif (isset($request->ks)) {
            $data_parsing->ks = array_map('floatval', $request->ks);
            $data_parsing->kb = array_map('floatval', $request->kb);
        }

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

        // Panggil service AnalystFormula
        $data_kalkulasi = AnalystFormula::where('function', $function)
            ->where('data', $data_parsing)
            ->where('id_parameter', $par->id)
            ->process();

        if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
            return (object) [
                'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
                'status' => 404
            ];
        }

        if (isset($data_kalkulasi['status']) && $data_kalkulasi['status'] == 'error') {
            return (object) [
                'message' => isset($data_kalkulasi['message']) ? $data_kalkulasi['message'] : null,
                'trace' => isset($data_kalkulasi['trace']) ? $data_kalkulasi['trace'] : null,
                'line' => isset($data_kalkulasi['line']) ? $data_kalkulasi['line'] : null,
                'status' => 500
            ];
        }

        $saveShift = [246, 247, 248, 249, 289, 290, 291, 293, 294, 295, 296, 299, 300, 326, 327, 328, 329, 308];

        try {
            // Siapkan header data untuk UPDATE
            $headerData = [
                'id' => $header->id,
                'use_absorbansi' => $data_parsing->use_absorbansi ?? false,
                'note' => null,
                'data_shift' => null
            ];

            // Set data_shift jika ada
            if (in_array($par->id, $saveShift) || $stp->id == 13) {
                if ($isO3) {
                    $ks = array_chunk(array_map('floatval', $request->ks), 2);
                    $kb = array_chunk(array_map('floatval', $request->kb), 2);
                    $data_shift = array_map(function ($sample, $blanko) {
                        return (object) [
                            "sample" => number_format(array_sum($sample) / count($sample), 4),
                            "blanko" => number_format(array_sum($blanko) / count($blanko), 4)
                        ];
                    }, $ks, $kb);
                } else {
                    $data_shift = array_map(function ($sample, $blanko) {
                        return (object) [
                            "sample" => number_format($sample, 4),
                            "blanko" => number_format($blanko, 4)
                        ];
                    }, $request->ks, $request->kb);
                }
                $headerData['data_shift'] = count($data_shift) > 0 ? json_encode($data_shift) : null;
            }

            if (isset($data_kalkulasi['data_pershift'])) {
                $headerData['data_pershift'] = json_encode($data_kalkulasi['data_pershift']);
            }

            // Siapkan ws_value_udara data untuk UPDATE
            if (array_key_exists('data_pershift', $data_kalkulasi)) {
                unset($data_kalkulasi['data_pershift']);
            }

            $wsValueUdaraData = [
                'id_lingkungan_header' => $header->id,
                'no_sampel' => $request->no_sample,
                'hasil1' => isset($data_kalkulasi['C']) ? $data_kalkulasi['C'] : null,
                'hasil2' => isset($data_kalkulasi['C1']) ? $data_kalkulasi['C1'] : null,
                'hasil3' => isset($data_kalkulasi['C2']) ? $data_kalkulasi['C2'] : null,
                'hasil4' => isset($data_kalkulasi['C3']) ? $data_kalkulasi['C3'] : null,
                'hasil5' => isset($data_kalkulasi['C4']) ? $data_kalkulasi['C4'] : null,
                'hasil6' => isset($data_kalkulasi['C5']) ? $data_kalkulasi['C5'] : null,
                'hasil7' => isset($data_kalkulasi['C6']) ? $data_kalkulasi['C6'] : null,
                'hasil8' => isset($data_kalkulasi['C7']) ? $data_kalkulasi['C7'] : null,
                'hasil9' => isset($data_kalkulasi['C8']) ? $data_kalkulasi['C8'] : null,
                'hasil10' => isset($data_kalkulasi['C9']) ? $data_kalkulasi['C9'] : null,
                'hasil11' => isset($data_kalkulasi['C10']) ? $data_kalkulasi['C10'] : null,
                'hasil12' => isset($data_kalkulasi['C11']) ? $data_kalkulasi['C11'] : null,
                'hasil13' => isset($data_kalkulasi['C12']) ? $data_kalkulasi['C12'] : null,
                'hasil14' => isset($data_kalkulasi['C13']) ? $data_kalkulasi['C13'] : null,
                'hasil15' => isset($data_kalkulasi['C14']) ? $data_kalkulasi['C14'] : null,
                'hasil16' => isset($data_kalkulasi['C15']) ? $data_kalkulasi['C15'] : null,
                'hasil17' => isset($data_kalkulasi['C16']) ? $data_kalkulasi['C16'] : null,
                'satuan' => $data_kalkulasi['satuan']
            ];

            // Siapkan ws_value_lingkungan data untuk UPDATE
            $wsValueLingkunganData = [
                'lingkungan_header_id' => $header->id,
                'tanggal_terima' => $check->tanggal_terima,
                'no_sampel' => $request->no_sample
            ];

            foreach ($data_kalkulasi as $key => $value) {
                if ($key !== 'satuan') {
                    $wsValueLingkunganData[$key] = $value;
                }
            }

            return (object) [
                'message' => 'Success',
                'status' => 200,
                'header' => $headerData,
                'ws_value_udara' => $wsValueUdaraData,
                'ws_value_lingkungan' => $wsValueLingkunganData
            ];
        } catch (\Exception $e) {
            return (object) [
                'message' => 'Error : ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'status' => 500,
                'trace' => $e->getTrace()
            ];
        }
    }

    // Original HelperLingkungan function kept for backward compatibility
    private function HelperLingkungan($request, $header, $stp, $check, $datlapanganh, $datlapangank, $datlapanganV, $par)
    {
        // OPTIMIZED: Redirect ke fungsi yang dioptimalkan
        return $this->HelperLingkunganOptimized(
            $request,
            $header,
            $stp,
            $check,
            $datlapanganh,
            $datlapangank,
            $datlapanganV,
            $par,
            collect(),
            collect(),
            collect(),
            null
        );
    }
}