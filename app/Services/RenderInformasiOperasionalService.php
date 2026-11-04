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
        return self::styleTag() . self::bodyHtml($data);
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
            $pdf->WriteHTML('<!DOCTYPE html>
                <html>
                    <head>
                        ' . self::styleTag() . '
                    </head>
                    <body>
                        ' . self::bodyHtml($data) . '
                    </body>
                </html>
            ');

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

    private static function styleTag()
    {
        return '<style>
            .custom1 { font-size: 11px; font-weight: bold; }
            .custom2 { font-size: 18px; font-weight: bold; text-align: center; padding: 5px; }
            .custom3 { font-size: 12px; font-weight: bold; text-align: center; padding: 5px; }
            .custom4 { font-size: 12px; font-weight: bold; text-align: center; border: 1px solid #000000; padding: 5px; }
            .custom5 { border: 1px solid #000000; }
            .custom6 { font-size: 11px; border: 1px solid #000000; padding: 5px; }
            .custom7 { font-size: 12px; border: 1px solid #000000; padding: 5px; }
            .io-section { margin: 0 0 12px 0; }
        </style>';
    }

    private static function bodyHtml($data)
    {
        $warna = strtoupper(trim((string) ($data['warna'] ?? '')));
        $judul = self::judulByWarna($data['warna'] ?? '');
        $noOrder = self::e($data['no_order'] ?? $data['no_quotation'] ?? '-');
        $jumlah = (int) ($data['jumlah_sampel'] ?? count($data['samples'] ?? []));
        $jumlahLabel = $warna === 'MERAH' ? 'RENCANA JUMLAH SAMPEL AIR' : 'RENCANA JUMLAH SAMPEL';
        $sampelNoun = $warna === 'MERAH' ? 'SAMPEL AIR' : 'SAMPEL';
        $peralatan = $data['peralatan'] ?? [];
        $table = 'width="100%" class="io-section" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;"';

        $html = '
            <div class="custom1" style="margin-bottom: 6px;">UNTUK PILIHAN ' . self::e($warna !== '' ? $warna : '-') . '</div>
            <table ' . $table . '>
                <tr>
                    <td width="22%" class="custom1 custom5" style="text-align: center;">PT INTI SURYA LABORATORIUM</td>
                    <td width="50%" class="custom2 custom5">' . self::e($judul) . '</td>
                    <td width="12%" class="custom3 custom5">NO.<br>ORDER</td>
                    <td width="16%" class="custom4" style="font-size: 14px;">' . $noOrder . '</td>
                </tr>
                <tr>
                    <td class="custom1 custom6">NAMA PELANGGAN</td>
                    <td colspan="3" class="custom6" style="text-align: center; font-weight: bold;">' . self::e($data['nama_perusahaan'] ?? '-') . '</td>
                </tr>
                <tr>
                    <td class="custom1 custom6">RENCANA TANGGAL SAMPLING</td>
                    <td colspan="3" class="custom6" style="text-align: center; font-weight: bold;">' . self::e($data['tanggal_label'] ?? '-') . '</td>
                </tr>
                <tr>
                    <td class="custom1 custom6">' . self::e($jumlahLabel) . '</td>
                    <td colspan="3" class="custom6" style="text-align: center; font-weight: bold;">' . ($jumlah > 0 ? $jumlah . ' SAMPEL' : '-') . '</td>
                </tr>
            </table>
        ';

        $html .= self::sampleGrid($data['samples'] ?? [], $sampelNoun, $table);
        $html .= self::spacer();
        $html .= self::petugasTable($data['petugas'] ?? [], $table);
        $html .= self::spacer();
        $html .= self::jenisSampelBlock($data, $peralatan, $table);
        $html .= self::spacer();
        $html .= self::infoKegiatanBlock($table);
        $html .= self::spacer();
        $html .= self::prosesSampelBlock($table);
        $html .= self::spacer();
        $html .= self::jaminanBlock($table);
        $html .= self::spacer();
        $html .= self::peralatanDigunakanBlock($table);
        $html .= self::spacer();
        $html .= self::parafTable($data['petugas'] ?? [], $table);
        $html .= self::spacer();
        $html .= self::opsSupplement($data, $table);

        return $html;
    }

    private static function spacer()
    {
        return '<div style="height: 12px; line-height: 12px; font-size: 1px;">&nbsp;</div>';
    }

    private static function sampleGrid($samples, $sampelNoun, $table)
    {
        $samples = array_values($samples);
        $count = count($samples);

        if ($count <= 10) {
            $html = '
            <table ' . $table . '>
                <tr>
                    <th width="8%" class="custom4">NO.</th>
                    <th width="46%" class="custom4">NO. ' . self::e($sampelNoun) . '</th>
                    <th width="46%" class="custom4">KATEGORI SAMPEL</th>
                </tr>
            ';
            if ($count === 0) {
                $html .= '
                <tr>
                    <td class="custom6" style="text-align: center;">1</td>
                    <td class="custom6"></td>
                    <td class="custom6"></td>
                </tr>';
            } else {
                foreach ($samples as $i => $row) {
                    $html .= '
                <tr>
                    <td class="custom6" style="text-align: center;">' . ($i + 1) . '</td>
                    <td class="custom6" style="text-align: center; font-weight: bold;">' . self::e($row['no_sampel'] ?? '', true) . '</td>
                    <td class="custom6" style="text-align: center; font-weight: bold;">' . self::e($row['kategori'] ?? '', true) . '</td>
                </tr>';
                }
            }
            $html .= '</table>';

            return $html;
        }

        $slots = (int) ceil($count / 2);
        $html = '
            <table ' . $table . '>
                <tr>
                    <th width="6%" class="custom4">NO.</th>
                    <th width="22%" class="custom4">NO. ' . self::e($sampelNoun) . '</th>
                    <th width="22%" class="custom4">KATEGORI SAMPEL</th>
                    <th width="6%" class="custom4">NO.</th>
                    <th width="22%" class="custom4">NO. ' . self::e($sampelNoun) . '</th>
                    <th width="22%" class="custom4">KATEGORI SAMPEL</th>
                </tr>
        ';

        for ($i = 0; $i < $slots; $i++) {
            $l = $samples[$i] ?? null;
            $r = $samples[$i + $slots] ?? null;
            $html .= '
                <tr>
                    <td class="custom6" style="text-align: center;">' . ($i + 1) . '</td>
                    <td class="custom6" style="text-align: center; font-weight: bold;">' . self::e($l['no_sampel'] ?? '', true) . '</td>
                    <td class="custom6" style="text-align: center; font-weight: bold;">' . self::e($l['kategori'] ?? '', true) . '</td>
                    <td class="custom6" style="text-align: center;">' . ($i + 1 + $slots) . '</td>
                    <td class="custom6" style="text-align: center; font-weight: bold;">' . self::e($r['no_sampel'] ?? '', true) . '</td>
                    <td class="custom6" style="text-align: center; font-weight: bold;">' . self::e($r['kategori'] ?? '', true) . '</td>
                </tr>
            ';
        }

        $html .= '</table>';

        return $html;
    }

    private static function petugasTable($petugas, $table)
    {
        $rows = array_values($petugas);
        while (count($rows) < 3) {
            $rows[] = ['nama' => '', 'jabatan' => '', 'kompetensi' => ''];
        }

        $html = '
            <table ' . $table . '>
                <tr>
                    <td colspan="4" class="custom4">DETAIL PERENCANAAN KEGIATAN SAMPLING</td>
                </tr>
                <tr>
                    <th width="8%" class="custom4">No</th>
                    <th width="34%" class="custom4">Nama Petugas Pengambil Contoh</th>
                    <th width="29%" class="custom4">Jabatan</th>
                    <th width="29%" class="custom4">Kompetensi</th>
                </tr>
        ';

        foreach ($rows as $i => $row) {
            $html .= '
                <tr>
                    <td class="custom6" style="text-align: center;">' . ($i + 1) . '</td>
                    <td class="custom6" style="text-align: center; font-weight: bold;">' . self::e($row['nama'] ?? '', true) . '</td>
                    <td class="custom6" style="text-align: center; font-weight: bold;">' . self::e($row['jabatan'] ?? '', true) . '</td>
                    <td class="custom6" style="text-align: center; font-weight: bold;">' . self::e($row['kompetensi'] ?? '', true) . '</td>
                </tr>
            ';
        }

        $html .= '</table>';

        return $html;
    }

    private static function jenisSampelBlock($data, $peralatan, $table)
    {
        $tujuan = trim((string) ($data['tujuan_sampling'] ?? ''));
        $html = '
            <table ' . $table . '>
                <tr>
                    <td width="8%" class="custom4">1</td>
                    <td colspan="3" class="custom4">JENIS SAMPEL KE-1</td>
                    <td width="10%" class="custom4">Verifikasi</td>
                </tr>
                <tr>
                    <td colspan="2" class="custom1 custom6">Tujuan Pengambilan Sampel</td>
                    <td colspan="2" class="custom6" style="text-align: center; font-weight: bold;">' . self::e($tujuan, true) . '</td>
                    <td class="custom6"></td>
                </tr>
                <tr>
                    <th width="18%" class="custom4"></th>
                    <th class="custom4"></th>
                    <th width="18%" class="custom4">Nomor Alat</th>
                    <th width="10%" class="custom4">Jumlah</th>
                    <th width="10%" class="custom4"></th>
                </tr>
        ';

        $used = [];
        $html .= self::alatGroup('Alat Pengambilan Contoh Uji', [
            'Sederhana(Gayung)',
            'Sederhana (Botol)',
            'Bailer',
            'Well Water Sampler',
            'Horizontal/Vertikal Water Sampler',
        ], $peralatan, $used);
        $html .= self::alatGroup('Alat Ukur Parameter Lapangan', [
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
        ], $peralatan, $used);
        $html .= self::alatGroup('Alat Pendukung', [
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
        ], $peralatan, $used);

        $extras = [];
        foreach ($peralatan as $item) {
            $key = self::norm($item['nama_alat'] ?? '');
            if ($key === '' || isset($used[$key])) {
                continue;
            }
            $extras[] = $item['nama_alat'];
            $used[$key] = true;
        }
        if ($extras) {
            $html .= self::alatGroup('Alat Tambahan Sampling Plan', $extras, $peralatan, $used);
        }

        $html .= self::staticQtyGroup('Dokumen Pendukung', [
            ['Surat Tugas Pengambilan Sampel', '1'],
            ['Berita Acara Sampling', '1'],
            ['Jobs Safety Analisis', '1'],
            ['Form Perencanaan Sampling', '1'],
            ['Form Pengambilan Sampel', '1<br><span style="font-weight: bold;">(Berdasarkan Jumlah Titik Sampling)</span>'],
            ['Chain Of Custody', '1<br><span style="font-weight: bold;">(Berdasarkan Jumlah Titik Sampling)</span>'],
        ]);
        $html .= self::staticQtyGroup('Jenis Bahan Wadah<br>(Mengikuti Rekap Persiapan Sampling)', [
            ['Botol Polyethylene (PE) 500 mL', ''],
            ['Botol Polyethylene (PE) 1000 mL', ''],
            ['Botol Winkler 300 mL', ''],
            ['Botol Glass 100 mL', ''],
            ['Botol Amber Glass 250 mL', ''],
            ['Botol Glass Steril 100 mL', ''],
            ['Botol Amber Glass 250 mL (Steril)', ''],
            ['Botol Glass Mulut Lebar 1000 mL', ''],
            ['Botol Steril', ''],
        ]);
        $html .= self::staticQtyGroup('Penanganan Wadah Contoh<br>(Mengikuti Rekap Persiapan Sampling)', [
            ['Dibilas dengan 1:1 HNO3', ''],
            ['Dibilas dengan 1:1 HCl', ''],
        ]);
        $html .= '</table>';

        return $html;
    }

    private static function alatGroup($label, $names, $peralatan, &$used)
    {
        $count = count($names);
        $html = '';
        foreach ($names as $i => $name) {
            $match = self::matchAlat($name, $peralatan);
            if ($match) {
                $used[self::norm($match['nama_alat'] ?? $name)] = true;
                $used[self::norm($name)] = true;
            }
            $nomor = $match && !empty($match['nomor_alat']) ? implode(', ', (array) $match['nomor_alat']) : '';
            $jumlah = $match ? (string) ($match['jumlah'] ?? '') : '';
            $html .= '<tr>';
            if ($i === 0) {
                $html .= '<td rowspan="' . $count . '" class="custom1 custom6" style="text-align: center;">' . $label . '</td>';
            }
            $html .= '
                    <td class="custom6" style="text-align: center;">' . self::e($name) . '</td>
                    <td class="custom6" style="text-align: center; font-weight: bold;">' . self::e($nomor, true) . '</td>
                    <td class="custom6" style="text-align: center; font-weight: bold;">' . self::e($jumlah, true) . '</td>
                    <td class="custom6"></td>
                </tr>
            ';
        }

        return $html;
    }

    private static function staticQtyGroup($label, $rows)
    {
        $count = count($rows);
        $html = '';
        foreach ($rows as $i => $row) {
            $html .= '<tr>';
            if ($i === 0) {
                $html .= '<td rowspan="' . $count . '" class="custom1 custom6" style="text-align: center;">' . $label . '</td>';
            }
            $html .= '
                    <td class="custom6" style="text-align: center;">' . $row[0] . '</td>
                    <td class="custom6"></td>
                    <td class="custom6" style="text-align: center; font-weight: bold;">' . $row[1] . '</td>
                    <td class="custom6"></td>
                </tr>
            ';
        }

        return $html;
    }

    private static function infoKegiatanBlock($table)
    {
        return '
            <table ' . $table . '>
                <tr><td colspan="3" class="custom4">INFORMASI PERENCANAAN KEGIATAN SAMPLING</td></tr>
                <tr><td width="6%" class="custom6" style="text-align: center;">a</td><td width="36%" class="custom6">Frekuensi Pengambilan (Kali)</td><td class="custom6" style="text-align: center; font-weight: bold;">1</td></tr>
                <tr><td class="custom6" style="text-align: center;">b</td><td class="custom6">Waktu Pengambilan (Menit)</td><td class="custom6" style="text-align: center; font-weight: bold;">10</td></tr>
                <tr><td class="custom6" style="text-align: center;">c</td><td class="custom6">Dokumentasi Lokasi</td><td class="custom6" style="text-align: center; font-weight: bold;">Dengan Foto</td></tr>
                <tr><td class="custom6" style="text-align: center;">d</td><td class="custom6">Dokumentasi Titik Sampling</td><td class="custom6" style="text-align: center; font-weight: bold;">Dengan Foto</td></tr>
                <tr><td class="custom6" style="text-align: center;">e</td><td class="custom6">Acuan Metode Sampling Fisika dan Kimia</td><td class="custom6" style="text-align: center; font-weight: bold;">SNI 8995:2021 / SNI 9063:2022</td></tr>
            </table>
        ';
    }

    private static function prosesSampelBlock($table)
    {
        $html = '<table ' . $table . '><tr><td colspan="3" class="custom4">INFORMASI PERENCANAAN PROSES SAMPEL</td></tr>';
        $html .= self::checkGroup('Pengawetan', [
            'Pendinginan suhu ≤ 6°C',
            'Pendinginan suhu ≤ 10°C',
            'HNO3 Pekat',
            'H2SO4 Pekat',
            'HCl',
            'Zink Asetat',
            'Dinatrium EDTA',
            'NaOH Pekat',
            'Sodium Thiosulfat',
        ]);
        $html .= self::checkGroup('Teknik Pengambilan', [
            'Gabungan Kedalaman',
            'Gabungan Waktu',
        ]);
        $html .= '</table>';

        return $html;
    }

    private static function jaminanBlock($table)
    {
        $html = '<table ' . $table . '><tr><td colspan="3" class="custom4">INFORMASI PERENCANAAN PENGATURAN JAMINAN TERHADAP SAMPEL</td></tr>';
        $html .= self::checkGroup('Pengendalian Mutu', [
            'Blanko Lapangan',
            'Blanko Peralatan',
            'Blanko Penyaringan',
            'Split',
            'Duplikat',
            'Larutan Buffer pH 10',
            'Larutan Buffer pH 7',
            'Larutan Buffer pH 4',
            'Larutan Buffer Conductivity 10 µS/cm',
            'Larutan Buffer Conductivity 100 µS/cm',
            'Larutan Buffer Conductivity 1413 µS/cm',
            'Larutan Buffer Klorin 1 ppm',
            'Larutan Buffer Klorin 4 ppm',
            'Larutan Buffer Klorin 8 ppm',
            'Larutan Buffer DO 0 mg/L',
            'Larutan Buffer DO 8 mg/L',
            'Larutan Buffer DO 14 mg/L',
        ]);
        $html .= self::checkGroup('Pengamanan Contoh', [
            'Identifikasi Contoh',
            'Segel Wadah',
            'Box Khusus Sampel',
            'Tindakan Pencegahan Selama Transportasi ke laboratorium',
        ]);
        $html .= '</table>';

        return $html;
    }

    private static function peralatanDigunakanBlock($table)
    {
        $html = '<table ' . $table . '><tr><td colspan="3" class="custom4">INFORMASI PERENCANAAN PERALATAN YANG DIGUNAKAN</td></tr>';
        $html .= self::checkGroup('Parameter In Situ (Lapangan)', [
            'pH',
            'Suhu',
            'Daya Hantar Listrik (DHL)',
            'Oksigen Terlarut (DO)',
            'Pengamatan/Observasi Lapangan',
            'Debit',
            'Klorin (Cl2)',
            'Kecerahan',
        ]);
        $html .= self::checkGroup('K3 / ALAT PELINDUNG DIRI', [
            'Baju Lapangan',
            'Masker',
            'Sepatu Boot Panjang',
            'Helm Keselamatan',
            'Tali Keselamatan',
            'Kacamata Pengaman',
            'Pelampung Keselamatan',
            'P3K',
            'Sepatu Pengaman',
        ]);
        $html .= '</table>';

        return $html;
    }

    private static function checkGroup($label, $items)
    {
        $count = count($items);
        $html = '';
        foreach ($items as $i => $name) {
            $html .= '<tr>';
            if ($i === 0) {
                $html .= '<td width="28%" rowspan="' . $count . '" class="custom1 custom6" style="text-align: center;">' . self::e($label) . '</td>';
            }
            $html .= '<td class="custom6" style="text-align: center;">' . self::e($name) . '</td><td width="18%" class="custom6"></td></tr>';
        }

        return $html;
    }

    private static function parafTable($petugas, $table)
    {
        $rows = array_values($petugas);
        while (count($rows) < 4) {
            $rows[] = ['nama' => ''];
        }

        $html = '
            <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; table-layout: fixed;">
                <tr>
                    <th width="8%" class="custom4">No</th>
                    <th width="46%" class="custom4">Nama Petugas Pengambil Contoh</th>
                    <th width="46%" class="custom4">Paraf Digital</th>
                </tr>
        ';
        foreach ($rows as $i => $row) {
            $html .= '
                <tr>
                    <td class="custom6" style="text-align: center;">' . ($i + 1) . '</td>
                    <td class="custom7" style="text-align: center; height: 22px;">' . self::e($row['nama'] ?? '', true) . '</td>
                    <td class="custom4" style="height: 22px;"></td>
                </tr>
            ';
        }
        $html .= '</table>';

        return $html;
    }

    private static function opsSupplement($data, $table)
    {
        $qsTambahan = self::listItems($data['qs_tambahan'] ?? []);
        $qsLain = self::listItems($data['qs_keterangan_lain'] ?? []);
        $ketOps = trim((string) ($data['keterangan_operasional'] ?? ''));
        $ketOps = $ketOps !== ''
            ? strip_tags($ketOps, '<p><br><br/><b><strong><i><em><ul><ol><li><span><div>')
            : '-';

        return '
            <table ' . $table . '>
                <tr><td colspan="2" class="custom4">INFORMASI OPERASIONAL (DATA SISTEM)</td></tr>
                <tr><td width="32%" class="custom1 custom6">Nomor Plat Mobil</td><td class="custom6">' . self::e($data['kendaraan'] ?? '-') . '</td></tr>
                <tr><td class="custom1 custom6">Nama Petugas Sampling</td><td class="custom6">' . self::e($data['sampler'] ?? '-') . '</td></tr>
                <tr><td class="custom1 custom6">Jam Berangkat</td><td class="custom6">' . self::e($data['waktu_berangkat'] ?? '-') . '</td></tr>
                <tr><td class="custom1 custom6">Jam Mulai Sampling</td><td class="custom6">' . self::e($data['jam_mulai'] ?? '-') . '</td></tr>
                <tr><td class="custom1 custom6">Wilayah</td><td class="custom6">' . self::e($data['wilayah'] ?? '-') . '</td></tr>
                <tr><td class="custom1 custom6">Nomor PIC</td><td class="custom6">' . self::e($data['pic_display'] ?? '-') . '</td></tr>
                <tr><td class="custom1 custom6">Keterangan QS</td><td class="custom6">' . $qsTambahan . ($qsLain !== '-' ? '<br>' . $qsLain : '') . '</td></tr>
                <tr><td class="custom1 custom6">Keterangan Operasional</td><td class="custom6">' . $ketOps . '</td></tr>
                <tr><td class="custom1 custom6">Nama Petugas Sampling Plan</td><td class="custom6">' . self::e($data['petugas_sampling_plan'] ?? '-') . '</td></tr>
                <tr><td class="custom1 custom6">Tanggal dan Jam Validasi SP</td><td class="custom6">' . self::e($data['validasi_at'] ?? '-') . '</td></tr>
            </table>
        ';
    }

    private static function matchAlat($templateName, $peralatan)
    {
        $target = self::norm($templateName);
        if ($target === '') {
            return null;
        }

        foreach ($peralatan as $item) {
            $name = self::norm($item['nama_alat'] ?? '');
            if ($name === '') {
                continue;
            }
            if ($name === $target || strpos($target, $name) !== false || strpos($name, $target) !== false) {
                return $item;
            }
        }

        return null;
    }

    private static function norm($value)
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($value));
    }

    private static function listItems($items)
    {
        $clean = array_values(array_filter(array_map('trim', $items)));
        if (!$clean) {
            return '-';
        }

        return implode(', ', array_map(function ($item) {
            return htmlspecialchars($item, ENT_QUOTES, 'UTF-8');
        }, $clean));
    }

    private static function e($value, $allowEmpty = false)
    {
        if ($value === null || $value === '') {
            return $allowEmpty ? '' : '-';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
