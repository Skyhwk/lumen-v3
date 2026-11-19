@php
    $box = static function (bool $on): string {
        if ($on) {
            return '<svg width="3.2mm" height="3.2mm" viewBox="0 0 12 12" style="vertical-align:middle;"><rect x="0.7" y="0.7" width="10.6" height="10.6" fill="#ffffff" stroke="#000000" stroke-width="1.1"/><polyline points="2.3,6.2 4.9,8.9 9.7,2.7" fill="none" stroke="#000000" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }
        return '<svg width="3.2mm" height="3.2mm" viewBox="0 0 12 12" style="vertical-align:middle;"><rect x="0.7" y="0.7" width="10.6" height="10.6" fill="#ffffff" stroke="#000000" stroke-width="1.1"/></svg>';
    };
    $valOrNbsp = static function ($v): string {
        $s = trim((string) ($v ?? ''));
        return $s !== '' ? e($s) : '&nbsp;';
    };
    $tlFlags = $tindakLanjutFlags ?? [
        'internalHr' => false,
        'komiteManajemen' => false,
        'tanpaSanksi' => false,
        'denganSanksi' => false,
    ];
@endphp
<style>
    body {
        font-family: calibri, sans-serif;
        font-size: 9.5pt;
        color: #000;
        line-height: 1.22;
    }
    p { margin: 0; }
    .doc-main-title {
        text-align: center;
        font-size: 12pt;
        font-weight: bold;
        text-decoration: underline;
        margin: 0.5mm 0 2.5mm 0;
    }
    .sec-title {
        font-size: 9.8pt;
        font-weight: bold;
        margin: 2.6mm 0 1.2mm 0;
        page-break-after: avoid;
    }
    .sec-hint {
        font-size: 9pt;
        font-weight: normal;
        font-style: italic;
    }
    table.form-lines {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    table.form-lines td {
        font-size: 9.5pt;
        vertical-align: bottom;
        padding: 0.65mm 0;
    }
    td.fl-lbl {
        padding-left: 5mm;
        white-space: nowrap;
    }
    td.fl-sep {
        width: 3.5mm;
        text-align: center;
    }
    td.fl-dot {
        border-bottom: 0.25mm dotted #000;
        padding-left: 1mm;
        padding-right: 1mm;
        word-wrap: break-word;
    }
    table.grid-tbl {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    table.grid-tbl th,
    table.grid-tbl td {
        border: 0.25mm solid #000;
        font-size: 9.3pt;
        padding: 1mm 1.5mm;
        vertical-align: middle;
        word-wrap: break-word;
    }
    table.grid-tbl th {
        font-weight: bold;
        text-align: center;
        background: #ffffff;
    }
    table.inner-clean {
        width: 100%;
        border-collapse: collapse;
    }
    table.inner-clean td {
        border: none;
        padding: 0.2mm 0;
        font-size: 9.3pt;
        vertical-align: middle;
    }
    .dotted-line-row {
        border-bottom: 0.25mm dotted #000;
        height: 4.3mm;
        line-height: 4.3mm;
    }
</style>

<div class="doc-main-title">FORM INVESTIGASI AWAL</div>

<p class="sec-title">A. &nbsp;DATA KEJADIAN</p>
<table class="form-lines" style="margin-bottom:1.5mm;">
    <colgroup>
        <col style="width:47mm;">
        <col style="width:3.5mm;">
        <col style="width:46mm;">
        <col style="width:13mm;">
        <col style="width:14mm;">
        <col style="width:3.5mm;">
        <col>
    </colgroup>
    <tr>
        <td class="fl-lbl">Judul Kejadian</td>
        <td class="fl-sep">:</td>
        <td colspan="5" class="fl-dot">{!! $valOrNbsp($judulKejadian) !!}</td>
    </tr>
    <tr>
        <td class="fl-lbl">Hari / Tgl Kejadian</td>
        <td class="fl-sep">:</td>
        <td colspan="2" class="fl-dot">{!! $valOrNbsp($tanggalKejadian) !!}</td>
        <td style="padding-left:3mm;white-space:nowrap;">Pukul</td>
        <td class="fl-sep">:</td>
        <td class="fl-dot">{!! $valOrNbsp($pukulKejadian) !!}</td>
    </tr>
    <tr>
        <td class="fl-lbl">Lokasi Kejadian</td>
        <td class="fl-sep">:</td>
        <td colspan="5" class="fl-dot">{!! $valOrNbsp($lokasiKejadian) !!}</td>
    </tr>
    <tr>
        <td class="fl-lbl">Tgl BAK Pertama / Terakhir</td>
        <td class="fl-sep">:</td>
        <td class="fl-dot">{!! $valOrNbsp($tglBakPertama) !!}</td>
        <td style="text-align:center;">/</td>
        <td colspan="3" class="fl-dot">{!! $valOrNbsp($tglBakTerakhir) !!}</td>
    </tr>
    <tr>
        <td class="fl-lbl">No BAK</td>
        <td class="fl-sep">:</td>
        <td class="fl-dot">{!! $valOrNbsp($noBakPertama ?? '') !!}</td>
        <td style="text-align:center;">s/d</td>
        <td colspan="3" class="fl-dot">{!! $valOrNbsp($noBakTerakhir ?? '') !!}</td>
    </tr>
    <tr>
        <td class="fl-lbl">Jumlah Bukti (Total)</td>
        <td class="fl-sep">:</td>
        <td colspan="5" class="fl-dot">{!! $valOrNbsp($jumlahBukti) !!}</td>
    </tr>
    <tr>
        <td class="fl-lbl" style="vertical-align:top;">Jenis Bukti</td>
        <td class="fl-sep" style="vertical-align:top;">:</td>
        <td colspan="5" class="fl-dot">{!! $valOrNbsp($jenisBukti) !!}</td>
    </tr>
</table>

<p class="sec-title">B. &nbsp;PIHAK TERLIBAT</p>
<table class="grid-tbl" style="margin-bottom:1.5mm;">
    <tr>
        <th style="width:9mm;">No</th>
        <th style="width:33%;">Nama</th>
        <th style="width:24%;">Divisi</th>
        <th>
            Keterangan<br>
            <span style="font-size:8.8pt;font-weight:bold;">(Pelapor/ Terlapor/ Saksi/ Lainnya)</span>
        </th>
    </tr>
    @foreach ($pihakRows as $row)
        <tr>
            <td style="text-align:center;">{{ $row['no'] }}.</td>
            <td>{!! $valOrNbsp($row['nama']) !!}</td>
            <td>{!! $valOrNbsp($row['divisi']) !!}</td>
            <td>{!! $valOrNbsp($row['peran']) !!}</td>
        </tr>
    @endforeach
</table>

<p class="sec-title">
    C. RINGKASAN KRONOLOGI (5W + 1H)
    <span class="sec-hint">&nbsp;(Wajib diisi secara lengkap dan objektif)</span>
</p>
<table width="100%" style="border-collapse:collapse;margin:0.5mm 0 1.2mm 0;">
    <tr>
        <td style="width:2mm;border:none;"></td>
        <td style="border:none;padding:1mm 0;line-height:1.35;text-align:justify;">
            {!! $valOrNbsp($kronologiRingkasan) !!}
        </td>
    </tr>
</table>

<table class="form-lines" style="margin-bottom:1.5mm;">
    <colgroup>
        <col style="width:34mm;">
        <col style="width:3.5mm;">
        <col>
    </colgroup>
    @foreach ($kronologi as $row)
        <tr>
            <td style="padding-left:2mm;white-space:nowrap;vertical-align:top;padding-top:1mm;">
                <i>{{ $row['en'] ?? '' }}</i> {{ $row['id'] ?? $row['label'] }}
            </td>
            <td class="fl-sep" style="vertical-align:top;padding-top:1mm;">:</td>
            <td class="fl-dot" style="padding-top:1mm;">
                @if(trim((string) ($row['value'] ?? '')) !== '')
                    {{ $row['value'] }}
                @else
                    &nbsp;
                @endif
            </td>
        </tr>
    @endforeach
    <tr>
        <td style="padding-left:2mm;font-weight:bold;vertical-align:top;padding-top:1.2mm;">Catatan Khusus</td>
        <td class="fl-sep" style="vertical-align:top;padding-top:1.2mm;">:</td>
        <td class="fl-dot" style="padding-top:1.2mm;">{!! $valOrNbsp($kronologiCatatan) !!}</td>
    </tr>
    @if(trim((string) ($kronologiCatatan ?? '')) === '')
        <tr>
            <td colspan="2"></td>
            <td class="fl-dot" style="height:4mm;">&nbsp;</td>
        </tr>
    @endif
</table>

<p class="sec-title">
    D. IDENTIFIKASI PENYEBAB AWAL
    <span class="sec-hint">&nbsp;(Beri checklist &amp; keterangan pada kategori penyebab yang teridentifikasi)</span>
</p>
<table class="grid-tbl">
    <tr>
        <th style="width:28mm;">Kategori</th>
        <th>Keterangan</th>
    </tr>
    @foreach (array_slice($penyebabRows, 0, 2) as $row)
        <tr>
            <td style="padding:1mm 1.5mm;">
                <table class="inner-clean">
                    <tr>
                        <td style="width:5mm;">{!! $box(!empty($row['on'])) !!}</td>
                        <td style="font-weight:bold;">{{ $row['kode'] }}</td>
                        <td style="width:3mm;font-weight:bold;text-align:right;">:</td>
                    </tr>
                </table>
            </td>
            <td>
                <table width="100%" style="border-collapse:collapse;">
                    <tr>
                        <td style="border:none;border-bottom:0.25mm dotted #000;padding:0.2mm 0.5mm;">
                            {!! $valOrNbsp($row['note']) !!}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @endforeach
</table>

<pagebreak />

<table class="grid-tbl" style="margin-top:0.5mm;margin-bottom:2mm;">
    @foreach (array_slice($penyebabRows, 2) as $row)
        <tr>
            <td style="width:28mm;padding:1mm 1.5mm;">
                <table class="inner-clean">
                    <tr>
                        <td style="width:5mm;">{!! $box(!empty($row['on'])) !!}</td>
                        <td style="font-weight:bold;">{{ $row['kode'] }}</td>
                        <td style="width:3mm;font-weight:bold;text-align:right;">:</td>
                    </tr>
                </table>
            </td>
            <td>
                <table width="100%" style="border-collapse:collapse;">
                    <tr>
                        <td style="border:none;border-bottom:0.25mm dotted #000;padding:0.2mm 0.5mm;">
                            {!! $valOrNbsp($row['note']) !!}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @endforeach
</table>

<p class="sec-title">
    E. DAMPAK KEJADIAN
    <span class="sec-hint">&nbsp;(Beri checklist pada kategori &amp; rincian dampak yang teridentifikasi)</span>
</p>
<table class="grid-tbl" style="margin-bottom:1mm;">
    <tr>
        <th style="width:28mm;">Kategori</th>
        <th>Rincian</th>
    </tr>
    <tr>
        <td>
            <table class="inner-clean">
                <tr>
                    <td style="width:5mm;">{!! $box(!empty($dampakMaster['finOn'])) !!}</td>
                    <td style="font-weight:bold;">FIN</td>
                    <td style="width:3mm;font-weight:bold;text-align:right;">:</td>
                </tr>
            </table>
        </td>
        <td>
            <table class="inner-clean">
                <tr>
                    <td style="width:42%;">{!! $box(!empty($dampakMaster['finKerugian'])) !!}&nbsp;Kerugian Langsung</td>
                    <td style="width:34%;">{!! $box(!empty($dampakMaster['finAset'])) !!}&nbsp;Aset</td>
                    <td style="width:24%;">&nbsp;</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td>
            <table class="inner-clean">
                <tr>
                    <td style="width:5mm;">{!! $box(!empty($dampakMaster['opsOn'])) !!}</td>
                    <td style="font-weight:bold;">OPS</td>
                    <td style="width:3mm;font-weight:bold;text-align:right;">:</td>
                </tr>
            </table>
        </td>
        <td>
            <table class="inner-clean">
                <tr>
                    <td style="width:42%;">{!! $box(!empty($dampakMaster['opsGangguan'])) !!}&nbsp;Gangguan Proses Kerja</td>
                    <td style="width:34%;">{!! $box(!empty($dampakMaster['opsDelay'])) !!}&nbsp;<i>Delay</i> Layanan</td>
                    <td style="width:24%;">&nbsp;</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td>
            <table class="inner-clean">
                <tr>
                    <td style="width:5mm;">{!! $box(!empty($dampakMaster['hseOn'])) !!}</td>
                    <td style="font-weight:bold;">HSE</td>
                    <td style="width:3mm;font-weight:bold;text-align:right;">:</td>
                </tr>
            </table>
        </td>
        <td>
            <table class="inner-clean">
                <tr>
                    <td style="width:42%;">{!! $box(!empty($dampakMaster['hseNear'])) !!}&nbsp;<i>Near Miss</i></td>
                    <td style="width:34%;">{!! $box(!empty($dampakMaster['hseInjury'])) !!}&nbsp;<i>Injury</i></td>
                    <td style="width:24%;">{!! $box(!empty($dampakMaster['hseFatality'])) !!}&nbsp;<i>Fatality</i></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td>
            <table class="inner-clean">
                <tr>
                    <td style="width:5mm;">{!! $box(!empty($dampakMaster['legOn'])) !!}</td>
                    <td style="font-weight:bold;">LEG</td>
                    <td style="width:3mm;font-weight:bold;text-align:right;">:</td>
                </tr>
            </table>
        </td>
        <td>
            <table class="inner-clean">
                <tr>
                    <td style="width:42%;">{!! $box(!empty($dampakMaster['legPelanggaran'])) !!}&nbsp;Pelanggaran Peraturan</td>
                    <td style="width:34%;">{!! $box(!empty($dampakMaster['legRisiko'])) !!}&nbsp;Risiko sanksi hukum</td>
                    <td style="width:24%;">&nbsp;</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="vertical-align:top;">
            <table class="inner-clean">
                <tr>
                    <td style="width:5mm;">{!! $box(!empty($dampakMaster['lainnyaOn'])) !!}</td>
                    <td style="font-weight:bold;">Lainnya</td>
                    <td style="width:3mm;font-weight:bold;text-align:right;">:</td>
                </tr>
            </table>
        </td>
        <td>
            <table width="100%" style="border-collapse:collapse;">
                <tr>
                    <td style="border:none;border-bottom:0.25mm dotted #000;padding:0.2mm 0.5mm;">
                        {!! $valOrNbsp($dampakMaster['lainnyaNote'] ?? '') !!}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="form-lines" style="margin-bottom:1.8mm;">
    <colgroup>
        <col style="width:28mm;">
        <col style="width:3.5mm;">
        <col>
    </colgroup>
    <tr>
        <td style="padding-left:2mm;">Catatan Khusus</td>
        <td class="fl-sep">:</td>
        <td class="fl-dot">{!! $valOrNbsp($dampakCatatan) !!}</td>
    </tr>
</table>

<p class="sec-title">
    F. TINDAK LANJUT USER
    <span class="sec-hint">&nbsp;(Jika ada)</span>
</p>
<table class="grid-tbl" style="margin-bottom:2mm;">
    <tr>
        <th style="width:9mm;">No</th>
        <th><i><b>Corrective Action</b></i> <b>(Perbaikan)</b></th>
        <th><i><b>Preventive Action</b></i> <b>(Pencegahan)</b></th>
    </tr>
    @foreach ($tindakRows as $row)
        <tr>
            <td style="text-align:center;">{{ $row['no'] }}.</td>
            <td>{!! $valOrNbsp($row['corrective']) !!}</td>
            <td>{!! $valOrNbsp($row['preventive']) !!}</td>
        </tr>
    @endforeach
</table>

<p class="sec-title">G. HASIL INVESTIGASI AWAL*)</p>
<table width="100%" style="border-collapse:collapse;font-size:9.3pt;margin-bottom:1.2mm;">
    <tr>
        <td style="width:36mm;padding:0.6mm 0 0.6mm 4mm;font-weight:bold;vertical-align:top;">Tindak Lanjut Awal</td>
        <td style="width:3.5mm;text-align:center;vertical-align:top;padding:0.6mm 0;">:</td>
        <td style="width:42mm;padding:0.6mm 0;font-weight:bold;vertical-align:top;">
            {!! $box(!empty($tlFlags['internalHr'])) !!}&nbsp;Internal Disciplinary
        </td>
        <td colspan="3" style="padding:0.6mm 0;font-weight:bold;vertical-align:top;">
            {!! $box(!empty($tlFlags['komiteManajemen'])) !!}&nbsp;Komite Manajemen
        </td>
    </tr>
    <tr>
        <td colspan="2" style="padding:0;"></td>
        <td colspan="4" style="padding:0.6mm 0;font-weight:bold;vertical-align:top;">
            {!! $box(!empty($tlFlags['tanpaSanksi'])) !!}&nbsp;Tanpa Sanksi
        </td>
    </tr>
    <tr>
        <td colspan="2" style="padding:0;"></td>
        <td style="padding:0.6mm 0;font-weight:bold;vertical-align:top;">
            {!! $box(!empty($tlFlags['denganSanksi'])) !!}&nbsp;Dengan Sanksi
        </td>
        <td style="width:24mm;padding:0.5mm 0;vertical-align:bottom;white-space:nowrap;">
            {!! $box(!empty($g1['stOn'])) !!}&nbsp;ST
        </td>
        <td style="width:3.5mm;text-align:center;vertical-align:bottom;padding:0.5mm 0;">:</td>
        <td style="border-bottom:0.25mm dotted #000;padding:0.5mm 1mm;vertical-align:bottom;">
            {!! $valOrNbsp($g1['stDetail'] ?? '') !!}
        </td>
    </tr>
    <tr>
        <td colspan="3" style="padding:0;"></td>
        <td style="padding:0.5mm 0;vertical-align:bottom;white-space:nowrap;">
            {!! $box(!empty($g1['spOn'])) !!}&nbsp;SP -
        </td>
        <td style="text-align:center;vertical-align:bottom;padding:0.5mm 0;">:</td>
        <td style="border-bottom:0.25mm dotted #000;padding:0.5mm 1mm;vertical-align:bottom;">
            {!! $valOrNbsp($g1['spDetail'] ?? '') !!}
        </td>
    </tr>
    <tr>
        <td colspan="3" style="padding:0;"></td>
        <td style="padding:0.5mm 0;vertical-align:bottom;white-space:nowrap;">
            {!! $box(!empty($g1['gantiRugiOn'])) !!}&nbsp;Ganti Rugi
        </td>
        <td style="text-align:center;vertical-align:bottom;padding:0.5mm 0;">:</td>
        <td style="border-bottom:0.25mm dotted #000;padding:0.5mm 1mm;vertical-align:bottom;">
            {!! $valOrNbsp($g1['gantiRugiDetail'] ?? '') !!}
        </td>
    </tr>
    <tr>
        <td colspan="3" style="padding:0;"></td>
        <td style="padding:0.5mm 0;vertical-align:bottom;white-space:nowrap;">
            {!! $box(!empty($g1['lainnyaOn'])) !!}&nbsp;Lainnya
        </td>
        <td style="text-align:center;vertical-align:bottom;padding:0.5mm 0;">:</td>
        <td style="border-bottom:0.25mm dotted #000;padding:0.5mm 1mm;vertical-align:bottom;">
            {!! $valOrNbsp($g1['lainnyaDetail'] ?? '') !!}
        </td>
    </tr>
</table>

<table class="form-lines" style="margin-bottom:2.5mm;">
    <colgroup>
        <col style="width:39mm;">
        <col style="width:3.5mm;">
        <col>
    </colgroup>
    <tr>
        <td style="padding-left:4mm;font-weight:bold;vertical-align:top;line-height:1.15;">
            Catatan Tambahan /<br>Pertimbangan
        </td>
        <td class="fl-sep" style="vertical-align:top;">:</td>
        <td class="fl-dot" style="vertical-align:top;">{!! $valOrNbsp($g1['catatan'] ?? '') !!}</td>
    </tr>
    @if(trim((string) ($g1['catatan'] ?? '')) === '')
        <tr>
            <td colspan="2"></td>
            <td class="fl-dot" style="height:3.8mm;">&nbsp;</td>
        </tr>
    @endif
</table>

<table class="grid-tbl" style="page-break-inside:avoid;">
    <tr>
        <th style="width:8mm;">No</th>
        <th style="width:27mm;">Keterangan</th>
        <th style="width:38mm;">Nama</th>
        <th style="width:38mm;">Jabatan</th>
        <th style="width:21mm;">TGL</th>
        <th>Tanda Tangan</th>
    </tr>
    @foreach ($pengesahanRows as $row)
        <tr>
            <td style="text-align:center;height:9.5mm;">{{ $row['no'] }}</td>
            <td>{{ $row['label'] }}</td>
            <td style="font-size:8.8pt;">{!! $valOrNbsp($row['nama']) !!}</td>
            <td style="font-size:8.3pt;">{!! $valOrNbsp($row['jabatan']) !!}</td>
            <td style="text-align:center;font-size:8.8pt;">{!! $valOrNbsp($row['tanggal']) !!}</td>
            <td style="text-align:center;vertical-align:middle;font-size:7.8pt;">&nbsp;</td>
        </tr>
    @endforeach
</table>
