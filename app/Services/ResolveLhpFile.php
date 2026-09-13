<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;

class ResolveLhpFile
{
    protected $lhpRelations = [
        'lhps_air' => 'LhpsAirHeader',
        'lhps_emisi' => 'LhpsEmisiHeader',
        'lhps_emisi_c' => 'LhpsEmisiCHeader',
        'lhps_emisi_isokinetik' => 'LhpsEmisiIsokinetikHeader',
        'lhps_getaran' => 'LhpsGetaranHeader',
        'lhps_kebisingan' => 'LhpsKebisinganHeader',
        'lhps_kebisingan_personal' => 'LhpsKebisinganPersonalHeader',
        'lhps_ling' => 'LhpsLingHeader',
        'lhps_medanlm' => 'LhpsMedanlmHeader',
        'lhps_pencahayaan' => 'LhpsPencahayaanHeader',
        'lhps_sinaruv' => 'LhpsSinaruvHeader',
        'lhps_ergonomi' => 'DraftErgonomiFile',
        'lhps_iklim' => 'LhpsIklimHeader',
        'lhps_swab_udara' => 'LhpsSwabTesHeader',
        'lhps_microbiologi' => 'LhpsMicrobiologiHeader',
        'lhps_padatan' => 'LhpsPadatanHeader',
        'lhp_psikologi' => 'LhpUdaraPsikologiHeader',
        'lhps_hygiene_sanitasi' => 'LhpsHygieneSanitasiHeader',
    ];

    public function fromOrderDetails(array $orderDetails): ?string
    {
        foreach ($orderDetails as $detail) {
            foreach (array_keys($this->lhpRelations) as $key) {
                $fileLhp = $detail[$key]['file_lhp'] ?? null;
                if (!empty($fileLhp)) {
                    return $fileLhp;
                }
            }
        }

        return null;
    }

    public function byCfr(string $noOrder, string $cfr, ?string $noSampel = null): ?string
    {
        foreach ($this->lhpRelations as $relation => $modelClass) {
            $modelClass = 'App\\Models\\' . $modelClass;
            if (!class_exists($modelClass)) {
                continue;
            }

            $modelInstance = new $modelClass();
            $tableColumns = Schema::getColumnListing($modelInstance->getTable());

            if (!in_array('file_lhp', $tableColumns)) {
                continue;
            }

            $matchColumn = $this->getMatchColumn($relation, $tableColumns);
            if ($matchColumn === null) {
                continue;
            }

            $matchValue = in_array($relation, ['lhps_air', 'lhps_padatan'])
                ? $noSampel
                : $cfr;

            if (empty($matchValue)) {
                continue;
            }

            $query = $modelClass::where('no_order', $noOrder)
                ->where($matchColumn, $matchValue)
                ->whereNotNull('file_lhp');

            if (in_array('is_active', $tableColumns)) {
                $query->where('is_active', 1);
            }

            $fileLhp = $query->value('file_lhp');

            if (!empty($fileLhp)) {
                return $fileLhp;
            }
        }

        return null;
    }

    private function getMatchColumn(string $relation, array $tableColumns): ?string
    {
        if (in_array($relation, ['lhps_air', 'lhps_padatan']) && in_array('no_sampel', $tableColumns)) {
            return 'no_sampel';
        }

        if ($relation === 'lhp_psikologi' && in_array('no_cfr', $tableColumns)) {
            return 'no_cfr';
        }

        if (in_array('no_lhp', $tableColumns)) {
            return 'no_lhp';
        }

        if (in_array('no_cfr', $tableColumns)) {
            return 'no_cfr';
        }

        return null;
    }
}
