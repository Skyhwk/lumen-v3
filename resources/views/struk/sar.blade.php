@php
    use Carbon\Carbon;
    Carbon::setLocale('id');

    $groupedBySampel = $data->detail->groupBy('nomor_sampel');

    $nilaiRujukan = 5; // ntar ganti ae klo dah ada
@endphp

<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #000;
        }

        .center {
            text-align: center;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 2px 0;
            vertical-align: top;
        }

        .bold {
            font-weight: bold;
        }

        .total {
            font-size: 11px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="center">
        <img src="{{ public_path('isl_logo.png') }}" width="120">

        <div class="bold" style="font-size: 12px; margin-top: 8px; margin-bottom: 6px;">
            PT. INTI SURYA LABORATORIUM
        </div>

        <div style="font-size: 8px; margin-bottom: 6px;">
            Ruko Icon Business Park Blok O No.5 - 6 BSD City<br />Jl. BSD Raya Utama, Cisauk, Sampora, Kab. Tangerang
            15341
        </div>
    </div>

    <div class="divider"></div>

    <table style="margin-top: 6px">
        <tr>
            <td width="35%">No. Order</td>
            <td width="5%">:</td>
            <td width="60%">
                {{ $data->no_order }}
            </td>
        </tr>

        <tr>
            <td width="35%">No. Quotation</td>
            <td width="5%">:</td>
            <td width="60%">
                {{ $data->no_quotation }}
            </td>
        </tr>

        <tr>
            <td>Nama Pelanggan</td>
            <td>:</td>
            <td>
                {{ $data->nama_pelanggan }}
            </td>
        </tr>

        <tr>
            <td>E-Mail Pelanggan</td>
            <td>:</td>
            <td>
                {{ $data->email_pelanggan }}
            </td>
        </tr>

        <tr>
            <td>No. Telp Pelanggan</td>
            <td>:</td>
            <td>
                {{ $data->no_telpon }}
            </td>
        </tr>

        <tr>
            <td>Alamat Pelanggan</td>
            <td>:</td>
            <td>
                {{ $data->alamat_pelanggan }}
            </td>
        </tr>

        <tr>
            <td>Jumlah Sampel</td>
            <td>:</td>
            <td>
                {{ $data->detail->groupBy('nomor_sampel')->count() }}
            </td>
        </tr>
    </table>

    @foreach ($groupedBySampel as $nomorSampel => $sampels)
        <div class="divider" style="margin-top: 15px;"></div>

        <table>
            <thead>
                <tr>
                    <td class="bold" width="35%">{{ $nomorSampel }}</td>
                    <td class="bold" width="5%">:</td>
                    <td class="bold">{{ $sampels->first()->lokasi_pengambilan_sampel ?: '-' }}</td>
                </tr>
            </thead>
        </table>

        <div class="divider"></div>

        <table>
            <thead>
                <tr>
                    <td width="50%" class="bold">Parameter</td>
                    <td width="25%" class="bold center">Hasil Uji</td>
                    <td width="25%" class="bold center">Nilai Rujukan</td>
                </tr>
            </thead>

            <tbody>
                @foreach ($sampels as $sampel)
                    <tr>
                        <td>
                            {{ $sampel->parameter }}
                        </td>

                        <td class="center">
                            {{ $sampel->hasil_uji }}
                                @if ($sampel->hasil_uji > $nilaiRujukan)
                                    ↗
                                @else 
                                    ↙
                                @endif
                        </td>

                        <td class="center">
                            {{ $nilaiRujukan }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="divider"></div>

    <table width="100%" style="font-size: 8px;">
        <tr>
            <td><b>KETERANGAN</b></td>
        </tr>
        <tr>
            <td>
                <b>↗</b> = HASIL UJI melebihi nilai rujukan<br>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <table width="100%">
        <tr>
            <td width="35%">Tanggal</td>
            <td width="5%">:</td>
            <td>{{ Carbon::parse($data->waktu_selesai_sampling)->translatedFormat('l, d F Y : H:i') }}</td>
        </tr>
        <tr>
            <td width="35%">Petugas</td>
            <td width="5%">:</td>
            <td>{{ $sampels->first()->created_by ?: '-' }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <table width="100%">
        <tr>
            <td align="center">Terima kasih telah melakukan pengujian di<br />PT. Inti Surya Laboratorium<br /><br />T: 021-5088-9889 / sales@intilab.com</td>
        </tr>
    </table>
</body>

</html>
