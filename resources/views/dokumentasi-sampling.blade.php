@php
    use Carbon\Carbon;
    Carbon::setLocale('id');
@endphp

<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Dokumentasi Kegiatan Sampling {{ $data->no_document }}</title>
    <style>
        body {
            font-family: 'dejavu sans', sans-serif;
            font-size: 11px;
        }

        .header-table,
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .header-table td,
        .info-table td {
            padding: 2px;
            vertical-align: top;
        }

        .no-border td {
            border: none;
        }

        .text-center {
            text-align: center;
        }

        .title {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="title">DOKUMENTASI KEGIATAN SAMPLING</div>

    <table class="header-table no-border">
        <tr>
            <td style="width: 70%;"><b>No. Dokumen: {{ $data->no_document }}</b></td>
            <td style="width: 30%; text-align: center; border: 1px solid black; padding: 5px;"><b>No. Order: {{ $data->no_order }}</b></td>
        </tr>
    </table>

    <table class="info-table no-border">
        <tr>
            <td colspan="3">Kegiatan dilaksanakan untuk pihak pelanggan dengan rincian sebagai berikut:</td>
        </tr>
        <tr>
            <td style="width: 20%;">Nama Perusahaan</td>
            <td style="width: 2%;">:</td>
            <td style="width: 78%;">{{ $data->nama_perusahaan }}</td>
        </tr>
        <tr>
            <td style="width: 20%; vertical-align: top;">Alamat</td>
            <td style="width: 2%;">:</td>
            <td style="width: 78%; vertical-align: top;">{{ $data->alamat_sampling }}</td>
        </tr>
    </table>

    <p>Sesuai dengan permintaan pihak pelanggan melalui dokumen ini, bahwa PT Inti Surya Laboratorium telah melakukan kegiatan pengambilan sampel / contoh uji (sampling) yang dibuktikan dengan dokumentasi kegiatan sampling berikut ini:</p>

    <table class="info-table no-border">
        @php $no = 1; @endphp
        @foreach ($detail as $item)
            @foreach ($item->any_data_lapangan as $dataLapangan)
                <tr>
                    <td style="width: 30px;">{{ $no++ }}.</td>
                    <td style="width: 150px;">No. Sampel</td>
                    <td>{{ $item->no_sampel }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td>Sub Kategori</td>
                    <td>{{ explode('-', $item->kategori_3)[1] }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td>Penamaan Titik</td>
                    <td>{{ $item->keterangan_1 }}</td>
                </tr>
                @if (optional($dataLapangan)->parameter)
                    <tr>
                        <td></td>
                        <td>Parameter Uji</td>
                        <td>{{ $dataLapangan->parameter }}</td>
                    </tr>
                @endif
                @if (optional($dataLapangan)->shift_pengambilan)
                    <tr>
                        <td></td>
                        <td>Shift Sampling</td>
                        <td>{{ $dataLapangan->shift_pengambilan }}</td>
                    </tr>
                @endif
                @php
                    $fotos = [];
                    if (optional($dataLapangan)->webp_path_lokasi && file_exists(public_path($dataLapangan->webp_path_lokasi))) {
                        $fotos[] = ['path' => public_path($dataLapangan->webp_path_lokasi), 'label' => 'Kegiatan Sampling'];
                    }

                    if (optional($dataLapangan)->webp_path_kondisi && file_exists(public_path($dataLapangan->webp_path_kondisi))) {
                        $fotos[] = ['path' => public_path($dataLapangan->webp_path_kondisi), 'label' => 'Kondisi Sampel'];
                    }

                    if (optional($dataLapangan)->webp_path_lainnya && file_exists(public_path($dataLapangan->webp_path_lainnya))) {
                        $fotos[] = ['path' => public_path($dataLapangan->webp_path_lainnya), 'label' => 'Foto Lainnya'];
                    }

                    $colWidth = count($fotos) > 0 ? 100 / count($fotos) : 100;
                @endphp

                <tr>
                    <td></td>
                    <td colspan="2">
                        <table width="100%" border="0" cellpadding="5">
                            <tr>
                                @forelse ($fotos as $foto)
                                    <td width="{{ $colWidth }}%" align="center">
                                        <img src="{{ $foto['path'] }}" alt="{{ $foto['label'] }}" style="width: auto; height: 500px;">
                                        <div>{{ $foto['label'] }}</div>
                                    </td>
                                @empty
                                    <td width="100%" align="center">
                                        <div style="width: 100%; height: 500px; border: 1px solid #ccc; display: table;">
                                            <span style="display: table-cell; vertical-align: middle;">Dokumentasi Tidak Tersedia</span>
                                        </div>
                                    </td>
                                @endforelse
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" style="padding-bottom:20px;"></td>
                </tr>
            @endforeach
        @endforeach
    </table>
</body>

</html>
