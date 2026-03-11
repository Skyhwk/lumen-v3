<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterPelanggan;
use App\Models\KontakPelanggan;
use App\Models\AlamatPelanggan;
use App\Models\PicPelanggan;
use App\Models\MasterKaryawan;
use App\Models\HargaTransportasi;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\OrderHeader;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\AlamatPelangganBlacklist;
use App\Models\KontakPelangganBlacklist;
use App\Models\LogWebphone;
use App\Models\LogWebphoneBackup;
use App\Models\MasterPelangganBlacklist;
use App\Models\PelangganBlacklist;
use App\Models\PicPelangganBlacklist;
use Yajra\Datatables\Datatables;

use App\Services\GetBawahan;
use Carbon\Carbon;

Carbon::setLocale('id');

class BankDataCustomerController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterPelanggan::with([
            'kontak_pelanggan',
            'alamat_pelanggan',
            'pic_pelanggan',
            'order_customer'
        ])
            ->where('is_active', true)
            ->whereNull('sales_id')
            ->whereNull('sales_penanggung_jawab');

        return Datatables::of($data)
            ->filterColumn('order_customer', function ($query, $keyword) {
                if (str_contains('ordered', strtolower($keyword))) {
                    $query->whereHas('order_customer');
                } else {
                    $query->whereDoesntHave('order_customer');
                }
            })
            ->filterColumn('telpon', function ($query, $keyword) {
                $query->whereHas('kontak_pelanggan', function ($q) use ($keyword) {
                    $q->where('no_tlp_perusahaan', 'like', "%{$keyword}%");
                });
            })
            ->orderColumn('telpon', function ($query, $orderDirection) {
                $query->with(['kontak_pelanggan' => function ($q) use ($orderDirection) {
                    $q->orderBy('no_tlp_perusahaan', $orderDirection);
                }]);
            })
            ->make(true);
    }

    public function getSales(Request $request)
    {
        $keyword = $request->get('term'); // dari select2

        $data = MasterKaryawan::where('is_active', true)->whereIn('id_jabatan', [24, 148])
            ->when($keyword, function ($q) use ($keyword) {
                $q->where('nama_lengkap', 'like', "%{$keyword}%");
            })
            ->get()
            ->map(function ($item) {
                return [
                    'id'   => $item->id,
                    'text' => $item->nama_lengkap
                ];
            });

        return response()->json([
            'results' => $data
        ]);
    }

    public function shareSales(Request $request)
    {
        DB::beginTransaction();
        try {
            // Validasi manual
            $salesId = $request->input('sales_id');
            $salesName = $request->input('sales_name');
            $customerIds = $request->input('customer_ids');

            // Cek validasi sederhana
            if (empty($salesId) || empty($salesName) || empty($customerIds) || !is_array($customerIds)) {
                return response()->json([
                    'message' => 'Validasi gagal. Pastikan semua data terisi dengan benar.'
                ], 422);
            }

            $successCount = count($customerIds);

            MasterPelanggan::whereIn('id', $customerIds)->update([
                'sales_id' => $salesId,
                'sales_penanggung_jawab' => $salesName
            ]);

            $data = MasterPelanggan::with('kontak_pelanggan')
                ->where('id', $customerIds)
                ->where('is_active', true)
                ->get(); // ⬅️ WAJIB

            $numbers = $data
                ->flatMap(function ($pelanggan) {
                    if ($pelanggan->kontak_pelanggan) {
                        return $pelanggan->kontak_pelanggan->pluck('no_tlp_perusahaan');
                    }

                    return [];
                })
                ->filter()
                ->values()
                ->toArray();

            $logIds = LogWebphone::whereIn('number', $numbers)
                ->where('created_at', '>=', Carbon::now()->subMonths(2))
                ->pluck('id')
                ->toArray();

            collect($logIds)->chunk(1000)->each(function ($chunk) {
                DB::transaction(function () use ($chunk) {
                    $logs = LogWebphone::whereIn('id', $chunk->toArray())->get();

                    if ($logs->isNotEmpty()) {
                        LogWebphoneBackup::insert(
                            $logs->map(fn($log) => self::prepareBackupData($log, ['created_at']))->toArray()
                        );

                        LogWebphone::whereIn('id', $chunk->toArray())->delete();
                    }
                });
            });

            // foreach ($customerIds as $customerId) {
            //     $pelanggan = MasterPelanggan::find($customerId);

            //     if ($pelanggan && $pelanggan->is_active) {
            //         $pelanggan->sales_id = $salesId;
            //         $pelanggan->sales_penanggung_jawab = $salesName;
            //         $pelanggan->save();

            //         $successCount++;
            //     } else {
            //         $failedCount++;
            //     }
            // }

            DB::commit();
            return response()->json([
                'message' => "Berhasil membagikan {$successCount} data pelanggan ke {$salesName}",
                'success' => true,
                'data' => [
                    'total_success' => $successCount,
                    'sales_id' => $salesId,
                    'sales_name' => $salesName
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

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
}
