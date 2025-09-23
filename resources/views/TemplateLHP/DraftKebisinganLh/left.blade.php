@php
  
@endphp

@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
            <thead>
                <tr>
                    <th width="6%"  class="pd-5-solid-top-center">NO</th>
                    <th width="30%"  class="pd-5-solid-top-center">LOKASI SAMPLING</th>
                    <th width="10%" class="pd-5-solid-top-center">HASIL UJI</th>
                    <th width="30%" class="pd-5-solid-top-center">TITIK KOORDINAT</th>
                    <th width="24%" class="pd-5-solid-top-center">TANGGAL SAMPLING</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detail as $k => $yy)
                    <tr>
                        <td class="pd-5-solid-center">{{ $k + 1 }}</td>
                        <td class="pd-5-solid-left"><sup style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup>
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
