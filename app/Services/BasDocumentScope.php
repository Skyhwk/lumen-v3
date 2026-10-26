<?php

namespace App\Services;

use App\Models\PersiapanSampelHeader;
use App\Models\OrderDetail;
use InvalidArgumentException;

class BasDocumentScope
{
    public static function samples($samples, $order)
    {
        if (!is_array($samples) || !$samples || !is_string($order) || trim($order) === '') {
            throw new InvalidArgumentException('Order dan sampel wajib diisi.');
        }
        $result = [];
        foreach ($samples as $sample) {
            if (!is_string($sample) || !preg_match('/^[A-Za-z0-9-]+(?:\/[A-Za-z0-9-]+)?$/D', $sample)) {
                throw new InvalidArgumentException('Nomor sampel tidak valid.');
            }
            if (strpos($sample, '/') === false) $sample = $order . '/' . $sample;
            if (strpos($sample, $order . '/') !== 0) throw new InvalidArgumentException('Sampel tidak sesuai order.');
            $result[] = $sample;
        }
        $result = array_values(array_unique($result));
        sort($result);
        return $result;
    }

    public static function date($date)
    {
        if (!is_string($date)) return false;
        $parsed = \DateTime::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }

    public static function resolve(array $item, $lock = false)
    {
        $order = $item['no_order'] ?? null;
        $date = $item['tanggal_sampling'] ?? null;
        if (!self::date($date)) throw new InvalidArgumentException('Tanggal sampling tidak valid.');
        $samples = self::samples($item['no_sampel'] ?? null, $order);
        $query = PersiapanSampelHeader::where('no_order', $order)->where('tanggal_sampling', $date)->where('is_active', true);
        if (!empty($item['id_persiapan'])) $query->where('id', $item['id_persiapan']);
        if (!empty($item['no_quotation'])) $query->where('no_quotation', $item['no_quotation']);
        if ($lock) $query->lockForUpdate();
        $matches = $query->get()->filter(function ($header) use ($samples, $order) {
            $stored = json_decode($header->no_sampel, true);
            if (is_string($stored)) $stored = json_decode($stored, true);
            if (!is_array($stored) || !$stored) return false;
            return !array_diff($samples, self::samples($stored, $order));
        });
        if ($matches->count() !== 1) throw new InvalidArgumentException('Header persiapan tidak ditemukan atau ambigu untuk order, tanggal, dan seluruh sampel.');
        $valid = OrderDetail::where('no_order', $order)->where('tanggal_sampling', $date)->where('is_active', true)->whereIn('no_sampel', $samples)->pluck('no_sampel')->unique()->all();
        if (array_diff($samples, $valid)) throw new InvalidArgumentException('Sampel tidak aktif atau tidak sesuai tanggal/order.');
        return $matches->first();
    }

    public static function validDecision($decision, $date)
    {
        if (!$decision || !empty($decision->is_finished)) return false;
        if ($decision->status === 'Dilanjutkan') {
            if (empty($decision->tanggal_dilanjutkan) || trim((string) $decision->tanggal_dilanjutkan) === '') {
                return true;
            }
            return self::date($decision->tanggal_dilanjutkan) && $decision->tanggal_dilanjutkan >= $date;
        }
        return $decision->status === 'Belum Selesai'
            && is_string($decision->alasan) && trim($decision->alasan) !== ''
            && $decision->alasan !== 'Dibatalkan otomatis dari Submit BAS'
            && ($decision->alasan !== 'Lainnya' || self::lainnyaKeteranganError($decision->keterangan) === '');
    }

    public static function freeTextError($value, $label = 'Teks')
    {
        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return $label . ' wajib diisi.';
        }
        if (strpos($text, '-') !== false) {
            return $label . ' tidak boleh memakai tanda minus (-).';
        }
        if (mb_strlen($text) < 10) {
            return $label . ' minimal 10 karakter.';
        }

        preg_match_all('/[A-Za-zÀ-ÿ]/u', $text, $matches);
        $letters = $matches[0] ?? [];
        if (count($letters) < 6) {
            return 'Tuliskan ' . mb_strtolower($label) . ' yang jelas, bukan angka atau simbol saja.';
        }

        $compact = strtolower(implode('', $letters));
        $unique = count(array_unique(str_split($compact)));
        $noSpace = preg_replace('/\s+/', '', $text);
        if (preg_match('/(.)\1{3,}/', $noSpace)) {
            return $label . ' terlihat ngasal. Tuliskan dengan kalimat yang benar.';
        }
        if ($unique < 4) {
            return $label . ' terlihat ngasal. Tuliskan dengan kalimat yang benar.';
        }
        if (strlen($compact) >= 10 && ($unique / strlen($compact)) > 0.85) {
            return $label . ' terlihat ngasal. Tuliskan dengan kalimat yang benar.';
        }
        if (!preg_match('/[aiueo]/', $compact)) {
            return $label . ' terlihat ngasal. Tuliskan dengan kalimat yang benar.';
        }
        if (preg_match('/^(.{2,5})\1{2,}$/', $compact)) {
            return $label . ' terlihat ngasal. Tuliskan dengan kalimat yang benar.';
        }

        return '';
    }

    public static function lainnyaKeteranganError($value)
    {
        return self::freeTextError($value, 'Alasan');
    }

    public static function filename($name)
    {
        return is_string($name) && $name !== '' && basename($name) === $name
            && !preg_match('/[\\\\\/\x00-\x1f]/', $name) && strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pdf';
    }
}
