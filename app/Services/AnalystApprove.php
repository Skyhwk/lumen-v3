<?php

namespace App\Services;
use Carbon\Carbon;
use App\Models\Colorimetri;
use App\Models\Titrimetri;
use App\Models\Gravimetri;
use App\Models\SubKontrak;
use App\Models\LingkunganHeader;
use App\Models\DebuPersonalHeader;
use App\Models\DustFallHeader;
use App\Models\EmisiCerobongHeader;
use Illuminate\Support\Facades\Log;

class AnalystApprove
{
    private $year;
    private $managerName;

    private const MODELS = [
        'colorimetri' => [
            'model' => Colorimetri::class,
            'approved_field' => 'is_approved'
        ],
        'titrimetri' => [
            'model' => Titrimetri::class,
            'approved_field' => 'is_approved'
        ],
        'gravimetri' => [
            'model' => Gravimetri::class,
            'approved_field' => 'is_approved'
        ],
        'sub_kontrak' => [
            'model' => SubKontrak::class,
            'approved_field' => 'is_approve' // Note: different field name
        ],
        'lingkungan' => [
            'model' => LingkunganHeader::class,
            'approved_field' => 'is_approved'
        ],
        'debu_personal' => [
            'model' => DebuPersonalHeader::class,
            'approved_field' => 'is_approved'
        ],
        'dust_fall' => [
            'model' => DustFallHeader::class,
            'approved_field' => 'is_approved'
        ],
        'emisi_cerobong' => [
            'model' => EmisiCerobongHeader::class,
            'approved_field' => 'is_approved'
        ]
    ];

    public function __construct()
    {
        $this->managerName = "SYSTEM";
    }

    public function year($value)
    {
        $this->year = $value;
        return $this;
    }

    private function getTimeRange()
    {
        $now = Carbon::now();
        $fiveHoursAgo = $now->copy()->subHours(24);

        return $fiveHoursAgo;
    }

    public function run()
    {
        try {
            Log::channel('analyst_approve')->info("[WorkerApproveAnalyst] Running untuk tahun {$this->year}");

            foreach (self::MODELS as $type => $config) {
                $this->approveByType($type, $config);
            }

            Log::channel('analyst_approve')->info("[WorkerApproveAnalyst] Proses approve analyst berhasil dijalankan untuk tahun {$this->year}");
            return response()->json([
                'status' => true,
                'message' => 'Proses approve analyst berhasil dijalankan untuk tahun ' . $this->year
            ], 200);
        } catch (\Throwable $th) {
            Log::channel('analyst_approve')->error('[WorkerApproveAnalyst] Error : %s, Line : %s, File : %s', [$th->getMessage(), $th->getLine(), $th->getFile()]);
            throw $th;
        }
    }

    private function approveByType(string $type, array $config): void
    {
        $modelClass = $config['model'];
        $approvedField = $config['approved_field'];

        $ids = $this->getModelIds($modelClass, $approvedField);

        if (empty($ids)) {
            Log::channel('analyst_approve')->info("[WorkerApproveAnalyst] No {$type} records to approve");
            return;
        }

        try {
            $data = $modelClass::whereIn('id', $ids)
                ->update([
                    $approvedField => true,
                    'approved_at' => Carbon::now(),
                    'approved_by' => $this->managerName,
                ]);

            Log::channel('analyst_approve')->info("[WorkerApproveAnalyst] Approved {$type} IDs: " . implode(',', $ids));
        } catch (\Throwable $th) {
            Log::channel('analyst_approve')->error("[WorkerApproveAnalyst] Error approving {$type}: " . $th->getMessage());
            throw $th;
        }
    }

    private function getModelIds(string $modelClass, string $approvedField): array
    {
        $timeRange = $this->getTimeRange();

        $data = $modelClass::where('created_at', '<=', $timeRange)
            ->where('is_active', true)
            ->where($approvedField, false);

        if ($modelClass === Colorimetri::class || $modelClass === Titrimetri::class || $modelClass === Gravimetri::class || $modelClass === SubKontrak::class) {
            $data->where('is_total', false);
        }

        $datas = $data->get()->pluck('id')->toArray();

        return $datas;
    }
}