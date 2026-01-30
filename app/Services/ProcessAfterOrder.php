<?php

namespace App\Services;

use App\Models\Ftc;
use App\Models\FtcT;
use App\Models\GenerateLink;
use App\Models\HistoryKuotaPengujian;
use App\Models\Jadwal;
use App\Models\KuotaPengujian;
use App\Models\LinkLhp;
use App\Models\LinkRingkasanOrder;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\SamplingPlan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAfterOrder
{
    private $id_pelanggan;
    private $no_order;
    private $is_kontrak;
    private $is_reorder;
    private $is_invoicing;
    private $use_kuota;
    private $orderHeader;
    private $periodeList;
    private $created_by;

    public function __construct($id_pelanggan, $no_order, $is_kontrak = false, $is_reorder = false, $is_invoicing = false, $use_kuota = false, $created_by)
    {
        $this->id_pelanggan = $id_pelanggan;
        $this->no_order = $no_order;
        $this->is_kontrak = $is_kontrak;
        $this->use_kuota = $use_kuota;
        $this->is_reorder = $is_reorder;
        $this->is_invoicing = $is_invoicing;
        $this->orderHeader = OrderHeader::where('no_order', $no_order)->first();
        $this->created_by = $created_by;
        
        if ($this->is_kontrak) {
            $orderDetail = OrderDetail::where('no_order', $no_order)
                ->whereNotNull('periode')
                ->orderBy('id', 'desc')
                ->get()
                ->unique('periode')
                ->values();

            $this->periodeList = $orderDetail->pluck('periode')->all();
        }
    }

    public function run()
    {
        if(!$this->is_invoicing) {
            $this->saveLinkLhp();
            $this->setLinkNonActive();
        }else{
            $this->deleteDataPengujianIfExist();
        }
        $this->saveUseKuotaData();
        $this->saveLinkRingkasanOrder();
    }

    private function deleteDataPengujianIfExist()
    {
        // Ambil list sampel
        $sampleList = OrderDetail::where('no_order', $this->no_order)
            ->pluck('no_sampel');

        foreach ($sampleList as $sample) {

            if (Ftc::where('no_sample', $sample)->exists()) {
                Ftc::where('no_sample', $sample)
                    ->update(['is_active' => 0]);
            }

            if (FtcT::where('no_sample', $sample)->exists()) {
                FtcT::where('no_sample', $sample)
                    ->update(['is_active' => 0]);
            }
        }

        // Order Detail
        if (OrderDetail::where('no_order', $this->no_order)->exists()) {
            OrderDetail::where('no_order', $this->no_order)
                ->update(['is_active' => 0]);
        }

        // Sampling Plan
        if (SamplingPlan::where('no_quotation', $this->orderHeader->no_document)->exists()) {
            SamplingPlan::where('no_quotation', $this->orderHeader->no_document)
                ->update(['is_active' => 0]);
        }

        // Jadwal
        if (Jadwal::where('no_quotation', $this->orderHeader->no_document)->exists()) {
            Jadwal::where('no_quotation', $this->orderHeader->no_document)
                ->update(['is_active' => 0]);
        }
    }


    private function saveLinkLhp()
    {
        DB::beginTransaction();
        try {
            Log::info('Processing LHP Link', [
                'no_order' => $this->no_order,
                'is_kontrak' => $this->is_kontrak,
                'is_reorder' => $this->is_reorder,
                'periode_list' => $this->periodeList ?? []
            ]);

            // KONDISI 1: KONTRAK & REORDER
            if ($this->is_kontrak && $this->is_reorder) {
                $this->processKontrakReorder();
            }
            // KONDISI 2: KONTRAK & BUKAN REORDER
            elseif ($this->is_kontrak && !$this->is_reorder) {
                $this->processKontrakNonReorder();
            }
            // KONDISI 3: NON KONTRAK & REORDER
            elseif (!$this->is_kontrak && $this->is_reorder) {
                $this->processNonKontrakReorder();
            }
            // KONDISI 4: NON KONTRAK & BUKAN REORDER
            else {
                $this->processNonKontrakNonReorder();
            }

            DB::commit();
            Log::info('Save link LHP success', ['no_order' => $this->no_order]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save LHP link', [
                'no_order' => $this->no_order,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw exception agar error bisa ditangkap di level atas
        }
    }

    /**
     * KONDISI 1: KONTRAK & REORDER
     * - Cek penambahan/pengurangan periode
     * - Penambahan: buat link baru
     * - Pengurangan: update expired jadi now
     * - Periode tetap: update expired sama seperti addedPeriode
     */
    private function processKontrakReorder(): void
    {
        Log::info('Processing KONTRAK REORDER', ['periode_list' => $this->periodeList]);

        $existingLinks = LinkLhp::where('no_order', $this->no_order)->get();
        $existingPeriods = $existingLinks->pluck('periode')->toArray();

        // Konversi ke Collection untuk operasi diff
        $currentPeriods = collect($this->periodeList);
        $existingPeriodsCollection = collect($existingPeriods);

        // Identifikasi periode yang berbeda
        $addedPeriods = $currentPeriods->diff($existingPeriodsCollection)->all();
        $removedPeriods = $existingPeriodsCollection->diff($currentPeriods)->all();
        $unchangedPeriods = $currentPeriods->intersect($existingPeriodsCollection)->all();

        Log::info('Period comparison', [
            'added' => $addedPeriods,
            'removed' => $removedPeriods,
            'unchanged' => $unchangedPeriods
        ]);

        // 1. PROSES PERIODE BARU (ADDED)
        foreach ($addedPeriods as $periode) {
            $this->createLinkLhpForPeriode($periode);
        }

        // 2. PROSES PERIODE YANG DIHAPUS (REMOVED)
        foreach ($removedPeriods as $periode) {
            $linkLhp = LinkLhp::where('no_order', $this->no_order)
                ->where('periode', $periode)
                ->first();
            
            if ($linkLhp) {
                // Update expired jadi sekarang
                GenerateLink::where('id_quotation', $linkLhp->id)
                    ->where('type', 'lhp_rilis')
                    ->where('quotation_status', 'lhp_rilis')
                    ->update(['status' => 1]);
                
                Log::info('Expired removed period', ['periode' => $periode]);
            }
        }

        // 3. PROSES PERIODE YANG TETAP (UNCHANGED)
        // Update expired periode yang tetap agar sama seperti periode baru (tambah 1 tahun dari sekarang)
        $newExpiredDate = Carbon::now()->addYear()->format('Y-m-d');
        foreach ($unchangedPeriods as $periode) {
            // Hitung jumlah LHP untuk periode ini
            $orderDetail = OrderDetail::where('no_order', $this->no_order)
                ->where('periode', $periode)
                ->whereNotNull('cfr')
                ->orderBy('id', 'desc')
                ->get()
                ->unique('cfr')
                ->values();

            $jumlah_lhp = $orderDetail->count();

            // Update link LHP
            $linkLhp = LinkLhp::where('no_order', $this->no_order)
                ->where('periode', $periode)
                ->first();
            
            if ($linkLhp) {
                // Update basic info
                $linkLhp->update([
                    'jumlah_lhp' => $jumlah_lhp,
                    'no_quotation' => $this->orderHeader->no_document,
                    'updated_by' => $this->created_by,
                    'updated_at' => Carbon::now()
                ]);
                
                // Update expired
                GenerateLink::where('id_quotation', $linkLhp->id)
                    ->where('type', 'lhp_rilis')
                    ->where('quotation_status', 'lhp_rilis')
                    ->update(['expired' => $newExpiredDate]);
                
                Log::info('Updated expired for unchanged period', [
                    'periode' => $periode,
                    'new_expired' => $newExpiredDate
                ]);
            }
        }
    }

    /**
     * KONDISI 2: KONTRAK & BUKAN REORDER
     * - Hanya perlu membuat link LHP sesuai dengan periodeList
     */
    private function processKontrakNonReorder(): void
    {
        Log::info('Processing KONTRAK NON-REORDER', ['periode_list' => $this->periodeList]);

        foreach ($this->periodeList as $periode) {
            // Cek dulu apakah sudah ada link untuk periode ini (untuk antisipasi duplicate)
            $existingLink = LinkLhp::where('no_order', $this->no_order)
                ->where('periode', $periode)
                ->first();
            
            if (!$existingLink) {
                $this->createLinkLhpForPeriode($periode);
            } else {
                Log::warning('Link already exists for period', [
                    'no_order' => $this->no_order,
                    'periode' => $periode
                ]);
            }
        }
    }

    /**
     * KONDISI 3: NON KONTRAK & REORDER
     * - Hanya cukup update expirednya saja (tambah 1 tahun dari sekarang)
     */
    private function processNonKontrakReorder(): void
    {
        Log::info('Processing NON-KONTRAK REORDER');

        // Hitung jumlah LHP
        $orderDetail = OrderDetail::where('no_order', $this->no_order)
            ->whereNotNull('cfr')
            ->orderBy('id', 'desc')
            ->get()
            ->unique('cfr')
            ->values();

        $jumlah_lhp = $orderDetail->count();

        $linkLhp = LinkLhp::where('no_order', $this->no_order)->first();
        
        if ($linkLhp) {
            // Update basic info
            $linkLhp->update([
                'jumlah_lhp' => $jumlah_lhp,
                'no_quotation' => $this->orderHeader->no_document,
                'updated_by' => $this->created_by,
                'updated_at' => Carbon::now()
            ]);

            // Update expired jadi 1 tahun dari sekarang
            $newExpiredDate = Carbon::now()->addYear()->format('Y-m-d');
            GenerateLink::where('id_quotation', $linkLhp->id)
                ->where('type', 'lhp_rilis')
                ->where('quotation_status', 'lhp_rilis')
                ->update(['expired' => $newExpiredDate]);
            
            Log::info('Updated non-kontrak reorder link', [
                'link_id' => $linkLhp->id,
                'new_expired' => $newExpiredDate
            ]);
        } else {
            // Buat link LHP baru
            $linkLhp = new LinkLhp();
            $linkLhp->no_quotation = $this->orderHeader->no_document;
            $linkLhp->no_order = $this->no_order;
            $linkLhp->nama_perusahaan = $this->orderHeader->nama_perusahaan;
            $linkLhp->jumlah_lhp = $jumlah_lhp;
            $linkLhp->created_by = $this->created_by;
            $linkLhp->created_at = Carbon::now();
            $linkLhp->updated_by = $this->created_by;
            $linkLhp->updated_at = Carbon::now();
            $linkLhp->save();

            // Generate token dan link
            $key = $this->no_order;
            $gen = md5($key);
            $gen_tahun = $this->encrypt(Carbon::now()->format('Y-m-d'));
            $token = $this->encrypt($gen . '|' . $gen_tahun);

            $tokenId = GenerateLink::insertGetId([
                'token' => $token,
                'key' => $gen,
                'id_quotation' => $linkLhp->id,
                'quotation_status' => "lhp_rilis",
                'type' => 'lhp_rilis',
                'expired' => Carbon::now()->addYear(),
                'created_at' => Carbon::now(),
                'created_by' => $this->created_by
            ]);

            $linkLhp->update([
                'id_token' => $tokenId,
                'link' => env('PORTAL_LHP', 'https://portal.intilab.com/lhp/') . $token
            ]);

            Log::info('Created non-kontrak reorder link', [
                'link_id' => $linkLhp->id,
                'token_id' => $tokenId,
                'token' => $token,
                'link' => $linkLhp->link
            ]);
        }
    }

    /**
     * KONDISI 4: NON KONTRAK & BUKAN REORDER
     * - Hanya perlu create link LHP baru
     */
    private function processNonKontrakNonReorder(): void
    {
        Log::info('Processing NON-KONTRAK NON-REORDER');

        // Cek apakah sudah ada link (untuk antisipasi duplicate)
        $existingLink = LinkLhp::where('no_order', $this->no_order)->first();
        
        if ($existingLink) {
            Log::warning('LHP link already exists for non-kontrak order', [
                'no_order' => $this->no_order,
                'link_id' => $existingLink->id
            ]);
            return;
        }

        // Hitung jumlah LHP
        $orderDetail = OrderDetail::where('no_order', $this->no_order)
            ->whereNotNull('cfr')
            ->orderBy('id', 'desc')
            ->get()
            ->unique('cfr')
            ->values();

        $jumlah_lhp = $orderDetail->count();

        // Buat link LHP baru
        $linkLhp = new LinkLhp();
        $linkLhp->no_quotation = $this->orderHeader->no_document;
        $linkLhp->no_order = $this->no_order;
        $linkLhp->nama_perusahaan = $this->orderHeader->nama_perusahaan;
        $linkLhp->jumlah_lhp = $jumlah_lhp;
        $linkLhp->created_by = $this->created_by;
        $linkLhp->created_at = Carbon::now();
        $linkLhp->updated_by = $this->created_by;
        $linkLhp->updated_at = Carbon::now();
        $linkLhp->save();

        // Generate token dan link
        $key = $this->no_order;
        $gen = md5($key);
        $gen_tahun = $this->encrypt(Carbon::now()->format('Y-m-d'));
        $token = $this->encrypt($gen . '|' . $gen_tahun);

        $tokenId = GenerateLink::insertGetId([
            'token' => $token,
            'key' => $gen,
            'id_quotation' => $linkLhp->id,
            'quotation_status' => "lhp_rilis",
            'type' => 'lhp_rilis',
            'expired' => Carbon::now()->addYear(),
            'created_at' => Carbon::now(),
            'created_by' => $this->created_by
        ]);

        $linkLhp->update([
            'id_token' => $tokenId,
            'link' => env('PORTAL_LHP', 'https://portal.intilab.com/lhp/') . $token
        ]);

        Log::info('Created non-kontrak non-reorder link', [
            'link_id' => $linkLhp->id,
            'token_id' => $tokenId
        ]);
    }

    /**
     * Helper method untuk membuat link LHP untuk periode tertentu
     */
    private function createLinkLhpForPeriode(string $periode): void
    {
        Log::info('Creating LHP link for period', ['periode' => $periode]);

        // Hitung jumlah LHP untuk periode ini
        $orderDetail = OrderDetail::where('no_order', $this->no_order)
            ->where('periode', $periode)
            ->whereNotNull('cfr')
            ->orderBy('id', 'desc')
            ->get()
            ->unique('cfr')
            ->values();

        $jumlah_lhp = $orderDetail->count();

        // Buat link LHP
        $linkLhp = new LinkLhp();
        $linkLhp->no_quotation = $this->orderHeader->no_document;
        $linkLhp->periode = $periode;
        $linkLhp->no_order = $this->no_order;
        $linkLhp->nama_perusahaan = $this->orderHeader->nama_perusahaan;
        $linkLhp->jumlah_lhp = $jumlah_lhp;
        $linkLhp->created_by = $this->created_by;
        $linkLhp->created_at = Carbon::now();
        $linkLhp->updated_by = $this->created_by;
        $linkLhp->updated_at = Carbon::now();
        $linkLhp->save();

        // Generate token dan link
        $key = $this->no_order;
        $gen = md5($key);
        
        // Parse periode untuk tanggal
        $tahun = explode('-', $periode)[0];
        $bulan = explode('-', $periode)[1];
        $day = date('d');

        $gen_tahun = $this->encrypt(
            Carbon::create($tahun, $bulan, $day)->format('Y-m-d')
        );

        $token = $this->encrypt($gen . '|' . $gen_tahun);

        $tokenId = GenerateLink::insertGetId([
            'token' => $token,
            'key' => $gen,
            'id_quotation' => $linkLhp->id,
            'quotation_status' => "lhp_rilis",
            'type' => 'lhp_rilis',
            'expired' => Carbon::now()->addYear(),
            'created_at' => Carbon::now(),
            'created_by' => $this->created_by
        ]);

        $linkLhp->update([
            'id_token' => $tokenId,
            'link' => env('PORTAL_LHP', 'https://portal.intilab.com/lhp/') . $token
        ]);

        Log::info('Created LHP link for period', [
            'periode' => $periode,
            'link_id' => $linkLhp->id,
            'token_id' => $tokenId
        ]);
    }

    private function saveUseKuotaData()
    {
        try {
            if ($this->use_kuota) {
                (new UseKuotaService($this->id_pelanggan, $this->no_order))->useKuota();
            } else {
                $kuotaExist = KuotaPengujian::where('pelanggan_ID', $this->id_pelanggan)->first();
                if ($kuotaExist) {
                    $history = HistoryKuotaPengujian::where('id_kuota', $kuotaExist->id)
                        ->where('no_order', $this->no_order)
                        ->first();
                    
                    if ($history) {
                        $kuotaExist->sisa = $kuotaExist->sisa - $history->total_used;
                        $kuotaExist->save();
                        $history->delete();

                        Log::info('Kuota usage reverted', [
                            'pelanggan_id' => $this->id_pelanggan,
                            'no_order' => $this->no_order,
                            'sisa_kuota' => $kuotaExist->sisa
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to process kuota data', [
                'error' => $e->getMessage(),
                'pelanggan_id' => $this->id_pelanggan,
                'no_order' => $this->no_order
            ]);
            throw $e;
        }
    }

    private function saveLinkRingkasanOrder()
    {
        DB::beginTransaction();
        try {
            Log::info('Processing ringkasan order link', ['no_order' => $this->no_order]);

            // Cek apakah sudah ada
            $existingLink = LinkRingkasanOrder::where('no_order', $this->no_order)->first();
            
            if ($existingLink) {
                // Update existing
                $existingLink->update([
                    'no_quotation' => $this->orderHeader->no_document,
                    'updated_by' => $this->created_by,
                    'updated_at' => Carbon::now()
                ]);

                // Update expired token
                GenerateLink::where('id_quotation', $existingLink->id)
                    ->where('type', 'ringkasan_order')
                    ->where('quotation_status', 'ringkasan_order')
                    ->update(['expired' => Carbon::now()->addYear()]);

                Log::info('Updated existing ringkasan order link', [
                    'link_id' => $existingLink->id
                ]);
            } else {
                // Create new
                $linkRingkasan = new LinkRingkasanOrder();
                $linkRingkasan->no_quotation = $this->orderHeader->no_document;
                $linkRingkasan->no_order = $this->no_order;
                $linkRingkasan->nama_perusahaan = $this->orderHeader->nama_perusahaan;
                $linkRingkasan->created_by = $this->created_by;
                $linkRingkasan->created_at = Carbon::now();
                $linkRingkasan->updated_by = $this->created_by;
                $linkRingkasan->updated_at = Carbon::now();
                $linkRingkasan->save();

                // Generate token dan link
                $key = $this->no_order;
                $gen = md5($key);
                $gen_tahun = $this->encrypt(Carbon::now()->format('Y-m-d'));
                $token = $this->encrypt($gen . '|' . $gen_tahun);

                $tokenId = GenerateLink::insertGetId([
                    'token' => $token,
                    'key' => $gen,
                    'id_quotation' => $linkRingkasan->id,
                    'quotation_status' => "ringkasan_order",
                    'type' => 'ringkasan_order',
                    'expired' => Carbon::now()->addYear(),
                    'created_at' => Carbon::now(),
                    'created_by' => $this->created_by
                ]);

                $linkRingkasan->update([
                    'id_token' => $tokenId,
                    'link' => env('PORTAL_RINGKASAN_ORDER', 'https://portal.intilab.com/ringkasan-order/') . $token
                ]);

                Log::info('Created new ringkasan order link', [
                    'link_id' => $linkRingkasan->id,
                    'token_id' => $tokenId
                ]);
            }

            DB::commit();
            Log::info('Save link ringkasan order success');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save ringkasan order link', [
                'no_order' => $this->no_order,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function setLinkNonActive()
    {
        $dateYesterday = Carbon::now()->subDay()->format('Y-m-d');

        // Link LHP
        $linkIds = LinkLhp::where('no_order', $this->no_order)
            ->pluck('id')
            ->toArray();

        if(count($linkIds) > 0) {
            GenerateLink::whereIn('id_quotation', $linkIds)
                ->where('type', 'lhp_rilis')
                ->where('quotation_status', 'lhp_rilis')
                ->update([
                    'expired' => $dateYesterday
                ]);
        }
    }

    private function encrypt($data)
    {
        $ENCRYPTION_KEY = 'intilab_jaya';
        $ENCRYPTION_ALGORITHM = 'AES-256-CBC';
        $EncryptionKey = base64_decode($ENCRYPTION_KEY);
        $InitializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length($ENCRYPTION_ALGORITHM));
        $EncryptedText = openssl_encrypt($data, $ENCRYPTION_ALGORITHM, $EncryptionKey, 0, $InitializationVector);
        $return = base64_encode($EncryptedText . '::' . $InitializationVector);
        return $return;
    }
}