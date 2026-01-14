<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{
    DFUS,
    DFUSBackup,
    MasterPelanggan,
    MasterKaryawan,
    HistoryPerubahanSales,
    LogWebphone,
    LogWebphoneBackup,
    QuotationKontrakH,
    QuotationNonKontrak,
};

class RandomSalesAssign
{
    private static $salesIdInactive;
    private static $salesIdNew;
    private static $salesPoolIndex = 0;

    public static function run($type = 'check')
    {
        try {
            Log::channel('reassign_customer')->info("\n\n\n== RANDOM SALES ASSIGNER STARTED ==\n\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);
            $customerMasDedi = 127;
            self::$salesIdInactive = MasterKaryawan::select('id', 'nama_lengkap')
                ->where('is_active', false)
                ->whereIn('id_jabatan', [24]) // sales staff
                ->get()
                ->shuffle()
                ->toArray();

            Log::channel('reassign_customer')->info("Finish Collect Inactive Sales ID with total data " . count(self::$salesIdInactive) . "\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);

            self::$salesIdNew = MasterKaryawan::select('id', 'nama_lengkap', 'tgl_mulai_kerja', 'id_jabatan', 'jabatan')
                ->where('is_active', true)
                ->whereIn('id_jabatan', [24]) // sales staff
                ->whereDate('tgl_mulai_kerja', '>=', Carbon::now()->subYear()) // hanya 1 tahun terakhir
                ->get()
                ->shuffle()
                ->toArray();

            Log::channel('reassign_customer')->info("Finish Collect New Sales ID | total data " . count(self::$salesIdNew) . "\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);

            $excludedSalesIds = MasterKaryawan::whereIn('id_jabatan', [15, 21]) // manajer, spv
                ->pluck('id')
                ->toArray();
            $excludedSalesIds[] = 41; // Novva Novita Ayu Putri Rukmana

            $customerWithDeactiveSales = MasterPelanggan::with([
                'kontak_pelanggan',
                'latestOrder:id,no_order,tanggal_order,id_pelanggan',
                'currentSales:id,nama_lengkap',
                'historySales',
            ])
                ->select('id', 'id_pelanggan', 'nama_pelanggan', 'sales_id', 'sales_penanggung_jawab', 'is_active')
                ->where('is_active', true)
                ->where('sales_id', '<>', $customerMasDedi)
                ->whereNotIn('sales_id', $excludedSalesIds)
                ->whereIn('sales_id', array_column(self::$salesIdInactive, 'id'))
                ->get();


            Log::channel('reassign_customer')->info("Finish Collect data Pelanggan with Deactive Sales | total data " . count(self::$salesIdNew) . "\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);

            $allID = $customerWithDeactiveSales->pluck('id')->toArray();

            // dump(count($allID));

            $AssignToSalesExecutive = [];
            $AssignToSalesNew = [];
            $AssignToSalesNewByCheckingAll = [];
            $NotReAssign = [];

            foreach ($customerWithDeactiveSales as $customer) {
                if ($customer->latestOrder) {
                    $customer->reAssignReason = 'Sales Penanggung Jawab lama sudah tidak aktif, dan ada data Order';
                    $AssignToSalesExecutive[] = $customer;
                } else {
                    $customer->reAssignReason = 'Sales Penanggung Jawab lama sudah tidak aktif, dan tidak ada data Order';
                    $AssignToSalesNew[] = $customer;
                }
            }

            Log::channel('reassign_customer')->info("Finish Progress sorting data Pelanggan from old Sales \n | Assign To Sales Executive " . count($AssignToSalesExecutive) . "\n | Assign To Sales New " . count($AssignToSalesNew) . "\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);

            // dump(count($AssignToSalesExecutive), count($AssignToSalesNew));

            // dump('customerWithDeactiveSales', count($customerWithDeactiveSales));

            $customerSpecial = MasterPelanggan::with([
                'kontak_pelanggan',
                'latestOrder:id,no_order,tanggal_order,id_pelanggan',
                'currentSales:id,nama_lengkap',
                'historySales',
            ])
                ->select('id', 'id_pelanggan', 'nama_pelanggan', 'sales_id', 'sales_penanggung_jawab', 'is_active')
                ->where('is_active', true)
                ->whereNotIn('id', $allID)
                ->where('sales_id', '<>', $customerMasDedi)
                ->whereIn('sales_id', $excludedSalesIds)
                ->get();

            // dump('customerSpecial', count($customerSpecial));
            $chunkIndex = 1;

            $customerMustBeReassigned = MasterPelanggan::with([
                'kontak_pelanggan',
                'latestOrder:id,no_order,tanggal_order,id_pelanggan',
                'currentSales:id,nama_lengkap',
                'historySales',
                'latestNonKontrakQuotation:id,pelanggan_ID,no_document,created_at,updated_at',
                'latestKontrakQuotation:id,pelanggan_ID,no_document,created_at,updated_at,periode_kontrak_akhir',
                'latestDFUS:id,id_pelanggan,sales_penanggung_jawab,tanggal',
            ])
                ->select(
                    'id',
                    'id_pelanggan',
                    'nama_pelanggan',
                    'sales_id',
                    'sales_penanggung_jawab',
                    'is_active'
                )
                ->where('is_active', true)
                ->whereNotIn('id', $allID)
                ->where('sales_id', '<>', $customerMasDedi)
                ->whereNotIn('sales_id', $excludedSalesIds)
                ->chunk(2000, function ($customers) use (&$NotReAssign, &$AssignToSalesNewByCheckingAll, &$chunkIndex) {
                    Log::channel('reassign_customer')->info("== Processing Chunk #{$chunkIndex} ==", [
                        'timestamp' => Carbon::now()->toDateTimeString(),
                        'customer_count' => $customers->count(),
                    ]);
                    foreach ($customers as $customer) {

                        // 1. Cek history rotasi 3 bulan
                        if (
                            $customer->historySales
                            && Carbon::parse($customer->historySales->tanggal_rotasi)
                            ->gt(Carbon::now()->subMonths(3))
                        ) {
                            $NotReAssign[] = $customer;
                            continue;
                        }

                        // 2. Penawaran terakhir > 6 bulan
                        $shouldReassignByQuotation = function () use ($customer) {
                            $checkQuotation = self::latestQuot(
                                $customer->latestKontrakQuotation,
                                $customer->latestNonKontrakQuotation,
                                $customer->id
                            );

                            return $checkQuotation
                                && Carbon::parse($checkQuotation)->lt(Carbon::now()->subMonths(6));
                        };

                        $shouldReassignByDFUS = function () use ($customer) {
                            $dfus = $customer->latestDFUSMatch;
                            return $dfus && Carbon::parse($dfus->tanggal)->lt(Carbon::now()->subWeek());
                        };

                        // 3. Jika punya order
                        if ($customer->latestOrder) {

                            // // Order > 8 bulan?
                            // if (Carbon::parse($customer->latestOrder->tanggal_order)->lt(Carbon::now()->subMonths(8))) {

                            //     if ($shouldReassignByQuotation()) {
                            //         $customer->reAssignReason = 'Order lebih dari 8 bulan dan penawaran terakhir lebih dari 6 bulan';
                            //         $AssignToSalesNewByCheckingAll[] = $customer;
                            //     } elseif ($shouldReassignByDFUS()) {
                            //         $customer->reAssignReason = 'Order lebih dari 8 bulan dan follow up sales terakhir lebih dari 1 minggu';
                            //         $AssignToSalesNewByCheckingAll[] = $customer;
                            //     } else {
                            //         $NotReAssign[] = $customer;
                            //         // $customer->reAssignReason = 'Order lebih dari 8 bulan';
                            //         // $AssignToSalesNewByCheckingAll[] = $customer;
                            //     }
                            // } else {
                            //     $NotReAssign[] = $customer;
                            // }

                            continue;
                        }

                        // 4. Tidak punya order
                        if ($shouldReassignByQuotation()) {
                            $customer->reAssignReason = 'Penawaran terakhir lebih dari 6 bulan';
                            $AssignToSalesNewByCheckingAll[] = $customer;
                        } elseif ($shouldReassignByDFUS()) {
                            $customer->reAssignReason = 'Follow up sales terakhir lebih dari 1 minggu';
                            $AssignToSalesNewByCheckingAll[] = $customer;
                        } else {
                            $NotReAssign[] = $customer;
                        }
                    }
                    $chunkIndex++;
                });

            $allDataNeedAssign = collect($AssignToSalesNew)->merge(collect($AssignToSalesNewByCheckingAll));
            $AssignToSalesExecutive = collect($AssignToSalesExecutive);
            $bankDataToAssign = collect([]);
            // Check Bank Data
            $bankData = MasterPelanggan::where('is_active', true)
                ->whereNull('sales_id')
                ->whereNull('sales_penanggung_jawab')
                ->count();

            if ($bankData < 5000) {
                $remainingSlotBankData = 5000 - $bankData;
                $tirtipercen = floor($allDataNeedAssign->count() * 0.3);
                $assignToBankData = 0;
                if ($tirtipercen > $remainingSlotBankData) {
                    $assignToBankData = $remainingSlotBankData;
                } else {
                    $assignToBankData = $tirtipercen;
                }
                // dump(count($allDataNeedAssign));

                $bankDataToAssign = $allDataNeedAssign->take($assignToBankData);
                $allDataNeedAssign = $allDataNeedAssign->slice($assignToBankData)->values();

                // dd(count($bankDataToAssign), count($allDataNeedAssign));
            }

            Log::channel('reassign_customer')->info("Finish Progress sorting All data Pelanggan \n | Assign To New Sales " . count($AssignToSalesNewByCheckingAll) . "\n | Not Re-Assign To Sales New " . count($NotReAssign) . "\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);
            Log::channel('reassign_customer')->info("\n\n== GET DATA TO REASSIGN FINISHED ==\n\n", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);

            Log::channel('reassign_customer')->info("=== Processing Reassign Data ===", [
                'timestamp' => Carbon::now()->toDateTimeString(),
            ]);

            try {
                DB::statement('SET SESSION innodb_lock_wait_timeout = 120');
                DB::statement('SET SESSION lock_wait_timeout = 120');
            } catch (\Exception $e) {
                Log::channel('reassign_customer')->warning("=== Could not set timeout: " . $e->getMessage());
            }
            // Delete webphone and log
            // dump(count($allDataNeedAssign), count($bankDataToAssign), count($AssignToSalesExecutive));
            $allForDelete = collect($allDataNeedAssign)
                ->merge(collect($bankDataToAssign))
                ->merge(collect($AssignToSalesExecutive));

            self::deteleAllReAssignDataWebphoneAndLog($allForDelete);

            // Assign
            DB::transaction(function () use (
                $bankDataToAssign,
                $AssignToSalesExecutive,
                $allDataNeedAssign
            ) {
                self::assignToBankData(collect($bankDataToAssign) ?? []);
                self::assignToSalesExecutive($AssignToSalesExecutive);
                self::assignToNewSales($allDataNeedAssign);
            });
            Log::channel('reassign_customer')->info("=== FINISH REASSIGN === ");

            // if ($type == 'check') {
            //     return [
            //         'status' => 'success',
            //         'message' => 'Success get data to reassign.',
            //         'new_sales' => self::$salesIdNew,
            //         'data from resign sales to new sales' => count($AssignToSalesNew),
            //         'data from resign sales to sales executive' => count($AssignToSalesExecutive),
            //         'data reassign to new sales' => count($AssignToSalesNewByCheckingAll),
            //         'data not reassign' => count($NotReAssign),
            //         'data assign to bank data' => count($bankDataToAssign),
            //         // 'reasons' => $reasons,
            //         // 'data' => $grouped
            //     ];
            // } else if ($type == 'reassign') {
                

            //     return [
            //         'status' => 'success',
            //         'message' => 'Success get data to reassign.',
            //         'new_sales' => self::$salesIdNew,
            //         'data from resign sales to new sales' => count($AssignToSalesNew),
            //         'data from resign sales to sales executive' => count($AssignToSalesExecutive),
            //         'data reassign to new sales' => count($AssignToSalesNewByCheckingAll),
            //         'data not reassign' => count($NotReAssign),
            //         'data assign to bank data' => count($bankDataToAssign),
            //     ];
            // }
        } catch (\Throwable $th) {
            dd($th);
            Log::channel('reassign_customer')->error('error', [$th->getMessage(), $th->getLine(), $th->getFile()]);
            throw $th;
        }
    }

    private static function assignToNewSales($data)
    {
        Log::channel('reassign_customer')->info("=== Processing Assign To New Sales === " . "| Total Data " . count($data));

        if (empty(self::$salesIdNew) || $data->isEmpty()) {
            return;
        }

        $totalNewSales = count(self::$salesIdNew);
        $totalData = count($data);
        $globalIndex = 0; // Track global position across all chunks

        $totalChunks = ceil($totalData / 2000);
        $currentChunk = 0;

        $data->chunk(2000)->each(function ($chunkData) use ($totalNewSales, &$globalIndex, &$currentChunk, $totalChunks) {
            Log::channel('reassign_customer')->info("Processing chunk " . ($currentChunk + 1) . " of " . $totalChunks . " | Total data in chunk: " . count($chunkData));

            $updates = [];
            $histories = [];

            foreach ($chunkData as $pelanggan) {
                $salesIndex = $globalIndex % $totalNewSales;
                $sales = self::$salesIdNew[$salesIndex];

                $globalIndex++; // Increment global counter

                $updates[$sales['id']][] = $pelanggan->id;

                $histories[] = [
                    'id_pelanggan' => $pelanggan->id_pelanggan,
                    'id_sales_lama' => $pelanggan->sales_id,
                    'id_sales_baru' => $sales['id'],
                    'tanggal_rotasi' => Carbon::now(),
                ];
            }

            // BULK UPDATE
            foreach ($updates as $salesId => $pelangganIds) {
                MasterPelanggan::whereIn('id', $pelangganIds)->update([
                    'sales_id' => $salesId,
                    'sales_penanggung_jawab' => collect(self::$salesIdNew)->firstWhere('id', $salesId)['nama_lengkap']
                ]);
            }

            // BULK INSERT HISTORY
            HistoryPerubahanSales::insert($histories);

            Log::channel('reassign_customer')->info("Chunk " . ($currentChunk + 1) . " of " . $totalChunks . " processed | Global Index: " . $globalIndex);
            $currentChunk++;
        });

        Log::channel('reassign_customer')->info("=== Finished Assign To New Sales === " . "| Total Data " . $totalData);
    }



    private static function assignToSalesExecutive($data)
    {
        Log::channel('reassign_customer')->info("=== Processing Assign To Sales Executive === "  . "| Total Data " . count($data),);

        if ($data->isEmpty()) {
            return;
        }

        $salesExec = MasterKaryawan::where('id_jabatan', 148)
            ->where('is_active', 1)
            ->orderBy('id')
            ->get(['id', 'nama_lengkap']);

        if ($salesExec->isEmpty()) {
            return;
        }

        $totalSales = $salesExec->count();
        $updates = [];
        $histories = [];

        foreach ($data as $i => $pelanggan) {
            $sales = $salesExec[$i % $totalSales];

            $updates[$sales->id][] = $pelanggan->id;

            $histories[] = [
                'id_pelanggan' => $pelanggan->id_pelanggan,
                'id_sales_lama' => $pelanggan->sales_id,
                'id_sales_baru' => $sales->id,
                'tanggal_rotasi' => Carbon::now(),
            ];
        }

        // BULK UPDATE
        foreach ($updates as $salesId => $pelangganIds) {
            MasterPelanggan::whereIn('id', $pelangganIds)->update([
                'sales_id' => $salesId,
                'sales_penanggung_jawab' =>
                $salesExec->firstWhere('id', $salesId)->nama_lengkap
            ]);
        }

        // BULK INSERT HISTORY
        HistoryPerubahanSales::insert($histories);
        Log::channel('reassign_customer')->info("=== Finished Assign To Executive Sales === "  . "| Total Data " . count($data),);
    }



    private static function assignToBankData($data)
    {
        if (!$data instanceof \Illuminate\Support\Collection || $data->isEmpty()) {
            Log::channel('reassign_customer')
                ->info("=== Assign To Bank Data SKIPPED | Data kosong / bukan collection ===");
            return;
        }

        Log::channel('reassign_customer')->info(
            "=== Processing Assign To Bank Data === | Total Data: " . $data->count()
        );

        $chunkNumber = 1;

        $data->chunk(500)->each(function ($chunk) use (&$chunkNumber) {

            Log::channel('reassign_customer')->info(
                ">>> Start Chunk #{$chunkNumber} | Total Chunk Data: " . $chunk->count()
            );

            MasterPelanggan::whereIn('id', $chunk->pluck('id')->toArray())
                ->update([
                    'sales_id' => null,
                    'sales_penanggung_jawab' => null,
                ]);
            Log::channel('reassign_customer')->info(
                ">>> Complete Update Chunk #{$chunkNumber}"
            );

            Log::channel('reassign_customer')->info(
                ">>> Preparing chunk history data"
            );

            $histories = $chunk->map(function ($pelanggan) {
                return [
                    'id_pelanggan'   => $pelanggan->id_pelanggan,
                    'id_sales_lama'  => $pelanggan->sales_id,
                    'id_sales_baru'  => null,
                    'tanggal_rotasi' => Carbon::now(),
                ];
            })->toArray();

            Log::channel('reassign_customer')->info(
                ">>> History data prepared, inserting..."
            );

            HistoryPerubahanSales::insert($histories);

            Log::channel('reassign_customer')->info(
                "<<< Finished Chunk #{$chunkNumber}"
            );

            $chunkNumber++;
        });

        Log::channel('reassign_customer')->info(
            "=== Finished Assign To Bank Data === | Total Data: " . $data->count()
        );
    }


    private static function deteleAllReAssignDataWebphoneAndLog($data)
    {
        if ($data->isEmpty()) {
            return;
        }

        $groupedBySales = $data->groupBy('sales_id');
        // dd(count($groupedBySales));
        foreach ($groupedBySales as $salesId => $items) {
            $numbers = $items
                ->flatMap(
                    fn($item) =>
                    $item->kontak_pelanggan->pluck('no_tlp_perusahaan')
                )
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $salesName = $items->first()->sales_penanggung_jawab;

            $pelangganIds = $items->pluck('id_pelanggan')->filter()->unique()->toArray();

            if (!empty($numbers)) {
                self::deleteWebphoneLogsBulk($salesId, $numbers);
            }

            if (!empty($pelangganIds)) {
                self::deleteDfusHistoryBulk($salesName, $pelangganIds);
            }
        }

        /**
         * ===============================
         * DELETE DFUS HISTORY
         * ===============================
         */
        // $groupedDfus = $data->groupBy('sales_penanggung_jawab');

        // foreach ($groupedDfus as $salesName => $items) {
        //     $pelangganIds = $items
        //         ->flatMap(
        //             fn($item) =>
        //             $item->pluck('id_pelanggan')
        //         )
        //         ->filter()
        //         ->unique()
        //         ->values()
        //         ->toArray();

        //     if (!empty($pelangganIds)) {
        //         self::deleteDfusHistoryBulk($salesName, $pelangganIds);
        //     }

        //     // foreach ($items as $pelangganId => $items) {
        //     //     Log::channel('reassign_customer')->info("=== Processing Delete DFUS for Sales Name " . $salesName . " | Pelanggan ID " . $pelangganId, [
        //     //         'timestamp' => Carbon::now()->toDateTimeString(),
        //     //     ]);
        //     //     self::deleteDfusHistoryBulk($salesName, $pelangganId);
        //     // }
        // }
    }

    private static function deleteWebphoneLogsBulk(int $karyawanId, array $numbers): void
    {

        // Ambil semua ID dulu
        $logIds = LogWebphone::where('karyawan_id', $karyawanId)
            ->whereIn('number', $numbers)
            ->pluck('id')
            ->toArray();

        Log::channel('reassign_customer')->info("=== Processing Delete Webphone Log for Sales ID " . $karyawanId . " | Total Number " . count($numbers) . " | Total Data " . count($logIds),);

        if (empty($logIds)) {
            return;
        }

        // Backup dan delete per chunk - TAPI DENGAN TRANSACTION SENDIRI
        collect($logIds)->chunk(1000)->each(function ($chunk) use ($karyawanId) {
            DB::transaction(function () use ($chunk, $karyawanId) {
                $logs = LogWebphone::whereIn('id', $chunk->toArray())->get();

                if ($logs->isNotEmpty()) {
                    LogWebphoneBackup::insert(
                        $logs->map(fn($log) => self::prepareBackupData($log, ['created_at']))->toArray()
                    );
                    Log::channel('reassign_customer')->info("Success Backup Webphone Log",);
                }
                Log::channel('reassign_customer')->info("=== Start Delete Log Webphone Log",);
                LogWebphone::whereIn('id', $logs->pluck('id')->toArray())->delete();
                Log::channel('reassign_customer')->info("=== Success Delete Log Webphone Log",);
            });
        });
    }

    private static function deleteDfusHistoryBulk(string $karyawanName, array $idPelanggan): void
    {
        // Ambil semua ID dulu
        $ids = collect($idPelanggan);
        $result = collect();

        $ids->chunk(500)->each(function ($chunk) use (&$result, $karyawanName) {
            $data = DFUS::where('sales_penanggung_jawab', $karyawanName)
                ->whereIn('id_pelanggan', $chunk)
                ->pluck('id');

            $result = $result->merge($data);
        });

        // $dfusIds = DFUS::where('sales_penanggung_jawab', $karyawanName)
        //     ->whereIn('id_pelanggan', $idPelanggan)
        //     ->pluck('id')
        //     ->toArray();

        Log::channel('reassign_customer')->info("=== Processing Delete DFUS for Sales ID " . $karyawanName . " | Total Pelanggan ID " . count($idPelanggan) . " | Total Data " . count($result),);

        if (empty($result)) {
            return;
        }

        // Backup dan delete per chunk - TAPI DENGAN TRANSACTION SENDIRI
        collect($result)->chunk(1000)->each(function ($chunk) {
            DB::transaction(function () use ($chunk) {
                $dfus = DFUS::whereIn('id', $chunk->toArray())->get();
                if ($dfus->isNotEmpty()) {
                    DFUSBackup::insert(
                        $dfus->map(fn($log) => self::prepareBackupData($log, ['created_at', 'updated_at']))->toArray()
                    );
                    Log::channel('reassign_customer')->info("Success Backup DFUS Log",);
                }
                Log::channel('reassign_customer')->info("=== Start Delete DFUS Log",);
                DFUS::whereIn('id', $dfus->pluck('id')->toArray())->delete();
                Log::channel('reassign_customer')->info("=== Success Delete DFUS Log",);
            });
        });
    }

    // private static function deleteDfusHistoryBulk(string $karyawanName, string $idPelanggan): void
    // {
    //     Log::channel('reassign_customer')->info("=== Processing Delete Webphone Log for Sales ID " . $karyawanName);

    //     DFUS::where('sales_penanggung_jawab', $karyawanName)
    //         ->where('id_pelanggan', $idPelanggan)
    //         ->chunkById(1000, function ($dfus) {

    //             DFUSBackup::insert(
    //                 $dfus->map(
    //                     fn($log) =>
    //                     self::prepareBackupData($log, ['created_at', 'updated_at'])
    //                 )->toArray()
    //             );

    //             DFUS::whereIn('id', $dfus->pluck('id'))->delete();
    //         });
    // }

    private static function prepareBackupData($model, array $dateFields): array
    {
        $data = $model->toArray();
        unset($data['id']);

        foreach ($dateFields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = Carbon::parse($data[$field])
                    ->format('Y-m-d H:i:s');
            }
        }

        return $data;
    }

    private static function latestQuot($kontrak, $nonKontrak, $id)
    {
        $latestKontrak = null;
        $latestNonKontrak = null;

        if ($kontrak && $kontrak->periode_kontrak_akhir != null) {
            // formatnya contoh: "05-2024"
            $periodeAkhir = Carbon::createFromFormat('m-Y', $kontrak->periode_kontrak_akhir)->endOfMonth();

            // kalau periode akhir sudah lewat bulan sekarang
            if ($periodeAkhir->lt(Carbon::now()->startOfMonth())) {
                $latestKontrak = $kontrak->updated_at ?? $kontrak->created_at;
            }
        }

        if ($nonKontrak) {
            $latestNonKontrak = $nonKontrak->updated_at ?? $nonKontrak->created_at;
        }

        // kalau dua-duanya null, ya null aja
        if (!$latestNonKontrak && !$latestKontrak) {
            return null;
        }

        // kalau salah satu null, ambil yang gak null
        if (!$latestNonKontrak) return $latestKontrak;
        if (!$latestKontrak) return $latestNonKontrak;

        // ambil yang paling baru (tertinggi)
        return Carbon::parse($latestNonKontrak)->gt(Carbon::parse($latestKontrak))
            ? $latestNonKontrak
            : $latestKontrak;
    }
}
