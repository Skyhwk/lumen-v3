<?php 

namespace App\Services;

use App\Models\HargaParameter;
use App\Models\KuotaPengujian;
use App\Models\HistoryKuotaPengujian;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakD;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UseKuotaService {
    protected $pelangganID;
    protected $noOrder;

    /**
     * Required parameters
     * 
     * @param string $pelangganID
     * @param array $noOrder
     */
    public function __construct( string $pelangganID, string $noOrder)
    {
        $this->pelangganID = $pelangganID;
        $this->noOrder = $noOrder;
    }

    public function useKuota()
    {
        DB::beginTransaction();
        try {
            $kuota = KuotaPengujian::where('pelanggan_ID', $this->pelangganID)
                ->where('is_active', true)
                ->first();
            if(!$kuota || $kuota->sisa == 0) {
                Log::info('Pelanggan ' . $this->pelangganID . ' tidak memiliki kuota pengujian');
                return ;
            }
            if($kuota->tanggal_awal < Carbon::now()->format('Y-m-d') || $kuota->tanggal_akhir > Carbon::now()->format('Y-m-d')) {
                if($kuota->using_template == 0) {
                    $totalUsed = $this->useKuotaByParameter($kuota);
                }else {
                    $totalUsed = $this->useKuotaByTemplate($kuota);
                }
    
                $kuota->sisa    = $totalUsed;
                $kuota->is_used = true;
                $kuota->save();
            }else{
                Log::info('Pelanggan ' . $this->pelangganID . ' belum dapat menggunakan kuota pengujian dikarenakan tanggal hari ini tidak termasuk dari masa berlaku ' . $kuota->tanggal_awal . ' sampai ' . $kuota->tanggal_akhir);
                return;
            }

            DB::commit();
            Log::info('Pelanggan ' . $this->pelangganID . ' telah menggunakan kuota pengujian pada order ' . $this->noOrder . '. Sisa kuota saat ini adalah ' . $totalUsed);
        }catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
        }
    }

    private function useKuotaByParameter($kuota)
    {
        $parameterFormatted = $kuota->id_parameter . ';' . $kuota->parameter;

        $currentUsed = OrderDetail::where('no_order', $this->noOrder)
            ->where('is_active', true)
            ->whereJsonContains('parameter', $parameterFormatted)
            ->count();

        $orderHeader = OrderHeader::where('no_order', $this->noOrder)->first();
        if (!$orderHeader) {
            return $kuota->sisa;
        }

        DB::beginTransaction();
        try{
            $history = HistoryKuotaPengujian::where('id_kuota', $kuota->id)
                ->where('no_order', $this->noOrder)
                ->first();

            if ($history) {
                // ================= REVISI =================
                $delta = $currentUsed - $history->total_used;

                if ($delta > 0) {
                    // Ambil kuota tambahan (cap ke sisa)
                    $delta = min($delta, $kuota->sisa);
                }
                // delta < 0 → otomatis pengembalian kuota

                $history->total_used += $delta;
                $history->save();
            } else {
                // ================= ORDER BARU =================
                $used = min($currentUsed, $kuota->sisa);

                if($used > 0) {
                    HistoryKuotaPengujian::create([
                        'id_kuota'     => $kuota->id,
                        'no_order'     => $this->noOrder,
                        'no_document'  => $orderHeader->no_document,
                        'total_used'   => $used,
                    ]);
                }
            }
            DB::commit();
        }catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        // ================= HITUNG SISA KUOTA =================
        $totalUsedAllOrder = HistoryKuotaPengujian::where('id_kuota', $kuota->id)
            ->sum('total_used');

        // Jaga agar tidak melebihi kuota awal
        return max(
            0,
            min($kuota->jumlah_kuota, $kuota->jumlah_kuota - $totalUsedAllOrder)
        );
    }

    private function useKuotaByTemplate($kuota) {
        $templateData = json_decode($kuota->template_data);

        $currentUsed = OrderDetail::where('no_order', $this->noOrder)
            ->where('is_active', true)
            ->where('kategori_2', $kuota->kategori->nama_kategori)
            ->where('kategori_3', $templateData->id_sub_kategori)
            ->whereJsonContains('regulasi', $templateData->regulasi)
            ->whereJsonContains('parameter', $templateData->parameter)
            ->count();

        $orderHeader = OrderHeader::where('no_order', $this->noOrder)->first();
        if (!$orderHeader) {
            return $kuota->sisa;
        }

        DB::beginTransaction();
        try{
            $history = HistoryKuotaPengujian::where('id_kuota', $kuota->id)
                ->where('no_order', $this->noOrder)
                ->first();

            if ($history) {
                // ================= REVISI =================
                $delta = $currentUsed - $history->total_used;

                if ($delta > 0) {
                    // Ambil kuota tambahan (cap ke sisa)
                    $delta = min($delta, $kuota->sisa);
                }
                // delta < 0 → otomatis pengembalian kuota

                $history->total_used += $delta;
                $history->save();
            } else {
                // ================= ORDER BARU =================
                $used = min($currentUsed, $kuota->sisa);

                if($used > 0) {
                    HistoryKuotaPengujian::create([
                        'id_kuota'     => $kuota->id,
                        'no_order'     => $this->noOrder,
                        'no_document'  => $orderHeader->no_document,
                        'total_used'   => $used,
                    ]);
                }
            }
            DB::commit();
        }catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        // ================= HITUNG SISA KUOTA =================
        $totalUsedAllOrder = HistoryKuotaPengujian::where('id_kuota', $kuota->id)
            ->sum('total_used');

        // Jaga agar tidak melebihi kuota awal
        return max(
            0,
            min($kuota->jumlah_kuota, $kuota->jumlah_kuota - $totalUsedAllOrder)
        );
    }
}