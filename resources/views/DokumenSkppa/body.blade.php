@php
    use Carbon\Carbon;
    \Carbon\Carbon::setLocale('id');

    function formatTanggalRange($awal, $akhir = null)
    {
        if (empty($awal)) {
            return '-';
        }

        return !empty($akhir)
            ? Carbon::parse($awal)->translatedFormat('d F Y') . ' - ' . Carbon::parse($akhir)->translatedFormat('d F Y')
            : Carbon::parse($awal)->translatedFormat('d F Y');
    }

    function formatPeriode($periode)
    {
        if (empty($periode)) {
            return '-';
        }

        try {
            return Carbon::createFromFormat('Y-m', $periode)->translatedFormat('F Y');
        } catch (\Throwable $th) {
            return $periode;
        }
    }

    $details = [];

    $details[] = [
        'label' => 'ID Pelanggan',
        'value' => $data->id_pelanggan ?? '-',
    ];

    $details[] = [
        'label' => 'Nama Perusahaan',
        'value' => $data->nama_perusahaan ?? '-',
    ];

    $details[] = [
        'label' => 'Alamat Sampling',
        'value' => $data->alamat_sampling ?? '-',
    ];

    $details[] = [
        'label' => 'No. Penawaran (Quotation)',
        'value' => $data->no_quotation ?? '-',
    ];

    $details[] = [
        'label' => 'Tanggal Penawaran',
        'value' => formatTanggalRange($data->tanggal_penawaran),
    ];

    $details[] = [
        'label' => 'No. Order',
        'value' => $data->no_order ?? '-',
    ];

    $details[] = [
        'label' => 'Tanggal Order',
        'value' => formatTanggalRange($data->tanggal_order),
    ];

    // Kalau kontrak → tambah periode di bawah PO
    if (!empty($data->periode)) {
        $details[] = [
            'label' => 'Periode',
            'value' => formatPeriode($data->periode),
        ];
    }

    $details[] = [
        'label' => 'No. PO Pelanggan',
        'value' => $data->no_po ?? '-',
    ];

    $details[] = [
        'label' => 'Total Sampel Analisa',
        'value' => $data->total_sampel . ' Sampel' ?? '-',
    ];

    $details[] = [
        'label' => 'Total Laporan Hasil Pengujian',
        'value' => $data->total_lhp . ' Dokumen' ?? '-',
    ];

    // $details[] = [
    //     'label' => 'Tanggal Sampling',
    //     'value' => formatTanggalRange($data->tanggal_sampling),
    // ];

    $kategori = json_decode($data->kategori, true);
    if (!in_array('SD', $kategori) && !in_array('SP', $kategori))
    {
        $details[] = [
            'label' => 'Tanggal Sampling',
            'value' => formatTanggalRange($data->tanggal_sampling_awal),
        ];
    }

    $details[] = [
        'label' => 'Tanggal Sampel Diterima',
        'value' => formatTanggalRange($data->tanggal_sampel_diterima_awal),
    ];
    // if (!empty($data->tanggal_sampel_diterima_awal)) {
    // } else {
    // }

    $details[] = [
        'label' => 'Tanggal Penyelesaian Analisa',
        'value' => formatTanggalRange($data->tanggal_penyelesaian_analisa_akhir),
    ];
@endphp

@php
    $subkategori = json_decode($data->subkategori ?? '[]', true);

    $subkategoriChunks = [];

    if (count($subkategori) > 4) {
        $left = [];
        $right = [];

        foreach (array_values($subkategori) as $i => $item) {
            if ($i % 2 == 0) {
                $left[] = $item;
            } else {
                $right[] = $item;
            }
        }

        $max = max(count($left), count($right));

        for ($i = 0; $i < $max; $i++) {
            $subkategoriChunks[] = [$left[$i] ?? null, $right[$i] ?? null];
        }
    }
@endphp

<p style="margin:0; font-size:14px;">
    Perihal : <b>Penyelesaian Pekerjaan Analisa</b>
</p>

<p style="margin-top:18px; font-size:14px;">
    Dengan ini perusahaan menerangkan rincian pekerjaan sebagai berikut :
</p>

<table style="width:100%; font-size:14px; line-height:1.2;">
    @foreach ($details as $index => $item)
        <tr>
            <td style="width:4%; vertical-align:top;">{{ chr(97 + $index) }}.</td>
            <td style="width:34%; vertical-align:top;">{{ $item['label'] }}</td>
            <td style="width:2%; vertical-align:top;">:</td>
            <td style="vertical-align:top;">{{ $item['value'] }}</td>
        </tr>

        @if ($item['label'] == 'Total Sampel Analisa')
            <tr>
                <td style="vertical-align:top;"></td>
                <td style="vertical-align:top;"></td>
                <td style="vertical-align:top;"></td>
                <td>
                    @if (count($subkategori) <= 4)
                        <table style="width:100%; border-collapse:collapse;">
                            @foreach ($subkategori as $itemKategori)
                                <tr>
                                    <td style="padding:1px 0; font-size: 12px">
                                        - {{ explode('-', $itemKategori['kategori'])[1] }} : {{ $itemKategori['jumlah'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    @else
                        <table style="width:100%; border-collapse:collapse;">
                            @foreach ($subkategoriChunks as $row)
                                <tr>
                                    @foreach ($row as $itemKategori)
                                        @if ($itemKategori)
                                            <td style="padding:1px 0; font-size: 12px">
                                                - {{ explode('-', $itemKategori['kategori'])[1] }} : {{ $itemKategori['jumlah'] }}
                                            </td>
                                        @else
                                            <td colspan="3"></td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </td>
            </tr>
        @endif
    @endforeach
</table>

<p style="margin-top:15px; font-size:14px; text-align:justify;">
    Bahwa perihal pekerjaan analisa lingkungan tersebut telah dilaksanakan dan diselesaikan dengan baik,
    sesuai dengan permintaan pihak pelanggan, serta sesuai dengan kesepakatan dan telah diperiksa
    dan disetujui oleh kedua belah pihak.
</p>

<p style="font-size:14px; text-align:justify;">
    Demikian surat keterangan ini dibuat agar dapat dipergunakan sebagaimana mestinya.
</p>

<div style="margin-top:20px; font-size:14px;">

    <table style="font-size:14px; border-collapse:collapse;">
        <tr>
            <td style="padding:0; text-align:left;">Tangerang, {{ formatTanggalRange($data->tanggal_penyelesaian_analisa_akhir) }}<br />PT Inti Surya Laboratorium</td>
        </tr>
        <tr>
            <td style="padding:18px 0 0 0; text-align:center;">
                <img src="{{ $qr }}" width="80px" height="80px">
            </td>
        </tr>
    </table>
</div>
