<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

Carbon::setLocale('id');

use Throwable;

use App\Models\MasterKaryawan;
use App\Models\AksesMenu;
use App\Models\MasterPelanggan;
use App\Models\HistoryPerubahanSales;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\DFUS;
use App\Models\DFUSBackup;
use App\Models\LogWebphone;
use App\Models\LogWebphoneBackup;

class NonaktifKaryawanService
{
    public function nonaktifKaryawan(MasterKaryawan $karyawan, string $updatedBy)
    {
        DB::beginTransaction();
        try {
            AksesMenu::where('user_id', $karyawan->user_id)->update([
                'is_active' => false,
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

            if ($karyawan->id_jabatan == 24) {
                $this->reassignCustomer($karyawan);
                $this->deleteUnOrderedQuotations($karyawan->id, $updatedBy);
                $this->deleteDfusHistory($karyawan->nama_lengkap);
                $this->deleteWebphoneLogs($karyawan->id);
            }

            DB::commit();

            Log::info("Berhasil menonaktifkan karyawan: {$karyawan->nama_lengkap}");
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error("Gagal menonaktifkan karyawan: {$karyawan->nama_lengkap}", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function reassignCustomer(MasterKaryawan $karyawan)
    {
        $activeSalesStaff = MasterKaryawan::where('id_jabatan', 24)
            ->where('is_active', true)
            ->where('id', '!=', 41)
            ->get();

        $salesCount = $activeSalesStaff->count();

        $customers = MasterPelanggan::where('sales_id', $karyawan->id)
            ->where('is_active', true)
            ->get(['id_pelanggan', 'sales_id']);

        $now = Carbon::now()->format('Y-m-d H:i:s');

        $customers = $customers->values()->map(function ($customer, $i) use ($activeSalesStaff, $salesCount, $karyawan, $now) {
            $newSales = $activeSalesStaff[$i % $salesCount];

            return [
                'customer' => $customer,
                'newSales' => $newSales,
                'history'  => [
                    'id_pelanggan'   => $customer->id_pelanggan,
                    'id_sales_lama'  => $karyawan->id,
                    'id_sales_baru'  => $newSales->id,
                    'tanggal_rotasi' => $now,
                ]
            ];
        });

        HistoryPerubahanSales::insert($customers->pluck('history')->toArray());

        $customers->groupBy(fn($item) => $item['newSales']->id)
            ->each(function ($group, $salesId) {
                $sales = $group->first()['newSales'];
                $ids   = $group->pluck('customer.id_pelanggan');

                MasterPelanggan::whereIn('id_pelanggan', $ids)
                    ->update([
                        'sales_id'              => $sales->id,
                        'sales_penanggung_jawab' => $sales->nama_lengkap,
                    ]);
            });
    }

    private function deleteUnOrderedQuotations(int $karyawanId, string $updatedBy)
    {
        $newSales = MasterKaryawan::find(783);
        $newSalesId = $newSales->id; // Sisca Wulandari (Sales Executive)
        $newSalesName = $newSales->nama_lengkap; // Sisca Wulandari (Sales Executive)

        $models = [QuotationKontrakH::class, QuotationNonKontrak::class];

        $qtsWithOrder = collect();

        foreach ($models as $model) {
            $qts = $model::where('sales_id', $karyawanId)
                ->where('is_active', true)
                ->whereNotIn('flag_status', ['rejected', 'void'])
                ->get();

            foreach ($qts as $qt) {
                $dataLama = $qt->data_lama ? json_decode($qt->data_lama) : null;

                if ($qt->flag_status == 'ordered' || (isset($dataLama->no_order) && !empty($dataLama->no_order))) {
                    $qtsWithOrder->push($qt->no_document);
                    $qt->update([
                        'sales_id' => $newSalesId,
                        'sales_penanggung_jawab' => $newSalesName,
                        'updated_by' => $updatedBy,
                        'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);
                } else {
                    $qt->update([
                        'deleted_by' => $updatedBy,
                        'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'is_active'  => false,
                    ]);
                }
            }
        }

        // kirim notif ke sales baru
        if ($qtsWithOrder->isNotEmpty()) {
            // Notification::send($newSales, new QuotationNonKontrakNotification($qtsWithOrder));
        }
    }

    private function deleteDfusHistory(string $karyawanName)
    {
        $dfus = DFUS::where('sales_penanggung_jawab', $karyawanName)->get();

        if ($dfus->isNotEmpty()) {
            $dfus->chunk(500)->each(function ($chunk) {
                $data = $chunk->map(function ($log) {
                    $logArr = $log->toArray();
                    unset($logArr['id']);

                    foreach (['created_at', 'updated_at'] as $field) {
                        if (!empty($logArr[$field])) {
                            $logArr[$field] = Carbon::parse($logArr[$field])->format('Y-m-d H:i:s');
                        }
                    }

                    return $logArr;
                })->toArray();

                DFUSBackup::insert($data);
            });

            DFUS::where('sales_penanggung_jawab', $karyawanName)->delete();
        }
    }

    private function deleteWebphoneLogs(int $karyawanId)
    {
        $logs = LogWebphone::where('karyawan_id', $karyawanId)->get();

        if ($logs->isNotEmpty()) {
            $logs->chunk(500)->each(function ($chunk) {
                $data = $chunk->map(function ($log) {
                    $logArr = $log->toArray();
                    unset($logArr['id']);

                    foreach (['created_at'] as $field) {
                        if (!empty($logArr[$field])) {
                            $logArr[$field] = Carbon::parse($logArr[$field])->format('Y-m-d H:i:s');
                        }
                    }

                    return $logArr;
                })->toArray();

                LogWebphoneBackup::insert($data);
            });

            LogWebphone::where('karyawan_id', $karyawanId)->delete();
        }
    }
}
