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
        $orderedCustomers = DB::table('order_header')
            ->select('id_pelanggan as ordered_id_pelanggan')
            ->where('is_active', 1)
            ->distinct();

        $data = MasterPelanggan::query()
            ->withOnly(['kontak_pelanggan:id,pelanggan_id,no_tlp_perusahaan'])
            ->select([
                'master_pelanggan.id',
                'master_pelanggan.id_pelanggan',
                'master_pelanggan.npwp',
                'master_pelanggan.nama_pelanggan',
                'master_pelanggan.wilayah',
                'master_pelanggan.sales_penanggung_jawab',
                'master_pelanggan.created_by',
                'master_pelanggan.created_at',
            ])
            ->selectRaw('(ordered_customers.ordered_id_pelanggan IS NOT NULL) as has_order')
            ->leftJoinSub($orderedCustomers, 'ordered_customers', function ($join) {
                $join->on('ordered_customers.ordered_id_pelanggan', '=', 'master_pelanggan.id_pelanggan');
            })
            ->where('master_pelanggan.is_active', true)
            ->whereNull('master_pelanggan.sales_id')
            ->whereNull('master_pelanggan.sales_penanggung_jawab');

        return Datatables::of($data)
            ->filterColumn('has_order', function ($query, $keyword) {
                $kw = strtolower(trim($keyword));
                $wantsOrdered = str_contains($kw, 'order') || $kw === '1';
                if ($wantsOrdered) {
                    $query->whereNotNull('ordered_customers.ordered_id_pelanggan');
                } else {
                    $query->whereNull('ordered_customers.ordered_id_pelanggan');
                }
            })
            ->orderColumn('has_order', 'has_order $1')
            ->filterColumn('telpon', function ($query, $keyword) {
                $query->whereHas('kontak_pelanggan', function ($q) use ($keyword) {
                    $q->where('no_tlp_perusahaan', 'like', "%{$keyword}%");
                });
            })
            ->orderColumn('telpon', function ($query, $orderDirection) {
                $dir = strtolower($orderDirection) === 'desc' ? 'desc' : 'asc';
                $query->orderByRaw(
                    '(select min(kontak_pelanggan.no_tlp_perusahaan) from kontak_pelanggan where kontak_pelanggan.pelanggan_id = master_pelanggan.id and kontak_pelanggan.is_active = 1) ' . $dir
                );
            })
            ->skipTotalRecords()
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
