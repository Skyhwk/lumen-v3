<style>
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 12px;
        color: #333;
    }
    .header-section {
        display: flex;
        align-items: center;
    }
    .subtitle {
        margin-top: 8px;
    }
    .logo {
        height: 60px;
        margin-right: 20px;
    }
    .title-text {
        font-size: 18px;
        font-weight: bold;
        margin-top: 20px;
    }
    .section-title {
        font-size: 14px;
        font-weight: bold;
        margin-top: 30px;
        margin-bottom: 10px;
        border-bottom: 1px solid #ccc;
        padding-bottom: 4px;
    }
    .info-table, .detail-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }
    .info-table td {
        padding: 4px 8px;
    }
    .detail-table th, .detail-table td {
        border: 1px solid #ccc;
        padding: 6px 8px;
        text-align: left;
    }
    .detail-table th {
        background-color: #f5f5f5;
    }
    .status-add {
        background-color: #d4edda;
    }
    .status-sub {
        background-color: #f8d7da;
    }
    .footer {
        margin-top: 40px;
        font-size: 12px;
    }
</style>

<div class="header-section">
    <div class="title-text">Informasi Perubahan Sampel</div>
</div>

<p class="subtitle">Berikut ini adalah catatan perubahan sampel pada beberapa order yang tercantum:</p>

<div class="section-title">ORDER #{{ $orders->order_header['no_order'] }}</div>

<table class="info-table">
    <tr>
        <td width="25%"><strong>Nama Perusahaan</strong></td>
        <td>: {{ $orders->order_header['nama_perusahaan'] }}</td>
    </tr>
    <tr>
        <td width="25%"><strong>No. Order</strong></td>
        <td>: {{ $orders->order_header['no_order'] }}</td>
    </tr>
    <tr>
        <td width="25%"><strong>Tanggal Order</strong></td>
        <td>:
            @php
                \Carbon\Carbon::setLocale('id');
                echo \Carbon\Carbon::parse($orders->order_header['tanggal_order'])->translatedFormat('d F Y');
            @endphp
        </td>
    </tr>
</table>

<table class="detail-table">
    <thead>
        <tr>
            <th>No</th>
            <th>No. Sampel</th>
            <th width="13%">Kategori 1</th>
            <th width="13%">Kategori 2</th>
            <th>Regulasi</th>
            <th>Parameter</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($orders->order_header['order_detail'] as $orderDetail)
            <tr class="{{ $orderDetail['keterangan'] === 'add' ? 'status-add' : ($orderDetail['keterangan'] === 'sub' ? 'status-sub' : '') }}">
                <td>{{ $loop->iteration }}</td>
                <td>{{ $orderDetail['no_sampel'] }}</td>
                <td>
                    @php
                        if ($orderDetail['kategori_1'] == 'S') echo 'Sampling';
                        if ($orderDetail['kategori_1'] == 'SD') echo 'Sample diantar';
                        if ($orderDetail['kategori_1'] == 'S24') echo 'Sampling 24Jam';
                        if ($orderDetail['kategori_1'] == 'RS') echo 'Re-Sampling';
                    @endphp
                </td>
                <td>{{ explode('-', $orderDetail['kategori_2'])[1] }}</td>
                @php
                    $regulasi = "";
                    $parameter = "";
                    if ($orderDetail['regulasi'] && $orderDetail['regulasi'] !== "") {
                        $regulasi = implode(', ', array_map(fn($r) => str_contains($r, '-') ? explode('-', $r)[1] : $r, json_decode($orderDetail['regulasi']) ?: []));
                    }

                    if ($orderDetail['parameter'] && $orderDetail['parameter'] !== "") {
                        $parameter = implode(', ', array_map(fn($p) => str_contains($p, ';') ? explode(';', $p)[1] : $p, json_decode($orderDetail['parameter']) ?: []));
                    }
                @endphp
                <td>{{ $regulasi }}</td>
                <td>{{ $parameter }}</td>
                <td>{{ $orderDetail['keterangan_1'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p><strong>Catatan:</strong></p>
<ul>
    <li style="margin-bottom: 8px;"><span style="background-color: #d4edda; padding: 2px 6px; border-radius: 4px;">Hijau</span>: Sampel ditambahkan (Add) otomatis oleh sistem.</li>
    <li style="margin-bottom: 8px;"><span style="background-color: #f8d7da; padding: 2px 6px; border-radius: 4px;">Merah</span>: Sampel diganti/dinonaktifkan (Sub) otomatis oleh sistem.</li>
    <li>Jika background berwarna putih, maka sampel yang ditampilkan masih utuh.</li>
</ul>

<div class="footer">
    <p>Perubahan di atas telah tercatat oleh sistem Inti Surya Laboratorium. Silakan lakukan pengecekan lebih lanjut bila diperlukan.</p>

    <p>Hormat kami,<br><strong>Inti Surya Laboratorium</strong></p>
</div>
