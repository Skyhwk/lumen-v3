<!DOCTYPE html>
<html>

<head>
    <title>Rekap Lembur</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 10pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 5px;
        }

        th {
            background-color: #f2f2f2;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .divisi-row {
            background-color: #ddd;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>Rekap Lembur</h2>
        @php \Carbon\Carbon::setLocale('id'); @endphp
        <p>Tanggal: {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">NIK</th>
                <th width="30%">Nama</th>
                <th width="10%">Mulai</th>
                <th width="10%">Selesai</th>
                <th width="30%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @foreach ($data as $divisi)
                <tr class="divisi-row">
                    <td colspan="6">({{ $divisi['kode_divisi'] }}) {{ $divisi['nama_divisi'] }}</td>
                </tr>
                @foreach ($divisi['detail'] as $row)
                    <tr>
                        <td style="text-align: center;">{{ $no++ }}</td>
                        <td style="text-align: center;">{{ $row['karyawan']['nik_karyawan'] ?? '-' }}</td>
                        <td>{{ $row['karyawan']['nama_lengkap'] ?? '-' }}</td>
                        <td style="text-align: center;">{{ $row['jam_mulai'] }}</td>
                        <td style="text-align: center;">{{ $row['jam_selesai'] }}</td>
                        <td>{{ $row['keterangan'] }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>

</html>
