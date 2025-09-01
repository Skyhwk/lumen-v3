{{ Carbon\Carbon::setLocale('id') }}
<!DOCTYPE html>
<html>

<head>
    <title>Laporan Hasil Pengujian</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px;
        }

        .title {
            text-align: center;
            font-weight: bold;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 2px;
            width: 250px;
            margin: 0 auto;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .company {
            text-align: center;
            font-weight: bold;
            margin-bottom: 40px;
            font-size: 14px;
        }

        .address {
            text-align: center;
            width: 450px;
            margin: 0 auto;
            margin-bottom: 20px;
        }

        .date-range {
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 8px;
        }

        .sample-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .sample-table td {
            padding: 4px 10px;
            border-bottom: 1px dotted #999;
            white-space: nowrap;
        }

        .sampling {
            margin-top: 10px;
            margin-bottom: 20px;
        }

        .footer-text {
            font-size: 9px;
            text-align: center;
            margin-top: 20px;
        }

        .footer-company {
            text-align: center;
            font-size: 7px;
            margin-top: 50px;
            /* border-top: 1px solid #000; */
            padding-top: 5px;
        }

        .sampling-signature {
            width: 100%;
            margin-top: 30px;
            table-layout: fixed;
        }

        .sampling-signature td {
            vertical-align: top;
            font-size: 12px;
        }

        .sampling-cell {
            width: 80%;
        }

        .signature-cell {
            width: 20%;
            text-align: center;
        }

        .sign-name {
            font-weight: bold;
            margin-top: 50px;
            margin-bottom: 0;
        }

        .sign-position {
            font-size: 10px;
            margin-top: 0;
        }
    </style>
</head>

<body>
    <div class="title">LAPORAN HASIL PENGUJIAN</div>

    <div class="company">{{ $data->nama_perusahaan }}</div>

    <div class="address">
        @php
            $alamat = $data->alamat_sampling;
            $alamat_tanpa_spasi_liar = str_replace(' ', '&nbsp;', $alamat);
            $alamat_final = str_replace(',&nbsp;', ', ', $alamat_tanpa_spasi_liar);
        @endphp
        {!! $alamat_final !!}
    </div>

    <div class="date-range">{{ $data->periode }}</div>

    <div class="section-title">Jenis dan Jumlah Sampel</div>

    <table class="sample-table">
        @php
            $items = $data->detail;
            $perRow = 4;
            $rows = array_chunk($items, $perRow);
            $alignments = ['left', 'center', 'center', 'right'];
        @endphp

        @foreach ($rows as $row)
            <tr>
                @for ($i = 0; $i < $perRow; $i++)
                    @php
                        $val = $row[$i] ?? '';
                    @endphp
                    <td style="width: 25%; text-align: {{ $alignments[$i] }}; vertical-align: top;">
                        {!! $val !!}
                    </td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>

</html>
