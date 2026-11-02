<?php

namespace App\Services;

use App\Models\DataAset;
use App\Models\GaAsset;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssetQrValidationService
{
    public const BASE_URL = 'https://www.intilab.com/validation/';

    public static function newToken(): string
    {
        return 'isldc' . bin2hex(random_bytes(16));
    }

    public static function url(string $token): string
    {
        return self::BASE_URL . rawurlencode($token);
    }

    /** Stable identity: exporting a label again must not invalidate a printed QR. */
    public function registerGaAsset(int $assetId, string $actor = 'GA Asset System'): object
    {
        return DB::transaction(function () use ($assetId, $actor) {
            $asset = GaAsset::where('id', $assetId)->where('is_active', 1)->lockForUpdate()->first();
            if (!$asset || trim((string) $asset->cs_code) === '') throw new \InvalidArgumentException('Aset aktif dengan NO CS tidak ditemukan.');
            $file = 'GA-ASSET-' . $asset->id;
            $record = DB::table('qr_documents')->where('type_document', 'asset')->where('file', $file)->lockForUpdate()->first();
            $payload = array_merge($this->gaPayload($asset), ['_asset_source' => 'ga_assets', '_asset_id' => (int) $asset->id]);
            $values = ['data' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
            if ($record) {
                DB::table('qr_documents')->where('id', $record->id)->update($values);
                $record->data = $values['data'];
                return $record;
            }
            $id = DB::table('qr_documents')->insertGetId(array_merge($values, [
                'type_document' => 'asset', 'kode_qr' => self::newToken(), 'file' => $file,
                'created_at' => Carbon::now('Asia/Jakarta'), 'created_by' => trim($actor) ?: 'GA Asset System',
            ]));
            return DB::table('qr_documents')->where('id', $id)->first();
        });
    }

    /** Explicit validation fields; relationship IDs and QR routing metadata stay internal. */
    public function publicData($record): ?array
    {
        if (!$record || $record->type_document !== 'asset') return null;
        $data = json_decode((string) $record->data, true);
        if (!is_array($data)) return null;
        if (($data['_asset_source'] ?? '') === 'ga_assets') {
            $asset = GaAsset::where('id', (int) ($data['_asset_id'] ?? 0))->where('is_active', 1)->first();
            return $asset ? $this->displayLabels($this->gaPayload($asset)) : null;
        }
        // Legacy asset QR records already store no_cs in qr_documents.data.
        if (!empty($data['no_cs'])) {
            $asset = DataAset::where('no_cs', $data['no_cs'])->where('is_active', 1)->first();
            if (!$asset) return null;
            return $this->displayLabels(['type_document' => 'asset', 'no_cs' => $asset->no_cs, 'merk' => $asset->merk,
                'tanggal_pembelian' => $asset->tanggal_pembelian, 'kondisi' => $asset->kondisi,
                'status' => $asset->status_alat, 'ruang' => $asset->ruang, 'lokasi' => $asset->lokasi]);
        }
        return null;
    }

    /** No HTTP request: resolve the token locally, then retain the existing NO CS lookup. */
    public function opnameCode(string $input): string
    {
        $input = trim($input);
        if ($input === '' || strlen($input) > 500) throw new \InvalidArgumentException('Kode scan tidak valid.');
        $isUrl = strpos($input, '://') !== false;
        if ($isUrl) {
            $parts = parse_url($input);
            if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https'
                || !in_array(strtolower($parts['host'] ?? ''), ['www.intilab.com', 'intilab.com'], true)
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
                || !preg_match('~^/validation/([A-Za-z0-9_-]{1,100})/?$~D', $parts['path'] ?? '', $matches)) {
                throw new \InvalidArgumentException('URL QR bukan URL validation Intilab yang valid.');
            }
            $token = $matches[1];
        } else {
            $token = $input;
        }
        if ($isUrl || strpos($token, 'isldc') === 0) {
            $record = DB::table('qr_documents')->where('kode_qr', $token)->where('type_document', 'asset')->first();
            $data = $record ? json_decode((string) $record->data, true) : null;
            if (!is_array($data) || ($data['_asset_source'] ?? '') !== 'ga_assets') {
                throw new \InvalidArgumentException('QR bukan milik aset Finance yang terdaftar.');
            }
            $asset = GaAsset::where('id', (int) ($data['_asset_id'] ?? 0))->where('is_active', 1)->first();
            if (!$asset || trim((string) $asset->cs_code) === '') throw new \InvalidArgumentException('Aset pada QR sudah tidak aktif atau tidak ditemukan.');
            return strtoupper(trim($asset->cs_code));
        }
        if (strlen($input) > 191) throw new \InvalidArgumentException('NO CS terlalu panjang.');
        return strtoupper($input);
    }

    private function gaPayload(GaAsset $asset): array
    {
        $asset->loadMissing(['room.location.cabang', 'kategori', 'subKategori']);
        $room = $asset->room;
        $location = $room ? $room->location : null;
        $branch = $location ? $location->cabang : null;
        return [
            'type_document' => 'asset',
            'no_cs' => $asset->cs_code,
            'kode_aset' => $asset->asset_code,
            'nama_aset' => $asset->name,
            'jenis_record' => $asset->record_kind,
            'kategori' => optional($asset->kategori)->nama_kategori,
            'sub_kategori' => optional($asset->subKategori)->nama_sub_kategori,
            'merk' => $asset->brand,
            'spesifikasi' => $this->plainText($asset->model_spec),
            'ruang' => optional($room)->name,
            'lokasi' => optional($location)->name,
            'cabang' => optional($branch)->nama_cabang,
            'kondisi' => $asset->condition,
            'condition_note' => $this->plainText($asset->condition_note),
            'condition_text' => $this->plainText($asset->condition_text),
            'status' => $asset->lifecycle_state,
            'status_penggunaan' => $asset->usage_state,
            'status_publikasi' => $asset->publication_state,
            'status_verifikasi' => $asset->verification_state,
            'asal_perolehan' => $asset->acquisition_origin,
            'purchase_date' => $asset->purchase_date,
            'acquisition_cost' => $asset->acquisition_cost === null ? null : 'Rp.' . number_format((float) $asset->acquisition_cost, 0, ',', '.'),
            'useful_life_years' => $asset->useful_life_years,
            'damage_received_date' => $asset->damage_received_date,
        ];
    }

    private function displayLabels(array $data): array
    {
        $labels = [
            'type_document' => 'Jenis Dokumen', 'no_cs' => 'Nomor CS',
            'kode_aset' => 'Kode Aset', 'nama_aset' => 'Nama Aset', 'jenis_record' => 'Jenis Aset',
            'kategori' => 'Kategori', 'sub_kategori' => 'Subkategori', 'merk' => 'Merek',
            'spesifikasi' => 'Spesifikasi', 'ruang' => 'Ruangan', 'lokasi' => 'Lokasi', 'cabang' => 'Cabang',
            'kondisi' => 'Kondisi', 'condition_note' => 'Catatan Kondisi', 'condition_text' => 'Keterangan Kondisi',
            'status' => 'Status Aset', 'status_penggunaan' => 'Status Penggunaan',
            'status_publikasi' => 'Status Publikasi', 'status_verifikasi' => 'Status Verifikasi',
            'asal_perolehan' => 'Asal Perolehan', 'purchase_date' => 'Tanggal Pembelian',
            'tanggal_pembelian' => 'Tanggal Pembelian', 'acquisition_cost' => 'Harga Perolehan',
            'useful_life_years' => 'Masa Manfaat (Tahun)', 'damage_received_date' => 'Tanggal Diterima Rusak',
        ];
        $result = [];
        foreach ($data as $key => $value) {
            if (in_array($key, ['purchase_date', 'tanggal_pembelian'], true) && $value !== null && trim((string) $value) !== '') {
                $value = Carbon::parse($value)->locale('id')->translatedFormat('j F Y');
            }
            $result[$labels[$key] ?? $key] = $value;
        }
        return $result;
    }

    private function plainText($value): ?string
    {
        if ($value === null) return null;
        $text = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('~<br\s*/?>|</p>|</div>|</li>~i', "\n", $text);
        return trim(strip_tags($text));
    }
}
