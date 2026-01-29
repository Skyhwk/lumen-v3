<?php

namespace App\Http\Controllers\external;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use App\Helpers\HelperSatuan;
use App\Services\GroupedCfrByLhp;

use App\Models\{
    GenerateLink,
    HoldHp,
    OrderHeader,
    Invoice,
    LinkLhp,
    MasterBakumutu,
    MdlEmisi,
    MdlUdara
};

class LHPHandleController extends BaseController
{
    // =========================================================================
    // CONSTANTS: Parameter ID
    // =========================================================================
    const PARAM_GETARAN = '242';
    const PARAM_ERGONOMI = '230';
    const PARAM_PSIKOLOGI = '318';
    const PARAM_OPASITAS_SOLAR = '376';
    const PARAM_OPASITAS_ESB = '2275';
    const PARAM_CO_BENSIN = '392';
    const PARAM_CO_GAS = '1201';
    const PARAM_HC_BENSIN = '393';
    const PARAM_HC_GAS = '1202';

    // =========================================================================
    // CONFIGURATIONS
    // =========================================================================

    // Config pencarian di data Lab (Worksheet Value)
    const WS_CONFIG = [
        'ws_value_air' => ['headers' => ['gravimetri', 'titrimetri', 'colorimetri', 'subkontrak'], 'type' => 'air'],
        'ws_value_udara' => ['headers' => ['lingkungan', 'microbiologi', 'medanLm', 'sinaruv', 'iklim', 'getaran', 'kebisingan', 'direct_lain', 'partikulat', 'pencahayaan', 'swab', 'subkontrak', 'dustfall', 'debuPersonal'], 'type' => 'udara'],
        'ws_value_emisi_cerobong' => ['headers' => ['emisi_cerobong_header', 'emisi_isokinetik', 'subkontrak'], 'type' => 'emisi'],
    ];

    // Config pencarian di data Lapangan
    const LAPANGAN_CONFIG = ['data_lapangan_ergonomi', 'data_lapangan_psikologi', 'data_lapangan_emisi_kendaraan',];

    // Mapping Parameter ID ke nama field di tabel lapangan
    const FIELD_MAP = [
        self::PARAM_OPASITAS_SOLAR => 'opasitas',
        self::PARAM_OPASITAS_ESB => 'opasitas',
        self::PARAM_CO_BENSIN => 'co',
        self::PARAM_CO_GAS => 'co',
        self::PARAM_HC_BENSIN => 'hc',
        self::PARAM_HC_GAS => 'hc',
    ];

    private $satuanHelper;

    public function __construct()
    {
        $this->satuanHelper = new HelperSatuan;
    }

    public function newCheckLhp(Request $request)
    {
        // 1. Sanitasi Token: Ganti spasi dengan plus (+) handle URL decoding issue.
        $token = str_replace(' ', '+', $request->token);
        if (!$token) return response()->json(['message' => 'Token is required'], 430);

        // 2. Rangkaian pengecekan data ke Database
        $generateLink = GenerateLink::where('token', $token)->first();
        if (!$generateLink) return response()->json(['message' => 'Token not found'], 401);

        $linkLhp = LinkLhp::where('id_token', $generateLink->id)->first();
        if (!$linkLhp) return response()->json(['message' => 'Link LHP not found'], 404);

        $orderHeader = OrderHeader::where('no_order', $linkLhp->no_order)->where('is_active', true)->first();
        if (!$orderHeader) return response()->json(['message' => 'Order Header not found'], 404);

        // 3. Ambil Invoice dengan Eager Loading (recordPembayaran, recordWithdraw) biar hemat query
        $invoices = Invoice::with(['recordPembayaran', 'recordWithdraw'])
            ->where('no_order', $linkLhp->no_order)
            ->where('is_active', true)
            ->get();

        // 4. Filter Invoice: Jika tidak ada invoice 'all', filter sesuai periode LHP
        if ($linkLhp->periode && !$invoices->contains('periode', 'all')) {
            $invoices = $invoices->where('periode', $linkLhp->periode)->values();
        }

        // 5. Transformasi Data (Core Logic)
        $dataGrouped = collect((new GroupedCfrByLhp($orderHeader, $linkLhp->periode))->get()->toArray())
            ->map(function ($item) {
                // Build rekap pengujian
                $item['rekap_pengujian'] = $this->getRekapPengujian($item['order_details']);

                // Hapus data mentah order_details agar response payload lebih ringan
                unset($item['order_details']);

                return $item;
            });

        return response()->json([
            'message' => 'LHP data retrieved successfully',
            'data' => $dataGrouped,
            'order' => $orderHeader,
            'periode' => $linkLhp->periode,
            'invoice' => $invoices,
            'fileName' => $linkLhp->filename,
            'hold' => HoldHp::where('no_order', $linkLhp->no_order)->where('periode', $linkLhp->periode)->first()
        ], 200);
    }

