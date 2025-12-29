<?php

namespace App\Services;

use Throwable;
use Carbon\Carbon;

use Illuminate\Support\Facades\{
    DB,
    Log
};

use App\Models\{
    DFUS,
    AksesMenu,
    DFUSBackup,
    LogWebphone,
    MasterKaryawan,
    MasterPelanggan,
    LogWebphoneBackup,
    HistoryPerubahanSales,
};

class NonaktifKaryawanService
{
    private const QUOTA_BANK_DATA = 5000;

    private const JABATAN_SALES = 24;
    private const JABATAN_CRO = 148;

    private const TARGET_JABATAN_ROTASI = [self::JABATAN_SALES, self::JABATAN_CRO];

    private $karyawan;
    private $timestamp;

    public function __construct($karyawan)
    {
        $this->karyawan = $karyawan;
        $this->timestamp = Carbon::now();
    }

    public function nonaktifKaryawan()
    {
        try {
            DB::transaction(function () {
                AksesMenu::where('user_id', $this->karyawan->user_id)
                    ->update([
                        'is_active' => false,
                        'deleted_at' => $this->timestamp,
                    ]);

                if (in_array($this->karyawan->id_jabatan, self::TARGET_JABATAN_ROTASI)) {
                    $this->handleSalesRotation();
                    $this->archiveSalesLogs();
                }
            });

            Log::info("Berhasil menonaktifkan karyawan: {$this->karyawan->nama_lengkap}");
        } catch (Throwable $e) {
            Log::error("Gagal menonaktifkan karyawan: {$this->karyawan->nama_lengkap}", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    private function handleSalesRotation()
    {
        $salesStaffs = $this->getActiveStaffsByJabatan(self::JABATAN_SALES, true);
        $croStaffs = $this->getActiveStaffsByJabatan(self::JABATAN_CRO);

        if ($salesStaffs->isEmpty() && $croStaffs->isEmpty()) {
            Log::warning("Tidak ada staff pengganti tersedia untuk rotasi pelanggan {$this->karyawan->nama_lengkap}");
            return;
        }

        $currentBankDataCount = MasterPelanggan::whereNull('sales_id')->where('is_active', true)->count();
        $remainingQuota = max(0, self::QUOTA_BANK_DATA - $currentBankDataCount); // Pake max 0 biar ga negatif

        if ($remainingQuota > 0) {
            // 70% dilempar ke bank data
            $noOrderQuery = MasterPelanggan::where([
                'sales_id' => $this->karyawan->id,
                'is_active' => true
            ])->whereDoesntHave('order_customer');

            $totalCount = $noOrderQuery->count();

            if ($totalCount > 0) {
                $limit70 = min((int) floor($totalCount * 0.7), $remainingQuota);

                if ($limit70 > 0) {
                    $idsToBankData = $noOrderQuery->limit($limit70)->pluck('id_pelanggan')->toArray();

                    if (!empty($idsToBankData)) $this->releaseCustomersToBankData($idsToBankData);
                }
            };
        }

        // sisanya didistribusikan ke sales/CRO lain
        $this->distributeRemainingCustomers($salesStaffs, $croStaffs);
    }

    private function releaseCustomersToBankData($idsToBankData)
    {
        $historyData = array_map(fn($customerId) => [
            'id_pelanggan' => $customerId,
            'id_sales_lama' => $this->karyawan->id,
            'id_sales_baru' => null,
            'tanggal_rotasi' => $this->timestamp,
        ], $idsToBankData);

        // Insert batch pake chunk biar ga nabrak limit placeholder database
        foreach (array_chunk($historyData, 1000) as $chunk) {
            HistoryPerubahanSales::insert($chunk);
        }

        MasterPelanggan::whereIn('id_pelanggan', $idsToBankData)
            ->update([
                'sales_id' => null,
                'sales_penanggung_jawab' => null,
                'updated_by' => 'System',
                'updated_at' => $this->timestamp,
            ]);
    }

    private function distributeRemainingCustomers($salesStaffs, $croStaffs)
    {
        $salesCount = $salesStaffs->count();
        $croCount = $croStaffs->count();

        $salesIndex = 0;
        $croIndex = 0;

        MasterPelanggan::with('order_customer')
            ->where([
                'sales_id' => $this->karyawan->id,
                'is_active' => true
            ])
            ->chunkById(500, function ($customers, $i) use (
                $salesStaffs,
                $croStaffs,
                $salesCount,
                $croCount,
                &$salesIndex,
                &$croIndex
            ) {
                $histories = [];
                $updatesBySalesId = [];

                foreach ($customers as $customer) {
                    $assignToCro = $customer->order_customer->isNotEmpty() || $this->karyawan->id_jabatan == self::JABATAN_CRO;

                    $newSales = null;
                    if ($assignToCro && $croCount > 0) {
                        $newSales = $croStaffs[$croIndex % $croCount];
                        $croIndex++;
                    } elseif ($salesCount > 0) {
                        $newSales = $salesStaffs[$salesIndex % $salesCount];
                        $salesIndex++;
                    } else {
                        // Fallback logic
                        $newSales = $croCount > 0 ? $croStaffs[$croIndex++ % $croCount] : null;
                    }

                    if (!$newSales) continue;

                    $histories[] = [
                        'id_pelanggan' => $customer->id_pelanggan,
                        'id_sales_lama' => $this->karyawan->id,
                        'id_sales_baru' => $newSales->id,
                        'tanggal_rotasi' => $this->timestamp,
                    ];

                    $updatesBySalesId[$newSales->id]['name'] = $newSales->nama_lengkap;
                    $updatesBySalesId[$newSales->id]['ids'][] = $customer->id_pelanggan;
                }

                if (!empty($histories)) HistoryPerubahanSales::insert($histories);

                foreach ($updatesBySalesId as $salesId => $data) {
                    MasterPelanggan::whereIn('id_pelanggan', $data['ids'])
                        ->update([
                            'sales_id' => $salesId,
                            'sales_penanggung_jawab' => $data['name'],
                            'updated_by' => 'System',
                            'updated_at' => $this->timestamp,
                        ]);
                }
            }, 'id_pelanggan');
    }

    private function getActiveStaffsByJabatan($jabatanId, $filterSenior = false)
    {
        return MasterKaryawan::where('id_jabatan', $jabatanId)
            ->where('is_active', true)
            ->when($filterSenior, fn($q) => $q->where('tgl_mulai_kerja', '>', Carbon::now()->subYear()))
            ->get();
    }

    private function archiveSalesLogs()
    {
        $this->archiveAndCleanupLog(
            DFUS::query(),
            DFUSBackup::class,
            'sales_penanggung_jawab',
            $this->karyawan->nama_lengkap
        );

        $this->archiveAndCleanupLog(
            LogWebphone::query(),
            LogWebphoneBackup::class,
            'karyawan_id',
            $this->karyawan->id
        );
    }

    private function archiveAndCleanupLog($queryBuilder, $backupModelClass, $keyField, $keyValue)
    {
        $queryBuilder->where($keyField, $keyValue)
            ->chunkById(500, function ($chunk) use ($backupModelClass) {
                $backupModelClass::insert($chunk->map(function ($item) {
                    $arr = $item->toArray();
                    unset($arr['id']); // Biasakan ID auto-increment baru di tabel backup

                    foreach (['created_at', 'updated_at'] as $dateField) {
                        if (!empty($arr[$dateField])) {
                            $arr[$dateField] = Carbon::parse($arr[$dateField])->format('Y-m-d H:i:s');
                        }
                    }

                    return $arr;
                })->toArray());

                $idsToDelete = $chunk->pluck('id')->toArray();

                $modelClass = get_class($chunk->first());
                $modelClass::whereIn('id', $idsToDelete)->delete();
            }, $keyField);
    }
}
