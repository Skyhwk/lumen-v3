<?php

namespace App\Services;

use App\Models\DataLapanganDirectLain;
use App\Models\DetailLingkunganHidup;
use App\Models\DetailLingkunganKerja;
use App\Models\DetailMicrobiologi;
use App\Models\DetailSenyawaVolatile;
use App\Models\OrderDetail;
use App\Models\RequiredParameters;
use App\Models\TemplateStp;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Cek kelengkapan input sampling FDL berdasarkan order_detail.parameter dan required_parameters.
 * Dipakai BAS Online dan pencatatan bas_sampel_selesai saat sampler submit.
 */
class SamplingCompletionService
{
    public function getStatusSampling($sample): string
    {
        try {
            $parametersRaw = json_decode($sample->parameter);
            if (!is_array($parametersRaw)) {
                $parametersRaw = [];
            }

            $templateIcp = TemplateStp::where('name', 'icp')
                ->where('category_id', 4)
                ->first();

            $icpParameters = [];
            if ($templateIcp && $templateIcp->param) {
                $icpParameters = json_decode($templateIcp->param, true) ?? [];
            }

            $requiredParameters = collect($this->getRequiredParameters())
                ->where('category', $sample->kategori_2);

            $hasK3Hygiene = false;
            $parameters = array_reduce($parametersRaw, function ($carry, $item) use ($sample, $requiredParameters, &$hasK3Hygiene) {
                $parameterName = explode(';', $item)[1] ?? null;

                if (!$parameterName) {
                    return $carry;
                }

                if ($this->isUlkHygieneK3Parameter($item, $sample->kategori_3)) {
                    $hasK3Hygiene = true;
                    return $carry;
                }

                $matchedParameter = $requiredParameters
                    ->where('parameter', $parameterName)
                    ->first();

                if ($matchedParameter == null) {
                    throw new Exception("Kemungkinan Parameter.{$parameterName}. Belum Terdaftar di RequiredParameters Hub IT");
                }
                $carry[] = $matchedParameter;
                return $carry;
            }, []);

            $parameters = array_filter($parameters, function ($param) {
                if ($param == null) {
                    return false;
                }
                if ($param['category'] == '6-Padatan') {
                    return is_array($param);
                }
                return is_array($param) && isset($param['model']);
            });

            if ($hasK3Hygiene && empty($parameters)) {
                return 'selesai';
            }

            $status = 'selesai';
            if (!empty($parameters)) {
                $parameterBypass = ['Gelombang Elektro', 'N-Propil Asetat (SC)', 'Xylene secara personil sampling (SC)', 'Psikologi'];

                foreach ($parameters as $parameter) {
                    $paramName = $parameter['parameter'];

                    if ($parameter['category'] == '6-Padatan') {
                        continue;
                    }

                    if (in_array($paramName, $parameterBypass)) {
                        continue;
                    }

                    $sampleNumber = $sample->no_sampel ?? $sample->no_sample;

                    if ($sampleNumber == 'ITEM012501/015' && in_array($paramName, ['NO2 (24 Jam)', 'PM 10 (24 Jam)', 'PM 2.5 (24 Jam)'])) {
                        continue;
                    }

                    if (in_array($sampleNumber, ['BUIL022603/12', 'BUIL022603/14', 'BUIL022603/15', 'BUIL022603/16', 'BUIL022603/008'])) {
                        continue;
                    }

                    if (in_array($paramName, $icpParameters)) {
                        if (!preg_match('/(jam|\d+j)/i', $paramName)) {
                            continue;
                        }
                    }

                    $verified = $this->verifyStatus($sampleNumber, $parameter);

                    if (!$verified) {
                        $status = 'belum selesai';
                        break;
                    }
                }
            } else {
                $status = 'belum selesai';
            }

            return $status;
        } catch (\Exception $th) {
            throw new Exception($th->getMessage());
        }
    }

    private function ulkHygieneK3Names(): array
    {
        return ['K3-KB', 'K3-KFK', 'K3-KFPBP', 'K3-KFS', 'K3-KRU', 'K3-KTRTHK'];
    }

