@php
    $table = 'width="100%" class="io-section" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;"';
@endphp

<div class="custom1" style="margin-bottom: 6px;">UNTUK PILIHAN {{ $warna !== '' ? $warna : '-' }}</div>
<table {!! $table !!}>
    <tr>
        <td width="22%" class="custom1 custom5" style="text-align: center;">PT INTI SURYA LABORATORIUM</td>
        <td width="50%" class="custom2 custom5">{{ $judul }}</td>
        <td width="12%" class="custom3 custom5">NO.<br>ORDER</td>
        <td width="16%" class="custom4" style="font-size: 14px;">{{ $noOrder }}</td>
    </tr>
    <tr>
        <td class="custom1 custom6">NAMA PELANGGAN</td>
        <td colspan="3" class="custom6" style="text-align: center; font-weight: bold;">{{ $namaPerusahaan }}</td>
    </tr>
    <tr>
        <td class="custom1 custom6">RENCANA TANGGAL SAMPLING</td>
        <td colspan="3" class="custom6" style="text-align: center; font-weight: bold;">{{ $tanggalLabel }}</td>
    </tr>
    <tr>
        <td class="custom1 custom6">{{ $jumlahLabel }}</td>
        <td colspan="3" class="custom6" style="text-align: center; font-weight: bold;">{{ $jumlah > 0 ? $jumlah . ' SAMPEL' : '-' }}</td>
    </tr>
</table>

