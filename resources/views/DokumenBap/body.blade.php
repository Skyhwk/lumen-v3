@php
    use Carbon\Carbon;
    \Carbon\Carbon::setLocale('id');
@endphp

<!-- BODY -->
<table style="width:100%; font-size:12px; line-height:1.5;">
    <tr>
        <td style="padding-bottom:8px;">Tangerang, {{ Carbon::parse($data->tanggal_rilis)->translatedFormat('d F Y') }}</td>
    </tr>
    <tr>
        <td style="padding-bottom:8px; text-align:justify;">
            Bersama ini kami informasikan perihal pekerjaan analisa lingkungan antara pihak :
        </td>
    </tr>
</table>

<!-- PIHAK I -->
<table style="width:100%; font-size:12px; line-height:1.5; margin-top:5px;">
    <tr>
        <td style="width:5%; vertical-align:top;">I.</td>
        <td style="font-weight:bold;">LABORATORIUM PENGUJI (INTI SURYA LABORATORIUM, PT)</td>
    </tr>
</table>

<table style="width:95%; margin-left:5%; font-size:12px; line-height:1.6;">
    <tr>
        <td style="width:30%;">Nama</td>
        <td style="width:2%;">:</td>
        <td>{{ $data->nama_tim_teknis }}</td>
    </tr>
    <tr>
        <td>Jabatan</td>
        <td>:</td>
        <td>{{ $data->jabatan_tim_teknis }}</td>
    </tr>
    <tr>
        <td style="width:15%; vertical-align:top;">Alamat</td>
        <td style="width:2%; vertical-align:top;">:</td>
        <td style="
        width:83%; 
        vertical-align:top; 
        line-height:1.6; 
        text-align:justify;
        word-break: break-word;
        white-space: normal;
    ">
            Ruko Icon Business Park blok O no.5 - 6, BSD City
            Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang - 15345
        </td>
    </tr>
</table>

<!-- PIHAK II -->
<table style="width:100%; font-size:12px; line-height:1.5; margin-top:8px;">
    <tr>
        <td style="width:5%; vertical-align:top;">II.</td>
        <td style="font-weight:bold;">{{ $data->nama_perusahaan }}</td>
    </tr>
</table>

<table style="width:95%; margin-left:5%; font-size:12px; line-height:1.6;">
    @foreach($data->nama_penanggung_jawab as $index => $item)
    <tr>
        <td style="width:30%;">Nama Penanggung Jawab <span> @if(count($data->nama_penanggung_jawab) > 1) {{ $index + 1 }} @endif</span></td>
        <td style="width:2%;">:</td>
        <td>{{ $item }}</td>
    </tr>
    <tr>
        <td>Jabatan Penanggung Jawab <span> @if(count($data->nama_penanggung_jawab) > 1) {{ $index + 1 }} @endif</span></td>
        <td>:</td>
        <td>{{ $data->jabatan_penanggung_jawab[$index] }}</td>
    </tr>
    @endforeach
    <tr>
        <td style="width:15%; vertical-align:top;">Alamat</td>
        <td style="width:2%; vertical-align:top;">:</td>
        <td style="
        width:83%; 
        vertical-align:top; 
        line-height:1.6; 
        text-align:justify;
        word-break: break-word;
        white-space: normal;
    ">
            {{ $data->alamat_perusahaan }}
        </td>
    </tr>
</table>


<table style="width:100%; font-size:12px; line-height:1.5; margin-top:10px;">
    <tr>
        <td style="padding-bottom:8px; text-align:justify;">
            Dengan rincian pekerjaan sebagai berikut :
        </td>
    </tr>
</table>