    private function isUlkHygieneK3Parameter($item, $kategori3): bool
    {
        if ($kategori3 !== '27-Udara Lingkungan Kerja') {
            return false;
        }

        $tokens = ['570;K3-KB', '571;K3-KFK', '574;K3-KFPBP', '573;K3-KFS', '575;K3-KRU', '572;K3-KTRTHK'];
        if (in_array($item, $tokens, true)) {
            return true;
        }

        $name = explode(';', (string) $item)[1] ?? (string) $item;
        if (strpos($name, 'K3-') === 0) {
            return true;
        }

        return in_array($name, $this->ulkHygieneK3Names(), true);
    }

    private function verifyStatus($sample_number, $parameter)
    {
        try {
            if (empty($parameter['model'])) {
                return true;
            }

            $model = $parameter['model'];
            $model2 = isset($parameter['model2']) ? $parameter['model2'] : null;
            $model3 = isset($parameter['model3']) ? $parameter['model3'] : null;
            $paramName = isset($parameter['parameter']) ? $parameter['parameter'] : null;

            if (in_array($paramName, ['PM 10 (24 Jam)', 'PM 2.5 (24 Jam)'])) {
                $sampleData = OrderDetail::where('no_sampel', $sample_number)->first();
                if ($sampleData && stripos($sampleData->kategori_3, 'Lingkungan Kerja') !== false) {
                    $parameter['requiredCount'] = 4;
                }
            }

            $requiredCount = isset($parameter['requiredCount']) ? (int) $parameter['requiredCount'] : 1;

            $environmentModels = [
                DetailLingkunganHidup::class,
                DetailLingkunganKerja::class,
                DetailMicrobiologi::class,
            ];

            if (in_array($model, $environmentModels, true)) {
                return $this->handleEnvironmentModel($sample_number, $parameter, $model, $model2, $model3);
            }

            $query = $model::where('no_sampel', $sample_number);

            if ($paramName === 'Opasitas (Solar)') {
                $queryN = clone $query;
                $queryN = $queryN->first();
                if ($queryN == null) {
                    if ($model2 != null) {
                        $query = $model2::where('no_sampel', $sample_number);
                        if (Schema::hasColumn((new $model2)->getTable(), 'is_blocked')) {
                            $query->where('is_blocked', 0);
                        }
                        if (Schema::hasColumn((new $model2)->getTable(), 'is_rejected')) {
                            $query->where('is_rejected', 0);
                        }
                    } else {
                        return null;
                    }
                }
            }
            $modelsWithParameter = [
                DetailLingkunganHidup::class,
                DetailSenyawaVolatile::class,
                DetailMicrobiologi::class,
                DataLapanganDirectLain::class,
            ];
            if (in_array($model, $modelsWithParameter, true) && $paramName !== null) {
                $query->where('parameter', $paramName);
            }

            $count = $query->count();
            if ($count >= $requiredCount) {
                return $query;
            }
            return null;
        } catch (\Throwable $th) {
            Log::error('Error in verifyStatus: ' . $th->getMessage(), [
                'no_sampel' => $sample_number,
                'parameter' => $parameter,
                'line' => $th->getLine(),
            ]);
            throw $th;
        }
    }

    private function handleEnvironmentModel($sample_number, $parameter, $model, $model2, $model3)
    {
        $paramName = isset($parameter['parameter']) ? $parameter['parameter'] : null;
        $requiredCount = isset($parameter['requiredCount']) ? (int) $parameter['requiredCount'] : 1;

        $hasPMParameter = in_array($paramName, ['PM 10 (24 Jam)', 'PM 2.5 (24 Jam)', 'Kelembaban', 'Suhu'], true);
        if (!$hasPMParameter) {
            $model3 = null;
        }

        if ($model3 === null || $model3 === 'App\Models\DetailMicrobiologi') {
            return $this->handleTemperatureHumidity($sample_number, $paramName, $requiredCount, $model, $model2, $model3);
        }

        return $this->handlePMParameters($sample_number, $paramName, $requiredCount, $model, $model2, $model3);
    }