    /**
     * Mencari hasil uji dari berbagai sumber (Lab & Lapangan).
     */
    private function getRekapPengujian($orderDetails)
    {
        return collect($orderDetails)->flatMap(function ($od) {
            $parameters = collect(json_decode($od['parameter'], true));

            return $parameters->flatMap(function ($paramString) use ($od) {
                [$paramId, $paramName] = explode(';', $paramString);

                // --- PRIORITY 1: Cari di Data Lab (Air, Udara, Emisi) ---
                // Loop konfigurasi WS_CONFIG untuk mencari hasil
                $result = collect(self::WS_CONFIG)->map(function ($config, $key) use ($od, $paramId, $paramName) {
                    $values = collect($od[$key] ?? []);
                    if ($values->isEmpty()) return null;

                    // Cari value yang parameternya cocok DAN sudah di-approve
                    $matchedValue = $values->first(function ($val) use ($config, $paramName) {
                        return collect($config['headers'])->contains(function ($header) use ($val, $paramName) {
                            $dataHeader = $val[$header] ?? [];

                            return isset($dataHeader['parameter'])
                                && $dataHeader['parameter'] == $paramName
                                && (isset($dataHeader['is_approved']) || isset($dataHeader['is_approve']));
                        });
                    });

                    if (!$matchedValue) return null;

                    // Formatting Hasil berdasarkan tipe parameter (Air/Udara/Emisi)
                    if ($config['type'] == 'air') {
                        return ['no_sampel' => $od['no_sampel'], 'parameter' => $paramName, 'hasil_uji' => $matchedValue['hasil']];
                    }
                    if ($config['type'] == 'udara') {
                        $satuan = $matchedValue['satuan'] ?? $this->getSatuanFromRegulasi($od, $paramId);
                        $index = $satuan ? ($this->satuanHelper->udara($satuan) ?: 1) : 1;
                        $column = "hasil$index"; // Dinamis pilih kolom hasil

                        $hasil = $matchedValue[$column] ?: $matchedValue['hasil1'];

                        // Cek MDL (Method Detection Limit)
                        $mdl = MdlUdara::where(['parameter_id' => $paramId, 'is_active' => true])->latest()->first();
                        if ($mdl && $mdl->$column && $hasil < $mdl->$column) $hasil = '<' . $mdl->$column;

                        // Format khusus Getaran (JSON to String)
                        if ($paramId == self::PARAM_GETARAN) {
                            $hasil = collect(json_decode($hasil, true))->map(fn($v, $k) => "$k: $v")->join('<br />');
                        }

                        return ['no_sampel' => $od['no_sampel'], 'parameter' => $paramName, 'hasil_uji' => $hasil];
                    }
                    if ($config['type'] == 'emisi') {
                        $satuan = $matchedValue['satuan'] ?? $this->getSatuanFromRegulasi($od, $paramId);
                        $index = $satuan ? ($this->satuanHelper->emisi($satuan) ?: '') : '';
                        $column = "C$index"; // Dinamis pilih kolom C

                        $hasil = $matchedValue[$column] ?: $matchedValue['C'];

                        // Cek MDL Emisi
                        $mdl = MdlEmisi::where(['parameter_id' => $paramId, 'is_active' => true])->latest()->first();
                        if ($mdl && $mdl->$column && $hasil < $mdl->$column) $hasil = '<' . $mdl->$column;

                        return ['no_sampel' => $od['no_sampel'], 'parameter' => $paramName, 'hasil_uji' => $hasil];
                    }
                })->first(fn($res) => $res !== null); // Ambil hasil valid pertama yang ditemukan

                // --- PRIORITY 2: Cari di Data Lapangan---
                if (!$result) {
                    $result = collect(self::LAPANGAN_CONFIG)->map(function ($key) use ($od, $paramId, $paramName) {
                        $data = $od[$key] ?? null;
                        if (!$data) return null;

                        // Case Khusus: Psikologi (Return array, bukan single object)
                        // Psikologi return-nya List of Objects (Array Numeric)
                        if ($paramId == self::PARAM_PSIKOLOGI && is_array($data)) {
                            $psikoResults = collect($data)->filter(fn($item) => isset($item['is_approved']) || isset($item['is_approve']))
                                ->map(function ($item) use ($od, $paramName) {
                                    $titles = [
                                        'kp' => 'Konflik Peran',
                                        'pk' => 'Pengembangan Karir',
                                        'tp' => 'Ketaksaan Peran',
                                        'tjo' => 'Tanggung Jawab terhadap Orang Lain',
                                        'bbkual' => 'Beban Berlebih Kualitatif',
                                        'bbkuan' => 'Beban Berlebih Kuantitatif',
                                    ];

                                    $hasilUji = collect(json_decode($item['hasil'], true)['kesimpulan'] ?? [])
                                        ->map(fn($val, $key) => ($titles[$key] ?? '') . ": {$val['nilai']} ({$val['kesimpulan']})")
                                        ->join('<br />');

                                    return [
                                        'no_sampel' => $od['no_sampel'] . '<br />' . ($od['keterangan_1'] ?? ''),
                                        'parameter' => $paramName,
                                        'hasil_uji' => $hasilUji
                                    ];
                                })
                                ->values();

                            return $psikoResults->isNotEmpty() ? $psikoResults->all() : null;
                        }

                        // Cek Approval Data Lapangan
                        if (!isset($data['is_approved']) && !isset($data['is_approve'])) return null;

                        // Case: Ergonomi
                        if ($paramId == self::PARAM_ERGONOMI) {
                            return ['no_sampel' => $od['no_sampel'], 'parameter' => $paramName, 'hasil_uji' => 'Nilai sudah keluar'];
                        }

                        // Case: Kendaraan (Opasitas, CO, HC) pakai FIELD_MAP constant
                        if (isset(self::FIELD_MAP[$paramId])) {
                            return ['no_sampel' => $od['no_sampel'], 'parameter' => $paramName, 'hasil_uji' => $data[self::FIELD_MAP[$paramId]]];
                        }

                        return null;
                    })->first(fn($res) => $res !== null);
                }

                // --- FALLBACK: Jika tidak ditemukan hasil ---
                if (!$result) {
                    // Cek apakah ada data mentah yang sedang diproses (exists tapi belum approved/final)
                    $isOnProcess = collect($od)->only(array_merge(array_keys(self::WS_CONFIG), self::LAPANGAN_CONFIG))
                        ->filter()
                        ->isNotEmpty();

                    $result = [
                        'no_sampel' => $od['no_sampel'],
                        'parameter' => $paramName,
                        'hasil_uji' => $isOnProcess ? 'Sedang dilakukan analisa' : 'Belum dilakukan analisa'
                    ];
                }

                // STANDARDISASI RETURN VALUE:
                if (isset($result['no_sampel'])) {
                    return [$result];
                }

                return $result ?: [];
            });
        })->values()->all();
    }

    /**
     * Helper untuk mendapatkan satuan dari Regulasi/Baku Mutu.
     * Digunakan jika satuan tidak ditemukan di record hasil uji.
     */
    private function getSatuanFromRegulasi($od, $paramId)
    {
        $regulasiIds = collect(json_decode($od['regulasi'], true) ?? [])
            ->map(fn($item) => explode('-', $item)[0])
            ->unique();

        $bakuMutu = MasterBakumutu::where(['id_parameter' => $paramId, 'is_active' => true])
            ->whereIn('id_regulasi', $regulasiIds->all())
            ->first();

        return $bakuMutu->satuan ?? null;
    }
}