<!-- DETAIL PEKERJAAN -->
<table style="width:100%; font-size:12px; line-height:1.5; ">
    <tr>
        <td style="width:3%; vertical-align:top;">a.</td>
        <td style="width:35%;">No. Penawaran (Quotation)</td>
        <td style="width:2%;">:</td>
        <td>{{$data->no_quotation ?? '-'}}</td>
    </tr>
    <tr>
        <td>b.</td>
        <td>No. PO Pelanggan</td>
        <td>:</td>
        <td>{{$data->no_po ?? '-'}}</td>
    </tr>
    <tr>
        <td>c.</td>
        <td>Tanggal Sampling</td>
        <td>:</td>
        <td>{{ !is_null($data->tanggal_sampling_akhir) ? Carbon::parse($data->tanggal_sampling_awal)->translatedFormat('d F Y') . ' - ' . Carbon::parse($data->tanggal_sampling_akhir)->translatedFormat('d F Y') : Carbon::parse($data->tanggal_sampling_awal)->translatedFormat('d F Y') }}</td>
    </tr>
    <tr>
        <td>d.</td>
        <td>Tanggal Sampel Diterima</td>
        <td>:</td>
        <td>{{ !is_null($data->tanggal_sampel_diterima_akhir) ? Carbon::parse($data->tanggal_sampel_diterima_awal)->translatedFormat('d F Y') . ' - ' . Carbon::parse($data->tanggal_sampel_diterima_akhir)->translatedFormat('d F Y') : Carbon::parse($data->tanggal_sampel_diterima_awal)->translatedFormat('d F Y') }}</td>
    </tr>
    <tr>
        <td>e.</td>
        <td>Tanggal Penyelesaian Analisa</td>
        <td>:</td>
        <td>{{ !is_null($data->tanggal_penyelesaian_analisa_akhir) ? Carbon::parse($data->tanggal_penyelesaian_analisa_awal)->translatedFormat('d F Y') . ' - ' . Carbon::parse($data->tanggal_penyelesaian_analisa_akhir)->translatedFormat('d F Y') : Carbon::parse($data->tanggal_penyelesaian_analisa_awal)->translatedFormat('d F Y') }}</td>
    </tr>
</table>

<!-- PARAGRAF PENUTUP -->
<p style="font-size:12px; line-height:1.6; text-align:justify; margin-top:10px;">
    Bahwa perihal pekerjaan analisa lingkungan tersebut telah dilaksanakan dan diselesaikan dengan baik,
    sesuai dengan permintaan pihak pelanggan, serta sesuai dengan kesepakatan dan telah diperiksa
    dan disetujui oleh kedua belah pihak.
</p>

<p style="font-size:12px; line-height:1.6; text-align:justify;">
    Demikian Berita Acara Penyelesaian ini dibuat agar dapat dipergunakan dengan sebagaimana mestinya.
</p>

<!-- TANDA TANGAN -->
<table style="width:100%; font-size:12px; margin-top:25px; text-align:center;">
    <tr>
        <td style="width:50%;">Penerima Kerja,</td>
        <td style="width:50%;">Pemberi Kerja,</td>
    </tr>
    <tr>
        <td><b>INTI SURYA LABORATORIUM, PT</b></td>
        <td><b>{{ $data->nama_perusahaan }}</b></td>
    </tr>
    <!-- spasi untuk tanda tangan -->
    <tr>
        <td style="height:80px;"></td>
        <td></td>
    </tr>
    <tr>
        <td>
            <table style="width:100%;">
                <tr>
                    <td style="width:50%;">
                        ( <b>{{$data->nama_tim_sales}}</b> )<br><i style="font-size:10px;">{{$data->jabatan_tim_sales}}</i>
                    </td style="width:50%;">
                    <td>( <b>{{$data->nama_tim_teknis}}</b> )<br><i style="font-size:10px;">{{$data->jabatan_tim_teknis}}</i></td>
                </tr>
            </table>
        </td>
        <td>
            <table style="width:100%;">
                <tr>
                    @foreach($data->nama_penanggung_jawab as $index => $item)
                        <td>( <b>{{$item}}</b> )<br><i style="font-size:10px;">{{$data->jabatan_penanggung_jawab[$index]}}</i></td>
                    @endforeach
                </tr>
            </table>
        </td>
    </tr>
</table>