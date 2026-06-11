@php
    use Carbon\Carbon;
    Carbon::setLocale('id');

    function formatTanggalSkhp($date)
    {
        if (empty($date)) {
            return '-';
        }

        return Carbon::parse($date)->translatedFormat('d F Y');
    }

    $details = [
        ['label' => 'Nama Pelanggan', 'value' => $data->nama_pelanggan ?? '-'],
        ['label' => 'No. HP', 'value' => $data->no_hp ?? '-'],
        ['label' => 'Email', 'value' => $data->email ?? '-'],
        ['label' => 'Alamat', 'value' => $data->alamat ?? '-'],
        ['label' => 'No. Order', 'value' => $data->no_order ?? '-'],
        ['label' => 'Tanggal Order', 'value' => formatTanggalSkhp($data->created_at)],
        ['label' => 'Lokasi Pengambilan', 'value' => $data->lokasi_pengambilan ?? '-']
    ];

    $tanggalSelesai = formatTanggalSkhp($data->tanggal_selesai ?? now());
@endphp

<p style="margin:0; font-size:14px;">
    Perihal : <b>Surat Keterangan Hasil Pengujian</b>
</p>

<p style="margin-top:18px; font-size:14px;">
    Dengan ini perusahaan menerangkan informasi pesanan pelanggan sebagai berikut :
</p>

<table style="width:100%; font-size:14px; line-height:1.2;">
    @foreach ($details as $index => $item)
        <tr>
            <td style="width:4%; vertical-align:top;">{{ chr(97 + $index) }}.</td>
            <td style="width:34%; vertical-align:top;">{{ $item['label'] }}</td>
            <td style="width:2%; vertical-align:top;">:</td>
            <td style="vertical-align:top;">{{ $item['value'] }}</td>
        </tr>
    @endforeach
</table>

<p style="margin-top:18px; font-size:14px;"><b>Tabel Hasil Pengujian</b></p>

<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; font-size:12px;">
    <thead>
        <tr>
            <th style="width:6%; text-align:center; border:1px solid #000; font-weight:bold; padding:5px; background-color:#f0f0f0;">
                NO
            </th>
            <th style="width:34%; text-align:center; border:1px solid #000; font-weight:bold; padding:5px; background-color:#f0f0f0;">
                PARAMETER
            </th>
            <th style="width:20%; text-align:center; border:1px solid #000; font-weight:bold; padding:5px; background-color:#f0f0f0;">
                HASIL UJI
            </th>
            <th style="width:20%; text-align:center; border:1px solid #000; font-weight:bold; padding:5px; background-color:#f0f0f0;">
                NILAI RUJUKAN
            </th>
        </tr>
    </thead>
    <tbody>
        @forelse ($hasilUji as $index => $item)
            <tr>
                <td style="border:1px solid #000; padding:5px; text-align:center;">
                    {{ $index + 1 }}
                </td>
                <td style="border:1px solid #000; padding:5px;">
                    {{ $item->parameter ?? '-' }}
                </td>
                <td style="border:1px solid #000; padding:5px; text-align:center;">
                    {{ $item->hasil_uji ?? '-' }} 
                    @if ($item->hasil_uji > optional($item->acuan)->nilai_rujukan)
                        <span style="color:red;"> ↑</span>
                    @endif
                </td>
                <td style="border:1px solid #000; padding:5px; text-align:center;">
                    {{ optional($item->acuan)->nilai_rujukan ?? '-' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" style="border:1px solid #000; padding:8px;">
                    Belum ada data hasil pengujian.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" style="border:1px solid #000; padding:5px; text-align:center;">
            ( ↑ ) bihi ambang batas nilai rujukan
            </td>
        </tr>
    </tfoot>
</table>

<p style="margin-top:15px; font-size:14px; text-align:justify;">
    Bahwa hasil pengujian SAR On-The-Spot tersebut telah dilaksanakan sesuai dengan permintaan pelanggan
    dan metode pengujian yang berlaku di PT Inti Surya Laboratorium.
</p>

<p style="font-size:14px; text-align:justify;">
    Hasil pengujian ini hanya berlaku pada tanggal pengujian yang tercantum dalam lembar hasil pengujian.
</p>

<p style="font-size:14px; text-align:justify;">
    Demikian surat keterangan hasil pengujian ini dibuat agar dapat dipergunakan sebagaimana mestinya.
</p>

<div style="margin-top:20px; font-size:14px;">
    <table style="font-size:14px; border-collapse:collapse;">
        <tr>
            <td style="padding:0; text-align:left;">
                Tangerang, {{ $tanggalSelesai }}<br />
                PT Inti Surya Laboratorium
            </td>
        </tr>
        <tr>
            <td style="padding:18px 0 0 0; text-align:center;">
                <img src="{{ $qr }}" width="80px" height="80px">
            </td>
        </tr>
    </table>
</div>
