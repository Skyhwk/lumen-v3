@php
@endphp

@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif; table-layout: auto; width: 100%;">
            <thead>
                <tr>
                    <th width="6%" class="custom">NO</th>
                    <th width="30%" class="custom">LOKASI / KETERANGAN SAMPEL</th>
                    <th width="10%" class="custom">HASIL UJI (dBA)</th>
                    <th width="30%" class="custom">TITIK KOORDINAT</th>
                    <th width="24%" class="custom">TANGGAL SAMPLING</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detail as $k => $yy)
                    <tr>
                        <td class="pd-5-solid-center">{{ $k + 1 }}</td>
                        <td class="pd-5-solid-left" style="word-wrap: break-word; white-space: normal;">
                            <sup style="font-size: 5px; margin-top: -5px; display: block;">{{ $yy['no_sampel'] }}</sup>
                            {{ $yy['lokasi_keterangan'] }}
                        </td>
                        <td class="pd-5-solid-center">{{ $yy['hasil_uji'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['titik_koordinat'] }}</td>
                        <td class="pd-5-solid-center">{{ $yy['tanggal_sampling'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