@if ($sampleTwoCols)
    <table {!! $table !!}>
        <tr>
            <th width="6%" class="custom4">NO.</th>
            <th width="22%" class="custom4">NO. {{ $sampelNoun }}</th>
            <th width="22%" class="custom4">KATEGORI SAMPEL</th>
            <th width="6%" class="custom4">NO.</th>
            <th width="22%" class="custom4">NO. {{ $sampelNoun }}</th>
            <th width="22%" class="custom4">KATEGORI SAMPEL</th>
        </tr>
        @foreach ($sampleRows as $row)
            <tr>
                <td class="custom6" style="text-align: center;">{{ $row['left_no'] }}</td>
                <td class="custom6" style="text-align: center; font-weight: bold;">{{ $row['left']['no_sampel'] ?? '' }}</td>
                <td class="custom6" style="text-align: center; font-weight: bold;">{{ $row['left']['kategori'] ?? '' }}</td>
                <td class="custom6" style="text-align: center;">{{ $row['right_no'] }}</td>
                <td class="custom6" style="text-align: center; font-weight: bold;">{{ $row['right']['no_sampel'] ?? '' }}</td>
                <td class="custom6" style="text-align: center; font-weight: bold;">{{ $row['right']['kategori'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@else
    <table {!! $table !!}>
        <tr>
            <th width="8%" class="custom4">NO.</th>
            <th width="46%" class="custom4">NO. {{ $sampelNoun }}</th>
            <th width="46%" class="custom4">KATEGORI SAMPEL</th>
        </tr>
        @forelse ($samples as $i => $row)
            <tr>
                <td class="custom6" style="text-align: center;">{{ $i + 1 }}</td>
                <td class="custom6" style="text-align: center; font-weight: bold;">{{ $row['no_sampel'] ?? '' }}</td>
                <td class="custom6" style="text-align: center; font-weight: bold;">{{ $row['kategori'] ?? '' }}</td>
            </tr>
        @empty
            <tr>
                <td class="custom6" style="text-align: center;">1</td>
                <td class="custom6"></td>
                <td class="custom6"></td>
            </tr>
        @endforelse
    </table>
@endif

<div style="height: 12px; line-height: 12px; font-size: 1px;">&nbsp;</div>

<table {!! $table !!}>
    <tr>
        <td colspan="4" class="custom4">DETAIL PERENCANAAN KEGIATAN SAMPLING</td>
    </tr>
    <tr>
        <th width="8%" class="custom4">No</th>
        <th width="34%" class="custom4">Nama Petugas Pengambil Contoh</th>
        <th width="29%" class="custom4">Jabatan</th>
        <th width="29%" class="custom4">Kompetensi</th>
    </tr>
    @foreach ($petugas as $i => $row)
        <tr>
            <td class="custom6" style="text-align: center;">{{ $i + 1 }}</td>
            <td class="custom6" style="text-align: center; font-weight: bold;">{{ $row['nama'] ?? '' }}</td>
            <td class="custom6" style="text-align: center; font-weight: bold;">{{ $row['jabatan'] ?? '' }}</td>
            <td class="custom6" style="text-align: center; font-weight: bold;">{{ $row['kompetensi'] ?? '' }}</td>
        </tr>
    @endforeach
</table>

<div style="height: 12px; line-height: 12px; font-size: 1px;">&nbsp;</div>

<table {!! $table !!}>
    <tr>
        <td width="8%" class="custom4">1</td>
        <td colspan="3" class="custom4">JENIS SAMPEL KE-1</td>
        <td width="10%" class="custom4">Verifikasi</td>
    </tr>
    <tr>
        <td colspan="2" class="custom1 custom6">Tujuan Pengambilan Sampel</td>
        <td colspan="2" class="custom6" style="text-align: center; font-weight: bold;">{{ $tujuanSampling }}</td>
        <td class="custom6"></td>
    </tr>
    <tr>
        <th width="18%" class="custom4"></th>
        <th class="custom4"></th>
        <th width="18%" class="custom4">Nomor Alat</th>
        <th width="10%" class="custom4">Jumlah</th>
        <th width="10%" class="custom4"></th>
    </tr>

    @foreach ($alatGroups as $group)
        @include('pdf.informasi-operasional.partials.alat-group', [
            'label' => $group['label'],
            'rows' => $group['rows'],
        ])
    @endforeach

    @include('pdf.informasi-operasional.partials.static-qty-group', [
        'label' => 'Dokumen Pendukung',
        'rows' => [
            ['nama' => 'Surat Tugas Pengambilan Sampel', 'jumlah' => '1'],
            ['nama' => 'Berita Acara Sampling', 'jumlah' => '1'],
            ['nama' => 'Jobs Safety Analisis', 'jumlah' => '1'],
            ['nama' => 'Form Perencanaan Sampling', 'jumlah' => '1'],
            ['nama' => 'Form Pengambilan Sampel', 'jumlah' => (string) $jumlah],
            ['nama' => 'Chain Of Custody', 'jumlah' => (string) $jumlah],
        ],
    ])

    @include('pdf.informasi-operasional.partials.static-qty-group', [
        'label' => 'Jenis Bahan Wadah<br>(Mengikuti Rekap Persiapan Sampling)',
        'rows' => [
            ['nama' => 'Botol Polyethylene (PE) 500 mL', 'jumlah' => ''],
            ['nama' => 'Botol Polyethylene (PE) 1000 mL', 'jumlah' => ''],
            ['nama' => 'Botol Winkler 300 mL', 'jumlah' => ''],
            ['nama' => 'Botol Glass 100 mL', 'jumlah' => ''],
            ['nama' => 'Botol Amber Glass 250 mL', 'jumlah' => ''],
            ['nama' => 'Botol Glass Steril 100 mL', 'jumlah' => ''],
            ['nama' => 'Botol Amber Glass 250 mL (Steril)', 'jumlah' => ''],
            ['nama' => 'Botol Glass Mulut Lebar 1000 mL', 'jumlah' => ''],
            ['nama' => 'Botol Steril', 'jumlah' => ''],
        ],
    ])

    @include('pdf.informasi-operasional.partials.static-qty-group', [
        'label' => 'Penanganan Wadah Contoh<br>(Mengikuti Rekap Persiapan Sampling)',
        'rows' => [
            ['nama' => 'Dibilas dengan 1:1 HNO3', 'jumlah' => ''],
            ['nama' => 'Dibilas dengan 1:1 HCl', 'jumlah' => ''],
        ],
    ])
</table>

<div style="height: 12px; line-height: 12px; font-size: 1px;">&nbsp;</div>

<table {!! $table !!}>
    <tr><td colspan="3" class="custom4">INFORMASI PERENCANAAN KEGIATAN SAMPLING</td></tr>
    <tr>
        <td width="6%" class="custom6" style="text-align: center;">a</td>
        <td width="36%" class="custom6">Frekuensi Pengambilan (Kali)</td>
        <td class="custom6" style="text-align: center; font-weight: bold;">1</td>
    </tr>
    <tr>
        <td class="custom6" style="text-align: center;">b</td>
        <td class="custom6">Waktu Pengambilan (Menit)</td>
        <td class="custom6" style="text-align: center; font-weight: bold;">10</td>
    </tr>
    <tr>
        <td class="custom6" style="text-align: center;">c</td>
        <td class="custom6">Dokumentasi Lokasi</td>
        <td class="custom6" style="text-align: center; font-weight: bold;">Dengan Foto</td>
    </tr>
    <tr>
        <td class="custom6" style="text-align: center;">d</td>
        <td class="custom6">Dokumentasi Titik Sampling</td>
        <td class="custom6" style="text-align: center; font-weight: bold;">Dengan Foto</td>
    </tr>
    <tr>
        <td class="custom6" style="text-align: center;">e</td>
        <td class="custom6">Acuan Metode Sampling Fisika dan Kimia</td>
        <td class="custom6" style="text-align: center; font-weight: bold;">SNI 8995:2021 / SNI 9063:2022</td>
    </tr>
</table>

<div style="height: 12px; line-height: 12px; font-size: 1px;">&nbsp;</div>

<table {!! $table !!}>
    <tr><td colspan="3" class="custom4">INFORMASI PERENCANAAN PROSES SAMPEL</td></tr>
    @include('pdf.informasi-operasional.partials.check-group', [
        'label' => 'Pengawetan',
        'items' => [
            'Pendinginan suhu ≤ 6°C',
            'Pendinginan suhu ≤ 10°C',
            'HNO3 Pekat',
            'H2SO4 Pekat',
            'HCl',
            'Zink Asetat',
            'Dinatrium EDTA',
            'NaOH Pekat',
            'Sodium Thiosulfat',
        ],
    ])
    @include('pdf.informasi-operasional.partials.check-group', [
        'label' => 'Teknik Pengambilan',
        'items' => [
            'Gabungan Kedalaman',
            'Gabungan Waktu',
        ],
    ])
</table>

<div style="height: 12px; line-height: 12px; font-size: 1px;">&nbsp;</div>

<table {!! $table !!}>
    <tr><td colspan="3" class="custom4">INFORMASI PERENCANAAN PENGATURAN JAMINAN TERHADAP SAMPEL</td></tr>
    @include('pdf.informasi-operasional.partials.check-group', [
        'label' => 'Pengendalian Mutu',
        'items' => [
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
        ],
    ])
    @include('pdf.informasi-operasional.partials.check-group', [
        'label' => 'Pengamanan Contoh',
        'items' => [
            'Identifikasi Contoh',
            'Segel Wadah',
            'Box Khusus Sampel',
            'Tindakan Pencegahan Selama Transportasi ke laboratorium',
        ],
    ])
</table>

<div style="height: 12px; line-height: 12px; font-size: 1px;">&nbsp;</div>

<table {!! $table !!}>
    <tr><td colspan="3" class="custom4">INFORMASI PERENCANAAN PERALATAN YANG DIGUNAKAN</td></tr>
    @include('pdf.informasi-operasional.partials.check-group', [
        'label' => 'Parameter In Situ (Lapangan)',
        'items' => [
            'pH',
            'Suhu',
            'Daya Hantar Listrik (DHL)',
            'Oksigen Terlarut (DO)',
            'Pengamatan/Observasi Lapangan',
            'Debit',
            'Klorin (Cl2)',
            'Kecerahan',
        ],
    ])
    @include('pdf.informasi-operasional.partials.check-group', [
        'label' => 'K3 / ALAT PELINDUNG DIRI',
        'items' => [
            'Baju Lapangan',
            'Masker',
            'Sepatu Boot Panjang',
            'Helm Keselamatan',
            'Tali Keselamatan',
            'Kacamata Pengaman',
            'Pelampung Keselamatan',
            'P3K',
            'Sepatu Pengaman',
        ],
    ])
</table>

<div style="height: 12px; line-height: 12px; font-size: 1px;">&nbsp;</div>

<table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; table-layout: fixed;">
    <tr>
        <th width="8%" class="custom4">No</th>
        <th width="46%" class="custom4">Nama Petugas Pengambil Contoh</th>
        <th width="46%" class="custom4">Paraf Digital</th>
    </tr>
    @foreach ($paraf as $i => $row)
        <tr>
            <td class="custom6" style="text-align: center;">{{ $i + 1 }}</td>
            <td class="custom7" style="text-align: center; height: 22px;">{{ $row['nama'] ?? '' }}</td>
            <td class="custom4" style="height: 22px;"></td>
        </tr>
    @endforeach
</table>

<div style="height: 12px; line-height: 12px; font-size: 1px;">&nbsp;</div>

<table {!! $table !!}>
    <tr><td colspan="2" class="custom4">INFORMASI OPERASIONAL (DATA SISTEM)</td></tr>
    <tr>
        <td width="32%" class="custom1 custom6">Nomor Plat Mobil</td>
        <td class="custom6">{{ $kendaraan }}</td>
    </tr>
    <tr>
        <td class="custom1 custom6">Nama Petugas Sampling</td>
        <td class="custom6">{{ $sampler }}</td>
    </tr>
    <tr>
        <td class="custom1 custom6">Jam Berangkat</td>
        <td class="custom6">{{ $waktuBerangkat }}</td>
    </tr>
    <tr>
        <td class="custom1 custom6">Jam Mulai Sampling</td>
        <td class="custom6">{{ $jamMulai }}</td>
    </tr>
    <tr>
        <td class="custom1 custom6">Wilayah</td>
        <td class="custom6">{{ $wilayah }}</td>
    </tr>
    <tr>
        <td class="custom1 custom6">Nomor PIC</td>
        <td class="custom6">{{ $picDisplay }}</td>
    </tr>
    <tr>
        <td class="custom1 custom6">Keterangan QS</td>
        <td class="custom6">
            {{ $qsTambahan }}
            @if ($qsLain !== '-')
                <br>{{ $qsLain }}
            @endif
        </td>
    </tr>
    <tr>
        <td class="custom1 custom6">Keterangan Operasional</td>
        <td class="custom6">{!! $keteranganOperasional !!}</td>
    </tr>
    <tr>
        <td class="custom1 custom6">Nama Petugas Sampling Plan</td>
        <td class="custom6">{{ $petugasSamplingPlan }}</td>
    </tr>
    <tr>
        <td class="custom1 custom6">Tanggal dan Jam Validasi SP</td>
        <td class="custom6">{{ $validasiAt }}</td>
    </tr>
</table>
