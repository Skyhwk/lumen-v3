@php
  
@endphp

@if (!empty($detail))
    <div class="left">
        <table style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;" width="100%">
            <thead>
                <tr>
                    <th width="6%" rowspan="2" class="custom">NO</th>
                    <th width="30%" rowspan="2" class="custom">LOKASI / KETERANGAN SAMPLE</th>
                    <th width="27%" class="custom"  colspan="3" >Kebisingan 24 Jam (dBA)</th>
                    <th width="20%" rowspan="2" class="custom">TITIK KOORDINAT</th>
                    <th width="17%" rowspan="2" class="custom">TANGGAL SAMPLING</th>
                </tr>
                <tr>
                    <th class="custom" >Ls (Siang)</th>
                    <th class="custom" >Lm (Malam)</th>
                    <th class="custom" >Ls-m (Siang-Malam)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detail as $k => $yy)
       
                    <tr>
                        <td class="custom">{{ $k + 1 }}</td>
                        <td class="custom4"><sup style="font-size: 5px; margin-top: -10px;">{{ $yy['no_sampel'] }}</sup>
                          {{ $yy['lokasi_keterangan'] }}
                        </td>
                        <td class="custom">{{ $yy['leq_ls'] }}</td>
                        <td class="custom">{{ $yy['leq_lm'] }}</td>
                        <td class="custom">{{ $yy['leq_lsm'] }}</td>
                        <td class="custom">{{ $yy['titik_koordinat'] }}</td>
                        <td class="custom">{{ $yy['tanggal_sampling'] }}</td>

                      
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