    private function handleTemperatureHumidity($sample_number, $paramName, $requiredCount, $model, $model2, $model3)
    {
        if (in_array($paramName, ['Suhu', 'Kelembaban', 'Laju Ventilasi', 'Laju Ventilasi (8 Jam)'], true)) {
            if ($paramName === 'Kelembaban') {
                $searchColumn = 'Kelembapan';
            } elseif ($paramName === 'Laju Ventilasi' || $paramName === 'Laju Ventilasi (8 Jam)') {
                $searchColumn = 'laju_ventilasi';
            } else {
                $searchColumn = $paramName;
            }

            if ($paramName === 'Laju Ventilasi' || $paramName === 'Laju Ventilasi (8 Jam)') {
                if ($model == DetailLingkunganKerja::class) {
                    $found = $model::where('no_sampel', $sample_number)
                        ->whereNotNull($searchColumn)
                        ->first();
                    if ($found) {
                        return $found;
                    }
                }

                if ($model2 == DetailLingkunganKerja::class) {
                    return $model2::where('no_sampel', $sample_number)
                        ->whereNotNull($searchColumn)
                        ->first();
                }

                return null;
            }

            $found = $model::where('no_sampel', $sample_number)
                ->whereNotNull($searchColumn)
                ->first();

            if ($found) {
                return $found;
            }

            if ($model2) {
                $found = $model2::where('no_sampel', $sample_number)
                    ->whereNotNull($searchColumn)
                    ->first();
                if ($found) {
                    return $found;
                }
            }

            if ($model3) {
                $found = $model3::where('no_sampel', $sample_number)
                    ->whereNotNull($searchColumn)
                    ->first();

                if ($found) {
                    return $found;
                }
            }

            return null;
        }

        if ($paramName === null) {
            return null;
        }
        $query1 = $model::where('no_sampel', $sample_number)
            ->where('parameter', $paramName);
        $count1 = $query1->count();
        if ($count1 >= $requiredCount) {
            return $query1;
        }
        if ($model2) {
            $query2 = $model2::where('no_sampel', $sample_number)
                ->where('parameter', $paramName);
            $count2 = $query2->count();
            if ($count2 >= $requiredCount) {
                return $query2;
            }
        }
        return null;
    }

    private function handlePMParameters($sample_number, $paramName, $requiredCount, $model, $model2, $model3)
    {
        if ($paramName === null) {
            return null;
        }
        $query1 = $model::where('no_sampel', $sample_number)
            ->where('parameter', $paramName);
        $count1 = $query1->count();
        if ($count1 >= $requiredCount) {
            return $query1;
        }
        if ($model2) {
            $query2 = $model2::where('no_sampel', $sample_number)
                ->where('parameter', $paramName);
            $count2 = $query2->count();
            if ($count2 >= $requiredCount) {
                return $query2;
            }
        }
        $query3 = $model3::where('no_sampel', $sample_number)
            ->where('parameter', $paramName);
        $count3 = $query3->count();
        if ($count3 >= $requiredCount) {
            return $query3;
        }
        return null;
    }

    public function getRequiredParameters(): array
    {
        $fromDb = RequiredParameters::all()->map(function ($item) {
            return [
                'parameter'     => $item->parameter,
                'requiredCount' => $item->required_count,
                'category'      => $item->category,
                'model'         => $item->model,
                'model2'        => $item->model2,
                'model3'        => $item->model3,
            ];
        })->toArray();

        $padatanParam = [
            'Al', 'Sb', 'Ag', 'As', 'Ba', 'Fe', 'B', 'Cd', 'Ca', 'Co', 'Mn', 'Na', 'Ni', 'Hg', 'Se', 'Zn', 'Tl', 'Cu', 'Sn', 'Pb', 'Ti', 'Cr', 'V', 'F', 'NO2', 'Cr6+', 'Mo', 'NO3', 'CN', 'Sulfida', 'Cl-', 'OG', 'Chloride', 'E.Coli (MM)', 'Salmonella (MM)', 'Shigella Sp. (MM)', 'Vibrio Ch (MM)', 'S.Aureus',
        ];

        foreach ($padatanParam as $value) {
            $fromDb[] = [
                'parameter'     => $value,
                'requiredCount' => 1,
                'category'      => '6-Padatan',
                'model'         => null,
                'model2'        => null,
            ];
        }

        return $fromDb;
    }
}
