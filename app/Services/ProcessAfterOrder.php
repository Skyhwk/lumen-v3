<?php

namespace App\Services;

use App\Models\GenerateLink;
use App\Models\HistoryKuotaPengujian;
use App\Models\KuotaPengujian;
use App\Models\LinkLhp;
use App\Models\LinkRingkasanOrder;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAfterOrder
{
    private $id_pelanggan;
    private $no_order;
    private $is_kontrak;
    private $use_kuota;
    private $orderHeader;
    private $periodeList;
    private $created_by;

    public function __construct($id_pelanggan, $no_order, $is_kontrak = false, $use_kuota = false, $created_by)
    {
        $this->id_pelanggan = $id_pelanggan;
        $this->no_order = $no_order;
        $this->is_kontrak = $is_kontrak;
        $this->use_kuota = $use_kuota;
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
        $this->saveLinkLhp();
        $this->saveUseKuotaData();
        $this->saveLinkRingkasanOrder();
    }

    private function saveLinkLhp()
    {
        DB::beginTransaction();
        try{
            if($this->is_kontrak){
                foreach ($this->periodeList as $key => $periode) {
                    $orderDetail = OrderDetail::where('no_order', $this->no_order)
                        ->where('periode', $periode)
                        ->whereNotNull('cfr')
                        ->orderBy('id', 'desc')
                        ->get()
                        ->unique('cfr')
                        ->values();

                    $jumlah_lhp = $orderDetail->count();

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

                    $key = $this->no_order;
                    $gen = MD5($key);

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
                        'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'created_by' => $this->created_by
                    ]);

                    $linkLhp->update([
                        'id_token' => $tokenId,
                        'link' => env('PORTAL_LHP', 'https://portal.intilab.com/lhp/') . $token
                    ]);
                }
            }else{
                $orderDetail = OrderDetail::where('no_order', $this->no_order)
                    ->whereNotNull('cfr')
                    ->orderBy('id', 'desc')
                    ->get()
                    ->unique('cfr')
                    ->values();

                $jumlah_lhp = $orderDetail->count();

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

                $key = $this->no_order;
                $gen = MD5($key);

                $gen_tahun = $this->encrypt(DATE('Y-m-d'));

                $token = $this->encrypt($gen . '|' . $gen_tahun);

                $tokenId = GenerateLink::insertGetId([
                    'token' => $token,
                    'key' => $gen,
                    'id_quotation' => $linkLhp->id,
                    'quotation_status' => "lhp_rilis",
                    'type' => 'lhp_rilis',
                    'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->created_by
                ]);

                $linkLhp->update([
                    'id_token' => $tokenId,
                    'link' => env('PORTAL_LHP', 'https://portal.intilab.com/lhp/') . $token
                ]);
            }

            DB::commit();
            Log::info('Save link lhp success');
        }catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
        }
    }

    private function saveUseKuotaData()
    {
        if($this->use_kuota){
            (new UseKuotaService($this->id_pelanggan, $this->no_order))->useKuota();
        }else{
            $kuotaExist = KuotaPengujian::where('pelanggan_ID', $this->id_pelanggan)->first();
            if($kuotaExist){
                $history = HistoryKuotaPengujian::where('id_kuota', $kuotaExist->id)->where('no_order', $this->no_order)->first();
                if($history){
                    $kuotaExist->sisa = $kuotaExist->sisa - $history->total_used;
                    $kuotaExist->save();

                    $history->delete();

                    Log::info('Pelanggan ' . $this->id_pelanggan . ' telah menarik penggunaan kuota yang telah dipakai pada order ' . $this->no_order . '. Sisa kuota saat ini adalah ' . $kuotaExist->sisa);
                }
            }
        }
    }

    private function saveLinkRingkasanOrder()
    {
        DB::beginTransaction();
        try{
            $lingRingkasan = new LinkRingkasanOrder();
            $lingRingkasan->no_quotation = $this->orderHeader->no_document;
            $lingRingkasan->no_order = $this->no_order;
            $lingRingkasan->nama_perusahaan = $this->orderHeader->nama_perusahaan;
            $lingRingkasan->created_by = $this->created_by;
            $lingRingkasan->created_at = Carbon::now();
            $lingRingkasan->updated_by = $this->created_by;
            $lingRingkasan->updated_at = Carbon::now();
            $lingRingkasan->save();

            $key = $this->no_order;
            $gen = MD5($key);
            $gen_tahun = $this->encrypt(DATE('Y-m-d'));
            $token = $this->encrypt($gen . '|' . $gen_tahun);

            $tokenId = GenerateLink::insertGetId([
                'token' => $token,
                'key' => $gen,
                'id_quotation' => $lingRingkasan->id,
                'quotation_status' => "ringkasan_order",
                'type' => 'ringkasan_order',
                'expired' => Carbon::now()->addYear()->format('Y-m-d'),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => $this->created_by
            ]);

            $lingRingkasan->update([
                'id_token' => $tokenId,
                'link' => env('PORTAL_RINGKASAN_ORDER', 'https://portal.intilab.com/ringkasan-order/') . $token
            ]);

            DB::commit();
            Log::info('Save link ringkasan order success');
        }catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
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