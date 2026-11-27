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
        'lhp_manual' => 'LhpManual',
    ];

    /** Kolom nama file aktual per jenis header */
    private const FILE_COLUMNS = ['file_lhp', 'no_dokumen', 'name_file'];

    public function fromOrderDetails(array $orderDetails): ?string
    {
        foreach ($orderDetails as $detail) {
            foreach (array_keys($this->lhpRelations) as $key) {
                $related = $detail[$key] ?? null;
                if (!is_array($related)) {
                    continue;
                }

                $fileLhp = $this->pickFileName($related);
                if (!empty($fileLhp)) {
                    return $fileLhp;
                }
            }

            // Snapshot order_berjalan detail sudah dinormalisasi ke file_lhp
            $direct = $this->pickFileName(is_array($detail) ? $detail : []);
            if (!empty($direct)) {
                return $direct;
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

            $availableFileColumns = array_values(array_filter(
                self::FILE_COLUMNS,
                fn ($col) => in_array($col, $tableColumns)
            ));

            if (empty($availableFileColumns)) {
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

            $select = array_values(array_unique(array_merge([$matchColumn], $availableFileColumns)));
            $query = $modelClass::where('no_order', $noOrder)
                ->where($matchColumn, $matchValue)
                ->select($select);

            if (in_array('is_active', $tableColumns)) {
                $query->where('is_active', 1);
            }

            $row = $query->first();
            if (!$row) {
                continue;
            }

            $fileLhp = $this->pickFileName($row->toArray());
            if (!empty($fileLhp)) {
                return $fileLhp;
            }
        }

        return null;
    }

    private function pickFileName(array $record): ?string
    {
        foreach (self::FILE_COLUMNS as $column) {
            if (!empty($record[$column])) {
                return (string) $record[$column];
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
