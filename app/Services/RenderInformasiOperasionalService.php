<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Mpdf;

class RenderInformasiOperasionalService
{
    public const DIR = 'informasi_operasional';

    public static function judulByWarna($warna)
    {
        $key = strtolower(trim((string) $warna));
        if ($key === 'merah') {
            return 'LEMBAR PERENCANAAN KEGIATAN SAMPLING AIR';
        }
        if ($key === 'biru') {
            return 'LEMBAR PERENCANAAN KEGIATAN SAMPLING (BIRU)';
        }
        if ($key === 'hitam') {
            return 'LEMBAR PERENCANAAN KEGIATAN SAMPLING (HITAM)';
        }

        return 'LEMBAR PERENCANAAN KEGIATAN SAMPLING';
    }

    public static function buildInnerHtml($data)
    {
        $viewData = self::viewData($data);

        return view('pdf.informasi-operasional.styles', $viewData)->render()
            . view('pdf.informasi-operasional.body', $viewData)->render();
    }

    public function renderPdf($data)
    {
        try {
            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 3,
                'margin_bottom' => 3,
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            ]);

            $footer = array(
                'odd' => array(
                    'C' => array(
                        'content' => 'Hal {PAGENO} dari {nbpg}',
                        'font-size' => 6,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#606060'
                    ),
                    'R' => array(
                        'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                        'font-size' => 5,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'line' => -1,
                )
            );

            $pdf->setFooter($footer);
            $pdf->WriteHTML(view('pdf.informasi-operasional.document', self::viewData($data))->render());

            $safeQt = preg_replace('/[^A-Za-z0-9]+/', '_', (string) ($data['no_order'] ?? $data['no_quotation'] ?? 'IO'));
            $fileName = 'IO_' . $safeQt . '_' . date('YmdHis') . '.pdf';

            $dirPath = public_path(self::DIR);
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0775, true);
            }

            $pdf->Output($dirPath . '/' . $fileName, \Mpdf\Output\Destination::FILE);

            return $fileName;
        } catch (\Exception $ex) {
            Log::info(["Error message" => $ex->getMessage(), "Error line" => $ex->getLine(), "Error file" => $ex->getFile()]);
            return null;
        }
    }

    private static function viewData($data): array
    {
        $warna = strtoupper(trim((string) ($data['warna'] ?? '')));
        $samples = array_values($data['samples'] ?? []);
        $jumlah = (int) ($data['jumlah_sampel'] ?? count($samples));
        $peralatan = $data['peralatan'] ?? [];
        $petugas = array_values($data['petugas'] ?? []);
        while (count($petugas) < 3) {
            $petugas[] = ['nama' => '', 'jabatan' => '', 'kompetensi' => ''];
        }

        $paraf = array_values($data['petugas'] ?? []);
        while (count($paraf) < 4) {
            $paraf[] = ['nama' => ''];
        }

        $ketOps = trim((string) ($data['keterangan_operasional'] ?? ''));
        $ketOps = $ketOps !== ''
            ? strip_tags($ketOps, '<p><br><br/><b><strong><i><em><ul><ol><li><span><div>')
            : '-';

        $qsTambahan = self::listItems($data['qs_tambahan'] ?? []);
        $qsLain = self::listItems($data['qs_keterangan_lain'] ?? []);

        return [
            'warna' => $warna,
            'judul' => self::judulByWarna($data['warna'] ?? ''),
            'noOrder' => self::display($data['no_order'] ?? $data['no_quotation'] ?? '-'),
            'namaPerusahaan' => self::display($data['nama_perusahaan'] ?? '-'),
            'tanggalLabel' => self::display($data['tanggal_label'] ?? '-'),
            'jumlah' => $jumlah,
            'jumlahLabel' => $warna === 'MERAH' ? 'RENCANA JUMLAH SAMPEL AIR' : 'RENCANA JUMLAH SAMPEL',
            'sampelNoun' => $warna === 'MERAH' ? 'SAMPEL AIR' : 'SAMPEL',
            'samples' => $samples,
            'sampleTwoCols' => count($samples) > 10,
            'sampleRows' => self::sampleRows($samples),
            'petugas' => $petugas,
            'paraf' => $paraf,
            'tujuanSampling' => trim((string) ($data['tujuan_sampling'] ?? '')),
            'alatGroups' => self::alatGroups($peralatan),
            'kendaraan' => self::display($data['kendaraan'] ?? '-'),
            'sampler' => self::display($data['sampler'] ?? '-'),
            'waktuBerangkat' => self::display($data['waktu_berangkat'] ?? '-'),
            'jamMulai' => self::display($data['jam_mulai'] ?? '-'),
            'wilayah' => self::display($data['wilayah'] ?? '-'),
            'picDisplay' => self::display($data['pic_display'] ?? '-'),
            'qsTambahan' => $qsTambahan,
            'qsLain' => $qsLain,
            'keteranganOperasional' => $ketOps,
            'petugasSamplingPlan' => self::display($data['petugas_sampling_plan'] ?? '-'),
            'validasiAt' => self::display($data['validasi_at'] ?? '-'),
        ];
    }

    private static function sampleRows(array $samples): array
    {
        $count = count($samples);
        if ($count <= 10) {
            return [];
        }

        $slots = (int) ceil($count / 2);
        $rows = [];
        for ($i = 0; $i < $slots; $i++) {
            $rows[] = [
                'left_no' => $i + 1,
                'right_no' => $i + 1 + $slots,
                'left' => $samples[$i] ?? null,
                'right' => $samples[$i + $slots] ?? null,
            ];
        }

        return $rows;
    }

    private static function alatGroups(array $peralatan): array
    {
        $catalog = [
            'Alat Pengambilan Contoh Uji' => [
                'Sederhana(Gayung)',
                'Sederhana (Botol)',
                'Bailer',
                'Well Water Sampler',
                'Horizontal/Vertikal Water Sampler',
            ],
            'Alat Ukur Parameter Lapangan' => [
                'pH Meter',
                'Thermometer',
                'Conductivity Meter',
                'DO Meter',
                'Thermohygrometer',
                'Chlorine Meter',
                'GPS',
                'Secchi Disk',
                'Alat Ukur Kedalaman',
                'Meteran',
            ],
            'Alat Pendukung' => [
                'Pipet Tetes',
                'pH Kertas Universal',
                'Coolbox',
                'P3K',
                'Ember Tampung',
                'Vakum Penyaringan',
                'Corong Penyaringan',
                'Kabel Roll',
                'Tongkat Kedalaman',
                'Alat Ukur Ketinggian Pasang-Surut',
                'Tali Tambang',
            ],
        ];

        $used = [];
        $groups = [];
        foreach ($catalog as $label => $names) {
            $groups[] = [
                'label' => $label,
                'rows' => self::mapAlatRows($names, $peralatan, $used),
            ];
        }

        $extras = [];
        foreach ($peralatan as $item) {
            $key = self::norm($item['nama_alat'] ?? '');
            $alt = self::norm($item['nama_aset'] ?? '');
            if (($key !== '' && isset($used[$key])) || ($alt !== '' && isset($used[$alt]))) {
                continue;
            }
            $label = $item['nama_alat'] ?: ($item['nama_aset'] ?? '');
            if ($label === '') {
                continue;
            }
            $extras[] = $label;
            if ($key !== '') $used[$key] = true;
            if ($alt !== '') $used[$alt] = true;
        }
        if ($extras) {
            $groups[] = [
                'label' => 'Aset yang sudah di-set (Monitoring Alat)',
                'rows' => self::mapAlatRows($extras, $peralatan, $used),
            ];
        }

        return $groups;
    }

    private static function mapAlatRows(array $names, array $peralatan, array &$used): array
    {
        $rows = [];
        foreach ($names as $name) {
            $match = self::matchAlat($name, $peralatan);
            if ($match) {
                $used[self::norm($match['nama_alat'] ?? $name)] = true;
                $used[self::norm($match['nama_aset'] ?? '')] = true;
                $used[self::norm($name)] = true;
            }
            $nomor = $match ? self::csCodeList($match) : '';
            $rows[] = [
                'nama' => $name,
                'nomor' => $nomor,
                'jumlah' => $nomor !== '' ? (string) ($match['jumlah'] ?? count((array) ($match['cs_code'] ?? []))) : '',
            ];
        }

        return $rows;
    }

    private static function csCodeList(array $match): string
    {
        $raw = $match['cs_code'] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $codes = [];
        foreach ($raw as $item) {
            $value = is_array($item)
                ? trim((string) ($item['cs_code'] ?? ''))
                : trim((string) $item);
            if ($value === '' || stripos($value, 'GA-') === 0) {
                continue;
            }
            $codes[] = $value;
        }

        return implode('<br>', array_map(function ($code) {
            return htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        }, array_values(array_unique($codes))));
    }

    public static function matchAlat($templateName, $peralatan)
    {
        $target = self::norm($templateName);
        if ($target === '') {
            return null;
        }

        foreach ($peralatan as $item) {
            if (self::csCodeList($item) === '') {
                continue;
            }
            foreach (['nama_alat', 'nama_aset'] as $field) {
                $name = self::norm($item[$field] ?? '');
                if ($name === '') {
                    continue;
                }
                if ($name === $target || strpos($target, $name) !== false || strpos($name, $target) !== false) {
                    return $item;
                }
            }
        }

        return null;
    }

    public static function norm($value)
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $value));
    }

    private static function listItems($items)
    {
        $clean = array_values(array_filter(array_map('trim', (array) $items)));
        if (!$clean) {
            return '-';
        }

        return implode(', ', $clean);
    }

    private static function display($value)
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : '-';
    }
}
